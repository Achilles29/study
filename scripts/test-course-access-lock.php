<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');
class CI_Model
{
	public $db;
}
require __DIR__.'/../application/models/Content_model.php';
class Course_access_result
{
	private $count;
	public function __construct($count)
	{
		$this->count = $count;
	}
	public function num_rows()
	{
		return $this->count;
	}
}
class Course_access_database
{
	public $calls = array();
	public $count = 1;
	public $fails = FALSE;
	public function query($sql, $params)
	{
		$this->calls[] = array($sql, $params);
		return $this->fails ? FALSE : new Course_access_result($this->count);
	}
}
$model = new Content_model();
$model->db = new Course_access_database();
$assertions = 0;
function course_lock_assert($condition, $message)
{
	global $assertions;
	if ( ! $condition) { throw new RuntimeException($message); }
	$assertions++;
}
$paid = array('id' => 17, 'is_free' => 0);
foreach (array(FALSE, TRUE) as $lock)
{
	course_lock_assert($model->can_access_course(0, $paid, $lock) === FALSE && $model->can_access_course(71, array('id' => 17, 'is_free' => 1), $lock) === TRUE, 'Guest/explicitly free access must preserve existing behavior.');
}
course_lock_assert($model->db->calls === array(), 'Guest/free checks must not add a database query.');
foreach (array(FALSE, TRUE, 1, 'yes', NULL) as $lock)
{
	foreach (array(0, 1) as $count)
	{
		$model->db->count = $count;
		$model->db->calls = array();
		course_lock_assert($model->can_access_course('71', $paid, $lock) === ($count > 0), 'Locking must not grant or remove course access.');
		course_lock_assert(count($model->db->calls) === 1 && $model->db->calls[0][1] === array(71, 17), 'Access must remain a single parameter-bound query.');
		$sql = preg_replace('/\s+/', ' ', trim($model->db->calls[0][0]));
		course_lock_assert((substr($sql, -10) === 'FOR UPDATE') === ($lock === TRUE), 'Only explicit transactional reads may use a current locking read.');
		course_lock_assert(strpos($sql, "ce.status = 'active'") !== FALSE && strpos($sql, "ce.source_type IN ('free','manual')") !== FALSE && strpos($sql, "e.status = 'active'") !== FALSE && strpos($sql, 'e.starts_at <= UTC_TIMESTAMP()') !== FALSE && strpos($sql, 'e.ends_at > UTC_TIMESTAMP()') !== FALSE, 'Enrollment/entitlement time and status predicates must not change.');
	}
}
$model->db->calls = array();
$model->can_access_course(72, array('id' => 19, 'course_id' => 18, 'is_free' => 0), TRUE);
course_lock_assert($model->db->calls[0][1] === array(72, 18), 'Lesson-shaped course input must retain its explicit course ID.');
$paid_lesson = array('id' => 19, 'course_id' => 18, 'is_free' => 0, 'is_preview' => 0);
foreach (array(FALSE, TRUE, 1, 'yes', NULL) as $lock)
{
	foreach (array(0, 1) as $count)
	{
		$model->db->count = $count;
		$model->db->calls = array();
		course_lock_assert($model->can_access_lesson(72, $paid_lesson, $lock) === ($count > 0), 'Lesson locking must retain paid access semantics.');
		course_lock_assert(count($model->db->calls) === 1 && $model->db->calls[0][1] === array(72, 18), 'Lesson access must retain one bound course query.');
		course_lock_assert((substr(trim($model->db->calls[0][0]), -10) === 'FOR UPDATE') === ($lock === TRUE), 'Lesson must forward only the explicit current-read flag.');
	}
	foreach (array('is_preview', 'is_free') as $public_field)
	{
		$public_lesson = $paid_lesson;
		$public_lesson[$public_field] = 1;
		$model->db->calls = array();
		course_lock_assert($model->can_access_lesson(72, $public_lesson, $lock) === TRUE && $model->db->calls === array(), 'Preview/free lessons must not add a paid-access query.');
	}
}
$model->db->calls = array();
$model->can_access_lesson(72, $paid_lesson);
course_lock_assert(strpos($model->db->calls[0][0], 'FOR UPDATE') === FALSE, 'Existing two-argument lesson reads must remain nonlocking.');
$model->db->calls = array();
course_lock_assert($model->can_access_lesson(0, $paid_lesson, TRUE) === FALSE && $model->db->calls === array(), 'Guest paid-lesson reads must not query.');
$model->db->fails = TRUE;
foreach (array(FALSE, TRUE) as $lock)
{
	try { $model->can_access_course(71, $paid, $lock); throw new LogicException('Failed access query was treated as data.'); }
	catch (RuntimeException $error) { course_lock_assert($error->getMessage() === 'Course access could not be read.', 'Query failure must throw a bounded diagnostic for the transaction wrapper.'); }
	try { $model->can_access_lesson(72, $paid_lesson, $lock); throw new LogicException('Failed lesson-access query was treated as data.'); }
	catch (RuntimeException $error) { course_lock_assert($error->getMessage() === 'Course access could not be read.', 'Lesson access must propagate the bounded failure to the transaction wrapper.'); }
}
echo 'PASS '.$assertions." course access assertions: explicit current locking reads, unchanged access predicates/bindings, public read defaults and bounded failure.\n";
echo "LIMITATION recording SQL only; no isolation-level, lock-wait or parallel-request proof.\n";
