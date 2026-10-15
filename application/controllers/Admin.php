<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin extends MY_Controller
{
	private function post_contains_non_scalar($fields)
	{
		foreach ($fields as $field)
		{
			$value = $this->input->post($field, FALSE);
			if ($value !== NULL && ! is_string($value))
			{
				return TRUE;
			}
		}
		return FALSE;
	}

	public function index()
	{
		$this->require_role(array('system_admin','content_editor','finance_operator','support_agent'));
		$this->require_http_methods(array('GET', 'HEAD'));
		$counts = array();
		$roles = $this->current_user['roles'];
		$visible_counts = array();
		if (in_array('system_admin', $roles, TRUE))
		{
			$visible_counts = array('courses' => 'st_courses', 'packages' => 'st_packages', 'orders' => 'st_payment_orders');
		}
		else
		{
			if (in_array('content_editor', $roles, TRUE))
			{
				$visible_counts['courses'] = 'st_courses';
			}
			if (in_array('finance_operator', $roles, TRUE))
			{
				$visible_counts['orders'] = 'st_payment_orders';
			}
		}
		foreach ($visible_counts as $key => $table)
		{
			$this->db->from($table);
			$counts[$key] = $this->db->count_all_results();
		}

		$this->render_page('admin/index', array(
			'page_title' => 'Panel pengelola',
			'counts' => $counts
		));
	}

	public function courses()
	{
		$this->require_role(array('system_admin','content_editor'));
		$this->require_http_methods(array('GET', 'HEAD'));
		$this->load->model('Content_model');
		$filters = array(
			'q' => $this->get_scalar_query_param('q', 100),
			'status' => $this->get_scalar_query_param('status', 20)
		);
		$statuses = array('', 'draft', 'review', 'published', 'archived');
		if ( ! in_array($filters['status'], $statuses, TRUE))
		{
			$filters['status'] = '';
		}

		$total_rows = (int) $this->Content_model->count_courses($filters);
		$per_page = 20;
		$page_count = max(1, (int) ceil($total_rows / $per_page));
		$page = min($page_count, $this->get_positive_query_int('halaman'));
		$offset = ($page - 1) * $per_page;
		$this->render_page('admin/courses', array(
			'page_title' => 'Kursus dan konten',
			'courses' => $this->Content_model->list_courses($filters, $per_page, $offset),
			'filters' => $filters,
			'total_rows' => $total_rows,
			'page' => $page,
			'page_count' => $page_count,
			'pagination_query' => array_filter($filters, static function ($value) {
				return $value !== '';
			})
		));
	}

	public function create_course()
	{
		$this->require_role(array('system_admin','content_editor'));
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		$this->load->model('Content_model');
		$errors = array();
		$submit_error = NULL;
		if ($this->input->method(TRUE) === 'POST')
		{
			if ($this->post_contains_non_scalar(array('title','summary','learning_objectives','prerequisites','target_group','category_id','is_free','age_min','age_max','estimated_minutes','rights_status','source_note')))
			{
				show_error('Periksa kembali data yang diisi.', 400, 'Permintaan tidak valid');
				return;
			}
			$this->form_validation->set_rules('title', 'Judul kursus', 'required|trim|min_length[3]|max_length[180]');
			$this->form_validation->set_rules('summary', 'Ringkasan', 'required|trim|min_length[10]|max_length[2000]');
			$this->form_validation->set_rules('learning_objectives', 'Tujuan belajar', 'required|trim|min_length[5]|max_length[3000]');
			$this->form_validation->set_rules('prerequisites', 'Prasyarat/persiapan', 'trim|max_length[2000]');
			$this->form_validation->set_rules('target_group', 'Kelompok belajar', 'required|in_list[early_childhood,primary,secondary,adult,all_ages]');
			$this->form_validation->set_rules('category_id', 'Kategori', 'trim|callback_valid_category');
			$this->form_validation->set_rules('is_free', 'Akses gratis', 'callback_valid_checkbox');
			$this->form_validation->set_rules('age_min', 'Usia minimum', 'trim|callback_valid_optional_age');
			$this->form_validation->set_rules('age_max', 'Usia maksimum', 'trim|callback_valid_optional_age');
			$this->form_validation->set_rules('estimated_minutes', 'Durasi', 'required|integer|less_than_equal_to[600]|greater_than[0]');
			$this->form_validation->set_rules('rights_status', 'Hak materi', 'required|in_list[owned,licensed,public_domain]');
			$this->form_validation->set_rules('source_note', 'Sumber dan hak penggunaan', 'required|trim|min_length[5]|max_length[2000]');
			if ($this->form_validation->run())
			{
				$age_min = $this->input->post('age_min', TRUE);
				$age_max = $this->input->post('age_max', TRUE);
				if ($age_min !== '' && $age_max !== '' && (int) $age_min > (int) $age_max)
				{
					$submit_error = 'Usia minimum tidak boleh lebih besar daripada usia maksimum.';
				}
				else
				{
					$course_id = $this->Content_model->create_course(array(
						'category_id' => (string) $this->input->post('category_id', TRUE),
						'title' => $this->input->post('title', TRUE),
						'summary' => $this->input->post('summary', TRUE),
						'learning_objectives' => $this->input->post('learning_objectives', TRUE),
						'prerequisites' => $this->input->post('prerequisites', TRUE),
						'target_group' => $this->input->post('target_group', TRUE),
						'age_min' => (string) $age_min,
						'age_max' => (string) $age_max,
						'estimated_minutes' => (string) $this->input->post('estimated_minutes', TRUE),
						'rights_status' => $this->input->post('rights_status', TRUE),
						'source_note' => $this->input->post('source_note', TRUE),
						'is_free' => $this->input->post('is_free', TRUE)
					), $this->current_user['id']);
					if ($course_id)
					{
						$this->session->set_flashdata('success', 'Draf kursus berhasil dibuat. Tambahkan pelajaran sebelum diajukan untuk review.');
						redirect('kelola/kursus/'.$course_id);
						return;
					}
					$submit_error = 'Kursus belum dapat dibuat. Periksa pilihan kategori atau coba lagi.';
				}
			}
			else
			{
				$errors = $this->form_validation->error_array();
			}
		}

		$this->render_page('admin/create_course', array(
			'page_title' => 'Buat draf kursus',
			'load_unsaved_form_script' => TRUE,
			'unsaved_initial' => $this->input->method(TRUE) === 'POST',
			'categories' => $this->Content_model->list_categories(TRUE),
			'form_errors' => $errors,
			'submit_error' => $submit_error
		));
	}

	public function edit_course($course_id)
	{
		$this->require_role(array('system_admin','content_editor'));
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		$this->load->model('Content_model');
		$course = $this->Content_model->get_course($course_id);
		$is_admin = in_array('system_admin', $this->current_user['roles'], TRUE);
		if ( ! $course)
		{
			show_404();
			return;
		}
		if ($course['status'] !== 'draft' || ((int) $course['author_user_id'] !== (int) $this->current_user['id'] && ! $is_admin))
		{
			show_error('Draf ini hanya dapat diubah oleh pembuatnya atau administrator.', 403, 'Akses dibatasi');
			return;
		}

		$errors = array();
		$submit_error = NULL;
		if ($this->input->method(TRUE) === 'POST')
		{
			$fields = array('title','summary','learning_objectives','prerequisites','target_group','category_id','age_min','age_max','estimated_minutes','rights_status','source_note','is_free');
			foreach ($fields as $field)
			{
				$value = $this->input->post($field, FALSE);
				if ($value !== NULL && ! is_string($value))
				{
					show_error('Periksa kembali data yang diisi.', 400, 'Permintaan tidak valid');
					return;
				}
			}
			if ($submit_error === NULL)
			{
				$this->form_validation->set_rules('title', 'Judul kursus', 'required|trim|min_length[3]|max_length[180]');
				$this->form_validation->set_rules('summary', 'Ringkasan', 'required|trim|min_length[10]|max_length[2000]');
				$this->form_validation->set_rules('learning_objectives', 'Tujuan belajar', 'required|trim|min_length[5]|max_length[3000]');
				$this->form_validation->set_rules('prerequisites', 'Prasyarat/persiapan', 'trim|max_length[2000]');
				$this->form_validation->set_rules('target_group', 'Kelompok belajar', 'required|in_list[early_childhood,primary,secondary,adult,all_ages]');
				$this->form_validation->set_rules('category_id', 'Kategori', 'trim|callback_valid_category');
				$this->form_validation->set_rules('is_free', 'Akses gratis', 'callback_valid_checkbox');
				$this->form_validation->set_rules('age_min', 'Usia minimum', 'trim|callback_valid_optional_age');
				$this->form_validation->set_rules('age_max', 'Usia maksimum', 'trim|callback_valid_optional_age');
				$this->form_validation->set_rules('estimated_minutes', 'Durasi', 'required|integer|less_than_equal_to[600]|greater_than[0]');
				$this->form_validation->set_rules('rights_status', 'Hak materi', 'required|in_list[owned,licensed,public_domain]');
				$this->form_validation->set_rules('source_note', 'Sumber dan hak penggunaan', 'required|trim|min_length[5]|max_length[2000]');
				if ($this->form_validation->run())
				{
					$age_min = $this->input->post('age_min', TRUE);
					$age_max = $this->input->post('age_max', TRUE);
					if ($age_min !== '' && $age_max !== '' && (int) $age_min > (int) $age_max)
					{
						$submit_error = 'Usia minimum tidak boleh lebih besar daripada usia maksimum.';
					}
					else
					{
						$ok = $this->Content_model->update_course_draft($course_id, $this->current_user['id'], array(
							'category_id' => (string) $this->input->post('category_id', TRUE),
							'title' => $this->input->post('title', TRUE),
							'summary' => $this->input->post('summary', TRUE),
							'learning_objectives' => $this->input->post('learning_objectives', TRUE),
							'prerequisites' => $this->input->post('prerequisites', TRUE),
							'target_group' => $this->input->post('target_group', TRUE),
							'age_min' => (string) $age_min,
							'age_max' => (string) $age_max,
							'estimated_minutes' => (string) $this->input->post('estimated_minutes', TRUE),
							'rights_status' => $this->input->post('rights_status', TRUE),
							'source_note' => $this->input->post('source_note', TRUE),
							'is_free' => $this->input->post('is_free', TRUE)
						), $is_admin);
						if ($ok)
						{
							$this->session->set_flashdata('success', 'Draf kursus diperbarui. Versi konten bertambah.');
							redirect('kelola/kursus/'.$course_id);
							return;
						}
						$submit_error = 'Draf kursus tidak dapat diperbarui; status atau kepemilikannya mungkin telah berubah.';
					}
				}
				else
				{
					$errors = $this->form_validation->error_array();
				}
			}
		}

		$this->render_page('admin/create_course', array(
			'page_title' => 'Edit draf kursus',
			'load_unsaved_form_script' => TRUE,
			'unsaved_initial' => $this->input->method(TRUE) === 'POST',
			'course' => $course,
			'is_edit' => TRUE,
			'categories' => $this->Content_model->list_categories(TRUE),
			'form_errors' => $errors,
			'submit_error' => $submit_error
		));
	}

	public function valid_optional_age($value)
	{
		if ($this->router->fetch_method() === __FUNCTION__)
		{
			show_404();
			return FALSE;
		}

		if ($value === '')
		{
			return TRUE;
		}
		if (ctype_digit((string) $value) && (int) $value >= 0 && (int) $value <= 120)
		{
			return TRUE;
		}
		$this->form_validation->set_message('valid_optional_age', 'Kolom {field} harus berupa angka 0 sampai 120 atau dikosongkan.');
		return FALSE;
	}

	public function valid_category($value)
	{
		if ($this->router->fetch_method() === __FUNCTION__)
		{
			show_404();
			return FALSE;
		}

		if ($value === '' || $value === '0')
		{
			return TRUE;
		}
		$this->db->where('id', (int) $value);
		$this->db->where('status', 'active');
		if ($this->db->count_all_results('st_categories') > 0)
		{
			return TRUE;
		}
		$this->form_validation->set_message('valid_category', 'Kategori yang dipilih tidak tersedia.');
		return FALSE;
	}

	public function valid_checkbox($value)
	{
		if ($this->router->fetch_method() === __FUNCTION__)
		{
			show_404();
			return FALSE;
		}

		if ($value === NULL || $value === '')
		{
			return TRUE;
		}
		if ($value === 'yes')
		{
			return TRUE;
		}
		$this->form_validation->set_message('valid_checkbox', 'Pilihan akses gratis tidak valid.');
		return FALSE;
	}

	public function categories()
	{
		$this->require_role(array('system_admin','content_editor'));
		$this->require_http_methods(array('GET', 'HEAD'));
		$this->load->model('Content_model');
		$this->render_page('admin/categories', array(
			'page_title' => 'Kategori belajar',
			'categories' => $this->Content_model->list_categories(FALSE)
		));
	}

	public function create_category()
	{
		$this->require_role(array('system_admin','content_editor'));
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		$this->load->model('Content_model');
		$errors = array();
		$submit_error = NULL;
		if ($this->input->method(TRUE) === 'POST')
		{
			$this->form_validation->set_rules('name', 'Nama kategori', 'required|trim|min_length[2]|max_length[120]');
			$this->form_validation->set_rules('description', 'Deskripsi', 'trim|max_length[1000]');
			if ($this->form_validation->run())
			{
				if ($this->Content_model->create_category($this->input->post('name', TRUE), $this->input->post('description', TRUE)))
				{
					$this->session->set_flashdata('success', 'Kategori berhasil ditambahkan.');
					redirect('kelola/kategori');
					return;
				}
				$submit_error = 'Kategori belum dapat ditambahkan. Nama kategori dengan slug tersebut mungkin sudah ada.';
			}
			else
			{
				$errors = $this->form_validation->error_array();
			}
		}
		$this->render_page('admin/create_category', array(
			'page_title' => 'Tambah kategori',
			'form_errors' => $errors,
			'submit_error' => $submit_error
		));
	}

	public function course($course_id)
	{
		$this->require_role(array('system_admin','content_editor'));
		$this->require_http_methods(array('GET', 'HEAD'));
		$this->load->model('Content_model');
		$this->load->model('Flashcard_model');
		$course = $this->Content_model->get_course($course_id);
		if ( ! $course)
		{
			show_404();
			return;
		}
		if ($course['status'] === 'draft' && (int) $course['author_user_id'] !== (int) $this->current_user['id'] && ! in_array('system_admin', $this->current_user['roles'], TRUE))
		{
			show_error('Draf ini hanya dapat dilihat oleh pembuatnya atau administrator.', 403, 'Akses dibatasi');
			return;
		}

		$this->load->model('Lesson_media_model');
		$lesson_images = $this->Lesson_media_model->get_for_course($course_id);
		if ($lesson_images === FALSE) { show_error('Gambar pendamping belum dapat dimuat. Buka kursus kembali.', 503, 'Kursus belum tersedia'); return; }
		$flashcard_decks = array();
		foreach ($this->Flashcard_model->get_course_decks($course_id) as $row)
		{
			$lesson_key = (int) $row['lesson_id'];
			if ( ! isset($flashcard_decks[$lesson_key]))
			{
				$flashcard_decks[$lesson_key] = array('id' => (int) $row['deck_id'], 'title' => $row['deck_title'], 'description' => $row['deck_description'], 'cards' => array());
			}
			if ($row['flashcard_id'] !== NULL)
			{
				$flashcard_decks[$lesson_key]['cards'][] = array('id' => (int) $row['flashcard_id'], 'front_text' => $row['front_text'], 'back_text' => $row['back_text'], 'hint_text' => $row['hint_text']);
			}
		}
		$this->render_page('admin/course', array(
			'page_title' => $course['title'],
			'course' => $course,
			'lessons' => $this->Content_model->get_course_lessons($course_id),
			'lesson_images' => $lesson_images,
			'flashcard_decks' => $flashcard_decks,
			'reviews' => $this->Content_model->get_reviews($course_id),
			'can_edit' => $course['status'] === 'draft' && ((int) $course['author_user_id'] === (int) $this->current_user['id'] || in_array('system_admin', $this->current_user['roles'], TRUE)),
			'can_review' => $course['status'] === 'review' && (int) $course['author_user_id'] !== (int) $this->current_user['id']
		));
	}

	public function preview_lesson($course_id = NULL, $lesson_id = NULL)
	{
		$this->require_role(array('system_admin','content_editor'));
		if ($this->input->method(TRUE) !== 'GET' || ! ctype_digit((string) $course_id) || ! ctype_digit((string) $lesson_id))
		{
			show_404();
			return;
		}
		$this->load->model('Content_model');
		$course = $this->Content_model->get_course($course_id);
		if ( ! $course)
		{
			show_404();
			return;
		}
		if ($course['status'] === 'draft' && (int) $course['author_user_id'] !== (int) $this->current_user['id'] && ! in_array('system_admin', $this->current_user['roles'], TRUE))
		{
			show_error('Draf ini hanya dapat dilihat oleh pembuatnya atau administrator.', 403, 'Akses dibatasi');
			return;
		}
		$lesson = $this->Content_model->get_course_lesson($course_id, $lesson_id);
		if ( ! $lesson)
		{
			show_404();
			return;
		}
		$this->load->model('Lesson_media_model');
		$lesson_image = $this->Lesson_media_model->get_for_lesson($lesson_id);
		if ($lesson_image === FALSE) { show_error('Gambar pendamping belum dapat dimuat. Buka pratinjau kembali.', 503, 'Pratinjau belum tersedia'); return; }
		$theme_options = array('early_childhood', 'primary', 'secondary', 'adult', 'all_ages');
		$preview_theme = $this->get_scalar_query_param('tema', 30);
		if ( ! in_array($preview_theme, $theme_options, TRUE))
		{
			$preview_theme = in_array($course['target_group'], $theme_options, TRUE) ? $course['target_group'] : 'all_ages';
		}
		$this->load->model('Flashcard_model');
		$deck = $this->Flashcard_model->get_deck_for_editor($course_id, $lesson_id);
		$cards = $deck ? $this->Flashcard_model->get_cards_for_editor($deck['id']) : array();
		foreach ($cards as &$card)
		{
			$card['learner_status'] = NULL;
		}
		unset($card);
		$this->output->set_header('Cache-Control: private, no-store');
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		$this->render_page('catalog/lesson', array(
			'page_title' => 'Pratinjau: '.$lesson['title'],
			'lesson' => $lesson,
			'lesson_image' => $lesson_image,
			'lesson_progress' => NULL,
			'next_lesson' => NULL,
			'flashcard_deck' => $deck,
			'flashcards' => $cards,
			'is_content_preview' => TRUE,
			'preview_theme' => $preview_theme,
			'preview_theme_options' => $theme_options,
			'preview_course' => $course
		));
	}

	public function add_lesson($course_id)
	{
		$this->require_role(array('system_admin','content_editor'));
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		$this->load->model('Content_model');
		$course = $this->Content_model->get_course($course_id);
		if ( ! $course)
		{
			show_404();
			return;
		}
		if ($course['status'] !== 'draft' || ((int) $course['author_user_id'] !== (int) $this->current_user['id'] && ! in_array('system_admin', $this->current_user['roles'], TRUE)))
		{
			show_error('Pelajaran tidak dapat diubah pada status kursus ini.', 403, 'Akses dibatasi');
			return;
		}

		$errors = array();
		$submit_error = NULL;
		if ($this->input->method(TRUE) === 'POST')
		{
			if ($this->post_contains_non_scalar(array('title','body_text','estimated_minutes','rights_status','source_note','is_preview')))
			{
				show_error('Periksa kembali data yang diisi.', 400, 'Permintaan tidak valid');
				return;
			}
			$this->form_validation->set_rules('title', 'Judul pelajaran', 'required|trim|min_length[3]|max_length[180]');
			$this->form_validation->set_rules('body_text', 'Isi pelajaran', 'required|trim|min_length[20]|max_length[30000]');
			$this->form_validation->set_rules('estimated_minutes', 'Durasi', 'required|integer|greater_than[0]|less_than_equal_to[600]');
			$this->form_validation->set_rules('rights_status', 'Hak materi', 'required|in_list[owned,licensed,public_domain]');
			$this->form_validation->set_rules('source_note', 'Sumber dan hak penggunaan', 'required|trim|min_length[5]|max_length[2000]');
			if ($this->form_validation->run())
			{
				$lesson_id = $this->Content_model->create_lesson($course_id, array(
					'title' => $this->input->post('title', TRUE),
					'body_text' => $this->input->post('body_text', TRUE),
					'estimated_minutes' => $this->input->post('estimated_minutes', TRUE),
					'rights_status' => $this->input->post('rights_status', TRUE),
					'source_note' => $this->input->post('source_note', TRUE),
					'is_preview' => $this->input->post('is_preview', TRUE)
				), $this->current_user['id']);
				if ($lesson_id)
				{
					$this->session->set_flashdata('success', 'Pelajaran ditambahkan ke draf kursus.');
					redirect('kelola/kursus/'.$course_id);
					return;
				}
				$submit_error = 'Pelajaran tidak dapat ditambahkan. Kursus mungkin telah berubah status.';
			}
			else
			{
				$errors = $this->form_validation->error_array();
			}
		}
		$this->render_page('admin/add_lesson', array(
			'page_title' => 'Tambah pelajaran',
			'load_unsaved_form_script' => TRUE,
			'unsaved_initial' => $this->input->method(TRUE) === 'POST',
			'course' => $course,
			'form_errors' => $errors,
			'submit_error' => $submit_error
		));
	}

	public function edit_lesson($course_id, $lesson_id)
	{
		$this->require_role(array('system_admin','content_editor'));
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		$this->load->model('Content_model');
		$course = $this->Content_model->get_course($course_id);
		if ( ! $course)
		{
			show_404();
			return;
		}
		if ($course['status'] !== 'draft' || ((int) $course['author_user_id'] !== (int) $this->current_user['id'] && ! in_array('system_admin', $this->current_user['roles'], TRUE)))
		{
			show_error('Pelajaran hanya dapat diubah pada draf milik Anda.', 403, 'Akses dibatasi');
			return;
		}
		$lesson = NULL;
		foreach ($this->Content_model->get_course_lessons($course_id) as $candidate)
		{
			if ((int) $candidate['id'] === (int) $lesson_id)
			{
				$lesson = $candidate;
				break;
			}
		}
		if ( ! $lesson)
		{
			show_404();
			return;
		}

		$errors = array();
		$submit_error = NULL;
		$has_conflict = FALSE;
		$form_revision = $lesson['content_version'];
		if ($this->input->method(TRUE) === 'POST')
		{
			$fields = array('title','body_text','estimated_minutes','rights_status','source_note','is_preview','revision');
			foreach ($fields as $field)
			{
				$value = $this->input->post($field, FALSE);
				if ($value !== NULL && ! is_string($value))
				{
					show_error('Format isian pelajaran tidak valid.', 400, 'Permintaan tidak valid');
					return;
				}
			}
			$form_revision = $this->input->post('revision', FALSE) ?? '';
			if ($submit_error === NULL)
			{
				$this->form_validation->set_rules('title', 'Judul pelajaran', 'required|trim|min_length[3]|max_length[180]');
				$this->form_validation->set_rules('body_text', 'Isi pelajaran', 'required|trim|min_length[20]|max_length[30000]');
				$this->form_validation->set_rules('estimated_minutes', 'Durasi', 'required|integer|greater_than[0]|less_than_equal_to[600]');
				$this->form_validation->set_rules('rights_status', 'Hak materi', 'required|in_list[owned,licensed,public_domain]');
				$this->form_validation->set_rules('source_note', 'Sumber dan hak penggunaan', 'required|trim|min_length[5]|max_length[2000]');
				$this->form_validation->set_rules('is_preview', 'Pratinjau gratis', 'callback_valid_checkbox');
				if ($this->form_validation->run())
				{
					$result = $this->Content_model->update_lesson_draft($course_id, $lesson_id, $this->current_user['id'], array(
						'title' => $this->input->post('title', TRUE),
						'body_text' => $this->input->post('body_text', TRUE),
						'estimated_minutes' => $this->input->post('estimated_minutes', TRUE),
						'rights_status' => $this->input->post('rights_status', TRUE),
						'source_note' => $this->input->post('source_note', TRUE),
						'is_preview' => $this->input->post('is_preview', TRUE)
					), $form_revision, in_array('system_admin', $this->current_user['roles'], TRUE));
					if ($result === 'ok')
					{
						$this->session->set_flashdata('success', 'Pelajaran diperbarui. Versi konten bertambah.');
						redirect('kelola/kursus/'.$course_id, 'location', 303);
						return;
					}
					$has_conflict = $result === 'conflict';
					$messages = array('conflict' => 'Pelajaran telah berubah sejak formulir dibuka. Salin isian yang ingin dipertahankan, lalu muat ulang sebelum menyimpan kembali.', 'invalid' => 'Versi formulir tidak valid. Salin isian yang ingin dipertahankan, lalu muat ulang pelajaran.', 'denied' => 'Pelajaran tidak dapat diperbarui; status, kursus, atau kepemilikannya mungkin berubah.', 'unavailable' => 'Pelajaran belum dapat disimpan. Tidak ada perubahan yang diterapkan.');
					$submit_error = $messages[$result] ?? $messages['unavailable'];
					$status_codes = array('conflict' => 409, 'invalid' => 400, 'denied' => 403, 'unavailable' => 503);
					$this->output->set_status_header($status_codes[$result] ?? 503);
				}
				else
				{
					$errors = $this->form_validation->error_array();
				}
			}
		}

		$this->render_page('admin/add_lesson', array(
			'page_title' => 'Edit draf pelajaran',
			'load_unsaved_form_script' => TRUE,
			'unsaved_initial' => $this->input->method(TRUE) === 'POST',
			'course' => $course,
			'lesson' => $lesson,
			'is_edit' => TRUE,
			'form_revision' => $form_revision,
			'has_conflict' => $has_conflict,
			'form_errors' => $errors,
			'submit_error' => $submit_error
		));
	}

	public function flashcards($course_id, $lesson_id)
	{
		$this->require_role(array('system_admin', 'content_editor'));
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		$this->load->model('Content_model');
		$this->load->model('Flashcard_model');
		$course = $this->Content_model->get_course($course_id);
		if ( ! $course)
		{
			show_404();
			return;
		}
		if ($course['status'] !== 'draft' || ((int) $course['author_user_id'] !== (int) $this->current_user['id'] && ! in_array('system_admin', $this->current_user['roles'], TRUE)))
		{
			show_error('Kartu belajar hanya dapat diubah pada draf kursus milik Anda.', 403, 'Akses dibatasi');
			return;
		}

		$lesson = NULL;
		foreach ($this->Content_model->get_course_lessons($course_id) as $candidate)
		{
			if ((int) $candidate['id'] === (int) $lesson_id)
			{
				$lesson = $candidate;
				break;
			}
		}
		if ( ! $lesson)
		{
			show_404();
			return;
		}

		$deck = $this->Flashcard_model->get_deck_for_editor($course_id, $lesson_id);
		$cards = $deck ? $this->Flashcard_model->get_cards_for_editor($deck['id']) : array();
		$errors = array();
		$submit_error = NULL;
		$action = $this->input->post('action', TRUE);

		if ($this->input->method(TRUE) === 'POST')
		{
			if ($this->post_contains_non_scalar(array('action', 'title', 'description', 'front_text', 'back_text', 'hint_text', 'sort_order', 'card_id')))
			{
				show_error('Periksa kembali data yang diisi.', 400, 'Permintaan tidak valid');
				return;
			}
			$posted_card_id = $this->input->post('card_id', TRUE);
			if ($action === 'save_card' && ( ! is_string($posted_card_id) || ! preg_match('/\A[0-9]*\z/', $posted_card_id)))
			{
				show_error('Identitas kartu tidak valid.', 400, 'Permintaan tidak valid');
				return;
			}
			if ($action === 'save_deck')
			{
				$this->form_validation->set_rules('title', 'Nama set kartu', 'required|trim|min_length[3]|max_length[180]');
				$this->form_validation->set_rules('description', 'Deskripsi', 'trim|max_length[1000]');
				if ($this->form_validation->run())
				{
					$result = $this->Flashcard_model->save_draft_deck($course_id, $lesson_id, $this->current_user['id'], $this->input->post('title', TRUE), $this->input->post('description', TRUE), in_array('system_admin', $this->current_user['roles'], TRUE));
					if ($result)
					{
						$this->session->set_flashdata('success', 'Set kartu tersimpan sebagai bagian dari draf kursus.');
						redirect('kelola/kursus/'.$course_id.'/pelajaran/'.$lesson_id.'/flashcard');
						return;
					}
					$submit_error = 'Set kartu tidak dapat disimpan. Pastikan draf kursus masih dapat diubah.';
				}
				else { $errors = $this->form_validation->error_array(); }
			}
			elseif ($action === 'save_card')
			{
				if ( ! $deck)
				{
					$submit_error = 'Simpan nama set kartu terlebih dahulu.';
				}
				else
				{
					$this->form_validation->set_rules('front_text', 'Sisi depan', 'required|trim|min_length[1]|max_length[500]');
					$this->form_validation->set_rules('back_text', 'Sisi belakang', 'required|trim|min_length[1]|max_length[2000]');
					$this->form_validation->set_rules('hint_text', 'Petunjuk', 'trim|max_length[500]');
					$this->form_validation->set_rules('sort_order', 'Urutan', 'required|integer|less_than_equal_to[1000]|greater_than_equal_to[0]');
					$this->form_validation->set_rules('card_id', 'Kartu', 'trim|integer');
					if ($this->form_validation->run())
					{
						$card_id = (int) $this->input->post('card_id', TRUE);
						$ok = $this->Flashcard_model->save_draft_card($course_id, $lesson_id, $deck['id'], $this->current_user['id'], $card_id, $this->input->post('front_text', TRUE), $this->input->post('back_text', TRUE), $this->input->post('hint_text', TRUE), (int) $this->input->post('sort_order', TRUE), in_array('system_admin', $this->current_user['roles'], TRUE));
						if ($ok)
						{
							$this->session->set_flashdata('success', $card_id > 0 ? 'Kartu diperbarui.' : 'Kartu ditambahkan.');
							redirect('kelola/kursus/'.$course_id.'/pelajaran/'.$lesson_id.'/flashcard');
							return;
						}
						$submit_error = 'Kartu tidak dapat disimpan. Periksa batas jumlah kartu dan status draf kursus.';
					}
					else { $errors = $this->form_validation->error_array(); }
				}
			}
			elseif ($action === 'delete_card')
			{
				$card_id = $this->input->post('card_id', TRUE);
				if ( ! is_string($card_id) || ! ctype_digit($card_id) || (int) $card_id < 1)
				{
					show_error('Kartu yang dipilih tidak valid.', 400, 'Permintaan tidak valid');
					return;
				}
				if ($deck && $this->Flashcard_model->delete_draft_card($course_id, $lesson_id, $deck['id'], (int) $card_id, $this->current_user['id'], in_array('system_admin', $this->current_user['roles'], TRUE)))
				{
					$this->session->set_flashdata('success', 'Kartu dihapus dari draf.');
					redirect('kelola/kursus/'.$course_id.'/pelajaran/'.$lesson_id.'/flashcard');
					return;
				}
				$submit_error = 'Kartu tidak dapat dihapus dari draf.';
			}
			else
			{
				show_error('Aksi kartu belajar tidak valid.', 400, 'Permintaan tidak valid');
				return;
			}
		}

		$selected_card = NULL;
		$edit_card_id = $this->get_positive_query_int('edit_kartu');
		foreach ($cards as $card)
		{
			if ((int) $card['id'] === $edit_card_id)
			{
				$selected_card = $card;
				break;
			}
		}
		$this->render_page('admin/flashcards', array(
			'page_title' => 'Kartu belajar · '.$lesson['title'],
			'course' => $course,
			'lesson' => $lesson,
			'deck' => $deck,
			'cards' => $cards,
			'selected_card' => $selected_card,
			'form_errors' => $errors,
			'submit_error' => $submit_error
		));
	}

	public function submit_course($course_id)
	{
		$this->require_role(array('system_admin','content_editor'));
		if ($this->input->method(TRUE) !== 'POST')
		{
			show_404();
			return;
		}
		$this->load->model('Content_model');
		$is_admin = in_array('system_admin', $this->current_user['roles'], TRUE);
		$result = $this->Content_model->submit_for_review($course_id, $this->current_user['id'], $is_admin);
		if ($result['ok'])
		{
			$this->session->set_flashdata('success', 'Kursus diajukan untuk ditinjau oleh pengelola lain.');
		}
		else
		{
			$this->session->set_flashdata('error', $result['message']);
		}
		redirect('kelola/kursus/'.$course_id);
	}

	public function review_course($course_id)
	{
		$this->require_role(array('system_admin','content_editor'));
		if ($this->input->method(TRUE) !== 'POST')
		{
			show_404();
			return;
		}
		$this->load->model('Content_model');
		$decision = $this->input->post('decision', TRUE);
		$notes = $this->input->post('notes', TRUE);
		if ( ! is_string($decision) || ! is_string($notes) || strlen($notes) > 5000)
		{
			$this->session->set_flashdata('error', 'Pilihan review tidak valid.');
			redirect('kelola/kursus/'.$course_id);
			return;
		}
		$result = $this->Content_model->review_course($course_id, $this->current_user['id'], $decision, $notes);
		if ($result['ok'])
		{
			$this->session->set_flashdata('success', 'Keputusan review berhasil dicatat.');
			if ($decision === 'changes_requested')
			{
				redirect('kelola/kursus');
				return;
			}
		}
		else
		{
			$this->session->set_flashdata('error', $result['message']);
		}
		redirect('kelola/kursus/'.$course_id);
	}

	public function archive_course($course_id)
	{
		$this->require_role(array('system_admin'));
		if ($this->input->method(TRUE) !== 'POST')
		{
			show_404();
			return;
		}
		$this->load->model('Content_model');
		if ($this->Content_model->archive_course($course_id, $this->current_user['id']))
		{
			$this->session->set_flashdata('success', 'Kursus diarsipkan. Riwayatnya tetap tersimpan.');
		}
		else
		{
			$this->session->set_flashdata('error', 'Kursus tidak dapat diarsipkan.');
		}
		redirect('kelola/kursus/'.$course_id);
	}
}
