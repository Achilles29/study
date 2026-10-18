<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Google_oidc
{
	public function __construct()
	{
		require_once APPPATH.'third_party/google_auth/vendor/autoload.php'; }
	private function qa_directory()
	{
		$ci =& get_instance(); $path = getenv('STUDY_GOOGLE_TEST_DIRECTORY');
		return ENVIRONMENT === 'development' && preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', $ci->db->database) && is_string($path) && preg_match('#\A/tmp/study-google-qa-[A-Za-z0-9]+\z#', $path) && is_dir($path) ? $path : NULL;
	}
	private function key()
	{
		$ci =& get_instance(); $key = $ci->config->item('encryption_key'); if ( ! is_string($key) || strlen($key) < 24) { throw new RuntimeException('Google encryption key unavailable.'); } return hash_hkdf('sha256', $key, 32, 'projectpintar/google-oidc/v1');
	}
	public function seal($value)
	{
		$nonce = random_bytes(12); $tag = ''; $encrypted = openssl_encrypt($value, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $nonce, $tag, 'google-oidc-v1', 16);
		if ($encrypted === FALSE) { throw new RuntimeException('Google secret unavailable.'); } return base64_encode($nonce.$tag.$encrypted);
	}
	public function unseal($value)
	{
		$raw = base64_decode((string) $value, TRUE); if ($raw === FALSE || strlen($raw) < 29) { throw new RuntimeException('Invalid Google secret.'); }
		$result = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'google-oidc-v1'); if ($result === FALSE) { throw new RuntimeException('Invalid Google secret.'); } return $result;
	}
	private function request($url, $fields = NULL)
	{
		$body = ''; $h = curl_init($url); $options = array(CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => FALSE, CURLOPT_SSL_VERIFYPEER => TRUE, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_HTTPHEADER => array('Accept: application/json'), CURLOPT_WRITEFUNCTION => static function ($h, $chunk) use (&$body) {
			if (strlen($body) + strlen($chunk) > 65536) { return 0; } $body .= $chunk; return strlen($chunk); });
		if ($fields !== NULL) { $options[CURLOPT_POST] = TRUE; $options[CURLOPT_POSTFIELDS] = http_build_query($fields, '', '&', PHP_QUERY_RFC3986); }
		curl_setopt_array($h, $options); $ok = curl_exec($h); $status = curl_getinfo($h, CURLINFO_HTTP_CODE); curl_close($h);
		if ($ok === FALSE || $status !== 200) { throw new RuntimeException('Google request unavailable.'); } $data = json_decode($body, TRUE); if ( ! is_array($data)) { throw new RuntimeException('Invalid Google response.'); } return $data;
	}
	public function exchange($config, $code, $verifier)
	{
		if ( ! is_string($code) || strlen($code) > 4096 || $code === '') { throw new InvalidArgumentException('Invalid authorization code.'); }
		$qa = $this->qa_directory(); if ($qa !== NULL) { if ( ! preg_match('/\A[a-f0-9]{64}\z/', $code) || ! is_file($qa.'/'.$code.'.json')) { throw new RuntimeException('QA authorization code rejected.'); } $v = json_decode(file_get_contents($qa.'/'.$code.'.json'), TRUE); if ( ! hash_equals($v['challenge'], rtrim(strtr(base64_encode(hash('sha256', $verifier, TRUE)), '+/', '-_'), '='))) { throw new RuntimeException('QA PKCE rejected.'); } return $v['id_token']; }
		$data = $this->request('https://oauth2.googleapis.com/token', array('code' => $code, 'client_id' => $config['client_id'], 'client_secret' => $this->unseal($config['secret_cipher']), 'redirect_uri' => site_url('auth/google/callback'), 'grant_type' => 'authorization_code', 'code_verifier' => $verifier));
		if ( ! is_string($data['id_token'] ?? NULL)) { throw new RuntimeException('Google ID token missing.'); } return $data['id_token'];
	}
	public function keys()
	{
		$qa = $this->qa_directory(); if ($qa !== NULL) { return json_decode(file_get_contents($qa.'/jwks.json'), TRUE); }
		$ci =& get_instance(); $q = $ci->db->query('SELECT jwks_json,jwks_until FROM st_google_config WHERE id=1'); if ($q === FALSE) { throw new RuntimeException('Google key cache unavailable.'); } $row = $q->row_array();
		if ($row['jwks_json'] && strtotime($row['jwks_until'].' UTC') > time()) { return json_decode($row['jwks_json'], TRUE); }
		$data = $this->request('https://www.googleapis.com/oauth2/v3/certs'); if (empty($data['keys'])) { throw new RuntimeException('Google keys missing.'); }
		$ci->db->where('id', 1)->update('st_google_config', array('jwks_json' => json_encode($data), 'jwks_until' => gmdate('Y-m-d H:i:s', time() + 300))); return $data;
	}
	public function verify($token, $client_id, $nonce_hash, $jwks, $with_claims = FALSE)
	{
		if ( ! is_string($token) || strlen($token) > 16384) { throw new InvalidArgumentException('Invalid token.'); }
		$keys = array(); foreach ($jwks['keys'] ?? array() as $k) { if (($k['kty'] ?? '') === 'RSA' && ($k['use'] ?? 'sig') === 'sig' && ($k['alg'] ?? 'RS256') === 'RS256') { $keys[] = $k; } }
		$claims = (array) \Firebase\JWT\JWT::decode($token, \Firebase\JWT\JWK::parseKeySet(array('keys' => $keys), 'RS256'));
		if ( ! in_array($claims['iss'] ?? NULL, array('https://accounts.google.com','accounts.google.com'), TRUE) || ($claims['aud'] ?? NULL) !== $client_id || (isset($claims['azp']) && $claims['azp'] !== $client_id) || ! is_int($claims['exp'] ?? NULL) || $claims['exp'] <= time() || ! is_int($claims['iat'] ?? NULL) || $claims['iat'] > time() + 30 || $claims['iat'] < time() - 600 || ! is_string($claims['nonce'] ?? NULL) || ! hash_equals($nonce_hash, hash('sha256', $claims['nonce'])) || ! is_string($claims['sub'] ?? NULL) || ! preg_match('/\A[0-9]{1,255}\z/', $claims['sub']) || ($claims['email_verified'] ?? FALSE) !== TRUE) { throw new InvalidArgumentException('Google identity rejected.'); }
		return $with_claims ? array('sub' => $claims['sub'], 'email' => $claims['email'] ?? NULL, 'hd' => $claims['hd'] ?? NULL) : $claims['sub'];
	}
}
