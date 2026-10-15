<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Account_security extends MY_Controller
{
	public function index()
	{
		$this->output->set_header('Cache-Control: private, no-store');
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		$this->require_login();
		if ( ! in_array($this->input->method(TRUE), array('GET', 'HEAD', 'POST'), TRUE))
		{
			header('Allow: GET, HEAD, POST');
			show_error('Metode tidak tersedia.', 405, 'Metode tidak tersedia');
		}
		$form_errors = array();
		$submit_error = NULL;
		$minimum = study_password_minimum($this->current_user);
		if ($this->input->method(TRUE) === 'POST')
		{
			foreach (array('current_password', 'new_password', 'password_confirm') as $field)
			{
				if ( ! is_string($this->input->post($field, FALSE)))
				{
					$form_errors[$field] = 'Isi kolom ini dengan kata sandi yang valid.';
				}
			}
			if ( ! $form_errors)
			{
				$this->form_validation->set_rules('current_password', 'Kata sandi sekarang', 'required|study_valid_password[1]');
				$this->form_validation->set_rules('new_password', 'Kata sandi baru', 'required|study_valid_password['.$minimum.']');
				$this->form_validation->set_rules('password_confirm', 'Ulangi kata sandi baru', 'required|matches[new_password]');
				if ($this->form_validation->run())
				{
					$result = $this->Account_model->change_password($this->current_user['id'], $this->current_user['auth_stamp'], $this->input->post('current_password', FALSE), $this->input->post('new_password', FALSE), $this->input->ip_address());
					if ($result === 'changed')
					{
						$this->session->unset_userdata(array('user_id', 'auth_stamp', 'directory_searches'));
						$this->session->sess_regenerate(TRUE);
						$this->session->set_flashdata('success', 'Kata sandi berhasil diganti. Sesi lama di semua perangkat tidak berlaku lagi. Silakan masuk dengan kata sandi baru.');
						redirect('masuk', 'location', 303);
						return;
					}
					if ($result === 'incorrect')
					{
						$form_errors['current_password'] = 'Kata sandi sekarang belum benar.';
					}
					elseif ($result === 'unchanged')
					{
						$form_errors['new_password'] = 'Pilih kata sandi baru yang berbeda.';
					}
					else
					{
						$this->output->set_status_header($result === 'throttled' ? 429 : 409);
						$submit_error = $result === 'throttled' ? 'Terlalu banyak percobaan. Tunggu 15 menit sebelum mencoba kembali.' : 'Perubahan belum dapat disimpan. Muat ulang halaman dan masuk kembali bila diminta.';
					}
				}
				else
				{
					$form_errors = $this->form_validation->error_array();
				}
			}
			if ($form_errors)
			{
				$this->output->set_status_header(400);
			}
		}
		$this->render_page('auth/security', compact('form_errors', 'submit_error', 'minimum') + array('page_title' => 'Keamanan akun'));
	}
}
