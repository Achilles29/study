<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_help_desk extends CI_Migration
{
	public function up()
	{
		foreach (array(
			"CREATE TABLE IF NOT EXISTS st_help_guard(id TINYINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB",
			"INSERT IGNORE INTO st_help_guard(id) VALUES(1)",
			"CREATE TABLE IF NOT EXISTS st_help_limits(bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, hits INT UNSIGNED NOT NULL, expires_at DATETIME NOT NULL, INDEX(expires_at)) ENGINE=InnoDB",
			"CREATE TABLE IF NOT EXISTS st_help_tickets(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NULL,access_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,category VARCHAR(20) NOT NULL,subject VARCHAR(120) NOT NULL,status ENUM('open','waiting','closed') NOT NULL DEFAULT 'open',revision INT UNSIGNED NOT NULL DEFAULT 1,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(user_id,updated_at),INDEX(status,updated_at),CONSTRAINT fk_st_help_user FOREIGN KEY(user_id) REFERENCES st_users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE IF NOT EXISTS st_help_messages(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,ticket_id BIGINT UNSIGNED NOT NULL,actor_user_id BIGINT UNSIGNED NULL,is_admin TINYINT UNSIGNED NOT NULL DEFAULT 0,body TEXT NOT NULL,request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,created_at DATETIME NOT NULL,INDEX(ticket_id,id),CONSTRAINT fk_st_help_message_ticket FOREIGN KEY(ticket_id) REFERENCES st_help_tickets(id) ON DELETE RESTRICT,CONSTRAINT fk_st_help_message_user FOREIGN KEY(actor_user_id) REFERENCES st_users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		) as $sql)
		{
			if ($this->db->query($sql) === FALSE) { throw new RuntimeException('Help schema unavailable.'); }
		}
	}
	public function down()
	{
		throw new RuntimeException('Preserve private support correspondence.');
	}
}
