<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_matching_history extends CI_Migration
{
	public function up()
	{
		$sql = "CREATE TABLE st_matching_runs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			learner_user_id BIGINT UNSIGNED NOT NULL,
			lesson_id BIGINT UNSIGNED NOT NULL,
			title_snapshot VARCHAR(180) NOT NULL,
			rules_version VARCHAR(40) NOT NULL,
			snapshot_json MEDIUMTEXT NOT NULL,
			state_json MEDIUMTEXT NOT NULL,
			status ENUM('in_progress','completed','abandoned') NOT NULL DEFAULT 'in_progress',
			revision INT UNSIGNED NOT NULL DEFAULT 0,
			active_key VARCHAR(100) NULL,
			start_key CHAR(64) NOT NULL,
			started_at DATETIME NOT NULL,
			finished_at DATETIME NULL,
			elapsed_seconds INT UNSIGNED NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_st_matching_active (active_key),
			UNIQUE KEY uq_st_matching_start (learner_user_id, start_key),
			KEY idx_st_matching_history (learner_user_id, id),
			CONSTRAINT fk_st_matching_user FOREIGN KEY (learner_user_id) REFERENCES st_users(id) ON DELETE RESTRICT,
			CONSTRAINT fk_st_matching_lesson FOREIGN KEY (lesson_id) REFERENCES st_lessons(id) ON DELETE RESTRICT
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
		if ($this->db->query($sql) === FALSE)
		{
			throw new RuntimeException('Migrasi histori permainan belum dapat diterapkan.');
		}
		$created = $this->db->query("CREATE TABLE st_matching_moves (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			run_id BIGINT UNSIGNED NOT NULL,
			request_key CHAR(64) NOT NULL,
			expected_revision INT UNSIGNED NOT NULL,
			left_index SMALLINT UNSIGNED NOT NULL,
			right_index SMALLINT UNSIGNED NOT NULL,
			outcome ENUM('matched','mismatch') NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_st_matching_move (run_id, request_key),
			CONSTRAINT fk_st_matching_move_run FOREIGN KEY (run_id) REFERENCES st_matching_runs(id) ON DELETE RESTRICT
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		if ($created === FALSE) { throw new RuntimeException('Migrasi langkah permainan belum dapat diterapkan.'); }
		return TRUE;
	}

	public function down()
	{
		foreach (array('st_matching_runs', 'st_matching_moves') as $table)
		{
			$query = $this->db->query('SELECT COUNT(*) AS total FROM '.$table);
			$row = $query === FALSE ? NULL : $query->row_array();
			if ( ! is_array($row) || ! isset($row['total']) || ( ! is_int($row['total']) && ! is_string($row['total'])) || ! preg_match('/\A[0-9]+\z/', (string) $row['total']) || (int) $row['total'] !== 0)
			{
				throw new RuntimeException('Migrasi histori permainan tidak dapat dibatalkan selama histori tersimpan atau pemeriksaan gagal.');
			}
		}
		if ($this->db->query('DROP TABLE st_matching_moves') === FALSE) { throw new RuntimeException('Migrasi langkah permainan belum dapat dibatalkan.'); }
		if ($this->db->query('DROP TABLE st_matching_runs') === FALSE) { throw new RuntimeException('Migrasi histori permainan belum dapat dibatalkan.'); }
		return TRUE;
	}
}
