<?php
if (PHP_SAPI !== 'cli' || getenv('DB_DATABASE') !== 'study_qa_monitor_sso039') exit(2);
$_SERVER['argv'] = array('index.php', 'cli/migrate');
ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean();
$ci =& get_instance();
$ci->load->model(array('Notification_model', 'Account_model'));
$db = $ci->db;
$model = $ci->Notification_model;
$admin = $ci->Account_model->find_active_user(1);
$before = $model->settings();
$ids = array();
function registration_check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function registration_event($db, $type, $id, $actor, $entity = 'user')
{
	$db->insert('st_audit_events', array('actor_user_id' => $actor, 'event_type' => $type, 'entity_type' => $entity, 'entity_id' => $id, 'details_json' => '{}', 'created_at' => gmdate('Y-m-d H:i:s')));
	return (int)$db->insert_id();
}

try {
	registration_check((int)$before['registration_floor'] > 0, 'Registration floor missing');
	$values = array('revision' => (string)$before['revision'], 'token' => '123456789:'.str_repeat('a', 35), 'chat_id' => '-1001234567890', 'enabled' => '1', 'members' => '1', 'children' => '1', 'confirm' => '1');
	foreach (array('payments', 'donations', 'help', 'visitors') as $key) $values[$key] = (string)$before[$key];
	$values['payments'] = $values['help'] = '1';
	registration_check($model->save_settings($admin, $values)['ok'], 'QA settings unavailable');
	$legacy_values = $values;
	$legacy_values['revision'] = (string)$model->settings()['revision'];
	$legacy_values['token'] = '';
	unset($legacy_values['members'], $legacy_values['children']);
	registration_check($model->save_settings($admin, $legacy_values)['ok'], 'Legacy form save failed');
	registration_check((int)$model->settings()['members'] === 1 && (int)$model->settings()['children'] === 1, 'Legacy form disabled new categories');
	$users = array();
	foreach (array('adult', 'parent', 'child') as $kind) {
		$users[$kind] = $db->select('id,username,email,display_name')->where('account_kind', $kind)->order_by('id')->limit(1)->get('st_users')->row_array();
		registration_check((bool)$users[$kind], 'QA user fixture missing: '.$kind);
	}
	$ids[] = registration_event($db, 'account.registered', $users['adult']['id'], $users['adult']['id']);
	$ids[] = registration_event($db, 'google.registered', $users['parent']['id'], $users['parent']['id']);
	$ids[] = registration_event($db, 'family.child_created', $users['child']['id'], $users['parent']['id']);
	registration_check($model->collect()['ok'], 'Registration collection failed');
	foreach ($ids as $index => $id) {
		$entry = $db->where('event_key', 'audit:'.$id)->get('st_telegram_outbox')->row_array();
		registration_check((bool)$entry, 'Registration event not queued: '.$index);
		registration_check($entry['category'] === ($index === 2 ? 'children' : 'members'), 'Wrong notification category');
		$person = $users[$index === 0 ? 'adult' : ($index === 1 ? 'parent' : 'child')];
		registration_check(strpos($entry['body'], $person['display_name']) !== FALSE, 'Account name missing from detailed Telegram message');
		registration_check(!$person['email'] || strpos($entry['body'], $person['email']) === FALSE, 'Email leaked to Telegram');
		registration_check($db->where('event_key', 'audit:'.$id)->count_all_results('st_notifications') >= 1, 'Admin in-app notification missing');
	}
	$child_body = $db->where('event_key', 'audit:'.$ids[2])->get('st_telegram_outbox')->row_array()['body'];
	registration_check(strpos($child_body, $users['parent']['display_name']) !== FALSE, 'Parent name missing from child notification');
	registration_check($model->collect()['ok'], 'Collector replay failed');
	foreach ($ids as $id) registration_check($db->where('event_key', 'audit:'.$id)->count_all_results('st_telegram_outbox') === 1, 'Duplicate Telegram notification');

	$values['revision'] = (string)$model->settings()['revision'];
	$values['token'] = '';
	$values['members'] = '0';
	registration_check($model->save_settings($admin, $values)['ok'], 'Member toggle not saved');
	$ids[] = registration_event($db, 'account.registered', $users['adult']['id'], $users['adult']['id']);
	$ids[] = registration_event($db, 'family.child_created', $users['child']['id'], $users['parent']['id']);
	registration_check($model->collect()['ok'], 'Collection with member toggle off failed');
	registration_check($db->where('event_key', 'audit:'.$ids[3])->count_all_results('st_telegram_outbox') === 0, 'Disabled member notification queued');
	registration_check($db->where('event_key', 'audit:'.$ids[4])->count_all_results('st_telegram_outbox') === 1, 'Child notification disabled with members');
	$order = $db->where('environment', 'production')->where('purpose', 'commerce')->order_by('id', 'DESC')->limit(1)->get('st_payment_orders')->row_array();
	$ticket = $db->where('user_id IS NOT NULL', NULL, FALSE)->order_by('id', 'DESC')->limit(1)->get('st_help_tickets')->row_array();
	registration_check($order && $ticket, 'Payment/help QA fixtures missing');
	$ids[] = registration_event($db, 'payment.order_created', $order['id'], $order['user_id'], 'payment_order');
	$ids[] = registration_event($db, 'help.created', $ticket['id'], $ticket['user_id'], 'help_ticket');
	registration_check($model->collect()['ok'], 'Detailed event collection failed');
	$order_message = $db->where('event_key', 'audit:'.$ids[5])->get('st_telegram_outbox')->row_array();
	$help_message = $db->where('event_key', 'audit:'.$ids[6])->get('st_telegram_outbox')->row_array();
	$order_owner = $db->select('display_name')->where('id', $order['user_id'])->get('st_users')->row_array();
	$help_owner = $db->select('display_name')->where('id', $ticket['user_id'])->get('st_users')->row_array();
	registration_check($order_message && strpos($order_message['body'], $order_owner['display_name']) !== FALSE && strpos($order_message['body'], number_format((int)$order['amount_idr'], 0, ',', '.')) !== FALSE, 'Payment name or amount missing');
	registration_check($help_message && strpos($help_message['body'], $help_owner['display_name']) !== FALSE && strpos($help_message['body'], $ticket['subject']) !== FALSE, 'Help sender or subject missing');
	echo "PASS 056: local and Google registrations and child creation queue once; separate switches work; names are included without email addresses.\n";
} finally {
	if ($ids) {
		$keys = array_map(static function($id) { return 'audit:'.$id; }, $ids);
		$db->where_in('event_key', $keys)->delete('st_telegram_outbox');
		$db->where_in('event_key', $keys)->delete('st_notifications');
		$db->where_in('event_id', $ids)->delete('st_notification_events');
		$db->where_in('id', $ids)->delete('st_audit_events');
	}
	$db->where('id', 1)->update('st_notification_config', array(
		'token_cipher' => $before['token_cipher'], 'chat_id' => $before['chat_id'],
		'enabled' => $before['enabled'], 'members' => $before['members'], 'children' => $before['children'],
		'payments' => $before['payments'], 'donations' => $before['donations'],
		'help' => $before['help'], 'visitors' => $before['visitors'],
		'revision' => (int)$model->settings()['revision'] + 1, 'updated_at' => gmdate('Y-m-d H:i:s'),
	));
}
