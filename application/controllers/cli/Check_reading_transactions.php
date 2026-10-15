<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Check_reading_transactions extends CI_Controller
{
	private $phase = 'target validation';
	public function __construct()
	{
		if (PHP_SAPI !== 'cli' || ENVIRONMENT !== 'development') { show_404(); exit(1); }
		parent::__construct();
	}
	private function rows($sql, $parameters = array())
	{
		$query = $this->db->query($sql, $parameters);
		if ($query === FALSE) { throw new RuntimeException('Probe read failed.'); }
		return $query->result_array();
	}
	private function one($sql, $parameters)
	{
		$rows = $this->rows($sql, $parameters);
		if (count($rows) !== 1) { throw new RuntimeException('Exact fixture required.'); }
		return $rows[0];
	}
	private function digest($exclude_user = 0, $exclude_lesson = 0)
	{
		$tables = array('st_account_tokens', 'st_audit_events', 'st_categories', 'st_content_reviews', 'st_course_enrollments', 'st_course_sections', 'st_courses', 'st_entitlements', 'st_family_links', 'st_flashcard_decks', 'st_flashcard_progress', 'st_flashcards', 'st_lesson_images', 'st_lesson_progress', 'st_lessons', 'st_login_failures', 'st_matching_moves', 'st_matching_runs', 'st_package_courses', 'st_packages', 'st_payment_orders', 'st_practice_answers', 'st_practice_attempts', 'st_practice_sessions', 'st_question_options', 'st_questions', 'st_roles', 'st_session_questions', 'st_user_roles', 'st_users', 'st_wallet_entries', 'st_wallets', 'study_migrations');
		$actual = array_column($this->rows('SHOW TABLES'), 'Tables_in_db_study');
		sort($actual, SORT_STRING); $expected = $tables; sort($expected, SORT_STRING);
		if ($actual !== $expected) { throw new RuntimeException('Probe table inventory mismatch.'); }
		$digests = array();
		foreach ($tables as $table)
		{
			$rows = $this->rows('SELECT * FROM '.$table);
			$serialized = array();
			foreach ($rows as $row)
			{
				if ($table === 'st_lesson_progress' && (int) $row['learner_user_id'] === $exclude_user && (int) $row['lesson_id'] === $exclude_lesson) { continue; }
				$serialized[] = serialize($row);
			}
			sort($serialized, SORT_STRING);
			$digests[$table] = hash('sha256', serialize($serialized));
		}
		return $digests;
	}
	private function validate_target($user_id, $lesson_id)
	{
		$user = $this->one('SELECT username, account_kind, status FROM st_users WHERE id = ?', array($user_id));
		if ($user['account_kind'] !== 'adult' || $user['status'] !== 'active' || ! preg_match('/\Aqa_progress_([a-f0-9]{16})\z/', $user['username'], $matches)) { throw new RuntimeException('Only the exact synthetic learner is allowed.'); }
		$suffix = $matches[1];
		$lesson = $this->one('SELECT l.slug, l.is_preview, l.status, l.source_note, c.id AS course_id, c.slug AS course_slug, c.author_user_id, c.status AS course_status, c.is_free FROM st_lessons l JOIN st_course_sections s ON s.id = l.section_id JOIN st_courses c ON c.id = s.course_id WHERE l.id = ?', array($lesson_id));
		if ($lesson['slug'] !== 'qa-progress-next-lesson-'.$suffix || $lesson['course_slug'] !== 'qa-progress-course-'.$suffix || (int) $lesson['author_user_id'] !== $user_id || $lesson['status'] !== 'published' || $lesson['course_status'] !== 'published' || (int) $lesson['is_preview'] !== 0 || (int) $lesson['is_free'] !== 0 || $lesson['source_note'] !== 'Synthetic QA fixture') { throw new RuntimeException('Synthetic lesson/course mismatch.'); }
		$access = $this->one('SELECT ce.source_type, ce.status, ce.learner_user_id, ce.package_id, e.user_id, e.package_id AS entitlement_package_id, e.status AS entitlement_status, e.payment_order_id, p.slug, (e.starts_at <= UTC_TIMESTAMP() AND (e.ends_at IS NULL OR e.ends_at > UTC_TIMESTAMP())) AS current_access FROM st_course_enrollments ce JOIN st_entitlements e ON e.id = ce.entitlement_id JOIN st_packages p ON p.id = e.package_id WHERE ce.learner_user_id = ? AND ce.course_id = ?', array($user_id, (int) $lesson['course_id']));
		if ($access['source_type'] !== 'package' || $access['status'] !== 'active' || $access['entitlement_status'] !== 'active' || (int) $access['user_id'] !== $user_id || (int) $access['package_id'] !== (int) $access['entitlement_package_id'] || $access['payment_order_id'] !== NULL || $access['slug'] !== 'qa-progress-package-'.$suffix || (int) $access['current_access'] !== 1) { throw new RuntimeException('Exact synthetic entitlement required.'); }
		if ($this->rows('SELECT id FROM st_lesson_progress WHERE learner_user_id = ? AND lesson_id = ?', array($user_id, $lesson_id))) { throw new RuntimeException('Initial probe progress must be absent.'); }
	}
	private function probe($state, $user_id, $lesson_id)
	{
		$database = $this->db;
		$cases = array('begin', 'begin_throw', 'lesson', 'lesson_throw', 'access', 'access_throw', 'write', 'write_throw', 'pre_status', 'pre_status_throw', 'post_status', 'post_status_throw', 'commit', 'commit_throw', 'rollback', 'rollback_throw');
		$baseline = $this->digest();
		foreach (array('save_lesson_position', 'complete_lesson') as $operation)
		{
			foreach ($cases as $failure)
			{
				$this->phase = $state.'/'.$operation.'/'.$failure;
				if ($this->digest() !== $baseline) { throw new RuntimeException('Baseline changed before injection.'); }
				$debug = $database->db_debug;
				$proxy = new Reading_transaction_probe($database, $failure);
				$this->db = $proxy;
				$harness_rollback = FALSE;
				try
				{
					$result = $operation === 'complete_lesson' ? $this->Content_model->complete_lesson($user_id, $lesson_id) : $this->Content_model->save_lesson_position($user_id, $lesson_id, 6800);
				}
				finally
				{
					$this->db = $database;
					if ($database->trans_active())
					{
						$harness_rollback = TRUE;
						if ($database->trans_rollback() !== TRUE) { throw new RuntimeException('Explicit harness rollback failed.'); }
					}
					$database->reset_query();
				}
				$uncertain = in_array($failure, array('commit', 'commit_throw', 'rollback', 'rollback_throw'), TRUE);
				$failed_rollback = in_array($failure, array('rollback', 'rollback_throw'), TRUE);
				$began = ! in_array($failure, array('begin', 'begin_throw'), TRUE);
				$after_write = in_array($failure, array('post_status', 'post_status_throw', 'commit', 'commit_throw', 'rollback', 'rollback_throw'), TRUE);
				if ($result !== array('ok' => FALSE, 'code' => 503, 'failure' => $uncertain ? 'uncertain' : 'unavailable') || $proxy->failures !== 1 || $proxy->begins !== 1 || $proxy->rollbacks !== ($began ? 1 : 0) || $proxy->rollback_faults !== ($failed_rollback ? 1 : 0) || $proxy->writes !== ($after_write ? 1 : 0) || $proxy->unexpected_commits !== 0 || $proxy->work_after_failure !== 0 || $harness_rollback !== $failed_rollback || $database->trans_active() || $database->db_debug !== $debug) { throw new RuntimeException('Injection lifecycle mismatch.'); }
				if ($this->digest() !== $baseline) { throw new RuntimeException('Persistent rows changed after injected operation.'); }
			}
		}
		fwrite(STDOUT, 'PASS reading '.$state." 32 injected failures: all rows of 33 tables unchanged; exact fault hit, no forwarded COMMIT or data work after failure.\n");
	}
	public function index($user_id = '', $lesson_id = '')
	{
		try
		{
			if (PHP_VERSION_ID < 80100 || PHP_VERSION_ID >= 80200 || $this->db->database !== 'db_study' || $this->db->username !== 'study_app' || ! in_array($this->db->hostname, array('127.0.0.1', 'localhost', '::1'), TRUE) || ! preg_match('/\A[1-9][0-9]{0,9}\z/', (string) $user_id) || ! preg_match('/\A[1-9][0-9]{0,9}\z/', (string) $lesson_id) || $this->db->trans_active()) { throw new RuntimeException('Probe runtime or target mismatch.'); }
			$user_id = (int) $user_id; $lesson_id = (int) $lesson_id;
			$this->load->model('Content_model');
			$this->load->library('Study_transaction');
			$debug = $this->db->db_debug;
			$this->db->db_debug = FALSE;
			try
			{
				$this->validate_target($user_id, $lesson_id);
				$initial = $this->digest();
				$this->probe('empty', $user_id, $lesson_id);
				$this->phase = 'confirmed in-progress setup';
				if ($this->Content_model->save_lesson_position($user_id, $lesson_id, 3700) !== array('ok' => TRUE)) { throw new RuntimeException('Setup save failed.'); }
				$row = $this->one('SELECT status, last_position, completed_at FROM st_lesson_progress WHERE learner_user_id = ? AND lesson_id = ?', array($user_id, $lesson_id));
				if ($row['status'] !== 'in_progress' || (int) $row['last_position'] !== 3700 || $row['completed_at'] !== NULL) { throw new RuntimeException('Setup save state mismatch.'); }
				$this->probe('in_progress', $user_id, $lesson_id);
				$this->phase = 'confirmed completion setup';
				if ($this->Content_model->complete_lesson($user_id, $lesson_id) !== array('ok' => TRUE)) { throw new RuntimeException('Setup completion failed.'); }
				$row = $this->one('SELECT status, last_position, completed_at FROM st_lesson_progress WHERE learner_user_id = ? AND lesson_id = ?', array($user_id, $lesson_id));
				if ($row['status'] !== 'completed' || (int) $row['last_position'] !== 10000 || $row['completed_at'] === NULL) { throw new RuntimeException('Setup completion state mismatch.'); }
				$this->probe('completed', $user_id, $lesson_id);
				$this->phase = 'source preservation';
				if ($this->digest($user_id, $lesson_id) !== $initial) { throw new RuntimeException('Unrelated baseline rows changed.'); }
			}
			finally { $this->db->db_debug = $debug; }
			fwrite(STDOUT, "PASS 96 reading failure cases and original source digests. Two separate confirmed setup commits leave only the exact fixture progress row for owning-suite cleanup.\n");
			fwrite(STDOUT, "LIMITATION fault COMMIT never reaches the server; not actual disconnect/concurrency. Twelve rollback failures require explicit harness rollback; auto-increment may advance. Run this fixture suite alone.\n");
		}
		catch (Throwable $error)
		{
			fwrite(STDERR, 'FAIL reading transaction probe at '.$this->phase.". Owning suite remains responsible for fixture cleanup.\n");
			exit(1);
		}
	}
}

class Reading_transaction_probe
{
	private $database;
	private $failure;
	public $failures = 0;
	public $begins = 0;
	public $rollbacks = 0;
	public $rollback_faults = 0;
	public $unexpected_commits = 0;
	public $work_after_failure = 0;
	public $writes = 0;
	public function __construct($database, $failure)
	{
		$this->database = $database; $this->failure = $failure;
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
				if ($this->failure === 'rollback_throw') { throw new RuntimeException('Synthetic rollback failure.'); }
				return FALSE;
			}
		}
		if ($this->failures > 0 && in_array($name, array('query', 'get', 'insert', 'update', 'delete'), TRUE))
		{
			$this->work_after_failure++;
			throw new RuntimeException('Unexpected work after injected failure.');
		}
		$sql = $name === 'query' ? ($arguments[0] ?? '') : '';
		$point = preg_replace('/_throw\z/', '', $this->failure);
		$selected = ($point === 'begin' && $name === 'trans_begin') || ($point === 'commit' && $name === 'trans_commit');
		$selected = $selected || ($point === 'lesson' && strpos($sql, 'FROM st_lessons l') !== FALSE);
		$selected = $selected || ($point === 'access' && strpos($sql, 'FROM st_course_enrollments ce') !== FALSE);
		$selected = $selected || ($point === 'write' && strpos($sql, 'INSERT INTO st_lesson_progress') === 0);
		$selected = $selected || ($name === 'trans_status' && (($point === 'pre_status' && $this->writes === 0) || (in_array($point, array('post_status', 'rollback'), TRUE) && $this->writes > 0)));
		if ($selected && $this->failures === 0)
		{
			$this->failures++;
			if (substr($this->failure, -6) === '_throw' && $point !== 'rollback') { throw new RuntimeException('Synthetic transaction failure.'); }
			return FALSE;
		}
		if ($name === 'trans_commit') { $this->unexpected_commits++; return FALSE; }
		$result = call_user_func_array(array($this->database, $name), $arguments);
		if ($result !== FALSE && $name === 'query' && strpos($sql, 'INSERT INTO st_lesson_progress') === 0) { $this->writes++; }
		return $result === $this->database ? $this : $result;
	}
}
