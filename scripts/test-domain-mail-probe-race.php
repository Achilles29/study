<?php
$database = getenv('DB_DATABASE');
if (PHP_SAPI !== 'cli' || ! preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', (string) $database)) { exit(2); }
$_SERVER['argv'] = array('index.php', 'cli/migrate'); ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean();
$ci =& get_instance(); $ci->load->model(array('Mail_model', 'Account_model')); $user = $ci->Account_model->find_active_user(1);
// A provider edit during external SMTP work must not mark new credentials as tested.
$ci->db->query('UPDATE st_mail_deliveries SET created_at=UTC_TIMESTAMP()-INTERVAL 2 DAY');
$ci->domain_mail_transport = new class($ci, $user) {
	private $ci; private $user;
	public function __construct($ci, $user) { $this->ci = $ci; $this->user = $user; }
	public function send($settings, $password, $recipient, $subject, $body)
	{
		$revision = $this->ci->Mail_model->configuration()['revision'];
		$result = $this->ci->Mail_model->save(1, $this->user['auth_stamp'], array('provider' => 'gmail', 'sender_email' => 'projectpintar.qa@gmail.com', 'app_password' => '', 'enabled' => '0', 'revision' => (string) $revision));
		if ( ! $result['ok']) { throw new LogicException('Race edit failed.'); }
		return TRUE;
	}
};
$before = $ci->Mail_model->configuration();
if ($before['provider'] !== 'smtp') { throw new RuntimeException('Run after SMTP browser fixture.'); }
$result = $ci->Mail_model->request_message(1, $user['auth_stamp'], 'probe', '', '127.0.0.1', (string) $before['revision']);
$after = $ci->Mail_model->configuration();
if ( ! $result['ok'] || $after['provider'] !== 'gmail' || $after['probe_status'] !== 'untested' || $after['enabled']) { throw new RuntimeException('Late probe trusted for new settings.'); }
echo "PASS in-flight SMTP probe cannot activate/test a newly selected Gmail profile.\n";
