<?php
if (PHP_SAPI !== 'cli') { exit(1); }
if ( ! extension_loaded('mbstring'))
{
	fwrite(STDERR, "Matching engine tests require the mbstring extension; no application or test fallback is substituted.\n");
	exit(1);
}
define('BASEPATH', dirname(__DIR__).'/system/');
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

class CI_Model
{
	public $db;
	public $load;
	public $services = array();

	public function __get($name)
	{
		if ( ! isset($this->services[$name])) { throw new RuntimeException('Unexpected matching test dependency.'); }
		return $this->services[$name];
	}
}
require __DIR__.'/../application/models/Matching_run_model.php';
require __DIR__.'/../application/libraries/Study_transaction.php';
require __DIR__.'/../application/libraries/Matching_cards.php';
require __DIR__.'/../application/libraries/Matching_state.php';

$matching_transaction_logs = array();
function log_message($level, $message)
{
	global $matching_transaction_logs;
	$matching_transaction_logs[] = array($level, $message);
}

class Matching_transaction_result
{
	private $database;
	private $name;
	private $value;

	public function __construct($database, $name, $value)
	{
		$this->database = $database;
		$this->name = $name;
		$this->value = $value;
	}

	public function row_array()
	{
		$this->database->hit('read:'.$this->name);
		return $this->value;
	}
}

class Matching_transaction_database
{
	public $db_debug = TRUE;
	public $active = FALSE;
	public $healthy = TRUE;
	public $calls = array();
	public $pending = array();
	public $durable = array();
	public $failure_at = 0;
	public $failure_mode = 'false';
	public $failed = FALSE;
	public $poison_on_failure = TRUE;
	public $rollback_mode = 'success';
	public $commit_ack_lost = FALSE;
	public $inserted_id = 51;
	public $allow_access = TRUE;
	public $lesson = array('id' => 61, 'title' => 'Synthetic lesson', 'content_version' => 2, 'is_preview' => 0, 'course_id' => 71, 'is_free' => 0, 'lesson_status' => 'published', 'course_status' => 'published');
	public $user = array('id' => 41, 'learning_stage' => 'primary');
	public $initial;
	public $locked;
	public $previous = array();
	public $existing_active = array();
	public $previous_move = array();
	public $deck = array('id' => 81);
	public $cards;
	public $swallowed_failure = '';

	public function __construct()
	{
		$this->cards = array(array('front_text' => 'Front one', 'back_text' => 'Back one'), array('front_text' => 'Front two', 'back_text' => 'Back two'));
		$snapshot = array('cards' => array(array('front' => 'A', 'back' => 'B'), array('front' => 'C', 'back' => 'D')), 'round_size' => 6, 'rounds' => array(array(0, 1)), 'lesson_version' => 2);
		$this->initial = array('id' => 51, 'learner_user_id' => 41, 'lesson_id' => 61, 'status' => 'in_progress', 'rules_version' => 'matching_v1', 'revision' => 3, 'snapshot_json' => json_encode($snapshot), 'state_json' => json_encode((new Matching_state())->initial()));
		$this->locked = $this->initial;
	}

	public function hit($name, $data = array())
	{
		$this->calls[] = array('name' => $name, 'data' => $data, 'debug' => $this->db_debug, 'active' => $this->active);
		if ($this->failure_at !== count($this->calls)) { return TRUE; }
		$this->failed = TRUE;
		if ($this->poison_on_failure) { $this->healthy = FALSE; }
		if ($this->failure_mode === 'throw') { throw new RuntimeException('Synthetic private driver detail must not escape.'); }
		return FALSE;
	}

	public function trans_active()
	{
		$this->hit('active');
		return $this->active;
	}

	public function trans_begin()
	{
		if ( ! $this->hit('begin')) { return FALSE; }
		$this->active = TRUE;
		return TRUE;
	}

	public function trans_status()
	{
		return $this->hit('status') && $this->healthy;
	}

	public function trans_commit()
	{
		if ( ! $this->hit('commit')) { return FALSE; }
		$this->durable = array_merge($this->durable, $this->pending);
		$this->pending = array();
		$this->active = FALSE;
		return ! $this->commit_ack_lost;
	}

	public function trans_rollback()
	{
		$this->hit('rollback');
		if ($this->rollback_mode === 'throw') { throw new RuntimeException('Synthetic private rollback detail.'); }
		if ($this->rollback_mode === 'false') { return FALSE; }
		$this->pending = array();
		$this->active = FALSE;
		return TRUE;
	}

	public function query($sql, $parameters)
	{
		if (strpos($sql, 'SELECT l.id,') === 0) { $name = 'lesson'; $value = $this->lesson; }
		elseif (strpos($sql, 'SELECT id,learning_stage FROM st_users') === 0) { $name = 'user'; $value = $this->user; }
		elseif (strpos($sql, 'SELECT r.* FROM st_matching_runs') === 0)
		{
			$name = substr($sql, -10) === 'FOR UPDATE' ? 'locked' : 'initial';
			$value = $name === 'locked' ? $this->locked : $this->initial;
		}
		elseif (strpos($sql, 'start_key=?') !== FALSE) { $name = 'previous'; $value = $this->previous; }
		elseif (strpos($sql, 'active_key=?') !== FALSE) { $name = 'existing_active'; $value = $this->existing_active; }
		elseif (strpos($sql, 'FROM st_matching_moves') !== FALSE) { $name = 'previous_move'; $value = $this->previous_move; }
		else { throw new RuntimeException('Unexpected SQL shape in isolated matching test.'); }
		if ( ! $this->hit('query:'.$name, array($sql, $parameters))) { return FALSE; }
		return new Matching_transaction_result($this, $name, $value);
	}

	public function where($key, $value)
	{
		$this->hit('where', array($key, $value));
		return $this;
	}

	public function set($key, $value, $escape)
	{
		$this->hit('set', array($key, $value, $escape));
		return $this;
	}

	public function insert($table, $data)
	{
		$name = 'insert:'.$table;
		if ( ! $this->hit($name, $data)) { return FALSE; }
		$this->pending[] = array($name, $data);
		return TRUE;
	}

	public function insert_id()
	{
		return $this->hit('insert_id') ? $this->inserted_id : 0;
	}

	public function update($table, $data)
	{
		$name = 'update:'.$table;
		if ( ! $this->hit($name, $data)) { return FALSE; }
		$this->pending[] = array($name, $data);
		return TRUE;
	}
}

class Matching_transaction_dependencies
{
	private $database;

	public function __construct($database)
	{
		$this->database = $database;
	}

	public function can_access_lesson($user_id, $lesson, $lock = FALSE)
	{
		return $this->database->hit('dependency:access', array($user_id, $lesson, $lock)) && $this->database->allow_access;
	}

	public function get_published_deck_for_lesson($lesson_id)
	{
		if ($this->database->swallowed_failure === 'deck') { $this->database->healthy = FALSE; }
		return $this->database->hit('dependency:deck', array($lesson_id)) ? $this->database->deck : FALSE;
	}

	public function get_published_cards($deck_id)
	{
		if ($this->database->swallowed_failure === 'cards') { $this->database->healthy = FALSE; }
		return $this->database->hit('dependency:cards', array($deck_id)) ? $this->database->cards : FALSE;
	}
}

class Matching_transaction_loader
{
	public function library($names)
	{
		foreach ((array) $names as $name)
		{
			if ( ! in_array($name, array('Study_transaction', 'Matching_cards', 'Matching_state'), TRUE)) { throw new RuntimeException('Unexpected matching test library.'); }
		}
	}

	public function model($name)
	{
		if ( ! in_array($name, array('Content_model', 'Flashcard_model'), TRUE)) { throw new RuntimeException('Unexpected matching test model.'); }
	}
}

class Matching_transaction_bad_json extends Matching_state
{
	public $phase;

	public function begin($cards, $size)
	{
		$result = parent::begin($cards, $size);
		if ($this->phase === 'snapshot') { $result['invalid'] = "\xFF"; }
		return $result;
	}

	public function initial()
	{
		$result = parent::initial();
		if ($this->phase === 'initial') { $result['invalid'] = "\xFF"; }
		return $result;
	}

	public function choose($snapshot, $state, $left, $right)
	{
		$result = parent::choose($snapshot, $state, $left, $right);
		if ($this->phase === 'move') { $result['state']['invalid'] = "\xFF"; }
		return $result;
	}
}

function matching_transaction_model($database)
{
	$model = new Matching_run_model();
	$model->db = $database;
	$model->load = new Matching_transaction_loader();
	$dependencies = new Matching_transaction_dependencies($database);
	$model->services = array('study_transaction' => new Study_transaction(), 'matching_state' => new Matching_state(), 'matching_cards' => new Matching_cards(), 'Content_model' => $dependencies, 'Flashcard_model' => $dependencies);
	return $model;
}

function matching_transaction_database($path)
{
	$database = new Matching_transaction_database();
	if ($path === 'complete')
	{
		$state = (new Matching_state())->initial();
		$state['matched'] = array(0);
		$state['move_count'] = 1;
		$database->initial['state_json'] = json_encode($state);
		$database->locked = $database->initial;
	}
	return $database;
}

function matching_transaction_run($model, $path)
{
	if ($path === 'start') { return $model->start(61, 41, str_repeat('a', 64)); }
	if ($path === 'abandon') { return $model->abandon(51, 41, 3); }
	return $model->move(51, 41, 3, str_repeat('b', 64), $path === 'complete' ? 1 : 0, in_array($path, array('complete', 'mismatch'), TRUE) ? 1 : 0);
}

function matching_transaction_names($database)
{
	return array_column($database->calls, 'name');
}

$assertions = 0;
function matching_transaction_assert($condition, $message)
{
	global $assertions;
	if ( ! $condition) { throw new RuntimeException($message); }
	$assertions++;
}

$success = array('ok' => TRUE, 'id' => 51);
$unavailable = array('ok' => FALSE, 'code' => 503, 'failure' => 'unavailable');
$uncertain = array('ok' => FALSE, 'code' => 503, 'failure' => 'uncertain');
$injected = 0;
foreach (array('start', 'match', 'mismatch', 'complete', 'abandon') as $path)
{
	$baseline = matching_transaction_database($path);
	matching_transaction_assert(matching_transaction_run(matching_transaction_model($baseline), $path) === $success, $path.': successful operation must retain ok/id result.');
	$names = matching_transaction_names($baseline);
	matching_transaction_assert($names[0] === 'active' && $names[1] === 'begin' && end($names) === 'commit' && ! in_array('rollback', $names, TRUE), 'Shared wrapper must own successful transaction boundaries.');
	matching_transaction_assert($baseline->db_debug && ! $baseline->active && $baseline->pending === array() && count($baseline->durable) >= 2, 'Success must confirm the fake commit and restore original debug.');
	foreach ($baseline->calls as $call)
	{
		matching_transaction_assert($call['debug'] === FALSE, 'Every guarded operation must disable debug display.');
		if ($call['name'] === 'dependency:access') { matching_transaction_assert($call['data'][2] === TRUE, 'Mutating access requires current locked read.'); }
		if (in_array($call['name'], array('query:lesson', 'query:user', 'query:locked', 'query:previous', 'query:existing_active', 'query:previous_move'), TRUE))
		{
			matching_transaction_assert(substr($call['data'][0], -10) === 'FOR UPDATE', 'Mutation lock and replay reads must use FOR UPDATE.');
		}
		if ($call['name'] === 'query:initial')
		{
			matching_transaction_assert($call['active'] && substr($call['data'][0], -10) !== 'FOR UPDATE' && $call['data'][1] === array(51, 41), 'Initial ownership lookup must run inside wrapper without changing lesson-before-run lock order.');
		}
		if ($call['name'] === 'query:previous') { matching_transaction_assert($call['data'][1] === array(41, str_repeat('a', 64)), 'Start replay must bind learner and key.'); }
		if ($call['name'] === 'query:existing_active') { matching_transaction_assert($call['data'][1] === array('41:61'), 'Active conflict must bind exact learner/lesson key.'); }
		if ($call['name'] === 'query:previous_move') { matching_transaction_assert($call['data'][1] === array(51, str_repeat('b', 64)), 'Move replay must bind run and key.'); }
	}
	matching_transaction_assert(array_search('query:lesson', $names, TRUE) < array_search($path === 'start' ? 'query:user' : 'query:locked', $names, TRUE), 'Lesson then user/run lock order must remain unchanged.');
	if ($path === 'start')
	{
		$created = $baseline->durable[0][1];
		$snapshot = json_decode($created['snapshot_json'], TRUE);
		matching_transaction_assert($created['status'] === 'in_progress' && $created['rules_version'] === 'matching_v1' && $created['active_key'] === '41:61' && $snapshot['lesson_version'] === 2 && $snapshot['round_size'] === 6 && count($snapshot['cards']) === 2, 'Start must preserve rules, active scope, snapshot and normal round size.');
		matching_transaction_assert(json_decode($created['state_json'], TRUE) === (new Matching_state())->initial() && $baseline->durable[1][1]['event_type'] === 'matching.started', 'Start must preserve initial state and its audit.');
	}
	elseif ($path === 'abandon')
	{
		matching_transaction_assert($baseline->durable[0][1]['status'] === 'abandoned' && $baseline->durable[0][1]['active_key'] === NULL && $baseline->durable[0][1]['revision'] === 4 && $baseline->durable[1][1]['event_type'] === 'matching.abandoned' && ! in_array('dependency:access', $names, TRUE), 'Abandon must retain revision, audit and permission after lesson access expires.');
	}
	else
	{
		$changes = $baseline->durable[0][1];
		$state = json_decode($changes['state_json'], TRUE);
		matching_transaction_assert($changes['revision'] === 4 && $state['move_count'] === ($path === 'complete' ? 2 : 1) && $state['mistake_count'] === ($path === 'mismatch' ? 1 : 0), 'Move must retain server counts and revision.');
		matching_transaction_assert($baseline->durable[1][1]['outcome'] === ($path === 'mismatch' ? 'mismatch' : 'matched') && $baseline->durable[1][1]['expected_revision'] === 3, 'Move log must retain outcome and replay revision.');
		matching_transaction_assert($state['complete'] === ($path === 'complete') && count($baseline->durable) === ($path === 'complete' ? 3 : 2), 'Only actual completion must record completed audit.');
		if ($path === 'complete')
		{
			matching_transaction_assert($changes['status'] === 'completed' && $changes['active_key'] === NULL && $baseline->durable[2][1]['event_type'] === 'matching.completed', 'Completion must clear active key and preserve final audit.');
			$set = $baseline->calls[array_search('set', $names, TRUE)]['data'];
			matching_transaction_assert($set === array('elapsed_seconds', 'GREATEST(0,TIMESTAMPDIFF(SECOND,started_at,UTC_TIMESTAMP()))', FALSE), 'Completion duration must remain server-derived.');
		}
	}
	foreach ($baseline->calls as $index => $call)
	{
		if (in_array($call['name'], array('where', 'set'), TRUE)) { continue; }
		$modes = $call['name'] === 'active' || strpos($call['name'], 'read:') === 0 ? array('throw') : array('false', 'throw');
		foreach ($modes as $mode)
		{
			$database = matching_transaction_database($path);
			$database->failure_at = $index + 1;
			$database->failure_mode = $mode;
			$database->db_debug = $mode === 'false';
			$result = matching_transaction_run(matching_transaction_model($database), $path);
			matching_transaction_assert($database->failed && $result === ($call['name'] === 'commit' ? $uncertain : $unavailable), $path.'/'.$call['name'].': injected boundary failure must not return success/domain rejection.');
			matching_transaction_assert($database->db_debug === ($mode === 'false') && $database->durable === array() && $database->pending === array(), 'Confirmed fake rollback must leave no durable/pending writes and restore debug.');
			$after = array_values(array_filter(array_slice(matching_transaction_names($database), $index + 1), static function ($name) {
				return $name !== 'status';
			}));
			matching_transaction_assert($after === (in_array($call['name'], array('active', 'begin'), TRUE) ? array() : array('rollback')), 'No read/write/dependency work may continue after the first failure.');
			$injected++;
		}
	}
}

// Explicit FALSE checks do not depend only on a driver's failed-transaction flag.
foreach (array('start', 'complete', 'abandon') as $path)
{
	$baseline = matching_transaction_database($path);
	matching_transaction_run(matching_transaction_model($baseline), $path);
	foreach ($baseline->calls as $index => $call)
	{
		if (strpos($call['name'], 'query:') !== 0 && strpos($call['name'], 'insert:') !== 0 && strpos($call['name'], 'update:') !== 0) { continue; }
		$database = matching_transaction_database($path);
		$database->failure_at = $index + 1;
		$database->poison_on_failure = FALSE;
		matching_transaction_assert(matching_transaction_run(matching_transaction_model($database), $path) === $unavailable && $database->failed, 'Read/write FALSE must fail even without a poisoned driver status.');
		matching_transaction_assert(array_slice(matching_transaction_names($database), $database->failure_at) === array('rollback'), 'Explicit FALSE must stop before any further operation.');
	}
}

foreach (array('start_replay', 'move_replay', 'abandon_replay') as $case)
{
	$database = new Matching_transaction_database();
	$path = $case === 'start_replay' ? 'start' : ($case === 'move_replay' ? 'match' : 'abandon');
	if ($case === 'start_replay') { $database->previous = array('id' => 51, 'lesson_id' => 61, 'status' => 'completed'); }
	if ($case === 'move_replay')
	{
		$database->previous_move = array('expected_revision' => 3, 'left_index' => 0, 'right_index' => 0);
		$database->locked['status'] = 'completed';
		$database->locked['revision'] = 9;
		$database->allow_access = FALSE;
	}
	if ($case === 'abandon_replay') { $database->locked['status'] = 'abandoned'; $database->locked['revision'] = 9; }
	matching_transaction_assert(matching_transaction_run(matching_transaction_model($database), $path) === $success && $database->durable === array() && end($database->calls)['name'] === 'commit', 'Accepted replay must retain original response without writes, even after final/revoked access where previously allowed.');
}

$cases = array('start_missing_lesson' => 404, 'start_missing_user' => 404, 'start_denied' => 403, 'start_archived' => 403, 'start_other_replay' => 409, 'start_active' => 409, 'start_no_deck' => 409, 'start_no_cards' => 409, 'move_missing_initial' => 404, 'move_missing_locked' => 404, 'move_other_replay' => 409, 'move_completed' => 409, 'move_rule' => 409, 'move_revision' => 409, 'move_denied' => 403, 'move_snapshot' => 409, 'move_state' => 409, 'move_cap' => 409, 'abandon_missing_initial' => 404, 'abandon_missing_locked' => 404, 'abandon_completed' => 409, 'abandon_revision' => 409);
foreach ($cases as $case => $code)
{
	$database = new Matching_transaction_database();
	$path = strpos($case, 'start_') === 0 ? 'start' : (strpos($case, 'abandon_') === 0 ? 'abandon' : 'match');
	if ($case === 'start_missing_lesson') { $database->lesson = array(); }
	if ($case === 'start_missing_user') { $database->user = array(); }
	if (strpos($case, 'missing_initial') !== FALSE) { $database->initial = array(); }
	if (strpos($case, 'missing_locked') !== FALSE) { $database->locked = array(); }
	if ($case === 'start_denied' || $case === 'move_denied') { $database->allow_access = FALSE; }
	if ($case === 'start_archived') { $database->lesson['lesson_status'] = 'archived'; }
	if ($case === 'start_other_replay') { $database->previous = array('id' => 52, 'lesson_id' => 99); }
	if ($case === 'start_active') { $database->existing_active = array('id' => 52); }
	if ($case === 'start_no_deck') { $database->deck = NULL; }
	if ($case === 'start_no_cards') { $database->cards = array(); }
	if ($case === 'move_other_replay') { $database->previous_move = array('expected_revision' => 3, 'left_index' => 0, 'right_index' => 1); }
	if ($case === 'move_completed' || $case === 'abandon_completed') { $database->locked['status'] = 'completed'; }
	if ($case === 'move_rule') { $database->locked['rules_version'] = 'unknown'; }
	if ($case === 'move_revision' || $case === 'abandon_revision') { $database->locked['revision'] = 4; }
	if ($case === 'move_snapshot') { $database->locked['snapshot_json'] = '{invalid'; }
	if ($case === 'move_state') { $database->locked['state_json'] = '{}'; }
	if ($case === 'move_cap') { $state = (new Matching_state())->initial(); $state['move_count'] = 10000; $state['mistake_count'] = 10000; $database->locked['state_json'] = json_encode($state); }
	matching_transaction_assert(matching_transaction_run(matching_transaction_model($database), $path) === array('ok' => FALSE, 'code' => $code), $case.': confirmed domain rejection must retain code.');
	matching_transaction_assert($database->durable === array() && $database->pending === array() && end($database->calls)['name'] === 'rollback', 'Domain rejection must roll back without writes.');
}

foreach (array('snapshot', 'initial', 'move') as $phase)
{
	$database = new Matching_transaction_database();
	$model = matching_transaction_model($database);
	$engine = new Matching_transaction_bad_json();
	$engine->phase = $phase;
	$model->services['matching_state'] = $engine;
	matching_transaction_assert(matching_transaction_run($model, $phase === 'move' ? 'match' : 'start') === $unavailable && $database->pending === array() && $database->durable === array() && end($database->calls)['name'] === 'rollback', 'Encoding exceptions must return unavailable and prevent partial writes.');
}
foreach (array('deck', 'cards') as $dependency)
{
	$database = new Matching_transaction_database();
	$database->swallowed_failure = $dependency;
	matching_transaction_assert(matching_transaction_run(matching_transaction_model($database), 'start') === $unavailable && $database->durable === array(), 'A downstream failed status must not become empty-deck conflict or success.');
	$index = array_search('dependency:'.$dependency, matching_transaction_names($database), TRUE);
	matching_transaction_assert(array_slice(matching_transaction_names($database), $index + 1) === array('status', 'rollback'), 'Downstream failure must stop before another read or write.');
}
foreach (array(0, -1) as $id)
{
	$database = new Matching_transaction_database();
	$database->inserted_id = $id;
	matching_transaction_assert(matching_transaction_run(matching_transaction_model($database), 'start') === $unavailable && $database->pending === array() && ! in_array('insert:st_audit_events', matching_transaction_names($database), TRUE), 'Invalid insert identity must stop and roll back before audit.');
}
foreach (array('false', 'throw') as $mode)
{
	$database = new Matching_transaction_database();
	$database->locked['revision'] = 4;
	$database->rollback_mode = $mode;
	matching_transaction_assert(matching_transaction_run(matching_transaction_model($database), 'match') === $uncertain, 'Unconfirmed rollback must replace a normal conflict with uncertainty.');
}
$database = matching_transaction_database('complete');
$database->commit_ack_lost = TRUE;
matching_transaction_assert(matching_transaction_run(matching_transaction_model($database), 'complete') === $uncertain && count($database->durable) === 3, 'Simulated commit application without acknowledgement must remain uncertain even if rollback reports success.');
$database = new Matching_transaction_database();
$database->active = TRUE;
matching_transaction_assert(matching_transaction_run(matching_transaction_model($database), 'abandon') === $unavailable && matching_transaction_names($database) === array('active') && $database->active, 'An existing outer transaction must remain untouched.');
$database = new Matching_transaction_database();
$database->lesson = array();
$database->allow_access = FALSE;
matching_transaction_assert(matching_transaction_run(matching_transaction_model($database), 'abandon') === $success, 'Owner must still be able to abandon after lesson access is gone.');
$database = new Matching_transaction_database();
$model = matching_transaction_model($database);
matching_transaction_assert($model->can_continue($database->initial) === TRUE && end($database->calls)['data'][2] === FALSE, 'Display-only can_continue must keep default nonlocking access.');

foreach (array('early_childhood' => 4, 'primary' => 6, 'secondary' => 6, 'adult' => 6) as $stage => $size)
{
	$database = new Matching_transaction_database();
	$database->user['learning_stage'] = $stage;
	matching_transaction_assert(matching_transaction_run(matching_transaction_model($database), 'start') === $success && json_decode($database->durable[0][1]['snapshot_json'], TRUE)['round_size'] === $size, 'Age round-size semantics must remain unchanged.');
}
foreach (array('', str_repeat('z', 64), array('key')) as $invalid_key)
{
	$database = new Matching_transaction_database();
	$model = matching_transaction_model($database);
	matching_transaction_assert($model->start(61, 41, $invalid_key) === array('ok' => FALSE, 'code' => 400) && $database->calls === array(), 'Invalid start key must be rejected before database access.');
	matching_transaction_assert($model->move(51, 41, 3, $invalid_key, 0, 0) === array('ok' => FALSE, 'code' => 400) && $database->calls === array(), 'Invalid move key must be rejected before database access.');
}
foreach (array(array('3', 0, 0), array(-1, 0, 0), array(3, -1, 0), array(3, 0, 100), array(3, '0', 0)) as $invalid)
{
	$database = new Matching_transaction_database();
	matching_transaction_assert(matching_transaction_model($database)->move(51, 41, $invalid[0], str_repeat('b', 64), $invalid[1], $invalid[2]) === array('ok' => FALSE, 'code' => 400) && $database->calls === array(), 'Invalid scalar move parameters must fail before database access.');
}
foreach ($matching_transaction_logs as $entry)
{
	matching_transaction_assert($entry[0] === 'error' && in_array($entry[1], array('Study transaction failed; inspect service availability.', 'Study transaction rollback could not be confirmed.'), TRUE), 'New diagnostics must not expose SQL, cards, keys or exception text.');
}
restore_error_handler();
fwrite(STDOUT, 'PASS '.$assertions.' matching transaction assertions, '.$injected." injected boundary/read/write/dependency failures; domain, replay, JSON, current-read and uncertain-result controls.\n");
fwrite(STDOUT, "LIMITATION actual model/shared wrapper and game engines with recording fakes; no real database rollback, SQL execution, network disconnect, concurrency, HTTP or driver-log sanitization proof.\n");
