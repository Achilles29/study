<?php
define('BASEPATH', dirname(__DIR__).'/system/');
class Payment_test_config
{
	public $secret = 'synthetic-payment-master-key-for-test-only';
	public function item($key)
	{
		return $this->secret; }
}
function &get_instance()
{
	static $instance;
	if ( ! $instance) { $instance = (object) array('config' => new Payment_test_config()); }
	return $instance;
}
require dirname(__DIR__).'/application/libraries/Payment_secrets.php';
$secrets = new Payment_secrets(); $input = array('server_key' => 'synthetic-server', 'client_key' => 'synthetic-client');
$one = $secrets->seal($input); $two = $secrets->seal($input);
$live = $secrets->seal($input, 'production');
if ($secrets->open($live, 'production') !== $input) { throw new RuntimeException('Live secret roundtrip failed.'); }
foreach (array(array($live, 'sandbox'), array($one, 'production')) as $cross)
{
	$failed = FALSE; try { $secrets->open($cross[0], $cross[1]); } catch (RuntimeException $error) { $failed = TRUE; }
	if ( ! $failed) { throw new RuntimeException('Cross-environment secret accepted.'); }
}
if ($one === $two || $secrets->open($one) !== $input || strpos($one, 'synthetic') !== FALSE) { throw new RuntimeException('Secret roundtrip/randomization failed.'); }
$bytes = base64_decode(substr($one, 3)); $bytes[30] = chr(ord($bytes[30]) ^ 1);
$failed = FALSE; try { $secrets->open('v1:'.base64_encode($bytes)); } catch (RuntimeException $error) { $failed = TRUE; }
if ( ! $failed) { throw new RuntimeException('Tampered ciphertext accepted.'); }
get_instance()->config->secret = 'different-synthetic-master-key-for-test';
$failed = FALSE; try { $secrets->open($one); } catch (RuntimeException $error) { $failed = TRUE; }
if ( ! $failed) { throw new RuntimeException('Wrong master key accepted.'); }
echo "PASS authenticated payment encryption: roundtrip, nonce uniqueness, tamper/wrong-key rejection; no plaintext output.\n";
