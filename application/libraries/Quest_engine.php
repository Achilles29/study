<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Quest_engine
{
	public function create($pack, $settings)
	{
		$items = $pack['items']; $graded = 0;
		foreach ($items as &$item)
		{
			shuffle($item['choices']); $item['choices'] = array_combine(array_slice(array('A', 'B', 'C', 'D'), 0, count($item['choices'])), $item['choices']);
			if ($item['kind'] === 'sentence') { $words = preg_split('/\s+/u', $item['answer']); shuffle($words); $item['words'] = implode(' / ', $words); }
			if ($item['kind'] !== 'story') { $graded++; }
		}
		unset($item);
		return array('snapshot' => array('items' => $items, 'translation' => $settings['translation'], 'audio' => $settings['audio']), 'state' => array('position' => 0, 'answers' => array(), 'feedback' => FALSE), 'count' => $graded);
	}

	public function advance($run, $action, $value, $now)
	{
		$snapshot = json_decode($run['snapshot_json'], TRUE, 512, JSON_THROW_ON_ERROR); $state = json_decode($run['state_json'], TRUE, 512, JSON_THROW_ON_ERROR);
		$updates = array('updated_at' => gmdate('Y-m-d H:i:s', $now), 'revision' => (int) $run['revision'] + 1);
		if ($action === 'stop') { return $updates + array('status' => 'abandoned', 'finished_at' => $updates['updated_at']); }
		$item = $snapshot['items'][$state['position']];
		if ($action === 'next' && $state['feedback']) { $state['position']++; $state['feedback'] = FALSE; }
		elseif ($action === 'answer' && ! $state['feedback'])
		{
			if ( ! is_string($value) || strlen($value) > 400 || ! mb_check_encoding($value, 'UTF-8') || trim($value) === '' || preg_match('/[\x00-\x1f\x7f]/', $value)) { throw new InvalidArgumentException('Invalid answer.'); }
			if ($item['kind'] === 'sentence')
			{
				$normalize = static function ($text) {
					return preg_replace('/\s+/u', ' ', mb_strtolower(trim(preg_replace('/[^\pL\pN\s]/u', '', $text)))); };
				$correct = $normalize($value) === $normalize($item['answer']); $value = $correct ? 'sesuai' : 'ulang'; $path = '';
			}
			else
			{
				if ( ! isset($item['choices'][$value])) { throw new InvalidArgumentException('Invalid choice.'); }
				$correct = $item['kind'] === 'story' ? NULL : $item['choices'][$value]['correct']; $path = $item['choices'][$value]['path'];
			}
			$state['answers'][] = array('value' => $value, 'correct' => $correct, 'path' => $path); $state['feedback'] = TRUE;
			$updates['correct_count'] = (int) $run['correct_count'] + ($correct === TRUE ? 1 : 0);
		}
		else { throw new InvalidArgumentException('Review the current scene first.'); }
		$updates['state_json'] = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		if ($state['position'] === count($snapshot['items'])) { $updates['status'] = 'completed'; $updates['finished_at'] = $updates['updated_at']; }
		return $updates;
	}

	private function feedback($item, $answer)
	{
		$solution = $item['answer'] ?? '';
		if ($item['kind'] !== 'sentence' && $item['kind'] !== 'story') { foreach ($item['choices'] as $choice) { if ($choice['correct']) { $solution = $choice['text']; } } }
		$choice = $item['choices'][$answer['value']] ?? NULL;
		return array('correct' => $answer['correct'], 'message' => $choice['feedback'] ?? ($answer['correct'] ? 'Susunan kalimat sesuai.' : 'Pelajari susunan contoh, lalu lanjutkan.'), 'solution' => $solution, 'selected' => $choice['text'] ?? '', 'path' => $answer['path'], 'word' => $item['word'], 'meaning' => $item['meaning']);
	}

	public function present($run)
	{
		$snapshot = json_decode($run['snapshot_json'], TRUE, 512, JSON_THROW_ON_ERROR); $state = json_decode($run['state_json'], TRUE, 512, JSON_THROW_ON_ERROR);
		unset($run['snapshot_json'], $run['state_json'], $run['start_key']);
		$run['position'] = $state['position']; $run['steps_count'] = count($snapshot['items']); $run['translation'] = $snapshot['translation']; $run['audio'] = $snapshot['audio']; $run['feedback'] = NULL; $run['review'] = array();
		if ($run['status'] === 'running')
		{
			$item = $snapshot['items'][$state['position']];
			if ($state['feedback']) { $run['feedback'] = $this->feedback($item, $state['answers'][$state['position']]); }
			unset($item['answer'], $item['word'], $item['meaning']); $item['choices'] = array_map(static function ($choice) {
				return $choice['text']; }, $item['choices']);
			if ($item['kind'] === 'sentence') { $item['choices'] = array(); }
			$run['step'] = $item;
		}
		else { foreach ($state['answers'] as $i => $answer) { $run['review'][] = $this->feedback($snapshot['items'][$i], $answer) + array('place' => $snapshot['items'][$i]['place'], 'prompt' => $snapshot['items'][$i]['prompt']); } }
		return $run;
	}
}
