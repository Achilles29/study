<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
	protected $current_user = NULL;

	public function __construct()
	{
		parent::__construct();
		$this->set_indonesian_validation_messages();
		$this->load->model('Account_model');

		$user_id = (int) $this->session->userdata('user_id');
		if ($user_id > 0)
		{
			$this->current_user = $this->Account_model->find_active_user($user_id);
			$stamp = $this->session->userdata('auth_stamp');
			if ( ! $this->current_user || ! is_string($stamp) || ! hash_equals($this->current_user['auth_stamp'], $stamp))
			{
				$this->current_user = NULL;
				$this->session->unset_userdata(array('user_id', 'auth_stamp', 'directory_searches', 'monitor_searches'));
				$this->session->sess_regenerate(TRUE);
				$this->session->set_flashdata('notice', 'Sesi akun tidak berlaku lagi. Silakan masuk kembali.');
				$this->output->set_header('Cache-Control: private, no-store');
				redirect('masuk', 'location', 303);
				exit;
			}
		}
	}

	private function set_indonesian_validation_messages()
	{
		$messages = array(
			'required' => 'Kolom {field} wajib diisi.',
			'min_length' => 'Kolom {field} harus berisi minimal {param} karakter.',
			'max_length' => 'Kolom {field} maksimal {param} karakter.',
			'matches' => 'Kolom {field} harus sama dengan {param}.',
			'study_valid_password' => 'Gunakan minimal {param} karakter dan maksimal 72 byte untuk kata sandi. Huruf beraksen dan emoji dapat memakai beberapa byte; karakter tidak valid tidak diperbolehkan.',
			'valid_email' => 'Masukkan alamat email yang valid.',
			'is_unique' => 'Nilai {field} sudah digunakan.',
			'alpha_dash' => 'Kolom {field} hanya boleh berisi huruf, angka, garis bawah, atau tanda hubung.',
			'in_list' => 'Pilihan {field} tidak valid.',
			'integer' => 'Kolom {field} harus berupa bilangan bulat.',
			'greater_than' => 'Kolom {field} harus lebih besar dari {param}.',
			'less_than_equal_to' => 'Kolom {field} tidak boleh lebih besar dari {param}.',
			'callback_valid_optional_age' => 'Masukkan angka usia yang valid atau kosongkan.',
			'callback_valid_category' => 'Pilih kategori yang tersedia.',
			'callback_valid_checkbox' => 'Pilihan akses gratis atau pratinjau tidak valid.'
		);
		foreach ($messages as $rule => $message)
		{
			$this->form_validation->set_message($rule, $message);
		}
	}

	protected function require_login($account_kinds = array())
	{
		if ( ! $this->current_user)
		{
			$this->session->set_flashdata('notice', 'Silakan masuk terlebih dahulu untuk melanjutkan.');
			redirect('masuk');
			exit;
		}

		if ($account_kinds && ! in_array($this->current_user['account_kind'], $account_kinds, TRUE))
		{
			show_error('Akses ke halaman ini tidak tersedia untuk akun Anda.', 403, 'Akses dibatasi');
		}
	}

	protected function require_http_methods($allowed)
	{
		if ( ! in_array($this->input->method(TRUE), $allowed, TRUE))
		{
			// show_error exits before CI_Output flushes queued response headers.
			header('Allow: '.implode(', ', $allowed));
			show_error('Metode permintaan ini tidak tersedia. Gunakan tautan atau formulir yang disediakan.', 405, 'Metode tidak tersedia');
		}
	}

	protected function get_scalar_query_param($name, $max_length = 200)
	{
		$value = $this->input->get($name, TRUE);
		if ( ! is_string($value))
		{
			return '';
		}

		$value = trim($value);
		if ( ! mb_check_encoding($value, 'UTF-8'))
		{
			return '';
		}
		return mb_substr($value, 0, max(0, (int) $max_length), 'UTF-8');
	}

	protected function get_positive_query_int($name)
	{
		$value = $this->get_scalar_query_param($name, 10);
		if ($value === '' || ! preg_match('/\\A[0-9]+\\z/', $value))
		{
			return 1;
		}

		return max(1, min((int) $value, 2147483647));
	}

	protected function render_page($view, $data = array())
	{
		$data['navigation_view'] = $view;
		$data['current_user'] = $this->current_user;
		$data['admin_theme'] = (strpos($view, 'admin/') === 0);
		$data['theme_class'] = 'theme-public';
		if ($this->current_user)
		{
			if ($data['admin_theme'] && array_intersect(array('system_admin', 'content_editor', 'finance_operator', 'support_agent'), $this->current_user['roles']))
			{
				$data['theme_class'] = 'theme-admin';
			}
			elseif ($this->current_user['account_kind'] === 'parent')
			{
				$data['theme_class'] = 'theme-parent';
			}
			elseif ($this->current_user['account_kind'] === 'child')
			{
				$stage_themes = array('early_childhood' => 'theme-child-early', 'primary' => 'theme-child-primary', 'secondary' => 'theme-child-secondary');
				$data['theme_class'] = isset($stage_themes[$this->current_user['learning_stage']]) ? $stage_themes[$this->current_user['learning_stage']] : 'theme-child-primary';
			}
			else
			{
				$data['theme_class'] = 'theme-adult';
			}
		}
		if ( ! empty($data['is_content_preview']))
		{
			$preview_themes = array('early_childhood' => 'theme-child-early', 'primary' => 'theme-child-primary', 'secondary' => 'theme-child-secondary', 'adult' => 'theme-adult', 'all_ages' => 'theme-public');
			$data['theme_class'] = isset($preview_themes[$data['preview_theme']]) ? $preview_themes[$data['preview_theme']] : 'theme-public';
		}
		// An uncertain mutation must not be accompanied by an older success notice.
		$data['flash_notice'] = empty($data['suppress_flash']) ? $this->session->flashdata('notice') : NULL;
		$data['flash_success'] = empty($data['suppress_flash']) ? $this->session->flashdata('success') : NULL;
		$data['flash_error'] = empty($data['suppress_flash']) ? $this->session->flashdata('error') : NULL;
		$data['page_title'] = isset($data['page_title']) ? $data['page_title'] : 'Project Pintar';
		$this->load->helper('study_admin');
		$this->load->model('Settings_model');
		$data['site_settings'] = $this->Settings_model->public_values();
		$data['seo'] = study_page_seo($view, $data, $this->current_user);
		if ($data['admin_theme'])
		{
			$this->require_role(array('system_admin', 'content_editor', 'finance_operator', 'support_agent'));
			$data['admin_menu'] = study_admin_menu($this->current_user['roles'], $view, $data['admin_section'] ?? '');
		}
		if ( ! $data['seo']['index'])
		{
			$this->output->set_header('X-Robots-Tag: noindex, nofollow');
			$this->output->set_header('Cache-Control: private, no-store');
		}

		$this->load->view('layouts/header', $data);
		$this->load->view($view, $data);
		$this->load->view('layouts/footer', $data);
		$this->load->model('Traffic_model');
		$this->Traffic_model->record($view, $this->current_user, $data);
		$this->load->model('Operational_traffic_model');
		$this->Operational_traffic_model->record($view, $this->current_user);
	}

	protected function require_role($role_codes)
	{
		$this->require_login();
		$role_codes = (array) $role_codes;
		if ( ! array_intersect($role_codes, $this->current_user['roles']))
		{
			show_error('Akses ke halaman ini tidak tersedia untuk akun Anda.', 403, 'Akses dibatasi');
		}
	}
}
