<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Privacy extends MY_Controller
{
	public function _remap($method, $args = array())
	{
		if ( ! in_array($method, array('index','preferences'), TRUE)) { show_404(); return; }
		$this->load->model('Traffic_model'); $this->output->set_header('Cache-Control: private, no-store');
		try { call_user_func_array(array($this, $method), $args); }
		catch (Throwable $error) { show_error('Informasi privasi belum dapat dimuat. Silakan coba lagi. Analitik opsional tidak dicatat jika persetujuan tidak dapat diperiksa.', 503); }
	}
	public function index()
	{
		$this->require_http_methods(array('GET','HEAD')); $this->render_page('privacy/policy', array('page_title' => 'Privasi & syarat layanan', 'policy' => $this->Traffic_model->settings()));
	}
	public function preferences()
	{
		$this->require_http_methods(array('GET','HEAD','POST')); $s = $this->Traffic_model->settings();
		if ($this->input->method(TRUE) === 'POST')
		{
			$target = $this->input->post('target', FALSE); if ( ! is_string($target) || ! preg_match('/\A(?:0|[1-9][0-9]{0,8})\z/', $target) || $this->input->post('confirm') !== '1') { show_error('Konfirmasi preferensi diperlukan.', 400); return; }
			$r = $this->Traffic_model->save_consent($this->current_user['id'] ?? NULL, $this->current_user['auth_stamp'] ?? NULL, (int) $target, $this->input->post('version', FALSE), $this->input->post('enabled', FALSE));
			if ( ! $r['ok']) { show_error('Preferensi belum tersimpan. Periksa versi kebijakan dan hubungan keluarga, lalu muat ulang.', $r['code'] ?? 503); return; }
			$this->session->set_flashdata('success', 'Preferensi trafik tersimpan. Menolak tidak membatasi fitur belajar.'); redirect('preferensi-privasi', 'location', 303); return;
		}
		$subjects = array();
		if ( ! $this->current_user || $this->current_user['account_kind'] !== 'child')
		{
			$subjects[] = array('id' => $this->current_user['id'] ?? 0, 'name' => 'Preferensi saya', 'enabled' => $this->Traffic_model->consent($this->current_user, $s));
			if ($this->current_user && $this->current_user['account_kind'] === 'parent')
			{
				foreach ($this->Account_model->get_children($this->current_user['id']) as $child) { $child['account_kind'] = 'child'; $subjects[] = array('id' => $child['id'], 'name' => 'Anak: '.$child['display_name'], 'enabled' => $this->Traffic_model->consent($child, $s)); }
			}
		}
		$this->render_page('privacy/preferences', array('page_title' => 'Preferensi privasi', 'policy' => $s, 'subjects' => $subjects));
	}
}
