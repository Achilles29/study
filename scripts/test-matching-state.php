<?php
define('BASEPATH', __DIR__);
require __DIR__.'/../application/libraries/Matching_state.php';
$engine = new Matching_state();
foreach (array(4, 6) as $size)
{
	foreach (range(2, 100) as $total)
	{
		$snapshot = $engine->begin(array_fill(0, $total, array('front' => 'A', 'back' => 'B')), $size);
		$state = $engine->initial();
		foreach ($snapshot['rounds'] as $round)
		{
			if (count($round) < 2 || count($round) > $size)
			{
				throw new RuntimeException('Invalid batch size.');
			}
			$wrong = $engine->choose($snapshot, $state, $round[0], $round[1]);
			if ($wrong['outcome'] !== 'mismatch' || count($wrong['state']['matched']) !== count($state['matched']))
			{
				throw new RuntimeException('Incorrect choice was credited.');
			}
			$state = $wrong['state'];
			foreach ($round as $id)
			{
				$result = $engine->choose($snapshot, $state, $id, $id);
				if ($result === NULL || $result['outcome'] !== 'matched')
				{
					throw new RuntimeException('Valid pair rejected.');
				}
				$state = $result['state'];
				if ($engine->choose($snapshot, $state, $id, $id) !== NULL)
				{
					throw new RuntimeException('Duplicate match was credited.');
				}
			}
		}
		if ( ! $state['complete'] || count($state['matched']) !== $total || $state['move_count'] !== $total + count($snapshot['rounds']))
		{
			throw new RuntimeException('Completion totals do not reconcile.');
		}
	}
}
$snapshot = $engine->begin(array_fill(0, 8, array()), 6);
$state = $engine->initial();
$corrupt_states = array();
$corrupt = $state;
$corrupt['round_index'] = 1;
$corrupt_states[] = $corrupt;
$corrupt = $state;
$corrupt['matched'] = array($snapshot['rounds'][1][0]);
$corrupt['move_count'] = 1;
$corrupt_states[] = $corrupt;
foreach ($corrupt_states as $corrupt)
{
	$id = $snapshot['rounds'][$corrupt['round_index']][0];
	if ($engine->choose($snapshot, $corrupt, $id, $id) !== NULL)
	{
		throw new RuntimeException('Inconsistent round history accepted.');
	}
}
if ($engine->choose($snapshot, $state, $snapshot['rounds'][1][0], $snapshot['rounds'][1][0]) !== NULL || $engine->choose($snapshot, $state, '0', 0) !== NULL)
{
	throw new RuntimeException('Foreign round or non-integer accepted.');
}
$state['move_count'] = 10000;
$state['mistake_count'] = 10000;
if ($engine->choose($snapshot, $state, 0, 0) !== NULL || $engine->begin(array(), 6) !== NULL)
{
	throw new RuntimeException('Cap or empty deck accepted.');
}
fwrite(STDOUT, "PASS server matching state: 2–100 cards, 4/6-sized rounds, mismatch/match totals, no repeats/foreign rounds, and move cap.\n");
