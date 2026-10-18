<?php
$database = getenv('DB_DATABASE'); if (PHP_SAPI !== 'cli' || ! preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', (string) $database)) { exit(2); }
$_SERVER['argv'] = array('index.php','cli/migrate'); ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean();
$ci =& get_instance(); $ci->load->model('Traffic_model'); $db = $ci->db;
function traffic_check($condition, $message) { if ( ! $condition) { throw new RuntimeException($message); } }
$old = $db->query("SELECT values_json FROM st_site_settings WHERE section='privasi'")->row_array()['values_json'];
$base = (int) $db->query('SELECT COUNT(*) n FROM st_traffic_events')->row_array()['n'];
for ($i = 0; $i < 3; $i++) { $db->insert('st_traffic_events', array('created_at' => '2026-09-14 '.($i === 0 ? '16:00:00' : '18:00:00'), 'expires_at' => gmdate('Y-m-d H:i:s', time() - 100), 'aggregate_expires_on' => '2027-09-14', 'policy_version' => 'qa-traffic-v1', 'page_key' => 'home/index', 'user_id' => NULL, 'account_kind' => 'guest', 'learning_stage' => 'unset', 'visitor_hash' => hash('sha256', 'qa-visitor-'.$i), 'visit_hash' => hash('sha256', 'qa-visit-'.$i))); }
$db->insert('st_traffic_daily', array('day_wib' => '2024-01-01', 'page_key' => 'home/index', 'account_kind' => 'guest', 'views' => 7, 'expires_on' => '2025-01-01'));
try
{
	$preview = $ci->Traffic_model->retention(); traffic_check($preview['events_to_aggregate'] === 3, 'Preview exact expired rows'); traffic_check( ! $ci->Traffic_model->retention(TRUE, 'wrong-version')['ok'], 'Manual approval version required');
	$db->query("CREATE TRIGGER qa_retention_audit_fail BEFORE INSERT ON st_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA retention fail'");
	try { $failed = $ci->Traffic_model->retention(TRUE, $preview['policy_version']); traffic_check( ! $failed['ok'], 'Audit failure propagated'); traffic_check((int) $db->query('SELECT COUNT(*) n FROM st_traffic_events')->row_array()['n'] === $base + 3, 'No partial removal'); traffic_check((int) $db->query('SELECT SUM(views) n FROM st_traffic_daily')->row_array()['n'] === 7, 'Aggregate rollback'); }
	finally { $db->query('DROP TRIGGER qa_retention_audit_fail'); }
	$db->where('section', 'privasi')->update('st_site_settings', array('values_json' => json_encode(array('approved' => '0', 'enabled' => '0'))));
	$r = $ci->Traffic_model->retention(TRUE, '', TRUE); traffic_check($r['ok'] && $r['events_aggregated'] === 3, 'Stored expiry honored after collection/policy is paused');
	traffic_check((int) $db->query('SELECT COUNT(*) n FROM st_traffic_events')->row_array()['n'] === $base, 'Only expired event rows removed');
	traffic_check((int) $db->query('SELECT SUM(views) n FROM st_traffic_daily')->row_array()['n'] === 3, 'Aggregate views preserved; expired old aggregate removed');
	traffic_check($db->query("SELECT day_wib,views FROM st_traffic_daily ORDER BY day_wib")->result_array() === array(array('day_wib' => '2026-09-14', 'views' => '1'), array('day_wib' => '2026-09-15', 'views' => '2')), 'WIB boundary correct');
	$repeat = $ci->Traffic_model->retention(TRUE, '', TRUE); traffic_check($repeat['ok'] && $repeat['events_aggregated'] === 0, 'Retention replay idempotent');
	echo "PASS retention preview/approval, atomic aggregation+purge+audit rollback, WIB dates, no identities in daily data, stored expiry despite policy pause, and idempotent repeat. Synthetic expired rows only.\n";
}
finally { $db->where('section', 'privasi')->update('st_site_settings', array('values_json' => $old)); }
