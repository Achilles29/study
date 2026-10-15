<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Matching_state
{
	public function begin($cards, $size)
	{
		if ( ! is_array($cards) || count($cards) < 2 || count($cards) > 100 || ! in_array($size, array(4, 6), TRUE))
		{
			return NULL;
		}
		$order = range(0, count($cards) - 1);
		for ($index = count($order) - 1; $index > 0; $index--)
		{
			$swap = random_int(0, $index);
			$temp = $order[$index];
			$order[$index] = $order[$swap];
			$order[$swap] = $temp;
		}
		$rounds = array();
		while ($order)
		{
			$take = count($order) === $size + 1 ? $size - 1 : min($size, count($order));
			$rounds[] = array_splice($order, 0, $take);
		}
		return array('cards' => array_values($cards), 'round_size' => $size, 'rounds' => $rounds);
	}

	public function initial()
	{
		return array('round_index' => 0, 'matched' => array(), 'move_count' => 0, 'mistake_count' => 0, 'complete' => FALSE);
	}

	public function choose($snapshot, $state, $left, $right)
	{
		if ( ! is_array($snapshot) || ! isset($snapshot['cards'], $snapshot['rounds']) || ! is_array($snapshot['cards']) || ! is_array($snapshot['rounds']) || count($snapshot['cards']) < 2 || count($snapshot['cards']) > 100 || ! is_array($state) || ! isset($state['matched'], $state['round_index'], $state['move_count'], $state['mistake_count'], $state['complete']) || ! is_array($state['matched']) || ! is_int($state['round_index']) || ! is_int($state['move_count']) || ! is_int($state['mistake_count']) || ! is_bool($state['complete']) || $state['move_count'] !== count($state['matched']) + $state['mistake_count'] || $state['mistake_count'] < 0)
		{
			return NULL;
		}
		$all = array();
		if ( ! isset($snapshot['round_size']) || ! in_array($snapshot['round_size'], array(4, 6), TRUE) || array_values($snapshot['rounds']) !== $snapshot['rounds'] || $state['round_index'] < 0)
		{
			return NULL;
		}
		foreach ($snapshot['rounds'] as $round_index => $round)
		{
			if ( ! is_array($round) || count($round) < 2 || count($round) > $snapshot['round_size'])
			{
				return NULL;
			}
			foreach ($round as $card_index)
			{
				if ( ! is_int($card_index))
				{
					return NULL;
				}
				$was_matched = in_array($card_index, $state['matched'], TRUE);
				if (($round_index < $state['round_index'] && ! $was_matched) || ($round_index > $state['round_index'] && $was_matched))
				{
					return NULL;
				}
			}
			$all = array_merge($all, $round);
		}
		sort($all);
		if ($all !== range(0, count($snapshot['cards']) - 1) || count(array_unique($state['matched'])) !== count($state['matched']))
		{
			return NULL;
		}
		foreach ($state['matched'] as $matched)
		{
			if ( ! is_int($matched) || ! in_array($matched, $all, TRUE))
			{
				return NULL;
			}
		}
		if ( ! is_int($left) || ! is_int($right) || $state['complete'] || $state['move_count'] >= 10000 || ! isset($snapshot['rounds'][$state['round_index']]))
		{
			return NULL;
		}
		$round = $snapshot['rounds'][$state['round_index']];
		if ( ! in_array($left, $round, TRUE) || ! in_array($right, $round, TRUE) || in_array($left, $state['matched'], TRUE) || in_array($right, $state['matched'], TRUE))
		{
			return NULL;
		}
		$state['move_count']++;
		$outcome = $left === $right ? 'matched' : 'mismatch';
		if ($outcome === 'matched')
		{
			$state['matched'][] = $left;
			if (count(array_intersect($round, $state['matched'])) === count($round))
			{
				$state['round_index']++;
				$state['complete'] = $state['round_index'] === count($snapshot['rounds']);
			}
		}
		else
		{
			$state['mistake_count']++;
		}
		return array('state' => $state, 'outcome' => $outcome);
	}
}
