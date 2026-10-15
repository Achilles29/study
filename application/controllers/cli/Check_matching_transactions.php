<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Check_matching_transactions extends CI_Controller
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
		// Full rows, not selected counters. No contents/digests are printed.
		// This deliberately detects unrelated writes too: run the suite alone.
		$tables = array(
			'st_users' => 'id', 'st_user_roles' => 'user_id, role_id', 'st_roles' => 'id',
			'st_family_links' => 'id', 'st_courses' => 'id', 'st_course_sections' => 'id',
			'st_lessons' => 'id', 'st_content_reviews' => 'id', 'st_lesson_progress' => 'id',
			'st_flashcard_decks' => 'id', 'st_flashcards' => 'id', 'st_flashcard_progress' => 'id',
			'st_packages' => 'id', 'st_package_courses' => 'package_id, course_id',
			'st_entitlements' => 'id', 'st_course_enrollments' => 'id',
			'st_matching_runs' => 'id', 'st_matching_moves' => 'id', 'st_audit_events' => 'id'
		);
		$digests = array();
		foreach ($tables as $table => $order)
		{
			$digests[$table] = hash('sha256', serialize($this->rows('SELECT * FROM '.$table.' ORDER BY '.$order)));
		}
		return $digests;
	}

	private function validate_target($user_id, $lesson_id)
	{
		$user = $this->one('SELECT username, account_kind, learning_stage, status FROM st_users WHERE id=?', array($user_id));
		if ($user['account_kind'] !== 'child' || $user['learning_stage'] !== 'primary' || $user['status'] !== 'active' || ! preg_match('/\Aqa_matchingrun_([a-f0-9]{12})\z/', $user['username'], $matches)) { throw new RuntimeException('Exact primary-stage synthetic learner required.'); }
		$suffix = $matches[1];
		$lesson = $this->one('SELECT l.slug, l.status, c.slug AS course_slug, c.status AS course_status, c.author_user_id, c.is_free FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id WHERE l.id=?', array($lesson_id));
		if ($lesson['slug'] !== 'qa-matching-run-'.$suffix || $lesson['course_slug'] !== 'qa-matching-run-'.$suffix || $lesson['status'] !== 'published' || $lesson['course_status'] !== 'published' || (int) $lesson['author_user_id'] !== $user_id || (int) $lesson['is_free'] !== 1) { throw new RuntimeException('Synthetic owned lesson/course mismatch.'); }
		$deck = $this->one('SELECT id, created_by_user_id FROM st_flashcard_decks WHERE lesson_id=?', array($lesson_id));
		if ((int) $deck['created_by_user_id'] !== $user_id) { throw new RuntimeException('Synthetic deck owner mismatch.'); }
		$cards = $this->rows('SELECT front_text, back_text, sort_order FROM st_flashcards WHERE deck_id=? ORDER BY sort_order, id', array((int) $deck['id']));
		if (count($cards) !== 8) { throw new RuntimeException('Eight untouched synthetic cards are required.'); }
		foreach ($cards as $index => $card)
		{
			$number = $index + 1;
			if ($card['front_text'] !== 'Card '.$number || $card['back_text'] !== 'Pair '.$number || (int) $card['sort_order'] !== $number) { throw new RuntimeException('Synthetic source card mismatch.'); }
		}
		if ($this->rows('SELECT id FROM st_matching_runs WHERE learner_user_id=?', array($user_id)) || $this->rows("SELECT id FROM st_audit_events WHERE actor_user_id=? AND entity_type='matching_run'", array($user_id))) { throw new RuntimeException('Probe requires a never-played synthetic learner.'); }
		return $suffix;
	}

	private function setup_final_pair($user_id, $lesson_id, $key)
	{
		// These eight normal operations intentionally COMMIT setup data. They
		// are separate from all injected probes, whose proxy never commits.
		$this->phase = 'confirmed setup start';
		$result = $this->Matching_run_model->start($lesson_id, $user_id, $key);
		if ( ! isset($result['ok'], $result['id']) || $result['ok'] !== TRUE || ! is_int($result['id']) || $result['id'] < 1) { throw new RuntimeException('Synthetic setup start was not confirmed.'); }
		$run_id = $result['id'];
		$run = $this->one('SELECT * FROM st_matching_runs WHERE id=? AND learner_user_id=? AND lesson_id=? AND start_key=?', array($run_id, $user_id, $lesson_id, $key));
		$snapshot_json = $run['snapshot_json'];
		$snapshot = json_decode($snapshot_json, TRUE, 512, JSON_THROW_ON_ERROR);
		if ($run['status'] !== 'in_progress' || (int) $run['revision'] !== 0 || $run['rules_version'] !== 'matching_v1' || ! isset($snapshot['cards'], $snapshot['rounds'], $snapshot['round_size']) || count($snapshot['cards']) !== 8 || $snapshot['round_size'] !== 6) { throw new RuntimeException('Synthetic setup snapshot is invalid.'); }
		$order = array();
		foreach ($snapshot['rounds'] as $round) { $order = array_merge($order, $round); }
		$sorted = $order;
		sort($sorted);
		if ($sorted !== range(0, 7)) { throw new RuntimeException('Synthetic setup round indices are invalid.'); }
		$last = array_pop($order);
		$this->phase = 'confirmed setup moves';
		foreach ($order as $revision => $pair)
		{
			$result = $this->Matching_run_model->move($run_id, $user_id, $revision, hash('sha256', $key.':setup:'.$revision), $pair, $pair);
			if ($result !== array('ok' => TRUE, 'id' => $run_id)) { throw new RuntimeException('Synthetic setup move was not confirmed.'); }
		}
		$run = $this->one('SELECT * FROM st_matching_runs WHERE id=? AND learner_user_id=? AND lesson_id=? AND start_key=?', array($run_id, $user_id, $lesson_id, $key));
		$state = json_decode($run['state_json'], TRUE, 512, JSON_THROW_ON_ERROR);
		$final = $this->matching_state->choose($snapshot, $state, $last, $last);
		$moves = $this->rows('SELECT expected_revision, left_index, right_index, outcome FROM st_matching_moves WHERE run_id=? ORDER BY expected_revision, id', array($run_id));
		$audit = $this->rows("SELECT event_type, entity_id FROM st_audit_events WHERE actor_user_id=? AND entity_type='matching_run' ORDER BY id", array($user_id));
		if ($run['status'] !== 'in_progress' || (int) $run['revision'] !== 7 || $run['snapshot_json'] !== $snapshot_json || count($moves) !== 7 || $final === NULL || $final['state']['complete'] !== TRUE || $final['outcome'] !== 'matched' || count($audit) !== 1 || $audit[0]['event_type'] !== 'matching.started' || (int) $audit[0]['entity_id'] !== $run_id) { throw new RuntimeException('One-pair-left setup verification failed.'); }
		foreach ($moves as $revision => $move)
		{
			if ((int) $move['expected_revision'] !== $revision || (int) $move['left_index'] !== $order[$revision] || (int) $move['right_index'] !== $order[$revision] || $move['outcome'] !== 'matched') { throw new RuntimeException('Setup move history does not match its state.'); }
		}
		return array('run_id' => $run_id, 'revision' => 7, 'pair' => $last);
	}

	private function probe($operation, $user_id, $lesson_id, $key, $fixture = array())
	{
		$this->phase = $operation.'/baseline';
		$cases = array('begin', 'begin_throw', 'read', 'read_throw');
		if ($operation === 'start') { $cases = array_merge($cases, array('user_read', 'start_replay_read', 'active_read', 'deck_read', 'cards_read', 'run_insert', 'run_insert_throw', 'insert_id')); }
		elseif ($operation === 'move_complete') { $cases = array_merge($cases, array('lesson_read', 'run_lock', 'move_replay_read', 'run_update', 'move_insert')); }
		else { $cases = array_merge($cases, array('lesson_read', 'run_lock', 'run_update')); }
		$cases = array_merge($cases, array('audit', 'audit_throw', 'status', 'commit', 'commit_throw', 'rollback', 'rollback_throw'));
		$database = $this->db;
		$debug = $database->db_debug;
		$baseline = $this->digest();
		foreach ($cases as $failure)
		{
			$this->phase = $operation.'/'.$failure;
			$before = $this->digest();
			if ($before !== $baseline) { throw new RuntimeException('Baseline changed before injection.'); }
			$proxy = new Matching_transaction_probe($database, $failure);
			$this->db = $proxy;
			$harness_rollback = FALSE;
			try
			{
				if ($operation === 'start') { $result = $this->Matching_run_model->start($lesson_id, $user_id, $key); }
				elseif ($operation === 'move_complete') { $result = $this->Matching_run_model->move($fixture['run_id'], $user_id, $fixture['revision'], hash('sha256', $key.':final'), $fixture['pair'], $fixture['pair']); }
				else { $result = $this->Matching_run_model->abandon($fixture['run_id'], $user_id, $fixture['revision']); }
			}
			finally
			{
				$this->db = $database;
				if ($database->trans_active())
				{
					// Only the native handle can clean failed rollback probes. This
					// cleanup is not attributed to the model's rollback behavior.
					$harness_rollback = TRUE;
					if ($database->trans_rollback() !== TRUE) { throw new RuntimeException('Explicit injected transaction cleanup failed.'); }
				}
				$database->reset_query();
			}
			$uncertain = in_array($failure, array('commit', 'commit_throw', 'rollback', 'rollback_throw'), TRUE);
			$failed_rollback = in_array($failure, array('rollback', 'rollback_throw'), TRUE);
			$expected = array('ok' => FALSE, 'code' => 503, 'failure' => $uncertain ? 'uncertain' : 'unavailable');
			if ($result !== $expected || $proxy->failures !== 1 || $proxy->begins !== 1 || $proxy->rollbacks !== (in_array($failure, array('begin', 'begin_throw'), TRUE) ? 0 : 1) || $proxy->rollback_faults !== ($failed_rollback ? 1 : 0) || $harness_rollback !== $failed_rollback || $proxy->unexpected_commits !== 0 || $proxy->work_after_failure !== 0 || $database->trans_active() || $database->db_debug !== $debug) { throw new RuntimeException('Injected result/lifecycle contract mismatch.'); }
			if ($failed_rollback && $proxy->writes_before_failure < 1) { throw new RuntimeException('Rollback fault must follow a real write.'); }
			if ($this->digest() !== $before) { throw new RuntimeException('Injected operation changed persistent rows.'); }
		}
		fwrite(STDOUT, 'PASS matching '.$operation.' '.count($cases)." injected failures: full ordered rows of 19 tables unchanged, exact fault reached, no forwarded COMMIT or later data work, debug restored.\n");
	}

	public function index($user_id = '', $lesson_id = '')
	{
		try
		{
			if (PHP_VERSION_ID < 80100 || PHP_VERSION_ID >= 80200 || $this->db->database !== 'db_study' || ! in_array($this->db->hostname, array('127.0.0.1', 'localhost', '::1'), TRUE) || ! preg_match('/\A[1-9][0-9]{0,9}\z/', (string) $user_id) || ! preg_match('/\A[1-9][0-9]{0,9}\z/', (string) $lesson_id) || $this->db->trans_active()) { throw new RuntimeException('QA runtime/target mismatch.'); }
			$user_id = (int) $user_id;
			$lesson_id = (int) $lesson_id;
			$this->load->model('Matching_run_model');
			$this->load->library(array('Study_transaction', 'Matching_state'));
			$suffix = $this->validate_target($user_id, $lesson_id);
			$key = hash('sha256', 'matching-transactions:'.$suffix);
			$this->phase = 'initial digest';
			$initial = $this->digest();
			$this->probe('start', $user_id, $lesson_id, $key);
			$fixture = $this->setup_final_pair($user_id, $lesson_id, $key);
			fwrite(STDOUT, "SETUP confirmed separately: one exact synthetic run plus seven real saved moves; eight normal model operations committed before completion/abandon fault baselines.\n");
			$this->probe('move_complete', $user_id, $lesson_id, $key, $fixture);
			$this->probe('abandon', $user_id, $lesson_id, $key, $fixture);
			$this->phase = 'source preservation';
			$after = $this->digest();
			foreach ($initial as $table => $digest)
			{
				if ( ! in_array($table, array('st_matching_runs', 'st_matching_moves', 'st_audit_events'), TRUE) && $after[$table] !== $digest) { throw new RuntimeException('Setup or injections changed users/content/access rows.'); }
			}
			fwrite(STDOUT, "PASS original users/content/access digests retained. Only the verified setup run, seven moves and started audit remain for exact owning-suite cleanup before normal regression.\n");
			fwrite(STDOUT, "LIMITATION injected commits fail before the real COMMIT, not during server disconnect. Six failed-rollback probes require explicit harness rollback. Auto-increment counters may advance. No HTTP, concurrency or server workers are exercised here.\n");
		}
		catch (Throwable $error)
		{
			fwrite(STDERR, 'FAIL matching transaction probe at '.$this->phase.". The owning PowerShell suite remains responsible for synthetic fixture cleanup.\n");
			exit(1);
		}
	}
}

class Matching_transaction_probe
{
	private $database;
	private $failure;
	private $builder_source = '';
	private $writes = 0;
	public $failures = 0;
	public $begins = 0;
	public $rollbacks = 0;
	public $rollback_faults = 0;
	public $unexpected_commits = 0;
	public $work_after_failure = 0;
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
		if ($this->failures > 0 && in_array($name, array('query', 'get', 'insert', 'update', 'delete'), TRUE))
		{
			$this->work_after_failure++;
			$this->database->reset_query();
			throw new RuntimeException('Unexpected data operation after injected failure.');
		}
		if ($name === 'from') { $this->builder_source = $arguments[0] ?? ''; }
		$sql = $name === 'query' ? ($arguments[0] ?? '') : '';
		$read = ($name === 'query' && preg_match('/\A\s*SELECT\b/i', $sql)) || $name === 'get';
		$markers = array('user_read' => 'SELECT id,learning_stage FROM st_users ', 'start_replay_read' => 'SELECT * FROM st_matching_runs WHERE learner_user_id=', 'active_read' => 'SELECT * FROM st_matching_runs WHERE active_key=', 'move_replay_read' => 'SELECT * FROM st_matching_moves WHERE run_id=', 'lesson_read' => 'FROM st_lessons l JOIN st_course_sections');
		$selected = isset($markers[$this->failure]) && $name === 'query' && strpos($sql, $markers[$this->failure]) !== FALSE;
		$selected = $selected || (in_array($this->failure, array('begin', 'begin_throw'), TRUE) && $name === 'trans_begin');
		$selected = $selected || (in_array($this->failure, array('read', 'read_throw'), TRUE) && $read);
		$selected = $selected || ($this->failure === 'run_lock' && $name === 'query' && strpos($sql, 'SELECT r.* FROM st_matching_runs r ') === 0 && strpos($sql, ' FOR UPDATE') !== FALSE);
		$selected = $selected || ($this->failure === 'deck_read' && $name === 'get' && $this->builder_source === 'st_flashcard_decks d');
		$selected = $selected || ($this->failure === 'cards_read' && $name === 'get' && $this->builder_source === 'st_flashcards c');
		$selected = $selected || (in_array($this->failure, array('run_insert', 'run_insert_throw'), TRUE) && $name === 'insert' && ($arguments[0] ?? '') === 'st_matching_runs');
		$selected = $selected || ($this->failure === 'insert_id' && $name === 'insert_id');
		$selected = $selected || ($this->failure === 'run_update' && $name === 'update' && ($arguments[0] ?? '') === 'st_matching_runs');
		$selected = $selected || ($this->failure === 'move_insert' && $name === 'insert' && ($arguments[0] ?? '') === 'st_matching_moves');
		$selected = $selected || (in_array($this->failure, array('audit', 'audit_throw', 'rollback', 'rollback_throw'), TRUE) && $name === 'insert' && ($arguments[0] ?? '') === 'st_audit_events');
		$selected = $selected || ($this->failure === 'status' && $name === 'trans_status' && $this->writes > 0);
		$selected = $selected || (in_array($this->failure, array('commit', 'commit_throw'), TRUE) && $name === 'trans_commit');
		if ($selected && $this->failures === 0)
		{
			$this->failures++;
			$this->writes_before_failure = $this->writes;
			$this->database->reset_query();
			if (in_array($this->failure, array('begin_throw', 'read_throw', 'run_insert_throw', 'audit_throw', 'commit_throw'), TRUE)) { throw new RuntimeException('Synthetic transaction exception.'); }
			return FALSE;
		}
		if ($name === 'trans_commit')
		{
			// Never forward even an unexpected commit. A missing/ignored probe
			// must fail its assertions, without persisting its attempted changes.
			$this->unexpected_commits++;
			$this->database->reset_query();
			return FALSE;
		}
		$result = call_user_func_array(array($this->database, $name), $arguments);
		if ($name === 'get') { $this->builder_source = ''; }
		if ($result !== FALSE && (in_array($name, array('insert', 'update', 'delete'), TRUE) || ($name === 'query' && preg_match('/\A\s*(?:INSERT|UPDATE|DELETE)\b/i', $sql)))) { $this->writes++; }
		return $result === $this->database ? $this : $result;
	}
}
