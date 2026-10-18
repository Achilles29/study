<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_story_quiz extends CI_Migration
{
	public function up()
	{
		$catalog = json_decode(file_get_contents(APPPATH.'config/arena_stories.json'), TRUE, 512, JSON_THROW_ON_ERROR);
		$now = gmdate('Y-m-d H:i:s');
		$this->db->trans_begin();
		foreach ($catalog['packs'] as $pack)
		{
			if ( ! $this->db->insert('st_arena_packs', array('slug' => $pack['slug'], 'game_type' => $pack['type'], 'title' => $pack['title'], 'learning_stage' => $pack['stage'], 'content_json' => json_encode($pack, JSON_UNESCAPED_UNICODE), 'content_version' => $catalog['version'], 'created_at' => $now, 'updated_at' => $now))) { $this->db->trans_rollback(); throw new RuntimeException('Story catalog not saved.'); }
		}
		if ($this->db->trans_status() !== TRUE || $this->db->trans_commit() !== TRUE) { throw new RuntimeException('Story catalog commit not confirmed.'); }
	}

	public function down()
	{
		throw new RuntimeException('Do not delete story progress automatically.');
	}
}
