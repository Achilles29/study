<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Family extends MY_Controller
{
	private function matching_identifier($value)
	{
		if ( ! is_string($value) || ! preg_match('/\A[1-9][0-9]{0,17}\z/', $value))
		{
			show_404();
		}
		return $value;
	}

	public function matching($child_user_id = '')
	{
		$child = $this->report_child($this->matching_identifier($child_user_id));
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		$this->load->model('Family_matching_model');
		$query = $this->get_scalar_query_param('q', 100);
		$status = $this->get_scalar_query_param('status', 20);
		if ( ! in_array($status, array('in_progress', 'completed', 'abandoned'), TRUE))
		{
			$status = '';
		}
		$summary = $this->Family_matching_model->summary($this->current_user['id'], $child['id']);
		$total = $this->Family_matching_model->history($this->current_user['id'], $child['id'], $query, $status, 0, TRUE);
		$page_count = max(1, (int) ceil($total / 10));
		$page = min($page_count, $this->get_positive_query_int('halaman'));
		$runs = $this->Family_matching_model->history($this->current_user['id'], $child['id'], $query, $status, ($page - 1) * 10);
		$this->render_page('family/matching', compact('child', 'summary', 'runs', 'query', 'status', 'total', 'page', 'page_count') + array('page_title' => 'Laporan permainan anak'));
	}

	public function matching_result($child_user_id = '', $run_id = '')
	{
		$child = $this->report_child($this->matching_identifier($child_user_id));
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		$this->load->model('Family_matching_model');
		$result = $this->Family_matching_model->result($this->current_user['id'], $child['id'], $this->matching_identifier($run_id));
		if ( ! $result)
		{
			show_404();
		}
		$this->render_page('family/matching_result', compact('child', 'result') + array('page_title' => 'Catatan permainan anak'));
	}

	private function report_child($child_user_id)
	{
		$this->require_login(array('parent'));
		$this->output->set_header('Cache-Control: private, no-store');
		if ( ! in_array($this->input->method(TRUE), array('GET', 'HEAD'), TRUE))
		{
			header('Allow: GET, HEAD');
			show_error('Laporan hanya dapat dibaca.', 405, 'Metode tidak tersedia');
		}
		$this->load->model('Family_practice_model');
		$child = $this->Family_practice_model->child($this->current_user['id'], $child_user_id);
		if ( ! $child)
		{
			show_404();
		}
		return $child;
	}

	public function practice($child_user_id = 0)
	{
		$child = $this->report_child($child_user_id);
		$summary = $this->Family_practice_model->summary($this->current_user['id'], $child['id']);
		$page_count = max(1, (int) ceil($summary['total'] / 10));
		$page = min($page_count, $this->get_positive_query_int('halaman'));
		$attempts = $this->Family_practice_model->history($this->current_user['id'], $child['id'], 10, ($page - 1) * 10);
		$this->render_page('family/practice', array('page_title' => 'Laporan latihan anak', 'child' => $child, 'summary' => $summary, 'attempts' => $attempts, 'page' => $page, 'page_count' => $page_count));
	}

	public function practice_result($child_user_id = 0, $attempt_id = 0)
	{
		$child = $this->report_child($child_user_id);
		$attempt = $this->Family_practice_model->result($this->current_user['id'], $child['id'], $attempt_id);
		if ( ! $attempt)
		{
			show_404();
			return;
		}
		$this->load->model('Practice_model');
		$questions = $this->Practice_model->display_questions($attempt);
		if (count($questions) !== (int) $attempt['question_count'])
		{
			show_error('Salinan soal belum dapat dimuat. Hubungi pengelola.', 409, 'Hasil tidak tersedia');
			return;
		}
		$this->render_page('practice/attempt', array('page_title' => 'Hasil latihan anak', 'attempt' => $attempt, 'questions' => $questions, 'submit_error' => NULL, 'can_retry' => FALSE, 'report_child' => $child));
	}

	public function children()
	{
		$this->require_login(array('parent'));
		$this->require_http_methods(array('GET', 'HEAD'));
		$this->load->model('Commerce_access_model');
		$this->render_page('family/children', array(
			'page_title' => 'Akun anak',
			'capacity' => 2 + $this->Commerce_access_model->extra_slots($this->current_user['id']),
			'children' => $this->Account_model->get_children($this->current_user['id'])
		));
	}

	public function add_child()
	{
		$this->require_login(array('parent'));
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		if ( ! $this->current_user['email_verified_at']) { $this->session->set_flashdata('notice', 'Verifikasi email orang tua sebelum membuat akun anak. Akun anak existing tetap dipertahankan.'); redirect('akun/email', 'location', 303); return; }
		$errors = array();
		$submit_error = NULL;
		if ($this->input->method(TRUE) === 'POST')
		{
			$fields = array('username', 'display_name', 'learning_stage', 'password', 'password_confirm');
			foreach ($fields as $field)
			{
				$value = $this->input->post($field, FALSE);
				if ($value !== NULL && ! is_string($value))
				{
					$submit_error = 'Periksa kembali data yang diisi.';
					break;
				}
			}

			if ($submit_error === NULL)
			{
				$this->form_validation->set_rules('username', 'Nama pengguna anak', 'required|trim|min_length[4]|max_length[80]|alpha_dash|is_unique[st_users.username]');
				$this->form_validation->set_rules('display_name', 'Nama panggilan', 'required|trim|min_length[2]|max_length[120]');
				$this->form_validation->set_rules('learning_stage', 'Kelompok usia belajar', 'required|in_list[early_childhood,primary,secondary]');
				$this->form_validation->set_rules('password', 'Kata sandi awal', 'required|study_valid_password['.study_child_password_minimum().']');
				$this->form_validation->set_rules('password_confirm', 'Ulangi kata sandi', 'required|matches[password]');

				if ($this->form_validation->run())
				{
					$created = $this->Account_model->create_child(
						$this->current_user['id'],
						strtolower($this->input->post('username', TRUE)),
						$this->input->post('display_name', TRUE),
						$this->input->post('password', FALSE),
						$this->input->post('learning_stage', TRUE),
						$this->current_user['auth_stamp']
					);

					if ($created)
					{
						$this->session->set_flashdata('success', 'Akun anak berhasil dibuat. Bagikan nama pengguna dan kata sandi awal secara aman.');
						redirect('keluarga/anak');
						return;
					}

					$submit_error = 'Akun anak belum dapat dibuat. Periksa kapasitas dua slot dasar + slot tambahan yang sah dan pastikan nama pengguna belum dipakai.';
				}
				else
				{
					$errors = $this->form_validation->error_array();
				}
			}
		}

		$this->render_page('family/add_child', array(
			'page_title' => 'Tambahkan akun anak',
			'form_errors' => $errors,
			'submit_error' => $submit_error
		));
	}

	public function update_child_stage($child_user_id)
	{
		$this->require_login(array('parent'));
		if ($this->input->method(TRUE) !== 'POST')
		{
			show_404();
			return;
		}

		$stage = $this->input->post('learning_stage', FALSE);
		if ( ! is_string($stage) || ! in_array($stage, array('early_childhood', 'primary', 'secondary'), TRUE))
		{
			$this->session->set_flashdata('error', 'Kelompok belajar yang dipilih tidak valid.');
			redirect('keluarga/anak');
			return;
		}

		if ($this->Account_model->update_child_learning_stage($this->current_user['id'], $child_user_id, $stage, $this->current_user['auth_stamp']))
		{
			$this->session->set_flashdata('success', 'Kelompok belajar anak berhasil diperbarui.');
		}
		else
		{
			$this->session->set_flashdata('error', 'Akun anak tidak ditemukan pada daftar keluarga Anda. Tidak ada perubahan yang disimpan.');
		}
		redirect('keluarga/anak');
	}
}
