<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__.'/English_engine.php';
require_once __DIR__.'/Quest_engine.php';

// Pure server-side rules. No client score, clock, keys, or identity is trusted.
class Arena_engine
{
	public function create($pack, $settings)
	{
		if ($pack['type'] === 'english') { return (new English_engine())->create($pack, $settings); }
		if ($pack['type'] === 'quest') { return (new Quest_engine())->create($pack, $settings); }
		$items = $pack['items'] ?? array();
		if ($pack['type'] === 'math')
		{
			for ($i = 0; $i < 10; $i++)
			{
				$operator = $pack['operators'][random_int(0, count($pack['operators']) - 1)];
				$a = random_int(1, $pack['max']); $b = random_int(1, $pack['max']);
				if ($operator === '-') { $a = max($a, $b); $b = random_int(0, $a); }
				if ($operator === '/') { $b = random_int(1, min(12, $pack['max'])); $answer = random_int(1, max(1, (int) floor($pack['max'] / $b))); $a = $b * $answer; }
				elseif ($operator === '+') { $answer = $a + $b; }
				elseif ($operator === '-') { $answer = $a - $b; }
				else { $a = random_int(1, min(12, $pack['max'])); $answer = $a * $b; }
				$symbol = array('+' => '+', '-' => '−', '*' => '×', '/' => '÷')[$operator];
				$items[] = array('term' => (string) $answer, 'definition' => $a.' '.$symbol.' '.$b.' = ?');
			}
		}
		elseif (in_array($pack['type'], array('expedition', 'story'), TRUE))
		{
			foreach ($items as &$item)
			{
				$correct_text = $item['options'][$item['term']]; $options = array_values($item['options']); shuffle($options);
				$item['options'] = array_combine(array('A', 'B', 'C', 'D'), $options);
				$item['term'] = array_search($correct_text, $item['options'], TRUE);
			}
			unset($item);
		}
		else
		{
			shuffle($items);
			if ($pack['type'] === 'memory')
			{
				// Indistinguishable answers (e.g. two cards showing 7) must not be false negatives.
				$seen = array(); $unique = array();
				foreach ($items as $item) { $key = mb_strtolower(trim($item['definition'])); if ( ! isset($seen[$key])) { $unique[] = $item; $seen[$key] = TRUE; } }
				$items = $unique;
			}
			$limit = $pack['type'] === 'flashcard' ? $settings['cards'] : ($pack['type'] === 'memory' ? $settings['pairs'] : $settings['words']);
			$items = array_slice($items, 0, (int) $limit);
		}
		if (count($items) < 2) { throw new RuntimeException('Not enough playable content.'); }
		$board = array();
		foreach ($items as $i => &$item)
		{
			$item['scrambled'] = '';
			if ($pack['type'] === 'word')
			{
				$letters = preg_split('//u', $item['term'], -1, PREG_SPLIT_NO_EMPTY); shuffle($letters);
				if (implode('', $letters) === $item['term']) { $letters = array_merge(array_slice($letters, 1), array_slice($letters, 0, 1)); }
				$item['scrambled'] = implode(' ', $letters);
			}
			$board[] = array('pair' => $i, 'text' => $item['term']); $board[] = array('pair' => $i, 'text' => $item['definition']);
		}
		unset($item); shuffle($board);
		return array('snapshot' => array('items' => $items, 'board' => $board, 'reading' => $pack['reading'] ?? '', 'passing_score' => $pack['type'] === 'expedition' ? (int) $settings['expedition_pass'] : NULL), 'state' => array('position' => 0, 'answers' => array(), 'matched' => array(), 'open' => array()), 'count' => count($items));
	}

	public function advance($run, $action, $value, $now)
	{
		if ($run['game_type'] === 'english') { return (new English_engine())->advance($run, $action, $value, $now); }
		if ($run['game_type'] === 'quest') { return (new Quest_engine())->advance($run, $action, $value, $now); }
		$state = json_decode($run['state_json'], TRUE, 512, JSON_THROW_ON_ERROR);
		$snapshot = json_decode($run['snapshot_json'], TRUE, 512, JSON_THROW_ON_ERROR);
		$updates = array('updated_at' => gmdate('Y-m-d H:i:s', $now), 'revision' => (int) $run['revision'] + 1);
		if ($action === 'stop') { return $updates + array('status' => 'abandoned', 'finished_at' => $updates['updated_at']); }
		if ($run['deadline_at'] !== NULL && $now >= strtotime($run['deadline_at'].' UTC')) { return $updates + array('status' => 'completed', 'finished_at' => $run['deadline_at']); }
		if ($run['game_type'] === 'flashcard')
		{
			if ($action === 'reveal' && empty($state['revealed'])) { $state['revealed'] = TRUE; $done = FALSE; }
			elseif ($action === 'rate' && ! empty($state['revealed']) && in_array($value, array('known', 'repeat'), TRUE))
			{
				$state['answers'][] = array('value' => $value, 'correct' => $value === 'known'); $state['position']++; $state['revealed'] = FALSE;
				$updates['correct_count'] = (int) $run['correct_count'] + ($value === 'known' ? 1 : 0);
				$done = $state['position'] === count($snapshot['items']);
			}
			else { throw new InvalidArgumentException('Reveal before rating a card.'); }
		}
		elseif ($run['game_type'] === 'memory')
		{
			if ($action !== 'flip' || ! is_string($value) || ! preg_match('/\A[0-9]{1,2}\z/', $value)) { throw new InvalidArgumentException('Invalid card.'); }
			$index = (int) $value;
			if (count($state['open']) === 2) { $state['open'] = array(); }
			if ( ! isset($snapshot['board'][$index]) || in_array($index, $state['matched'], TRUE) || in_array($index, $state['open'], TRUE)) { throw new InvalidArgumentException('Card already shown.'); }
			$state['open'][] = $index;
			if (count($state['open']) === 2)
			{
				$updates['moves'] = (int) $run['moves'] + 1;
				if ($snapshot['board'][$state['open'][0]]['pair'] === $snapshot['board'][$state['open'][1]]['pair'])
				{
					$state['matched'] = array_merge($state['matched'], $state['open']);
					$updates['correct_count'] = (int) $run['correct_count'] + 1;
				}
			}
			$done = count($state['matched']) === count($snapshot['board']);
		}
		else
		{
			if ($action !== 'answer' || ! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || strlen($value) > 100 || preg_match('/[\x00-\x1f\x7f]/', $value)) { throw new InvalidArgumentException('Invalid answer.'); }
			$value = mb_strtoupper(trim($value));
			if ($value === '' || ($run['game_type'] === 'math' && ! preg_match('/\A[0-9]{1,8}\z/', $value))) { throw new InvalidArgumentException('Answer required.'); }
			$expected = $snapshot['items'][$state['position']]['term'];
			if (in_array($run['game_type'], array('expedition', 'story'), TRUE) && ! isset($snapshot['items'][$state['position']]['options'][$value])) { throw new InvalidArgumentException('Choose an available option.'); }
			$correct = $run['game_type'] === 'math' ? (int) $expected === (int) $value : $expected === $value;
			$state['answers'][] = array('value' => $value, 'correct' => $correct); $state['position']++;
			$updates['correct_count'] = (int) $run['correct_count'] + ($correct ? 1 : 0);
			$done = $state['position'] === count($snapshot['items']);
		}
		$updates['state_json'] = json_encode($state, JSON_UNESCAPED_UNICODE);
		if ($done)
		{
			$updates['status'] = 'completed'; $updates['finished_at'] = $updates['updated_at'];
			if ($run['game_type'] === 'expedition') { $updates['passed'] = $updates['correct_count'] * 100 >= count($snapshot['items']) * $snapshot['passing_score'] ? 1 : 0; }
		}
		return $updates;
	}

	// Deliberately strip answers/board pair IDs from the active participant payload.
	public function present($run)
	{
		if ($run['game_type'] === 'english') { return (new English_engine())->present($run); }
		if ($run['game_type'] === 'quest') { return (new Quest_engine())->present($run); }
		$snapshot = json_decode($run['snapshot_json'], TRUE, 512, JSON_THROW_ON_ERROR);
		$state = json_decode($run['state_json'], TRUE, 512, JSON_THROW_ON_ERROR);
		unset($run['snapshot_json'], $run['state_json'], $run['start_key']);
		$run['position'] = $state['position']; $run['board'] = array(); $run['review'] = array();
		$run['passing_score'] = $snapshot['passing_score'] ?? NULL;
		$run['reading'] = $snapshot['reading'] ?? '';
		if ($run['game_type'] === 'flashcard' && $run['status'] === 'running')
		{
			$item = $snapshot['items'][$state['position']];
			$run['card_front'] = $item['term']; $run['card_hint'] = $item['hint'] ?? '';
			$run['card_back'] = ! empty($state['revealed']) ? $item['definition'] : NULL;
			return $run;
		}
		if ($run['game_type'] === 'memory')
		{
			foreach ($snapshot['board'] as $i => $card)
			{
				$visible = $run['status'] !== 'running' || in_array($i, $state['open'], TRUE) || in_array($i, $state['matched'], TRUE);
				$run['board'][] = array('index' => $i, 'text' => $visible ? $card['text'] : NULL, 'matched' => in_array($i, $state['matched'], TRUE), 'disabled' => in_array($i, $state['matched'], TRUE) || (count($state['open']) === 1 && in_array($i, $state['open'], TRUE)));
			}
		}
		elseif ($run['status'] === 'running')
		{
			$item = $snapshot['items'][$state['position']];
			$run['prompt'] = $item['definition']; $run['letters'] = $run['game_type'] === 'word' ? $item['scrambled'] : '';
			$run['options'] = $item['options'] ?? array(); $run['hint'] = $item['hint'] ?? ''; $run['hint_deeper'] = $item['hint_deeper'] ?? '';
		}
		else
		{
			foreach ($snapshot['items'] as $i => $item)
			{
				$submitted = $state['answers'][$i]['value'] ?? '';
				$run['review'][] = array('prompt' => $item['definition'], 'answer' => $item['options'][$item['term']] ?? $item['term'], 'submitted' => $submitted === '' ? 'Belum dijawab' : ($item['options'][$submitted] ?? $submitted), 'correct' => $state['answers'][$i]['correct'] ?? FALSE, 'explanation' => $item['explanation'] ?? '', 'tip' => $item['tip'] ?? '');
			}
		}
		return $run;
	}
}
