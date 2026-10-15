<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_roles_and_course_access extends CI_Migration
{
	public function up()
	{
		$queries = array(
			"CREATE TABLE `st_roles` (
				`id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`role_code` VARCHAR(40) NOT NULL,
				`role_name` VARCHAR(100) NOT NULL,
				`created_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_st_roles_code` (`role_code`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_user_roles` (
				`user_id` BIGINT UNSIGNED NOT NULL,
				`role_id` SMALLINT UNSIGNED NOT NULL,
				`granted_by_user_id` BIGINT UNSIGNED NULL,
				`created_at` DATETIME NOT NULL,
				PRIMARY KEY (`user_id`,`role_id`),
				KEY `idx_st_user_roles_role` (`role_id`,`user_id`),
				CONSTRAINT `fk_st_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `st_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `st_roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_user_roles_granter` FOREIGN KEY (`granted_by_user_id`) REFERENCES `st_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_course_enrollments` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`learner_user_id` BIGINT UNSIGNED NOT NULL,
				`course_id` BIGINT UNSIGNED NOT NULL,
				`source_type` ENUM('free','package','manual') NOT NULL,
				`package_id` BIGINT UNSIGNED NULL,
				`entitlement_id` BIGINT UNSIGNED NULL,
				`status` ENUM('active','revoked','completed') NOT NULL DEFAULT 'active',
				`enrolled_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_st_enrollments_learner_course` (`learner_user_id`,`course_id`),
				KEY `idx_st_enrollments_status` (`status`,`enrolled_at`),
				CONSTRAINT `fk_st_enrollments_learner` FOREIGN KEY (`learner_user_id`) REFERENCES `st_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_enrollments_course` FOREIGN KEY (`course_id`) REFERENCES `st_courses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_enrollments_package` FOREIGN KEY (`package_id`) REFERENCES `st_packages` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_enrollments_entitlement` FOREIGN KEY (`entitlement_id`) REFERENCES `st_entitlements` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_content_reviews` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`course_id` BIGINT UNSIGNED NOT NULL,
				`reviewer_user_id` BIGINT UNSIGNED NOT NULL,
				`decision` ENUM('approved','changes_requested','rejected') NOT NULL,
				`notes` TEXT NULL,
				`created_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				KEY `idx_st_content_reviews_course_time` (`course_id`,`created_at`),
				CONSTRAINT `fk_st_content_reviews_course` FOREIGN KEY (`course_id`) REFERENCES `st_courses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_content_reviews_reviewer` FOREIGN KEY (`reviewer_user_id`) REFERENCES `st_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_account_tokens` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`user_id` BIGINT UNSIGNED NOT NULL,
				`token_type` ENUM('email_verification','password_reset') NOT NULL,
				`token_hash` CHAR(64) NOT NULL,
				`expires_at` DATETIME NOT NULL,
				`used_at` DATETIME NULL,
				`created_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_st_account_tokens_hash` (`token_hash`),
				KEY `idx_st_account_tokens_user_type` (`user_id`,`token_type`,`expires_at`),
				CONSTRAINT `fk_st_account_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `st_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);

		foreach ($queries as $sql)
		{
			if ($this->db->query($sql) === FALSE)
			{
				throw new RuntimeException('Migrasi peran dan akses belum dapat diterapkan.');
			}
		}

		$now = gmdate('Y-m-d H:i:s');
		$roles = array(
			array('role_code' => 'system_admin', 'role_name' => 'Administrator sistem', 'created_at' => $now),
			array('role_code' => 'content_editor', 'role_name' => 'Pengelola konten', 'created_at' => $now),
			array('role_code' => 'finance_operator', 'role_name' => 'Petugas transaksi', 'created_at' => $now),
			array('role_code' => 'support_agent', 'role_name' => 'Petugas dukungan', 'created_at' => $now)
		);

		foreach ($roles as $role)
		{
			if ($this->db->insert('st_roles', $role) === FALSE) { throw new RuntimeException('Peran awal belum dapat disimpan. Migrasi dihentikan.'); }
		}

		if ($this->db->trans_status() === FALSE) { throw new RuntimeException('Migrasi peran dan akses belum dapat dipastikan berhasil.'); }
		return TRUE;
	}

	public function down()
	{
		$tables = array('st_account_tokens','st_content_reviews','st_course_enrollments','st_user_roles','st_roles');
		foreach ($tables as $table)
		{
			$query = $this->db->query('SELECT COUNT(*) AS total FROM `'.$table.'`');
			$row = $query === FALSE ? NULL : $query->row_array();
			if ( ! is_array($row) || ! isset($row['total']) ||
				( ! is_int($row['total']) && ! is_string($row['total'])) ||
				! preg_match('/\A[0-9]+\z/', (string) $row['total']) || (int) $row['total'] !== 0)
			{
				throw new RuntimeException('Migrasi peran dan akses tidak dapat dibatalkan karena data masih tersimpan atau pemeriksaan data gagal. Buat cadangan dan tinjau data sebelum melanjutkan.');
			}
		}
		foreach ($tables as $table)
		{
			if ($this->db->query('DROP TABLE IF EXISTS `'.$table.'`') === FALSE) { throw new RuntimeException('Migrasi peran dan akses belum dapat dibatalkan.'); }
		}
		return TRUE;
	}
}
