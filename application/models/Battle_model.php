<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Battle_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct(); $this->load->library(array('battle_rules', 'study_transaction')); $this->load->model('Settings_model');
	}

	private function rows($sql, $bindings = array())
	{
		$query = $this->db->query($sql, $bindings); if ($query === FALSE) { throw new RuntimeException('Battle unavailable.'); } return $query->result_array();
	}

	public function settings()
	{
		return $this->Settings_model->get_section('battle')['values'];
	}

	public function children_for($actor)
	{
		return $this->rows("SELECT c.id,c.display_name FROM st_family_links f JOIN st_users c ON c.id=f.child_user_id AND c.account_kind='child' AND c.status='active' WHERE f.parent_user_id=? AND f.status='active' ORDER BY c.id", array($actor));
	}

	private function actor($id, $lock = FALSE)
	{
		return $this->rows("SELECT id,account_kind,email_verified_at FROM st_users WHERE id=? AND status='active'".($lock ? ' FOR UPDATE' : ''), array($id))[0] ?? NULL;
	}

	private function guardian($parent, $child, $verified = FALSE)
	{
		return (bool) $this->rows("SELECT l.id FROM st_family_links l JOIN st_users p ON p.id=l.parent_user_id AND p.account_kind='parent' AND p.status='active' JOIN st_users c ON c.id=l.child_user_id AND c.account_kind='child' AND c.status='active' WHERE l.parent_user_id=? AND l.child_user_id=? AND l.status='active'".($verified ? ' AND p.email_verified_at IS NOT NULL' : ''), array($parent, $child));
	}

	private function host($actor, $room)
	{
		return (string) $actor === (string) $room['creator_user_id'] || $this->guardian($actor, $room['creator_user_id']);
	}

	private function audit($actor, $room, $event, $detail = array())
	{
		if ( ! $this->db->insert('st_audit_events', array('actor_user_id' => $actor, 'event_type' => 'battle.'.$event, 'entity_type' => 'battle_room', 'entity_id' => $room, 'details_json' => json_encode($detail), 'created_at' => gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Battle audit failed.'); }
	}

	public function listing($actor, $page)
	{
		$scope = " FROM st_battle_rooms r WHERE r.creator_user_id=? OR EXISTS(SELECT 1 FROM st_family_links f JOIN st_users c ON c.id=f.child_user_id AND c.status='active' WHERE f.child_user_id=r.creator_user_id AND f.parent_user_id=? AND f.status='active') OR EXISTS(SELECT 1 FROM st_battle_entries e WHERE e.room_id=r.id AND (e.learner_user_id=? OR (e.guardian_user_id=? AND EXISTS(SELECT 1 FROM st_family_links f WHERE f.parent_user_id=e.guardian_user_id AND f.child_user_id=e.learner_user_id AND f.status='active'))))";
		$bindings = array($actor, $actor, $actor, $actor); $total = (int) $this->rows('SELECT COUNT(*) n'.$scope, $bindings)[0]['n']; $page_count = max(1, (int) ceil($total / 20)); $page = min(max(1, $page), $page_count);
		$rooms = $this->rows('SELECT r.id,r.title,r.status,r.starts_at,r.ends_at'.$scope.' ORDER BY r.id DESC LIMIT 20 OFFSET '.(($page - 1) * 20), $bindings); return compact('rooms', 'total', 'page', 'page_count');
	}

	public function access($actor, $id)
	{
		$room = $this->rows('SELECT * FROM st_battle_rooms WHERE id=?', array($id))[0] ?? NULL; if ( ! $room) { return NULL; }
		$host = $this->host($actor, $room); $entry = $this->rows('SELECT * FROM st_battle_entries WHERE room_id=? AND learner_user_id=?', array($id, $actor))[0] ?? NULL;
		$children = $this->rows("SELECT e.id,e.alias,e.learner_user_id,e.status,e.revoked_at,e.correct_count,e.question_count FROM st_battle_entries e JOIN st_family_links f ON f.parent_user_id=e.guardian_user_id AND f.child_user_id=e.learner_user_id AND f.status='active' JOIN st_users c ON c.id=e.learner_user_id AND c.status='active' WHERE e.room_id=? AND e.guardian_user_id=?", array($id, $actor));
		if ( ! $host && ! $entry && ! $children) { return NULL; }
		if ($entry && $entry['guardian_user_id'] && ! $this->guardian($entry['guardian_user_id'], $actor, TRUE)) { return NULL; }
		return compact('room', 'host', 'entry', 'children');
	}

	public function create($actor, $input, $create_key)
	{
		$mode = $input['play_mode'] ?? 'async'; if ( ! in_array($mode, array('async','live'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		if ( ! is_string($create_key) || ! preg_match('/\A[a-f0-9]{64}\z/', $create_key)) { return array('ok' => FALSE, 'code' => 400); }
		try { $details = $this->battle_rules->details($input); $title = $details['title']; $start = $details['starts_at']; $end = $details['ends_at']; }
		catch (InvalidArgumentException $error) { return array('ok' => FALSE, 'code' => 400); }
		if ($end <= $start || strtotime($end.' UTC') <= time() || strtotime($end.' UTC') > time() + 30 * 86400) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($actor, $title, $start, $end, $create_key, $mode) {
			if ( ! $this->actor($actor, TRUE)) { return array('ok' => FALSE, 'code' => 403); } $settings = $this->settings();
			$existing = $this->rows('SELECT id FROM st_battle_rooms WHERE creator_user_id=? AND create_key=?', array($actor, $create_key)); if ($existing) { return array('ok' => TRUE, 'id' => $existing[0]['id']); }
			if ($settings['enabled'] !== '1' || (int) $this->rows("SELECT COUNT(*) n FROM st_battle_rooms WHERE creator_user_id=? AND (status='draft' OR (status='published' AND ends_at>UTC_TIMESTAMP()))", array($actor))[0]['n'] >= (int) $settings['rooms']) { return array('ok' => FALSE, 'code' => 403); }
			$now = gmdate('Y-m-d H:i:s');
			if ($mode === 'live' && $settings['live_enabled'] !== '1') { return array('ok' => FALSE, 'code' => 403); }
			if ( ! $this->db->insert('st_battle_rooms', array('creator_user_id' => $actor, 'create_key' => $create_key, 'title' => $title, 'invite_code' => bin2hex(random_bytes(8)), 'starts_at' => $start, 'ends_at' => $end, 'capacity' => (int) $settings['capacity'], 'draft_json' => '[]', 'created_at' => $now, 'updated_at' => $now))) { throw new RuntimeException('Battle draft not saved.'); }
			$id = $this->db->insert_id(); $this->db->where('id', $id)->update('st_battle_rooms', array('play_mode' => $mode, 'question_seconds' => (int) $settings['live_seconds'])); $this->audit($actor, $id, 'created'); return array('ok' => TRUE, 'id' => $id);
		});
	}

	public function edit($actor, $id, $revision, $action, $input)
	{
		if ( ! is_string($revision) || ! ctype_digit($revision) || ! in_array($action, array('details', 'question', 'remove', 'publish', 'cancel', 'archive'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		try { $question = $action === 'question' ? $this->battle_rules->question($input) : NULL; $reason = $action === 'cancel' ? $this->battle_rules->text($input['reason'] ?? NULL, 300, 5) : ''; $details = $action === 'details' ? $this->battle_rules->details($input) : array(); }
		catch (InvalidArgumentException $error) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($actor, $id, $revision, $action, $input, $question, $reason, $details) {
			$user = $this->actor($actor, TRUE); if ( ! $user) { return array('ok' => FALSE, 'code' => 403); }
			$room = $this->rows('SELECT * FROM st_battle_rooms WHERE id=? FOR UPDATE', array($id))[0] ?? NULL;
			if ( ! $room || ! $this->host($actor, $room)) { return array('ok' => FALSE, 'code' => 404); }
			if ((string) $room['revision'] !== $revision) { return array('ok' => FALSE, 'code' => 409); }
			$draft = json_decode($room['draft_json'], TRUE, 512, JSON_THROW_ON_ERROR); $update = array('revision' => (int) $revision + 1, 'updated_at' => gmdate('Y-m-d H:i:s')); $settings = $this->settings();
			if ($action === 'cancel') { if ( ! in_array($room['status'], array('draft', 'published'), TRUE)) { return array('ok' => FALSE, 'code' => 409); } $update['status'] = 'cancelled'; $update['cancelled_at'] = gmdate('Y-m-d H:i:s'); }
			elseif ($action === 'archive') { if ($room['status'] !== 'cancelled' && ! ($room['status'] === 'published' && strtotime($room['ends_at'].' UTC') <= time())) { return array('ok' => FALSE, 'code' => 403); } if ($room['play_mode'] === 'live' && $room['status'] === 'published') { $this->db->query("UPDATE st_battle_entries SET status='completed',finished_at=?,revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE room_id=? AND status<>'completed' AND revoked_at IS NULL", array($room['ends_at'], $id)); } $update['status'] = 'archived'; }
			else
			{
				if ($room['status'] !== 'draft' || $settings['enabled'] !== '1') { return array('ok' => FALSE, 'code' => 403); }
				if ($action === 'details') { $update += $details; }
				elseif ($action === 'question') { if (count($draft) >= (int) $settings['questions']) { return array('ok' => FALSE, 'code' => 400); } $draft[] = $question; $update['draft_json'] = json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
				elseif ($action === 'remove') { $index = $input['index'] ?? NULL; if ( ! is_string($index) || ! ctype_digit($index) || ! isset($draft[(int) $index])) { return array('ok' => FALSE, 'code' => 400); } array_splice($draft, (int) $index, 1); $update['draft_json'] = json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
				else
				{
					if ($user['account_kind'] === 'child' || ! $user['email_verified_at'] || ! $draft || count($draft) > (int) $settings['questions'] || strtotime($room['ends_at'].' UTC') <= time() || ($input['confirm'] ?? '') !== '1') { return array('ok' => FALSE, 'code' => 403); }
					$update['status'] = 'published'; $update['publisher_user_id'] = $actor; $update['snapshot_json'] = $room['draft_json'];
				}
			}
			if ( ! $this->db->where('id', $id)->update('st_battle_rooms', $update)) { throw new RuntimeException('Battle update failed.'); }
			$this->audit($actor, $id, $action, array('revision' => $update['revision'], 'reason' => $reason)); return array('ok' => TRUE, 'id' => $id);
		});
	}

	public function join($actor, $code, $child, $consent, $public_id = NULL)
	{
		if (($public_id === NULL && ( ! is_string($code) || ! preg_match('/\A[a-f0-9]{16}\z/i', $code))) || ($public_id !== NULL && ( ! is_string($public_id) || ! ctype_digit($public_id))) || ! is_string($child) || ($child !== '' && ! ctype_digit($child))) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($actor, $code, $child, $consent, $public_id) {
			$user = $this->actor($actor, TRUE); if ( ! $user || $user['account_kind'] === 'child' || $this->settings()['enabled'] !== '1') { return array('ok' => FALSE, 'code' => 403); }
			$learner = $child === '' ? $actor : $child;
			if ($child !== '' && ($consent !== '1' || ! $this->guardian($actor, $child, TRUE))) { return array('ok' => FALSE, 'code' => 403); }
			$room = $public_id === NULL ? ($this->rows('SELECT * FROM st_battle_rooms WHERE invite_code=? FOR UPDATE', array(strtolower($code)))[0] ?? NULL) : ($this->rows('SELECT * FROM st_battle_rooms WHERE id=? FOR UPDATE', array($public_id))[0] ?? NULL);
			if ( ! $room || $room['status'] !== 'published' || strtotime($room['ends_at'].' UTC') <= time()) { return array('ok' => FALSE, 'code' => 404); }
			if ($public_id !== NULL && ($room['listing_status'] !== 'approved' || ! $this->publisher_ready($room))) { return array('ok' => FALSE, 'code' => 404); }
			if ($this->host($actor, $room)) { return array('ok' => FALSE, 'code' => 403); }
			if (in_array((string) $learner, array((string) $room['creator_user_id'], (string) $room['publisher_user_id']), TRUE)) { return array('ok' => FALSE, 'code' => 403); }
			$existing = $this->rows('SELECT id,revoked_at FROM st_battle_entries WHERE room_id=? AND learner_user_id=? FOR UPDATE', array($room['id'], $learner));
			if ($existing) { return $existing[0]['revoked_at'] ? array('ok' => FALSE, 'code' => 403) : array('ok' => TRUE, 'id' => $room['id']); }
			if ($room['play_mode'] === 'live' && $room['live_started_at'] !== NULL) { return array('ok' => FALSE, 'code' => 409); }
			// Locking reads see the latest committed entries even under REPEATABLE READ.
			$count = count($this->rows('SELECT id FROM st_battle_entries WHERE room_id=? FOR UPDATE', array($room['id']))); if ($count >= (int) $room['capacity']) { return array('ok' => FALSE, 'code' => 403); }
			$now = gmdate('Y-m-d H:i:s');
			if ( ! $this->db->insert('st_battle_entries', array('room_id' => $room['id'], 'learner_user_id' => $learner, 'guardian_user_id' => $child !== '' ? $actor : NULL, 'alias' => 'Pemain-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT), 'consent_version' => $child !== '' ? 'battle-private-v1' : NULL, 'consented_at' => $child !== '' ? $now : NULL, 'state_json' => '{"position":0,"answers":[]}', 'question_count' => count(json_decode($room['snapshot_json'], TRUE)), 'created_at' => $now, 'updated_at' => $now))) { throw new RuntimeException('Battle join failed.'); }
			$this->audit($actor, $room['id'], 'joined', array('child_entry' => $child !== '', 'consent_version' => $child !== '' ? 'battle-private-v1' : NULL)); return array('ok' => TRUE, 'id' => $room['id']);
		});
	}

	public function answer($actor, $id, $revision, $action, $value)
	{
		if ( ! is_string($revision) || ! ctype_digit($revision) || ! in_array($action, array('answer', 'finish'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($actor, $id, $revision, $action, $value) {
			if ( ! $this->actor($actor, TRUE)) { return array('ok' => FALSE, 'code' => 403); }
			$room = $this->rows('SELECT * FROM st_battle_rooms WHERE id=? FOR UPDATE', array($id))[0] ?? NULL; $entry = $this->rows('SELECT * FROM st_battle_entries WHERE room_id=? AND learner_user_id=? FOR UPDATE', array($id, $actor))[0] ?? NULL;
			if ( ! $room || ! $entry) { return array('ok' => FALSE, 'code' => 404); }
			if ($room['status'] !== 'published' || $entry['revoked_at'] || ($entry['guardian_user_id'] && ! $this->guardian($entry['guardian_user_id'], $actor, TRUE))) { return array('ok' => FALSE, 'code' => 403); }
			if ($entry['status'] === 'completed') { return array('ok' => TRUE); }
			if ((string) $entry['revision'] !== $revision) { return array('ok' => FALSE, 'code' => 409); }
			$settings = $this->settings(); if (($settings['enabled'] !== '1' || ($room['play_mode'] === 'live' && $settings['live_enabled'] !== '1')) && time() < strtotime($room['ends_at'].' UTC')) { return array('ok' => FALSE, 'code' => 403); }
			try { $updates = $this->battle_rules->advance($room, $entry, $action, $value, time()); } catch (InvalidArgumentException $error) { return array('ok' => FALSE, 'code' => 400); }
			if ( ! $this->db->where('id', $entry['id'])->update('st_battle_entries', $updates)) { throw new RuntimeException('Battle answer not saved.'); } return array('ok' => TRUE);
		});
	}

	public function participants($room_id)
	{
		return $this->rows('SELECT alias,status,revoked_at,correct_count,question_count,finished_at FROM st_battle_entries WHERE room_id=? ORDER BY id LIMIT 50', array($room_id));
	}

	public function start_live($actor, $id, $revision)
	{
		if ( ! is_string($revision) || ! ctype_digit($revision)) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($actor, $id, $revision) {
			$u = $this->actor($actor, TRUE); $room = $this->rows('SELECT * FROM st_battle_rooms WHERE id=? FOR UPDATE', array($id))[0] ?? NULL;
			if ( ! $u || $u['account_kind'] === 'child' || ! $u['email_verified_at'] || ! $room || ! $this->host($actor, $room)) { return array('ok' => FALSE, 'code' => 403); }
			if ($room['status'] !== 'published' || $room['play_mode'] !== 'live') { return array('ok' => FALSE, 'code' => 409); }
			if ($room['live_started_at']) { return array('ok' => TRUE); }
			$s = $this->settings(); $now = time(); $count = count(json_decode($room['snapshot_json'], TRUE)); $start = $now + 5; $end = $start + $count * (int) $room['question_seconds'];
			if ((string) $room['revision'] !== $revision || $s['enabled'] !== '1' || $s['live_enabled'] !== '1' || $now < strtotime($room['starts_at'].' UTC') || $end > strtotime($room['ends_at'].' UTC') || ! $count || ! $this->rows('SELECT id FROM st_battle_entries WHERE room_id=? AND revoked_at IS NULL LIMIT 1 FOR UPDATE', array($id))) { return array('ok' => FALSE, 'code' => 409); }
			$this->db->where('id', $id)->update('st_battle_rooms', array('live_started_at' => gmdate('Y-m-d H:i:s', $start), 'ends_at' => gmdate('Y-m-d H:i:s', $end), 'revision' => (int) $room['revision'] + 1, 'updated_at' => gmdate('Y-m-d H:i:s')));
			$this->audit($actor, $id, 'live_started', array('question_seconds' => (int) $room['question_seconds'], 'questions' => $count)); return array('ok' => TRUE);
		});
	}

	public function revoke($actor, $id, $entry_id)
	{
		return $this->study_transaction->run($this->db, function () use ($actor, $id, $entry_id) {
			if ( ! $this->actor($actor, TRUE)) { return array('ok' => FALSE, 'code' => 403); }
			$this->rows('SELECT id FROM st_battle_rooms WHERE id=? FOR UPDATE', array($id));
			$entry = $this->rows('SELECT * FROM st_battle_entries WHERE id=? AND room_id=? AND guardian_user_id=? FOR UPDATE', array($entry_id, $id, $actor))[0] ?? NULL;
			if ( ! $entry || ! $this->guardian($actor, $entry['learner_user_id'])) { return array('ok' => FALSE, 'code' => 404); }
			if ($entry['revoked_at']) { return array('ok' => TRUE); }
			if ( ! $this->db->where('id', $entry['id'])->update('st_battle_entries', array('revoked_at' => gmdate('Y-m-d H:i:s'), 'revision' => (int) $entry['revision'] + 1))) { throw new RuntimeException('Consent revocation failed.'); }
			$this->audit($actor, $id, 'consent_revoked', array('entry' => $entry['id'])); return array('ok' => TRUE);
		});
	}

	public function report($actor, $id, $reason)
	{
		try { $reason = $this->battle_rules->text($reason, 500, 5); } catch (InvalidArgumentException $error) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($actor, $id, $reason) {
			if ( ! $this->actor($actor, TRUE) || ! $this->access($actor, $id)) { return array('ok' => FALSE, 'code' => 404); }
			if ($this->rows("SELECT id FROM st_battle_reports WHERE room_id=? AND reporter_user_id=? AND status='open'", array($id, $actor))) { return array('ok' => TRUE); }
			if ( ! $this->db->insert('st_battle_reports', array('room_id' => $id, 'reporter_user_id' => $actor, 'reason' => $reason, 'created_at' => gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Report not saved.'); }
			$this->audit($actor, $id, 'reported'); return array('ok' => TRUE);
		});
	}

	public function reports($page)
	{
		$total = (int) $this->rows("SELECT COUNT(*) n FROM st_battle_reports WHERE status='open'")[0]['n']; $page_count = max(1, (int) ceil($total / 20)); $page = min(max(1, $page), $page_count);
		$reports = $this->rows("SELECT p.id,p.room_id,p.reason,p.created_at,r.title,r.status FROM st_battle_reports p JOIN st_battle_rooms r ON r.id=p.room_id WHERE p.status='open' ORDER BY p.id DESC LIMIT 20 OFFSET ".(($page - 1) * 20)); return compact('reports', 'total', 'page', 'page_count');
	}

	private function publisher_ready($room)
	{
		$publisher = $this->actor($room['publisher_user_id']); $creator = $this->actor($room['creator_user_id']);
		return $publisher && $creator && $publisher['account_kind'] !== 'child' && $publisher['email_verified_at'] && ((string) $room['publisher_user_id'] === (string) $room['creator_user_id'] || $this->guardian($room['publisher_user_id'], $room['creator_user_id'], TRUE));
	}
	private function directory_scope()
	{
		return " FROM st_battle_rooms r JOIN st_users p ON p.id=r.publisher_user_id AND p.status='active' AND p.account_kind<>'child' AND p.email_verified_at IS NOT NULL JOIN st_users c ON c.id=r.creator_user_id AND c.status='active' WHERE r.status='published' AND r.listing_status='approved' AND r.ends_at>UTC_TIMESTAMP() AND (p.id=c.id OR (p.account_kind='parent' AND c.account_kind='child' AND EXISTS(SELECT 1 FROM st_family_links f WHERE f.parent_user_id=p.id AND f.child_user_id=c.id AND f.status='active')))";
	}
	public function directory($page, $age = '', $id = NULL)
	{
		$scope = $this->directory_scope(); $bindings = array();
		if ($age !== '') { $scope .= ' AND r.public_age=?'; $bindings[] = $age; }
		if ($id !== NULL) { $scope .= ' AND r.id=?'; $bindings[] = $id; }
		$total = (int) $this->rows('SELECT COUNT(*) n'.$scope, $bindings)[0]['n']; $page_count = max(1, (int) ceil($total / 12)); $page = min(max(1, $page), $page_count);
		$rooms = $this->rows('SELECT r.id,r.title,r.public_summary,r.public_age,r.starts_at,r.ends_at,r.capacity,JSON_LENGTH(r.snapshot_json) question_count'.$scope.' ORDER BY r.starts_at,r.id LIMIT 12 OFFSET '.(($page - 1) * 12), $bindings);
		return compact('rooms', 'total', 'page', 'page_count', 'age');
	}
	public function request_listing($actor, $id, $revision, $input)
	{
		$action = $input['action'] ?? NULL;
		if ( ! is_string($revision) || ! ctype_digit($revision) || ! in_array($action, array('list_request', 'list_withdraw'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		try { $summary = $action === 'list_request' ? $this->battle_rules->text($input['public_summary'] ?? NULL, 500, 20) : ''; } catch (InvalidArgumentException $error) { return array('ok' => FALSE, 'code' => 400); }
		if ($action === 'list_request' && ( ! in_array($input['public_age'] ?? NULL, array('primary', 'secondary', 'all_ages'), TRUE) || ($input['confirm_review'] ?? '') !== '1')) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($actor, $id, $revision, $input, $action, $summary) {
			$user = $this->actor($actor, TRUE); if ( ! $user) { return array('ok' => FALSE, 'code' => 403); }
			$room = $this->rows('SELECT * FROM st_battle_rooms WHERE id=? FOR UPDATE', array($id))[0] ?? NULL;
			if ( ! $room || ! $this->host($actor, $room)) { return array('ok' => FALSE, 'code' => 404); }
			if ((string) $room['revision'] !== $revision) { return array('ok' => FALSE, 'code' => 409); }
			$update = array('listing_status' => 'hidden', 'revision' => (int) $revision + 1, 'updated_at' => gmdate('Y-m-d H:i:s'));
			if ($action === 'list_request')
			{
				if ($user['account_kind'] === 'child' || ! $user['email_verified_at'] || $room['status'] !== 'published' || strtotime($room['ends_at'].' UTC') <= time() || ! $this->publisher_ready($room) || $this->settings()['enabled'] !== '1') { return array('ok' => FALSE, 'code' => 403); }
				$update += array('public_summary' => $summary, 'public_age' => $input['public_age'], 'listing_reason' => '', 'listing_requested_at' => gmdate('Y-m-d H:i:s'), 'listing_reviewed_at' => NULL, 'listing_reviewed_by' => NULL); $update['listing_status'] = 'pending';
			}
			if ( ! $this->db->where('id', $id)->update('st_battle_rooms', $update)) { throw new RuntimeException('Listing request not saved.'); }
			$this->audit($actor, $id, $action, array('revision' => $update['revision'])); return array('ok' => TRUE);
		});
	}
	public function review_queue($page, $status)
	{
		$total = (int) $this->rows('SELECT COUNT(*) n FROM st_battle_rooms WHERE listing_status=?', array($status))[0]['n']; $page_count = max(1, (int) ceil($total / 20)); $page = min(max(1, $page), $page_count);
		$rooms = $this->rows('SELECT id,title,public_age,listing_requested_at,listing_status,status FROM st_battle_rooms WHERE listing_status=? ORDER BY listing_requested_at,id LIMIT 20 OFFSET '.(($page - 1) * 20), array($status)); return compact('rooms', 'total', 'page', 'page_count', 'status');
	}
	public function review_room($id)
	{
		return $this->rows("SELECT id,title,public_summary,public_age,starts_at,ends_at,capacity,revision,listing_status,listing_reason,status,snapshot_json FROM st_battle_rooms WHERE id=? AND listing_requested_at IS NOT NULL AND listing_status IN ('pending','approved','rejected','hidden')", array($id))[0] ?? NULL;
	}
	public function review_listing($actor, $id, $revision, $input)
	{
		$decision = $input['decision'] ?? NULL;
		if ( ! is_string($revision) || ! ctype_digit($revision) || ! in_array($decision, array('approved', 'rejected', 'hidden'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		try { $reason = $this->battle_rules->text($input['reason'] ?? NULL, 300, 5); } catch (InvalidArgumentException $error) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($actor, $id, $revision, $input, $decision, $reason) {
			if ( ! $this->actor($actor, TRUE) || ! $this->rows("SELECT ur.user_id FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.role_code='system_admin'", array($actor))) { return array('ok' => FALSE, 'code' => 403); }
			$room = $this->rows('SELECT * FROM st_battle_rooms WHERE id=? FOR UPDATE', array($id))[0] ?? NULL;
			if ( ! $room || ! $room['listing_requested_at']) { return array('ok' => FALSE, 'code' => 404); }
			if ((string) $room['revision'] !== $revision || ($decision !== 'hidden' && $room['listing_status'] !== 'pending')) { return array('ok' => FALSE, 'code' => 409); }
			$self_review = $this->host($actor, $room);
			if ($decision === 'approved')
			{
				if ($room['status'] !== 'published' || strtotime($room['ends_at'].' UTC') <= time() || ! $this->publisher_ready($room) || ($self_review && $this->Settings_model->get_section('pembelajaran')['values']['solo_review'] !== '1')) { return array('ok' => FALSE, 'code' => 403); }
				foreach (array('check_content', 'check_rights', 'check_age', 'check_privacy') as $key) { if (($input[$key] ?? '') !== '1') { return array('ok' => FALSE, 'code' => 400); } }
			}
			if ( ! $this->db->where('id', $id)->update('st_battle_rooms', array('listing_status' => $decision, 'listing_reason' => $reason, 'listing_reviewed_at' => gmdate('Y-m-d H:i:s'), 'listing_reviewed_by' => $actor, 'revision' => (int) $revision + 1, 'updated_at' => gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Listing review not saved.'); }
			$this->audit($actor, $id, 'listing_reviewed', array('decision' => $decision, 'reason' => $reason, 'self_review' => $self_review, 'revision' => (int) $revision + 1)); return array('ok' => TRUE);
		});
	}
	public function moderate($actor, $report_id, $cancel, $reason)
	{
		if ( ! in_array($cancel, array('0', '1'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		try { $reason = $this->battle_rules->text($reason, 300, 5); } catch (InvalidArgumentException $error) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($actor, $report_id, $cancel, $reason) {
			if ( ! $this->actor($actor, TRUE) || ! $this->rows("SELECT ur.user_id FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.role_code='system_admin'", array($actor))) { return array('ok' => FALSE, 'code' => 403); }
			$report = $this->rows('SELECT room_id FROM st_battle_reports WHERE id=?', array($report_id))[0] ?? NULL; if ( ! $report) { return array('ok' => FALSE, 'code' => 404); }
			$room = $this->rows('SELECT * FROM st_battle_rooms WHERE id=? FOR UPDATE', array($report['room_id']))[0];
			$report = $this->rows('SELECT * FROM st_battle_reports WHERE id=? FOR UPDATE', array($report_id))[0]; if ($report['status'] !== 'open') { return array('ok' => TRUE); }
			$now = gmdate('Y-m-d H:i:s');
			if ($cancel === '1' && ! $this->db->where('id', $room['id'])->update('st_battle_rooms', array('status' => 'cancelled', 'cancelled_at' => $now, 'revision' => (int) $room['revision'] + 1, 'updated_at' => $now))) { throw new RuntimeException('Battle moderation failed.'); }
			if ( ! $this->db->where('id', $report_id)->update('st_battle_reports', array('status' => 'resolved', 'resolved_at' => $now, 'resolved_by_user_id' => $actor))) { throw new RuntimeException('Moderation resolution failed.'); }
			$this->audit($actor, $room['id'], 'moderated', array('cancelled' => $cancel === '1', 'reason' => $reason)); return array('ok' => TRUE);
		});
	}
}
