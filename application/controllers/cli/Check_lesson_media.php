<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Check_lesson_media extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		if (PHP_SAPI !== 'cli' || ENVIRONMENT !== 'development') { show_404(); exit(1); }
		$this->load->model('Lesson_media_model');
		$this->load->library('Lesson_image_store');
	}

	public function index($course_id = '', $mode = '')
	{
		if ($this->db->database !== 'db_study' || ! is_string($course_id) || ! preg_match('/\A[1-9][0-9]{0,17}\z/', $course_id) || ! in_array($mode, array('seed', 'verify', 'cleanup'), TRUE)) { throw new RuntimeException('QA target mismatch.'); }
		$course = $this->db->where('id', (int) $course_id)->get('st_courses')->row_array();
		if ( ! $course || ! preg_match('/\Aqa-media-([a-f0-9]{12})-main\z/', $course['slug'], $matches)) { throw new RuntimeException('Only an exact synthetic media course is permitted.'); }
		$prefix = 'qa_media_'.$matches[1];
		$owner = $this->db->where('id', (int) $course['author_user_id'])->get('st_users')->row_array();
		if ( ! $owner || $owner['username'] !== $prefix.'_owner' || $owner['status'] !== 'active') { throw new RuntimeException('Synthetic media owner mismatch.'); }
		$lesson = $this->db->select('l.*')->from('st_lessons l')->join('st_course_sections s', 's.id = l.section_id')->where('s.course_id', (int) $course_id)->where('l.slug', 'qa-media-'.$matches[1].'-lesson')->get()->row_array();
		if ( ! $lesson) { throw new RuntimeException('Synthetic media lesson mismatch.'); }
		if ($mode === 'cleanup')
		{
			$image = $this->Lesson_media_model->get_for_lesson($lesson['id']);
			if ($image === FALSE || ($image && ! $this->lesson_image_store->remove($image['storage_key']))) { throw new RuntimeException('Synthetic image cleanup failed.'); }
			fwrite(STDOUT, "PASS synthetic lesson image file cleanup; database rows are left to the scoped HTTP harness.\n");
			return;
		}
		if ($course['status'] !== 'draft') { throw new RuntimeException('Synthetic media course must be a draft.'); }
		if ($mode === 'seed') { $this->seed($course, $lesson, $owner); return; }
		$this->verify($course, $lesson, $owner, $prefix);
	}

	private function seed($course, $lesson, $owner)
	{
		if ( ! function_exists('imagecreatetruecolor') || ! function_exists('imagepng')) { throw new RuntimeException('Synthetic seed requires GD in the CLI runtime.'); }
		$temp_file = tempnam(sys_get_temp_dir(), 'study-media-');
		if ($temp_file === FALSE) { throw new RuntimeException('Synthetic image fixture could not be created.'); }
		$canvas = NULL;
		try
		{
			$canvas = imagecreatetruecolor(32, 20);
			if ($canvas === FALSE) { throw new RuntimeException('Synthetic image canvas could not be created.'); }
			imagefill($canvas, 0, 0, imagecolorallocate($canvas, 78, 96, 214));
			imagefilledrectangle($canvas, 3, 4, 14, 15, imagecolorallocate($canvas, 253, 204, 82));
			if ( ! imagepng($canvas, $temp_file)) { throw new RuntimeException('Synthetic image could not be encoded.'); }
			$stored = $this->lesson_image_store->from_local_file($temp_file, 'synthetic.png');
		}
		finally
		{
			if ($canvas) { imagedestroy($canvas); }
			if (is_file($temp_file) && ! unlink($temp_file)) { throw new RuntimeException('Synthetic source image cleanup failed.'); }
		}
		if (($stored['status'] ?? NULL) !== 'ok') { throw new RuntimeException('Synthetic normalized image storage failed.'); }
		$data = array('alt_text' => 'Dua bentuk berwarna untuk latihan sintetis', 'caption' => 'Gambar uji sintetis; bukan materi pembelajaran nyata.', 'source_note' => 'Dibuat oleh harness lokal untuk pengujian.', 'rights_status' => 'owned');
		$result = $this->Lesson_media_model->change($course['id'], $lesson['id'], $owner['id'], $lesson['content_version'], 'upload', $data, $stored['image']);
		if ($result['status'] !== 'ok')
		{
			if ($result['status'] !== 'uncertain') { $this->lesson_image_store->remove($stored['image']['storage_key']); }
			throw new RuntimeException('Synthetic image attachment was not confirmed; inspect fixture state.');
		}
		if ($result['previous_key'] !== NULL && ! $this->lesson_image_store->remove($result['previous_key'])) { throw new RuntimeException('Previous synthetic image cleanup failed.'); }
		$image = $this->Lesson_media_model->get_for_lesson($lesson['id']);
		if ( ! $image || $this->lesson_image_store->read($image) === FALSE || ! $this->Lesson_media_model->ready_for_review($course['id'])) { throw new RuntimeException('Attached synthetic image could not be verified.'); }
		fwrite(STDOUT, "PASS GD CLI synthetic PNG normalized, attached with lesson/course revisions and audit, and readable from private storage.\n");
	}

	private function snapshot($course_id, $lesson_id)
	{
		return array(
			'course' => $this->db->where('id', (int) $course_id)->get('st_courses')->row_array(),
			'lesson' => $this->db->where('id', (int) $lesson_id)->get('st_lessons')->row_array(),
			'image' => $this->Lesson_media_model->get_for_lesson($lesson_id),
			'audit' => $this->db->where('entity_type', 'lesson')->where('entity_id', (int) $lesson_id)->order_by('id')->get('st_audit_events')->result_array()
		);
	}

	private function verify($course, $lesson, $owner, $prefix)
	{
		$before = $this->snapshot($course['id'], $lesson['id']);
		$image = $before['image'];
		if ( ! $image || (int) $lesson['content_version'] < 2) { throw new RuntimeException('Seed the synthetic image before model verification.'); }
		$bytes_before = $this->lesson_image_store->read($image);
		if ($bytes_before === FALSE) { throw new RuntimeException('Synthetic image storage is unavailable.'); }
		$metadata = array_intersect_key($image, array_flip(array('alt_text', 'caption', 'source_note', 'rights_status')));
		foreach (array('other', 'member') as $kind)
		{
			$actor = $this->db->where('username', $prefix.'_'.$kind)->get('st_users')->row_array();
			if ( ! $actor || $this->Lesson_media_model->change($course['id'], $lesson['id'], $actor['id'], $lesson['content_version'], 'remove', array())['status'] !== 'denied') { throw new RuntimeException('Model image ownership/role boundary failed.'); }
		}
		$this->db->where('id', (int) $owner['id'])->update('st_users', array('status' => 'suspended'));
		try
		{
			if ($this->Lesson_media_model->change($course['id'], $lesson['id'], $owner['id'], $lesson['content_version'], 'remove', array())['status'] !== 'denied') { throw new RuntimeException('Inactive image actor was accepted.'); }
		}
		finally { $this->db->where('id', (int) $owner['id'])->update('st_users', array('status' => 'active')); }
		if ($this->Lesson_media_model->change($course['id'], $lesson['id'], $owner['id'], (int) $lesson['content_version'] - 1, 'remove', array())['status'] !== 'conflict') { throw new RuntimeException('Stale image revision was accepted.'); }
		if ($this->Lesson_media_model->change($course['id'], $lesson['id'], $owner['id'], $lesson['content_version'], 'metadata', $metadata)['status'] !== 'no_change') { throw new RuntimeException('Unchanged image metadata was mutated.'); }
		$invalid_metadata = array(array('alt_text' => array('bad')), array('alt_text' => 'ab'), array('alt_text' => str_repeat('a', 301)), array('alt_text' => "bad\0text"), array('alt_text' => "bad\xC3"), array('caption' => str_repeat('c', 1001)), array('source_note' => 'abcd'), array('source_note' => str_repeat('s', 2001)), array('rights_status' => 'unknown'));
		foreach ($invalid_metadata as $invalid)
		{
			if ($this->Lesson_media_model->change($course['id'], $lesson['id'], $owner['id'], $lesson['content_version'], 'metadata', array_replace($metadata, $invalid))['status'] !== 'invalid') { throw new RuntimeException('Invalid image metadata was accepted.'); }
		}
		$invalid_images = array(array('storage_key' => '../outside.png'), array('storage_key' => str_repeat('a', 64).'.svg'), array('mime_type' => 'image/jpeg'), array('byte_size' => '6291457'), array('width' => '4097'), array('width' => '2001', 'height' => '2000'), array('height' => array(2)), array('sha256' => 'bad'));
		foreach ($invalid_images as $invalid)
		{
			if ($this->Lesson_media_model->change($course['id'], $lesson['id'], $owner['id'], $lesson['content_version'], 'upload', $metadata, array_replace($image, $invalid))['status'] !== 'invalid') { throw new RuntimeException('Invalid normalized image metadata was accepted.'); }
		}
		foreach (array(0, '01', '1e2', array(2), '2147483648') as $revision)
		{
			if ($this->Lesson_media_model->change($course['id'], $lesson['id'], $owner['id'], $revision, 'metadata', $metadata)['status'] !== 'invalid') { throw new RuntimeException('Invalid image revision was accepted.'); }
		}
		$this->verify_failures($course, $lesson, $owner, $metadata, $image, $before, $bytes_before);
		if ($this->snapshot($course['id'], $lesson['id']) !== $before || $this->lesson_image_store->read($image) !== $bytes_before) { throw new RuntimeException('Image boundary verification mutated fixture state.'); }
		fwrite(STDOUT, "PASS real-DB image actor/ownership, revision, no-op, metadata/image bounds and failure isolation; fixture rows/revisions/timestamps/audit and file bytes unchanged.\n");
	}

	private function verify_failures($course, $lesson, $owner, $metadata, $image, $before, $bytes_before)
	{
		$database = $this->db;
		$debug = $database->db_debug;
		$cases = array('begin', 'course', 'actor', 'roles', 'lesson', 'image', 'image_update', 'lesson_update', 'course_update', 'audit', 'image_delete', 'audit_delete', 'audit_upload', 'throw', 'commit', 'commit_throw', 'rollback', 'rollback_throw');
		foreach ($cases as $failure)
		{
			$action = in_array($failure, array('image_delete', 'audit_delete'), TRUE) ? 'remove' : ($failure === 'audit_upload' ? 'upload' : 'metadata');
			$probe = new Lesson_media_failure_probe($database, $failure);
			$this->db = $probe;
			try { $result = $this->Lesson_media_model->change($course['id'], $lesson['id'], $owner['id'], $lesson['content_version'], $action, array_replace($metadata, array('alt_text' => 'Synthetic rollback probe')), $image); }
			finally
			{
				$this->db = $database;
				if (in_array($failure, array('rollback', 'rollback_throw'), TRUE) && ! $database->trans_rollback()) { throw new RuntimeException('Synthetic media rollback cleanup failed.'); }
			}
			$expected = in_array($failure, array('commit', 'commit_throw', 'rollback', 'rollback_throw'), TRUE) ? 'uncertain' : 'unavailable';
			if ($result !== array('status' => $expected) || ! $probe->failed || $probe->rollbacks !== ($failure === 'begin' ? 0 : 1) || $database->db_debug !== $debug || $this->snapshot($course['id'], $lesson['id']) !== $before || $this->lesson_image_store->read($image) !== $bytes_before) { throw new RuntimeException('Image transaction failure isolation failed at '.$failure); }
		}
		fwrite(STDOUT, 'PASS '.count($cases)." injected real-DB image transaction failures; commit probes fail before actual commit, not a server disconnect. Failed rollback probes require harness cleanup.\n");
	}
}

class Lesson_media_failure_probe
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
			if ($this->failure === 'rollback_throw') { throw new RuntimeException('Synthetic media rollback exception.'); }
		}
		$sql = $name === 'query' ? ($arguments[0] ?? '') : '';
		$read_markers = array('course' => 'FROM st_courses ', 'actor' => 'FROM st_users ', 'roles' => 'FROM st_user_roles ', 'lesson' => 'SELECT l.id, l.content_version', 'image' => 'SELECT * FROM st_lesson_images');
		$write_targets = array('image_update' => array('update', 'st_lesson_images'), 'lesson_update' => array('update', 'st_lessons'), 'course_update' => array('update', 'st_courses'), 'audit' => array('insert', 'st_audit_events'), 'image_delete' => array('delete', 'st_lesson_images'));
		$read_failure = isset($read_markers[$this->failure]) && strpos($sql, $read_markers[$this->failure]) !== FALSE;
		$write_failure = isset($write_targets[$this->failure]) && $name === $write_targets[$this->failure][0] && ($arguments[0] ?? '') === $write_targets[$this->failure][1];
		$write_failure = $write_failure || (in_array($this->failure, array('audit_delete', 'audit_upload', 'rollback', 'rollback_throw'), TRUE) && $name === 'insert' && ($arguments[0] ?? '') === 'st_audit_events');
		$special_failure = ($this->failure === 'begin' && $name === 'trans_begin') || ($this->failure === 'throw' && $name === 'query') || (in_array($this->failure, array('commit', 'commit_throw'), TRUE) && $name === 'trans_commit');
		if ( ! $this->failed && ($read_failure || $write_failure || $special_failure))
		{
			$this->failed = TRUE;
			$this->database->reset_query();
			if (in_array($this->failure, array('throw', 'commit_throw'), TRUE)) { throw new RuntimeException('Synthetic media transaction exception.'); }
			if ($special_failure) { return FALSE; }
			return $this->database->query('STUDY_QA_FORCED_MEDIA_QUERY_FAILURE');
		}
		$result = call_user_func_array(array($this->database, $name), $arguments);
		return $result === $this->database ? $this : $result;
	}
}
