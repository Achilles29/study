<?php
define('BASEPATH', __DIR__.'/../system/');
require __DIR__.'/../application/libraries/Practice_schedule.php';
$schedule = new Practice_schedule();
$now = strtotime('2026-10-15 00:00:00 UTC');
$valid = $schedule->parse('2026-10-15T08:00', '2026-10-15T09:00', $now);
if ($valid !== array('available_from' => '2026-10-15 01:00:00', 'available_until' => '2026-10-15 02:00:00'))
{
	throw new RuntimeException('WIB schedule must be stored in UTC.');
}
if ($schedule->parse('', '2026-10-15T09:00', $now)['available_from'] !== '2026-10-15 00:00:00')
{
	throw new RuntimeException('Blank start must mean now.');
}
foreach (array(array('2026-02-30T07:00', '2026-10-15T09:00'), array('', '2026-10-15T06:59'), array('2026-10-15T09:00', '2026-10-15T08:00'), array('', '2027-10-15T09:00'), array('', '2026-10-15T09:00Z'), array(array(), '2026-10-15T09:00'), array('2026-10-15T06:00', '2026-10-15T09:00')) as $invalid)
{
	if ($schedule->parse($invalid[0], $invalid[1], $now) !== NULL)
	{
		throw new RuntimeException('Invalid/past/overlong schedule must be rejected.');
	}
}
echo "PASS family schedule: WIB to UTC, immediate start, calendar validation, future bounds and malformed input.\n";
