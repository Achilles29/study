<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_course_practice_snapshots extends CI_Migration
{
	public function up()
	{
		$queries = array(
			"ALTER TABLE st_practice_sessions
				ADD COLUMN course_id BIGINT UNSIGNED NULL,
				ADD COLUMN idempotency_key CHAR(64) NULL,
				ADD COLUMN rules_version VARCHAR(32) NULL,
				ADD UNIQUE KEY uq_st_practice_start (learner_user_id, idempotency_key),
				ADD KEY idx_st_practice_course_learner (course_id, learner_user_id, status),
				ADD CONSTRAINT fk_st_practice_course FOREIGN KEY (course_id) REFERENCES st_courses (id) ON DELETE RESTRICT ON UPDATE CASCADE",
			'ALTER TABLE st_session_questions ADD COLUMN snapshot_json LONGTEXT NULL',
			'ALTER TABLE st_practice_attempts ADD COLUMN answer_revision INT UNSIGNED NOT NULL DEFAULT 0'
		);
		foreach ($queries as $sql)
		{
			if ($this->db->query($sql) === FALSE)
			{
				throw new RuntimeException('Migrasi snapshot latihan belum dapat diterapkan.');
			}
		}
		return TRUE;
	}

	public function down()
	{
		$query = $this->db->query('SELECT COUNT(*) AS total FROM st_practice_sessions WHERE course_id IS NOT NULL');
		$row = $query === FALSE ? NULL : $query->row_array();
		if ( ! is_array($row) || ! isset($row['total']) || ( ! is_int($row['total']) && ! is_string($row['total'])) || ! preg_match('/\A[0-9]+\z/', (string) $row['total']) || (int) $row['total'] !== 0)
		{
			throw new RuntimeException('Migrasi snapshot tidak dapat dibatalkan selama data latihan tersimpan atau pemeriksaan gagal.');
		}
		$queries = array(
			'ALTER TABLE st_practice_attempts DROP COLUMN answer_revision',
			'ALTER TABLE st_session_questions DROP COLUMN snapshot_json',
			'ALTER TABLE st_practice_sessions DROP FOREIGN KEY fk_st_practice_course, DROP INDEX uq_st_practice_start, DROP INDEX idx_st_practice_course_learner, DROP COLUMN course_id, DROP COLUMN idempotency_key, DROP COLUMN rules_version'
		);
		foreach ($queries as $sql)
		{
			if ($this->db->query($sql) === FALSE) { throw new RuntimeException('Migrasi snapshot latihan belum dapat dibatalkan.'); }
		}
		return TRUE;
	}
}
