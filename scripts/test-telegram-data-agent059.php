<?php
if (PHP_SAPI !== 'cli' || getenv('DB_DATABASE') !== 'study_qa_monitor_sso039') exit(2);
$_SERVER['argv'] = array('index.php', 'cli/migrate');
ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean();
$ci =& get_instance();
$ci->load->model(array('Telegram_bot_model', 'Telegram_bot_query_model', 'Telegram_bot_commands_model'));
$db = $ci->db;
$before = $db->query('SELECT chat_id FROM st_notification_config WHERE id=1')->row_array();
$chat = '123456789';
function data_check($value, $message) { if (!$value) throw new RuntimeException($message); }
try {
    $db->where('id', 1)->update('st_notification_config', array('chat_id' => $chat));
    $member_plan = array('queries' => array(array('dataset' => 'users', 'metric' => 'count', 'filters' => array(array('field' => 'is_member', 'op' => 'eq', 'value' => 1), array('field' => 'created_at', 'op' => 'this_month')))));
    $result = $ci->Telegram_bot_query_model->execute($chat, $member_plan);
    data_check(isset($result['results'][0]['rows'][0]['value']), 'Member month count missing');
    $payment_plan = array('queries' => array(array('dataset' => 'payments', 'metric' => 'sum', 'field' => 'amount_idr', 'filters' => array(array('field' => 'purpose', 'op' => 'eq', 'value' => 'commerce'), array('field' => 'status', 'op' => 'eq', 'value' => 'paid')))));
    $payments = $ci->Telegram_bot_query_model->execute($chat, $payment_plan);
    $expected = $db->query("SELECT SUM(amount_idr) amount FROM st_payment_orders WHERE environment='production' AND purpose='commerce' AND status='paid'")->row_array();
    data_check((string)$payments['results'][0]['rows'][0]['value'] === (string)$expected['amount'], 'Production payment filter missing');
    $group = $ci->Telegram_bot_query_model->execute($chat, array('queries' => array(array('dataset' => 'courses', 'metric' => 'count', 'group_by' => 'month:created_at', 'filters' => array()))));
    data_check(isset($group['results'][0]['rows']), 'Monthly group missing');
	$names = $ci->Telegram_bot_query_model->execute($chat, array('queries' => array(array('dataset' => 'members', 'metric' => 'list', 'fields' => array('name', 'status'), 'sort_by' => 'created_at', 'limit' => 15), array('dataset' => 'members', 'metric' => 'count'))));
	data_check(isset($names['results'][0]['rows'][0]['name']) && !array_key_exists('email', $names['results'][0]['rows'][0]), 'Member names unavailable');
	$visitors = $ci->Telegram_bot_query_model->execute($chat, array('queries' => array(array('dataset' => 'operational_visits', 'metric' => 'distinct_count', 'field' => 'ip_key', 'filters' => array(array('field' => 'is_bot', 'op' => 'eq', 'value' => 0), array('field' => 'created_at', 'op' => 'today'))))));
	data_check(isset($visitors['results'][0]['rows'][0]['value']), 'Visitor estimate unavailable');
	$menu = $ci->Telegram_bot_commands_model->menu();
	data_check(mb_strlen($menu, 'UTF-8') <= 1500 && strpos($menu, '/anak') !== FALSE && strpos($menu, '/halaman') !== FALSE && strpos($menu, 'bulan_ini') !== FALSE, 'Command menu incomplete or too long');
	$registered = $ci->Telegram_bot_commands_model->telegram_commands();
	data_check(count($registered) <= 100 && in_array('anak', array_column($registered, 'command'), TRUE) && in_array('halaman', array_column($registered, 'command'), TRUE), 'Telegram command registration incomplete');
	$child_reply = $ci->Telegram_bot_commands_model->answer($chat, 'anak', 'nama');
	data_check(strpos($child_reply, 'Menampilkan') !== FALSE && strpos($child_reply, 'Akun anak') !== FALSE, 'Child names command failed');
	$pages_reply = $ci->Telegram_bot_commands_model->answer($chat, 'halaman', 'terbaru 10');
	data_check(strpos($pages_reply, 'Halaman terakhir dikunjungi') !== FALSE, 'Recent pages command failed');
	$time_reply = $ci->Telegram_bot_commands_model->answer($chat, 'member', 'bulan_ini');
	data_check(strpos($time_reply, 'bulan ini') !== FALSE, 'Time option failed');
	$range_reply = $ci->Telegram_bot_commands_model->answer($chat, 'member', '2026-10-01..2026-10-07');
	data_check(strpos($range_reply, '2026-10-01 sampai 2026-10-07') !== FALSE, 'Custom date range failed');
    putenv('STUDY_CODEX_BOT_TEST_PLAN='.json_encode($member_plan));
    $method = new ReflectionMethod($ci->Telegram_bot_model, 'planned_data_context');
    $method->setAccessible(TRUE);
    $context = $method->invoke($ci->Telegram_bot_model, $chat, array(), 'Berapa member baru bulan ini?', 'codex', array());
    data_check(strpos($context, 'Hasil kueri operasional terbaru') !== FALSE && strpos($context, '"dataset":"users"') !== FALSE, 'Natural language plan did not reach data layer');
	putenv('STUDY_CODEX_BOT_TEST_PLAN={"queries":[]}');
	$name_context = $method->invoke($ci->Telegram_bot_model, $chat, array(array('role' => 'user', 'content' => 'anggota baru')), 'siapa aja namanya', 'codex', array());
	data_check(strpos($name_context, '"dataset":"members"') !== FALSE && strpos($name_context, '"name"') !== FALSE, 'Member-name follow-up did not resolve');
	$visitor_context = $method->invoke($ci->Telegram_bot_model, $chat, array(array('role' => 'user', 'content' => 'berapa pengunjung hari ini')), 'siapa aja namanya', 'codex', array());
	data_check($visitor_context === '', 'Visitor follow-up confused with members');
	$child_context = $method->invoke($ci->Telegram_bot_model, $chat, array(), 'data nama anak', 'codex', array());
	data_check(strpos($child_context, '"dataset":"children"') !== FALSE && strpos($child_context, '"name"') !== FALSE, 'Child-name question did not query database');
    foreach (array(
        array('queries' => array(array('dataset' => 'st_bot_ai_config', 'metric' => 'list', 'fields' => array('key_cipher')))),
        array('queries' => array(array('dataset' => 'users', 'metric' => 'list', 'fields' => array('password_hash')))),
        array('queries' => array(array('dataset' => 'users', 'metric' => 'count', 'filters' => array(array('field' => 'id', 'op' => 'eq', 'value' => '1 OR 1=1'))))),
		array('queries' => array(array('dataset' => 'operational_visits', 'metric' => 'list', 'fields' => array('ip_key')))),
    ) as $blocked) {
        $rejected = FALSE;
        try { $ci->Telegram_bot_query_model->execute($chat, $blocked); }
        catch (InvalidArgumentException $error) { $rejected = TRUE; }
        data_check($rejected, 'Unsafe query accepted');
    }
    try { $ci->Telegram_bot_query_model->execute('987654321', $member_plan); throw new RuntimeException('Unpaired chat accepted'); }
    catch (RuntimeException $error) { data_check($error->getMessage() === 'Bot data access denied.', 'Wrong authorization failure'); }
    echo "PASS 059: natural-language data, children, page visits, full command menu with periods, authorization, and sensitive-column denial.\n";
} finally {
    putenv('STUDY_CODEX_BOT_TEST_PLAN');
    $db->where('id', 1)->update('st_notification_config', array('chat_id' => $before['chat_id']));
}
