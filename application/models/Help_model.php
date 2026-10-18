<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Help_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->load->library('study_transaction');
	}
	public function categories()
	{
		return array('account' => 'Akun & pendaftaran', 'privacy' => 'Privasi & data keluarga', 'learning' => 'Pembelajaran', 'payment' => 'Pembayaran & akses', 'other' => 'Lainnya');
	}
	public function statuses()
	{
		return array('open' => 'Menunggu pengelola', 'waiting' => 'Sudah dibalas pengelola', 'closed' => 'Ditutup');
	}
	private function rows($sql, $args = array())
	{
		$query = $this->db->query($sql, $args);
		if ($query === FALSE) { throw new RuntimeException('Help storage unavailable.'); }
		return $query->result_array();
	}
	public function digest($value)
	{
		return hash_hmac('sha256', 'help|'.$value, (string) $this->config->item('encryption_key'));
	}
	private function valid_text($value, $min, $max)
	{
		return is_string($value) && mb_check_encoding($value, 'UTF-8') && mb_strlen(trim($value)) >= $min && mb_strlen($value) <= $max && ! preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value);
	}
	public function validation_errors($input, $reply = FALSE)
	{
		$errors = array();
		if ( ! $this->valid_text($input['body'] ?? NULL, $reply ? 5 : 20, 3000)) { $errors['body'] = 'Isi pesan '.($reply ? '5' : '20').'–3.000 karakter teks biasa, tanpa karakter kontrol.'; }
		if ( ! $reply)
		{
			if ( ! $this->valid_text($input['subject'] ?? NULL, 5, 120)) { $errors['subject'] = 'Isi judul 5–120 karakter teks biasa.'; }
			if ( ! is_string($input['category'] ?? NULL) || ! isset($this->categories()[$input['category']])) { $errors['category'] = 'Pilih topik yang tersedia.'; }
			if (($input['consent'] ?? '') !== '1') { $errors['consent'] = 'Konfirmasi kewenangan dan pemrosesan pesan diperlukan.'; }
		}
		return $errors;
	}
	private function actor($user, $admin)
	{
		if ($user === NULL) { return ! $admin; }
		$row = $this->rows("SELECT * FROM st_users WHERE id=? AND status='active' AND account_kind IN('adult','parent') FOR UPDATE", array($user['id']))[0] ?? NULL;
		if ( ! $row || ! hash_equals(study_credential_stamp($row), $user['auth_stamp'])) { return FALSE; }
		return ! $admin || (bool) $this->rows("SELECT ur.user_id FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.role_code='system_admin' FOR UPDATE", array($user['id']));
	}
	private function audit($user, $event, $id)
	{
		if ( ! $this->db->insert('st_audit_events', array('actor_user_id' => $user['id'] ?? NULL, 'event_type' => 'help.'.$event, 'entity_type' => 'help_ticket', 'entity_id' => $id, 'details_json' => $event === 'created' ? '{"contact_consent":"help-2026-10-18"}' : '{}', 'created_at' => gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Help audit unavailable.'); }
	}
	// One short lock serializes quotas and retries, including first requests from a new address.
	private function guard()
	{
		if ( ! $this->rows('SELECT id FROM st_help_guard WHERE id=1 FOR UPDATE')) { throw new RuntimeException('Help guard unavailable.'); }
	}
	private function limit($scope, $maximum)
	{
		$key = $this->digest('limit|'.$scope);
		$this->db->query('DELETE FROM st_help_limits WHERE expires_at<=UTC_TIMESTAMP()');
		$row = $this->rows('SELECT hits FROM st_help_limits WHERE bucket_hash=? FOR UPDATE', array($key))[0] ?? NULL;
		if ($row && (int) $row['hits'] >= $maximum) { return FALSE; }
		$this->db->query('INSERT INTO st_help_limits(bucket_hash,hits,expires_at) VALUES(?,1,UTC_TIMESTAMP()+INTERVAL 1 HOUR) ON DUPLICATE KEY UPDATE hits=hits+1', array($key));
		return TRUE;
	}
	private function allowed($ticket, $user, $grant, $admin)
	{
		return $ticket && ($admin || ($ticket['user_id'] !== NULL ? ($user && (int) $ticket['user_id'] === (int) $user['id']) : (is_string($grant) && hash_equals($ticket['access_hash'], $grant))));
	}
	public function create($user, $input, $request_hash, $secret, $address)
	{
		if ($this->validation_errors($input) || ($input['website'] ?? '') !== '') { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($user, $input, $request_hash, $secret, $address) {
			$this->guard();
			if ( ! $this->actor($user, FALSE)) { return array('ok' => FALSE, 'code' => 403); }
			$old = $this->rows('SELECT id,user_id,access_hash FROM st_help_tickets WHERE request_hash=? FOR UPDATE', array($request_hash))[0] ?? NULL;
			if ($old) { return $this->allowed($old, $user, hash('sha256', $secret), FALSE) ? array('ok' => TRUE, 'id' => $old['id']) : array('ok' => FALSE, 'code' => 404); }
			if ( ! $this->limit('new|'.$address, 10)) { return array('ok' => FALSE, 'code' => 409); }
			$now = gmdate('Y-m-d H:i:s');
			$this->db->insert('st_help_tickets', array('user_id' => $user['id'] ?? NULL, 'access_hash' => $user ? NULL : hash('sha256', $secret), 'request_hash' => $request_hash, 'category' => $input['category'], 'subject' => trim($input['subject']), 'created_at' => $now, 'updated_at' => $now));
			$id = $this->db->insert_id();
			$this->db->insert('st_help_messages', array('ticket_id' => $id, 'actor_user_id' => $user['id'] ?? NULL, 'body' => trim($input['body']), 'request_hash' => $request_hash, 'created_at' => $now));
			$this->audit($user, 'created', $id);
			return array('ok' => TRUE, 'id' => $id);
		});
	}
	public function recover($code, $address)
	{
		return $this->study_transaction->run($this->db, function () use ($code, $address) {
			$this->guard();
			if ( ! $this->limit('recover|'.$address, 30)) { return array('ok' => TRUE, 'found' => FALSE); }
			// Failed attempts also commit their quota. Never log or put the code in URLs.
			if ( ! is_string($code) || ! preg_match('/\A([1-9][0-9]{0,17})\.([a-f0-9]{64})\z/', trim($code), $match)) { return array('ok' => TRUE, 'found' => FALSE); }
			$row = $this->rows('SELECT id,access_hash FROM st_help_tickets WHERE id=? AND user_id IS NULL', array($match[1]))[0] ?? NULL;
			$hash = hash('sha256', $match[2]);
			return $row && hash_equals($row['access_hash'], $hash) ? array('ok' => TRUE, 'found' => TRUE, 'id' => $row['id'], 'grant' => $hash) : array('ok' => TRUE, 'found' => FALSE);
		});
	}
	public function thread($id, $user, $grant, $admin, $page)
	{
		return $this->study_transaction->run($this->db, function () use ($id, $user, $grant, $admin, $page) {
			if ( ! $this->actor($user, $admin)) { return array('ok' => FALSE, 'code' => 403); }
			$ticket = $this->rows('SELECT * FROM st_help_tickets WHERE id=?', array($id))[0] ?? NULL;
			if ( ! $this->allowed($ticket, $user, $grant, $admin)) { return array('ok' => FALSE, 'code' => 404); }
			if ($admin) { $this->audit($user, 'viewed', $id); }
			$total = (int) $this->rows('SELECT COUNT(*) n FROM st_help_messages WHERE ticket_id=?', array($id))[0]['n'];
			$page = min(max(1, (int) $page), max(1, (int) ceil($total / 20)));
			$messages = $this->rows('SELECT is_admin,body,created_at FROM st_help_messages WHERE ticket_id=? ORDER BY id DESC LIMIT 20 OFFSET '.(($page - 1) * 20), array($id));
			unset($ticket['access_hash'], $ticket['request_hash']);
			return array('ok' => TRUE, 'ticket' => $ticket, 'messages' => array_reverse($messages), 'page' => $page, 'pages' => max(1, (int) ceil($total / 20)));
		});
	}
	public function reply($id, $user, $grant, $admin, $input, $request_hash, $address)
	{
		if ( ! $this->valid_text($input['body'] ?? NULL, 5, 3000) || ! is_string($input['revision'] ?? NULL) || ! preg_match('/\A[1-9][0-9]{0,9}\z/', $input['revision']) || ($admin && ! in_array($input['status'] ?? NULL, array('open','waiting','closed'), TRUE))) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($id, $user, $grant, $admin, $input, $request_hash, $address) {
			$this->guard();
			if ( ! $this->actor($user, $admin)) { return array('ok' => FALSE, 'code' => 403); }
			$ticket = $this->rows('SELECT * FROM st_help_tickets WHERE id=? FOR UPDATE', array($id))[0] ?? NULL;
			if ( ! $this->allowed($ticket, $user, $grant, $admin)) { return array('ok' => FALSE, 'code' => 404); }
			$old = $this->rows('SELECT ticket_id,actor_user_id,is_admin FROM st_help_messages WHERE request_hash=?', array($request_hash))[0] ?? NULL;
			if ($old) { return array('ok' => (int) $old['ticket_id'] === (int) $id && (int) $old['actor_user_id'] === (int) ($user['id'] ?? 0) && (bool) $old['is_admin'] === $admin, 'code' => 409); }
			if ((string) $ticket['revision'] !== $input['revision'] || ( ! $admin && $ticket['status'] === 'closed')) { return array('ok' => FALSE, 'code' => 409); }
			if ( ! $admin && ( ! $this->limit('reply|'.$address, 60) || ! $this->limit('ticket|'.$id, 20))) { return array('ok' => FALSE, 'code' => 409); }
			$now = gmdate('Y-m-d H:i:s');
			$this->db->insert('st_help_messages', array('ticket_id' => $id, 'actor_user_id' => $user['id'] ?? NULL, 'is_admin' => (int) $admin, 'body' => trim($input['body']), 'request_hash' => $request_hash, 'created_at' => $now));
			$this->db->where('id', $id)->update('st_help_tickets', array('status' => $admin ? $input['status'] : 'open', 'revision' => (int) $ticket['revision'] + 1, 'updated_at' => $now));
			$this->audit($user, $admin ? 'answered' : 'replied', $id);
			return array('ok' => TRUE);
		});
	}
	public function tickets($user_id, $status, $page)
	{
		$where = $user_id === NULL ? '1=1' : 'user_id='.(int) $user_id;
		$args = array();
		if (isset($this->statuses()[$status])) { $where .= ' AND status=?'; $args[] = $status; }
		$total = (int) $this->rows('SELECT COUNT(*) n FROM st_help_tickets WHERE '.$where, $args)[0]['n'];
		$page = min(max(1, (int) $page), max(1, (int) ceil($total / 20)));
		return array('tickets' => $this->rows('SELECT id,subject,category,status,updated_at FROM st_help_tickets WHERE '.$where.' ORDER BY updated_at DESC,id DESC LIMIT 20 OFFSET '.(($page - 1) * 20), $args), 'page' => $page, 'pages' => max(1, (int) ceil($total / 20)));
	}
}
