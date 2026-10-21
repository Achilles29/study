<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Rules are copied at start, never taken from an answer submission. */
class Practice_sheet
{
	public static function defaults()
	{
		return array('view_mode'=>'ledger', 'focus_guard'=>'0', 'focus_limit'=>'3');
	}

	public static function validate($input)
	{
		$v = array_intersect_key($input, self::defaults()) + self::defaults();
		if (!is_string($v['view_mode']) || !in_array($v['view_mode'], array('single','ledger'), TRUE)
			|| !is_string($v['focus_guard']) || !in_array($v['focus_guard'], array('0','1'), TRUE)
			|| !is_string($v['focus_limit']) || !preg_match('/\A(?:[1-9]|10)\z/', $v['focus_limit'])) {
			return NULL;
		}
		return $v;
	}

	public static function read($json)
	{
		$v = is_string($json) ? json_decode($json, TRUE) : NULL;
		// Legacy sessions stay ledger / guard off; no retroactive penalty.
		return is_array($v) ? (self::validate($v) ?? array('view_mode'=>'ledger','focus_guard'=>'0','focus_limit'=>'3'))
			: array('view_mode'=>'ledger','focus_guard'=>'0','focus_limit'=>'3');
	}
}
