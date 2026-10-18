<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Battle extends MY_Controller
{
	public function _remap($method, $params = array())
	{
		$this->require_login(); $this->output->set_header('Cache-Control: private, no-store'); $this->output->set_header('X-Robots-Tag: noindex, nofollow');
		if ( ! in_array($method, array('index', 'create', 'join', 'room', 'live_state', 'manage', 'directory', 'public_room', 'queue', 'review'), TRUE)) { show_404(); return; }
		$this->require_http_methods(in_array($method, array('create', 'join'), TRUE) ? array('POST') : array('GET', 'HEAD', 'POST'));
		if (in_array($method, array('index', 'directory', 'queue', 'live_state'), TRUE)) { $this->require_http_methods(array('GET', 'HEAD')); }
		if (in_array($method, array('manage', 'queue', 'review'), TRUE)) { $this->require_role(array('system_admin')); }
		$this->load->model('Battle_model', 'battle'); $this->db->db_debug = FALSE;
		try { call_user_func_array(array($this, $method), $params); }
		catch (Throwable $error) { log_message('error', 'Private battle request failed without exposing questions or credentials.'); show_error('Battle belum dapat dimuat. Data tersimpan tetap dipertahankan; coba muat ulang.', 503, 'Battle belum tersedia'); }
	}

	private function identifier($value)
	{
		if ( ! is_string($value) || ! preg_match('/\A[1-9][0-9]{0,17}\z/', $value)) { show_404(); exit; } return $value;
	}

	private function token($nonce)
	{
		return $nonce.'.'.hash_hmac('sha256', 'battle-create:'.$this->current_user['id'].':'.$nonce, $this->config->item('encryption_key'));
	}

	private function error_message($result)
	{
		$this->output->set_status_header($result['code']); return array(400 => 'Periksa isian, jadwal WIB, pilihan jawaban, dan sumber/hak soal.', 403 => 'Aksi belum diizinkan. Periksa verifikasi email, persetujuan orang tua, kuota, jadwal, atau status ruang.', 404 => 'Ruang/kode tidak tersedia untuk akun ini.', 409 => 'Data sudah berubah. Tinjau halaman terbaru sebelum mencoba lagi.', 503 => 'Penyimpanan belum dapat dipastikan. Muat ulang sebelum mencoba kembali.')[$result['code']] ?? 'Permintaan belum dapat diproses.';
	}

	public function index($error = '')
	{
		$this->render_page('battle/index', $this->battle->listing($this->current_user['id'], $this->get_positive_query_int('halaman')) + array('page_title' => 'Battle Belajar Gratis', 'settings' => $this->battle->settings(), 'children' => $this->battle->children_for($this->current_user['id']), 'create_token' => $this->token(bin2hex(random_bytes(16))), 'error' => $error));
	}

	public function create()
	{
		$token = $this->input->post('create_token', FALSE);
		if ( ! is_string($token) || ! preg_match('/\A[a-f0-9]{32}\.[a-f0-9]{64}\z/', $token) || ! hash_equals($this->token(substr($token, 0, 32)), $token)) { show_error('Formulir tidak valid. Buka kembali halaman battle.', 400); return; }
		$result = $this->battle->create($this->current_user['id'], $this->input->post(NULL, FALSE), hash('sha256', $token));
		if ($result['ok']) { redirect('belajar/battle/ruang/'.$result['id'], 'location', 303); return; } $this->index($this->error_message($result));
	}

	public function join()
	{
		$window = $this->session->userdata('battle_join_window'); if ( ! is_array($window) || time() - $window['time'] > 60) { $window = array('time' => time(), 'count' => 0); }
		$window['count']++; $this->session->set_userdata('battle_join_window', $window);
		if ($window['count'] > 10) { show_error('Terlalu banyak percobaan kode. Tunggu satu menit.', 429); return; }
		$result = $this->battle->join($this->current_user['id'], $this->input->post('code', FALSE), $this->input->post('child_id', FALSE) ?? '', $this->input->post('consent', FALSE));
		if ($result['ok']) { redirect('belajar/battle/ruang/'.$result['id'], 'location', 303); return; } $this->index($this->error_message($result));
	}

	public function room($id = '')
	{
		$id = $this->identifier($id); $actor = $this->current_user['id']; $error = ''; $data = $this->battle->access($actor, $id);
		if ( ! $data) { show_404(); return; }
		if ($this->input->method(TRUE) === 'POST')
		{
			$action = $this->input->post('action', FALSE); $input = $this->input->post(NULL, FALSE);
			if (in_array($action, array('details', 'question', 'remove', 'publish', 'cancel', 'archive'), TRUE)) { $result = $this->battle->edit($actor, $id, $this->input->post('revision', FALSE), $action, $input); }
			elseif (in_array($action, array('answer', 'finish'), TRUE)) { $result = $this->battle->answer($actor, $id, $this->input->post('entry_revision', FALSE), $action, $this->input->post('value', FALSE)); }
			elseif ($action === 'revoke') { $result = $this->battle->revoke($actor, $id, $this->identifier($this->input->post('entry_id', FALSE))); }
			elseif ($action === 'report') { $result = $this->battle->report($actor, $id, $this->input->post('reason', FALSE)); }
			elseif ($action === 'live_start') { $result = $this->input->post('confirm', FALSE) === '1' ? $this->battle->start_live($actor, $id, $this->input->post('revision', FALSE)) : array('ok' => FALSE, 'code' => 400); }
			elseif (in_array($action, array('list_request', 'list_withdraw'), TRUE)) { $result = $this->battle->request_listing($actor, $id, $this->input->post('revision', FALSE), $input); }
			else { show_error('Aksi tidak valid.', 400); return; }
			if ($result['ok']) { $this->session->set_flashdata('success', 'Perubahan tersimpan.'); redirect('belajar/battle/ruang/'.$id, 'location', 303); return; }
			$error = $this->error_message($result); $data = $this->battle->access($actor, $id); if ( ! $data) { show_404(); return; }
		}
		$room = $data['room']; $ended = time() >= strtotime($room['ends_at'].' UTC'); $started = time() >= strtotime($room['starts_at'].' UTC'); $cancelled = $room['cancelled_at'] !== NULL;
		$live = $room['play_mode'] === 'live'; $live_state = $this->live_clock($data);
		if ($live) { $started = $room['live_started_at'] !== NULL && time() >= strtotime($room['live_started_at'].' UTC'); }
		$questions = $data['host'] ? json_decode($room['draft_json'], TRUE) : array(); $question = NULL; $review = array(); $position = 0;
		$entry = $data['entry'];
		if ($entry)
		{
			$state = json_decode($entry['state_json'], TRUE); $position = (int) $state['position']; $snapshot = json_decode($room['snapshot_json'], TRUE) ?? array();
			$waiting = FALSE; if ($live && $started) { $position = $live_state['position']; $waiting = (int) $state['position'] > $position; }
			if ( ! $waiting && ! $cancelled && ! $entry['revoked_at'] && $room['status'] === 'published' && $started && ! $ended && $entry['status'] !== 'completed' && isset($snapshot[$position])) { $item = $snapshot[$position]; $question = array('prompt' => $item['prompt'], 'options' => $item['options']); }
			if ($ended && ! $cancelled && ! $entry['revoked_at']) { foreach ($snapshot as $i => $item) { $review[] = $item + array('submitted' => $state['answers'][$i] ?? ''); } }
		}
		// Do not hand private snapshots to templates or serialized UI state.
		unset($data['room']['draft_json'], $data['room']['snapshot_json'], $data['room']['create_key']); unset($room['draft_json'], $room['snapshot_json'], $room['create_key']);
		$this->render_page('battle/room', $data + compact('error', 'ended', 'started', 'cancelled', 'questions', 'question', 'review', 'position', 'live', 'live_state') + array('page_title' => $room['title'], 'settings' => $this->battle->settings(), 'participants' => $data['host'] ? $this->battle->participants($id) : array()));
	}

	private function live_clock($data)
	{
		$r = $data['room']; $now = time(); $start = $r['live_started_at'] ? strtotime($r['live_started_at'].' UTC') : NULL; $end = strtotime($r['ends_at'].' UTC'); $position = $start !== NULL && $now >= $start ? (int) floor(($now - $start) / max(1, (int) $r['question_seconds'])) : -1;
		$phase = $r['status'] !== 'published' ? $r['status'] : ($now >= $end ? 'ended' : ($start === NULL ? 'lobby' : ($now < $start ? 'countdown' : 'question')));
		if ($phase !== 'question') { $position = -1; }
		$s = $this->battle->settings();
		return array('phase' => $phase, 'position' => $position, 'remaining' => max(0, ($phase === 'question' ? min($end, $start + ($position + 1) * (int) $r['question_seconds']) : ($phase === 'countdown' ? $start : $end)) - $now), 'key' => $phase.'|'.$position.'|'.($data['entry']['revoked_at'] ?? '').'|'.$s['enabled'].'|'.$s['live_enabled']);
	}
	public function live_state($id = '')
	{
		$data = $this->battle->access($this->current_user['id'], $this->identifier($id)); if ( ! $data || $data['room']['play_mode'] !== 'live') { show_404(); return; }
		$this->output->set_content_type('application/json')->set_output(json_encode($this->live_clock($data)));
	}

	public function directory()
	{
		$age = $this->input->get('jenjang', FALSE) ?? ''; if ( ! in_array($age, array('', 'primary', 'secondary', 'all_ages'), TRUE)) { show_error('Jenjang tidak valid.', 400); return; }
		$this->render_page('battle/directory', $this->battle->directory($this->get_positive_query_int('halaman'), $age) + array('page_title' => 'Battle untuk semua member', 'settings' => $this->battle->settings()));
	}
	public function public_room($id = '')
	{
		$id = $this->identifier($id); $error = ''; $room = $this->battle->directory(1, '', $id)['rooms'][0] ?? NULL; if ( ! $room) { show_404(); return; }
		if ($this->input->method(TRUE) === 'POST')
		{
			$result = $this->battle->join($this->current_user['id'], '', $this->input->post('child_id', FALSE) ?? '', $this->input->post('consent', FALSE), $id);
			if ($result['ok']) { redirect('belajar/battle/ruang/'.$id, 'location', 303); return; } $error = $this->error_message($result);
		}
		$this->render_page('battle/public_room', compact('room', 'error') + array('page_title' => $room['title'], 'settings' => $this->battle->settings(), 'children' => $this->battle->children_for($this->current_user['id'])));
	}
	public function queue()
	{
		$status = $this->input->get('status', FALSE) ?? 'pending'; if ( ! in_array($status, array('pending', 'approved', 'rejected', 'hidden'), TRUE)) { show_error('Filter tidak valid.', 400); return; }
		$this->render_page('admin/battle_queue', $this->battle->review_queue($this->get_positive_query_int('halaman'), $status) + array('page_title' => 'Review katalog battle', 'admin_section' => 'battle'));
	}
	public function review($id = '')
	{
		$id = $this->identifier($id); $error = ''; $room = $this->battle->review_room($id); if ( ! $room) { show_404(); return; }
		if ($this->input->method(TRUE) === 'POST')
		{
			$result = $this->battle->review_listing($this->current_user['id'], $id, $this->input->post('revision', FALSE), $this->input->post(NULL, FALSE));
			if ($result['ok']) { $this->session->set_flashdata('success', 'Keputusan katalog tersimpan dan diaudit.'); redirect('kelola/battle/tinjau/'.$id, 'location', 303); return; } $error = $this->error_message($result); $room = $this->battle->review_room($id);
		}
		$questions = json_decode($room['snapshot_json'], TRUE); unset($room['snapshot_json']);
		$this->render_page('admin/battle_review', compact('room', 'questions', 'error') + array('page_title' => 'Tinjau battle komunitas', 'admin_section' => 'battle'));
	}
	public function manage()
	{
		$error = '';
		if ($this->input->method(TRUE) === 'POST')
		{
			$result = $this->battle->moderate($this->current_user['id'], $this->identifier($this->input->post('report_id', FALSE)), $this->input->post('cancel', FALSE), $this->input->post('reason', FALSE));
			if ($result['ok']) { $this->session->set_flashdata('success', 'Keputusan moderasi tersimpan dan diaudit.'); redirect('kelola/battle', 'location', 303); return; } $error = $this->error_message($result);
		}
		$this->render_page('admin/battle', $this->battle->reports($this->get_positive_query_int('halaman')) + array('page_title' => 'Moderasi Battle', 'admin_section' => 'battle', 'error' => $error));
	}
}
