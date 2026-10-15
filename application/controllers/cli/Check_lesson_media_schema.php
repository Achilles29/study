<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Check_lesson_media_schema extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		if (PHP_SAPI !== 'cli' || ENVIRONMENT !== 'development') { show_404(); exit(1); }
		$this->load->database();
		if ( ! preg_match('/\Adb_study_media_schema_qa_[a-f0-9]{12}\z/', $this->db->database) || ! in_array($this->db->hostname, array('127.0.0.1', 'localhost'), TRUE)) { throw new RuntimeException('Only a local uniquely named scratch database is permitted.'); }
		$actual = $this->db->query('SELECT DATABASE() AS active_database')->row_array();
		if ($actual['active_database'] !== $this->db->database) { throw new RuntimeException('Active scratch database mismatch.'); }
		$this->db->db_debug = FALSE;
		$this->load->library('migration');
	}

	private function require_true($condition, $message)
	{
		if ( ! $condition) { throw new RuntimeException($message); }
	}

	private function schema_state($version, $tables)
	{
		$row = $this->db->get('study_migrations')->row_array();
		$this->require_true((int) $row['version'] === $version, 'Migration version marker mismatch.');
		$state = $this->db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN engine='InnoDB' AND table_collation LIKE 'utf8mb4%' THEN 0 ELSE 1 END) AS incompatible FROM information_schema.tables WHERE table_schema=? AND table_type='BASE TABLE'", array($this->db->database))->row_array();
		$this->require_true((int) $state['total'] === $tables && (int) $state['incompatible'] === 0, 'Table count/engine/charset mismatch.');
	}

	private function must_fail($sql, $parameters, $code)
	{
		$result = $this->db->query($sql, $parameters);
		$error = $this->db->error();
		$this->require_true($result === FALSE && (int) $error['code'] === $code, 'Expected database constraint did not reject its synthetic statement.');
	}

	private function image_insert($lesson_id, $uploader_id, $key)
	{
		return array($lesson_id, $key, 'image/png', 99, 12, 9, str_repeat('c', 64), 'Synthetic alternative text', 'Synthetic caption', 'Synthetic source note', 'owned', $uploader_id, '2026-10-15 00:00:00', '2026-10-15 00:00:00');
	}

	public function index()
	{
		$this->require_true($this->migration->version(13) !== FALSE, 'Clean migration to version 13 failed.');
		$this->schema_state(13, 33);
		$this->require_true($this->migration->version(13) === TRUE, 'Repeated version 13 should be a no-op.');
		$suffix = substr($this->db->database, -12);
		$prefix = 'qa_media_schema_'.$suffix;
		$users = array();
		foreach (array('author', 'uploader') as $kind)
		{
			$this->require_true($this->db->query("INSERT INTO st_users(username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES(?,'not-a-real-login-hash','Schema QA','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP())", array($prefix.'_'.$kind)) !== FALSE, 'Synthetic account creation failed.');
			$users[$kind] = (int) $this->db->insert_id();
		}
		$this->require_true($this->db->query("INSERT INTO st_courses(author_user_id,title,slug,status,created_at,updated_at) VALUES(?,'Schema course',?,'draft',UTC_TIMESTAMP(),UTC_TIMESTAMP())", array($users['author'], 'qa-media-schema-'.$suffix)) !== FALSE, 'Synthetic course creation failed.');
		$course_id = (int) $this->db->insert_id();
		$this->require_true($this->db->query("INSERT INTO st_course_sections(course_id,title,sort_order,created_at,updated_at) VALUES(?,'Schema section',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())", array($course_id)) !== FALSE, 'Synthetic section creation failed.');
		$section_id = (int) $this->db->insert_id();
		$lessons = array();
		foreach (array('first', 'second') as $label)
		{
			$this->require_true($this->db->query("INSERT INTO st_lessons(section_id,title,slug,body_text,created_at,updated_at) VALUES(?,'Schema lesson',?,'Synthetic schema fixture.',UTC_TIMESTAMP(),UTC_TIMESTAMP())", array($section_id, 'qa-media-schema-'.$suffix.'-'.$label)) !== FALSE, 'Synthetic lesson creation failed.');
			$lessons[] = (int) $this->db->insert_id();
		}
		$insert = 'INSERT INTO st_lesson_images(lesson_id,storage_key,mime_type,byte_size,width,height,sha256,alt_text,caption,source_note,rights_status,uploader_user_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
		$this->require_true($this->db->query($insert, $this->image_insert($lessons[0], $users['uploader'], str_repeat('a', 64).'.png')) !== FALSE, 'Synthetic image metadata creation failed.');
		$image_id = (int) $this->db->insert_id();
		$original_image = $this->db->where('id', $image_id)->get('st_lesson_images')->row_array();
		$this->must_fail($insert, $this->image_insert($lessons[0], $users['uploader'], str_repeat('b', 64).'.png'), 1062);
		$this->must_fail($insert, $this->image_insert($lessons[1], $users['uploader'], str_repeat('a', 64).'.png'), 1062);
		$this->must_fail($insert, $this->image_insert($lessons[1] + 1000000, $users['uploader'], str_repeat('b', 64).'.png'), 1452);
		$this->must_fail($insert, $this->image_insert($lessons[1], $users['uploader'] + 1000000, str_repeat('b', 64).'.png'), 1452);
		$this->must_fail('DELETE FROM st_lessons WHERE id=?', array($lessons[0]), 1451);
		$this->must_fail('DELETE FROM st_users WHERE id=?', array($users['uploader']), 1451);
		$this->require_true($this->db->count_all_results('st_lesson_images') === 1 && $this->db->where('id', $image_id)->get('st_lesson_images')->row_array() === $original_image, 'Rejected statements changed image metadata.');
		fwrite(STDOUT, "PASS v13 unique lesson/storage keys, lesson/uploader foreign keys and delete-restrict preserve exact metadata.\n");

		$refused = FALSE;
		try { $this->migration->version(12); }
		catch (RuntimeException $error) { $refused = TRUE; }
		$this->require_true($refused, 'Nonempty downgrade must stop the CI migration runner, not merely return FALSE.');
		$this->schema_state(13, 33);
		$this->require_true($this->db->where('id', $image_id)->get('st_lesson_images')->row_array() === $original_image && $this->db->count_all_results('st_lesson_images') === 1, 'Refused downgrade changed or lost metadata.');
		fwrite(STDOUT, "PASS CI migration->version(12) refuses nonempty images; version remains 13, all 33 tables and metadata unchanged.\n");

		// A CREATE failure must also throw rather than let CI3 silently advance a marker.
		$duplicate_create_refused = FALSE;
		try { $step = new Migration_Add_lesson_images(); $step->up(); }
		catch (RuntimeException $error) { $duplicate_create_refused = TRUE; }
		$this->require_true($duplicate_create_refused, 'Failed migration CREATE must throw.');
		$this->schema_state(13, 33);
		$this->require_true($this->db->where('id', $image_id)->get('st_lesson_images')->row_array() === $original_image, 'Failed duplicate CREATE changed metadata.');
		$this->require_true($this->db->where('id', $image_id)->delete('st_lesson_images') !== FALSE, 'Exact synthetic image cleanup failed.');
		$this->require_true($this->db->where('section_id', $section_id)->where_in('id', $lessons)->delete('st_lessons') !== FALSE, 'Exact synthetic lesson cleanup failed.');
		$this->require_true($this->db->where('id', $section_id)->where('course_id', $course_id)->delete('st_course_sections') !== FALSE, 'Exact synthetic section cleanup failed.');
		$this->require_true($this->db->where('id', $course_id)->where('slug', 'qa-media-schema-'.$suffix)->delete('st_courses') !== FALSE, 'Exact synthetic course cleanup failed.');
		$this->require_true($this->db->where_in('id', array_values($users))->where_in('username', array($prefix.'_author', $prefix.'_uploader'))->delete('st_users') !== FALSE, 'Exact synthetic user cleanup failed.');
		$this->require_true($this->db->count_all_results('st_lesson_images') === 0 && $this->db->count_all_results('st_lessons') === 0 && $this->db->count_all_results('st_users') === 0, 'Synthetic rows remain before downgrade.');
		$this->require_true($this->migration->version(12) !== FALSE, 'Empty migration downgrade failed.');
		$this->schema_state(12, 32);
		$absent = $this->db->query("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema=? AND table_name='st_lesson_images'", array($this->db->database))->row_array();
		$this->require_true((int) $absent['total'] === 0, 'Version 12 still contains the image table.');
		$this->require_true($this->migration->version(12) === TRUE, 'Repeated empty downgrade must be a no-op.');
		$this->require_true($this->migration->version(13) !== FALSE && $this->migration->version(13) === TRUE, 'Re-upgrade/repeat failed.');
		$this->schema_state(13, 33);
		$this->require_true($this->db->count_all_results('st_lesson_images') === 0, 'Re-upgraded image table must be empty.');
		fwrite(STDOUT, "PASS failed CREATE stops safely; exact fixtures removed; empty v13->v12 (32 tables)->v13 (33 tables) and repeated versions pass.\n");
	}
}
