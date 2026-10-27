<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Add only the authored recall cards for an unchanged course manuscript. */
class Add_series_flashcards extends CI_Controller
{
	public function index($grade = '', $mode = 'check')
	{
		if (PHP_SAPI !== 'cli' || ENVIRONMENT !== 'development' || ! in_array($mode, array('check', 'apply'), TRUE)) { show_404(); exit(1); }
		if ($mode === 'apply' && getenv('STUDY_CARDS_SERIES') !== $grade) { throw new RuntimeException('Card gate disabled.'); }
		$this->load->library('Curriculum_series');
		$courses = $this->curriculum_series->load($grade);
		$this->load->database(); $this->db->db_debug = FALSE;
		if ( ! in_array($this->db->database, array('db_study', 'study_qa_monitor_sso039'), TRUE)) { throw new RuntimeException('Database denied.'); }
		$lock = 'study_series_'.$grade;
		if ((int) $this->db->query('SELECT GET_LOCK(?,5) locked', array($lock))->row_array()['locked'] !== 1) { throw new RuntimeException('Cards busy.'); }
		$started = FALSE; $counts = array('courses'=>0, 'lessons'=>0, 'decks_new'=>0, 'cards_new'=>0);
		try
		{
			if ( ! $this->db->trans_begin()) { throw new RuntimeException('Transaction unavailable.'); }
			$started = TRUE;
			foreach ($courses as $expected)
			{
				$course = $this->db->where('slug', $expected['slug'])->get('st_courses')->row_array();
				if ( ! $course || $course['status'] !== 'published') { throw new RuntimeException('Course not published: '.$expected['slug']); }
				$audit = $this->db->where('event_type', 'content.series_imported')->where('entity_type', 'course')->where('entity_id', $course['id'])->get('st_audit_events')->row_array();
				$meta = $audit ? json_decode($audit['details_json'], TRUE) : NULL;
				if ( ! $meta || ($meta['grade'] ?? '') !== $grade || ($meta['source_sha256'] ?? '') !== $expected['source_sha256']) { throw new RuntimeException('Provenance mismatch.'); }
				$sections = $this->db->where('course_id', $course['id'])->order_by('sort_order')->order_by('id')->get('st_course_sections')->result_array();
				if (count($sections) !== count($expected['chapters'])) { throw new RuntimeException('Chapter count changed.'); }
				$counts['courses']++;
				foreach ($sections as $si => $section)
				{
					$chapter = $expected['chapters'][$si];
					if ($section['title'] !== $chapter['title']) { throw new RuntimeException('Chapter changed.'); }
					$lessons = $this->db->where('section_id', $section['id'])->order_by('sort_order')->order_by('id')->get('st_lessons')->result_array();
					if (count($lessons) !== 2) { throw new RuntimeException('Lesson count changed.'); }
					foreach ($lessons as $li => $lesson)
					{
						if ($lesson['status'] !== 'published' || $lesson['title'] !== $chapter['lessons'][$li]['title'] || $lesson['body_text'] !== $chapter['lessons'][$li]['body']) { throw new RuntimeException('Lesson manuscript changed.'); }
						$parts = $expected['source_row'][$si] ?? NULL;
						if ( ! $parts || count($parts) !== 8) { throw new RuntimeException('Card source row missing.'); }
						$title = 'Kartu belajar — '.$chapter['title'];
						if (mb_strlen($title, 'UTF-8') > 180) { throw new RuntimeException('Deck title too long.'); }
						$cards = $li === 0
							? array(array($parts[3], $parts[4]), array('Apa gagasan utama dari '.$parts[0].'?', $parts[1]))
							: array(array($parts[6], $parts[7]), array('Situasi apa yang digunakan untuk menerapkan '.$parts[0].'?', $parts[5]));
						foreach ($cards as $card) { if (mb_strlen($card[0], 'UTF-8') > 500 || mb_strlen($card[1], 'UTF-8') > 2000) { throw new RuntimeException('Card text too long.'); } }
						$deck = $this->db->where('lesson_id', $lesson['id'])->get('st_flashcard_decks')->row_array();
						if ($deck)
						{
							if ($deck['title'] !== $title) { throw new RuntimeException('Existing deck conflict.'); }
							$existing = $this->db->where('deck_id', $deck['id'])->order_by('sort_order')->order_by('id')->get('st_flashcards')->result_array();
							if (count($existing) !== 2) { throw new RuntimeException('Existing card count conflict.'); }
							foreach ($existing as $ci => $card) { if ($card['front_text'] !== $cards[$ci][0] || $card['back_text'] !== $cards[$ci][1]) { throw new RuntimeException('Existing card conflict.'); } }
						}
						else
						{
							$counts['decks_new']++; $counts['cards_new'] += 2;
							if ($mode === 'apply')
							{
								$now = gmdate('Y-m-d H:i:s');
								if ( ! $this->db->insert('st_flashcard_decks', array('lesson_id'=>$lesson['id'], 'title'=>$title, 'description'=>'Dua pertanyaan singkat untuk memeriksa pemahaman materi dan penerapannya.', 'created_by_user_id'=>1, 'created_at'=>$now, 'updated_at'=>$now))) { throw new RuntimeException('Deck insert failed.'); }
								$deck_id = (int) $this->db->insert_id();
								foreach ($cards as $ci => $card) { if ( ! $this->db->insert('st_flashcards', array('deck_id'=>$deck_id, 'front_text'=>$card[0], 'back_text'=>$card[1], 'hint_text'=>'Coba jawab sebelum membalik kartu.', 'sort_order'=>$ci+1, 'created_at'=>$now, 'updated_at'=>$now))) { throw new RuntimeException('Card insert failed.'); } }
							}
						}
						$counts['lessons']++;
					}
				}
			}
			if ( ! $this->db->trans_status()) { throw new RuntimeException('Card transaction failed.'); }
			if ($mode === 'check') { $this->db->trans_rollback(); }
			elseif ( ! $this->db->trans_commit()) { throw new RuntimeException('Commit uncertain.'); }
			$started = FALSE;
			echo json_encode(array('grade'=>$grade, 'mode'=>$mode)+$counts, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR), PHP_EOL;
		}
		catch (Throwable $error) { if ($started) { $this->db->trans_rollback(); } throw $error; }
		finally { $this->db->query('SELECT RELEASE_LOCK(?)', array($lock)); }
	}
}
