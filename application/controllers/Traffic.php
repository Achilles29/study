<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Traffic extends MY_Controller
{
	public function live()
	{
		$this->require_role(array('system_admin')); $this->require_http_methods(array('GET','HEAD'));
		$this->output->set_header('Cache-Control: private, no-store')->set_header('X-Robots-Tag: noindex, nofollow')->set_header('X-Content-Type-Options: nosniff')->set_content_type('application/json');
		$this->load->model('Operational_traffic_model');
		try { $data=$this->Operational_traffic_model->live_summary($this->current_user['id']); }
		catch (Throwable $error) { $this->output->set_status_header(503);$data=array('ok'=>FALSE,'message'=>'Data trafik belum dapat diperbarui. Ini bukan berarti tidak ada kunjungan.'); }
		$this->output->set_output(json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
	}
	public function index($tab = 'ringkasan')
	{
		$this->require_role(array('system_admin')); $this->require_http_methods(array('GET','HEAD')); $this->load->model('Traffic_model');
		$this->output->set_header('Cache-Control: private, no-store');
		$this->output->set_header('Referrer-Policy: no-referrer')->set_header('X-Robots-Tag: noindex, nofollow');
		$source = $this->input->get('sumber', FALSE) ?? 'operasional';
		if ( ! in_array($source, array('operasional','opsional'), TRUE)) { show_error('Sumber laporan tidak valid.', 400); return; }
		if ($source === 'operasional') { $this->operational($tab); return; }
		if ( ! in_array($tab, array('ringkasan','halaman','pengguna'), TRUE)) { show_404(); return; }
		try
		{
			$policy = $this->Traffic_model->settings(); $pages = $this->Traffic_model->page_options(); $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta'));
			$filters = array(); foreach (array('from' => $today->modify('-6 days')->format('Y-m-d'), 'to' => $today->format('Y-m-d'), 'audience' => '', 'stage' => '', 'page_key' => '', 'user_id' => '', 'halaman' => '1') as $key => $default)
			{
				$value = $this->input->get($key, FALSE) ?? $default; if (in_array($key,array('from','to'),TRUE) && $value==='') { $value=$default; } if ( ! is_string($value) || strlen($value) > 100) { show_error('Filter tidak valid.', 400); return; } $filters[$key] = $value;
			}
			$from = DateTimeImmutable::createFromFormat('!Y-m-d', $filters['from'], new DateTimeZone('Asia/Jakarta')); $to = DateTimeImmutable::createFromFormat('!Y-m-d', $filters['to'], new DateTimeZone('Asia/Jakarta'));
			if ( ! in_array($filters['stage'], array('','early_childhood','primary','secondary','adult','unset'), TRUE)) { show_error('Jenjang tidak valid.', 400); return; }
			if ( ! $from || ! $to || $from->format('Y-m-d') !== $filters['from'] || $to->format('Y-m-d') !== $filters['to'] || $from > $to || $to > $today || $from < $today->modify('-'.(int) $policy['detail_days'].' days') || ! in_array($filters['audience'], array('','guest','adult','parent','child'), TRUE) || ($filters['page_key'] !== '' && ! isset($pages[$filters['page_key']])) || ($filters['user_id'] !== '' && ! preg_match('/\A[1-9][0-9]{0,8}\z/', $filters['user_id'])) || ! preg_match('/\A[1-9][0-9]{0,3}\z/', $filters['halaman'])) { show_error('Pilih rentang tanggal dalam retensi detail saat ini dan filter yang tersedia.', 400); return; }
			$page = (int) $filters['halaman']; $query = $filters; $query['sumber'] = 'opsional'; unset($query['halaman']);
			$filters['start_utc'] = $from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); $filters['end_utc'] = $to->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
			$report = $this->Traffic_model->report($filters, $tab, $page);
			$report['health'] = $this->Traffic_model->health();
			$this->render_page('admin/traffic', compact('report','filters','query','tab','page','pages','policy') + array('page_title' => 'Trafik & analitik', 'admin_section' => 'traffic'));
		}
		catch (Throwable $error) { show_error('Data trafik belum dapat dibaca. Ini bukan berarti trafik bernilai nol.', 503); }
	}
	private function operational($tab)
	{
		if ( ! in_array($tab, array('ringkasan','halaman','pengguna','log'), TRUE)) { show_404(); return; }
		$this->load->model('Operational_traffic_model');
		$this->load->library('traffic_navigation');
		try
		{
			$policy = $this->Traffic_model->settings(); $pages = $this->Operational_traffic_model->pages(); $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta'));
			$filters = array();
			foreach (array('from' => $today->modify('-6 days')->format('Y-m-d'), 'to' => $today->format('Y-m-d'), 'audience' => '', 'page_key' => '', 'user_id' => '', 'ip_address'=>'', 'device' => '', 'bots' => 'all', 'halaman' => '1') as $key => $default)
			{
				$value = $this->input->get($key, FALSE) ?? $default; if (in_array($key,array('from','to'),TRUE) && $value==='') { $value=$default; } if ( ! is_string($value) || strlen($value) > 100) { show_error('Filter tidak valid.', 400); return; } $filters[$key] = $value;
			}
			$from = DateTimeImmutable::createFromFormat('!Y-m-d', $filters['from'], new DateTimeZone('Asia/Jakarta')); $to = DateTimeImmutable::createFromFormat('!Y-m-d', $filters['to'], new DateTimeZone('Asia/Jakarta'));
			if($filters['ip_address']!==''&&$filters['ip_address']!=='unknown'){
				if(!filter_var($filters['ip_address'],FILTER_VALIDATE_IP)){show_error('Alamat IP filter tidak valid.',400);return;}
				$filters['ip_address']=inet_ntop(inet_pton($filters['ip_address']));
			}
			if ( ! $from || ! $to || $from->format('Y-m-d') !== $filters['from'] || $to->format('Y-m-d') !== $filters['to'] || $from > $to || $to > $today || $from < $today->modify('-7 days') || ! in_array($filters['audience'], array('','guest','adult','parent'), TRUE) || ! in_array($filters['device'], array('','Desktop','Ponsel','Tablet','Bot/alat','Tidak diketahui'), TRUE) || ! in_array($filters['bots'], array('all','exclude','only'), TRUE) || ($filters['page_key'] !== '' && ! isset($pages[$filters['page_key']])) || ($filters['user_id'] !== '' && ! preg_match('/\A[1-9][0-9]{0,8}\z/', $filters['user_id'])) || ! preg_match('/\A[1-9][0-9]{0,3}\z/', $filters['halaman'])) { show_error('Periksa filter dan tanggal dalam 7 hari terakhir.', 400); return; }
			$page = (int) $filters['halaman']; $query = $filters; unset($query['halaman']);
			$filters['start_utc'] = $from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); $filters['end_utc'] = $to->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
			$report = $this->Operational_traffic_model->report($filters, $tab, $page, $this->current_user['id']);
			$this->render_page('admin/traffic_operational', compact('policy','pages','filters','query','tab','page','report') + array('page_title' => 'Monitoring trafik', 'admin_section' => 'traffic'));
		}
		catch (Throwable $error) { show_error('Monitoring operasional belum dapat dibaca. Ini bukan berarti tidak ada kunjungan.', 503); }
	}
}
