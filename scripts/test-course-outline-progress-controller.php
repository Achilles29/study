<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

class Outline_controller_services
{
	public $calls = array();
	public $course = array('id' => 17, 'title' => 'Kursus sintetis', 'target_group' => 'all_ages');
	public $lessons = array(array('id' => 93, 'is_preview' => 0), array('id' => 28, 'is_preview' => 1));
	public $progress;
	public $can_read = FALSE;
	public function __construct($progress)
	{
		$this->progress = $progress;
	}
	public function model($name)
	{
		$this->calls[] = array('model', $name);
	}
	public function get_course_by_slug($slug)
	{
		$this->calls[] = array('course', $slug);
		return $this->course;
	}
	public function get_published_lesson_index($course_id)
	{
		$this->calls[] = array('lessons', $course_id);
		return $this->lessons;
	}
	public function get_course_lesson_progress_map($user_id, $course_id)
	{
		$this->calls[] = array('progress', $user_id, $course_id);
		return $this->progress;
	}
	public function can_access_course($user_id, $course)
	{
		$this->calls[] = array('access', $user_id, $course);
		return $this->can_read;
	}
}
class Outline_controller_database
{
	public $calls = array();
	public function where($key, $value)
	{
		$this->calls[] = array('where', $key, $value);
		return $this;
	}
	public function count_all_results($table)
	{
		$this->calls[] = array('count', $table);
		return 3;
	}
}
class Outline_controller_output
{
	public $headers = array();
	public function set_header($value)
	{
		$this->headers[] = $value;
		return $this;
	}
}
class MY_Controller
{
	protected $current_user;
	public $load;
	public $db;
	public $output;
	public $guards = array();
	public $rendered;
	public function __construct($user, $progress)
	{
		$this->current_user = $user;
		$this->load = new Outline_controller_services($progress);
		$this->db = new Outline_controller_database();
		$this->output = new Outline_controller_output();
	}
	public function __get($name)
	{
		if ($name === 'Content_model') { return $this->load; }
		throw new LogicException('Unexpected dependency in course outline.');
	}
	protected function require_http_methods($allowed)
	{
		if ($this->load->calls || $this->output->headers || $this->db->calls) { throw new LogicException('Guard must precede page work.'); }
		$this->guards[] = $allowed;
	}
	protected function render_page($view, $data)
	{
		$this->rendered = array($view, $data);
	}
}
function study_target_group_label($value)
{
	return 'Sasaran sintetis: '.$value;
}
function show_404()
{
	throw new RuntimeException('Synthetic missing course');
}
require __DIR__.'/../application/controllers/Catalog.php';

$assertions = 0;
function outline_controller_assert($condition, $message)
{
	global $assertions;
	if ( ! $condition) { throw new RuntimeException($message); }
	$assertions++;
}
// These values must never select another learner or replace the slug-resolved course.
$_GET = array('user_id' => '999', 'learner_user_id' => '998', 'child_id' => '997', 'course_id' => '996');
$cases = 0;
foreach (array('adult', 'child', 'parent') as $kind)
{
	foreach (array(array(), FALSE, array(28 => 'completed', 93 => 'in_progress')) as $progress)
	{
		foreach (array(FALSE, TRUE) as $can_read)
		{
			$catalog = new Catalog(array('id' => 71, 'account_kind' => $kind), $progress);
			$catalog->load->can_read = $can_read;
			$catalog->course('kursus-sintetis');
			outline_controller_assert($catalog->guards === array(array('GET', 'HEAD')), 'Only GET/HEAD must be permitted before reading course data.');
			outline_controller_assert($catalog->output->headers === array('Cache-Control: private, no-store', 'X-Robots-Tag: noindex, nofollow'), 'Personal statuses must forbid caching and indexing.');
			outline_controller_assert($catalog->load->calls === array(array('model', 'Content_model'), array('course', 'kursus-sintetis'), array('lessons', 17), array('progress', 71, 17), array('access', 71, $catalog->load->course)), 'Adult/child/parent must read only their session identity and resolved course, without family lookups.');
			$data = $catalog->rendered[1];
			outline_controller_assert($catalog->rendered[0] === 'catalog/course' && $data['lesson_progress_map'] === $progress && $data['lessons'] === $catalog->load->lessons, 'Failure/empty/map and published index ordering must reach the view unchanged.');
			outline_controller_assert($data['can_read_course'] === $can_read && $data['has_preview_lessons'] === TRUE, 'Saved status must never grant or remove course/preview access.');
			outline_controller_assert($data['question_count'] === 3 && $catalog->db->calls === array(array('where', 'course_id', 17), array('where', 'visibility', 'shared'), array('where', 'target_child_user_id', NULL), array('where', 'status', 'published'), array('count', 'st_questions')), 'Existing question count must remain public/shared and published only.');
			$cases++;
		}
	}
}
foreach (array(FALSE, array(28 => 'completed')) as $progress)
{
	$guest = new Catalog(NULL, $progress);
	$guest->course('kursus-sintetis');
	outline_controller_assert($guest->load->calls === array(array('model', 'Content_model'), array('course', 'kursus-sintetis'), array('lessons', 17), array('access', 0, $guest->load->course)), 'Guest must not query personal progress, even with forged query values.');
	outline_controller_assert($guest->rendered[1]['lesson_progress_map'] === NULL && $guest->output->headers === array(), 'Guest must receive no personalized status or new private headers.');
	$cases++;
}
foreach (array(array(), array(array('id' => 93, 'is_preview' => 0))) as $lessons)
{
	$catalog = new Catalog(array('id' => 72, 'account_kind' => 'parent'), array());
	$catalog->load->lessons = $lessons;
	$catalog->course('kursus-sintetis');
	outline_controller_assert($catalog->rendered[1]['has_preview_lessons'] === FALSE && $catalog->rendered[1]['lessons'] === $lessons, 'No-lesson/no-preview course must retain its existing empty state.');
	$cases++;
}
foreach (array(NULL, array('id' => 71, 'account_kind' => 'adult')) as $user)
{
	$missing = new Catalog($user, array(93 => 'completed'));
	$missing->load->course = NULL;
	try { $missing->course('tidak-ada'); throw new LogicException('Missing course was rendered'); }
	catch (RuntimeException $error) { outline_controller_assert($error->getMessage() === 'Synthetic missing course', 'Unknown slug must stop at the existing not-found guard.'); }
	outline_controller_assert($missing->load->calls === array(array('model', 'Content_model'), array('course', 'tidak-ada')) && $missing->db->calls === array() && $missing->rendered === NULL, 'Missing/unpublished lookup must not read progress, questions, access or render.');
	$cases++;
}
echo 'PASS '.$assertions.' assertions across '.$cases." course outline controller cases: session-only identity, parent-own progress, guest isolation, status/access independence and read-only guards.\n";
echo "LIMITATION isolated guard/service recording only; real SQL, HTTP methods and cache/access behavior require integration tests.\n";
