<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_account_controls extends CI_Migration
{
	public function up()
	{
		foreach (array(
			"ALTER TABLE st_users ADD auth_version INT UNSIGNED NOT NULL DEFAULT 0",
			"CREATE TABLE st_course_access_blocks (user_id BIGINT UNSIGNED NOT NULL,course_id BIGINT UNSIGNED NOT NULL,blocked TINYINT UNSIGNED NOT NULL DEFAULT 1,revision INT UNSIGNED NOT NULL DEFAULT 1,updated_at DATETIME NOT NULL,PRIMARY KEY(user_id,course_id),CONSTRAINT fk_st_block_user FOREIGN KEY(user_id) REFERENCES st_users(id) ON DELETE RESTRICT,CONSTRAINT fk_st_block_course FOREIGN KEY(course_id) REFERENCES st_courses(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		) as $sql) { if ($this->db->query($sql) === FALSE) { throw new RuntimeException('Account controls migration failed.'); } }
	}
	public function down()
	{
		throw new RuntimeException('Account controls retain history; no automatic downgrade.'); }
}
