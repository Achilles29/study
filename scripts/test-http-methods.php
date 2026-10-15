<?php
if (PHP_SAPI !== 'cli')
{
	exit(1);
}
define('BASEPATH', dirname(__DIR__).'/system/');
class CI_Controller
{
	public $input;
}
class Method_input
{
	public $verb;
	public function method($upper)
	{
		if ($upper !== TRUE)
		{
			throw new RuntimeException('Method must be requested in uppercase.');
		}
		return $this->verb;
	}
}
function show_error($message, $code, $heading)
{
	throw new RuntimeException($message, $code);
}
require dirname(__DIR__).'/application/core/MY_Controller.php';
class Method_probe extends MY_Controller
{
	public function __construct()
	{
		$this->input = new Method_input();
	}
	public function check($allowed)
	{
		$this->require_http_methods($allowed);
		return TRUE;
	}
}
$count = 0;
foreach (array(array('GET', 'HEAD'), array('GET', 'HEAD', 'POST'), array('POST')) as $allowed)
{
	foreach (array('GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE', 'CONNECT', 'UNKNOWN') as $verb)
	{
		$probe = new Method_probe();
		$probe->input->verb = $verb;
		$continued = FALSE;
		$status = 0;
		try
		{
			$continued = $probe->check($allowed);
		}
		catch (RuntimeException $error)
		{
			$status = $error->getCode();
		}
		$accepted = in_array($verb, $allowed, TRUE);
		if ($continued !== $accepted || $status !== ($accepted ? 0 : 405))
		{
			throw new RuntimeException('HTTP method boundary failed for '.$verb);
		}
		$count++;
	}
}
echo 'PASS HTTP method helper: '.$count." allow/deny cases; 405 stops action; no framework/database needed. Allow headers are verified by real HTTP tests.\n";
