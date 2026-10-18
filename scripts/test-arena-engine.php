<?php
define('BASEPATH', dirname(__DIR__).'/system/');
require dirname(__DIR__).'/application/libraries/Arena_engine.php';
function ensure($condition, $message)
{
	if ( ! $condition) { throw new RuntimeException($message); }
}
$engine = new Arena_engine();
$catalog = json_decode(file_get_contents(dirname(__DIR__).'/application/config/arena_catalog.json'), TRUE, 512, JSON_THROW_ON_ERROR);
ensure(count($catalog['packs']) === 37, '37 packs expected');
foreach ($catalog['packs'] as $pack)
{
	for ($repeat = 0; $repeat < 10; $repeat++)
	{
		$game = $engine->create($pack, array('pairs' => '6', 'words' => '10'));
		$run = array('game_type' => $pack['type'], 'snapshot_json' => json_encode($game['snapshot']), 'state_json' => json_encode($game['state']), 'status' => 'running', 'revision' => 1, 'deadline_at' => NULL, 'correct_count' => 0, 'moves' => 0);
		$visible = $engine->present($run); ensure( ! isset($visible['snapshot_json']), 'Snapshot hidden');
		if ($pack['type'] === 'memory')
		{
			ensure(count(array_filter(array_column($visible['board'], 'text'))) === 0, 'Memory initially hidden');
			$definitions = array_column($game['snapshot']['items'], 'definition'); ensure(count($definitions) === count(array_unique($definitions)), 'Memory unambiguous');
			$pairs = array(); foreach ($game['snapshot']['board'] as $i => $card) { $pairs[$card['pair']][] = $i; }
			foreach ($pairs as $pair) { foreach ($pair as $i) { $run = array_replace($run, $engine->advance($run, 'flip', (string) $i, time())); } }
		}
		else
		{
			ensure( ! isset($visible['answer']), 'Key hidden');
			foreach ($game['snapshot']['items'] as $item)
			{
				if ($pack['type'] === 'math')
				{
					preg_match('/^(\d+) (.) (\d+) = \?$/u', $item['definition'], $m);
					$a = (int) $m[1]; $b = (int) $m[3];
					$expected = match($m[2]) { '+' => $a + $b, '−' => $a - $b, '×' => $a * $b, '÷' => $a / $b };
					ensure((int) $expected === (int) $item['term'] && $expected >= 0 && $expected == (int) $expected, 'Arithmetic correct');
				}
				$run = array_replace($run, $engine->advance($run, 'answer', $item['term'], time()));
			}
		}
		ensure($run['status'] === 'completed' && $run['correct_count'] === $game['count'], 'Completion calculated by server');
		$run['status'] = 'running'; $run['deadline_at'] = gmdate('Y-m-d H:i:s', time() - 1); $before = $run['correct_count'];
		$expired = array_replace($run, $engine->advance($run, 'answer', '999999', time()));
		ensure($expired['status'] === 'completed' && $expired['correct_count'] === $before, 'Expiry never credits answer');
	}
}
echo "PASS Arena engine: 37 packs × 10 randomized rounds; answer validation, arithmetic, hidden keys, unambiguous cards, completion and expiry.\n";
$expedition = json_decode(file_get_contents(dirname(__DIR__).'/application/config/arena_expedition.json'), TRUE, 512, JSON_THROW_ON_ERROR);
ensure(count($expedition['packs']) === 12, '12 expedition levels');
foreach ($expedition['packs'] as $pack)
{
	foreach (array(0, 3, 4, 5) as $right_answers)
	{
		$game = $engine->create($pack, array('expedition_pass' => '80'));
		$run = array('game_type' => 'expedition', 'snapshot_json' => json_encode($game['snapshot']), 'state_json' => json_encode($game['state']), 'status' => 'running', 'revision' => 1, 'deadline_at' => NULL, 'correct_count' => 0, 'moves' => 0);
		$visible = $engine->present($run); ensure(count($visible['options']) === 4 && ! isset($visible['term']), 'Choice keys hidden');
		try { $engine->advance($run, 'answer', 'E', time()); throw new RuntimeException('Invalid option accepted'); }
		catch (InvalidArgumentException $expected) { }
		foreach ($game['snapshot']['items'] as $i => $item)
		{
			$wrong = array_values(array_diff(array_keys($item['options']), array($item['term'])))[0];
			$run = array_replace($run, $engine->advance($run, 'answer', $i < $right_answers ? $item['term'] : $wrong, time()));
		}
		ensure($run['status'] === 'completed' && $run['passed'] === ($right_answers >= 4 ? 1 : 0), 'Snapshot threshold applied');
		ensure(count($engine->present($run)['review']) === 5, 'Full explanation after final');
	}
}
echo "PASS Math Expedition: 12 levels, option validation/shuffling, no active keys, 0/60/80/100% results, snapshot passing threshold and final explanation.\n";
$flashcards = json_decode(file_get_contents(dirname(__DIR__).'/application/config/arena_flashcards.json'), TRUE, 512, JSON_THROW_ON_ERROR);
foreach ($flashcards['packs'] as $pack)
{
	foreach (array(5, 10, 15) as $limit)
	{
		$game = $engine->create($pack, array('cards' => $limit));
		ensure($game['count'] === min($limit, count($pack['items'])), 'Card limit enforced');
		$run = array('game_type' => 'flashcard', 'snapshot_json' => json_encode($game['snapshot'], JSON_THROW_ON_ERROR), 'state_json' => json_encode($game['state']), 'status' => 'running', 'revision' => 1, 'deadline_at' => NULL, 'correct_count' => 0, 'moves' => 0);
		foreach ($game['snapshot']['items'] as $i => $card)
		{
			ensure($engine->present($run)['card_back'] === NULL, 'Card back hidden before reveal');
			try { $engine->advance($run, 'rate', 'known', time()); throw new RuntimeException('Rating unrevealed card accepted'); }
			catch (InvalidArgumentException $expected) { }
			$run = array_replace($run, $engine->advance($run, 'reveal', '', time()));
			ensure($engine->present($run)['card_back'] === $card['definition'], 'Back matches stable card');
			try { $engine->advance($run, 'rate', '100', time()); throw new RuntimeException('Arbitrary rating accepted'); }
			catch (InvalidArgumentException $expected) { }
			$run = array_replace($run, $engine->advance($run, 'rate', $i === 0 ? 'repeat' : 'known', time()));
		}
		ensure($run['status'] === 'completed' && $run['correct_count'] === $game['count'] - 1, 'Card self-assessment saved');
		ensure(count($engine->present($run)['review']) === $game['count'], 'Card review complete');
	}
}
echo "PASS Flashcard engine: 14 decks × 5/10/15-card limits, Unicode snapshots, reveal before rating, rating allowlist, completion and self-assessment review.\n";
