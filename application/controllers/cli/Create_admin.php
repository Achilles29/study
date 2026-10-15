<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Create_admin extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		if (PHP_SAPI !== 'cli')
		{
			show_404();
			exit;
		}
		$this->load->database();
	}

	public function index()
	{
		$config = array(
			'username' => getenv('STUDY_ADMIN_USERNAME'),
			'email' => getenv('STUDY_ADMIN_EMAIL'),
			'display_name' => getenv('STUDY_ADMIN_NAME'),
			'password' => getenv('STUDY_ADMIN_PASSWORD')
		);

		foreach ($config as $key => $value)
		{
			if ($value === FALSE || $value === '')
			{
				fwrite(STDERR, "Set STUDY_ADMIN_USERNAME, STUDY_ADMIN_EMAIL, STUDY_ADMIN_NAME, and STUDY_ADMIN_PASSWORD in the process environment.\n");
				exit(2);
			}
		}

		if ( ! study_valid_password($config['password'], 16) || ! preg_match('/^[A-Za-z0-9_-]{4,80}$/', $config['username']) || ! filter_var($config['email'], FILTER_VALIDATE_EMAIL))
		{
			fwrite(STDERR, "Username/email/password does not meet bootstrap requirements. Password: minimum 16 UTF-8 characters, maximum 72 bytes, no NUL.\n");
			exit(2);
		}

		$this->db->where('role_code', 'system_admin');
		$role = $this->db->get('st_roles')->row_array();
		$this->db->from('st_user_roles ur');
		$this->db->join('st_roles r', 'r.id = ur.role_id');
		$this->db->where('r.role_code', 'system_admin');
		if ( ! $role || $this->db->count_all_results() > 0)
		{
			fwrite(STDERR, "A system administrator is already provisioned; refusing to create another through this bootstrap command.\n");
			exit(3);
		}

		$this->db->where('username', strtolower($config['username']));
		$this->db->or_where('email', strtolower($config['email']));
		if ($this->db->count_all_results('st_users') > 0)
		{
			fwrite(STDERR, "Username or email already exists.\n");
			exit(3);
		}

		$now = gmdate('Y-m-d H:i:s');
		$this->db->trans_begin();
		$this->db->insert('st_users', array(
			'username' => strtolower($config['username']),
			'email' => strtolower($config['email']),
			'password_hash' => password_hash($config['password'], PASSWORD_DEFAULT),
			'display_name' => trim($config['display_name']),
			'account_kind' => 'adult',
			'status' => 'active',
			'email_verified_at' => $now,
			'created_at' => $now,
			'updated_at' => $now
		));
		$user_id = (int) $this->db->insert_id();
		$this->db->insert('st_wallets', array(
			'owner_user_id' => $user_id,
			'balance_credits' => 0,
			'created_at' => $now,
			'updated_at' => $now
		));
		$this->db->insert('st_user_roles', array(
			'user_id' => $user_id,
			'role_id' => $role['id'],
			'granted_by_user_id' => NULL,
			'created_at' => $now
		));
		$this->db->insert('st_audit_events', array(
			'actor_user_id' => $user_id,
			'event_type' => 'admin.initial_bootstrap',
			'entity_type' => 'user',
			'entity_id' => $user_id,
			'correlation_id' => NULL,
			'details_json' => json_encode(array('role' => 'system_admin')),
			'created_at' => $now
		));

		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			fwrite(STDERR, "Administrator bootstrap failed; transaction rolled back.\n");
			exit(1);
		}

		$this->db->trans_commit();
		fwrite(STDOUT, "Initial system administrator created. Remove STUDY_ADMIN_PASSWORD from the process environment immediately.\n");
	}
}
