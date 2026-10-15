<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Flashcard_model extends CI_Model
{
	private function now_utc()
	{
		return gmdate('Y-m-d H:i:s');
	}

	private function lock_editable_course($course_id, $lesson_id, $actor_user_id, $can_manage_all)
	{
		return $this->db->query(
			"SELECT c.id, c.author_user_id, c.content_version, l.id AS lesson_id
			FROM st_courses c
			JOIN st_course_sections s ON s.course_id = c.id
			JOIN st_lessons l ON l.section_id = s.id
			WHERE c.id = ? AND l.id = ? AND c.status = 'draft'
			FOR UPDATE",
			array((int) $course_id, (int) $lesson_id)
		)->row_array();
	}

	private function audit_content_change($actor_user_id, $course_id, $deck_id, $event_type, $version)
	{
		$this->db->insert('st_audit_events', array(
			'actor_user_id' => (int) $actor_user_id,
			'event_type' => $event_type,
			'entity_type' => 'flashcard_deck',
			'entity_id' => (int) $deck_id,
			'correlation_id' => NULL,
			'details_json' => json_encode(array('course_id' => (int) $course_id, 'content_version' => (int) $version)),
			'created_at' => $this->now_utc()
		));
	}

	private function advance_course_version($course, $actor_user_id)
	{
		$next_version = (int) $course['content_version'] + 1;
		$this->db->where('id', (int) $course['id']);
		$this->db->where('status', 'draft');
		$this->db->update('st_courses', array('content_version' => $next_version, 'updated_by_user_id' => (int) $actor_user_id, 'updated_at' => $this->now_utc()));
		return $next_version;
	}

	public function get_deck_for_editor($course_id, $lesson_id)
	{
		$this->db->select('d.*');
		$this->db->from('st_flashcard_decks d');
		$this->db->join('st_lessons l', 'l.id = d.lesson_id');
		$this->db->join('st_course_sections s', 's.id = l.section_id');
		$this->db->where('s.course_id', (int) $course_id);
		$this->db->where('l.id', (int) $lesson_id);
		return $this->db->get()->row_array();
	}

	public function get_cards_for_editor($deck_id)
	{
		$this->db->where('deck_id', (int) $deck_id);
		$this->db->order_by('sort_order', 'ASC');
		$this->db->order_by('id', 'ASC');
		return $this->db->get('st_flashcards')->result_array();
	}

	public function get_course_decks($course_id)
	{
		$this->db->select('d.id AS deck_id, d.lesson_id, d.title AS deck_title, d.description AS deck_description, f.id AS flashcard_id, f.front_text, f.back_text, f.hint_text, f.sort_order');
		$this->db->from('st_flashcard_decks d');
		$this->db->join('st_lessons l', 'l.id = d.lesson_id');
		$this->db->join('st_course_sections s', 's.id = l.section_id');
		$this->db->join('st_flashcards f', 'f.deck_id = d.id', 'left');
		$this->db->where('s.course_id', (int) $course_id);
		$this->db->order_by('l.sort_order', 'ASC');
		$this->db->order_by('f.sort_order', 'ASC');
		$this->db->order_by('f.id', 'ASC');
		return $this->db->get()->result_array();
	}

	public function save_draft_deck($course_id, $lesson_id, $actor_user_id, $title, $description, $can_manage_all = FALSE)
	{
		$this->db->trans_begin();
		$course = $this->lock_editable_course($course_id, $lesson_id, $actor_user_id, $can_manage_all);
		if ( ! $course || ( ! $can_manage_all && (int) $course['author_user_id'] !== (int) $actor_user_id))
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$now = $this->now_utc();
		$deck = $this->get_deck_for_editor($course_id, $lesson_id);
		if ($deck)
		{
			$this->db->where('id', (int) $deck['id']);
			$this->db->update('st_flashcard_decks', array('title' => trim($title), 'description' => trim($description), 'updated_at' => $now));
			$deck_id = (int) $deck['id'];
			$event = 'content.flashcard_deck_updated';
		}
		else
		{
			$this->db->insert('st_flashcard_decks', array(
				'lesson_id' => (int) $lesson_id,
				'title' => trim($title),
				'description' => trim($description),
				'created_by_user_id' => (int) $actor_user_id,
				'created_at' => $now,
				'updated_at' => $now
			));
			$deck_id = (int) $this->db->insert_id();
			$event = 'content.flashcard_deck_created';
		}

		$version = $this->advance_course_version($course, $actor_user_id);
		$this->audit_content_change($actor_user_id, $course_id, $deck_id, $event, $version);
		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$this->db->trans_commit();
		return $deck_id;
	}

	public function save_draft_card($course_id, $lesson_id, $deck_id, $actor_user_id, $card_id, $front, $back, $hint, $sort_order, $can_manage_all = FALSE)
	{
		$this->db->trans_begin();
		$course = $this->lock_editable_course($course_id, $lesson_id, $actor_user_id, $can_manage_all);
		$deck = $this->get_deck_for_editor($course_id, $lesson_id);
		if ( ! $course || ! $deck || (int) $deck['id'] !== (int) $deck_id || ( ! $can_manage_all && (int) $course['author_user_id'] !== (int) $actor_user_id))
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$now = $this->now_utc();
		if ((int) $card_id > 0)
		{
			$existing = $this->db->where('id', (int) $card_id)->where('deck_id', (int) $deck_id)->get('st_flashcards')->row_array();
			if ( ! $existing)
			{
				$this->db->trans_rollback();
				return FALSE;
			}
			$this->db->where('id', (int) $card_id);
			$this->db->where('deck_id', (int) $deck_id);
			$this->db->update('st_flashcards', array(
				'front_text' => trim($front),
				'back_text' => trim($back),
				'hint_text' => trim($hint) === '' ? NULL : trim($hint),
				'sort_order' => (int) $sort_order,
				'updated_at' => $now
			));
			$event = 'content.flashcard_updated';
		}
		else
		{
			$count = $this->db->where('deck_id', (int) $deck_id)->count_all_results('st_flashcards');
			if ($count >= 100)
			{
				$this->db->trans_rollback();
				return FALSE;
			}
			$this->db->insert('st_flashcards', array(
				'deck_id' => (int) $deck_id,
				'front_text' => trim($front),
				'back_text' => trim($back),
				'hint_text' => trim($hint) === '' ? NULL : trim($hint),
				'sort_order' => (int) $sort_order,
				'created_at' => $now,
				'updated_at' => $now
			));
			$event = 'content.flashcard_created';
		}

		$version = $this->advance_course_version($course, $actor_user_id);
		$this->audit_content_change($actor_user_id, $course_id, $deck_id, $event, $version);
		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$this->db->trans_commit();
		return TRUE;
	}

	public function delete_draft_card($course_id, $lesson_id, $deck_id, $card_id, $actor_user_id, $can_manage_all = FALSE)
	{
		$this->db->trans_begin();
		$course = $this->lock_editable_course($course_id, $lesson_id, $actor_user_id, $can_manage_all);
		$deck = $this->get_deck_for_editor($course_id, $lesson_id);
		if ( ! $course || ! $deck || (int) $deck['id'] !== (int) $deck_id || ( ! $can_manage_all && (int) $course['author_user_id'] !== (int) $actor_user_id))
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$this->db->where('id', (int) $card_id);
		$this->db->where('deck_id', (int) $deck_id);
		$deleted = $this->db->delete('st_flashcards');
		if ( ! $deleted || $this->db->affected_rows() !== 1)
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$version = $this->advance_course_version($course, $actor_user_id);
		$this->audit_content_change($actor_user_id, $course_id, $deck_id, 'content.flashcard_deleted', $version);
		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$this->db->trans_commit();
		return TRUE;
	}

	public function get_published_deck_for_lesson($lesson_id)
	{
		$this->db->select('d.id, d.title, d.description');
		$this->db->from('st_flashcard_decks d');
		$this->db->join('st_lessons l', 'l.id = d.lesson_id');
		$this->db->join('st_course_sections s', 's.id = l.section_id');
		$this->db->join('st_courses c', 'c.id = s.course_id');
		$this->db->where('l.id', (int) $lesson_id);
		$this->db->where('l.status', 'published');
		$this->db->where('c.status', 'published');
		return $this->db->get()->row_array();
	}

	public function get_published_cards($deck_id, $learner_user_id = 0)
	{
		$this->db->select('c.id, c.front_text, c.back_text, c.hint_text, c.sort_order, p.status AS learner_status');
		$this->db->from('st_flashcards c');
		$this->db->join('st_flashcard_progress p', 'p.flashcard_id = c.id AND p.learner_user_id = '.(int) $learner_user_id, 'left', FALSE);
		$this->db->where('c.deck_id', (int) $deck_id);
		$this->db->order_by('c.sort_order', 'ASC');
		$this->db->order_by('c.id', 'ASC');
		return $this->db->get()->result_array();
	}

	public function get_family_flashcard_progress($parent_user_id)
	{
		return $this->db->query(
			"SELECT child.id AS learner_user_id, child.display_name AS learner_name,
				d.id AS deck_id, d.title AS deck_title, c.title AS course_title,
				COUNT(fc.id) AS total_cards,
				SUM(CASE WHEN fp.status = 'known' THEN 1 ELSE 0 END) AS known_cards,
				COUNT(fp.id) AS reviewed_cards,
				MAX(fp.last_reviewed_at) AS last_reviewed_at
			FROM st_family_links family
			JOIN st_users child ON child.id = family.child_user_id AND child.status = 'active'
			JOIN st_flashcard_progress fp ON fp.learner_user_id = child.id
			JOIN st_flashcards fc ON fc.id = fp.flashcard_id
			JOIN st_flashcard_decks d ON d.id = fc.deck_id
			JOIN st_lessons l ON l.id = d.lesson_id AND l.status = 'published'
			JOIN st_course_sections section ON section.id = l.section_id
			JOIN st_courses c ON c.id = section.course_id AND c.status = 'published'
			WHERE family.parent_user_id = ? AND family.status = 'active'
			GROUP BY child.id, child.display_name, d.id, d.title, c.title
			ORDER BY last_reviewed_at DESC, child.id ASC, d.id ASC
			LIMIT 300",
			array((int) $parent_user_id)
		)->result_array();
	}

	public function set_card_status($learner_user_id, $lesson_id, $flashcard_id, $status)
	{
		if ( ! in_array($status, array('learning', 'known'), TRUE))
		{
			return array('ok' => FALSE, 'code' => 400);
		}
		foreach (array($learner_user_id, $lesson_id, $flashcard_id) as $identity)
		{
			if (( ! is_int($identity) && ! is_string($identity)) || ! preg_match('/\A[1-9][0-9]{0,9}\z/', (string) $identity)) { return array('ok' => FALSE, 'code' => 400); }
		}
		$this->load->model('Content_model');
		$this->load->library('Study_transaction');
		return $this->study_transaction->run($this->db, function () use ($learner_user_id, $lesson_id, $flashcard_id, $status) {
			return $this->set_card_status_locked($learner_user_id, $lesson_id, $flashcard_id, $status);
		});
	}

	private function set_card_status_locked($learner_user_id, $lesson_id, $flashcard_id, $status)
	{
		$query = $this->db->query(
			"SELECT f.id, l.is_preview, c.id AS course_id, c.is_free FROM st_flashcards f
			JOIN st_flashcard_decks d ON d.id = f.deck_id
			JOIN st_lessons l ON l.id = d.lesson_id
			JOIN st_course_sections s ON s.id = l.section_id
			JOIN st_courses c ON c.id = s.course_id
			WHERE f.id = ? AND l.id = ? AND l.status = 'published' AND c.status = 'published' FOR UPDATE",
			array((int) $flashcard_id, (int) $lesson_id)
		);
		if ($query === FALSE) { throw new RuntimeException('Card context could not be read.'); }
		$card = $query->row_array();
		if ( ! $card || ! $this->Content_model->can_access_lesson($learner_user_id, $card, TRUE))
		{
			return array('ok' => FALSE, 'code' => 403);
		}
		if ($this->db->trans_status() !== TRUE) { throw new RuntimeException('Card access could not be confirmed.'); }

		$now = $this->now_utc();
		$saved = $this->db->query(
			"INSERT INTO st_flashcard_progress (learner_user_id, flashcard_id, status, review_count, last_reviewed_at)
			VALUES (?, ?, ?, 1, ?)
			ON DUPLICATE KEY UPDATE
			review_count = review_count + IF(status <> VALUES(status), 1, 0),
			last_reviewed_at = IF(status <> VALUES(status), VALUES(last_reviewed_at), last_reviewed_at),
			status = VALUES(status)",
			array((int) $learner_user_id, (int) $flashcard_id, $status, $now)
		);
		if ($saved === FALSE) { throw new RuntimeException('Card progress could not be written.'); }
		return array('ok' => TRUE);
	}
}
