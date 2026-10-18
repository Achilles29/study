<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// No controller, session, database, URL helper or external asset dependency.
$recovery_code = isset($status_code) ? (int) $status_code : 500;
$recovery_titles = array(
	400 => 'Permintaan perlu diperiksa',
	401 => 'Silakan masuk kembali',
	403 => 'Halaman belum dapat dibuka',
	404 => 'Halaman ini belum ditemukan',
	405 => 'Cara membuka halaman tidak sesuai',
	409 => 'Ada perubahan yang perlu diperiksa',
	429 => 'Istirahat sebentar, ya'
);
$recovery_title = $recovery_titles[$recovery_code] ?? 'Layanan sedang mengalami kendala';
$recovery_text = 'Silakan coba lagi nanti. Jika baru mengirim jawaban atau melakukan transaksi, periksa hasilnya sebelum mengulang.';
if ($recovery_code === 404)
{
	$recovery_text = 'Mungkin tautannya sudah berubah atau halaman ini belum tersedia. Mari kembali ke beranda untuk melanjutkan belajar.';
}
elseif ($recovery_code >= 400 && $recovery_code < 500)
{
	$recovery_text = isset($message) && is_string($message) ? trim(strip_tags($message)) : '';
	if ($recovery_text === 'The action you have requested is not allowed.')
	{
		$recovery_text = 'Formulir belum dapat diproses. Buka kembali halaman formulir, lalu periksa isian sebelum mengirim lagi.';
	}
	elseif ($recovery_text === 'The URI you submitted has disallowed characters.')
	{
		$recovery_text = 'Alamat halaman belum dapat dibuka. Gunakan tautan di aplikasi atau kembali ke beranda.';
	}
	elseif ($recovery_text === '')
	{
		$recovery_text = 'Periksa kembali halaman yang ingin dibuka. Jika masih mengalami kendala, minta bantuan pengelola.';
	}
}

// Never infer a return URL from request headers. Avoid config bootstrap on 5xx.
$recovery_base = getenv('APP_BASE_URL');
if ($recovery_code >= 400 && $recovery_code < 500 && function_exists('config_item'))
{
	$recovery_base = config_item('base_url');
}
$recovery_url = '';
if (is_string($recovery_base) && filter_var($recovery_base, FILTER_VALIDATE_URL)
	&& ! preg_match('/[\x00-\x20\x7f\\\\]/', $recovery_base))
{
	$recovery_parts = parse_url($recovery_base);
	if (is_array($recovery_parts) && isset($recovery_parts['host'], $recovery_parts['scheme'])
		&& in_array(strtolower($recovery_parts['scheme']), array('http', 'https'), TRUE)
		&& ! isset($recovery_parts['user']) && ! isset($recovery_parts['pass'])
		&& ! isset($recovery_parts['query']) && ! isset($recovery_parts['fragment']))
	{
		$recovery_url = rtrim($recovery_base, '/').'/';
	}
}
if ( ! headers_sent())
{
	header('Cache-Control: no-store, private');
	header('X-Robots-Tag: noindex, nofollow');
	header('Content-Type: text/html; charset=utf-8');
}
?><!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo htmlspecialchars($recovery_title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> | Project Pintar</title>
	<style>
		* { box-sizing: border-box; }
		body { margin: 0; color: #202844; background: #f7f8ff; font: 1rem/1.65 system-ui, sans-serif; }
		.skip-link { position: fixed; top: 0; left: 1rem; z-index: 2; padding: .75rem 1rem; background: #fff; transform: translateY(-150%); }
		.skip-link:focus { transform: translateY(0); }
		a { color: #403bb3; }
		a:focus-visible { outline: 3px solid #403bb3; outline-offset: 5px; }
		main { width: min(100% - 2rem, 42rem); margin: clamp(2rem, 8vh, 6rem) auto; padding: clamp(1.5rem, 5vw, 3.5rem); border: 1px solid #e8eaf2; border-radius: 1.5rem; background: #fff; overflow-wrap: anywhere; }
		.brand { margin: 0 0 2.5rem; font-weight: 750; letter-spacing: .03em; color: #403bb3; }
		.mark { display: grid; place-items: center; width: 4.5rem; height: 4.5rem; border-radius: 1.5rem 1.5rem .5rem 1.5rem; background: #eeedff; color: #403bb3; font-size: 2rem; font-weight: 750; }
		.code { margin: 1.5rem 0 .5rem; color: #596078; font-size: .875rem; font-weight: 650; }
		h1 { margin: 0 0 1rem; font-size: clamp(1.65rem, 5vw, 2.35rem); line-height: 1.25; letter-spacing: -.025em; }
		.message { margin: 0 0 1.75rem; }
		.button { display: inline-block; min-height: 48px; padding: .75rem 1.25rem; border-radius: .8rem; background: #5854d8; color: #fff; font-weight: 650; text-decoration: none; }
		.button:hover { background: #403bb3; }
		.note { margin: 1.75rem 0 0; padding-top: 1.25rem; border-top: 1px solid #e8eaf2; color: #596078; font-size: .9rem; }
		@media (forced-colors: active) { .button { border: 1px solid ButtonText; } }
	</style>
</head>
<body>
	<a class="skip-link" href="#konten-utama">Lewati ke isi halaman</a>
	<main id="konten-utama" tabindex="-1">
		<p class="brand">Project Pintar</p>
		<div class="mark" aria-hidden="true">?</div>
		<p class="code">Kode halaman <?php echo $recovery_code; ?></p>
		<h1><?php echo htmlspecialchars($recovery_title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h1>
		<p class="message"><?php echo htmlspecialchars($recovery_text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
<?php if ($recovery_url !== ''): ?>
		<a class="button" href="<?php echo htmlspecialchars($recovery_url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">Kembali ke beranda</a>
<?php endif; ?>
		<p class="note">Jika memerlukan bantuan, sampaikan kode halaman ini kepada pengelola. Jangan membagikan kata sandi atau data pribadi.</p>
	</main>
</body>
</html>
