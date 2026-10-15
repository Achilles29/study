<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');
define('FCPATH', dirname(__DIR__).'/');
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
function html_escape($value)
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function site_url($path)
{
	return 'http://localhost/study/index.php/'.$path;
}
function form_open($path, $attributes = array())
{
	throw new LogicException('Recovery must not create a form, request a CSRF token or offer a POST replay.');
}
function practice_recovery_view_render($data)
{
	extract($data, EXTR_SKIP);
	ob_start();
	try
	{
		require FCPATH.'application/views/practice/recovery.php';
		return ob_get_contents();
	}
	finally { ob_end_clean(); }
}
$assertions = 0;
function practice_recovery_view_assert($condition, $message)
{
	global $assertions;
	if ( ! $condition) { throw new RuntimeException($message); }
	$assertions++;
}
function practice_recovery_view_check($html, $uncertain, $path)
{
	practice_recovery_view_assert(strpos($html, 'data-practice-recovery="'.($uncertain ? 'uncertain' : 'unavailable').'"') !== FALSE, 'Recovery state must remain observable without exposing model or SQL details.');
	practice_recovery_view_assert(preg_match('/<h1\b[^>]*>Periksa kembali latihan<\/h1>/', $html) === 1 && substr_count($html, 'role="status"') === 1, 'Recovery must have a clear heading and one non-interrupting status region.');
	practice_recovery_view_assert( ! preg_match('/<(?:form|input|button|textarea|select|script|iframe|meta)\b|\son[a-z]+=|javascript:/i', $html), 'Recovery must contain no mutation controls, executable behavior, automatic retry or refresh.');
	practice_recovery_view_assert(strpos($html, 'data-unsaved-') === FALSE, 'A recovery page must not attach the answer-form unsaved guard.');
	practice_recovery_view_assert(strpos($html, 'tidak mengirim ulang permintaan') !== FALSE, 'Recovery must explain that the links do not resubmit the mutation.');
	practice_recovery_view_assert((strpos($html, 'Hasil penyimpanan belum dapat dipastikan.') !== FALSE) === $uncertain && (strpos($html, 'Permintaan belum berhasil disimpan.') !== FALSE) === ! $uncertain, 'Uncertain outcomes must not be presented as confirmed rollback or ordinary failed saving.');
	if ($uncertain) { practice_recovery_view_assert(strpos($html, 'Permintaanmu mungkin sudah diproses.') !== FALSE, 'Unknown commit results must explicitly acknowledge that processing may have succeeded.'); }
	preg_match_all('/<a\b[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/s', $html, $links, PREG_SET_ORDER);
	practice_recovery_view_assert(count($links) === 2, 'Recovery must offer only a current-state GET link and a history GET link.');
	practice_recovery_view_assert(html_entity_decode($links[0][1], ENT_QUOTES, 'UTF-8') === site_url($path) && trim(strip_tags($links[0][2])) === 'Periksa keadaan terbaru', 'The primary native anchor must preserve the controller-supplied fixed local destination.');
	practice_recovery_view_assert(html_entity_decode($links[1][1], ENT_QUOTES, 'UTF-8') === site_url('latihan') && trim(strip_tags($links[1][2])) === 'Riwayat latihan saya', 'The alternative action must only read the current learner history.');
	practice_recovery_view_assert(strpos($html, 'd-flex flex-wrap gap-2') !== FALSE, 'Recovery links must reuse the wrapping mobile-friendly action utility.');
	foreach (array('PRIVATE_POST_ANSWER', 'PRIVATE_QUERY_TOKEN', 'PRIVATE_FLASH_SUCCESS', 'PRIVATE_SQL_DETAIL', 'PRIVATE_MODEL_ID') as $private)
	{
		practice_recovery_view_assert(strpos($html, $private) === FALSE, 'Unrelated request/model/flash data must not leak into recovery.');
	}
}

$_POST = array('answers' => array(101 => 'PRIVATE_POST_ANSWER'), 'action' => 'finish');
$_GET = array('start_token' => 'PRIVATE_QUERY_TOKEN');
$renders = 0;
foreach (array('latihan/kursus/17', 'latihan/keluarga/31', 'latihan/percobaan/77') as $path)
{
	foreach (array(FALSE, TRUE) as $uncertain)
	{
		$html = practice_recovery_view_render(array('recovery_uncertain' => $uncertain, 'recovery_path' => $path, 'flash_success' => 'PRIVATE_FLASH_SUCCESS', 'error' => 'PRIVATE_SQL_DETAIL', 'attempt' => array('id' => 'PRIVATE_MODEL_ID')));
		practice_recovery_view_check($html, $uncertain, $path);
		$renders++;
	}
}
// Only the controller validates/builds the route. This extra render verifies
// attribute escaping without claiming that an arbitrary route is authorized.
$path = 'latihan/percobaan/77?label="<unsafe>&日本語';
$html = practice_recovery_view_render(array('recovery_uncertain' => TRUE, 'recovery_path' => $path));
practice_recovery_view_assert(strpos($html, 'href="'.html_escape(site_url($path)).'"') !== FALSE && strpos($html, '<unsafe>') === FALSE, 'Even unexpected route text must remain escaped once inside the href attribute.');
$renders++;

class CI_Controller
{
	public $load;
	public $session;
	public function __get($name)
	{
		throw new LogicException('Layout recovery must not access extra dependencies: '.$name);
	}
}
class Practice_recovery_layout_loader
{
	public $calls = array();
	public function view($view, $data)
	{
		$this->calls[] = array($view, $data);
	}
}
class Practice_recovery_layout_session
{
	public $reads = array();
	public $forbid_reads = FALSE;
	public function flashdata($name)
	{
		if ($this->forbid_reads) { throw new LogicException('Suppressed recovery must not even read older flash data.'); }
		$this->reads[] = $name;
		return 'PRIVATE_FLASH_'.$name;
	}
}
require FCPATH.'application/core/MY_Controller.php';
class Practice_recovery_renderer extends MY_Controller
{
	public function __construct($user)
	{
		// Deliberately exercise the real shared render method, not authentication
		// bootstrap. No database, config, framework singleton or session is loaded.
		$this->current_user = $user;
		$this->load = new Practice_recovery_layout_loader();
		$this->session = new Practice_recovery_layout_session();
	}
	public function render($view, $data)
	{
		$this->render_page($view, $data);
	}
}
$layout_cases = 0;
$users = array(
	array(NULL, 'theme-public'),
	array(array('id' => 71, 'roles' => array(), 'account_kind' => 'adult'), 'theme-adult'),
	array(array('id' => 72, 'roles' => array(), 'account_kind' => 'parent'), 'theme-parent'),
	array(array('id' => 73, 'roles' => array(), 'account_kind' => 'child', 'learning_stage' => 'primary'), 'theme-child-primary'),
	array(array('id' => 74, 'roles' => array('system_admin'), 'account_kind' => 'adult'), 'theme-admin')
);
foreach ($users as $user)
{
	foreach (array(NULL, FALSE, TRUE) as $suppress)
	{
		$renderer = new Practice_recovery_renderer($user[0]);
		$renderer->session->forbid_reads = $suppress === TRUE;
		$data = array('page_title' => 'Periksa kembali latihan', 'recovery_uncertain' => TRUE, 'recovery_path' => 'latihan/percobaan/77', 'flash_notice' => 'UNTRUSTED_NOTICE', 'flash_success' => 'UNTRUSTED_SUCCESS', 'flash_error' => 'UNTRUSTED_ERROR');
		if ($suppress !== NULL) { $data['suppress_flash'] = $suppress; }
		$renderer->render('practice/recovery', $data);
		$layout_cases++;
		practice_recovery_view_assert(array_column($renderer->load->calls, 0) === array('layouts/header', 'practice/recovery', 'layouts/footer'), 'Recovery must keep the existing shared layout order.');
		practice_recovery_view_assert($renderer->session->reads === ($suppress === TRUE ? array() : array('notice', 'success', 'error')), 'Flash suppression must avoid session reads, while absent/false opt-in preserves ordinary page behavior.');
		$rendered = $renderer->load->calls[0][1];
		practice_recovery_view_assert($renderer->load->calls[1][1] === $rendered && $renderer->load->calls[2][1] === $rendered, 'Header, recovery and footer must all receive the same suppressed notices.');
		foreach (array('notice', 'success', 'error') as $name)
		{
			practice_recovery_view_assert($rendered['flash_'.$name] === ($suppress === TRUE ? NULL : 'PRIVATE_FLASH_'.$name), 'Older or injected '.$name.' notices must not contradict an uncertain outcome.');
		}
		practice_recovery_view_assert($rendered['current_user'] === $user[0] && $rendered['theme_class'] === $user[1] && $rendered['navigation_view'] === 'practice/recovery' && $rendered['admin_theme'] === FALSE, 'Flash suppression must preserve account-specific theme and navigation context.');
	}
}
restore_error_handler();
echo 'PASS '.$assertions.' assertions across '.$renders.' recovery view renders and '.$layout_cases." shared render cases: uncertainty wording, native read-only links, escaping/privacy, no replay, and opt-in flash suppression without session/model reads.\n";
echo "LIMITATION isolated PHP rendering; actual layout HTML/assets, authentication, HTTP status, transaction durability and browser behavior require separate tests.\n";
