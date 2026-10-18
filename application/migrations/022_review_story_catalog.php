<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Review_story_catalog extends CI_Migration
{
	public function up()
	{
		$old = json_decode(file_get_contents(APPPATH.'config/arena_stories.json'), TRUE, 512, JSON_THROW_ON_ERROR); $expected = array_column($old['packs'], NULL, 'slug');
		$catalog = json_decode(file_get_contents(APPPATH.'config/arena_stories_reviewed.json'), TRUE, 512, JSON_THROW_ON_ERROR); $now = gmdate('Y-m-d H:i:s'); $this->db->trans_begin();
		try
		{
			foreach ($catalog['packs'] as $pack)
			{
				$query = $this->db->query('SELECT * FROM st_arena_packs WHERE slug=? FOR UPDATE', array($pack['slug'])); if ($query === FALSE) { throw new RuntimeException('Story catalog unavailable.'); } $row = $query->row_array();
				if (isset($expected[$pack['slug']]))
				{
					if ( ! $row || (int) $row['content_version'] !== 1 || $row['game_type'] !== 'story' || $row['title'] !== $expected[$pack['slug']]['title'] || json_decode($row['content_json'], TRUE) !== $expected[$pack['slug']]) { throw new RuntimeException('Existing story modified; stop instead of overwriting server changes.'); }
					if ( ! $this->db->where('id', $row['id'])->update('st_arena_packs', array('content_json' => json_encode($pack, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'content_version' => 2, 'revision' => (int) $row['revision'] + 1, 'updated_at' => $now))) { throw new RuntimeException('Story update failed.'); }
				}
				else
				{
					if ($row) { throw new RuntimeException('Story slug already exists; preserve server content.'); }
					if ( ! $this->db->insert('st_arena_packs', array('slug' => $pack['slug'], 'game_type' => 'story', 'title' => $pack['title'], 'learning_stage' => $pack['stage'], 'content_json' => json_encode($pack, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'content_version' => 2, 'created_at' => $now, 'updated_at' => $now))) { throw new RuntimeException('Story insert failed.'); }
				}
			}
			if ($this->db->trans_status() !== TRUE || $this->db->trans_commit() !== TRUE) { throw new RuntimeException('Story commit not confirmed.'); }
		}
		catch (Throwable $error) { $this->db->trans_rollback(); throw $error; }
	}

	public function down()
	{
		throw new RuntimeException('Preserve story IDs, content versions and run snapshots.');
	}
}
