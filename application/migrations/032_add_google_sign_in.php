<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_google_sign_in extends CI_Migration
{
	public function up()
	{
		foreach (array(
			"CREATE TABLE st_google_config(id TINYINT UNSIGNED NOT NULL PRIMARY KEY,client_id VARCHAR(200) NOT NULL DEFAULT '',secret_cipher TEXT NULL,enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,revision INT UNSIGNED NOT NULL DEFAULT 1,tested_at DATETIME NULL,jwks_json TEXT NULL,jwks_until DATETIME NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"INSERT INTO st_google_config(id,updated_at) VALUES(1,UTC_TIMESTAMP())",
			"CREATE TABLE st_google_identities(user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,sub_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,created_at DATETIME NOT NULL,CONSTRAINT fk_st_google_user FOREIGN KEY(user_id) REFERENCES st_users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE st_google_flows(state_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,session_hash CHAR(64) CHARACTER SET ascii NOT NULL,address_hash CHAR(64) CHARACTER SET ascii NOT NULL,nonce_hash CHAR(64) CHARACTER SET ascii NOT NULL,verifier_cipher TEXT NULL,user_id BIGINT UNSIGNED NULL,credential_stamp CHAR(64) CHARACTER SET ascii NULL,purpose ENUM('login','link','probe') NOT NULL,config_revision INT UNSIGNED NOT NULL,expires_at DATETIME NOT NULL,used_at DATETIME NULL,created_at DATETIME NOT NULL,INDEX(session_hash,created_at),INDEX(address_hash,created_at),CONSTRAINT fk_st_google_flow_user FOREIGN KEY(user_id) REFERENCES st_users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		) as $sql) { if ($this->db->query($sql) === FALSE) { throw new RuntimeException('Google sign-in migration failed.'); } }
	}
	public function down()
	{
		throw new RuntimeException('Google sign-in history is retained.'); }
}
