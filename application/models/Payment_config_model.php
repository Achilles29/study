<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payment_config_model extends CI_Model
{
	public function latest($environment = 'sandbox')
	{
		$query = $this->db->query('SELECT id,environment,merchant_id,created_at,probe_attempted_at,probe_result,checkout_enabled FROM st_payment_configs WHERE environment=? ORDER BY id DESC LIMIT 1', array($environment));
		if ($query === FALSE) { throw new RuntimeException('Payment configuration unavailable.'); }
		return $query->row_array();
	}

	private function actor($actor_id, $stamp = NULL)
	{
		$query = $this->db->query("SELECT u.* FROM st_users u JOIN st_user_roles ur ON ur.user_id=u.id JOIN st_roles r ON r.id=ur.role_id WHERE u.id=? AND u.status='active' AND r.role_code='system_admin' FOR UPDATE", array($actor_id));
		if ($query === FALSE) { throw new RuntimeException('Payment actor unavailable.'); }
		$user = $query->row_array();
		return $user && ($stamp === NULL || (is_string($stamp) && hash_equals(study_credential_stamp($user), $stamp)));
	}

	private function audit($actor_id, $action, $id, $details)
	{
		if ( ! $this->db->insert('st_audit_events', array('actor_user_id' => $actor_id, 'event_type' => $action, 'entity_type' => 'payment_config', 'entity_id' => $id, 'details_json' => json_encode($details), 'created_at' => gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Payment settings audit unavailable.'); }
	}

	public function save($values, $previous_id, $actor_id, $stamp = NULL)
	{
		$errors = array();
		$environment = $values['environment'] ?? '';
		if ( ! in_array($environment, array('sandbox','production'), TRUE)) { return array('ok' => FALSE, 'code' => 400, 'errors' => array('environment' => 'Pilih Sandbox atau Production / Live.')); }
		$prefix = $environment === 'sandbox' ? 'SB-' : '';
		$enabled = $values['checkout_enabled'] ?? '0';
		if ( ! in_array($enabled, array('0','1'), TRUE) || ($environment === 'sandbox' && $enabled !== '0')) { return array('ok' => FALSE, 'code' => 400); }
		foreach (array('merchant_id', 'server_key', 'client_key') as $key)
		{
			// Clipboard padding is not part of a credential; internal whitespace remains invalid.
			if (is_string($values[$key] ?? NULL)) { $values[$key] = trim($values[$key], " \t\r\n"); }
		}
		if ( ! is_string($values['merchant_id'] ?? NULL) || ! preg_match('/\A[A-Za-z0-9_-]{1,80}\z/', $values['merchant_id']) || preg_match('/\A(?:SB-)?Mid-(?:server|client)-/', $values['merchant_id'])) { $errors['merchant_id'] = 'Isi Merchant ID dari dashboard Midtrans pada lingkungan yang dipilih → Settings → Access Keys, bukan Server Key atau Client Key.'; }
		foreach (array('server_key' => 'server', 'client_key' => 'client') as $key => $kind)
		{
			if ( ! is_string($values[$key] ?? NULL) || ($values[$key] !== '' && ! preg_match('#\A'.$prefix.'Mid-'.$kind.'-[A-Za-z0-9_.+/=-]{10,200}\z#', $values[$key]))) { $errors[$key] = ($kind === 'server' ? 'Server Key' : 'Client Key').' harus sesuai lingkungan '.($environment === 'sandbox' ? 'Sandbox' : 'Production / Live').' dan berawalan '.$prefix.'Mid-'.$kind.'-. Jangan menukar kedua kolom.'; }
		}
		if ($errors) { return array('ok' => FALSE, 'code' => 400, 'errors' => $errors); }
		$this->load->library(array('payment_secrets', 'study_transaction'));
		return $this->study_transaction->run($this->db, function () use ($values, $previous_id, $actor_id, $stamp, $environment, $enabled) {
			if (($environment === 'production' && $stamp === NULL) || ! $this->actor($actor_id, $stamp)) { return array('ok' => FALSE, 'code' => 403); }
			// A dedicated settings row serializes first insertion as well as later rotations.
			$this->db->query("SELECT id FROM st_site_settings WHERE section='umum' FOR UPDATE");
			$previous = $this->db->query('SELECT * FROM st_payment_configs WHERE environment=? ORDER BY id DESC LIMIT 1 FOR UPDATE', array($environment))->row_array();
			if ((string) ($previous['id'] ?? '0') !== (string) $previous_id) { return array('ok' => FALSE, 'code' => 409); }
			$secrets = $previous ? $this->payment_secrets->open($previous['credentials_cipher'], $environment) : array('server_key' => '', 'client_key' => '');
			if ($previous && $previous['merchant_id'] !== $values['merchant_id']) { $secrets['client_key'] = ''; }
			foreach (array('server_key', 'client_key') as $key) { if ($values[$key] !== '') { $secrets[$key] = $values[$key]; } }
			if ($secrets['server_key'] === '' || ($previous && $previous['merchant_id'] !== $values['merchant_id'] && $values['server_key'] === '')) { return array('ok' => FALSE, 'code' => 400, 'errors' => array('server_key' => 'Server Key sesuai lingkungan wajib diisi pada penyimpanan pertama atau saat mengganti Merchant ID. Merchant ID saja belum cukup.')); }
			$unchanged = $previous && $previous['merchant_id'] === $values['merchant_id'] && $values['server_key'] === '' && $values['client_key'] === '';
			if ($enabled === '1' && (($values['confirm_live'] ?? '') !== '1' || ! $unchanged || $previous['probe_result'] !== 'status_api_reached')) { return array('ok' => FALSE, 'code' => 400, 'errors' => array('checkout_enabled' => 'Simpan kunci dengan checkout nonaktif, uji koneksi, lalu aktifkan dengan konfirmasi. Saat aktivasi, kosongkan kedua kunci dan jangan mengganti merchant.')); }
			if ( ! $this->db->insert('st_payment_configs', array('environment' => $environment, 'merchant_id' => $values['merchant_id'], 'credentials_cipher' => $this->payment_secrets->seal($secrets, $environment), 'checkout_enabled' => (int) $enabled, 'probe_result' => $unchanged ? $previous['probe_result'] : NULL, 'probe_attempted_at' => $unchanged ? $previous['probe_attempted_at'] : NULL, 'created_by_user_id' => $actor_id, 'created_at' => gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Configuration not saved.'); }
			$id = $this->db->insert_id();
			$this->audit($actor_id, 'payment.config_saved', $id, array('previous_id' => $previous_id, 'server_key_changed' => $values['server_key'] !== '', 'client_key_changed' => $values['client_key'] !== '', 'environment' => $environment, 'checkout_enabled' => (int) $enabled));
			return array('ok' => TRUE, 'id' => $id);
		});
	}

	public function probe($id, $actor_id, $environment = 'sandbox', $stamp = NULL)
	{
		$this->load->library(array('payment_secrets', 'study_transaction', 'midtrans_probe'));
		$prepared = $this->study_transaction->run($this->db, function () use ($id, $actor_id, $environment, $stamp) {
			if (($environment === 'production' && $stamp === NULL) || ! $this->actor($actor_id, $stamp)) { return array('ok' => FALSE, 'code' => 403); }
			$row = $this->db->query('SELECT *,probe_attempted_at > UTC_TIMESTAMP() - INTERVAL 60 SECOND AS limited FROM st_payment_configs WHERE id=? AND environment=? FOR UPDATE', array($id, $environment))->row_array();
			if ( ! $row) { return array('ok' => FALSE, 'code' => 404); }
			if ($row['limited']) { return array('ok' => FALSE, 'code' => 409); }
			$secret = $this->payment_secrets->open($row['credentials_cipher'], $environment);
			$this->db->where('id', $id)->update('st_payment_configs', array('probe_attempted_at' => gmdate('Y-m-d H:i:s'), 'probe_result' => 'checking'));
			$this->audit($actor_id, 'payment.config_probe', $id, array('status' => 'started'));
			return array('ok' => TRUE, 'server_key' => $secret['server_key']);
		});
		if ( ! $prepared['ok']) { return $prepared; }
		try { $status = $this->midtrans_probe->check($prepared['server_key'], $environment); }
		catch (Throwable $error) { $status = 'unreachable'; }
		unset($prepared['server_key']);
		$result = $this->study_transaction->run($this->db, function () use ($id, $actor_id, $status) {
			$this->db->where('id', $id)->update('st_payment_configs', array('probe_result' => $status));
			$this->audit($actor_id, 'payment.config_probe', $id, array('status' => $status));
			return array('ok' => TRUE, 'status' => $status);
		});
		return $result;
	}
}
