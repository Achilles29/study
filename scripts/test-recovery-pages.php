<?php
if (PHP_SAPI !== 'cli')
{
	exit(1);
}
define('BASEPATH', dirname(__DIR__).'/system/');
define('VIEWPATH', dirname(__DIR__).'/application/views/');
$without_config = in_array('--without-config', $argv, TRUE);
$test_base = 'https://study.example.test/kelas/';
$base_reads = 0;
if ( ! $without_config)
{
	function config_item($key)
	{
		global $test_base, $base_reads;
		if ($key === 'base_url')
		{
			$base_reads++;
			return $test_base;
		}
		return '';
	}
}
function is_cli()
{
	return FALSE; // Exercise CI's actual HTML error renderer in an isolated CLI process.
}
function set_status_header($code)
{
	http_response_code($code);
}
function check_recovery($condition, $label)
{
	if ( ! $condition)
	{
		throw new RuntimeException('Recovery page assertion failed: '.$label);
	}
}
require BASEPATH.'core/Exceptions.php';
$renderer = new CI_Exceptions();
$previous_base = getenv('APP_BASE_URL');
$_SERVER['HTTP_HOST'] = 'untrusted-host.example.test';
$_SERVER['HTTP_REFERER'] = 'https://untrusted-referrer.example.test/';
try
{
	putenv('APP_BASE_URL=https://study.example.test/kelas/');
	if ($without_config)
	{
		$status_code = 503;
		$message = 'private bootstrap detail';
		ob_start();
		include VIEWPATH.'errors/html/error_general.php';
		$html = ob_get_clean();
		check_recovery(strpos($html, 'href="https://study.example.test/kelas/"') !== FALSE, 'bootstrapless URL');
		check_recovery(strpos($html, 'private bootstrap detail') === FALSE, 'bootstrapless privacy');
	}
	else
	{
		foreach (array(400, 401, 403, 404, 405, 409, 429, 500, 503) as $code)
		{
			$reads_before = $base_reads;
			$html = $renderer->show_error('PRIVATE-HEADING', 'PRIVATE-DETAIL', $code === 404 ? 'error_404' : 'error_general', $code);
			check_recovery(http_response_code() === $code, 'HTTP status '.$code);
			check_recovery(strpos($html, '<html lang="id">') !== FALSE && strpos($html, 'name="viewport"') !== FALSE, 'language/viewport');
			check_recovery(strpos($html, 'PRIVATE-HEADING') === FALSE, 'heading not reflected');
			check_recovery((strpos($html, 'PRIVATE-DETAIL') !== FALSE) === ($code < 500 && $code !== 404), 'message privacy '.$code);
			check_recovery($code < 500 || $base_reads === $reads_before, 'no config bootstrap on server error');
			check_recovery(strpos($html, '<script') === FALSE && strpos($html, '<form') === FALSE && strpos($html, '<link') === FALSE, 'standalone/no replay');
		}
		$html = $renderer->show_error('<script>alert(1)</script>', '<img src=x onerror=alert(1)>Periksa "akses" & jadwal &lt;script&gt;', 'error_general', 409);
		check_recovery(strpos($html, '<img') === FALSE && strpos($html, 'Periksa &quot;akses&quot; &amp; jadwal &amp;lt;script&amp;gt;') !== FALSE, 'escape application text');
		$html = $renderer->show_error('An Error Was Encountered', 'The action you have requested is not allowed.', 'error_general', 403);
		check_recovery(strpos($html, 'Buka kembali halaman formulir') !== FALSE && strpos($html, 'The action') === FALSE, 'CSRF translation');
		$html = $renderer->show_error('', 'The URI you submitted has disallowed characters.', 'error_general', 400);
		check_recovery(strpos($html, 'Gunakan tautan di aplikasi') !== FALSE && strpos($html, 'The URI') === FALSE, 'URI translation');
		foreach (array('', '//evil.test', 'javascript:alert(1)', 'https://fixture@evil.test/', 'https://safe.test/?next=evil', 'https://safe.test/#evil', "https://safe.test/\r\nHost:evil", 'https://safe.test/\\evil') as $invalid)
		{
			$test_base = $invalid;
			putenv('APP_BASE_URL='.$invalid);
			foreach (array(404, 500) as $code)
			{
				$html = $renderer->show_error('', '', 'error_general', $code);
				check_recovery(strpos($html, 'class="button"') === FALSE, 'unsafe return URL omitted');
				check_recovery(strpos($html, 'untrusted-') === FALSE, 'no request-header fallback');
			}
		}
	}
}
finally
{
	putenv($previous_base === FALSE ? 'APP_BASE_URL' : 'APP_BASE_URL='.$previous_base);
}
echo 'PASS recovery pages: '.($without_config ? 'no framework helpers/config/database' : 'CI HTML rendering, statuses, privacy, escaping, CSRF translation and safe return URL').".\n";
