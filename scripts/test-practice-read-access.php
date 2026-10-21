<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');

class CI_Model
{
	public $db;
	public $load;
	public $services = array();

	public function __get($name)
	{
		if ( ! isset($this->services[$name])) { throw new RuntimeException('Unexpected model dependency.'); }
		return $this->services[$name];
	}
}
require __DIR__.'/../application/models/Practice_model.php';

class Practice_read_database
{
	public $calls = array();
	public $row = array('id' => 61, 'status' => 'published');

	public function where($field, $value)
	{
		$this->calls[] = array('where', $field, $value);
		return $this;
	}

	public function get($table)
	{
		$this->calls[] = array('get', $table);
		return $this;
	}

	public function query($sql, $parameters)
	{
		$this->calls[] = array('query', $sql, $parameters);
		return $this;
	}

	public function row_array()
	{
		$this->calls[] = array('row_array');
		return $this->row;
	}

	public function __call($method, $arguments)
	{
		throw new RuntimeException('Unexpected database operation in read-access unit.');
	}
}

class Practice_read_loader
{
	public $calls = array();

	public function model($name)
	{
		if ( ! in_array($name, array('Content_model', 'Assignment_model'), TRUE)) { throw new RuntimeException('Unexpected loaded model.'); }
		$this->calls[] = $name;
	}
}

class Practice_read_content
{
	public $calls = array();
	public $allowed = TRUE;

	public function can_access_course($user_id, $course)
	{
		$this->calls[] = array($user_id, $course);
		return $this->allowed;
	}
}

class Practice_read_assignment
{
	public $calls = array();
	public $session = array('id' => 51, 'status' => 'in_progress');
	public $allowed = TRUE;

	public function child_session($session_id, $user_id)
	{
		$this->calls[] = array('child_session', $session_id, $user_id);
		return $this->session;
	}

	public function can_work($session)
	{
		$this->calls[] = array('can_work', $session);
		return $session !== NULL && $this->allowed;
	}
}

function practice_read_fixture()
{
	$model = new Practice_model();
	$model->db = new Practice_read_database();
	$model->load = new Practice_read_loader();
	$model->services = array('Content_model' => new Practice_read_content(), 'Assignment_model' => new Practice_read_assignment());
	return $model;
}

$assertions = 0;
function practice_read_assert($condition, $message)
{
	global $assertions;
	if ( ! $condition) { throw new RuntimeException($message); }
	$assertions++;
}

function practice_read_no_dependencies($model)
{
	practice_read_assert($model->db->calls === array() && $model->load->calls === array() && $model->Content_model->calls === array() && $model->Assignment_model->calls === array(), 'Denied/graded short circuit must not query or delegate access.');
}

$base_attempt = array('id' => 31, 'learner_user_id' => 41, 'session_id' => 51, 'course_id' => 61, 'created_by_user_id' => 71);
foreach (array('course_quiz', 'parent_assigned') as $session_type)
{
	foreach (array('graded', 'in_progress', 'submitted', 'abandoned') as $status)
	{
		$model = practice_read_fixture();
		$attempt = $base_attempt + array('session_type' => $session_type, 'status' => $status);
		practice_read_assert($model->can_read_attempt($attempt, 42) === FALSE, 'Another learner must never read an attempt, including graded results.');
		practice_read_no_dependencies($model);
	}
	$model = practice_read_fixture();
	$model->db->row = NULL;
	$model->Content_model->allowed = FALSE;
	$model->Assignment_model->allowed = FALSE;
	$attempt = $base_attempt + array('session_type' => $session_type, 'status' => 'graded');
	practice_read_assert($model->can_read_attempt($attempt, '41') === TRUE, 'Own graded results remain readable after current access is revoked.');
	practice_read_no_dependencies($model);
	foreach (array('submitted', 'abandoned', 'unknown', '', NULL, TRUE, 1, 'IN_PROGRESS', 'GRADED') as $status)
	{
		$model = practice_read_fixture();
		$attempt['status'] = $status;
		practice_read_assert($model->can_read_attempt($attempt, 41) === FALSE, 'Only exact in_progress or graded may be read; terminal course attempts must not expose an editable form.');
		practice_read_no_dependencies($model);
	}
}

foreach (array(NULL, FALSE, array()) as $missing)
{
	$model = practice_read_fixture();
	practice_read_assert($model->can_read_attempt($missing, 41) === FALSE, 'Missing attempts must be denied without querying.');
	practice_read_no_dependencies($model);
}

$attempt = $base_attempt + array('session_type' => 'course_quiz', 'status' => 'in_progress');
foreach (array(TRUE, FALSE) as $allowed)
{
	$model = practice_read_fixture();
	$model->Content_model->allowed = $allowed;
	practice_read_assert($model->can_read_attempt($attempt, 41) === $allowed, 'Active course attempts must follow current course-access result.');
	practice_read_assert($model->db->calls === array(array('where', 'id', 61), array('get', 'st_courses'), array('row_array')), 'Course lookup must stay scoped to the attempt course and read-only.');
	practice_read_assert($model->Content_model->calls === array(array(41, $model->db->row)) && $model->load->calls === array('Content_model') && $model->Assignment_model->calls === array(), 'Course access must receive its learner and current course, never delegate to assignments.');
}
foreach (array(NULL, array('id' => 61, 'status' => 'draft'), array('id' => 61, 'status' => 'archived')) as $course)
{
	$model = practice_read_fixture();
	$model->db->row = $course;
	practice_read_assert($model->can_read_attempt($attempt, 41) === FALSE && $model->Content_model->calls === array(), 'Missing or unpublished courses must deny active attempts before enrollment access.');
}

$attempt['session_type'] = 'parent_assigned';
foreach (array(TRUE, FALSE) as $allowed)
{
	$model = practice_read_fixture();
	$model->Assignment_model->allowed = $allowed;
	practice_read_assert($model->can_read_attempt($attempt, 41) === $allowed, 'Active family attempts must follow delegated relationship/schedule/access checks.');
	practice_read_assert($model->Assignment_model->calls === array(array('child_session', 51, 41), array('can_work', $model->Assignment_model->session)), 'Family access must resolve exactly this session for this learner before can_work.');
	practice_read_assert($model->db->calls === array() && $model->Content_model->calls === array() && $model->load->calls === array('Assignment_model'), 'Family attempts must not fall through to course access.');
}
$model = practice_read_fixture();
$model->Assignment_model->session = NULL;
practice_read_assert($model->can_read_attempt($attempt, 41) === FALSE && $model->Assignment_model->calls === array(array('child_session', 51, 41), array('can_work', NULL)), 'Missing/revoked family relationship must be passed to can_work and denied.');

// The lookup, not can_read_attempt, owns the session-type allowlist.
$model = practice_read_fixture();
$model->db->row = $attempt;
practice_read_assert($model->find_attempt('31', '41') === $attempt, 'Scoped lookup returns the existing attempt unchanged.');
$call = $model->db->calls[0];
practice_read_assert(count($model->db->calls) === 2 && $call[0] === 'query' && $call[2] === array(31, 41, 41), 'Attempt lookup must bind attempt ID and both learner ownership checks.');
practice_read_assert(strpos($call[1], "WHERE a.id = ? AND a.learner_user_id = ? AND s.learner_user_id = ? AND s.session_type IN ('course_quiz', 'parent_assigned', 'self_practice', 'assessment')") !== FALSE, 'Attempt/session ownership and explicit supported-type allowlist must remain intact.');
foreach(array('self_practice','assessment') as $kind){
 $model=practice_read_fixture();$attempt['session_type']=$kind;$model->db->row=array('session_id'=>51);
 practice_read_assert($model->can_read_attempt($attempt,41)===TRUE,'Standalone attempt requires its drill record.');
 $model->db->row=NULL;practice_read_assert($model->can_read_attempt($attempt,41)===FALSE,'Missing drill metadata must fail closed.');
}

fwrite(STDOUT, 'PASS '.$assertions." practice read-access assertions: owner isolation, strict statuses, final-result retention, course access and family delegation.\n");
fwrite(STDOUT, "LIMITATION isolated recording fakes only; no database, HTTP, browser or actual entitlement/schedule evaluation.\n");
