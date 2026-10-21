<?php
define('BASEPATH', __DIR__);
require dirname(__DIR__).'/application/helpers/study_admin_helper.php';
require dirname(__DIR__).'/application/libraries/Site_settings.php';
function site_url($path = '') { return 'https://example.test/'.ltrim($path, '/'); }
$checks = 0;
function check073($condition, $message)
{
	global $checks;
	if ( ! $condition) { throw new RuntimeException($message); }
	$checks++;
}
$settings = new Site_settings();
$defaults = $settings->defaults('umum');
check073(strpos($defaults['seo_title'], 'Latihan Soal Online') !== FALSE, 'Default title');
check073(!$settings->validate('umum', $defaults)['errors'], 'Defaults valid');
$custom = array_replace($defaults, array('seo_title' => 'Belajar & berlatih bersama', 'seo_description' => 'Deskripsi pilihan pemilik.'));
check073(!$settings->validate('umum', $custom)['errors'], 'Custom text valid');
foreach (array('', str_repeat('a', 101), array('x'), "Judul\0") as $bad)
{
	check073(isset($settings->validate('umum', array_replace($defaults, array('seo_title' => $bad)))['errors']['seo_title']), 'Invalid title rejected');
}
check073(study_seo_excerpt(' <b>Bahasa</b> &amp; seni  untuk anak. ') === 'Bahasa & seni untuk anak.', 'HTML/entities/whitespace');
check073(study_seo_excerpt('Satu dua tiga empat', 12) === 'Satu dua…', 'Do not split word');
check073(study_seo_excerpt('Satu dua tiga empat', 14) === 'Satu dua tiga…', 'Keep full word at boundary');
check073(study_seo_excerpt('Mengenal café dan angka 🌟 bersama keluarga.', 26) === 'Mengenal café dan angka 🌟…', 'UTF-8 boundary');
check073(study_seo_excerpt('Materi singkat.') === 'Materi singkat.', 'No unnecessary truncation');
$home = study_page_seo('home/index', array('site_settings' => $custom), NULL);
check073($home['description'] === $custom['seo_description'] && $home['index'], 'Owner description preserved');
$data = array('site_settings' => $defaults, 'page' => 2, 'has_filters' => FALSE);
$catalog = study_page_seo('catalog/index', $data, NULL);
check073($catalog['index'] && $catalog['canonical'] === 'https://example.test/kursus?halaman=2', 'Pagination canonical');
$data['has_filters'] = TRUE;
check073(!study_page_seo('catalog/index', $data, NULL)['index'], 'Filter noindex');
$course = array('course' => array('title' => 'Matematika Kelas 6 — Pecahan, Geometri, Data, dan Peluang', 'slug' => 'matematika-kelas-6', 'summary' => str_repeat('Materi dan pembahasan yang sesuai kelas. ', 10)), 'question_count' => 24);
$seo = study_page_seo('catalog/course', $course, NULL);
check073($seo['index'] && strpos($seo['description'], 'Latihan soal online') === 0, 'Available exercises mentioned');
check073(mb_strlen($seo['description'], 'UTF-8') <= 160, 'Description bounded');
check073($seo['canonical'] === 'https://example.test/kursus/matematika-kelas-6', 'Course canonical unchanged');
$course['question_count'] = 0;
check073(strpos(study_page_seo('catalog/course', $course, NULL)['description'], 'Latihan soal') === FALSE, 'No claims for empty questions');
foreach (array('auth/login', 'admin/settings', 'catalog/lesson', 'practice/attempt') as $view)
{
	check073(!study_page_seo($view, array(), NULL)['index'], 'Private/lesson noindex unchanged');
}
foreach (array('home/index', 'catalog/index', 'catalog/course') as $view)
{
	check073(!study_page_seo($view, $course, array('id' => 1))['index'], 'Authenticated view noindex');
}
echo "PASS $checks SEO checks: defaults, custom settings, title validation, safe word excerpts, available questions only, canonical/filter/private policies.\n";
