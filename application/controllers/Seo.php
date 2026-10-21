<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Seo extends CI_Controller
{
	private function read_only()
	{
		if ( ! in_array($this->input->method(TRUE), array('GET', 'HEAD'), TRUE)) { header('Allow: GET, HEAD'); show_error('Metode tidak tersedia.', 405); exit; }
	}

	public function robots()
	{
		$this->read_only();
		$this->output->set_content_type('text/plain', 'utf-8')->set_output("User-agent: *\nDisallow: /application/\nDisallow: /system/\nDisallow: /docs/\nDisallow: /scripts/\nDisallow: /kelola\nDisallow: /keluarga/\nDisallow: /akun/\n\nSitemap: ".site_url('sitemap.xml')."\n");
	}

	public function sitemap()
	{
		$this->read_only();
		$result = $this->db->select('slug')->where('status', 'published')->order_by('id')->limit(10000)->get('st_courses');
		if ($result === FALSE) { show_error('Peta situs belum tersedia.', 503); return; }
		$urls = array(site_url('/'), site_url('kursus'));
		foreach ($result->result_array() as $course) { $urls[] = site_url('kursus/'.$course['slug']); }
		$this->load->model('Public_learning_model');
		try {
			$courses=$this->Public_learning_model->courses();
			if($courses) { $urls[]=site_url('latihan-soal'); }
			foreach($courses as $course) {
				$urls[]=site_url('latihan-soal/kelas-'.$course['grade']);
				$urls[]=site_url($course['public_path']);
			}
			foreach($this->Public_learning_model->lessons(array_column($courses,'id')) as $lesson) { $urls[]=site_url('materi/'.$lesson['slug']); }
		} catch(RuntimeException $error) { show_error('Peta situs belum tersedia.',503); return; }
		$urls=array_values(array_unique($urls));
		$xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
		foreach ($urls as $url) { $xml .= '<url><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>'; }
		$this->output->set_content_type('application/xml', 'utf-8')->set_output($xml.'</urlset>');
	}
}
