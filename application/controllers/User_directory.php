<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_directory extends MY_Controller
{
	private function options()
	{
		return array(
			'jenis' => array('adult' => 'Dewasa', 'parent' => 'Orang tua', 'child' => 'Anak'),
			'status' => array('pending' => 'Menunggu aktivasi', 'active' => 'Aktif', 'suspended' => 'Ditangguhkan', 'closed' => 'Ditutup'),
			'peran' => array('system_admin' => 'Admin sistem', 'content_editor' => 'Pengelola konten', 'finance_operator' => 'Keuangan', 'support_agent' => 'Dukungan', 'none' => 'Tanpa peran internal'),
			'urutan' => array('newest' => 'Terbaru', 'oldest' => 'Terlama', 'username' => 'Nama pengguna A–Z')
		);
	}

	public function index()
	{
		$this->output->set_header('Cache-Control: private, no-store');
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		$this->require_role(array('system_admin'));
		$method = $this->input->method(TRUE);
		if ( ! in_array($method, array('GET', 'HEAD', 'POST'), TRUE))
		{
			header('Allow: GET, HEAD, POST');
			show_error('Direktori hanya mendukung membaca dan mencari.', 405, 'Metode tidak tersedia');
		}
		$options = $this->options();
		$filters = array();
		foreach ($options as $key => $values)
		{
			$value = $method === 'POST' ? $this->input->post($key, FALSE) : $this->get_scalar_query_param($key, 30);
			$filters[$key] = is_string($value) && isset($values[$value]) ? $value : ($key === 'urutan' ? 'newest' : '');
		}
		$contexts = $this->session->userdata('directory_searches');
		$contexts = is_array($contexts) ? $contexts : array();
		if ($method === 'POST')
		{
			$query = $this->input->post('q', FALSE);
			if ( ! is_string($query) || ! mb_check_encoding($query, 'UTF-8'))
			{
				show_error('Kata pencarian tidak valid. Kembali ke direktori untuk mencari ulang.', 400, 'Pencarian tidak valid');
			}
			$query = mb_substr(trim($query), 0, 100, 'UTF-8');
			if ($query !== '')
			{
				$context = bin2hex(random_bytes(16));
				$contexts[$context] = array('owner' => (int) $this->current_user['id'], 'query' => $query);
				$this->session->set_userdata('directory_searches', array_slice($contexts, -10, NULL, TRUE));
				$filters['pencarian'] = $context;
			}
			redirect('kelola/pengguna?'.http_build_query($filters, '', '&', PHP_QUERY_RFC3986), 'location', 303);
			return;
		}
		$context = $this->get_scalar_query_param('pencarian', 100);
		$query = '';
		if ($this->input->get('pencarian', FALSE) !== NULL)
		{
			if ( ! preg_match('/\A[a-f0-9]{32}\z/', $context) || ! isset($contexts[$context]) || $contexts[$context]['owner'] !== (int) $this->current_user['id'])
			{
				show_error('Pencarian tidak tersedia pada sesi ini. Buka direktori pengguna dan cari ulang.', 400, 'Pencarian berakhir');
			}
			$query = $contexts[$context]['query'];
		}
		$this->load->model('User_directory_model');
		$total = $this->User_directory_model->search($query, $filters, 0, TRUE);
		$page_count = max(1, (int) ceil($total / 20));
		$page = min($page_count, $this->get_positive_query_int('halaman'));
		$offset = ($page - 1) * 20;
		$users = $this->User_directory_model->search($query, $filters, $offset);
		$this->render_page('admin/users', compact('query', 'context', 'filters', 'options', 'total', 'page', 'page_count', 'offset', 'users') + array('page_title' => 'Direktori pengguna'));
	}
}
