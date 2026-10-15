<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_family_delivery extends CI_Migration
{
	public function up()
	{
		if ($this->db->query('ALTER TABLE st_practice_sessions ADD COLUMN available_from DATETIME NULL') === FALSE)
		{
			throw new RuntimeException('Migrasi jadwal keluarga belum dapat diterapkan.');
		}
		if ($this->db->query('ALTER TABLE st_practice_attempts ADD UNIQUE KEY uq_st_attempt_session_learner (session_id, learner_user_id)') === FALSE) { throw new RuntimeException('Migrasi batas percobaan latihan belum dapat diterapkan.'); }
		return TRUE;
	}

	public function down()
	{
		$query = $this->db->query('SELECT COUNT(*) AS total FROM st_practice_sessions WHERE available_from IS NOT NULL');
		$row = $query === FALSE ? NULL : $query->row_array();
		if ( ! is_array($row) || ! isset($row['total']) || ( ! is_int($row['total']) && ! is_string($row['total'])) || ! preg_match('/\A[0-9]+\z/', (string) $row['total']) || (int) $row['total'] !== 0)
		{
			throw new RuntimeException('Migrasi jadwal tidak dapat dibatalkan selama jadwal tersimpan atau pemeriksaan gagal.');
		}
		if ($this->db->query('ALTER TABLE st_practice_attempts DROP INDEX uq_st_attempt_session_learner') === FALSE) { throw new RuntimeException('Migrasi batas percobaan latihan belum dapat dibatalkan.'); }
		if ($this->db->query('ALTER TABLE st_practice_sessions DROP COLUMN available_from') === FALSE) { throw new RuntimeException('Migrasi jadwal keluarga belum dapat dibatalkan.'); }
		return TRUE;
	}
}
