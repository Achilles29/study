<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migrate extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();

		if (PHP_SAPI !== 'cli')
		{
			show_404();
			exit;
		}

		$this->load->database();
		$this->load->library('migration');
	}

	public function index()
	{
		if ($this->migration->latest() === FALSE)
		{
			fwrite(STDERR, "Migration gagal: ".$this->migration->error_string()."\n");
			exit(1);
		}

		echo "Migration berhasil.\n";
	}
}
