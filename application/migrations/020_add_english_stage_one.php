<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_english_stage_one extends CI_Migration
{
	public function up()
	{
		$catalog = json_decode(file_get_contents(APPPATH.'config/arena_english.json'), TRUE, 512, JSON_THROW_ON_ERROR);
		$now = gmdate('Y-m-d H:i:s'); $this->db->trans_begin();
		foreach ($catalog['packs'] as $pack)
		{
			if ( ! $this->db->insert('st_arena_packs', array('slug' => $pack['slug'], 'game_type' => 'english', 'title' => $pack['title'], 'learning_stage' => $pack['stage'], 'content_json' => json_encode($pack, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'content_version' => 1, 'created_at' => $now, 'updated_at' => $now))) { $this->db->trans_rollback(); throw new RuntimeException('English catalog not saved.'); }
		}
		if ( ! $this->db->insert('st_site_settings', array('id' => 4, 'section' => 'english', 'values_json' => '{}', 'revision' => 1, 'updated_at' => $now))) { $this->db->trans_rollback(); throw new RuntimeException('English settings not saved.'); }
		if ($this->db->trans_status() !== TRUE || $this->db->trans_commit() !== TRUE) { throw new RuntimeException('English catalog commit not confirmed.'); }
	}

	public function down()
	{
		throw new RuntimeException('Preserve English history; do not delete catalog automatically.');
	}
}
