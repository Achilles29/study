<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Account_model extends CI_Model
{
	private function now_utc()
	{
		return gmdate('Y-m-d H:i:s');
	}

	private function digest($kind, $value)
	{
		$key = (string) $this->config->item('encryption_key');
		return hash_hmac('sha256', $kind.'|'.$value, $key);
	}

	public function find_active_user($user_id)
	{
		$this->db->select('id, username, email, display_name, account_kind, learning_stage, status, password_hash');
		$this->db->from('st_users');
		$this->db->where('id', (int) $user_id);
		$this->db->where('status', 'active');
		$user = $this->db->get()->row_array();
		if ( ! $user)
		{
			return NULL;
		}

		$user['auth_stamp'] = $this->digest('credential', $user['id'].'|'.$user['password_hash']);
		unset($user['password_hash']);
		$this->db->select('r.role_code');
		$this->db->from('st_user_roles ur');
		$this->db->join('st_roles r', 'r.id = ur.role_id');
		$this->db->where('ur.user_id', (int) $user_id);
		$user['roles'] = array_column($this->db->get()->result_array(), 'role_code');
		return $user;
	}

	public function identity_exists($username, $email = NULL)
	{
		$this->db->from('st_users');
		$this->db->group_start()->where('username', $username);
		if ($email !== NULL && $email !== '')
		{
			$this->db->or_where('email', $email);
		}
		$this->db->group_end();
		return $this->db->count_all_results() > 0;
	}

	public function register_adult_or_parent($account_kind, $username, $email, $display_name, $password)
	{
		if ( ! study_valid_password($password, 12) || ! in_array($account_kind, array('adult', 'parent'), TRUE))
		{
			return FALSE;
		}

		$username = strtolower(trim($username));
		$email = strtolower(trim($email));
		if ($this->identity_exists($username, $email))
		{
			return FALSE;
		}

		$now = $this->now_utc();
		$this->db->trans_begin();
		$this->db->insert('st_users', array(
			'username' => $username,
			'email' => $email,
			'password_hash' => password_hash($password, PASSWORD_DEFAULT),
			'display_name' => trim($display_name),
			'account_kind' => $account_kind,
			'learning_stage' => $account_kind === 'adult' ? 'adult' : 'unset',
			'status' => 'active',
			'created_at' => $now,
			'updated_at' => $now
		));

		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$user_id = (int) $this->db->insert_id();
		$this->db->insert('st_wallets', array(
			'owner_user_id' => $user_id,
			'balance_credits' => 0,
			'created_at' => $now,
			'updated_at' => $now
		));
		$this->db->insert('st_audit_events', array(
			'actor_user_id' => $user_id,
			'event_type' => 'account.registered',
			'entity_type' => 'user',
			'entity_id' => $user_id,
			'correlation_id' => NULL,
			'details_json' => json_encode(array('account_kind' => $account_kind)),
			'created_at' => $now
		));

		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return $this->find_active_user($user_id);
	}

	public function is_login_throttled($identity, $address)
	{
		$since = gmdate('Y-m-d H:i:s', time() - 900);
		$identity_hash = $this->digest('identity', strtolower(trim($identity)));
		$address_hash = $this->digest('address', $address);

		$this->db->from('st_login_failures');
		$this->db->where('identity_hash', $identity_hash);
		$this->db->where('attempted_at >=', $since);
		$identity_count = $this->db->count_all_results();

		$this->db->from('st_login_failures');
		$this->db->where('address_hash', $address_hash);
		$this->db->where('attempted_at >=', $since);
		$address_count = $this->db->count_all_results();

		return ($identity_count >= 10 || $address_count >= 50);
	}

	public function authenticate($identity, $password, $address)
	{
		$identity = strtolower(trim($identity));
		if ($this->is_login_throttled($identity, $address))
		{
			return array('ok' => FALSE, 'throttled' => TRUE);
		}

		$this->db->select('id, username, email, password_hash, display_name, account_kind, status');
		$this->db->from('st_users');
		$this->db->group_start()->where('username', $identity)->or_where('email', $identity)->group_end();
		$user = $this->db->get()->row_array();

		if (study_valid_password($password) && $user && $user['status'] === 'active' && password_verify($password, $user['password_hash']))
		{
			$identity_hash = $this->digest('identity', $identity);
			$address_hash = $this->digest('address', $address);
			$now = $this->now_utc();
			$this->db->trans_begin();
			$this->db->where('identity_hash', $identity_hash);
			$this->db->where('address_hash', $address_hash);
			$this->db->delete('st_login_failures');
			$this->db->where('attempted_at <', gmdate('Y-m-d H:i:s', time() - 604800));
			$this->db->delete('st_login_failures');
			$this->db->where('id', (int) $user['id']);
			$this->db->update('st_users', array('last_login_at' => $now, 'updated_at' => $now));
			$this->db->insert('st_audit_events', array(
				'actor_user_id' => (int) $user['id'],
				'event_type' => 'auth.login',
				'entity_type' => 'user',
				'entity_id' => (int) $user['id'],
				'correlation_id' => NULL,
				'details_json' => json_encode(array('method' => 'password')),
				'created_at' => $now
			));
			if ($this->db->trans_status() === FALSE)
			{
				$this->db->trans_rollback();
				return array('ok' => FALSE, 'throttled' => FALSE);
			}
			$this->db->trans_commit();
			$user['auth_stamp'] = $this->digest('credential', $user['id'].'|'.$user['password_hash']);
			unset($user['password_hash']);
			return array('ok' => TRUE, 'user' => $user);
		}

		$this->db->insert('st_login_failures', array(
			'identity_hash' => $this->digest('identity', $identity),
			'address_hash' => $this->digest('address', $address),
			'attempted_at' => $this->now_utc()
		));
		return array('ok' => FALSE, 'throttled' => FALSE);
	}

	public function record_logout($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1)
		{
			return FALSE;
		}

		return $this->db->insert('st_audit_events', array(
			'actor_user_id' => $user_id,
			'event_type' => 'auth.logout',
			'entity_type' => 'user',
			'entity_id' => $user_id,
			'correlation_id' => NULL,
			'details_json' => json_encode(array()),
			'created_at' => $this->now_utc()
		));
	}

	public function change_password($user_id, $stamp, $current_password, $new_password, $address)
	{
		if ( ! is_string($stamp) || ! study_valid_password($current_password) || ! study_valid_password($new_password))
		{
			return 'invalid';
		}
		$this->db->trans_begin();
		$row = $this->db->query('SELECT id, password_hash FROM st_users WHERE id = ? AND status = ? FOR UPDATE', array((int) $user_id, 'active'))->row_array();
		if ( ! $row || ! hash_equals($this->digest('credential', $row['id'].'|'.$row['password_hash']), $stamp))
		{
			$this->db->trans_rollback();
			return 'stale';
		}
		$user = $this->find_active_user($user_id);
		if ( ! $user || ! study_valid_password($new_password, study_password_minimum($user)))
		{
			$this->db->trans_rollback();
			return 'invalid';
		}
		$identity_hash = $this->digest('password-change', (string) $user_id);
		$count = $this->db->where('identity_hash', $identity_hash)->where('attempted_at >=', gmdate('Y-m-d H:i:s', time() - 900))->count_all_results('st_login_failures');
		if ($count >= 10)
		{
			$this->db->trans_rollback();
			return 'throttled';
		}
		if ( ! password_verify($current_password, $row['password_hash']))
		{
			$this->db->insert('st_login_failures', array('identity_hash' => $identity_hash, 'address_hash' => $this->digest('password-change-address', $address), 'attempted_at' => $this->now_utc()));
			if ($this->db->trans_status() === FALSE)
			{
				$this->db->trans_rollback();
				return 'unavailable';
			}
			$this->db->trans_commit();
			return 'incorrect';
		}
		if (password_verify($new_password, $row['password_hash']))
		{
			$this->db->trans_rollback();
			return 'unchanged';
		}
		$this->db->where('id', (int) $user_id)->update('st_users', array('password_hash' => password_hash($new_password, PASSWORD_DEFAULT), 'updated_at' => $this->now_utc()));
		$this->db->insert('st_audit_events', array('actor_user_id' => (int) $user_id, 'event_type' => 'auth.password_changed', 'entity_type' => 'user', 'entity_id' => (int) $user_id, 'correlation_id' => NULL, 'details_json' => json_encode(array('sessions_revoked' => TRUE)), 'created_at' => $this->now_utc()));
		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return 'unavailable';
		}
		$this->db->trans_commit();
		return 'changed';
	}

	public function get_children($parent_user_id)
	{
		$this->db->select('u.id, u.username, u.display_name, u.learning_stage, l.status AS link_status');
		$this->db->select("(SELECT COUNT(*) FROM st_lesson_progress p WHERE p.learner_user_id = u.id AND p.status = 'completed') AS completed_lessons", FALSE);
		$this->db->from('st_family_links l');
		$this->db->join('st_users u', 'u.id = l.child_user_id AND u.status = "active"');
		$this->db->where('l.parent_user_id', (int) $parent_user_id);
		$this->db->where('l.status', 'active');
		$this->db->order_by('u.display_name', 'ASC');
		return $this->db->get()->result_array();
	}

	public function update_child_learning_stage($parent_user_id, $child_user_id, $learning_stage)
	{
		$allowed_stages = array('early_childhood', 'primary', 'secondary');
		if ( ! in_array($learning_stage, $allowed_stages, TRUE))
		{
			return FALSE;
		}

		$this->db->trans_begin();
		$child = $this->db->query(
			"SELECT u.id, u.learning_stage FROM st_family_links l
			JOIN st_users u ON u.id = l.child_user_id
			WHERE l.parent_user_id = ? AND l.child_user_id = ? AND l.status = 'active'
			AND u.status = 'active' AND u.account_kind = 'child' FOR UPDATE",
			array((int) $parent_user_id, (int) $child_user_id)
		)->row_array();
		if ( ! $child)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$now = $this->now_utc();
		$this->db->where('id', (int) $child_user_id);
		$this->db->where('account_kind', 'child');
		$this->db->update('st_users', array('learning_stage' => $learning_stage, 'updated_at' => $now));
		$this->db->insert('st_audit_events', array(
			'actor_user_id' => (int) $parent_user_id,
			'event_type' => 'family.child_learning_stage_updated',
			'entity_type' => 'user',
			'entity_id' => (int) $child_user_id,
			'correlation_id' => NULL,
			'details_json' => json_encode(array('previous' => $child['learning_stage'], 'next' => $learning_stage)),
			'created_at' => $now
		));
		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return TRUE;
	}

	public function create_child($parent_user_id, $username, $display_name, $password, $learning_stage)
	{
		if ( ! study_valid_password($password, 10))
		{
			return FALSE;
		}
		$parent = $this->find_active_user($parent_user_id);
		$allowed_stages = array('early_childhood', 'primary', 'secondary');
		if ( ! $parent || $parent['account_kind'] !== 'parent' || ! in_array($learning_stage, $allowed_stages, TRUE) || $this->identity_exists(strtolower(trim($username))))
		{
			return FALSE;
		}

		$now = $this->now_utc();
		$this->db->trans_begin();
		$this->db->insert('st_users', array(
			'username' => strtolower(trim($username)),
			'email' => NULL,
			'password_hash' => password_hash($password, PASSWORD_DEFAULT),
			'display_name' => trim($display_name),
			'account_kind' => 'child',
			'learning_stage' => $learning_stage,
			'status' => 'active',
			'created_at' => $now,
			'updated_at' => $now
		));
		$child_user_id = (int) $this->db->insert_id();
		$this->db->insert('st_family_links', array(
			'parent_user_id' => (int) $parent_user_id,
			'child_user_id' => $child_user_id,
			'status' => 'active',
			'created_at' => $now,
			'updated_at' => $now
		));
		$this->db->insert('st_audit_events', array(
			'actor_user_id' => (int) $parent_user_id,
			'event_type' => 'family.child_created',
			'entity_type' => 'user',
			'entity_id' => $child_user_id,
			'correlation_id' => NULL,
			'details_json' => json_encode(array('family_linked' => TRUE, 'learning_stage' => $learning_stage)),
			'created_at' => $now
		));

		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return TRUE;
	}
}
