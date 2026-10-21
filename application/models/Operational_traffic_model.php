<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Operational_traffic_model extends CI_Model
{
	private function rows($sql, $args = array())
	{
		$q = $this->db->query($sql, $args); if ($q === FALSE) { throw new RuntimeException('Operational traffic unavailable.'); } return $q->result_array();
	}
	public function pages()
	{
		$this->load->model('Traffic_model');
		return $this->Traffic_model->pages() + array('arena/choose' => 'Detail aktivitas', 'privacy/policy' => 'Kebijakan privasi');
	}
	public function client_info($server)
	{
		// Nginx resolves the trusted proxy chain. Never trust arbitrary HTTP forwarding headers here.
		$ip = $server['REMOTE_ADDR'] ?? ''; $ip = is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) ? inet_ntop(inet_pton($ip)) : NULL;
		$country = $server['STUDY_COUNTRY'] ?? ''; $country = is_string($country) && preg_match('/\A[A-Z]{2}\z/', $country) && ! in_array($country, array('XX','T1'), TRUE) ? $country : NULL;
		$ua = is_string($server['HTTP_USER_AGENT'] ?? NULL) ? substr($server['HTTP_USER_AGENT'], 0, 1024) : '';
		$bot = (bool) preg_match('/bot|crawler|spider|headless|preview|curl|wget|python|node|undici|webindexer|projectpintar-qa/i', $ua);
		$device = $bot ? 'Bot/alat' : (preg_match('/ipad|tablet|android(?!.*mobile)/i', $ua) ? 'Tablet' : (preg_match('/mobile|iphone|ipod|android/i', $ua) ? 'Ponsel' : ($ua === '' ? 'Tidak diketahui' : 'Desktop')));
		$browser = 'Lainnya'; foreach (array('Edge' => '/Edg(?:e|A|iOS)?\//i', 'Opera' => '/OPR\//i', 'Samsung Internet' => '/SamsungBrowser\//i', 'Firefox' => '/Firefox\/|FxiOS\//i', 'Chrome' => '/Chrome\/|CriOS\//i', 'Safari' => '/Safari\//i') as $label => $pattern) { if (preg_match($pattern, $ua)) { $browser = $label; break; } }
		$os = 'Lainnya'; foreach (array('Android' => '/Android/i','iOS/iPadOS' => '/iPhone|iPad|iPod/i','Windows' => '/Windows/i','ChromeOS' => '/CrOS/i','macOS' => '/Macintosh|Mac OS/i','Linux' => '/Linux/i') as $label => $pattern) { if (preg_match($pattern, $ua)) { $os = $label; break; } }
		return array('ip_address' => $ip, 'country' => $country, 'device' => $device, 'browser' => $browser, 'os' => $os, 'is_bot' => (int) $bot);
	}
	public function record($view, $user)
	{
		if ( ! isset($this->pages()[$view]) || $this->input->method(TRUE) !== 'GET' || $this->input->is_ajax_request() || http_response_code() >= 300 || $this->db->trans_active()) { return; }
		if ($user && ($user['account_kind'] === 'child' || array_intersect(array('system_admin','content_editor','finance_operator','support_agent'), $user['roles']))) { return; }
		$dest = $this->input->get_request_header('Sec-Fetch-Dest'); if ($dest !== NULL && $dest !== 'document') { return; }
		$debug = $this->db->db_debug; $this->db->db_debug = FALSE;
		try
		{
			$this->load->model('Settings_model'); $s = $this->Settings_model->get_section('privasi')['values'];
			if ($s['approved'] !== '1' || $s['operational_enabled'] !== '1') { return; }
			$now = time(); $event = $this->client_info($_SERVER) + array('created_at' => gmdate('Y-m-d H:i:s', $now), 'expires_at' => gmdate('Y-m-d H:i:s', $now + 7 * 86400), 'page_key' => $view, 'user_id' => $user['id'] ?? NULL, 'account_kind' => $user['account_kind'] ?? 'guest');
			$this->load->library('traffic_navigation');
			$event['destination_path']=$this->traffic_navigation->safe_path($_SERVER['REQUEST_URI']??'');
			$event+=$this->traffic_navigation->referrer($_SERVER['HTTP_REFERER']??NULL,base_url());
			// Only allowlisted content paths and referring host/category; no query, tokens, raw UA or child profile.
			if ( ! $this->db->insert('st_operational_events', $event)) { throw new RuntimeException('Operational write unavailable.'); }
			$this->db->query('UPDATE st_operational_state SET first_event_at=COALESCE(first_event_at,UTC_TIMESTAMP()),last_event_at=UTC_TIMESTAMP() WHERE id=1');
		}
		catch (Throwable $e)
		{
			log_message('error', 'Operational traffic not recorded; request remains available.');
			try { $this->db->query('UPDATE st_operational_state SET failures=failures+1,last_failure_at=UTC_TIMESTAMP() WHERE id=1'); } catch (Throwable $ignored) { }
		}
		finally { $this->db->db_debug = $debug; }
	}
	public function report($f, $tab, $page, $actor)
	{
		$debug = $this->db->db_debug; $this->db->db_debug = FALSE;
		try
		{
			// Defense in depth: the data model also requires an active Superadmin.
			if ( ! $this->rows("SELECT u.id FROM st_users u JOIN st_user_roles ur ON ur.user_id=u.id JOIN st_roles r ON r.id=ur.role_id WHERE u.id=? AND u.status='active' AND r.role_code='system_admin'", array($actor))) { throw new RuntimeException('Operational access denied.'); }
			$where = ' FROM st_operational_events e LEFT JOIN st_users u ON u.id=e.user_id WHERE e.created_at>=? AND e.created_at<? AND e.expires_at>UTC_TIMESTAMP()'; $args = array($f['start_utc'], $f['end_utc']);
			foreach (array('audience' => 'account_kind','page_key' => 'page_key','user_id' => 'user_id','device' => 'device') as $input => $column) { if ($f[$input] !== '') { $where .= ' AND e.'.$column.'=?'; $args[] = $f[$input]; } }
			if(($f['ip_address']??'')!==''){
				if($f['ip_address']==='unknown'){$where.=' AND e.ip_address IS NULL';}
				else{$where.=' AND e.ip_address=?';$args[]=$f['ip_address'];}
			}
			if ($f['bots'] !== 'all') { $where .= ' AND e.is_bot=?'; $args[] = $f['bots'] === 'only' ? 1 : 0; }
			$summary = $this->rows('SELECT COUNT(*) views,COUNT(DISTINCT e.ip_address) ips,COUNT(DISTINCT e.user_id) users,SUM(e.is_bot) bots'.$where, $args)[0];
			$daily = $this->rows('SELECT DATE(e.created_at+INTERVAL 7 HOUR) day_wib,COUNT(*) views'.$where.' GROUP BY day_wib ORDER BY day_wib', $args);
			$rows = array(); $total = 0; $offset = max(0, ((int) $page - 1) * 20);
			if(in_array($tab,array('log','pengguna'),TRUE)){
				if(!$this->db->insert('st_audit_events',array('actor_user_id'=>$actor,'event_type'=>'traffic.operational_viewed','entity_type'=>'traffic','entity_id'=>NULL,'details_json'=>json_encode(array('tab'=>$tab,'page'=>(int)$page)),'created_at'=>gmdate('Y-m-d H:i:s')))){throw new RuntimeException('Operational access audit unavailable.');}
			}
			if ($tab === 'log')
			{
				$total = (int) $summary['views'];
				// Audit before revealing IPs; never copy IPs or filters containing identity into audit metadata.
				$rows = $this->rows('SELECT e.*,u.display_name'.$where.' ORDER BY e.created_at DESC,e.id DESC LIMIT 20 OFFSET '.$offset, $args);
			}
			elseif ($tab === 'halaman' || $tab === 'pengguna')
			{
				$select = $tab === 'halaman' ? 'e.page_key' : 'e.user_id,u.display_name,CASE WHEN e.user_id IS NULL THEN e.ip_address ELSE NULL END guest_ip';
				$group = $tab === 'halaman'?' GROUP BY e.page_key':' GROUP BY e.user_id,u.display_name,CASE WHEN e.user_id IS NULL THEN e.ip_address ELSE NULL END';
				$total = (int) $this->rows('SELECT COUNT(*) n FROM (SELECT '.$select.$where.$group.') q', $args)[0]['n'];
				$rows = $this->rows('SELECT '.$select.',COUNT(*) views,SUM(e.is_bot) bots,COUNT(DISTINCT e.ip_address) networks,MAX(e.created_at) last_event'.$where.$group.' ORDER BY last_event DESC,'.($tab === 'halaman' ? 'e.page_key' : 'e.user_id,guest_ip').' LIMIT 20 OFFSET '.$offset, $args);
			}
			$health = $this->rows('SELECT * FROM st_operational_state WHERE id=1')[0];
			return compact('summary','daily','rows','total','health');
		}
		finally { $this->db->db_debug = $debug; }
	}
	/** Bounded, identity-free aggregates; refresh requests are not page views. */
	public function live_summary($actor)
	{
		$debug=$this->db->db_debug; $this->db->db_debug=FALSE;
		try {
			if (!$this->rows("SELECT u.id FROM st_users u JOIN st_user_roles ur ON ur.user_id=u.id JOIN st_roles r ON r.id=ur.role_id WHERE u.id=? AND u.status='active' AND r.role_code='system_admin'",array($actor))) { throw new RuntimeException('Operational access denied.'); }
			$this->load->model('Settings_model'); $policy=$this->Settings_model->get_section('privasi')['values'];
			$now=new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta')); $today=$now->format('Y-m-d'); $start=$now->setTime(0,0)->modify('-6 days');
			$utc=new DateTimeZone('UTC'); $cutoff=$now->setTimezone($utc)->format('Y-m-d H:i:s');
			$groups=$this->rows('SELECT DATE(created_at+INTERVAL 7 HOUR) day_wib,HOUR(created_at+INTERVAL 7 HOUR) hour_wib,COUNT(*) views,SUM(CASE WHEN is_bot=1 THEN 1 ELSE 0 END) bots FROM st_operational_events WHERE created_at>=? AND created_at<=? AND expires_at>? GROUP BY day_wib,hour_wib ORDER BY day_wib,hour_wib',array($start->setTimezone($utc)->format('Y-m-d H:i:s'),$cutoff,$cutoff));
			$recent=$this->rows('SELECT COUNT(*) views FROM st_operational_events WHERE created_at>=? AND created_at<=? AND expires_at>?',array($now->modify('-5 minutes')->setTimezone($utc)->format('Y-m-d H:i:s'),$cutoff,$cutoff))[0];
			$health=$this->rows('SELECT first_event_at,last_event_at,last_failure_at FROM st_operational_state WHERE id=1')[0]??array();
			$daily=array(); for($i=0;$i<7;$i++){$date=$start->modify('+'.$i.' days');$daily[$date->format('Y-m-d')]=array('key'=>$date->format('Y-m-d'),'label'=>$date->format('d/m'),'views'=>0,'bots'=>0,'non_bot'=>0);}
			$hourly=array(); for($i=0;$i<24;$i++){$hourly[$i]=array('key'=>sprintf('%02d:00',$i),'label'=>sprintf('%02d',$i),'views'=>0,'bots'=>0,'non_bot'=>0,'future'=>$i>(int)$now->format('G'));}
			foreach($groups as $row){$day=$row['day_wib'];if(!isset($daily[$day]))continue;$views=(int)$row['views'];$bots=(int)$row['bots'];foreach(array('views'=>$views,'bots'=>$bots,'non_bot'=>$views-$bots) as $key=>$count){$daily[$day][$key]+=$count;if($day===$today)$hourly[(int)$row['hour_wib']][$key]+=$count;}}
			$summary=$daily[$today];unset($summary['key'],$summary['label']);$summary['recent_views']=(int)$recent['views'];
			$format=static function($value)use($utc){return $value?(new DateTimeImmutable($value,$utc))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d/m/Y H:i:s'):NULL;};
			return array('ok'=>TRUE,'enabled'=>$policy['approved']==='1'&&$policy['operational_enabled']==='1','timezone'=>'Asia/Jakarta','refresh_seconds'=>30,'generated_at'=>$now->format(DATE_ATOM),'updated_label'=>$now->format('d/m/Y H:i:s').' WIB','today'=>$today,'summary'=>$summary,'daily'=>array_values($daily),'hourly'=>$hourly,'first_event_label'=>$format($health['first_event_at']??NULL),'last_event_label'=>$format($health['last_event_at']??NULL),'recording_warning'=>!empty($health['last_failure_at'])&&($health['last_failure_at']>($health['last_event_at']??'')));
		} finally { $this->db->db_debug=$debug; }
	}
	public function retention($apply = FALSE)
	{
		$debug = $this->db->db_debug; $this->db->db_debug = FALSE;
		try
		{
			$count = (int) $this->rows('SELECT COUNT(*) n FROM st_operational_events WHERE expires_at<=UTC_TIMESTAMP()')[0]['n'];
			if ($apply) { if ($this->db->query('DELETE FROM st_operational_events WHERE expires_at<=UTC_TIMESTAMP() ORDER BY id LIMIT 10000') === FALSE) { throw new RuntimeException('Operational retention unavailable.'); } $count = $this->db->affected_rows(); if ($this->db->query('UPDATE st_operational_state SET retention_at=UTC_TIMESTAMP() WHERE id=1') === FALSE) { throw new RuntimeException('Operational retention status unavailable.'); } }
			return array('ok' => TRUE, 'operational_expired_rows' => $count, 'applied' => (bool) $apply);
		}
		finally { $this->db->db_debug = $debug; }
	}
}
