<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Lesson_media_model extends CI_Model
{
	private function positive_integer($value, $maximum = PHP_INT_MAX)
	{
		return (is_string($value) || is_int($value)) && preg_match('/\A[1-9][0-9]{0,17}\z/', (string) $value) && (int) $value <= $maximum;
	}

	private function valid_text($value, $minimum, $maximum)
	{
		return is_string($value) && mb_check_encoding($value, 'UTF-8') && ! preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) && mb_strlen(trim($value), 'UTF-8') >= $minimum && mb_strlen(trim($value), 'UTF-8') <= $maximum;
	}

	private function valid_metadata($data)
	{
		return is_array($data) && $this->valid_text($data['alt_text'] ?? NULL, 3, 300) && $this->valid_text($data['caption'] ?? NULL, 0, 1000) && $this->valid_text($data['source_note'] ?? NULL, 5, 2000) && in_array($data['rights_status'] ?? NULL, array('owned', 'licensed', 'public_domain'), TRUE);
	}

	private function metadata_matches($previous, $metadata)
	{
		foreach ($metadata as $field => $value)
		{
			if ( ! array_key_exists($field, $previous) || $previous[$field] !== $value) { return FALSE; }
		}
		return TRUE;
	}

	private function valid_image($image)
	{
		if ( ! is_array($image) || ! is_string($image['storage_key'] ?? NULL) || ! preg_match('/\A[a-f0-9]{64}\.(png|jpg)\z/', $image['storage_key']) || ! is_string($image['sha256'] ?? NULL) || ! preg_match('/\A[a-f0-9]{64}\z/', $image['sha256'])) { return FALSE; }
		$expected_mime = substr($image['storage_key'], -4) === '.png' ? 'image/png' : 'image/jpeg';
		return ($image['mime_type'] ?? NULL) === $expected_mime && $this->positive_integer($image['byte_size'] ?? NULL, 6291456) && $this->positive_integer($image['width'] ?? NULL, 4096) && $this->positive_integer($image['height'] ?? NULL, 4096) && (int) $image['width'] * (int) $image['height'] <= 4000000;
	}

	private function read_rows($sql, $parameters)
	{
		$debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		try
		{
			$query = $this->db->query($sql, $parameters);
			return $query === FALSE ? FALSE : $query->result_array();
		}
		catch (Throwable $error)
		{
			log_message('error', 'Lesson image metadata could not be read.');
			return FALSE;
		}
		finally { $this->db->db_debug = $debug; }
	}

	public function get_for_lesson($lesson_id)
	{
		if ( ! $this->positive_integer($lesson_id)) { return NULL; }
		$rows = $this->read_rows('SELECT * FROM st_lesson_images WHERE lesson_id = ?', array((int) $lesson_id));
		return $rows === FALSE ? FALSE : ($rows[0] ?? NULL);
	}

	public function get_for_course($course_id)
	{
		if ( ! $this->positive_integer($course_id)) { return FALSE; }
		$rows = $this->read_rows('SELECT i.* FROM st_lesson_images i JOIN st_lessons l ON l.id = i.lesson_id JOIN st_course_sections s ON s.id = l.section_id WHERE s.course_id = ? ORDER BY i.lesson_id', array((int) $course_id));
		if ($rows === FALSE) { return FALSE; }
		$images = array();
		foreach ($rows as $row) { $images[(int) $row['lesson_id']] = $row; }
		return $images;
	}

	private function stored_image_is_readable($image)
	{
		try
		{
			$this->load->library('Lesson_image_store');
			return is_string($this->lesson_image_store->read($image));
		}
		catch (Throwable $error)
		{
			log_message('error', 'Lesson image storage could not be verified.');
			return FALSE;
		}
	}

	public function ready_for_review($course_id)
	{
		$images = $this->get_for_course($course_id);
		if ($images === FALSE) { return FALSE; }
		foreach ($images as $image)
		{
			if ( ! $this->valid_metadata($image) || ! $this->valid_image($image) || ! $this->stored_image_is_readable($image)) { return FALSE; }
		}
		return TRUE;
	}

	private function reject($status)
	{
		try
		{
			if ( ! $this->db->trans_rollback()) { return array('status' => 'uncertain'); }
		}
		catch (Throwable $error)
		{
			log_message('error', 'Lesson image rollback could not be confirmed.');
			return array('status' => 'uncertain');
		}
		return array('status' => $status);
	}

	public function change($course_id, $lesson_id, $actor_user_id, $revision, $action, $data, $image = NULL)
	{
		if ( ! $this->positive_integer($course_id) || ! $this->positive_integer($lesson_id) || ! $this->positive_integer($actor_user_id) || ! $this->positive_integer($revision, 2147483647) || ! in_array($action, array('upload', 'metadata', 'remove'), TRUE) || ! is_array($data)) { return array('status' => 'invalid'); }
		if ($action !== 'remove' && ! $this->valid_metadata($data)) { return array('status' => 'invalid'); }
		if ($action === 'upload' && ! $this->valid_image($image)) { return array('status' => 'invalid'); }
		$debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$started = FALSE;
		$committing = FALSE;
		try
		{
			if ( ! $this->db->trans_begin()) { return array('status' => 'unavailable'); }
			$started = TRUE;
			$result = $this->change_locked($course_id, $lesson_id, $actor_user_id, $revision, $action, $data, $image);
			if ($result['status'] !== 'ready') { return $this->reject($result['status']); }
			if ($this->db->trans_status() === FALSE) { return $this->reject('unavailable'); }
			$committing = TRUE;
			if ( ! $this->db->trans_commit()) { return $this->reject('uncertain'); }
			return array('status' => 'ok', 'previous_key' => $result['previous_key']);
		}
		catch (Throwable $error)
		{
			// Never include SQL, paths, source notes or driver details in this log.
			log_message('error', 'Lesson image transaction failed; inspect database availability.');
			return $started ? $this->reject($committing ? 'uncertain' : 'unavailable') : array('status' => 'unavailable');
		}
		finally { $this->db->db_debug = $debug; }
	}

	private function change_locked($course_id, $lesson_id, $actor_user_id, $revision, $action, $data, $image)
	{
		$course_query = $this->db->query('SELECT id, status, author_user_id, content_version FROM st_courses WHERE id = ? FOR UPDATE', array((int) $course_id));
		if ($course_query === FALSE) { return array('status' => 'unavailable'); }
		$course = $course_query->row_array();
		$actor_query = $this->db->query('SELECT id, status FROM st_users WHERE id = ? FOR UPDATE', array((int) $actor_user_id));
		if ($actor_query === FALSE) { return array('status' => 'unavailable'); }
		$actor = $actor_query->row_array();
		$roles_query = $this->db->query('SELECT r.role_code FROM st_user_roles ur JOIN st_roles r ON r.id = ur.role_id WHERE ur.user_id = ? FOR UPDATE', array((int) $actor_user_id));
		if ($roles_query === FALSE) { return array('status' => 'unavailable'); }
		$roles = array_column($roles_query->result_array(), 'role_code');
		$is_admin = in_array('system_admin', $roles, TRUE);
		if ( ! $course || ! $actor || $actor['status'] !== 'active' || $course['status'] !== 'draft' || ( ! $is_admin && ( ! in_array('content_editor', $roles, TRUE) || (int) $course['author_user_id'] !== (int) $actor_user_id))) { return array('status' => 'denied'); }
		$lesson_query = $this->db->query('SELECT l.id, l.content_version FROM st_lessons l JOIN st_course_sections s ON s.id = l.section_id WHERE l.id = ? AND s.course_id = ? FOR UPDATE', array((int) $lesson_id, (int) $course_id));
		if ($lesson_query === FALSE) { return array('status' => 'unavailable'); }
		$lesson = $lesson_query->row_array();
		if ( ! $lesson) { return array('status' => 'invalid'); }
		if ((int) $lesson['content_version'] !== (int) $revision || (int) $revision >= 2147483647 || (int) $course['content_version'] >= 2147483647) { return array('status' => 'conflict'); }
		$image_query = $this->db->query('SELECT * FROM st_lesson_images WHERE lesson_id = ? FOR UPDATE', array((int) $lesson_id));
		if ($image_query === FALSE) { return array('status' => 'unavailable'); }
		$previous = $image_query->row_array();
		$metadata = array();
		if ($action !== 'remove')
		{
			foreach (array('alt_text', 'caption', 'source_note', 'rights_status') as $field) { $metadata[$field] = trim($data[$field]); }
		}
		if ($action === 'metadata' && ! $previous) { return array('status' => 'invalid'); }
		if ($action === 'remove' && ! $previous) { return array('status' => 'no_change'); }
		if ($action === 'metadata' && $this->metadata_matches($previous, $metadata)) { return array('status' => 'no_change'); }
		if ($action === 'upload' && ! $this->stored_image_is_readable($image)) { return array('status' => 'unavailable'); }
		if ($action === 'upload' && $previous && $previous['storage_key'] === $image['storage_key'] && $this->metadata_matches($previous, $metadata)) { return array('status' => 'no_change'); }
		$now = gmdate('Y-m-d H:i:s');
		if ($action === 'remove')
		{
			if ( ! $this->db->where('id', (int) $previous['id'])->delete('st_lesson_images')) { return array('status' => 'unavailable'); }
		}
		else
		{
			$values = $metadata + array('updated_at' => $now);
			if ($action === 'upload')
			{
				foreach (array('storage_key', 'mime_type', 'byte_size', 'width', 'height', 'sha256') as $field) { $values[$field] = $image[$field]; }
				$values['uploader_user_id'] = (int) $actor_user_id;
			}
			if ($previous)
			{
				if ( ! $this->db->where('id', (int) $previous['id'])->update('st_lesson_images', $values)) { return array('status' => 'unavailable'); }
			}
			elseif ( ! $this->db->insert('st_lesson_images', $values + array('lesson_id' => (int) $lesson_id, 'created_at' => $now))) { return array('status' => 'unavailable'); }
		}
		if ( ! $this->db->where('id', (int) $lesson_id)->update('st_lessons', array('content_version' => (int) $revision + 1, 'updated_at' => $now))) { return array('status' => 'unavailable'); }
		if ( ! $this->db->where('id', (int) $course_id)->update('st_courses', array('content_version' => (int) $course['content_version'] + 1, 'updated_by_user_id' => (int) $actor_user_id, 'updated_at' => $now))) { return array('status' => 'unavailable'); }
		$details = array('action' => $action, 'course_id' => (int) $course_id, 'lesson_id' => (int) $lesson_id, 'previous_version' => (int) $revision, 'next_version' => (int) $revision + 1, 'previous_course_version' => (int) $course['content_version'], 'next_course_version' => (int) $course['content_version'] + 1);
		if ( ! $this->db->insert('st_audit_events', array('actor_user_id' => (int) $actor_user_id, 'event_type' => 'content.lesson_image_changed', 'entity_type' => 'lesson', 'entity_id' => (int) $lesson_id, 'correlation_id' => NULL, 'details_json' => json_encode($details), 'created_at' => $now))) { return array('status' => 'unavailable'); }
		$previous_key = $previous && ($action === 'remove' || ($action === 'upload' && $previous['storage_key'] !== $image['storage_key'])) ? $previous['storage_key'] : NULL;
		return array('status' => 'ready', 'previous_key' => $previous_key);
	}
}
