<?php
$database = getenv('DB_DATABASE'); if (PHP_SAPI !== 'cli' || ! preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', (string) $database)) { exit(2); }
$_SERVER['argv'] = array('index.php', 'cli/migrate'); ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean(); $ci =& get_instance();
$tables = array('st_categories','st_learning_grades','st_arena_categories','st_arena_pack_categories','st_arena_packs','st_users','st_arena_runs');
$checksum = function () use ($ci, $tables) { return $ci->db->query('CHECKSUM TABLE '.implode(',', $tables).' EXTENDED')->result_array(); };
$before = $checksum(); require_once APPPATH.'migrations/035_add_sma_masters.php'; (new Migration_Add_sma_masters())->up();
if ($checksum() !== $before) { throw new RuntimeException('Import rerun changed existing data.'); }
foreach (array('st_learning_grades' => 14, 'st_arena_categories' => 35, 'st_arena_pack_categories' => 29) as $table => $expected) { if ((int) $ci->db->count_all($table) !== $expected) { throw new RuntimeException('Unexpected master count.'); } }
foreach ($tables as $table)
{
	$old = $ci->db->query('SELECT * FROM study_qa_monitor_admin034.'.$table.' ORDER BY '.($table === 'st_arena_pack_categories' ? 'pack_id' : 'id'))->result_array();
	$key = $table === 'st_arena_pack_categories' ? 'pack_id' : 'id';
	foreach ($old as $row)
	{
		$current = $ci->db->get_where($table, array($key => $row[$key]))->row_array();
		// Browser QA legitimately logs in; only those login timestamps differ from the cloned fixture.
		if ($table === 'st_users') { unset($current['last_login_at'], $current['updated_at'], $row['last_login_at'], $row['updated_at']); }
		if ($current !== $row) { throw new RuntimeException('Existing row was modified: '.$table); }
	}
}
echo "PASS 14 grades/35 categories/29 activity mappings, full checksum unchanged on reimport; pre-035 rows/IDs preserved except expected QA login timestamps.\n";
