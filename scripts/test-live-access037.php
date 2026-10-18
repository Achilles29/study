<?php
$database = getenv('DB_DATABASE'); $spool = getenv('STUDY_PAYMENT_TEST_DIRECTORY');
if (PHP_SAPI !== 'cli' || ! preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', (string) $database) || ! preg_match('#\A/tmp/study-payment-qa-[A-Za-z0-9]+\z#', (string) $spool)) { exit(2); }
putenv('STUDY_QA_LIVE_ACCESS_ONLY=1');
$_SERVER['argv'] = array('index.php','cli/migrate'); ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean(); $ci =& get_instance();
$ci->load->model(array('Commerce_access_model','Content_model','Parent_practice_model','Learning_monitor_model','Commerce_model'));
$state = json_decode(file_get_contents($spool.'/live-state.json'), TRUE); $ids = $state['ids']; $db = $ci->db;
function live_check($value, $message) { if ( ! $value) { throw new RuntimeException($message); } }
$before = $db->query('CHECKSUM TABLE st_packages,st_commerce_offers EXTENDED')->result_array();
require_once APPPATH.'migrations/038_add_draft_products.php'; (new Migration_Add_draft_products())->up();
live_check($db->query('CHECKSUM TABLE st_packages,st_commerce_offers EXTENDED')->result_array() === $before, 'Draft rerun overwrote catalog.');
$db->trans_begin();
try
{
	$db->where('id', 1)->update('st_courses', array('is_free' => 0)); $course = array('id' => 1, 'is_free' => 0);
	live_check($ci->Content_model->can_access_course($ids['child'], $course), 'Live child access');
	live_check( ! $ci->Content_model->can_access_course($ids['other'], $course), 'Other family access');
	live_check($ci->Parent_practice_model->has_access($ids['parent']), 'Live parent authoring');
	live_check($ci->Commerce_access_model->extra_slots($ids['parent'], TRUE) === 1, 'Live slot capacity');
	$db->query("UPDATE st_commerce_grants SET environment='sandbox' WHERE owner_user_id=?", array($ids['parent']));
	$db->query("UPDATE st_family_slots SET environment='sandbox' WHERE owner_user_id=?", array($ids['parent']));
	live_check( ! $ci->Content_model->can_access_course($ids['child'], $course), 'Sandbox grant leaked to live');
	live_check( ! $ci->Parent_practice_model->has_access($ids['parent']), 'Sandbox parent capability leaked');
	live_check($ci->Commerce_access_model->extra_slots($ids['parent'], TRUE) === 0, 'Sandbox slot leaked');
	$db->query("UPDATE st_commerce_grants SET environment='production' WHERE owner_user_id=?", array($ids['parent']));
	$db->query("UPDATE st_commerce_grants SET ends_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE owner_user_id=? AND kind='platinum'", array($ids['parent']));
	live_check($ci->Content_model->can_access_course($ids['child'], $course), 'Individual package survives Platinum expiry');
	live_check( ! $ci->Parent_practice_model->has_access($ids['parent']), 'Expired Platinum authoring');
	$db->query("UPDATE st_commerce_grants SET status='revoked' WHERE owner_user_id=? AND kind='package'", array($ids['parent']));
	live_check( ! $ci->Content_model->can_access_course($ids['child'], $course), 'Revoked package access');
	$db->query("UPDATE st_commerce_grants SET ends_at=UTC_TIMESTAMP()+INTERVAL 1 DAY WHERE owner_user_id=? AND kind='platinum'", array($ids['parent']));
	live_check($ci->Content_model->can_access_course($ids['child'], $course), 'Live family Platinum');
	$db->query("UPDATE st_family_links SET status='revoked' WHERE parent_user_id=? AND child_user_id=?", array($ids['parent'],$ids['child']));
	live_check( ! $ci->Content_model->can_access_course($ids['child'], $course), 'Revoked family link');
	live_check(is_array($ci->Content_model->get_resumable_lessons($ids['child'])), 'Resume query');
	$report = $ci->Learning_monitor_model->entitlements($ids['child'], 1);
	live_check(strpos(json_encode($report), 'Live') !== FALSE, 'Monitoring environment label');
	$db->query("UPDATE st_questions SET status='draft' WHERE course_id=1");
	live_check( ! $ci->Commerce_model->package_ready($state['pack']), 'Unpublished questions must block readiness');
	echo "PASS live vs sandbox course access, family capability/slots, expiry, revocation, cross-family isolation, resume/report labels, unpublished-question guard and idempotent draft import. Fixture mutations rolled back.\n";
}
finally { $db->trans_rollback(); }
$questions = $db->select('id,status')->get_where('st_questions', array('course_id' => 1))->result_array();
try
{
	$db->where('course_id', 1)->update('st_questions', array('status' => 'draft'));
	$root = $db->get_where('st_users', array('id' => 1))->row_array();
	$before_packages = $db->count_all('st_packages');
	$publish = $ci->Commerce_model->save_package(1, study_credential_stamp($root), 0, array('title' => 'QA rejected empty questions', 'summary' => 'Synthetic only', 'status' => 'published', 'is_official' => '1', 'revision' => '0', 'confirm' => '1', 'family_practice_enabled' => '0', 'point_price' => '0', 'point_days' => '0', 'requires_questions' => '1', 'courses' => array('1')));
	live_check( ! $publish['ok'] && $publish['code'] === 400 && $db->count_all('st_packages') === $before_packages, 'Publishing missing questions must roll back');
	echo "PASS missing-question publication rejected and transaction rolled back.\n";
}
finally { foreach ($questions as $question) { $db->where('id', $question['id'])->update('st_questions', array('status' => $question['status'])); } }
