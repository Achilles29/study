<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Arena_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->load->library(array('arena_engine', 'study_transaction'));
		$this->load->model('Settings_model');
	}

	private function rows($sql, $bindings = array())
	{
		$result = $this->db->query($sql, $bindings);
		if ($result === FALSE) { throw new RuntimeException('Arena unavailable.'); }
		return $result->result_array();
	}

	public function settings()
	{
		return $this->Settings_model->get_section('arena')['values'];
	}

	public function english_settings()
	{
		return $this->Settings_model->get_section('english')['values'];
	}

	public function quest_settings()
	{
		return $this->Settings_model->get_section('quest')['values'];
	}

	public function quest_catalog($user_id)
	{
		$packs = $this->rows("SELECT p.id,p.slug,p.title,p.enabled,JSON_UNQUOTE(JSON_EXTRACT(p.content_json,'$.season_title')) season_title,JSON_LENGTH(p.content_json,'$.items') scenes,
			COALESCE(MAX(r.status='completed'),0) completed,COALESCE(MAX(CAST(JSON_EXTRACT(r.state_json,'$.position') AS UNSIGNED)),0) visited,
			MAX(IF(r.status='completed',ROUND(r.correct_count*100/r.question_count),NULL)) best_score
			FROM st_arena_packs p LEFT JOIN st_arena_runs r ON r.pack_id=p.id AND r.learner_user_id=? AND r.content_version=p.content_version WHERE p.game_type='quest' GROUP BY p.id ORDER BY p.id", array($user_id));
		$ordered = $this->quest_settings()['ordered'] === '1'; $previous = TRUE;
		foreach ($packs as &$pack) { $pack['unlocked'] = ! $ordered || $previous; $previous = $previous && (bool) $pack['completed']; }
		unset($pack); return $packs;
	}

	public function quest_open($user_id, $pack_id)
	{
		foreach ($this->quest_catalog($user_id) as $pack) { if ((string) $pack['id'] === (string) $pack_id) { return $pack['unlocked']; } }
		return FALSE;
	}

	public function english_catalog($user_id)
	{
		$packs = $this->rows("SELECT p.id,p.slug,p.title,p.enabled,JSON_UNQUOTE(JSON_EXTRACT(p.content_json,'$.kind')) kind,JSON_EXTRACT(p.content_json,'$.chapter') chapter,JSON_UNQUOTE(JSON_EXTRACT(p.content_json,'$.chapter_title')) chapter_title,JSON_EXTRACT(p.content_json,'$.requires') requirements,
			COALESCE(MAX(r.status='completed'),0) completed,MAX(r.passed) passed,MAX(IF(r.status='completed' AND JSON_UNQUOTE(JSON_EXTRACT(r.snapshot_json,'$.kind'))<>'lesson',ROUND(r.correct_count*100/r.question_count),NULL)) best_score
			FROM st_arena_packs p LEFT JOIN st_arena_runs r ON r.pack_id=p.id AND r.learner_user_id=? AND r.content_version=p.content_version WHERE p.game_type='english' GROUP BY p.id ORDER BY p.id", array($user_id));
		$completed = array_column(array_filter($packs, static function ($pack) {
			return (bool) $pack['completed']; }), 'slug');
		foreach ($packs as &$pack) { $requires = json_decode($pack['requirements'], TRUE); $pack['unlocked'] = ! array_diff($requires, $completed); unset($pack['requirements']); }
		unset($pack); return $packs;
	}

	public function english_open($user_id, $pack_id)
	{
		foreach ($this->english_catalog($user_id) as $pack) { if ((string) $pack['id'] === (string) $pack_id) { return $pack['unlocked']; } }
		return FALSE;
	}

	public function catalog($all = FALSE)
	{
		return $this->rows('SELECT id,slug,game_type,title,learning_stage,enabled,revision,content_version FROM st_arena_packs'.($all ? '' : ' WHERE enabled=1').' ORDER BY game_type,id');
	}

	public function management_catalog()
	{
		return $this->rows("SELECT p.id,p.slug,p.game_type,p.title,p.learning_stage,p.enabled,p.revision,p.content_version,c.id master_category_id,c.name master_category,COALESCE(g.name,'Lintas kelas / berbasis level') grade_name,s.name subject_name FROM st_arena_packs p LEFT JOIN st_arena_pack_categories m ON m.pack_id=p.id LEFT JOIN st_arena_categories c ON c.id=m.arena_category_id LEFT JOIN st_learning_grades g ON g.id=c.grade_id LEFT JOIN st_categories s ON s.id=c.category_id ORDER BY p.game_type,p.id");
	}

	public function pack($slug)
	{
		return $this->rows('SELECT * FROM st_arena_packs WHERE slug=?', array($slug))[0] ?? NULL;
	}

	public function owned($id, $user_id)
	{
		return $this->rows('SELECT * FROM st_arena_runs WHERE id=? AND learner_user_id=?', array($id, $user_id))[0] ?? NULL;
	}

	public function progress($user_id)
	{
		return $this->rows("SELECT pack_id,COUNT(*) attempts,SUM(status='completed') completed,MAX(IF(status='completed' AND game_type NOT IN ('memory','flashcard'),ROUND(correct_count*100/question_count),NULL)) best_score,MAX(updated_at) last_activity FROM st_arena_runs WHERE learner_user_id=? GROUP BY pack_id", array($user_id));
	}

	public function card_summary($user_id)
	{
		return $this->rows("SELECT p.id pack_id,JSON_LENGTH(p.content_json,'$.items') total_cards,COUNT(c.id) reviewed_cards,COALESCE(SUM(c.status='known'),0) known_cards
			FROM st_arena_packs p LEFT JOIN st_arena_card_progress c ON c.pack_id=p.id AND c.learner_user_id=? AND c.content_version=p.content_version
			WHERE p.game_type='flashcard' GROUP BY p.id", array($user_id));
	}

	public function expedition_levels($user_id)
	{
		$levels = $this->rows("SELECT p.id,p.slug,p.title,p.learning_stage,p.enabled,CAST(JSON_EXTRACT(p.content_json,'$.level') AS UNSIGNED) level_number,
			COALESCE(m.passed,0) passed,COALESCE(m.attempts,0) attempts,m.best_score
			FROM st_arena_packs p LEFT JOIN (SELECT pack_id,MAX(COALESCE(passed,0)) passed,COUNT(*) attempts,MAX(IF(status='completed',ROUND(correct_count*100/question_count),NULL)) best_score FROM st_arena_runs WHERE learner_user_id=? GROUP BY pack_id) m ON m.pack_id=p.id
			WHERE p.game_type='expedition' ORDER BY level_number", array($user_id));
		$previous_passed = TRUE;
		foreach ($levels as &$level) { $level['unlocked'] = $previous_passed; $previous_passed = $previous_passed && (bool) $level['passed']; }
		unset($level); return $levels;
	}

	public function expedition_open($user_id, $pack_id)
	{
		foreach ($this->expedition_levels($user_id) as $level) { if ((string) $level['id'] === (string) $pack_id) { return $level['unlocked']; } }
		return FALSE;
	}

	public function history($user_id, $page, $parent_id = NULL)
	{
		$scope = ' WHERE learner_user_id=?'; $bindings = array($user_id);
		if ($parent_id !== NULL)
		{
			$scope .= " AND EXISTS (SELECT 1 FROM st_family_links l JOIN st_users c ON c.id=l.child_user_id AND c.account_kind='child' AND c.status='active' JOIN st_users p ON p.id=l.parent_user_id AND p.account_kind='parent' AND p.status='active' WHERE l.child_user_id=learner_user_id AND l.parent_user_id=? AND l.status='active')";
			$bindings[] = $parent_id;
		}
		$total = (int) $this->rows('SELECT COUNT(*) total FROM st_arena_runs'.$scope, $bindings)[0]['total'];
		$page_count = max(1, (int) ceil($total / 20)); $page = min($page_count, max(1, (int) $page));
		$runs = $this->rows('SELECT id,pack_id,game_type,title_snapshot,status,correct_count,question_count,moves,started_at,updated_at,finished_at,deadline_at,passed,JSON_UNQUOTE(JSON_EXTRACT(snapshot_json,\'$.kind\')) activity_kind,JSON_EXTRACT(state_json,\'$.position\') position FROM st_arena_runs'.$scope.' ORDER BY id DESC LIMIT 20 OFFSET '.(($page - 1) * 20), $bindings);
		return compact('runs', 'total', 'page', 'page_count');
	}

	public function running($user_id)
	{
		return $this->rows("SELECT id,title_snapshot FROM st_arena_runs WHERE learner_user_id=? AND status='running' ORDER BY id DESC LIMIT 1", array($user_id))[0] ?? NULL;
	}

	private function lock_actor($user_id, $admin = FALSE)
	{
		$user = $this->rows("SELECT id,learning_stage FROM st_users WHERE id=? AND status='active' FOR UPDATE", array($user_id))[0] ?? NULL;
		if ($user && $admin && ! $this->rows("SELECT ur.user_id FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.role_code='system_admin' FOR UPDATE", array($user_id))) { return NULL; }
		return $user;
	}

	public function start($user_id, $slug, $key, $mode)
	{
		if ( ! is_string($key) || ! preg_match('/\A[a-f0-9]{64}\z/', $key) || ! in_array($mode, array('calm', 'timed'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($user_id, $slug, $key, $mode) {
			$user = $this->lock_actor($user_id); if ( ! $user) { return array('ok' => FALSE, 'code' => 403); }
			$existing = $this->rows('SELECT id FROM st_arena_runs WHERE learner_user_id=? AND start_key=?', array($user_id, $key));
			if ($existing) { return array('ok' => TRUE, 'id' => $existing[0]['id']); }
			$settings = $this->settings(); $pack = $this->pack($slug);
			if ( ! $pack || ! $pack['enabled'] || $settings['enabled'] !== '1') { return array('ok' => FALSE, 'code' => 403); }
			if ($pack['game_type'] === 'expedition' && ! $this->expedition_open($user_id, $pack['id'])) { return array('ok' => FALSE, 'code' => 403); }
			if ($pack['game_type'] === 'english')
			{
				$english = $this->english_settings();
				if ($english['enabled'] !== '1' || ! $this->english_open($user_id, $pack['id'])) { return array('ok' => FALSE, 'code' => 403); }
				$settings = array_merge($settings, $english);
			}
			if ($pack['game_type'] === 'quest')
			{
				$quest = $this->quest_settings();
				if ($quest['enabled'] !== '1' || ! $this->quest_open($user_id, $pack['id'])) { return array('ok' => FALSE, 'code' => 403); }
				$settings = array_merge($settings, $quest);
			}
			if ($mode === 'timed' && ($pack['game_type'] !== 'math' || $settings['timer'] !== '1' || $user['learning_stage'] === 'early_childhood')) { return array('ok' => FALSE, 'code' => 400); }
			$running = $this->running($user_id);
			if ($running) { return array('ok' => TRUE, 'id' => $running['id'], 'resumed' => TRUE); }
			if ($user['learning_stage'] === 'early_childhood') { $settings['pairs'] = '4'; $settings['words'] = '5'; $settings['cards'] = '5'; }
			$content = json_decode($pack['content_json'], TRUE, 512, JSON_THROW_ON_ERROR);
			$game = $this->arena_engine->create($content, $settings); $now = gmdate('Y-m-d H:i:s');
			if ( ! $this->db->insert('st_arena_runs', array('learner_user_id' => $user_id, 'pack_id' => $pack['id'], 'start_key' => $key, 'game_type' => $pack['game_type'], 'title_snapshot' => $pack['title'], 'content_version' => $pack['content_version'], 'snapshot_json' => json_encode($game['snapshot'], JSON_UNESCAPED_UNICODE), 'state_json' => json_encode($game['state']), 'question_count' => $game['count'], 'started_at' => $now, 'updated_at' => $now, 'deadline_at' => $mode === 'timed' ? gmdate('Y-m-d H:i:s', time() + (int) $content['duration']) : NULL))) { throw new RuntimeException('Arena start failed.'); }
			$run_id = $this->db->insert_id();
			$this->load->model('Points_model'); $reward_rule = $this->Points_model->rules();
			if ($reward_rule['enabled'] === '1' && ! $this->db->where('id', $run_id)->update('st_arena_runs', array('reward_rule_json' => json_encode($reward_rule)))) { throw new RuntimeException('Reward rules not saved.'); }
			return array('ok' => TRUE, 'id' => $run_id);
		});
	}

	public function act($user_id, $id, $revision, $action, $value)
	{
		if ( ! is_string($revision) || ! preg_match('/\A[1-9][0-9]{0,9}\z/', $revision) || ! in_array($action, array('answer', 'flip', 'stop', 'finish', 'reveal', 'rate', 'next'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($user_id, $id, $revision, $action, $value) {
			if ( ! $this->lock_actor($user_id)) { return array('ok' => FALSE, 'code' => 403); }
			$run = $this->rows('SELECT * FROM st_arena_runs WHERE id=? AND learner_user_id=? FOR UPDATE', array($id, $user_id))[0] ?? NULL;
			if ( ! $run) { return array('ok' => FALSE, 'code' => 404); }
			if ($run['status'] !== 'running') { return array('ok' => TRUE); }
			if ((string) $run['revision'] !== $revision) { return array('ok' => FALSE, 'code' => 409); }
			if ($action !== 'stop')
			{
				$settings = $this->settings(); $pack = $this->rows('SELECT enabled FROM st_arena_packs WHERE id=?', array($run['pack_id']));
				if ($settings['enabled'] !== '1' || empty($pack[0]['enabled'])) { return array('ok' => FALSE, 'code' => 403); }
				if ($run['game_type'] === 'english' && $this->english_settings()['enabled'] !== '1') { return array('ok' => FALSE, 'code' => 403); }
				if ($run['game_type'] === 'quest' && $this->quest_settings()['enabled'] !== '1') { return array('ok' => FALSE, 'code' => 403); }
			}
			try { $updates = $this->arena_engine->advance($run, $action, $value, time()); }
			catch (InvalidArgumentException $error) { return array('ok' => FALSE, 'code' => 400); }
			if ( ! $this->db->where('id', $id)->update('st_arena_runs', $updates)) { throw new RuntimeException('Arena save failed.'); }
			if (($updates['status'] ?? '') === 'completed') { $this->load->model('Points_model'); $this->Points_model->award_arena(array_replace($run, $updates)); }
			if ($run['game_type'] === 'flashcard' && $action === 'rate')
			{
				$state = json_decode($run['state_json'], TRUE); $snapshot = json_decode($run['snapshot_json'], TRUE); $card = $snapshot['items'][$state['position']];
				if ( ! isset($card['key']) || ! preg_match('/\A[a-f0-9]{64}\z/', $card['key'])) { throw new RuntimeException('Invalid card identity.'); }
				if ($this->db->query('INSERT INTO st_arena_card_progress (learner_user_id,pack_id,content_version,card_key,status,last_run_id,updated_at) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),review_count=review_count+1,last_run_id=VALUES(last_run_id),updated_at=VALUES(updated_at)', array($user_id, $run['pack_id'], $run['content_version'], $card['key'], $value, $id, $updates['updated_at'])) === FALSE) { throw new RuntimeException('Card progress not saved.'); }
			}
			return array('ok' => TRUE);
		});
	}

	public function toggle_pack($actor_id, $id, $revision, $enabled)
	{
		if ( ! in_array($enabled, array('0', '1'), TRUE) || ! is_string($revision) || ! preg_match('/\A[1-9][0-9]{0,9}\z/', $revision)) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($actor_id, $id, $revision, $enabled) {
			if ( ! $this->lock_actor($actor_id, TRUE)) { return array('ok' => FALSE, 'code' => 403); }
			$pack = $this->rows('SELECT * FROM st_arena_packs WHERE id=? FOR UPDATE', array($id))[0] ?? NULL;
			if ( ! $pack) { return array('ok' => FALSE, 'code' => 404); }
			if ((string) $pack['revision'] !== $revision) { return array('ok' => FALSE, 'code' => 409); }
			$now = gmdate('Y-m-d H:i:s');
			if ( ! $this->db->where('id', $id)->update('st_arena_packs', array('enabled' => (int) $enabled, 'revision' => (int) $revision + 1, 'updated_at' => $now))) { throw new RuntimeException('Pack save failed.'); }
			if ( ! $this->db->insert('st_audit_events', array('actor_user_id' => $actor_id, 'event_type' => 'arena.availability_changed', 'entity_type' => 'arena_pack', 'entity_id' => $id, 'details_json' => json_encode(array('before' => (int) $pack['enabled'], 'after' => (int) $enabled, 'revision' => (int) $revision + 1)), 'created_at' => $now))) { throw new RuntimeException('Pack audit failed.'); }
			return array('ok' => TRUE);
		});
	}
}
