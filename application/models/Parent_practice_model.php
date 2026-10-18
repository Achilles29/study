<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Parent_practice_model extends CI_Model
{
	public function has_access($parent_user_id, $lock = FALSE)
	{
		$this->load->model('Commerce_access_model');
		if ($this->Commerce_access_model->family_practice($parent_user_id, $lock)) { return TRUE; }
		$sql = "SELECT e.id FROM st_entitlements e JOIN st_packages p ON p.id = e.package_id JOIN st_users u ON u.id = e.user_id
			WHERE e.user_id = ? AND u.account_kind = 'parent' AND u.status = 'active' AND e.status = 'active'
			AND e.starts_at <= UTC_TIMESTAMP() AND (e.ends_at IS NULL OR e.ends_at > UTC_TIMESTAMP()) AND p.family_practice_enabled = 1
			ORDER BY e.id LIMIT 1";
		return $this->db->query($sql.($lock ? ' FOR UPDATE' : ''), array((int) $parent_user_id))->num_rows() === 1;
	}

	private function ownership_sql()
	{
		return " FROM st_practice_sessions s JOIN st_family_links l ON l.parent_user_id = s.created_by_user_id AND l.child_user_id = s.learner_user_id AND l.status = 'active'
			JOIN st_users c ON c.id = l.child_user_id AND c.account_kind = 'child' AND c.status = 'active'
			JOIN st_users p ON p.id = l.parent_user_id AND p.account_kind = 'parent' AND p.status = 'active'
			WHERE s.session_type = 'parent_assigned' AND s.created_by_user_id = ?";
	}

	public function find_session($parent_user_id, $session_id, $lock = FALSE)
	{
		return $this->db->query('SELECT s.*, c.display_name AS child_name'.$this->ownership_sql().' AND s.id = ?'.($lock ? ' FOR UPDATE' : ''), array((int) $parent_user_id, (int) $session_id))->row_array();
	}

	private function filtered_scope($parent_user_id, $filters)
	{
		$sql = $this->ownership_sql();
		$params = array((int) $parent_user_id);
		if (isset($filters['q']) && $filters['q'] !== '')
		{
			$sql .= " AND s.title LIKE ? ESCAPE '!'";
			$params[] = '%'.$this->db->escape_like_str($filters['q']).'%';
		}
		if ( ! empty($filters['child_id']))
		{
			$sql .= ' AND s.learner_user_id = ?';
			$params[] = (int) $filters['child_id'];
		}
		if ( ! empty($filters['status']))
		{
			$now = isset($filters['now']) ? $filters['now'] : gmdate('Y-m-d H:i:s');
			$sql .= " AND (CASE WHEN s.status IN ('available', 'in_progress') AND s.available_until <= ? THEN 'expired'
				WHEN s.status IN ('available', 'in_progress') AND s.available_from > ? THEN 'scheduled' ELSE s.status END) = ?";
			$params[] = $now;
			$params[] = $now;
			$params[] = $filters['status'];
		}
		return array($sql, $params);
	}

	public function count_sessions($parent_user_id, $filters = array())
	{
		list($sql, $params) = $this->filtered_scope($parent_user_id, $filters);
		return (int) $this->db->query('SELECT COUNT(*) AS total'.$sql, $params)->row()->total;
	}

	public function deliver($parent_user_id, $session_id, $revision, $schedule)
	{
		$this->db->trans_begin();
		$this->db->query('SELECT id FROM st_users WHERE id = ? FOR UPDATE', array((int) $parent_user_id));
		$session = $this->find_session($parent_user_id, $session_id, TRUE);
		if ( ! $session)
		{
			return $this->fail(404);
		}
		if ($session['status'] !== 'draft')
		{
			return in_array($session['status'], array('available', 'in_progress', 'completed'), TRUE) && (int) $session['draft_revision'] === $revision + 1 ? $this->commit($parent_user_id, $session_id, NULL) : $this->fail(409);
		}
		if ((int) $session['draft_revision'] !== $revision)
		{
			return $this->fail(409);
		}
		if ( ! $this->has_access($parent_user_id, TRUE))
		{
			return $this->fail(403);
		}
		if ( ! is_array($schedule) || ! isset($schedule['available_from'], $schedule['available_until']) || $schedule['available_until'] <= gmdate('Y-m-d H:i:s') || $schedule['available_until'] <= $schedule['available_from'])
		{
			return $this->fail(400);
		}
		$this->load->model('Question_model');
		$questions = $this->questions($parent_user_id, $session_id);
		if ( ! $questions || count($questions) !== $this->db->where('session_id', (int) $session_id)->count_all_results('st_session_questions') || $this->db->where('session_id', (int) $session_id)->count_all_results('st_practice_attempts') > 0)
		{
			return $this->fail(409);
		}
		foreach ($questions as $question)
		{
			$data = $question;
			$data['correct'] = -1;
			$data['options'] = array();
			$correct_count = 0;
			foreach ($question['options'] as $index => $option)
			{
				$data['options'][] = $option['option_text'];
				if ((int) $option['is_correct'] === 1)
				{
					$data['correct'] = $index;
					$correct_count++;
				}
			}
			if ($question['status'] !== 'draft' || $question['question_type'] !== 'single_choice' || $correct_count !== 1 || ! $this->Question_model->valid_data($data) || $this->db->where('question_id', $question['id'])->count_all_results('st_session_questions') !== 1)
			{
				return $this->fail(409);
			}
			$snapshot = array('prompt' => $question['prompt'], 'explanation' => $question['explanation'], 'content_version' => (int) $question['content_version'], 'options' => $question['options'], 'source_note' => $question['source_note'], 'rights_status' => $question['rights_status']);
			$this->db->where('session_id', (int) $session_id)->where('question_id', $question['id'])->update('st_session_questions', array('snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)));
		}
		$this->db->where('id', (int) $session_id)->update('st_practice_sessions', array('status' => 'available', 'available_from' => $schedule['available_from'], 'available_until' => $schedule['available_until'], 'draft_revision' => $revision + 1, 'updated_at' => gmdate('Y-m-d H:i:s')));
		return $this->commit($parent_user_id, $session_id, 'family.practice_delivered');
	}

	public function withdraw($parent_user_id, $session_id, $revision)
	{
		$this->db->trans_begin();
		$this->db->query('SELECT id FROM st_users WHERE id = ? FOR UPDATE', array((int) $parent_user_id));
		$session = $this->find_session($parent_user_id, $session_id, TRUE);
		if ( ! $session)
		{
			return $this->fail(404);
		}
		if ($session['status'] === 'cancelled' && (int) $session['draft_revision'] === $revision + 1)
		{
			return $this->commit($parent_user_id, $session_id, NULL);
		}
		if ( ! in_array($session['status'], array('available', 'in_progress'), TRUE) || (int) $session['draft_revision'] !== $revision)
		{
			return $this->fail(409);
		}
		$this->db->where('session_id', (int) $session_id)->where('status', 'in_progress')->update('st_practice_attempts', array('status' => 'abandoned'));
		$this->db->where('id', (int) $session_id)->update('st_practice_sessions', array('status' => 'cancelled', 'draft_revision' => $revision + 1, 'updated_at' => gmdate('Y-m-d H:i:s')));
		return $this->commit($parent_user_id, $session_id, 'family.practice_withdrawn');
	}

	public function sessions($parent_user_id, $offset, $filters = array())
	{
		list($sql, $params) = $this->filtered_scope($parent_user_id, $filters);
		$params[] = max(0, (int) $offset);
		return $this->db->query('SELECT s.id, s.title, s.status, s.updated_at, s.available_from, s.available_until, c.display_name AS child_name'.$sql.' ORDER BY s.id DESC LIMIT 10 OFFSET ?', $params)->result_array();
	}

	public function questions($parent_user_id, $session_id)
	{
		$session = $this->find_session($parent_user_id, $session_id);
		if ( ! $session)
		{
			return array();
		}
		$rows = $this->db->query("SELECT q.*, sq.snapshot_json FROM st_session_questions sq JOIN st_questions q ON q.id = sq.question_id
			WHERE sq.session_id = ? AND q.created_by_user_id = ? AND q.target_child_user_id = ? AND q.visibility = 'family_private' AND q.course_id IS NULL ORDER BY sq.sort_order, q.id LIMIT 50",
			array((int) $session_id, (int) $parent_user_id, (int) $session['learner_user_id']))->result_array();
		foreach ($rows as &$row)
		{
			if ($session['available_from'])
			{
				$snapshot = json_decode($row['snapshot_json'], TRUE);
				if ( ! is_array($snapshot) || ! isset($snapshot['prompt'], $snapshot['explanation'], $snapshot['content_version'], $snapshot['options']) || ! is_array($snapshot['options']) || count($snapshot['options']) !== 4)
				{
					return array();
				}
				foreach (array('prompt', 'explanation', 'content_version', 'options') as $field)
				{
					$row[$field] = $snapshot[$field];
				}
				// Older snapshots retain immutable source metadata on their private question.
				foreach (array('source_note', 'rights_status') as $field)
				{
					if (array_key_exists($field, $snapshot))
					{
						$row[$field] = $snapshot[$field];
					}
				}
			}
			else
			{
				$row['options'] = $this->db->where('question_id', $row['id'])->order_by('sort_order')->get('st_question_options')->result_array();
			}
			unset($row['snapshot_json']);
		}
		unset($row);
		return $rows;
	}

	private function fail($code)
	{
		$this->db->trans_rollback();
		return array('ok' => FALSE, 'code' => $code);
	}

	private function commit($parent_user_id, $session_id, $event)
	{
		if ($event !== NULL)
		{
			$this->db->insert('st_audit_events', array('actor_user_id' => (int) $parent_user_id, 'event_type' => $event, 'entity_type' => 'practice_session', 'entity_id' => (int) $session_id, 'details_json' => '{}', 'created_at' => gmdate('Y-m-d H:i:s')));
		}
		if ($this->db->trans_status() === FALSE)
		{
			return $this->fail(500);
		}
		$this->db->trans_commit();
		return array('ok' => TRUE, 'id' => (int) $session_id);
	}

	public function create_draft($parent_user_id, $child_user_id, $title, $key)
	{
		if ( ! is_string($title) || trim($title) === '' || mb_strlen($title, 'UTF-8') > 180 || ! is_string($key) || ! preg_match('/\A[a-f0-9]{64}\z/', $key))
		{
			return array('ok' => FALSE, 'code' => 400);
		}
		$this->db->trans_begin();
		// Serialize drafts per parent before checking limits, entitlement and replay keys.
		$parent = $this->db->query("SELECT id FROM st_users WHERE id = ? AND account_kind = 'parent' AND status = 'active' FOR UPDATE", array((int) $parent_user_id))->row_array();
		$child = $this->db->query("SELECT l.id FROM st_family_links l JOIN st_users c ON c.id = l.child_user_id WHERE l.parent_user_id = ? AND l.child_user_id = ? AND l.status = 'active' AND c.status = 'active' AND c.account_kind = 'child' FOR UPDATE", array((int) $parent_user_id, (int) $child_user_id))->row_array();
		if ( ! $parent || ! $child)
		{
			return $this->fail(404);
		}
		if ( ! $this->has_access($parent_user_id, TRUE))
		{
			return $this->fail(403);
		}
		$previous = $this->db->where('learner_user_id', (int) $child_user_id)->where('idempotency_key', $key)->get('st_practice_sessions')->row_array();
		if ($previous)
		{
			return (int) $previous['created_by_user_id'] === (int) $parent_user_id && $previous['session_type'] === 'parent_assigned' ? $this->commit($parent_user_id, $previous['id'], NULL) : $this->fail(409);
		}
		if ($this->db->where('created_by_user_id', (int) $parent_user_id)->where('session_type', 'parent_assigned')->where('status', 'draft')->count_all_results('st_practice_sessions') >= 100)
		{
			return $this->fail(409);
		}
		$now = gmdate('Y-m-d H:i:s');
		$this->db->insert('st_practice_sessions', array('created_by_user_id' => (int) $parent_user_id, 'learner_user_id' => (int) $child_user_id, 'title' => trim($title), 'session_type' => 'parent_assigned', 'status' => 'draft', 'idempotency_key' => $key, 'rules_version' => 'equal_weight_v1', 'created_at' => $now, 'updated_at' => $now));
		return $this->commit($parent_user_id, $this->db->insert_id(), 'family.practice_created');
	}

	public function change_draft($parent_user_id, $session_id, $revision, $action, $question_id, $data)
	{
		$this->db->trans_begin();
		$this->db->query('SELECT id FROM st_users WHERE id = ? FOR UPDATE', array((int) $parent_user_id));
		$session = $this->find_session($parent_user_id, $session_id, TRUE);
		if ( ! $session)
		{
			return $this->fail(404);
		}
		if ($session['status'] !== 'draft' || (int) $session['draft_revision'] !== $revision || $this->db->where('session_id', (int) $session_id)->count_all_results('st_practice_attempts') > 0)
		{
			return $this->fail(409);
		}
		if ($action !== 'cancel' && ! $this->has_access($parent_user_id, TRUE))
		{
			return $this->fail(403);
		}
		$changes = array('draft_revision' => $revision + 1, 'updated_at' => gmdate('Y-m-d H:i:s'));
		if ($action === 'title')
		{
			if ( ! isset($data['title']) || ! is_string($data['title']) || trim($data['title']) === '' || mb_strlen($data['title'], 'UTF-8') > 180)
			{
				return $this->fail(400);
			}
			$changes['title'] = trim($data['title']);
		}
		elseif ($action === 'cancel')
		{
			$changes['status'] = 'cancelled';
		}
		elseif (in_array($action, array('save', 'delete'), TRUE))
		{
			$questions = $this->questions($parent_user_id, $session_id);
			if ($this->db->where('session_id', (int) $session_id)->count_all_results('st_session_questions') !== count($questions))
			{
				return $this->fail(409);
			}
			$selected = NULL;
			foreach ($questions as $question)
			{
				if ((int) $question['id'] === $question_id)
				{
					$selected = $question;
				}
			}
			if (($question_id > 0 && ! $selected) || ($action === 'delete' && ! $selected))
			{
				return $this->fail(404);
			}
			// A draft owns its questions exclusively; never edit another session's source.
			if ($selected && $this->db->where('question_id', $question_id)->count_all_results('st_session_questions') !== 1)
			{
				return $this->fail(409);
			}
			if ($action === 'delete')
			{
				$this->db->where('session_id', (int) $session_id)->where('question_id', $question_id)->delete('st_session_questions');
				$this->db->where('question_id', $question_id)->delete('st_question_options');
				$this->db->where('id', $question_id)->delete('st_questions');
			}
			else
			{
				$this->load->model('Question_model');
				if ( ! $this->Question_model->valid_data($data) || ( ! $selected && count($questions) >= 50))
				{
					return $this->fail(400);
				}
				$row = array('prompt' => trim($data['prompt']), 'explanation' => trim($data['explanation']), 'source_note' => trim($data['source_note']), 'rights_status' => $data['rights_status'], 'content_version' => $selected ? (int) $selected['content_version'] + 1 : 1, 'updated_at' => $changes['updated_at']);
				if ($selected)
				{
					$this->db->where('id', $question_id)->update('st_questions', $row);
					$this->db->where('question_id', $question_id)->delete('st_question_options');
				}
				else
				{
					$row += array('created_by_user_id' => (int) $parent_user_id, 'target_child_user_id' => (int) $session['learner_user_id'], 'visibility' => 'family_private', 'question_type' => 'single_choice', 'status' => 'draft', 'created_at' => $changes['updated_at']);
					$this->db->insert('st_questions', $row);
					$question_id = (int) $this->db->insert_id();
					$sort_order = (int) $this->db->select_max('sort_order')->where('session_id', (int) $session_id)->get('st_session_questions')->row()->sort_order + 1;
					$this->db->insert('st_session_questions', array('session_id' => (int) $session_id, 'question_id' => $question_id, 'sort_order' => $sort_order));
				}
				foreach ($data['options'] as $index => $option)
				{
					$this->db->insert('st_question_options', array('question_id' => $question_id, 'option_text' => trim($option), 'is_correct' => $index === $data['correct'] ? 1 : 0, 'sort_order' => $index));
				}
			}
		}
		else
		{
			return $this->fail(400);
		}
		$this->db->where('id', (int) $session_id)->update('st_practice_sessions', $changes);
		return $this->commit($parent_user_id, $session_id, 'family.practice_'.$action);
	}
}
