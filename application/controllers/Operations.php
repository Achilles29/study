<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Operations extends MY_Controller
{
	public function index()
	{
		$this->require_role(array('system_admin')); $this->require_http_methods(array('GET','HEAD')); $this->output->set_header('Cache-Control: private, no-store');
		$this->load->model('Operations_model'); $this->db->db_debug = FALSE;
		try { $this->render_page('admin/operations', array('page_title' => 'Kesehatan operasional', 'admin_section' => 'operations', 'health' => $this->Operations_model->health())); }
		catch (Throwable $error) { log_message('error', 'Operations summary unavailable.'); show_error('Ringkasan belum dapat dibaca. Jangan menganggap angka nol; periksa layanan server.', 503); }
	}
}
