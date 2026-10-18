<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Account_controls extends MY_Controller
{
	public function _remap($method, $args = array())
	{
		if ( ! in_array($method, array('child','manage'), TRUE)) { show_404(); return; }
		$this->require_http_methods(array('GET','HEAD','POST'));
		if ($method === 'child') { $this->require_login(array('parent')); } else { $this->require_role(array('system_admin')); }
		$this->output->set_header('Cache-Control: private, no-store')->set_header('Referrer-Policy: no-referrer');
		$this->load->model(array('Account_controls_model','Mail_model'));
		if (count($args) !== 1 || ! preg_match('/\A[1-9][0-9]{0,17}\z/', (string) $args[0])) { show_404(); return; }
		try { $this->$method((int) $args[0]); } catch (Throwable $error) { log_message('error', 'Account controls unavailable.'); show_error('Data belum dapat dipastikan. Muat ulang sebelum mengulang perubahan.', 503); }
	}
	private function result($r, $url)
	{
		if ( ! $r['ok']) { show_error('Perubahan belum tersimpan. Periksa izin, sandi konfirmasi, versi formulir dan kapasitas keluarga. Muat ulang sebelum mencoba kembali.', $r['code'] ?? 503); return; }
		$this->session->set_flashdata('success', 'Perubahan tersimpan dan diaudit. Progres, transaksi dan saldo tidak dihapus.'); redirect($url, 'location', 303);
	}
	private function reauth()
	{
		if ($this->input->post('confirm', FALSE) !== '1') { return array('ok' => FALSE, 'code' => 400); }
		return $this->Mail_model->reauthenticate($this->current_user['id'], $this->current_user['auth_stamp'], $this->input->post('current_password', FALSE), $this->input->ip_address());
	}
	public function child($id)
	{
		$record = $this->Account_controls_model->child($this->current_user['id'], $id); if ( ! $record) { show_404(); return; }
		if ($this->input->method(TRUE) === 'POST')
		{
			$r = $this->reauth(); $password = $this->input->post('new_password', FALSE);
			if ($r['ok'] && $password !== $this->input->post('password_confirm', FALSE)) { $r = array('ok' => FALSE, 'code' => 400); }
			if ($r['ok']) { $r = $this->Account_controls_model->reset_child($this->current_user['id'], $this->current_user['auth_stamp'], $id, $this->input->post('version', FALSE), $password); }
			$this->result($r, 'keluarga/anak'); return;
		}
		$this->render_page('family/reset_child', array('page_title' => 'Pulihkan sandi anak', 'record' => $record));
	}
	public function manage($id)
	{
		$record = $this->Account_controls_model->person($id); if ( ! $record) { show_404(); return; }
		if ($this->input->method(TRUE) === 'POST')
		{
			$r = $this->reauth(); $action = $this->input->post('action', FALSE);
			if ($r['ok'] && $action === 'status') { $r = $this->Account_controls_model->status($this->current_user['id'], $this->current_user['auth_stamp'], $id, $this->input->post('version', FALSE), $this->input->post('status', FALSE), $this->input->post('reason', FALSE)); }
			elseif ($r['ok'] && $action === 'course' && is_string($this->input->post('course_id', FALSE)) && preg_match('/\A[1-9][0-9]{0,17}\z/', $this->input->post('course_id', FALSE))) { $r = $this->Account_controls_model->course($this->current_user['id'], $this->current_user['auth_stamp'], $id, (int) $this->input->post('course_id', FALSE), $this->input->post('version', FALSE), $this->input->post('blocked', FALSE), $this->input->post('reason', FALSE)); }
			elseif ($r['ok']) { $r = array('ok' => FALSE, 'code' => 400); }
			$this->result($r, 'kelola/pengguna/'.$id.'/akses'); return;
		}
		$page = $this->input->get('halaman', FALSE) ?? '1'; if ( ! is_string($page) || ! preg_match('/\A[1-9][0-9]{0,3}\z/', $page)) { show_error('Halaman tidak valid.', 400); return; }
		$this->render_page('admin/account_controls', array('page_title' => 'Kelola akun & akses', 'admin_section' => 'users', 'record' => $record, 'courses' => $this->Account_controls_model->courses($id, (int) $page), 'page' => (int) $page));
	}
}
