<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_course_prerequisites extends CI_Migration
{
	public function up()
	{
		if ($this->db->query("ALTER TABLE `st_courses` ADD COLUMN `prerequisites` TEXT NULL AFTER `learning_objectives`") === FALSE)
		{
			throw new RuntimeException('Migrasi persiapan kursus belum dapat diterapkan.');
		}

		return TRUE;
	}

	public function down()
	{
		$query = $this->db->query('SELECT COUNT(*) AS total FROM `st_courses`');
		$row = $query === FALSE ? NULL : $query->row_array();
		if ( ! is_array($row) || ! isset($row['total']) ||
			( ! is_int($row['total']) && ! is_string($row['total'])) ||
			! preg_match('/\A[0-9]+\z/', (string) $row['total']) || (int) $row['total'] !== 0)
		{
			throw new RuntimeException('Migrasi persiapan kursus tidak dapat dibatalkan karena data masih tersimpan atau pemeriksaan data gagal. Buat cadangan dan tinjau data sebelum melanjutkan.');
		}
		if ($this->db->query("ALTER TABLE `st_courses` DROP COLUMN `prerequisites`") === FALSE)
		{
			throw new RuntimeException('Migrasi persiapan kursus belum dapat dibatalkan.');
		}

		return TRUE;
	}
}
