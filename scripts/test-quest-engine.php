<?php
define('BASEPATH', dirname(__DIR__).'/system/');
require dirname(__DIR__).'/application/libraries/Quest_engine.php';
function verify_quest($condition, $message)
{
	if ( ! $condition) { throw new RuntimeException($message); }
}
$catalog = json_decode(file_get_contents(dirname(__DIR__).'/application/config/arena_quest.json'), TRUE, 512, JSON_THROW_ON_ERROR); $engine = new Quest_engine(); $count = 0;
foreach ($catalog['packs'] as $pack)
{
	if ($pack['type'] !== 'quest') { continue; }
	foreach (array(TRUE, FALSE) as $right)
	{
		$game = $engine->create($pack, array('translation' => '1', 'audio' => '0'));
		$run = array('game_type' => 'quest', 'snapshot_json' => json_encode($game['snapshot'], JSON_THROW_ON_ERROR), 'state_json' => json_encode($game['state']), 'status' => 'running', 'revision' => 1, 'correct_count' => 0, 'question_count' => $game['count']);
		foreach ($game['snapshot']['items'] as $item)
		{
			$visible = $engine->present($run); verify_quest( ! isset($visible['step']['answer']) && ! isset($visible['snapshot_json']), 'Private answer fields stripped');
			foreach ($visible['step']['choices'] as $choice) { verify_quest(is_string($choice), 'No choice correctness/path/feedback before answer'); }
			try { $engine->advance($run, 'next', '', time()); throw new RuntimeException('Skip accepted'); } catch (InvalidArgumentException $expected) { }
			if ($item['kind'] === 'sentence') { $answer = $right ? $item['answer'] : 'PRIVATE INPUT MUST NOT PERSIST'; }
			else { $answer = NULL; foreach ($item['choices'] as $key => $choice) { if ($item['kind'] === 'story' || $choice['correct'] === $right) { $answer = $key; break; } } }
			$run = array_replace($run, $engine->advance($run, 'answer', $answer, time()));
			$feedback = $engine->present($run)['feedback']; verify_quest($feedback['correct'] === ($item['kind'] === 'story' ? NULL : $right), 'Branch not graded, language server graded');
			verify_quest(strpos($run['state_json'], 'PRIVATE INPUT') === FALSE, 'No free text retained');
			try { $engine->advance($run, 'answer', $answer, time()); throw new RuntimeException('Repeat accepted'); } catch (InvalidArgumentException $expected) { }
			$run = array_replace($run, $engine->advance($run, 'next', '', time()));
		}
		verify_quest($run['status'] === 'completed' && $run['correct_count'] === ($right ? 13 : 0), '13 graded challenges, two ungraded story decisions');
		verify_quest(count($engine->present($run)['review']) === 15, 'Story diary preserved');
	}
	$count++;
}
verify_quest($count === 15, 'All active source episodes');
echo "PASS Quest 15 episodes/225 scenes, correct and incorrect paths, ungraded story choices, no skip/duplicate, hidden keys, private typed input and immutable diary.\n";
