<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');
define('FCPATH', dirname(__DIR__).'/');
define('APPPATH', FCPATH.'application/');
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
	throw new LogicException('Matching recovery must not create forms or fetch CSRF/action tokens.');
}
function matching_recovery_render($data)
{
	extract($data, EXTR_SKIP);
	ob_start();
	try
	{
		require APPPATH.'views/games/recovery.php';
		return ob_get_contents();
	}
	finally { ob_end_clean(); }
}
$assertions = 0; $renders = 0;
function matching_recovery_view_assert($condition, $message)
{
	global $assertions;
	if ( ! $condition) { throw new RuntimeException($message); }
	$assertions++;
}
$_POST = array('action_token' => 'PRIVATE_POST_TOKEN', 'left' => 'PRIVATE_CHOICE');
$_GET = array('next' => 'PRIVATE_QUERY_NEXT');
foreach (array('permainan/tersimpan/pelajaran/'.rawurlencode('Materi & "特別"/uji'), 'permainan/tersimpan/77') as $path)
{
	foreach (array(FALSE, TRUE) as $uncertain)
	{
		$html = matching_recovery_render(array('recovery_uncertain' => $uncertain, 'recovery_path' => $path, 'feedback' => 'PRIVATE_FEEDBACK', 'flash_success' => 'PRIVATE_FLASH_SUCCESS', 'run' => array('id' => 'PRIVATE_RUN_ID'), 'action_token' => 'PRIVATE_ACTION_TOKEN', 'error' => 'PRIVATE_SQL_DETAIL'));
		$renders++;
		matching_recovery_view_assert(strpos($html, 'data-matching-recovery="'.($uncertain ? 'uncertain' : 'unavailable').'"') !== FALSE && strpos($html, 'data-practice-recovery') === FALSE, 'Shared component must preserve the matching-specific state marker only.');
		matching_recovery_view_assert(preg_match('/<h1\b[^>]*>Periksa kembali permainan<\/h1>/', $html) === 1 && strpos($html, 'Permainan belajar') !== FALSE && substr_count($html, 'role="status"') === 1, 'Matching recovery needs its own clear heading/eyebrow and one polite status region.');
		matching_recovery_view_assert( ! preg_match('/<(?:form|input|button|textarea|select|script|iframe|meta|progress)\b|\son[a-z]+=|javascript:/i', $html), 'No mutation control, score/progress widget, script, retry or refresh may appear.');
		matching_recovery_view_assert(strpos($html, 'data-unsaved-') === FALSE && strpos($html, 'saved-matching-form') === FALSE && strpos($html, 'matching-save') === FALSE, 'Recovery must not attach an editable game or save/unsaved enhancement.');
		matching_recovery_view_assert((strpos($html, 'Hasil penyimpanan belum dapat dipastikan.') !== FALSE) === $uncertain && (strpos($html, 'Permintaan belum berhasil disimpan.') !== FALSE) === ! $uncertain, 'Uncertain result must not claim confirmed failure or rollback.');
		if ($uncertain) { matching_recovery_view_assert(strpos($html, 'Permintaanmu mungkin sudah diproses.') !== FALSE, 'Unknown outcome must acknowledge possible successful processing.'); }
		matching_recovery_view_assert(strpos($html, 'tidak mengirim ulang permintaan') !== FALSE, 'Native GET actions must be explained as not replaying the mutation.');
		matching_recovery_view_assert( ! preg_match('/pasti (?:tersimpan|hilang|dibatalkan)|Permintaan berhasil disimpan\.|Progres tersimpan|Pasanganmu sudah tersimpan/', strip_tags($html)), 'Recovery must not contain optimistic result or absolute durability claims.');
		preg_match_all('/<a\b[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/s', $html, $links, PREG_SET_ORDER);
		matching_recovery_view_assert(count($links) === 2, 'Exactly two native GET anchors must be offered.');
		matching_recovery_view_assert(html_entity_decode($links[0][1], ENT_QUOTES, 'UTF-8') === site_url($path) && trim(strip_tags($links[0][2])) === 'Periksa keadaan terbaru', 'Primary action must retain the fixed controller-supplied local route.');
		matching_recovery_view_assert(html_entity_decode($links[1][1], ENT_QUOTES, 'UTF-8') === site_url('permainan/riwayat') && trim(strip_tags($links[1][2])) === 'Riwayat permainan saya', 'Secondary action must read only matching history, not practice history.');
		matching_recovery_view_assert(strpos($html, 'd-flex flex-wrap gap-2') !== FALSE, 'Shared wrapping utilities should keep native actions responsive.');
		foreach (array('PRIVATE_POST_TOKEN', 'PRIVATE_CHOICE', 'PRIVATE_QUERY_NEXT', 'PRIVATE_FEEDBACK', 'PRIVATE_FLASH_SUCCESS', 'PRIVATE_RUN_ID', 'PRIVATE_ACTION_TOKEN', 'PRIVATE_SQL_DETAIL') as $private)
		{
			matching_recovery_view_assert(strpos($html, $private) === FALSE, 'Unrelated POST, flash, token, run or SQL data must not leak.');
		}
	}
}
$path = 'permainan/tersimpan/77?label="<unsafe>&日本語';
$html = matching_recovery_render(array('recovery_uncertain' => TRUE, 'recovery_path' => $path));
$renders++;
matching_recovery_view_assert(strpos($html, 'href="'.html_escape(site_url($path)).'"') !== FALSE && strpos($html, '<unsafe>') === FALSE && strpos($html, '&amp;lt;') === FALSE, 'Unexpected URI text must be escaped exactly once; route authorization is the controller responsibility.');
restore_error_handler();
echo 'PASS '.$assertions.' assertions across '.$renders." matching recovery view renders: shared matching text, certainty wording, native GET links, escaping/privacy, no form/progress/token/script or replay.\n";
echo "LIMITATION isolated PHP view rendering; shared flash suppression is covered by test-practice-recovery-view.php, not duplicated here. No actual HTTP, assets, browser or transaction evidence.\n";
