<?php
$database = getenv('DB_DATABASE');
if (PHP_SAPI !== 'cli' || ! preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', (string) $database)) { exit(2); }
$_SERVER['argv'] = array('index.php', 'cli/migrate'); ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean();
$ci =& get_instance(); $ci->load->model(array('Arena_model','Points_model','Account_model')); $db = $ci->db;
function points_check($condition, $message) { if ( ! $condition) { throw new RuntimeException($message); } }
$child = $db->query("SELECT id FROM st_users WHERE username LIKE 'qacap_%' AND account_kind='child' ORDER BY id DESC LIMIT 1")->row_array()['id'];
$previous = $db->query("SELECT values_json FROM st_site_settings WHERE section='poin'")->row_array()['values_json'];
$db->where('section', 'poin')->update('st_site_settings', array('values_json' => json_encode(array('enabled' => '1', 'reward' => '10', 'daily_cap' => '30', 'pass_score' => '80'))));
$balance = static function () use ($ci, $child) { return (int) $ci->Points_model->summary($child)['balance_points']; };
$run_math = static function ($slug, $correct = TRUE, $fail = FALSE, $learner = NULL) use ($ci, $db, $child) {
	$learner = $learner ?? $child; $start = $ci->Arena_model->start($learner, $slug, bin2hex(random_bytes(32)), 'calm'); points_check($start['ok'], 'Start math'); $id = $start['id'];
	while (TRUE)
	{
		$r = $db->query('SELECT * FROM st_arena_runs WHERE id=?', array($id))->row_array(); if ($r['status'] !== 'running') { break; }
		$s = json_decode($r['snapshot_json'], TRUE); $state = json_decode($r['state_json'], TRUE); $value = $correct ? $s['items'][$state['position']]['term'] : '99999999';
		if ($fail && $state['position'] === count($s['items']) - 1)
		{
			$db->query("CREATE TRIGGER qa_points_fail BEFORE INSERT ON st_point_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA points fail'");
			try { $failed = $ci->Arena_model->act($learner, $id, (string) $r['revision'], 'answer', $value); points_check( ! $failed['ok'] && $failed['code'] === 503, 'Reward failure fails whole finish'); points_check($db->query('SELECT status FROM st_arena_runs WHERE id=?', array($id))->row_array()['status'] === 'running', 'Game completion rolled back'); }
			finally { $db->query('DROP TRIGGER qa_points_fail'); } $fail = FALSE;
		}
		$result = $ci->Arena_model->act($learner, $id, (string) $r['revision'], 'answer', $value); points_check($result['ok'], 'Server math answer');
	}
	$replay = $ci->Arena_model->act($learner, $id, '1', 'answer', '99999999'); points_check($replay['ok'], 'Finished replay safe'); return $id;
};
try
{
	$base = $balance(); points_check($base === 0, 'Use a fresh synthetic child for this run');
	$run_math('hitung-1', FALSE); points_check($balance() === 0, 'Below threshold earns nothing');
	$run_math('hitung-1'); points_check($balance() === 10, 'First qualified set reward');
	$run_math('hitung-1'); points_check($balance() === 10, 'Repeated set never rewards twice');
	$run_math('hitung-2', TRUE, TRUE); points_check($balance() === 20, 'Reward/game/audit rollback and retry');
	$run_math('hitung-3'); $run_math('hitung-4'); points_check($balance() === 30, 'WIB daily cap');
	$run_math('hitung-1', TRUE, FALSE, 20); points_check((int) $ci->Points_model->summary(20)['balance_points'] === 0, 'Adult cannot earn child redemption points');
	$db->where('section', 'poin')->update('st_site_settings', array('values_json' => json_encode(array('enabled' => '0')))); $run_math('hitung-5'); points_check($balance() === 30, 'Disabled engine no award');
	points_check((int) $db->query('SELECT SUM(delta_points) n FROM st_point_entries WHERE learner_user_id=?', array($child))->row_array()['n'] === $balance(), 'Points ledger reconciles');
	echo "PASS real Arena server scoring to child rewards, threshold, first-set uniqueness/replay, WIB cap, adult exclusion, disabled rules and finish/reward rollback with retry.\n";
}
finally { $db->where('section', 'poin')->update('st_site_settings', array('values_json' => $previous)); }
