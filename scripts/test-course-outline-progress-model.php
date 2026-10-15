<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');

class CI_Model
{
	public $db;
}
require __DIR__.'/../application/models/Content_model.php';

$outline_logs = array();
function log_message($level, $message)
{
	global $outline_logs;
	$outline_logs[] = array($level, $message);
}

class Outline_progress_result
{
	private $rows;
	private $throws;

	public function __construct($rows, $throws)
	{
		$this->rows = $rows;
		$this->throws = $throws;
	}

	public function result_array()
	{
		if ($this->throws) { throw new RuntimeException('Synthetic private result failure.'); }
		return $this->rows;
	}
}

class Outline_progress_database
{
	public $db_debug = TRUE;
	public $calls = array();
	public $rows = array();
	public $mode = 'success';

	public function query($sql, $parameters)
	{
		$this->calls[] = array('sql' => $sql, 'parameters' => $parameters, 'debug' => $this->db_debug);
		if ($this->mode === 'false') { return FALSE; }
		if ($this->mode === 'throw') { throw new RuntimeException('Synthetic private SQL failure.'); }
		return new Outline_progress_result($this->rows, $this->mode === 'result_throw');
	}

	public function __call($method, $arguments)
	{
		throw new RuntimeException('Unexpected database operation in outline progress read.');
	}
}

$assertions = 0;
function outline_progress_assert($condition, $message)
{
	global $assertions;
	if ( ! $condition) { throw new RuntimeException($message); }
	$assertions++;
}

$model = new Content_model();
$database = new Outline_progress_database();
$model->db = $database;
foreach (array(0, -1, '', '0', '-1', 'invalid', '41 OR 1=1', '1e2', '2.5', 2.5, TRUE, FALSE, NULL, array(41), new stdClass(), PHP_INT_MAX.'0') as $invalid_id)
{
	outline_progress_assert($model->get_course_lesson_progress_map($invalid_id, 17) === array(), 'Guest/invalid learner must return no private progress.');
	outline_progress_assert($model->get_course_lesson_progress_map(41, $invalid_id) === array(), 'Invalid course must return no progress.');
}
outline_progress_assert($database->calls === array() && $database->db_debug === TRUE && $outline_logs === array(), 'Invalid IDs must not query, change debug or log.');

$database->rows = array(
	array('lesson_id' => '12', 'status' => 'completed'),
	array('lesson_id' => '11', 'status' => 'in_progress'),
	array('lesson_id' => '13', 'status' => 'not_started')
);
outline_progress_assert($model->get_course_lesson_progress_map('41', '17') === array(12 => 'completed', 11 => 'in_progress', 13 => 'not_started'), 'Return an integer lesson-ID map containing only saved status strings.');
outline_progress_assert(count($database->calls) === 1 && $database->calls[0]['parameters'] === array(41, 17), 'One bound read must use the current learner and requested course, in that order.');
outline_progress_assert($database->calls[0]['debug'] === FALSE && $database->db_debug === TRUE, 'The query must suppress debug locally and restore it afterwards.');
$sql = preg_replace('/\s+/', ' ', trim($database->calls[0]['sql']));
outline_progress_assert($sql === "SELECT p.lesson_id, p.status FROM st_lesson_progress p JOIN st_lessons l ON l.id = p.lesson_id AND l.status = 'published' JOIN st_course_sections s ON s.id = l.section_id JOIN st_courses c ON c.id = s.course_id AND c.status = 'published' WHERE p.learner_user_id = ? AND c.id = ?", 'Projection, publication filters, section/course relation and ownership scope must remain exact.');
outline_progress_assert(substr_count($sql, '?') === 2 && strpos($sql, '*') === FALSE && ! preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|CALL|LIMIT|FOR UPDATE)\b/', $sql), 'The map must use one read, no wildcard/private fields, writes, locking or truncated result limit.');
outline_progress_assert( ! preg_match('/st_entitlements|st_course_enrollments|st_family_links|is_free|is_preview|p\.status\s*=/i', $sql), 'Own saved statuses must not disappear for locked lessons, parent users or completed/not-started states.');

// Many lesson statuses must still require one query, not an access/read per row.
$database->calls = array();
$database->rows = array();
$expected = array();
for ($lesson_id = 1; $lesson_id <= 120; $lesson_id++)
{
	$status = array('not_started', 'in_progress', 'completed')[$lesson_id % 3];
	$database->rows[] = array('lesson_id' => (string) $lesson_id, 'status' => $status);
	$expected[$lesson_id] = $status;
}
outline_progress_assert($model->get_course_lesson_progress_map(42, 18) === $expected && count($database->calls) === 1 && $database->calls[0]['parameters'] === array(42, 18), 'A different learner/course and 120 rows must stay one independently bound query.');

foreach (array(TRUE, FALSE) as $debug)
{
	foreach (array('success', 'false', 'throw', 'result_throw') as $mode)
	{
		$database = new Outline_progress_database();
		$database->db_debug = $debug;
		$database->mode = $mode;
		$model->db = $database;
		outline_progress_assert($model->get_course_lesson_progress_map(41, 17) === ($mode === 'success' ? array() : FALSE), 'Unavailable reads must be distinct from an empty saved-progress map.');
		outline_progress_assert(count($database->calls) === 1 && $database->calls[0]['debug'] === FALSE && $database->db_debug === $debug, 'Every success/failure must restore the original debug state after exactly one read.');
	}
}
outline_progress_assert(count($outline_logs) === 4, 'Thrown query/result failures must produce bounded generic diagnostics.');

foreach (array(NULL, FALSE, 'bad-result', array(NULL), array(array('lesson_id' => 1)), array(array('lesson_id' => 0, 'status' => 'completed')), array(array('lesson_id' => 'invalid', 'status' => 'completed')), array(array('lesson_id' => 1, 'status' => 'unknown')), array(array('lesson_id' => 1, 'status' => TRUE)), array(array('lesson_id' => 1, 'status' => 'completed'), array('lesson_id' => '1', 'status' => 'in_progress'))) as $invalid_rows)
{
	$database = new Outline_progress_database();
	$database->rows = $invalid_rows;
	$model->db = $database;
	outline_progress_assert($model->get_course_lesson_progress_map(41, 17) === FALSE && $database->db_debug === TRUE && count($database->calls) === 1, 'Malformed/duplicate results must fail without presenting fabricated saved status.');
}
outline_progress_assert(count($outline_logs) === 14, 'Each malformed read must produce only one bounded diagnostic.');
foreach ($outline_logs as $entry)
{
	outline_progress_assert($entry === array('error', 'Course lesson progress could not be read.'), 'Logs must not expose SQL, identities, saved values or exception contents.');
}

fwrite(STDOUT, 'PASS '.$assertions." course outline progress model assertions: one bound published-course/lesson read, own status-only map, locked-history retention, invalid IDs, no N+1 and debug-restoring failures.\n");
fwrite(STDOUT, "LIMITATION recording fake database only; no SQL execution, HTTP ownership, access-link or browser-rendering proof.\n");
