<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Settings extends MY_Controller
{
	public function index($section = 'umum')
	{
		$this->require_role(array('system_admin'));
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		$this->load->model('Settings_model');
		$this->load->library('site_settings');
		$definitions = $this->site_settings->definitions();
		if ( ! isset($definitions[$section])) { show_404(); return; }
		try { $record = $this->Settings_model->get_section($section); }
		catch (RuntimeException $error) { show_error('Pengaturan belum dapat dimuat. Coba kembali.', 503); return; }
		$values = $record['values']; $errors = array(); $submit_error = '';
		if ($this->input->method(TRUE) === 'POST')
		{
			$validated = $this->site_settings->validate($section, $this->input->post(NULL, FALSE));
			$values = $validated['values']; $errors = $validated['errors'];
			$revision = $this->input->post('revision', FALSE);
			if ( ! is_string($revision) || ! preg_match('/\A[1-9][0-9]{0,9}\z/', $revision)) { $submit_error = 'Versi formulir tidak valid. Muat ulang halaman.'; }
			elseif ( ! $errors)
			{
				$result = $this->Settings_model->save_section($section, $values, $revision, $this->current_user['id'], $this->current_user['auth_stamp']);
				if ($result['ok'])
				{
					$this->session->set_flashdata('success', 'Pengaturan tersimpan dan perubahan tercatat dalam audit.');
					redirect('kelola/pengaturan/'.$section, 'location', 303); return;
				}
				$code = $result['code'] ?? 503;
				if ($code === 409) { $submit_error = 'Pengaturan sudah diubah dari halaman lain. Muat ulang dan tinjau nilai terbaru sebelum menyimpan.'; }
				elseif ($code === 403) { show_error('Izin pengelolaan tidak berlaku lagi.', 403); return; }
				else { $submit_error = 'Penyimpanan belum dapat dipastikan. Muat ulang dan periksa nilai sebelum mencoba kembali.'; }
				$this->output->set_status_header($code);
			}
			if ($errors || ($submit_error && ! isset($code))) { $this->output->set_status_header(400); }
			// Keep the submitted revision on conflict, never overwrite a newer edit silently.
			if (is_string($revision) && preg_match('/\A[1-9][0-9]{0,9}\z/', $revision)) { $record['revision'] = $revision; }
		}
		$this->render_page('admin/settings', compact('section', 'definitions', 'record', 'values', 'errors', 'submit_error') + array('page_title' => 'Pusat pengaturan', 'admin_section' => 'settings'));
	}
}
