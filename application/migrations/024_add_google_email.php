<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_google_email extends CI_Migration
{
	public function up()
	{
		$queries = array(
			"CREATE TABLE st_mail_config (id TINYINT UNSIGNED NOT NULL PRIMARY KEY,sender_email VARCHAR(190) NOT NULL DEFAULT '',credentials_cipher TEXT NULL,enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,revision INT UNSIGNED NOT NULL DEFAULT 1,probe_status VARCHAR(20) NOT NULL DEFAULT 'untested',probe_at DATETIME NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"INSERT INTO st_mail_config(id,updated_at) VALUES(1,UTC_TIMESTAMP())",
			"ALTER TABLE st_account_tokens ADD email_snapshot VARCHAR(190) NULL,ADD credential_stamp CHAR(64) CHARACTER SET ascii NULL,ADD delivery_status ENUM('pending','sent','failed') NOT NULL DEFAULT 'failed'",
			"CREATE TABLE st_mail_deliveries (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NULL,token_id BIGINT UNSIGNED NULL,purpose ENUM('verify','reset','probe') NOT NULL,ip_hash CHAR(64) CHARACTER SET ascii NOT NULL,status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,KEY idx_mail_user(user_id,created_at),KEY idx_mail_ip(ip_hash,created_at),KEY idx_mail_created(created_at),FOREIGN KEY(user_id) REFERENCES st_users(id) ON DELETE RESTRICT,FOREIGN KEY(token_id) REFERENCES st_account_tokens(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);
		foreach ($queries as $query) { if ($this->db->query($query) === FALSE) { throw new RuntimeException('Email schema unavailable.'); } }
	}
	public function down()
	{
		throw new RuntimeException('Do not delete account tokens or delivery evidence automatically.'); }
}
