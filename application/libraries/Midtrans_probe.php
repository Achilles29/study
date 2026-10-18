<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Midtrans_probe
{
	public function check($server_key, $environment = 'sandbox')
	{
		if ( ! in_array($environment, array('sandbox','production'), TRUE) || strpos($server_key, ($environment === 'sandbox' ? 'SB-' : '').'Mid-server-') !== 0) { return 'unauthorized'; }
		$ci =& get_instance(); $qa = getenv('STUDY_PAYMENT_TEST_DIRECTORY');
		if (ENVIRONMENT === 'development' && preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', $ci->db->database) && is_string($qa) && preg_match('#\A/tmp/study-payment-qa-[A-Za-z0-9]+\z#', $qa) && is_file($qa.'/probe-'.$environment)) { return trim(file_get_contents($qa.'/probe-'.$environment)) === 'ok' ? 'status_api_reached' : 'unauthorized'; }
		if ($environment === 'production' && (ENVIRONMENT !== 'production' || preg_match('/\Astudy_qa_/', $ci->db->database))) { return 'unreachable'; }
		// Fixed status-only URL: never a charge or a change to global callback settings.
		$handle = curl_init(($environment === 'sandbox' ? 'https://api.sandbox.midtrans.com' : 'https://api.midtrans.com').'/v2/PP-PROBE-'.bin2hex(random_bytes(12)).'/status');
		$body = '';
		curl_setopt_array($handle, array(
			CURLOPT_HTTPHEADER => array('Accept: application/json', 'Authorization: Basic '.base64_encode($server_key.':')),
			CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => FALSE,
			CURLOPT_SSL_VERIFYPEER => TRUE, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
			CURLOPT_WRITEFUNCTION => static function ($handle, $chunk) use (&$body) {
				if (strlen($body) + strlen($chunk) > 65536) { return 0; }
				$body .= $chunk; return strlen($chunk);
			}
		));
		$ok = curl_exec($handle); $http = curl_getinfo($handle, CURLINFO_HTTP_CODE); curl_close($handle);
		if ($ok === FALSE) { return 'unreachable'; }
		return $this->response_status($http, $body);
	}
	private function response_status($http, $body)
	{
		$value = json_decode($body, TRUE, 32);
		$code = is_array($value) ? ($value['status_code'] ?? NULL) : NULL;
		$status = is_string($code) || is_int($code) ? (string) $code : '';
		if (in_array($http, array(401,403), TRUE) || in_array($status, array('401','403'), TRUE)) { return 'unauthorized'; }
		// Midtrans may deliver API 404 (random probe order absent) over HTTP 200.
		// Accept only these two exact combinations; an HTTP 200 by itself is not proof.
		if (in_array($http, array(200,404), TRUE) && $status === '404') { return 'status_api_reached'; }
		return 'unexpected_response';
	}
}
