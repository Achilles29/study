<?php
// Pure response parser regression; no database, credential or network access.
define('BASEPATH', __DIR__);
require __DIR__.'/../application/libraries/Midtrans_probe.php';
$probe = new Midtrans_probe(); $method = new ReflectionMethod($probe, 'response_status'); $method->setAccessible(TRUE);
$cases = array(
 array(200, '{"status_code":"404"}', 'status_api_reached'),
 array(404, '{"status_code":"404"}', 'status_api_reached'),
 array(200, '{"status_code":404}', 'status_api_reached'),
 array(401, '{"status_code":"404"}', 'unauthorized'),
 array(403, '{}', 'unauthorized'), array(200, '{"status_code":"401"}', 'unauthorized'),
 array(404, '{"status_code":"403"}', 'unauthorized'),
 array(500, '{"status_code":"404"}', 'unexpected_response'),
 array(302, '{"status_code":"404"}', 'unexpected_response'),
 array(204, '{"status_code":"404"}', 'unexpected_response'),
 array(200, '{"status_code":"200"}', 'unexpected_response'),
 array(404, '<html>Not found</html>', 'unexpected_response'),
 array(200, '', 'unexpected_response'), array(200, '{}', 'unexpected_response'),
 array(200, 'null', 'unexpected_response'), array(200, '{"status_code":true}', 'unexpected_response'),
 array(200, '{"status_code":[404]}', 'unexpected_response'), array(200, '{"status_code":"404 "}', 'unexpected_response')
);
foreach ($cases as $i => $case) { if ($method->invoke($probe, $case[0], $case[1]) !== $case[2]) { throw new RuntimeException('Failed case '.$i); } }
echo 'PASS '.count($cases).' Midtrans probe response cases; HTTP200/API404 accepted, auth/error/redirect/malformed responses fail closed.'.PHP_EOL;
