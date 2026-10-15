<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Matching_run_model extends CI_Model
{
	private function key_valid($key)
	{
		return is_string($key) && preg_match('/\A[a-f0-9]{64}\z/', $key);
	}

	private function fail($code)
	{
		return array('ok' => FALSE, 'code' => $code);
	}

	private function checked($result)
	{
		if ($result === FALSE) { throw new RuntimeException('Matching data operation failed.'); }
		return $result;
	}

	private function assert_transaction()
	{
		if ($this->db->trans_status() !== TRUE) { throw new RuntimeException('Matching transaction operation failed.'); }
	}

	private function audit($user_id, $run_id, $event)
	{
		$this->checked($this->db->insert('st_audit_events', array('actor_user_id' => (int) $user_id, 'entity_type' => 'matching_run', 'entity_id' => (int) $run_id, 'event_type' => $event, 'details_json' => '{}', 'created_at' => gmdate('Y-m-d H:i:s'))));
	}

	private function lesson($lesson_id)
	{
		return $this->checked($this->db->query('SELECT l.id, l.title, l.content_version, l.is_preview, l.status AS lesson_status, c.id AS course_id, c.status AS course_status, c.is_free FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id WHERE l.id=? FOR UPDATE', array((int) $lesson_id)))->row_array();
	}

	private function can_play($user_id, $lesson, $lock = FALSE)
	{
		$this->load->model('Content_model');
		return $lesson && $lesson['course_status'] === 'published' && $lesson['lesson_status'] === 'published' && $this->Content_model->can_access_lesson($user_id, $lesson, $lock);
	}

	public function find_owned($run_id, $user_id, $lock = FALSE)
	{
		return $this->checked($this->db->query("SELECT r.* FROM st_matching_runs r JOIN st_users u ON u.id=r.learner_user_id AND u.status='active' WHERE r.id=? AND r.learner_user_id=?".($lock ? ' FOR UPDATE' : ''), array((int) $run_id, (int) $user_id)))->row_array();
	}

	public function active_id($lesson_id, $user_id)
	{
		$row = $this->db->select('id')->where('active_key', (int) $user_id.':'.(int) $lesson_id)->get('st_matching_runs')->row_array();
		return $row ? (int) $row['id'] : NULL;
	}

	public function current_lesson($lesson_id)
	{
		return $this->db->query('SELECT l.id, l.slug, l.title, l.is_preview, l.status AS lesson_status, c.id AS course_id, c.status AS course_status, c.is_free FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id WHERE l.id=?', array((int) $lesson_id))->row_array();
	}

	public function can_continue($run)
	{
		return $run['status'] === 'in_progress' && $run['rules_version'] === 'matching_v1' && $this->can_play($run['learner_user_id'], $this->current_lesson($run['lesson_id']));
	}

	public function history($user_id, $query, $status, $offset = 0, $count_only = FALSE)
	{
		$where = 'learner_user_id=?';
		$params = array((int) $user_id);
		if ($query !== '')
		{
			$where .= " AND title_snapshot LIKE ? ESCAPE '!'";
			$params[] = '%'.str_replace(array('!', '%', '_'), array('!!', '!%', '!_'), $query).'%';
		}
		if (in_array($status, array('in_progress', 'completed', 'abandoned'), TRUE))
		{
			$where .= ' AND status=?';
			$params[] = $status;
		}
		if ($count_only)
		{
			return (int) $this->db->query('SELECT COUNT(*) AS total FROM st_matching_runs WHERE '.$where, $params)->row_array()['total'];
		}
		return $this->db->query('SELECT id,title_snapshot,status,started_at FROM st_matching_runs WHERE '.$where.' ORDER BY id DESC LIMIT 10 OFFSET '.max(0, (int) $offset), $params)->result_array();
	}

	public function start($lesson_id, $user_id, $key)
	{
		if ( ! $this->key_valid($key))
		{
			return array('ok' => FALSE, 'code' => 400);
		}
		$this->load->library('Study_transaction');
		return $this->study_transaction->run($this->db, function () use ($lesson_id, $user_id, $key) {
			return $this->start_locked($lesson_id, $user_id, $key);
		});
	}

	private function start_locked($lesson_id, $user_id, $key)
	{
		$lesson = $this->lesson($lesson_id);
		$user = $this->checked($this->db->query("SELECT id,learning_stage FROM st_users WHERE id=? AND status='active' FOR UPDATE", array((int) $user_id)))->row_array();
		if ( ! $lesson || ! $user)
		{
			return $this->fail(404);
		}
		$can_start = $this->can_play($user_id, $lesson, TRUE);
		$this->assert_transaction();
		if ( ! $can_start)
		{
			return $this->fail(403);
		}
		$previous = $this->checked($this->db->query('SELECT * FROM st_matching_runs WHERE learner_user_id=? AND start_key=? FOR UPDATE', array((int) $user_id, $key)))->row_array();
		if ($previous)
		{
			return (int) $previous['lesson_id'] === (int) $lesson_id ? array('ok' => TRUE, 'id' => (int) $previous['id']) : $this->fail(409);
		}
		$active_key = (int) $user_id.':'.(int) $lesson_id;
		$active = $this->checked($this->db->query('SELECT * FROM st_matching_runs WHERE active_key=? FOR UPDATE', array($active_key)))->row_array();
		if ($active)
		{
			return $this->fail(409);
		}
		$this->load->model('Flashcard_model');
		$this->load->library(array('Matching_cards', 'Matching_state'));
		$deck = $this->checked($this->Flashcard_model->get_published_deck_for_lesson($lesson_id));
		$this->assert_transaction();
		$published_cards = $deck ? $this->checked($this->Flashcard_model->get_published_cards($deck['id'])) : array();
		$this->assert_transaction();
		$cards = $this->matching_cards->eligible($published_cards);
		$snapshot = $this->matching_state->begin($cards, $user['learning_stage'] === 'early_childhood' ? 4 : 6);
		if ($snapshot === NULL)
		{
			return $this->fail(409);
		}
		$snapshot['lesson_version'] = (int) $lesson['content_version'];
		$this->checked($this->db->insert('st_matching_runs', array('learner_user_id' => (int) $user_id, 'lesson_id' => (int) $lesson_id, 'title_snapshot' => $lesson['title'], 'rules_version' => 'matching_v1', 'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'state_json' => json_encode($this->matching_state->initial(), JSON_THROW_ON_ERROR), 'status' => 'in_progress', 'active_key' => $active_key, 'start_key' => $key, 'started_at' => gmdate('Y-m-d H:i:s'))));
		$run_id = (int) $this->db->insert_id();
		if ($run_id < 1) { throw new RuntimeException('Matching run identifier unavailable.'); }
		$this->audit($user_id, $run_id, 'matching.started');
		return array('ok' => TRUE, 'id' => $run_id);
	}

	public function move($run_id, $user_id, $revision, $key, $left, $right)
	{
		if ( ! $this->key_valid($key) || ! is_int($revision) || $revision < 0 || ! is_int($left) || ! is_int($right) || $left < 0 || $right < 0 || $left > 99 || $right > 99)
		{
			return array('ok' => FALSE, 'code' => 400);
		}
		$this->load->library('Study_transaction');
		return $this->study_transaction->run($this->db, function () use ($run_id, $user_id, $revision, $key, $left, $right) {
			return $this->move_locked($run_id, $user_id, $revision, $key, $left, $right);
		});
	}

	private function move_locked($run_id, $user_id, $revision, $key, $left, $right)
	{
		$initial = $this->find_owned($run_id, $user_id);
		if ( ! $initial)
		{
			return array('ok' => FALSE, 'code' => 404);
		}
		$lesson = $this->lesson($initial['lesson_id']);
		$run = $this->find_owned($run_id, $user_id, TRUE);
		if ( ! $run)
		{
			return $this->fail(404);
		}
		$previous = $this->checked($this->db->query('SELECT * FROM st_matching_moves WHERE run_id=? AND request_key=? FOR UPDATE', array((int) $run_id, $key)))->row_array();
		if ($previous)
		{
			return (int) $previous['expected_revision'] === $revision && (int) $previous['left_index'] === $left && (int) $previous['right_index'] === $right ? array('ok' => TRUE, 'id' => (int) $run_id) : $this->fail(409);
		}
		if ($run['status'] !== 'in_progress' || $run['rules_version'] !== 'matching_v1' || (int) $run['revision'] !== $revision)
		{
			return $this->fail(409);
		}
		$can_move = $this->can_play($user_id, $lesson, TRUE);
		$this->assert_transaction();
		if ( ! $can_move)
		{
			return $this->fail(403);
		}
		$snapshot = json_decode($run['snapshot_json'], TRUE);
		$state = json_decode($run['state_json'], TRUE);
		if ( ! is_array($snapshot) || ! isset($snapshot['rounds'], $snapshot['cards']) || ! is_array($state) || ! isset($state['round_index'], $state['matched'], $state['move_count'], $state['mistake_count'], $state['complete']))
		{
			return $this->fail(409);
		}
		$this->load->library('Matching_state');
		$result = $this->matching_state->choose($snapshot, $state, $left, $right);
		if ($result === NULL)
		{
			return $this->fail(409);
		}
		$changes = array('revision' => $revision + 1, 'state_json' => json_encode($result['state'], JSON_THROW_ON_ERROR));
		if ($result['state']['complete'])
		{
			$changes += array('status' => 'completed', 'active_key' => NULL, 'finished_at' => gmdate('Y-m-d H:i:s'));
			$this->db->set('elapsed_seconds', 'GREATEST(0,TIMESTAMPDIFF(SECOND,started_at,UTC_TIMESTAMP()))', FALSE);
		}
		$this->checked($this->db->where('id', (int) $run_id)->update('st_matching_runs', $changes));
		$this->checked($this->db->insert('st_matching_moves', array('run_id' => (int) $run_id, 'request_key' => $key, 'expected_revision' => $revision, 'left_index' => $left, 'right_index' => $right, 'outcome' => $result['outcome'], 'created_at' => gmdate('Y-m-d H:i:s'))));
		if ($result['state']['complete'])
		{
			$this->audit($user_id, $run_id, 'matching.completed');
		}
		return array('ok' => TRUE, 'id' => (int) $run_id);
	}

	public function abandon($run_id, $user_id, $revision = NULL)
	{
		$this->load->library('Study_transaction');
		return $this->study_transaction->run($this->db, function () use ($run_id, $user_id, $revision) {
			return $this->abandon_locked($run_id, $user_id, $revision);
		});
	}

	private function abandon_locked($run_id, $user_id, $revision)
	{
		$initial = $this->find_owned($run_id, $user_id);
		if ( ! $initial)
		{
			return array('ok' => FALSE, 'code' => 404);
		}
		$this->lesson($initial['lesson_id']);
		$run = $this->find_owned($run_id, $user_id, TRUE);
		if ( ! $run)
		{
			return $this->fail(404);
		}
		if ($run['status'] === 'abandoned')
		{
			return array('ok' => TRUE, 'id' => (int) $run_id);
		}
		if ($run['status'] !== 'in_progress')
		{
			return $this->fail(409);
		}
		if ($revision !== NULL && (! is_int($revision) || $revision !== (int) $run['revision']))
		{
			return $this->fail(409);
		}
		$this->checked($this->db->where('id', (int) $run_id)->update('st_matching_runs', array('status' => 'abandoned', 'active_key' => NULL, 'revision' => (int) $run['revision'] + 1, 'finished_at' => gmdate('Y-m-d H:i:s'))));
		$this->audit($user_id, $run_id, 'matching.abandoned');
		return array('ok' => TRUE, 'id' => (int) $run_id);
	}
}
