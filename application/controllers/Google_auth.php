<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Google_auth extends MY_Controller
{
	public function __construct()
	{
		// CI redirect() exits before queued Output headers are flushed.
		header('Referrer-Policy: no-referrer'); header('Cache-Control: private, no-store'); header('X-Robots-Tag: noindex, nofollow');
		parent::__construct();
	}
	public function _remap($method)
	{
		if ( ! in_array($method, array('settings','account','start','callback','register'), TRUE)) { show_404(); return; }
		$this->require_http_methods($method === 'callback' ? array('GET') : ($method === 'start' ? array('POST') : array('GET','HEAD','POST')));
		$this->output->set_header('Cache-Control: private, no-store')->set_header('Referrer-Policy: no-referrer')->set_header('X-Robots-Tag: noindex, nofollow');
		$this->load->model(array('Google_auth_model','Mail_model')); $this->db->db_debug = FALSE;
		try { $this->$method(); } catch (Throwable $error) { log_message('error', 'Google sign-in unavailable; no token or credentials recorded.'); if ($method === 'callback') { $this->callback_failure(); return; } show_error('Integrasi Google belum dapat diproses. Gunakan login sandi lokal atau coba lagi.', 503); }
	}
	private function reauth()
	{
		if ($this->input->post('confirm', FALSE) !== '1') { return array('ok' => FALSE, 'code' => 400); }
		return $this->Mail_model->reauthenticate($this->current_user['id'], $this->current_user['auth_stamp'], $this->input->post('current_password', FALSE), $this->input->ip_address());
	}
	private function fail($r)
	{
		show_error('Permintaan belum disimpan. Periksa sandi, konfirmasi, versi, dan hasil uji Google sebelum aktivasi. Pendaftaran baru memerlukan kebijakan privasi dan syarat layanan yang sudah disetujui pada Pengaturan Privasi.', $r['code'] ?? 503); }
	public function settings()
	{
		$this->require_role(array('system_admin'));
		if ($this->input->method(TRUE) === 'POST')
		{
			$r = $this->reauth(); if ($r['ok']) { $r = $this->Google_auth_model->save($this->current_user['id'], $this->current_user['auth_stamp'], $this->input->post(NULL, FALSE)); }
			if ( ! $r['ok']) { $this->fail($r); return; } $this->session->set_flashdata('success', 'Pengaturan Google tersimpan. Kredensial baru wajib diuji sebelum diaktifkan.'); redirect('kelola/pengaturan/google', 'location', 303); return;
		}
		$this->render_page('admin/google_settings', array('page_title' => 'Pengaturan Google Sign-in', 'admin_section' => 'settings', 'record' => $this->Google_auth_model->config_public(), 'policy_ready' => $this->Google_auth_model->registration_policy() !== NULL));
	}
	public function account()
	{
		$this->require_login(array('adult','parent'));
		if ($this->input->method(TRUE) === 'POST')
		{
			$r = $this->reauth(); if ($r['ok']) { $r = $this->Google_auth_model->unlink($this->current_user['id'], $this->current_user['auth_stamp']); }
			if ( ! $r['ok']) { $this->fail($r); return; } $this->session->unset_userdata(array('user_id','auth_stamp','google_browser')); $this->session->sess_regenerate(TRUE); $this->session->set_flashdata('success', 'Google dilepas dan sesi lama dibatalkan. Masuk kembali dengan sandi Project Pintar.'); redirect('masuk', 'location', 303); return;
		}
		$this->render_page('auth/google_account', array('page_title' => 'Hubungkan Google', 'linked' => $this->Google_auth_model->linked($this->current_user['id']), 'record' => $this->Google_auth_model->config_public()));
	}
	public function start()
	{
		$purpose = $this->input->post('purpose', FALSE);
		if ( ! in_array($purpose, array('login','link','probe','register'), TRUE)) { show_error('Tujuan tidak valid.', 400); return; }
		if ( ! in_array($purpose, array('login','register'), TRUE))
		{
			$this->require_login(array('adult','parent')); if ($purpose === 'probe') { $this->require_role(array('system_admin')); }
			$r = $this->reauth(); if ( ! $r['ok']) { $this->fail($r); return; }
		}
		$browser = $this->session->userdata('google_browser'); if ( ! is_string($browser)) { $browser = bin2hex(random_bytes(32)); $this->session->set_userdata('google_browser', $browser); }
		$r = $this->Google_auth_model->begin($this->current_user['id'] ?? NULL, $this->current_user['auth_stamp'] ?? NULL, $purpose, $browser, $this->input->ip_address());
		if ( ! $r['ok']) { $this->fail($r); return; } redirect($r['url'], 'location', 303);
	}
	public function register()
	{
		if ($this->current_user) { redirect(study_account_landing($this->current_user), 'location', 303); return; }
		$key = $this->session->userdata('google_registration'); $browser = $this->session->userdata('google_browser');
		$email = $this->Google_auth_model->registration_email($key, $browser);
		if ($email === NULL) { $this->session->unset_userdata('google_registration'); $this->session->set_flashdata('error', 'Sesi pendaftaran habis atau pengaturan berubah. Mulai kembali dengan Google.'); redirect('daftar', 'location', 303); return; }
		$error = NULL;
		if ($this->input->method(TRUE) === 'POST')
		{
			$r = $this->Google_auth_model->register($key, $browser, $this->input->post(NULL, FALSE));
			if ($r['ok']) { $this->session->unset_userdata(array('google_registration','google_browser')); $this->session->sess_regenerate(TRUE); $this->session->set_userdata(array('user_id' => $r['id'], 'auth_stamp' => $r['stamp'])); $this->session->set_flashdata('success', 'Akun berhasil dibuat dan Google ditautkan. Simpan sandi Project Pintar Anda untuk konfirmasi keamanan dan login cadangan.'); redirect('beranda', 'location', 303); return; }
			$this->output->set_status_header($r['code'] ?? 503);
			$error = ($r['code'] ?? 503) === 400 ? 'Periksa jenis akun, nama, nama pengguna, sandi minimal 12 karakter/maksimal 72 byte, ulangi sandi dan persetujuan.' : 'Akun belum dapat dibuat. Nama pengguna/email mungkin telah terpakai atau sesi berubah. Coba nama pengguna lain; untuk akun lama, masuk dengan sandi lalu tautkan Google.';
		}
		$this->render_page('auth/google_register', array('page_title' => 'Lengkapi pendaftaran Google', 'email' => $email, 'submit_error' => $error, 'policy' => $this->Google_auth_model->registration_policy()));
	}
	private function callback_failure()
	{
		$this->session->set_flashdata('error', 'Login/penautan Google belum berhasil atau dibatalkan. Gunakan sandi lokal dan mulai kembali. Untuk akun baru, pilih Daftar dengan Google memakai Gmail atau Google Workspace. Jika email sudah terdaftar, masuk dengan sandi lokal lalu tautkan Google di Akun saya. Akun tidak digabung otomatis.'); redirect($this->current_user ? 'akun/google' : 'masuk', 'location', 303);
	}
	public function callback()
	{
		$r = $this->Google_auth_model->claim($this->input->get('state', FALSE), $this->session->userdata('google_browser'), $this->current_user['id'] ?? NULL);
		if ( ! $r['ok'] || $this->input->get('error', FALSE) !== NULL) { $this->callback_failure(); return; }
		$token = $this->google_oidc->exchange($r['config'], $this->input->get('code', FALSE), $this->google_oidc->unseal($r['flow']['verifier_cipher']));
		$identity = $this->google_oidc->verify($token, $r['config']['client_id'], $r['flow']['nonce_hash'], $this->google_oidc->keys(), TRUE);
		$result = $this->Google_auth_model->complete($r['flow'], $identity['sub'], $identity); if ( ! $result['ok']) { $this->callback_failure(); return; }
		if ($result['purpose'] === 'register') { $this->session->sess_regenerate(TRUE); $this->session->set_userdata('google_registration', $result['flow']); redirect('auth/google/daftar', 'location', 303); return; }
		if ($result['purpose'] === 'login') { $this->session->sess_regenerate(TRUE); $this->session->set_userdata(array('user_id' => (int) $result['id'], 'auth_stamp' => $result['stamp'])); redirect(study_account_landing($this->Account_model->find_active_user($result['id'])), 'location', 303); return; }
		$this->session->set_flashdata('success', $result['purpose'] === 'probe' ? 'Uji Google berhasil. Anda dapat mengaktifkan konfigurasi yang telah diuji.' : 'Google berhasil ditautkan ke akun ini. Email kontak dan sandi lokal tidak diubah.'); redirect($result['purpose'] === 'probe' ? 'kelola/pengaturan/google' : 'akun/google', 'location', 303);
	}
}
