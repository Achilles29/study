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
	public $study_transaction;
	public function __get($name)
	{
		if ( ! isset($this->services[$name])) { throw new RuntimeException('Unexpected assignment test dependency.'); }
		return $this->services[$name];
	}
}
$assignment_logs = array();
function log_message($level, $message)
{
	global $assignment_logs;
	$assignment_logs[] = array($level, $message);
}
require __DIR__.'/../application/models/Assignment_model.php';
require __DIR__.'/../application/models/Parent_practice_model.php';
if (is_file(__DIR__.'/../application/libraries/Study_transaction.php')) { require __DIR__.'/../application/libraries/Study_transaction.php'; }

class Assignment_transaction_result
{
	private $database;
	private $name;
	private $rows;
	public function __construct($database, $name, $rows)
	{
		$this->database = $database; $this->name = $name; $this->rows = $rows;
	}
	public function row_array()
	{
		return $this->database->operation('row.'.$this->name, $this->rows ? $this->rows[0] : array());
	}
	public function result_array()
	{
		return $this->database->operation('rows.'.$this->name, $this->rows);
	}
	public function num_rows()
	{
		return $this->database->operation('count.'.$this->name, count($this->rows));
	}
}
class Assignment_transaction_database
{
	public $db_debug = TRUE;
	public $calls = array();
	public $failures = array();
	public $session;
	public $locked_session;
	public $attempt = array();
	public $questions = array(array('snapshot_json' => '{"synthetic":true}'));
	public $access = TRUE;
	public $transaction_status = TRUE;
	public $transaction_active = FALSE;
	public $pending = array();
	public $committed = array();
	public $inserted_id = 701;
	private $conditions = array();
	public function __construct()
	{
		$this->session = array('id' => 51, 'created_by_user_id' => 21, 'learner_user_id' => 41, 'status' => 'available', 'available_from' => '2000-01-01 00:00:00', 'available_until' => '2100-01-01 00:00:00');
		$this->locked_session = $this->session;
	}
	public function operation($name, $value, $details = array())
	{
		$this->calls[] = array('name' => $name, 'debug' => $this->db_debug, 'details' => $details);
		if (isset($this->failures[$name]))
		{
			if ($this->failures[$name] === 'throw') { throw new RuntimeException('PRIVATE_SQL_ANSWER_TOKEN must not escape.'); }
			return FALSE;
		}
		return $value;
	}
	public function trans_active()
	{
		return $this->operation('active', $this->transaction_active);
	}
	public function trans_begin()
	{
		return $this->operation('begin', TRUE);
	}
	public function trans_status()
	{
		return $this->operation('status', $this->transaction_status);
	}
	public function trans_commit()
	{
		$result = $this->operation('commit', TRUE);
		if ($result) { $this->committed = $this->pending; $this->pending = array(); }
		return $result;
	}
	public function trans_rollback()
	{
		$result = $this->operation('rollback', TRUE);
		if ($result) { $this->pending = array(); }
		return $result;
	}
	public function query($sql, $parameters)
	{
		if (strpos($sql, 'SELECT s.*') === 0)
		{
			$name = substr($sql, -10) === 'FOR UPDATE' ? 'session_locked' : 'session_initial';
			$row = $name === 'session_locked' ? $this->locked_session : $this->session;
			$rows = $row ? array($row) : array();
		}
		elseif (strpos($sql, 'SELECT id FROM st_users') === 0) { $name = 'parent_lock'; $rows = array(array('id' => 21)); }
		elseif (strpos($sql, 'SELECT e.id FROM st_entitlements') === 0) { $name = 'entitlement'; $rows = $this->access ? array(array('id' => 31)) : array(); }
		elseif (strpos($sql, 'SELECT * FROM st_practice_attempts') === 0) { $name = 'attempt'; $rows = $this->attempt ? array($this->attempt) : array(); }
		elseif (strpos($sql, 'SELECT * FROM st_session_questions') === 0) { $name = 'snapshots'; $rows = $this->questions; }
		else { throw new RuntimeException('Unexpected assignment SQL.'); }
		if ($this->operation('query.'.$name, TRUE, array('sql' => $sql, 'parameters' => $parameters)) === FALSE) { return FALSE; }
		return new Assignment_transaction_result($this, $name, $rows);
	}
	public function where($field, $value)
	{
		$this->conditions[$field] = $value; return $this;
	}
	public function get($table)
	{
		$name = $table === 'st_practice_attempts' ? 'attempt' : ($table === 'st_session_questions' ? 'snapshots' : 'unexpected');
		if ($name === 'unexpected') { throw new RuntimeException('Unexpected assignment read table.'); }
		$conditions = $this->conditions; $this->conditions = array();
		if ($this->operation('get.'.$name, TRUE, $conditions) === FALSE) { return FALSE; }
		return new Assignment_transaction_result($this, $name, $name === 'attempt' ? ($this->attempt ? array($this->attempt) : array()) : $this->questions);
	}
	public function insert($table, $data)
	{
		$name = $table === 'st_practice_attempts' ? 'attempt' : ($table === 'st_audit_events' ? 'audit' : 'unexpected');
		if ($name === 'unexpected') { throw new RuntimeException('Unexpected assignment insert table.'); }
		$result = $this->operation('insert.'.$name, TRUE, $data);
		if ($result) { $this->pending[] = array('table' => $table, 'data' => $data); }
		return $result;
	}
	public function insert_id()
	{
		return $this->operation('insert_id', $this->inserted_id);
	}
	public function update($table, $data)
	{
		if ($table !== 'st_practice_sessions') { throw new RuntimeException('Unexpected assignment update table.'); }
		$conditions = $this->conditions; $this->conditions = array();
		$result = $this->operation('update.session', TRUE, array('where' => $conditions, 'data' => $data));
		if ($result) { $this->pending[] = array('table' => $table, 'data' => $data); }
		return $result;
	}
}
class Assignment_transaction_loader
{
	private $model;
	public function __construct($model)
	{
		$this->model = $model;
	}
	public function model($name)
	{
		if ($name !== 'Parent_practice_model') { throw new RuntimeException('Unexpected model load.'); }
		$this->model->services['Parent_practice_model'] = new Parent_practice_model();
		$this->model->Parent_practice_model->db = $this->model->db;
	}
	public function library($name)
	{
		if (strtolower($name) !== 'study_transaction' || ! class_exists('Study_transaction')) { throw new RuntimeException('Expected shared transaction library is unavailable.'); }
		$this->model->study_transaction = new Study_transaction();
	}
}
function assignment_fixture()
{
	$model = new Assignment_model();
	$model->db = new Assignment_transaction_database();
	$model->load = new Assignment_transaction_loader($model);
	return $model;
}
$assignment_assertions = 0;
function assignment_assert($condition, $label)
{
	global $assignment_assertions;
	$assignment_assertions++;
	if ( ! $condition) { throw new RuntimeException($label); }
}
function assignment_names($database)
{
	return array_column($database->calls, 'name');
}
function assignment_failure($result, $failure)
{
	assignment_assert($result === array('ok' => FALSE, 'code' => 503, 'failure' => $failure), 'Database failure must have the exact safe unavailable/uncertain response.');
}
function assignment_no_work_after($database, $name)
{
	$names = assignment_names($database); $index = array_search($name, $names, TRUE);
	assignment_assert($index !== FALSE, 'Injected failure point must actually be reached.');
	foreach (array_slice($names, $index + 1) as $later)
	{
		assignment_assert(in_array($later, array('rollback'), TRUE), 'Only checked rollback may follow the first failed operation.');
	}
}

if (in_array('--red-commit', $argv, TRUE))
{
	$model = assignment_fixture(); $model->db->attempt = array('id' => 701, 'status' => 'in_progress'); $model->db->failures['commit'] = 'false';
	assignment_failure($model->start(51, 41), 'uncertain');
	fwrite(STDOUT, "PASS rejected unconfirmed assignment commit.\n");
	exit(0);
}

foreach (array(TRUE, FALSE) as $debug)
{
	$model = assignment_fixture(); $model->db->db_debug = $debug;
	$result = $model->start(51, 41);
	assignment_assert($result === array('ok' => TRUE, 'id' => 701, 'status' => 'in_progress'), 'A confirmed new assignment start must return its committed status and ID.');
	$names = assignment_names($model->db);
	assignment_assert(array_slice($names, 0, 3) === array('active', 'begin', 'query.session_initial') && end($names) === 'commit', 'All initial reads must occur after nested-transaction rejection and checked BEGIN, and before confirmed COMMIT.');
	assignment_assert($model->db->db_debug === $debug, 'Original database debug setting must be restored after success.');
	foreach ($model->db->calls as $call) { assignment_assert($call['debug'] === FALSE, 'Database debug must be suppressed throughout the protected operation.'); }
	assignment_assert(count($model->db->committed) === 3 && $model->db->pending === array(), 'New start commits exactly the attempt, session state and audit.');
	$audit = $model->db->committed[2]['data'];
	assignment_assert($audit['event_type'] === 'practice.started' && $audit['entity_type'] === 'practice_attempt' && $audit['entity_id'] === 701 && $audit['actor_user_id'] === 41 && $audit['details_json'] === '{}', 'Audit must retain safe existing IDs/event without answers or tokens.');
	foreach ($model->db->calls as $call)
	{
		if ($call['name'] === 'query.session_initial' || $call['name'] === 'query.session_locked')
		{
			assignment_assert($call['details']['parameters'] === array(41, 51), 'Session must stay scoped to the bound child and session IDs.');
			foreach (array("l.status = 'active'", "p.status = 'active'", "c.status = 'active'", "s.session_type = 'parent_assigned'") as $scope) { assignment_assert(strpos($call['details']['sql'], $scope) !== FALSE, 'Active family/account scope must be preserved.'); }
		}
		if ($call['name'] === 'query.parent_lock') { assignment_assert($call['details']['parameters'] === array(21), 'Parent row lock must use the session parent identity.'); }
		if ($call['name'] === 'query.entitlement') { assignment_assert(substr($call['details']['sql'], -10) === 'FOR UPDATE' && $call['details']['parameters'] === array(21), 'Capability/entitlement read must remain locked and parent-scoped.'); }
		if ($call['name'] === 'query.attempt') { assignment_assert($call['details']['parameters'] === array(51, 41) && $call['details']['sql'] === 'SELECT * FROM st_practice_attempts WHERE session_id = ? AND learner_user_id = ? FOR UPDATE', 'Replay must be scoped to the same child/session using a current locking read after the parent/session lock.'); }
		if ($call['name'] === 'query.snapshots') { assignment_assert($call['details']['parameters'] === array(51) && $call['details']['sql'] === 'SELECT * FROM st_session_questions WHERE session_id = ? FOR UPDATE', 'Snapshot validation must use a current locking read scoped to this session.'); }
	}
	assignment_assert(array_search('query.parent_lock', $names, TRUE) < array_search('query.session_locked', $names, TRUE) && array_search('query.session_locked', $names, TRUE) < array_search('query.attempt', $names, TRUE), 'Lock ordering must remain parent, current session, then current attempt.');
}

foreach (array('in_progress', 'graded') as $status)
{
	$model = assignment_fixture(); $model->db->attempt = array('id' => 801, 'status' => $status);
	if ($status === 'graded') { $model->db->locked_session['status'] = 'cancelled'; $model->db->access = FALSE; }
	assignment_assert($model->start(51, 41) === array('ok' => TRUE, 'id' => 801, 'status' => $status), 'Replay must return the existing status without recreating or downgrading it.');
	assignment_assert($model->db->committed === array() && ! in_array('query.snapshots', assignment_names($model->db), TRUE), 'Replay must not recreate snapshots or emit a start audit.');
	if ($status === 'graded') { assignment_assert( ! in_array('query.entitlement', assignment_names($model->db), TRUE), 'Graded replay remains readable without restoring expired work entitlement.'); }
}

foreach (array('missing_initial', 'missing_locked', 'cancelled', 'future', 'expired', 'missing_schedule', 'denied_capability', 'empty_snapshots', 'too_many_snapshots', 'null_snapshot') as $case)
{
	$model = assignment_fixture(); $expected = 403;
	if ($case === 'missing_initial') { $model->db->session = array(); $expected = 404; }
	elseif ($case === 'missing_locked') { $model->db->locked_session = array(); $expected = 404; }
	elseif ($case === 'cancelled') { $model->db->locked_session['status'] = 'cancelled'; }
	elseif ($case === 'future') { $model->db->locked_session['available_from'] = '2100-01-01 00:00:00'; }
	elseif ($case === 'expired') { $model->db->locked_session['available_until'] = '2000-01-01 00:00:00'; }
	elseif ($case === 'missing_schedule') { $model->db->locked_session['available_from'] = NULL; }
	elseif ($case === 'denied_capability') { $model->db->access = FALSE; }
	elseif ($case === 'empty_snapshots') { $model->db->questions = array(); $expected = 409; }
	elseif ($case === 'too_many_snapshots') { $model->db->questions = array_fill(0, 51, array('snapshot_json' => '{}')); $expected = 409; }
	elseif ($case === 'null_snapshot') { $model->db->questions = array(array('snapshot_json' => NULL)); $expected = 409; }
	assignment_assert($model->start(51, 41) === array('ok' => FALSE, 'code' => $expected), 'Existing family/schedule/snapshot domain refusal must retain its code.');
	$names = assignment_names($model->db);
	assignment_assert(end($names) === 'rollback' && ! in_array('commit', $names, TRUE) && $model->db->committed === array() && $model->db->pending === array(), 'Domain refusal must confirm rollback and perform no write/commit.');
	assignment_assert($model->db->db_debug === TRUE, 'Debug must be restored after domain refusal.');
}

$failure_points = array('begin', 'query.session_initial', 'query.parent_lock', 'query.session_locked', 'query.attempt', 'query.entitlement', 'query.snapshots', 'insert.attempt', 'insert_id', 'update.session', 'insert.audit');
foreach ($failure_points as $point)
{
	foreach (array('false', 'throw') as $mode)
	{
		$model = assignment_fixture(); $model->db->failures[$point] = $mode;
		assignment_failure($model->start(51, 41), 'unavailable');
		assignment_no_work_after($model->db, $point);
		assignment_assert($model->db->committed === array() && $model->db->pending === array() && $model->db->db_debug === TRUE, 'Confirmed rollback/no-BEGIN must preserve no committed changes and restore debug.');
	}
}
foreach (array('row.session_initial', 'row.session_locked', 'row.attempt', 'count.entitlement', 'rows.snapshots') as $point)
{
	$model = assignment_fixture(); $model->db->failures[$point] = 'throw';
	assignment_failure($model->start(51, 41), 'unavailable'); assignment_no_work_after($model->db, $point);
	assignment_assert($model->db->committed === array() && $model->db->pending === array() && $model->db->db_debug === TRUE, 'Result decoding failures must roll back and restore debug.');
}
foreach (array('false', 'throw') as $mode)
{
	$model = assignment_fixture(); $model->db->failures['commit'] = $mode;
	assignment_failure($model->start(51, 41), 'uncertain');
	$names = assignment_names($model->db);
	assignment_assert(end($names) === 'rollback' && count(array_keys($names, 'commit', TRUE)) === 1, 'Unconfirmed COMMIT permits cleanup rollback but must never retry or become a success claim.');
	assignment_no_work_after($model->db, 'commit');
	assignment_assert($model->db->db_debug === TRUE, 'Debug must be restored after uncertain commit.');
	foreach (array('domain', 'write') as $origin)
	{
		$model = assignment_fixture(); $model->db->failures['rollback'] = $mode;
		if ($origin === 'domain') { $model->db->locked_session = array(); } else { $model->db->failures['insert.audit'] = 'false'; }
		assignment_failure($model->start(51, 41), 'uncertain');
		$names = assignment_names($model->db);
		assignment_assert(end($names) === 'rollback' && ! in_array('commit', $names, TRUE) && $model->db->db_debug === TRUE, 'Unconfirmed ROLLBACK must stop, restore debug and remain uncertain.');
	}
}
$model = assignment_fixture(); $model->db->transaction_status = FALSE;
assignment_failure($model->start(51, 41), 'unavailable');
assignment_assert($model->db->pending === array() && $model->db->committed === array() && ! in_array('commit', assignment_names($model->db), TRUE), 'Failed transaction status must roll back all pending writes, never commit.');
$model = assignment_fixture(); $model->db->transaction_active = TRUE;
assignment_failure($model->start(51, 41), 'unavailable');
assignment_assert(assignment_names($model->db) === array('active') && $model->db->db_debug === TRUE, 'Existing outer transaction must be left untouched without reads, begin, commit or rollback.');
$model = assignment_fixture(); $model->db->failures['active'] = 'throw';
assignment_failure($model->start(51, 41), 'unavailable');
assignment_assert(assignment_names($model->db) === array('active') && $model->db->db_debug === TRUE, 'Failed transaction ownership inspection must stop before begin and restore debug.');
foreach ($assignment_logs as $entry) { assignment_assert(strpos($entry[1], 'PRIVATE_SQL_ANSWER_TOKEN') === FALSE, 'New transaction diagnostics must not log exception payloads.'); }
restore_error_handler();
fwrite(STDOUT, 'PASS assignment transactions: '.$assignment_assertions." assertions for checked boundaries, query/write/audit failure stops, rollback certainty, scope/schedule/capability, snapshot limits and replay statuses. Recording fake database; not real DB, concurrent requests or network-loss proof.\n");
