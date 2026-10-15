<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Check_legacy_migration_guards extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		if (PHP_SAPI !== 'cli' || ENVIRONMENT !== 'development') { show_404(); exit(1); }
		$this->load->database();
		if ( ! preg_match('/\Adb_study_legacy_migration_qa_[a-f0-9]{12}\z/', $this->db->database) || ! in_array($this->db->hostname, array('127.0.0.1', 'localhost'), TRUE)) { throw new RuntimeException('Only a uniquely named local scratch database is permitted.'); }
		$actual = $this->db->query('SELECT DATABASE() AS active_database')->row_array();
		if ($actual['active_database'] !== $this->db->database) { throw new RuntimeException('Active scratch database mismatch.'); }
		$this->db->db_debug = FALSE;
		$this->load->library('migration');
	}

	private function require_true($condition, $message)
	{
		if ( ! $condition) { throw new RuntimeException($message); }
	}

	private function rows($sql, $bindings = array())
	{
		$query = $this->db->query($sql, $bindings);
		$this->require_true($query !== FALSE, 'Scratch inspection query failed.');
		return $query->result_array();
	}

	private function schema_digest()
	{
		$inventory = $this->rows("SELECT TABLE_NAME AS table_name FROM information_schema.tables WHERE table_schema=? AND table_type='BASE TABLE' ORDER BY TABLE_NAME", array($this->db->database));
		$digest = array();
		foreach ($inventory as $item)
		{
			$table = $item['table_name'];
			$this->require_true((bool) preg_match('/\A(?:st_[a-z_]+|study_migrations)\z/', $table), 'Unexpected scratch table identifier.');
			$rows = $this->rows('SELECT * FROM `'.$table.'`');
			$encoded = array();
			foreach ($rows as $row)
			{
				ksort($row, SORT_STRING);
				$encoded[] = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
			}
			sort($encoded, SORT_STRING);
			$structure = $this->rows('SHOW CREATE TABLE `'.$table.'`');
			$this->require_true(count($structure) === 1 && isset($structure[0]['Create Table']), 'Scratch table structure is unavailable.');
			$digest[$table] = array('rows' => count($rows), 'data' => hash('sha256', json_encode($encoded, JSON_THROW_ON_ERROR)), 'ddl' => hash('sha256', $structure[0]['Create Table']));
		}
		return $digest;
	}

	private function seed_early_guard($version)
	{
		$suffix = substr($this->db->database, -12);
		if ($version === 3)
		{
			$this->require_true($this->db->count_all_results('st_roles') === 4, 'Four canonical role seeds must already protect this rollback.');
			return;
		}
		if ($version === 2)
		{
			$this->require_true($this->db->query('INSERT INTO st_login_failures(identity_hash,address_hash,attempted_at) VALUES(?,?,UTC_TIMESTAMP())', array(str_repeat('a', 64), str_repeat('b', 64))) !== FALSE, 'Synthetic throttle row failed.');
			return;
		}
		$this->require_true($this->db->query("INSERT INTO st_users(username,password_hash,display_name,account_kind,status,created_at,updated_at) VALUES(?,'not-a-real-login-hash','Rollback guard QA','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP())", array('qa_early_guard_'.$suffix)) !== FALSE, 'Synthetic early-guard user failed.');
		$user_id = (int) $this->db->insert_id();
		if ($version === 1 || $version === 6) { return; }
		$this->require_true($this->db->query("INSERT INTO st_courses(author_user_id,title,slug,status,created_at,updated_at) VALUES(?,'Synthetic rollback course',?,'draft',UTC_TIMESTAMP(),UTC_TIMESTAMP())", array($user_id, 'qa-early-guard-'.$suffix)) !== FALSE, 'Synthetic early-guard course failed.');
		$course_id = (int) $this->db->insert_id();
		if ($version === 4 || $version === 8)
		{
			$field = $version === 8 ? 'prerequisites' : 'source_note';
			$this->require_true($this->db->where('id', $course_id)->update('st_courses', array($field => 'Synthetic retained content & Unicode: belajar.')) !== FALSE, 'Synthetic course metadata failed.');
			return;
		}
		$this->require_true($this->db->query("INSERT INTO st_course_sections(course_id,title,sort_order,created_at,updated_at) VALUES(?,'Synthetic rollback section',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())", array($course_id)) !== FALSE, 'Synthetic early-guard section failed.');
		$section_id = (int) $this->db->insert_id();
		$this->require_true($this->db->query("INSERT INTO st_lessons(section_id,title,slug,body_text,rights_status,source_note,created_at,updated_at) VALUES(?,'Synthetic rollback lesson',?,'Synthetic retained lesson body.','owned','Synthetic owned source',UTC_TIMESTAMP(),UTC_TIMESTAMP())", array($section_id, 'qa-early-guard-'.$suffix.'-lesson')) !== FALSE, 'Synthetic early-guard lesson failed.');
		$lesson_id = (int) $this->db->insert_id();
		if ($version === 5) { return; }
		if ($version === 7)
		{
			$this->require_true($this->db->query("INSERT INTO st_flashcard_decks(lesson_id,title,created_by_user_id,created_at,updated_at) VALUES(?,'Synthetic retained deck',?,UTC_TIMESTAMP(),UTC_TIMESTAMP())", array($lesson_id, $user_id)) !== FALSE, 'Synthetic deck failed.');
			return;
		}
		// Metadata only in the scratch schema; no media store/files are touched.
		$this->require_true($version === 13 && $this->db->query("INSERT INTO st_lesson_images(lesson_id,storage_key,mime_type,byte_size,width,height,sha256,alt_text,caption,source_note,rights_status,uploader_user_id,created_at,updated_at) VALUES(?,?,'image/png',1,1,1,?,'Synthetic image','Synthetic caption','Synthetic owned source','owned',?,UTC_TIMESTAMP(),UTC_TIMESTAMP())", array($lesson_id, str_repeat('c', 64).'.png', str_repeat('d', 64), $user_id)) !== FALSE, 'Synthetic image metadata failed.');
	}

	public function early($version = '', $mode = 'nonempty')
	{
		$this->require_true(is_string($version) && in_array($version, array('1','2','3','4','5','6','7','8','13'), TRUE), 'Unsupported one-step scratch migration version.');
		$this->require_true(in_array($mode, array('nonempty', 'empty'), TRUE), 'Unsupported scratch migration mode.');
		$version = (int) $version;
		$initial = $this->schema_digest();
		$marker = $this->rows('SELECT version FROM study_migrations');
		$this->require_true(array_keys($initial) === array('study_migrations') && count($marker) === 1 && (int) $marker[0]['version'] === 0, 'A fresh scratch schema is required.');
		if ($mode === 'empty')
		{
			$this->require_true($version !== 3, 'Canonical role seeds must not be erased to force a clean downgrade.');
			$this->require_true($this->migration->version($version - 1) !== FALSE, 'Fresh empty baseline migration failed.');
			$baseline = $this->schema_digest();
			$this->require_true($this->migration->version($version) !== FALSE, 'Empty target migration failed.');
			$this->require_true($this->migration->version($version - 1) !== FALSE && $this->schema_digest() === $baseline, 'Empty one-step downgrade did not restore exact baseline schema/data/version.');
			fwrite(STDOUT, 'PASS empty one-step '.$version.'->'.($version - 1).' restores all '.count($baseline)." baseline table data/DDL digests and version. Only isolated scratch DDL executed.\n");
			return;
		}
		$this->require_true($this->migration->version($version) !== FALSE, 'Fresh target migration failed.');
		$this->seed_early_guard($version);
		$before = $this->schema_digest();
		$refused = FALSE;
		try { $this->migration->version($version - 1); }
		catch (RuntimeException $error) { $refused = TRUE; }
		$this->require_true($refused && $this->schema_digest() === $before, 'One-step refusal did not preserve every table, row, definition and migration marker.');
		fwrite(STDOUT, 'PASS one-step '.$version.'->'.($version - 1).' refused before destructive change; all '.count($before)." table data/DDL digests and version preserved. Scratch fixture remains for exact outer cleanup.\n");
	}

	public function index()
	{
		$this->require_true($this->migration->version(12) !== FALSE, 'Fresh legacy migrations failed.');
		$suffix = substr($this->db->database, -12);
		$username = 'qa_legacy_guard_'.$suffix;
		$slug = 'qa-legacy-guard-'.$suffix;
		$this->require_true($this->db->query("INSERT INTO st_users(username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES(?,'not-a-real-login-hash','Legacy guard QA','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP())", array($username)) !== FALSE, 'Synthetic user failed.');
		$user_id = (int) $this->db->insert_id();
		$this->require_true($this->db->query("INSERT INTO st_courses(author_user_id,title,slug,status,created_at,updated_at) VALUES(?,'Legacy guard course',?,'draft',UTC_TIMESTAMP(),UTC_TIMESTAMP())", array($user_id, $slug)) !== FALSE, 'Synthetic course failed.');
		$course_id = (int) $this->db->insert_id();
		$this->require_true($this->db->query("INSERT INTO st_course_sections(course_id,title,sort_order,created_at,updated_at) VALUES(?,'Legacy guard section',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())", array($course_id)) !== FALSE, 'Synthetic section failed.');
		$section_id = (int) $this->db->insert_id();
		$this->require_true($this->db->query("INSERT INTO st_lessons(section_id,title,slug,body_text,created_at,updated_at) VALUES(?,'Legacy guard lesson',?,'Synthetic migration fixture.',UTC_TIMESTAMP(),UTC_TIMESTAMP())", array($section_id, $slug.'-lesson')) !== FALSE, 'Synthetic lesson failed.');
		$lesson_id = (int) $this->db->insert_id();
		$this->require_true($this->db->query("INSERT INTO st_matching_runs(learner_user_id,lesson_id,title_snapshot,rules_version,snapshot_json,state_json,status,revision,active_key,start_key,started_at) VALUES(?,?,'Synthetic run','qa-rules','{}','{}','in_progress',0,?,?,UTC_TIMESTAMP())", array($user_id, $lesson_id, 'qa-'.$suffix, str_repeat('a', 64))) !== FALSE, 'Synthetic matching run failed.');
		$run_id = (int) $this->db->insert_id();
		$before = $this->db->where('id', $run_id)->get('st_matching_runs')->row_array();
		$refused = FALSE;
		try { $this->migration->version(11); }
		catch (RuntimeException $error) { $refused = TRUE; }
		$version = $this->db->get('study_migrations')->row_array();
		$tables = $this->db->query("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema=? AND table_type='BASE TABLE'", array($this->db->database))->row_array();
		$this->require_true($refused && (int) $version['version'] === 12 && (int) $tables['total'] === 32 && $this->db->where('id', $run_id)->get('st_matching_runs')->row_array() === $before, 'Nonempty matching downgrade did not preserve version/tables/exact data.');
		$this->require_true($this->db->count_all_results('st_matching_runs') === 1 && $this->db->count_all_results('st_roles') === 4 && $this->migration->version(12) === TRUE, 'Preserved matching row, four role seeds or repeatability mismatch.');
		fwrite(STDOUT, "PASS fresh legacy migrations to 12 with four roles; real CI downgrade 12->11 refuses nonempty matching history and preserves version 12, 32 tables and exact run data. This history case executes no successful downgrade.\n");
		$this->require_true($this->db->where('id', $run_id)->where('learner_user_id', $user_id)->delete('st_matching_runs') !== FALSE, 'Exact run cleanup failed.');
		$this->require_true($this->db->where('id', $lesson_id)->where('slug', $slug.'-lesson')->delete('st_lessons') !== FALSE, 'Exact lesson cleanup failed.');
		$this->require_true($this->db->where('id', $section_id)->where('course_id', $course_id)->delete('st_course_sections') !== FALSE, 'Exact section cleanup failed.');
		$this->require_true($this->db->where('id', $course_id)->where('slug', $slug)->delete('st_courses') !== FALSE, 'Exact course cleanup failed.');
		$this->require_true($this->db->where('id', $user_id)->where('username', $username)->delete('st_users') !== FALSE, 'Exact user cleanup failed.');
		$this->require_true($this->db->count_all_results('st_matching_runs') === 0 && $this->db->count_all_results('st_users') === 0 && $this->db->count_all_results('st_lessons') === 0, 'Synthetic scratch fixture cleanup was incomplete.');
		fwrite(STDOUT, "PASS exact legacy guard fixtures removed; scratch schema remains version 12 for outer cleanup.\n");
	}
}
