<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Practice extends MY_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_login();
		$this->load->model('Practice_model');
		$this->load->model('Content_model');
		$this->output->set_header('Cache-Control: private, no-store');
	}

	private function start_signature($course_id, $nonce)
	{
		return hash_hmac('sha256', $this->current_user['id'].':'.(int) $course_id.':'.$nonce, $this->config->item('encryption_key'));
	}

	private function recover_transaction($result, $path)
	{
		$this->output->set_status_header(503);
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		$this->render_page('practice/recovery', array(
			'page_title' => 'Periksa kembali latihan',
			'recovery_uncertain' => ($result['failure'] ?? 'uncertain') !== 'unavailable',
			'recovery_path' => $path,
			'suppress_flash' => TRUE
		));
	}

	public function assignments()
	{
		$this->require_login(array('child'));
		$this->require_http_methods(array('GET', 'HEAD'));
		$this->load->model('Assignment_model');
		$page_count = max(1, (int) ceil($this->Assignment_model->count_inbox($this->current_user['id']) / 10));
		$page = min($page_count, $this->get_positive_query_int('halaman'));
		$this->render_page('practice/assignments', array('page_title' => 'Latihan dari keluarga', 'sessions' => $this->Assignment_model->inbox($this->current_user['id'], ($page - 1) * 10), 'page' => $page, 'page_count' => $page_count));
	}

	public function assigned($session_id = 0)
	{
		$this->require_login(array('child'));
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		$this->load->model('Assignment_model');
		$session = $this->Assignment_model->child_session($session_id, $this->current_user['id']);
		if ( ! $session)
		{
			show_404();
			return;
		}
		if ($this->input->method(TRUE) === 'POST')
		{
			if ($this->input->post('action') !== 'start')
			{
				show_error('Aksi tidak valid.', 400, 'Permintaan tidak valid');
				return;
			}
			$result = $this->Assignment_model->start($session_id, $this->current_user['id']);
			if ( ! $result['ok'])
			{
				if ($result['code'] === 503) { $this->recover_transaction($result, 'latihan/keluarga/'.(int) $session_id); return; }
				show_error('Sesi belum tersedia atau akses telah berubah. Minta orang tua memeriksa jadwal dan hak paket.', $result['code'], 'Latihan belum dapat dibuka');
				return;
			}
			redirect('latihan/percobaan/'.$result['id'], 'location', 303);
			return;
		}
		$attempt = $this->db->where('session_id', (int) $session_id)->where('learner_user_id', $this->current_user['id'])->get('st_practice_attempts')->row_array();
		$this->render_page('practice/assigned', array('page_title' => 'Latihan dari keluarga', 'practice_session' => $session, 'can_start' => $this->Assignment_model->can_work($session), 'existing_attempt' => $attempt));
	}

	public function course($course_id = 0)
	{
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		$course = $this->Content_model->get_course($course_id);
		if ( ! $course || $course['status'] !== 'published')
		{
			show_404();
			return;
		}
		$can_start = $this->Practice_model->course_access($this->current_user['id'], $course);
		$question_count = $this->db->where('course_id', (int) $course_id)->where('visibility', 'shared')->where('target_child_user_id', NULL)->where('status', 'published')->count_all_results('st_questions');
		if ($this->input->method(TRUE) === 'POST')
		{
			$token = $this->input->post('start_token');
			if ( ! is_string($token) || ! preg_match('/\A([a-f0-9]{32})\.([a-f0-9]{64})\z/', $token, $parts) || ! hash_equals($this->start_signature($course_id, $parts[1]), $parts[2]))
			{
				show_error('Muat ulang halaman latihan lalu coba lagi.', 400, 'Permintaan tidak valid');
				return;
			}
			$result = $this->Practice_model->start($course_id, $this->current_user['id'], hash('sha256', $token));
			if ( ! $result['ok'])
			{
				if ($result['code'] === 503) { $this->recover_transaction($result, 'latihan/kursus/'.(int) $course_id); return; }
				show_error('Latihan belum dapat dimulai. Periksa akses kursus atau kembali ke daftar materi.', $result['code'], 'Latihan belum tersedia');
				return;
			}
			redirect('latihan/percobaan/'.$result['id'], 'location', 303);
			return;
		}
		$nonce = bin2hex(random_bytes(16));
		$this->render_page('practice/course', array('page_title' => 'Latihan mandiri', 'course' => $course, 'can_start' => $can_start, 'question_count' => $question_count, 'start_token' => $nonce.'.'.$this->start_signature($course_id, $nonce)));
	}

	public function attempt($attempt_id = 0)
	{
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		$attempt = $this->Practice_model->find_attempt($attempt_id, $this->current_user['id']);
		if ( ! $attempt)
		{
			show_404();
			return;
		}
		if ( ! $this->Practice_model->can_read_attempt($attempt, $this->current_user['id']))
		{
			show_error('Akses atau jadwal latihan telah berubah. Jawaban yang sudah disimpan tetap tersimpan; minta bantuan orang tua atau pengelola untuk memeriksa akses.', 403, 'Latihan belum dapat dilanjutkan');
			return;
		}
		$error = NULL;
		if ($this->input->method(TRUE) === 'POST')
		{
			$answers = $this->input->post('answers');
			$revision = $this->input->post('revision');
			$action = $this->input->post('action');
			if ($answers === NULL)
			{
				$answers = array();
			}
			if ( ! is_array($answers) || count($answers) > 50 || ! is_string($revision) || ! preg_match('/\A(?:0|[1-9][0-9]{0,8})\z/', $revision) || ! in_array($action, array('save', 'finish'), TRUE))
			{
				show_error('Format jawaban tidak valid.', 400, 'Permintaan tidak valid');
				return;
			}
			$result = $this->Practice_model->save_answers($attempt_id, $this->current_user['id'], (int) $revision, $answers, $action === 'finish');
			if ($result['ok'])
			{
				$this->session->set_flashdata('success', $result['status'] === 'graded' ? 'Latihan selesai. Yuk, lihat pembahasannya.' : 'Jawaban tersimpan. Kamu bisa melanjutkan latihan nanti.');
				redirect('latihan/percobaan/'.$result['id'], 'location', 303);
				return;
			}
			if ($result['code'] === 503) { $this->recover_transaction($result, 'latihan/percobaan/'.(int) $attempt_id); return; }
			if ($result['code'] !== 409 && $result['code'] !== 400)
			{
				show_error('Jawaban tidak dapat disimpan. Periksa kembali akses dan jadwal latihan.', $result['code'], 'Akses latihan berubah');
				return;
			}
			$this->output->set_status_header($result['code']);
			$error = $result['code'] === 409 ? 'Latihan telah diperbarui dari tab lain. Jawaban dari tab ini tidak menimpa simpanan terbaru. Periksa jawaban yang tampil sebelum melanjutkan.' : 'Pilihan jawaban tidak cocok dengan soal. Jawaban sebelumnya tetap tersimpan.';
			$attempt = $this->Practice_model->find_attempt($attempt_id, $this->current_user['id']);
		}
		$questions = $this->Practice_model->display_questions($attempt);
		if (count($questions) !== (int) $attempt['question_count'])
		{
			show_error('Salinan soal belum dapat dimuat. Hubungi pengelola.', 409, 'Latihan tidak tersedia');
			return;
		}
		$can_retry = $attempt['session_type'] === 'course_quiz' && $this->Practice_model->course_access($this->current_user['id'], $this->Content_model->get_course($attempt['course_id']));
		$this->render_page('practice/attempt', array(
			'page_title' => $attempt['status'] === 'graded' ? 'Hasil latihan' : 'Kerjakan latihan',
			'attempt' => $attempt,
			'questions' => $questions,
			'submit_error' => $error,
			'can_retry' => $can_retry,
			'load_unsaved_form_script' => $attempt['status'] === 'in_progress'
		));
	}

	public function index()
	{
		$this->require_http_methods(array('GET', 'HEAD'));
		$total = $this->Practice_model->count_history($this->current_user['id']);
		$page_count = max(1, (int) ceil($total / 10));
		$page = min($page_count, $this->get_positive_query_int('halaman'));
		$attempts = $this->Practice_model->history($this->current_user['id'], 10, ($page - 1) * 10);
		foreach ($attempts as &$attempt)
		{
			$attempt['can_open'] = $this->Practice_model->can_read_attempt($this->Practice_model->find_attempt($attempt['id'], $this->current_user['id']), $this->current_user['id']);
		}
		unset($attempt);
		$this->render_page('practice/history', array('page_title' => 'Riwayat latihan', 'attempts' => $attempts, 'page' => $page, 'page_count' => $page_count));
	}
}
