<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Course_structure extends MY_Controller
{
	public function index($course_id = '')
	{
		$this->require_role(array('system_admin', 'content_editor'));
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		if ( ! is_string($course_id) || ! preg_match('/\A[1-9][0-9]{0,17}\z/', $course_id)) { show_404(); return; }
		$this->load->model(array('Content_model', 'Course_structure_model'));
		$course = $this->Content_model->get_course($course_id);
		if ( ! $course) { show_404(); return; }
		$is_owner = (int) $course['author_user_id'] === (int) $this->current_user['id'] || in_array('system_admin', $this->current_user['roles'], TRUE);
		if ($course['status'] !== 'draft' || ! $is_owner) { show_error('Susunan hanya dapat diubah pada draf milik Anda.', 403, 'Akses dibatasi'); return; }
		$this->output->set_header('Cache-Control: private, no-store');
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		$errors = array();
		$submit_error = NULL;
		$has_conflict = FALSE;
		$has_invalid_section = FALSE;
		$action = '';
		$fields = array();
		$context = $this->structure_context($course_id, $this->input->get('bagian', FALSE));
		$lessons = $this->Course_structure_model->lessons($course_id, ($context['page'] - 1) * 10);
		if ($this->input->method(TRUE) === 'POST')
		{
			foreach (array('action', 'revision', 'section_id', 'lesson_id', 'title', 'sort_order') as $field)
			{
				$value = $this->input->post($field, FALSE);
				if ($value !== NULL && ! is_string($value)) { show_error('Format isian susunan tidak valid.', 400, 'Permintaan tidak valid'); return; }
				$fields[$field] = $value;
			}
			$action = $fields['action'];
			if ( ! in_array($action, array('create_section', 'update_section', 'move_lesson'), TRUE)) { show_error('Tindakan susunan tidak dikenal.', 400, 'Permintaan tidak valid'); return; }
			if ($action === 'update_section')
			{
				$has_invalid_section = TRUE;
				foreach ($context['sections'] as $section)
				{
					if ((string) $section['id'] === $fields['section_id'])
					{
						$context['selected_section'] = $section;
						$has_invalid_section = FALSE;
						break;
					}
				}
			}
			$this->form_validation->set_rules('sort_order', 'Nomor urut', 'required|integer|greater_than[0]|less_than_equal_to[9999]');
			if ($action !== 'move_lesson') { $this->form_validation->set_rules('title', 'Judul bagian', 'required|trim|min_length[3]|max_length[180]'); }
			if ($this->form_validation->run())
			{
				$result = $this->Course_structure_model->change($course_id, $this->current_user['id'], $fields['revision'], $action, $fields);
				if (in_array($result, array('ok', 'no_change'), TRUE))
				{
					$this->session->set_flashdata('success', $result === 'ok' ? 'Susunan kursus tersimpan. Periksa urutan sebelum mengajukan review.' : 'Tidak ada perubahan susunan yang perlu disimpan.');
					// These actions do not remove sections or change the lesson count.
					// Avoid reads after commit; the destination GET refreshes and clamps the context.
					redirect($this->structure_path($course_id, $context), 'location', 303);
					return;
				}
				if ($result === 'denied') { show_error('Draf atau izin Anda telah berubah. Muat ulang kursus.', 403, 'Akses dibatasi'); return; }
				$messages = array('conflict' => 'Draf sudah berubah sejak formulir dibuka. Muat ulang halaman sebelum mencoba lagi.', 'limit' => 'Editor mendukung maksimal 100 bagian per kursus. Gunakan bagian yang sudah ada.', 'invalid' => 'Bagian, pelajaran, versi atau nomor urut tidak valid untuk kursus ini.', 'unavailable' => 'Susunan belum dapat disimpan. Tidak ada perubahan yang diterapkan.', 'uncertain' => 'Status penyimpanan belum dapat dipastikan. Muat ulang susunan dan periksa hasil sebelum mencoba lagi.');
				$submit_error = $messages[$result] ?? $messages['unavailable'];
				if (in_array($result, array('unavailable', 'uncertain'), TRUE)) { $submit_error .= ' Daftar di bawah menampilkan keadaan sebelum upaya penyimpanan. Muat ulang susunan sebelum mencoba lagi.'; }
				$has_conflict = in_array($result, array('conflict', 'unavailable', 'uncertain'), TRUE);
				$this->output->set_status_header($result === 'conflict' ? 409 : (in_array($result, array('unavailable', 'uncertain'), TRUE) ? 503 : 400));
			}
			else { $errors = $this->form_validation->error_array(); }
		}
		$this->render_page('admin/structure', array_merge($context, array('page_title' => 'Susun bagian dan pelajaran', 'course' => $course, 'structure_context_path' => $this->structure_path($course_id, $context), 'lessons' => $lessons, 'form_errors' => $errors, 'submit_error' => $submit_error, 'has_conflict' => $has_conflict, 'has_invalid_section' => $has_invalid_section, 'active_action' => $action, 'posted_fields' => $fields)));
	}

	private function structure_context($course_id, $selected_id)
	{
		$sections = $this->Course_structure_model->sections($course_id);
		$selected_section = $sections ? $sections[0] : NULL;
		if ((is_string($selected_id) || is_int($selected_id)) && preg_match('/\A[1-9][0-9]{0,17}\z/', (string) $selected_id))
		{
			foreach ($sections as $section) { if ((string) $section['id'] === (string) $selected_id) { $selected_section = $section; break; } }
		}
		$total = $this->Course_structure_model->lesson_count($course_id);
		$page_count = max(1, (int) ceil($total / 10));
		$page = min($page_count, $this->get_positive_query_int('halaman'));
		return array('sections' => $sections, 'selected_section' => $selected_section, 'page' => $page, 'page_count' => $page_count, 'total_lessons' => $total);
	}

	private function structure_path($course_id, array $context)
	{
		$query = array('halaman' => $context['page']);
		if ($context['selected_section']) { $query['bagian'] = $context['selected_section']['id']; }
		return 'kelola/kursus/'.$course_id.'/susunan?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	}
}
