<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_login_throttling extends CI_Migration
{
	public function up()
	{
		$created = $this->db->query("CREATE TABLE `st_login_failures` (
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`identity_hash` CHAR(64) NOT NULL,
			`address_hash` CHAR(64) NOT NULL,
			`attempted_at` DATETIME NOT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_st_login_failures_identity_time` (`identity_hash`,`attempted_at`),
			KEY `idx_st_login_failures_address_time` (`address_hash`,`attempted_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		if ($created === FALSE) { throw new RuntimeException('Migrasi pembatasan masuk belum dapat diterapkan.'); }
		return TRUE;
	}

	public function down()
	{
		$query = $this->db->query('SELECT COUNT(*) AS total FROM `st_login_failures`');
		$row = $query === FALSE ? NULL : $query->row_array();
		if ( ! is_array($row) || ! isset($row['total']) ||
			( ! is_int($row['total']) && ! is_string($row['total'])) ||
			! preg_match('/\A[0-9]+\z/', (string) $row['total']) || (int) $row['total'] !== 0)
		{
			throw new RuntimeException('Migrasi pembatasan masuk tidak dapat dibatalkan karena data masih tersimpan atau pemeriksaan data gagal. Buat cadangan dan tinjau data sebelum melanjutkan.');
		}
		if ($this->db->query('DROP TABLE IF EXISTS `st_login_failures`') === FALSE) { throw new RuntimeException('Migrasi pembatasan masuk belum dapat dibatalkan.'); }
		return TRUE;
	}
}
