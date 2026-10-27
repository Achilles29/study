<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_parent_practice_live extends CI_Migration
{
	public function up()
	{
		if (!$this->db->field_exists('parent_live_enabled', 'st_exam_templates') && !$this->db->query('ALTER TABLE st_exam_templates ADD parent_live_enabled TINYINT(1) NOT NULL DEFAULT 0')) {
			throw new RuntimeException('Exam monitoring setting unavailable.');
		}
		if (!$this->db->query('CREATE TABLE IF NOT EXISTS st_practice_live_state (attempt_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, current_question_id BIGINT UNSIGNED NULL, opened_at DATETIME NULL, last_seen_at DATETIME NOT NULL, CONSTRAINT fk_practice_live_attempt FOREIGN KEY (attempt_id) REFERENCES st_practice_attempts(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4')) {
			throw new RuntimeException('Practice live state unavailable.');
		}
		if (!$this->db->query('CREATE TABLE IF NOT EXISTS st_practice_answer_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, attempt_id BIGINT UNSIGNED NOT NULL, question_id BIGINT UNSIGNED NOT NULL, selected_option_id BIGINT UNSIGNED NULL, elapsed_ms INT UNSIGNED NULL, created_at DATETIME NOT NULL, KEY idx_practice_answer_events (attempt_id, question_id, id), CONSTRAINT fk_practice_answer_event_attempt FOREIGN KEY (attempt_id) REFERENCES st_practice_attempts(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4')) {
			throw new RuntimeException('Practice answer events unavailable.');
		}
	}

	public function down()
	{
		throw new RuntimeException('Preserve monitoring history.');
	}
}
