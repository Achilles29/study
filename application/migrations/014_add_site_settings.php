<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_site_settings extends CI_Migration
{
	public function up()
	{
		if ($this->db->query("CREATE TABLE st_site_settings (
			id SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
			section VARCHAR(40) NOT NULL UNIQUE,
			values_json TEXT NOT NULL,
			revision INT UNSIGNED NOT NULL DEFAULT 1,
			updated_by_user_id BIGINT UNSIGNED NULL,
			updated_at DATETIME NOT NULL,
			CONSTRAINT fk_st_settings_actor FOREIGN KEY (updated_by_user_id) REFERENCES st_users(id) ON DELETE RESTRICT
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci") === FALSE)
		{
			throw new RuntimeException('Tabel pengaturan belum dapat dibuat.');
		}
		foreach (array(1 => 'umum', 2 => 'pembelajaran') as $id => $section)
		{
			if ( ! $this->db->insert('st_site_settings', array('id' => $id, 'section' => $section, 'values_json' => '{}', 'revision' => 1, 'updated_at' => gmdate('Y-m-d H:i:s'))))
			{
				throw new RuntimeException('Pengaturan awal belum dapat disimpan; tinjau sebelum mencoba kembali.');
			}
		}
	}

	public function down()
	{
		throw new RuntimeException('Pengaturan tidak dihapus otomatis. Gunakan prosedur cadangan yang disetujui.');
	}
}
