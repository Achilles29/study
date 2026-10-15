<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Course_structure_model extends CI_Model
{
	private function positive_integer($value, $maximum = PHP_INT_MAX)
	{
		return (is_string($value) || is_int($value)) && preg_match('/\A[1-9][0-9]{0,17}\z/', (string) $value) && (int) $value <= $maximum;
	}

	public function sections($course_id)
	{
		return $this->db->where('course_id', (int) $course_id)->order_by('sort_order', 'ASC')->order_by('id', 'ASC')->get('st_course_sections')->result_array();
	}

	public function lesson_count($course_id)
	{
		return $this->db->from('st_lessons l')->join('st_course_sections s', 's.id = l.section_id')->where('s.course_id', (int) $course_id)->count_all_results();
	}

	public function lessons($course_id, $offset = 0)
	{
		return $this->db->select('l.id, l.title, l.section_id, l.sort_order, s.title AS section_title')->from('st_lessons l')->join('st_course_sections s', 's.id = l.section_id')->where('s.course_id', (int) $course_id)->order_by('s.sort_order', 'ASC')->order_by('s.id', 'ASC')->order_by('l.sort_order', 'ASC')->order_by('l.id', 'ASC')->limit(10, max(0, (int) $offset))->get()->result_array();
	}

	private function reject($status)
	{
		try
		{
			if ( ! $this->db->trans_rollback()) { return 'uncertain'; }
		}
		catch (Throwable $error)
		{
			log_message('error', 'Course structure rollback could not be confirmed.');
			return 'uncertain';
		}
		return $status;
	}

	public function change($course_id, $actor_user_id, $revision, $action, $data)
	{
		if ( ! $this->positive_integer($course_id) || ! $this->positive_integer($actor_user_id) || ! $this->positive_integer($revision, 2147483647) || ! in_array($action, array('create_section', 'update_section', 'move_lesson'), TRUE) || ! is_array($data) || ! $this->positive_integer($data['sort_order'] ?? NULL, 9999))
		{
			return 'invalid';
		}
		if ($action !== 'create_section' && ! $this->positive_integer($data['section_id'] ?? NULL)) { return 'invalid'; }
		if ($action === 'move_lesson' && ! $this->positive_integer($data['lesson_id'] ?? NULL)) { return 'invalid'; }
		if ($action !== 'move_lesson' && ( ! is_string($data['title'] ?? NULL) || ! mb_check_encoding($data['title'], 'UTF-8') || mb_strlen(trim($data['title']), 'UTF-8') < 3 || mb_strlen(trim($data['title']), 'UTF-8') > 180)) { return 'invalid'; }

		$debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$started = FALSE;
		$committing = FALSE;
		try
		{
			if ( ! $this->db->trans_begin()) { return 'unavailable'; }
			$started = TRUE;
			$result = $this->change_locked($course_id, $actor_user_id, $revision, $action, $data);
			if ($result !== 'ready') { return $this->reject($result); }
			if ($this->db->trans_status() === FALSE) { return $this->reject('unavailable'); }
			$committing = TRUE;
			if ( ! $this->db->trans_commit()) { return $this->reject('uncertain'); }
			return 'ok';
		}
		catch (Throwable $error)
		{
			// Do not log SQL, lesson text or driver exception details here.
			log_message('error', 'Course structure transaction failed; inspect database availability.');
			return $started ? $this->reject($committing ? 'uncertain' : 'unavailable') : 'unavailable';
		}
		finally { $this->db->db_debug = $debug; }
	}

	private function change_locked($course_id, $actor_user_id, $revision, $action, $data)
	{
		$course_query = $this->db->query('SELECT id, status, author_user_id, content_version FROM st_courses WHERE id = ? FOR UPDATE', array((int) $course_id));
		if ($course_query === FALSE) { return 'unavailable'; }
		$course = $course_query->row_array();
		$actor_query = $this->db->query('SELECT id, status FROM st_users WHERE id = ? FOR UPDATE', array((int) $actor_user_id));
		if ($actor_query === FALSE) { return 'unavailable'; }
		$actor = $actor_query->row_array();
		$roles_query = $this->db->query('SELECT r.role_code FROM st_user_roles ur JOIN st_roles r ON r.id = ur.role_id WHERE ur.user_id = ?', array((int) $actor_user_id));
		if ($roles_query === FALSE) { return 'unavailable'; }
		$roles = $roles_query->result_array();
		$role_codes = array_column($roles, 'role_code');
		$is_admin = in_array('system_admin', $role_codes, TRUE);
		if ( ! $course || ! $actor || $actor['status'] !== 'active' || $course['status'] !== 'draft' || ( ! $is_admin && ( ! in_array('content_editor', $role_codes, TRUE) || (int) $course['author_user_id'] !== (int) $actor_user_id))) { return 'denied'; }
		if ((int) $course['content_version'] !== (int) $revision || (int) $revision >= 2147483647) { return 'conflict'; }
		$now = gmdate('Y-m-d H:i:s');
		$details = array('action' => $action, 'previous_version' => (int) $revision, 'next_version' => (int) $revision + 1, 'sort_order' => (int) $data['sort_order']);
		if ($action === 'create_section')
		{
			$count_query = $this->db->query('SELECT COUNT(*) AS total FROM st_course_sections WHERE course_id = ?', array((int) $course_id));
			if ($count_query === FALSE) { return 'unavailable'; }
			if ((int) $count_query->row_array()['total'] >= 100) { return 'limit'; }
			if ( ! $this->db->insert('st_course_sections', array('course_id' => (int) $course_id, 'title' => trim($data['title']), 'sort_order' => (int) $data['sort_order'], 'created_at' => $now, 'updated_at' => $now))) { return 'unavailable'; }
			$details['section_id'] = (int) $this->db->insert_id();
		}
		else
		{
			$section_query = $this->db->query('SELECT id, title, sort_order FROM st_course_sections WHERE course_id = ? AND id = ?', array((int) $course_id, (int) $data['section_id']));
			if ($section_query === FALSE) { return 'unavailable'; }
			$section = $section_query->row_array();
			if ( ! $section) { return 'invalid'; }
			$details['section_id'] = (int) $section['id'];
			if ($action === 'update_section')
			{
				if ($section['title'] === trim($data['title']) && (int) $section['sort_order'] === (int) $data['sort_order']) { return 'no_change'; }
				$details['previous_sort_order'] = (int) $section['sort_order'];
				if ( ! $this->db->where('id', (int) $section['id'])->update('st_course_sections', array('title' => trim($data['title']), 'sort_order' => (int) $data['sort_order'], 'updated_at' => $now))) { return 'unavailable'; }
			}
			else
			{
				$lesson_query = $this->db->query('SELECT l.id, l.section_id, l.sort_order, l.content_version FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id WHERE l.id=? AND s.course_id=? FOR UPDATE', array((int) $data['lesson_id'], (int) $course_id));
				if ($lesson_query === FALSE) { return 'unavailable'; }
				$lesson = $lesson_query->row_array();
				if ( ! $lesson) { return 'invalid'; }
				if ((int) $lesson['section_id'] === (int) $section['id'] && (int) $lesson['sort_order'] === (int) $data['sort_order']) { return 'no_change'; }
				if ((int) $lesson['content_version'] >= 2147483647) { return 'conflict'; }
				$details += array('lesson_id' => (int) $lesson['id'], 'previous_section_id' => (int) $lesson['section_id'], 'previous_sort_order' => (int) $lesson['sort_order']);
				if ( ! $this->db->where('id', (int) $lesson['id'])->update('st_lessons', array('section_id' => (int) $section['id'], 'sort_order' => (int) $data['sort_order'], 'content_version' => (int) $lesson['content_version'] + 1, 'updated_at' => $now))) { return 'unavailable'; }
			}
		}
		if ( ! $this->db->where('id', (int) $course_id)->update('st_courses', array('content_version' => (int) $revision + 1, 'updated_by_user_id' => (int) $actor_user_id, 'updated_at' => $now))) { return 'unavailable'; }
		if ( ! $this->db->insert('st_audit_events', array('actor_user_id' => (int) $actor_user_id, 'event_type' => 'content.structure_changed', 'entity_type' => 'course', 'entity_id' => (int) $course_id, 'correlation_id' => NULL, 'details_json' => json_encode($details), 'created_at' => $now))) { return 'unavailable'; }
		return 'ready';
	}
}
