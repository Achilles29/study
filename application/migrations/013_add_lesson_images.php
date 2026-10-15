<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_lesson_images extends CI_Migration
{
	public function up()
	{
		$created = $this->db->query("CREATE TABLE st_lesson_images (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			lesson_id BIGINT UNSIGNED NOT NULL,
			storage_key VARCHAR(68) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			mime_type VARCHAR(20) NOT NULL,
			byte_size INT UNSIGNED NOT NULL,
			width SMALLINT UNSIGNED NOT NULL,
			height SMALLINT UNSIGNED NOT NULL,
			sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			alt_text VARCHAR(300) NOT NULL,
			caption VARCHAR(1000) NOT NULL,
			source_note VARCHAR(2000) NOT NULL,
			rights_status ENUM('owned','licensed','public_domain') NOT NULL,
			uploader_user_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uq_st_lesson_image_lesson (lesson_id),
			UNIQUE KEY uq_st_lesson_image_storage (storage_key),
			CONSTRAINT fk_st_lesson_image_lesson FOREIGN KEY (lesson_id) REFERENCES st_lessons(id) ON DELETE RESTRICT,
			CONSTRAINT fk_st_lesson_image_uploader FOREIGN KEY (uploader_user_id) REFERENCES st_users(id) ON DELETE RESTRICT
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		// CI3 does not inspect boolean migration return values before advancing its version.
		if ($created === FALSE) { throw new RuntimeException('Migrasi gambar pelajaran belum dapat diterapkan.'); }
		return TRUE;
	}

	public function down()
	{
		$query = $this->db->query('SELECT COUNT(*) AS total FROM st_lesson_images');
		$row = $query === FALSE ? NULL : $query->row_array();
		if ( ! is_array($row) || ! isset($row['total']) ||
			( ! is_int($row['total']) && ! is_string($row['total'])) ||
			! preg_match('/\A[0-9]+\z/', (string) $row['total']) || (int) $row['total'] !== 0)
		{
			throw new RuntimeException('Migrasi gambar tidak dapat dibatalkan karena metadata masih tersimpan atau pemeriksaan data gagal. Buat cadangan dan tinjau data sebelum melanjutkan.');
		}
		if ($this->db->query('DROP TABLE st_lesson_images') === FALSE) { throw new RuntimeException('Migrasi gambar pelajaran belum dapat dibatalkan.'); }
		return TRUE;
	}
}
