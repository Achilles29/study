<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Commerce_model extends CI_Model
{
	private $environment = 'sandbox';
	public function set_environment($environment)
	{
		if ( ! in_array($environment, array('sandbox','production'), TRUE)) { throw new InvalidArgumentException('Invalid commerce environment.'); }
		$this->environment = $environment;
	}
	private function transaction_allowed($actor)
	{
		return $this->environment === 'production' || $this->sandbox_allowed($actor);
	}
	public function package_ready($id)
	{
		$p = $this->rows('SELECT * FROM st_packages WHERE id=?', array($id))[0] ?? NULL;
		if ( ! $p || $p['status'] !== 'published') { return FALSE; }
		$courses = $this->rows('SELECT c.id,c.status FROM st_package_courses pc JOIN st_courses c ON c.id=pc.course_id WHERE pc.package_id=?', array($id));
		if ( ! $courses) { return FALSE; }
		foreach ($courses as $course)
		{
			if ($course['status'] !== 'published') { return FALSE; }
			$questions = $this->rows("SELECT q.id FROM st_questions q WHERE q.course_id=? AND q.status='published' AND q.visibility='shared' AND q.question_type='single_choice' AND (SELECT COUNT(*) FROM st_question_options o WHERE o.question_id=q.id) BETWEEN 2 AND 6 AND (SELECT SUM(is_correct) FROM st_question_options o WHERE o.question_id=q.id)=1 LIMIT 1", array($course['id']));
			$lessons = $this->rows("SELECT l.id FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id=? AND l.status='published' LIMIT 1", array($course['id']));
			if ($p['requires_questions'] ? ! $questions : ( ! $questions && ! $lessons)) { return FALSE; }
		}
		return TRUE;
	}
	private function live_offer_ready($offer)
	{
		if ( ! $offer['live_approved'] || $offer['status'] !== 'active') { return FALSE; }
		if ($offer['package_id']) { return $this->package_ready($offer['package_id']); }
		if ($offer['kind'] === 'platinum')
		{
			foreach ($this->rows("SELECT id FROM st_packages WHERE is_official=1 AND status='published'") as $p) { if ($this->package_ready($p['id'])) { return TRUE; } }
			return FALSE;
		}
		return TRUE;
	}
	private function rows($sql, $params = array())
	{
		$q = $this->db->query($sql, $params);
		if ($q === FALSE) { throw new RuntimeException('Commerce data unavailable.'); }
		return $q->result_array();
	}
	private function audit($actor, $event, $type, $id, $details)
	{
		if ( ! $this->db->insert('st_audit_events', array('actor_user_id' => $actor, 'event_type' => $event, 'entity_type' => $type, 'entity_id' => $id, 'details_json' => json_encode($details), 'created_at' => gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Commerce audit unavailable.'); }
	}
	private function actor($id, $stamp, $admin = FALSE)
	{
		$u = $this->rows("SELECT * FROM st_users WHERE id=? AND status='active' AND account_kind IN ('adult','parent') FOR UPDATE", array($id))[0] ?? NULL;
		if ( ! $u || ! is_string($stamp) || ! hash_equals(study_credential_stamp($u), $stamp)) { return NULL; }
		if ($admin && ! $this->rows("SELECT ur.user_id FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.role_code='system_admin' FOR UPDATE", array($id))) { return NULL; }
		return $u;
	}
	public function number($value, $min = 0, $max = 100000000)
	{
		return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,8})\z/', $value) && (int) $value >= $min && (int) $value <= $max;
	}
	private function valid_text($value, $min, $max)
	{
		return is_string($value) && mb_check_encoding($value, 'UTF-8') && mb_strlen(trim($value), 'UTF-8') >= $min && mb_strlen(trim($value), 'UTF-8') <= $max;
	}
	public function sandbox_allowed($actor)
	{
		// Sandbox remains restricted and never mixes with live or legacy balances.
		return ENVIRONMENT === 'development' || (bool) $this->rows("SELECT ur.user_id FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.role_code='system_admin'", array($actor));
	}
	public function packages($page = NULL)
	{
		return $this->rows('SELECT id,title,status,is_official,revision FROM st_packages ORDER BY id DESC'.($page === NULL ? ' LIMIT 500' : ' LIMIT 20 OFFSET '.(((int) $page - 1) * 20)));
	}
	public function package($id)
	{
		$p = $this->rows('SELECT * FROM st_packages WHERE id=?', array($id))[0] ?? NULL;
		if ($p) { $p['courses'] = array_map('intval', array_column($this->rows('SELECT course_id FROM st_package_courses WHERE package_id=? ORDER BY sort_order,course_id', array($id)), 'course_id')); }
		return $p;
	}
	public function courses()
	{
		return $this->rows("SELECT id,title,status,grade_id FROM st_courses ORDER BY title,id LIMIT 500");
	}
	public function save_package($actor, $stamp, $id, $v)
	{
		if ( ! in_array($v['requires_questions'] ?? '0', array('0','1'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		if ( ! in_array($v['family_practice_enabled'] ?? '0', array('0','1'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		if ( ! $this->number($v['point_price'] ?? '0', 0, 1000000) || ! $this->number($v['point_days'] ?? '0', 0, 3660) || (((int) ($v['point_price'] ?? 0) === 0) !== ((int) ($v['point_days'] ?? 0) === 0))) { return array('ok' => FALSE, 'code' => 400); }
		if ( ! $this->valid_text($v['title'] ?? NULL, 3, 180) || ! $this->valid_text($v['summary'] ?? NULL, 0, 3000) || ! $this->number($v['revision'] ?? NULL) || ! in_array($v['status'] ?? NULL, array('draft','published','archived'), TRUE) || ! in_array($v['is_official'] ?? NULL, array('0','1'), TRUE) || ! is_array($v['courses'] ?? NULL) || count($v['courses']) > 100) { return array('ok' => FALSE, 'code' => 400); }
		$ids = array(); foreach ($v['courses'] as $course) { if ( ! $this->number($course, 1)) { return array('ok' => FALSE, 'code' => 400); } $ids[] = (int) $course; } $ids = array_values(array_unique($ids)); sort($ids);
		if ($v['status'] === 'published' && ( ! $ids || ($v['confirm'] ?? '') !== '1')) { return array('ok' => FALSE, 'code' => 400); }
		$this->load->library('study_transaction');
		return $this->study_transaction->run($this->db, function () use ($actor, $stamp, $id, $v, $ids) {
			if ( ! $this->actor($actor, $stamp, TRUE)) { return array('ok' => FALSE, 'code' => 403); }
			$old = $id ? ($this->rows('SELECT * FROM st_packages WHERE id=? FOR UPDATE', array($id))[0] ?? NULL) : NULL;
			if (($id && ! $old) || (int) $v['revision'] !== (int) ($old['revision'] ?? 0)) { return array('ok' => FALSE, 'code' => 409); }
			foreach ($ids as $course) { $c = $this->rows('SELECT status FROM st_courses WHERE id=? FOR UPDATE', array($course))[0] ?? NULL; if ( ! $c || ($v['status'] === 'published' && $c['status'] !== 'published')) { return array('ok' => FALSE, 'code' => 400); } }
			$now = gmdate('Y-m-d H:i:s'); $data = array('title' => trim($v['title']), 'summary' => trim($v['summary']), 'status' => $v['status'], 'is_official' => (int) $v['is_official'], 'revision' => (int) $v['revision'] + 1, 'updated_at' => $now);
			$data['requires_questions'] = (int) ($v['requires_questions'] ?? '0');
			$data['family_practice_enabled'] = (int) ($v['family_practice_enabled'] ?? '0');
			$data['point_price'] = (int) ($v['point_price'] ?? '0'); $data['point_days'] = (int) ($v['point_days'] ?? '0');
			if ($old) { $this->db->where('id', $id)->update('st_packages', $data); }
			else { $data += array('slug' => 'paket-'.bin2hex(random_bytes(10)), 'created_at' => $now); $this->db->insert('st_packages', $data); $id = (int) $this->db->insert_id(); }
			$this->db->where('package_id', $id)->delete('st_package_courses'); foreach ($ids as $i => $course) { $this->db->insert('st_package_courses', array('package_id' => $id, 'course_id' => $course, 'sort_order' => $i)); }
			if ($v['status'] === 'published' && $data['requires_questions'] && ! $this->package_ready($id)) { return array('ok' => FALSE, 'code' => 400, 'message' => 'Paket latihan belum dapat diterbitkan: setiap kursus harus memiliki soal pilihan ganda terbit yang lengkap dan dapat dikerjakan.'); }
			$this->audit($actor, 'commerce.package_saved', 'package', $id, array('before' => $old, 'after' => $data, 'course_ids' => $ids));
			return array('ok' => TRUE, 'id' => $id);
		});
	}
	public function offers($admin = FALSE, $page = 1)
	{
		$live = ! $admin && $this->environment === 'production';
		$offers = $this->rows('SELECT o.*,p.title package_title,p.summary package_summary,p.revision package_revision FROM st_commerce_offers o LEFT JOIN st_packages p ON p.id=o.package_id'.($admin ? '' : " WHERE o.status='active' AND (o.package_id IS NULL OR p.status='published')".($live ? ' AND o.live_approved=1' : '')).' ORDER BY o.id DESC'.($live ? '' : ' LIMIT 20 OFFSET '.(((int) $page - 1) * 20)));
		if ($live) { $offers = array_slice(array_values(array_filter($offers, function ($o) { return $this->live_offer_ready($o); })), ((int) $page - 1) * 20, 20); }
		return $offers;
	}
	public function offer($id)
	{
		return $this->rows('SELECT * FROM st_commerce_offers WHERE id=?', array($id))[0] ?? NULL; }
	public function save_offer($actor, $stamp, $id, $v)
	{
		if ( ! in_array($v['live_approved'] ?? '0', array('0','1'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		if ( ! $this->valid_text($v['title'] ?? NULL, 3, 180) || ! in_array($v['kind'] ?? NULL, array('package','voucher','topup','platinum','slot'), TRUE) || ! in_array($v['status'] ?? NULL, array('draft','active','paused'), TRUE)) { return array('ok' => FALSE, 'code' => 400); }
		foreach (array('revision','package_id','price','credit_amount','access_days','voucher_days') as $f) { if ( ! $this->number($v[$f] ?? NULL)) { return array('ok' => FALSE, 'code' => 400); } }
		$kind = $v['kind']; $pack = in_array($kind, array('package','voucher'), TRUE);
		if ((int) $v['price'] < 1 || (int) $v['price'] > ($pack ? 1000000 : 10000000) || ($pack !== ((int) $v['package_id'] > 0)) || (($pack || $kind === 'platinum') ? ((int) $v['access_days'] < 1 || (int) $v['access_days'] > 3660) : (int) $v['access_days'] !== 0) || ($kind === 'voucher' ? ((int) $v['voucher_days'] < 1 || (int) $v['voucher_days'] > 366) : (int) $v['voucher_days'] !== 0) || ($kind === 'topup' ? ((int) $v['credit_amount'] < 1 || (int) $v['credit_amount'] > 1000000) : (int) $v['credit_amount'] !== 0) || ($v['status'] === 'active' && ($v['confirm'] ?? '') !== '1') || ! $this->valid_text($v['reason'] ?? NULL, 10, 300)) { return array('ok' => FALSE, 'code' => 400); }
		$this->load->library('study_transaction');
		return $this->study_transaction->run($this->db, function () use ($actor, $stamp, $id, $v, $pack) {
			if ( ! $this->actor($actor, $stamp, TRUE)) { return array('ok' => FALSE, 'code' => 403); }
			$old = $id ? ($this->rows('SELECT * FROM st_commerce_offers WHERE id=? FOR UPDATE', array($id))[0] ?? NULL) : NULL;
			if (($id && ! $old) || (int) $v['revision'] !== (int) ($old['revision'] ?? 0) || ($old && $old['kind'] !== $v['kind'])) { return array('ok' => FALSE, 'code' => 409); }
			if ($pack) { $p = $this->rows('SELECT status FROM st_packages WHERE id=? FOR UPDATE', array((int) $v['package_id']))[0] ?? NULL; if ( ! $p || ($v['status'] === 'active' && $p['status'] !== 'published')) { return array('ok' => FALSE, 'code' => 400); } }
			$now = gmdate('Y-m-d H:i:s'); $data = array('title' => trim($v['title']), 'kind' => $v['kind'], 'status' => $v['status'], 'package_id' => $pack ? (int) $v['package_id'] : NULL, 'price' => (int) $v['price'], 'credit_amount' => (int) $v['credit_amount'], 'access_days' => (int) $v['access_days'], 'voucher_days' => (int) $v['voucher_days'], 'revision' => (int) $v['revision'] + 1, 'updated_at' => $now);
			$data['live_approved'] = (int) ($v['live_approved'] ?? '0');
			if ($data['live_approved'] && (($v['confirm'] ?? '') !== '1' || ! $this->live_offer_ready($data))) { return array('ok' => FALSE, 'code' => 400, 'message' => 'Persetujuan live membutuhkan produk aktif, ketentuan disetujui, dan isi paket terbit yang siap digunakan. Paket latihan tanpa soal serta Platinum tanpa paket resmi siap tidak dapat dijual.'); }
			if ($old) { $this->db->where('id', $id)->update('st_commerce_offers', $data); } else { $this->db->insert('st_commerce_offers', $data + array('created_at' => $now)); $id = (int) $this->db->insert_id(); }
			$this->audit($actor, 'commerce.offer_saved', 'offer', $id, array('before' => $old, 'after' => $data, 'reason' => trim($v['reason'])));
			return array('ok' => TRUE, 'id' => $id);
		});
	}
	public function beneficiaries($actor)
	{
		return $this->rows("SELECT id,display_name FROM st_users WHERE id=? AND status='active' AND account_kind IN ('adult','parent') UNION SELECT u.id,u.display_name FROM st_family_links l JOIN st_users u ON u.id=l.child_user_id JOIN st_users p ON p.id=l.parent_user_id WHERE l.parent_user_id=? AND l.status='active' AND u.status='active' AND u.account_kind='child' AND p.status='active' AND p.account_kind='parent'", array($actor, $actor));
	}
	private function beneficiary($actor, $learner)
	{
		if ((int) $actor['id'] === (int) $learner) { return TRUE; }
		return $actor['account_kind'] === 'parent' && (bool) $this->rows("SELECT l.id FROM st_family_links l JOIN st_users u ON u.id=l.child_user_id WHERE l.parent_user_id=? AND l.child_user_id=? AND l.status='active' AND u.status='active' AND u.account_kind='child' FOR UPDATE", array($actor['id'], $learner));
	}
	public function wallet($actor, $page = 1)
	{
		$w = $this->rows("SELECT id,balance_credits FROM st_wallets WHERE owner_user_id=? AND environment='{$this->environment}'", array($actor))[0] ?? NULL;
		if ( ! $w) { return array('id' => 0, 'balance_credits' => 0, 'total' => 0, 'entries' => array()); }
		$w['total'] = (int) $this->rows('SELECT COUNT(*) n FROM st_wallet_entries WHERE wallet_id=?', array($w['id']))[0]['n'];
		$w['entries'] = $this->rows('SELECT id,entry_type,delta_credits,reference_type,reference_id,note,created_at FROM st_wallet_entries WHERE wallet_id=? ORDER BY id DESC LIMIT 20 OFFSET '.(((int) $page - 1) * 20), array($w['id']));
		return $w;
	}
	public function purchases($actor, $page = 1)
	{
		return $this->rows('SELECT p.*,u.display_name recipient_name,g.starts_at access_starts_at,g.ends_at access_ends_at FROM st_commerce_purchases p LEFT JOIN st_users u ON u.id=p.learner_user_id LEFT JOIN st_commerce_grants g ON g.purchase_id=p.id WHERE p.owner_user_id=? AND p.environment=? ORDER BY p.id DESC LIMIT 20 OFFSET '.(((int) $page - 1) * 20), array($actor, $this->environment));
	}
	public function wallets($page = 1)
	{
		return $this->rows("SELECT w.id,w.owner_user_id,w.balance_credits,u.display_name,(SELECT COALESCE(SUM(e.delta_credits),0) FROM st_wallet_entries e WHERE e.wallet_id=w.id) ledger_balance FROM st_wallets w JOIN st_users u ON u.id=w.owner_user_id WHERE w.environment='{$this->environment}' ORDER BY w.id DESC LIMIT 20 OFFSET ".(((int) $page - 1) * 20));
	}
	public function wallet_owner($id)
	{
		return $this->rows("SELECT id,display_name,account_kind FROM st_users WHERE id=? AND account_kind IN ('adult','parent')", array($id))[0] ?? NULL;
	}
	public function compensate_voucher($actor, $stamp, $id, $reason)
	{
		if ( ! $this->valid_text($reason, 10, 300)) { return array('ok' => FALSE, 'code' => 400); }
		$this->load->library('study_transaction');
		return $this->study_transaction->run($this->db, function () use ($actor, $stamp, $id, $reason) {
			if ( ! $this->actor($actor, $stamp, TRUE)) { return array('ok' => FALSE, 'code' => 403); }
			$owner = $this->rows('SELECT owner_user_id FROM st_commerce_purchases WHERE id=?', array($id))[0] ?? NULL;
			if ( ! $owner) { return array('ok' => FALSE, 'code' => 404); }
			$this->rows('SELECT id FROM st_users WHERE id=? FOR UPDATE', array($owner['owner_user_id']));
			$p = $this->rows('SELECT * FROM st_commerce_purchases WHERE id=? FOR UPDATE', array($id))[0]; $s = json_decode($p['snapshot_json'], TRUE);
			if ($s['kind'] !== 'voucher') { return array('ok' => FALSE, 'code' => 409); }
			if ($p['status'] === 'refunded') { return array('ok' => TRUE, 'owner' => (int) $owner['owner_user_id'], 'environment' => $p['environment']); }
			if ($p['status'] !== 'available') { return array('ok' => FALSE, 'code' => 409, 'message' => 'Voucher telah digunakan. Kompensasi otomatis tidak tersedia untuk akses yang sudah diterbitkan.'); }
			$w = $this->rows("SELECT * FROM st_wallets WHERE owner_user_id=? AND environment=? FOR UPDATE", array($owner['owner_user_id'], $p['environment']))[0] ?? NULL;
			if ( ! $w || (int) $w['balance_credits'] + (int) $s['price'] > 100000000) { return array('ok' => FALSE, 'code' => 409); }
			$now = gmdate('Y-m-d H:i:s');
			$this->db->where('id', $w['id'])->update('st_wallets', array('balance_credits' => (int) $w['balance_credits'] + (int) $s['price'], 'updated_at' => $now));
			$this->db->insert('st_wallet_entries', array('wallet_id' => $w['id'], 'actor_user_id' => $actor, 'entry_type' => 'refund', 'delta_credits' => (int) $s['price'], 'idempotency_key' => 'voucher-refund:'.$id, 'reference_type' => 'commerce_purchase', 'reference_id' => $id, 'note' => 'Kompensasi voucher #'.$id, 'created_at' => $now));
			$this->db->where('id', $id)->update('st_commerce_purchases', array('status' => 'refunded'));
			$this->audit($actor, 'commerce.voucher_compensated', 'commerce_purchase', $id, array('credits' => (int) $s['price'], 'reason' => trim($reason)));
			return array('ok' => TRUE, 'owner' => (int) $owner['owner_user_id'], 'environment' => $p['environment']);
		});
	}
	private function snapshot($offer)
	{
		$s = $offer; $s['environment'] = $this->environment;
		$s['course_ids'] = array(); $s['family_practice_enabled'] = 0;
		if ($offer['package_id'])
		{
			$p = $this->rows("SELECT * FROM st_packages WHERE id=? AND status='published' FOR UPDATE", array($offer['package_id']))[0] ?? NULL;
			if ( ! $p) { return NULL; }
			$c = $this->rows("SELECT pc.course_id,c.status FROM st_package_courses pc JOIN st_courses c ON c.id=pc.course_id WHERE pc.package_id=? ORDER BY pc.course_id FOR UPDATE", array($p['id']));
			if ( ! $c || count($c) > 100) { return NULL; }
			foreach ($c as $row) { if ($row['status'] !== 'published') { return NULL; } }
			$s['course_ids'] = array_map('intval', array_column($c, 'course_id')); $s['package_title'] = $p['title']; $s['package_revision'] = (int) $p['revision']; $s['family_practice_enabled'] = (int) $p['family_practice_enabled'];
		}
		return $s;
	}
	private function grant($purchase, $owner, $learner, $s)
	{
		$now = gmdate('Y-m-d H:i:s');
		// Renewals append time only to this learner's active grant for the same package.
		$last = $this->rows("SELECT ends_at FROM st_commerce_grants WHERE learner_user_id=? AND package_id=? AND environment=? AND status='active' AND ends_at>UTC_TIMESTAMP() ORDER BY ends_at DESC LIMIT 1 FOR UPDATE", array($learner, $s['package_id'], $this->environment));
		$start = $last ? $last[0]['ends_at'] : $now;
		$this->db->insert('st_commerce_grants', array('environment' => $this->environment, 'purchase_id' => $purchase, 'owner_user_id' => $owner, 'learner_user_id' => $learner, 'package_id' => $s['package_id'], 'kind' => 'package', 'course_ids_json' => json_encode($s['course_ids']), 'family_practice_enabled' => $s['family_practice_enabled'], 'starts_at' => $start, 'ends_at' => gmdate('Y-m-d H:i:s', strtotime($start.' UTC') + (int) $s['access_days'] * 86400)));
	}
	public function buy($actor, $stamp, $offer_id, $revision, $learner, $key, $package_revision)
	{
		if ( ! $this->transaction_allowed($actor)) { return array('ok' => FALSE, 'code' => 403, 'message' => 'Transaksi sandbox saat ini hanya untuk pengelola. Penjualan publik belum dibuka.'); }
		if ( ! is_string($key) || ! preg_match('/\A[a-f0-9]{64}\z/', $key) || ! $this->number($revision, 1) || ! $this->number($package_revision, 1) || ! $this->number($learner)) { return array('ok' => FALSE, 'code' => 400); }
		$hash = hash('sha256', json_encode(array((int) $offer_id, (int) $revision, (int) $learner, (int) $package_revision)));
		$this->load->library('study_transaction');
		return $this->study_transaction->run($this->db, function () use ($actor, $stamp, $offer_id, $revision, $learner, $key, $hash, $package_revision) {
			$u = $this->actor($actor, $stamp); if ( ! $u || ! $u['email_verified_at']) { return array('ok' => FALSE, 'code' => 403); }
			if ($this->rows("SELECT id FROM st_payment_orders WHERE user_id=? AND environment='{$this->environment}' AND review_required=1 LIMIT 1 FOR UPDATE", array($actor))) { return array('ok' => FALSE, 'code' => 409, 'message' => 'Transaksi baru dijeda sementara ada refund/sengketa yang perlu ditinjau pengelola.'); }
			$previous = $this->rows('SELECT id,request_hash,environment FROM st_commerce_purchases WHERE owner_user_id=? AND request_key=? FOR UPDATE', array($actor, $key))[0] ?? NULL;
			if ($previous) { return $previous['environment'] === $this->environment && hash_equals($previous['request_hash'], $hash) ? array('ok' => TRUE, 'id' => (int) $previous['id']) : array('ok' => FALSE, 'code' => 409); }
			$o = $this->rows('SELECT * FROM st_commerce_offers WHERE id=? FOR UPDATE', array($offer_id))[0] ?? NULL;
			if ( ! $o || $o['status'] !== 'active' || (int) $o['revision'] !== (int) $revision || ($this->environment === 'production' && ! $this->live_offer_ready($o))) { return array('ok' => FALSE, 'code' => 409); }
			if ( ! in_array($o['kind'], array('package','voucher'), TRUE) || ($o['kind'] === 'voucher' ? (int) $learner !== 0 : ! $this->beneficiary($u, $learner))) { return array('ok' => FALSE, 'code' => 403); }
			$s = $this->snapshot($o); if ( ! $s || (int) $s['package_revision'] !== (int) $package_revision) { return array('ok' => FALSE, 'code' => 409); }
			$w = $this->rows("SELECT * FROM st_wallets WHERE owner_user_id=? AND environment='{$this->environment}' FOR UPDATE", array($actor))[0] ?? NULL;
			if ( ! $w || (int) $w['balance_credits'] < (int) $o['price']) { return array('ok' => FALSE, 'code' => 409, 'message' => 'Saldo kredit tidak cukup.'); }
			$now = gmdate('Y-m-d H:i:s'); $voucher = $o['kind'] === 'voucher';
			$this->db->insert('st_commerce_purchases', array('environment' => $this->environment, 'owner_user_id' => $actor, 'offer_id' => $offer_id, 'request_key' => $key, 'request_hash' => $hash, 'snapshot_json' => json_encode($s), 'status' => $voucher ? 'available' : 'fulfilled', 'learner_user_id' => $voucher ? NULL : (int) $learner, 'redeem_by' => $voucher ? gmdate('Y-m-d H:i:s', time() + (int) $o['voucher_days'] * 86400) : NULL, 'created_at' => $now));
			$id = (int) $this->db->insert_id();
			$this->db->where('id', $w['id'])->update('st_wallets', array('balance_credits' => (int) $w['balance_credits'] - (int) $o['price'], 'updated_at' => $now));
			$this->db->insert('st_wallet_entries', array('wallet_id' => $w['id'], 'actor_user_id' => $actor, 'entry_type' => $voucher ? 'voucher_purchase' : 'package_purchase', 'delta_credits' => -(int) $o['price'], 'idempotency_key' => 'commerce:'.$id, 'reference_type' => 'commerce_purchase', 'reference_id' => $id, 'note' => $o['title'], 'created_at' => $now));
			if ( ! $voucher) { $this->grant($id, $actor, (int) $learner, $s); }
			$this->audit($actor, 'commerce.purchased', 'commerce_purchase', $id, array('kind' => $o['kind'], 'credits' => (int) $o['price'], 'offer_revision' => (int) $o['revision']));
			return array('ok' => TRUE, 'id' => $id);
		});
	}
	public function redeem($actor, $stamp, $id, $learner)
	{
		if ( ! $this->transaction_allowed($actor)) { return array('ok' => FALSE, 'code' => 403); }
		if ( ! $this->number($learner, 1)) { return array('ok' => FALSE, 'code' => 400); }
		$this->load->library('study_transaction');
		return $this->study_transaction->run($this->db, function () use ($actor, $stamp, $id, $learner) {
			$u = $this->actor($actor, $stamp); if ( ! $u || ! $u['email_verified_at'] || ! $this->beneficiary($u, $learner)) { return array('ok' => FALSE, 'code' => 403); }
			if ($this->rows("SELECT id FROM st_payment_orders WHERE user_id=? AND environment='{$this->environment}' AND review_required=1 LIMIT 1 FOR UPDATE", array($actor))) { return array('ok' => FALSE, 'code' => 409); }
			$p = $this->rows('SELECT *,redeem_by<=UTC_TIMESTAMP() expired FROM st_commerce_purchases WHERE id=? AND owner_user_id=? AND environment=? FOR UPDATE', array($id, $actor, $this->environment))[0] ?? NULL;
			if ( ! $p) { return array('ok' => FALSE, 'code' => 404); }
			if ($p['status'] === 'redeemed') { return (int) $p['learner_user_id'] === (int) $learner ? array('ok' => TRUE, 'id' => $id) : array('ok' => FALSE, 'code' => 409); }
			if ($p['status'] !== 'available' || $p['expired']) { return array('ok' => FALSE, 'code' => 409); }
			$s = json_decode($p['snapshot_json'], TRUE); if ($s['kind'] !== 'voucher') { return array('ok' => FALSE, 'code' => 409); }
			$this->grant($id, $actor, (int) $learner, $s);
			$this->db->where('id', $id)->update('st_commerce_purchases', array('status' => 'redeemed', 'learner_user_id' => (int) $learner, 'redeemed_at' => gmdate('Y-m-d H:i:s')));
			$this->audit($actor, 'commerce.voucher_redeemed', 'commerce_purchase', $id, array('learner_user_id' => (int) $learner));
			return array('ok' => TRUE, 'id' => $id);
		});
	}
	private function payment_wallet($actor)
	{
		$w = $this->rows("SELECT * FROM st_wallets WHERE owner_user_id=? AND environment='{$this->environment}' FOR UPDATE", array($actor))[0] ?? NULL;
		if ($w) { return $w; }
		$this->db->insert('st_wallets', array('owner_user_id' => $actor, 'environment' => $this->environment, 'balance_credits' => 0, 'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')));
		return array('id' => (int) $this->db->insert_id(), 'balance_credits' => 0);
	}
	public function orders($actor = NULL, $page = 1, $status = '')
	{
		$where = " WHERE environment='{$this->environment}' AND purpose='commerce'"; $params = array();
		if ($actor !== NULL) { $where .= ' AND user_id=?'; $params[] = $actor; }
		if ($status === 'review') { $where .= ' AND review_required=1'; }
		elseif (in_array($status, array('created','pending','paid','expired','failed','cancelled'), TRUE)) { $where .= ' AND status=?'; $params[] = $status; }
		return $this->rows('SELECT id,user_id,environment,order_code,amount_idr,credit_amount,status,review_required,created_at,updated_at FROM st_payment_orders'.$where.' ORDER BY id DESC LIMIT 20 OFFSET '.(((int) $page - 1) * 20), $params);
	}
	public function order($id, $actor = NULL)
	{
		$params = array($id); $sql = "SELECT id,user_id,environment,purpose,order_code,amount_idr,credit_amount,status,qr_url,review_required,created_at,updated_at,checked_at,snapshot_json FROM st_payment_orders WHERE id=? AND environment IN ('sandbox','production')";
		if ($actor !== NULL) { $sql .= ' AND user_id=?'; $params[] = $actor; }
		return $this->rows($sql, $params)[0] ?? NULL;
	}
	public function checkout($actor, $stamp, $offer, $revision, $key)
	{
		if ( ! $this->transaction_allowed($actor)) { return array('ok' => FALSE, 'code' => 403); }
		if ( ! $this->number($revision, 1) || ! is_string($key) || ! preg_match('/\A[a-f0-9]{64}\z/', $key)) { return array('ok' => FALSE, 'code' => 400); }
		$this->load->library(array('study_transaction','payment_secrets','midtrans_gateway'));
		$r = $this->study_transaction->run($this->db, function () use ($actor, $stamp, $offer, $revision, $key) {
			$u = $this->actor($actor, $stamp); if ( ! $u || ! $u['email_verified_at']) { return array('ok' => FALSE, 'code' => 403); }
			if ($this->rows("SELECT id FROM st_payment_orders WHERE user_id=? AND environment=? AND review_required=1 LIMIT 1 FOR UPDATE", array($actor, $this->environment))) { return array('ok' => FALSE, 'code' => 409, 'message' => 'Ada refund/sengketa yang perlu ditinjau sebelum transaksi baru.'); }
			$hash = hash('sha256', $offer.'|'.$revision);
			$old = $this->rows('SELECT id,request_hash,environment FROM st_payment_orders WHERE user_id=? AND request_key=? FOR UPDATE', array($actor, $key))[0] ?? NULL;
			if ($old) { return $old['environment'] === $this->environment && hash_equals($old['request_hash'], $hash) ? array('ok' => TRUE, 'id' => (int) $old['id'], 'existing' => TRUE) : array('ok' => FALSE, 'code' => 409); }
			$o = $this->rows("SELECT * FROM st_commerce_offers WHERE id=? AND status='active' FOR UPDATE", array($offer))[0] ?? NULL;
			if ( ! $o || (int) $o['revision'] !== (int) $revision || ($this->environment === 'production' && ! $this->live_offer_ready($o)) || ! in_array($o['kind'], array('topup','platinum','slot'), TRUE)) { return array('ok' => FALSE, 'code' => 409); }
			if ($o['kind'] !== 'topup' && $u['account_kind'] !== 'parent') { return array('ok' => FALSE, 'code' => 403); }
			$c = $this->rows('SELECT * FROM st_payment_configs WHERE environment=? ORDER BY id DESC LIMIT 1 FOR UPDATE', array($this->environment))[0] ?? NULL;
			if ( ! $c || $c['environment'] !== $this->environment || $c['probe_result'] !== 'status_api_reached' || ($this->environment === 'production' && ! $c['checkout_enabled'])) { return array('ok' => FALSE, 'code' => 409, 'message' => 'Kunci pada lingkungan ini belum teruji atau checkout live belum diaktifkan pengelola.'); }
			$recent = $this->rows("SELECT id FROM st_payment_orders WHERE user_id=? AND created_at>UTC_TIMESTAMP()-INTERVAL 1 HOUR FOR UPDATE", array($actor));
			if (count($recent) >= 10) { return array('ok' => FALSE, 'code' => 409, 'message' => 'Maksimal 10 pesanan per jam. Periksa pesanan sebelumnya.'); }
			$w = $this->payment_wallet($actor); $now = gmdate('Y-m-d H:i:s'); $code = ($this->environment === 'sandbox' ? 'PP-SB-' : 'PP-LIVE-').bin2hex(random_bytes(16));
			$o['environment'] = $this->environment;
			$this->db->insert('st_payment_orders', array('user_id' => $actor, 'wallet_id' => $w['id'], 'order_code' => $code, 'provider_order_id' => $code, 'amount_idr' => $o['price'], 'credit_amount' => $o['credit_amount'], 'status' => 'created', 'environment' => $this->environment, 'offer_id' => $offer, 'config_id' => $c['id'], 'request_key' => $key, 'request_hash' => $hash, 'snapshot_json' => json_encode($o), 'created_at' => $now, 'updated_at' => $now));
			$id = (int) $this->db->insert_id(); $this->audit($actor, 'payment.order_created', 'payment_order', $id, array('amount_idr' => (int) $o['price'], 'offer_revision' => (int) $revision, 'environment' => $this->environment));
			return array('ok' => TRUE, 'id' => $id, 'code' => $code, 'amount' => (int) $o['price'], 'environment' => $this->environment, 'key' => $this->payment_secrets->open($c['credentials_cipher'], $c['environment'])['server_key']);
		});
		if ( ! $r['ok'] || ! empty($r['existing'])) { return $r; }
		// Exactly one charge attempt. After any uncertain result, reconcile the same order; never create/recharge automatically.
		$remote = $this->midtrans_gateway->request($r['key'], $r['code'], array('payment_type' => 'qris', 'transaction_details' => array('order_id' => $r['code'], 'gross_amount' => $r['amount']), 'qris' => array('acquirer' => 'gopay')), $r['environment']);
		if (is_array($remote)) { $this->apply_payment_status($r['id'], $remote, FALSE); }
		return array('ok' => TRUE, 'id' => $r['id']);
	}
	public function donation_settings()
	{
		$this->load->model('Settings_model'); $s = $this->Settings_model->get_section('donasi');
		$c = $this->rows('SELECT probe_result,checkout_enabled FROM st_payment_configs WHERE environment=? ORDER BY id DESC LIMIT 1', array($this->environment))[0] ?? NULL;
		$s['payment_ready'] = $c && $c['probe_result'] === 'status_api_reached' && ($this->environment === 'sandbox' || $c['checkout_enabled']);
		return $s;
	}
	public function donation_report($actor, $page = 1, $status = '')
	{
		$where = " FROM st_payment_orders o JOIN st_users u ON u.id=o.user_id WHERE o.purpose='donation' AND o.environment=?"; $args = array($this->environment);
		if ($actor !== NULL) { $where .= ' AND o.user_id=?'; $args[] = $actor; }
		if ($status === 'review') { $where .= ' AND o.review_required=1'; }
		elseif (in_array($status, array('created','pending','paid','expired','failed','cancelled'), TRUE)) { $where .= ' AND o.status=?'; $args[] = $status; }
		$summary = $this->rows("SELECT COUNT(*) total,COALESCE(SUM(CASE WHEN o.status='paid' AND o.review_required=0 THEN o.amount_idr ELSE 0 END),0) received,COALESCE(SUM(o.status='paid' AND o.review_required=0),0) paid_count,COALESCE(SUM(o.review_required=1),0) review_count".$where, $args)[0];
		$rows = $this->rows('SELECT o.id,o.amount_idr,o.status,o.review_required,o.created_at,o.paid_at,o.order_code,u.display_name'.$where.' ORDER BY o.id DESC LIMIT 20 OFFSET '.(((int) $page - 1) * 20), $args);
		return compact('summary','rows');
	}
	public function donate($actor, $stamp, $amount, $revision, $key)
	{
		if ( ! $this->transaction_allowed($actor)) { return array('ok' => FALSE, 'code' => 403); }
		if ( ! $this->number($amount, 1, 10000000) || ! $this->number($revision, 1) || ! is_string($key) || ! preg_match('/\A[a-f0-9]{64}\z/', $key)) { return array('ok' => FALSE, 'code' => 400, 'message' => 'Masukkan nominal Rupiah bulat dalam batas yang ditampilkan.'); }
		$this->load->library(array('study_transaction','payment_secrets','midtrans_gateway'));
		$r = $this->study_transaction->run($this->db, function () use ($actor, $stamp, $amount, $revision, $key) {
			$u = $this->actor($actor, $stamp); if ( ! $u || ! $u['email_verified_at']) { return array('ok' => FALSE, 'code' => 403); }
			$hash = hash('sha256', 'donation|'.$amount.'|'.$revision);
			$old = $this->rows('SELECT id,request_hash,environment,purpose FROM st_payment_orders WHERE user_id=? AND request_key=? FOR UPDATE', array($actor, $key))[0] ?? NULL;
			if ($old) { return $old['purpose'] === 'donation' && $old['environment'] === $this->environment && hash_equals($old['request_hash'], $hash) ? array('ok' => TRUE, 'id' => (int) $old['id'], 'existing' => TRUE) : array('ok' => FALSE, 'code' => 409); }
			if ($this->rows('SELECT id FROM st_payment_orders WHERE user_id=? AND environment=? AND review_required=1 LIMIT 1 FOR UPDATE', array($actor, $this->environment))) { return array('ok' => FALSE, 'code' => 409, 'message' => 'Selesaikan peninjauan transaksi sebelumnya sebelum membuat donasi baru.'); }
			$this->rows("SELECT id FROM st_site_settings WHERE section='donasi' FOR UPDATE"); $s = $this->donation_settings(); $v = $s['values'];
			if ((int) $revision !== (int) $s['revision'] || $v['enabled'] !== '1' || (int) $amount < (int) $v['minimum'] || (int) $amount > (int) $v['maximum']) { return array('ok' => FALSE, 'code' => 409, 'message' => 'Pengaturan donasi berubah atau nominal di luar batas. Muat ulang formulir.'); }
			$c = $this->rows('SELECT * FROM st_payment_configs WHERE environment=? ORDER BY id DESC LIMIT 1 FOR UPDATE', array($this->environment))[0] ?? NULL;
			if ( ! $c || $c['probe_result'] !== 'status_api_reached' || ($this->environment === 'production' && ! $c['checkout_enabled'])) { return array('ok' => FALSE, 'code' => 409, 'message' => 'Penerimaan donasi menunggu kesiapan pembayaran Midtrans oleh pengelola.'); }
			$recent = $this->rows('SELECT id FROM st_payment_orders WHERE user_id=? AND created_at>UTC_TIMESTAMP()-INTERVAL 1 HOUR FOR UPDATE', array($actor));
			if (count($recent) >= 10) { return array('ok' => FALSE, 'code' => 409, 'message' => 'Maksimal 10 pesanan per jam. Periksa riwayat sebelum membuat yang baru.'); }
			$w = $this->payment_wallet($actor); $now = gmdate('Y-m-d H:i:s'); $code = ($this->environment === 'sandbox' ? 'PP-SB-' : 'PP-LIVE-').bin2hex(random_bytes(16));
			$snapshot = array('kind' => 'donation', 'title' => 'Donasi pengembangan Project Pintar', 'amount_idr' => (int) $amount, 'environment' => $this->environment, 'settings_revision' => (int) $revision, 'notice_version' => 'donation-043', 'no_rewards' => TRUE);
			$this->db->insert('st_payment_orders', array('user_id' => $actor, 'wallet_id' => $w['id'], 'purpose' => 'donation', 'order_code' => $code, 'provider_order_id' => $code, 'amount_idr' => (int) $amount, 'credit_amount' => 0, 'status' => 'created', 'environment' => $this->environment, 'offer_id' => NULL, 'config_id' => $c['id'], 'request_key' => $key, 'request_hash' => $hash, 'snapshot_json' => json_encode($snapshot), 'created_at' => $now, 'updated_at' => $now));
			$id = (int) $this->db->insert_id(); $this->audit($actor, 'donation.created', 'payment_order', $id, array('amount_idr' => (int) $amount, 'environment' => $this->environment, 'notice_version' => 'donation-043'));
			return array('ok' => TRUE, 'id' => $id, 'code' => $code, 'amount' => (int) $amount, 'key' => $this->payment_secrets->open($c['credentials_cipher'], $c['environment'])['server_key']);
		});
		if ( ! $r['ok'] || ! empty($r['existing'])) { return $r; }
		$remote = $this->midtrans_gateway->request($r['key'], $r['code'], array('payment_type' => 'qris', 'transaction_details' => array('order_id' => $r['code'], 'gross_amount' => $r['amount']), 'item_details' => array(array('id' => 'PP-DONATION', 'price' => $r['amount'], 'quantity' => 1, 'name' => 'Dukungan pengembangan Project Pintar')), 'qris' => array('acquirer' => 'gopay')), $this->environment);
		if (is_array($remote)) { $this->apply_payment_status($r['id'], $remote, FALSE); }
		return array('ok' => TRUE, 'id' => $r['id']);
	}
	private function payment_config($order)
	{
		$c = $this->rows("SELECT * FROM st_payment_configs WHERE id=? AND environment=?", array($order['config_id'], $order['environment']))[0] ?? NULL;
		if ( ! $c) { throw new RuntimeException('Payment configuration unavailable.'); }
		$this->load->library('payment_secrets'); $c['key'] = $this->payment_secrets->open($c['credentials_cipher'], $c['environment'])['server_key']; return $c;
	}
	private function matches_payment($order, $config, $v)
	{
		if ( ! is_array($v)) { return FALSE; }
		foreach (array('order_id','gross_amount','currency','payment_type','merchant_id','transaction_id','transaction_status','status_code') as $f) { if ( ! is_string($v[$f] ?? NULL)) { return FALSE; } }
		return in_array($order['environment'], array('sandbox','production'), TRUE) && $config['environment'] === $order['environment'] && strpos($order['order_code'], $order['environment'] === 'sandbox' ? 'PP-SB-' : 'PP-LIVE-') === 0 && $v['order_id'] === $order['order_code'] && preg_match('/\A[0-9]+(?:\.00)?\z/', $v['gross_amount']) && $v['gross_amount'] == (string) $order['amount_idr'] && $v['currency'] === 'IDR' && $v['payment_type'] === 'qris' && $v['merchant_id'] === $config['merchant_id'] && preg_match('/\A[A-Za-z0-9_-]{1,100}\z/', $v['transaction_id']) && ($order['provider_transaction_id'] === NULL || $order['provider_transaction_id'] === $v['transaction_id']) && (! isset($v['fraud_status']) || $v['fraud_status'] === 'accept');
	}
	public function reconcile($actor, $stamp, $id, $admin = FALSE)
	{
		$this->load->library(array('study_transaction','midtrans_gateway'));
		$r = $this->study_transaction->run($this->db, function () use ($actor, $stamp, $id, $admin) {
			if ( ! $this->actor($actor, $stamp, $admin)) { return array('ok' => FALSE, 'code' => 403); }
			$o = $this->rows("SELECT *,checked_at>UTC_TIMESTAMP()-INTERVAL 15 SECOND limited FROM st_payment_orders WHERE id=? AND environment IN ('sandbox','production') FOR UPDATE", array($id))[0] ?? NULL;
			if ( ! $o || ( ! $admin && (int) $o['user_id'] !== (int) $actor)) { return array('ok' => FALSE, 'code' => 404); }
			if ($o['limited']) { return array('ok' => FALSE, 'code' => 409); }
			$this->db->where('id', $id)->update('st_payment_orders', array('checked_at' => gmdate('Y-m-d H:i:s')));
			return array('ok' => TRUE, 'order' => $o);
		});
		if ( ! $r['ok']) { return $r; } $c = $this->payment_config($r['order']);
		$v = $this->midtrans_gateway->request($c['key'], $r['order']['order_code'], NULL, $r['order']['environment']);
		if ( ! $v) { return array('ok' => FALSE, 'code' => 503); }
		return $this->apply_payment_status($id, $v, TRUE);
	}
	public function notification($v)
	{
		if ( ! is_array($v) || ! is_string($v['order_id'] ?? NULL) || ! preg_match('/\APP-(?:SB|LIVE)-[a-f0-9]{32}\z/', $v['order_id']) || ! is_string($v['signature_key'] ?? NULL)) { return array('ok' => FALSE, 'code' => 400); }
		$o = $this->rows("SELECT * FROM st_payment_orders WHERE order_code=? AND environment IN ('sandbox','production')", array($v['order_id']))[0] ?? NULL;
		if ( ! $o) { return array('ok' => FALSE, 'code' => 404); } $c = $this->payment_config($o);
		if ( ! $this->matches_payment($o, $c, $v) || ! hash_equals(hash('sha512', $v['order_id'].$v['status_code'].$v['gross_amount'].$c['key']), $v['signature_key'])) { return array('ok' => FALSE, 'code' => 403); }
		$this->load->library('midtrans_gateway'); $remote = $this->midtrans_gateway->request($c['key'], $o['order_code'], NULL, $o['environment']);
		if ( ! $remote) { return array('ok' => FALSE, 'code' => 503); }
		return $this->apply_payment_status($o['id'], $remote, TRUE);
	}
	private function apply_payment_status($id, $v, $authoritative)
	{
		$this->load->library('study_transaction');
		$owner = $this->rows('SELECT user_id FROM st_payment_orders WHERE id=?', array($id))[0] ?? NULL;
		if ( ! $owner) { return array('ok' => FALSE, 'code' => 404); }
		return $this->study_transaction->run($this->db, function () use ($id, $v, $authoritative, $owner) {
			// All money mutations serialize on owner, including notifications arriving during checkout.
			$this->rows('SELECT id FROM st_users WHERE id=? FOR UPDATE', array($owner['user_id']));
			$o = $this->rows("SELECT * FROM st_payment_orders WHERE id=? AND environment IN ('sandbox','production') FOR UPDATE", array($id))[0] ?? NULL;
			if ( ! $o || ! $this->matches_payment($o, $this->payment_config($o), $v)) { return array('ok' => FALSE, 'code' => 409); }
			$state = $v['transaction_status']; $now = gmdate('Y-m-d H:i:s'); $data = array('provider_transaction_id' => $v['transaction_id'], 'updated_at' => $now);
			if (in_array($state, array('refund','partial_refund','chargeback','partial_chargeback'), TRUE))
			{
				if ( ! $authoritative) { return array('ok' => FALSE, 'code' => 409); }
				$data['review_required'] = 1; // Never silently debit spent credit or revoke an occupied child slot.
			}
			elseif ($state === 'settlement')
			{
				if ( ! $authoritative || $v['status_code'] !== '200') { return array('ok' => FALSE, 'code' => 409); }
				if ($o['review_required'] || $o['status'] === 'refunded') { return array('ok' => TRUE, 'id' => $id); }
				if ($o['status'] !== 'paid') { $this->fulfill_payment($o); $data['status'] = 'paid'; $data['paid_at'] = $now; }
			}
			elseif (in_array($state, array('pending','expire','deny','cancel'), TRUE))
			{
				$map = array('pending' => 'pending', 'expire' => 'expired', 'deny' => 'failed', 'cancel' => 'cancelled');
				if (in_array($o['status'], array('created','pending'), TRUE)) { $data['status'] = $map[$state]; }
			}
			else { return array('ok' => FALSE, 'code' => 409); }
			foreach ((is_array($v['actions'] ?? NULL) ? $v['actions'] : array()) as $action)
			{
				$url = is_array($action) ? ($action['url'] ?? NULL) : NULL;
				if (is_string($url) && preg_match('#\Ahttps://api\\.'.($o['environment'] === 'sandbox' ? 'sandbox\\.' : '').'midtrans\\.com/v[24]/qris/[A-Za-z0-9_-]{1,120}/qr-code\\z#', $url) && ($action['method'] ?? '') === 'GET') { $data['qr_url'] = $url; }
			}
			$this->db->where('id', $id)->update('st_payment_orders', $data);
			if (($data['status'] ?? $o['status']) !== $o['status'] || (($data['review_required'] ?? 0) && ! $o['review_required'])) { $this->audit(NULL, 'payment.status_verified', 'payment_order', $id, array('before' => $o['status'], 'after' => $data['status'] ?? $o['status'], 'review_required' => (bool) ($data['review_required'] ?? 0), 'source' => $authoritative ? 'status_api' : 'charge')); }
			return array('ok' => TRUE, 'id' => $id);
		});
	}
	private function fulfill_payment($o)
	{
		$s = json_decode($o['snapshot_json'], TRUE); $now = gmdate('Y-m-d H:i:s'); $actor = (int) $o['user_id'];
		if ($o['purpose'] === 'donation')
		{
			if (($s['kind'] ?? '') !== 'donation' || (int) $o['credit_amount'] !== 0 || $o['offer_id'] !== NULL || (int) ($s['amount_idr'] ?? 0) !== (int) $o['amount_idr']) { throw new RuntimeException('Donation snapshot invalid.'); }
			$this->audit($actor, 'donation.received', 'payment_order', $o['id'], array('amount_idr' => (int) $o['amount_idr'], 'environment' => $o['environment']));
			return; // Donation never grants credit, points, packages, Platinum or child slots.
		}
		$w = $this->rows("SELECT * FROM st_wallets WHERE id=? AND owner_user_id=? AND environment=? FOR UPDATE", array($o['wallet_id'], $actor, $o['environment']))[0] ?? NULL;
		if ( ! $w) { throw new RuntimeException('Payment wallet unavailable.'); }
		if ($s['kind'] === 'topup')
		{
			if ((int) $w['balance_credits'] + (int) $o['credit_amount'] > 100000000) { throw new RuntimeException('Wallet review required.'); }
			$this->db->where('id', $w['id'])->update('st_wallets', array('balance_credits' => (int) $w['balance_credits'] + (int) $o['credit_amount'], 'updated_at' => $now));
			$this->db->insert('st_wallet_entries', array('wallet_id' => $w['id'], 'actor_user_id' => $actor, 'entry_type' => 'top_up', 'delta_credits' => $o['credit_amount'], 'idempotency_key' => 'midtrans:'.$o['environment'].':'.$o['id'], 'reference_type' => 'payment_order', 'reference_id' => $o['id'], 'note' => 'Top up '.($o['environment'] === 'sandbox' ? 'uji' : 'live').' — '.$s['title'], 'created_at' => $now));
			return;
		}
		$this->db->insert('st_commerce_purchases', array('environment' => $o['environment'], 'owner_user_id' => $actor, 'offer_id' => $o['offer_id'], 'request_key' => hash('sha256', 'payment:'.$o['id']), 'request_hash' => hash('sha256', $o['order_code']), 'snapshot_json' => $o['snapshot_json'], 'status' => 'fulfilled', 'learner_user_id' => $actor, 'payment_order_id' => $o['id'], 'created_at' => $now)); $purchase = (int) $this->db->insert_id();
		if ($s['kind'] === 'slot') { $this->db->insert('st_family_slots', array('environment' => $o['environment'], 'owner_user_id' => $actor, 'purchase_id' => $purchase, 'created_at' => $now)); return; }
		if ($s['kind'] !== 'platinum') { throw new RuntimeException('Unknown purchase type.'); }
		$last = $this->rows("SELECT ends_at FROM st_commerce_grants WHERE owner_user_id=? AND kind='platinum' AND environment=? AND status='active' AND ends_at>UTC_TIMESTAMP() ORDER BY ends_at DESC LIMIT 1 FOR UPDATE", array($actor, $o['environment'])); $start = $last ? $last[0]['ends_at'] : $now;
		$this->db->insert('st_commerce_grants', array('environment' => $o['environment'], 'purchase_id' => $purchase, 'owner_user_id' => $actor, 'learner_user_id' => $actor, 'kind' => 'platinum', 'course_ids_json' => '[]', 'family_practice_enabled' => 1, 'starts_at' => $start, 'ends_at' => gmdate('Y-m-d H:i:s', strtotime($start.' UTC') + (int) $s['access_days'] * 86400)));
	}
}
