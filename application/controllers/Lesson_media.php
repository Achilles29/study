<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Lesson_media extends MY_Controller
{
	private function valid_id($value)
	{
		return is_string($value) && preg_match('/\A[1-9][0-9]{0,17}\z/', $value);
	}

	private function private_response()
	{
		$this->output->set_header('Cache-Control: private, no-store');
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
	}

	private function editor_context($course_id, $lesson_id, $draft_only)
	{
		if ( ! $this->valid_id($course_id) || ! $this->valid_id($lesson_id)) { show_404(); return NULL; }
		$this->load->model(array('Content_model', 'Lesson_media_model'));
		$course = $this->Content_model->get_course($course_id);
		$lesson = $this->Content_model->get_course_lesson($course_id, $lesson_id);
		if ( ! $course || ! $lesson) { show_404(); return NULL; }
		$is_owner = (int) $course['author_user_id'] === (int) $this->current_user['id'] || in_array('system_admin', $this->current_user['roles'], TRUE);
		if (($draft_only && ($course['status'] !== 'draft' || ! $is_owner)) || ($course['status'] === 'draft' && ! $is_owner))
		{
			show_error('Gambar draf hanya dapat dikelola oleh pemilik kursus atau administrator.', 403, 'Akses dibatasi');
			return NULL;
		}
		return array('course' => $course, 'lesson' => $lesson);
	}

	public function edit($course_id = '', $lesson_id = '')
	{
		$this->require_role(array('system_admin', 'content_editor'));
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		$this->private_response();
		$context = $this->editor_context($course_id, $lesson_id, TRUE);
		if ( ! $context) { return; }
		$image = $this->Lesson_media_model->get_for_lesson($lesson_id);
		if ($image === FALSE) { show_error('Gambar belum dapat dimuat. Coba muat ulang nanti.', 503, 'Layanan belum tersedia'); return; }
		$this->load->library('Lesson_image_store');
		$upload_available = $this->lesson_image_store->upload_available();
		$fields = array();
		$errors = array();
		$submit_error = NULL;
		$has_conflict = FALSE;
		$action = '';
		if ($this->input->method(TRUE) === 'POST')
		{
			foreach (array('action', 'revision', 'alt_text', 'caption', 'source_note', 'rights_status', 'confirm_remove') as $name)
			{
				$value = $this->input->post($name, FALSE);
				if ($value !== NULL && ! is_string($value)) { show_error('Format isian gambar tidak valid.', 400, 'Permintaan tidak valid'); return; }
				$fields[$name] = $value ?? '';
			}
			$action = $fields['action'];
			if ( ! in_array($action, array('upload', 'metadata', 'remove'), TRUE)) { show_error('Tindakan gambar tidak dikenal.', 400, 'Permintaan tidak valid'); return; }
			if ( ! preg_match('/\A[1-9][0-9]{0,9}\z/', $fields['revision']) || (int) $fields['revision'] > 2147483647)
			{
				$submit_error = 'Versi formulir tidak valid. Muat ulang halaman sebelum menyimpan.';
				$has_conflict = TRUE;
				$this->output->set_status_header(400);
			}
			elseif ((int) $fields['revision'] !== (int) $context['lesson']['content_version'])
			{
				$submit_error = 'Pelajaran sudah berubah sejak formulir dibuka. Salin keterangan yang ingin dipertahankan, lalu muat ulang.';
				$has_conflict = TRUE;
				$this->output->set_status_header(409);
			}
			if ($action === 'remove')
			{
				if ($fields['confirm_remove'] !== 'yes') { $errors['confirm_remove'] = 'Centang persetujuan untuk melepas gambar dari pelajaran.'; }
			}
			else
			{
				foreach (array('alt_text' => array(3, 300, 'Teks alternatif'), 'caption' => array(0, 1000, 'Keterangan'), 'source_note' => array(5, 2000, 'Sumber gambar')) as $name => $rule)
				{
					$fields[$name] = trim($fields[$name]);
					if ( ! mb_check_encoding($fields[$name], 'UTF-8') || mb_strlen($fields[$name], 'UTF-8') < $rule[0] || mb_strlen($fields[$name], 'UTF-8') > $rule[1]) { $errors[$name] = $rule[2].' harus berisi '.$rule[0].'–'.$rule[1].' karakter.'; }
				}
				if ( ! in_array($fields['rights_status'], array('owned', 'licensed', 'public_domain'), TRUE)) { $errors['rights_status'] = 'Pilih status hak penggunaan gambar.'; }
			}
			$new_image = NULL;
			if ( ! $errors && $submit_error === NULL && $action === 'upload')
			{
				$upload = $this->lesson_image_store->from_upload($_FILES['image'] ?? NULL);
				if ($upload['status'] === 'ok') { $new_image = $upload['image']; }
				elseif ($upload['status'] === 'unavailable')
				{
					$submit_error = 'Unggah gambar belum tersedia pada server ini. Berkas lama tidak diubah. Hubungi pengelola.';
					$has_conflict = TRUE;
					$this->output->set_status_header(503);
				}
				else { $errors['image'] = $upload['message']; }
			}
			if ( ! $errors && $submit_error === NULL)
			{
				$result = $this->Lesson_media_model->change($course_id, $lesson_id, $this->current_user['id'], $fields['revision'], $action, $fields, $new_image);
				$status = $result['status'];
				if ($new_image && $status !== 'ok' && $status !== 'uncertain') { $this->cleanup_file($new_image['storage_key']); }
				if ($status === 'denied') { show_error('Izin atau status kursus telah berubah. Muat ulang sebelum melanjutkan.', 403, 'Akses dibatasi'); return; }
				if (in_array($status, array('ok', 'no_change'), TRUE))
				{
					if ($status === 'ok' && ! empty($result['previous_key']) && $result['previous_key'] !== ($new_image['storage_key'] ?? NULL)) { $this->cleanup_file($result['previous_key']); }
					$this->session->set_flashdata('success', $status === 'no_change' ? 'Tidak ada perubahan gambar yang perlu disimpan.' : ($action === 'remove' ? 'Gambar dilepas. Teks materi dan progres tetap tersimpan.' : 'Gambar dan keterangannya tersimpan. Periksa pratinjau sebelum review.'));
					redirect('kelola/kursus/'.$course_id.'/pelajaran/'.$lesson_id.'/gambar', 'location', 303);
					return;
				}
				$messages = array('denied' => 'Izin atau status kursus telah berubah. Muat ulang sebelum melanjutkan.', 'invalid' => 'Gambar, keterangan atau versi yang dikirim tidak valid.', 'conflict' => 'Pelajaran sudah berubah. Salin keterangan yang ingin dipertahankan lalu muat ulang.', 'unavailable' => 'Perubahan belum dapat disimpan. Muat ulang sebelum mencoba lagi.', 'uncertain' => 'Status penyimpanan belum dapat dipastikan. Muat ulang dan periksa gambar sebelum mencoba lagi.');
				$submit_error = $messages[$status] ?? $messages['uncertain'];
				$codes = array('denied' => 403, 'invalid' => 400, 'conflict' => 409, 'unavailable' => 503, 'uncertain' => 503);
				$this->output->set_status_header($codes[$status] ?? 503);
				$has_conflict = $status !== 'invalid';
				if (in_array($status, array('unavailable', 'uncertain'), TRUE)) { $submit_error .= ' Keterangan di bawah berasal dari sebelum upaya simpan.'; }
			}
			if ($errors && $submit_error === NULL) { $this->output->set_status_header(400); }
		}
		$this->render_page('admin/lesson_media', array_merge($context, array('page_title' => 'Gambar pendamping pelajaran', 'image' => $image, 'form_errors' => $errors, 'submit_error' => $submit_error, 'has_conflict' => $has_conflict, 'posted_fields' => $fields, 'active_action' => $action, 'upload_available' => $upload_available)));
	}

	private function cleanup_file($key)
	{
		if ( ! $this->lesson_image_store->remove($key)) { log_message('error', 'Lesson image cleanup requires reconciliation.'); }
	}

	public function show($slug = '')
	{
		$this->require_http_methods(array('GET', 'HEAD'));
		$this->private_response();
		$this->load->model(array('Content_model', 'Lesson_media_model'));
		$lesson = $this->Content_model->get_published_lesson($slug);
		if ( ! $lesson) { show_404(); return; }
		if ( ! $this->Content_model->can_access_lesson($this->current_user ? $this->current_user['id'] : 0, $lesson)) { show_error('Gambar materi ini belum dapat diakses.', 403, 'Akses dibatasi'); return; }
		$this->send_image($lesson['id']);
	}

	public function preview($course_id = '', $lesson_id = '')
	{
		$this->require_role(array('system_admin', 'content_editor'));
		$this->require_http_methods(array('GET', 'HEAD'));
		$this->private_response();
		if ( ! $this->editor_context($course_id, $lesson_id, FALSE)) { return; }
		$this->send_image($lesson_id);
	}

	private function send_image($lesson_id)
	{
		$image = $this->Lesson_media_model->get_for_lesson($lesson_id);
		if ($image === FALSE) { show_error('Gambar belum dapat dimuat.', 503, 'Layanan belum tersedia'); return; }
		if ( ! $image) { show_404(); return; }
		$this->load->library('Lesson_image_store');
		$bytes = $this->lesson_image_store->read($image);
		if ($bytes === FALSE) { show_error('Gambar sementara tidak tersedia. Teks materi tetap dapat dibaca.', 503, 'Gambar belum tersedia'); return; }
		$this->output->enable_profiler(FALSE);
		$this->output->parse_exec_vars = FALSE;
		$this->output->set_content_type($image['mime_type']);
		// CI3 appends its text charset by default; raster responses have no charset.
		$this->output->set_header('Content-Type: '.$image['mime_type']);
		$this->output->set_header('X-Content-Type-Options: nosniff');
		$this->output->set_header('Cross-Origin-Resource-Policy: same-origin');
		$this->output->set_header('Content-Disposition: inline; filename="lesson-image.'.($image['mime_type'] === 'image/png' ? 'png' : 'jpg').'"');
		$this->output->set_header('Content-Length: '.strlen($bytes));
		$this->output->set_output($this->input->method(TRUE) === 'HEAD' ? '' : $bytes);
	}
}
