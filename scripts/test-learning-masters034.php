<?php
$database = getenv('DB_DATABASE'); if (PHP_SAPI !== 'cli' || ! preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', (string) $database)) { exit(2); }
$_SERVER['argv'] = array('index.php', 'cli/migrate'); ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean();
$ci =& get_instance();
$tables = array('st_categories','st_learning_grades','st_arena_categories','st_arena_pack_categories','st_arena_packs','st_users','st_arena_runs');
$checksum = function () use ($ci, $tables) { return $ci->db->query('CHECKSUM TABLE '.implode(',', $tables).' EXTENDED')->result_array(); };
$before = $checksum();
require_once APPPATH.'migrations/034_add_learning_masters.php'; $migration = new Migration_Add_learning_masters(); $migration->up();
if ($checksum() !== $before) { throw new RuntimeException('Rerun must not change existing masters, IDs, game content, accounts or progress.'); }
foreach (array('st_learning_grades' => 11, 'st_arena_categories' => 29, 'st_arena_pack_categories' => 29) as $table => $expected)
{
	if ((int) $ci->db->count_all($table) !== $expected) { throw new RuntimeException('Unexpected import count.'); }
}
$wrong = $ci->db->query('SELECT COUNT(*) n FROM st_arena_pack_categories m JOIN st_arena_packs p ON p.id=m.pack_id JOIN st_arena_categories c ON c.id=m.arena_category_id JOIN st_learning_grades g ON g.id=c.grade_id WHERE p.game_type<>c.game_type OR p.learning_stage<>g.learning_stage')->row_array();
if ((int) $wrong['n'] !== 0) { throw new RuntimeException('Game/grade mapping mismatch.'); }
echo "PASS master snapshot counts, game/stage mappings, idempotent reimport and checksums of existing IDs/users/progress/content preserved.\n";
