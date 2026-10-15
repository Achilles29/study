<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line); });
class CI_Model
{
	public $db;
	public $load;
	public $study_transaction;
}
function log_message($level, $message)
{}
require __DIR__.'/../application/libraries/Study_transaction.php';
require __DIR__.'/../application/models/Content_model.php';
class Reading_test_loader
{
	public function library($name)
	{
		if ($name !== 'Study_transaction') { throw new LogicException('Unexpected library.'); } }
}
class Reading_test_result
{
	private $database;
	private $kind;
	public function __construct($database, $kind)
	{
		$this->database = $database; $this->kind = $kind; }
	public function row_array()
	{
		$this->database->boundary('row');
		return $this->database->missing ? NULL : array('id' => 17, 'course_id' => 27, 'is_preview' => 0, 'is_free' => 0);
	}
	public function num_rows()
	{
		$this->database->boundary('access_rows'); return $this->database->denied ? 0 : 1; }
}
class Reading_test_database
{
	public $db_debug = TRUE;
	public $calls = array();
	public $sql = array();
	public $fault = '';
	public $throw = FALSE;
	public $missing = FALSE;
	public $denied = FALSE;
	public $nested = FALSE;
	public function boundary($name)
	{
		$this->calls[] = $name;
		if ($name !== $this->fault) { return TRUE; }
		if ($this->throw) { throw new RuntimeException('Synthetic reading failure.'); }
		return FALSE;
	}
	public function trans_active()
	{
		$this->boundary('active'); return $this->nested; }
	public function trans_begin()
	{
		return $this->boundary('begin'); }
	public function trans_status()
	{
		return $this->boundary('status'); }
	public function trans_commit()
	{
		return $this->boundary('commit'); }
	public function trans_rollback()
	{
		return $this->boundary('rollback'); }
	public function query($sql, $parameters)
	{
		$kind = strpos($sql, 'INSERT INTO st_lesson_progress') !== FALSE ? 'write' : (strpos($sql, 'st_course_enrollments') !== FALSE ? 'access' : 'lesson');
		$this->sql[] = array($kind, $sql, $parameters, $this->db_debug);
		if ( ! $this->boundary($kind)) { return FALSE; }
		return $kind === 'write' ? TRUE : new Reading_test_result($this, $kind);
	}
}
$checks = 0;
function reading_assert($condition, $message)
{
	global $checks;
	if ( ! $condition) { throw new RuntimeException($message); }
	$checks++;
}
function reading_fixture()
{
	$model = new Content_model();
	$model->db = new Reading_test_database();
	$model->load = new Reading_test_loader();
	$model->study_transaction = new Study_transaction();
	return $model;
}
function reading_run($model, $method, $position = 3700)
{
	return $method === 'complete_lesson' ? $model->$method(71, 17) : $model->$method(71, 17, $position); }

// This assertion is deliberately first: the former model returned TRUE here.
foreach (array('complete_lesson', 'save_lesson_position') as $method)
{
	$model = reading_fixture(); $model->db->fault = 'commit';
	$result = reading_run($model, $method);
	reading_assert(is_array($result) && $result['ok'] === FALSE && $result['code'] === 503 && $result['failure'] === 'uncertain', $method.' must not report success when COMMIT is FALSE.');
	foreach (array('begin', 'lesson', 'access', 'write', 'status', 'commit') as $fault)
	{
		foreach (array(FALSE, TRUE) as $throw)
		{
			$model = reading_fixture(); $model->db->fault = $fault; $model->db->throw = $throw;
			$result = reading_run($model, $method);
			reading_assert($result === array('ok' => FALSE, 'code' => 503, 'failure' => $fault === 'commit' ? 'uncertain' : 'unavailable'), 'Fault must be classified without success: '.$method.'/'.$fault);
			reading_assert($model->db->db_debug === TRUE, 'Database debug restored.');
			$after = array_slice($model->db->calls, array_search($fault, $model->db->calls, TRUE) + 1);
			reading_assert($after === ($fault === 'begin' ? array() : array('rollback')), 'Only rollback may follow first failure.');
		}
	}
	foreach (array('row', 'access_rows') as $fault)
	{
		$model = reading_fixture(); $model->db->fault = $fault; $model->db->throw = TRUE;
		reading_assert(reading_run($model, $method)['failure'] === 'unavailable', 'Result extraction exception must roll back.');
	}
	foreach (array('missing', 'denied') as $domain)
	{
		$model = reading_fixture(); $model->db->$domain = TRUE;
		reading_assert(reading_run($model, $method) === array('ok' => FALSE, 'code' => 403), 'Missing or revoked lesson must reject without write.');
		reading_assert( ! in_array('write', $model->db->calls, TRUE) && end($model->db->calls) === 'rollback', 'Domain rejection rolls back only.');
		foreach (array(FALSE, TRUE) as $throw)
		{
			$model = reading_fixture(); $model->db->$domain = TRUE; $model->db->fault = 'rollback'; $model->db->throw = $throw;
			reading_assert(reading_run($model, $method)['failure'] === 'uncertain', 'Unconfirmed rollback cannot claim rejection certainty.');
		}
	}
	$model = reading_fixture(); $model->db->nested = TRUE;
	reading_assert(reading_run($model, $method)['failure'] === 'unavailable' && $model->db->calls === array('active'), 'Must not adopt caller transaction.');
	$model = reading_fixture(); $result = reading_run($model, $method);
	reading_assert($result === array('ok' => TRUE), 'Confirmed success is structured.');
	reading_assert(end($model->db->calls) === 'commit', 'No query after commit.');
	reading_assert(count($model->db->sql) === 3, 'Exactly lesson, access and upsert.');
	foreach ($model->db->sql as $query)
	{
		reading_assert($query[3] === FALSE, 'No DB debug during mutation.');
		if ($query[0] !== 'write') { reading_assert(strpos($query[1], 'FOR UPDATE') !== FALSE, 'Authorization and lesson require current read.'); }
	}
	$sql = $model->db->sql[2];
	reading_assert(array_slice($sql[2], 0, 2) === array(71, 17), 'Mutation binds learner and lesson.');
	reading_assert(strpos($sql[1], 'ON DUPLICATE KEY UPDATE') !== FALSE, 'Upsert preserved.');
	reading_assert(strpos($sql[1], $method === 'complete_lesson' ? 'COALESCE(completed_at, ?)' : "IF(status = 'completed', 'completed', 'in_progress')") !== FALSE, 'Completion remains terminal and initial completion time stable.');
}
foreach (array(-1, 10001, '1e3', array(1), TRUE, 1.5, NULL) as $invalid)
{
	$model = reading_fixture();
	reading_assert($model->save_lesson_position(71, 17, $invalid) === array('ok' => FALSE, 'code' => 400) && $model->db->calls === array(), 'Invalid position rejected before transaction.');
}
foreach (array(0, 10000) as $position)
{
	$model = reading_fixture(); reading_assert(reading_run($model, 'save_lesson_position', $position)['ok'], 'Position endpoints accepted.');
}
echo 'PASS '.$checks." reading transaction assertions; recording fakes, not real database disconnect/concurrency.\n";
