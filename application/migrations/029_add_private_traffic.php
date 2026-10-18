<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_private_traffic extends CI_Migration
{
	public function up()
	{
		$queries = array(
			"CREATE TABLE st_traffic_state (id TINYINT UNSIGNED NOT NULL PRIMARY KEY,first_event_at DATETIME NULL,last_event_at DATETIME NULL,failures INT UNSIGNED NOT NULL DEFAULT 0,last_failure_at DATETIME NULL,retention_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"INSERT INTO st_traffic_state(id) VALUES(1)",
			"CREATE TABLE st_traffic_consents (user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,actor_user_id BIGINT UNSIGNED NOT NULL,policy_version VARCHAR(40) NOT NULL,enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,updated_at DATETIME NOT NULL,CONSTRAINT fk_traffic_consent_user FOREIGN KEY(user_id) REFERENCES st_users(id) ON DELETE RESTRICT,CONSTRAINT fk_traffic_consent_actor FOREIGN KEY(actor_user_id) REFERENCES st_users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE st_traffic_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,created_at DATETIME NOT NULL,expires_at DATETIME NOT NULL,aggregate_expires_on DATE NOT NULL,policy_version VARCHAR(40) NOT NULL,page_key VARCHAR(80) NOT NULL,user_id BIGINT UNSIGNED NULL,account_kind ENUM('guest','adult','parent','child') NOT NULL,learning_stage VARCHAR(30) NOT NULL,visitor_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,visit_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,KEY idx_traffic_time(created_at,page_key),KEY idx_traffic_expiry(expires_at,id),KEY idx_traffic_user(user_id,created_at),KEY idx_traffic_page(page_key,created_at),CONSTRAINT fk_traffic_event_user FOREIGN KEY(user_id) REFERENCES st_users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE st_traffic_daily (day_wib DATE NOT NULL,page_key VARCHAR(80) NOT NULL,account_kind ENUM('guest','adult','parent','child') NOT NULL,views BIGINT UNSIGNED NOT NULL,expires_on DATE NOT NULL,PRIMARY KEY(day_wib,page_key,account_kind)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);
		foreach ($queries as $sql) { if ($this->db->query($sql) === FALSE) { throw new RuntimeException('Traffic schema unavailable.'); } }
		$id = (int) $this->db->query('SELECT COALESCE(MAX(id),0)+1 next_id FROM st_site_settings')->row_array()['next_id'];
		if ( ! $this->db->insert('st_site_settings', array('id' => $id, 'section' => 'privasi', 'values_json' => '{}', 'revision' => 1, 'updated_at' => gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Privacy settings unavailable.'); }
	}
	public function down()
	{
		throw new RuntimeException('Consent and traffic evidence cannot be reset automatically.'); }
}
