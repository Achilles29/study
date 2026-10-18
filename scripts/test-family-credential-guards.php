<?php
$database = getenv('DB_DATABASE'); if (PHP_SAPI !== 'cli' || ! preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', (string) $database)) { exit(2); }
$_SERVER['argv'] = array('index.php','cli/migrate'); ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean();
$ci =& get_instance(); $ci->load->model('Account_model'); $db = $ci->db; $parent = $ci->Account_model->find_active_user(2); $nonce = bin2hex(random_bytes(4)); $name = 'qaguard_'.$nonce;
function study_family_check($ok, $message) { if ( ! $ok) { throw new RuntimeException($message); } }
study_family_check( ! $ci->Account_model->update_child_learning_stage(2, 3, 'primary', 'stale'), 'Stale parent cannot change stage');
study_family_check($ci->Account_model->update_child_learning_stage(2, 3, 'primary', $parent['auth_stamp']), 'Fresh parent can change stage');
study_family_check( ! $ci->Account_model->create_child(2, $name, 'QA Guard', 'Child-QA-Only-2026!', 'primary', 'stale'), 'Stale parent cannot create child');
$other = $db->query("SELECT u.id FROM st_family_links l JOIN st_users u ON u.id=l.child_user_id WHERE l.parent_user_id=2 AND l.status='active' AND u.status='active' AND u.id<>3 ORDER BY u.id LIMIT 1")->row_array()['id'];
$verified = $db->query('SELECT email_verified_at FROM st_users WHERE id=2')->row_array()['email_verified_at']; $created = NULL;
try
{
	$db->where('id', $other)->update('st_users', array('status' => 'suspended'));
	$db->where('id', 2)->update('st_users', array('email_verified_at' => NULL));
	study_family_check( ! $ci->Account_model->create_child(2, $name, 'QA Guard', 'Child-QA-Only-2026!', 'primary', $parent['auth_stamp']), 'Unverified parent cannot create child even with capacity');
	$db->where('id', 2)->update('st_users', array('email_verified_at' => $verified));
	study_family_check($ci->Account_model->create_child(2, $name, 'QA Guard', 'Child-QA-Only-2026!', 'primary', $parent['auth_stamp']), 'Verified parent with current credential can create child');
	$created = $db->query('SELECT id FROM st_users WHERE username=?', array($name))->row_array()['id'];
	echo "PASS family stage/create current-credential guard, unverified parent rejection and verified creation with available capacity. Synthetic child retained suspended.\n";
}
finally
{
	if ($created) { $db->where('id', $created)->update('st_users', array('status' => 'suspended')); }
	$db->where('id', 2)->update('st_users', array('email_verified_at' => $verified)); $db->where('id', $other)->update('st_users', array('status' => 'active'));
}
