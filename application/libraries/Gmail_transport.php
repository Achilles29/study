<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Gmail_transport
{
	public function send($sender, $password, $recipient, $subject, $body, $smtp = NULL)
	{
		if (preg_match('/[\r\n]/', $subject)) { throw new InvalidArgumentException('Invalid subject.'); }
		foreach (array($sender, $recipient) as $address) { if ( ! filter_var($address, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $address)) { throw new InvalidArgumentException('Invalid address.'); } }
		$message = 'Date: '.gmdate('D, d M Y H:i:s').' +0000'."\r\nMessage-ID: <".bin2hex(random_bytes(16)).'@'.substr(strrchr($sender, '@'), 1).">\r\nFrom: Project Pintar <".$sender.">\r\nTo: <".$recipient.">\r\nSubject: ".$subject."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($body), 76, "\r\n");
		// No QA capture override is accepted through HTTP or in production.
		$ci =& get_instance(); $test_path = getenv('STUDY_MAIL_TEST_DIRECTORY');
		if (ENVIRONMENT === 'development' && preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', $ci->db->database) && is_string($test_path) && preg_match('#\A/tmp/study-mail-qa-[a-zA-Z0-9]+\z#', $test_path) && is_dir($test_path))
		{
			if (is_file($test_path.'/fail')) { return FALSE; }
			$file = $test_path.'/'.bin2hex(random_bytes(12)).'.json';
			if (file_put_contents($file, json_encode(array('recipient' => $recipient, 'subject' => $subject, 'body' => $body)), LOCK_EX) === FALSE) { return FALSE; } chmod($file, 0600); return TRUE;
		}
		$host = $smtp['host'] ?? 'smtp.gmail.com'; $port = $smtp['port'] ?? 587;
		$handle = curl_init(($port === 465 ? 'smtps' : 'smtp').'://'.$host.':'.$port); $offset = 0;
		if ($smtp !== NULL)
		{
			$ci->load->library('domain_mail_transport');
			// Pin the checked IP; TLS still verifies the hostname. No proxy/rebinding bypass.
			curl_setopt($handle, CURLOPT_RESOLVE, array($host.':'.$port.':'.$ci->domain_mail_transport->resolve($host)));
		}
		curl_setopt_array($handle, array(CURLOPT_PROXY => '', CURLOPT_FOLLOWLOCATION => FALSE, CURLOPT_USERNAME => $smtp['username'] ?? $sender, CURLOPT_PASSWORD => $password, CURLOPT_USE_SSL => CURLUSESSL_ALL, CURLOPT_SSL_VERIFYPEER => TRUE, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_MAIL_FROM => $sender, CURLOPT_MAIL_RCPT => array($recipient), CURLOPT_UPLOAD => TRUE, CURLOPT_READFUNCTION => static function ($curl, $stream, $length) use (&$offset, $message) {
			$chunk = substr($message, $offset, $length); $offset += strlen($chunk); return $chunk; }, CURLOPT_RETURNTRANSFER => TRUE));
		try { return curl_exec($handle) !== FALSE && curl_getinfo($handle, CURLINFO_RESPONSE_CODE) === 250; }
		finally { curl_close($handle); }
	}
}
