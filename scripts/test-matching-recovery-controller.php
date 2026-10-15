<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

class Matching_recovery_context
{
	public $result;
	public $post;
	public $calls = array();
	public $mutation_done = FALSE;
	public $forbid_later_reads = TRUE;
	public $find_count = 0;
	public $rendered;
	public $redirected;
	public $error;
	public $flashes = array();
	public $headers = array();
	public $status = 200;
	public $lesson_slug = 'Materi & "特別"/uji';
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
		if ($this->mutation_done) { throw new LogicException('A matching request must not retry its mutation.'); }
		$this->calls[] = array($name, $arguments);
		$this->mutation_done = TRUE;
		return $this->result;
	}
	public function run_data()
	{
		return array('id' => 77, 'user_id' => 71, 'lesson_id' => 17, 'status' => 'in_progress', 'revision' => $this->find_count > 1 ? 18 : 17,
			'state_json' => json_encode(array('matched' => array(), 'round_index' => 0, 'move_count' => 0)),
			'snapshot_json' => json_encode(array('cards' => array(array('id' => 1), array('id' => 2)), 'rounds' => array(array(1, 2)))));
	}
}
class Matching_recovery_service
{
	private $context;
	private $kind;
	public function __construct($context, $kind)
	{
		$this->context = $context; $this->kind = $kind;
	}
	public function __call($name, $arguments)
	{
		if (in_array($name, array('start', 'move', 'abandon'), TRUE)) { return $this->context->mutate($this->kind.'.'.$name, $arguments); }
		$this->context->read($this->kind.'.'.$name, $arguments);
		switch ($name)
		{
			case 'get_published_lesson': return array('id' => 17, 'course_id' => 27, 'slug' => $this->context->lesson_slug, 'title' => 'Materi sintetis');
			case 'can_access_lesson':
			case 'can_continue': return TRUE;
			case 'find_owned': $this->context->find_count++; return $this->context->run_data();
			case 'get_published_deck_for_lesson': return array('id' => 21);
			case 'get_published_cards':
			case 'eligible': return array(array('id' => 1), array('id' => 2));
			case 'active_id': return 77;
			case 'current_lesson': return array('id' => 17, 'slug' => $this->context->lesson_slug);
			case 'library': return NULL;
		}
		throw new LogicException('Unmodeled matching dependency: '.$name);
	}
}
class Matching_recovery_input
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
		$this->context->read('input.post', array($name));
		return $this->context->post[$name] ?? NULL;
	}
}
class Matching_recovery_output
{
	private $context;
	public function __construct($context)
	{
		$this->context = $context;
	}
	public function set_header($header)
	{
		$this->context->headers[] = $header; return $this;
	}
	public function set_status_header($status)
	{
		$this->context->status = $status; return $this;
	}
}
class Matching_recovery_session
{
	private $context;
	public function __construct($context)
	{
		$this->context = $context;
	}
	public function set_flashdata($name, $value)
	{
		if ( ! $this->context->result['ok']) { throw new LogicException('Failed matching mutation must not set success/feedback flash.'); }
		$this->context->flashes[] = array($name, $value);
	}
	public function flashdata($name)
	{
		$this->context->read('session.flashdata', array($name)); return NULL;
	}
}
class Matching_recovery_config
{
	private $context;
	public function __construct($context)
	{
		$this->context = $context;
	}
	public function item($name)
	{
		$this->context->read('config.item', array($name));
		if ($name !== 'encryption_key') { throw new LogicException('Unexpected configuration lookup.'); }
		return 'synthetic-matching-signing-key';
	}
}
class MY_Controller
{
	public static $fixture;
	protected $current_user = array('id' => 71, 'account_kind' => 'child');
	public $load;
	public $input;
	public $output;
	public $session;
	public $config;
	public function __get($name)
	{
		if (in_array($name, array('Matching_run_model', 'Content_model', 'Flashcard_model', 'matching_cards'), TRUE)) { return new Matching_recovery_service(self::$fixture, $name); }
		throw new LogicException('Unexpected controller property or direct database access.');
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
	throw new LogicException('Unexpected terminal domain error in valid synthetic controller fixture.');
}
function show_404()
{
	show_error('Synthetic missing resource', 404);
}
require __DIR__.'/../application/controllers/Matching.php';
class Matching_recovery_controller extends Matching
{
	public function __construct($context)
	{
		// Bypass authentication/CI bootstrap; execute real action methods only.
		self::$fixture = $context;
		$this->load = new Matching_recovery_service($context, 'loader');
		$this->input = new Matching_recovery_input($context);
		$this->output = new Matching_recovery_output($context);
		$this->session = new Matching_recovery_session($context);
		$this->config = new Matching_recovery_config($context);
	}
}

$assertions = 0; $cases = 0;
function matching_recovery_assert($condition, $message)
{
	global $assertions;
	if ( ! $condition) { throw new RuntimeException($message); }
	$assertions++;
}
function matching_recovery_run($route, $result, $right = '2')
{
	global $cases;
	$purpose = $route === 'lesson' ? 'start:17' : ($route === 'run' ? 'move:77:17' : 'stop:77:17');
	$nonce = str_repeat('a', 32);
	$token = $nonce.'.'.hash_hmac('sha256', '71:'.$purpose.':'.$nonce, 'synthetic-matching-signing-key');
	$post = array('revision' => '17', 'action_token' => $token, 'left' => '2', 'right' => $right, 'confirm' => '1', 'recovery_path' => '//untrusted.invalid/PRIVATE_POST', 'user_id' => '999');
	$context = new Matching_recovery_context($result, $post);
	$context->forbid_later_reads = $result['ok'] || $result['code'] === 503;
	$controller = new Matching_recovery_controller($context);
	$controller->$route($route === 'lesson' ? 'requested-route-slug' : '77');
	$cases++;
	matching_recovery_assert($context->mutation_done, 'Valid signed fixture must reach the actual matching mutation.');
	$mutations = array_values(array_filter($context->calls, function ($call) {
		return in_array($call[0], array('Matching_run_model.start', 'Matching_run_model.move', 'Matching_run_model.abandon'), TRUE);
	}));
	$expected = $route === 'lesson' ? array('Matching_run_model.start', array(17, 71, hash('sha256', $token))) : ($route === 'run' ? array('Matching_run_model.move', array('77', 71, 17, hash('sha256', $token), 2, (int) $right)) : array('Matching_run_model.abandon', array('77', 71, 17)));
	matching_recovery_assert($mutations === array($expected), 'Exactly one mutation must use route/session identity, validated signature, revision and choices.');
	if ($context->forbid_later_reads)
	{
		matching_recovery_assert(end($context->calls) === $expected, 'No token, loader, config, flash or model read may follow a 503 or confirmed-success mutation.');
	}
	return $context;
}

$_GET = array('user_id' => '998', 'next' => '//untrusted.invalid/PRIVATE_QUERY');
$_POST = array('action_token' => 'PRIVATE_SUPERGLOBAL_TOKEN');
foreach (array('lesson', 'run', 'stop') as $route)
{
	foreach (array('unavailable', 'uncertain', NULL, 'unknown') as $failure)
	{
		$result = array('ok' => FALSE, 'code' => 503);
		if ($failure !== NULL) { $result['failure'] = $failure; }
		$context = matching_recovery_run($route, $result);
		$path = $route === 'lesson' ? 'permainan/tersimpan/pelajaran/'.rawurlencode($context->lesson_slug) : 'permainan/tersimpan/77';
		matching_recovery_assert($context->status === 503 && $context->error === NULL && $context->redirected === NULL && $context->flashes === array(), '503 must render recovery without redirect, generic error or optimistic feedback.');
		matching_recovery_assert($context->rendered !== NULL && $context->rendered[0] === 'games/recovery', 'All matching transaction failures must use the dedicated recovery view.');
		$data = $context->rendered[1];
		matching_recovery_assert($data === array('page_title' => 'Periksa kembali permainan', 'recovery_uncertain' => $failure !== 'unavailable', 'recovery_path' => $path, 'suppress_flash' => TRUE), 'Recovery uses only fixed encoded local routes and defaults unknown outcomes to uncertain, without run/progress/tokens/scripts.');
		matching_recovery_assert(in_array('X-Robots-Tag: noindex, nofollow', $context->headers, TRUE), 'Recovery must explicitly prohibit indexing.');
		matching_recovery_assert($context->find_count === ($route === 'lesson' ? 0 : 1), '503 permits the initial ownership check but never rereads run state.');
	}
	$context = matching_recovery_run($route, array('ok' => TRUE, 'id' => 77));
	matching_recovery_assert($context->redirected === array('permainan/tersimpan/77'.($route === 'run' ? '#hasil-pilihan' : ''), 'location', 303) && $context->rendered === NULL && $context->error === NULL, 'Confirmed success retains native 303 POST/Redirect/GET without reread.');
	matching_recovery_assert($context->flashes === ($route === 'run' ? array(array('matching_feedback', 'Cocok! Pasanganmu sudah tersimpan.')) : array()), 'Only a confirmed choice saves its existing matching feedback.');
	foreach (array(400, 403, 404, 409) as $code)
	{
		$context = matching_recovery_run($route, array('ok' => FALSE, 'code' => $code));
		$view = $route === 'lesson' ? 'games/start' : 'games/'.$route;
		matching_recovery_assert($context->status === $code && $context->rendered !== NULL && $context->rendered[0] === $view && $context->redirected === NULL && $context->error === NULL && $context->flashes === array(), 'Legacy domain errors must retain their original status and normal native page handling.');
		matching_recovery_assert( ! empty($context->rendered[1]['submit_error']) && ! empty($context->rendered[1]['action_token']), 'Healthy domain rejection retains its explanatory message and newly signed form token.');
		matching_recovery_assert($context->find_count === ($route === 'lesson' ? 0 : 2), 'Healthy domain rejection retains its authoritative-state read where previously required.');
		if ($route !== 'lesson') { matching_recovery_assert($context->rendered[1]['run']['revision'] === 18, 'Normal domain-error page must use reread state, not the rejected revision.'); }
	}
}
$context = matching_recovery_run('run', array('ok' => TRUE, 'id' => 77), '1');
matching_recovery_assert($context->flashes === array(array('matching_feedback', 'Belum cocok. Pilihan sudah tersimpan; coba pasangan lain, ya.')), 'Confirmed mismatched choice keeps existing non-optimistic matching outcome wording.');
restore_error_handler();
echo 'PASS '.$assertions.' assertions across '.$cases." matching recovery controller cases: valid signed actions, 503 certainty/defaults, no post-failure or post-success reads, fixed routes, 303, preserved domain handling.\n";
echo "LIMITATION constructor-free controller/service fakes; not authentication, real HTTP headers/status, SQL durability, concurrency or browser evidence.\n";
