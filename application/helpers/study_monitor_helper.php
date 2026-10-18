<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function study_monitor_modules()
{
	return array('materi' => 'Kursus & materi', 'latihan' => 'Kuis / latihan', 'flashcard' => 'Flashcard kursus', 'kartu_arena' => 'Flashcard Arena', 'english_lesson' => 'Inggris · pelajaran', 'english_test' => 'Inggris · evaluasi', 'permainan' => 'Cocokkan Pasangan kursus', 'arena' => 'Arena · mini games', 'battle' => 'Battle privat');
}

function study_monitor_url($path, $filters, $extra = array())
{
	$query = array_intersect_key($filters, array_flip(array('mulai', 'sampai', 'modul', 'status', 'kursus', 'paket', 'jenjang', 'aktivitas', 'urutan', 'pencarian', 'konten')));
	$query = array_filter(array_merge($query, $extra), static function ($value) {
		return $value !== ''; });
	return site_url($path).($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
}

function study_monitor_state($state)
{
	return array('completed' => 'Selesai / dikenal', 'running' => 'Sedang belajar', 'abandoned' => 'Dihentikan')[$state] ?? 'Belum tercatat';
}
