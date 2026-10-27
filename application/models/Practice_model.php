<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once dirname(__DIR__).'/libraries/Question_media.php';
require_once dirname(__DIR__).'/libraries/Practice_sheet.php';

class Practice_model extends CI_Model
{
	public function course_access($user_id, $course, $lock = FALSE)
	{
		$this->load->model('Content_model');
		return $course && $course['status'] === 'published' && $this->Content_model->can_access_course($user_id, $course, $lock);
	}

	public function find_attempt($attempt_id, $user_id)
	{
		return $this->checked($this->db->query("SELECT a.*, s.course_id, s.title, s.rules_version, s.session_type, s.created_by_user_id FROM st_practice_attempts a JOIN st_practice_sessions s ON s.id = a.session_id WHERE a.id = ? AND a.learner_user_id = ? AND s.learner_user_id = ? AND s.session_type IN ('course_quiz', 'parent_assigned', 'self_practice', 'assessment')", array((int) $attempt_id, (int) $user_id, (int) $user_id)))->row_array();
	}

	public function can_read_attempt($attempt, $user_id)
	{
		if ( ! $attempt || (int) $attempt['learner_user_id'] !== (int) $user_id)
		{
			return FALSE;
		}
		if ($attempt['status'] === 'graded')
		{
			return TRUE;
		}
		if ($attempt['status'] !== 'in_progress')
		{
			return FALSE;
		}
		if (in_array($attempt['session_type'], array('self_practice','assessment'), TRUE)) {
			return (bool)$this->db->where('session_id',$attempt['session_id'])->get('st_drill_sessions')->row_array();
		}
		if ($attempt['session_type'] === 'parent_assigned')
		{
			$this->load->model('Assignment_model');
			return $attempt['status'] === 'in_progress' && $this->Assignment_model->can_work($this->Assignment_model->child_session($attempt['session_id'], $user_id));
		}
		$course = $this->db->where('id', $attempt['course_id'])->get('st_courses')->row_array();
		return $this->course_access($user_id, $course);
	}

	private function fail($code)
	{
		return array('ok' => FALSE, 'code' => $code);
	}

	private function checked($result)
	{
		if ($result === FALSE) { throw new RuntimeException('Practice data operation failed.'); }
		return $result;
	}

	private function assert_transaction()
	{
		if ($this->db->trans_status() === FALSE) { throw new RuntimeException('Practice transaction operation failed.'); }
	}

	private function audit($user_id, $attempt_id, $event)
	{
		$this->checked($this->db->insert('st_audit_events', array('actor_user_id' => (int) $user_id, 'event_type' => $event, 'entity_type' => 'practice_attempt', 'entity_id' => (int) $attempt_id, 'details_json' => '{}', 'created_at' => gmdate('Y-m-d H:i:s'))));
	}

	public function start($course_id, $user_id, $idempotency_key, $sheet = array())
	{
		$sheet = Practice_sheet::validate($sheet);
		if (!$sheet) { return $this->fail(400); }
		$this->load->library('Study_transaction');
		return $this->study_transaction->run($this->db, function () use ($course_id, $user_id, $idempotency_key, $sheet) {
			return $this->start_locked($course_id, $user_id, $idempotency_key, $sheet);
		});
	}

	private function start_locked($course_id, $user_id, $idempotency_key, $sheet)
	{
		// Serialize starts for a course, including retries from separate browser tabs.
		$course = $this->checked($this->db->query('SELECT * FROM st_courses WHERE id = ? FOR UPDATE', array((int) $course_id)))->row_array();
		$can_start = $this->course_access($user_id, $course, TRUE);
		$this->assert_transaction();
		if ( ! $can_start)
		{
			return $this->fail(403);
		}
		$previous = $this->checked($this->db->query("SELECT a.id, a.status FROM st_practice_attempts a JOIN st_practice_sessions s ON s.id = a.session_id WHERE s.course_id = ? AND s.learner_user_id = ? AND s.session_type = 'course_quiz' AND s.idempotency_key = ?", array((int) $course_id, (int) $user_id, $idempotency_key)))->row_array();
		if ($previous)
		{
			return array('ok' => TRUE, 'id' => (int) $previous['id'], 'status' => $previous['status']);
		}
		$active = $this->checked($this->db->query("SELECT a.id, a.status FROM st_practice_attempts a JOIN st_practice_sessions s ON s.id = a.session_id WHERE s.course_id = ? AND s.learner_user_id = ? AND s.session_type = 'course_quiz' AND a.status = 'in_progress' ORDER BY a.id DESC LIMIT 1", array((int) $course_id, (int) $user_id)))->row_array();
		if ($active)
		{
			return array('ok' => TRUE, 'id' => (int) $active['id'], 'status' => $active['status']);
		}
		$this->load->model('Question_model');
		$questions = $this->checked($this->Question_model->list_for_course($course_id));
		$this->assert_transaction();
		if ( ! $questions)
		{
			return $this->fail(409);
		}
		$ready = $this->Question_model->ready_for_review($course_id);
		$this->assert_transaction();
		if ( ! $ready)
		{
			return $this->fail(409);
		}
		foreach ($questions as $question)
		{
			if ($question['status'] !== 'published')
			{
				return $this->fail(409);
			}
		}
		$now = gmdate('Y-m-d H:i:s');
		$this->checked($this->db->insert('st_practice_sessions', array('created_by_user_id' => (int) $user_id, 'learner_user_id' => (int) $user_id, 'course_id' => (int) $course_id, 'title' => $course['title'], 'session_type' => 'course_quiz', 'status' => 'in_progress', 'idempotency_key' => $idempotency_key, 'rules_version' => 'equal_weight_v1', 'created_at' => $now, 'updated_at' => $now)));
		$session_id = (int) $this->db->insert_id();
		if ($session_id < 1) { throw new RuntimeException('Practice session identifier unavailable.'); }
		$this->checked($this->db->where('id',$session_id)->update('st_practice_sessions', array('sheet_rules_json'=>json_encode($sheet, JSON_THROW_ON_ERROR))));
		foreach ($questions as $index => $question)
		{
			$snapshot = array('prompt' => $question['prompt'], 'explanation' => $question['explanation'], 'content_version' => (int) $question['content_version'], 'options' => $question['options']);
			$this->checked($this->db->insert('st_session_questions', array('session_id' => $session_id, 'question_id' => (int) $question['id'], 'sort_order' => $index, 'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))));
		}
		$this->checked($this->db->insert('st_practice_attempts', array('session_id' => $session_id, 'learner_user_id' => (int) $user_id, 'status' => 'in_progress', 'question_count' => count($questions), 'started_at' => $now)));
		$attempt_id = (int) $this->db->insert_id();
		if ($attempt_id < 1) { throw new RuntimeException('Practice attempt identifier unavailable.'); }
		$this->audit($user_id, $attempt_id, 'practice.started');
		return array('ok' => TRUE, 'id' => $attempt_id, 'status' => 'in_progress');
	}

	private function snapshots($session_id)
	{
		$rows = $this->checked($this->db->where('session_id', (int) $session_id)->order_by('sort_order', 'ASC')->get('st_session_questions'))->result_array();
		$result = array();
		foreach ($rows as $row)
		{
			$snapshot = json_decode($row['snapshot_json'], TRUE);
			if ( ! is_array($snapshot) || ! isset($snapshot['prompt'], $snapshot['explanation'], $snapshot['options']) || ! is_array($snapshot['options']) || count($snapshot['options']) !== 4)
			{
				return array();
			}
			$result[(int) $row['question_id']] = $snapshot;
		}
		return $result;
	}

	public function display_questions($attempt)
	{
		$questions = $this->snapshots($attempt['session_id']);
		$answers = $this->db->where('attempt_id', $attempt['id'])->get('st_practice_answers')->result_array();
		$answer_map = array();
		foreach ($answers as $answer)
		{
			$answer_map[(int) $answer['question_id']] = $answer;
		}
		$result = array();
		foreach ($questions as $question_id => $question)
		{
			$answer = isset($answer_map[$question_id]) ? $answer_map[$question_id] : NULL;
			$item = array('id' => $question_id, 'prompt' => $question['prompt'], 'has_image' => !empty($question['image']), 'selected_option_id' => $answer ? $answer['selected_option_id'] : NULL, 'options' => array());
			$item['image_count'] = !empty($question['image']) ? 1 + count($question['image']['additional_images'] ?? array()) : 0;
			$item['media_layout'] = Question_media::layout($question['image'] ?? NULL, $question['prompt'], array_column($question['options'], 'option_text'));
			foreach ($question['options'] as $option)
			{
				$public_option = array('id' => (int) $option['id'], 'option_text' => $option['option_text']);
				if ($attempt['status'] === 'graded')
				{
					$public_option['is_correct'] = (int) $option['is_correct'];
				}
				$item['options'][] = $public_option;
			}
			if ($attempt['status'] === 'graded')
			{
				$item['explanation'] = $question['explanation'];
				$item['is_correct'] = $answer ? (int) $answer['is_correct'] : 0;
			}
			$result[] = $item;
		}
		return $result;
	}

	public function save_answers($attempt_id, $user_id, $revision, $answers, $finish, $events = array())
	{
		if (!is_array($events) || count($events)>20) { return $this->fail(400); }
		foreach ($events as $token=>$reason) {
			if (!is_string($token) || !preg_match('/\A[a-f0-9]{32}\z/',$token)
				|| !in_array($reason,array('hidden','blur','sleep'),TRUE)) { return $this->fail(400); }
		}
		$this->load->library('Study_transaction');
		return $this->study_transaction->run($this->db, function () use ($attempt_id, $user_id, $revision, $answers, $finish, $events) {
			return $this->save_answers_locked($attempt_id, $user_id, $revision, $answers, $finish, $events);
		});
	}

	private function save_answers_locked($attempt_id, $user_id, $revision, $answers, $finish, $events)
	{
		$attempt = $this->find_attempt($attempt_id, $user_id);
		if ( ! $attempt)
		{
			return array('ok' => FALSE, 'code' => 404);
		}
		if ($attempt['status'] === 'graded')
		{
			return array('ok' => TRUE, 'id' => (int) $attempt_id, 'status' => 'graded');
		}
		// Match lock order used by start/content publishing before locking this attempt.
		$drill = NULL;
		if (in_array($attempt['session_type'],array('self_practice','assessment'),TRUE))
		{
			$this->checked($this->db->query('SELECT id FROM st_users WHERE id=? FOR UPDATE',array($user_id)));
			$drill=$this->checked($this->db->query('SELECT * FROM st_drill_sessions WHERE session_id=?',array($attempt['session_id'])))->row_array();
			$can_save=(bool)$drill;
		}
		elseif ($attempt['session_type'] === 'parent_assigned')
		{
			$this->load->model('Assignment_model');
			$session = $this->Assignment_model->lock_for_child($attempt['session_id'], $user_id, $attempt['created_by_user_id']);
			$this->assert_transaction();
			$can_save = $this->Assignment_model->can_work($session, TRUE);
		}
		else
		{
			$course = $this->checked($this->db->query('SELECT * FROM st_courses WHERE id = ? FOR UPDATE', array((int) $attempt['course_id'])))->row_array();
			$can_save = $this->course_access($user_id, $course, TRUE);
		}
		$this->assert_transaction();
		$locked = $this->checked($this->db->query('SELECT * FROM st_practice_attempts WHERE id = ? AND learner_user_id = ? FOR UPDATE', array((int) $attempt_id, (int) $user_id)))->row_array();
		if ( ! $locked)
		{
			return $this->fail(404);
		}
		if ($locked['status'] === 'graded')
		{
			return array('ok' => TRUE, 'id' => (int) $attempt_id, 'status' => 'graded');
		}
		if ( ! $can_save)
		{
			return $this->fail(403);
		}
		$sheet = $this->sheet_state($attempt);
		$stale = (int) $locked['answer_revision'] !== $revision;
		if ($locked['status'] !== 'in_progress' || $attempt['rules_version'] !== 'equal_weight_v1' || ($stale && (!$events || $sheet['rules']['focus_guard']!=='1')))
		{
			return $this->fail(409);
		}
		$questions = $this->snapshots($attempt['session_id']);
		if ( ! $questions || count($questions) !== (int) $locked['question_count'])
		{
			return $this->fail(409);
		}
		$expired = $drill && $drill['expires_at'] && $drill['expires_at'] <= gmdate('Y-m-d H:i:s');
		if ($expired || $stale) {
			// After the deadline, reject late input and grade only the last saved answers.
			$answers=array();
			foreach($this->checked($this->db->where('attempt_id',$attempt_id)->get('st_practice_answers'))->result_array() as $saved){$answers[$saved['question_id']]=$saved['selected_option_id']===NULL?'':(string)$saved['selected_option_id'];}
			$finish=(bool)$expired;
		}
		foreach ($answers as $question_id => $option_id)
		{
			if ( ! ctype_digit((string) $question_id) || ! isset($questions[(int) $question_id]) || ! is_string($option_id) || ($option_id !== '' && ! preg_match('/\A[1-9][0-9]{0,18}\z/', $option_id)))
			{
				return $this->fail(400);
			}
			$valid_options = array_column($questions[(int) $question_id]['options'], 'id');
			if ($option_id !== '' && ! in_array($option_id, array_map('strval', $valid_options), TRUE))
			{
				return $this->fail(400);
			}
		}
		if ($sheet['rules']['focus_guard']==='1') {
			foreach ($events as $token=>$reason) {
				$this->checked($this->db->query('INSERT INTO st_practice_focus_events(attempt_id,event_token,reason,created_at) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE event_token=event_token',array($attempt_id,$token,$reason,gmdate('Y-m-d H:i:s'))));
			}
			$focus_count = $this->focus_count($attempt_id);
			if ($focus_count >= (int)$sheet['rules']['focus_limit']) {
				$finish=TRUE;
				$this->audit($user_id,$attempt_id,'practice.focus_limit');
			}
		}
		$now = gmdate('Y-m-d H:i:s');
		$complete_answers = array();
		foreach ($questions as $question_id => $_question) $complete_answers[$question_id] = isset($answers[$question_id]) ? $answers[$question_id] : '';
		$this->load->model('Practice_live_model');
		$this->Practice_live_model->record_changes($attempt, $complete_answers, $now);
		$correct = 0;
		foreach ($questions as $question_id => $question)
		{
			$option_id = isset($answers[$question_id]) && $answers[$question_id] !== '' ? (int) $answers[$question_id] : NULL;
			$is_correct = 0;
			foreach ($question['options'] as $option)
			{
				if ($option_id === (int) $option['id'] && (int) $option['is_correct'] === 1)
				{
					$is_correct = 1;
				}
			}
			$correct += $is_correct;
			$this->checked($this->db->query('INSERT INTO st_practice_answers (attempt_id, question_id, selected_option_id, is_correct, answered_at) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE selected_option_id = VALUES(selected_option_id), is_correct = VALUES(is_correct), answered_at = VALUES(answered_at)', array((int) $attempt_id, $question_id, $option_id, $finish ? $is_correct : NULL, $now)));
		}
		$changes = array('answer_revision' => (int) $locked['answer_revision'] + 1);
		if ($finish)
		{
			$this->load->library('Practice_scoring');
			$changes += array('status' => 'graded', 'score' => $this->practice_scoring->percentage($correct, count($questions)), 'correct_count' => $correct, 'submitted_at' => $now);
			$this->checked($this->db->where('id', $attempt['session_id'])->update('st_practice_sessions', array('status' => 'completed', 'updated_at' => $now)));
			$this->audit($user_id, $attempt_id, 'practice.graded');
		}
		$this->checked($this->db->where('id', (int) $attempt_id)->update('st_practice_attempts', $changes));
		return array('ok' => TRUE, 'id' => (int) $attempt_id, 'status' => $finish ? 'graded' : 'in_progress', 'conflict'=>$stale);
	}

	public function count_history($user_id)
	{
		return (int) $this->db->query("SELECT COUNT(*) AS total FROM st_practice_attempts a JOIN st_practice_sessions s ON s.id = a.session_id WHERE a.learner_user_id = ? AND s.learner_user_id = ? AND s.session_type IN ('course_quiz', 'parent_assigned', 'self_practice', 'assessment')", array((int) $user_id, (int) $user_id))->row()->total;
	}

	public function history($user_id, $limit, $offset)
	{
		return $this->db->query("SELECT a.id, a.status, a.score, a.correct_count, a.question_count, a.started_at, s.title FROM st_practice_attempts a JOIN st_practice_sessions s ON s.id = a.session_id WHERE a.learner_user_id = ? AND s.learner_user_id = ? AND s.session_type IN ('course_quiz', 'parent_assigned', 'self_practice', 'assessment') ORDER BY a.id DESC LIMIT ? OFFSET ?", array((int) $user_id, (int) $user_id, (int) $limit, (int) $offset))->result_array();
	}

	public function deadline($attempt){return $this->db->where('session_id',$attempt['session_id'])->get('st_drill_sessions')->row_array()['expires_at']??NULL;}
	public function focus_count($attempt_id)
	{
		return (int)$this->db->where('attempt_id',$attempt_id)->count_all_results('st_practice_focus_events');
	}
	public function sheet_state($attempt)
	{
		$row=$this->checked($this->db->select('sheet_rules_json')->where('id',$attempt['session_id'])->get('st_practice_sessions'))->row_array();
		return array('rules'=>Practice_sheet::read($row['sheet_rules_json']??NULL),'focus_count'=>$this->focus_count($attempt['id']));
	}
	public function snapshot_image($attempt,$question_id){return $this->snapshots($attempt['session_id'])[(int)$question_id]['image']??NULL;}
}
