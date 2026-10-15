<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Check_matching extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		if (PHP_SAPI !== 'cli' || ENVIRONMENT !== 'development')
		{
			show_404();
			exit(1);
		}
		$this->load->database();
		$this->load->model('Matching_run_model');
	}

	private function expect($condition, $message)
	{
		if ( ! $condition)
		{
			fwrite(STDERR, 'FAIL matching storage: '.$message."\n");
			exit(1);
		}
	}

	public function hold($lesson_id = 0, $user_id = 0)
	{
		$this->expect($this->db->database === 'db_study', 'Only local test database is allowed.');
		$fixture = $this->db->query("SELECT l.id FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id JOIN st_users u ON u.id=c.author_user_id WHERE l.id=? AND u.id=? AND l.slug LIKE 'qa-matching-run-%' AND u.username LIKE 'qa_matchingrun_%'", array((int) $lesson_id, (int) $user_id))->row_array();
		$this->expect((bool) $fixture, 'Lock barrier requires synthetic matching fixture.');
		$this->db->trans_begin();
		$this->db->query('SELECT id FROM st_lessons WHERE id=? FOR UPDATE', array((int) $lesson_id));
		$connection = $this->db->query('SELECT CONNECTION_ID() AS id')->row_array();
		fwrite(STDOUT, 'READY '.(int) $connection['id']."\n");
		fflush(STDOUT);
		stream_set_blocking(STDIN, FALSE);
		$deadline = microtime(TRUE) + 20;
		$input = '';
		while (microtime(TRUE) < $deadline)
		{
			$input .= stream_get_contents(STDIN);
			if (strpos($input, 'release') !== FALSE)
			{
				break;
			}
			usleep(50000);
		}
		$this->db->trans_rollback();
		$this->expect(strpos($input, 'release') !== FALSE, 'Barrier timed out and was rolled back.');
		fwrite(STDOUT, "RELEASED\n");
	}

	public function index($user_id = 0, $other_id = 0, $lesson_id = 0)
	{
		$this->expect($this->db->database === 'db_study', 'Only local test database is allowed.');
		$users = $this->db->where_in('id', array((int) $user_id, (int) $other_id))->like('username', 'qa_matchingrun_', 'after')->get('st_users')->result_array();
		$lesson = $this->db->query("SELECT l.id,s.course_id FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id WHERE l.id=? AND l.slug LIKE 'qa-matching-run-%'", array((int) $lesson_id))->row_array();
		$this->expect(count($users) === 2 && $lesson, 'Only exact synthetic fixture IDs are allowed.');
		$model = $this->Matching_run_model;
		$key = hash('sha256', 'first');
		$start = $model->start($lesson_id, $user_id, $key);
		$this->expect($start['ok'], 'Start failed.');
		$id = $start['id'];
		$this->expect($model->start($lesson_id, $user_id, $key)['id'] === $id && $model->start($lesson_id, $user_id, hash('sha256', 'another-tab'))['code'] === 409, 'Start retry or competing active start was mishandled.');
		$run = $model->find_owned($id, $user_id);
		$snapshot = json_decode($run['snapshot_json'], TRUE);
		$this->expect(count($snapshot['cards']) === 8 && $snapshot['round_size'] === 6, 'Snapshot or age size differs.');
		$this->expect($model->find_owned($id, $other_id) === NULL, 'Another learner read run.');
		$this->expect($model->move($id, $other_id, 0, hash('sha256', 'foreign'), 0, 0)['code'] === 404, 'Another learner changed run.');
		$first = $snapshot['rounds'][0][0];
		$second = $snapshot['rounds'][0][1];
		$future = $snapshot['rounds'][1][0];
		$this->expect($model->move($id, $user_id, 0, hash('sha256', 'future'), $future, $future)['code'] === 409, 'Future round accepted.');
		$move_key = hash('sha256', 'mismatch');
		$this->expect($model->move($id, $user_id, 0, $move_key, $first, $second)['ok'], 'Mismatch was not recorded.');
		$this->expect($model->move($id, $user_id, 0, $move_key, $first, $second)['ok'], 'Exact move retry failed.');
		$this->expect($model->move($id, $user_id, 0, $move_key, $first, $first)['code'] === 409, 'Replay key changed payload.');
		$this->expect($model->move($id, $user_id, 0, hash('sha256', 'stale'), $first, $first)['code'] === 409, 'Stale revision accepted.');
		$this->expect($this->db->where('run_id', $id)->count_all_results('st_matching_moves') === 1, 'Retry duplicated move.');
		$this->db->where('id', $lesson['course_id'])->update('st_courses', array('is_free' => 0));
		$this->expect($model->move($id, $user_id, 1, hash('sha256', 'denied'), $first, $first)['code'] === 403, 'Closed access allowed a move.');
		$this->expect((int) $model->find_owned($id, $user_id)['revision'] === 1, 'Denied move changed revision.');
		$this->db->where('id', $lesson['course_id'])->update('st_courses', array('is_free' => 1));
		$this->db->query('UPDATE st_flashcards f JOIN st_flashcard_decks d ON d.id=f.deck_id SET f.front_text=? WHERE d.lesson_id=?', array('Changed fixture source', (int) $lesson_id));
		$this->expect($model->find_owned($id, $user_id)['snapshot_json'] === $run['snapshot_json'], 'Source change altered snapshot.');
		$this->db->query('UPDATE st_matching_runs SET started_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 125 SECOND) WHERE id=?', array($id));
		$revision = 1;
		foreach ($snapshot['rounds'] as $round)
		{
			foreach ($round as $card_id)
			{
				$last_key = hash('sha256', 'match:'.$card_id);
				$this->expect($model->move($id, $user_id, $revision, $last_key, $card_id, $card_id)['ok'], 'Valid saved match failed.');
				$this->expect($model->move($id, $user_id, $revision, $last_key, $card_id, $card_id)['ok'], 'Saved match retry failed.');
				$revision++;
			}
		}
		$done = $model->find_owned($id, $user_id);
		$state = json_decode($done['state_json'], TRUE);
		$this->expect($done['status'] === 'completed' && $done['active_key'] === NULL && count($state['matched']) === 8 && $state['move_count'] === 9 && $state['mistake_count'] === 1 && (int) $done['elapsed_seconds'] >= 125, 'Stored outcome/time did not reconcile.');
		$this->expect($model->start($lesson_id, $user_id, $key)['id'] === $id, 'Start retry replaced completed history.');
		$this->expect($model->abandon($id, $user_id)['code'] === 409, 'Completed run was abandoned.');
		// Restore only fixture card texts to make a fresh valid run.
		$this->db->query("UPDATE st_flashcards f JOIN st_flashcard_decks d ON d.id=f.deck_id SET f.front_text=CONCAT('Card ',f.id) WHERE d.lesson_id=?", array((int) $lesson_id));
		$new = $model->start($lesson_id, $user_id, hash('sha256', 'fresh'));
		$this->expect($new['ok'] && $new['id'] !== $id, 'New attempt did not preserve old history.');
		$debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$duplicate = $this->db->query('INSERT INTO st_matching_runs(learner_user_id,lesson_id,title_snapshot,rules_version,snapshot_json,state_json,active_key,start_key,started_at) SELECT learner_user_id,lesson_id,title_snapshot,rules_version,snapshot_json,state_json,active_key,?,started_at FROM st_matching_runs WHERE id=?', array(hash('sha256', 'constraint'), $new['id']));
		$error = $this->db->error();
		$this->db->db_debug = $debug;
		$this->expect($duplicate === FALSE && (int) $error['code'] === 1062, 'Database allowed duplicate active key.');
		$this->db->where('id', $lesson['course_id'])->update('st_courses', array('is_free' => 0));
		$this->expect($model->abandon($new['id'], $user_id)['ok'] && $model->abandon($new['id'], $user_id)['ok'], 'Abandon/replay after expiry failed.');
		$this->expect($model->find_owned($new['id'], $user_id)['status'] === 'abandoned' && $this->db->where('run_id', $id)->count_all_results('st_matching_moves') === 9, 'Abandon deleted prior moves.');
		$this->expect($this->db->where('actor_user_id', (int) $user_id)->where('entity_type', 'matching_run')->count_all_results('st_audit_events') === 4, 'Idempotency duplicated audit.');
		fwrite(STDOUT, "PASS matching storage: owner isolation, snapshot, per-round server decisions, replay/revisions, access loss, terminal history, server elapsed time, active unique key, and abandon.\n");
	}
}
