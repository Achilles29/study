<?php
// Export published story content only; deduplicate repeated grade variants and repeated questions.
if (PHP_SAPI !== 'cli' || empty($argv[1])) { exit(1); }
define('BASEPATH', rtrim($argv[1], '/').'/system/'); define('ENVIRONMENT', 'production');
require rtrim($argv[1], '/').'/application/config/database.php';
$source_config = $db[$active_group]; $source = new mysqli($source_config['hostname'], $source_config['username'], $source_config['password'], $source_config['database'], (int) ($source_config['port'] ?? 3306));
$source->set_charset('utf8mb4'); $source->query('START TRANSACTION READ ONLY');
$passages = $source->query('SELECT id,code,title,body,summary,grade_level_id FROM learn_story_passages WHERE is_active=1 AND grade_level_id BETWEEN 3 AND 11 ORDER BY id')->fetch_all(MYSQLI_ASSOC);
$groups = array(); $old = json_decode(file_get_contents(dirname(__DIR__).'/application/config/arena_stories.json'), TRUE, 512, JSON_THROW_ON_ERROR); $existing = array_column($old['packs'], NULL, 'slug');
$duplicate_questions = 0;
foreach ($passages as $row)
{
	$body_key = hash('sha256', trim($row['body'])); $grade = (int) $row['grade_level_id'];
	if (isset($groups[$body_key])) { $groups[$body_key]['source_codes'][] = $row['code']; $groups[$body_key]['source_grades'][] = $grade - 2; continue; }
	$slug = 'cerita-'.str_replace('_', '-', $row['code']); $known = isset($existing[$slug]);
	$title = $known ? $row['title'] : preg_replace('/\s*—.*$/u', '', $row['title']).' · '.($grade <= 5 ? 'SD awal' : ($grade <= 8 ? 'SD lanjut' : 'SMP'));
	$pack = array('slug' => $slug, 'type' => 'story', 'title' => $title, 'stage' => $grade <= 8 ? 'primary' : 'secondary', 'reading' => $row['body'], 'description' => $row['summary'], 'source_codes' => array($row['code']), 'source_grades' => array($grade - 2), 'items' => array());
	$stmt = $source->prepare('SELECT question,option_a,option_b,option_c,option_d,correct_option,explanation FROM learn_story_questions WHERE passage_id=? AND is_active=1 ORDER BY sort_order,id'); $stmt->execute(array($row['id'])); $seen = array();
	foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $question)
	{
		$key = trim($question['question']); if (isset($seen[$key])) { $duplicate_questions++; continue; } $seen[$key] = TRUE;
		$options = array($question['option_a'], $question['option_b'], $question['option_c'], $question['option_d']); $correct = (int) $question['correct_option'];
		if ($correct < 0 || $correct > 3 || count(array_unique($options)) !== 4) { throw new RuntimeException('Invalid story options.'); }
		// Improve generic title/summary distractors using other existing passages, not generated filler.
		if (in_array($key, array('Judul mana yang sesuai dengan bacaan tersebut?', 'Ringkasan mana yang paling sesuai dengan bacaan?'), TRUE))
		{
			$answer = $options[$correct]; $field = strpos($key, 'Judul') === 0 ? 'title' : 'summary'; $candidates = array();
			foreach ($passages as $other) { if ($other['id'] !== $row['id'] && $other[$field] !== $answer && trim($other[$field] ?? '') !== '') { $candidates[$other[$field]] = $other[$field]; } }
			$options = array_merge(array($answer), array_slice(array_values($candidates), 0, 3)); $correct = 0;
		}
		$pack['items'][] = array('definition' => $key, 'term' => array('A', 'B', 'C', 'D')[$correct], 'options' => array_combine(array('A', 'B', 'C', 'D'), $options), 'explanation' => $question['explanation']);
	}
	$groups[$body_key] = $pack;
}
$source->rollback();
if (count($passages) !== 45 || count($groups) !== 22) { throw new RuntimeException('Source catalog changed: review before deployment.'); }
echo json_encode(array('version' => 2, 'source' => 'Read-only published Pustaka Story Quiz SD/SMP, 2026-10-16. Identical reading variants consolidated, duplicate questions removed, distractors sourced from other readings.', 'source_passages' => count($passages), 'removed_duplicate_questions' => $duplicate_questions, 'packs' => array_values($groups)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
