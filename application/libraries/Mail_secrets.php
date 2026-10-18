<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Mail_secrets
{
	private function key()
	{
		$ci =& get_instance(); $key = $ci->config->item('encryption_key');
		if ( ! is_string($key) || strlen($key) < 24) { throw new RuntimeException('Mail key unavailable.'); }
		return hash_hkdf('sha256', $key, 32, 'projectpintar/gmail/v1');
	}
	public function seal($password)
	{
		$nonce = random_bytes(12); $tag = ''; $cipher = openssl_encrypt($password, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $nonce, $tag, 'projectpintar:gmail:v1', 16);
		if ($cipher === FALSE) { throw new RuntimeException('Mail encryption failed.'); } return 'v1:'.base64_encode($nonce.$tag.$cipher);
	}
	public function open($cipher)
	{
		if ( ! is_string($cipher) || substr($cipher, 0, 3) !== 'v1:') { throw new RuntimeException('Invalid mail secret.'); }
		$raw = base64_decode(substr($cipher, 3), TRUE); if ($raw === FALSE || strlen($raw) < 29) { throw new RuntimeException('Invalid mail secret.'); }
		$plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'projectpintar:gmail:v1');
		if ($plain === FALSE) { throw new RuntimeException('Mail secret authentication failed.'); } return $plain;
	}
}
