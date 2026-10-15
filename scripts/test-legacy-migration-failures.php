<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');

class CI_Migration
{
	public $db;
	public function __construct($database)
	{
		$this->db = $database;
	}
}
class Migration_count_probe
{
	private $row;
	private $should_throw;
	public function __construct($row, $should_throw = FALSE)
	{
		$this->row = $row;
		$this->should_throw = $should_throw;
	}
	public function row_array()
	{
		if ($this->should_throw) { throw new RuntimeException('Synthetic COUNT row-read failure.'); }
		return $this->row;
	}
}
class Migration_database_probe
{
	public $calls = array();
	public $fail_at = 0;
	public $throw_at = 0;
	public $count_at = 0;
	public $row_throw_at = 0;
	public $count_row = array('total' => 0);
	public $transaction_ok = TRUE;
	private function write_result($operation, $data)
	{
		$this->calls[] = array($operation, $data);
		if (count($this->calls) === $this->throw_at) { throw new RuntimeException('Synthetic query failure.'); }
		return count($this->calls) !== $this->fail_at;
	}
	public function query($sql)
	{
		if ( ! $this->write_result('query', $sql)) { return FALSE; }
		if (strpos($sql, 'SELECT COUNT(*) AS total') === 0)
		{
			return new Migration_count_probe(count($this->calls) === $this->count_at ? $this->count_row : array('total' => '0'), count($this->calls) === $this->row_throw_at);
		}
		return TRUE;
	}
	public function insert($table, $data)
	{
		return $this->write_result('insert', array($table, $data));
	}
	public function where($column, $value)
	{
		if ($column !== 'account_kind' || $value !== 'adult') { throw new RuntimeException('Unexpected migration WHERE condition.'); }
		return $this;
	}
	public function update($table, $data)
	{
		return $this->write_result('update', array($table, $data));
	}
	public function trans_status()
	{
		return $this->transaction_ok;
	}
}
function expect_migration($condition, $message)
{
	if ( ! $condition) { throw new RuntimeException($message); }
}
function must_stop_migration($migration, $direction, $database, $calls)
{
	$thrown = FALSE;
	try { $migration->$direction(); }
	catch (RuntimeException $error) { $thrown = TRUE; }
	expect_migration($thrown && count($database->calls) === $calls, get_class($migration).' '.$direction.' must throw and stop before any later SQL statement.');
}
function is_migration_count_read($call)
{
	return $call[0] === 'query' && strpos($call[1], 'SELECT COUNT(*) AS total') === 0;
}
function expect_guard_only_reads($calls)
{
	foreach ($calls as $call)
	{
		expect_migration(is_migration_count_read($call), 'A refused guard must execute no DDL, insert, update or other write, including before the failed COUNT.');
	}
}
function expect_migration_preflight($calls, $expected_guards)
{
	$guards = 0;
	$write_seen = FALSE;
	foreach ($calls as $call)
	{
		if (is_migration_count_read($call))
		{
			expect_migration( ! $write_seen, 'All COUNT guards must finish before the first DDL or other write.');
			$guards++;
		}
		else { $write_seen = TRUE; }
	}
	expect_migration($guards === $expected_guards, 'The complete migration preflight COUNT inventory must be preserved.');
}
function expect_all_table_guard_targets($calls)
{
	$guard_tables = array();
	$write_tables = array();
	foreach ($calls as $call)
	{
		expect_migration($call[0] === 'query', 'Destructive migration inventory must contain only checked SQL.');
		if (is_migration_count_read($call))
		{
			expect_migration(preg_match('/\ASELECT COUNT\(\*\) AS total FROM `?([a-z0-9_]+)`?\z/', $call[1], $matches) === 1, 'Every protected table must be counted completely, including seeded roles.');
			$guard_tables[] = $matches[1];
		}
		else
		{
			expect_migration(preg_match('/\A(?:DROP TABLE(?: IF EXISTS)?|ALTER TABLE) `?([a-z0-9_]+)`?/', $call[1], $matches) === 1, 'Unexpected destructive migration SQL.');
			$write_tables[] = $matches[1];
		}
	}
	$write_tables = array_values(array_unique($write_tables));
	sort($guard_tables, SORT_STRING);
	sort($write_tables, SORT_STRING);
	expect_migration($guard_tables === $write_tables, 'Each destructive target must have exactly one complete preflight COUNT; duplicate or unrelated guards cannot hide a missing target.');
}

$inventory = array(
	array('001_create_core_schema.php', 'Migration_Create_core_schema', 20, 40, 20),
	array('002_add_login_throttling.php', 'Migration_Add_login_throttling', 1, 2, 1),
	array('003_add_roles_and_course_access.php', 'Migration_Add_roles_and_course_access', 9, 10, 5),
	array('004_add_content_review_metadata.php', 'Migration_Add_content_review_metadata', 4, 8, 4),
	array('005_use_plain_text_for_lessons.php', 'Migration_Use_plain_text_for_lessons', 2, 3, 1),
	array('006_add_learning_stage_to_users.php', 'Migration_Add_learning_stage_to_users', 2, 2, 1),
	array('007_create_flashcards.php', 'Migration_Create_flashcards', 3, 6, 3),
	array('008_add_course_prerequisites.php', 'Migration_Add_course_prerequisites', 1, 2, 1),
	array('009_add_course_practice_snapshots.php', 'Migration_Add_course_practice_snapshots', 3, 4, 1),
	array('010_add_family_practice_authoring.php', 'Migration_Add_family_practice_authoring', 2, 4, 2),
	array('011_add_family_delivery.php', 'Migration_Add_family_delivery', 2, 3, 1),
	array('012_add_matching_history.php', 'Migration_Add_matching_history', 2, 4, 2),
	array('013_add_lesson_images.php', 'Migration_Add_lesson_images', 1, 2, 1)
);
$failure_cases = 0;
$guard_cases = 0;
$guard_exception_cases = 0;
$empty_integer_cases = 0;
$guard_locations = 0;
$bad_count_rows = array(
	array('total' => 1), array('total' => '12'), array('total' => 4),
	FALSE, NULL, array(), array('total' => NULL), array('total' => array(0)),
	array('total' => new stdClass()), array('total' => -1), array('total' => 'invalid'),
	array('total' => FALSE), array('total' => TRUE), array('total' => 0.0),
	array('total' => 1.5), array('total' => ''), array('total' => ' 0 '),
	array('total' => '-0'), array('total' => '0.0'), array('total' => '0e0')
);
// A PHP warning is not an acceptable fail-closed guard exception.
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
foreach ($inventory as $item)
{
	require dirname(__DIR__).'/application/migrations/'.$item[0];
	foreach (array('up' => $item[2], 'down' => $item[3]) as $direction => $expected_calls)
	{
		$database = new Migration_database_probe();
		$migration = new $item[1]($database);
		expect_migration($migration->$direction() === TRUE && count($database->calls) === $expected_calls, 'Success-path SQL/write inventory changed: '.$item[0].' '.$direction);
		$baseline = $database->calls;
		expect_migration_preflight($baseline, $direction === 'down' ? $item[4] : 0);
		if ($direction === 'down' && ((int) substr($item[0], 0, 3) <= 8 || $item[0] === '013_add_lesson_images.php'))
		{
			expect_all_table_guard_targets($baseline);
		}
		foreach ($baseline as $index => $call)
		{
			$database = new Migration_database_probe();
			$database->fail_at = $index + 1;
			$migration = new $item[1]($database);
			must_stop_migration($migration, $direction, $database, $index + 1);
			foreach ($database->calls as $seen_index => $seen_call)
			{
				// Seed timestamps are deliberately excluded from this order comparison.
				if ($seen_call[0] !== 'insert') { expect_migration($seen_call === $baseline[$seen_index], 'Failure path changed SQL before its failed statement.'); }
			}
			$failure_cases++;
			if (is_migration_count_read($call))
			{
				// The failed query case above must also have made no earlier writes.
				expect_guard_only_reads($database->calls);
				$guard_locations++;
				foreach ($bad_count_rows as $row)
				{
					$database = new Migration_database_probe();
					$database->count_at = $index + 1;
					$database->count_row = $row;
					$migration = new $item[1]($database);
					must_stop_migration($migration, $direction, $database, $index + 1);
					expect_guard_only_reads($database->calls);
					$guard_cases++;
				}
				foreach (array('throw_at', 'row_throw_at') as $failure_type)
				{
					$database = new Migration_database_probe();
					$database->$failure_type = $index + 1;
					must_stop_migration(new $item[1]($database), $direction, $database, $index + 1);
					expect_guard_only_reads($database->calls);
					$guard_exception_cases++;
				}
				$database = new Migration_database_probe();
				$database->count_at = $index + 1;
				$database->count_row = array('total' => 0);
				$migration = new $item[1]($database);
				expect_migration($migration->$direction() === TRUE && $database->calls === $baseline, 'A valid integer-zero COUNT must preserve the empty-success SQL path.');
				$empty_integer_cases++;
			}
		}
	}
}
$database = new Migration_database_probe();
$database->transaction_ok = FALSE;
must_stop_migration(new Migration_Add_roles_and_course_access($database), 'up', $database, 9);
restore_error_handler();
echo 'PASS '.(count($inventory) * 2).' migration success paths, '.$failure_cases.' individual failed SQL/write stops, '.$guard_locations.' COUNT locations checked before writes, '.$guard_cases.' nonempty/malformed guards, '.$guard_exception_cases.' guard read exceptions, '.$empty_integer_cases." integer-zero controls, and failed role-seed transaction status; refused guards issue no writes; fake DB only, no schema or migration-version changes.\n";
