<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Check_family_quota extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		if (PHP_SAPI !== 'cli' || ENVIRONMENT !== 'development' || ! preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', $this->db->database) || $this->db->hostname !== '/tmp/mysql-study.sock') { show_404(); exit; }
		$this->load->model('Account_model');
	}

	public function worker($parent_id = '', $username = '')
	{
		if ( ! ctype_digit($parent_id) || ! preg_match('/\Aqa_quota_[a-z0-9]+\z/', $username)) { exit(2); }
		$ok = $this->Account_model->create_child($parent_id, $username, 'Anak QA kuota', 'Quota-QA-Only-2026!', 'primary', $this->Account_model->find_active_user($parent_id)['auth_stamp'] ?? NULL);
		echo $ok ? "CREATED\n" : "DENIED\n";
	}
}
