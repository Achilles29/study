<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Account_settings extends MY_Controller
{
	public function index()
	{
		$this->require_login();
		$this->require_http_methods(array('GET', 'HEAD'));
		$this->output->set_header('Cache-Control: private, no-store');
		$this->load->model('Google_auth_model');
		$this->render_page('auth/account_settings', array('page_title' => 'Pengaturan akun', 'google_linked' => $this->current_user['account_kind'] !== 'child' && $this->Google_auth_model->linked($this->current_user['id'])));
	}
}
