<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Telegram_bot_commands_model extends CI_Model
{
	private function definitions()
	{
		return array(
			'member'=>array('members','Member','created_at',array('name','status','created_at'),'status'),
			'anak'=>array('children','Akun anak','created_at',array('name','parent_name','status'),'status'),
			'akun'=>array('users','Semua akun','created_at',NULL,'status'),
			'keluarga'=>array('family_links','Hubungan keluarga','created_at',NULL,'status'),
			'google'=>array('google_accounts','Akun Google','created_at'),
			'kursus'=>array('courses','Kursus','created_at',array('title','status','created_at'),'status'),
			'pelajaran'=>array('lessons','Pelajaran','created_at',array('title','status','created_at'),'status'),
			'soal'=>array('questions','Soal','created_at',NULL,'status'),
			'ujian'=>array('exams','Simulasi ujian','created_at',array('title','status','created_at'),'status'),
			'sesi'=>array('practice_sessions','Sesi latihan','created_at',array('title','session_type','status'),'status'),
			'latihan'=>array('practice_attempts','Pengerjaan latihan','started_at',NULL,'status'),
			'nilai'=>array('practice_attempts','Rata-rata nilai latihan','submitted_at',NULL,NULL,'avg','score'),
			'kecepatan'=>array('practice_answer_events','Rata-rata waktu jawaban','created_at',NULL,NULL,'avg','elapsed_ms',' ms'),
			'pendaftaran_kursus'=>array('enrollments','Pendaftaran kursus','enrolled_at',NULL,'status'),
			'progres'=>array('lesson_progress','Progres pelajaran','started_at',NULL,'status'),
			'pembayaran'=>array('payments','Pesanan produksi','created_at',array('order_code','status','amount_idr','created_at'),'status','count',NULL,NULL,array(array('field'=>'purpose','op'=>'eq','value'=>'commerce'))),
			'donasi'=>array('payments','Donasi produksi','created_at',array('order_code','status','amount_idr','created_at'),'status','count',NULL,NULL,array(array('field'=>'purpose','op'=>'eq','value'=>'donation'))),
			'penawaran'=>array('offers','Penawaran','created_at',array('title','kind','status'),'status'),
			'paket'=>array('packages','Paket','created_at',array('title','status','created_at'),'status'),
			'tiket'=>array('help_tickets','Tiket bantuan','created_at',NULL,'status'),
			'ulasan'=>array('reviews','Ulasan asli','created_at',NULL,'status'),
			'arena'=>array('arena_runs','Permainan arena','started_at',NULL,'status'),
			'paket_arena'=>array('arena_packs','Paket arena','created_at',array('title','game_type','enabled')),
			'progres_arena'=>array('arena_card_progress','Progres arena','updated_at',NULL,'status'),
			'battle'=>array('battle_rooms','Ruang battle','created_at',array('title','status','created_at'),'status'),
			'peserta_battle'=>array('battle_entries','Peserta battle','created_at',NULL,'status'),
			'flashcard'=>array('flashcard_decks','Dek flashcard','created_at',array('title','created_at')),
			'progres_flashcard'=>array('flashcard_progress','Progres flashcard','last_reviewed_at',NULL,'status'),
			'poin'=>array('point_entries','Perubahan poin','created_at',NULL,NULL,'sum','delta_points',' poin'),
			'saldo_poin'=>array('point_wallets','Saldo poin anak','updated_at',NULL,NULL,'sum','balance_points',' poin'),
			'akses'=>array('feature_unlocks','Fitur terbuka','created_at'),
			'notifikasi'=>array('notifications','Notifikasi aplikasi','created_at',NULL,'category'),
			'telegram'=>array('telegram_deliveries','Pengiriman Telegram','created_at',NULL,'status'),
			'email'=>array('email_deliveries','Pengiriman email','created_at',NULL,'status'),
			'pengunjung'=>array('operational_visits','Perkiraan pengunjung unik','created_at',NULL,NULL,'distinct_count','ip_key',' IP unik',array(array('field'=>'is_bot','op'=>'eq','value'=>0))),
			'kunjungan'=>array('operational_visits','Tampilan halaman','created_at',NULL,NULL,'count',NULL,NULL,array(array('field'=>'is_bot','op'=>'eq','value'=>0))),
			'halaman'=>array('operational_visits','Halaman terakhir dikunjungi','created_at',array('destination_path','page_key','created_at'),NULL,'list',NULL,NULL,array(array('field'=>'is_bot','op'=>'eq','value'=>0))),
			'kunjungan_izin'=>array('traffic_events','Pengunjung yang menyetujui pelacakan','created_at',NULL,NULL,'distinct_count','visitor_key',' pengunjung'),
			'arsip_kunjungan'=>array('traffic_daily','Arsip tampilan halaman','day_wib',NULL,NULL,'sum','views',' tampilan'),
			'audit'=>array('audit_events','Peristiwa pengelolaan','created_at',NULL,'event_type')
		);
	}

	public function telegram_commands()
	{
		$commands = array(
			array('command'=>'start','description'=>'Mulai memakai bot'),
			array('command'=>'menu','description'=>'Semua perintah data dan opsi waktu'),
			array('command'=>'status','description'=>'Status notifikasi dan AI'),
			array('command'=>'ringkasan','description'=>'Angka utama Project Pintar'),
			array('command'=>'server','description'=>'Status dasar server'),
			array('command'=>'bantuan','description'=>'Panduan singkat bot')
		);
		foreach ($this->definitions() as $name=>$definition) $commands[] = array('command'=>$name,'description'=>$definition[1]);
		return $commands;
	}

	public function menu()
	{
		return "Pijar · data Project Pintar\n"
			."Akun: /member /anak /akun /keluarga /google\n"
			."Belajar: /kursus /pelajaran /soal /ujian /sesi /latihan /nilai /kecepatan /pendaftaran_kursus /progres\n"
			."Transaksi: /pembayaran /donasi /penawaran /paket /poin /saldo_poin /akses\n"
			."Komunitas: /tiket /ulasan /arena /paket_arena /progres_arena /battle /peserta_battle /flashcard /progres_flashcard\n"
			."Operasional: /pengunjung /kunjungan /halaman /kunjungan_izin /arsip_kunjungan /notifikasi /telegram /email /audit /server\n"
			."Lainnya: /ringkasan /status /bantuan\n\n"
			."Opsi waktu: hari_ini, kemarin, minggu_ini, minggu_lalu, 7_hari, bulan_ini, bulan_lalu, 30_hari, tahun_ini, tahun_lalu, semua, tgl_2026-10-01, atau 2026-10-01..2026-10-07.\n"
			."Opsi data: nama (/member, /anak), terbaru [jumlah], status, populer (/halaman).\n"
			."Contoh: /anak nama · /member nama bulan_ini · /halaman terbaru 10 · /pengunjung hari_ini · /pembayaran status bulan_ini.\n"
			."Tulis pertanyaan biasa di luar menu; Codex akan mencari datanya.";
	}

	private function period($token)
	{
		$presets = array('hari_ini'=>array('today','hari ini'), 'kemarin'=>array('yesterday','kemarin'), 'minggu_ini'=>array('this_week','minggu ini'), 'minggu_lalu'=>array('previous_week','minggu lalu'), '7_hari'=>array('last_days','7 hari terakhir',7), 'bulan_ini'=>array('this_month','bulan ini'), 'bulan_lalu'=>array('previous_month','bulan lalu'), '30_hari'=>array('last_days','30 hari terakhir',30), 'tahun_ini'=>array('this_year','tahun ini'), 'tahun_lalu'=>array('previous_year','tahun lalu'), 'semua'=>array(NULL,'seluruh data yang tersedia'));
		if (isset($presets[$token])) return $presets[$token];
		if (!preg_match('/\A(?:tgl_)?(\d{4}-\d{2}-\d{2})(?:\.\.(\d{4}-\d{2}-\d{2}))?\z/', $token, $match)) return NULL;
		$zone = new DateTimeZone('Asia/Jakarta');
		$start = DateTimeImmutable::createFromFormat('!Y-m-d', $match[1], $zone);
		$last = DateTimeImmutable::createFromFormat('!Y-m-d', $match[2] ?? $match[1], $zone);
		if (!$start || !$last || $start->format('Y-m-d') !== $match[1] || $last->format('Y-m-d') !== ($match[2] ?? $match[1]) || $last < $start || $last > $start->modify('+366 days')) return NULL;
		return array('custom', isset($match[2]) ? $match[1].' sampai '.$match[2] : $match[1], $start, $last->modify('+1 day'));
	}

	private function format_rows($rows, $checked_at)
	{
		$lines = array();
		foreach ($rows as $index=>$row)
		{
			$parts = array();
			foreach ($row as $field=>$value)
			{
				if ($value === NULL || $value === '') continue;
				if (substr($field, -3) === '_at' && preg_match('/\A\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\z/', (string)$value)) $value = (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d/m/Y H:i');
				$parts[] = mb_substr((string)$value, 0, 100, 'UTF-8');
			}
			$lines[] = ($index+1).'. '.implode(' · ', $parts);
		}
		return implode("\n", $lines);
	}

	public function answer($chat_id, $command, $arguments)
	{
		$definitions = $this->definitions();
		if (!isset($definitions[$command])) return NULL;
		$d = $definitions[$command];
		$words = preg_split('/\s+/u', mb_strtolower(trim($arguments), 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
		if (count($words) > 5) return 'Terlalu banyak opsi. Buka /menu untuk contoh perintah.';
		$action = NULL; $period = NULL; $limit = 10;
		foreach ($words as $word)
		{
			if (($p = $this->period($word)) !== NULL) { if ($period !== NULL) return 'Pilih satu opsi waktu saja. Lihat /menu.'; $period = $p; }
			elseif (in_array($word, array('nama','terbaru','terakhir','status','populer','jumlah'), TRUE)) $action = $word;
			elseif (ctype_digit($word) && (int)$word >= 1 && (int)$word <= 15) $limit = (int)$word;
			else return 'Opsi "'.$word.'" belum dikenali. Lihat /menu untuk pilihan waktu dan data.';
		}
		if ($period === NULL) $period = in_array($command, array('pengunjung','kunjungan'), TRUE) ? $this->period('hari_ini') : $this->period('semua');
		$filters = $d[8] ?? array();
		if ($period[0] === 'custom')
		{
			$utc = $d[0] === 'traffic_daily';
			$start = $utc ? $period[2] : $period[2]->setTimezone(new DateTimeZone('UTC'));
			$end = $utc ? $period[3] : $period[3]->setTimezone(new DateTimeZone('UTC'));
			$format = $utc ? 'Y-m-d' : 'Y-m-d H:i:s';
			$filters[] = array('field'=>$d[2], 'op'=>'gte', 'value'=>$start->format($format));
			$filters[] = array('field'=>$d[2], 'op'=>'lt', 'value'=>$end->format($format));
		}
		elseif ($period[0] !== NULL)
		{
			$filter = array('field'=>$d[2], 'op'=>$period[0]);
			if (isset($period[2])) $filter['value']=$period[2];
			$filters[] = $filter;
		}
		$metric = $d[5] ?? 'count'; $field = $d[6] ?? NULL;
		if ($action === 'nama' || $action === 'terbaru' || $action === 'terakhir' || ($command === 'halaman' && $action !== 'jumlah' && $action !== 'populer'))
		{
			if (!$d[3]) return 'Daftar rinci untuk /'.$command.' belum tersedia. Coba /'.$command.' status atau tanyakan dengan kalimat biasa.';
			$metric = 'list';
		}
		if ($action === 'status' || $action === 'populer')
		{
			$group = $action === 'populer' && $command === 'halaman' ? 'page_key' : ($d[4] ?? NULL);
			if (!$group) return 'Pengelompokan ini belum tersedia untuk /'.$command.'.';
			$metric = 'count';
		}
		else $group = NULL;
		if ($action === 'jumlah') { $metric='count'; $field=NULL; }
		$plan = array('dataset'=>$d[0], 'metric'=>$metric, 'filters'=>$filters);
		if ($metric === 'list') { $plan['fields']=$d[3]; $plan['sort_by']=$d[2]; $plan['sort_dir']='desc'; $plan['limit']=$limit; }
		elseif ($group) $plan['group_by']=$group;
		elseif ($field !== NULL && $metric !== 'count') $plan['field']=$field;
		$queries = array($plan);
		if ($metric === 'list') $queries[] = array('dataset'=>$d[0], 'metric'=>'count', 'filters'=>$filters);
		$this->load->model('Telegram_bot_query_model');
		try { $data = $this->Telegram_bot_query_model->execute($chat_id, array('queries'=>$queries)); }
		catch (Throwable $error) { log_message('error','Bot command data unavailable; details omitted.'); return 'Data /'.$command.' sedang belum bisa dibaca. Coba lagi sebentar lagi.'; }
		$rows = $data['results'][0]['rows'];
		$heading = $d[1].' · '.$period[1].' · '.$data['checked_at'];
		if ($metric === 'list')
		{
			$total = (int)$data['results'][1]['rows'][0]['value'];
			return $heading."\n".($rows ? $this->format_rows($rows, $data['checked_at']) : 'Belum ada data.')."\nMenampilkan ".count($rows).' dari '.$total.'.';
		}
		if ($group)
		{
			$lines = array(); foreach ($rows as $row) $lines[] = ($row['group_value'] === NULL || $row['group_value'] === '' ? 'Tanpa nilai' : $row['group_value']).': '.number_format((int)$row['value'],0,',','.');
			return $heading."\n".($lines ? implode("\n",$lines) : 'Belum ada data.');
		}
		$value = $rows[0]['value'] ?? NULL;
		if ($value === NULL) return $heading."\nBelum ada data untuk dihitung.";
		if ($metric === 'avg') $shown = number_format((float)$value, 2, ',', '.');
		else $shown = number_format((int)$value, 0, ',', '.');
		return $heading."\n".$shown.($d[7] ?? ($command === 'pengunjung' ? ' IP unik (perkiraan pengunjung)' : '')).($command === 'pengunjung' ? '. Satu IP bisa dipakai beberapa orang.' : '.');
	}
}
