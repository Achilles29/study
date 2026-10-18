<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_sma_masters extends CI_Migration
{
	private function query($sql, $bindings = array())
	{
		$result = $this->db->query($sql, $bindings); if ($result === FALSE) { throw new RuntimeException('SMA master storage unavailable.'); } return $result;
	}
	public function up()
	{
		$data = json_decode(file_get_contents(APPPATH.'config/pustaka_sma_masters.json'), TRUE, 512, JSON_THROW_ON_ERROR);
		if (count($data['grades']) !== 3 || count($data['game_categories']) !== 6) { throw new RuntimeException('Unexpected SMA master snapshot.'); }
		$this->db->trans_begin();
		try
		{
			$grades = array();
			foreach ($data['grades'] as $grade)
			{
				$row = $this->query('SELECT id FROM st_learning_grades WHERE code=? FOR UPDATE', array($grade['code']))->row_array();
				if ( ! $row) { $this->query("INSERT INTO st_learning_grades(code,name,learning_stage,grade_number,sort_order,source_code) VALUES(?,?,'upper_secondary',?,?,?)", array($grade['code'], $grade['name'], $grade['grade_number'], $grade['sort_order'], 'pustaka-grade-'.$grade['id'])); }
				$grades[$grade['code']] = $row ? $row['id'] : $this->db->insert_id();
			}
			foreach ($data['game_categories'] as $category)
			{
				$source = 'pustaka-game-'.$category['id'];
				if ($this->query('SELECT id FROM st_arena_categories WHERE source_code=? FOR UPDATE', array($source))->num_rows()) { continue; }
				$subject = $this->query('SELECT id FROM st_categories WHERE slug=? OR name=?', array(str_replace('_', '-', $category['subject_code']), $category['subject_name']))->result_array();
				if (count($subject) !== 1) { throw new RuntimeException('Resolve subject mapping before SMA master import.'); }
				$type = array('memory_match' => 'memory', 'word_scramble' => 'word')[$category['game_code']];
				$this->query('INSERT INTO st_arena_categories(source_code,game_type,name,description,category_id,grade_id) VALUES(?,?,?,?,?,?)', array($source, $type, $category['name'], $category['description'], $subject[0]['id'], $grades[$category['grade_code']]));
			}
			if ( ! $this->db->trans_status() || ! $this->db->trans_commit()) { throw new RuntimeException('SMA master import not committed.'); }
		}
		catch (Throwable $error) { $this->db->trans_rollback(); throw $error; }
	}
	public function down() { throw new RuntimeException('Preserve master records and IDs.'); }
}
