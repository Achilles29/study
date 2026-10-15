<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_core_schema extends CI_Migration
{
	public function up()
	{
		$tables = array(
			"CREATE TABLE `st_users` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`username` VARCHAR(80) NOT NULL,
				`email` VARCHAR(190) NULL,
				`password_hash` VARCHAR(255) NOT NULL,
				`display_name` VARCHAR(120) NOT NULL,
				`account_kind` ENUM('adult','parent','child') NOT NULL,
				`status` ENUM('pending','active','suspended','closed') NOT NULL DEFAULT 'pending',
				`email_verified_at` DATETIME NULL,
				`last_login_at` DATETIME NULL,
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_st_users_username` (`username`),
				UNIQUE KEY `uq_st_users_email` (`email`),
				KEY `idx_st_users_kind_status` (`account_kind`,`status`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_family_links` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`parent_user_id` BIGINT UNSIGNED NOT NULL,
				`child_user_id` BIGINT UNSIGNED NOT NULL,
				`status` ENUM('pending','active','revoked') NOT NULL DEFAULT 'pending',
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_st_family_pair` (`parent_user_id`,`child_user_id`),
				KEY `idx_st_family_child_status` (`child_user_id`,`status`),
				CONSTRAINT `fk_st_family_parent` FOREIGN KEY (`parent_user_id`) REFERENCES `st_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_family_child` FOREIGN KEY (`child_user_id`) REFERENCES `st_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_categories` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`name` VARCHAR(120) NOT NULL,
				`slug` VARCHAR(140) NOT NULL,
				`description` TEXT NULL,
				`sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
				`status` ENUM('active','archived') NOT NULL DEFAULT 'active',
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_st_categories_slug` (`slug`),
				KEY `idx_st_categories_status_sort` (`status`,`sort_order`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_courses` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`category_id` BIGINT UNSIGNED NULL,
				`author_user_id` BIGINT UNSIGNED NULL,
				`title` VARCHAR(180) NOT NULL,
				`slug` VARCHAR(200) NOT NULL,
				`summary` TEXT NULL,
				`target_group` ENUM('early_childhood','primary','secondary','adult','all_ages') NOT NULL DEFAULT 'all_ages',
				`status` ENUM('draft','review','published','archived') NOT NULL DEFAULT 'draft',
				`published_at` DATETIME NULL,
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_st_courses_slug` (`slug`),
				KEY `idx_st_courses_status_group` (`status`,`target_group`),
				CONSTRAINT `fk_st_courses_category` FOREIGN KEY (`category_id`) REFERENCES `st_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
				CONSTRAINT `fk_st_courses_author` FOREIGN KEY (`author_user_id`) REFERENCES `st_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_course_sections` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`course_id` BIGINT UNSIGNED NOT NULL,
				`title` VARCHAR(180) NOT NULL,
				`sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				KEY `idx_st_sections_course_sort` (`course_id`,`sort_order`),
				CONSTRAINT `fk_st_sections_course` FOREIGN KEY (`course_id`) REFERENCES `st_courses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_lessons` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`section_id` BIGINT UNSIGNED NOT NULL,
				`title` VARCHAR(180) NOT NULL,
				`slug` VARCHAR(200) NOT NULL,
				`body_html` MEDIUMTEXT NULL,
				`estimated_minutes` SMALLINT UNSIGNED NULL,
				`sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
				`status` ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_st_lessons_slug` (`slug`),
				KEY `idx_st_lessons_section_sort` (`section_id`,`sort_order`),
				CONSTRAINT `fk_st_lessons_section` FOREIGN KEY (`section_id`) REFERENCES `st_course_sections` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_questions` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`course_id` BIGINT UNSIGNED NULL,
				`created_by_user_id` BIGINT UNSIGNED NULL,
				`target_child_user_id` BIGINT UNSIGNED NULL,
				`visibility` ENUM('shared','family_private') NOT NULL DEFAULT 'shared',
				`question_type` ENUM('single_choice','true_false','short_text') NOT NULL DEFAULT 'single_choice',
				`prompt` TEXT NOT NULL,
				`explanation` TEXT NULL,
				`status` ENUM('draft','review','published','archived') NOT NULL DEFAULT 'draft',
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				KEY `idx_st_questions_course_status` (`course_id`,`status`),
				KEY `idx_st_questions_private_owner` (`visibility`,`created_by_user_id`,`target_child_user_id`),
				CONSTRAINT `fk_st_questions_course` FOREIGN KEY (`course_id`) REFERENCES `st_courses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
				CONSTRAINT `fk_st_questions_author` FOREIGN KEY (`created_by_user_id`) REFERENCES `st_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
				CONSTRAINT `fk_st_questions_child` FOREIGN KEY (`target_child_user_id`) REFERENCES `st_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_question_options` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`question_id` BIGINT UNSIGNED NOT NULL,
				`option_text` TEXT NOT NULL,
				`is_correct` TINYINT(1) NOT NULL DEFAULT 0,
				`sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				KEY `idx_st_options_question_sort` (`question_id`,`sort_order`),
				CONSTRAINT `fk_st_options_question` FOREIGN KEY (`question_id`) REFERENCES `st_questions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_packages` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`title` VARCHAR(180) NOT NULL,
				`slug` VARCHAR(200) NOT NULL,
				`summary` TEXT NULL,
				`credit_price` BIGINT UNSIGNED NOT NULL DEFAULT 0,
				`access_days` INT UNSIGNED NULL,
				`status` ENUM('draft','review','published','archived') NOT NULL DEFAULT 'draft',
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_st_packages_slug` (`slug`),
				KEY `idx_st_packages_status` (`status`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_package_courses` (
				`package_id` BIGINT UNSIGNED NOT NULL,
				`course_id` BIGINT UNSIGNED NOT NULL,
				`sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (`package_id`,`course_id`),
				CONSTRAINT `fk_st_package_courses_package` FOREIGN KEY (`package_id`) REFERENCES `st_packages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
				CONSTRAINT `fk_st_package_courses_course` FOREIGN KEY (`course_id`) REFERENCES `st_courses` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_wallets` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`owner_user_id` BIGINT UNSIGNED NOT NULL,
				`balance_credits` BIGINT UNSIGNED NOT NULL DEFAULT 0,
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_st_wallet_owner` (`owner_user_id`),
				CONSTRAINT `fk_st_wallet_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `st_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_wallet_entries` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`wallet_id` BIGINT UNSIGNED NOT NULL,
				`actor_user_id` BIGINT UNSIGNED NULL,
				`entry_type` ENUM('top_up','package_purchase','refund','adjustment') NOT NULL,
				`delta_credits` BIGINT NOT NULL,
				`idempotency_key` VARCHAR(120) NOT NULL,
				`reference_type` VARCHAR(40) NULL,
				`reference_id` BIGINT UNSIGNED NULL,
				`note` VARCHAR(255) NULL,
				`created_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_st_wallet_entry_idempotency` (`idempotency_key`),
				KEY `idx_st_wallet_entries_wallet_created` (`wallet_id`,`created_at`),
				CONSTRAINT `fk_st_wallet_entries_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `st_wallets` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_wallet_entries_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `st_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_payment_orders` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`user_id` BIGINT UNSIGNED NOT NULL,
				`wallet_id` BIGINT UNSIGNED NOT NULL,
				`order_code` VARCHAR(64) NOT NULL,
				`provider_order_id` VARCHAR(100) NULL,
				`amount_idr` BIGINT UNSIGNED NOT NULL,
				`credit_amount` BIGINT UNSIGNED NOT NULL,
				`status` ENUM('created','pending','paid','expired','failed','cancelled','refunded') NOT NULL DEFAULT 'created',
				`expires_at` DATETIME NULL,
				`paid_at` DATETIME NULL,
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_st_payment_order_code` (`order_code`),
				UNIQUE KEY `uq_st_payment_provider_order` (`provider_order_id`),
				KEY `idx_st_payment_user_created` (`user_id`,`created_at`),
				KEY `idx_st_payment_status_created` (`status`,`created_at`),
				CONSTRAINT `fk_st_payment_user` FOREIGN KEY (`user_id`) REFERENCES `st_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_payment_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `st_wallets` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_entitlements` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`user_id` BIGINT UNSIGNED NOT NULL,
				`package_id` BIGINT UNSIGNED NOT NULL,
				`payment_order_id` BIGINT UNSIGNED NULL,
				`status` ENUM('active','expired','revoked') NOT NULL DEFAULT 'active',
				`starts_at` DATETIME NOT NULL,
				`ends_at` DATETIME NULL,
				`created_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				KEY `idx_st_entitlements_user_status` (`user_id`,`status`,`ends_at`),
				KEY `idx_st_entitlements_order` (`payment_order_id`),
				CONSTRAINT `fk_st_entitlements_user` FOREIGN KEY (`user_id`) REFERENCES `st_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_entitlements_package` FOREIGN KEY (`package_id`) REFERENCES `st_packages` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_entitlements_order` FOREIGN KEY (`payment_order_id`) REFERENCES `st_payment_orders` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_practice_sessions` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`created_by_user_id` BIGINT UNSIGNED NOT NULL,
				`learner_user_id` BIGINT UNSIGNED NOT NULL,
				`title` VARCHAR(180) NOT NULL,
				`session_type` ENUM('self_practice','parent_assigned','course_quiz','assessment') NOT NULL DEFAULT 'self_practice',
				`status` ENUM('draft','available','in_progress','completed','expired','cancelled') NOT NULL DEFAULT 'draft',
				`available_until` DATETIME NULL,
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				KEY `idx_st_sessions_learner_status` (`learner_user_id`,`status`),
				KEY `idx_st_sessions_creator` (`created_by_user_id`,`created_at`),
				CONSTRAINT `fk_st_sessions_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `st_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_sessions_learner` FOREIGN KEY (`learner_user_id`) REFERENCES `st_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_session_questions` (
				`session_id` BIGINT UNSIGNED NOT NULL,
				`question_id` BIGINT UNSIGNED NOT NULL,
				`sort_order` INT UNSIGNED NOT NULL,
				PRIMARY KEY (`session_id`,`question_id`),
				UNIQUE KEY `uq_st_session_question_order` (`session_id`,`sort_order`),
				CONSTRAINT `fk_st_session_questions_session` FOREIGN KEY (`session_id`) REFERENCES `st_practice_sessions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
				CONSTRAINT `fk_st_session_questions_question` FOREIGN KEY (`question_id`) REFERENCES `st_questions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_practice_attempts` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`session_id` BIGINT UNSIGNED NOT NULL,
				`learner_user_id` BIGINT UNSIGNED NOT NULL,
				`status` ENUM('in_progress','submitted','graded','abandoned') NOT NULL DEFAULT 'in_progress',
				`score` DECIMAL(7,2) NULL,
				`question_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				`correct_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				`started_at` DATETIME NOT NULL,
				`submitted_at` DATETIME NULL,
				PRIMARY KEY (`id`),
				KEY `idx_st_attempts_learner_status` (`learner_user_id`,`status`,`started_at`),
				CONSTRAINT `fk_st_attempts_session` FOREIGN KEY (`session_id`) REFERENCES `st_practice_sessions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_attempts_learner` FOREIGN KEY (`learner_user_id`) REFERENCES `st_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_practice_answers` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`attempt_id` BIGINT UNSIGNED NOT NULL,
				`question_id` BIGINT UNSIGNED NOT NULL,
				`selected_option_id` BIGINT UNSIGNED NULL,
				`answer_text` TEXT NULL,
				`is_correct` TINYINT(1) NULL,
				`answered_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_st_answers_attempt_question` (`attempt_id`,`question_id`),
				CONSTRAINT `fk_st_answers_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `st_practice_attempts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_answers_question` FOREIGN KEY (`question_id`) REFERENCES `st_questions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_answers_option` FOREIGN KEY (`selected_option_id`) REFERENCES `st_question_options` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_lesson_progress` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`learner_user_id` BIGINT UNSIGNED NOT NULL,
				`lesson_id` BIGINT UNSIGNED NOT NULL,
				`status` ENUM('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
				`last_position` INT UNSIGNED NOT NULL DEFAULT 0,
				`started_at` DATETIME NULL,
				`completed_at` DATETIME NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_st_progress_learner_lesson` (`learner_user_id`,`lesson_id`),
				CONSTRAINT `fk_st_progress_learner` FOREIGN KEY (`learner_user_id`) REFERENCES `st_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_progress_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `st_lessons` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_audit_events` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`actor_user_id` BIGINT UNSIGNED NULL,
				`event_type` VARCHAR(80) NOT NULL,
				`entity_type` VARCHAR(80) NOT NULL,
				`entity_id` BIGINT UNSIGNED NULL,
				`correlation_id` VARCHAR(100) NULL,
				`details_json` JSON NULL,
				`created_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				KEY `idx_st_audit_entity` (`entity_type`,`entity_id`,`created_at`),
				KEY `idx_st_audit_actor` (`actor_user_id`,`created_at`),
				CONSTRAINT `fk_st_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `st_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);

		foreach ($tables as $sql)
		{
			if ($this->db->query($sql) === FALSE)
			{
				throw new RuntimeException('Migrasi skema inti belum dapat diterapkan.');
			}
		}

		return TRUE;
	}

	public function down()
	{
		$tables = array(
			'st_audit_events', 'st_lesson_progress', 'st_practice_answers',
			'st_practice_attempts', 'st_session_questions', 'st_practice_sessions',
			'st_entitlements', 'st_payment_orders', 'st_wallet_entries', 'st_wallets',
			'st_package_courses', 'st_packages', 'st_question_options', 'st_questions',
			'st_lessons', 'st_course_sections', 'st_courses', 'st_categories',
			'st_family_links', 'st_users'
		);

		foreach ($tables as $table)
		{
			$query = $this->db->query('SELECT COUNT(*) AS total FROM `'.$table.'`');
			$row = $query === FALSE ? NULL : $query->row_array();
			if ( ! is_array($row) || ! isset($row['total']) ||
				( ! is_int($row['total']) && ! is_string($row['total'])) ||
				! preg_match('/\A[0-9]+\z/', (string) $row['total']) || (int) $row['total'] !== 0)
			{
				throw new RuntimeException('Migrasi skema inti tidak dapat dibatalkan karena data masih tersimpan atau pemeriksaan data gagal. Buat cadangan dan tinjau data sebelum melanjutkan.');
			}
		}

		foreach ($tables as $table)
		{
			if ($this->db->query('DROP TABLE IF EXISTS `'.$table.'`') === FALSE)
			{
				throw new RuntimeException('Migrasi skema inti belum dapat dibatalkan.');
			}
		}

		return TRUE;
	}
}
