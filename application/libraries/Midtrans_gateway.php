<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Midtrans_gateway
{
	public function request($key, $order, $charge = NULL, $environment = 'sandbox')
	{
		if ( ! in_array($environment, array('sandbox','production'), TRUE)) { return NULL; }
		$prefix = $environment === 'sandbox' ? 'SB' : 'LIVE';
		if ( ! preg_match('/\APP-'.$prefix.'-[a-f0-9]{32}\z/', $order) || strpos($key, ($environment === 'sandbox' ? 'SB-' : '').'Mid-server-') !== 0) { return NULL; }
		$ci =& get_instance(); $qa = getenv('STUDY_PAYMENT_TEST_DIRECTORY');
		if (ENVIRONMENT === 'development' && preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', $ci->db->database) && is_string($qa) && preg_match('#\A/tmp/study-payment-qa-[A-Za-z0-9]+\z#', $qa) && is_dir($qa))
		{
			// Local synthetic transport only; never enabled by HTTP or in production.
			$path = $qa.'/'.$order.'.json';
			if ($charge !== NULL && ! file_exists($path))
			{
				$value = array('status_code' => '201', 'transaction_status' => 'pending', 'transaction_id' => bin2hex(random_bytes(16)), 'order_id' => $order, 'merchant_id' => 'QA-MERCHANT', 'gross_amount' => $charge['transaction_details']['gross_amount'].'.00', 'currency' => 'IDR', 'payment_type' => 'qris', 'fraud_status' => 'accept');
				file_put_contents($path, json_encode($value), LOCK_EX); chmod($path, 0600);
			}
			return file_exists($qa.'/fail') || ! file_exists($path) ? NULL : json_decode(file_get_contents($path), TRUE);
		}
		// QA/development can never fall through to a real Production charge.
		if ($environment === 'production' && (ENVIRONMENT !== 'production' || preg_match('/\Astudy_qa_/', $ci->db->database))) { return NULL; }
		$headers = array('Accept: application/json', 'Content-Type: application/json', 'Authorization: Basic '.base64_encode($key.':'));
		if ($charge !== NULL)
		{
			$callback = site_url('pembayaran/notifikasi');
			if (strpos($callback, 'https://www.projectpintar.com/') !== 0) { return NULL; }
			$headers[] = 'X-Override-Notification: '.$callback;
		}
		$handle = curl_init(($environment === 'sandbox' ? 'https://api.sandbox.midtrans.com' : 'https://api.midtrans.com').'/v2/'.($charge !== NULL ? 'charge' : rawurlencode($order).'/status')); $body = '';
		$options = array(CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => FALSE, CURLOPT_SSL_VERIFYPEER => TRUE, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_WRITEFUNCTION => static function ($handle, $chunk) use (&$body) {
			if (strlen($body) + strlen($chunk) > 65536) { return 0; } $body .= $chunk; return strlen($chunk);
		});
		if ($charge !== NULL) { $options[CURLOPT_POST] = TRUE; $options[CURLOPT_POSTFIELDS] = json_encode($charge); }
		curl_setopt_array($handle, $options); $ok = curl_exec($handle); $http = curl_getinfo($handle, CURLINFO_HTTP_CODE); curl_close($handle);
		if ($ok === FALSE || $http < 200 || $http > 299) { return NULL; }
		$v = json_decode($body, TRUE); return is_array($v) ? $v : NULL;
	}
}
