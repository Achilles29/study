<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Telegram_bot_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->load->library(array('study_transaction', 'telegram_gateway', 'openai_bot_gateway', 'codex_bot_gateway'));
		$this->load->model(array('Telegram_bot_context_model', 'Telegram_bot_query_model', 'Telegram_bot_commands_model'));
	}

	private function rows($sql, $args = array())
	{
		$query = $this->db->query($sql, $args);
		if ($query === FALSE) throw new RuntimeException('Bot storage unavailable.');
		return $query->result_array();
	}

	public function ai_settings()
	{
		return $this->rows('SELECT * FROM st_bot_ai_config WHERE id=1')[0];
	}

	public function save_ai_settings($user, $input)
	{
		$provider = $input['provider'] ?? 'api';
		if (!is_string($input['revision'] ?? NULL) || !ctype_digit($input['revision']) || !in_array($input['enabled'] ?? NULL, array('0', '1'), TRUE) || !in_array($provider, array('api', 'codex'), TRUE) || !is_string($input['key'] ?? NULL) || !is_string($input['daily_limit'] ?? NULL) || !ctype_digit($input['daily_limit']) || (int)$input['daily_limit'] < 1 || (int)$input['daily_limit'] > 100 || ($input['confirm'] ?? '') !== '1')
		{
			return array('ok' => FALSE, 'code' => 400, 'reason' => 'invalid_form');
		}
		$key = trim($input['key']);
		if ($key !== '' && (strlen($key) < 20 || strlen($key) > 300 || !preg_match('/\A[!-~]+\z/', $key))) return array('ok' => FALSE, 'code' => 400, 'reason' => 'invalid_key');
		return $this->study_transaction->run($this->db, function() use ($user, $input, $key, $provider) {
			$actor = $this->rows("SELECT u.id FROM st_users u JOIN st_user_roles ur ON ur.user_id=u.id JOIN st_roles r ON r.id=ur.role_id WHERE u.id=? AND u.status='active' AND r.role_code='system_admin' FOR UPDATE", array($user['id']))[0] ?? NULL;
			if (!$actor || !$user['auth_stamp'] || !hash_equals(study_credential_stamp($this->rows('SELECT * FROM st_users WHERE id=? FOR UPDATE', array($user['id']))[0]), $user['auth_stamp'])) return array('ok' => FALSE, 'code' => 403);
			$before = $this->rows('SELECT * FROM st_bot_ai_config WHERE id=1 FOR UPDATE')[0];
			if ((string)$before['revision'] !== $input['revision']) return array('ok' => FALSE, 'code' => 409);
			if ($provider === 'api' && $input['enabled'] === '1' && $key === '' && !$before['key_cipher']) return array('ok' => FALSE, 'code' => 400, 'reason' => 'key_required');
			$next = array('enabled' => (int)$input['enabled'], 'provider' => $provider, 'daily_limit' => (int)$input['daily_limit'], 'revision' => (int)$before['revision'] + 1, 'updated_at' => gmdate('Y-m-d H:i:s'));
			if ($key !== '') $next['key_cipher'] = $this->openai_bot_gateway->seal($key);
			$this->db->where('id', 1)->update('st_bot_ai_config', $next);
			$this->db->insert('st_audit_events', array('actor_user_id' => $user['id'], 'event_type' => 'bot_ai.settings_updated', 'entity_type' => 'bot_ai_config', 'entity_id' => 1, 'details_json' => json_encode(array('enabled' => $next['enabled'], 'provider' => $provider, 'daily_limit' => $next['daily_limit'], 'key_replaced' => $key !== '')), 'created_at' => gmdate('Y-m-d H:i:s')));
			return array('ok' => TRUE);
		});
	}

	public function poll()
	{
		if (PHP_SAPI !== 'cli') throw new RuntimeException('Bot polling is worker only.');
		if ((int)$this->rows("SELECT GET_LOCK('study_telegram_bot_poll',0) locked")[0]['locked'] !== 1) return array('ok' => TRUE, 'busy' => TRUE);
		try {
			$config = $this->rows('SELECT token_cipher,chat_id,revision FROM st_notification_config WHERE id=1')[0];
			if (!$config['token_cipher']) return array('ok' => TRUE, 'updates' => 0);
			$token = $this->telegram_gateway->open($config['token_cipher']);
			$state = $this->rows('SELECT * FROM st_telegram_bot_state WHERE id=1')[0];
			if (!$state['commands_set']) {
				$set = $this->telegram_gateway->set_commands($token);
				if ($set['ok']) $this->db->where('id', 1)->update('st_telegram_bot_state', array('commands_set' => 1, 'updated_at' => gmdate('Y-m-d H:i:s')));
			}
			$updates = $this->telegram_gateway->updates($token, (int)$state['next_update_id']);
			if (!$updates['ok']) return array('ok' => FALSE, 'error' => $updates['error'] ?? 'unavailable');
			if (!is_array($updates['result'] ?? NULL)) return array('ok' => FALSE, 'error' => 'invalid_response');
			$reply = $this->study_transaction->run($this->db, function() use ($config, $state, $updates) {
				$current = $this->rows('SELECT * FROM st_telegram_bot_state WHERE id=1 FOR UPDATE')[0];
				if ((int)$current['next_update_id'] !== (int)$state['next_update_id']) return array('ok' => TRUE, 'busy' => TRUE);
				$next = (int)$state['next_update_id'];
				$count = 0;
				foreach ($updates['result'] as $update) {
					$id = $update['update_id'] ?? NULL;
					if (!is_int($id) || $id < $next) continue;
					$next = max($next, $id + 1);
					$message = $update['message'] ?? NULL;
					if (!is_array($message) || !is_string($message['text'] ?? NULL)) continue;
					$chat = $message['chat'] ?? NULL;
					$from = $message['from'] ?? NULL;
					if (!is_array($chat) || !is_array($from) || ($chat['type'] ?? '') !== 'private' || !is_int($chat['id'] ?? NULL) || !is_int($from['id'] ?? NULL) || $chat['id'] !== $from['id'] || !empty($from['is_bot'])) continue;
					$chat_id = (string)$chat['id'];
					$date = $message['date'] ?? NULL;
					if (!$this->telegram_gateway->valid_chat_id($chat_id) || !is_int($date)) continue;
					$text = $message['text'];
					if (preg_match('/\A\/start pp_([a-f0-9]{32})\z/', $text, $match) && $date >= time() - 900 && $date <= time() + 30) {
						$this->db->query('INSERT IGNORE INTO st_telegram_pair_updates(code_hash,chat_id,message_at,created_at) VALUES(?,?,?,UTC_TIMESTAMP())', array(hash('sha256', $match[1]), $chat_id, gmdate('Y-m-d H:i:s', $date)));
					}
					if ($chat_id !== $config['chat_id'] || !is_int($message['message_id'] ?? NULL) || mb_strlen($text, 'UTF-8') > 4096) continue;
					$this->db->query('INSERT IGNORE INTO st_telegram_bot_inbox(update_id,chat_id,message_id,body_cipher,created_at) VALUES(?,?,?,?,UTC_TIMESTAMP())', array($id, $chat_id, $message['message_id'], $this->openai_bot_gateway->seal($text)));
					$count++;
				}
				$this->db->where('id', 1)->update('st_telegram_bot_state', array('next_update_id' => $next, 'updated_at' => gmdate('Y-m-d H:i:s')));
				$this->db->query('DELETE FROM st_telegram_pair_updates WHERE created_at<UTC_TIMESTAMP()-INTERVAL 15 MINUTE');
				$this->db->query('DELETE FROM st_telegram_bot_inbox WHERE created_at<UTC_TIMESTAMP()-INTERVAL 7 DAY');
				$this->db->query('DELETE FROM st_bot_ai_turns WHERE created_at<UTC_TIMESTAMP()-INTERVAL 7 DAY');
				return array('ok' => TRUE, 'updates' => count($updates['result']), 'accepted' => $count);
			});
			return $reply;
		} finally {
			$this->db->query("SELECT RELEASE_LOCK('study_telegram_bot_poll')");
		}
	}

	private function menu()
	{
		return $this->Telegram_bot_commands_model->menu();
	}

	private function operational_snapshot($chat_id)
	{
		try { return $this->Telegram_bot_context_model->snapshot($chat_id); }
		catch (Throwable $error) { log_message('error', 'Bot operational summary unavailable; details omitted.'); return NULL; }
	}

	private function member_count_question($text)
	{
		$text = mb_strtolower($text, 'UTF-8');
		if (preg_match('/\b(baru|kemarin|minggu|bulan|hari ini|7 hari|30 hari)\b/u', $text)) return FALSE;
		return (bool) (preg_match('/\b(berapa|jumlah|total|banyak)\b.{0,100}\b(member|anggota)\b/u', $text) || preg_match('/\b(member|anggota)\b.{0,100}\b(berapa|jumlah|total|banyak)\b/u', $text));
	}

	private function operational_question($text)
	{
		return (bool) preg_match('/\b(siapa|kapan|berapa|jumlah|total|banyak|paling|terbaru|terakhir|bulan|minggu|hari ini|kemarin|data|nama|member|anggota|pengguna|akun|pendaftaran|terdaftar|bergabung|orang tua|anak|murid|siswa|kursus|kelas|materi|pelajaran|tiket|bantuan|ulasan|review|latihan|soal|ujian|simulasi|pesanan|order|pembayaran|donasi|pendapatan|penjualan|omzet|transaksi|statistik|ringkasan|laporan|performa|operasional|pengunjung|kunjungan|halaman|dikunjungi|dibuka|traffic|arena|battle|paket|notifikasi|telegram|email|google|flashcard|progres|poin|kredit|server|disk|memori)\b/iu', $text);
	}

	private function simple_name_dataset($message, $history)
	{
		$text = mb_strtolower($message, 'UTF-8');
		if (!preg_match('/\b(nama|namanya|siapa|daftar)\b/u', $text) || preg_match('/\b(baru|hari|minggu|bulan|tahun|aktif|terbaru|terakhir)\b/u', $text)) return NULL;
		if (preg_match('/\b(anak|murid|siswa)\b/u', $text)) return 'children';
		if (preg_match('/\b(member|anggota)\b/u', $text)) return 'members';
		if (preg_match('/\b(pengunjung|kunjungan|kursus|pembayaran|donasi|tiket)\b/u', $text)) return NULL;
		foreach (array_reverse($history) as $turn)
		{
			if (($turn['role'] ?? '') !== 'user' || !is_string($turn['content'] ?? NULL)) continue;
			$previous = mb_strtolower($turn['content'], 'UTF-8');
			if (preg_match('/\b(anak|murid|siswa)\b/u', $previous)) return 'children';
			if (preg_match('/\b(member|anggota)\b/u', $previous)) return 'members';
			if (preg_match('/\b(pengunjung|kunjungan|kursus|pembayaran|donasi|tiket)\b/u', $previous)) return NULL;
		}
		return NULL;
	}

	private function planned_data_context($chat_id, $history, $message, $provider, $ai)
	{
		try {
			$catalog = $this->Telegram_bot_query_model->catalog_text();
			$plan = $provider === 'codex'
				? $this->codex_bot_gateway->plan($history, $message, $catalog)
				: $this->openai_bot_gateway->plan($this->openai_bot_gateway->open($ai['key_cipher']), $ai['model'], $history, $message, $catalog);
			if (!$plan['ok'] || !is_string($plan['text'] ?? NULL)) return '';
			$raw = trim($plan['text']);
			if (preg_match('/\A```(?:json)?\s*([\s\S]*?)\s*```\z/i', $raw, $match)) $raw = $match[1];
			$parsed = json_decode($raw, TRUE);
			if (!is_array($parsed)) $parsed = array('queries' => array());
			$name_dataset = $this->simple_name_dataset($message, $history);
			if ($name_dataset !== NULL)
			{
				$has_names = FALSE;
				foreach (is_array($parsed['queries'] ?? NULL) ? $parsed['queries'] : array() as $query)
				{
					if (($query['dataset'] ?? '') === $name_dataset && ($query['metric'] ?? '') === 'list' && in_array('name', $query['fields'] ?? array(), TRUE)) $has_names = TRUE;
				}
				if (!$has_names) $parsed = array('queries' => array(
					array('dataset' => $name_dataset, 'metric' => 'list', 'fields' => $name_dataset === 'children' ? array('name', 'parent_name', 'status') : array('name', 'status'), 'sort_by' => 'created_at', 'sort_dir' => 'desc', 'limit' => 15, 'filters' => array()),
					array('dataset' => $name_dataset, 'metric' => 'count', 'filters' => array())
				));
			}
			if (empty($parsed['queries'])) return '';
			$result = $this->Telegram_bot_query_model->execute($chat_id, $parsed);
			return 'Hasil kueri operasional terbaru yang sesuai pertanyaan, diperiksa '.$result['checked_at'].'. Nilai waktu datetime adalah UTC; tanggal day_wib dalam WIB. Gunakan hasil ini secara tepat. Jika hasil tidak cukup, katakan yang belum tersedia. '.json_encode($result['results'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		} catch (Throwable $error) {
			log_message('error', 'Bot planned data query unavailable; details omitted.');
			return '';
		}
	}

	private function ai_active($ai)
	{
		if (!$ai['enabled']) return FALSE;
		return ($ai['provider'] ?? 'api') === 'codex' ? $this->codex_bot_gateway->authenticated() : (bool)$ai['key_cipher'];
	}

	private function status_text($config, $ai)
	{
		$labels = array('members' => 'anggota baru', 'children' => 'akun anak', 'payments' => 'pembayaran', 'donations' => 'donasi', 'help' => 'bantuan', 'visitors' => 'kunjungan');
		$active = array();
		foreach ($labels as $key => $label) if ($config[$key]) $active[] = $label;
		return "Status Project Pintar\nNotifikasi: ".($config['enabled'] ? 'aktif' : 'nonaktif').".\nJenis aktif: ".($active ? implode(', ', $active) : 'tidak ada').".\nPercakapan AI: ".($this->ai_active($ai) ? 'aktif melalui '.(($ai['provider'] ?? 'api') === 'codex' ? 'Codex' : 'OpenAI API') : 'belum aktif').".\nPengaturan: https://www.projectpintar.com/kelola/pengaturan/telegram";
	}

	private function ai_answer($chat_id, $message, $ai)
	{
		$provider = $ai['provider'] ?? 'api';
		if ($ai['enabled'] && $provider === 'codex' && !$this->codex_bot_gateway->authenticated()) return array('text' => 'Codex untuk bot ini belum login ke ChatGPT. Pengelola perlu menyelesaikan login perangkat di server. /menu dan /status tetap tersedia.');
		if (!$ai['enabled'] || ($provider === 'api' && !$ai['key_cipher'])) return array('text' => 'Pesanmu sudah kuterima. Percakapan bebas belum aktif. Kamu masih bisa memakai /menu, /status, atau /bantuan. Pilihan koneksi AI ada di dashboard admin → Pengaturan Telegram → Percakapan AI.');
		$day = gmdate('Y-m-d', time() + 7 * 3600);
		$usage = $this->study_transaction->run($this->db, function() use ($day) {
			$row = $this->rows('SELECT * FROM st_bot_ai_config WHERE id=1 FOR UPDATE')[0];
			$count = $row['usage_day'] === $day ? (int)$row['used_count'] : 0;
			if ($count >= (int)$row['daily_limit']) return array('ok' => FALSE, 'code' => 409);
			$this->db->where('id', 1)->update('st_bot_ai_config', array('usage_day' => $day, 'used_count' => $count + 1));
			return array('ok' => TRUE);
		});
		if (!$usage['ok']) return array('text' => ($usage['code'] ?? 503) === 409 ? 'Batas percakapan AI hari ini sudah tercapai. Aku tetap bisa menjawab /menu dan /status.' : 'Pengaturan AI sedang belum dapat diperiksa. Coba lagi sebentar lagi.');
		$history = array();
		$rows = $this->rows('SELECT role,content_cipher FROM st_bot_ai_turns WHERE chat_id=? AND created_at>UTC_TIMESTAMP()-INTERVAL 7 DAY ORDER BY id DESC LIMIT 20', array($chat_id));
		foreach (array_reverse($rows) as $row) $history[] = array('role' => $row['role'], 'content' => $this->openai_bot_gateway->open($row['content_cipher']));
		$site_context = '';
		$site_context = $this->planned_data_context($chat_id, $history, $message, $provider, $ai);
		if ($site_context === '' && $this->operational_question($message))
		{
			$snapshot = $this->operational_snapshot($chat_id);
			$site_context = $snapshot ? $this->Telegram_bot_context_model->context_text($snapshot) : 'Data operasional sedang tidak tersedia. Jangan menebak angka terbaru.';
		}
		$result = $provider === 'codex' ? $this->codex_bot_gateway->reply($history, $message, $site_context) : $this->openai_bot_gateway->reply($this->openai_bot_gateway->open($ai['key_cipher']), $ai['model'], $history, $message, $site_context);
		if (!$result['ok']) return array('text' => ($result['error'] ?? '') === 'invalid_key' ? 'Kunci OpenAI ditolak. Perbarui kunci di pengaturan Telegram pada dashboard admin.' : (($result['error'] ?? '') === 'model_unavailable' ? 'Model AI belum tersedia untuk proyek API ini. Periksa akses model pada OpenAI Platform.' : 'Maaf, layanan AI sedang belum bisa menjawab. Coba lagi sebentar lagi.'));
		$this->db->insert('st_bot_ai_turns', array('chat_id' => $chat_id, 'role' => 'user', 'content_cipher' => $this->openai_bot_gateway->seal(mb_substr($message, 0, 1500, 'UTF-8')), 'created_at' => gmdate('Y-m-d H:i:s')));
		$this->db->insert('st_bot_ai_turns', array('chat_id' => $chat_id, 'role' => 'assistant', 'content_cipher' => $this->openai_bot_gateway->seal($result['text']), 'created_at' => gmdate('Y-m-d H:i:s')));
		$this->db->query('DELETE FROM st_bot_ai_turns WHERE created_at<UTC_TIMESTAMP()-INTERVAL 7 DAY');
		return array('text' => $result['text']);
	}

	public function respond()
	{
		if (PHP_SAPI !== 'cli') throw new RuntimeException('Bot responses are worker only.');
		if ((int)$this->rows("SELECT GET_LOCK('study_telegram_bot_response',0) locked")[0]['locked'] !== 1) return array('ok' => TRUE, 'busy' => TRUE);
		try {
			$processed = 0;
			for ($i = 0; $i < 1; $i++) {
				$entry = $this->rows("SELECT * FROM st_telegram_bot_inbox WHERE status='pending' ORDER BY update_id LIMIT 1")[0] ?? NULL;
				if (!$entry) break;
				$this->db->where('update_id', $entry['update_id'])->where('status', 'pending')->update('st_telegram_bot_inbox', array('status' => 'processing'));
				if ($this->db->affected_rows() !== 1) continue;
				try {
					$config = $this->rows('SELECT * FROM st_notification_config WHERE id=1')[0];
					$ai = $this->ai_settings();
					if (!$config['token_cipher'] || $config['chat_id'] !== $entry['chat_id']) {
						$this->db->where('update_id', $entry['update_id'])->update('st_telegram_bot_inbox', array('status' => 'failed', 'body_cipher' => '', 'error' => 'destination_changed', 'processed_at' => gmdate('Y-m-d H:i:s')));
						continue;
					}
					$text = trim($this->openai_bot_gateway->open($entry['body_cipher']));
					$command = mb_strtolower(strtok($text, " \n\r\t") ?: '', 'UTF-8');
					if (strpos($command, '@') !== FALSE) $command = strstr($command, '@', TRUE);
					if ($command === '/start' || $command === '/menu') $answer = $this->menu();
					elseif ($command === '/status') $answer = $this->status_text($config, $ai);
					elseif ($command === '/ringkasan' || $command === '/data') { $snapshot = $this->operational_snapshot($entry['chat_id']); $answer = $snapshot ? $this->Telegram_bot_context_model->summary_reply($snapshot) : 'Ringkasan operasional sedang belum bisa dibaca. Coba lagi sebentar lagi.'; }
					elseif ($command === '/server') { $snapshot = $this->operational_snapshot($entry['chat_id']); $server = $snapshot['server'] ?? NULL; $answer = $server ? 'Server · '.$snapshot['checked_at']."\nDatabase: {$server['database']}. Disk kosong: {$server['disk_free_gb']} dari {$server['disk_total_gb']} GB. Memori tersedia: {$server['memory_available_gb']} dari {$server['memory_total_gb']} GB. Beban 1 menit: {$server['load_1m']}. Pekerja notifikasi terakhir (UTC): {$server['notification_worker_at_utc']}." : 'Status server belum bisa dibaca.'; }
					elseif ($command === '/bantuan' || $command === '/help') $answer = "Buka /menu untuk semua perintah data. Tambahkan opsi waktu setelah perintah, misalnya /anak nama, /pengunjung hari_ini, atau /halaman terbaru 10. Anda juga bisa bertanya dengan kalimat biasa; Codex memilih data yang diperlukan. Pengaturan: https://www.projectpintar.com/kelola/pengaturan/telegram";
					elseif ($this->member_count_question($text)) { $snapshot = $this->operational_snapshot($entry['chat_id']); $answer = $snapshot ? $this->Telegram_bot_context_model->member_reply($snapshot) : 'Jumlah member sedang belum bisa dibaca. Coba lagi sebentar lagi.'; }
					elseif (strpos($command, '/') === 0 && ($data_answer = $this->Telegram_bot_commands_model->answer($entry['chat_id'], substr($command, 1), trim(substr($text, strlen(strtok($text, " \n\r\t")))))) !== NULL) $answer = $data_answer;
					else $answer = $this->ai_answer($entry['chat_id'], $text, $ai)['text'];
					$this->db->insert('st_telegram_outbox', array('event_key' => 'bot-reply:'.$entry['update_id'], 'category' => 'bot_reply', 'body' => mb_substr($answer, 0, 1500, 'UTF-8'), 'config_revision' => $config['revision'], 'reply_to_message_id' => $entry['message_id'], 'next_at' => gmdate('Y-m-d H:i:s'), 'created_at' => gmdate('Y-m-d H:i:s')));
					$this->db->where('update_id', $entry['update_id'])->update('st_telegram_bot_inbox', array('status' => 'done', 'body_cipher' => '', 'processed_at' => gmdate('Y-m-d H:i:s')));
					$processed++;
				} catch (Throwable $error) {
					log_message('error', 'Bot response failed; secret details omitted.');
					$this->db->where('update_id', $entry['update_id'])->update('st_telegram_bot_inbox', array('status' => 'failed', 'body_cipher' => '', 'error' => 'processing_failed', 'processed_at' => gmdate('Y-m-d H:i:s')));
				}
			}
			return array('ok' => TRUE, 'processed' => $processed);
		} finally {
			$this->db->query("SELECT RELEASE_LOCK('study_telegram_bot_response')");
		}
	}
}
