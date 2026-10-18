<?php
$database = getenv('DB_DATABASE'); if (PHP_SAPI !== 'cli' || ! preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', (string) $database)) { exit(2); }
$_SERVER['argv'] = array('index.php','cli/migrate'); ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean();
$ci =& get_instance(); $ci->load->model(array('Content_model','Account_model','Commerce_model','Mail_model','Points_model')); $db = $ci->db;
function study_controls_check($ok, $message) { if ( ! $ok) { throw new RuntimeException($message); } }
$parent = $ci->Account_model->find_active_user(2); $child = $ci->Account_model->find_active_user(3);
study_controls_check((int) $parent['auth_version'] > 0 && (int) $child['auth_version'] > 0, 'Nonzero session epochs required.');
study_controls_check($ci->Mail_model->reauthenticate(2, $parent['auth_stamp'], 'Monitor-QA-Only-2026!', '127.0.0.1')['ok'], 'Reauth after epoch rotation');
$offer = $db->query("SELECT o.*,p.revision package_revision FROM st_commerce_offers o JOIN st_packages p ON p.id=o.package_id WHERE o.kind='voucher' AND o.status='active' LIMIT 1")->row_array();
$result = $ci->Commerce_model->buy(2, $parent['auth_stamp'], (int) $offer['id'], (string) $offer['revision'], '0', bin2hex(random_bytes(32)), (string) $offer['package_revision']); study_controls_check(($result['code'] ?? 0) === 409, 'Valid post-rotation commerce credential reaches balance/review guard, not auth rejection.');
$package = $db->query('SELECT id,revision FROM st_packages WHERE point_price>0 LIMIT 1')->row_array();
$result = $ci->Points_model->redeem(3, $child['auth_stamp'], (int) $package['id'], (string) $package['revision'], bin2hex(random_bytes(32))); study_controls_check(($result['code'] ?? 0) === 409, 'Valid rotated child credential reaches insufficient-points guard.');
$db->trans_begin();
try
{
	$db->query('INSERT INTO st_course_access_blocks(user_id,course_id,blocked,updated_at) VALUES(3,1,1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE blocked=1');
	study_controls_check( ! $ci->Content_model->can_access_course(3, array('id' => 1, 'is_free' => 1), TRUE), 'Block even free course while logged in');
	study_controls_check( ! $ci->Content_model->can_access_lesson(3, array('course_id' => 1, 'is_free' => 1, 'is_preview' => 1), TRUE), 'Preview cannot bypass account restriction');
	study_controls_check($ci->Content_model->can_access_course(0, array('id' => 1, 'is_free' => 1)), 'Public free course remains public');
	study_controls_check(is_array($ci->Content_model->get_resumable_lessons(3)), 'Resume SQL with course restriction');
	$db->query('UPDATE st_course_access_blocks SET blocked=0 WHERE user_id=3 AND course_id=1');
	study_controls_check($ci->Content_model->can_access_course(3, array('id' => 1, 'is_free' => 1), TRUE), 'Restore removes only the restriction');
	echo "PASS credential epochs across mail/commerce/points, blocked free/preview course and resume, public guest semantics; access fixture changes rolled back.\n";
}
finally { $db->trans_rollback(); }
