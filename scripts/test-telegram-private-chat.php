<?php
if (PHP_SAPI !== 'cli' || getenv('DB_DATABASE') !== 'study_qa_monitor_sso039') exit(2);
$_SERVER['argv'] = array('index.php', 'cli/migrate');
ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean();
$ci =& get_instance();
$ci->load->model(array('Notification_model', 'Account_model'));
$gateway = $ci->telegram_gateway;
$model = $ci->Notification_model;
$admin = $ci->Account_model->find_active_user(1);
function telegram_check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

telegram_check($gateway->valid_chat_id('123456789'), 'Private chat ID rejected');
telegram_check($gateway->valid_chat_id('-1001234567890'), 'Existing group ID rejected');
foreach (array('', '0', '-0', '0123456789', '@someone', '+628123456789', '123abc') as $invalid) {
	telegram_check(!$gateway->valid_chat_id($invalid), 'Invalid chat ID accepted');
}
$token = '123456789:'.str_repeat('a', 35);
putenv('STUDY_TELEGRAM_TEST=success');
try {
	telegram_check($gateway->send($token, '123456789', 'Private test')['ok'], 'Private delivery rejected');
	telegram_check($gateway->send($token, '-1001234567890', 'Group test')['ok'], 'Group delivery regressed');
	telegram_check($gateway->send($token, '@someone', 'Invalid test')['error'] === 'invalid_configuration', 'Username should be rejected');
} finally { putenv('STUDY_TELEGRAM_TEST'); }
$config = $model->settings();
telegram_check((int)$config['revision'] > 0, 'QA settings revision unavailable');
$input = array('revision'=>'0', 'token'=>$token, 'chat_id'=>'123456789', 'enabled'=>'1', 'payments'=>'1', 'donations'=>'1', 'help'=>'1', 'visitors'=>'0', 'confirm'=>'1');
telegram_check($model->save_settings($admin, $input)['code'] === 409, 'Private ID should pass validation and reach revision guard');
$input['chat_id'] = '@someone';
telegram_check($model->save_settings($admin, $input)['code'] === 400, 'Username should fail form validation');
$input['chat_id'] = '123456789';
$input['revision'] = (string)$config['revision'];
$input['token'] = '';
foreach (array('enabled','payments','donations','help','visitors') as $key) $input[$key] = (string)$config[$key];
telegram_check($model->save_settings($admin, $input)['ok'], 'Private chat destination not saved');
telegram_check($model->settings()['chat_id'] === '123456789', 'Saved private destination unavailable');
$input['revision'] = (string)$model->settings()['revision'];
$input['chat_id'] = $config['chat_id'];
telegram_check($model->save_settings($admin, $input)['ok'], 'QA destination not restored');
$before_staging = $model->settings();
try {
	$input['revision'] = (string)$before_staging['revision'];
	$input['token'] = $token;
	$input['chat_id'] = '';
	$input['enabled'] = '1';
	$staged = $model->save_settings($admin, $input);
	telegram_check($staged['ok'] && !empty($staged['activation_pending']), 'Token staging with checked activation failed');
	$after_staging = $model->settings();
	telegram_check((int)$after_staging['enabled'] === 0 && $after_staging['chat_id'] === '', 'Staged token unexpectedly enabled delivery');
	telegram_check($gateway->open($after_staging['token_cipher']) === $token, 'Staged token was not saved');
} finally {
	$ci->db->where('id', 1)->update('st_notification_config', array(
		'token_cipher' => $before_staging['token_cipher'],
		'chat_id' => $before_staging['chat_id'],
		'enabled' => $before_staging['enabled'],
		'payments' => $before_staging['payments'],
		'donations' => $before_staging['donations'],
		'help' => $before_staging['help'],
		'visitors' => $before_staging['visitors'],
		'revision' => (int)$model->settings()['revision'] + 1,
		'updated_at' => gmdate('Y-m-d H:i:s'),
	));
}
echo "PASS: private/group IDs and token staging accepted; delivery stayed disabled until chat exists; QA configuration restored.\n";
