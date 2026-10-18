<?php
// Service entry point. Credentials are read privately, never passed on the command line/output.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$environment_path = '/etc/projectpintar/environment.conf';
if ( ! is_readable($environment_path)) { fwrite(STDERR, "Runtime environment unavailable.\n"); exit(1); }
$configuration = parse_ini_file($environment_path, FALSE, INI_SCANNER_RAW);
foreach (($configuration['env'] ?? array()) as $key => $value) { putenv($key.'='.$value); }
if (getenv('DB_DATABASE') !== 'db_study') { fwrite(STDERR, "Unexpected database; no retention executed.\n"); exit(1); }
chdir(dirname(__DIR__)); $_SERVER['argv'] = array('index.php','cli/traffic_retention','index','scheduled'); require 'index.php';
