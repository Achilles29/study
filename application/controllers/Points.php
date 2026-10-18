<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Points extends MY_Controller
{
	public function _remap($method, $args = array())
	{
		if ( ! in_array($method, array('index','redeem','child','admin'), TRUE)) { show_404(); return; }
		$this->require_login(); $this->load->model('Points_model'); $this->output->set_header('Cache-Control: private, no-store');
		try { call_user_func_array(array($this, $method), $args); }
		catch (Throwable $error) { log_message('error', 'Points request unavailable.'); show_error('Poin belum dapat dimuat. Saldo tidak diubah; coba lagi nanti.', 503); }
	}
	private function page()
	{
		$p = $this->input->get('halaman', FALSE) ?? '1'; if ( ! is_string($p) || ! preg_match('/\A[1-9][0-9]{0,3}\z/', $p)) { show_error('Halaman tidak valid.', 400); } return (int) $p;
	}
	private function report($learner, $editable, $base, $admin = FALSE)
	{
		$this->require_http_methods(array('GET','HEAD')); $page = $this->page();
		$this->render_page($admin ? 'admin/points/report' : 'points/index', array('page_title' => 'Poin belajar', 'admin_section' => 'points', 'summary' => $this->Points_model->summary($learner['id'], $page), 'learner_name' => $learner['display_name'], 'packages' => $editable ? $this->Points_model->packages() : array(), 'rules' => $this->Points_model->rules(), 'editable' => $editable, 'page' => $page, 'base' => $base));
	}
	public function index()
	{
		$this->require_login(array('child')); $this->report($this->current_user, TRUE, 'belajar/poin');
	}
	public function child($id = '')
	{
		$this->require_login(array('parent')); if ( ! ctype_digit((string) $id)) { show_404(); return; }
		$this->load->model('Family_practice_model'); $child = $this->Family_practice_model->child($this->current_user['id'], $id); if ( ! $child) { show_404(); return; }
		$this->report($child, FALSE, 'keluarga/anak/'.(int) $id.'/poin');
	}
	public function admin($id = '')
	{
		$this->require_role(array('system_admin')); $this->require_http_methods(array('GET','HEAD'));
		if ($id === '') { $page = $this->page(); $this->render_page('admin/points/index', array('page_title' => 'Monitoring poin belajar', 'admin_section' => 'points', 'learners' => $this->Points_model->learners($page), 'page' => $page)); return; }
		if ( ! ctype_digit((string) $id)) { show_404(); return; } $child = $this->Account_model->find_active_user($id); if ( ! $child || $child['account_kind'] !== 'child') { show_404(); return; }
		$this->report($child, FALSE, 'kelola/poin/'.(int) $id, TRUE);
	}
	public function redeem($id = '')
	{
		$this->require_login(array('child')); $this->require_http_methods(array('POST'));
		if ( ! ctype_digit((string) $id) || $this->input->post('confirm') !== '1') { show_error('Pilih paket dan konfirmasi penukaran.', 400); return; }
		$r = $this->Points_model->redeem($this->current_user['id'], $this->current_user['auth_stamp'], (int) $id, $this->input->post('revision', FALSE), $this->input->post('request_key', FALSE));
		if ( ! $r['ok']) { show_error('Penukaran belum berhasil. Periksa saldo, status dan harga paket terbaru sebelum mencoba lagi.', $r['code'] ?? 503); return; }
		$this->session->set_flashdata('success', 'Poin ditukar dengan akses paket untuk akunmu. Kredit berbayar tidak berubah.'); redirect('belajar/poin', 'location', 303);
	}
}
