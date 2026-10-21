<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_practice_sheet extends CI_Migration
{
	public function up()
	{
		foreach (array('st_practice_sessions','st_exam_templates') as $table) {
			if (!$this->db->field_exists('sheet_rules_json', $table)
				&& !$this->db->query("ALTER TABLE $table ADD sheet_rules_json TEXT NULL")) {
				throw new RuntimeException('Practice sheet schema unavailable.');
			}
		}
		if (!$this->db->query("CREATE TABLE IF NOT EXISTS st_practice_focus_events (
			attempt_id BIGINT UNSIGNED NOT NULL,
			event_token CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			reason VARCHAR(12) NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY(attempt_id,event_token),
			CONSTRAINT fk_focus_attempt FOREIGN KEY(attempt_id) REFERENCES st_practice_attempts(id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
			throw new RuntimeException('Practice focus events unavailable.');
		}
	}
	public function down(){throw new RuntimeException('Preserve session rules and focus history.');}
}
