<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_learning_masters extends CI_Migration
{
	private function query($sql, $bindings = array())
	{
		$result = $this->db->query($sql, $bindings); if ($result === FALSE) { throw new RuntimeException('Learning master storage unavailable.'); } return $result;
	}
	public function up()
	{
		$this->query("CREATE TABLE IF NOT EXISTS st_learning_grades (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,code VARCHAR(40) NOT NULL UNIQUE,name VARCHAR(120) NOT NULL,learning_stage VARCHAR(30) NOT NULL,grade_number TINYINT UNSIGNED NULL,sort_order INT UNSIGNED NOT NULL DEFAULT 0,source_code VARCHAR(80) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$this->query("CREATE TABLE IF NOT EXISTS st_arena_categories (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,source_code VARCHAR(80) NOT NULL UNIQUE,game_type VARCHAR(20) NOT NULL,name VARCHAR(120) NOT NULL,description TEXT NULL,category_id BIGINT UNSIGNED NOT NULL,grade_id BIGINT UNSIGNED NOT NULL,FOREIGN KEY(category_id) REFERENCES st_categories(id) ON DELETE RESTRICT,FOREIGN KEY(grade_id) REFERENCES st_learning_grades(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$this->query("CREATE TABLE IF NOT EXISTS st_arena_pack_categories (pack_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,arena_category_id BIGINT UNSIGNED NOT NULL,FOREIGN KEY(pack_id) REFERENCES st_arena_packs(id) ON DELETE RESTRICT,FOREIGN KEY(arena_category_id) REFERENCES st_arena_categories(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$data = json_decode(file_get_contents(APPPATH.'config/pustaka_learning_masters.json'), TRUE, 512, JSON_THROW_ON_ERROR);
		if (count($data['subjects']) !== 20 || count($data['grades']) !== 11 || count($data['game_categories']) !== 29) { throw new RuntimeException('Unexpected master snapshot.'); }
		$this->db->trans_begin();
		try
		{
			$subjects = $grades = array();
			foreach ($data['subjects'] as $s)
			{
				$slug = str_replace('_', '-', $s['code']);
				$existing = $this->query('SELECT id FROM st_categories WHERE slug=? OR name=? FOR UPDATE', array($slug, $s['name']))->result_array();
				if (count($existing) > 1) { throw new RuntimeException('Resolve duplicate master category before import.'); }
				if ( ! $existing) { $this->query("INSERT INTO st_categories(name,slug,description,sort_order,status,created_at,updated_at) VALUES(?,?,'',?,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP())", array($s['name'], $slug, $s['sort_order'])); }
				$subjects[$s['code']] = $existing ? $existing[0]['id'] : $this->db->insert_id();
			}
			foreach ($data['grades'] as $g)
			{
				$existing = $this->query('SELECT id FROM st_learning_grades WHERE code=?', array($g['code']))->row_array();
				if ( ! $existing) { $this->query('INSERT INTO st_learning_grades(code,name,learning_stage,grade_number,sort_order,source_code) VALUES(?,?,?,?,?,?)', array($g['code'], $g['name'], array('tk' => 'early_childhood', 'sd' => 'primary', 'smp' => 'secondary')[$g['education_level']], $g['grade_number'], $g['sort_order'], 'pustaka-grade-'.$g['id'])); }
				$grades[$g['code']] = $existing ? $existing['id'] : $this->db->insert_id();
			}
			foreach ($data['game_categories'] as $c)
			{
				$source = 'pustaka-game-'.$c['id']; $existing = $this->query('SELECT id FROM st_arena_categories WHERE source_code=?', array($source))->row_array();
				if ( ! $existing) { $this->query('INSERT INTO st_arena_categories(source_code,game_type,name,description,category_id,grade_id) VALUES(?,?,?,?,?,?)', array($source, $c['game_type'], $c['name'], $c['description'], $subjects[$c['subject_code']], $grades[$c['grade_code']])); }
				$id = $existing ? $existing['id'] : $this->db->insert_id();
				$this->query('INSERT INTO st_arena_pack_categories(pack_id,arena_category_id) SELECT p.id,? FROM st_arena_packs p WHERE p.slug=? AND p.game_type=? AND NOT EXISTS(SELECT 1 FROM st_arena_pack_categories m WHERE m.pack_id=p.id)', array($id, $c['pack_slug'], $c['game_type']));
			}
			if ( ! $this->db->trans_status() || ! $this->db->trans_commit()) { throw new RuntimeException('Master import not committed.'); }
		}
		catch (Throwable $error) { $this->db->trans_rollback(); throw $error; }
	}
	public function down() { throw new RuntimeException('Preserve imported masters and existing IDs.'); }
}
