<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Practice_live_model extends CI_Model
{
	public function globally_enabled()
	{
		$this->load->model('Settings_model');
		return ($this->Settings_model->get_section('latihan_mandiri')['values']['parent_live_enabled'] ?? '0') === '1';
	}

	public function enabled_for_attempt($attempt)
	{
		if (!$attempt || $attempt['status'] !== 'in_progress' || !$this->globally_enabled()) return FALSE;
		$learner = $this->db->select('account_kind,status')->where('id', (int)$attempt['learner_user_id'])->get('st_users')->row_array();
		if (!$learner || $learner['account_kind'] !== 'child' || $learner['status'] !== 'active') return FALSE;
		if ($attempt['session_type'] !== 'assessment') return in_array($attempt['session_type'], array('self_practice', 'course_quiz'), TRUE);
		$row = $this->db->query('SELECT e.parent_live_enabled FROM st_drill_sessions d JOIN st_exam_templates e ON e.id=d.exam_id WHERE d.session_id=?', array((int)$attempt['session_id']))->row_array();
		return $row && (int)$row['parent_live_enabled'] === 1;
	}

	public function mark_seen($attempt, $question_id)
	{
		if (!$this->enabled_for_attempt($attempt)) return FALSE;
		$found = $this->db->query('SELECT question_id FROM st_session_questions WHERE session_id=? AND question_id=?', array((int)$attempt['session_id'], (int)$question_id))->row_array();
		if (!$found) return FALSE;
		$now = gmdate('Y-m-d H:i:s');
		return $this->db->query('INSERT INTO st_practice_live_state (attempt_id,current_question_id,opened_at,last_seen_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE opened_at=IF(current_question_id <=> VALUES(current_question_id),opened_at,VALUES(opened_at)),current_question_id=VALUES(current_question_id),last_seen_at=VALUES(last_seen_at)', array((int)$attempt['id'], (int)$question_id, $now, $now));
	}

	// Called inside the answer transaction after revision and answer validation.
	public function record_changes($attempt, $answers, $now)
	{
		if (!$this->enabled_for_attempt($attempt)) return;
		$previous = array();
		foreach ($this->db->select('question_id,selected_option_id')->where('attempt_id', (int)$attempt['id'])->get('st_practice_answers')->result_array() as $row) {
			$previous[(int)$row['question_id']] = $row['selected_option_id'] === NULL ? NULL : (int)$row['selected_option_id'];
		}
		$presence = $this->db->where('attempt_id', (int)$attempt['id'])->get('st_practice_live_state')->row_array();
		foreach ($answers as $question_id => $value) {
			$selected = $value === '' ? NULL : (int)$value;
			if (($previous[(int)$question_id] ?? NULL) === $selected) continue;
			$elapsed = NULL;
			if ($presence && (int)$presence['current_question_id'] === (int)$question_id && $presence['opened_at']) {
				$seconds = strtotime($now.' UTC') - strtotime($presence['opened_at'].' UTC');
				if ($seconds >= 0 && $seconds <= 3600) $elapsed = $seconds * 1000;
			}
			if (!$this->db->insert('st_practice_answer_events', array('attempt_id'=>(int)$attempt['id'], 'question_id'=>(int)$question_id, 'selected_option_id'=>$selected, 'elapsed_ms'=>$elapsed, 'created_at'=>$now))) throw new RuntimeException('Practice answer event unavailable.');
		}
	}

	public function state($attempt_id)
	{
		return $this->db->where('attempt_id', (int)$attempt_id)->get('st_practice_live_state')->row_array();
	}

	public function answers($attempt)
	{
		$rows = $this->db->query('SELECT q.question_id, a.selected_option_id FROM st_session_questions q LEFT JOIN st_practice_answers a ON a.attempt_id=? AND a.question_id=q.question_id WHERE q.session_id=? ORDER BY q.sort_order', array((int)$attempt['id'], (int)$attempt['session_id']))->result_array();
		if (count($rows) !== (int)$attempt['question_count']) return NULL;
		$result = array();
		foreach ($rows as $row) $result[(int)$row['question_id']] = $row['selected_option_id'] === NULL ? NULL : (int)$row['selected_option_id'];
		return $result;
	}

	public function answer_timings($attempt_id)
	{
		$rows = $this->db->query('SELECT question_id, selected_option_id, elapsed_ms, created_at FROM st_practice_answer_events WHERE attempt_id=? ORDER BY id', array((int)$attempt_id))->result_array();
		$result = array();
		foreach ($rows as $row) {
			$id = (int)$row['question_id'];
			if (!isset($result[$id])) $result[$id] = array('first_ms'=>NULL, 'changes'=>0, 'last_at'=>NULL, 'has_first'=>FALSE);
			if ($row['selected_option_id'] !== NULL && !$result[$id]['has_first']) {
				$result[$id]['first_ms'] = $row['elapsed_ms'] === NULL ? NULL : (int)$row['elapsed_ms'];
				$result[$id]['has_first'] = TRUE;
			}
			$result[$id]['changes']++;
			$result[$id]['last_at'] = $row['created_at'];
		}
		foreach ($result as &$timing) unset($timing['has_first']);
		unset($timing);
		return $result;
	}
}
