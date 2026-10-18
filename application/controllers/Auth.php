<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends MY_Controller
{
	public function sign_up()
	{
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		if (ENVIRONMENT === 'production' || getenv('STUDY_QA_SSO_ONLY') === '1')
		{
			if ($this->current_user) { redirect(study_account_landing($this->current_user)); return; }
			if ($this->input->method(TRUE) === 'POST') { show_error('Pendaftaran email/sandi belum dibuka. Gunakan Daftar dengan Google.', 403); return; }
			$this->load->model('Google_auth_model');
			$c = $this->Google_auth_model->config_public();
			$this->output->set_header('Cache-Control: private, no-store');
			$this->render_page('auth/google_sign_up', array('page_title' => 'Daftar dengan Google', 'google_enabled' => $c['enabled'] && $c['registration_enabled'] && $this->Google_auth_model->registration_policy())); return;
		}

		if ($this->current_user)
		{
			redirect(study_account_landing($this->current_user));
			return;
		}

		$errors = array();
		$submit_error = NULL;
		if ($this->input->method(TRUE) === 'POST')
		{
			$fields = array('account_kind', 'username', 'email', 'display_name', 'password', 'password_confirm', 'agree_terms');
			foreach ($fields as $field)
			{
				$value = $this->input->post($field, FALSE);
				if ($value !== NULL && ! is_string($value))
				{
					$submit_error = 'Periksa kembali data yang diisi.';
					break;
				}
			}

			if ($submit_error === NULL)
			{
				$this->form_validation->set_rules('account_kind', 'Jenis akun', 'required|in_list[adult,parent]');
				$this->form_validation->set_rules('username', 'Nama pengguna', 'required|trim|min_length[4]|max_length[80]|alpha_dash|is_unique[st_users.username]');
				$this->form_validation->set_rules('email', 'Email', 'required|trim|valid_email|max_length[190]|is_unique[st_users.email]');
				$this->form_validation->set_rules('display_name', 'Nama tampilan', 'required|trim|min_length[2]|max_length[120]');
				$this->form_validation->set_rules('password', 'Kata sandi', 'required|min_length[12]|study_valid_password[12]');
				$this->form_validation->set_rules('password_confirm', 'Ulangi kata sandi', 'required|matches[password]');
				$this->form_validation->set_rules('agree_terms', 'Persetujuan', 'required');

				if ($this->form_validation->run())
				{
					$account = $this->Account_model->register_adult_or_parent(
						$this->input->post('account_kind', TRUE),
						strtolower($this->input->post('username', TRUE)),
						strtolower($this->input->post('email', TRUE)),
						$this->input->post('display_name', TRUE),
						$this->input->post('password', FALSE)
					);

					if ($account)
					{
						$this->session->set_flashdata('success', 'Akun berhasil dibuat. Silakan masuk untuk mulai.');
						redirect('masuk');
						return;
					}

					$submit_error = 'Akun belum dapat dibuat. Periksa apakah nama pengguna atau email sudah digunakan, lalu coba lagi.';
				}
				else
				{
					$errors = $this->form_validation->error_array();
				}
			}
		}

		$this->render_page('auth/sign_up', array(
			'page_title' => 'Buat akun belajar',
			'form_errors' => $errors,
			'submit_error' => $submit_error
		));
	}

	public function sign_in()
	{
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		if ($this->current_user)
		{
			redirect(study_account_landing($this->current_user));
			return;
		}

		$errors = array();
		$submit_error = NULL;
		if ($this->input->method(TRUE) === 'POST')
		{
			$identity = $this->input->post('identity', TRUE);
			$password = $this->input->post('password', FALSE);
			if ( ! is_string($identity) || ! is_string($password))
			{
				$identity = '';
				$password = '';
				$submit_error = 'Nama pengguna/email atau kata sandi belum benar.';
			}
			else
			{
				$this->form_validation->set_rules('identity', 'Nama pengguna atau email', 'required|trim|max_length[190]');
				$this->form_validation->set_rules('password', 'Kata sandi', 'required|study_valid_password[1]');
				if ($this->form_validation->run())
				{
					$result = $this->Account_model->authenticate($identity, $password, $this->input->ip_address());
					if ($result['ok'])
					{
						$this->session->sess_regenerate(TRUE);
						$this->session->set_userdata('user_id', (int) $result['user']['id']);
						$this->session->set_userdata('auth_stamp', $result['user']['auth_stamp']);
						redirect(study_account_landing($this->Account_model->find_active_user($result['user']['id'])));
						return;
					}

					$submit_error = $result['throttled']
						? 'Terlalu banyak percobaan masuk. Tunggu beberapa menit, lalu coba lagi.'
						: 'Nama pengguna/email atau kata sandi belum benar.';
				}
				else
				{
					$errors = $this->form_validation->error_array();
				}
			}
		}

		$google_enabled = FALSE; $debug = $this->db->db_debug; $this->db->db_debug = FALSE;
		try { $q = $this->db->query('SELECT enabled FROM st_google_config WHERE id=1'); $google_enabled = $q !== FALSE && ! empty($q->row_array()['enabled']); } catch (Throwable $ignored) { /* Local password login remains available. */ } finally { $this->db->db_debug = $debug; }
		$this->render_page('auth/sign_in', array(
			'google_enabled' => $google_enabled,
			'page_title' => 'Masuk ke akun',
			'form_errors' => $errors,
			'submit_error' => $submit_error
		));
	}

	public function sign_out()
	{
		if ($this->input->method(TRUE) !== 'POST')
		{
			show_404();
			return;
		}

		if ($this->current_user)
		{
			$this->Account_model->record_logout($this->current_user['id']);
		}
		$this->session->sess_destroy();
		redirect('/');
	}
}
