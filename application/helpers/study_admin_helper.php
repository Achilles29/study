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
		array('Pembelajaran', 'Kursus & materi', 'kelola/kursus', 'bi-journal-richtext', $content, 'courses'),
		array('Pembelajaran', 'Data master', 'kelola/kategori', 'bi-tags', $content, 'categories'),
		array('Pembelajaran', 'Arena Belajar', 'kelola/arena', 'bi-controller', $owner, 'arena'),
		array('Pembelajaran', 'Moderasi battle', 'kelola/battle', 'bi-shield-check', $owner, 'battle'),
		array('Pembelajaran', 'Progres pengguna', 'kelola/pembelajaran', 'bi-graph-up', $owner, 'learning'),
		array('Pembelajaran', 'Progres per modul', 'kelola/pembelajaran/modul', 'bi-collection', $owner, 'modules'),
		array('Pembelajaran', 'Rapor belajar', 'kelola/rapor', 'bi-file-earmark-bar-graph', $owner, 'reports'),
		array('Pembelajaran', 'Poin & penukaran', 'kelola/poin', 'bi-award', $owner, 'points'),
		array('Operasional', 'Jejak audit', 'kelola/audit', 'bi-shield-check', $owner, 'audit'),
		array('Operasional', 'Kotak Bantuan', 'kelola/bantuan', 'bi-chat-left-text', $owner, 'help'),
		array('Operasional', 'Kesehatan aplikasi', 'kelola/operasional', 'bi-activity', $owner, 'operations'),
		array('Trafik & analitik', 'Analitik trafik', 'kelola/trafik', 'bi-bar-chart', $owner, 'traffic'),
		array('Paket & pembayaran', 'Paket, kredit & voucher', 'kelola/paket', 'bi-wallet2', $owner, 'commerce'),
		array('Paket & pembayaran', 'Pesanan & rekonsiliasi', 'kelola/pesanan', 'bi-receipt', $owner, 'orders'),
		array('Paket & pembayaran', 'Donasi', 'kelola/donasi', 'bi-heart', $owner, 'donations'),
		array('Paket & pembayaran', 'Kredit & ledger', 'kelola/kredit', 'bi-journal-text', $owner, 'wallets'),
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

function study_page_seo($view, $data, $user)
{
	$index = ! $user && in_array($view, array('home/index', 'catalog/index', 'catalog/course'), TRUE);
	$description = 'Project Pintar, aplikasi belajar anak TK, SD, dan SMP dengan latihan soal, flashcard, permainan edukasi, dan pemantauan progres oleh orang tua.';
	$canonical = site_url('/');
	if ($view === 'home/index' && isset($data['site_settings']['seo_description'])) { $description = $data['site_settings']['seo_description']; }
	if ($view === 'catalog/index')
	{
		$canonical = site_url('kursus');
		$description = 'Jelajahi kursus dan materi belajar anak di '.($data['site_settings']['brand_name'] ?? 'Project Pintar').'. Pilih kegiatan belajar, latihan soal, dan kartu belajar sesuai kebutuhan keluarga.';
		if ( ! empty($data['has_filters'])) { $index = FALSE; }
		if (($data['page'] ?? 1) > 1) { $canonical .= '?halaman='.(int) $data['page']; }
	}
	elseif ($view === 'catalog/course' && isset($data['course']))
	{
		$canonical = site_url('kursus/'.$data['course']['slug']);
		$description = mb_substr(preg_replace('/\s+/u', ' ', strip_tags($data['course']['summary'])), 0, 160, 'UTF-8');
	}
	return array('index' => $index, 'description' => $description, 'canonical' => $canonical);
}
