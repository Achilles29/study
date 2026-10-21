<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Settings_model extends CI_Model
{
	public function public_values()
	{
		$this->load->library('site_settings');
		$debug = $this->db->db_debug; $this->db->db_debug = FALSE;
		try { return $this->get_section('umum')['values']; }
		catch (Throwable $error)
		{
			log_message('error', 'Public site settings unavailable; using bundled identity.');
			return $this->site_settings->defaults('umum');
		}
		finally { $this->db->db_debug = $debug; }
	}

	public function get_section($section)
	{
		$this->load->library('site_settings');
		$query = $this->db->query('SELECT * FROM st_site_settings WHERE section=?', array($section));
		if ($query === FALSE || ! ($row = $query->row_array())) { throw new RuntimeException('Settings unavailable.'); }
		$stored = json_decode($row['values_json'], TRUE);
		if ( ! is_array($stored)) { throw new RuntimeException('Settings invalid.'); }
		$row['values'] = array_replace($this->site_settings->defaults($section), $stored);
		unset($row['values_json']);
		return $row;
	}

	public function save_section($section, $input, $revision, $actor_id, $auth_stamp = NULL)
	{
		$this->load->library(array('site_settings', 'study_transaction'));
		if ( ! isset($this->site_settings->definitions()[$section])) { return array('ok' => FALSE, 'code' => 400); }
		$validated = $this->site_settings->validate($section, $input);
		if ($validated['errors']) { return array('ok' => FALSE, 'code' => 400); }
		return $this->study_transaction->run($this->db, function () use ($section, $validated, $revision, $actor_id, $auth_stamp) {
			$actor = $this->db->query("SELECT u.* FROM st_users u JOIN st_user_roles ur ON ur.user_id=u.id JOIN st_roles r ON r.id=ur.role_id WHERE u.id=? AND u.status='active' AND r.role_code='system_admin' FOR UPDATE", array($actor_id));
			if ($actor === FALSE) { throw new RuntimeException('Actor unavailable.'); }
			if ( ! $actor->num_rows()) { return array('ok' => FALSE, 'code' => 403); }
			// HTTP callers must pass the credential stamp checked at request entry.
			// CLI maintenance remains role-checked without a browser session.
			if (($auth_stamp !== NULL || PHP_SAPI !== 'cli') && (!is_string($auth_stamp) || !hash_equals(study_credential_stamp($actor->row_array()),$auth_stamp))) { return array('ok'=>FALSE,'code'=>403); }
			$query = $this->db->query('SELECT * FROM st_site_settings WHERE section=? FOR UPDATE', array($section));
			if ($query === FALSE || ! ($row = $query->row_array())) { throw new RuntimeException('Settings unavailable.'); }
			if ((string) $row['revision'] !== (string) $revision) { return array('ok' => FALSE, 'code' => 409); }
			$before = array_replace($this->site_settings->defaults($section), json_decode($row['values_json'], TRUE));
			if ($section === 'privasi' && $before['approved'] === '1' && $before['policy_version'] === $validated['values']['policy_version'])
			{
				foreach (array('policy_text','terms_text','detail_days','aggregate_days','children') as $field) { if ($before[$field] !== $validated['values'][$field]) { return array('ok' => FALSE, 'code' => 409); } }
			}
			$now = gmdate('Y-m-d H:i:s');
			if ( ! $this->db->where('id', $row['id'])->update('st_site_settings', array('values_json' => json_encode($validated['values']), 'revision' => (int) $row['revision'] + 1, 'updated_by_user_id' => $actor_id, 'updated_at' => $now))) { throw new RuntimeException('Settings not saved.'); }
			if ( ! $this->db->insert('st_audit_events', array('actor_user_id' => $actor_id, 'event_type' => 'settings.updated', 'entity_type' => 'settings', 'entity_id' => $row['id'], 'details_json' => json_encode(array('section' => $section, 'before' => $before, 'after' => $validated['values'], 'revision' => (int) $row['revision'] + 1)), 'created_at' => $now))) { throw new RuntimeException('Settings audit not saved.'); }
			return array('ok' => TRUE);
		});
	}
}
