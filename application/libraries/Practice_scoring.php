<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Practice_scoring
{
	public function percentage($correct, $total)
	{
		if ( ! is_int($correct) || ! is_int($total) || $total < 1 || $total > 50 || $correct < 0 || $correct > $total)
		{
			throw new InvalidArgumentException('Jumlah jawaban tidak valid.');
		}
		$hundredths = intdiv($correct * 10000 + intdiv($total, 2), $total);
		return intdiv($hundredths, 100).'.'.str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT);
	}
}
