<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_private_battles extends CI_Migration
{
	public function up()
	{
		$queries = array(
			"CREATE TABLE st_battle_rooms (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,creator_user_id BIGINT UNSIGNED NOT NULL,publisher_user_id BIGINT UNSIGNED NULL,title VARCHAR(160) NOT NULL,status ENUM('draft','published','cancelled','archived') NOT NULL DEFAULT 'draft',create_key CHAR(64) CHARACTER SET ascii NOT NULL,cancelled_at DATETIME NULL,UNIQUE KEY uq_battle_create(creator_user_id,create_key),invite_code CHAR(16) CHARACTER SET ascii NOT NULL,starts_at DATETIME NOT NULL,ends_at DATETIME NOT NULL,capacity SMALLINT UNSIGNED NOT NULL,draft_json LONGTEXT NOT NULL,snapshot_json LONGTEXT NULL,revision INT UNSIGNED NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE KEY uq_battle_code(invite_code),KEY idx_battle_owner(creator_user_id,status),KEY idx_battle_window(status,ends_at),FOREIGN KEY(creator_user_id) REFERENCES st_users(id) ON DELETE RESTRICT,FOREIGN KEY(publisher_user_id) REFERENCES st_users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE st_battle_entries (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,room_id BIGINT UNSIGNED NOT NULL,learner_user_id BIGINT UNSIGNED NOT NULL,guardian_user_id BIGINT UNSIGNED NULL,alias VARCHAR(32) NOT NULL,consent_version VARCHAR(40) NULL,consented_at DATETIME NULL,revoked_at DATETIME NULL,status ENUM('joined','running','completed') NOT NULL DEFAULT 'joined',state_json LONGTEXT NOT NULL,revision INT UNSIGNED NOT NULL DEFAULT 1,correct_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,question_count SMALLINT UNSIGNED NOT NULL,started_at DATETIME NULL,finished_at DATETIME NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE KEY uq_battle_entry(room_id,learner_user_id),KEY idx_battle_learner(learner_user_id,updated_at),FOREIGN KEY(room_id) REFERENCES st_battle_rooms(id) ON DELETE RESTRICT,FOREIGN KEY(learner_user_id) REFERENCES st_users(id) ON DELETE RESTRICT,FOREIGN KEY(guardian_user_id) REFERENCES st_users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE st_battle_reports (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,room_id BIGINT UNSIGNED NOT NULL,reporter_user_id BIGINT UNSIGNED NOT NULL,reason VARCHAR(500) NOT NULL,status ENUM('open','resolved') NOT NULL DEFAULT 'open',created_at DATETIME NOT NULL,resolved_at DATETIME NULL,resolved_by_user_id BIGINT UNSIGNED NULL,KEY idx_battle_reports(status,created_at),FOREIGN KEY(room_id) REFERENCES st_battle_rooms(id) ON DELETE RESTRICT,FOREIGN KEY(reporter_user_id) REFERENCES st_users(id) ON DELETE RESTRICT,FOREIGN KEY(resolved_by_user_id) REFERENCES st_users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);
		foreach ($queries as $query) { if ($this->db->query($query) === FALSE) { throw new RuntimeException('Battle schema unavailable.'); } }
		if ( ! $this->db->insert('st_site_settings', array('id' => 6, 'section' => 'battle', 'values_json' => '{}', 'revision' => 1, 'updated_at' => gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Battle settings unavailable.'); }
	}

	public function down()
	{
		throw new RuntimeException('Never delete battle attempts or consent automatically.');
	}
}
