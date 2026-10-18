<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Battle_rules
{
	public function text($value, $max, $minimum = 1)
	{
		if ( ! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0b-\x1f\x7f]/', $value)) { throw new InvalidArgumentException('Invalid text.'); }
		$value = trim($value); if (mb_strlen($value) < $minimum || mb_strlen($value) > $max) { throw new InvalidArgumentException('Invalid text length.'); } return $value;
	}

	public function date($value)
	{
		if ( ! is_string($value)) { throw new InvalidArgumentException('Invalid date.'); }
		$date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone('Asia/Jakarta'));
		if ( ! $date || $date->format('Y-m-d\TH:i') !== $value) { throw new InvalidArgumentException('Invalid WIB date.'); }
		return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
	}

	public function question($input)
	{
		$options = array(); foreach (array('A', 'B', 'C', 'D') as $key) { $options[$key] = $this->text($input['option_'.$key] ?? NULL, 300); }
		if (count(array_unique(array_map('mb_strtolower', $options))) !== 4 || ! is_string($input['answer'] ?? NULL) || ! isset($options[$input['answer']])) { throw new InvalidArgumentException('Distinct options and valid answer required.'); }
		return array('prompt' => $this->text($input['prompt'] ?? NULL, 1200), 'options' => $options, 'answer' => $input['answer'], 'explanation' => $this->text($input['explanation'] ?? NULL, 1500), 'source' => $this->text($input['source'] ?? NULL, 300), 'rights' => $this->text($input['rights'] ?? NULL, 300));
	}

	public function details($input)
	{
		$title = $this->text($input['title'] ?? NULL, 160, 3);
		$start = $this->date($input['starts_at'] ?? NULL); $end = $this->date($input['ends_at'] ?? NULL);
		if ($end <= $start || strtotime($end.' UTC') <= time() || strtotime($end.' UTC') > time() + 30 * 86400 || strtotime($start.' UTC') < time() - 3600) { throw new InvalidArgumentException('Invalid schedule.'); }
		return array('title' => $title, 'starts_at' => $start, 'ends_at' => $end);
	}

	public function advance($room, $entry, $action, $value, $now)
	{
		$state = json_decode($entry['state_json'], TRUE, 512, JSON_THROW_ON_ERROR); $questions = json_decode($room['snapshot_json'], TRUE, 512, JSON_THROW_ON_ERROR);
		$updates = array('updated_at' => gmdate('Y-m-d H:i:s', $now), 'revision' => (int) $entry['revision'] + 1);
		if ($now >= strtotime($room['ends_at'].' UTC')) { return $updates + array('status' => 'completed', 'finished_at' => $room['ends_at']); }
		if (($room['play_mode'] ?? 'async') === 'live')
		{
			if ( ! $room['live_started_at'] || $now < strtotime($room['live_started_at'].' UTC')) { throw new InvalidArgumentException('Live battle is waiting.'); }
			$position = (int) floor(($now - strtotime($room['live_started_at'].' UTC')) / (int) $room['question_seconds']);
			if ($action !== 'answer' || ! is_string($value) || ! preg_match('/\A([0-9]{1,2}):([A-D])\z/', $value, $match) || (int) $match[1] !== $position || (int) $state['position'] > $position || ! isset($questions[$position])) { throw new InvalidArgumentException('Live question expired or already answered.'); }
			while (count($state['answers']) < $position) { $state['answers'][] = NULL; } $state['position'] = $position; $value = $match[2];
		}
		if ($now < strtotime($room['starts_at'].' UTC')) { throw new InvalidArgumentException('Battle has not started.'); }
		if ($action !== 'answer' || ! is_string($value) || ! isset($questions[$state['position']]['options'][$value])) { throw new InvalidArgumentException('Invalid answer.'); }
		$correct = $questions[$state['position']]['answer'] === $value;
		$state['answers'][] = $value; $state['position']++;
		$updates['correct_count'] = (int) $entry['correct_count'] + ($correct ? 1 : 0); $updates['state_json'] = json_encode($state); $updates['status'] = 'running';
		if ($entry['started_at'] === NULL) { $updates['started_at'] = $updates['updated_at']; }
		if ($state['position'] === count($questions)) { $updates['status'] = 'completed'; $updates['finished_at'] = $updates['updated_at']; }
		return $updates;
	}
}
