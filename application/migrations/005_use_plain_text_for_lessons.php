<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Use_plain_text_for_lessons extends CI_Migration
{
	public function up()
	{
		if ($this->db->query('ALTER TABLE `st_lessons` CHANGE COLUMN `body_html` `body_text` MEDIUMTEXT NULL') === FALSE)
		{
			throw new RuntimeException('Migrasi teks pelajaran belum dapat diterapkan.');
		}

		if ($this->db->query("ALTER TABLE `st_lessons`
			ADD COLUMN `rights_status` ENUM('pending_review','owned','licensed','public_domain') NOT NULL DEFAULT 'pending_review' AFTER `source_note`") === FALSE) { throw new RuntimeException('Migrasi hak pelajaran belum dapat diterapkan.'); }
		return TRUE;
	}

	public function down()
	{
		$query = $this->db->query('SELECT COUNT(*) AS total FROM `st_lessons`');
		$row = $query === FALSE ? NULL : $query->row_array();
		if ( ! is_array($row) || ! isset($row['total']) ||
			( ! is_int($row['total']) && ! is_string($row['total'])) ||
			! preg_match('/\A[0-9]+\z/', (string) $row['total']) || (int) $row['total'] !== 0)
		{
			throw new RuntimeException('Migrasi teks pelajaran tidak dapat dibatalkan karena data masih tersimpan atau pemeriksaan data gagal. Buat cadangan dan tinjau data sebelum melanjutkan.');
		}
		if ($this->db->query('ALTER TABLE `st_lessons` DROP COLUMN `rights_status`') === FALSE) { throw new RuntimeException('Migrasi hak pelajaran belum dapat dibatalkan.'); }
		if ($this->db->query('ALTER TABLE `st_lessons` CHANGE COLUMN `body_text` `body_html` MEDIUMTEXT NULL') === FALSE) { throw new RuntimeException('Migrasi teks pelajaran belum dapat dibatalkan.'); }
		return TRUE;
	}
}
