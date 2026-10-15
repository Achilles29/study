<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Create_flashcards extends CI_Migration
{
	public function up()
	{
		$tables = array(
			"CREATE TABLE `st_flashcard_decks` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`lesson_id` BIGINT UNSIGNED NOT NULL,
				`title` VARCHAR(180) NOT NULL,
				`description` VARCHAR(1000) NULL,
				`created_by_user_id` BIGINT UNSIGNED NOT NULL,
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_st_flashcard_deck_lesson` (`lesson_id`),
				CONSTRAINT `fk_st_flashcard_deck_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `st_lessons` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_flashcard_deck_author` FOREIGN KEY (`created_by_user_id`) REFERENCES `st_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_flashcards` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`deck_id` BIGINT UNSIGNED NOT NULL,
				`front_text` VARCHAR(500) NOT NULL,
				`back_text` VARCHAR(2000) NOT NULL,
				`hint_text` VARCHAR(500) NULL,
				`sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				`created_at` DATETIME NOT NULL,
				`updated_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				KEY `idx_st_flashcards_deck_sort` (`deck_id`,`sort_order`,`id`),
				CONSTRAINT `fk_st_flashcards_deck` FOREIGN KEY (`deck_id`) REFERENCES `st_flashcard_decks` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE `st_flashcard_progress` (
				`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				`learner_user_id` BIGINT UNSIGNED NOT NULL,
				`flashcard_id` BIGINT UNSIGNED NOT NULL,
				`status` ENUM('learning','known') NOT NULL DEFAULT 'learning',
				`review_count` INT UNSIGNED NOT NULL DEFAULT 0,
				`last_reviewed_at` DATETIME NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_st_flashcard_progress_learner_card` (`learner_user_id`,`flashcard_id`),
				KEY `idx_st_flashcard_progress_card_status` (`flashcard_id`,`status`),
				CONSTRAINT `fk_st_flashcard_progress_learner` FOREIGN KEY (`learner_user_id`) REFERENCES `st_users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
				CONSTRAINT `fk_st_flashcard_progress_card` FOREIGN KEY (`flashcard_id`) REFERENCES `st_flashcards` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);

		foreach ($tables as $sql)
		{
			if ($this->db->query($sql) === FALSE)
			{
				throw new RuntimeException('Migrasi kartu belajar belum dapat diterapkan.');
			}
		}

		return TRUE;
	}

	public function down()
	{
		$tables = array('st_flashcard_progress', 'st_flashcards', 'st_flashcard_decks');
		foreach ($tables as $table)
		{
			$query = $this->db->query('SELECT COUNT(*) AS total FROM `'.$table.'`');
			$row = $query === FALSE ? NULL : $query->row_array();
			if ( ! is_array($row) || ! isset($row['total']) ||
				( ! is_int($row['total']) && ! is_string($row['total'])) ||
				! preg_match('/\A[0-9]+\z/', (string) $row['total']) || (int) $row['total'] !== 0)
			{
				throw new RuntimeException('Migrasi kartu belajar tidak dapat dibatalkan karena data masih tersimpan atau pemeriksaan data gagal. Buat cadangan dan tinjau data sebelum melanjutkan.');
			}
		}
		foreach ($tables as $table)
		{
			if ($this->db->query('DROP TABLE `'.$table.'`') === FALSE)
			{
				throw new RuntimeException('Migrasi kartu belajar belum dapat dibatalkan.');
			}
		}
		return TRUE;
	}
}
