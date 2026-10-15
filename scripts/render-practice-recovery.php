<?php
// Render the production view only; this CLI fixture never loads CI, a database or a session.
if (PHP_SAPI !== 'cli') { exit(1); }

$recovery_fixture_base_url = isset($argv[1]) ? $argv[1] : '';
$recovery_fixture_kind = isset($argv[2]) ? $argv[2] : 'practice';
$recovery_fixture_url = parse_url($recovery_fixture_base_url);
if ( ! in_array(count($argv), array(2, 3), TRUE) || ! in_array($recovery_fixture_kind, array('practice', 'matching', 'reading', 'flashcard'), TRUE) || strlen($recovery_fixture_base_url) > 2048 ||
	! filter_var($recovery_fixture_base_url, FILTER_VALIDATE_URL) || ! is_array($recovery_fixture_url) ||
	! isset($recovery_fixture_url['scheme'], $recovery_fixture_url['host'], $recovery_fixture_url['path']) ||
	$recovery_fixture_url['scheme'] !== 'http' ||
	! in_array($recovery_fixture_url['host'], array('localhost', '127.0.0.1', '[::1]'), TRUE) ||
	substr($recovery_fixture_url['path'], -1) !== '/' ||
	isset($recovery_fixture_url['user']) || isset($recovery_fixture_url['pass']) || isset($recovery_fixture_url['query']) || isset($recovery_fixture_url['fragment']) ||
	preg_match('/[\x00-\x20\x7f<>"\x27`\\\\]/', $recovery_fixture_base_url))
{
	fwrite(STDERR, "Recovery fixture requires an HTTP loopback base URL ending in / without credentials/query/fragment, and optional practice, matching, reading or flashcard kind.\n");
	exit(1);
}

function html_escape($value)
{
	return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function site_url($uri)
{
	global $recovery_fixture_base_url, $recovery_fixture_kind;
	$allowed = $recovery_fixture_kind === 'matching' ? array('permainan/riwayat', 'permainan/tersimpan/1') : array('latihan', 'latihan/percobaan/1');
	if ($recovery_fixture_kind === 'reading') { $allowed = array('beranda', 'materi/uji'); }
	if ($recovery_fixture_kind === 'flashcard') { $allowed = array('beranda', 'materi/uji#kartu-belajar'); }
	if ( ! in_array($uri, $allowed, TRUE)) { throw new RuntimeException('Unexpected recovery fixture destination.'); }
	return $recovery_fixture_base_url.$uri;
}

$recovery_fixture_output = array();
try
{
	foreach (array('unavailable', 'uncertain') as $recovery_fixture_state)
	{
		$recovery_uncertain = $recovery_fixture_state === 'uncertain';
		$recovery_path = $recovery_fixture_kind === 'matching' ? 'permainan/tersimpan/1' : 'latihan/percobaan/1';
		if ($recovery_fixture_kind === 'reading') { $recovery_path = 'materi/uji'; }
		if ($recovery_fixture_kind === 'flashcard') { $recovery_path = 'materi/uji#kartu-belajar'; }
		ob_start();
		if ($recovery_fixture_kind === 'matching') { require __DIR__.'/../application/views/games/recovery.php'; }
		elseif ($recovery_fixture_kind === 'reading') { require __DIR__.'/../application/views/catalog/recovery.php'; }
		elseif ($recovery_fixture_kind === 'flashcard') { require __DIR__.'/../application/views/catalog/card_recovery.php'; }
		else { require __DIR__.'/../application/views/practice/recovery.php'; }
		$recovery_fixture_output[$recovery_fixture_state] = ob_get_clean();
	}
	fwrite(STDOUT, json_encode($recovery_fixture_output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
}
catch (Throwable $error)
{
	while (ob_get_level()) { ob_end_clean(); }
	fwrite(STDERR, "Production recovery view could not be rendered for the isolated fixture.\n");
	exit(1);
}
