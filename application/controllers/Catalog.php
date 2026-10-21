<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Catalog extends MY_Controller
{
	private function public_context($course_id)
	{
		$this->load->model('Public_learning_model');
		return $this->Public_learning_model->context($course_id);
	}
	private function target_group_label($value)
	{
		return study_target_group_label($value);
	}

	public function index()
	{
		$this->require_http_methods(array('GET', 'HEAD'));
		$this->load->model('Content_model');
		$filters = array(
			'kelas' => study_learner_grade_filter($this->current_user),
			'q' => $this->get_scalar_query_param('q', 100),
			'category_id' => (int) $this->get_scalar_query_param('kategori', 10)
		);
		$filters['category_id'] = $filters['category_id'] > 0 ? $filters['category_id'] : 0;
		$total_rows = (int) $this->Content_model->count_courses($filters, TRUE);
		$per_page = 12;
		$page_count = max(1, (int) ceil($total_rows / $per_page));
		$page = min($page_count, $this->get_positive_query_int('halaman'));
		$target_group_labels = array();
		foreach (array('all_ages','early_childhood','primary','secondary','adult') as $target_group)
		{
			$target_group_labels[$target_group] = study_target_group_label($target_group);
		}
		$this->render_page('catalog/index', array(
			'page_title' => 'Materi dan Latihan Soal SD Kelas 1–6'.($page > 1 ? ' · Halaman '.$page : ''),
			'courses' => $this->Content_model->list_courses($filters, $per_page, ($page - 1) * $per_page, TRUE),
			'categories' => $this->Content_model->list_categories(TRUE),
			'filters' => $filters,
			'total_rows' => $total_rows,
			'has_filters' => $filters['q'] !== '' || $filters['category_id'] > 0 || $filters['kelas'] !== '',
			'target_group_labels' => $target_group_labels,
			'page' => $page,
			'page_count' => $page_count,
			'pagination_query' => array_filter(array('q' => $filters['q'], 'kategori' => $filters['category_id'], 'kelas' => $filters['kelas']), static function ($value) {
				return $value !== 0;
			})
		));
	}

	public function course($slug)
	{
		$this->require_http_methods(array('GET', 'HEAD'));
		if ($this->current_user)
		{
			$this->output->set_header('Cache-Control: private, no-store');
			$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		}
		$this->load->model('Content_model');
		$course = $this->Content_model->get_course_by_slug($slug);
		if ( ! $course)
		{
			show_404();
			return;
		}
		$lessons = $this->Content_model->get_published_lesson_index($course['id']);
		$has_preview_lessons = FALSE;
		foreach ($lessons as $lesson)
		{
			if ((int) $lesson['is_preview'] === 1)
			{
				$has_preview_lessons = TRUE;
				break;
			}
		}
		$this->render_page('catalog/course', array(
			'page_title' => $course['title'],
			'course' => $course,
			'public_context' => $public_context=$this->public_context($course['id']),
			'breadcrumbs' => $public_context?$this->Public_learning_model->breadcrumbs($public_context):array(),
			'public_source' => $public_context,
			'target_group_label' => $this->target_group_label($course['target_group']),
			'lessons' => $lessons,
			'lesson_progress_map' => $this->current_user ? $this->Content_model->get_course_lesson_progress_map($this->current_user['id'], $course['id']) : NULL,
			'has_preview_lessons' => $has_preview_lessons,
			'question_count' => $this->db->where('course_id', (int) $course['id'])->where('visibility', 'shared')->where('target_child_user_id', NULL)->where('status', 'published')->count_all_results('st_questions'),
			'can_read_course' => $this->Content_model->can_access_course($this->current_user ? $this->current_user['id'] : 0, $course)
		));
	}

	public function lesson($slug)
	{
		$this->require_http_methods(array('GET', 'HEAD'));
		$this->load->model('Content_model');
		$lesson = $this->Content_model->get_published_lesson($slug);
		if ( ! $lesson)
		{
			show_404();
			return;
		}
		if ( ! $this->Content_model->can_access_lesson($this->current_user ? $this->current_user['id'] : 0, $lesson))
		{
			$this->session->set_flashdata('notice', 'Materi ini memerlukan akses kursus. Periksa hak akses atau paket yang tersedia; akun anak dapat meminta bantuan orang tua.');
			redirect('kursus/'.$lesson['course_slug']);
			return;
		}
		$this->load->model('Lesson_media_model');
		$lesson_image = $this->Lesson_media_model->get_for_lesson($lesson['id']);
		if ($lesson_image === FALSE) { show_error('Gambar pendamping belum dapat dimuat. Coba buka materi kembali.', 503, 'Materi belum tersedia'); return; }
		$progress = $this->current_user ? $this->Content_model->get_lesson_progress($this->current_user['id'], $lesson['id']) : NULL;
		$next_lesson = $this->Content_model->get_next_accessible_lesson($this->current_user ? $this->current_user['id'] : 0, $lesson['course_id'], $lesson['id']);
		$this->load->model('Flashcard_model');
		$flashcard_deck = $this->Flashcard_model->get_published_deck_for_lesson($lesson['id']);
		$flashcards = $flashcard_deck ? $this->Flashcard_model->get_published_cards($flashcard_deck['id'], $this->current_user ? $this->current_user['id'] : 0) : array();
		$this->load->library('Matching_cards');
		$public_context=$this->public_context($lesson['course_id']);
		$public_lesson=FALSE; $previous_public_lesson=NULL; $public_source=NULL;
		if($public_context) {
			$previous=NULL;
			foreach($this->Public_learning_model->lessons(array($lesson['course_id'])) as $item) {
				if((int)$item['id']===(int)$lesson['id']) { $public_lesson=TRUE; $previous_public_lesson=$previous; $public_source=$item; break; }
				$previous=$item;
			}
		}
		$this->render_page('catalog/lesson', array(
			'page_title' => $lesson['title'].($public_lesson?' — '.$public_context['subject_label'].' Kelas '.$public_context['grade'].' SD':''),
			'lesson' => $lesson,
			'public_lesson' => $public_lesson,
			'public_source' => $public_source,
			'public_context' => $public_context,
			'previous_public_lesson' => $previous_public_lesson,
			'breadcrumbs' => $public_lesson?$this->Public_learning_model->breadcrumbs($public_context,$lesson):array(),
			'lesson_image' => $lesson_image,
			'lesson_progress' => $progress,
			'next_lesson' => $next_lesson,
			'flashcard_deck' => $flashcard_deck,
			'flashcards' => $flashcards,
			'can_play_matching' => count($this->matching_cards->eligible($flashcards)) >= 2,
			'load_lesson_progress_script' => TRUE
		));
	}

	public function save_flashcard_progress($slug, $flashcard_id)
	{
		$this->require_login();
		if ($this->input->method(TRUE) !== 'POST')
		{
			show_404();
			return;
		}
		$status = $this->input->post('status', TRUE);
		if ( ! is_string($status) || ! in_array($status, array('learning', 'known'), TRUE))
		{
			show_error('Status kartu belajar tidak valid.', 400, 'Permintaan tidak valid');
			return;
		}

		$this->load->model('Content_model');
		$this->load->model('Flashcard_model');
		$lesson = $this->Content_model->get_published_lesson($slug);
		if ( ! $lesson)
		{
			show_404();
			return;
		}
		if ( ! $this->Content_model->can_access_lesson($this->current_user['id'], $lesson))
		{
			show_error('Akses materi ini tidak tersedia.', 403, 'Akses dibatasi');
			return;
		}
		$result = $this->Flashcard_model->set_card_status($this->current_user['id'], $lesson['id'], $flashcard_id, $status);
		if ( ! $result['ok'] && $result['code'] === 503)
		{
			$this->output->set_status_header(503)->set_header('Cache-Control: private, no-store')->set_header('X-Robots-Tag: noindex, nofollow');
			$this->render_page('catalog/card_recovery', array(
				'page_title' => 'Periksa kembali kartu belajar',
				'recovery_uncertain' => ($result['failure'] ?? 'uncertain') !== 'unavailable',
				'recovery_path' => 'materi/'.rawurlencode($lesson['slug']).'#kartu-belajar',
				'suppress_flash' => TRUE
			));
			return;
		}
		if ( ! $result['ok'])
		{
			show_error('Status kartu tidak dapat disimpan. Periksa kembali materi dan aksesmu.', $result['code'], 'Permintaan tidak dapat diproses');
			return;
		}
		$this->session->set_flashdata('success', $status === 'known' ? 'Kartu ditandai sudah dikuasai.' : 'Kartu ditandai untuk dipelajari lagi.');
		redirect('materi/'.rawurlencode($lesson['slug']).'#kartu-belajar', 'location', 303);
	}

	public function save_lesson_position($slug)
	{
		$this->require_login();
		if ($this->input->method(TRUE) !== 'POST')
		{
			show_404();
			return;
		}

		$position = $this->input->post('position', TRUE);
		if ( ! is_string($position) || ! preg_match('/\A(?:0|[1-9][0-9]{0,3}|10000)\z/', $position))
		{
			show_error('Posisi baca tidak valid.', 400, 'Permintaan tidak valid');
			return;
		}

		$this->load->model('Content_model');
		$lesson = $this->Content_model->get_published_lesson($slug);
		if ( ! $lesson)
		{
			show_404();
			return;
		}
		$result = $this->Content_model->save_lesson_position($this->current_user['id'], $lesson['id'], (int) $position);
		if ( ! $result['ok'] && $result['code'] === 503)
		{
			$this->recover_reading_transaction($result, $lesson['slug'], $this->input->is_ajax_request());
			return;
		}
		if ($result['ok'])
		{
			if ($this->input->is_ajax_request())
			{
				$this->output
					->set_content_type('application/json')
					->set_output(json_encode(array(
						'ok' => TRUE,
						'message' => 'Progres tersimpan.',
						'csrf_hash' => $this->security->get_csrf_hash()
					)));
				return;
			}
			$this->session->set_flashdata('success', 'Posisi baca tersimpan. Kamu bisa melanjutkan dari posisi ini nanti.');
		}
		else
		{
			if ($this->input->is_ajax_request())
			{
				$this->output
					->set_status_header(403)
					->set_content_type('application/json')
					->set_output(json_encode(array(
						'ok' => FALSE,
						'message' => 'Akses materi sudah berubah.',
						'csrf_hash' => $this->security->get_csrf_hash()
					)));
				return;
			}
			$this->session->set_flashdata('error', 'Posisi baca tidak dapat disimpan karena akses materi sudah berubah.');
		}
		redirect('materi/'.rawurlencode($lesson['slug']), 'location', 303);
	}

	public function complete_lesson($slug)
	{
		$this->require_login();
		if ($this->input->method(TRUE) !== 'POST')
		{
			show_404();
			return;
		}
		$this->load->model('Content_model');
		$lesson = $this->Content_model->get_published_lesson($slug);
		if ( ! $lesson)
		{
			show_404();
			return;
		}
		$result = $this->Content_model->complete_lesson($this->current_user['id'], $lesson['id']);
		if ( ! $result['ok'] && $result['code'] === 503)
		{
			$this->recover_reading_transaction($result, $lesson['slug'], FALSE);
			return;
		}
		if ($result['ok'])
		{
			$this->session->set_flashdata('success', 'Pelajaran selesai. Kerja bagus! Progres belajarmu sudah disimpan.');
		}
		else
		{
			$this->session->set_flashdata('error', 'Progres tidak dapat disimpan karena akses pelajaran sudah berubah. Muat ulang halaman dan coba lagi.');
		}
		redirect('materi/'.rawurlencode($lesson['slug']), 'location', 303);
	}

	private function recover_reading_transaction($result, $slug, $ajax)
	{
		$uncertain = ($result['failure'] ?? 'uncertain') !== 'unavailable';
		$this->output->set_status_header(503)->set_header('Cache-Control: private, no-store')->set_header('X-Robots-Tag: noindex, nofollow');
		if ($ajax)
		{
			$this->output->set_content_type('application/json')->set_output(json_encode(array(
				'ok' => FALSE,
				'failure' => $uncertain ? 'uncertain' : 'unavailable',
				'message' => $uncertain ? 'Hasil penyimpanan belum dapat dipastikan.' : 'Permintaan belum berhasil disimpan.'
			)));
			return;
		}
		$this->render_page('catalog/recovery', array(
			'page_title' => 'Periksa kembali progres baca',
			'recovery_uncertain' => $uncertain,
			'recovery_path' => 'materi/'.rawurlencode($slug),
			'suppress_flash' => TRUE
		));
	}
}
