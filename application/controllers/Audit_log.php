<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Audit_log extends MY_Controller
{
	private function day_bound($value, $exclusive_end = FALSE)
	{
		if ( ! preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value))
		{
			return NULL;
		}
		$date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Jakarta'));
		if ( ! $date || $date->format('Y-m-d') !== $value || (int) $date->format('Y') < 1970 || (int) $date->format('Y') > 2100)
		{
			return NULL;
		}
		return ($exclusive_end ? $date->modify('+1 day') : $date)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
	}

	public function index()
	{
		$this->output->set_header('Cache-Control: private, no-store');
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		$this->require_role(array('system_admin'));
		if ( ! in_array($this->input->method(TRUE), array('GET', 'HEAD'), TRUE))
		{
			header('Allow: GET, HEAD');
			show_error('Jejak audit hanya dapat dibaca.', 405, 'Metode tidak tersedia');
		}
		$this->load->model('Audit_log_model');
		$events = $this->Audit_log_model->event_options();
		$entities = $this->Audit_log_model->entity_options();
		$filters = array();
		$errors = array();
		foreach (array('aksi', 'entitas', 'pelaku', 'id_entitas', 'mulai', 'akhir', 'urutan') as $key)
		{
			$raw = $this->input->get($key, FALSE);
			if (in_array($key, array('pelaku', 'id_entitas', 'mulai', 'akhir'), TRUE) && $raw !== NULL && ( ! is_string($raw) || ! mb_check_encoding($raw, 'UTF-8')))
			{
				$errors[$key] = 'Nilai filter tidak valid. Isi ulang kolom ini.';
			}
			$filters[$key] = $this->get_scalar_query_param($key, 100);
		}
		foreach (array('aksi' => $events, 'entitas' => $entities) as $key => $options)
		{
			if ($filters[$key] !== 'other' && ! isset($options[$filters[$key]]))
			{
				$filters[$key] = '';
			}
		}
		foreach (array('pelaku', 'id_entitas') as $key)
		{
			if ($filters[$key] !== '' && ! preg_match('/\A[1-9][0-9]{0,17}\z/', $filters[$key]))
			{
				$errors[$key] = 'Gunakan ID berupa angka positif, maksimal 18 digit.';
			}
		}
		$filters['urutan'] = $filters['urutan'] === 'oldest' ? 'oldest' : 'newest';
		$bounds = array('mulai' => NULL, 'akhir' => NULL);
		foreach (array('mulai', 'akhir') as $key)
		{
			if ($filters[$key] !== '')
			{
				$bounds[$key] = $this->day_bound($filters[$key], $key === 'akhir');
				if ($bounds[$key] === NULL)
				{
					$errors[$key] = 'Masukkan tanggal kalender yang valid antara tahun 1970 dan 2100.';
				}
			}
		}
		if ($bounds['mulai'] !== NULL && $bounds['akhir'] !== NULL && $bounds['mulai'] >= $bounds['akhir'])
		{
			$errors['akhir'] = 'Tanggal akhir tidak boleh lebih awal dari tanggal mulai.';
		}
		if ($errors)
		{
			$this->output->set_status_header(400);
		}
		$total = $errors ? 0 : $this->Audit_log_model->search($filters, $bounds, 0, TRUE);
		$page_count = max(1, (int) ceil($total / 20));
		$page = min($page_count, $this->get_positive_query_int('halaman'));
		$offset = ($page - 1) * 20;
		$entries = $errors ? array() : $this->Audit_log_model->search($filters, $bounds, $offset);
		$this->render_page('admin/audit', compact('filters', 'errors', 'events', 'entities', 'total', 'page', 'page_count', 'offset', 'entries') + array('page_title' => 'Jejak audit'));
	}
}
