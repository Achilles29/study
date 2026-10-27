<?php
if (PHP_SAPI !== 'cli' || getenv('DB_DATABASE') !== 'study_qa_monitor_sso039') exit(2);
$_SERVER['argv'] = array('index.php', 'cli/migrate');
ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean();
$ci =& get_instance();
$ci->load->model(array('Notification_model', 'Telegram_bot_model', 'Account_model'));
$db = $ci->db;
$notifications = $ci->Notification_model;
$bot = $ci->Telegram_bot_model;
$admin = $ci->Account_model->find_active_user(1);
$before = $notifications->settings();
$before_ai = $bot->ai_settings();
$before_state = $db->where('id', 1)->get('st_telegram_bot_state')->row_array();
$token = '123456789:'.str_repeat('a', 35);
$chat = '123456789';
$code = bin2hex(random_bytes(16));
$base = 800000000 + random_int(100000, 900000);
$text = 'Halo, bisa bantu saya merencanakan belajar?';
function bot_check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

try {
	$settings = array('revision' => (string)$before['revision'], 'token' => $token, 'chat_id' => $chat, 'enabled' => '1', 'confirm' => '1');
	foreach (array('payments', 'donations', 'help', 'visitors', 'members', 'children') as $key) $settings[$key] = (string)$before[$key];
	bot_check($notifications->save_settings($admin, $settings)['ok'], 'QA bot destination not saved');
	$ai = array('revision' => (string)$before_ai['revision'], 'key' => 'sk-test-'.str_repeat('x', 36), 'daily_limit' => '5', 'enabled' => '1', 'confirm' => '1');
	$nonadmin = $ci->Account_model->find_active_user(2);
	bot_check($bot->save_ai_settings($nonadmin, $ai)['code'] === 403, 'Nonadmin changed AI settings');
	bot_check($bot->save_ai_settings($admin, $ai)['ok'], 'QA AI settings not saved');
	$sealed_ai = $bot->ai_settings();
	bot_check(strpos($sealed_ai['key_cipher'], $ai['key']) === FALSE && $ci->openai_bot_gateway->open($sealed_ai['key_cipher']) === $ai['key'], 'AI key not encrypted');
	bot_check($bot->save_ai_settings($admin, $ai)['code'] === 409, 'Stale AI settings revision accepted');
	$now = time();
	$messages = array(
		array('update_id' => $base, 'message' => array('message_id' => 11, 'date' => $now, 'text' => '/start pp_'.$code, 'chat' => array('id' => (int)$chat, 'type' => 'private'), 'from' => array('id' => (int)$chat, 'is_bot' => FALSE))),
		array('update_id' => $base + 1, 'message' => array('message_id' => 12, 'date' => $now, 'text' => '/menu', 'chat' => array('id' => (int)$chat, 'type' => 'private'), 'from' => array('id' => (int)$chat, 'is_bot' => FALSE))),
		array('update_id' => $base + 2, 'message' => array('message_id' => 13, 'date' => $now, 'text' => $text, 'chat' => array('id' => (int)$chat, 'type' => 'private'), 'from' => array('id' => (int)$chat, 'is_bot' => FALSE))),
		array('update_id' => $base + 3, 'message' => array('message_id' => 14, 'date' => $now, 'text' => '/status', 'chat' => array('id' => 987654321, 'type' => 'private'), 'from' => array('id' => 987654321, 'is_bot' => FALSE))),
	);
	putenv('STUDY_TELEGRAM_PAIR_TEST_SETMYCOMMANDS='.json_encode(array('ok' => TRUE, 'result' => TRUE)));
	putenv('STUDY_TELEGRAM_PAIR_TEST_GETUPDATES='.json_encode(array('ok' => TRUE, 'result' => $messages)));
	putenv('STUDY_OPENAI_BOT_TEST_RESPONSE='.json_encode(array('status' => 'completed', 'output' => array(array('type' => 'message', 'content' => array(array('type' => 'output_text', 'text' => 'Tentu, mari buat rencana belajar yang ringan.')))))));
	$polled = $bot->poll();
	bot_check($polled['ok'] && (int)$polled['accepted'] === 3, 'Only paired private chat should be accepted');
	bot_check($ci->telegram_gateway->find_private_chat($token, $code, $now)['chat_id'] === $chat, 'Pairing update was lost after polling');
	bot_check((int)$db->where('id', 1)->get('st_telegram_bot_state')->row_array()['next_update_id'] === $base + 4, 'Bot offset not stored');
	$processed = 0;
	for ($i = 0; $i < 3; $i++) $processed += $bot->respond()['processed'];
	bot_check($processed === 3, 'Bot did not answer three messages');
	$replies = $db->where_in('event_key', array('bot-reply:'.$base, 'bot-reply:'.($base + 1), 'bot-reply:'.($base + 2)))->order_by('id')->get('st_telegram_outbox')->result_array();
	bot_check(count($replies) === 3 && (int)$replies[2]['reply_to_message_id'] === 13, 'Bot replies missing or not threaded');
	bot_check(strpos($replies[1]['body'], '/anak') !== FALSE && strpos($replies[1]['body'], '/halaman') !== FALSE && strpos($replies[1]['body'], 'bulan_ini') !== FALSE, 'Full data menu missing');
	bot_check(strpos($replies[2]['body'], 'rencana belajar') !== FALSE, 'AI answer missing');
	bot_check($db->where('event_key', 'bot-reply:'.($base + 3))->count_all_results('st_telegram_outbox') === 0, 'Unknown chat received a reply');
	bot_check($db->where('chat_id', $chat)->count_all_results('st_bot_ai_turns') === 2, 'AI context was not stored');
	$turn = $db->where('chat_id', $chat)->order_by('id')->limit(1)->get('st_bot_ai_turns')->row_array();
	bot_check(strpos($turn['content_cipher'], $text) === FALSE, 'AI context stored in plaintext');
	putenv('STUDY_TELEGRAM_TEST=success');
	bot_check($notifications->deliver()['sent'] >= 3, 'Bot replies not delivered');
	echo "PASS 057: menu, private-chat authorization, pairing after update confirmation, AI reply, encrypted context and delivery.\n";
} finally {
	putenv('STUDY_TELEGRAM_PAIR_TEST_SETMYCOMMANDS');
	putenv('STUDY_TELEGRAM_PAIR_TEST_GETUPDATES');
	putenv('STUDY_OPENAI_BOT_TEST_RESPONSE');
	putenv('STUDY_TELEGRAM_TEST');
	$keys = array('bot-reply:'.$base, 'bot-reply:'.($base + 1), 'bot-reply:'.($base + 2), 'bot-reply:'.($base + 3));
	$db->where_in('event_key', $keys)->delete('st_telegram_outbox');
	$db->where('update_id >=', $base)->where('update_id <=', $base + 3)->delete('st_telegram_bot_inbox');
	$db->where('code_hash', hash('sha256', $code))->delete('st_telegram_pair_updates');
	$db->where('chat_id', $chat)->delete('st_bot_ai_turns');
	$db->where('id', 1)->update('st_telegram_bot_state', array('next_update_id' => $before_state['next_update_id'], 'commands_set' => $before_state['commands_set'], 'updated_at' => gmdate('Y-m-d H:i:s')));
	$db->where('id', 1)->update('st_bot_ai_config', array('provider' => $before_ai['provider'], 'enabled' => $before_ai['enabled'], 'key_cipher' => $before_ai['key_cipher'], 'daily_limit' => $before_ai['daily_limit'], 'usage_day' => $before_ai['usage_day'], 'used_count' => $before_ai['used_count'], 'revision' => (int)$bot->ai_settings()['revision'] + 1, 'updated_at' => gmdate('Y-m-d H:i:s')));
	$db->where('id', 1)->update('st_notification_config', array('token_cipher' => $before['token_cipher'], 'chat_id' => $before['chat_id'], 'enabled' => $before['enabled'], 'revision' => (int)$notifications->settings()['revision'] + 1, 'updated_at' => gmdate('Y-m-d H:i:s')));
}
