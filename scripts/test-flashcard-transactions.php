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
	public $study_transaction;
	public $content;
	public function __get($name)
	{
		if ($name === 'Content_model') { return $this->content; }
		throw new LogicException('Unexpected model dependency.');
	}
}
function log_message($level, $message)
{}
require __DIR__.'/../application/libraries/Study_transaction.php';
require __DIR__.'/../application/models/Content_model.php';
require __DIR__.'/../application/models/Flashcard_model.php';
class Card_test_loader
{
	public function library($name)
	{}
	public function model($name)
	{}
}
class Card_test_result
{
	private $db;
	public function __construct($db)
	{
		$this->db = $db; }
	public function row_array()
	{
		$this->db->step('row');
		return $this->db->missing ? NULL : array('id' => 31, 'course_id' => 27, 'is_preview' => $this->db->preview, 'is_free' => $this->db->free);
	}
	public function num_rows()
	{
		$this->db->step('access_rows'); return $this->db->denied ? 0 : 1; }
}
class Card_test_database
{
	public $db_debug = TRUE;
	public $calls = array();
	public $queries = array();
	public $fault = '';
	public $throw = FALSE;
	public $missing = FALSE;
	public $denied = FALSE;
	public $nested = FALSE;
	public $preview = 0;
	public $free = 0;
	private $writes = 0;
	public function step($name)
	{
		$this->calls[] = $name;
		if ($name !== $this->fault) { return TRUE; }
		if ($this->throw) { throw new RuntimeException('Synthetic card operation failure.'); }
		return FALSE;
	}
	public function trans_active()
	{
		$this->step('active'); return $this->nested; }
	public function trans_begin()
	{
		return $this->step('begin'); }
	public function trans_commit()
	{
		return $this->step('commit'); }
	public function trans_rollback()
	{
		return $this->step('rollback'); }
	public function trans_status()
	{
		return $this->step($this->writes ? 'post_status' : 'pre_status'); }
	public function query($sql, $parameters)
	{
		$kind = strpos($sql, 'INSERT INTO st_flashcard_progress') !== FALSE ? 'write' : (strpos($sql, 'st_course_enrollments') !== FALSE ? 'access' : 'card');
		$this->queries[] = array($kind, $sql, $parameters, $this->db_debug);
		if ( ! $this->step($kind)) { return FALSE; }
		if ($kind === 'write') { $this->writes++; return TRUE; }
		return new Card_test_result($this);
	}
}
$assertions = 0;
function card_assert($condition, $message)
{
	global $assertions;
	if ( ! $condition) { throw new RuntimeException($message); }
	$assertions++;
}
function card_fixture()
{
	$model = new Flashcard_model(); $model->db = new Card_test_database(); $model->load = new Card_test_loader();
	$model->study_transaction = new Study_transaction(); $model->content = new Content_model(); $model->content->db = $model->db;
	return $model;
}
$model = card_fixture(); $model->db->fault = 'commit';
$result = $model->set_card_status(71, 17, 31, 'known');
card_assert(is_array($result) && $result === array('ok' => FALSE, 'code' => 503, 'failure' => 'uncertain'), 'COMMIT FALSE must not be reported as saved card status.');
foreach (array('known', 'learning') as $status)
{
	foreach (array('begin', 'card', 'access', 'write', 'pre_status', 'post_status', 'commit') as $fault)
	{
		foreach (array(FALSE, TRUE) as $throw)
		{
			$model = card_fixture(); $model->db->fault = $fault; $model->db->throw = $throw;
			$result = $model->set_card_status(71, 17, 31, $status);
			card_assert($result === array('ok' => FALSE, 'code' => 503, 'failure' => $fault === 'commit' ? 'uncertain' : 'unavailable'), 'Fault classification: '.$fault);
			card_assert($model->db->db_debug === TRUE, 'Restore database debug.');
			card_assert(array_slice($model->db->calls, array_search($fault, $model->db->calls, TRUE) + 1) === ($fault === 'begin' ? array() : array('rollback')), 'No work after first failure.');
		}
	}
	foreach (array('missing', 'denied') as $domain)
	{
		$model = card_fixture(); $model->db->$domain = TRUE;
		card_assert($model->set_card_status(71, 17, 31, $status) === array('ok' => FALSE, 'code' => 403), 'Domain rejection.');
		card_assert( ! in_array('write', $model->db->calls, TRUE), 'Denied access cannot write.');
		foreach (array(FALSE, TRUE) as $throw)
		{
			$model = card_fixture(); $model->db->$domain = TRUE; $model->db->fault = 'rollback'; $model->db->throw = $throw;
			card_assert($model->set_card_status(71, 17, 31, $status)['failure'] === 'uncertain', 'Rollback failure is uncertain.');
		}
	}
	foreach (array('row', 'access_rows') as $fault)
	{
		$model = card_fixture(); $model->db->fault = $fault; $model->db->throw = TRUE;
		card_assert($model->set_card_status(71, 17, 31, $status)['failure'] === 'unavailable', 'Result extraction failure is rolled back.');
	}
	$model = card_fixture(); $model->db->nested = TRUE;
	card_assert($model->set_card_status(71, 17, 31, $status)['failure'] === 'unavailable' && $model->db->calls === array('active'), 'Cannot adopt outer transaction.');
	$model = card_fixture();
	card_assert($model->set_card_status(71, 17, '31', $status) === array('ok' => TRUE), 'Confirmed result and canonical route ID.');
	card_assert($model->db->calls[0] === 'active' && $model->db->calls[1] === 'begin' && end($model->db->calls) === 'commit', 'All reads within transaction; none after commit.');
	foreach ($model->db->queries as $query)
	{
		card_assert($query[3] === FALSE, 'Debug suppressed during mutation.');
		if ($query[0] !== 'write') { card_assert(strpos($query[1], 'FOR UPDATE') !== FALSE, 'Card and access current reads.'); }
		else
		{
			card_assert(array_slice($query[2], 0, 3) === array(71, 31, $status), 'Bound owner/card/status.');
			card_assert(strpos($query[1], 'review_count + IF(status <> VALUES(status), 1, 0)') !== FALSE && strpos($query[1], 'VALUES(last_reviewed_at), last_reviewed_at)') !== FALSE, 'Same-state replay does not increment review or update review time.');
		}
	}
}
foreach (array('preview', 'free') as $shortcut)
{
	$model = card_fixture(); $model->db->$shortcut = 1; $model->db->denied = TRUE;
	card_assert($model->set_card_status(71, 17, 31, 'known')['ok'] && ! in_array('access', $model->db->calls, TRUE), 'Existing preview/free access retained.');
}
foreach (array(0, -1, TRUE, 1.5, '31oops', '01', array(31), NULL) as $invalid)
{
	foreach (array(0, 1, 2) as $slot)
	{
		$model = card_fixture(); $args = array(71, 17, 31, 'known'); $args[$slot] = $invalid;
		card_assert(call_user_func_array(array($model, 'set_card_status'), $args) === array('ok' => FALSE, 'code' => 400) && $model->db->calls === array(), 'Invalid identity rejected before DB.');
	}
}
foreach (array('', 'KNOWN', array('known'), NULL) as $invalid)
{
	$model = card_fixture(); card_assert($model->set_card_status(71, 17, 31, $invalid) === array('ok' => FALSE, 'code' => 400) && $model->db->calls === array(), 'Invalid status rejected before DB.');
}
echo 'PASS '.$assertions." card transaction assertions; actual models/runner with recording fakes, not SQL/concurrency/disconnect proof.\n";
