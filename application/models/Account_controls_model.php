<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Account_controls_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct(); $this->load->library('study_transaction'); }
	private function rows($sql, $args = array())
	{
		$q = $this->db->query($sql, $args); if ($q === FALSE) { throw new RuntimeException('Account operation unavailable.'); } return $q->result_array();
	}
	private function actor($id, $stamp, $admin)
	{
		$u = $this->rows("SELECT * FROM st_users WHERE id=? AND status='active' FOR UPDATE", array($id))[0] ?? NULL;
		if ( ! $u || ! is_string($stamp) || ! hash_equals(study_credential_stamp($u), $stamp)) { return NULL; }
		if ($admin) { if ( ! $this->is_admin($id)) { return NULL; } }
		elseif ($u['account_kind'] !== 'parent' || ! $u['email_verified_at']) { return NULL; }
		return $u;
	}
	private function is_admin($id)
	{
		return (bool) $this->rows("SELECT ur.user_id FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.role_code='system_admin' FOR UPDATE", array($id));
	}
	private function audit($actor, $event, $id, $data)
	{
		$this->db->insert('st_audit_events', array('actor_user_id' => $actor, 'event_type' => $event, 'entity_type' => 'user', 'entity_id' => $id, 'details_json' => json_encode($data), 'created_at' => gmdate('Y-m-d H:i:s')));
	}
	public function person($id)
	{
		return $this->rows('SELECT id,username,display_name,account_kind,status,auth_version FROM st_users WHERE id=?', array($id))[0] ?? NULL;
	}
	public function child($parent, $id)
	{
		return $this->rows("SELECT u.id,u.username,u.display_name,u.auth_version FROM st_users u JOIN st_family_links l ON l.child_user_id=u.id WHERE l.parent_user_id=? AND u.id=? AND l.status='active' AND u.status='active' AND u.account_kind='child'", array($parent, $id))[0] ?? NULL;
	}
	public function reset_child($actor, $stamp, $id, $version, $password)
	{
		if ( ! study_valid_password($password, study_child_password_minimum()) || ! is_string($version) || ! ctype_digit($version)) { return array('ok' => FALSE, 'code' => 400); }
		$hash = password_hash($password, PASSWORD_DEFAULT);
		return $this->study_transaction->run($this->db, function () use ($actor, $stamp, $id, $version, $password, $hash) {
			if ( ! $this->actor($actor, $stamp, FALSE)) { return array('ok' => FALSE, 'code' => 403); }
			$u = $this->rows("SELECT u.* FROM st_users u JOIN st_family_links l ON l.child_user_id=u.id WHERE l.parent_user_id=? AND u.id=? AND l.status='active' AND u.status='active' AND u.account_kind='child' FOR UPDATE", array($actor, $id))[0] ?? NULL;
			if ( ! $u) { return array('ok' => FALSE, 'code' => 404); }
			if ((string) $u['auth_version'] !== $version || password_verify($password, $u['password_hash'])) { return array('ok' => FALSE, 'code' => 409); }
			if ((int) $this->rows("SELECT COUNT(*) n FROM st_audit_events WHERE actor_user_id=? AND event_type='family.child_password_reset' AND created_at>=UTC_TIMESTAMP()-INTERVAL 15 MINUTE", array($actor))[0]['n'] >= 5) { return array('ok' => FALSE, 'code' => 409); }
			$this->db->where('id', $id)->update('st_users', array('password_hash' => $hash, 'auth_version' => (int) $u['auth_version'] + 1, 'updated_at' => gmdate('Y-m-d H:i:s')));
			$this->audit($actor, 'family.child_password_reset', $id, array('sessions_revoked' => TRUE));
			return array('ok' => TRUE);
		});
	}
	private function reason($value)
	{
		return is_string($value) && mb_check_encoding($value, 'UTF-8') && mb_strlen(trim($value), 'UTF-8') >= 10 && mb_strlen($value, 'UTF-8') <= 300;
	}
	public function status($actor, $stamp, $id, $version, $next, $reason)
	{
		if ( ! $this->reason($reason) || ! is_string($version) || ! ctype_digit($version) || ! in_array($next, array('active','suspended'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($actor, $stamp, $id, $version, $next, $reason) {
			if ( ! $this->actor($actor, $stamp, TRUE) || (int) $actor === (int) $id || $this->is_admin($id)) { return array('ok' => FALSE, 'code' => 403); }
			// Parent locks serialize restoration of a child with creation of another child.
			$parents = $this->rows("SELECT p.id FROM st_family_links l JOIN st_users p ON p.id=l.parent_user_id WHERE l.child_user_id=? AND l.status='active' ORDER BY p.id FOR UPDATE", array($id));
			$u = $this->rows('SELECT * FROM st_users WHERE id=? FOR UPDATE', array($id))[0] ?? NULL;
			if ( ! $u) { return array('ok' => FALSE, 'code' => 404); }
			if ((string) $u['auth_version'] !== $version || ! in_array($u['status'], array('active','suspended'), TRUE)) { return array('ok' => FALSE, 'code' => 409); }
			if ($u['status'] === $next) { return array('ok' => TRUE); }
			if ($next === 'active' && $u['account_kind'] === 'child')
			{
				$this->load->model('Commerce_access_model');
				foreach ($parents as $p)
				{
					$count = count($this->rows("SELECT u.id FROM st_family_links l JOIN st_users u ON u.id=l.child_user_id WHERE l.parent_user_id=? AND l.status='active' AND u.status='active' AND u.account_kind='child' FOR UPDATE", array($p['id'])));
					if ($count >= 2 + $this->Commerce_access_model->extra_slots($p['id'], TRUE)) { return array('ok' => FALSE, 'code' => 409); }
				}
			}
			$this->db->where('id', $id)->update('st_users', array('status' => $next, 'auth_version' => (int) $u['auth_version'] + 1, 'updated_at' => gmdate('Y-m-d H:i:s')));
			$this->audit($actor, 'account.status_changed', $id, array('before' => $u['status'], 'after' => $next, 'reason' => trim($reason), 'sessions_revoked' => TRUE));
			return array('ok' => TRUE);
		});
	}
	public function courses($id, $page = 1)
	{
		return $this->rows('SELECT c.id,c.title,c.status,COALESCE(b.blocked,0) blocked,COALESCE(b.revision,0) revision FROM st_courses c LEFT JOIN st_course_access_blocks b ON b.course_id=c.id AND b.user_id=? ORDER BY c.id DESC LIMIT 20 OFFSET '.(((int) $page - 1) * 20), array($id));
	}
	public function course($actor, $stamp, $id, $course, $revision, $blocked, $reason)
	{
		if ( ! $this->reason($reason) || ! is_string($revision) || ! ctype_digit($revision) || ! in_array($blocked, array('0','1'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($actor, $stamp, $id, $course, $revision, $blocked, $reason) {
			if ( ! $this->actor($actor, $stamp, TRUE) || $this->is_admin($id)) { return array('ok' => FALSE, 'code' => 403); }
			if ( ! $this->rows('SELECT id FROM st_users WHERE id=? FOR UPDATE', array($id)) || ! $this->rows('SELECT id FROM st_courses WHERE id=? FOR UPDATE', array($course))) { return array('ok' => FALSE, 'code' => 404); }
			$old = $this->rows('SELECT * FROM st_course_access_blocks WHERE user_id=? AND course_id=? FOR UPDATE', array($id, $course))[0] ?? NULL;
			if ((string) ($old['revision'] ?? 0) !== $revision) { return array('ok' => FALSE, 'code' => 409); }
			$this->db->query('INSERT INTO st_course_access_blocks(user_id,course_id,blocked,revision,updated_at) VALUES(?,?,?,1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE blocked=VALUES(blocked),revision=revision+1,updated_at=UTC_TIMESTAMP()', array($id, $course, $blocked));
			$this->audit($actor, 'account.course_access_changed', $id, array('course_id' => (int) $course, 'blocked' => $blocked === '1', 'reason' => trim($reason)));
			return array('ok' => TRUE);
		});
	}
}
