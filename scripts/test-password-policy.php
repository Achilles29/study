<?php
if (PHP_SAPI !== 'cli')
{
	exit(1);
}
define('BASEPATH', dirname(__DIR__).'/system/');
require dirname(__DIR__).'/application/helpers/study_password_helper.php';

$cases = array(
	array(str_repeat('a', 72), 12, TRUE), array(str_repeat('a', 73), 12, FALSE),
	array(str_repeat('é', 36), 12, TRUE), array(str_repeat('é', 37), 12, FALSE),
	array(str_repeat('😀', 18), 12, TRUE), array(str_repeat('😀', 19), 12, FALSE),
	array(str_repeat('é', 11), 12, FALSE), array(str_repeat('a', 10), 10, TRUE),
	array(str_repeat('a', 9), 10, FALSE), array(str_repeat('a', 15), 16, FALSE),
	array(str_repeat('a', 16), 16, TRUE), array('  exact spaces  ', 12, TRUE),
	array("valid-password\0suffix", 12, FALSE), array("invalid-utf8-\xff", 12, FALSE),
	array(array('not-scalar'), 1, FALSE), array(NULL, 1, FALSE), array('', 1, FALSE)
);
foreach ($cases as $index => $case)
{
	if (study_valid_password($case[0], $case[1]) !== $case[2])
	{
		throw new RuntimeException('Password policy case failed: '.$index);
	}
}
$boundary = str_repeat('a', 72);
$hash = password_hash($boundary, PASSWORD_DEFAULT);
if ( ! password_verify($boundary, $hash) || password_verify(str_repeat('a', 71).'b', $hash)
	|| ! password_verify($boundary.'ignored-suffix', $hash) || study_valid_password($boundary.'ignored-suffix'))
{
	throw new RuntimeException('Bcrypt boundary reproduction or application guard failed.');
}
// Invalid direct model calls must exit before touching DB or hashing.
class CI_Model {}
require dirname(__DIR__).'/application/models/Account_model.php';
$model = new Account_model();
foreach (array($boundary.'x', str_repeat('é', 37), "invalid-secret\0suffix", array()) as $invalid)
{
	if ($model->register_adult_or_parent('adult', 'unused', 'unused@example.test', 'Unused', $invalid) !== FALSE
		|| $model->create_child(1, 'unused', 'Unused', $invalid, 'primary') !== FALSE)
	{
		throw new RuntimeException('Model accepted invalid password.');
	}
}
echo "PASS password policy: byte/UTF-8/minimum boundaries, native bcrypt suffix reproduction, scalar/NUL guards and direct model no-write rejection.\n";
