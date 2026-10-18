<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_operational_traffic extends CI_Migration
{
	public function up()
	{
		foreach (array(
			"CREATE TABLE IF NOT EXISTS st_operational_events(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,created_at DATETIME NOT NULL,expires_at DATETIME NOT NULL,page_key VARCHAR(100) NOT NULL,user_id BIGINT UNSIGNED NULL,account_kind ENUM('guest','adult','parent') NOT NULL,ip_address VARCHAR(45) CHARACTER SET ascii NULL,country CHAR(2) CHARACTER SET ascii NULL,device VARCHAR(16) NOT NULL,browser VARCHAR(24) NOT NULL,os VARCHAR(24) NOT NULL,is_bot TINYINT UNSIGNED NOT NULL DEFAULT 0,INDEX(created_at,id),INDEX(expires_at,id),INDEX(user_id,created_at),INDEX(page_key,created_at),CONSTRAINT fk_operational_user FOREIGN KEY(user_id) REFERENCES st_users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
			"CREATE TABLE IF NOT EXISTS st_operational_state(id TINYINT UNSIGNED PRIMARY KEY,first_event_at DATETIME NULL,last_event_at DATETIME NULL,retention_at DATETIME NULL,failures BIGINT UNSIGNED NOT NULL DEFAULT 0,last_failure_at DATETIME NULL) ENGINE=InnoDB",
			"INSERT IGNORE INTO st_operational_state(id) VALUES(1)"
		) as $sql) { if ($this->db->query($sql) === FALSE) { throw new RuntimeException('Operational traffic schema unavailable.'); } }
	}
	public function down() { throw new RuntimeException('No destructive rollback for operational traffic.'); }
}
