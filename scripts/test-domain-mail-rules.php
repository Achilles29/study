<?php
define('BASEPATH', __DIR__);
require dirname(__DIR__).'/application/libraries/Domain_mail_transport.php';
$transport = new Domain_mail_transport();
foreach (array('127.0.0.1', '10.0.0.1', '169.254.169.254', '100.64.0.1', '192.168.1.1', '172.16.0.1', '0.0.0.0', '198.18.0.1', '192.0.2.1', '198.51.100.1', '203.0.113.1', '224.0.0.1', '255.255.255.255', '::1', '::ffff:127.0.0.1') as $ip)
{
	if ($transport->public_address($ip)) { throw new RuntimeException('Non-public address accepted: '.$ip); }
}
if ( ! $transport->public_address('8.8.8.8') || ! $transport->valid_settings('mail.example.com', '465', 'sender@example.com') || ! $transport->valid_settings('mail.example.com', '587', 'sender')) { throw new RuntimeException('Valid settings rejected.'); }
try { $transport->resolve('localhost'); throw new LogicException('Loopback DNS accepted.'); } catch (RuntimeException $expected) { /* No connection made. */ }
echo "PASS SMTP hostname/port and public IPv4 rules; loopback resolution refused without network connection.\n";
