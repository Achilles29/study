<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Telegram_bot_context_model extends CI_Model
{
	private function one($sql, $args = array())
	{
		$query = $this->db->query($sql, $args);
		if ($query === FALSE) throw new RuntimeException('Bot operational summary unavailable.');
		return $query->row_array();
	}

	private function server_health()
	{
		$root = defined('FCPATH') ? FCPATH : APPPATH;
		$total = @disk_total_space($root);
		$free = @disk_free_space($root);
		$load = function_exists('sys_getloadavg') ? sys_getloadavg() : FALSE;
		$memory = @file_get_contents('/proc/meminfo');
		$available_gb = NULL; $total_gb = NULL;
		if (is_string($memory))
		{
			if (preg_match('/^MemAvailable:\s+(\d+) kB/m', $memory, $match)) $available_gb = round((int)$match[1] / 1048576, 1);
			if (preg_match('/^MemTotal:\s+(\d+) kB/m', $memory, $match)) $total_gb = round((int)$match[1] / 1048576, 1);
		}
		$worker = $this->one('SELECT worker_at FROM st_notification_config WHERE id=1');
		return array('database' => 'terhubung', 'disk_free_gb' => $free === FALSE ? NULL : round($free / 1073741824, 1), 'disk_total_gb' => $total === FALSE ? NULL : round($total / 1073741824, 1), 'memory_available_gb' => $available_gb, 'memory_total_gb' => $total_gb, 'load_1m' => is_array($load) ? round($load[0], 2) : NULL, 'notification_worker_at_utc' => $worker['worker_at'] ?? NULL);
	}

	public function snapshot($chat_id)
	{
		if (PHP_SAPI !== 'cli' || !is_string($chat_id) || !preg_match('/\A[1-9][0-9]*\z/', $chat_id)) throw new RuntimeException('Bot operational access denied.');
		$destination = $this->one('SELECT chat_id FROM st_notification_config WHERE id=1');
		if (!$destination || $destination['chat_id'] !== $chat_id) throw new RuntimeException('Bot operational access denied.');
		$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
		$wib = $now->setTimezone(new DateTimeZone('Asia/Jakarta'));
		$today = $wib->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
		$week = $wib->modify('-7 days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
		$month = $wib->modify('-30 days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
		$staff = "SELECT DISTINCT ur.user_id FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE r.role_code IN ('system_admin','content_editor','finance_operator','support_agent')";
		$member = "u.account_kind IN ('adult','parent') AND staff.user_id IS NULL";
		$users = $this->one("SELECT COUNT(*) all_accounts,
			COALESCE(SUM($member),0) member_registered,
			COALESCE(SUM($member AND u.status='active'),0) member_active,
			COALESCE(SUM($member AND u.status='pending'),0) member_pending,
			COALESCE(SUM($member AND u.status='suspended'),0) member_suspended,
			COALESCE(SUM($member AND u.created_at>=?),0) member_new_today,
			COALESCE(SUM($member AND u.created_at>=?),0) member_new_7d,
			COALESCE(SUM($member AND u.created_at>=?),0) member_new_30d,
			COALESCE(SUM(u.account_kind='child'),0) child_registered,
			COALESCE(SUM(u.account_kind='child' AND u.status='active'),0) child_active,
			COALESCE(SUM(staff.user_id IS NOT NULL),0) staff_accounts
			FROM st_users u LEFT JOIN ($staff) staff ON staff.user_id=u.id", array($today, $week, $month));
		$courses = $this->one("SELECT COUNT(*) total,COALESCE(SUM(status='published'),0) published,COALESCE(SUM(status='draft'),0) draft,COALESCE(SUM(status='review'),0) review FROM st_courses");
		$help = $this->one("SELECT COUNT(*) total,COALESCE(SUM(status='open'),0) open,COALESCE(SUM(status='waiting'),0) waiting FROM st_help_tickets");
		$reviews = $this->one("SELECT COUNT(*) total_real,COALESCE(SUM(status='published'),0) published_real FROM st_reviews WHERE is_demo=0");
		$practice = $this->one("SELECT COUNT(*) attempts_7d,COALESCE(SUM(status IN ('submitted','graded')),0) completed_7d FROM st_practice_attempts WHERE started_at>=?", array($week));
		$payments = $this->one("SELECT
			COALESCE(SUM(purpose='commerce' AND status='paid'),0) paid_orders,
			COALESCE(SUM(CASE WHEN purpose='commerce' AND status='paid' THEN amount_idr ELSE 0 END),0) paid_order_amount,
			COALESCE(SUM(purpose='commerce' AND status IN ('created','pending')),0) pending_orders,
			COALESCE(SUM(purpose='donation' AND status='paid'),0) paid_donations,
			COALESCE(SUM(CASE WHEN purpose='donation' AND status='paid' THEN amount_idr ELSE 0 END),0) paid_donation_amount
			FROM st_payment_orders WHERE environment='production'");
		return array('checked_at' => $wib->format('d/m/Y H:i:s').' WIB', 'users' => $users, 'courses' => $courses, 'help' => $help, 'reviews' => $reviews, 'practice' => $practice, 'payments' => $payments, 'server' => $this->server_health());
	}

	public function context_text($snapshot)
	{
		$u = $snapshot['users']; $c = $snapshot['courses']; $h = $snapshot['help']; $r = $snapshot['reviews']; $p = $snapshot['practice']; $pay = $snapshot['payments']; $server = $snapshot['server'];
		return "Ringkasan operasional Project Pintar, dicek {$snapshot['checked_at']}. Semua angka dibaca langsung dari database saat pesan diterima.\n"
			."Definisi member: akun dewasa/orang tua tanpa peran pengelola; akun anak terpisah. Member terdaftar {$u['member_registered']}, aktif {$u['member_active']}, menunggu {$u['member_pending']}, ditangguhkan {$u['member_suspended']}. Member baru hari ini {$u['member_new_today']}, 7 hari terakhir {$u['member_new_7d']}, 30 hari terakhir {$u['member_new_30d']}. Akun anak terdaftar {$u['child_registered']}, aktif {$u['child_active']}. Akun pengelola {$u['staff_accounts']}; semua akun termasuk anak/pengelola {$u['all_accounts']}.\n"
			."Kursus total {$c['total']}, terbit {$c['published']}, draf {$c['draft']}, dalam review {$c['review']}. Tiket bantuan total {$h['total']}, terbuka {$h['open']}, menunggu {$h['waiting']}. Ulasan asli {$r['total_real']}, terbit {$r['published_real']}. Percobaan latihan 7 hari {$p['attempts_7d']}, selesai {$p['completed_7d']}.\n"
			."Hanya pesanan produksi: pesanan berstatus dibayar {$pay['paid_orders']} dengan jumlah nominal Rp{$pay['paid_order_amount']}; pesanan menunggu {$pay['pending_orders']}. Donasi berstatus dibayar {$pay['paid_donations']} dengan jumlah nominal Rp{$pay['paid_donation_amount']}. Nominal tersebut bukan laba bersih.\n"
			.'Status server dari proses bot: '.json_encode($server, JSON_UNESCAPED_UNICODE).". Ini bukan uji keterjangkauan website dari internet.\n"
			."Untuk data yang tidak ada dalam ringkasan ini, katakan belum tersedia; jangan menebak atau mengaku sudah memeriksa detailnya.";
	}

	public function member_reply($snapshot)
	{
		$u = $snapshot['users'];
		return 'Per '.$snapshot['checked_at'].', ada '.number_format((int)$u['member_active'], 0, ',', '.').' member aktif (akun dewasa/orang tua, di luar pengelola). Total member yang pernah terdaftar '.number_format((int)$u['member_registered'], 0, ',', '.').'. Akun anak aktif '.number_format((int)$u['child_active'], 0, ',', '.').', dihitung terpisah.';
	}

	public function summary_reply($snapshot)
	{
		$u = $snapshot['users']; $c = $snapshot['courses']; $h = $snapshot['help']; $r = $snapshot['reviews'];
		return "Ringkasan Project Pintar · {$snapshot['checked_at']}\n"
			."Member aktif: {$u['member_active']} (baru hari ini: {$u['member_new_today']}); anak aktif: {$u['child_active']}.\n"
			."Kursus terbit: {$c['published']}; tiket terbuka: {$h['open']}; ulasan asli terbit: {$r['published_real']}.\n"
			.'Tanyakan angka tertentu untuk rincian yang tersedia.';
	}
}
