<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends MY_Controller
{
	public function index()
	{
		$this->require_http_methods(array('GET', 'HEAD'));
		$this->db->from('st_courses');
		$this->db->where('status', 'published');
		$course_count = $this->db->count_all_results();

		$this->render_page('home/index', array(
			'page_title' => 'Belajar Anak TK, SD, SMP dan Latihan Soal',
			'course_count' => $course_count
		));
	}
}
