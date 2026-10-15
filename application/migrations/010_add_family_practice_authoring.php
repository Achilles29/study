<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_family_practice_authoring extends CI_Migration
{
	public function up()
	{
		if ($this->db->query('ALTER TABLE st_packages ADD COLUMN family_practice_enabled TINYINT(1) UNSIGNED NOT NULL DEFAULT 0') === FALSE)
		{
			throw new RuntimeException('Migrasi latihan keluarga belum dapat diterapkan.');
		}
		if ($this->db->query('ALTER TABLE st_practice_sessions ADD COLUMN draft_revision INT UNSIGNED NOT NULL DEFAULT 0') === FALSE) { throw new RuntimeException('Migrasi revisi latihan keluarga belum dapat diterapkan.'); }
		return TRUE;
	}

	public function down()
	{
		foreach (array("SELECT COUNT(*) AS total FROM st_practice_sessions WHERE session_type = 'parent_assigned'", 'SELECT COUNT(*) AS total FROM st_packages WHERE family_practice_enabled = 1') as $sql)
		{
			$query = $this->db->query($sql);
			$row = $query === FALSE ? NULL : $query->row_array();
			if ( ! is_array($row) || ! isset($row['total']) || ( ! is_int($row['total']) && ! is_string($row['total'])) || ! preg_match('/\A[0-9]+\z/', (string) $row['total']) || (int) $row['total'] !== 0)
			{
				throw new RuntimeException('Migrasi latihan keluarga tidak dapat dibatalkan selama data aktif tersimpan atau pemeriksaan gagal.');
			}
		}
		if ($this->db->query('ALTER TABLE st_practice_sessions DROP COLUMN draft_revision') === FALSE) { throw new RuntimeException('Migrasi revisi latihan keluarga belum dapat dibatalkan.'); }
		if ($this->db->query('ALTER TABLE st_packages DROP COLUMN family_practice_enabled') === FALSE) { throw new RuntimeException('Migrasi kapabilitas keluarga belum dapat dibatalkan.'); }
		return TRUE;
	}
}
