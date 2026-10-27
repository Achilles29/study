<?php
if (PHP_SAPI !== 'cli' || getenv('DB_DATABASE') !== 'study_qa_monitor_sso039') exit(2);
$_SERVER['argv'] = array('index.php', 'cli/migrate');
ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean();
$ci =& get_instance();
$gateway = $ci->telegram_gateway ?? NULL;
if (!$gateway) { $ci->load->library('telegram_gateway'); $gateway = $ci->telegram_gateway; }
function pair_check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$token = '123456789:'.str_repeat('a', 35);
$now = time();
$code = bin2hex(random_bytes(16));
putenv('STUDY_TELEGRAM_PAIR_TEST_GETME='.json_encode(array('ok'=>TRUE,'result'=>array('is_bot'=>TRUE,'username'=>'ProjectPintarTestBot'))));
pair_check($gateway->bot_username($token)['username'] === 'ProjectPintarTestBot', 'Bot username lookup failed');
$message = static function($type, $text, $date, $chat_id, $from_id) { return array('message'=>array('chat'=>array('type'=>$type,'id'=>$chat_id),'from'=>array('id'=>$from_id,'is_bot'=>FALSE),'text'=>$text,'date'=>$date)); };
$updates = array(
	$message('group','/start pp_'.$code,$now,-1001234567890,123456789),
	$message('private','/start pp_'.$code,$now-700,123456789,123456789),
	$message('private','/start pp_'.str_repeat('0',32),$now,234567890,234567890),
	$message('private','/start pp_'.$code,$now,345678901,345678901),
);
putenv('STUDY_TELEGRAM_PAIR_TEST_GETUPDATES='.json_encode(array('ok'=>TRUE,'result'=>$updates)));
pair_check($gateway->find_private_chat($token,$code,$now)['chat_id'] === '345678901', 'Private challenge not matched precisely');
pair_check($gateway->find_private_chat($token,str_repeat('f',32),$now)['error'] === 'not_found', 'Wrong challenge accepted');
putenv('STUDY_TELEGRAM_PAIR_TEST_GETUPDATES='.json_encode(array('ok'=>TRUE,'result'=>array_slice($updates,0,3))));
pair_check($gateway->find_private_chat($token,$code,$now)['error'] === 'not_found', 'Group or stale message accepted');
putenv('STUDY_TELEGRAM_PAIR_TEST_GETUPDATES='.json_encode(array('ok'=>FALSE,'error'=>'webhook_active')));
pair_check($gateway->find_private_chat($token,$code,$now)['error'] === 'webhook_active', 'Webhook conflict not surfaced');
putenv('STUDY_TELEGRAM_PAIR_TEST_GETME');putenv('STUDY_TELEGRAM_PAIR_TEST_GETUPDATES');
echo "PASS: deep-link bot lookup, private chat challenge, group/stale/wrong-code rejection and webhook fallback. No external Telegram call.\n";
