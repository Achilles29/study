<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_domain_mail extends CI_Migration
{
	public function up()
	{
		$queries = array(
			"ALTER TABLE st_mail_config ADD provider VARCHAR(10) NOT NULL DEFAULT 'gmail', ADD smtp_host VARCHAR(253) NOT NULL DEFAULT '', ADD smtp_port SMALLINT UNSIGNED NOT NULL DEFAULT 587, ADD smtp_username VARCHAR(190) NOT NULL DEFAULT ''",
			"CREATE TABLE st_mail_profiles (provider VARCHAR(10) NOT NULL PRIMARY KEY,sender_email VARCHAR(190) NOT NULL DEFAULT '',credentials_cipher TEXT NULL,smtp_host VARCHAR(253) NOT NULL DEFAULT '',smtp_port SMALLINT UNSIGNED NOT NULL DEFAULT 587,smtp_username VARCHAR(190) NOT NULL DEFAULT '',probe_status VARCHAR(20) NOT NULL DEFAULT 'untested',probe_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"INSERT INTO st_mail_profiles(provider,sender_email,credentials_cipher,probe_status,probe_at) SELECT 'gmail',sender_email,credentials_cipher,probe_status,probe_at FROM st_mail_config WHERE id=1",
			"INSERT INTO st_mail_profiles(provider) VALUES('smtp')"
		);
		foreach ($queries as $query) { if ($this->db->query($query) === FALSE) { throw new RuntimeException('Domain mail schema unavailable.'); } }
	}
	public function down() { throw new RuntimeException('Preserve mail profiles and delivery evidence.'); }
}
