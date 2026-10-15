<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

class CI_Model
{
	public $db;
	public $load;
	public $services = array();

	public function __get($name)
	{
		if ( ! isset($this->services[$name])) { throw new RuntimeException('Unexpected practice test dependency.'); }
		return $this->services[$name];
	}
}
require __DIR__.'/../application/models/Practice_model.php';
require __DIR__.'/../application/libraries/Study_transaction.php';
require __DIR__.'/../application/libraries/Practice_scoring.php';

$practice_transaction_logs = array();
function log_message($level, $message)
{
	global $practice_transaction_logs;
	$practice_transaction_logs[] = array($level, $message);
}

class Practice_transaction_result
{
	private $database;
	private $label;
	private $value;

	public function __construct($database, $label, $value)
	{
		$this->database = $database;
		$this->label = $label;
		$this->value = $value;
	}

	public function row_array()
	{
		$this->database->hit('read:'.$this->label);
		return $this->value;
	}

	public function result_array()
	{
		$this->database->hit('read:'.$this->label);
		return $this->value;
	}
}

class Practice_transaction_database
{
	public $db_debug = TRUE;
	public $active = FALSE;
	public $healthy = TRUE;
	public $calls = array();
	public $failure_at = 0;
	public $failure_mode = 'false';
	public $failed = FALSE;
	public $poison_on_failure = TRUE;
	public $rollback_mode = 'success';
	public $commit_ack_lost = FALSE;
	public $pending = array();
	public $durable = array();
	public $course = array('id' => 61, 'status' => 'published', 'title' => 'Synthetic course');
	public $attempt;
	public $locked;
	public $previous = array();
	public $existing_active = array();
	public $questions;
	public $snapshots;
	public $allow_course = TRUE;
	public $allow_family = TRUE;
	public $ready = TRUE;
	public $session = array('id' => 51, 'status' => 'in_progress');
	private $last_insert;

	public function __construct()
	{
		$this->attempt = array('id' => 31, 'session_id' => 51, 'learner_user_id' => 41, 'course_id' => 61, 'created_by_user_id' => 71, 'session_type' => 'course_quiz', 'status' => 'in_progress', 'rules_version' => 'equal_weight_v1', 'answer_revision' => 3, 'question_count' => 2);
		$this->locked = $this->attempt;
		$this->questions = array();
		$this->snapshots = array();
		foreach (array(101, 102) as $question_id)
		{
			$options = array();
			for ($index = 0; $index < 4; $index++) { $options[] = array('id' => $question_id * 10 + $index, 'option_text' => 'Synthetic option '.$index, 'is_correct' => $index === 0 ? 1 : 0); }
			$question = array('id' => $question_id, 'status' => 'published', 'prompt' => 'Synthetic private question', 'explanation' => 'Synthetic private explanation', 'content_version' => 2, 'options' => $options);
			$this->questions[] = $question;
			$this->snapshots[] = array('question_id' => $question_id, 'snapshot_json' => json_encode($question));
		}
	}

	public function hit($name, $data = array())
	{
		$this->calls[] = array('name' => $name, 'data' => $data, 'debug' => $this->db_debug, 'active' => $this->active);
		if ($this->failure_at !== count($this->calls)) { return TRUE; }
		$this->failed = TRUE;
		if ($this->poison_on_failure) { $this->healthy = FALSE; }
		if ($this->failure_mode === 'throw') { throw new RuntimeException('Synthetic private driver text must not escape.'); }
		return FALSE;
	}

	public function trans_active()
	{
		$this->hit('active');
		return $this->active;
	}

	public function trans_begin()
	{
		if ( ! $this->hit('begin')) { return FALSE; }
		$this->active = TRUE;
		return TRUE;
	}

	public function trans_status()
	{
		return $this->hit('status') && $this->healthy;
	}

	public function trans_commit()
	{
		if ( ! $this->hit('commit')) { return FALSE; }
		$this->durable = array_merge($this->durable, $this->pending);
		$this->pending = array();
		$this->active = FALSE;
		return ! $this->commit_ack_lost;
	}

	public function trans_rollback()
	{
		$this->hit('rollback');
		if ($this->rollback_mode === 'throw') { throw new RuntimeException('Synthetic private rollback detail.'); }
		if ($this->rollback_mode === 'false') { return FALSE; }
		$this->pending = array();
		$this->active = FALSE;
		return TRUE;
	}

	public function query($sql, $parameters)
	{
		if (strpos($sql, 'SELECT a.*,') === 0) { $name = 'attempt'; $value = $this->attempt; }
		elseif (strpos($sql, 'SELECT * FROM st_courses') === 0) { $name = 'course'; $value = $this->course; }
		elseif (strpos($sql, 'SELECT * FROM st_practice_attempts') === 0) { $name = 'locked'; $value = $this->locked; }
		elseif (strpos($sql, 's.idempotency_key = ?') !== FALSE) { $name = 'previous'; $value = $this->previous; }
		elseif (strpos($sql, "a.status = 'in_progress'") !== FALSE) { $name = 'existing_active'; $value = $this->existing_active; }
		elseif (strpos($sql, 'INSERT INTO st_practice_answers ') === 0)
		{
			$name = 'answer:'.$parameters[1];
			if ( ! $this->hit($name, array($sql, $parameters))) { return FALSE; }
			$this->pending[] = array($name, $parameters);
			return TRUE;
		}
		else { throw new RuntimeException('Unexpected SQL shape in isolated practice test.'); }
		if ( ! $this->hit('query:'.$name, array($sql, $parameters))) { return FALSE; }
		return new Practice_transaction_result($this, $name, $value);
	}

	public function where($key, $value)
	{
		$this->hit('where', array($key, $value));
		return $this;
	}

	public function order_by($key, $direction)
	{
		$this->hit('order_by', array($key, $direction));
		return $this;
	}

	public function get($table)
	{
		if ($table !== 'st_session_questions') { throw new RuntimeException('Unexpected practice read table.'); }
		if ( ! $this->hit('query:snapshots')) { return FALSE; }
		return new Practice_transaction_result($this, 'snapshots', $this->snapshots);
	}

	public function insert($table, $data)
	{
		$name = 'insert:'.$table.($table === 'st_session_questions' ? ':'.$data['question_id'] : '');
		if ( ! $this->hit($name, $data)) { return FALSE; }
		$this->pending[] = array($name, $data);
		$this->last_insert = $table;
		return TRUE;
	}

	public function insert_id()
	{
		if ( ! $this->hit('id:'.$this->last_insert)) { return 0; }
		return $this->last_insert === 'st_practice_sessions' ? 51 : 31;
	}

	public function update($table, $data)
	{
		$name = 'update:'.$table;
		if ( ! $this->hit($name, $data)) { return FALSE; }
		$this->pending[] = array($name, $data);
		return TRUE;
	}
}

class Practice_transaction_dependencies
{
	private $database;

	public function __construct($database)
	{
		$this->database = $database;
	}
	public function can_access_course($user_id, $course, $lock = FALSE)
	{
		return $this->database->hit('dependency:course_access', array($user_id, $course, $lock)) && $this->database->allow_course;
	}
	public function list_for_course($course_id)
	{
		return $this->database->hit('dependency:questions', array($course_id)) ? $this->database->questions : FALSE;
	}
	public function ready_for_review($course_id)
	{
		return $this->database->hit('dependency:ready', array($course_id)) && $this->database->ready;
	}
	public function lock_for_child($session_id, $user_id, $parent_id)
	{
		return $this->database->hit('dependency:family_lock', array($session_id, $user_id, $parent_id)) ? $this->database->session : FALSE;
	}
	public function can_work($session, $lock)
	{
		return $this->database->hit('dependency:family_access', array($session, $lock)) && $session && $this->database->allow_family;
	}
}

class Practice_transaction_loader
{
	public $calls = array();
	public function library($name)
	{
		if ( ! in_array($name, array('Study_transaction', 'Practice_scoring'), TRUE)) { throw new RuntimeException('Unexpected practice test library.'); }
		$this->calls[] = array('library', $name);
	}
	public function model($name)
	{
		if ( ! in_array($name, array('Content_model', 'Question_model', 'Assignment_model'), TRUE)) { throw new RuntimeException('Unexpected practice test model.'); }
		$this->calls[] = array('model', $name);
	}
}

function practice_transaction_model($database)
{
	$model = new Practice_model();
	$model->db = $database;
	$model->load = new Practice_transaction_loader();
	$dependencies = new Practice_transaction_dependencies($database);
	$model->services = array('study_transaction' => new Study_transaction(), 'practice_scoring' => new Practice_scoring(), 'Content_model' => $dependencies, 'Question_model' => $dependencies, 'Assignment_model' => $dependencies);
	return $model;
}

$assertions = 0;
function practice_transaction_assert($condition, $message)
{
	global $assertions;
	if ( ! $condition) { throw new RuntimeException($message); }
	$assertions++;
}
function practice_transaction_run($model, $operation)
{
	return $operation === 'start' ? $model->start(61, 41, str_repeat('a', 64)) : $model->save_answers(31, 41, 3, array(101 => '1010', 102 => ''), $operation === 'finish');
}
function practice_transaction_names($database)
{
	return array_column($database->calls, 'name');
}

$unavailable = array('ok' => FALSE, 'code' => 503, 'failure' => 'unavailable');
$uncertain = array('ok' => FALSE, 'code' => 503, 'failure' => 'uncertain');
$injected = 0;
foreach (array('start', 'save', 'finish', 'family_save', 'family_finish') as $path)
{
	$operation = strpos($path, 'family_') === 0 ? substr($path, 7) : $path;
	$baseline = new Practice_transaction_database();
	if (strpos($path, 'family_') === 0) { $baseline->attempt['session_type'] = 'parent_assigned'; $baseline->locked = $baseline->attempt; }
	$model = practice_transaction_model($baseline);
	$result = practice_transaction_run($model, $operation);
	practice_transaction_assert($result === array('ok' => TRUE, 'id' => 31, 'status' => $operation === 'finish' ? 'graded' : 'in_progress'), $path.': successful operation must return committed ID and authoritative status.');
	practice_transaction_assert($baseline->db_debug === TRUE && ! $baseline->active && $baseline->pending === array() && count($baseline->durable) > 0, $path.': success must confirm commit and restore debug.');
	$names = practice_transaction_names($baseline);
	practice_transaction_assert($names[0] === 'active' && $names[1] === 'begin' && end($names) === 'commit' && ! in_array('rollback', $names, TRUE), $path.': shared wrapper must own transaction boundaries.');
	foreach ($baseline->calls as $call) { practice_transaction_assert($call['debug'] === FALSE, 'All operations must run with debug display disabled.'); }
	foreach ($baseline->calls as $call)
	{
		if ($call['name'] === 'dependency:course_access')
		{
			practice_transaction_assert($call['data'][2] === TRUE, 'Mutating practice access checks must request a current locked enrollment read.');
		}
	}
	if ($operation !== 'start')
	{
		practice_transaction_assert(array_search('query:attempt', $names, TRUE) > array_search('begin', $names, TRUE), 'Initial save lookup must be inside the guarded transaction.');
		practice_transaction_assert(array_search($path === 'family_save' || $path === 'family_finish' ? 'dependency:family_lock' : 'query:course', $names, TRUE) < array_search('query:locked', $names, TRUE), 'Existing course/parent then attempt lock order must remain unchanged.');
		$answer_writes = array_values(array_filter($baseline->durable, static function ($write) {
			return strpos($write[0], 'answer:') === 0;
		}));
		practice_transaction_assert(count($answer_writes) === 2 && array_slice($answer_writes[0][1], 0, 4) === array(31, 101, 1010, $operation === 'finish' ? 1 : NULL) && array_slice($answer_writes[1][1], 0, 4) === array(31, 102, NULL, $operation === 'finish' ? 0 : NULL), 'Saved answers/blank state and grading visibility must remain unchanged.');
		$attempt_write = end($baseline->durable);
		practice_transaction_assert($attempt_write[0] === 'update:st_practice_attempts' && $attempt_write[1]['answer_revision'] === 4, 'Each actual save/final must advance the existing revision exactly once.');
		if ($operation === 'finish') { practice_transaction_assert($attempt_write[1]['score'] === '50.00' && $attempt_write[1]['correct_count'] === 1 && $attempt_write[1]['status'] === 'graded', 'Final grading must preserve the equal-weight snapshot score.'); }
	}
	foreach ($baseline->calls as $index => $call)
	{
		if (in_array($call['name'], array('where', 'order_by'), TRUE)) { continue; }
		$modes = $call['name'] === 'active' || strpos($call['name'], 'read:') === 0 ? array('throw') : array('false', 'throw');
		foreach ($modes as $failure_mode)
		{
			$database = new Practice_transaction_database();
			if (strpos($path, 'family_') === 0) { $database->attempt['session_type'] = 'parent_assigned'; $database->locked = $database->attempt; }
			$database->failure_at = $index + 1;
			$database->failure_mode = $failure_mode;
			$database->db_debug = $failure_mode === 'false';
			$model = practice_transaction_model($database);
			$result = practice_transaction_run($model, $operation);
			practice_transaction_assert($database->failed && $result === ($call['name'] === 'commit' ? $uncertain : $unavailable), $path.'/'.$call['name'].': injected failure must not report success or an ordinary domain rejection.');
			practice_transaction_assert($database->db_debug === ($failure_mode === 'false') && $database->durable === array() && $database->pending === array(), 'Confirmed fake rollback must restore debug and leave no durable/pending writes.');
			$after = array_slice(practice_transaction_names($database), $index + 1);
			$after_operations = array_values(array_filter($after, static function ($name) {
				return $name !== 'status';
			}));
			practice_transaction_assert($after_operations === (in_array($call['name'], array('active', 'begin'), TRUE) ? array() : array('rollback')), $path.'/'.$call['name'].': no read/write/dependency work may continue after the first failure.');
			$injected++;
		}
	}
}

// A query/write returning FALSE is checked immediately even if a fake driver
// does not set its transaction flag. This is stronger than relying on final status.
foreach (array('query:course', 'insert:st_practice_sessions', 'insert:st_session_questions:101', 'insert:st_practice_attempts', 'insert:st_audit_events') as $target)
{
	$baseline = new Practice_transaction_database();
	practice_transaction_run(practice_transaction_model($baseline), 'start');
	$database = new Practice_transaction_database();
	$database->failure_at = array_search($target, practice_transaction_names($baseline), TRUE) + 1;
	$database->poison_on_failure = FALSE;
	practice_transaction_assert(practice_transaction_run(practice_transaction_model($database), 'start') === $unavailable && $database->failed, 'Explicit read/write FALSE must fail even without a poisoned transaction flag.');
	practice_transaction_assert(array_slice(practice_transaction_names($database), $database->failure_at) === array('rollback'), 'Unchecked FALSE must not permit subsequent operations.');
}

foreach (array('previous', 'existing_active', 'initial_graded', 'locked_graded') as $replay)
{
	$database = new Practice_transaction_database();
	if ($replay === 'previous') { $database->previous = array('id' => 39, 'status' => 'graded'); }
	if ($replay === 'existing_active') { $database->existing_active = array('id' => 31, 'status' => 'in_progress'); }
	if ($replay === 'initial_graded') { $database->attempt['status'] = 'graded'; $database->allow_course = FALSE; }
	if ($replay === 'locked_graded') { $database->locked['status'] = 'graded'; }
	$operation = in_array($replay, array('previous', 'existing_active'), TRUE) ? 'start' : 'finish';
	$result = practice_transaction_run(practice_transaction_model($database), $operation);
	practice_transaction_assert($result === array('ok' => TRUE, 'id' => $replay === 'previous' ? 39 : 31, 'status' => $replay === 'existing_active' ? 'in_progress' : 'graded'), 'Replay must return the existing database status instead of inventing a new active attempt.');
	practice_transaction_assert($database->durable === array() && end($database->calls)['name'] === 'commit', 'Replay must confirm commit without writing answer, attempt or audit rows.');
	foreach ($database->calls as $call)
	{
		if (in_array($call['name'], array('query:previous', 'query:existing_active'), TRUE)) { practice_transaction_assert(strpos($call['data'][0], 'SELECT a.id, a.status ') === 0, 'Start replay query must select authoritative status with its ID.'); }
	}
}

foreach (array('missing_attempt' => 404, 'missing_lock' => 404, 'denied_course' => 403, 'denied_family' => 403, 'revision' => 409, 'bad_snapshot' => 409, 'question_count' => 409, 'empty_questions' => 409, 'unready_questions' => 409, 'unpublished_question' => 409) as $case => $code)
{
	$database = new Practice_transaction_database();
	$operation = 'save';
	if ($case === 'missing_attempt') { $database->attempt = array(); }
	if ($case === 'missing_lock') { $database->locked = array(); }
	if ($case === 'denied_course') { $database->allow_course = FALSE; }
	if ($case === 'denied_family') { $database->attempt['session_type'] = 'parent_assigned'; $database->allow_family = FALSE; }
	if ($case === 'revision') { $database->locked['answer_revision'] = 4; }
	if ($case === 'bad_snapshot') { $database->snapshots[0]['snapshot_json'] = '{'; }
	if ($case === 'question_count') { $database->locked['question_count'] = 3; }
	if ($case === 'empty_questions') { $operation = 'start'; $database->questions = array(); }
	if ($case === 'unready_questions') { $operation = 'start'; $database->ready = FALSE; }
	if ($case === 'unpublished_question') { $operation = 'start'; $database->questions[0]['status'] = 'draft'; }
	practice_transaction_assert(practice_transaction_run(practice_transaction_model($database), $operation) === array('ok' => FALSE, 'code' => $code), 'Confirmed rollback must preserve the existing domain failure code.');
	practice_transaction_assert($database->durable === array() && $database->pending === array() && end($database->calls)['name'] === 'rollback', 'Domain failure must roll back without writing or committing.');
}
foreach (array(array(999 => '1010'), array(101 => '1020'), array(101 => array('1010'))) as $invalid_answers)
{
	$database = new Practice_transaction_database();
	practice_transaction_assert(practice_transaction_model($database)->save_answers(31, 41, 3, $invalid_answers, FALSE) === array('ok' => FALSE, 'code' => 400) && $database->durable === array() && $database->pending === array(), 'Malformed/foreign options must retain a rollback-confirmed 400 without writes.');
}

$database = new Practice_transaction_database();
$database->questions[0]['prompt'] = "Invalid UTF-8 \xFF";
practice_transaction_assert(practice_transaction_run(practice_transaction_model($database), 'start') === $unavailable && $database->durable === array() && $database->pending === array() && end($database->calls)['name'] === 'rollback', 'Snapshot JSON exceptions after session insertion must roll back and return unavailable.');

foreach (array('false', 'throw') as $rollback_mode)
{
	$database = new Practice_transaction_database();
	$database->locked['answer_revision'] = 4;
	$database->rollback_mode = $rollback_mode;
	practice_transaction_assert(practice_transaction_run(practice_transaction_model($database), 'save') === $uncertain, 'Unconfirmed rollback must replace ordinary conflict with an uncertain result.');
}
$database = new Practice_transaction_database();
$database->commit_ack_lost = TRUE;
practice_transaction_assert(practice_transaction_run(practice_transaction_model($database), 'finish') === $uncertain && count($database->durable) > 0, 'A simulated applied commit with lost acknowledgement must remain uncertain, even after rollback reports success.');
$database = new Practice_transaction_database();
$database->active = TRUE;
practice_transaction_assert(practice_transaction_run(practice_transaction_model($database), 'save') === $unavailable && practice_transaction_names($database) === array('active') && $database->active, 'An existing outer transaction must remain untouched rather than claiming a nested commit is durable.');
$database = new Practice_transaction_database();
$model = practice_transaction_model($database);
practice_transaction_assert($model->course_access(41, $database->course) === TRUE && $database->calls[0]['data'][2] === FALSE && practice_transaction_names($database) === array('dependency:course_access'), 'Display-only access checks must retain the default nonlocking read.');

foreach ($practice_transaction_logs as $entry)
{
	practice_transaction_assert($entry[0] === 'error' && in_array($entry[1], array('Study transaction failed; inspect service availability.', 'Study transaction rollback could not be confirmed.'), TRUE), 'New model/wrapper diagnostics must be generic without SQL, questions, tokens or exception text.');
}
restore_error_handler();
fwrite(STDOUT, 'PASS '.$assertions.' practice transaction assertions, '.$injected." injected boundary/read/write/dependency failures, replay/domain/snapshot and uncertain-result controls.\n");
fwrite(STDOUT, "LIMITATION actual model/shared wrapper with recording fakes; no real database rollback, SQL execution, network disconnect, concurrency, HTTP or driver-log sanitization proof.\n");
