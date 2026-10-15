<?php
if ($argc !== 2 || ! in_array($argv[1], array('development', 'production'), TRUE))
{
	fwrite(STDERR, "Usage: php test-environment-config.php development|production\n");
	exit(2);
}

define('BASEPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', $argv[1]);
putenv('APP_BASE_URL=https://study.example.test/');
putenv('APP_ENCRYPTION_KEY=qa-only-placeholder-not-a-secret');
putenv('APP_COOKIE_SECURE=1');
$config = array();
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';

$expected_log_threshold = ENVIRONMENT === 'production' ? 1 : 0;
if ( ! isset($config['log_threshold']) || $config['log_threshold'] !== $expected_log_threshold)
{
	fwrite(STDERR, 'Unexpected log threshold for ' . ENVIRONMENT . ".\n");
	exit(1);
}

if ( ! isset($config['log_file_permissions']) || $config['log_file_permissions'] !== 0600)
{
	fwrite(STDERR, "Log files must be created with permission 0600.\n");
	exit(1);
}

fwrite(STDOUT, 'PASS ' . ENVIRONMENT . ' log threshold=' . $config['log_threshold'] . ', log file permission=0600.' . "\n");
