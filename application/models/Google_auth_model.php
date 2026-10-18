<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Google_auth_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct(); $this->load->library(array('google_oidc','study_transaction')); }
	private function rows($sql, $args = array())
	{
		$q = $this->db->query($sql, $args); if ($q === FALSE) { throw new RuntimeException('Google storage unavailable.'); } return $q->result_array(); }
	private function digest($value)
	{
		return hash_hmac('sha256', $value, (string) $this->config->item('encryption_key')); }
	private function actor($id, $stamp, $admin = FALSE)
	{
		$u = $this->rows("SELECT * FROM st_users WHERE id=? AND status='active' AND account_kind IN('adult','parent') FOR UPDATE", array($id))[0] ?? NULL;
		if ( ! $u || ! is_string($stamp) || ! hash_equals(study_credential_stamp($u), $stamp)) { return NULL; }
		if ($admin && ! $this->rows("SELECT ur.user_id FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.role_code='system_admin' FOR UPDATE", array($id))) { return NULL; } return $u;
	}
	private function audit($id, $event, $data = array())
	{
		$this->db->insert('st_audit_events', array('actor_user_id' => $id, 'event_type' => 'google.'.$event, 'entity_type' => 'user', 'entity_id' => $id, 'details_json' => json_encode($data), 'created_at' => gmdate('Y-m-d H:i:s'))); }
	public function registration_policy($lock = FALSE)
	{
		$row = $this->rows("SELECT values_json FROM st_site_settings WHERE section='privasi'".($lock ? ' FOR UPDATE' : ''))[0] ?? NULL;
		$v = $row ? json_decode($row['values_json'], TRUE) : array();
		return ($v['approved'] ?? '') === '1' && mb_strlen($v['policy_text'] ?? '') >= 200 && mb_strlen($v['terms_text'] ?? '') >= 200 && ! empty($v['policy_version']) ? array('version' => $v['policy_version'], 'hash' => hash('sha256', $row['values_json'])) : NULL;
	}
	public function config_public()
	{
		return $this->rows('SELECT client_id,enabled,registration_enabled,revision,tested_at,(secret_cipher IS NOT NULL) configured FROM st_google_config WHERE id=1')[0]; }
	public function linked($id)
	{
		return (bool) $this->rows('SELECT user_id FROM st_google_identities WHERE user_id=?', array($id)); }
	public function save($actor, $stamp, $input)
	{
		$input['registration_enabled'] = $input['registration_enabled'] ?? '0';
		if ( ! in_array($input['registration_enabled'], array('0','1'), TRUE) || ($input['registration_enabled'] === '1' && ($input['enabled'] ?? '') !== '1')) { return array('ok' => FALSE, 'code' => 400); }
		foreach (array('client_id','secret','enabled','revision') as $field) { if ( ! is_string($input[$field] ?? NULL)) { return array('ok' => FALSE, 'code' => 400); } }
		if ( ! preg_match('/\A[0-9]+-[A-Za-z0-9_-]+\.apps\.googleusercontent\.com\z/', $input['client_id']) || strlen($input['client_id']) > 200 || ($input['secret'] !== '' && ! preg_match('/\A[!-~]{10,300}\z/', $input['secret'])) || ! in_array($input['enabled'], array('0','1'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($actor, $stamp, $input) {
			$c = $this->rows('SELECT * FROM st_google_config WHERE id=1 FOR UPDATE')[0]; if ( ! $this->actor($actor, $stamp, TRUE)) { return array('ok' => FALSE, 'code' => 403); }
			if ((string) $c['revision'] !== $input['revision']) { return array('ok' => FALSE, 'code' => 409); }
			if ($input['registration_enabled'] === '1' && ! $this->registration_policy(TRUE)) { return array('ok' => FALSE, 'code' => 409); }
			$changed = $c['client_id'] !== $input['client_id'] || $input['secret'] !== '';
			if (($c['client_id'] !== $input['client_id'] || ! $c['secret_cipher']) && $input['secret'] === '') { return array('ok' => FALSE, 'code' => 400); }
			if ($input['enabled'] === '1' && ($changed || ! $c['tested_at'])) { return array('ok' => FALSE, 'code' => 409); }
			$this->db->where('id', 1)->update('st_google_config', array('client_id' => $input['client_id'], 'secret_cipher' => $input['secret'] === '' ? $c['secret_cipher'] : $this->google_oidc->seal($input['secret']), 'enabled' => (int) $input['enabled'], 'registration_enabled' => (int) $input['registration_enabled'], 'revision' => (int) $c['revision'] + 1, 'tested_at' => $changed ? NULL : $c['tested_at'], 'updated_at' => gmdate('Y-m-d H:i:s')));
			$this->audit($actor, 'configuration_saved', array('enabled' => $input['enabled'] === '1', 'secret_changed' => $input['secret'] !== '', 'registration_enabled' => $input['registration_enabled'] === '1')); return array('ok' => TRUE);
		});
	}
	public function begin($actor, $stamp, $purpose, $browser, $address)
	{
		if ( ! in_array($purpose, array('login','link','probe','register'), TRUE) || ! is_string($browser) || ! preg_match('/\A[a-f0-9]{64}\z/', $browser)) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($actor, $stamp, $purpose, $browser, $address) {
			$c = $this->rows('SELECT * FROM st_google_config WHERE id=1 FOR UPDATE')[0];
			if ( ! $c['secret_cipher'] || ($purpose !== 'probe' && ! $c['enabled'])) { return array('ok' => FALSE, 'code' => 403); }
			if ( ! in_array($purpose, array('login','register'), TRUE)) { $u = $this->actor($actor, $stamp, $purpose === 'probe'); if ( ! $u || ($purpose === 'link' && ! $u['email_verified_at'])) { return array('ok' => FALSE, 'code' => 403); } }
			elseif ($actor !== NULL) { return array('ok' => FALSE, 'code' => 403); }
			if ($purpose === 'register' && ( ! $c['registration_enabled'] || ! $this->registration_policy(TRUE))) { return array('ok' => FALSE, 'code' => 403); }
			// Expired temporary identity payloads are cleared, not retained indefinitely.
			$this->db->query('UPDATE st_google_flows SET registration_cipher=NULL WHERE expires_at<=UTC_TIMESTAMP() AND registration_cipher IS NOT NULL');
			$session_hash = $this->digest('google-browser|'.$browser); $address_hash = $this->digest('google-address|'.$address);
			if ((int) $this->rows('SELECT COUNT(*) n FROM st_google_flows WHERE session_hash=? AND created_at>=UTC_TIMESTAMP()-INTERVAL 15 MINUTE', array($session_hash))[0]['n'] >= 10 || (int) $this->rows('SELECT COUNT(*) n FROM st_google_flows WHERE address_hash=? AND created_at>=UTC_TIMESTAMP()-INTERVAL 15 MINUTE', array($address_hash))[0]['n'] >= 100) { return array('ok' => FALSE, 'code' => 409); }
			$state = bin2hex(random_bytes(32)); $nonce = bin2hex(random_bytes(32)); $verifier = bin2hex(random_bytes(32));
			$this->db->insert('st_google_flows', array('state_hash' => hash('sha256', $state), 'session_hash' => $session_hash, 'address_hash' => $address_hash, 'nonce_hash' => hash('sha256', $nonce), 'verifier_cipher' => $this->google_oidc->seal($verifier), 'user_id' => $actor, 'credential_stamp' => $stamp, 'purpose' => $purpose, 'config_revision' => $c['revision'], 'expires_at' => gmdate('Y-m-d H:i:s', time() + 600), 'created_at' => gmdate('Y-m-d H:i:s')));
			$url = 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(array('client_id' => $c['client_id'], 'response_type' => 'code', 'scope' => 'openid email', 'redirect_uri' => site_url('auth/google/callback'), 'state' => $state, 'nonce' => $nonce, 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, TRUE)), '+/', '-_'), '='), 'code_challenge_method' => 'S256', 'prompt' => 'select_account'), '', '&', PHP_QUERY_RFC3986);
			return array('ok' => TRUE, 'url' => $url);
		});
	}
	public function claim($state, $browser, $actor)
	{
		if ( ! is_string($state) || ! preg_match('/\A[a-f0-9]{64}\z/', $state) || ! is_string($browser)) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($state, $browser, $actor) {
			$c = $this->rows('SELECT * FROM st_google_config WHERE id=1 FOR UPDATE')[0]; $f = $this->rows('SELECT * FROM st_google_flows WHERE state_hash=? FOR UPDATE', array(hash('sha256', $state)))[0] ?? NULL;
			if ( ! $f || $f['used_at'] || strtotime($f['expires_at'].' UTC') <= time() || ! hash_equals($f['session_hash'], $this->digest('google-browser|'.$browser)) || (string) $f['user_id'] !== (string) $actor || (int) $f['config_revision'] !== (int) $c['revision'] || ($f['purpose'] !== 'probe' && ! $c['enabled'])) { return array('ok' => FALSE, 'code' => 400); }
			if ( ! in_array($f['purpose'], array('login','register'), TRUE) && ! $this->actor($actor, $f['credential_stamp'], $f['purpose'] === 'probe')) { return array('ok' => FALSE, 'code' => 403); }
			$this->db->where('state_hash', $f['state_hash'])->update('st_google_flows', array('used_at' => gmdate('Y-m-d H:i:s'), 'verifier_cipher' => NULL));
			return array('ok' => TRUE, 'flow' => $f, 'config' => $c);
		});
	}
	// Called only after signature/claims validation; never exposed as a route.
	public function complete($flow, $sub, $identity = NULL)
	{
		return $this->study_transaction->run($this->db, function () use ($flow, $sub, $identity) {
			$c = $this->rows('SELECT * FROM st_google_config WHERE id=1 FOR UPDATE')[0]; if ((int) $c['revision'] !== (int) $flow['config_revision'] || ($flow['purpose'] !== 'probe' && ! $c['enabled'])) { return array('ok' => FALSE, 'code' => 409); }
			$id = $flow['user_id'];
			if ( ! in_array($flow['purpose'], array('login','register'), TRUE)) { $u = $this->actor($id, $flow['credential_stamp'], $flow['purpose'] === 'probe'); if ( ! $u || ($flow['purpose'] === 'link' && ! $u['email_verified_at'])) { return array('ok' => FALSE, 'code' => 403); } }
			$hash = $this->digest('google-sub|'.$sub);
			if ($flow['purpose'] === 'register')
			{
				if ( ! $c['registration_enabled'] || ! $this->registration_policy(TRUE)) { return array('ok' => FALSE, 'code' => 403); }
				$email = is_string($identity['email'] ?? NULL) ? strtolower(trim($identity['email'])) : '';
				$hd = $identity['hd'] ?? NULL;
				$authoritative = substr($email, -10) === '@gmail.com' || (is_string($hd) && preg_match('/\A[a-z0-9][a-z0-9.-]*\.[a-z]{2,}\z/i', $hd));
				if (strlen($email) > 190 || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $authoritative) { return array('ok' => FALSE, 'code' => 400); }
				if ($this->rows('SELECT id FROM st_users WHERE email=? FOR UPDATE', array($email)) || $this->rows('SELECT user_id FROM st_google_identities WHERE sub_hash=? FOR UPDATE', array($hash))) { return array('ok' => FALSE, 'code' => 409); }
				$this->db->where('state_hash', $flow['state_hash'])->update('st_google_flows', array('registration_cipher' => $this->google_oidc->seal(json_encode(array('email' => $email, 'sub_hash' => $hash)))));
				return array('ok' => TRUE, 'purpose' => 'register', 'flow' => $flow['state_hash']);
			}
			if ($flow['purpose'] === 'probe') { $this->db->where('id', 1)->update('st_google_config', array('tested_at' => gmdate('Y-m-d H:i:s'))); $this->audit($id, 'probe_passed'); return array('ok' => TRUE, 'purpose' => 'probe'); }
			if ($flow['purpose'] === 'link')
			{
				if ($this->rows('SELECT user_id FROM st_google_identities WHERE user_id=? OR sub_hash=? FOR UPDATE', array($id, $hash))) { return array('ok' => FALSE, 'code' => 409); }
				$this->db->insert('st_google_identities', array('user_id' => $id, 'sub_hash' => $hash, 'created_at' => gmdate('Y-m-d H:i:s'))); $this->audit($id, 'linked'); return array('ok' => TRUE, 'purpose' => 'link');
			}
			$u = $this->rows("SELECT u.* FROM st_google_identities g JOIN st_users u ON u.id=g.user_id WHERE g.sub_hash=? AND u.status='active' AND u.account_kind IN('adult','parent') AND u.email_verified_at IS NOT NULL FOR UPDATE", array($hash))[0] ?? NULL;
			if ( ! $u) { return array('ok' => FALSE, 'code' => 403); }
			$this->db->where('id', $u['id'])->update('st_users', array('last_login_at' => gmdate('Y-m-d H:i:s'))); $this->audit($u['id'], 'login'); return array('ok' => TRUE, 'purpose' => 'login', 'id' => $u['id'], 'stamp' => study_credential_stamp($u));
		});
	}
	private function registration_flow($key, $browser, $lock = FALSE)
	{
		if ( ! is_string($key) || ! preg_match('/\A[a-f0-9]{64}\z/', $key) || ! is_string($browser)) { return NULL; }
		$f = $this->rows('SELECT f.* FROM st_google_flows f JOIN st_google_config c ON c.id=1 WHERE f.state_hash=? AND f.session_hash=? AND f.purpose=\'register\' AND f.used_at IS NOT NULL AND f.completed_at IS NULL AND f.registration_cipher IS NOT NULL AND f.expires_at>UTC_TIMESTAMP() AND f.config_revision=c.revision AND c.enabled=1 AND c.registration_enabled=1'.($lock ? ' FOR UPDATE' : ''), array($key, $this->digest('google-browser|'.$browser)))[0] ?? NULL;
		return $f;
	}
	public function registration_email($key, $browser)
	{
		$f = $this->registration_flow($key, $browser);
		return $f && $this->registration_policy() ? json_decode($this->google_oidc->unseal($f['registration_cipher']), TRUE)['email'] : NULL;
	}
	public function register($key, $browser, $v)
	{
		foreach (array('username','display_name','account_kind','password','password_confirm','agree_terms','website') as $field) { if ( ! is_string($v[$field] ?? NULL)) { return array('ok' => FALSE, 'code' => 400); } }
		if ( ! preg_match('/\A[a-zA-Z0-9_-]{4,80}\z/', $v['username']) || ! mb_check_encoding($v['display_name'], 'UTF-8') || mb_strlen(trim($v['display_name'])) < 2 || mb_strlen(trim($v['display_name'])) > 120 || ! in_array($v['account_kind'], array('adult','parent'), TRUE) || ! study_valid_password($v['password'], 12) || $v['password'] !== $v['password_confirm'] || $v['agree_terms'] !== '1' || $v['website'] !== '') { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($key, $browser, $v) {
			$this->rows('SELECT id FROM st_google_config WHERE id=1 FOR UPDATE');
			$f = $this->registration_flow($key, $browser, TRUE); if ( ! $f) { return array('ok' => FALSE, 'code' => 409); }
			$policy = $this->registration_policy(TRUE);
			if ( ! $policy || ! is_string($v['policy_hash'] ?? NULL) || ! hash_equals($policy['hash'], $v['policy_hash'])) { return array('ok' => FALSE, 'code' => 409); }
			$identity = json_decode($this->google_oidc->unseal($f['registration_cipher']), TRUE);
			if ($this->rows('SELECT id FROM st_users WHERE username=? OR email=? FOR UPDATE', array(strtolower($v['username']), $identity['email'])) || $this->rows('SELECT user_id FROM st_google_identities WHERE sub_hash=? FOR UPDATE', array($identity['sub_hash']))) { return array('ok' => FALSE, 'code' => 409); }
			$now = gmdate('Y-m-d H:i:s');
			$user = array('username' => strtolower($v['username']), 'email' => $identity['email'], 'display_name' => trim($v['display_name']), 'account_kind' => $v['account_kind'], 'learning_stage' => $v['account_kind'] === 'parent' ? 'unset' : 'adult', 'password_hash' => password_hash($v['password'], PASSWORD_DEFAULT), 'status' => 'active', 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now, 'last_login_at' => $now, 'auth_version' => 0);
			$this->db->insert('st_users', $user); $id = (int) $this->db->insert_id(); $user['id'] = $id;
			$this->db->insert('st_google_identities', array('user_id' => $id, 'sub_hash' => $identity['sub_hash'], 'created_at' => $now));
			$this->db->where('state_hash', $key)->update('st_google_flows', array('completed_at' => $now, 'registration_cipher' => NULL));
			$this->audit($id, 'registered', array('account_kind' => $v['account_kind'], 'adult_confirmed' => TRUE, 'notice_version' => 'google-registration-039', 'policy_version' => $policy['version'], 'policy_hash' => $policy['hash'], 'contact_verified_by' => 'google_authoritative'));
			return array('ok' => TRUE, 'id' => $id, 'stamp' => study_credential_stamp($user));
		});
	}
	public function unlink($actor, $stamp)
	{
		return $this->study_transaction->run($this->db, function () use ($actor, $stamp) {
			$this->rows('SELECT id FROM st_google_config WHERE id=1 FOR UPDATE'); $u = $this->actor($actor, $stamp); if ( ! $u) { return array('ok' => FALSE, 'code' => 403); }
			$this->db->where('user_id', $actor)->delete('st_google_identities'); $this->db->where('id', $actor)->update('st_users', array('auth_version' => (int) $u['auth_version'] + 1)); $this->audit($actor, 'unlinked', array('sessions_revoked' => TRUE)); return array('ok' => TRUE);
		});
	}
}
