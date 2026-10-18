<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class English_engine
{
	public function create($pack, $settings)
	{
		$items = $pack['items'];
		foreach ($items as &$item)
		{
			if ($item['kind'] === 'choice') { shuffle($item['options']); $item['options'] = array_combine(array('A', 'B', 'C', 'D'), $item['options']); }
			if ($item['kind'] === 'sentence') { $words = preg_split('/\s+/u', $item['english']); shuffle($words); $item['words'] = implode(' / ', $words); }
		}
		unset($item);
		return array('snapshot' => array('items' => $items, 'kind' => $pack['kind'], 'chapter' => $pack['chapter'], 'passing_score' => (int) $settings['pass_score'], 'translation' => $settings['translation'], 'audio' => $settings['audio']), 'state' => array('position' => 0, 'answers' => array(), 'feedback' => FALSE), 'count' => count($items));
	}

	private function normalize($value)
	{
		$value = mb_strtolower(str_replace(array('’', '‘'), "'", trim($value)), 'UTF-8');
		return preg_replace('/\s+/u', ' ', preg_replace('/[.!?,;:]+$/u', '', $value));
	}

	public function advance($run, $action, $value, $now)
	{
		$snapshot = json_decode($run['snapshot_json'], TRUE, 512, JSON_THROW_ON_ERROR); $state = json_decode($run['state_json'], TRUE, 512, JSON_THROW_ON_ERROR);
		$updates = array('updated_at' => gmdate('Y-m-d H:i:s', $now), 'revision' => (int) $run['revision'] + 1);
		if ($action === 'stop') { return $updates + array('status' => 'abandoned', 'finished_at' => $updates['updated_at']); }
		$item = $snapshot['items'][$state['position']]; $lesson = $snapshot['kind'] === 'lesson';
		if ($action === 'next' && $lesson && $state['feedback']) { $state['position']++; $state['feedback'] = FALSE; }
		elseif ($action === 'next' && $lesson && in_array($item['kind'], array('read', 'mission'), TRUE))
		{
			if ($item['kind'] === 'mission' && ! in_array($value, array('practiced', 'repeat'), TRUE)) { throw new InvalidArgumentException('Choose reflection.'); }
			$state['answers'][] = array('value' => $item['kind'] === 'read' ? 'read' : $value, 'correct' => NULL); $state['position']++;
		}
		elseif ($action === 'answer' && ! $state['feedback'] && in_array($item['kind'], array('choice', 'sentence'), TRUE))
		{
			if ( ! is_string($value) || strlen($value) > 400 || ! mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $value) || trim($value) === '') { throw new InvalidArgumentException('Invalid answer.'); }
			if ($item['kind'] === 'choice')
			{
				if ( ! isset($item['options'][$value])) { throw new InvalidArgumentException('Invalid choice.'); }
				$correct = $item['options'][$value] === $item['answer'];
			}
			else { $correct = $this->normalize($value) === $this->normalize($item['english']); $value = $correct ? 'sesuai' : 'pelajari_lagi'; }
			// Never retain free-text child answers or microphone recordings.
			$state['answers'][] = array('value' => $value, 'correct' => $correct);
			$updates['correct_count'] = (int) $run['correct_count'] + ($correct ? 1 : 0);
			if ($lesson) { $state['feedback'] = TRUE; } else { $state['position']++; }
		}
		else { throw new InvalidArgumentException('Action not available.'); }
		$updates['state_json'] = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		if ($state['position'] === count($snapshot['items']))
		{
			$updates['status'] = 'completed'; $updates['finished_at'] = $updates['updated_at'];
			if ( ! $lesson) { $updates['passed'] = ($updates['correct_count'] ?? $run['correct_count']) * 100 >= count($snapshot['items']) * $snapshot['passing_score'] ? 1 : 0; }
		}
		return $updates;
	}

	public function present($run)
	{
		$snapshot = json_decode($run['snapshot_json'], TRUE, 512, JSON_THROW_ON_ERROR); $state = json_decode($run['state_json'], TRUE, 512, JSON_THROW_ON_ERROR);
		unset($run['snapshot_json'], $run['state_json'], $run['start_key']);
		$run['kind'] = $snapshot['kind']; $run['position'] = $state['position']; $run['passing_score'] = $snapshot['passing_score']; $run['translation'] = $snapshot['translation']; $run['audio'] = $snapshot['audio']; $run['feedback'] = NULL; $run['review'] = array();
		$graded = array_filter($state['answers'], static function ($answer) {
			return $answer['correct'] !== NULL; }); $run['graded_count'] = count($graded);
		if ($run['status'] === 'running')
		{
			$item = $snapshot['items'][$state['position']];
			if ($state['feedback']) { $run['feedback'] = array('correct' => $state['answers'][$state['position']]['correct'], 'answer' => $item['answer'] ?? $item['english'], 'explanation' => $item['explanation'] ?? $item['translation']); }
			unset($item['answer']);
			if ($item['kind'] === 'sentence') { unset($item['english']); }
			$run['step'] = $item;
		}
		else
		{
			foreach ($snapshot['items'] as $i => $item)
			{
				$answer = $state['answers'][$i] ?? NULL;
				$run['review'][] = array('prompt' => $item['prompt'] ?? $item['english'], 'answer' => $item['answer'] ?? $item['translation'], 'correct' => $answer['correct'] ?? NULL, 'visited' => $answer !== NULL, 'reflection' => $answer['value'] ?? '', 'explanation' => $item['explanation'] ?? $item['note'] ?? '');
			}
		}
		return $run;
	}
}
