<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_content_review_metadata extends CI_Migration
{
	public function up()
	{
		$queries = array(
			"ALTER TABLE `st_courses`
				ADD COLUMN `learning_objectives` TEXT NULL AFTER `summary`,
				ADD COLUMN `age_min` TINYINT UNSIGNED NULL AFTER `target_group`,
				ADD COLUMN `age_max` TINYINT UNSIGNED NULL AFTER `age_min`,
				ADD COLUMN `estimated_minutes` SMALLINT UNSIGNED NULL AFTER `age_max`,
				ADD COLUMN `content_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `status`,
				ADD COLUMN `rights_status` ENUM('pending_review','owned','licensed','public_domain') NOT NULL DEFAULT 'pending_review' AFTER `content_version`,
				ADD COLUMN `source_note` TEXT NULL AFTER `rights_status`,
				ADD COLUMN `is_free` TINYINT(1) NOT NULL DEFAULT 0 AFTER `source_note`,
				ADD COLUMN `updated_by_user_id` BIGINT UNSIGNED NULL AFTER `author_user_id`,
				ADD CONSTRAINT `fk_st_courses_updated_by` FOREIGN KEY (`updated_by_user_id`) REFERENCES `st_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE",
			"ALTER TABLE `st_lessons`
				ADD COLUMN `content_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `status`,
				ADD COLUMN `is_preview` TINYINT(1) NOT NULL DEFAULT 0 AFTER `content_version`,
				ADD COLUMN `source_note` TEXT NULL AFTER `is_preview`",
			"ALTER TABLE `st_questions`
				ADD COLUMN `content_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `status`,
				ADD COLUMN `rights_status` ENUM('pending_review','owned','licensed','public_domain') NOT NULL DEFAULT 'pending_review' AFTER `content_version`,
				ADD COLUMN `source_note` TEXT NULL AFTER `rights_status`,
				ADD COLUMN `reviewed_by_user_id` BIGINT UNSIGNED NULL AFTER `source_note`,
				ADD CONSTRAINT `fk_st_questions_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `st_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE",
			"ALTER TABLE `st_content_reviews`
				ADD COLUMN `content_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `decision`"
		);

		foreach ($queries as $sql)
		{
			if ($this->db->query($sql) === FALSE)
			{
				throw new RuntimeException('Migrasi metadata review belum dapat diterapkan.');
			}
		}
		return TRUE;
	}

	public function down()
	{
		$tables = array('st_content_reviews', 'st_questions', 'st_lessons', 'st_courses');
		foreach ($tables as $table)
		{
			$query = $this->db->query('SELECT COUNT(*) AS total FROM `'.$table.'`');
			$row = $query === FALSE ? NULL : $query->row_array();
			if ( ! is_array($row) || ! isset($row['total']) ||
				( ! is_int($row['total']) && ! is_string($row['total'])) ||
				! preg_match('/\A[0-9]+\z/', (string) $row['total']) || (int) $row['total'] !== 0)
			{
				throw new RuntimeException('Migrasi metadata review tidak dapat dibatalkan karena data masih tersimpan atau pemeriksaan data gagal. Buat cadangan dan tinjau data sebelum melanjutkan.');
			}
		}
		$queries = array(
			'ALTER TABLE `st_content_reviews` DROP COLUMN `content_version`',
			'ALTER TABLE `st_questions` DROP FOREIGN KEY `fk_st_questions_reviewer`, DROP COLUMN `reviewed_by_user_id`, DROP COLUMN `source_note`, DROP COLUMN `rights_status`, DROP COLUMN `content_version`',
			'ALTER TABLE `st_lessons` DROP COLUMN `source_note`, DROP COLUMN `is_preview`, DROP COLUMN `content_version`',
			'ALTER TABLE `st_courses` DROP FOREIGN KEY `fk_st_courses_updated_by`, DROP COLUMN `updated_by_user_id`, DROP COLUMN `is_free`, DROP COLUMN `source_note`, DROP COLUMN `rights_status`, DROP COLUMN `content_version`, DROP COLUMN `estimated_minutes`, DROP COLUMN `age_max`, DROP COLUMN `age_min`, DROP COLUMN `learning_objectives`'
		);
		foreach ($queries as $sql)
		{
			if ($this->db->query($sql) === FALSE) { throw new RuntimeException('Migrasi metadata review belum dapat dibatalkan.'); }
		}
		return TRUE;
	}
}
