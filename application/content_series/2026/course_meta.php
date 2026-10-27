<?php
defined('BASEPATH') OR exit('No direct script access allowed');

return static function ($grade, $category, array $chapters, $subtitle = '') {
	$labels = array(
		'matematika'=>'Matematika','bahasa-indonesia'=>'Bahasa Indonesia','bahasa-inggris'=>'Bahasa Inggris',
		'ipa'=>'IPA','ips'=>'IPS','ppkn'=>'Pendidikan Pancasila','penjaskes'=>'PJOK',
		'seni-budaya'=>'Seni Budaya','tik'=>'Informatika','sejarah'=>'Sejarah',
		'biologi'=>'Biologi','fisika'=>'Fisika','kimia'=>'Kimia','geografi'=>'Geografi','ekonomi'=>'Ekonomi',
		'sosiologi'=>'Sosiologi','matematika-lanjut'=>'Matematika Tingkat Lanjut',
		'koding-ka'=>'Koding dan Kecerdasan Artifisial'
	);
	if ( ! isset($labels[$category]) || ! preg_match('/\A(sd_[56]|smp_[789]|sma_1[012])\z/', $grade, $match)) { throw new InvalidArgumentException('Unknown course metadata.'); }
	list($stage, $number_text) = explode('_', $grade); $level = strtoupper($stage); $number = (int) $number_text;
	$topics = array_column($chapters, 0);
	$title = $labels[$category].' Kelas '.$number.' '.$level.($subtitle !== '' ? ' — '.$subtitle : '');
	return array(
		'title'=>$title,
		'slug'=>$category.'-kelas-'.$number.'-'.strtolower($level).'-panduan-2026',
		'summary'=>'Panduan belajar bertahap untuk kelas '.$number.' '.$level.': '.implode(', ',array_slice($topics,0,3)).', hingga '.end($topics).'. Setiap bab terdiri dari sesi pemahaman dan penerapan dengan contoh, latihan, serta pembahasan.',
		'objectives'=>'Menjelaskan konsep inti pada setiap bab dengan kata-kata sendiri; menerapkan konsep pada contoh baru; memeriksa langkah dan bukti sebelum menyimpulkan.',
		'prerequisites'=>'Siapkan catatan dan alat tulis. Mulai dari bab pertama, lalu ulangi sesi yang belum dipahami. Guru atau pendamping dapat membantu kegiatan praktik dan memeriksa sumber informasi.',
		'source'=>'Naskah, contoh, dan latihan orisinal Project Pintar 2026. Alur kelas disusun dari CP Fase '.($stage==='sd'?'C':($stage==='smp'?'D':($number===10?'E':'F'))).' Keputusan BSKAP 046/H/KR/2025 Lampiran II: https://kurikulum.kemendikdasmen.go.id/file/1753929861_manage_file.pdf . Urutan bab adalah rancangan Project Pintar, bukan urutan resmi yang wajib dipakai setiap sekolah.',
		'chapters'=>$chapters
	);
};
