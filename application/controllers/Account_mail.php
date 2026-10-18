<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Account_mail extends MY_Controller
{
	public function _remap($method, $params = array())
	{
		$this->output->set_header('Cache-Control: private, no-store'); $this->output->set_header('Referrer-Policy: no-referrer'); $this->output->set_header('X-Robots-Tag: noindex, nofollow');
		if ( ! in_array($method, array('settings', 'index', 'verify', 'forgot', 'reset'), TRUE)) { show_404(); return; }
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		if ($method === 'settings') { $this->require_role(array('system_admin')); }
		if ($method === 'verify' && $this->current_user && $this->current_user['account_kind'] === 'child') { show_error('Verifikasi email dikelola dari akun orang tua.', 403); return; }
		if (in_array($method, array('index', 'settings'), TRUE) || ($method === 'verify' && $this->input->method(TRUE) === 'POST')) { $this->require_login(array('adult', 'parent')); }
		$this->load->model('Mail_model', 'mail'); $this->db->db_debug = FALSE;
		try { $this->$method(); }
		catch (Throwable $error) { log_message('error', 'Account mail request failed without exposing secrets or tokens.'); show_error('Layanan email belum dapat diproses. Data akun tetap dipertahankan. Coba kembali nanti.', 503); }
	}
	private function message($result)
	{
		$this->output->set_status_header($result['code']);
		return array(400 => 'Periksa email, kredensial SMTP, token, atau persetujuan formulir. Pergantian penyedia/koneksi harus disimpan nonaktif lalu diuji. Token mungkin kedaluwarsa/sudah dipakai.', 403 => 'Periksa kata sandi akun Project Pintar, izin akun, atau status layanan email.', 409 => 'Data berubah atau batas percobaan tercapai. Muat ulang; kirim ulang paling cepat 60 detik dan maksimal 5 kali per jam.', 503 => 'Pengiriman/penyimpanan belum dapat dipastikan. Periksa kotak masuk dan status, lalu coba kembali setelah jeda.')[$result['code']] ?? 'Permintaan belum dapat diproses.';
	}
	private function reauthenticate()
	{
		return $this->mail->reauthenticate($this->current_user['id'], $this->current_user['auth_stamp'], $this->input->post('current_password', FALSE), $this->input->ip_address());
	}
	public function settings()
	{
		$error = '';
		if ($this->input->method(TRUE) === 'POST')
		{
			$action = $this->input->post('action', FALSE); if ( ! in_array($action, array('save', 'probe'), TRUE)) { show_error('Aksi tidak valid.', 400); return; }
			$result = $this->reauthenticate();
			if ($result['ok']) { $result = $action === 'save' ? $this->mail->save($this->current_user['id'], $this->current_user['auth_stamp'], $this->input->post(NULL, FALSE)) : $this->mail->request_message($this->current_user['id'], $this->current_user['auth_stamp'], 'probe', '', $this->input->ip_address(), $this->input->post('revision', FALSE)); }
			if ($result['ok']) { $this->session->set_flashdata('success', $action === 'save' ? 'Profil email dipilih dan disimpan. Profil lainnya tetap tersimpan; rahasia tidak ditampilkan kembali.' : 'Server SMTP menerima pesan uji ke alamat pengirim. Periksa kotak masuk/spam sebelum mengaktifkan layanan; ini belum memverifikasi akun pengguna.'); redirect('kelola/pengaturan/email', 'location', 303); return; }
			$error = $this->message($result);
		}
		$provider = $this->input->method(TRUE) === 'POST' ? $this->input->post('provider', FALSE) : $this->input->get('provider', FALSE);
		$this->render_page('admin/email_settings', array('page_title' => 'Email & verifikasi', 'admin_section' => 'settings', 'record' => $this->mail->configuration($provider), 'error' => $error));
	}
	public function index()
	{
		$error = '';
		if ($this->input->method(TRUE) === 'POST')
		{
			$result = $this->reauthenticate(); if ($result['ok']) { $result = $this->mail->request_message($this->current_user['id'], $this->current_user['auth_stamp'], 'verify', $this->input->post('email', FALSE), $this->input->ip_address()); }
			if ($result['ok']) { $this->session->set_flashdata('success', 'Permintaan verifikasi dikirim. Buka email, lalu konfirmasi tautan/kode dalam 30 menit. Alamat akun lama tetap berlaku sampai konfirmasi berhasil.'); redirect('akun/email', 'location', 303); return; } $error = $this->message($result);
		}
		$this->render_page('auth/email', array('page_title' => 'Email & verifikasi akun', 'error' => $error, 'mail_enabled' => $this->mail->configuration()['enabled']));
	}
	public function verify()
	{
		$error = '';
		if ($this->input->method(TRUE) === 'POST')
		{
			$result = $this->mail->consume($this->current_user['id'], $this->current_user['auth_stamp'], $this->input->post('token', FALSE), 'verify');
			if ($result['ok']) { $this->session->set_flashdata('success', 'Email berhasil diverifikasi. Akun dewasa/orang tua dapat memakai fitur penyelenggara sesuai aturan battle.'); redirect('akun/email', 'location', 303); return; } $error = $this->message($result);
		}
		$this->render_page('auth/email_token', array('page_title' => 'Konfirmasi email', 'purpose' => 'verify', 'error' => $error));
	}
	public function forgot()
	{
		if ($this->input->method(TRUE) === 'POST')
		{
			// The same response for unknown, unverified, child, throttled and unavailable accounts.
			try { $this->mail->reset_request($this->input->post('email', FALSE), $this->input->ip_address()); } catch (Throwable $error) { log_message('error', 'Password recovery request could not be completed.'); }
			$this->session->set_flashdata('notice', 'Jika alamat terdaftar pada akun dewasa/orang tua terverifikasi dan layanan tersedia, tautan pemulihan akan dikirim. Periksa kotak masuk/spam; hindari mengirim berulang.'); redirect('lupa-sandi', 'location', 303); return;
		}
		$this->render_page('auth/forgot', array('page_title' => 'Lupa kata sandi'));
	}
	public function reset()
	{
		$error = '';
		if ($this->input->method(TRUE) === 'POST')
		{
			$password = $this->input->post('password', FALSE); $confirm = $this->input->post('password_confirm', FALSE);
			$result = is_string($password) && is_string($confirm) && hash_equals($password, $confirm) ? $this->mail->consume(NULL, NULL, $this->input->post('token', FALSE), 'reset', $password) : array('ok' => FALSE, 'code' => 400);
			if ($result['ok']) { $this->session->unset_userdata(array('user_id', 'auth_stamp')); $this->session->sess_regenerate(TRUE); $this->session->set_flashdata('success', 'Kata sandi diperbarui. Semua sesi lama tidak berlaku; silakan masuk kembali.'); redirect('masuk', 'location', 303); return; } $error = $this->message($result);
		}
		$this->render_page('auth/email_token', array('page_title' => 'Pulihkan kata sandi', 'purpose' => 'reset', 'error' => $error));
	}
}
