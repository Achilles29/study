<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_learning_points extends CI_Migration
{
	public function up()
	{
		$queries = array(
			"ALTER TABLE st_arena_runs ADD reward_rule_json TEXT NULL",
			"ALTER TABLE st_packages ADD point_price INT UNSIGNED NOT NULL DEFAULT 0,ADD point_days INT UNSIGNED NOT NULL DEFAULT 0",
			"CREATE TABLE st_point_wallets (learner_user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,balance_points INT UNSIGNED NOT NULL DEFAULT 0,updated_at DATETIME NOT NULL,CONSTRAINT fk_points_user FOREIGN KEY(learner_user_id) REFERENCES st_users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE st_point_entries (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,learner_user_id BIGINT UNSIGNED NOT NULL,delta_points INT NOT NULL,reference_key VARCHAR(100) NOT NULL,note VARCHAR(200) NOT NULL,rule_json TEXT NOT NULL,created_at DATETIME NOT NULL,UNIQUE KEY uq_point_reference(learner_user_id,reference_key),KEY idx_points_day(learner_user_id,created_at),CONSTRAINT fk_point_entry_user FOREIGN KEY(learner_user_id) REFERENCES st_users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE st_point_redemptions (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,learner_user_id BIGINT UNSIGNED NOT NULL,package_id BIGINT UNSIGNED NOT NULL,request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,snapshot_json LONGTEXT NOT NULL,course_ids_json LONGTEXT NOT NULL,starts_at DATETIME NOT NULL,ends_at DATETIME NOT NULL,status ENUM('active','revoked') NOT NULL DEFAULT 'active',created_at DATETIME NOT NULL,UNIQUE KEY uq_point_redemption(learner_user_id,request_key),KEY idx_point_access(learner_user_id,status,ends_at),CONSTRAINT fk_point_redemption_user FOREIGN KEY(learner_user_id) REFERENCES st_users(id) ON DELETE RESTRICT,CONSTRAINT fk_point_redemption_package FOREIGN KEY(package_id) REFERENCES st_packages(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);
		foreach ($queries as $sql) { if ($this->db->query($sql) === FALSE) { throw new RuntimeException('Points schema unavailable.'); } }
		$id = (int) $this->db->query('SELECT COALESCE(MAX(id),0)+1 next_id FROM st_site_settings')->row_array()['next_id'];
		if ( ! $this->db->insert('st_site_settings', array('id' => $id, 'section' => 'poin', 'values_json' => '{}', 'revision' => 1, 'updated_at' => gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Points settings unavailable.'); }
	}
	public function down()
	{
		throw new RuntimeException('Learning rewards cannot be reset automatically.'); }
}
