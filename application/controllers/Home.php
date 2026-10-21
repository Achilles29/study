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
		$review_highlights = array('rows' => array(), 'summary' => array('total' => 0, 'average' => 0));
		try
		{
			$this->load->model('Review_model');
			$review_highlights = $this->Review_model->landing_sample(3);
		}
		catch (Throwable $exception)
		{
			log_message('error', 'Landing review highlights unavailable: '.$exception->getMessage());
		}

		$this->render_page('home/index', array(
			'page_title' => 'Latihan Soal Online SD Kelas 1–6 dan Materi Gratis',
			'course_count' => $course_count,
			'review_highlights' => $review_highlights
		));
	}
}
