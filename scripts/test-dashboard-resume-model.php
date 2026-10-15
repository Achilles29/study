<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');

class CI_Model
{
	public $db;
}
require __DIR__.'/../application/models/Content_model.php';

$resume_logs = array();
function log_message($level, $message)
{
	global $resume_logs;
	$resume_logs[] = array($level, $message);
}

class Dashboard_resume_result
{
	private $rows;
	private $throws;

	public function __construct($rows, $throws = FALSE)
	{
		$this->rows = $rows;
		$this->throws = $throws;
	}

	public function result_array()
	{
		if ($this->throws) { throw new RuntimeException('Synthetic private result failure.'); }
		return $this->rows;
	}

	public function num_rows()
	{
		return count($this->rows);
	}
}

class Dashboard_resume_database
{
	public $db_debug = TRUE;
	public $calls = array();
	public $mode = 'success';
	public $rows = array();

	public function query($sql, $parameters)
	{
		$this->calls[] = array('sql' => $sql, 'parameters' => $parameters, 'debug' => $this->db_debug);
		if ($this->mode === 'false') { return FALSE; }
		if ($this->mode === 'throw') { throw new RuntimeException('Synthetic private SQL failure.'); }
		return new Dashboard_resume_result($this->rows, $this->mode === 'result_throw');
	}

	public function __call($method, $arguments)
	{
		throw new RuntimeException('Unexpected database operation in resume read.');
	}
}

function resume_assert($condition, $message)
{
	if ( ! $condition) { throw new RuntimeException($message); }
}

function resume_sql($sql)
{
	$sql = preg_replace('/\s+/', ' ', trim($sql));
	$sql = preg_replace('/\(\s+/', '(', $sql);
	return preg_replace('/\s+\)/', ')', $sql);
}

$model = new Content_model();
$database = new Dashboard_resume_database();
$model->db = $database;
foreach (array(0, -1, '', '0', '-1', 'not-an-id', '41 OR 1=1', '1e2', '2.5', TRUE, FALSE, NULL, array(41), new stdClass(), PHP_INT_MAX.'0') as $invalid_id)
{
	resume_assert($model->get_resumable_lessons($invalid_id) === array(), 'Invalid/nonpositive learner IDs must return an empty list.');
}
resume_assert($database->calls === array() && $database->db_debug === TRUE, 'Invalid learner IDs must not query or change database debug.');

$database->rows = array(
	array('lesson_id' => '12', 'lesson_title' => 'Synthetic <b>lesson</b> "quoted"', 'lesson_slug' => 'synthetic-lesson', 'course_title' => 'Synthetic course', 'course_slug' => 'synthetic-course', 'last_position' => '4000', 'last_activity_at' => '2026-10-15 01:02:03'),
	array('lesson_id' => '11', 'lesson_title' => 'Synthetic Unicode '.chr(0xF0).chr(0x9F).chr(0x8C).chr(0x88), 'lesson_slug' => 'synthetic-rainbow', 'course_title' => 'Synthetic course', 'course_slug' => 'synthetic-course', 'last_position' => '0', 'last_activity_at' => '2026-10-15 01:02:03')
);
$rows = $model->get_resumable_lessons('41');
resume_assert($rows === $database->rows, 'Selected metadata must pass through without HTML conversion, position rewriting or reordering.');
resume_assert(count($database->calls) === 1, 'Resume listing must issue exactly one read, not per-candidate access queries.');
resume_assert($database->calls[0]['parameters'] === array(41), 'Learner identity must use one bound positive integer.');
resume_assert($database->calls[0]['debug'] === FALSE && $database->db_debug === TRUE, 'Debug must be suppressed only during the read and restored afterwards.');
$sql = resume_sql($database->calls[0]['sql']);
resume_assert(substr_count($sql, '?') === 1 && preg_match('/\ASELECT (.+?) FROM st_lesson_progress p /', $sql, $selected) === 1, 'Use one parameterized SELECT rooted in learner progress.');
$fields = preg_split('/\s*,\s*/', $selected[1]);
resume_assert($fields === array('l.id AS lesson_id', 'l.title AS lesson_title', 'l.slug AS lesson_slug', 'c.title AS course_title', 'c.slug AS course_slug', 'p.last_position', 'p.updated_at AS last_activity_at'), 'Projection must contain exactly the seven dashboard fields, without broad or private fields.');
resume_assert(strpos($sql, '*') === FALSE && ! preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|CALL|FOR UPDATE)\b/', $sql), 'Resume listing must never select wildcards or write data.');
resume_assert(strpos($sql, "JOIN st_lessons l ON l.id = p.lesson_id AND l.status = 'published' JOIN st_course_sections s ON s.id = l.section_id JOIN st_courses c ON c.id = s.course_id AND c.status = 'published'") !== FALSE, 'Only published lessons in published parent courses may be resumed.');
resume_assert(strpos($sql, "WHERE p.learner_user_id = ? AND p.status = 'in_progress' AND (l.is_preview = 1 OR c.is_free = 1 OR EXISTS (SELECT 1 FROM st_course_enrollments ce") !== FALSE, 'Own in-progress rows and grouped preview/free/enrollment access must filter the query before LIMIT.');
resume_assert(strpos($sql, "LEFT JOIN st_entitlements e ON e.id = ce.entitlement_id WHERE ce.learner_user_id = p.learner_user_id AND ce.course_id = c.id AND ce.status = 'active' AND (ce.source_type IN ('free','manual') OR (ce.source_type = 'package' AND e.status = 'active' AND e.starts_at <= UTC_TIMESTAMP() AND (e.ends_at IS NULL OR e.ends_at > UTC_TIMESTAMP())))))") !== FALSE, 'Enrollment ownership/course/status, free/manual branches and active package time bounds must remain exact.');
resume_assert(preg_match('/ ORDER BY p\.updated_at DESC, p\.lesson_id DESC LIMIT 3\z/', $sql) === 1 && substr_count($sql, 'LIMIT') === 1, 'Access filtering must precede the single fixed three-row limit with a deterministic lesson-ID tie-break.');
resume_assert(substr_count($sql, 'EXISTS') === 1 && strpos(substr($sql, 0, strpos($sql, 'EXISTS')), 'st_course_enrollments') === FALSE && strpos($sql, 'GROUP BY') === FALSE && strpos($sql, 'DISTINCT') === FALSE, 'Use a correlated EXISTS, not a row-multiplying enrollment join or after-the-fact deduplication.');

// Lock the new correlated predicate to the existing material-access predicate.
$access_database = new Dashboard_resume_database();
$model->db = $access_database;
resume_assert($model->can_access_course(41, array('id' => 17, 'is_free' => 0)) === FALSE, 'Existing access result must remain unchanged in the unit fixture.');
$existing_sql = resume_sql($access_database->calls[0]['sql']);
resume_assert(preg_match('/\ASELECT ce\.id (FROM st_course_enrollments ce .+) LIMIT 1\z/', $existing_sql, $existing_access) === 1, 'Expected existing access predicate shape changed; review resume parity.');
$correlated_access = str_replace(array('ce.learner_user_id = ?', 'ce.course_id = ?'), array('ce.learner_user_id = p.learner_user_id', 'ce.course_id = c.id'), $existing_access[1]);
resume_assert(strpos($sql, 'EXISTS (SELECT 1 '.$correlated_access.')') !== FALSE, 'Dashboard access must mirror current can_access_course semantics exactly.');

$failure_cases = 0;
foreach (array(TRUE, FALSE) as $debug)
{
	foreach (array('success', 'false', 'throw', 'result_throw') as $mode)
	{
		$database = new Dashboard_resume_database();
		$database->db_debug = $debug;
		$database->mode = $mode;
		$model->db = $database;
		$result = $model->get_resumable_lessons(42);
		resume_assert($result === ($mode === 'success' ? array() : FALSE), 'Read failures must be distinct from an empty accessible list.');
		resume_assert(count($database->calls) === 1 && $database->calls[0]['parameters'] === array(42) && $database->calls[0]['debug'] === FALSE, 'Each valid call must execute one scoped read with debug locally disabled.');
		resume_assert($database->db_debug === $debug, 'Original database debug must be restored on every success/failure path.');
		$failure_cases++;
	}
}
resume_assert(count($resume_logs) === 4, 'Only thrown read/result failures should produce the bounded generic diagnostic.');
foreach ($resume_logs as $entry)
{
	resume_assert($entry === array('error', 'Resumable lessons could not be read.'), 'Diagnostics must not expose SQL, learner identity or exception contents.');
}
fwrite(STDOUT, 'PASS dashboard resume model: scoped single SELECT, seven-field projection, existing access parity before fixed LIMIT 3, stable ordering, invalid IDs and '.$failure_cases." debug-restoring success/failure controls.\n");
fwrite(STDOUT, "LIMITATION recording fake database only; SQL execution, access fixtures, HTTP isolation and browser rendering require separate integration checks.\n");
