<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_live_battle extends CI_Migration
{
	public function up()
	{
		if ($this->db->query("ALTER TABLE st_battle_rooms ADD play_mode ENUM('async','live') NOT NULL DEFAULT 'async',ADD question_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 30,ADD live_started_at DATETIME NULL") === FALSE) { throw new RuntimeException('Live battle migration failed.'); }
	}
	public function down()
	{
		throw new RuntimeException('Live battle snapshots are retained.'); }
}
