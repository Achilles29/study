<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Check_account_security extends CI_Controller
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
		$this->load->model('Account_model');
	}

	private function fixture($user_id)
	{
		if ($this->db->database !== 'db_study') { throw new RuntimeException('QA database mismatch.'); }
		$row = $this->db->where('id', (int) $user_id)->get('st_users')->row_array();
		if ( ! $row || ! preg_match('/\Aqa_security_[a-f0-9]{12}_race\z/', $row['username'])) { throw new RuntimeException('Only the exact synthetic race fixture is permitted.'); }
		return $row;
	}

	public function hold($user_id = 0)
	{
		$this->fixture($user_id);
		$this->db->trans_begin();
		$this->db->query('SELECT id FROM st_users WHERE id=? FOR UPDATE', array((int) $user_id));
		$connection = $this->db->query('SELECT CONNECTION_ID() AS id')->row_array();
		fwrite(STDOUT, 'READY '.(int) $connection['id']."\n");
		fflush(STDOUT);
		stream_set_blocking(STDIN, FALSE);
		$deadline = microtime(TRUE) + 20;
		$input = '';
		while (microtime(TRUE) < $deadline)
		{
			$input .= stream_get_contents(STDIN);
			if (strpos($input, 'release') !== FALSE) { break; }
			usleep(50000);
		}
		$this->db->trans_rollback();
		if (strpos($input, 'release') === FALSE) { throw new RuntimeException('Security QA barrier timed out; rolled back.'); }
		fwrite(STDOUT, "RELEASED\n");
	}

	public function index($user_id = 0)
	{
		$before = $this->fixture($user_id);
		$current = getenv('STUDY_QA_SECURITY_PASSWORD');
		$next = getenv('STUDY_QA_SECURITY_NEXT');
		if ( ! study_valid_password($current, 12) || ! study_valid_password($next, 12) || ! password_verify($current, $before['password_hash'])) { throw new RuntimeException('Invalid synthetic credential setup.'); }
		$stamp = $this->Account_model->find_active_user($user_id)['auth_stamp'];
		$count = $this->db->where('actor_user_id', (int) $user_id)->where('event_type', 'auth.password_changed')->count_all_results('st_audit_events');
		$database = $this->db;
		$debug = $database->db_debug;
		$proxy = new Security_failure_probe($database);
		$database->db_debug = FALSE;
		$this->db = $proxy;
		try
		{
			$result = $this->Account_model->change_password($user_id, $stamp, $current, $next, '127.0.0.1');
		}
		finally
		{
			$this->db = $database;
			$database->db_debug = $debug;
		}
		$after = $this->fixture($user_id);
		$after_count = $this->db->where('actor_user_id', (int) $user_id)->where('event_type', 'auth.password_changed')->count_all_results('st_audit_events');
		if ($result !== 'unavailable' || ! $proxy->failed || $after['password_hash'] !== $before['password_hash'] || $after['updated_at'] !== $before['updated_at'] || $after_count !== $count) { throw new RuntimeException('Failed audit did not roll back the password transaction.'); }
		if ($this->Account_model->find_active_user($user_id)['auth_stamp'] !== $stamp) { throw new RuntimeException('Failed change invalidated credentials.'); }
		fwrite(STDOUT, "PASS real database query failure at audit insertion rolls back hash/timestamp/audit and preserves the credential stamp.\n");
	}
}

class Security_failure_probe
{
	private $database;
	public $failed = FALSE;
	public function __construct($database)
	{
		$this->database = $database;
	}
	public function __call($name, $arguments)
	{
		if ($name === 'insert' && ($arguments[0] ?? '') === 'st_audit_events' && ($arguments[1]['event_type'] ?? '') === 'auth.password_changed')
		{
			$this->failed = TRUE;
			// Actual SQL failure on the same transaction; never writes another table.
			return $this->database->query('STUDY_QA_FORCED_AUDIT_FAILURE');
		}
		return call_user_func_array(array($this->database, $name), $arguments);
	}
}
