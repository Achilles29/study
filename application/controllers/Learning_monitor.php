<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Learning_monitor extends MY_Controller
{
	public function _remap($method, $params = array())
	{
		$this->output->set_header('Cache-Control: private, no-store');
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		$this->load->helper('study_monitor');
		$this->load->model('Learning_monitor_model', 'monitor');
		if ( ! in_array($method, array('index', 'modules', 'user', 'activity', 'reports', 'report', 'family_report'), TRUE)) { show_404(); return; }
		if ($method !== 'family_report') { $this->require_role(array('system_admin')); }
		$this->require_http_methods($method === 'index' || $method === 'reports' ? array('GET', 'HEAD', 'POST') : array('GET', 'HEAD'));
		try { call_user_func_array(array($this, $method), $params); }
		catch (RuntimeException $error) { log_message('error', 'Learning monitoring could not be loaded.'); show_error('Laporan belum dapat dimuat. Silakan coba kembali.', 503, 'Laporan belum tersedia'); }
	}

	private function identifier($value)
	{
		if ( ! is_string($value) || ! preg_match('/\A[1-9][0-9]{0,17}\z/', $value)) { show_404(); exit; }
		return $value;
	}

	private function filters()
	{
		$today = new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta'));
		$filters = array('mulai' => $today->modify('-29 days')->format('Y-m-d'), 'sampai' => $today->format('Y-m-d'), 'modul' => '', 'status' => '', 'kursus' => '', 'paket' => '', 'jenjang' => '', 'aktivitas' => '', 'urutan' => 'terbaru', 'pencarian' => '', 'konten' => '');
		foreach ($filters as $key => $default)
		{
			$value = $this->input->method(TRUE) === 'POST' ? $this->input->post($key, FALSE) : $this->input->get($key, FALSE);
			if ($value === NULL) { continue; }
			if ( ! is_string($value) || strlen($value) > 100) { show_error('Filter tidak valid. Buka kembali laporan dan pilih filter.', 400, 'Filter tidak valid'); exit; }
			$filters[$key] = $value;
		}
		$start = DateTimeImmutable::createFromFormat('!Y-m-d', $filters['mulai'], new DateTimeZone('Asia/Jakarta'));
		$end = DateTimeImmutable::createFromFormat('!Y-m-d', $filters['sampai'], new DateTimeZone('Asia/Jakarta'));
		if ( ! $start || ! $end || $start->format('Y-m-d') !== $filters['mulai'] || $end->format('Y-m-d') !== $filters['sampai'] || $end < $start || $start->diff($end)->days > 366)
		{
			show_error('Pilih tanggal yang valid, mulai sebelum akhir, dengan rentang maksimal 367 hari.', 400, 'Periode tidak valid'); exit;
		}
		$allow = array('modul' => array_keys(study_monitor_modules()), 'status' => array('running', 'completed', 'abandoned'), 'jenjang' => array('early_childhood', 'primary', 'secondary', 'adult', 'unset'), 'aktivitas' => array('ada', 'belum'), 'urutan' => array('terbaru', 'nama'));
		foreach ($allow as $key => $values)
		{
			if ($filters[$key] !== '' && ! in_array($filters[$key], $values, TRUE)) { show_error('Pilihan filter tidak dikenal.', 400, 'Filter tidak valid'); exit; }
		}
		foreach (array('kursus', 'paket', 'konten') as $key) { if ($filters[$key] !== '' && ! preg_match('/\A[1-9][0-9]{0,17}\z/', $filters[$key])) { show_error('ID filter tidak valid.', 400, 'Filter tidak valid'); exit; } }
		if ($filters['konten'] !== '' && $filters['modul'] === '') { show_error('Pilih modul untuk membatasi konten.', 400, 'Filter tidak valid'); exit; }
		$filters['from_utc'] = $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
		$filters['until_utc'] = $end->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
		return $filters;
	}

	private function search(&$filters, $base)
	{
		$contexts = $this->session->userdata('monitor_searches'); $contexts = is_array($contexts) ? $contexts : array();
		if ($this->input->method(TRUE) === 'POST')
		{
			$query = $this->input->post('q', FALSE);
			if ( ! is_string($query) || ! mb_check_encoding($query, 'UTF-8') || mb_strlen($query, 'UTF-8') > 100) { show_error('Pencarian maksimal 100 karakter.', 400, 'Pencarian tidak valid'); exit; }
			$filters['pencarian'] = '';
			if (trim($query) !== '')
			{
				$token = bin2hex(random_bytes(16));
				$contexts[$token] = array('owner' => (int) $this->current_user['id'], 'query' => trim($query));
				$this->session->set_userdata('monitor_searches', array_slice($contexts, -10, NULL, TRUE)); $filters['pencarian'] = $token;
			}
			redirect(study_monitor_url($base, $filters), 'location', 303); exit;
		}
		$token = $filters['pencarian'];
		if ($token === '') { return ''; }
		if ( ! preg_match('/\A[a-f0-9]{32}\z/', $token) || ! isset($contexts[$token]) || $contexts[$token]['owner'] !== (int) $this->current_user['id']) { show_error('Pencarian telah berakhir. Kembali ke daftar dan cari ulang.', 400, 'Pencarian berakhir'); exit; }
		return $contexts[$token]['query'];
	}

	private function list_page($base, $section, $title, $module = '')
	{
		$filters = $this->filters();
		if ($module !== '')
		{
			if ( ! isset(study_monitor_modules()[$module])) { show_404(); return; }
			$filters['modul'] = $module; $filters['aktivitas'] = 'ada';
		}
		$query = $this->search($filters, $base);
		$data = $this->monitor->users($filters, $query, $this->get_positive_query_int('halaman'));
		$this->render_page('admin/monitor/users', $data + $this->monitor->options() + compact('filters', 'query', 'base') + array('page_title' => $title, 'admin_section' => $section));
	}

	public function index()
	{
		$this->list_page('kelola/pembelajaran', 'learning', 'Progres belajar pengguna'); }
	public function reports()
	{
		$this->list_page('kelola/rapor', 'reports', 'Rapor belajar'); }

	public function modules($module = '')
	{
		if ($module !== '') { $this->list_page('kelola/pembelajaran/modul/'.$module, 'modules', study_monitor_modules()[$module] ?? 'Modul', $module); return; }
		$filters = $this->filters(); $summaries = array();
		foreach (study_monitor_modules() as $key => $label)
		{
			if ($filters['modul'] !== '' && $filters['modul'] !== $key) { continue; }
			$selected = $filters; $selected['modul'] = $key; $summaries[$key] = $this->monitor->summary($selected);
		}
		$this->render_page('admin/monitor/modules', $this->monitor->contents($filters, $this->get_positive_query_int('halaman')) + $this->monitor->options() + compact('filters', 'summaries') + array('page_title' => 'Progres per modul', 'admin_section' => 'modules', 'base' => 'kelola/pembelajaran/modul'));
	}

	public function user($id = '')
	{
		$user = $this->monitor->user($this->identifier($id)); if ( ! $user) { show_404(); return; }
		$filters = $this->filters(); $this->search($filters, 'kelola/pembelajaran');
		$tab = $this->input->get('tab', FALSE) ?? 'ringkasan';
		if ( ! is_string($tab) || ! in_array($tab, array_merge(array('ringkasan', 'akses'), array_keys(study_monitor_modules())), TRUE)) { show_error('Tab tidak valid.', 400, 'Tab tidak valid'); return; }
		if (isset(study_monitor_modules()[$tab]))
		{
			if ($filters['modul'] !== $tab) { $filters['konten'] = ''; }
			$filters['modul'] = $tab;
		}
		$selected = $filters;
		$data = $tab === 'akses' ? $this->monitor->entitlements($id, $this->get_positive_query_int('halaman')) : $this->monitor->activities($selected, $id, $this->get_positive_query_int('halaman'));
		$this->render_page('admin/monitor/user', $data + $this->monitor->courses($selected, $id, $this->get_positive_query_int('kursus_halaman')) + $this->monitor->options() + compact('filters', 'user', 'tab') + array('summary' => $this->monitor->summary($selected, $id), 'page_title' => 'Progres '.$user['display_name'], 'admin_section' => 'learning', 'base' => 'kelola/pembelajaran/pengguna/'.$id));
	}

	public function activity($module = '', $id = '')
	{
		if ( ! isset(study_monitor_modules()[$module])) { show_404(); return; }
		$activity = $this->monitor->activity($module, $this->identifier($id)); if ( ! $activity) { show_404(); return; }
		$filters = $this->filters();
		$this->render_page('admin/monitor/activity', compact('activity', 'filters') + array('page_title' => 'Detail aktivitas belajar', 'admin_section' => 'learning'));
	}

	public function report($id = '')
	{
		$user = $this->monitor->user($this->identifier($id)); if ( ! $user) { show_404(); return; }
		$this->report_page($user, FALSE);
	}

	public function family_report($id = '')
	{
		$this->require_login(array('parent'));
		$this->load->model('Family_practice_model');
		$user = $this->Family_practice_model->child($this->current_user['id'], $this->identifier($id));
		if ( ! $user) { show_404(); return; }
		$this->report_page($user, TRUE);
	}

	private function report_page($user, $family)
	{
		$filters = $this->filters();
		$options = $family ? array('courses' => array(), 'packages' => array()) : $this->monitor->options();
		$this->render_page($family ? 'family/report' : 'admin/monitor/report', $options + $this->monitor->courses($filters, $user['id'], $this->get_positive_query_int('kursus_halaman')) + compact('filters', 'user', 'family') + array('summary' => $this->monitor->summary($filters, $user['id']), 'load_report_styles' => TRUE, 'page_title' => 'Rapor '.$user['display_name'], 'admin_section' => 'reports', 'base' => ($family ? 'keluarga/anak/' : 'kelola/rapor/').$user['id'].($family ? '/rapor' : '')));
	}
}
