<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function study_admin_menu($roles, $view, $section = '')
{
	$all = array('system_admin', 'content_editor', 'finance_operator', 'support_agent');
	$content = array('system_admin', 'content_editor');
	$owner = array('system_admin');
	$items = array(
		array('Ringkasan', 'Dashboard', 'kelola', 'bi-grid-1x2', $all, 'dashboard'),
		array('Pengguna & keluarga', 'Direktori pengguna', 'kelola/pengguna', 'bi-people', $owner, 'users'),
		array('Konten & ujian', 'Kursus & materi', 'kelola/kursus', 'bi-journal-richtext', $content, 'courses'),
		array('Konten & ujian', 'English Hack', 'kelola/kelas-inggris', 'bi-mortarboard', $owner, 'english_course'),
		array('Konten & ujian', 'Bank soal mandiri', 'kelola/bank-soal', 'bi-collection', $owner, 'bank'),
		array('Konten & ujian', 'Simulasi ujian', 'kelola/simulasi-ujian', 'bi-ui-checks', $owner, 'exams'),
		array('Konten & ujian', 'Data master', 'kelola/kategori', 'bi-tags', $content, 'categories'),
		array('Aktivitas belajar', 'Progres pengguna', 'kelola/pembelajaran', 'bi-graph-up', $owner, 'learning'),
		array('Aktivitas belajar', 'Progres per modul', 'kelola/pembelajaran/modul', 'bi-collection', $owner, 'modules'),
		array('Aktivitas belajar', 'Rapor belajar', 'kelola/rapor', 'bi-file-earmark-bar-graph', $owner, 'reports'),
		array('Aktivitas belajar', 'Arena Belajar', 'kelola/arena', 'bi-controller', $owner, 'arena'),
		array('Aktivitas belajar', 'Akses kredit Arena', 'kelola/akses-arena', 'bi-unlock', $owner, 'arena_access'),
		array('Aktivitas belajar', 'Moderasi battle', 'kelola/battle', 'bi-shield-check', $owner, 'battle'),
		array('Aktivitas belajar', 'Poin & penukaran', 'kelola/poin', 'bi-award', $owner, 'points'),
		array('Komunitas', 'Kotak bantuan', 'kelola/bantuan', 'bi-chat-left-text', $owner, 'help'),
		array('Komunitas', 'Moderasi ulasan', 'kelola/ulasan', 'bi-star', $owner, 'reviews'),
		array('Paket & pembayaran', 'Paket, kredit & voucher', 'kelola/paket', 'bi-wallet2', $owner, 'commerce'),
		array('Paket & pembayaran', 'Pesanan & rekonsiliasi', 'kelola/pesanan', 'bi-receipt', $owner, 'orders'),
		array('Paket & pembayaran', 'Donasi', 'kelola/donasi', 'bi-heart', $owner, 'donations'),
		array('Paket & pembayaran', 'Kredit & riwayat', 'kelola/kredit', 'bi-journal-text', $owner, 'wallets'),
		array('Trafik & operasional', 'Analitik trafik', 'kelola/trafik', 'bi-bar-chart', $owner, 'traffic'),
		array('Trafik & operasional', 'Kesehatan aplikasi', 'kelola/operasional', 'bi-activity', $owner, 'operations'),
		array('Trafik & operasional', 'Jejak audit', 'kelola/audit', 'bi-shield-check', $owner, 'audit'),
		array('Komunikasi', 'Notifikasi', 'notifikasi', 'bi-envelope', $owner, 'notifications'),
		array('Komunikasi', 'Telegram', 'kelola/pengaturan/telegram', 'bi-telegram', $owner, 'telegram'),
		array('Pengaturan', 'Pusat pengaturan', 'kelola/pengaturan', 'bi-gear', $owner, 'settings')
	);
	if ($section === '')
	{
		$section = $view === 'admin/index' ? 'dashboard' : ($view === 'admin/users' ? 'users' : ($view === 'admin/audit' ? 'audit' : (strpos($view, 'categor') !== FALSE ? 'categories' : 'courses')));
	}
	$result = array();
	foreach ($items as $item)
	{
		if (array_intersect($roles, $item[4]))
		{
			$result[$item[0]][] = array('label' => $item[1], 'path' => $item[2], 'icon' => $item[3], 'active' => $section === $item[5]);
		}
	}
	return $result;
}

function study_role_label($role)
{
	return array('system_admin' => 'Superadmin', 'content_editor' => 'Pengelola konten', 'finance_operator' => 'Keuangan', 'support_agent' => 'Dukungan')[$role] ?? 'Pengguna';
}

/** Plain-text excerpts on word boundaries; the HTML template still escapes output. */
function study_seo_excerpt($text, $limit = 160)
{
	$text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
	if (mb_strlen($text, 'UTF-8') <= $limit) { return $text; }
	$excerpt = mb_substr($text, 0, $limit - 1, 'UTF-8');
	$boundary = mb_strrpos($excerpt, ' ', 0, 'UTF-8');
	// If the next character is whitespace, the excerpt already ends at a whole word.
	if (mb_substr($text, $limit - 1, 1, 'UTF-8') !== ' ' && $boundary !== FALSE)
	{
		$excerpt = mb_substr($excerpt, 0, $boundary, 'UTF-8');
	}
	return rtrim($excerpt, " \t\n\r\0\x0B,;:.").'…';
}

function study_page_seo($view, $data, $user)
{
	$index = ! $user && in_array($view, array('home/index', 'catalog/index', 'catalog/course', 'public_learning/index'), TRUE);
	$description = 'Latihan soal online SD kelas 1–6 dengan pembahasan, materi gratis, dan flashcard. Belajar bersama Project Pintar.';
	$canonical = site_url('/');
	if ($view === 'home/index' && isset($data['site_settings']['seo_description'])) { $description = $data['site_settings']['seo_description']; }
	if ($view === 'catalog/index')
	{
		$canonical = site_url('kursus');
		$description = 'Pilih materi kursus SD, SMP, dan SMA di '.($data['site_settings']['brand_name'] ?? 'Project Pintar').'. Belajar bertahap menurut kelas, bab, dan mata pelajaran.';
		if ( ! empty($data['has_filters'])) { $index = FALSE; }
		if (($data['page'] ?? 1) > 1) { $canonical .= '?halaman='.(int) $data['page']; }
	}
	elseif ($view === 'catalog/course' && isset($data['course']))
	{
		$canonical = site_url('kursus/'.$data['course']['slug']);
		$intro = ! empty($data['question_count']) ? 'Latihan soal online dan materi ' : 'Materi ';
		$description = study_seo_excerpt($intro.$data['course']['title'].'. '.$data['course']['summary']);
	}
	elseif ($view === 'public_learning/index')
	{
		$canonical=site_url($data['path']);
		$description=study_seo_excerpt($data['page_title'].'. '.$data['intro']);
	}
	elseif ($view === 'catalog/lesson' && !empty($data['lesson']['is_free']) && empty($data['is_content_preview']))
	{
		$index=!$user;
		$canonical=site_url('materi/'.$data['lesson']['slug']);
		$description=study_seo_excerpt($data['page_title'].'. '.$data['lesson']['body_text']);
	}
	return array('index' => $index, 'description' => $description, 'canonical' => $canonical);
}
