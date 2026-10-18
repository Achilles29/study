<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_arena_games extends CI_Migration
{
	public function up()
	{
		$catalog = json_decode(file_get_contents(APPPATH.'config/arena_catalog.json'), TRUE, 512, JSON_THROW_ON_ERROR);
		$this->db->query("CREATE TABLE st_arena_packs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			slug VARCHAR(100) NOT NULL UNIQUE, game_type VARCHAR(20) NOT NULL,
			title VARCHAR(180) NOT NULL, learning_stage VARCHAR(30) NOT NULL,
			content_json MEDIUMTEXT NOT NULL, content_version INT UNSIGNED NOT NULL,
			enabled TINYINT UNSIGNED NOT NULL DEFAULT 1, revision INT UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
			KEY idx_arena_catalog (enabled,learning_stage,game_type)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$this->db->query("CREATE TABLE st_arena_runs (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			learner_user_id BIGINT UNSIGNED NOT NULL, pack_id BIGINT UNSIGNED NOT NULL,
			start_key CHAR(64) CHARACTER SET ascii NOT NULL,
			game_type VARCHAR(20) NOT NULL, title_snapshot VARCHAR(180) NOT NULL,
			content_version INT UNSIGNED NOT NULL, snapshot_json MEDIUMTEXT NOT NULL, state_json MEDIUMTEXT NOT NULL,
			status ENUM('running','completed','abandoned') NOT NULL DEFAULT 'running',
			revision INT UNSIGNED NOT NULL DEFAULT 1, question_count INT UNSIGNED NOT NULL,
			correct_count INT UNSIGNED NOT NULL DEFAULT 0, moves INT UNSIGNED NOT NULL DEFAULT 0,
			started_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, finished_at DATETIME NULL, deadline_at DATETIME NULL,
			UNIQUE KEY uq_arena_start (learner_user_id,start_key),
			KEY idx_arena_owner (learner_user_id,status,id), KEY idx_arena_report (updated_at,pack_id,learner_user_id),
			CONSTRAINT fk_arena_owner FOREIGN KEY (learner_user_id) REFERENCES st_users(id) ON DELETE RESTRICT,
			CONSTRAINT fk_arena_pack FOREIGN KEY (pack_id) REFERENCES st_arena_packs(id) ON DELETE RESTRICT
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$now = gmdate('Y-m-d H:i:s');
		foreach ($catalog['packs'] as $pack)
		{
			if ( ! $this->db->insert('st_arena_packs', array('slug' => $pack['slug'], 'game_type' => $pack['type'], 'title' => $pack['title'], 'learning_stage' => $pack['stage'], 'content_json' => json_encode($pack, JSON_UNESCAPED_UNICODE), 'content_version' => $catalog['version'], 'created_at' => $now, 'updated_at' => $now))) { throw new RuntimeException('Arena catalog could not be installed.'); }
		}
		if ( ! $this->db->insert('st_site_settings', array('id' => 3, 'section' => 'arena', 'values_json' => '{}', 'revision' => 1, 'updated_at' => $now))) { throw new RuntimeException('Arena settings unavailable.'); }
	}

	public function down()
	{
		throw new RuntimeException('Arena progress and content must not be deleted automatically.');
	}
}
