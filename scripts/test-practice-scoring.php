<?php
define('BASEPATH', __DIR__);
require __DIR__.'/../application/libraries/Practice_scoring.php';

$scoring = new Practice_scoring();
$cases = array(array(0, 3, '0.00'), array(3, 3, '100.00'), array(1, 3, '33.33'), array(2, 3, '66.67'), array(1, 6, '16.67'), array(1, 32, '3.13'), array(25, 50, '50.00'));
foreach ($cases as $case)
{
	if ($scoring->percentage($case[0], $case[1]) !== $case[2])
	{
		throw new RuntimeException('Incorrect practice percentage rounding.');
	}
}
foreach (array(array(0, 0), array(-1, 3), array(4, 3), array(1, 51), array('1', 3)) as $case)
{
	try
	{
		$scoring->percentage($case[0], $case[1]);
	}
	catch (InvalidArgumentException $error)
	{
		continue;
	}
	throw new RuntimeException('Invalid scoring arguments were accepted.');
}
echo "PASS practice scoring: zero/perfect scores, fractional half-up rounding, and invalid input.\n";
