<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payment_secrets
{
	private function key()
	{
		$ci =& get_instance();
		$key = $ci->config->item('encryption_key');
		if ( ! is_string($key) || strlen($key) < 24) { throw new RuntimeException('Payment encryption key unavailable.'); }
		return hash_hkdf('sha256', $key, 32, 'projectpintar/payment-config/v1');
	}

	public function seal($values, $environment = 'sandbox')
	{
		if ( ! in_array($environment, array('sandbox','production'), TRUE)) { throw new RuntimeException('Invalid payment environment.'); }
		$nonce = random_bytes(12); $tag = '';
		$cipher = openssl_encrypt(json_encode($values, JSON_THROW_ON_ERROR), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $nonce, $tag, 'projectpintar:'.$environment.':v1', 16);
		if ($cipher === FALSE) { throw new RuntimeException('Encryption failed.'); }
		return ($environment === 'sandbox' ? 'v1:' : 'v2:').base64_encode($nonce.$tag.$cipher);
	}

	public function open($cipher, $environment = 'sandbox')
	{
		if ( ! in_array($environment, array('sandbox','production'), TRUE) || ! is_string($cipher) || substr($cipher, 0, 3) !== ($environment === 'sandbox' ? 'v1:' : 'v2:')) { throw new RuntimeException('Secret format invalid.'); }
		$bytes = base64_decode(substr($cipher, 3), TRUE);
		if ($bytes === FALSE || strlen($bytes) < 29) { throw new RuntimeException('Secret format invalid.'); }
		$plain = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16), 'projectpintar:'.$environment.':v1');
		if ($plain === FALSE) { throw new RuntimeException('Secret authentication failed.'); }
		$value = json_decode($plain, TRUE, 16, JSON_THROW_ON_ERROR);
		if ( ! is_array($value) || ! isset($value['server_key'], $value['client_key'])) { throw new RuntimeException('Credentials invalid.'); }
		return $value;
	}
}
