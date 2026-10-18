<?php
// One-off content export, READ ONLY transaction. Never load Pustaka models or user tables.
if (PHP_SAPI !== 'cli' || empty($argv[1])) { exit(1); }
define('BASEPATH', rtrim($argv[1], '/').'/system/'); define('ENVIRONMENT', 'production');
require rtrim($argv[1], '/').'/application/config/database.php';
$source_config = $db[$active_group];
$source = new mysqli($source_config['hostname'], $source_config['username'], $source_config['password'], $source_config['database'], (int) ($source_config['port'] ?? 3306));
$source->set_charset('utf8mb4'); $source->query('START TRANSACTION READ ONLY');
$episodes = $source->query("SELECT e.id,e.code,e.title,e.subtitle,e.description,s.code season,s.title season_title FROM learn_english_rpg_episodes e JOIN learn_english_rpg_seasons s ON s.id=e.season_id WHERE e.is_active=1 AND s.is_active=1 ORDER BY s.sort_order,e.id")->fetch_all(MYSQLI_ASSOC);
$packs = array(); $previous = NULL;
foreach ($episodes as $episode)
{
	$stmt = $source->prepare('SELECT sort_order,place,speaker,emoji,dialogue,translation,prompt,choices_json,vocabulary_word,vocabulary_meaning,challenge_type,sentence_answer FROM learn_english_rpg_scenes WHERE episode_id=? AND is_active=1 ORDER BY sort_order,id'); $stmt->execute(array($episode['id'])); $scenes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	if ( ! $scenes) { continue; }
	$items = array(); $vocabulary = array();
	foreach ($scenes as $scene)
	{
		$choices = json_decode($scene['choices_json'], TRUE, 512, JSON_THROW_ON_ERROR);
		if ( ! in_array($scene['challenge_type'], array('choice', 'listening', 'sentence', 'story'), TRUE) || count($choices) < 2 || count($choices) > 4) { throw new RuntimeException('Unsupported scene; review source.'); }
		$item = array('kind' => $scene['challenge_type'], 'place' => $scene['place'], 'speaker' => $scene['speaker'], 'english' => $scene['dialogue'], 'translation' => $scene['translation'], 'prompt' => $scene['prompt'], 'choices' => array(), 'word' => $scene['vocabulary_word'], 'meaning' => $scene['vocabulary_meaning']);
		foreach ($choices as $choice) { $item['choices'][] = array('text' => $choice['text'], 'correct' => ! empty($choice['correct']), 'feedback' => $choice['feedback'] ?? '', 'path' => $choice['choice_code'] ?? ''); }
		if ($item['kind'] === 'sentence') { if ( ! $scene['sentence_answer']) { throw new RuntimeException('Missing sentence.'); } $item['answer'] = $scene['sentence_answer']; }
		elseif ($item['kind'] !== 'story' && count(array_filter($item['choices'], static function ($choice) {
			return $choice['correct']; })) !== 1) { throw new RuntimeException('Ambiguous answer.'); }
		$items[] = $item;
		if ($item['word'] && $item['meaning']) { $key = hash('sha256', json_encode(array($item['word'], $item['meaning']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); $vocabulary[$key] = array('term' => $item['word'], 'definition' => $item['meaning'], 'hint' => '', 'key' => $key); }
	}
	$slug = 'quest-'.str_replace('_', '-', $episode['code']);
	$packs[] = array('slug' => $slug, 'type' => 'quest', 'stage' => 'primary', 'title' => $episode['title'], 'description' => $episode['description'], 'season' => $episode['season'], 'season_title' => $episode['season_title'], 'requires' => $previous, 'items' => $items);
	$packs[] = array('slug' => $slug.'-kosakata', 'type' => 'flashcard', 'stage' => 'primary', 'title' => 'Quest · '.$episode['title'], 'description' => 'Tinjau kosakata episode '.$episode['title'].', tanpa nilai ujian.', 'items' => array_values($vocabulary));
	$previous = $slug;
}
$source->rollback();
echo json_encode(array('version' => 1, 'source' => 'Read-only export of active Pustaka episode/scene content on 2026-10-16; no accounts, progress, inventory ownership or secrets.', 'content_sha256' => hash('sha256', json_encode($packs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 'packs' => $packs), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
