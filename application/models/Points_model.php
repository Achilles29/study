<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Points_model extends CI_Model
{
	private function rows($sql, $params = array())
	{
		$q = $this->db->query($sql, $params); if ($q === FALSE) { throw new RuntimeException('Points unavailable.'); } return $q->result_array();
	}
	public function rules()
	{
		$this->load->model('Settings_model');
		$r = $this->Settings_model->get_section('poin'); return $r['values'] + array('revision' => (int) $r['revision']);
	}
	private function wallet_locked($learner)
	{
		$w = $this->rows('SELECT * FROM st_point_wallets WHERE learner_user_id=? FOR UPDATE', array($learner))[0] ?? NULL;
		if ($w) { return $w; }
		$this->db->insert('st_point_wallets', array('learner_user_id' => $learner, 'balance_points' => 0, 'updated_at' => gmdate('Y-m-d H:i:s'))); return array('balance_points' => 0);
	}
	// Called only inside Arena's transaction, after server scoring, while the learner row is locked.
	public function award_arena($run)
	{
		if ( ! $this->db->trans_active()) { throw new RuntimeException('Reward requires a transaction.'); }
		$rule = json_decode($run['reward_rule_json'] ?? 'null', TRUE);
		if ( ! $rule || ($rule['enabled'] ?? '0') !== '1' || $run['status'] !== 'completed' || ! in_array($run['game_type'], array('math','expedition','story','english','quest'), TRUE)) { return; }
		$snapshot = json_decode($run['snapshot_json'], TRUE); if ($run['game_type'] === 'english' && ($snapshot['kind'] ?? '') === 'lesson') { return; }
		if ((int) $run['question_count'] < 1 || (int) $run['correct_count'] * 100 < (int) $rule['pass_score'] * (int) $run['question_count']) { return; }
		$u = $this->rows("SELECT id FROM st_users WHERE id=? AND account_kind='child' AND status='active' FOR UPDATE", array($run['learner_user_id'])); if ( ! $u) { return; }
		$current = $this->rules(); if ($current['enabled'] !== '1') { return; }
		$key = 'arena:'.$run['pack_id']; if ($this->rows('SELECT id FROM st_point_entries WHERE learner_user_id=? AND reference_key=? FOR UPDATE', array($run['learner_user_id'], $key))) { return; }
		$day = new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta')); $since = $day->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
		$today = $this->rows('SELECT delta_points FROM st_point_entries WHERE learner_user_id=? AND delta_points>0 AND created_at>=? FOR UPDATE', array($run['learner_user_id'], $since)); $earned = array_sum(array_column($today, 'delta_points'));
		$reward = (int) $rule['reward']; if ($reward < 1 || $earned + $reward > (int) $current['daily_cap']) { return; }
		$w = $this->wallet_locked($run['learner_user_id']); if ((int) $w['balance_points'] + $reward > 1000000) { return; }
		$now = gmdate('Y-m-d H:i:s'); $this->db->where('learner_user_id', $run['learner_user_id'])->update('st_point_wallets', array('balance_points' => (int) $w['balance_points'] + $reward, 'updated_at' => $now));
		$this->db->insert('st_point_entries', array('learner_user_id' => $run['learner_user_id'], 'delta_points' => $reward, 'reference_key' => $key, 'note' => 'Pertama tuntas: '.$run['title_snapshot'], 'rule_json' => json_encode($rule + array('run_id' => (int) $run['id'])), 'created_at' => $now));
		$this->db->insert('st_audit_events', array('actor_user_id' => $run['learner_user_id'], 'event_type' => 'points.earned', 'entity_type' => 'arena_run', 'entity_id' => $run['id'], 'details_json' => json_encode(array('points' => $reward, 'rule_revision' => $rule['revision'])), 'created_at' => $now));
	}
	public function summary($learner, $page = 1)
	{
		$w = $this->rows('SELECT balance_points FROM st_point_wallets WHERE learner_user_id=?', array($learner))[0] ?? array('balance_points' => 0);
		$w['entries'] = $this->rows('SELECT delta_points,note,created_at FROM st_point_entries WHERE learner_user_id=? ORDER BY id DESC LIMIT 20 OFFSET '.(((int) $page - 1) * 20), array($learner));
		$w['redemptions'] = $this->rows('SELECT snapshot_json,starts_at,ends_at,status FROM st_point_redemptions WHERE learner_user_id=? ORDER BY id DESC LIMIT 20 OFFSET '.(((int) $page - 1) * 20), array($learner)); return $w;
	}
	public function packages()
	{
		return $this->rows("SELECT id,title,summary,point_price,point_days,revision FROM st_packages WHERE status='published' AND point_price>0 AND point_days>0 ORDER BY id DESC LIMIT 100");
	}
	public function learners($page)
	{
		return $this->rows("SELECT u.id,u.display_name,w.balance_points,w.updated_at FROM st_point_wallets w JOIN st_users u ON u.id=w.learner_user_id ORDER BY w.updated_at DESC,w.learner_user_id DESC LIMIT 20 OFFSET ".(((int) $page - 1) * 20));
	}
	public function redeem($learner, $stamp, $package, $revision, $key)
	{
		if ( ! is_string($key) || ! preg_match('/\A[a-f0-9]{64}\z/', $key) || ! is_string($revision) || ! preg_match('/\A[1-9][0-9]{0,8}\z/', $revision)) { return array('ok' => FALSE, 'code' => 400); }
		$this->load->library('study_transaction');
		return $this->study_transaction->run($this->db, function () use ($learner, $stamp, $package, $revision, $key) {
			$u = $this->rows("SELECT id,auth_version,password_hash FROM st_users WHERE id=? AND account_kind='child' AND status='active' FOR UPDATE", array($learner))[0] ?? NULL;
			if ( ! $u || ! is_string($stamp) || ! hash_equals(study_credential_stamp($u), $stamp)) { return array('ok' => FALSE, 'code' => 403); }
			$hash = hash('sha256', $package.'|'.$revision); $old = $this->rows('SELECT id,request_hash FROM st_point_redemptions WHERE learner_user_id=? AND request_key=? FOR UPDATE', array($learner, $key))[0] ?? NULL;
			if ($old) { return hash_equals($old['request_hash'], $hash) ? array('ok' => TRUE) : array('ok' => FALSE, 'code' => 409); }
			if ($this->rules()['enabled'] !== '1') { return array('ok' => FALSE, 'code' => 403); }
			$p = $this->rows("SELECT * FROM st_packages WHERE id=? AND status='published' AND point_price>0 AND point_days>0 FOR UPDATE", array($package))[0] ?? NULL;
			if ( ! $p || (int) $p['revision'] !== (int) $revision) { return array('ok' => FALSE, 'code' => 409); }
			$courses = $this->rows('SELECT pc.course_id,c.status FROM st_package_courses pc JOIN st_courses c ON c.id=pc.course_id WHERE pc.package_id=? ORDER BY pc.course_id FOR UPDATE', array($package));
			if ( ! $courses || count($courses) > 100) { return array('ok' => FALSE, 'code' => 409); } foreach ($courses as $c) { if ($c['status'] !== 'published') { return array('ok' => FALSE, 'code' => 409); } }
			$w = $this->wallet_locked($learner); if ((int) $w['balance_points'] < (int) $p['point_price']) { return array('ok' => FALSE, 'code' => 409); }
			$now = gmdate('Y-m-d H:i:s'); $last = $this->rows("SELECT ends_at FROM st_point_redemptions WHERE learner_user_id=? AND package_id=? AND status='active' AND ends_at>UTC_TIMESTAMP() ORDER BY ends_at DESC LIMIT 1 FOR UPDATE", array($learner, $package)); $start = $last ? $last[0]['ends_at'] : $now;
			$snapshot = array('title' => $p['title'], 'points' => (int) $p['point_price'], 'days' => (int) $p['point_days'], 'revision' => (int) $revision);
			$this->db->insert('st_point_redemptions', array('learner_user_id' => $learner, 'package_id' => $package, 'request_key' => $key, 'request_hash' => $hash, 'snapshot_json' => json_encode($snapshot), 'course_ids_json' => json_encode(array_map('intval', array_column($courses, 'course_id'))), 'starts_at' => $start, 'ends_at' => gmdate('Y-m-d H:i:s', strtotime($start.' UTC') + (int) $p['point_days'] * 86400), 'created_at' => $now)); $id = (int) $this->db->insert_id();
			$this->db->where('learner_user_id', $learner)->update('st_point_wallets', array('balance_points' => (int) $w['balance_points'] - (int) $p['point_price'], 'updated_at' => $now));
			$this->db->insert('st_point_entries', array('learner_user_id' => $learner, 'delta_points' => -(int) $p['point_price'], 'reference_key' => 'redeem:'.$id, 'note' => 'Ditukar: '.$p['title'], 'rule_json' => json_encode($snapshot), 'created_at' => $now));
			$this->db->insert('st_audit_events', array('actor_user_id' => $learner, 'event_type' => 'points.redeemed', 'entity_type' => 'point_redemption', 'entity_id' => $id, 'details_json' => json_encode($snapshot), 'created_at' => $now)); return array('ok' => TRUE);
		});
	}
	public function course_predicate($learner, $course)
	{
		$learner = (int) $learner; $course = $course === 'c.id' ? 'c.id' : (string) (int) $course;
		return "pr.learner_user_id={$learner} AND pr.status='active' AND pr.starts_at<=UTC_TIMESTAMP() AND pr.ends_at>UTC_TIMESTAMP() AND JSON_CONTAINS(pr.course_ids_json,CAST({$course} AS CHAR))";
	}
	public function course($learner, $course, $lock = FALSE)
	{
		return (bool) $this->rows('SELECT pr.id FROM st_point_redemptions pr WHERE '.$this->course_predicate($learner, $course).' LIMIT 1'.($lock ? ' FOR UPDATE' : ''));
	}
}
