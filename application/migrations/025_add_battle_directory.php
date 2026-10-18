<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_battle_directory extends CI_Migration
{
	public function up()
	{
		if ($this->db->query("ALTER TABLE st_battle_rooms ADD listing_status ENUM('private','pending','approved','rejected','hidden') NOT NULL DEFAULT 'private',ADD public_summary VARCHAR(500) NOT NULL DEFAULT '',ADD public_age ENUM('primary','secondary','all_ages') NOT NULL DEFAULT 'all_ages',ADD listing_reason VARCHAR(300) NOT NULL DEFAULT '',ADD listing_requested_at DATETIME NULL,ADD listing_reviewed_at DATETIME NULL,ADD listing_reviewed_by BIGINT UNSIGNED NULL,ADD KEY idx_battle_listing(listing_status,status,ends_at),ADD CONSTRAINT fk_battle_listing_reviewer FOREIGN KEY(listing_reviewed_by) REFERENCES st_users(id) ON DELETE RESTRICT") === FALSE) { throw new RuntimeException('Battle directory schema unavailable.'); }
	}
	public function down()
	{
		throw new RuntimeException('Do not delete moderation history automatically.'); }
}
