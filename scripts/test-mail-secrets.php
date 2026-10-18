<?php
define('BASEPATH', dirname(__DIR__).'/system/');
class Mail_test_config
{
	public $secret = 'synthetic-mail-master-key-test-only';
	public function item($name)
	{
		return $this->secret; }
}
function &get_instance()
{
	static $ci;
	if ( ! $ci) { $ci = (object) array('config' => new Mail_test_config()); } return $ci;
}
require dirname(__DIR__).'/application/libraries/Mail_secrets.php';
$secrets = new Mail_secrets(); $secret = 'synthetic-secret'; $one = $secrets->seal($secret); $two = $secrets->seal($secret);
if ($one === $two || $secrets->open($one) !== $secret || strpos($one, $secret) !== FALSE) { throw new RuntimeException('Mail encryption roundtrip failed.'); }
$raw = base64_decode(substr($one, 3)); $raw[29] = chr(ord($raw[29]) ^ 1);
foreach (array('', 'v1:!', 'v1:'.base64_encode($raw)) as $invalid)
{
	$rejected = FALSE; try { $secrets->open($invalid); } catch (RuntimeException $error) { $rejected = TRUE; }
	if ( ! $rejected) { throw new RuntimeException('Invalid ciphertext accepted.'); }
}
get_instance()->config->secret = 'different-synthetic-mail-key-for-test';
$rejected = FALSE; try { $secrets->open($one); } catch (RuntimeException $error) { $rejected = TRUE; }
if ( ! $rejected) { throw new RuntimeException('Wrong key accepted.'); }
echo "PASS Gmail secret roundtrip, random nonce, malformed/tampered ciphertext and wrong-key rejection.\n";
