<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');
require __DIR__.'/../application/libraries/Study_transaction.php';
$transaction_logs = array();
function log_message($level, $message)
{
	global $transaction_logs;
	$transaction_logs[] = array($level, $message);
}
class Transaction_recording_database
{
	public $db_debug = TRUE;
	public $calls = array();
	public $failure = '';
	public $rollback_failure = '';
	public $active = FALSE;
	public function __construct($failure, $rollback_failure = '')
	{
		$this->failure = $failure;
		$this->rollback_failure = $rollback_failure;
	}
	private function record($name)
	{
		if ($this->db_debug !== FALSE) { throw new LogicException('Database debug must be suppressed during transaction work.'); }
		$this->calls[] = $name;
		if ($this->failure === $name.'_throw' || ($name === 'rollback' && $this->rollback_failure === 'throw')) { throw new RuntimeException('PRIVATE_SQL_TOKEN_ANSWER'); }
		return $this->failure !== $name && ! ($name === 'rollback' && $this->rollback_failure === 'false');
	}
	public function trans_begin()
	{
		return $this->record('begin');
	}
	public function trans_active()
	{
		$this->record('active');
		return $this->active;
	}
	public function trans_status()
	{
		return $this->record('status');
	}
	public function trans_commit()
	{
		return $this->record('commit');
	}
	public function trans_rollback()
	{
		return $this->record('rollback');
	}
}
$assertions = 0;
function transaction_assert($condition, $message)
{
	global $assertions;
	if ( ! $condition) { throw new RuntimeException($message); }
	$assertions++;
}
$runner = new Study_transaction();
$success = array('ok' => TRUE, 'id' => 19, 'status' => 'graded');
$unavailable = array('ok' => FALSE, 'code' => 503, 'failure' => 'unavailable');
$uncertain = array('ok' => FALSE, 'code' => 503, 'failure' => 'uncertain');
foreach (array(TRUE, FALSE) as $debug)
{
	foreach (array('', 'begin', 'begin_throw', 'operation_throw', 'status', 'status_throw', 'commit', 'commit_throw') as $failure)
	{
		foreach (array('', 'false', 'throw') as $rollback_failure)
		{
			$database = new Transaction_recording_database($failure, $rollback_failure);
			$database->db_debug = $debug;
			$result = $runner->run($database, function () use ($database, $failure, $success) {
				transaction_assert($database->db_debug === FALSE, 'Callback must run with debug suppressed.');
				$database->calls[] = 'operation';
				if ($failure === 'operation_throw') { throw new RuntimeException('PRIVATE_QUESTION_TOKEN'); }
				return $success;
			});
			$begin_failed = strpos($failure, 'begin') === 0;
			$commit_failed = strpos($failure, 'commit') === 0;
			$expected = $failure === '' ? $success : (($commit_failed || ($rollback_failure !== '' && ! $begin_failed)) ? $uncertain : $unavailable);
			transaction_assert($result === $expected, 'Commit or rollback uncertainty must never become success or a confirmed rejection.');
			transaction_assert($database->db_debug === $debug, 'Original debug value must always be restored.');
			$expected_calls = array('active', 'begin');
			if ( ! $begin_failed)
			{
				$expected_calls[] = 'operation';
				if ($failure !== 'operation_throw') { $expected_calls[] = 'status'; }
				if ($failure === '' || $commit_failed) { $expected_calls[] = 'commit'; }
				if ($failure !== '') { $expected_calls[] = 'rollback'; }
			}
			transaction_assert($database->calls === $expected_calls, 'No callback after begin failure, retry, second rollback or post-commit query is permitted.');
		}
	}
	foreach (array(400, 403, 404, 409) as $code)
	{
		foreach (array('', 'false', 'throw') as $rollback_failure)
		{
			$database = new Transaction_recording_database('', $rollback_failure);
			$database->db_debug = $debug;
			$denied = array('ok' => FALSE, 'code' => $code);
			$result = $runner->run($database, function () use ($denied) {
				return $denied;
			});
			transaction_assert($result === ($rollback_failure === '' ? $denied : $uncertain), 'Domain rejection requires a confirmed rollback.');
			transaction_assert($database->calls === array('active', 'begin', 'status', 'rollback') && $database->db_debug === $debug, 'Domain rejection must not commit and must restore debug.');
		}
	}
	foreach (array(NULL, FALSE, 'bad-result', array(), array('ok' => 1), array('ok' => FALSE, 'code' => '409'), array('ok' => FALSE, 'code' => 500), array('ok' => FALSE, 'code' => 503)) as $invalid)
	{
		$database = new Transaction_recording_database('');
		$database->db_debug = $debug;
		$result = $runner->run($database, function () use ($invalid) {
			return $invalid;
		});
		transaction_assert($result === $unavailable && $database->db_debug === $debug, 'Malformed/unsupported operation result must fail closed.');
		transaction_assert(end($database->calls) === 'rollback' && ! in_array('commit', $database->calls, TRUE), 'Invalid callback result must not commit.');
	}
	$nested = new Transaction_recording_database('');
	$nested->db_debug = $debug;
	$nested->active = TRUE;
	$result = $runner->run($nested, function () {
		throw new LogicException('Nested callback must not run.');
	});
	transaction_assert($result === $unavailable && $nested->calls === array('active') && $nested->db_debug === $debug, 'Do not adopt or roll back an outer transaction owned by another caller.');
	$active_failure = new Transaction_recording_database('active_throw');
	$active_failure->db_debug = $debug;
	$result = $runner->run($active_failure, function () {
		throw new LogicException('Callback must not run without checking transaction ownership.');
	});
	transaction_assert($result === $unavailable && $active_failure->calls === array('active') && $active_failure->db_debug === $debug, 'Ownership-check failure must stop before BEGIN and restore debug.');
}
foreach ($transaction_logs as $entry)
{
	transaction_assert($entry[0] === 'error' && in_array($entry[1], array('Study transaction failed; inspect service availability.', 'Study transaction rollback could not be confirmed.'), TRUE), 'New diagnostics must not include exception details, SQL, answers or tokens.');
}
echo 'PASS '.$assertions." transaction runner assertions: checked begin/status/commit/rollback, domain rejection, uncertainty, no automatic retry, generic diagnostics and restored debug.\n";
echo "LIMITATION recording fake only; this does not reproduce a real database disconnect or sanitize CI3 driver logs.\n";
