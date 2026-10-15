<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');
class CI_Controller {}
require __DIR__.'/../application/controllers/cli/Check_reading_transactions.php';
class Reading_probe_fake
{
	public $db_debug = TRUE;
	public $calls = array();
	public function __call($name, $args)
	{
		$this->calls[] = $name;
		return TRUE;
	}
}
$assertions = 0;
function reading_probe_assert($condition)
{
	global $assertions;
	if ( ! $condition) { throw new RuntimeException('Reading probe safety assertion failed.'); }
	$assertions++;
}
foreach (array('commit', 'commit_throw', 'unknown') as $fault)
{
	$db = new Reading_probe_fake(); $probe = new Reading_transaction_probe($db, $fault);
	$thrown = FALSE;
	try { $value = $probe->trans_commit(); }
	catch (RuntimeException $error) { $thrown = TRUE; }
	reading_probe_assert($db->calls === array());
	reading_probe_assert($thrown === ($fault === 'commit_throw'));
	reading_probe_assert($probe->unexpected_commits === ($fault === 'unknown' ? 1 : 0));
}
foreach (array('begin' => array('trans_begin', array()), 'lesson' => array('query', array('SELECT id FROM st_lessons l')), 'access' => array('query', array('SELECT id FROM st_course_enrollments ce')), 'write' => array('query', array('INSERT INTO st_lesson_progress VALUES (?)')), 'pre_status' => array('trans_status', array()), 'post_status' => array('trans_status', array()), 'rollback' => array('trans_status', array())) as $fault => $call)
{
	foreach (array('', '_throw') as $suffix)
	{
		$db = new Reading_probe_fake(); $probe = new Reading_transaction_probe($db, $fault.$suffix);
		if (in_array($fault, array('post_status', 'rollback'), TRUE)) { $probe->query('INSERT INTO st_lesson_progress VALUES (?)', array(1)); }
		$thrown = FALSE;
		try { $value = call_user_func_array(array($probe, $call[0]), $call[1]); }
		catch (RuntimeException $error) { $thrown = TRUE; }
		reading_probe_assert($probe->failures === 1 && $thrown === ($suffix !== '' && $fault !== 'rollback'));
		reading_probe_assert(count($db->calls) === (in_array($fault, array('post_status', 'rollback'), TRUE) ? 1 : 0));
		$thrown = FALSE;
		try { $probe->query('SELECT private'); }
		catch (RuntimeException $error) { $thrown = TRUE; }
		reading_probe_assert($thrown && $probe->work_after_failure === 1);
		$thrown = FALSE;
		try { $value = $probe->trans_rollback(); }
		catch (RuntimeException $error) { $thrown = TRUE; }
		reading_probe_assert($probe->rollbacks === 1 && $probe->rollback_faults === ($fault === 'rollback' ? 1 : 0) && $thrown === ($fault === 'rollback' && $suffix !== ''));
	}
}
$db = new Reading_probe_fake(); $probe = new Reading_transaction_probe($db, 'unknown');
$probe->db_debug = FALSE;
reading_probe_assert($db->db_debug === FALSE && $probe->db_debug === FALSE);
echo 'PASS '.$assertions." reading probe safety assertions: never forward COMMIT, exact faults, no later query and explicit rollback failures. Fake driver only.\n";
