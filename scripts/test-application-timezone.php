<?php

$project_root = dirname(__DIR__);
define('BASEPATH', $project_root.DIRECTORY_SEPARATOR.'system'.DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'testing');

$config = array();
require $project_root.DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'config.php';

if (date_default_timezone_get() !== 'Asia/Jakarta' || $config['time_reference'] !== 'Asia/Jakarta')
{
	fwrite(STDERR, "Application timezone is not configured consistently as Asia/Jakarta.\n");
	exit(1);
}

echo "PASS application timezone is Asia/Jakarta (".date('P').").\n";
