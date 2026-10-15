<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Practice_schedule
{
	private function local_time($value)
	{
		if ( ! is_string($value) || ! preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}\z/', $value))
		{
			return NULL;
		}
		$date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone('Asia/Jakarta'));
		return $date && $date->format('Y-m-d\TH:i') === $value ? $date->getTimestamp() : NULL;
	}

	public function parse($from, $until, $now = NULL)
	{
		$now = $now === NULL ? time() : $now;
		$start = $from === '' ? $now : $this->local_time($from);
		$end = $this->local_time($until);
		if ($start === NULL || $end === NULL || $start < $now - 300 || $end <= max($start, $now) || $end > $now + 90 * 86400)
		{
			return NULL;
		}
		return array('available_from' => gmdate('Y-m-d H:i:s', max($start, $now)), 'available_until' => gmdate('Y-m-d H:i:s', $end));
	}
}
