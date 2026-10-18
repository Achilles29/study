<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Mail_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct(); $this->load->library(array('mail_secrets', 'gmail_transport', 'domain_mail_transport', 'study_transaction'));
	}
	private function rows($sql, $bindings = array())
	{
		$q = $this->db->query($sql, $bindings); if ($q === FALSE) { throw new RuntimeException('Mail storage unavailable.'); } return $q->result_array();
	}
	private function digest($kind, $value)
	{
		return hash_hmac('sha256', $kind.'|'.$value, $this->config->item('encryption_key')); }
	private function binding($user)
	{
		return $this->digest('mail-binding', $user['id'].'|'.$user['password_hash'].'|'.$user['email'].((int) ($user['auth_version'] ?? 0) > 0 ? '|v'.(int) $user['auth_version'] : '')); }
	private function actor($id, $stamp = NULL, $admin = FALSE)
	{
		$user = $this->rows("SELECT * FROM st_users WHERE id=? AND status='active' AND account_kind<>'child' FOR UPDATE", array($id))[0] ?? NULL;
		if ( ! $user || ($stamp !== NULL && ! hash_equals(study_credential_stamp($user), $stamp))) { return NULL; }
		if ($admin && ! $this->rows("SELECT ur.user_id FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.role_code='system_admin'", array($id))) { return NULL; } return $user;
	}
	private function audit($actor, $event, $details = array())
	{
		if ( ! $this->db->insert('st_audit_events', array('actor_user_id' => $actor, 'event_type' => 'mail.'.$event, 'entity_type' => 'user', 'entity_id' => $actor, 'details_json' => json_encode($details), 'created_at' => gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Mail audit unavailable.'); }
	}
	public function configuration($provider = NULL)
	{
		$row = $this->rows('SELECT provider,sender_email,smtp_host,smtp_port,smtp_username,enabled,revision,probe_status,probe_at,updated_at,(credentials_cipher IS NOT NULL) configured FROM st_mail_config WHERE id=1')[0];
		$row['active_provider'] = $row['provider'];
		if (in_array($provider, array('gmail', 'smtp'), TRUE) && $provider !== $row['provider'])
		{
			$profile = $this->rows('SELECT provider,sender_email,smtp_host,smtp_port,smtp_username,probe_status,probe_at,(credentials_cipher IS NOT NULL) configured FROM st_mail_profiles WHERE provider=?', array($provider))[0];
			$row = array_merge($row, $profile, array('enabled' => 0));
		}
		return $row;
	}
	public function reauthenticate($actor, $stamp, $password, $address)
	{
		if ( ! study_valid_password($password)) { return array('ok' => FALSE, 'code' => 400); }
		$result = $this->study_transaction->run($this->db, function () use ($actor, $stamp, $password, $address) {
			$user = $this->actor($actor, $stamp); if ( ! $user) { return array('ok' => FALSE, 'code' => 403); }
			$identity = $this->digest('mail-reauth', (string) $actor); $ip = $this->digest('mail-reauth-ip', $address);
			if ((int) $this->rows('SELECT COUNT(*) n FROM st_login_failures WHERE attempted_at>=UTC_TIMESTAMP()-INTERVAL 15 MINUTE AND identity_hash=?', array($identity))[0]['n'] >= 10 || (int) $this->rows('SELECT COUNT(*) n FROM st_login_failures WHERE attempted_at>=UTC_TIMESTAMP()-INTERVAL 15 MINUTE AND address_hash=?', array($ip))[0]['n'] >= 50) { return array('ok' => FALSE, 'code' => 409); }
			$allowed = password_verify($password, $user['password_hash']);
			if ( ! $allowed && ! $this->db->insert('st_login_failures', array('identity_hash' => $identity, 'address_hash' => $ip, 'attempted_at' => gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Reauthentication not recorded.'); }
			return array('ok' => TRUE, 'allowed' => $allowed);
		});
		return $result['ok'] && ! $result['allowed'] ? array('ok' => FALSE, 'code' => 403) : $result;
	}
	public function save($actor, $stamp, $input)
	{
		foreach (array('sender_email', 'app_password', 'revision', 'enabled') as $field) { if ( ! is_string($input[$field] ?? NULL)) { return array('ok' => FALSE, 'code' => 400); } }
		$provider = $input['provider'] ?? 'gmail';
		if ( ! in_array($provider, array('gmail', 'smtp'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		$email = strtolower(trim($input['sender_email'])); $password = $provider === 'gmail' ? str_replace(' ', '', $input['app_password']) : $input['app_password'];
		if ( ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || preg_match('/[\r\n]/', $email) || ! ctype_digit($input['revision']) || ! in_array($input['enabled'], array('0', '1'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		$smtp = array('smtp_host' => '', 'smtp_port' => 587, 'smtp_username' => '');
		if ($provider === 'gmail')
		{
			if ( ! preg_match('/@(gmail\.com|googlemail\.com)\z/', $email) || ($password !== '' && ! preg_match('/\A[a-zA-Z0-9]{16}\z/', $password))) { return array('ok' => FALSE, 'code' => 400); }
		}
		else
		{
			foreach (array('smtp_host', 'smtp_port', 'smtp_username') as $field) { if ( ! is_string($input[$field] ?? NULL)) { return array('ok' => FALSE, 'code' => 400); } }
			$smtp = array('smtp_host' => strtolower(trim($input['smtp_host'])), 'smtp_port' => $input['smtp_port'], 'smtp_username' => trim($input['smtp_username']));
			if ( ! $this->domain_mail_transport->valid_settings($smtp['smtp_host'], $smtp['smtp_port'], $smtp['smtp_username']) || strlen($password) > 512 || preg_match('/[\x00-\x1f\x7f]/', $password)) { return array('ok' => FALSE, 'code' => 400); }
		}
		return $this->study_transaction->run($this->db, function () use ($actor, $stamp, $input, $email, $password, $provider, $smtp) {
			if ( ! $this->actor($actor, $stamp, TRUE)) { return array('ok' => FALSE, 'code' => 403); }
			$row = $this->rows('SELECT * FROM st_mail_config WHERE id=1 FOR UPDATE')[0]; if ((string) $row['revision'] !== $input['revision']) { return array('ok' => FALSE, 'code' => 409); }
			$profile = $this->rows('SELECT * FROM st_mail_profiles WHERE provider=? FOR UPDATE', array($provider))[0];
			$changed = $profile['sender_email'] !== $email || $password !== '';
			foreach ($smtp as $key => $value) { $changed = $changed || (string) $profile[$key] !== (string) $value; }
			if (($profile['sender_email'] !== $email || $profile['smtp_host'] !== $smtp['smtp_host'] || $profile['smtp_username'] !== $smtp['smtp_username'] || ! $profile['credentials_cipher']) && $password === '') { return array('ok' => FALSE, 'code' => 400); }
			$switching = $row['provider'] !== $provider;
			if ($input['enabled'] === '1' && ($switching || $changed || $profile['probe_status'] !== 'sent' || ($input['inbox_confirm'] ?? '') !== '1')) { return array('ok' => FALSE, 'code' => 400); }
			$profile = array_merge($profile, $smtp, array('sender_email' => $email));
			if ($password !== '') { $profile['credentials_cipher'] = $this->mail_secrets->seal($password); }
			if ($changed || $switching) { $profile['probe_status'] = 'untested'; $profile['probe_at'] = NULL; }
			$update = array_merge($profile, array('enabled' => $input['enabled'], 'revision' => (int) $row['revision'] + 1, 'updated_at' => gmdate('Y-m-d H:i:s')));
			if ( ! $this->db->where('provider', $provider)->update('st_mail_profiles', $profile)) { throw new RuntimeException('Mail profile not saved.'); }
			if ( ! $this->db->where('id', 1)->update('st_mail_config', $update)) { throw new RuntimeException('Mail settings not saved.'); }
			$this->audit($actor, 'settings_saved', array('provider' => $provider, 'revision' => $update['revision'], 'secret_changed' => $password !== '', 'enabled' => $input['enabled'] === '1')); return array('ok' => TRUE);
		});
	}
	public function request_message($actor, $stamp, $purpose, $email, $address, $revision = NULL)
	{
		if ( ! in_array($purpose, array('verify', 'probe', 'reset'), TRUE) || ! is_string($email) || strlen($email) > 190 || ($purpose !== 'probe' && ! filter_var($email, FILTER_VALIDATE_EMAIL))) { return array('ok' => FALSE, 'code' => 400); }
		$email = strtolower(trim($email)); $token = bin2hex(random_bytes(32)); $ip = $this->digest('mail-ip', $address);
		$prepared = $this->study_transaction->run($this->db, function () use ($actor, $stamp, $purpose, $email, $token, $ip, $revision) {
			$user = $actor ? $this->actor($actor, $stamp, $purpose === 'probe') : NULL;
			if ($purpose !== 'reset' && ! $user) { return array('ok' => FALSE, 'code' => 403); }
			$config = $this->rows('SELECT * FROM st_mail_config WHERE id=1 FOR UPDATE')[0];
			if ( ! $config['credentials_cipher'] || ($purpose !== 'probe' && ! $config['enabled'])) { return array('ok' => FALSE, 'code' => 403); }
			if ($purpose === 'probe' && (string) $config['revision'] !== $revision) { return array('ok' => FALSE, 'code' => 409); }
			// All send reservations serialize on the singleton; use current reads, not a stale snapshot.
			$recent = $this->rows('SELECT user_id,ip_hash,created_at FROM st_mail_deliveries WHERE created_at>=UTC_TIMESTAMP()-INTERVAL 1 DAY FOR UPDATE');
			$mine = 0; $network = 0; $hour = 0; $last = 0;
			foreach ($recent as $send) { $age = time() - strtotime($send['created_at'].' UTC'); if ($age < 3600) { $hour++; if ($send['ip_hash'] === $ip) { $network++; } if ($actor && (string) $send['user_id'] === (string) $actor) { $mine++; $last = max($last, strtotime($send['created_at'].' UTC')); } } }
			if (count($recent) >= 400 || $hour >= 80 || $network >= 30 || $mine >= 5 || ($last && time() - $last < 60)) { return array('ok' => FALSE, 'code' => 409); }
			$now = gmdate('Y-m-d H:i:s'); $token_id = NULL; $eligible = TRUE;
			if ($purpose === 'reset') { $eligible = $user && $user['email_verified_at'] && strtolower($user['email']) === $email; }
			if ($purpose === 'verify' && $this->rows('SELECT id FROM st_users WHERE email=? AND id<>?', array($email, $actor))) { return array('ok' => FALSE, 'code' => 400); }
			if ($purpose !== 'probe' && $eligible)
			{
				$type = $purpose === 'verify' ? 'email_verification' : 'password_reset';
				if ( ! $this->db->where('user_id', $actor)->where('token_type', $type)->where('used_at', NULL)->update('st_account_tokens', array('used_at' => $now))) { throw new RuntimeException('Token revocation failed.'); }
				if ( ! $this->db->insert('st_account_tokens', array('user_id' => $actor, 'token_type' => $type, 'token_hash' => hash('sha256', $token), 'expires_at' => gmdate('Y-m-d H:i:s', time() + ($purpose === 'verify' ? 1800 : 900)), 'created_at' => $now, 'email_snapshot' => $email, 'credential_stamp' => $this->binding($user), 'delivery_status' => 'pending'))) { throw new RuntimeException('Token not saved.'); } $token_id = $this->db->insert_id();
			}
			if ( ! $this->db->insert('st_mail_deliveries', array('user_id' => $actor ?: NULL, 'token_id' => $token_id, 'purpose' => $purpose, 'ip_hash' => $ip, 'status' => $eligible ? 'pending' : 'failed', 'created_at' => $now, 'updated_at' => $now))) { throw new RuntimeException('Send reservation failed.'); }
			$id = $this->db->insert_id(); if ($actor) { $this->audit($actor, 'send_requested', array('purpose' => $purpose, 'delivery' => $id)); }
			return array('ok' => TRUE, 'eligible' => $eligible, 'id' => $id, 'token_id' => $token_id, 'config_revision' => $config['revision'], 'provider' => $config['provider'], 'smtp_host' => $config['smtp_host'], 'smtp_port' => $config['smtp_port'], 'smtp_username' => $config['smtp_username'], 'sender' => $config['sender_email'], 'recipient' => $purpose === 'probe' ? $config['sender_email'] : $email, 'password' => $eligible ? $this->mail_secrets->open($config['credentials_cipher']) : '');
		});
		if ( ! $prepared['ok'] || ! $prepared['eligible']) { return $prepared['ok'] ? array('ok' => TRUE) : $prepared; }
		$body = $purpose === 'probe' ? 'Uji pengiriman Project Pintar. Pesan ini bukan verifikasi akun. Jika pesan tiba di kotak masuk, kembali ke pengaturan untuk mengaktifkan email.' : 'Permintaan '.($purpose === 'verify' ? 'verifikasi email' : 'pemulihan kata sandi')." Project Pintar.\n\n".site_url($purpose === 'verify' ? 'akun/verifikasi' : 'pulihkan-sandi').'#token='.$token."\n\nKode sekali pakai (jika tautan tidak bekerja): ".$token."\n\nBerlaku ".($purpose === 'verify' ? '30' : '15').' menit. Jika tidak meminta, abaikan email ini. Jangan bagikan kode kepada siapa pun.';
		$subject = 'Project Pintar - '.($purpose === 'probe' ? 'uji pengiriman' : ($purpose === 'verify' ? 'verifikasi email' : 'pemulihan kata sandi'));
		try { $sent = $prepared['provider'] === 'smtp' ? $this->domain_mail_transport->send($prepared, $prepared['password'], $prepared['recipient'], $subject, $body) : $this->gmail_transport->send($prepared['sender'], $prepared['password'], $prepared['recipient'], $subject, $body); } catch (Throwable $error) { $sent = FALSE; }
		unset($prepared['password'], $body, $token);
		$result = $this->study_transaction->run($this->db, function () use ($actor, $prepared, $purpose, $sent) {
			if ($actor) { $this->rows('SELECT id FROM st_users WHERE id=? FOR UPDATE', array($actor)); } $this->rows('SELECT id FROM st_mail_config WHERE id=1 FOR UPDATE'); $status = $sent ? 'sent' : 'failed';
			if ( ! $this->db->where('id', $prepared['id'])->update('st_mail_deliveries', array('status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Send result unavailable.'); }
			if ($prepared['token_id'] && ! $this->db->where('id', $prepared['token_id'])->update('st_account_tokens', array('delivery_status' => $status))) { throw new RuntimeException('Token result unavailable.'); }
			if ($purpose === 'probe' && ! $this->db->where('id', 1)->where('revision', $prepared['config_revision'])->update('st_mail_config', array('probe_status' => $status, 'probe_at' => gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Probe result unavailable.'); }
			if ($purpose === 'probe' && $this->db->affected_rows() === 1 && ! $this->db->where('provider', $prepared['provider'])->update('st_mail_profiles', array('probe_status' => $status, 'probe_at' => gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Profile probe unavailable.'); }
			if ($actor) { $this->audit($actor, 'send_finished', array('purpose' => $purpose, 'delivery' => $prepared['id'], 'status' => $status)); } return array('ok' => TRUE, 'sent' => $sent);
		});
		return $result['ok'] && ! $sent ? array('ok' => FALSE, 'code' => 503) : $result;
	}
	public function reset_request($email, $address)
	{
		if ( ! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) { return; }
		$user = $this->rows("SELECT id FROM st_users WHERE email=? AND status='active' AND account_kind<>'child'", array(strtolower(trim($email))))[0] ?? NULL;
		$this->request_message($user['id'] ?? NULL, NULL, 'reset', $email, $address);
	}
	public function consume($actor, $stamp, $token, $purpose, $password = NULL)
	{
		if ( ! is_string($token) || ! preg_match('/\A[a-f0-9]{64}\z/', $token) || ! in_array($purpose, array('verify', 'reset'), TRUE) || ($purpose === 'reset' && ! study_valid_password($password, 12))) { return array('ok' => FALSE, 'code' => 400); }
		$hash = hash('sha256', $token); $found = $this->rows('SELECT user_id FROM st_account_tokens WHERE token_hash=?', array($hash))[0] ?? NULL; if ( ! $found || ($purpose === 'verify' && (string) $found['user_id'] !== (string) $actor)) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($actor, $stamp, $hash, $purpose, $password, $found) {
			$user = $this->actor($found['user_id'], $purpose === 'verify' ? $stamp : NULL); if ( ! $user) { return array('ok' => FALSE, 'code' => 400); }
			$row = $this->rows('SELECT * FROM st_account_tokens WHERE token_hash=? FOR UPDATE', array($hash))[0];
			if ($row['token_type'] !== ($purpose === 'verify' ? 'email_verification' : 'password_reset') || $row['used_at'] || $row['delivery_status'] !== 'sent' || strtotime($row['expires_at'].' UTC') <= time() || ! is_string($row['credential_stamp']) || ! hash_equals($this->binding($user), $row['credential_stamp'])) { return array('ok' => FALSE, 'code' => 400); }
			if ($purpose === 'verify' && $this->rows('SELECT id FROM st_users WHERE email=? AND id<>?', array($row['email_snapshot'], $user['id']))) { return array('ok' => FALSE, 'code' => 400); }
			$now = gmdate('Y-m-d H:i:s'); $update = array('updated_at' => $now);
			if ($purpose === 'verify') { $update['email'] = $row['email_snapshot']; $update['email_verified_at'] = $now; }
			else { if ( ! $user['email_verified_at'] || ! study_valid_password($password, study_password_minimum($this->Account_model->find_active_user($user['id'])))) { return array('ok' => FALSE, 'code' => 400); } $update['password_hash'] = password_hash($password, PASSWORD_DEFAULT); }
			if ( ! $this->db->where('id', $user['id'])->update('st_users', $update) || ! $this->db->where('user_id', $user['id'])->where('used_at', NULL)->update('st_account_tokens', array('used_at' => $now))) { throw new RuntimeException('Account confirmation failed.'); }
			$this->audit($user['id'], $purpose === 'verify' ? 'email_verified' : 'password_reset', array('sessions_revoked' => $purpose === 'reset')); return array('ok' => TRUE);
		});
	}
}
