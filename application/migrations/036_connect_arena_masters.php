<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Connect_arena_masters extends CI_Migration
{
	private function query($sql, $bindings = array())
	{
		$result = $this->db->query($sql, $bindings);
		if ($result === FALSE) { throw new RuntimeException('Arena master storage unavailable.'); }
		return $result;
	}

	public function up()
	{
		// Level/chapter-based activities have a stage but no invented school grade.
		if ( ! $this->db->field_exists('learning_stage', 'st_arena_categories')) { $this->query('ALTER TABLE st_arena_categories ADD learning_stage VARCHAR(30) NULL'); }
		$this->query('ALTER TABLE st_arena_categories MODIFY grade_id BIGINT UNSIGNED NULL');
		$this->db->trans_begin();
		try
		{
			$this->query('UPDATE st_arena_categories c JOIN st_learning_grades g ON g.id=c.grade_id SET c.learning_stage=g.learning_stage WHERE c.learning_stage IS NULL');
			$files = array('arena_catalog', 'arena_expedition', 'arena_flashcards', 'arena_english', 'arena_quest', 'arena_stories_reviewed');
			foreach ($files as $file)
			{
				$data = json_decode(file_get_contents(APPPATH.'config/'.$file.'.json'), TRUE, 512, JSON_THROW_ON_ERROR);
				foreach ($data['packs'] as $pack)
				{
					if (in_array($pack['type'], array('memory', 'word'), TRUE)) { continue; }
					$meta = $this->metadata($pack);
					$actual = $this->query('SELECT id,game_type,learning_stage FROM st_arena_packs WHERE slug=?', array($pack['slug']))->row_array();
					if ( ! $actual || $actual['game_type'] !== $pack['type'] || $actual['learning_stage'] !== $pack['stage']) { throw new RuntimeException('Arena source mapping requires review.'); }
					$subject = $this->query('SELECT id FROM st_categories WHERE slug=?', array($meta['subject']))->row_array();
					$grade = $meta['grade'] === NULL ? NULL : $this->query('SELECT id,learning_stage FROM st_learning_grades WHERE code=?', array($meta['grade']))->row_array();
					if ( ! $subject || ($meta['grade'] !== NULL && ( ! $grade || $grade['learning_stage'] !== $pack['stage']))) { throw new RuntimeException('Arena subject/grade mapping requires review.'); }
					$source = 'study-arena-'.$pack['type'].'-'.$meta['group'];
					$category = $this->query('SELECT * FROM st_arena_categories WHERE source_code=?', array($source))->row_array();
					if ( ! $category)
					{
						$this->query('INSERT INTO st_arena_categories(source_code,game_type,name,description,category_id,grade_id,learning_stage) VALUES(?,?,?,?,?,?,?)', array($source, $pack['type'], $meta['name'], $meta['description'], $subject['id'], $grade['id'] ?? NULL, $pack['stage']));
						$category = array('id' => $this->db->insert_id(), 'game_type' => $pack['type'], 'learning_stage' => $pack['stage']);
					}
					if ($category['game_type'] !== $pack['type'] || $category['learning_stage'] !== $pack['stage']) { throw new RuntimeException('Existing category conflicts with source.'); }
					$mapping = $this->query('SELECT arena_category_id FROM st_arena_pack_categories WHERE pack_id=?', array($actual['id']))->row_array();
					if ($mapping && (string) $mapping['arena_category_id'] !== (string) $category['id']) { throw new RuntimeException('Preserve existing mapping; review conflict before import.'); }
					if ( ! $mapping) { $this->query('INSERT INTO st_arena_pack_categories(pack_id,arena_category_id) VALUES(?,?)', array($actual['id'], $category['id'])); }
				}
			}
			if ( ! $this->db->trans_status() || ! $this->db->trans_commit()) { throw new RuntimeException('Arena master mapping not committed.'); }
		}
		catch (Throwable $error) { $this->db->trans_rollback(); throw $error; }
	}

	private function metadata($pack)
	{
		$meta = array('group' => $pack['slug'], 'name' => $pack['title'], 'subject' => 'bahasa-inggris', 'grade' => NULL, 'description' => 'Referensi katalog Arena yang sudah diadopsi. Tidak mengubah isi, urutan akses, progres atau aturan hadiah.');
		if ($pack['type'] === 'math' || $pack['type'] === 'expedition')
		{
			$meta['subject'] = 'matematika';
			$meta['name'] = $pack['type'] === 'expedition' ? $pack['skill'] : $pack['title'];
		}
		elseif ($pack['type'] === 'english')
		{
			$chapter = (int) ($pack['chapter'] ?? 0);
			$meta['group'] = $chapter > 0 ? 'chapter-'.$chapter : 'stage-one-evaluation';
			$meta['name'] = $chapter > 0 ? 'Bab '.$chapter.' · '.$pack['chapter_title'] : 'Evaluasi Stage 1';
		}
		elseif ($pack['type'] === 'quest')
		{
			$meta['group'] = $pack['season']; $meta['name'] = $pack['season_title'];
		}
		elseif ($pack['type'] === 'flashcard')
		{
			if (strpos($pack['slug'], 'quest-') === 0) { $meta['group'] = 'quest-vocabulary'; $meta['name'] = 'Kosakata English Quest'; }
			else
			{
				$subjects = array('mtk' => 'matematika', 'bindo' => 'bahasa-indonesia', 'english' => 'bahasa-inggris', 'ipa' => 'ipa', 'ipas' => 'ipas', 'pancasila' => 'ppkn', 'informatika' => 'tik');
				$prefix = explode('_', $pack['code'])[0];
				if ( ! isset($subjects[$prefix]) || ! preg_match('/Kelas ([1-9])\b/u', $pack['title'], $match)) { throw new RuntimeException('Flashcard metadata requires explicit mapping.'); }
				$meta['subject'] = $subjects[$prefix]; $meta['grade'] = ((int) $match[1] <= 6 ? 'sd_' : 'smp_').$match[1];
			}
		}
		elseif ($pack['type'] === 'story')
		{
			$grades = $pack['source_grades']; sort($grades);
			$meta['subject'] = $pack['slug'] === 'cerita-english-green-school' ? 'bahasa-inggris' : 'bahasa-indonesia';
			$meta['group'] = $meta['subject'].'-grades-'.implode('-', $grades);
			$meta['name'] = ($meta['subject'] === 'bahasa-inggris' ? 'Bacaan Inggris' : 'Bacaan pemahaman').' · kelas '.implode(', ', $grades);
			if (count($grades) === 1) { $meta['grade'] = ($grades[0] <= 6 ? 'sd_' : 'smp_').$grades[0]; }
			else { $meta['description'] .= ' Sumber mencakup kelas '.implode(', ', $grades).'; tidak dipaksakan ke satu kelas.'; }
		}
		else { throw new RuntimeException('Unknown Arena master type.'); }
		return $meta;
	}

	public function down() { throw new RuntimeException('Preserve Arena masters, mappings and historical IDs.'); }
}
