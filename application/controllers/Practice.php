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

	public function catalog()
	{
		$this->require_http_methods(array('GET', 'HEAD'));
		$filters = array('kelas' => study_learner_grade_filter($this->current_user), 'q' => $this->get_scalar_query_param('q', 100), 'category_id' => 0);
		$total = $this->Content_model->count_courses($filters, TRUE);
		$page_count = max(1, (int)ceil($total / 12));
		$page = min($page_count, $this->get_positive_query_int('halaman'));
		$courses = $this->Content_model->list_courses($filters, 12, ($page - 1) * 12, TRUE);
		$counts = array();
		if ($courses) {
			$counts = array_column($this->db->select('course_id, COUNT(*) AS total', FALSE)->where_in('course_id', array_column($courses, 'id'))->where('visibility', 'shared')->where('target_child_user_id', NULL)->where('status', 'published')->group_by('course_id')->get('st_questions')->result_array(), 'total', 'course_id');
		}
		$this->render_page('practice/catalog', compact('courses','counts','filters','page','page_count','total') + array('page_title' => 'Latihan kursus gratis'));
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
			$sheet=array();
			foreach(Practice_sheet::defaults() as $k=>$default){$sheet[$k]=$this->input->post($k,FALSE)??$default;}
			$result = $this->Practice_model->start($course_id, $this->current_user['id'], hash('sha256', $token), $sheet);
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
		$this->render_page('practice/course', array('page_title' => 'Latihan kursus gratis', 'course' => $course, 'can_start' => $can_start, 'question_count' => $question_count, 'start_token' => $nonce.'.'.$this->start_signature($course_id, $nonce)));
	}

	public function attempt($attempt_id = 0, $view = 'result')
	{
		if (!in_array($view, array('result', 'review'), TRUE)) { show_404(); return; }
		$this->require_http_methods($view === 'review' ? array('GET', 'HEAD') : array('GET', 'HEAD', 'POST'));
		$attempt = $this->Practice_model->find_attempt($attempt_id, $this->current_user['id']);
		if ( ! $attempt)
		{
			show_404();
			return;
		}
		if ($view === 'review' && $attempt['status'] !== 'graded') { show_404(); return; }
		if ( ! $this->Practice_model->can_read_attempt($attempt, $this->current_user['id']))
		{
			show_error('Akses atau jadwal latihan telah berubah. Jawaban yang sudah disimpan tetap tersimpan; minta bantuan orang tua atau pengelola untuk memeriksa akses.', 403, 'Latihan belum dapat dilanjutkan');
			return;
		}
		if ($this->input->method(TRUE)==='GET' && $this->input->get('sheet_state')==='1') {
			$this->sheet_response($attempt);
			return;
		}
		$error = NULL;
		if ($this->input->method(TRUE) === 'POST')
		{
			if ($this->input->post('sheet_presence') === '1') {
				$this->load->model('Practice_live_model');
				$question_id = $this->input->post('current_question_id', FALSE);
				if (!is_string($question_id) || !preg_match('/\A[1-9][0-9]{0,18}\z/', $question_id) || !$this->Practice_live_model->mark_seen($attempt, (int)$question_id)) { show_404(); return; }
				$this->sheet_response($attempt);
				return;
			}
			$answers = $this->input->post('answers', FALSE);
			$json = $this->input->post('answers_json', FALSE);
			if ($json !== NULL) {
				$answers = is_string($json) ? json_decode($json, TRUE, 4) : FALSE;
				if (!is_array($answers)) { show_error('Format jawaban tidak valid. Jawaban sebelumnya tidak berubah.', 400); return; }
			} elseif ($this->input->post('answers_form_start', FALSE) !== NULL && $this->input->post('answers_form_end', FALSE) !== '1') {
				show_error('Jawaban belum diterima lengkap. Aktifkan JavaScript dan kirim ulang; jawaban sebelumnya tidak berubah.', 400); return;
			}
			$revision = $this->input->post('revision');
			$action = $this->input->post('action');
			if ($answers === NULL)
			{
				$answers = array();
			}
			if ( ! is_array($answers) || count($answers) > (int)$attempt['question_count'] || ! is_string($revision) || ! preg_match('/\A(?:0|[1-9][0-9]{0,8})\z/', $revision) || ! in_array($action, array('save', 'finish'), TRUE))
			{
				show_error('Format jawaban tidak valid.', 400, 'Permintaan tidak valid');
				return;
			}
			$result = $this->Practice_model->save_answers($attempt_id, $this->current_user['id'], (int) $revision, $answers, $action === 'finish', $this->input->post('focus_events',FALSE)??array());
			if ($this->input->post('sheet_ajax')==='1') {
				$fresh=$this->Practice_model->find_attempt($attempt_id,$this->current_user['id']);
				$this->sheet_response($fresh,$result);
				return;
			}
			if ($result['ok'])
			{
				$this->session->set_flashdata('success', $result['status'] === 'graded' ? 'Latihan selesai. Nilai dan rincian hasilmu sudah tersedia.' : 'Jawaban tersimpan. Kamu bisa melanjutkan latihan nanti.');
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
		$result_page = $attempt['status'] === 'graded' && $view !== 'review';
		$this->load->model('Practice_live_model');
		$this->render_page($result_page ? 'practice/result' : 'practice/attempt', array(
			'page_title' => $attempt['status'] === 'graded' ? ($result_page ? 'Nilai latihan' : 'Pembahasan latihan') : 'Kerjakan latihan',
			'attempt' => $attempt,
			'questions' => $questions,
			'submit_error' => $error,
			'can_retry' => $can_retry,
			'review_view' => $view === 'review',
			'deadline' => $this->Practice_model->deadline($attempt),
			'sheet' => $this->Practice_model->sheet_state($attempt),
			'load_practice_sheet_script' => !$result_page,
			'parent_live_enabled' => $this->Practice_live_model->enabled_for_attempt($attempt),
			'load_unsaved_form_script' => FALSE
		));
	}

	private function sheet_response($attempt, $result = array('ok'=>TRUE))
	{
		if (!$attempt) { show_404(); return; }
		$this->output->set_status_header($result['ok']?200:($result['code']??503));
		$this->output->set_content_type('application/json')->set_output(json_encode(array(
			'ok'=>(bool)$result['ok'],'conflict'=>!empty($result['conflict']),
			'status'=>$attempt['status'],'revision'=>(int)$attempt['answer_revision'],
			'focus_count'=>$this->Practice_model->focus_count($attempt['id']),
			'csrf_name'=>$this->security->get_csrf_token_name(),'csrf_token'=>$this->security->get_csrf_hash(),
			'server_time'=>time()
		),JSON_THROW_ON_ERROR));
	}

	public function image($attempt_id=0,$question_id=0,$image_index='0')
	{
		$this->require_http_methods(array('GET','HEAD'));
		$attempt=$this->Practice_model->find_attempt($attempt_id,$this->current_user['id']);
		if($attempt && !$this->Practice_model->can_read_attempt($attempt,$this->current_user['id'])){$attempt=NULL;}
		if(!$attempt && $this->current_user['account_kind']==='parent'){
			$this->load->model('Family_practice_model');
			$owner=$this->db->select('learner_user_id')->where('id',(int)$attempt_id)->get('st_practice_attempts')->row_array();
			if($owner){
				$attempt=$this->Family_practice_model->result($this->current_user['id'],$owner['learner_user_id'],$attempt_id);
				if(!$attempt){
					$candidate=$this->Family_practice_model->live_attempt($this->current_user['id'],$owner['learner_user_id'],$attempt_id);
					$this->load->model('Practice_live_model');
					if($candidate&&$this->Practice_live_model->enabled_for_attempt($candidate)&&$this->Practice_model->can_read_attempt($candidate,$owner['learner_user_id']))$attempt=$candidate;
				}
			}
		}
		if(!$attempt && in_array('system_admin',$this->current_user['roles'],TRUE)){
			$owner=$this->db->select('learner_user_id')->where('id',(int)$attempt_id)->get('st_practice_attempts')->row_array();
			if($owner){$candidate=$this->Practice_model->find_attempt($attempt_id,$owner['learner_user_id']);if($candidate&&$candidate['status']==='graded'&&in_array($candidate['session_type'],array('self_practice','assessment'),TRUE)){
				if($this->db->insert('st_audit_events',array('actor_user_id'=>$this->current_user['id'],'event_type'=>'learning.image_read','entity_type'=>'practice_attempt','entity_id'=>$attempt_id,'details_json'=>'{}','created_at'=>gmdate('Y-m-d H:i:s')))){$attempt=$candidate;}
			}}
		}
		$image=$attempt?$this->Practice_model->snapshot_image($attempt,$question_id):NULL;
		$this->load->model('Bank_model');
		$image=$this->Bank_model->image_at($image,$image_index);
		if(!$image){show_404();return;}$this->load->library('lesson_image_store');$bytes=$this->lesson_image_store->read($image);if($bytes===FALSE){show_404();return;}
		$this->output->set_header('X-Content-Type-Options: nosniff')->set_header('X-Robots-Tag: noindex, nofollow')->set_content_type($image['mime_type'])->set_output($bytes);
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
