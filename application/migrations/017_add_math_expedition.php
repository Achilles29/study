<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_math_expedition extends CI_Migration
{
	public function up()
	{
		$catalog = json_decode(file_get_contents(APPPATH.'config/arena_expedition.json'), TRUE, 512, JSON_THROW_ON_ERROR);
		if ($this->db->query('ALTER TABLE st_arena_runs ADD COLUMN passed TINYINT UNSIGNED NULL, ADD KEY idx_arena_pack_progress (learner_user_id,pack_id,status)') === FALSE) { throw new RuntimeException('Expedition progress unavailable.'); }
		$now = gmdate('Y-m-d H:i:s');
		foreach ($catalog['packs'] as $pack)
		{
			if ( ! $this->db->insert('st_arena_packs', array('slug' => $pack['slug'], 'game_type' => $pack['type'], 'title' => $pack['title'], 'learning_stage' => $pack['stage'], 'content_json' => json_encode($pack, JSON_UNESCAPED_UNICODE), 'content_version' => $catalog['version'], 'created_at' => $now, 'updated_at' => $now))) { throw new RuntimeException('Expedition content not saved.'); }
		}
	}

	public function down()
	{
		throw new RuntimeException('Do not delete expedition history automatically.');
	}
}
