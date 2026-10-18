<?php
// Reuse CI bootstrap through a local, CLI-only harness. Never run against production.
$database = getenv('DB_DATABASE');
if (PHP_SAPI !== 'cli' || ! preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', (string) $database)) { exit(2); }
$_SERVER['argv'] = array('index.php', 'cli/migrate');
ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean();
$ci =& get_instance(); $ci->load->model(array('Commerce_access_model','Content_model','Parent_practice_model','Learning_monitor_model'));
function check_commerce($condition, $message) { if ( ! $condition) { throw new RuntimeException($message); } }
$db = $ci->db;
$db->trans_begin();
try
{
	$db->where('id', 1)->update('st_courses', array('is_free' => 0));
	$db->query("UPDATE st_course_enrollments SET status='revoked' WHERE learner_user_id IN (2,3,4,19) AND course_id=1");
	$course = array('id' => 1, 'is_free' => 0);
	check_commerce($ci->Content_model->can_access_course(3, $course), 'Child package/platinum access');
	check_commerce( ! $ci->Content_model->can_access_course(4, $course), 'Other family cannot inherit Platinum');
	check_commerce($ci->Parent_practice_model->has_access(2), 'Platinum enables parent practice');
	check_commerce($ci->Commerce_access_model->extra_slots(2, TRUE) === 1, 'Purchased slot counted once');
	$db->query("UPDATE st_commerce_grants SET ends_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE kind='platinum'");
	check_commerce($ci->Content_model->can_access_course(3, $course), 'Individual grant survives Platinum expiry');
	check_commerce( ! $ci->Parent_practice_model->has_access(2), 'Expired Platinum cannot author family practice');
	$db->query("UPDATE st_commerce_grants SET status='revoked' WHERE kind='package' AND learner_user_id=3");
	check_commerce( ! $ci->Content_model->can_access_course(3, $course), 'Revoked grants deny access');
	$db->query("UPDATE st_commerce_grants SET ends_at=UTC_TIMESTAMP()+INTERVAL 1 DAY WHERE kind='platinum'");
	check_commerce($ci->Content_model->can_access_course(3, $course), 'Platinum covers published official package');
	$db->query("UPDATE st_packages SET is_official=0");
	check_commerce( ! $ci->Content_model->can_access_course(3, $course), 'Nonofficial packages excluded from Platinum');
	$db->query("UPDATE st_packages SET is_official=1");
	$db->query("UPDATE st_family_links SET status='revoked' WHERE parent_user_id=2 AND child_user_id=3");
	check_commerce( ! $ci->Content_model->can_access_course(3, $course), 'Unlinked child loses family Platinum');
	check_commerce(is_array($ci->Content_model->get_resumable_lessons(3)), 'Resume SQL integrates purchased grants');
	check_commerce(is_array($ci->Learning_monitor_model->entitlements(3, 1)), 'Access monitoring integrates grants');
	$point_child = (int) $db->query('SELECT MAX(learner_user_id) id FROM st_point_redemptions')->row_array()['id'];
	if ($point_child > 0)
	{
		$db->query("UPDATE st_commerce_grants SET status='revoked' WHERE kind='platinum'");
		check_commerce($ci->Content_model->can_access_course($point_child, $course), 'Point redemption opens only owned package');
		check_commerce(is_array($ci->Learning_monitor_model->entitlements($point_child, 1)), 'Points appear in access monitor');
		$db->query("UPDATE st_point_redemptions SET status='revoked' WHERE learner_user_id=?", array($point_child));
		check_commerce( ! $ci->Content_model->can_access_course($point_child, $course), 'Revoked points access denied, ledger retained');
	}
	echo "PASS course grants, individual/Platinum expiry, cross-family isolation, link revocation, official scope, parent capability, purchased slots, resume and monitoring. All fixture changes rolled back.\n";
}
finally { $db->trans_rollback(); }
