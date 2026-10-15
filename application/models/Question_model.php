<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Question_model extends CI_Model
{
	public function list_for_course($course_id)
	{
		$questions = $this->db->where('course_id', (int) $course_id)->where('visibility', 'shared')->where('target_child_user_id', NULL)->order_by('id', 'ASC')->get('st_questions')->result_array();
		foreach ($questions as &$question)
		{
			$question['options'] = $this->db->where('question_id', $question['id'])->order_by('sort_order', 'ASC')->order_by('id', 'ASC')->get('st_question_options')->result_array();
		}
		unset($question);
		return $questions;
	}

	public function ready_for_review($course_id)
	{
		$questions = $this->list_for_course($course_id);
		if (count($questions) > 50)
		{
			return FALSE;
		}
		foreach ($questions as $question)
		{
			if ($question['question_type'] !== 'single_choice' || trim($question['prompt']) === '' || trim((string) $question['explanation']) === '' || trim((string) $question['source_note']) === '' || ! in_array($question['rights_status'], array('owned', 'licensed', 'public_domain'), TRUE) || count($question['options']) !== 4)
			{
				return FALSE;
			}
			$correct = 0;
			$texts = array();
			foreach ($question['options'] as $option)
			{
				$correct += (int) $option['is_correct'];
				$texts[] = mb_strtolower(trim($option['option_text']), 'UTF-8');
			}
			if ($correct !== 1 || count(array_unique($texts)) !== 4 || in_array('', $texts, TRUE))
			{
				return FALSE;
			}
		}
		return TRUE;
	}

	private function lock_course($course_id, $actor_user_id)
	{
		return $this->db->query(
			"SELECT c.id, c.content_version FROM st_courses c WHERE c.id = ? AND c.status = 'draft'
			AND EXISTS (SELECT 1 FROM st_user_roles ur JOIN st_roles r ON r.id = ur.role_id JOIN st_users u ON u.id = ur.user_id
			WHERE u.id = ? AND u.status = 'active' AND (r.role_code = 'system_admin' OR (r.role_code = 'content_editor' AND c.author_user_id = u.id))) FOR UPDATE",
			array((int) $course_id, (int) $actor_user_id)
		)->row_array();
	}

	public function valid_data($data)
	{
		foreach (array('prompt' => 3000, 'explanation' => 3000, 'source_note' => 1000) as $field => $limit)
		{
			if ( ! isset($data[$field]) || ! is_string($data[$field]) || trim($data[$field]) === '' || mb_strlen($data[$field], 'UTF-8') > $limit)
			{
				return FALSE;
			}
		}
		if ( ! isset($data['rights_status'], $data['correct'], $data['options']) || ! in_array($data['rights_status'], array('owned', 'licensed', 'public_domain'), TRUE) || ! is_int($data['correct']) || $data['correct'] < 0 || $data['correct'] > 3 || ! is_array($data['options']) || array_keys($data['options']) !== array(0, 1, 2, 3))
		{
			return FALSE;
		}
		$texts = array();
		foreach ($data['options'] as $option)
		{
			if ( ! is_string($option) || trim($option) === '' || mb_strlen($option, 'UTF-8') > 1000)
			{
				return FALSE;
			}
			$texts[] = mb_strtolower(trim($option), 'UTF-8');
		}
		return count(array_unique($texts)) === 4;
	}

	private function finish_change($course, $actor_user_id, $question_id, $event)
	{
		$now = gmdate('Y-m-d H:i:s');
		$this->db->where('id', $course['id'])->update('st_courses', array('content_version' => (int) $course['content_version'] + 1, 'updated_by_user_id' => (int) $actor_user_id, 'updated_at' => $now));
		$this->db->insert('st_audit_events', array('actor_user_id' => (int) $actor_user_id, 'event_type' => $event, 'entity_type' => 'question', 'entity_id' => (int) $question_id, 'details_json' => json_encode(array('course_id' => (int) $course['id'])), 'created_at' => $now));
		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$this->db->trans_commit();
		return TRUE;
	}

	public function save_draft($course_id, $actor_user_id, $question_id, $data)
	{
		if ( ! $this->valid_data($data))
		{
			return FALSE;
		}
		$this->db->trans_begin();
		$course = $this->lock_course($course_id, $actor_user_id);
		$question = $question_id ? $this->db->where('id', (int) $question_id)->where('course_id', (int) $course_id)->where('visibility', 'shared')->where('target_child_user_id', NULL)->get('st_questions')->row_array() : NULL;
		$referenced = $question_id && $this->db->where('question_id', (int) $question_id)->count_all_results('st_session_questions') > 0;
		if ( ! $course || ($question_id && ( ! $question || $referenced)) || ( ! $question_id && $this->db->where('course_id', (int) $course_id)->count_all_results('st_questions') >= 50))
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$now = gmdate('Y-m-d H:i:s');
		$row = array('prompt' => trim($data['prompt']), 'explanation' => trim($data['explanation']), 'source_note' => trim($data['source_note']), 'rights_status' => $data['rights_status'], 'status' => 'draft', 'content_version' => $question ? (int) $question['content_version'] + 1 : 1, 'reviewed_by_user_id' => NULL, 'updated_at' => $now);
		if ($question)
		{
			$this->db->where('id', (int) $question_id)->update('st_questions', $row);
			$this->db->where('question_id', (int) $question_id)->delete('st_question_options');
		}
		else
		{
			$row += array('course_id' => (int) $course_id, 'created_by_user_id' => (int) $actor_user_id, 'visibility' => 'shared', 'question_type' => 'single_choice', 'created_at' => $now);
			$this->db->insert('st_questions', $row);
			$question_id = (int) $this->db->insert_id();
		}
		foreach ($data['options'] as $index => $text)
		{
			$this->db->insert('st_question_options', array('question_id' => (int) $question_id, 'option_text' => trim($text), 'is_correct' => $index === $data['correct'] ? 1 : 0, 'sort_order' => $index));
		}
		return $this->finish_change($course, $actor_user_id, $question_id, $question ? 'content.question_updated' : 'content.question_created');
	}

	public function delete_draft($course_id, $actor_user_id, $question_id)
	{
		$this->db->trans_begin();
		$course = $this->lock_course($course_id, $actor_user_id);
		$question = $this->db->where('id', (int) $question_id)->where('course_id', (int) $course_id)->where('visibility', 'shared')->where('target_child_user_id', NULL)->get('st_questions')->row_array();
		if ( ! $course || ! $question || $this->db->where('question_id', (int) $question_id)->count_all_results('st_session_questions') > 0)
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$this->db->where('question_id', (int) $question_id)->delete('st_question_options');
		$this->db->where('id', (int) $question_id)->delete('st_questions');
		return $this->finish_change($course, $actor_user_id, $question_id, 'content.question_deleted');
	}
}
