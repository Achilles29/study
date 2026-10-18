<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Operations_model extends CI_Model
{
	private function row($sql)
	{
		$q = $this->db->query($sql); if ($q === FALSE) { throw new RuntimeException('Operations data unavailable.'); } return $q->row_array(); }
	public function health()
	{
		$h = array('checked_at' => gmdate('Y-m-d H:i:s'));
		$h['migration'] = $this->row('SELECT version FROM study_migrations')['version'];
		$h['orders'] = $this->row("SELECT COUNT(*) total,COALESCE(SUM(status IN('created','pending')),0) pending,COALESCE(SUM(status IN('created','pending') AND created_at<UTC_TIMESTAMP()-INTERVAL 1 HOUR),0) old_pending,COALESCE(SUM(review_required=1),0) review FROM st_payment_orders WHERE environment='production' AND purpose='commerce'");
		$h['credit_mismatch'] = $this->row("SELECT COUNT(*) n FROM st_wallets w LEFT JOIN(SELECT wallet_id,SUM(delta_credits) total FROM st_wallet_entries GROUP BY wallet_id) e ON e.wallet_id=w.id WHERE w.environment='production' AND w.balance_credits<>COALESCE(e.total,0)")['n'];
		$h['point_mismatch'] = $this->row('SELECT COUNT(*) n FROM st_point_wallets w LEFT JOIN(SELECT learner_user_id,SUM(delta_points) total FROM st_point_entries GROUP BY learner_user_id) e ON e.learner_user_id=w.learner_user_id WHERE w.balance_points<>COALESCE(e.total,0)')['n'];
		$h['gmail'] = $this->row('SELECT provider,enabled,probe_status FROM st_mail_config WHERE id=1');
		$h['google'] = $this->row('SELECT enabled,tested_at FROM st_google_config WHERE id=1');
		$h['payment'] = $this->row("SELECT environment,checkout_enabled,probe_result AS probe_status FROM st_payment_configs WHERE environment='production' ORDER BY id DESC LIMIT 1");
		$h['traffic'] = $this->row('SELECT failures,last_failure_at,retention_at FROM st_traffic_state WHERE id=1');
		$h['moderation'] = $this->row("SELECT COUNT(*) n FROM st_battle_reports WHERE status='open'")['n'];
		$h['backup'] = NULL;
		$path = '/www/study-private/operations/restore-evidence.json';
		if (is_readable($path) && filesize($path) <= 8192)
		{
			$v = json_decode(file_get_contents($path), TRUE);
			if (is_array($v) && ($v['database'] ?? '') === 'db_study' && ($v['restore_verified'] ?? FALSE) === TRUE && isset($v['checked_at'], $v['migration'], $v['tables'], $v['media_files'])) { $h['backup'] = $v; }
		}
		return $h;
	}
}
