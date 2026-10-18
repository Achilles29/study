<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Help extends MY_Controller
{
	public function _remap($method, $args = array())
	{
		if ( ! in_array($method, array('index','recover','ticket','manage','manage_ticket'), TRUE) || count($args) > 1) { show_404(); return; }
		$this->output->set_header('Cache-Control: private, no-store')->set_header('Referrer-Policy: no-referrer')->set_header('X-Robots-Tag: noindex, nofollow');
		if ($this->current_user && $this->current_user['account_kind'] === 'child') { show_error('Mintalah orang tua atau wali menghubungi Bantuan dari akunnya. Jangan kirim data pribadi melalui akun anak.', 403); return; }
		if (strpos($method, 'manage') === 0) { $this->require_role(array('system_admin')); }
		$this->load->model('Help_model');
		try { call_user_func_array(array($this, $method), $args); }
		catch (Throwable $error) { log_message('error', 'Help request unavailable.'); show_error('Bantuan belum dapat dimuat. Jika baru mengirim pesan, periksa percakapan sebelum mengirim ulang.', 503); }
	}
	private function browser()
	{
		$value = $this->session->userdata('help_browser');
		if ( ! is_string($value) || ! preg_match('/\A[a-f0-9]{64}\z/', $value)) { $value = bin2hex(random_bytes(32)); $this->session->set_userdata('help_browser', $value); }
		return $value;
	}
	private function request_hash($scope)
	{
		$key = $this->input->post('request_key', FALSE);
		if ( ! is_string($key) || ! preg_match('/\A[a-f0-9]{64}\z/', $key)) { return NULL; }
		return $this->Help_model->digest('request|'.$scope.'|'.$this->browser().'|'.$key);
	}
	private function grant($id, $hash = NULL)
	{
		$grants = $this->session->userdata('help_grants');
		if ( ! is_array($grants)) { $grants = array(); }
		if ($hash !== NULL)
		{
			unset($grants[$id]); $grants[$id] = $hash;
			$grants = array_slice($grants, -10, NULL, TRUE);
			$this->session->set_userdata('help_grants', $grants);
		}
		return $grants[$id] ?? NULL;
	}
	private function data($admin = FALSE)
	{
		return array('page_title' => $admin ? 'Kotak Bantuan' : 'Bantuan Project Pintar', 'admin_section' => 'help', 'help_admin' => $admin, 'categories' => $this->Help_model->categories(), 'statuses' => $this->Help_model->statuses(), 'request_key' => bin2hex(random_bytes(32)), 'error' => '', 'errors' => array(), 'values' => array());
	}
	private function failure($result)
	{
		$code = $result['code'] ?? 503;
		$this->output->set_status_header($code);
		if ($code === 400) { return 'Periksa kategori, judul (5–120 karakter), isi pesan (20–3.000 karakter untuk pesan baru; 5–3.000 untuk balasan), dan persetujuan. Jangan gunakan karakter kontrol.'; }
		if ($code === 409) { return 'Ada pesan baru, status berubah, atau batas kiriman tercapai. Salin isian Anda, lalu muat ulang percakapan. Jika terlalu sering mengirim, coba lagi setelah satu jam.'; }
		return 'Penyimpanan belum dapat dipastikan. Periksa percakapan sebelum mengirim ulang.';
	}
	public function index()
	{
		$this->require_http_methods(array('GET','HEAD','POST'));
		$data = $this->data(); $this->browser();
		if ($this->input->method(TRUE) === 'POST')
		{
			$input = $this->input->post(NULL, FALSE); $hash = $this->request_hash('new');
			$secret = $hash ? $this->Help_model->digest('secret|'.$hash) : '';
			$result = $hash ? $this->Help_model->create($this->current_user, $input, $hash, $secret, $this->input->ip_address()) : array('ok' => FALSE, 'code' => 400);
			if ($result['ok'])
			{
				if ( ! $this->current_user) { $this->grant($result['id'], hash('sha256', $secret)); $this->session->set_flashdata('help_code', $result['id'].'.'.$secret); }
				$this->session->set_flashdata('success', 'Pesan diterima. Periksa balasan melalui halaman ini; tidak ada pemberitahuan email otomatis.');
				redirect('bantuan/tiket/'.$result['id'], 'location', 303); return;
			}
			$data['error'] = $this->failure($result);
			$data['errors'] = $this->Help_model->validation_errors($input);
			foreach (array('subject','body','category') as $key) { $data['values'][$key] = is_string($input[$key] ?? NULL) ? mb_substr($input[$key], 0, 3000) : ''; }
			if ($hash) { $data['request_key'] = $input['request_key']; }
		}
		$data += $this->current_user ? $this->Help_model->tickets($this->current_user['id'], '', $this->get_positive_query_int('halaman')) : array('tickets' => array(), 'page' => 1, 'pages' => 1);
		$this->render_page('help/index', $data);
	}
	public function recover()
	{
		$this->require_http_methods(array('POST'));
		$result = $this->Help_model->recover($this->input->post('code', FALSE), $this->input->ip_address());
		if ( ! $result['ok']) { show_error('Kode belum dapat diperiksa. Coba lagi nanti.', 503); return; }
		if ( ! $result['found']) { $this->session->set_flashdata('notice', 'Kode tidak dapat dibuka. Periksa kode lengkap; jika terlalu sering mencoba, tunggu satu jam. Kode hanya untuk pesan yang dibuat tanpa masuk akun.'); redirect('bantuan', 'location', 303); return; }
		$this->grant($result['id'], $result['grant']); redirect('bantuan/tiket/'.$result['id'], 'location', 303);
	}
	public function ticket($id = '')
	{
		$this->conversation($id, FALSE);
	}
	public function manage_ticket($id = '')
	{
		$this->conversation($id, TRUE);
	}
	private function conversation($id, $admin)
	{
		$this->require_http_methods(array('GET','HEAD','POST'));
		if ( ! is_string($id) || ! preg_match('/\A[1-9][0-9]{0,17}\z/', $id)) { show_404(); return; }
		$data = $this->data($admin); $path = ($admin ? 'kelola/bantuan/' : 'bantuan/tiket/').$id;
		if ($this->input->method(TRUE) === 'POST')
		{
			$input = $this->input->post(NULL, FALSE); $hash = $this->request_hash('reply|'.$id.'|'.(int) $admin);
			$result = $hash ? $this->Help_model->reply($id, $this->current_user, $this->grant($id), $admin, $input, $hash, $this->input->ip_address()) : array('ok' => FALSE, 'code' => 400);
			if ($result['ok']) { $this->session->set_flashdata('success', 'Balasan tersimpan.'); redirect($path, 'location', 303); return; }
			if (in_array($result['code'] ?? 503, array(403,404), TRUE)) { show_error('Percakapan tidak tersedia untuk Anda.', $result['code']); return; }
			$data['error'] = $this->failure($result);
			$data['values']['body'] = is_string($input['body'] ?? NULL) ? mb_substr($input['body'], 0, 3000) : '';
			$data['errors'] = $this->Help_model->validation_errors($input, TRUE);
			$data['blocked'] = ($result['code'] ?? 503) !== 400;
			$data['submitted_revision'] = is_string($input['revision'] ?? NULL) && preg_match('/\A[1-9][0-9]{0,9}\z/', $input['revision']) ? $input['revision'] : '0';
			if ($hash) { $data['request_key'] = $input['request_key']; }
		}
		$result = $this->Help_model->thread($id, $this->current_user, $this->grant($id), $admin, $this->get_positive_query_int('halaman'));
		if ( ! $result['ok']) { show_error('Percakapan tidak tersedia untuk Anda.', $result['code'] ?? 503); return; }
		$data += $result; $data['path'] = $path; $data['help_code'] = $admin ? NULL : $this->session->flashdata('help_code');
		$this->render_page($admin ? 'admin/help_ticket' : 'help/ticket', $data);
	}
	public function manage()
	{
		$this->require_http_methods(array('GET','HEAD'));
		$data = $this->data(TRUE); $status = $this->get_scalar_query_param('status', 20);
		$data['status'] = isset($data['statuses'][$status]) ? $status : '';
		$data += $this->Help_model->tickets(NULL, $data['status'], $this->get_positive_query_int('halaman'));
		$this->render_page('admin/help', $data);
	}
}
