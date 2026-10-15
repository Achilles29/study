<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Questions extends MY_Controller
{
	public function index($course_id = 0)
	{
		$this->require_role(array('system_admin', 'content_editor'));
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		$this->load->model('Content_model');
		$this->load->model('Question_model');
		$course = $this->Content_model->get_course($course_id);
		if ( ! $course)
		{
			show_404();
			return;
		}
		$owner = (int) $course['author_user_id'] === (int) $this->current_user['id'] || in_array('system_admin', $this->current_user['roles'], TRUE);
		if ($course['status'] === 'draft' && ! $owner)
		{
			show_error('Draf soal hanya dapat dibuka oleh penulis atau administrator.', 403, 'Akses dibatasi');
			return;
		}
		$can_edit = $course['status'] === 'draft' && $owner;
		$errors = array();
		$submit_error = NULL;
		$selected_id = (int) $this->get_scalar_query_param('edit', 19);
		if ($this->input->method(TRUE) === 'POST')
		{
			if ( ! $can_edit)
			{
				show_error('Soal hanya dapat diubah saat kursus berupa draf.', 403, 'Akses dibatasi');
				return;
			}
			foreach (array('action', 'question_id', 'prompt', 'explanation', 'source_note', 'rights_status', 'correct', 'option_0', 'option_1', 'option_2', 'option_3', 'confirm_delete') as $field)
			{
				$value = $this->input->post($field, FALSE);
				if ($value !== NULL && ! is_string($value))
				{
					show_error('Format isian soal tidak valid.', 400, 'Permintaan tidak valid');
					return;
				}
			}
			$posted_id = $this->input->post('question_id');
			if ( ! is_string($posted_id) || ! preg_match('/\A(?:0|[1-9][0-9]{0,18})\z/', $posted_id))
			{
				show_error('Identitas soal tidak valid.', 400, 'Permintaan tidak valid');
				return;
			}
			$selected_id = (int) $posted_id;
			$action = $this->input->post('action');
			$ok = FALSE;
			if ($action === 'delete' && $this->input->post('confirm_delete') === '1' && $selected_id > 0)
			{
				$ok = $this->Question_model->delete_draft($course_id, $this->current_user['id'], $selected_id);
			}
			elseif ($action === 'save')
			{
				$this->form_validation->set_rules('prompt', 'Pertanyaan', 'required|trim|max_length[3000]');
				$this->form_validation->set_rules('explanation', 'Pembahasan', 'required|trim|max_length[3000]');
				$this->form_validation->set_rules('source_note', 'Sumber', 'required|trim|max_length[1000]');
				$this->form_validation->set_rules('rights_status', 'Hak penggunaan', 'required|in_list[owned,licensed,public_domain]');
				$this->form_validation->set_rules('correct', 'Jawaban benar', 'required|in_list[0,1,2,3]');
				foreach (range(0, 3) as $index)
				{
					$this->form_validation->set_rules('option_'.$index, 'Pilihan '.chr(65 + $index), 'required|trim|max_length[1000]');
				}
				if ($this->form_validation->run())
				{
					$data = array('prompt' => $this->input->post('prompt'), 'explanation' => $this->input->post('explanation'), 'source_note' => $this->input->post('source_note'), 'rights_status' => $this->input->post('rights_status'), 'correct' => (int) $this->input->post('correct'), 'options' => array());
					foreach (range(0, 3) as $index)
					{
						$data['options'][] = $this->input->post('option_'.$index);
					}
					$ok = $this->Question_model->save_draft($course_id, $this->current_user['id'], $selected_id, $data);
				}
				else
				{
					$errors = $this->form_validation->error_array();
				}
			}
			else
			{
				show_error('Aksi tidak valid atau konfirmasi hapus belum dipilih.', 400, 'Permintaan tidak valid');
				return;
			}
			if ($ok)
			{
				$this->session->set_flashdata('success', $action === 'delete' ? 'Soal dihapus dari draf.' : 'Soal tersimpan. Ajukan review kursus untuk menerbitkannya.');
				redirect('kelola/kursus/'.(int) $course_id.'/soal');
				return;
			}
			$submit_error = 'Soal belum tersimpan. Periksa isian, pastikan empat pilihan berbeda, dan batas 50 soal belum tercapai.';
		}
		$questions = $this->Question_model->list_for_course($course_id);
		$selected = NULL;
		foreach ($questions as $question)
		{
			if ((int) $question['id'] === $selected_id)
			{
				$selected = $question;
			}
		}
		if ($selected_id > 0 && ! $selected)
		{
			show_404();
			return;
		}
		$page_count = max(1, (int) ceil(count($questions) / 10));
		$page = min($page_count, $this->get_positive_query_int('halaman'));
		$this->output->set_header('Cache-Control: private, no-store');
		$this->render_page('admin/questions', array('page_title' => 'Soal latihan', 'course' => $course, 'questions' => array_slice($questions, ($page - 1) * 10, 10), 'total_questions' => count($questions), 'selected_question' => $selected, 'can_edit' => $can_edit, 'form_errors' => $errors, 'submit_error' => $submit_error, 'page' => $page, 'page_count' => $page_count));
	}
}
