<?php
define('BASEPATH', dirname(__DIR__).'/system/');
require dirname(__DIR__).'/application/libraries/English_engine.php';
function verify_english($condition, $message)
{
	if ( ! $condition) { throw new RuntimeException($message); }
}
$catalog = json_decode(file_get_contents(dirname(__DIR__).'/application/config/arena_english.json'), TRUE, 512, JSON_THROW_ON_ERROR); $engine = new English_engine();
$lessons = 0; $graded = 0;
foreach ($catalog['packs'] as $pack)
{
	foreach (array(TRUE, FALSE) as $correct)
	{
		$game = $engine->create($pack, array('pass_score' => '75', 'translation' => '0', 'audio' => '0'));
		$run = array('game_type' => 'english', 'snapshot_json' => json_encode($game['snapshot'], JSON_THROW_ON_ERROR), 'state_json' => json_encode($game['state']), 'status' => 'running', 'revision' => 1, 'correct_count' => 0, 'passed' => NULL);
		foreach ($game['snapshot']['items'] as $item)
		{
			$visible = $engine->present($run); verify_english( ! isset($visible['snapshot_json'], $visible['state_json']) && ! isset($visible['step']['answer']), 'No answer payload');
			if (in_array($item['kind'], array('read', 'mission'), TRUE)) { $run = array_replace($run, $engine->advance($run, 'next', 'repeat', time())); }
			else
			{
				try { $engine->advance($run, 'next', '', time()); throw new RuntimeException('Skipped question'); } catch (InvalidArgumentException $expected) { }
				if ($item['kind'] === 'sentence') { verify_english( ! isset($visible['step']['english']), 'Sentence order not leaked'); $answer = $correct ? mb_strtolower(rtrim($item['english'], '.!?')) : 'Private name QA must not persist'; }
				else { $key = array_search($item['answer'], $item['options'], TRUE); $answer = $correct ? $key : array_values(array_diff(array_keys($item['options']), array($key)))[0]; }
				$run = array_replace($run, $engine->advance($run, 'answer', $answer, time()));
				verify_english(strpos($run['state_json'], 'Private name') === FALSE, 'Free text not persisted');
				if ($pack['kind'] === 'lesson')
				{
					verify_english($engine->present($run)['feedback']['correct'] === $correct, 'Immediate practice feedback');
					try { $engine->advance($run, 'answer', $answer, time()); throw new RuntimeException('Duplicate answer'); } catch (InvalidArgumentException $expected) { }
					$run = array_replace($run, $engine->advance($run, 'next', '', time()));
				}
			}
		}
		verify_english($run['status'] === 'completed', 'All steps complete');
		if ($pack['kind'] !== 'lesson') { verify_english($run['passed'] === ($correct ? 1 : 0), 'Server threshold'); }
		verify_english(count($engine->present($run)['review']) === count($pack['items']), 'Full review');
	}
	if ($pack['kind'] === 'lesson') { $lessons++; } else { $graded += count($pack['items']); }
}
verify_english($lessons === 60 && $graded === 214, 'Complete source stage');
// Exact 75% boundary, not only perfect/zero-score cases.
$boundary_pack = array_values(array_filter($catalog['packs'], static function ($pack) {
	return $pack['kind'] !== 'lesson' && count($pack['items']) === 12; }))[0];
foreach (array(8 => 0, 9 => 1) as $correct_count => $passed)
{
	$game = $engine->create($boundary_pack, array('pass_score' => '75', 'translation' => '0', 'audio' => '0'));
	$run = array('snapshot_json' => json_encode($game['snapshot']), 'state_json' => json_encode($game['state']), 'status' => 'running', 'revision' => 1, 'correct_count' => 0);
	foreach ($game['snapshot']['items'] as $index => $item)
	{
		$key = array_search($item['answer'], $item['options'], TRUE); $answer = $index < $correct_count ? $key : array_values(array_diff(array_keys($item['options']), array($key)))[0];
		$run = array_replace($run, $engine->advance($run, 'answer', $answer, time()));
	}
	verify_english($run['passed'] === $passed, '75 percent boundary');
}
echo "PASS English: 60 lessons/600 steps, 12 checkpoints/2 reviews/final (214 assessment items), correct/incorrect runs, server scoring, no skip/duplicate/free-text persistence and private active payloads.\n";
