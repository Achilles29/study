<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Content_model extends CI_Model
{
	private function now_utc()
	{
		return gmdate('Y-m-d H:i:s');
	}

	public function list_categories($active_only = FALSE)
	{
		if ($active_only)
		{
			$this->db->where('status', 'active');
		}
		$this->db->order_by('sort_order', 'ASC');
		$this->db->order_by('name', 'ASC');
		return $this->db->get('st_categories')->result_array();
	}

	public function create_category($name, $description)
	{
		$slug = url_title($name, 'dash', TRUE);
		if ($slug === '')
		{
			return FALSE;
		}
		$this->db->where('slug', $slug);
		if ($this->db->count_all_results('st_categories') > 0)
		{
			return FALSE;
		}
		$now = $this->now_utc();
		return $this->db->insert('st_categories', array(
			'name' => trim($name),
			'slug' => $slug,
			'description' => trim($description),
			'sort_order' => 0,
			'status' => 'active',
			'created_at' => $now,
			'updated_at' => $now
		));
	}

	public function list_courses($filters, $limit, $offset, $published_only = FALSE)
	{
		study_grade_where($filters['kelas'] ?? '');
		$this->db->select('c.id, c.grade_id, c.title, c.slug, c.summary, c.target_group, c.age_min, c.age_max, c.estimated_minutes, c.status, c.is_free, c.updated_at, cat.name AS category_name, u.display_name AS author_name');
		$this->db->from('st_courses c');
		$this->db->join('st_categories cat', 'cat.id = c.category_id', 'left');
		$this->db->join('st_users u', 'u.id = c.author_user_id', 'left');
		if ($published_only)
		{
			$this->db->where('c.status', 'published');
			if (isset($filters['category_id']) && (int) $filters['category_id'] > 0)
			{
				$this->db->where('c.category_id', (int) $filters['category_id']);
			}
		}
		elseif (isset($filters['status']) && $filters['status'] !== '')
		{
			$this->db->where('c.status', $filters['status']);
		}
		if (isset($filters['q']) && $filters['q'] !== '')
		{
			$this->db->group_start()->like('c.title', $filters['q'])->or_like('c.summary', $filters['q'])->group_end();
		}
		if ($published_only)
		{
			$this->db->order_by('c.published_at', 'DESC');
		}
		else
		{
			$this->db->order_by('c.updated_at', 'DESC');
		}
		$this->db->order_by('c.id', 'DESC');
		$this->db->limit((int) $limit, (int) $offset);
		return $this->db->get()->result_array();
	}

	public function count_courses($filters, $published_only = FALSE)
	{
		study_grade_where($filters['kelas'] ?? '');
		$this->db->from('st_courses c');
		if ($published_only)
		{
			$this->db->where('c.status', 'published');
			if (isset($filters['category_id']) && (int) $filters['category_id'] > 0)
			{
				$this->db->where('c.category_id', (int) $filters['category_id']);
			}
		}
		elseif (isset($filters['status']) && $filters['status'] !== '')
		{
			$this->db->where('c.status', $filters['status']);
		}
		if (isset($filters['q']) && $filters['q'] !== '')
		{
			$this->db->group_start()->like('c.title', $filters['q'])->or_like('c.summary', $filters['q'])->group_end();
		}
		return $this->db->count_all_results();
	}

	public function get_course($course_id)
	{
		$this->db->select('c.*, cat.name AS category_name, u.display_name AS author_name');
		$this->db->from('st_courses c');
		$this->db->join('st_categories cat', 'cat.id = c.category_id', 'left');
		$this->db->join('st_users u', 'u.id = c.author_user_id', 'left');
		$this->db->where('c.id', (int) $course_id);
		return $this->db->get()->row_array();
	}

	public function get_course_by_slug($slug)
	{
		$this->db->select('c.*, cat.name AS category_name');
		$this->db->from('st_courses c');
		$this->db->join('st_categories cat', 'cat.id = c.category_id', 'left');
		$this->db->where('c.slug', $slug);
		$this->db->where('c.status', 'published');
		return $this->db->get()->row_array();
	}

	public function get_course_lessons($course_id, $published_only = FALSE)
	{
		$this->db->select('l.id, l.title, l.slug, l.body_text, l.estimated_minutes, l.sort_order, l.status, l.content_version, l.is_preview, l.rights_status, l.source_note, s.title AS section_title, s.sort_order AS section_order');
		$this->db->from('st_lessons l');
		$this->db->join('st_course_sections s', 's.id = l.section_id');
		$this->db->where('s.course_id', (int) $course_id);
		if ($published_only)
		{
			$this->db->where('l.status', 'published');
		}
		$this->db->order_by('s.sort_order', 'ASC');
		$this->db->order_by('s.id', 'ASC');
		$this->db->order_by('l.sort_order', 'ASC');
		$this->db->order_by('l.id', 'ASC');
		return $this->db->get()->result_array();
	}

	public function get_course_lesson($course_id, $lesson_id)
	{
		$this->db->select('l.*, s.title AS section_title, s.course_id, c.title AS course_title, c.slug AS course_slug');
		$this->db->from('st_lessons l');
		$this->db->join('st_course_sections s', 's.id = l.section_id');
		$this->db->join('st_courses c', 'c.id = s.course_id');
		$this->db->where('c.id', (int) $course_id);
		$this->db->where('l.id', (int) $lesson_id);
		return $this->db->get()->row_array();
	}

	public function get_published_lesson_index($course_id)
	{
		$this->db->select('l.id, l.title, l.slug, l.estimated_minutes, l.sort_order, l.status, l.is_preview, s.course_id AS course_id, c.is_free, s.title AS section_title, s.sort_order AS section_order');
		$this->db->from('st_lessons l');
		$this->db->join('st_course_sections s', 's.id = l.section_id');
		$this->db->join('st_courses c', 'c.id = s.course_id');
		$this->db->where('s.course_id', (int) $course_id);
		$this->db->where('l.status', 'published');
		$this->db->order_by('s.sort_order', 'ASC');
		$this->db->order_by('s.id', 'ASC');
		$this->db->order_by('l.sort_order', 'ASC');
		$this->db->order_by('l.id', 'ASC');
		return $this->db->get()->result_array();
	}

	public function get_next_accessible_lesson($user_id, $course_id, $current_lesson_id)
	{
		$lessons = $this->get_published_lesson_index($course_id);
		$found_current = FALSE;
		foreach ($lessons as $lesson)
		{
			if ($found_current && $this->can_access_lesson($user_id, $lesson))
			{
				return $lesson;
			}
			if ((int) $lesson['id'] === (int) $current_lesson_id)
			{
				$found_current = TRUE;
			}
		}
		return NULL;
	}

	public function get_published_lesson($slug)
	{
		$this->db->select('l.id, l.title, l.slug, l.body_text, l.estimated_minutes, l.is_preview, c.id AS course_id, c.title AS course_title, c.slug AS course_slug, c.is_free, s.title AS section_title');
		$this->db->from('st_lessons l');
		$this->db->join('st_course_sections s', 's.id = l.section_id');
		$this->db->join('st_courses c', 'c.id = s.course_id');
		$this->db->where('l.slug', $slug);
		$this->db->where('l.status', 'published');
		$this->db->where('c.status', 'published');
		return $this->db->get()->row_array();
	}

	public function can_access_course($user_id, $course, $lock = FALSE)
	{
		if ($this->course_is_blocked($user_id, $course, $lock)) { return FALSE; }
		if ((int) $course['is_free'] === 1)
		{
			return TRUE;
		}
		if ((int) $user_id < 1)
		{
			return FALSE;
		}
		$this->load->model('Commerce_access_model');
		$purchase_course_id = isset($course['course_id']) ? $course['course_id'] : $course['id'];
		$this->load->model('Points_model');
		if ($this->Points_model->course($user_id, $purchase_course_id, $lock)) { return TRUE; }
		if ($this->Commerce_access_model->course($user_id, $purchase_course_id, $lock)) { return TRUE; }

		$sql = "SELECT ce.id
			FROM st_course_enrollments ce
			LEFT JOIN st_entitlements e ON e.id = ce.entitlement_id
			WHERE ce.learner_user_id = ? AND ce.course_id = ? AND ce.status = 'active'
			AND (ce.source_type IN ('free','manual') OR (ce.source_type = 'package'
				AND e.status = 'active' AND e.starts_at <= UTC_TIMESTAMP()
				AND (e.ends_at IS NULL OR e.ends_at > UTC_TIMESTAMP())))
			LIMIT 1";
		$course_id = isset($course['course_id']) ? $course['course_id'] : $course['id'];
		$query = $this->db->query($sql.($lock === TRUE ? ' FOR UPDATE' : ''), array((int) $user_id, (int) $course_id));
		if ($query === FALSE) { throw new RuntimeException('Course access could not be read.'); }
		return $query->num_rows() > 0;
	}

	public function can_access_lesson($user_id, $lesson, $lock = FALSE)
	{
		if ($this->course_is_blocked($user_id, $lesson, $lock)) { return FALSE; }
		return (int) $lesson['is_preview'] === 1 || $this->can_access_course($user_id, $lesson, $lock);
	}

	private function course_is_blocked($user_id, $course, $lock)
	{
		if ((int) $user_id < 1) { return FALSE; }
		$q = $this->db->query('SELECT blocked FROM st_course_access_blocks WHERE user_id=? AND course_id=?'.($lock ? ' FOR UPDATE' : ''), array((int) $user_id, (int) ($course['course_id'] ?? $course['id'])));
		if ($q === FALSE) { throw new RuntimeException('Access restriction unavailable.'); }
		return (bool) ($q->row_array()['blocked'] ?? FALSE);
	}

	public function complete_lesson($learner_user_id, $lesson_id)
	{
		return $this->write_reading_progress($learner_user_id, $lesson_id, 10000, TRUE);
	}

	public function save_lesson_position($learner_user_id, $lesson_id, $position)
	{
		if ( ! is_int($position) || $position < 0 || $position > 10000) { return array('ok' => FALSE, 'code' => 400); }
		return $this->write_reading_progress($learner_user_id, $lesson_id, $position, FALSE);
	}

	private function write_reading_progress($learner_user_id, $lesson_id, $position, $complete)
	{
		$this->load->library('Study_transaction');
		return $this->study_transaction->run($this->db, function () use ($learner_user_id, $lesson_id, $position, $complete) {
			return $this->write_reading_progress_locked($learner_user_id, $lesson_id, $position, $complete);
		});
	}

	private function write_reading_progress_locked($learner_user_id, $lesson_id, $position, $complete)
	{
		$query = $this->db->query(
			"SELECT l.id, l.is_preview, c.id AS course_id, c.is_free
			FROM st_lessons l
			JOIN st_course_sections s ON s.id = l.section_id
			JOIN st_courses c ON c.id = s.course_id
			WHERE l.id = ? AND l.status = 'published' AND c.status = 'published' FOR UPDATE",
			array((int) $lesson_id)
		);
		if ($query === FALSE) { throw new RuntimeException('Reading lesson could not be read.'); }
		$lesson = $query->row_array();
		if ( ! $lesson || ! $this->can_access_lesson($learner_user_id, $lesson, TRUE))
		{
			return array('ok' => FALSE, 'code' => 403);
		}
		if ($this->db->trans_status() !== TRUE) { throw new RuntimeException('Reading access could not be confirmed.'); }

		$now = $this->now_utc();
		if ($complete)
		{
			$sql = "INSERT INTO st_lesson_progress
				(learner_user_id, lesson_id, status, last_position, started_at, completed_at, updated_at)
				VALUES (?, ?, 'completed', 10000, ?, ?, ?)
				ON DUPLICATE KEY UPDATE status = 'completed', last_position = 10000,
				started_at = COALESCE(started_at, ?), completed_at = COALESCE(completed_at, ?), updated_at = ?";
			$parameters = array((int) $learner_user_id, (int) $lesson_id, $now, $now, $now, $now, $now, $now);
		}
		else
		{
			$sql = "INSERT INTO st_lesson_progress
			(learner_user_id, lesson_id, status, last_position, started_at, updated_at)
			VALUES (?, ?, 'in_progress', ?, ?, ?)
			ON DUPLICATE KEY UPDATE
			last_position = IF(status = 'completed', last_position, ?),
			status = IF(status = 'completed', 'completed', 'in_progress'),
			started_at = COALESCE(started_at, ?), updated_at = ?";
			$parameters = array(
			(int) $learner_user_id, (int) $lesson_id, (int) $position, $now, $now,
			(int) $position, $now, $now
			);
		}
		if ($this->db->query($sql, $parameters) === FALSE) { throw new RuntimeException('Reading progress could not be written.'); }
		return array('ok' => TRUE);
	}

	public function get_lesson_progress($learner_user_id, $lesson_id)
	{
		$this->db->where('learner_user_id', (int) $learner_user_id);
		$this->db->where('lesson_id', (int) $lesson_id);
		return $this->db->get('st_lesson_progress')->row_array();
	}

	public function get_course_lesson_progress_map($learner_user_id, $course_id)
	{
		if (( ! is_int($learner_user_id) && ! is_string($learner_user_id)) || ( ! is_int($course_id) && ! is_string($course_id))) { return array(); }
		$learner_user_id = filter_var($learner_user_id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
		$course_id = filter_var($course_id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
		if ($learner_user_id === FALSE || $course_id === FALSE) { return array(); }

		// Saved status is not an access grant; the public index controls readable links.
		$sql = "SELECT p.lesson_id, p.status
			FROM st_lesson_progress p
			JOIN st_lessons l ON l.id = p.lesson_id AND l.status = 'published'
			JOIN st_course_sections s ON s.id = l.section_id
			JOIN st_courses c ON c.id = s.course_id AND c.status = 'published'
			WHERE p.learner_user_id = ? AND c.id = ?";
		$debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		try
		{
			$query = $this->db->query($sql, array($learner_user_id, $course_id));
			if ($query === FALSE) { return FALSE; }
			$rows = $query->result_array();
			if ( ! is_array($rows)) { throw new UnexpectedValueException('Invalid course progress result.'); }
			$progress = array();
			foreach ($rows as $row)
			{
				if ( ! is_array($row) || ! isset($row['lesson_id'], $row['status']) || ! in_array($row['status'], array('not_started', 'in_progress', 'completed'), TRUE)) { throw new UnexpectedValueException('Invalid course progress row.'); }
				$lesson_id = filter_var($row['lesson_id'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
				if ($lesson_id === FALSE || isset($progress[$lesson_id])) { throw new UnexpectedValueException('Invalid course progress lesson.'); }
				$progress[$lesson_id] = $row['status'];
			}
			return $progress;
		}
		catch (Throwable $error)
		{
			log_message('error', 'Course lesson progress could not be read.');
			return FALSE;
		}
		finally { $this->db->db_debug = $debug; }
	}

	public function get_resumable_lessons($learner_user_id)
	{
		if ( ! is_int($learner_user_id) && ! is_string($learner_user_id)) { return array(); }
		$learner_user_id = filter_var($learner_user_id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
		if ($learner_user_id === FALSE) { return array(); }
		$this->load->model('Commerce_access_model');
		$purchased_access = $this->Commerce_access_model->course_predicate($learner_user_id, 'c.id');
		$this->load->model('Points_model'); $point_access = $this->Points_model->course_predicate($learner_user_id, 'c.id');

		$sql = "SELECT l.id AS lesson_id, l.title AS lesson_title, l.slug AS lesson_slug,
			c.title AS course_title, c.slug AS course_slug, p.last_position, p.updated_at AS last_activity_at
			FROM st_lesson_progress p
			JOIN st_lessons l ON l.id = p.lesson_id AND l.status = 'published'
			JOIN st_course_sections s ON s.id = l.section_id
			JOIN st_courses c ON c.id = s.course_id AND c.status = 'published'
			WHERE p.learner_user_id = ? AND p.status = 'in_progress'
			AND NOT EXISTS(SELECT 1 FROM st_course_access_blocks b WHERE b.user_id=p.learner_user_id AND b.course_id=c.id AND b.blocked=1)
			AND (l.is_preview = 1 OR c.is_free = 1 OR EXISTS (SELECT 1 FROM st_point_redemptions pr WHERE ".$point_access.") OR EXISTS (SELECT 1 FROM st_commerce_grants g WHERE ".$purchased_access.") OR EXISTS (SELECT 1
				FROM st_course_enrollments ce
				LEFT JOIN st_entitlements e ON e.id = ce.entitlement_id
				WHERE ce.learner_user_id = p.learner_user_id AND ce.course_id = c.id AND ce.status = 'active'
				AND (ce.source_type IN ('free','manual') OR (ce.source_type = 'package'
					AND e.status = 'active' AND e.starts_at <= UTC_TIMESTAMP()
					AND (e.ends_at IS NULL OR e.ends_at > UTC_TIMESTAMP())))))
			ORDER BY p.updated_at DESC, p.lesson_id DESC
			LIMIT 3";
		$debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		try
		{
			$query = $this->db->query($sql, array($learner_user_id));
			return $query === FALSE ? FALSE : $query->result_array();
		}
		catch (Throwable $error)
		{
			log_message('error', 'Resumable lessons could not be read.');
			return FALSE;
		}
		finally { $this->db->db_debug = $debug; }
	}

	public function get_learner_progress($learner_user_id)
	{
		$this->db->select("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_lessons", FALSE);
		$this->db->select("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_lessons", FALSE);
		$this->db->where('learner_user_id', (int) $learner_user_id);
		$summary = $this->db->get('st_lesson_progress')->row_array();
		return array(
			'completed_lessons' => (int) $summary['completed_lessons'],
			'in_progress_lessons' => (int) $summary['in_progress_lessons']
		);
	}

	public function get_learner_course_progress($learner_user_id)
	{
		$sql = "SELECT c.id AS course_id, c.slug AS course_slug, c.title AS course_title,
			SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) AS completed_lessons,
			SUM(CASE WHEN p.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_lessons,
			MAX(p.updated_at) AS last_activity_at
			FROM st_lesson_progress p
			JOIN st_lessons l ON l.id = p.lesson_id AND l.status = 'published'
			JOIN st_course_sections s ON s.id = l.section_id
			JOIN st_courses c ON c.id = s.course_id AND c.status = 'published'
			WHERE p.learner_user_id = ? AND p.status IN ('completed','in_progress')
			GROUP BY c.id, c.slug, c.title
			ORDER BY last_activity_at DESC, c.title ASC";
		return $this->db->query($sql, array((int) $learner_user_id))->result_array();
	}

	public function get_family_course_progress($parent_user_id)
	{
		$sql = "SELECT u.id AS learner_user_id, c.id AS course_id, c.slug AS course_slug, c.title AS course_title,
			SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) AS completed_lessons,
			SUM(CASE WHEN p.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_lessons,
			MAX(p.updated_at) AS last_activity_at
			FROM st_family_links f
			JOIN st_users u ON u.id = f.child_user_id AND u.status = 'active' AND u.account_kind = 'child'
			JOIN st_lesson_progress p ON p.learner_user_id = u.id AND p.status IN ('completed','in_progress')
			JOIN st_lessons l ON l.id = p.lesson_id AND l.status = 'published'
			JOIN st_course_sections s ON s.id = l.section_id
			JOIN st_courses c ON c.id = s.course_id AND c.status = 'published'
			WHERE f.parent_user_id = ? AND f.status = 'active'
			GROUP BY u.id, c.id, c.slug, c.title
			ORDER BY u.id ASC, last_activity_at DESC, c.title ASC";
		return $this->db->query($sql, array((int) $parent_user_id))->result_array();
	}

	public function valid_grade($id, $target)
	{
		if ($id === NULL || $id === '') { return TRUE; }
		if (!is_string($id) || !preg_match('/\\A[1-9][0-9]{0,8}\\z/', $id)) { return FALSE; }
		foreach(study_grades() as $g) { if ((string)$g['id'] === $id) { return $target === 'all_ages' || $target === $g['learning_stage'] || ($target === 'secondary' && $g['learning_stage'] === 'upper_secondary'); } }
		return FALSE;
	}

	public function create_course($data, $author_user_id)
	{
		if (!$this->valid_grade($data['grade_id'] ?? NULL, $data['target_group'])) { return FALSE; }
		$slug_base = url_title($data['title'], 'dash', TRUE);
		if ($slug_base === '')
		{
			return FALSE;
		}
		$slug = $slug_base;
		$suffix = 2;
		do {
			$this->db->where('slug', $slug);
			$exists = $this->db->count_all_results('st_courses') > 0;
			if ($exists)
			{
				$slug = $slug_base.'-'.$suffix++;
			}
		} while ($exists && $suffix < 1000);

		$now = $this->now_utc();
		$this->db->trans_begin();
		$this->db->insert('st_courses', array(
			'grade_id' => empty($data['grade_id']) ? NULL : (int)$data['grade_id'],
			'category_id' => in_array((string) $data['category_id'], array('', '0'), TRUE) ? NULL : (int) $data['category_id'],
			'author_user_id' => (int) $author_user_id,
			'updated_by_user_id' => (int) $author_user_id,
			'title' => trim($data['title']),
			'slug' => $slug,
			'summary' => trim($data['summary']),
			'learning_objectives' => trim($data['learning_objectives']),
			'prerequisites' => isset($data['prerequisites']) ? trim($data['prerequisites']) : '',
			'target_group' => $data['target_group'],
			'age_min' => $data['age_min'] !== '' ? (int) $data['age_min'] : NULL,
			'age_max' => $data['age_max'] !== '' ? (int) $data['age_max'] : NULL,
			'estimated_minutes' => $data['estimated_minutes'] !== '' ? (int) $data['estimated_minutes'] : NULL,
			'status' => 'draft',
			'content_version' => 1,
			'rights_status' => $data['rights_status'],
			'source_note' => trim($data['source_note']),
			'is_free' => ! empty($data['is_free']) ? 1 : 0,
			'created_at' => $now,
			'updated_at' => $now
		));

		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$course_id = (int) $this->db->insert_id();
		$this->db->insert('st_course_sections', array(
			'course_id' => $course_id,
			'title' => 'Bagian 1',
			'sort_order' => 1,
			'created_at' => $now,
			'updated_at' => $now
		));
		$this->audit($author_user_id, 'content.course_created', 'course', $course_id, array('status' => 'draft'));
		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$this->db->trans_commit();
		return $course_id;
	}

	public function update_course_draft($course_id, $actor_user_id, $data, $can_manage_all = FALSE, $expected_version = NULL)
	{
		if (!$this->valid_grade($data['grade_id'] ?? NULL, $data['target_group'])) { return FALSE; }
		$this->db->trans_begin();
		$course = $this->db->query('SELECT id, status, author_user_id, content_version, grade_id FROM st_courses WHERE id = ? FOR UPDATE', array((int) $course_id))->row_array();
		if ( ! $course || $course['status'] !== 'draft' || ( ! $can_manage_all && (int) $course['author_user_id'] !== (int) $actor_user_id)
			|| ($expected_version !== NULL && (!is_int($expected_version) || $expected_version !== (int)$course['content_version'])))
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$now = $this->now_utc();
		$this->db->where('id', (int) $course_id);
		$this->db->where('status', 'draft');
		$this->db->update('st_courses', array(
			'grade_id' => array_key_exists('grade_id', $data) ? (empty($data['grade_id']) ? NULL : (int)$data['grade_id']) : $course['grade_id'],
			'category_id' => in_array((string) $data['category_id'], array('', '0'), TRUE) ? NULL : (int) $data['category_id'],
			'title' => trim($data['title']),
			'summary' => trim($data['summary']),
			'learning_objectives' => trim($data['learning_objectives']),
			'prerequisites' => isset($data['prerequisites']) ? trim($data['prerequisites']) : '',
			'target_group' => $data['target_group'],
			'age_min' => $data['age_min'] !== '' ? (int) $data['age_min'] : NULL,
			'age_max' => $data['age_max'] !== '' ? (int) $data['age_max'] : NULL,
			'estimated_minutes' => (int) $data['estimated_minutes'],
			'rights_status' => $data['rights_status'],
			'source_note' => trim($data['source_note']),
			'is_free' => ! empty($data['is_free']) ? 1 : 0,
			'content_version' => (int) $course['content_version'] + 1,
			'updated_by_user_id' => (int) $actor_user_id,
			'updated_at' => $now
		));
		$this->audit($actor_user_id, 'content.course_updated', 'course', $course_id, array('previous_version' => (int) $course['content_version'], 'next_version' => (int) $course['content_version'] + 1));
		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$this->db->trans_commit();
		return TRUE;
	}

	public function create_lesson($course_id, $data, $actor_user_id)
	{
		$this->db->trans_begin();
		$course = $this->db->query('SELECT id, status, content_version FROM st_courses WHERE id = ? FOR UPDATE', array((int) $course_id))->row_array();
		if ( ! $course || $course['status'] !== 'draft' || (int) $course['content_version'] >= 2147483647)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->where('course_id', (int) $course_id);
		$this->db->order_by('sort_order', 'ASC');
		$this->db->order_by('id', 'ASC');
		$section = $this->db->get('st_course_sections')->row_array();
		if ( ! $section)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$slug_base = url_title($data['title'], 'dash', TRUE);
		$slug = $slug_base.'-'.substr(hash('sha256', $course_id.'|'.$data['title'].'|'.microtime(TRUE)), 0, 8);
		$now = $this->now_utc();
		$this->db->insert('st_lessons', array(
			'section_id' => (int) $section['id'],
			'title' => trim($data['title']),
			'slug' => $slug,
			'body_text' => trim($data['body_text']),
			'estimated_minutes' => (int) $data['estimated_minutes'],
			'sort_order' => 1 + $this->count_lessons($course_id),
			'status' => 'draft',
			'content_version' => 1,
			'is_preview' => ! empty($data['is_preview']) ? 1 : 0,
			'source_note' => trim($data['source_note']),
			'rights_status' => $data['rights_status'],
			'created_at' => $now,
			'updated_at' => $now
		));
		$lesson_id = (int) $this->db->insert_id();
		$this->db->where('id', (int) $course_id)->update('st_courses', array('content_version' => (int) $course['content_version'] + 1, 'updated_by_user_id' => (int) $actor_user_id, 'updated_at' => $now));
		$this->audit($actor_user_id, 'content.lesson_created', 'lesson', $lesson_id, array('course_id' => (int) $course_id, 'previous_course_version' => (int) $course['content_version'], 'next_course_version' => (int) $course['content_version'] + 1));
		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$this->db->trans_commit();
		return $lesson_id;
	}

	public function update_lesson_draft($course_id, $lesson_id, $actor_user_id, $data, $revision, $can_manage_all = FALSE)
	{
		if (( ! is_string($revision) && ! is_int($revision)) || ! preg_match('/\A[1-9][0-9]{0,9}\z/', (string) $revision) || (int) $revision > 2147483647)
		{
			return 'invalid';
		}
		$this->db->trans_begin();
		$course = $this->db->query('SELECT id, status, author_user_id, content_version, grade_id FROM st_courses WHERE id = ? FOR UPDATE', array((int) $course_id))->row_array();
		if ( ! $course || $course['status'] !== 'draft' || ( ! $can_manage_all && (int) $course['author_user_id'] !== (int) $actor_user_id))
		{
			$this->db->trans_rollback();
			return 'denied';
		}
		$lesson = $this->db->query(
			' SELECT l.id, l.content_version FROM st_lessons l JOIN st_course_sections s ON s.id = l.section_id WHERE l.id = ? AND s.course_id = ? FOR UPDATE',
			array((int) $lesson_id, (int) $course_id)
		)->row_array();
		if ( ! $lesson)
		{
			$this->db->trans_rollback();
			return 'denied';
		}
		if ((int) $lesson['content_version'] !== (int) $revision || (int) $revision >= 2147483647 || (int) $course['content_version'] >= 2147483647)
		{
			$this->db->trans_rollback();
			return 'conflict';
		}

		$now = $this->now_utc();
		$this->db->where('id', (int) $lesson_id);
		$this->db->update('st_lessons', array(
			'title' => trim($data['title']),
			'body_text' => trim($data['body_text']),
			'estimated_minutes' => (int) $data['estimated_minutes'],
			'is_preview' => ! empty($data['is_preview']) ? 1 : 0,
			'source_note' => trim($data['source_note']),
			'rights_status' => $data['rights_status'],
			'content_version' => (int) $lesson['content_version'] + 1,
			'updated_at' => $now
		));
		$this->db->where('id', (int) $course_id)->update('st_courses', array('content_version' => (int) $course['content_version'] + 1, 'updated_by_user_id' => (int) $actor_user_id, 'updated_at' => $now));
		$this->audit($actor_user_id, 'content.lesson_updated', 'lesson', $lesson_id, array('course_id' => (int) $course_id, 'previous_version' => (int) $lesson['content_version'], 'next_version' => (int) $lesson['content_version'] + 1, 'previous_course_version' => (int) $course['content_version'], 'next_course_version' => (int) $course['content_version'] + 1));
		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return 'unavailable';
		}
		$this->db->trans_commit();
		return 'ok';
	}

	private function count_lessons($course_id)
	{
		$this->db->from('st_lessons l');
		$this->db->join('st_course_sections s', 's.id = l.section_id');
		$this->db->where('s.course_id', (int) $course_id);
		return $this->db->count_all_results();
	}

	public function submit_for_review($course_id, $actor_user_id, $can_manage_all = FALSE)
	{
		$this->db->trans_begin();
		$course = $this->db->query('SELECT id, status, author_user_id, content_version, rights_status, source_note FROM st_courses WHERE id = ? FOR UPDATE', array((int) $course_id))->row_array();
		if ( ! $course || $course['status'] !== 'draft' || ( ! $can_manage_all && (int) $course['author_user_id'] !== (int) $actor_user_id))
		{
			$this->db->trans_rollback();
			return array('ok' => FALSE, 'message' => 'Kursus tidak dapat diajukan untuk ditinjau.');
		}

		$lessons = $this->get_course_lessons($course_id);
		if (count($lessons) === 0)
		{
			$this->db->trans_rollback();
			return array('ok' => FALSE, 'message' => 'Tambahkan minimal satu pelajaran sebelum mengajukan kursus.');
		}
		foreach ($lessons as $lesson)
		{
			if (trim($lesson['body_text']) === '' || ! in_array($lesson['rights_status'], array('owned', 'licensed', 'public_domain'), TRUE) || trim($lesson['source_note']) === '')
			{
				$this->db->trans_rollback();
				return array('ok' => FALSE, 'message' => 'Lengkapi isi, sumber, dan status hak penggunaan setiap pelajaran terlebih dahulu.');
			}
		}
		if ( ! $this->flashcard_decks_are_complete($course_id))
		{
			$this->db->trans_rollback();
			return array('ok' => FALSE, 'message' => 'Setiap set kartu belajar harus memiliki minimal satu kartu sebelum kursus diajukan.');
		}
		$this->load->model('Question_model');
		if ( ! $this->Question_model->ready_for_review($course_id))
		{
			$this->db->trans_rollback();
			return array('ok' => FALSE, 'message' => 'Lengkapi soal latihan: empat pilihan berbeda, satu jawaban benar, pembahasan, sumber, dan hak penggunaan.');
		}
		$this->load->model('Lesson_media_model');
		if ( ! $this->Lesson_media_model->ready_for_review($course_id))
		{
			$this->db->trans_rollback();
			return array('ok' => FALSE, 'message' => 'Periksa gambar pelajaran: berkas, teks alternatif, sumber, dan hak penggunaannya harus lengkap dan tersedia.');
		}
		if ( ! in_array($course['rights_status'], array('owned', 'licensed', 'public_domain'), TRUE) || trim($course['source_note']) === '')
		{
			$this->db->trans_rollback();
			return array('ok' => FALSE, 'message' => 'Lengkapi sumber dan status hak penggunaan kursus terlebih dahulu.');
		}

		$this->db->where('id', (int) $course_id);
		$this->db->where('status', 'draft');
		$updated = $this->db->update('st_courses', array('status' => 'review', 'updated_by_user_id' => (int) $actor_user_id, 'updated_at' => $this->now_utc()));
		if ($updated)
		{
			$this->db->where('course_id', (int) $course_id)->where('visibility', 'shared')->where('target_child_user_id', NULL)->update('st_questions', array('status' => 'review', 'updated_at' => $this->now_utc()));
			$this->audit($actor_user_id, 'content.course_submitted', 'course', $course_id, array('content_version' => (int) $course['content_version']));
		}
		if ($updated && $this->db->trans_status() !== FALSE)
		{
			$this->db->trans_commit();
			return array('ok' => TRUE, 'message' => '');
		}
		$this->db->trans_rollback();
		return array('ok' => FALSE, 'message' => 'Status kursus tidak berhasil diperbarui.');
	}

	public function review_course($course_id, $reviewer_user_id, $decision, $notes, $quality_confirmed = FALSE, $expected_version = NULL)
	{
		if ( ! in_array($decision, array('approved','changes_requested'), TRUE))
		{
			return array('ok' => FALSE, 'code' => 409, 'message' => 'Pilihan review tidak valid.');
		}

		$this->load->library('study_transaction');
		$result = $this->study_transaction->run($this->db, function () use ($course_id, $reviewer_user_id, $decision, $notes, $quality_confirmed, $expected_version) {
			$actor = $this->db->query("SELECT u.id,r.role_code FROM st_users u JOIN st_user_roles ur ON ur.user_id=u.id JOIN st_roles r ON r.id=ur.role_id WHERE u.id=? AND u.status='active' AND r.role_code IN ('system_admin','content_editor') FOR UPDATE", array($reviewer_user_id))->result_array();
			if ( ! $actor) { return array('ok' => FALSE, 'code' => 403, 'message' => 'Akses review tidak tersedia.'); }
			$course = $this->db->query('SELECT id, status, author_user_id, content_version, rights_status, source_note FROM st_courses WHERE id = ? FOR UPDATE', array((int) $course_id))->row_array();
			if ( ! $course || $course['status'] !== 'review')
			{
				return array('ok' => FALSE, 'code' => 409, 'message' => 'Kursus tidak dapat ditinjau oleh akun ini.');
			}

			$self_review = (int) $course['author_user_id'] === (int) $reviewer_user_id;
			if ($expected_version !== NULL && (!is_int($expected_version) || $expected_version !== (int) $course['content_version']))
			{
				return array('ok' => FALSE, 'code' => 409, 'message' => 'Versi konten berubah setelah diperiksa. Tinjau ulang sebelum menerbitkan.');
			}
			$settings_revision = NULL;
			if ($self_review)
			{
				$setting = $this->db->query("SELECT values_json,revision FROM st_site_settings WHERE section='pembelajaran' FOR UPDATE")->row_array();
				$values = $setting ? json_decode($setting['values_json'], TRUE) : array();
				if ( ! in_array('system_admin', array_column($actor, 'role_code'), TRUE) || ($values['solo_review'] ?? '0') !== '1' || $quality_confirmed !== TRUE || ! is_string($notes) || mb_strlen(trim($notes), 'UTF-8') < 10)
				{
					return array('ok' => FALSE, 'code' => 403, 'message' => 'Review sendiri membutuhkan mode operator tunggal, Superadmin, ketiga checklist kualitas dan catatan minimal 10 karakter.');
				}
				$settings_revision = (int) $setting['revision'];
			}
			$lessons = $this->get_course_lessons($course_id);
			if ($decision === 'approved')
			{
				if ( ! in_array($course['rights_status'], array('owned', 'licensed', 'public_domain'), TRUE) || trim($course['source_note']) === '' || count($lessons) === 0)
				{
					return array('ok' => FALSE, 'code' => 409, 'message' => 'Konten kursus atau catatan hak penggunaan belum lengkap.');
				}
				foreach ($lessons as $lesson)
				{
					if (trim($lesson['body_text']) === '' || ! in_array($lesson['rights_status'], array('owned', 'licensed', 'public_domain'), TRUE) || trim($lesson['source_note']) === '')
					{
							return array('ok' => FALSE, 'code' => 409, 'message' => 'Isi, sumber, atau hak penggunaan pelajaran belum lengkap.');
					}
				}
				if ( ! $this->flashcard_decks_are_complete($course_id))
				{
					return array('ok' => FALSE, 'code' => 409, 'message' => 'Setiap set kartu belajar harus memiliki minimal satu kartu sebelum kursus diterbitkan.');
				}
				$this->load->model('Question_model');
				if ( ! $this->Question_model->ready_for_review($course_id))
				{
					return array('ok' => FALSE, 'code' => 409, 'message' => 'Soal latihan belum lengkap atau belum memiliki satu jawaban benar.');
				}
				$this->load->model('Lesson_media_model');
				if ( ! $this->Lesson_media_model->ready_for_review($course_id))
				{
					return array('ok' => FALSE, 'code' => 409, 'message' => 'Gambar pelajaran belum lengkap atau berkasnya tidak tersedia. Periksa gambar sebelum menerbitkan kursus.');
				}
			}

			$now = $this->now_utc();
			$this->db->insert('st_content_reviews', array(
			'course_id' => (int) $course_id,
			'reviewer_user_id' => (int) $reviewer_user_id,
			'decision' => $decision,
			'content_version' => (int) $course['content_version'],
			'notes' => trim($notes),
			'created_at' => $now
			));
			$new_status = $decision === 'approved' ? 'published' : 'draft';
			$this->db->where('course_id', (int) $course_id)->where('visibility', 'shared')->where('target_child_user_id', NULL)->update('st_questions', array('status' => $new_status, 'reviewed_by_user_id' => $decision === 'approved' ? (int) $reviewer_user_id : NULL, 'updated_at' => $now));
			$this->db->where('id', (int) $course_id);
			$this->db->where('status', 'review');
			$this->db->update('st_courses', array(
			'status' => $new_status,
			'content_version' => $decision === 'approved' ? (int) $course['content_version'] : (int) $course['content_version'] + 1,
			'published_at' => $decision === 'approved' ? $now : NULL,
			'updated_by_user_id' => (int) $reviewer_user_id,
			'updated_at' => $now
			));
			if ($decision === 'approved')
			{
				$this->db->where('section_id IN (SELECT id FROM st_course_sections WHERE course_id = '.(int) $course_id.')', NULL, FALSE);
				$this->db->update('st_lessons', array('status' => 'published', 'updated_at' => $now));
			}
			else
			{
				$this->db->where('section_id IN (SELECT id FROM st_course_sections WHERE course_id = '.(int) $course_id.')', NULL, FALSE);
				$this->db->set('content_version', 'content_version + 1', FALSE);
				$this->db->set('updated_at', $now);
				$this->db->update('st_lessons');
			}
			$this->audit($reviewer_user_id, 'content.course_reviewed', 'course', $course_id, array('decision' => $decision, 'content_version' => (int) $course['content_version'], 'self_review' => $self_review, 'quality_confirmed' => $quality_confirmed === TRUE, 'settings_revision' => $settings_revision));
			if ($this->db->trans_status() === FALSE)
			{
				return array('ok' => FALSE, 'code' => 409, 'message' => 'Perubahan review gagal disimpan.');
			}
			return array('ok' => TRUE, 'message' => '');
		});
		return $result + array('message' => 'Penyimpanan review belum dapat dipastikan. Muat ulang kursus sebelum mencoba kembali.');
	}

	public function get_reviews($course_id)
	{
		$this->db->select('r.decision, r.content_version, r.notes, r.created_at, u.display_name AS reviewer_name');
		$this->db->from('st_content_reviews r');
		$this->db->join('st_users u', 'u.id = r.reviewer_user_id');
		$this->db->where('r.course_id', (int) $course_id);
		$this->db->order_by('r.created_at', 'DESC');
		return $this->db->get()->result_array();
	}

	public function archive_course($course_id, $actor_user_id)
	{
		$this->db->trans_begin();
		$course = $this->db->query('SELECT id, status, content_version FROM st_courses WHERE id = ? FOR UPDATE', array((int) $course_id))->row_array();
		if ( ! $course || $course['status'] !== 'published')
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$this->db->where('id', (int) $course_id);
		$this->db->where('status', 'published');
		$this->db->update('st_courses', array('status' => 'archived', 'updated_by_user_id' => (int) $actor_user_id, 'updated_at' => $this->now_utc()));
		$this->audit($actor_user_id, 'content.course_archived', 'course', $course_id, array('content_version' => (int) $course['content_version']));
		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$this->db->trans_commit();
		return TRUE;
	}

	private function audit($actor_user_id, $event_type, $entity_type, $entity_id, $details)
	{
		$this->db->insert('st_audit_events', array(
			'actor_user_id' => (int) $actor_user_id,
			'event_type' => $event_type,
			'entity_type' => $entity_type,
			'entity_id' => (int) $entity_id,
			'correlation_id' => NULL,
			'details_json' => json_encode($details),
			'created_at' => $this->now_utc()
		));
	}

	private function flashcard_decks_are_complete($course_id)
	{
		$query = $this->db->query(
			"SELECT COUNT(*) AS incomplete_decks
			FROM st_flashcard_decks d
			JOIN st_lessons l ON l.id = d.lesson_id
			JOIN st_course_sections s ON s.id = l.section_id
			LEFT JOIN st_flashcards f ON f.deck_id = d.id
			WHERE s.course_id = ?
			GROUP BY d.id
			HAVING COUNT(f.id) = 0",
			array((int) $course_id)
		);
		if ($query === FALSE)
		{
			return FALSE;
		}
		$row = $query->row_array();
		return ! $row;
	}
}
