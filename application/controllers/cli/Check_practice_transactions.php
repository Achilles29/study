<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Check_practice_transactions extends CI_Controller
{
	private $phase = 'target validation';
	public function __construct()
	{
		if (PHP_SAPI !== 'cli' || ENVIRONMENT !== 'development') { show_404(); exit(1); }
		parent::__construct();
	}

	private function rows($sql, $parameters = array())
	{
		$debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		try
		{
			$query = $this->db->query($sql, $parameters);
			if ($query === FALSE) { throw new RuntimeException('Probe data could not be read.'); }
			return $query->result_array();
		}
		finally { $this->db->db_debug = $debug; }
	}

	private function one($sql, $parameters)
	{
		$rows = $this->rows($sql, $parameters);
		if (count($rows) !== 1) { throw new RuntimeException('Exact synthetic target is required.'); }
		return $rows[0];
	}

	private function digest()
	{
		// Complete, ordered row digests deliberately include other rows in these
		// fixed tables. Run the owning suite alone; no row contents are printed.
		$tables = array(
			'st_users' => 'id', 'st_user_roles' => 'user_id, role_id', 'st_roles' => 'id',
			'st_family_links' => 'id', 'st_courses' => 'id', 'st_course_sections' => 'id',
			'st_lessons' => 'id', 'st_content_reviews' => 'id', 'st_questions' => 'id',
			'st_question_options' => 'id', 'st_packages' => 'id',
			'st_package_courses' => 'package_id, course_id', 'st_entitlements' => 'id',
			'st_course_enrollments' => 'id', 'st_practice_sessions' => 'id',
			'st_session_questions' => 'session_id, question_id',
			'st_practice_attempts' => 'id', 'st_practice_answers' => 'id', 'st_audit_events' => 'id'
		);
		$result = array();
		foreach ($tables as $table => $order)
		{
			$result[$table] = hash('sha256', serialize($this->rows('SELECT * FROM '.$table.' ORDER BY '.$order)));
		}
		return $result;
	}

	private function course_target($course_id, $user_id, $suffix)
	{
		$course = $this->one('SELECT c.*, u.username AS author_username, u.status AS author_status FROM st_courses c JOIN st_users u ON u.id = c.author_user_id WHERE c.id = ?', array($course_id));
		if ($course['slug'] !== 'qa-practice-'.$suffix || $course['author_username'] !== 'qa_practice_editor_'.$suffix || $course['author_status'] !== 'active' || $course['status'] !== 'published') { throw new RuntimeException('Synthetic course/author mismatch.'); }
		$questions = $this->rows('SELECT id, created_by_user_id, target_child_user_id, visibility, status FROM st_questions WHERE course_id = ? ORDER BY id', array($course_id));
		if ( ! $questions || count($questions) > 50) { throw new RuntimeException('Synthetic course questions are required.'); }
		foreach ($questions as $question)
		{
			if ((int) $question['created_by_user_id'] !== (int) $course['author_user_id'] || $question['target_child_user_id'] !== NULL || $question['visibility'] !== 'shared' || $question['status'] !== 'published') { throw new RuntimeException('Synthetic course question ownership mismatch.'); }
		}
		if ( ! $this->Practice_model->course_access($user_id, $course)) { throw new RuntimeException('Synthetic learner access is required.'); }
	}

	private function family_target($session_id, $user_id, $suffix, $starting)
	{
		$session = $this->one('SELECT s.*, u.username AS creator_username, u.account_kind AS creator_kind, u.status AS creator_status FROM st_practice_sessions s JOIN st_users u ON u.id = s.created_by_user_id WHERE s.id = ? AND s.learner_user_id = ?', array($session_id, $user_id));
		if ($session['session_type'] !== 'parent_assigned' || $session['course_id'] !== NULL || $session['creator_username'] !== 'qa_delivery_parent_'.$suffix || $session['creator_kind'] !== 'parent' || $session['creator_status'] !== 'active' || $session['status'] !== ($starting ? 'available' : 'in_progress')) { throw new RuntimeException('Synthetic assignment/creator mismatch.'); }
		$link = $this->one('SELECT status FROM st_family_links WHERE parent_user_id = ? AND child_user_id = ?', array((int) $session['created_by_user_id'], $user_id));
		if ($link['status'] !== 'active' || ! $this->Assignment_model->can_work($session)) { throw new RuntimeException('Synthetic family access and current schedule are required.'); }
		$questions = $this->rows('SELECT q.created_by_user_id, q.target_child_user_id, q.visibility, sq.snapshot_json FROM st_session_questions sq JOIN st_questions q ON q.id = sq.question_id WHERE sq.session_id = ? ORDER BY sq.question_id', array($session_id));
		if ( ! $questions || count($questions) > 50) { throw new RuntimeException('Synthetic assignment snapshots are required.'); }
		foreach ($questions as $question)
		{
			if ((int) $question['created_by_user_id'] !== (int) $session['created_by_user_id'] || (int) $question['target_child_user_id'] !== $user_id || $question['visibility'] !== 'family_private' || ! is_string($question['snapshot_json'])) { throw new RuntimeException('Synthetic private question ownership mismatch.'); }
		}
	}

	private function target($mode, $target_id, $user_id)
	{
		$user = $this->one('SELECT id, username, account_kind, status FROM st_users WHERE id = ?', array($user_id));
		if ($user['account_kind'] !== 'child' || $user['status'] !== 'active' || ! preg_match('/\Aqa_(practice|delivery)_child_([a-f0-9]{12})\z/', $user['username'], $matches)) { throw new RuntimeException('Only an exact synthetic child is permitted.'); }
		$kind = $matches[1];
		$suffix = $matches[2];
		if ($mode === 'course_start')
		{
			if ($kind !== 'practice') { throw new RuntimeException('Course fixture identity mismatch.'); }
			$this->course_target($target_id, $user_id, $suffix);
			if ($this->rows("SELECT id FROM st_practice_sessions WHERE course_id = ? AND learner_user_id = ? AND session_type = 'course_quiz'", array($target_id, $user_id))) { throw new RuntimeException('Start probes require a never-started fixture.'); }
			return array('key' => hash('sha256', 'practice-transaction-probe:'.$suffix));
		}
		if ($mode === 'family_start')
		{
			if ($kind !== 'delivery') { throw new RuntimeException('Assignment fixture identity mismatch.'); }
			$this->family_target($target_id, $user_id, $suffix, TRUE);
			if ($this->rows('SELECT id FROM st_practice_attempts WHERE session_id = ?', array($target_id))) { throw new RuntimeException('Start probes require an assignment without an attempt.'); }
			return array();
		}
		$attempt = $this->one('SELECT a.*, s.course_id, s.created_by_user_id, s.session_type, s.learner_user_id AS session_learner_id FROM st_practice_attempts a JOIN st_practice_sessions s ON s.id = a.session_id WHERE a.id = ? AND a.learner_user_id = ?', array($target_id, $user_id));
		if ($attempt['status'] !== 'in_progress' || (int) $attempt['session_learner_id'] !== $user_id) { throw new RuntimeException('An active own synthetic attempt is required.'); }
		if ($kind === 'practice')
		{
			if ($attempt['session_type'] !== 'course_quiz' || (int) $attempt['created_by_user_id'] !== $user_id) { throw new RuntimeException('Course attempt context mismatch.'); }
			$this->course_target((int) $attempt['course_id'], $user_id, $suffix);
		}
		else { $this->family_target((int) $attempt['session_id'], $user_id, $suffix, FALSE); }
		$rows = $this->rows('SELECT question_id, snapshot_json FROM st_session_questions WHERE session_id = ? ORDER BY sort_order, question_id', array((int) $attempt['session_id']));
		if ( ! $rows || count($rows) !== (int) $attempt['question_count']) { throw new RuntimeException('Complete synthetic snapshots are required.'); }
		$answers = array();
		foreach ($rows as $row)
		{
			$snapshot = json_decode($row['snapshot_json'], TRUE, 512, JSON_THROW_ON_ERROR);
			if ( ! isset($snapshot['options']) || ! is_array($snapshot['options']) || count($snapshot['options']) !== 4 || ! isset($snapshot['options'][0]['id'])) { throw new RuntimeException('Valid synthetic options are required.'); }
			$answers[(int) $row['question_id']] = (string) $snapshot['options'][0]['id'];
		}
		return array('revision' => (int) $attempt['answer_revision'], 'answers' => $answers);
	}

	public function index($mode = '', $target_id = '', $user_id = '')
	{
		try
		{
			if (PHP_VERSION_ID < 80100 || PHP_VERSION_ID >= 80200 || $this->db->database !== 'db_study' || ! in_array($this->db->hostname, array('127.0.0.1', 'localhost', '::1'), TRUE) || ! in_array($mode, array('course_start', 'family_start', 'save'), TRUE) || ! preg_match('/\A[1-9][0-9]{0,9}\z/', (string) $target_id) || ! preg_match('/\A[1-9][0-9]{0,9}\z/', (string) $user_id) || $this->db->trans_active()) { throw new RuntimeException('QA runtime/target mismatch.'); }
			$this->load->model('Practice_model');
			$this->load->model('Assignment_model');
			$this->load->library('Study_transaction');
			$target_id = (int) $target_id;
			$user_id = (int) $user_id;
			$database = $this->db;
			$debug = $database->db_debug;
			$database->db_debug = FALSE;
			try
			{
				$fixture = $this->target($mode, $target_id, $user_id);
				$this->phase = 'baseline digest';
				$baseline = $this->digest();
			}
			finally { $database->db_debug = $debug; }
			$cases = array('begin', 'begin_throw', 'read', 'read_throw');
			if ($mode === 'course_start') { $cases = array_merge($cases, array('session_insert', 'snapshot_insert', 'attempt_insert')); }
			elseif ($mode === 'family_start') { $cases = array_merge($cases, array('snapshot_read', 'attempt_insert', 'session_update')); }
			else { $cases = array_merge($cases, array('snapshot_read', 'answer_upsert', 'session_update', 'attempt_update')); }
			$cases = array_merge($cases, array('audit', 'audit_throw', 'commit', 'commit_throw', 'rollback', 'rollback_throw'));
			foreach ($cases as $failure)
			{
				$this->phase = $mode.'/'.$failure;
				$before = $this->digest();
				if ($before !== $baseline) { throw new RuntimeException('Baseline changed before probe.'); }
				$proxy = new Practice_transaction_probe($database, $failure);
				$this->db = $proxy;
				$harness_rollback = FALSE;
				try
				{
					if ($mode === 'course_start') { $result = $this->Practice_model->start($target_id, $user_id, $fixture['key']); }
					elseif ($mode === 'family_start') { $result = $this->Assignment_model->start($target_id, $user_id); }
					else { $result = $this->Practice_model->save_answers($target_id, $user_id, $fixture['revision'], $fixture['answers'], TRUE); }
				}
				finally
				{
					$this->db = $database;
					// Failed-rollback probes intentionally leave a real transaction open.
					// Cleanup bypasses the proxy; the model receives no credit for it.
					if ($database->trans_active())
					{
						$harness_rollback = TRUE;
						if ($database->trans_rollback() !== TRUE) { throw new RuntimeException('Explicit probe transaction cleanup failed.'); }
					}
					$database->reset_query();
				}
				$uncertain = in_array($failure, array('commit', 'commit_throw', 'rollback', 'rollback_throw'), TRUE);
				$failed_rollback = in_array($failure, array('rollback', 'rollback_throw'), TRUE);
				$expected = array('ok' => FALSE, 'code' => 503, 'failure' => $uncertain ? 'uncertain' : 'unavailable');
				if ($result !== $expected || $proxy->failures !== 1 || $proxy->begins !== 1 || $proxy->rollbacks !== (in_array($failure, array('begin', 'begin_throw'), TRUE) ? 0 : 1) || $proxy->rollback_faults !== ($failed_rollback ? 1 : 0) || $harness_rollback !== $failed_rollback || $proxy->unexpected_commits !== 0 || $database->trans_active() || $database->db_debug !== $debug) { throw new RuntimeException('Transaction response/lifecycle contract mismatch.'); }
				if ($failed_rollback && $proxy->writes_before_failure < 1) { throw new RuntimeException('Rollback failure did not follow a real write.'); }
				if ($this->digest() !== $before) { throw new RuntimeException('Probe changed persistent baseline rows.'); }
			}
			fwrite(STDOUT, 'PASS '.$mode.' '.count($cases)." synthetic transaction failures: complete ordered rows of 19 tables unchanged, exact fault reached, no real commit, debug restored, rollback only after begin.\n");
			fwrite(STDOUT, "LIMITATION commit failures occur before real COMMIT, not during server disconnect; two rollback faults require explicit harness cleanup. Auto-increment counters may advance. No fixture rows are deleted here; run the owning PowerShell suite alone.\n");
		}
		catch (Throwable $error)
		{
			// Do not expose exception text, SQL, IDs, answers, credentials or rows.
			fwrite(STDERR, 'FAIL practice transaction probe at '.$this->phase.". The owning PowerShell harness remains responsible for fixture cleanup.\n");
			exit(1);
		}
	}
}

class Practice_transaction_probe
{
	private $database;
	private $failure;
	private $writes = 0;
	public $failures = 0;
	public $begins = 0;
	public $rollbacks = 0;
	public $rollback_faults = 0;
	public $unexpected_commits = 0;
	public $writes_before_failure = 0;
	public function __construct($database, $failure)
	{
		$this->database = $database;
		$this->failure = $failure;
	}
	public function __get($name)
	{
		return $this->database->$name;
	}
	public function __set($name, $value)
	{
		$this->database->$name = $value;
	}
	public function __call($name, $arguments)
	{
		if ($name === 'trans_begin') { $this->begins++; }
		if ($name === 'trans_rollback')
		{
			$this->rollbacks++;
			if (in_array($this->failure, array('rollback', 'rollback_throw'), TRUE))
			{
				$this->rollback_faults++;
				$this->database->reset_query();
				if ($this->failure === 'rollback_throw') { throw new RuntimeException('Synthetic rollback exception.'); }
				return FALSE;
			}
		}
		$sql = $name === 'query' ? ($arguments[0] ?? '') : '';
		$read = ($name === 'query' && preg_match('/\A\s*SELECT\b/i', $sql)) || $name === 'get';
		$targets = array('session_insert' => array('insert', 'st_practice_sessions'), 'snapshot_insert' => array('insert', 'st_session_questions'), 'attempt_insert' => array('insert', 'st_practice_attempts'), 'session_update' => array('update', 'st_practice_sessions'), 'attempt_update' => array('update', 'st_practice_attempts'));
		$selected = isset($targets[$this->failure]) && $name === $targets[$this->failure][0] && ($arguments[0] ?? '') === $targets[$this->failure][1];
		$selected = $selected || (in_array($this->failure, array('begin', 'begin_throw'), TRUE) && $name === 'trans_begin');
		$selected = $selected || (in_array($this->failure, array('read', 'read_throw'), TRUE) && $read);
		$selected = $selected || ($this->failure === 'snapshot_read' && (($name === 'query' && strpos($sql, 'FROM st_session_questions ') !== FALSE) || ($name === 'get' && ($arguments[0] ?? '') === 'st_session_questions')));
		$selected = $selected || ($this->failure === 'answer_upsert' && $name === 'query' && strpos($sql, 'INSERT INTO st_practice_answers ') === 0);
		$selected = $selected || (in_array($this->failure, array('audit', 'audit_throw', 'rollback', 'rollback_throw'), TRUE) && $name === 'insert' && ($arguments[0] ?? '') === 'st_audit_events');
		$selected = $selected || (in_array($this->failure, array('commit', 'commit_throw'), TRUE) && $name === 'trans_commit');
		if ($selected && $this->failures === 0)
		{
			$this->failures++;
			$this->writes_before_failure = $this->writes;
			$this->database->reset_query();
			if (in_array($this->failure, array('begin_throw', 'read_throw', 'audit_throw', 'commit_throw'), TRUE)) { throw new RuntimeException('Synthetic transaction exception.'); }
			return FALSE;
		}
		if ($name === 'trans_commit')
		{
			// Even a broken model or unmatched probe must never persist a fixture
			// mutation. An unexpected commit attempt fails this probe afterward.
			$this->unexpected_commits++;
			$this->database->reset_query();
			return FALSE;
		}
		$result = call_user_func_array(array($this->database, $name), $arguments);
		if ($result !== FALSE && (in_array($name, array('insert', 'update', 'delete'), TRUE) || ($name === 'query' && preg_match('/\A\s*(?:INSERT|UPDATE|DELETE)\b/i', $sql)))) { $this->writes++; }
		return $result === $this->database ? $this : $result;
	}
}
