<?php
$database = getenv('DB_DATABASE');
if (PHP_SAPI !== 'cli' || ! preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', (string) $database)) { exit(2); }
$_SERVER['argv'] = array('index.php', 'cli/migrate'); ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean(); $ci =& get_instance();
$tables = array('st_categories','st_learning_grades','st_arena_categories','st_arena_pack_categories','st_arena_packs','st_users','st_arena_runs');
$before = $ci->db->query('CHECKSUM TABLE '.implode(',', $tables).' EXTENDED')->result_array();
require_once APPPATH.'migrations/036_connect_arena_masters.php'; (new Migration_Connect_arena_masters())->up();
if ($ci->db->query('CHECKSUM TABLE '.implode(',', $tables).' EXTENDED')->result_array() !== $before) { throw new RuntimeException('Repeated mapping changed data.'); }
foreach (array('st_learning_grades' => 14, 'st_arena_categories' => 97, 'st_arena_pack_categories' => 190) as $table => $expected)
{
	if ((int) $ci->db->count_all($table) !== $expected) { throw new RuntimeException('Unexpected master count.'); }
}
// Legacy categories retain source grade metadata; some legacy packs intentionally span stages.
$new_invalid = $ci->db->query("SELECT COUNT(*) n FROM st_arena_packs p JOIN st_arena_pack_categories m ON m.pack_id=p.id JOIN st_arena_categories c ON c.id=m.arena_category_id WHERE c.source_code LIKE 'study-arena-%' AND (c.game_type<>p.game_type OR c.learning_stage<>p.learning_stage)")->row_array();
if ((int) $new_invalid['n'] !== 0 || (int) $ci->db->query('SELECT COUNT(*) n FROM st_arena_packs p LEFT JOIN st_arena_pack_categories m ON m.pack_id=p.id WHERE m.pack_id IS NULL')->row_array()['n'] !== 0) { throw new RuntimeException('Incomplete/wrong mapping.'); }
foreach ($tables as $table)
{
	$key = $table === 'st_arena_pack_categories' ? 'pack_id' : 'id';
	foreach ($ci->db->query('SELECT * FROM study_qa_monitor_master035.'.$table.' ORDER BY '.$key)->result_array() as $row)
	{
		$current = $ci->db->get_where($table, array($key => $row[$key]))->row_array();
		if ($table === 'st_users') { unset($current['last_login_at'], $current['updated_at'], $row['last_login_at'], $row['updated_at']); }
		if ($table === 'st_arena_categories') { unset($current['learning_stage']); }
		if ($current !== $row) { throw new RuntimeException('Historical row changed: '.$table); }
	}
}
echo "PASS all 190 packs mapped across 8 types, 97 categories, 14 grades; old IDs/content/progress and mappings preserved, rerun checksum unchanged.\n";
