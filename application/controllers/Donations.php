<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Donations extends MY_Controller
{
	private $environment;
	public function _remap($method, $args = array())
	{
		if ( ! in_array($method, array('index','order','admin'), TRUE)) { show_404(); return; }
		if ($this->current_user && $this->current_user['account_kind'] === 'child') { show_error('Donasi hanya tersedia untuk akun dewasa atau orang tua.', 403); return; }
		if ($method !== 'index' || $this->input->method(TRUE) === 'POST') { $this->require_login(array('adult','parent')); }
		if ($method === 'admin') { $this->require_role(array('system_admin')); }
		$this->output->set_header('Cache-Control: private, no-store')->set_header('Referrer-Policy: no-referrer');
		$this->load->model('Commerce_model');
		$this->environment = $this->input->post('environment', FALSE) ?? $this->input->get('environment', FALSE) ?? 'production';
		if ( ! in_array($this->environment, array('sandbox','production'), TRUE)) { show_error('Lingkungan tidak valid.', 400); return; }
		$can_sandbox = $this->current_user && $this->Commerce_model->sandbox_allowed($this->current_user['id']);
		if ($this->environment === 'sandbox' && ! $can_sandbox) { show_error('Sandbox khusus pengelola.', 403); return; }
		$this->Commerce_model->set_environment($this->environment);
		$this->load->vars(array('environment' => $this->environment, 'can_sandbox' => $can_sandbox));
		try { call_user_func_array(array($this, $method), $args); }
		catch (Throwable $error) { log_message('error', 'Donation request unavailable; details omitted.'); show_error('Donasi belum dapat dimuat. Periksa riwayat sebelum mengulang pembayaran.', 503); }
	}
	private function page()
	{
		$p = $this->input->get('halaman', FALSE) ?? '1'; if ( ! $this->Commerce_model->number($p, 1, 10000)) { show_error('Halaman tidak valid.', 400); } return (int) $p;
	}
	public function index()
	{
		$this->require_http_methods(array('GET','HEAD','POST')); $page = $this->page(); $settings = $this->Commerce_model->donation_settings(); $error = ''; $amount = ''; $request_key = bin2hex(random_bytes(32));
		$message = '';
		if ($this->input->method(TRUE) === 'POST')
		{
			$amount = $this->input->post('amount', FALSE); $request_key = $this->input->post('request_key', FALSE);
			$message = $this->input->post('message', FALSE) ?? '';
			$r = $this->input->post('confirm') === '1' ? $this->Commerce_model->donate($this->current_user['id'], $this->current_user['auth_stamp'], $amount, $this->input->post('revision', FALSE), $request_key, $message) : array('ok' => FALSE, 'code' => 400, 'message' => 'Konfirmasi bahwa donasi bersifat sukarela.');
			if ($r['ok']) { redirect('donasi/pesanan/'.$r['id'].'?environment='.$this->environment, 'location', 303); return; }
			$error = $r['message'] ?? (($r['code'] ?? 503) === 403 ? 'Masuk dengan akun dewasa/orang tua aktif dan email terverifikasi.' : 'Donasi belum dapat dipastikan. Periksa riwayat; jangan langsung mengulangi pembayaran.');
			$this->output->set_status_header($r['code'] ?? 503);
			$amount = is_string($amount) && preg_match('/\A[0-9]{1,9}\z/', $amount) ? $amount : '';
			$message = is_string($message) && mb_check_encoding($message, 'UTF-8') ? mb_substr($message, 0, 1000, 'UTF-8') : '';
			// Keep a valid request key on uncertainty: retry refers to the same intent, never auto-recharge.
			if ( ! is_string($request_key) || ! preg_match('/\A[a-f0-9]{64}\z/', $request_key)) { $request_key = bin2hex(random_bytes(32)); }
		}
		$report = $this->current_user ? $this->Commerce_model->donation_report($this->current_user['id'], $page) : NULL;
		$this->render_page('donations/index', compact('settings','report','page','error','amount','request_key','message') + array('page_title' => 'Donasi sukarela'));
	}
	public function order($id = '0')
	{
		$this->require_http_methods(array('GET','HEAD','POST')); if ( ! $this->Commerce_model->number((string) $id, 1)) { show_404(); return; }
		$admin = in_array('system_admin', $this->current_user['roles'], TRUE); $record = $this->Commerce_model->order((int) $id, $admin ? NULL : $this->current_user['id']);
		if ( ! $record || $record['purpose'] !== 'donation') { show_404(); return; }
		if ($record['environment'] === 'sandbox' && ! $this->Commerce_model->sandbox_allowed($this->current_user['id'])) { show_error('Sandbox khusus pengelola.', 403); return; }
		if ($this->input->method(TRUE) === 'POST')
		{
			$r = $this->Commerce_model->reconcile($this->current_user['id'], $this->current_user['auth_stamp'], (int) $id, $admin);
			if ( ! $r['ok']) { show_error(($r['code'] ?? 503) === 409 ? 'Tunggu 15 detik sebelum memeriksa lagi.' : 'Status belum dapat dipastikan. Periksa pesanan yang sama nanti; jangan bayar ulang.', $r['code'] ?? 503); return; }
			$this->session->set_flashdata('success', 'Status donasi diperiksa langsung ke Midtrans.'); redirect('donasi/pesanan/'.$id.'?environment='.$record['environment'], 'location', 303); return;
		}
		$this->render_page($admin ? 'admin/donation_order' : 'donations/order', array('page_title' => 'Status donasi', 'record' => $record, 'admin_section' => 'donations'));
	}
	public function admin()
	{
		$this->require_http_methods(array('GET','HEAD')); $page = $this->page(); $status = $this->input->get('status', FALSE) ?? '';
		if ( ! in_array($status, array('','created','pending','paid','expired','failed','cancelled','review'), TRUE)) { show_error('Filter tidak valid.', 400); return; }
		$this->render_page('admin/donations', array('page_title' => 'Donasi & rekonsiliasi', 'admin_section' => 'donations', 'settings' => $this->Commerce_model->donation_settings(), 'report' => $this->Commerce_model->donation_report(NULL, $page, $status), 'page' => $page, 'status' => $status));
	}
}
