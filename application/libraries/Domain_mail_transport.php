<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Domain_mail_transport
{
	public function valid_settings($host, $port, $username)
	{
		return is_string($host) && strlen($host) <= 253 && strpos($host, '.') !== FALSE
			&& filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) && ! filter_var($host, FILTER_VALIDATE_IP)
			&& ! preg_match('/\.(localhost|local|internal|test|invalid)\z/i', $host)
			&& in_array((string) $port, array('465', '587'), TRUE)
			&& is_string($username) && strlen($username) >= 1 && strlen($username) <= 190 && ! preg_match('/[\x00-\x20\x7f]/', $username);
	}
	public function public_address($ip)
	{
		// IPv4 only: reject non-public and special ranges, including metadata/CGNAT.
		if ( ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) { return FALSE; }
		$n = (int) sprintf('%u', ip2long($ip));
		foreach (array(array('100.64.0.0', 10), array('192.0.0.0', 24), array('192.0.2.0', 24), array('192.88.99.0', 24), array('198.18.0.0', 15), array('198.51.100.0', 24), array('203.0.113.0', 24), array('224.0.0.0', 4)) as $range)
		{
			$mask = (0xffffffff << (32 - $range[1])) & 0xffffffff;
			if (($n & $mask) === (ip2long($range[0]) & $mask)) { return FALSE; }
		}
		return TRUE;
	}
	public function send($settings, $password, $recipient, $subject, $body)
	{
		$host = $settings['smtp_host']; $port = (int) $settings['smtp_port'];
		if ( ! $this->valid_settings($host, $port, $settings['smtp_username'])) { throw new InvalidArgumentException('Invalid SMTP settings.'); }
		$ci =& get_instance(); $ci->load->library('gmail_transport');
		return $ci->gmail_transport->send($settings['sender'], $password, $recipient, $subject, $body, array('host' => $host, 'port' => $port, 'username' => $settings['smtp_username']));
	}
	public function resolve($host)
	{
		$addresses = gethostbynamel($host);
		if ( ! $addresses) { throw new RuntimeException('SMTP DNS unavailable.'); }
		foreach ($addresses as $ip) { if ( ! $this->public_address($ip)) { throw new RuntimeException('SMTP requires public IPv4.'); } }
		return $addresses[0];
	}
}
