<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Parent_practice extends MY_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_login(array('parent'));
		$this->load->model('Parent_practice_model');
		$this->output->set_header('Cache-Control: private, no-store');
	}

	private function scalar_fields($fields)
	{
		foreach ($fields as $field)
		{
			$value = $this->input->post($field, FALSE);
			if ($value !== NULL && ! is_string($value))
			{
				show_error('Format isian tidak valid.', 400, 'Permintaan tidak valid');
			}
		}
	}

	private function signature($nonce)
	{
		return hash_hmac('sha256', 'parent-practice:'.$this->current_user['id'].':'.$nonce, $this->config->item('encryption_key'));
	}

	private function result_error($result)
	{
		if ($result['code'] === 404)
		{
			show_404();
		}
		$this->output->set_status_header($result['code']);
		$messages = array(400 => 'Periksa isian dan jadwal. Empat pilihan harus berbeda dan jumlah soal maksimal 50.', 403 => 'Hak paket penyusun latihan tidak aktif. Isi tetap tersimpan dan dapat dibaca.', 409 => 'Periksa revisi, status sesi, kelengkapan soal, atau batas 100 draf. Muat ulang dan periksa isi terbaru sebelum mengubahnya.');
		return isset($messages[$result['code']]) ? $messages[$result['code']] : 'Perubahan belum dapat disimpan. Coba lagi nanti.';
	}

	public function index()
	{
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		$errors = array();
		$error = NULL;
		if ($this->input->method(TRUE) === 'POST')
		{
			$this->scalar_fields(array('title', 'child_id', 'create_token'));
			$token = $this->input->post('create_token');
			if ( ! is_string($token) || ! preg_match('/\A([a-f0-9]{32})\.([a-f0-9]{64})\z/', $token, $parts) || ! hash_equals($this->signature($parts[1]), $parts[2]))
			{
				show_error('Muat ulang formulir sebelum membuat sesi.', 400, 'Permintaan tidak valid');
				return;
			}
			$this->form_validation->set_rules('title', 'Nama latihan', 'required|trim|max_length[180]');
			$this->form_validation->set_rules('child_id', 'Anak tujuan', 'required|regex_match[/\A[1-9][0-9]{0,18}\z/]', array('regex_match' => 'Pilih anak tujuan yang valid.'));
			if ($this->form_validation->run())
			{
				$result = $this->Parent_practice_model->create_draft($this->current_user['id'], (int) $this->input->post('child_id'), $this->input->post('title'), hash('sha256', $this->input->post('child_id').':'.$token));
				if ($result['ok'])
				{
					redirect('keluarga/latihan/'.$result['id']);
					return;
				}
				$error = $this->result_error($result);
			}
			else
			{
				$errors = $this->form_validation->error_array();
			}
		}
		$nonce = bin2hex(random_bytes(16));
		$status_options = study_practice_status_options();
		$requested_status = $this->get_scalar_query_param('status', 20);
		$requested_child = $this->get_scalar_query_param('anak', 19);
		$filters = array('q' => $this->get_scalar_query_param('q', 100), 'status' => isset($status_options[$requested_status]) ? $requested_status : '', 'child_id' => preg_match('/\A[1-9][0-9]{0,17}\z/', $requested_child) ? (int) $requested_child : 0, 'now' => gmdate('Y-m-d H:i:s'));
		$total = $this->Parent_practice_model->count_sessions($this->current_user['id'], $filters);
		$page_count = max(1, (int) ceil($total / 10));
		$page = min($page_count, $this->get_positive_query_int('halaman'));
		$this->render_page('family/practice_drafts', array('page_title' => 'Susun latihan anak', 'children' => $this->Account_model->get_children($this->current_user['id']), 'can_create' => $this->Parent_practice_model->has_access($this->current_user['id']), 'sessions' => $this->Parent_practice_model->sessions($this->current_user['id'], ($page - 1) * 10, $filters), 'create_token' => $nonce.'.'.$this->signature($nonce), 'form_errors' => $errors, 'submit_error' => $error, 'page' => $page, 'page_count' => $page_count, 'filters' => $filters, 'status_options' => $status_options, 'total_sessions' => $total));
	}

	private function question_data()
	{
		foreach (array('prompt' => array('Pertanyaan', 3000), 'explanation' => array('Pembahasan', 3000), 'source_note' => array('Sumber', 1000)) as $field => $settings)
		{
			$this->form_validation->set_rules($field, $settings[0], 'required|trim|max_length['.$settings[1].']');
		}
		$this->form_validation->set_rules('rights_status', 'Hak penggunaan', 'required|in_list[owned,licensed,public_domain]');
		$this->form_validation->set_rules('correct', 'Jawaban benar', 'required|in_list[0,1,2,3]');
		foreach (range(0, 3) as $index)
		{
			$this->form_validation->set_rules('option_'.$index, 'Pilihan '.chr(65 + $index), 'required|trim|max_length[1000]');
		}
		if ( ! $this->form_validation->run())
		{
			return NULL;
		}
		$data = array('prompt' => $this->input->post('prompt'), 'explanation' => $this->input->post('explanation'), 'source_note' => $this->input->post('source_note'), 'rights_status' => $this->input->post('rights_status'), 'correct' => (int) $this->input->post('correct'), 'options' => array());
		foreach (range(0, 3) as $index)
		{
			$data['options'][] = $this->input->post('option_'.$index);
		}
		return $data;
	}

	public function edit($session_id = 0)
	{
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		$session = $this->Parent_practice_model->find_session($this->current_user['id'], $session_id);
		if ( ! $session)
		{
			show_404();
			return;
		}
		$error = NULL;
		$errors = array();
		$selected_id = (int) $this->get_scalar_query_param('edit', 19);
		if ($this->input->method(TRUE) === 'POST')
		{
			$this->scalar_fields(array('action', 'revision', 'question_id', 'title', 'prompt', 'explanation', 'source_note', 'rights_status', 'correct', 'option_0', 'option_1', 'option_2', 'option_3', 'confirm', 'available_from', 'available_until'));
			$action = $this->input->post('action');
			$revision = $this->input->post('revision');
			$posted_id = $this->input->post('question_id');
			if ( ! in_array($action, array('title', 'save', 'delete', 'cancel', 'deliver', 'withdraw'), TRUE) || ! is_string($revision) || ! preg_match('/\A(?:0|[1-9][0-9]{0,8})\z/', $revision) || ($posted_id !== NULL && ! preg_match('/\A(?:0|[1-9][0-9]{0,18})\z/', $posted_id)) || (in_array($action, array('delete', 'cancel', 'deliver', 'withdraw'), TRUE) && $this->input->post('confirm') !== '1'))
			{
				show_error('Aksi, revisi, atau konfirmasi tidak valid.', 400, 'Permintaan tidak valid');
				return;
			}
			$selected_id = (int) $posted_id;
			$data = $action === 'save' ? $this->question_data() : array('title' => $this->input->post('title'));
			if ($action === 'deliver')
			{
				$this->load->library('Practice_schedule');
				$data = $this->practice_schedule->parse($this->input->post('available_from'), $this->input->post('available_until'));
				if ($data === NULL)
				{
					$errors['available_until'] = 'Isi jadwal WIB yang valid: akhir harus setelah mulai dan belum lewat, maksimal 90 hari dari sekarang. Kosongkan waktu mulai untuk langsung tersedia.';
					$this->output->set_status_header(400);
				}
			}
			if ($action === 'title')
			{
				$this->form_validation->set_rules('title', 'Nama latihan', 'required|trim|max_length[180]');
				if ( ! $this->form_validation->run())
				{
					$data = NULL;
				}
			}
			if ($data !== NULL)
			{
				if ($action === 'deliver')
				{
					$result = $this->Parent_practice_model->deliver($this->current_user['id'], $session_id, (int) $revision, $data);
				}
				elseif ($action === 'withdraw')
				{
					$result = $this->Parent_practice_model->withdraw($this->current_user['id'], $session_id, (int) $revision);
				}
				else
				{
					$result = $this->Parent_practice_model->change_draft($this->current_user['id'], $session_id, (int) $revision, $action, $selected_id, $data);
				}
				if ($result['ok'])
				{
					$messages = array('cancel' => 'Draf dibatalkan. Isinya tetap tersimpan sebagai riwayat.', 'deliver' => 'Sesi terkirim. Anak dapat membuka sesuai jadwal selama hak paket aktif.', 'withdraw' => 'Sesi dibatalkan. Jawaban yang pernah disimpan tetap menjadi riwayat, tanpa nilai final.');
					$this->session->set_flashdata('success', isset($messages[$action]) ? $messages[$action] : 'Perubahan draf tersimpan. Belum dikirim kepada anak.');
					redirect('keluarga/latihan/'.(int) $session_id);
					return;
				}
				$error = $this->result_error($result);
			}
			else
			{
				$errors += $this->form_validation->error_array();
			}
			$session = $this->Parent_practice_model->find_session($this->current_user['id'], $session_id);
		}
		if ( ! $session)
		{
			show_404();
			return;
		}
		$questions = $this->Parent_practice_model->questions($this->current_user['id'], $session_id);
		if (count($questions) !== $this->db->where('session_id', (int) $session_id)->count_all_results('st_session_questions'))
		{
			show_error('Isi atau salinan soal belum dapat dimuat. Hubungi pengelola.', 409, 'Sesi tidak tersedia');
			return;
		}
		$selected = NULL;
		foreach ($questions as $question)
		{
			if ((int) $question['id'] === $selected_id)
			{
				$selected = $question;
			}
		}
		if ($selected_id > 0 && ! $selected)
		{
			show_404();
			return;
		}
		$page_count = max(1, (int) ceil(count($questions) / 10));
		$page = min($page_count, $this->get_positive_query_int('halaman'));
		$this->render_page('family/practice_editor', array('page_title' => 'Draf latihan anak', 'practice_session' => $session, 'questions' => array_slice($questions, ($page - 1) * 10, 10), 'total_questions' => count($questions), 'selected_question' => $selected, 'can_edit' => $session['status'] === 'draft' && $this->Parent_practice_model->has_access($this->current_user['id']), 'form_errors' => $errors, 'submit_error' => $error, 'page' => $page, 'page_count' => $page_count));
	}

	public function result($session_id = 0)
	{
		if ( ! in_array($this->input->method(TRUE), array('GET', 'HEAD'), TRUE))
		{
			header('Allow: GET, HEAD');
			show_error('Hasil hanya dapat dibaca.', 405, 'Metode tidak tersedia');
		}
		$session = $this->Parent_practice_model->find_session($this->current_user['id'], $session_id);
		$attempt = $session ? $this->db->where('session_id', (int) $session_id)->where('learner_user_id', $session['learner_user_id'])->where('status', 'graded')->get('st_practice_attempts')->row_array() : NULL;
		if ( ! $attempt)
		{
			show_404();
			return;
		}
		$attempt += array('title' => $session['title'], 'course_id' => NULL, 'session_type' => 'parent_assigned');
		$this->load->model('Practice_model');
		$questions = $this->Practice_model->display_questions($attempt);
		if (count($questions) !== (int) $attempt['question_count'])
		{
			show_error('Salinan soal belum dapat dimuat.', 409, 'Hasil tidak tersedia');
			return;
		}
		$this->render_page('practice/attempt', array('page_title' => 'Hasil latihan keluarga', 'attempt' => $attempt, 'questions' => $questions, 'submit_error' => NULL, 'can_retry' => FALSE, 'report_child' => array('id' => $session['learner_user_id'], 'display_name' => $session['child_name']), 'history_path_override' => 'keluarga/latihan/'.$session_id));
	}

	public function copy($session_id = 0)
	{
		if ( ! in_array($this->input->method(TRUE), array('GET', 'HEAD', 'POST'), TRUE))
		{
			header('Allow: GET, HEAD, POST');
			show_error('Metode tidak tersedia.', 405, 'Permintaan tidak valid');
		}
		$source = $this->Parent_practice_model->find_session($this->current_user['id'], $session_id);
		if ( ! $source)
		{
			show_404();
			return;
		}
		$error = NULL;
		$errors = array();
		if ($this->input->method(TRUE) === 'POST')
		{
			$this->scalar_fields(array('title', 'child_id', 'copy_token', 'revision', 'confirm'));
			$token = $this->input->post('copy_token');
			$revision = $this->input->post('revision');
			if ( ! is_string($token) || ! preg_match('/\A([a-f0-9]{32})\.([a-f0-9]{64})\z/', $token, $parts) || ! hash_equals($this->signature('copy:'.$source['id'].':'.$parts[1]), $parts[2]) || ! is_string($revision) || ! preg_match('/\A(?:0|[1-9][0-9]{0,8})\z/', $revision) || $this->input->post('confirm') !== '1')
			{
				show_error('Muat ulang formulir dan konfirmasi sebelum menyalin.', 400, 'Permintaan tidak valid');
				return;
			}
			$this->form_validation->set_rules('title', 'Nama latihan baru', 'required|trim|max_length[180]');
			$this->form_validation->set_rules('child_id', 'Anak tujuan', 'required|regex_match[/\A[1-9][0-9]{0,18}\z/]', array('regex_match' => 'Pilih anak tujuan yang valid.'));
			if ($this->form_validation->run())
			{
				$this->load->model('Parent_practice_copy_model');
				$key = hash('sha256', 'copy:'.$source['id'].':'.$revision.':'.$this->input->post('child_id').':'.$token);
				$result = $this->Parent_practice_copy_model->copy_session($this->current_user['id'], $source['id'], (int) $revision, (int) $this->input->post('child_id'), $this->input->post('title'), $key);
				if ($result['ok'])
				{
					$this->session->set_flashdata('success', 'Salinan draf siap diperiksa. Jadwal, jawaban dan nilai lama tidak disalin; belum dikirim kepada anak.');
					redirect('keluarga/latihan/'.$result['id']);
					return;
				}
				$error = $this->result_error($result);
			}
			else
			{
				$errors = $this->form_validation->error_array();
				$this->output->set_status_header(400);
			}
		}
		$nonce = bin2hex(random_bytes(16));
		$this->render_page('family/practice_copy', array('page_title' => 'Salin latihan', 'source_session' => $source, 'children' => $this->Account_model->get_children($this->current_user['id']), 'can_copy' => $this->Parent_practice_model->has_access($this->current_user['id']), 'copy_token' => $nonce.'.'.$this->signature('copy:'.$source['id'].':'.$nonce), 'form_errors' => $errors, 'submit_error' => $error));
	}
}
