<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Parent_practice_copy_model extends CI_Model
{
	private function fail($code)
	{
		$this->db->trans_rollback();
		return array('ok' => FALSE, 'code' => $code);
	}

	private function finish($session_id)
	{
		if ($this->db->trans_status() === FALSE)
		{
			return $this->fail(500);
		}
		$this->db->trans_commit();
		return array('ok' => TRUE, 'id' => (int) $session_id);
	}

	public function copy_session($parent_user_id, $source_id, $revision, $child_user_id, $title, $key)
	{
		if ( ! is_string($title) || trim($title) === '' || mb_strlen($title, 'UTF-8') > 180 || ! is_string($key) || ! preg_match('/\A[a-f0-9]{64}\z/', $key))
		{
			return array('ok' => FALSE, 'code' => 400);
		}
		$this->load->model(array('Parent_practice_model', 'Question_model'));
		$this->db->trans_begin();
		// Match draft/delivery lock order, including the per-parent capacity guard.
		$this->db->query('SELECT id FROM st_users WHERE id = ? FOR UPDATE', array((int) $parent_user_id));
		$source = $this->Parent_practice_model->find_session($parent_user_id, $source_id, TRUE);
		$target = $this->db->query("SELECT l.id FROM st_family_links l JOIN st_users c ON c.id = l.child_user_id WHERE l.parent_user_id = ? AND l.child_user_id = ? AND l.status = 'active' AND c.status = 'active' AND c.account_kind = 'child' FOR UPDATE", array((int) $parent_user_id, (int) $child_user_id))->row_array();
		if ( ! $source || ! $target)
		{
			return $this->fail(404);
		}
		if ( ! $this->Parent_practice_model->has_access($parent_user_id, TRUE))
		{
			return $this->fail(403);
		}
		$previous = $this->db->where('learner_user_id', (int) $child_user_id)->where('idempotency_key', $key)->get('st_practice_sessions')->row_array();
		if ($previous)
		{
			return (int) $previous['created_by_user_id'] === (int) $parent_user_id && $previous['session_type'] === 'parent_assigned' ? $this->finish($previous['id']) : $this->fail(409);
		}
		if ((int) $source['draft_revision'] !== $revision || $this->db->where('created_by_user_id', (int) $parent_user_id)->where('session_type', 'parent_assigned')->where('status', 'draft')->count_all_results('st_practice_sessions') >= 100)
		{
			return $this->fail(409);
		}
		$questions = $this->Parent_practice_model->questions($parent_user_id, $source_id);
		if ( ! $questions || count($questions) !== $this->db->where('session_id', (int) $source_id)->count_all_results('st_session_questions'))
		{
			return $this->fail(409);
		}
		// Validate all rows before writing any copy; never propagate a partial session.
		$copies = array();
		foreach ($questions as $question)
		{
			$data = $question;
			$data['options'] = array();
			$data['correct'] = -1;
			$correct_count = 0;
			foreach ($question['options'] as $index => $option)
			{
				if ( ! is_array($option) || ! isset($option['option_text'], $option['is_correct']) || ! in_array($option['is_correct'], array(0, 1, '0', '1'), TRUE))
				{
					return $this->fail(409);
				}
				$data['options'][] = $option['option_text'];
				if ((int) $option['is_correct'] === 1)
				{
					$data['correct'] = $index;
					$correct_count++;
				}
			}
			if ($question['question_type'] !== 'single_choice' || $correct_count !== 1 || ! $this->Question_model->valid_data($data) || $this->db->where('question_id', $question['id'])->count_all_results('st_session_questions') !== 1)
			{
				return $this->fail(409);
			}
			$copies[] = $data;
		}
		$now = gmdate('Y-m-d H:i:s');
		$this->db->insert('st_practice_sessions', array('created_by_user_id' => (int) $parent_user_id, 'learner_user_id' => (int) $child_user_id, 'title' => trim($title), 'session_type' => 'parent_assigned', 'status' => 'draft', 'idempotency_key' => $key, 'rules_version' => 'equal_weight_v1', 'created_at' => $now, 'updated_at' => $now));
		$session_id = (int) $this->db->insert_id();
		foreach ($copies as $index => $data)
		{
			$this->db->insert('st_questions', array('created_by_user_id' => (int) $parent_user_id, 'target_child_user_id' => (int) $child_user_id, 'visibility' => 'family_private', 'question_type' => 'single_choice', 'status' => 'draft', 'prompt' => trim($data['prompt']), 'explanation' => trim($data['explanation']), 'source_note' => trim($data['source_note']), 'rights_status' => $data['rights_status'], 'content_version' => 1, 'created_at' => $now, 'updated_at' => $now));
			$question_id = (int) $this->db->insert_id();
			$this->db->insert('st_session_questions', array('session_id' => $session_id, 'question_id' => $question_id, 'sort_order' => $index + 1));
			foreach ($data['options'] as $option_index => $option)
			{
				$this->db->insert('st_question_options', array('question_id' => $question_id, 'option_text' => trim($option), 'is_correct' => $option_index === $data['correct'] ? 1 : 0, 'sort_order' => $option_index));
			}
		}
		$this->db->insert('st_audit_events', array('actor_user_id' => (int) $parent_user_id, 'event_type' => 'family.practice_copied', 'entity_type' => 'practice_session', 'entity_id' => $session_id, 'details_json' => json_encode(array('source_session_id' => (int) $source_id, 'source_revision' => $revision, 'child_user_id' => (int) $child_user_id)), 'created_at' => $now));
		return $this->finish($session_id);
	}
}
