<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line); });
class Reading_recovery_context
{
	public $result;
	public $ajax = FALSE;
	public $method = 'POST';
	public $position = '3700';
	public $card_status = 'known';
	public $access = TRUE;
	public $mutated = FALSE;
	public $missing = FALSE;
	public $status = 200;
	public $headers = array();
	public $flash = array();
	public $render;
	public $json;
	public $redirect;
	public $error;
	public $token_reads = 0;
	public $slug = 'Materi & "特別"/uji';
}
class Reading_recovery_service
{
	private $context;
	public function __construct($context)
	{
		$this->context = $context; }
	public function __call($name, $args)
	{
		$c = $this->context;
		if ($name === 'save_lesson_position' || $name === 'complete_lesson' || $name === 'set_card_status')
		{
			if ($c->mutated || array_slice($args, 0, 2) !== array(71, 17)) { throw new LogicException('Repeated or wrong-owner mutation.'); }
			$c->mutated = TRUE; return $c->result;
		}
		if ($name === 'get_published_lesson')
		{
			if ($c->mutated) { throw new LogicException('Forbidden read after mutation.'); }
			return $c->missing ? NULL : array('id' => 17, 'slug' => $c->slug);
		}
		if ($name === 'model') { if ($c->mutated) { throw new LogicException('Late model load.'); } return; }
		if ($name === 'can_access_lesson') { if ($c->mutated) { throw new LogicException('Late access lookup.'); } return $c->access; }
		if ($name === 'method') { return $c->method; }
		if ($name === 'post') { return $args[0] === 'status' ? $c->card_status : $c->position; }
		if ($name === 'is_ajax_request') { return $c->ajax; }
		if ($name === 'set_status_header') { $c->status = $args[0]; return $this; }
		if ($name === 'set_header') { $c->headers[] = $args[0]; return $this; }
		if ($name === 'set_content_type') { return $this; }
		if ($name === 'set_output') { $c->json = json_decode($args[0], TRUE, 512, JSON_THROW_ON_ERROR); return $this; }
		if ($name === 'set_flashdata') { if (($c->result['code'] ?? 0) === 503) { throw new LogicException('503 cannot flash.'); } $c->flash[] = $args; return; }
		if ($name === 'get_csrf_hash') { if (($c->result['code'] ?? 0) === 503) { throw new LogicException('503 cannot generate retry token.'); } $c->token_reads++; return 'rotated-token'; }
		throw new LogicException('Unmodeled dependency: '.$name);
	}
}
class MY_Controller
{
	public static $context;
	protected $current_user = array('id' => 71);
	private $content_model;
	public $input;
	public $output;
	public $session;
	public $security;
	public $load;
	public function __construct()
	{
		foreach (array('content_model', 'input', 'output', 'session', 'security', 'load') as $property) { $this->$property = new Reading_recovery_service(self::$context); }
	}
	public function __get($name)
	{
		if ($name === 'Content_model' || $name === 'Flashcard_model') { return $this->content_model; }
		throw new LogicException('Unexpected controller service.');
	}
	protected function require_login()
	{}
	protected function render_page($view, $data)
	{
		self::$context->render = array($view, $data); }
}
function redirect($path, $method = 'auto', $code = NULL)
{
	MY_Controller::$context->redirect = array($path, $method, $code); }
function show_error($message, $code, $heading)
{
	MY_Controller::$context->error = $code; }
function show_404()
{
	MY_Controller::$context->error = 404; }
function html_escape($text)
{
	return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function site_url($path)
{
	return 'http://localhost/study/index.php/'.$path; }
function form_open()
{
	throw new LogicException('No recovery form.'); }
require __DIR__.'/../application/controllers/Catalog.php';
$checks = 0;
function reading_recovery_assert($value, $message)
{
	global $checks; if ( ! $value) { throw new LogicException($message); } $checks++; }
foreach (array('complete_lesson', 'save_lesson_position') as $method)
{
	foreach (array(FALSE, TRUE) as $ajax)
	{
		foreach (array('unavailable', 'uncertain', 'unknown') as $failure)
		{
			$c = new Reading_recovery_context(); $c->ajax = $ajax; $c->result = array('ok' => FALSE, 'code' => 503, 'failure' => $failure); MY_Controller::$context = $c;
			(new Catalog())->$method('untrusted-route');
			reading_recovery_assert($c->mutated && $c->status === 503 && ! $c->redirect && ! $c->flash && $c->token_reads === 0, '503 without redirect/flash/token.');
			reading_recovery_assert(in_array('Cache-Control: private, no-store', $c->headers, TRUE) && in_array('X-Robots-Tag: noindex, nofollow', $c->headers, TRUE), 'Recovery privacy headers.');
			if ($ajax && $method === 'save_lesson_position')
			{
				reading_recovery_assert($c->json['ok'] === FALSE && $c->json['failure'] === ($failure === 'unavailable' ? 'unavailable' : 'uncertain') && ! isset($c->json['csrf_hash']) && ! $c->render, 'AJAX result has no token or rendered page.');
			}
			else
			{
				reading_recovery_assert($c->render[0] === 'catalog/recovery' && $c->render[1]['suppress_flash'] === TRUE && $c->render[1]['recovery_path'] === 'materi/'.rawurlencode($c->slug), 'Recovery uses actual database slug and suppresses stale flash.');
				$data = $c->render[1]; extract($data, EXTR_OVERWRITE); ob_start(); require __DIR__.'/../application/views/catalog/recovery.php'; $html = ob_get_clean();
				reading_recovery_assert(strpos($html, 'data-reading-recovery="'.($failure === 'unavailable' ? 'unavailable' : 'uncertain').'"') !== FALSE, 'Correct reading certainty marker.');
				reading_recovery_assert(strpos($html, 'Periksa kembali progres baca') !== FALSE && strpos($html, site_url('beranda')) !== FALSE, 'Reading heading and existing dashboard route.');
				reading_recovery_assert(substr_count($html, '<a ') === 2 && ! preg_match('/<(?:form|input|button|script|progress)\b/i', $html), 'Only native GET recovery actions.');
				reading_recovery_assert(strpos($html, html_escape(site_url('materi/'.rawurlencode($c->slug)))) !== FALSE, 'Encoded and escaped recovery URL.');
			}
		}
		foreach (array(TRUE, FALSE) as $success)
		{
			$c = new Reading_recovery_context(); $c->ajax = $ajax; $c->result = $success ? array('ok' => TRUE) : array('ok' => FALSE, 'code' => 403); MY_Controller::$context = $c;
			(new Catalog())->$method('old-route');
			if ($ajax && $method === 'save_lesson_position') { reading_recovery_assert($c->json['ok'] === $success && $c->status === ($success ? 200 : 403) && $c->token_reads === 1, 'Legacy AJAX success/domain contract retained.'); }
			else { reading_recovery_assert($c->redirect === array('materi/'.rawurlencode($c->slug), 'location', 303) && $c->flash[0][0] === ($success ? 'success' : 'error'), 'Native POST redirects with 303 and truthful feedback.'); }
		}
	}
	$c = new Reading_recovery_context(); $c->method = 'GET'; MY_Controller::$context = $c; (new Catalog())->$method('route');
	reading_recovery_assert($c->error === 404 && ! $c->mutated, 'GET cannot mutate.');
	$c = new Reading_recovery_context(); $c->missing = TRUE; MY_Controller::$context = $c; (new Catalog())->$method('route');
	reading_recovery_assert($c->error === 404 && ! $c->mutated, 'Missing preflight lesson cannot mutate.');
}
foreach (array(array(1), '10001', '-1', '', '01') as $invalid)
{
	$c = new Reading_recovery_context(); $c->position = $invalid; MY_Controller::$context = $c; (new Catalog())->save_lesson_position('route');
	reading_recovery_assert($c->error === 400 && ! $c->mutated, 'Invalid position rejected before mutation.');
}
echo 'PASS '.$checks." reading controller/view assertions. Isolated dependencies, not actual HTTP 503 or browser rendering.\n";

$checks = 0;
foreach (array('known', 'learning') as $status)
{
	foreach (array('unavailable', 'uncertain', 'unknown') as $failure)
	{
		$c = new Reading_recovery_context(); $c->card_status = $status; $c->result = array('ok' => FALSE, 'code' => 503, 'failure' => $failure); MY_Controller::$context = $c;
		(new Catalog())->save_flashcard_progress('untrusted-route', '31');
		reading_recovery_assert($c->mutated && $c->status === 503 && ! $c->redirect && ! $c->flash && $c->token_reads === 0, 'Card failure cannot redirect/flash/read token.');
		reading_recovery_assert(in_array('Cache-Control: private, no-store', $c->headers, TRUE) && in_array('X-Robots-Tag: noindex, nofollow', $c->headers, TRUE), 'Private card recovery.');
		reading_recovery_assert($c->render[0] === 'catalog/card_recovery' && $c->render[1]['suppress_flash'] === TRUE && $c->render[1]['recovery_path'] === 'materi/'.rawurlencode($c->slug).'#kartu-belajar', 'Card recovery uses server slug and fixed fragment.');
		$data = $c->render[1]; extract($data, EXTR_OVERWRITE); ob_start(); require __DIR__.'/../application/views/catalog/card_recovery.php'; $html = ob_get_clean();
		reading_recovery_assert(strpos($html, 'data-flashcard-recovery="'.($failure === 'unavailable' ? 'unavailable' : 'uncertain').'"') !== FALSE, 'Card-specific certainty.');
		reading_recovery_assert(strpos($html, 'Periksa kembali kartu belajar') !== FALSE && strpos($html, site_url('beranda')) !== FALSE && strpos($html, html_escape(site_url($data['recovery_path']))) !== FALSE, 'Card heading and encoded GET destinations.');
		reading_recovery_assert(substr_count($html, '<a ') === 2 && ! preg_match('/<(?:form|input|button|script|progress)\b/i', $html), 'Card recovery is read-only.');
	}
	foreach (array(200, 400, 403) as $code)
	{
		$c = new Reading_recovery_context(); $c->card_status = $status; $c->result = $code === 200 ? array('ok' => TRUE) : array('ok' => FALSE, 'code' => $code); MY_Controller::$context = $c;
		(new Catalog())->save_flashcard_progress('old-route', '31');
		if ($code === 200) { reading_recovery_assert($c->redirect === array('materi/'.rawurlencode($c->slug).'#kartu-belajar', 'location', 303) && count($c->flash) === 1 && $c->flash[0][0] === 'success', 'Confirmed card save redirects with 303 only.'); }
		else { reading_recovery_assert($c->error === $code && ! $c->redirect && ! $c->flash && ! $c->render, 'Card domain rejection preserves status and has no success.'); }
	}
}
foreach (array('get', 'missing', 'access', 'invalid_status') as $preflight)
{
	$c = new Reading_recovery_context(); MY_Controller::$context = $c;
	if ($preflight === 'get') { $c->method = 'GET'; }
	if ($preflight === 'missing') { $c->missing = TRUE; }
	if ($preflight === 'access') { $c->access = FALSE; }
	if ($preflight === 'invalid_status') { $c->card_status = array('known'); }
	(new Catalog())->save_flashcard_progress('route', '31');
	reading_recovery_assert( ! $c->mutated && $c->error === ($preflight === 'access' ? 403 : ($preflight === 'invalid_status' ? 400 : 404)), 'Card preflight denies without mutation.');
}
echo 'PASS '.$checks." flashcard controller/view assertions using shared Catalog fixture; no real HTTP 503 or DB failure.\n";
