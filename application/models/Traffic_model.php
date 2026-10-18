<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Traffic_model extends CI_Model
{
	public function pages()
	{
		return array('home/index' => 'Beranda publik', 'catalog/index' => 'Katalog kursus', 'catalog/course' => 'Detail kursus', 'dashboard/index' => 'Dashboard peserta', 'arena/index' => 'Arena Belajar', 'arena/game' => 'Pilihan game', 'arena/pick' => 'Detail aktivitas', 'arena/run' => 'Aktivitas Arena', 'arena/english' => 'Kelas Inggris', 'arena/english_run' => 'Aktivitas Inggris', 'arena/quest' => 'Peta Quest', 'arena/quest_run' => 'Aktivitas Quest', 'arena/history' => 'Riwayat Arena', 'battle/index' => 'Battle Belajar', 'battle/directory' => 'Katalog battle umum', 'battle/public_room' => 'Informasi battle umum', 'catalog/lesson' => 'Materi belajar', 'family/children' => 'Daftar anak');
	}
	private function rows($sql, $params = array())
	{
		$debug = $this->db->db_debug; $this->db->db_debug = FALSE;
		try { $q = $this->db->query($sql, $params); if ($q === FALSE) { throw new RuntimeException('Traffic data unavailable.'); } return $q->result_array(); }
		finally { $this->db->db_debug = $debug; }
	}
	public function page_options()
	{
		$pages = $this->pages(); $pages['arena/choose'] = 'Detail aktivitas';
		foreach ($this->rows('SELECT DISTINCT page_key FROM st_traffic_events ORDER BY page_key LIMIT 500') as $r)
		{
			$parts = explode(':', $r['page_key'], 2); if (isset($pages[$parts[0]]) && count($parts) === 2) { $pages[$r['page_key']] = $pages[$parts[0]].' · '.$parts[1]; }
		}
		return $pages;
	}
	public function settings()
	{
		$this->load->model('Settings_model'); return $this->Settings_model->get_section('privasi')['values'];
	}
	public function health()
	{
		return $this->rows('SELECT * FROM st_traffic_state WHERE id=1')[0]; }
	private function digest($text)
	{
		return hash_hmac('sha256', 'traffic|'.$text, (string) $this->config->item('encryption_key')); }
	private function cookie($name, $value, $expires)
	{
		setcookie($name, $value, array('expires' => $expires, 'path' => '/', 'secure' => (bool) $this->config->item('cookie_secure'), 'httponly' => TRUE, 'samesite' => 'Lax'));
	}
	private function read_cookie($name)
	{
		$value = $this->input->cookie($name, FALSE); if ( ! is_string($value) || strlen($value) > 2000 || substr_count($value, '.') !== 1) { return NULL; }
		list($payload, $signature) = explode('.', $value); if ( ! hash_equals($this->digest($name.'|'.$payload), $signature)) { return NULL; }
		$decoded = json_decode(base64_decode($payload, TRUE) ?: '', TRUE); return is_array($decoded) ? $decoded : NULL;
	}
	private function write_cookie($name, $values, $expires)
	{
		$payload = base64_encode(json_encode($values)); $this->cookie($name, $payload.'.'.$this->digest($name.'|'.$payload), $expires);
	}
	public function clear_identity()
	{
		$this->cookie('pp_traffic', '', time() - 3600); }
	public function consent($user, $settings)
	{
		if ( ! $user) { $c = $this->read_cookie('pp_traffic_consent'); return $c && ($c['version'] ?? '') === $settings['policy_version'] && ($c['enabled'] ?? 0) === 1 && ($c['expires'] ?? 0) > time(); }
		$c = $this->rows('SELECT * FROM st_traffic_consents WHERE user_id=?', array($user['id']))[0] ?? NULL;
		if ( ! $c || ! $c['enabled'] || $c['policy_version'] !== $settings['policy_version']) { return FALSE; }
		if ($user['account_kind'] !== 'child') { return (int) $c['actor_user_id'] === (int) $user['id']; }
		return $settings['children'] === '1' && (bool) $this->rows("SELECT l.id FROM st_family_links l JOIN st_users p ON p.id=l.parent_user_id WHERE l.parent_user_id=? AND l.child_user_id=? AND l.status='active' AND p.status='active' AND p.account_kind='parent' AND p.email_verified_at IS NOT NULL", array($c['actor_user_id'], $user['id']));
	}
	public function save_consent($actor, $stamp, $target, $version, $enabled)
	{
		if ( ! is_string($version) || ! in_array($enabled, array('0','1'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		$s = $this->settings(); if ($version !== $s['policy_version'] || ($enabled === '1' && ($s['approved'] !== '1' || $s['enabled'] !== '1'))) { return array('ok' => FALSE, 'code' => 409); }
		if ($actor === NULL)
		{
			if ((int) $target !== 0) { return array('ok' => FALSE, 'code' => 403); }
			$this->write_cookie('pp_traffic_consent', array('version' => $version, 'enabled' => (int) $enabled, 'expires' => time() + 2592000), time() + 2592000); $this->clear_identity(); return array('ok' => TRUE);
		}
		$this->load->library('study_transaction');
		$result = $this->study_transaction->run($this->db, function () use ($actor, $stamp, $target, $version, $enabled) {
			$u = $this->rows("SELECT * FROM st_users WHERE id=? AND status='active' AND account_kind IN('adult','parent') FOR UPDATE", array($actor))[0] ?? NULL;
			if ( ! $u || ! is_string($stamp) || ! hash_equals(study_credential_stamp($u), $stamp)) { return array('ok' => FALSE, 'code' => 403); }
			$s = $this->settings(); if ($s['policy_version'] !== $version || ($enabled === '1' && ($s['approved'] !== '1' || $s['enabled'] !== '1'))) { return array('ok' => FALSE, 'code' => 409); }
			if ((int) $target !== (int) $actor)
			{
				if ($u['account_kind'] !== 'parent' || ($enabled === '1' && ( ! $u['email_verified_at'] || $s['children'] !== '1')) || ! $this->rows("SELECT l.id FROM st_family_links l JOIN st_users c ON c.id=l.child_user_id WHERE l.parent_user_id=? AND l.child_user_id=? AND l.status='active' AND c.status='active' AND c.account_kind='child' FOR UPDATE", array($actor, $target))) { return array('ok' => FALSE, 'code' => 403); }
			}
			$now = gmdate('Y-m-d H:i:s');
			$this->db->query('INSERT INTO st_traffic_consents(user_id,actor_user_id,policy_version,enabled,updated_at) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE actor_user_id=VALUES(actor_user_id),policy_version=VALUES(policy_version),enabled=VALUES(enabled),updated_at=VALUES(updated_at)', array($target, $actor, $version, (int) $enabled, $now));
			$this->db->insert('st_audit_events', array('actor_user_id' => $actor, 'event_type' => 'privacy.traffic_consent', 'entity_type' => 'user', 'entity_id' => $target, 'details_json' => json_encode(array('policy_version' => $version, 'enabled' => $enabled === '1')), 'created_at' => $now)); return array('ok' => TRUE);
		});
		if ($result['ok']) { $this->clear_identity(); } return $result;
	}
	public function record($view, $user, $data = array())
	{
		$debug = $this->db->db_debug; $this->db->db_debug = FALSE;
		try
		{
			if (( ! isset($this->pages()[$view]) && $view !== 'arena/choose') || $this->input->method(TRUE) !== 'GET' || $this->input->is_ajax_request() || http_response_code() >= 300 || $this->db->trans_active()) { return; }
			$destination = $this->input->get_request_header('Sec-Fetch-Dest'); if ($destination !== NULL && $destination !== 'document') { return; }
			if (preg_match('/bot|crawler|spider|headless|preview|curl|wget/i', (string) $this->input->user_agent())) { return; }
			if ($user && array_intersect(array('system_admin','content_editor','finance_operator','support_agent'), $user['roles'])) { $this->clear_identity(); return; }
			$s = $this->settings(); if ($s['approved'] !== '1' || $s['enabled'] !== '1' || ! $this->consent($user, $s)) { $this->clear_identity(); return; }
			$now = time(); $context = $this->digest('context|'.($user ? 'user:'.$user['id'] : 'guest').'|'.$s['policy_version']); $c = $this->read_cookie('pp_traffic');
			if ( ! $c || ($c['context'] ?? '') !== $context || ($c['expires'] ?? 0) <= $now || ! preg_match('/\A[a-f0-9]{32}\z/', $c['visitor'] ?? '')) { $c = array('context' => $context, 'visitor' => bin2hex(random_bytes(16)), 'expires' => $now + 2592000, 'last' => 0); }
			if (($c['last'] ?? 0) < $now - 1800 || ! preg_match('/\A[a-f0-9]{32}\z/', $c['visit'] ?? '')) { $c['visit'] = bin2hex(random_bytes(16)); } $c['last'] = $now;
			$page_key = $view;
			foreach (array('catalog/course' => 'course', 'catalog/lesson' => 'lesson', 'arena/choose' => 'pack') as $allowed_view => $source)
			{
				if ($view === $allowed_view && isset($data[$source]['id']) && is_scalar($data[$source]['id']) && ctype_digit((string) $data[$source]['id'])) { $page_key .= ':'.(int) $data[$source]['id']; }
			}
			if ($view === 'arena/game' && in_array($data['type'] ?? '', array('memory','word','math','expedition','story','flashcard','english','quest'), TRUE)) { $page_key .= ':'.$data['type']; }
			$aggregate_expiry = (new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta')))->modify('+'.(int) $s['aggregate_days'].' days')->format('Y-m-d');
			if ( ! $this->db->insert('st_traffic_events', array('created_at' => gmdate('Y-m-d H:i:s'), 'expires_at' => gmdate('Y-m-d H:i:s', $now + (int) $s['detail_days'] * 86400), 'aggregate_expires_on' => $aggregate_expiry, 'policy_version' => $s['policy_version'], 'page_key' => $page_key, 'user_id' => $user['id'] ?? NULL, 'account_kind' => $user['account_kind'] ?? 'guest', 'learning_stage' => $user['learning_stage'] ?? 'unset', 'visitor_hash' => $this->digest('visitor|'.$c['visitor']), 'visit_hash' => $this->digest('visit|'.$c['visit'])))) { throw new RuntimeException('Traffic event not recorded.'); }
			$this->db->query('UPDATE st_traffic_state SET first_event_at=COALESCE(first_event_at,(SELECT MIN(created_at) FROM st_traffic_events)),last_event_at=UTC_TIMESTAMP() WHERE id=1');
			$this->write_cookie('pp_traffic', $c, $c['expires']); $this->output->set_header('Cache-Control: private, no-store')->set_header('Vary: Cookie', FALSE);
		}
		catch (Throwable $error)
		{
			log_message('error', 'Traffic recording unavailable; page remains accessible. Coverage may be incomplete.');
			try { $this->db->query('UPDATE st_traffic_state SET failures=failures+1,last_failure_at=UTC_TIMESTAMP() WHERE id=1'); } catch (Throwable $ignored) { /* Service log is the fallback when the database itself is unavailable. */ }
		}
		finally { $this->db->db_debug = $debug; }
	}
	public function report($filters, $tab, $page)
	{
		$where = ' FROM st_traffic_events e LEFT JOIN st_users u ON u.id=e.user_id WHERE e.created_at>=? AND e.created_at<?'; $params = array($filters['start_utc'], $filters['end_utc']);
		if ($filters['audience'] !== '') { $where .= ' AND e.account_kind=?'; $params[] = $filters['audience']; }
		if (($filters['stage'] ?? '') !== '') { $where .= ' AND e.learning_stage=?'; $params[] = $filters['stage']; }
		if ($filters['page_key'] !== '') { $where .= ' AND e.page_key=?'; $params[] = $filters['page_key']; }
		if ($filters['user_id'] !== '') { $where .= ' AND e.user_id=?'; $params[] = (int) $filters['user_id']; }
		$summary = $this->rows('SELECT COUNT(*) views,COUNT(DISTINCT e.visitor_hash) visitors,COUNT(DISTINCT e.visit_hash) visits,COUNT(DISTINCT e.user_id) users,MIN(e.created_at) first_event,MAX(e.created_at) last_event'.$where, $params)[0];
		$daily = $this->rows("SELECT DATE(e.created_at+INTERVAL 7 HOUR) day_wib,COUNT(*) views".$where.' GROUP BY day_wib ORDER BY day_wib', $params);
		if ($tab === 'halaman') { $group = ' GROUP BY e.page_key'; $select = 'e.page_key'; }
		else { $where .= ' AND e.user_id IS NOT NULL'; $group = ' GROUP BY e.user_id,u.display_name'; $select = "e.user_id,COALESCE(u.display_name,'Akun tidak tersedia') display_name"; }
		$total = (int) $this->rows('SELECT COUNT(*) n FROM (SELECT '.$select.$where.$group.') grouped', $params)[0]['n'];
		$rows = $tab === 'ringkasan' ? array() : $this->rows('SELECT '.$select.',COUNT(*) views,COUNT(DISTINCT e.visit_hash) visits,COUNT(DISTINCT e.visitor_hash) visitors,COUNT(DISTINCT e.page_key) pages,COUNT(DISTINCT e.user_id) users,MAX(e.created_at) last_event'.$where.$group.' ORDER BY last_event DESC,'.($tab === 'halaman' ? 'e.page_key' : 'e.user_id').' LIMIT 20 OFFSET '.(((int) $page - 1) * 20), $params);
		$timeline = $filters['user_id'] === '' ? array() : $this->rows('SELECT e.created_at,e.page_key'.$where.' ORDER BY e.created_at DESC,e.id DESC LIMIT 20 OFFSET '.(((int) $page - 1) * 20), $params);
		return compact('summary','daily','rows','total','timeline');
	}
	public function retention($apply = FALSE, $version = '', $scheduled = FALSE)
	{
		$s = $this->settings(); $cutoff = gmdate('Y-m-d H:i:s');
		$count = (int) $this->rows('SELECT COUNT(*) n FROM st_traffic_events WHERE expires_at<=?', array($cutoff))[0]['n'];
		if ( ! $apply) { return array('ok' => TRUE, 'events_to_aggregate' => $count, 'policy_version' => $s['policy_version']); }
		if ( ! $scheduled && ($s['approved'] !== '1' || ! hash_equals($s['policy_version'], $version))) { return array('ok' => FALSE, 'code' => 403); }
		$this->load->library('study_transaction');
		return $this->study_transaction->run($this->db, function () use ($cutoff, $s, $scheduled) {
			// Lock the settings row to serialize retention workers; never run a destructive batch on a page view.
			$this->rows("SELECT id FROM st_site_settings WHERE section='privasi' FOR UPDATE");
			$current = $this->settings(); if ( ! $scheduled && ($current['approved'] !== '1' || $current['policy_version'] !== $s['policy_version'])) { return array('ok' => FALSE, 'code' => 409); }
			$ids = $this->rows('SELECT id FROM st_traffic_events WHERE expires_at<=? ORDER BY id LIMIT 10000 FOR UPDATE', array($cutoff));
			if ($ids)
			{
				$max = (int) end($ids)['id'];
				$this->db->query("INSERT INTO st_traffic_daily(day_wib,page_key,account_kind,views,expires_on) SELECT DATE(created_at+INTERVAL 7 HOUR),page_key,account_kind,COUNT(*),MIN(aggregate_expires_on) FROM st_traffic_events WHERE expires_at<=? AND id<=? GROUP BY DATE(created_at+INTERVAL 7 HOUR),page_key,account_kind ON DUPLICATE KEY UPDATE views=views+VALUES(views),expires_on=LEAST(expires_on,VALUES(expires_on))", array($cutoff,$max));
				$this->db->query('DELETE FROM st_traffic_events WHERE expires_at<=? AND id<=?', array($cutoff,$max));
			}
			$day = (new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d'); $this->db->query('DELETE FROM st_traffic_daily WHERE expires_on<=?', array($day)); $removed = $this->db->affected_rows();
			if ($ids || $removed) { $this->db->insert('st_audit_events', array('actor_user_id' => NULL, 'event_type' => 'traffic.retained', 'entity_type' => 'traffic', 'entity_id' => NULL, 'details_json' => json_encode(array('contract' => 'stored_expiry', 'events_aggregated' => count($ids), 'aggregates_expired' => $removed)), 'created_at' => gmdate('Y-m-d H:i:s'))); }
			$this->db->query('UPDATE st_traffic_state SET retention_at=UTC_TIMESTAMP() WHERE id=1');
			return array('ok' => TRUE, 'events_aggregated' => count($ids));
		});
	}
}
