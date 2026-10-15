<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller
{
	public function index()
	{
		$this->require_login();
		$this->require_http_methods(array('GET', 'HEAD'));
		$this->output->set_header('Cache-Control: private, no-store');
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		$data = array('page_title' => 'Beranda belajar');
		$data['children'] = array();
		$data['child_course_progress'] = array();
		$data['child_flashcard_progress'] = array();
		$data['resume_lessons'] = array();
		$this->load->model('Content_model');

		if ($this->current_user['account_kind'] === 'parent')
		{
			$this->load->model('Flashcard_model');
			$data['children'] = $this->Account_model->get_children($this->current_user['id']);
			foreach ($this->Content_model->get_family_course_progress($this->current_user['id']) as $course_progress)
			{
				$learner_id = (int) $course_progress['learner_user_id'];
				if ( ! isset($data['child_course_progress'][$learner_id]))
				{
					$data['child_course_progress'][$learner_id] = array();
				}
				$data['child_course_progress'][$learner_id][] = $course_progress;
			}
			foreach ($this->Flashcard_model->get_family_flashcard_progress($this->current_user['id']) as $flashcard_progress)
			{
				$learner_id = (int) $flashcard_progress['learner_user_id'];
				if ( ! isset($data['child_flashcard_progress'][$learner_id]))
				{
					$data['child_flashcard_progress'][$learner_id] = array();
				}
				$data['child_flashcard_progress'][$learner_id][] = $flashcard_progress;
			}
		}
		else
		{
			$data['resume_lessons'] = $this->Content_model->get_resumable_lessons($this->current_user['id']);
			$data['progress'] = $this->Content_model->get_learner_progress($this->current_user['id']);
			$data['course_progress'] = $this->Content_model->get_learner_course_progress($this->current_user['id']);
		}

		$this->render_page('dashboard/index', $data);
	}
}
