<?php
define('BASEPATH', __DIR__); class CI_Model {}
require __DIR__.'/../application/models/Operational_traffic_model.php';
$m = new Operational_traffic_model();
function expect($condition) { if ( ! $condition) { throw new RuntimeException('Client classification check failed.'); } }
$info = $m->client_info(array('REMOTE_ADDR' => '2001:0db8:0000::1', 'HTTP_CF_CONNECTING_IP' => '8.8.8.8', 'HTTP_X_FORWARDED_FOR' => '9.9.9.9', 'HTTP_CF_IPCOUNTRY' => 'US', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36 Chrome/110.0 Mobile Safari/537.36'));
expect($info['ip_address'] === '2001:db8::1' && $info['country'] === NULL && $info['device'] === 'Ponsel' && $info['browser'] === 'Chrome' && $info['os'] === 'Android');
$info = $m->client_info(array('REMOTE_ADDR' => '<script>', 'STUDY_COUNTRY' => 'ID', 'HTTP_USER_AGENT' => 'curl/8.0'));
expect($info['ip_address'] === NULL && $info['country'] === 'ID' && $info['is_bot'] === 1);
foreach (array('XX','T1','<script>','ID,US',array('ID')) as $bad) { expect($m->client_info(array('STUDY_COUNTRY' => $bad))['country'] === NULL); }
expect($m->client_info(array('HTTP_USER_AGENT' => array('bad')))['device'] === 'Tidak diketahui');
echo "PASS client: trusted REMOTE_ADDR only, canonical IPv6, trusted country server parameter only, invalid input, no raw UA, browser/device/OS and bot labels.\n";
require __DIR__.'/../application/libraries/Site_settings.php'; $settings = new Site_settings(); $v = $settings->defaults('privasi');
$v['policy_text'] = str_repeat("Contoh kebijakan privasi\r\n", 12); $v['terms_text'] = str_repeat("Contoh syarat layanan\r\n", 12); $v['approved'] = '1'; $v['policy_version'] = 'qa-v1'; $v['operational_enabled'] = '1';
$result = $settings->validate('privasi', $v); expect($result['errors'] === array() && strpos($result['values']['policy_text'], "\r") === FALSE);
$v['policy_text'] .= "\0"; expect(isset($settings->validate('privasi', $v)['errors']['policy_text']));
echo "PASS settings: browser CRLF textarea normalized; NUL still rejected.\n";
