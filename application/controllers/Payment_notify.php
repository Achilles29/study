<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payment_notify extends CI_Controller
{
	public function index()
	{
		$this->output->set_header('Cache-Control: no-store')->set_content_type('application/json');
		if ($this->input->method(TRUE) !== 'POST') { $this->output->set_header('Allow: POST')->set_status_header(405)->set_output('{"ok":false}'); return; }
		if (strlen($this->input->raw_input_stream) > 65536) { $this->output->set_status_header(413)->set_output('{"ok":false}'); return; }
		$this->load->model('Commerce_model');
		try { $r = $this->Commerce_model->notification(json_decode($this->input->raw_input_stream, TRUE)); }
		catch (Throwable $error) { log_message('error', 'Payment notification unavailable.'); $r = array('ok' => FALSE, 'code' => 503); }
		$this->output->set_status_header($r['ok'] ? 200 : ($r['code'] ?? 503))->set_output(json_encode(array('ok' => $r['ok'])));
	}
}
