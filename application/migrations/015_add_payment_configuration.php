<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_payment_configuration extends CI_Migration
{
	public function up()
	{
		if ($this->db->query("CREATE TABLE st_payment_configs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			environment ENUM('sandbox') NOT NULL DEFAULT 'sandbox',
			merchant_id VARCHAR(80) NOT NULL,
			credentials_cipher TEXT NOT NULL,
			created_by_user_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			probe_attempted_at DATETIME NULL,
			probe_result VARCHAR(40) NULL,
			CONSTRAINT fk_st_payment_config_actor FOREIGN KEY (created_by_user_id) REFERENCES st_users(id) ON DELETE RESTRICT
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci") === FALSE)
		{
			throw new RuntimeException('Konfigurasi pembayaran belum dapat dibuat.');
		}
	}

	public function down()
	{
		throw new RuntimeException('Versi konfigurasi pembayaran tidak boleh dihapus otomatis.');
	}
}
