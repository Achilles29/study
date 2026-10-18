<?php
// Read only the two inspected literal data files, never bootstrap a Pustaka model/database.
if (PHP_SAPI !== 'cli' || empty($argv[1])) { exit(1); }
define('BASEPATH', __DIR__);
$root = rtrim($argv[1], '/').'/application/models/data/';
$sources = array();
foreach (array('english_course_stage1.php', 'english_course_stage1_enrichment.php') as $file)
{
	$sources[] = array('file' => $file, 'sha256' => hash_file('sha256', $root.$file));
}
$chapters = require $root.'english_course_stage1.php';
$extras = require $root.'english_course_stage1_enrichment.php';
$packs = array(); $lesson_slugs = array(); $chapter_pools = array();
function english_card($pair, $kind = 'read', $note = '')
{
	return array('kind' => $kind, 'english' => $pair[0], 'translation' => $pair[1], 'note' => $note);
}
function english_questions($pool, $limit)
{
	$unique = array(); foreach ($pool as $line) { $unique[$line[0]] = $line; } $pool = array_values($unique);
	$questions = array();
	// Traverse without repeated prompts, unlike the source's modulo stride on a 35-item pool.
	foreach (array_slice($pool, 0, $limit) as $i => $line)
	{
		$options = array($line[1]);
		for ($j = 1; count($options) < 4 && $j < count($pool); $j++) { $candidate = $pool[($i + $j) % count($pool)][1]; if ( ! in_array($candidate, $options, TRUE)) { $options[] = $candidate; } }
		if (count($options) !== 4) { throw new RuntimeException('Insufficient distinct choices.'); }
		$questions[] = array('kind' => 'choice', 'prompt' => 'Pilih arti: '.$line[0], 'options' => $options, 'answer' => $line[1], 'explanation' => $line[0].' — '.$line[1]);
	}
	return $questions;
}
function english_pack($slug, $title, $kind, $chapter, $chapter_title, $items, $requires = array())
{
	return array('slug' => $slug, 'type' => 'english', 'title' => $title, 'stage' => 'primary', 'kind' => $kind, 'chapter' => $chapter, 'chapter_title' => $chapter_title, 'items' => $items, 'requires' => $requires);
}
foreach ($chapters as $index => $chapter)
{
	$n = $index + 1; $extra = $extras[$chapter[0]]; $base = $chapter[6]; $prefix = 'inggris-'.$chapter[0];
	// All mission work uses fictional characters; do not request/store personal names or addresses.
	if ($chapter[0] === 'hello-english') { $extra['missions'][1] = array('Introduce a fictional character named Rani.', 'Perkenalkan tokoh rekaan bernama Rani.'); }
	$dialogue = array(); foreach ($base as $i => $line) { $dialogue[] = english_card($line); $dialogue[] = english_card($extra['replies'][$i]); }
	$grammar = array(); foreach ($extra['rules'] as $line) { $grammar[] = english_card($line, 'read', $chapter[4]); } foreach ($base as $line) { $grammar[] = english_card($line, 'sentence', $chapter[4]); }
	$vocabulary = array(); $pairs = array(); foreach (explode('|', $extra['vocab']) as $entry) { $pair = explode('=', $entry, 2); $pairs[] = $pair; $vocabulary[] = english_card($pair); }
	$skills = array(); $skill_source = array_merge($extra['rules'], $extra['replies']);
	foreach (english_questions($skill_source, 10) as $i => $question) { $skills[] = $i % 3 === 1 ? english_card($skill_source[$i], 'sentence', $chapter[4]) : $question; }
	$mission = array(); foreach (array_merge($extra['missions'], $base) as $line) { $mission[] = english_card($line, 'mission', 'Gunakan tokoh rekaan. Latih secara mandiri atau bersama pendamping; tidak perlu mengirim nama, alamat, rekaman, atau jawaban pribadi.'); }
	$chapter_lessons = array();
	foreach (array('dialogue' => array('Percakapan', $dialogue), 'grammar' => array('Pola Kalimat', $grammar), 'vocabulary' => array('Kosakata', $vocabulary), 'skills' => array('Latihan Terarah', $skills), 'mission' => array('Misi Komunikasi', $mission)) as $kind => $data)
	{
		$slug = $prefix.'-'.$kind; $chapter_lessons[] = $slug; $lesson_slugs[] = $slug;
		$packs[] = english_pack($slug, $chapter[1].' · '.$data[0], 'lesson', $n, $chapter[1], $data[1]);
	}
	$pool = array_merge($base, $extra['rules'], $extra['replies'], $pairs); $chapter_pools[$n] = $pool;
	$packs[] = english_pack($prefix.'-checkpoint', $chapter[1].' · Checkpoint', 'checkpoint', $n, $chapter[1], english_questions($pool, 12), $chapter_lessons);
}
for ($part = 1; $part <= 2; $part++)
{
	$pool = array(); foreach (array_slice($chapter_pools, ($part - 1) * 6, 6) as $lines) { $pool = array_merge($pool, $lines); }
	// Interleave chapters so the review covers all six, not only the first chapter.
	$pool = array(); for ($i = 0; $i < 5; $i++) { foreach (array_slice($chapter_pools, ($part - 1) * 6, 6) as $lines) { $pool[] = $lines[$i]; } }
	$packs[] = english_pack('inggris-review-'.$part, 'Review Bab '.($part === 1 ? '1–6' : '7–12'), 'review', 0, 'Review Stage 1', english_questions($pool, 20), array_slice($lesson_slugs, ($part - 1) * 30, 30));
}
$items = array(); $translation_pool = array(); $vocab_pool = array(); $english_pool = array(); $reply_pool = array();
foreach ($chapters as $chapter)
{
	$extra = $extras[$chapter[0]]; foreach ($chapter[6] as $line) { $translation_pool[] = $line[1]; $english_pool[] = $line[0]; }
	foreach ($extra['rules'] as $line) { $english_pool[] = $line[0]; } foreach ($extra['replies'] as $line) { $reply_pool[] = $line[0]; }
	foreach (explode('|', $extra['vocab']) as $entry) { $vocab_pool[] = explode('=', $entry, 2)[1]; }
}
foreach ($chapters as $i => $chapter) { $line = $chapter[6][$i % 5]; $items[] = array('Pilih arti: '.$line[0], $line[1], $translation_pool, $chapter[4].' — '.$line[1]); }
foreach (array_slice($chapters, 0, 6) as $i => $chapter) { $pair = explode('=', explode('|', $extras[$chapter[0]]['vocab'])[$i % 10], 2); $items[] = array('Apa arti: '.$pair[0].'?', $pair[1], $vocab_pool, $pair[0].' — '.$pair[1]); }
foreach (array_slice($chapters, 6, 6) as $i => $chapter) { $line = $extras[$chapter[0]]['rules'][$i % 5]; $items[] = array('Pilih kalimat Inggris: '.$line[1], $line[0], $english_pool, $chapter[4]); }
foreach (array_slice($chapters, 0, 6) as $i => $chapter) { $line = $chapter[6][$i % 5]; $reply = $extras[$chapter[0]]['replies'][$i % 5]; $items[] = array('Pilih respons pada dialog yang dipelajari: '.$line[0], $reply[0], $reply_pool, $reply[0].' — '.$reply[1]); }
$final = array(); foreach ($items as $item) { $wrong = array_values(array_unique(array_filter($item[2], static function ($v) use ($item) {
	return $v !== $item[1]; }))); $final[] = array('kind' => 'choice', 'prompt' => $item[0], 'options' => array_merge(array($item[1]), array_slice($wrong, 0, 3)), 'answer' => $item[1], 'explanation' => $item[3]); }
$packs[] = english_pack('inggris-final-stage-1', 'Evaluasi Akhir Stage 1', 'final', 0, 'Evaluasi Stage 1', $final, $lesson_slugs);
if (count($packs) !== 75 || count($final) !== 30) { throw new RuntimeException('Catalog count mismatch.'); }
echo json_encode(array('version' => 1, 'source' => 'Pustaka active Stage 1; adapted privacy, server assessment, no user/progress import.', 'sources' => $sources, 'packs' => $packs), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
