<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Matching extends MY_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->require_login();
		$this->load->model(array('Matching_run_model', 'Content_model', 'Flashcard_model'));
		$this->output->set_header('Cache-Control: private, no-store');
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
	}

	private function methods($allow_post = FALSE)
	{
		$allowed = $allow_post ? array('GET', 'HEAD', 'POST') : array('GET', 'HEAD');
		if ( ! in_array($this->input->method(TRUE), $allowed, TRUE))
		{
			header('Allow: '.implode(', ', $allowed));
			show_error('Metode ini tidak tersedia.', 405, 'Permintaan tidak tersedia');
		}
	}

	private function signature($purpose, $nonce)
	{
		return hash_hmac('sha256', $this->current_user['id'].':'.$purpose.':'.$nonce, $this->config->item('encryption_key'));
	}

	private function recover_transaction($result, $path)
	{
		$this->output->set_status_header(503);
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		$this->render_page('games/recovery', array(
			'page_title' => 'Periksa kembali permainan',
			'recovery_uncertain' => ($result['failure'] ?? 'uncertain') !== 'unavailable',
			'recovery_path' => $path,
			'suppress_flash' => TRUE
		));
	}

	private function token($purpose)
	{
		$nonce = bin2hex(random_bytes(16));
		return $nonce.'.'.$this->signature($purpose, $nonce);
	}

	private function posted_key($purpose)
	{
		$token = $this->input->post('action_token');
		if ( ! is_string($token) || ! preg_match('/\A([a-f0-9]{32})\.([a-f0-9]{64})\z/', $token, $parts) || ! hash_equals($this->signature($purpose, $parts[1]), $parts[2]))
		{
			show_error('Muat ulang halaman lalu coba kembali.', 400, 'Formulir tidak valid');
		}
		return hash('sha256', $token);
	}

	private function posted_integer($name, $max)
	{
		$value = $this->input->post($name);
		if ( ! is_string($value) || ! preg_match('/\A(?:0|[1-9][0-9]{0,8})\z/', $value) || (int) $value > $max)
		{
			show_error('Pilih satu kartu pada setiap kolom lalu kirim kembali.', 400, 'Pilihan belum valid');
		}
		return (int) $value;
	}

	private function owned($id)
	{
		if ( ! is_string($id) || ! preg_match('/\A[1-9][0-9]{0,17}\z/', $id))
		{
			show_404();
		}
		$run = $this->Matching_run_model->find_owned($id, $this->current_user['id']);
		if ( ! $run)
		{
			show_404();
		}
		return $run;
	}

	public function index()
	{
		$this->methods();
		$query = $this->get_scalar_query_param('q', 100);
		$status = $this->get_scalar_query_param('status', 20);
		if ( ! in_array($status, array('in_progress', 'completed', 'abandoned'), TRUE))
		{
			$status = '';
		}
		$total = $this->Matching_run_model->history($this->current_user['id'], $query, $status, 0, TRUE);
		$page_count = max(1, (int) ceil($total / 10));
		$page = min($page_count, $this->get_positive_query_int('halaman'));
		$runs = $this->Matching_run_model->history($this->current_user['id'], $query, $status, ($page - 1) * 10);
		$this->render_page('games/history', compact('runs', 'query', 'status', 'total', 'page', 'page_count') + array('page_title' => 'Riwayat permainan saya'));
	}

	public function lesson($slug = '')
	{
		$this->methods(TRUE);
		$lesson = $this->Content_model->get_published_lesson($slug);
		if ( ! $lesson)
		{
			show_404();
		}
		if ( ! $this->Content_model->can_access_lesson($this->current_user['id'], $lesson))
		{
			show_error('Akses materi belum terbuka untuk akun Anda.', 403, 'Akses dibatasi');
		}
		$error = NULL;
		if ($this->input->method(TRUE) === 'POST')
		{
			$key = $this->posted_key('start:'.$lesson['id']);
			$result = $this->Matching_run_model->start($lesson['id'], $this->current_user['id'], $key);
			if ($result['ok'])
			{
				redirect('permainan/tersimpan/'.$result['id'], 'location', 303);
				return;
			}
			if ($result['code'] === 503) { $this->recover_transaction($result, 'permainan/tersimpan/pelajaran/'.rawurlencode($lesson['slug'])); return; }
			$this->output->set_status_header($result['code']);
			$error = 'Permainan belum dapat dimulai. Jika tab lain sudah memulai, gunakan Lanjutkan permainan; jika belum, muat ulang materi.';
		}
		$deck = $this->Flashcard_model->get_published_deck_for_lesson($lesson['id']);
		$this->load->library('Matching_cards');
		$cards = $deck ? $this->matching_cards->eligible($this->Flashcard_model->get_published_cards($deck['id'])) : array();
		$this->render_page('games/start', array('page_title' => 'Simpan perjalanan bermain', 'lesson' => $lesson, 'card_count' => count($cards), 'active_id' => $this->Matching_run_model->active_id($lesson['id'], $this->current_user['id']), 'action_token' => $this->token('start:'.$lesson['id']), 'submit_error' => $error));
	}

	public function run($id = '')
	{
		$this->methods(TRUE);
		$run = $this->owned($id);
		$error = NULL;
		if ($this->input->method(TRUE) === 'POST')
		{
			$revision = $this->posted_integer('revision', 10000);
			$key = $this->posted_key('move:'.$id.':'.$revision);
			$left = $this->posted_integer('left', 99);
			$right = $this->posted_integer('right', 99);
			$result = $this->Matching_run_model->move($id, $this->current_user['id'], $revision, $key, $left, $right);
			if ($result['ok'])
			{
				$this->session->set_flashdata('matching_feedback', $left === $right ? 'Cocok! Pasanganmu sudah tersimpan.' : 'Belum cocok. Pilihan sudah tersimpan; coba pasangan lain, ya.');
				redirect('permainan/tersimpan/'.$id.'#hasil-pilihan', 'location', 303);
				return;
			}
			if ($result['code'] === 503) { $this->recover_transaction($result, 'permainan/tersimpan/'.(int) $id); return; }
			$this->output->set_status_header($result['code']);
			$error = $result['code'] === 409 ? 'Halaman sudah berubah atau pilihan tidak tersedia. Periksa progres terbaru di bawah, lalu pilih kembali.' : 'Pilihan belum tersimpan. Periksa akses materi atau muat ulang halaman sebelum mencoba lagi.';
			$run = $this->owned($id);
		}
		$can_continue = $this->Matching_run_model->can_continue($run);
		if ($run['status'] === 'in_progress' && ! $can_continue)
		{
			$this->output->set_status_header(403);
		}
		$state = json_decode($run['state_json'], TRUE);
		$snapshot = json_decode($run['snapshot_json'], TRUE);
		if ( ! is_array($state) || ! is_array($snapshot) || ! isset($state['matched'], $snapshot['cards'], $snapshot['rounds']))
		{
			show_error('Data permainan belum dapat dibaca. Hubungi pengelola; jangan mulai ulang sebelum diperiksa.', 409, 'Permainan belum tersedia');
		}
		$left_order = $can_continue ? $snapshot['rounds'][$state['round_index']] : array();
		$right_order = $left_order;
		usort($right_order, function ($first, $second) use ($id, $state) {
			return strcmp(hash('sha256', $id.':'.$state['round_index'].':'.$first), hash('sha256', $id.':'.$state['round_index'].':'.$second));
		});
		$this->render_page('games/run', compact('run', 'state', 'snapshot', 'can_continue', 'left_order', 'right_order') + array('page_title' => 'Cocokkan Pasangan tersimpan', 'action_token' => $this->token('move:'.$id.':'.$run['revision']), 'submit_error' => $error, 'feedback' => $this->session->flashdata('matching_feedback'), 'lesson' => $this->Matching_run_model->current_lesson($run['lesson_id']), 'load_matching_save_script' => $can_continue && $state['move_count'] < 10000));
	}

	public function stop($id = '')
	{
		$this->methods(TRUE);
		$run = $this->owned($id);
		$error = NULL;
		if ($this->input->method(TRUE) === 'POST')
		{
			$revision = $this->posted_integer('revision', 10000);
			$this->posted_key('stop:'.$id.':'.$revision);
			if ($this->input->post('confirm') !== '1')
			{
				show_error('Centang persetujuan untuk menghentikan permainan.', 400, 'Konfirmasi diperlukan');
			}
			$result = $this->Matching_run_model->abandon($id, $this->current_user['id'], $revision);
			if ($result['ok'])
			{
				redirect('permainan/tersimpan/'.$id, 'location', 303);
				return;
			}
			if ($result['code'] === 503) { $this->recover_transaction($result, 'permainan/tersimpan/'.(int) $id); return; }
			$this->output->set_status_header($result['code']);
			$error = 'Progres sudah berubah atau permainan sudah selesai. Buka kembali permainan sebelum memutuskan untuk menghentikannya.';
			$run = $this->owned($id);
		}
		$this->render_page('games/stop', array('page_title' => 'Hentikan permainan?', 'run' => $run, 'submit_error' => $error, 'action_token' => $this->token('stop:'.$id.':'.$run['revision'])));
	}
}
