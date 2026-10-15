<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

class Practice_recovery_context
{
	public $calls = array();
	public $result;
	public $post;
	public $mutation_done = FALSE;
	public $forbid_later_reads = TRUE;
	public $find_count = 0;
	public $rendered;
	public $redirected;
	public $error;
	public $flashes = array();
	public $headers = array();
	public $status = 200;
	public $session_type = 'course_quiz';
	public function __construct($result, $post)
	{
		$this->result = $result;
		$this->post = $post;
	}
	public function read($name, $arguments = array())
	{
		if ($this->mutation_done && $this->forbid_later_reads) { throw new LogicException('Unexpected dependency read after mutation: '.$name); }
		$this->calls[] = array($name, $arguments);
	}
	public function mutate($name, $arguments)
	{
		if ($this->mutation_done) { throw new LogicException('A single request must never retry its mutation.'); }
		$this->calls[] = array($name, $arguments);
		$this->mutation_done = TRUE;
		return $this->result;
	}
	public function attempt()
	{
		return array('id' => 77, 'learner_user_id' => 71, 'course_id' => 17, 'session_type' => $this->session_type, 'status' => 'in_progress', 'question_count' => 1, 'answer_revision' => $this->find_count > 1 ? 18 : 17, 'title' => 'Latihan sintetis');
	}
}
class Practice_recovery_services
{
	private $context;
	private $kind;
	public function __construct($context, $kind)
	{
		$this->context = $context;
		$this->kind = $kind;
	}
	public function __call($name, $arguments)
	{
		if (in_array($name, array('start', 'save_answers'), TRUE)) { return $this->context->mutate($this->kind.'.'.$name, $arguments); }
		$this->context->read($this->kind.'.'.$name, $arguments);
		switch ($name)
		{
			case 'get_course': return array('id' => 17, 'status' => 'published', 'title' => 'Kursus sintetis');
			case 'child_session': return array('id' => 31, 'target_child_user_id' => 71, 'title' => 'Sesi sintetis');
			case 'course_access':
			case 'can_read_attempt':
			case 'can_work': return TRUE;
			case 'find_attempt':
				$this->context->find_count++;
				return $this->context->attempt();
			case 'display_questions': return array(array('id' => 101, 'selected_option_id' => 1002));
			case 'model': return NULL;
		}
		throw new LogicException('Unmodeled practice dependency: '.$name);
	}
}
class Practice_recovery_database
{
	private $context;
	public function __construct($context)
	{
		$this->context = $context;
	}
	public function __call($name, $arguments)
	{
		$this->context->read('db.'.$name, $arguments);
		if ($name === 'count_all_results') { return 1; }
		if ($name === 'row_array') { return $this->context->attempt(); }
		return $this;
	}
}
class Practice_recovery_input
{
	private $context;
	public function __construct($context)
	{
		$this->context = $context;
	}
	public function method($upper)
	{
		return $upper ? 'POST' : 'post';
	}
	public function post($name)
	{
		return $this->context->post[$name] ?? NULL;
	}
}
class Practice_recovery_output
{
	private $context;
	public function __construct($context)
	{
		$this->context = $context;
	}
	public function set_header($header)
	{
		$this->context->headers[] = $header;
		return $this;
	}
	public function set_status_header($status)
	{
		$this->context->status = $status;
		return $this;
	}
}
class Practice_recovery_session
{
	private $context;
	public function __construct($context)
	{
		$this->context = $context;
	}
	public function set_flashdata($name, $value)
	{
		$this->context->flashes[] = array($name, $value);
	}
}
class Practice_recovery_config
{
	public function item($key)
	{
		if ($key !== 'encryption_key') { throw new LogicException('Unexpected config lookup.'); }
		return 'synthetic-practice-signing-key';
	}
}
class MY_Controller
{
	public static $fixture;
	protected $current_user = array('id' => 71, 'account_kind' => 'child');
	public $load;
	public $db;
	public $input;
	public $output;
	public $session;
	public $config;
	public function __construct()
	{
		$this->load = new Practice_recovery_services(self::$fixture, 'loader');
		$this->db = new Practice_recovery_database(self::$fixture);
		$this->input = new Practice_recovery_input(self::$fixture);
		$this->output = new Practice_recovery_output(self::$fixture);
		$this->session = new Practice_recovery_session(self::$fixture);
		$this->config = new Practice_recovery_config();
	}
	public function __get($name)
	{
		if (in_array($name, array('Practice_model', 'Assignment_model', 'Content_model'), TRUE)) { return new Practice_recovery_services(self::$fixture, $name); }
		throw new LogicException('Unexpected controller property.');
	}
	protected function require_login($kinds = array())
	{
		self::$fixture->read('guard.login', $kinds);
	}
	protected function require_http_methods($methods)
	{
		self::$fixture->read('guard.methods', $methods);
	}
	protected function render_page($view, $data)
	{
		if (self::$fixture->rendered !== NULL) { throw new LogicException('A response must render only once.'); }
		self::$fixture->rendered = array($view, $data);
	}
}
function redirect($path, $method = 'auto', $code = NULL)
{
	MY_Controller::$fixture->redirected = array($path, $method, $code);
}
function show_error($message, $code = 500, $heading = '')
{
	MY_Controller::$fixture->error = array($message, $code, $heading);
}
function show_404()
{
	show_error('Synthetic missing resource', 404);
}
require __DIR__.'/../application/controllers/Practice.php';

$assertions = 0;
$cases = 0;
function practice_recovery_assert($condition, $message)
{
	global $assertions;
	if ( ! $condition) { throw new RuntimeException($message); }
	$assertions++;
}
function practice_recovery_run($route, $result, $action = 'save', $session_type = 'course_quiz')
{
	global $cases;
	$nonce = str_repeat('a', 32);
	$token = $nonce.'.'.hash_hmac('sha256', '71:17:'.$nonce, 'synthetic-practice-signing-key');
	$post = array('action' => $route === 'assigned' ? 'start' : $action, 'answers' => array(101 => '1004'), 'revision' => '17', 'start_token' => $token, 'recovery_path' => '//untrusted.invalid/PRIVATE_POST', 'learner_user_id' => '999');
	$context = new Practice_recovery_context($result, $post);
	$context->session_type = $session_type;
	$context->forbid_later_reads = $result['ok'] || ! in_array($result['code'], array(400, 409), TRUE) || $route !== 'attempt';
	MY_Controller::$fixture = $context;
	$controller = new Practice();
	$ids = array('course' => 17, 'assigned' => 31, 'attempt' => 77);
	$controller->$route($ids[$route]);
	$cases++;
	practice_recovery_assert($context->mutation_done, 'Fixture must reach the actual start/save operation.');
	practice_recovery_assert(in_array('Cache-Control: private, no-store', $context->headers, TRUE), 'Practice responses must retain private/no-store headers.');
	$mutations = array_values(array_filter($context->calls, function ($call) {
		return in_array($call[0], array('Practice_model.start', 'Assignment_model.start', 'Practice_model.save_answers'), TRUE);
	}));
	$expected = $route === 'course' ? array('Practice_model.start', array(17, 71, hash('sha256', $token))) : ($route === 'assigned' ? array('Assignment_model.start', array(31, 71)) : array('Practice_model.save_answers', array(77, 71, 17, array(101 => '1004'), $action === 'finish')));
	practice_recovery_assert($mutations === array($expected), 'Exactly one mutation must use route/session identity, validated revision and native action.');
	return $context;
}

$_GET = array('learner_user_id' => '998', 'attempt_id' => '997', 'next' => '//untrusted.invalid/PRIVATE_QUERY');
$_POST = array('answers' => 'PRIVATE_SUPERGLOBAL_ANSWERS');
foreach (array('course' => 'latihan/kursus/17', 'assigned' => 'latihan/keluarga/31', 'attempt' => 'latihan/percobaan/77') as $route => $path)
{
	foreach (array('unavailable', 'uncertain', NULL, 'unknown') as $failure)
	{
		foreach ($route === 'attempt' ? array('save', 'finish') : array('save') as $action)
		{
			$result = array('ok' => FALSE, 'code' => 503);
			if ($failure !== NULL) { $result['failure'] = $failure; }
			$context = practice_recovery_run($route, $result, $action);
			practice_recovery_assert($context->status === 503 && $context->error === NULL && $context->redirected === NULL && $context->flashes === array(), '503 must render a dedicated recovery response without generic errors, redirect or success flash.');
			practice_recovery_assert($context->rendered !== NULL && $context->rendered[0] === 'practice/recovery', 'Unavailable and uncertain outcomes need the same safe recovery view.');
			$data = $context->rendered[1];
			practice_recovery_assert($data['page_title'] === 'Periksa kembali latihan' && $data['recovery_uncertain'] === ($failure !== 'unavailable') && $data['recovery_path'] === $path, 'Recovery must distinguish uncertainty, default unknown failures safely, and offer only the fixed local GET route.');
			practice_recovery_assert(count($data) === 4 && $data['suppress_flash'] === TRUE, 'Recovery must suppress older flash notices and never carry answers, tokens, stale editable snapshots or unsaved-form scripts.');
			practice_recovery_assert(in_array('X-Robots-Tag: noindex, nofollow', $context->headers, TRUE), 'Recovery states must explicitly forbid indexing.');
		}
	}
	foreach (array(403, 404) as $code)
	{
		$context = practice_recovery_run($route, array('ok' => FALSE, 'code' => $code));
		practice_recovery_assert($context->error !== NULL && $context->error[1] === $code && $context->rendered === NULL && $context->redirected === NULL && $context->flashes === array(), 'Existing denied/not-found business errors must stop without recovery or follow-up reads.');
	}
}
foreach (array('course', 'assigned') as $route)
{
	foreach (array(400, 409) as $code)
	{
		$context = practice_recovery_run($route, array('ok' => FALSE, 'code' => $code));
		practice_recovery_assert($context->error !== NULL && $context->error[1] === $code && $context->rendered === NULL && $context->redirected === NULL, 'Start validation/conflict responses must remain terminal and never re-submit.');
	}
	$context = practice_recovery_run($route, array('ok' => TRUE, 'id' => 77, 'status' => 'in_progress'));
	practice_recovery_assert($context->redirected !== NULL && $context->redirected[0] === 'latihan/percobaan/77' && $context->redirected[2] === 303 && $context->error === NULL && $context->rendered === NULL, 'Successful or reused start must redirect to the authoritative attempt GET with HTTP 303.');
}
foreach (array('course_quiz', 'parent_assigned') as $session_type)
{
	foreach (array('save', 'finish') as $action)
	{
		foreach (array('in_progress', 'graded') as $status)
		{
			$context = practice_recovery_run('attempt', array('ok' => TRUE, 'id' => 77, 'status' => $status), $action, $session_type);
			$message = $status === 'graded' ? 'Latihan selesai. Yuk, lihat pembahasannya.' : 'Jawaban tersimpan. Kamu bisa melanjutkan latihan nanti.';
			practice_recovery_assert($context->flashes === array(array('success', $message)), 'Success/no-op feedback must use the confirmed model status, not the requested action or a new database read.');
			practice_recovery_assert($context->redirected !== NULL && $context->redirected[0] === 'latihan/percobaan/77' && $context->redirected[2] === 303 && $context->rendered === NULL && $context->error === NULL && $context->find_count === 1, 'Success including duplicate graded submission must issue one 303 without any post-commit dependency read.');
		}
	}
	foreach (array(400, 409) as $code)
	{
		$context = practice_recovery_run('attempt', array('ok' => FALSE, 'code' => $code), 'save', $session_type);
		practice_recovery_assert($context->status === $code && $context->error === NULL && $context->redirected === NULL && $context->flashes === array() && $context->find_count === 2, 'Healthy validation/old-revision recovery must keep its response code and reread current saved state.');
		practice_recovery_assert($context->rendered !== NULL && $context->rendered[0] === 'practice/attempt', 'Validation/conflict remains the native attempt page rather than unavailable recovery.');
		$data = $context->rendered[1];
		practice_recovery_assert($data['attempt']['answer_revision'] === 18 && $data['questions'] === array(array('id' => 101, 'selected_option_id' => 1002)) && is_string($data['submit_error']) && $data['submit_error'] !== '', 'Displayed revision and choices must be authoritative saved values, never rejected posted answers.');
		practice_recovery_assert($data['load_unsaved_form_script'] === TRUE && ! isset($data['unsaved_initial']), 'Authoritative active form keeps its existing clean-state unsaved guard contract.');
	}
}
restore_error_handler();
echo 'PASS '.$assertions.' assertions across '.$cases." practice controller recovery cases: no reads after 503/success, fixed GET destinations, explicit 303, confirmed-status duplicate feedback and authoritative 400/409 data.\n";
echo "LIMITATION isolated controller/services only; shared layout, SQL transaction durability, HTTP middleware, JavaScript and real database behavior are not exercised.\n";
