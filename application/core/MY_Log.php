<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Log extends CI_Log
{
	public function write_log($level, $msg)
	{
		// The CI database driver includes SQL values on failure. Support correspondence is private.
		if (is_string($msg) && preg_match('/\A(?:Query error:|Invalid query:)/', $msg) && stripos($msg, 'st_help_') !== FALSE)
		{
			$msg = 'Help database operation failed; SQL and correspondence omitted.';
		}
		if (is_string($msg) && preg_match('/\A(?:Query error:|Invalid query:)/', $msg) && stripos($msg, 'st_operational_') !== FALSE)
		{
			$msg = 'Operational traffic database operation failed; SQL and client details omitted.';
		}
		return parent::write_log($level, $msg);
	}
}
