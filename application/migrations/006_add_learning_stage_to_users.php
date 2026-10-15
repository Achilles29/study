<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_learning_stage_to_users extends CI_Migration
{
	public function up()
	{
		$sql = "ALTER TABLE `st_users`
			ADD COLUMN `learning_stage` ENUM('early_childhood','primary','secondary','adult','unset') NOT NULL DEFAULT 'unset' AFTER `account_kind`";
		if ($this->db->query($sql) === FALSE)
		{
			throw new RuntimeException('Migrasi tahap belajar belum dapat diterapkan.');
		}

		$this->db->where('account_kind', 'adult');
		if ($this->db->update('st_users', array('learning_stage' => 'adult')) === FALSE) { throw new RuntimeException('Tahap belajar awal belum dapat disimpan. Migrasi dihentikan.'); }
		return TRUE;
	}

	public function down()
	{
		$query = $this->db->query('SELECT COUNT(*) AS total FROM `st_users`');
		$row = $query === FALSE ? NULL : $query->row_array();
		if ( ! is_array($row) || ! isset($row['total']) ||
			( ! is_int($row['total']) && ! is_string($row['total'])) ||
			! preg_match('/\A[0-9]+\z/', (string) $row['total']) || (int) $row['total'] !== 0)
		{
			throw new RuntimeException('Migrasi tahap belajar tidak dapat dibatalkan karena data masih tersimpan atau pemeriksaan data gagal. Buat cadangan dan tinjau data sebelum melanjutkan.');
		}
		if ($this->db->query('ALTER TABLE `st_users` DROP COLUMN `learning_stage`') === FALSE) { throw new RuntimeException('Migrasi tahap belajar belum dapat dibatalkan.'); }
		return TRUE;
	}
}
