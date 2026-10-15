<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Assignment_model extends CI_Model
{
	private function scope()
	{
		return " FROM st_practice_sessions s JOIN st_family_links l ON l.parent_user_id = s.created_by_user_id AND l.child_user_id = s.learner_user_id AND l.status = 'active'
			JOIN st_users p ON p.id = s.created_by_user_id AND p.account_kind = 'parent' AND p.status = 'active'
			JOIN st_users c ON c.id = s.learner_user_id AND c.account_kind = 'child' AND c.status = 'active'
			WHERE s.session_type = 'parent_assigned' AND s.status <> 'draft' AND s.learner_user_id = ?";
	}

	public function child_session($session_id, $user_id, $lock = FALSE)
	{
		$query = $this->db->query('SELECT s.*'.$this->scope().' AND s.id = ?'.($lock ? ' FOR UPDATE' : ''), array((int) $user_id, (int) $session_id));
		if ($query === FALSE) { throw new RuntimeException('Assignment session could not be read.'); }
		return $query->row_array();
	}

	public function count_inbox($user_id)
	{
		return (int) $this->db->query('SELECT COUNT(*) AS total'.$this->scope(), array((int) $user_id))->row()->total;
	}

	public function inbox($user_id, $offset)
	{
		return $this->db->query('SELECT s.id, s.title, s.status, s.available_from, s.available_until'.$this->scope().' ORDER BY s.id DESC LIMIT 10 OFFSET ?', array((int) $user_id, max(0, (int) $offset)))->result_array();
	}

	public function can_work($session, $lock = FALSE)
	{
		if ( ! $session || ! in_array($session['status'], array('available', 'in_progress'), TRUE) || ! $session['available_from'] || ! $session['available_until'] || $session['available_from'] > gmdate('Y-m-d H:i:s') || $session['available_until'] <= gmdate('Y-m-d H:i:s'))
		{
			return FALSE;
		}
		$this->load->model('Parent_practice_model');
		return $this->Parent_practice_model->has_access($session['created_by_user_id'], $lock);
	}

	public function lock_for_child($session_id, $user_id, $parent_user_id)
	{
		if ($this->db->query('SELECT id FROM st_users WHERE id = ? FOR UPDATE', array((int) $parent_user_id)) === FALSE)
		{
			throw new RuntimeException('Assignment parent could not be locked.');
		}
		return $this->child_session($session_id, $user_id, TRUE);
	}

	public function start($session_id, $user_id)
	{
		$this->load->library('Study_transaction');
		return $this->study_transaction->run($this->db, function () use ($session_id, $user_id) {
			$initial = $this->child_session($session_id, $user_id);
			if ( ! $initial)
			{
				return array('ok' => FALSE, 'code' => 404);
			}
			$session = $this->lock_for_child($session_id, $user_id, $initial['created_by_user_id']);
			if ( ! $session)
			{
				return array('ok' => FALSE, 'code' => 404);
			}
			// Use current reads after the family lock, not the earlier consistent-read snapshot.
			$query = $this->db->query('SELECT * FROM st_practice_attempts WHERE session_id = ? AND learner_user_id = ? FOR UPDATE', array((int) $session_id, (int) $user_id));
			if ($query === FALSE) { throw new RuntimeException('Assignment attempt could not be read.'); }
			$attempt = $query->row_array();
			if ( ! ($attempt && $attempt['status'] === 'graded') && ! $this->can_work($session, TRUE))
			{
				return array('ok' => FALSE, 'code' => 403);
			}
			if ( ! $attempt)
			{
				$query = $this->db->query('SELECT * FROM st_session_questions WHERE session_id = ? FOR UPDATE', array((int) $session_id));
				if ($query === FALSE) { throw new RuntimeException('Assignment snapshots could not be read.'); }
				$rows = $query->result_array();
				if (count($rows) < 1 || count($rows) > 50 || in_array(NULL, array_column($rows, 'snapshot_json'), TRUE))
				{
					return array('ok' => FALSE, 'code' => 409);
				}
				$now = gmdate('Y-m-d H:i:s');
				if ($this->db->insert('st_practice_attempts', array('session_id' => (int) $session_id, 'learner_user_id' => (int) $user_id, 'status' => 'in_progress', 'question_count' => count($rows), 'started_at' => $now)) === FALSE)
				{
					throw new RuntimeException('Assignment attempt could not be created.');
				}
				$attempt = array('id' => (int) $this->db->insert_id(), 'status' => 'in_progress');
				if ($attempt['id'] < 1) { throw new RuntimeException('Assignment attempt identity is unavailable.'); }
				if ($this->db->where('id', (int) $session_id)->update('st_practice_sessions', array('status' => 'in_progress', 'updated_at' => $now)) === FALSE)
				{
					throw new RuntimeException('Assignment session could not be updated.');
				}
				if ($this->db->insert('st_audit_events', array('actor_user_id' => (int) $user_id, 'event_type' => 'practice.started', 'entity_type' => 'practice_attempt', 'entity_id' => $attempt['id'], 'details_json' => '{}', 'created_at' => $now)) === FALSE)
				{
					throw new RuntimeException('Assignment start audit could not be recorded.');
				}
			}
			return array('ok' => TRUE, 'id' => (int) $attempt['id'], 'status' => $attempt['status']);
		});
	}
}
