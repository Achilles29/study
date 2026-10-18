<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function study_arena_type($type)
{
	return study_arena_games()[$type]['title'] ?? 'Permainan';
}

function study_arena_games()
{
	return array(
		'quest' => array('title' => 'English Quest', 'path' => 'english-quest', 'icon' => 'bi-compass', 'description' => 'Jelajahi cerita, tentukan tindakan tokoh, dan latih bahasa Inggris di setiap episode.'),
		'english' => array('title' => 'Kelas Bahasa Inggris', 'path' => 'kelas-inggris', 'icon' => 'bi-translate', 'description' => 'Pelajari percakapan dan kosakata, latih susunan kalimat, lalu tinjau pemahamanmu.'),
		'flashcard' => array('title' => 'Flashcard', 'path' => 'flashcard', 'icon' => 'bi-stack', 'description' => 'Buka kartu belajar dan tandai mana yang sudah dipahami atau perlu diulang.'),
		'memory' => array('title' => 'Cocokkan Pasangan', 'path' => 'pasangan', 'icon' => 'bi-grid-3x3-gap', 'description' => 'Balik kartu, ingat posisinya, lalu temukan pasangan yang cocok.'),
		'word' => array('title' => 'Susun Kata', 'path' => 'susun-kata', 'icon' => 'bi-alphabet', 'description' => 'Susun huruf acak dan temukan kata dari petunjuknya.'),
		'math' => array('title' => 'Hitung Cepat', 'path' => 'hitung-cepat', 'icon' => 'bi-calculator', 'description' => 'Asah berhitung dalam mode santai atau tantangan waktu pilihan.'),
		'expedition' => array('title' => 'Math Expedition', 'path' => 'math-expedition', 'icon' => 'bi-map', 'description' => 'Jelajahi level matematika bertahap, dengan petunjuk dan pembahasan.'),
		'story' => array('title' => 'Story Quiz', 'path' => 'story-quiz', 'icon' => 'bi-book', 'description' => 'Baca cerita pendek dan temukan maknanya melalui pertanyaan pemahaman.')
	);
}

function study_arena_game_url($type)
{
	$game = study_arena_games()[$type] ?? NULL;
	return site_url($game ? 'belajar/arena/game/'.$game['path'] : 'belajar/arena');
}

function study_arena_stage($stage)
{
	return array('early_childhood' => 'TK', 'primary' => 'SD', 'secondary' => 'SMP')[$stage] ?? 'Semua';
}

function study_arena_status($status)
{
	return array('running' => 'Berjalan', 'completed' => 'Selesai', 'abandoned' => 'Diakhiri lebih awal')[$status] ?? 'Belum tercatat';
}

function study_arena_art($type, $title = '')
{
	$themes = array(
		'library' => 'library|perpustakaan|rak terakhir|detektif|museum',
		'market' => 'market|pasar', 'ocean' => 'sea|pantai|pesisir|pirates|islands',
		'space' => 'star station|angkasa', 'castle' => 'castle|kingdom|dragon|menara',
		'mountain' => 'mountain|gunung', 'city' => 'future city|digital|informatika',
		'weather' => 'winter|payung|setetes air|waktu|my day',
		'food' => 'food|drinks|makanan|minuman', 'animals' => 'hewan|semut|belalang',
		'nature' => 'forest|jungle|green|tumbuhan|lingkungan|kebun|mangrove|sungai|ekosistem|makhluk hidup',
		'school' => 'school|sekolah|classroom|penemu', 'home' => 'home|rumah|sekitarku|daily world',
		'science' => 'sains|energi|ipa kelas', 'math' => 'matematika|numbers|geometri|aljabar|pecahan'
	);
	$key = isset(study_arena_games()[$type]) || $type === 'battle' ? $type : 'hero';
	foreach ($themes as $theme => $pattern)
	{
		if (preg_match('/'.$pattern.'/iu', $title)) { $key = $theme; break; }
	}
	return base_url('assets/images/learning/'.$key.'-v1.webp');
}

function study_arena_art_tag($type, $title = '', $class = 'arena-topic-art')
{
	return '<img class="'.html_escape($class).'" src="'.html_escape(study_arena_art($type, $title)).'" width="768" height="512" loading="lazy" decoding="async" alt="" aria-hidden="true">';
}
