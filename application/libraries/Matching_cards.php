<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Matching_cards
{
	private function key($value)
	{
		return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)), 'UTF-8');
	}

	public function eligible($cards)
	{
		$front_counts = array();
		$back_counts = array();
		$prepared = array();
		foreach (array_slice($cards, 0, 100) as $card)
		{
			$front = $this->key($card['front_text']);
			$back = $this->key($card['back_text']);
			$front_counts[$front] = isset($front_counts[$front]) ? $front_counts[$front] + 1 : 1;
			$back_counts[$back] = isset($back_counts[$back]) ? $back_counts[$back] + 1 : 1;
			$prepared[] = array('front' => $front, 'back' => $back, 'card' => $card);
		}
		$result = array();
		foreach ($prepared as $item)
		{
			$card = $item['card'];
			if ($item['front'] !== '' && $item['back'] !== '' && $front_counts[$item['front']] === 1 && $back_counts[$item['back']] === 1 && mb_strlen($card['front_text'], 'UTF-8') <= 160 && mb_strlen($card['back_text'], 'UTF-8') <= 160)
			{
				$result[] = array('front' => $card['front_text'], 'back' => $card['back_text']);
			}
		}
		return $result;
	}
}
