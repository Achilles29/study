<?php

/**
 * Development-only router for PHP's built-in web server.
 * Serve real project files directly; send application routes to CI3.
 */
$project_root = realpath(dirname(__DIR__));
$requested_path = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
$requested_file = realpath($project_root . DIRECTORY_SEPARATOR . ltrim((string) $requested_path, '/\\'));

if ($requested_file !== false &&
	strpos($requested_file, $project_root . DIRECTORY_SEPARATOR) === 0 &&
	is_file($requested_file))
{
	return false;
}

require $project_root . DIRECTORY_SEPARATOR . 'index.php';
