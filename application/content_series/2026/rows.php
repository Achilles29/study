<?php
defined('BASEPATH') OR exit('No direct script access allowed');
return static function ($text) {
	$rows = array();
	foreach (preg_split('/\r?\n/', trim($text)) as $line)
	{
		if (trim($line) === '') { continue; }
		$fields = array_map('trim', explode(' | ', $line));
		if (count($fields) !== 8) { throw new InvalidArgumentException('Each chapter needs eight authored fields.'); }
		$rows[] = $fields;
	}
	return $rows;
};
