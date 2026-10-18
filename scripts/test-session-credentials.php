<?php
if (PHP_SAPI !== 'cli')
{
	exit(1);
}
define('BASEPATH', dirname(__DIR__).'/system/');
class Session_probe
{
	public $data = array();
	public $rotated = FALSE;
	public function userdata($key)
	{
		return $this->data[$key] ?? NULL;
	}
	public function unset_userdata($keys)
	{
		foreach ($keys as $key) { unset($this->data[$key]); }
	}
	public function sess_regenerate($destroy)
	{
		$this->rotated = $destroy;
	}
	public function set_flashdata($key, $value)
	{
	}
}
class Account_probe
{
	public $user;
	public function find_active_user($id)
	{
		return $this->user;
	}
}
class Noop_probe
{
	public function model($name)
	{
	}
	public function set_message($key, $value)
	{
	}
	public function set_header($value)
	{
	}
}
class CI_Controller
{
	public $session;
	public $account_model;
	public $load;
	public $form_validation;
	public $output;
	public function __construct()
	{
		$this->session = $GLOBALS['probe_session'];
		$this->account_model = $GLOBALS['probe_account'];
		$this->load = $this->form_validation = $this->output = new Noop_probe();
	}
	public function __get($name)
	{
		return $name === 'Account_model' ? $this->account_model : NULL;
	}
}
function redirect($path, $method, $status)
{
	if ($path !== 'masuk' || $status !== 303) { throw new LogicException('Unexpected session redirect.'); }
	throw new RuntimeException('session-rejected');
}
require dirname(__DIR__).'/application/core/MY_Controller.php';
$stamp = str_repeat('a', 64);
foreach (array(NULL, array('invalid'), str_repeat('b', 64), $stamp) as $stored)
{
	$GLOBALS['probe_session'] = new Session_probe();
	$GLOBALS['probe_session']->data = array('user_id' => 1, 'auth_stamp' => $stored, 'directory_searches' => array('private'), 'monitor_searches' => array('private'));
	$GLOBALS['probe_account'] = new Account_probe();
	$GLOBALS['probe_account']->user = array('auth_stamp' => $stamp);
	$rejected = FALSE;
	try { new MY_Controller(); } catch (RuntimeException $exception) { if ($exception->getMessage() !== 'session-rejected') { throw $exception; } $rejected = TRUE; }
	if ($rejected !== ($stored !== $stamp)) { throw new LogicException('Session credential check mismatch.'); }
	if ($rejected && ($GLOBALS['probe_session']->data || ! $GLOBALS['probe_session']->rotated)) { throw new LogicException('Stale session retained authentication/context or old ID.'); }
}
$GLOBALS['probe_account']->user = NULL;
try { new MY_Controller(); throw new LogicException('Inactive account accepted.'); } catch (RuntimeException $exception) { if ($exception->getMessage() !== 'session-rejected') { throw $exception; } }
echo "PASS session credentials: absent/array/stale stamps and inactive accounts rejected, auth/private context cleared, session ID rotated; current stamp accepted.\n";
