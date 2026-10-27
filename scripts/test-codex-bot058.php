<?php
if (PHP_SAPI !== 'cli' || getenv('DB_DATABASE') !== 'study_qa_monitor_sso039') exit(2);
$_SERVER['argv'] = array('index.php', 'cli/migrate');
ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean();
$ci =& get_instance();
$ci->load->model(array('Notification_model', 'Telegram_bot_model', 'Account_model'));
$db = $ci->db;
$notify = $ci->Notification_model;
$bot = $ci->Telegram_bot_model;
$admin = $ci->Account_model->find_active_user(1);
$before = $notify->settings();
$before_ai = $bot->ai_settings();
$before_state = $db->where('id', 1)->get('st_telegram_bot_state')->row_array();
$chat = '123456789';
$base = max(900000000 + random_int(1000, 99999), (int)$before_state['next_update_id'] + 10);
function codex_check($value, $message) { if (!$value) throw new RuntimeException($message); }
try {
    $settings = array('revision' => (string)$before['revision'], 'token' => '123456789:'.str_repeat('a', 35), 'chat_id' => $chat, 'enabled' => '1', 'confirm' => '1');
    foreach (array('payments','donations','help','visitors','members','children') as $key) $settings[$key] = (string)$before[$key];
    codex_check($notify->save_settings($admin, $settings)['ok'], 'Bot destination not saved');
    $new_ai = array('revision' => (string)$before_ai['revision'], 'provider' => 'codex', 'key' => '', 'daily_limit' => '5', 'enabled' => '1', 'confirm' => '1');
    codex_check($bot->save_ai_settings($admin, $new_ai)['ok'], 'Codex provider without API key not saved');
    codex_check($bot->ai_settings()['provider'] === 'codex', 'Codex provider missing');
    putenv('STUDY_CODEX_BOT_TEST_RESPONSE=Halo! Aku siap membantu belajarmu.');
    putenv('STUDY_TELEGRAM_PAIR_TEST_SETMYCOMMANDS='.json_encode(array('ok' => TRUE, 'result' => TRUE)));
    putenv('STUDY_TELEGRAM_PAIR_TEST_GETUPDATES='.json_encode(array('ok' => TRUE, 'result' => array(
        array('update_id' => $base, 'message' => array('message_id' => 100, 'date' => time(), 'text' => 'Bantu buat rencana belajar', 'chat' => array('id' => (int)$chat, 'type' => 'private'), 'from' => array('id' => (int)$chat, 'is_bot' => FALSE))),
        array('update_id' => $base + 1, 'message' => array('message_id' => 101, 'date' => time(), 'text' => 'Berapa jumlah member kita sekarang?', 'chat' => array('id' => (int)$chat, 'type' => 'private'), 'from' => array('id' => (int)$chat, 'is_bot' => FALSE))),
        array('update_id' => $base + 2, 'message' => array('message_id' => 102, 'date' => time(), 'text' => '/ringkasan', 'chat' => array('id' => (int)$chat, 'type' => 'private'), 'from' => array('id' => (int)$chat, 'is_bot' => FALSE))),
        array('update_id' => $base + 3, 'message' => array('message_id' => 103, 'date' => time(), 'text' => '/anak nama', 'chat' => array('id' => (int)$chat, 'type' => 'private'), 'from' => array('id' => (int)$chat, 'is_bot' => FALSE))),
        array('update_id' => $base + 4, 'message' => array('message_id' => 104, 'date' => time(), 'text' => '/halaman terbaru 10', 'chat' => array('id' => (int)$chat, 'type' => 'private'), 'from' => array('id' => (int)$chat, 'is_bot' => FALSE))),
        array('update_id' => $base + 5, 'message' => array('message_id' => 105, 'date' => time(), 'text' => 'Rahasia?', 'chat' => array('id' => 987654321, 'type' => 'private'), 'from' => array('id' => 987654321, 'is_bot' => FALSE)))
    ))));
    codex_check((int)$bot->poll()['accepted'] === 5, 'Only paired private chat should be accepted');
    codex_check((int)$bot->respond()['processed'] === 1, 'Codex reply not processed');
    $usage_after_ai = (int)$bot->ai_settings()['used_count'];
    for ($i = 0; $i < 4; $i++) codex_check((int)$bot->respond()['processed'] === 1, 'Operational reply not processed');
    codex_check((int)$bot->ai_settings()['used_count'] === $usage_after_ai, 'Operational counts consumed AI quota');
    $reply = $db->where('event_key', 'bot-reply:'.$base)->get('st_telegram_outbox')->row_array();
    codex_check($reply && strpos($reply['body'], 'Halo! Aku siap') !== FALSE, 'Codex reply missing');
    $members = $db->where('event_key', 'bot-reply:'.($base + 1))->get('st_telegram_outbox')->row_array();
    $ci->load->model('Telegram_bot_context_model');
    $expected_members = (int)$ci->Telegram_bot_context_model->snapshot($chat)['users']['member_active'];
    codex_check($members && strpos($members['body'], 'ada '.number_format($expected_members, 0, ',', '.').' member aktif') !== FALSE && strpos($members['body'], 'Akun anak aktif') !== FALSE, 'Live member count missing or inaccurate');
    $summary = $db->where('event_key', 'bot-reply:'.($base + 2))->get('st_telegram_outbox')->row_array();
    codex_check($summary && strpos($summary['body'], 'Ringkasan Project Pintar') !== FALSE, 'Operational summary missing');
    $children = $db->where('event_key', 'bot-reply:'.($base + 3))->get('st_telegram_outbox')->row_array();
    codex_check($children && strpos($children['body'], 'Akun anak') !== FALSE && strpos($children['body'], 'Menampilkan') !== FALSE, 'Child names command was not dispatched');
    $pages = $db->where('event_key', 'bot-reply:'.($base + 4))->get('st_telegram_outbox')->row_array();
    codex_check($pages && strpos($pages['body'], 'Halaman terakhir dikunjungi') !== FALSE, 'Recent pages command was not dispatched');
    codex_check($db->where('event_key', 'bot-reply:'.($base + 5))->count_all_results('st_telegram_outbox') === 0, 'Unknown chat received reply');
    codex_check($db->where('chat_id', $chat)->count_all_results('st_bot_ai_turns') === 2, 'Encrypted history not saved');
    echo "PASS 058: Codex chat, member count, child/page commands, operational summary, and private-chat authorization.\n";
} finally {
    putenv('STUDY_CODEX_BOT_TEST_RESPONSE');
    putenv('STUDY_TELEGRAM_PAIR_TEST_SETMYCOMMANDS');
    putenv('STUDY_TELEGRAM_PAIR_TEST_GETUPDATES');
    $db->where_in('event_key', array('bot-reply:'.$base, 'bot-reply:'.($base + 1), 'bot-reply:'.($base + 2), 'bot-reply:'.($base + 3), 'bot-reply:'.($base + 4), 'bot-reply:'.($base + 5)))->delete('st_telegram_outbox');
    $db->where('update_id >=', $base)->where('update_id <=', $base + 5)->delete('st_telegram_bot_inbox');
    $db->where('chat_id', $chat)->delete('st_bot_ai_turns');
    $db->where('id', 1)->update('st_telegram_bot_state', array('next_update_id' => $before_state['next_update_id'], 'commands_set' => $before_state['commands_set'], 'updated_at' => gmdate('Y-m-d H:i:s')));
    $db->where('id', 1)->update('st_bot_ai_config', array('provider' => $before_ai['provider'], 'enabled' => $before_ai['enabled'], 'key_cipher' => $before_ai['key_cipher'], 'daily_limit' => $before_ai['daily_limit'], 'usage_day' => $before_ai['usage_day'], 'used_count' => $before_ai['used_count'], 'revision' => (int)$bot->ai_settings()['revision'] + 1, 'updated_at' => gmdate('Y-m-d H:i:s')));
    $db->where('id', 1)->update('st_notification_config', array('token_cipher' => $before['token_cipher'], 'chat_id' => $before['chat_id'], 'enabled' => $before['enabled'], 'revision' => (int)$notify->settings()['revision'] + 1, 'updated_at' => gmdate('Y-m-d H:i:s')));
}
