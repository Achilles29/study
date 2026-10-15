<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

class Dashboard_resume_services
{
	public $calls = array();
	public $resume;
	public function __construct($resume)
	{
		$this->resume = $resume;
	}
	public function model($name)
	{
		$this->calls[] = array('model', $name);
	}
	public function get_resumable_lessons($user_id)
	{
		$this->calls[] = array('resume', $user_id);
		return $this->resume;
	}
	public function get_learner_progress($user_id)
	{
		$this->calls[] = array('progress', $user_id);
		return array('completed_lessons' => 2, 'in_progress_lessons' => 1);
	}
	public function get_learner_course_progress($user_id)
	{
		$this->calls[] = array('courses', $user_id);
		return array();
	}
	public function get_children($user_id)
	{
		$this->calls[] = array('children', $user_id);
		return array();
	}
	public function get_family_course_progress($user_id)
	{
		$this->calls[] = array('family_courses', $user_id);
		return array();
	}
	public function get_family_flashcard_progress($user_id)
	{
		$this->calls[] = array('family_flashcards', $user_id);
		return array();
	}
}

class Dashboard_resume_output
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
	public $output;
	public $guards = array();
	public $rendered;

	public function __construct($user, $resume)
	{
		$this->current_user = $user;
		$this->load = new Dashboard_resume_services($resume);
		$this->output = new Dashboard_resume_output();
	}

	public function __get($name)
	{
		if (in_array($name, array('Content_model', 'Account_model', 'Flashcard_model'), TRUE)) { return $this->load; }
		throw new LogicException('Unexpected dashboard dependency.');
	}

	protected function require_login()
	{
		$this->guards[] = 'login';
		if ( ! $this->current_user) { throw new RuntimeException('Synthetic login required'); }
	}

	protected function require_http_methods($allowed)
	{
		$this->guards[] = $allowed;
	}
	protected function render_page($view, $data)
	{
		$this->rendered = array($view, $data);
	}
}

require __DIR__.'/../application/controllers/Dashboard.php';
function dashboard_controller_assert($ok, $message)
{
	if ( ! $ok) { throw new RuntimeException($message); }
}

$cases = 0;
foreach (array('adult', 'child') as $kind)
{
	foreach (array(array(), FALSE, array(array('lesson_id' => 9))) as $resume)
	{
		$dashboard = new Dashboard(array('id' => 71, 'account_kind' => $kind), $resume);
		$dashboard->index();
		dashboard_controller_assert($dashboard->guards === array('login', array('GET', 'HEAD')), 'Login/read-only method guards must precede the dashboard data flow.');
		dashboard_controller_assert($dashboard->output->headers === array('Cache-Control: private, no-store', 'X-Robots-Tag: noindex, nofollow'), 'Personal learning summaries must explicitly forbid shared storage and indexing.');
		dashboard_controller_assert($dashboard->load->calls === array(array('model', 'Content_model'), array('resume', 71), array('progress', 71), array('courses', 71)), 'All learner queries must receive only the authenticated identity and no family lookup.');
		dashboard_controller_assert($dashboard->rendered[0] === 'dashboard/index' && $dashboard->rendered[1]['resume_lessons'] === $resume, 'Empty, unavailable and populated resume states must reach the view unchanged.');
		dashboard_controller_assert($dashboard->rendered[1]['children'] === array(), 'Learner dashboard must not add another person data.');
		$cases++;
	}
}
foreach (array(FALSE, array(array('lesson_id' => 999))) as $resume)
{
	$parent = new Dashboard(array('id' => 72, 'account_kind' => 'parent'), $resume);
	$parent->index();
	dashboard_controller_assert($parent->load->calls === array(array('model', 'Content_model'), array('model', 'Flashcard_model'), array('children', 72), array('family_courses', 72), array('family_flashcards', 72)), 'Parent dashboard must retain its own family queries without a learner-resume lookup.');
	dashboard_controller_assert($parent->rendered[1]['resume_lessons'] === array(), 'Parent must receive no resume lesson from injected model data.');
	$cases++;
}
$guest = new Dashboard(NULL, array(array('lesson_id' => 999)));
try { $guest->index(); throw new LogicException('Guest should be rejected'); }
catch (RuntimeException $error) { dashboard_controller_assert($error->getMessage() === 'Synthetic login required', 'Guest rejection must occur before reading progress.'); }
dashboard_controller_assert($guest->load->calls === array() && $guest->rendered === NULL, 'Unauthenticated requests must not read or render learner data.');
echo 'PASS '.($cases + 1)." dashboard controller cases: authenticated identity, unchanged empty/failure/list states, family-only parent flow and guest rejection. Mock guards/services only; real HTTP permissions require integration tests.\n";
