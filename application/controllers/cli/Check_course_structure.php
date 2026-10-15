<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Check_course_structure extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		if (PHP_SAPI !== 'cli' || ENVIRONMENT !== 'development') { show_404(); exit(1); }
		$this->load->model('Course_structure_model');
	}

	public function index($course_id = '')
	{
		if ($this->db->database !== 'db_study' || ! ctype_digit((string) $course_id)) { throw new RuntimeException('QA target mismatch.'); }
		$course = $this->db->where('id', (int) $course_id)->get('st_courses')->row_array();
		if ( ! $course || ! preg_match('/\Aqa-structure-([a-f0-9]{12})-main\z/', $course['slug'], $matches)) { throw new RuntimeException('Only an exact synthetic course is permitted.'); }
		$prefix = 'qa_structure_'.$matches[1];
		$owner = $this->db->where('id', (int) $course['author_user_id'])->get('st_users')->row_array();
		if ( ! $owner || $owner['username'] !== $prefix.'_owner' || $owner['status'] !== 'active') { throw new RuntimeException('Synthetic owner mismatch.'); }
		$section = $this->Course_structure_model->sections($course_id)[0];
		$before = $this->db->where('entity_type', 'course')->where('entity_id', (int) $course_id)->count_all_results('st_audit_events');
		$data = array('section_id' => $section['id'], 'title' => 'Synthetic rejected change', 'sort_order' => 1);
		foreach (array('other', 'member') as $kind)
		{
			$actor = $this->db->where('username', $prefix.'_'.$kind)->get('st_users')->row_array();
			if ( ! $actor || $this->Course_structure_model->change($course_id, $actor['id'], $course['content_version'], 'update_section', $data) !== 'denied') { throw new RuntimeException('Model ownership/role boundary failed.'); }
		}
		foreach (array(array('sort_order' => array(1)), array('sort_order' => '1e2'), array('sort_order' => '10000'), array('title' => array('bad')), array('title' => str_repeat('a', 181)), array('section_id' => '99999999999999999999')) as $invalid)
		{
			if ($this->Course_structure_model->change($course_id, $owner['id'], $course['content_version'], 'update_section', array_replace($data, $invalid)) !== 'invalid') { throw new RuntimeException('Model input boundary failed.'); }
		}
		$this->db->where('id', (int) $owner['id'])->update('st_users', array('status' => 'suspended'));
		try { if ($this->Course_structure_model->change($course_id, $owner['id'], $course['content_version'], 'update_section', $data) !== 'denied') { throw new RuntimeException('Inactive model actor was accepted.'); } }
		finally { $this->db->where('id', (int) $owner['id'])->update('st_users', array('status' => 'active')); }
		$database = $this->db;
		$debug = $database->db_debug;
		$sections_before = $this->Course_structure_model->sections($course_id);
		$lessons_before = $this->db->select('l.*')->from('st_lessons l')->join('st_course_sections s', 's.id=l.section_id')->where('s.course_id', (int) $course_id)->order_by('l.id', 'ASC')->get()->result_array();
		$cases = array('begin', 'course', 'actor', 'roles', 'count', 'section', 'lesson', 'section_insert', 'section_update', 'lesson_update', 'course_update', 'audit', 'throw', 'commit', 'commit_throw', 'rollback', 'rollback_throw');
		foreach ($cases as $failure)
		{
			$action = in_array($failure, array('count', 'section_insert'), TRUE) ? 'create_section' : (in_array($failure, array('lesson', 'lesson_update'), TRUE) ? 'move_lesson' : 'update_section');
			$probe_data = array('section_id' => $section['id'], 'title' => 'Synthetic failure rollback', 'sort_order' => 9999, 'lesson_id' => $lessons_before[0]['id']);
			$proxy = new Structure_failure_probe($database, $failure);
			$this->db = $proxy;
			try { $result = $this->Course_structure_model->change($course_id, $owner['id'], $course['content_version'], $action, $probe_data); }
			finally
			{
				$this->db = $database;
				// These two probes deliberately prevent the model's rollback.
				// The harness, not the model, cleans the still-open synthetic transaction.
				if (in_array($failure, array('rollback', 'rollback_throw'), TRUE) && ! $database->trans_rollback()) { throw new RuntimeException('Synthetic rollback cleanup failed.'); }
			}
			if ($database->db_debug !== $debug) { throw new RuntimeException('Database debug setting was not restored.'); }
			$after_course = $this->db->where('id', (int) $course_id)->get('st_courses')->row_array();
			$after_sections = $this->Course_structure_model->sections($course_id);
			$after_lessons = $this->db->select('l.*')->from('st_lessons l')->join('st_course_sections s', 's.id=l.section_id')->where('s.course_id', (int) $course_id)->order_by('l.id', 'ASC')->get()->result_array();
			$after = $this->db->where('entity_type', 'course')->where('entity_id', (int) $course_id)->count_all_results('st_audit_events');
			$expected = in_array($failure, array('commit', 'commit_throw', 'rollback', 'rollback_throw'), TRUE) ? 'uncertain' : 'unavailable';
			if ($result !== $expected || ! $proxy->failed || $proxy->rollbacks !== ($failure === 'begin' ? 0 : 1) || $course !== $after_course || $sections_before !== $after_sections || $lessons_before !== $after_lessons || $before !== $after) { throw new RuntimeException('Structure failure isolation failed at '.$failure); }
		}
		fwrite(STDOUT, "PASS structure boundaries and 15 injected transaction failures: rows/revisions/timestamps/audit unchanged, debug restored, rollback attempted only after begin. Two additional failed-rollback probes return uncertain and require explicit harness cleanup. Commit probes fail before the real commit, not a simulated server disconnect.\n");
	}
}

class Structure_failure_probe
{
	private $database;
	private $failure;
	public $failed = FALSE;
	public $rollbacks = 0;
	public function __construct($database, $failure)
	{
		$this->database = $database;
		$this->failure = $failure;
	}
	public function __get($name)
	{
		return $this->database->$name;
	}
	public function __set($name, $value)
	{
		$this->database->$name = $value;
	}
	public function __call($name, $arguments)
	{
		if ($name === 'trans_rollback')
		{
			$this->rollbacks++;
			if ($this->failure === 'rollback') { return FALSE; }
			if ($this->failure === 'rollback_throw') { throw new RuntimeException('Synthetic rollback exception.'); }
		}
		$sql = $name === 'query' ? ($arguments[0] ?? '') : '';
		$read_markers = array('course' => 'FROM st_courses ', 'actor' => 'FROM st_users ', 'roles' => 'FROM st_user_roles ', 'count' => 'SELECT COUNT(*)', 'section' => 'SELECT id, title, sort_order FROM st_course_sections', 'lesson' => 'SELECT l.id, l.section_id');
		$write_targets = array('section_insert' => array('insert', 'st_course_sections'), 'section_update' => array('update', 'st_course_sections'), 'lesson_update' => array('update', 'st_lessons'), 'course_update' => array('update', 'st_courses'), 'audit' => array('insert', 'st_audit_events'));
		$read_failure = isset($read_markers[$this->failure]) && strpos($sql, $read_markers[$this->failure]) !== FALSE;
		$write_failure = isset($write_targets[$this->failure]) && $name === $write_targets[$this->failure][0] && ($arguments[0] ?? '') === $write_targets[$this->failure][1];
		$write_failure = $write_failure || (in_array($this->failure, array('rollback', 'rollback_throw'), TRUE) && $name === 'insert' && ($arguments[0] ?? '') === 'st_audit_events');
		$special_failure = ($this->failure === 'begin' && $name === 'trans_begin') || ($this->failure === 'throw' && $name === 'query') || (in_array($this->failure, array('commit', 'commit_throw'), TRUE) && $name === 'trans_commit');
		if ( ! $this->failed && ($read_failure || $write_failure || $special_failure))
		{
			$this->failed = TRUE;
			$this->database->reset_query();
			if (in_array($this->failure, array('throw', 'commit_throw'), TRUE)) { throw new RuntimeException('Synthetic transaction exception.'); }
			if ($special_failure) { return FALSE; }
			return $this->database->query('STUDY_QA_FORCED_STRUCTURE_QUERY_FAILURE');
		}
		$result = call_user_func_array(array($this->database, $name), $arguments);
		return $result === $this->database ? $this : $result;
	}
}
