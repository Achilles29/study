<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Check_lesson_revisions extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		if (PHP_SAPI !== 'cli' || ENVIRONMENT !== 'development') { show_404(); exit(1); }
		$this->load->model('Content_model');
	}

	public function index($course_id = '', $operation = '')
	{
		if ($this->db->database !== 'db_study' || ! ctype_digit((string) $course_id) || ! in_array($operation, array('create', 'update'), TRUE)) { throw new RuntimeException('QA target mismatch.'); }
		$course = $this->db->where('id', (int) $course_id)->get('st_courses')->row_array();
		if ( ! $course || $course['status'] !== 'draft' || ! preg_match('/\Aqa-lesson-revision-([a-f0-9]{12})\z/', $course['slug'], $matches)) { throw new RuntimeException('Only an exact synthetic draft course is permitted.'); }
		$owner = $this->db->where('id', (int) $course['author_user_id'])->get('st_users')->row_array();
		if ( ! $owner || $owner['username'] !== 'qa_lesson_revision_'.$matches[1].'_owner' || $owner['status'] !== 'active') { throw new RuntimeException('Synthetic owner mismatch.'); }
		$lessons = $this->Content_model->get_course_lessons($course_id);
		if ( ! $lessons) { throw new RuntimeException('Synthetic lesson missing.'); }
		$lesson = $lessons[0];
		$data = array('title' => 'Synthetic rollback lesson', 'body_text' => 'Synthetic rollback body which must not be persisted.', 'estimated_minutes' => 5, 'rights_status' => 'owned', 'source_note' => 'Synthetic QA source', 'is_preview' => '');
		$before_audit = $this->db->where('actor_user_id', (int) $owner['id'])->count_all_results('st_audit_events');
		foreach (array(NULL, array(1), '0', '-1', '01', '1e0', '2147483648') as $invalid)
		{
			if ($this->Content_model->update_lesson_draft($course_id, $lesson['id'], $owner['id'], $data, $invalid) !== 'invalid') { throw new RuntimeException('Invalid lesson revision was accepted.'); }
		}
		$database = $this->db;
		$debug = $database->db_debug;
		$database->db_debug = FALSE;
		$proxy = new Lesson_revision_failure_probe($database);
		$this->db = $proxy;
		try
		{
			$result = $operation === 'create' ? $this->Content_model->create_lesson($course_id, $data, $owner['id']) : $this->Content_model->update_lesson_draft($course_id, $lesson['id'], $owner['id'], $data, $lesson['content_version']);
		}
		finally { $this->db = $database; $database->db_debug = $debug; }
		$after_course = $this->db->where('id', (int) $course_id)->get('st_courses')->row_array();
		$after_lessons = $this->Content_model->get_course_lessons($course_id);
		$after_audit = $this->db->where('actor_user_id', (int) $owner['id'])->count_all_results('st_audit_events');
		$expected = $operation === 'create' ? FALSE : 'unavailable';
		if ($result !== $expected || ! $proxy->failed || $course !== $after_course || $lessons !== $after_lessons || $before_audit !== $after_audit) { throw new RuntimeException('Lesson audit failure did not roll back course/lesson/audit.'); }
		fwrite(STDOUT, 'PASS lesson '.$operation." audit failure rolls back course and lesson versions/content with no audit; invalid revisions rejected.\n");
	}
}

class Lesson_revision_failure_probe
{
	private $database;
	public $failed = FALSE;
	public function __construct($database)
	{
		$this->database = $database;
	}
	public function __call($name, $arguments)
	{
		if ($name === 'insert' && ($arguments[0] ?? '') === 'st_audit_events')
		{
			$this->failed = TRUE;
			return $this->database->query('STUDY_QA_FORCED_LESSON_REVISION_AUDIT_FAILURE');
		}
		return call_user_func_array(array($this->database, $name), $arguments);
	}
}
