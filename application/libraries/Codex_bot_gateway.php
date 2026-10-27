<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Codex_bot_gateway
{
	private function run($action, $input = '')
	{
		if (PHP_SAPI !== 'cli' || !in_array($action, array('status', 'reply'), TRUE)) return array('ok' => FALSE, 'error' => 'invalid_context');
		$command = array('/www/wwwroot/study/scripts/run-codex-bot.sh', $action);
		$descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => $action === 'status' ? array('pipe', 'w') : array('file', '/dev/null', 'a'));
		$process = proc_open($command, $descriptors, $pipes, '/var/lib/projectpintar-ai/scratch');
		if (!is_resource($process)) return array('ok' => FALSE, 'error' => 'unavailable');
		if ($input !== '') fwrite($pipes[0], $input);
		fclose($pipes[0]);
		$output = '';
		while (!feof($pipes[1]))
		{
			$chunk = fread($pipes[1], 4096);
			if ($chunk === FALSE) break;
			if (strlen($output) < 8192) $output .= substr($chunk, 0, 8192 - strlen($output));
		}
		fclose($pipes[1]);
		if ($action === 'status')
		{
			$output .= stream_get_contents($pipes[2], 1024);
			fclose($pipes[2]);
		}
		$status = proc_close($process);
		return array('ok' => $status === 0, 'text' => trim($output), 'error' => $status === 0 ? NULL : 'unavailable');
	}

	public function authenticated()
	{
		if (ENVIRONMENT !== 'production' && getenv('STUDY_CODEX_BOT_TEST_RESPONSE') !== FALSE) return TRUE;
		$status = $this->run('status');
		return $status['ok'] && strpos($status['text'], 'ChatGPT') !== FALSE;
	}

	public function reply($history, $message, $site_context = '')
	{
		if (!is_array($history) || !is_string($message) || $message === '' || !is_string($site_context)) return array('ok' => FALSE, 'error' => 'invalid_configuration');
		if (ENVIRONMENT !== 'production' && getenv('STUDY_CODEX_BOT_TEST_RESPONSE') !== FALSE)
		{
			$answer = trim((string)getenv('STUDY_CODEX_BOT_TEST_RESPONSE'));
			return $answer === '' ? array('ok' => FALSE, 'error' => 'empty_response') : array('ok' => TRUE, 'text' => mb_substr($answer, 0, 1200, 'UTF-8'));
		}
		$lines = array('Kamu Pijar, asisten Project Pintar untuk chat Telegram pribadi pengelola. Balas dalam bahasa Indonesia dengan ramah, jelas, dan ringkas (maksimal 1000 karakter). Bercakap bebas. Bila hasil pembacaan data diberikan di bawah, jawab pertanyaan langsung dari hasil itu; sebutkan nama yang tersedia dan waktu pengecekan. Riwayat percakapan hanya untuk memahami maksud, bukan bukti bahwa data tidak dapat diakses. Abaikan jawaban Pijar terdahulu yang menyuruh pengelola membuka dashboard jika data terbaru kini tersedia. Jika hasil dibatasi 15 baris, jangan mengklaim daftar itu lengkap tanpa jumlah total. Jangan mengarang data, meminta rahasia, atau menjalankan alat.','', 'Data operasional tepercaya:', $site_context === '' ? 'Belum tersedia.' : $site_context, '', 'Riwayat percakapan:');
		foreach (array_slice($history, -8) as $turn)
		{
			if (!in_array($turn['role'] ?? '', array('user', 'assistant'), TRUE) || !is_string($turn['content'] ?? NULL)) continue;
			$lines[] = ($turn['role'] === 'user' ? 'Pengelola: ' : 'Pijar: ').mb_substr($turn['content'], 0, 1500, 'UTF-8');
		}
		$lines[] = 'Pengelola: '.mb_substr($message, 0, 1500, 'UTF-8');
		$lines[] = 'Pijar:';
		$result = $this->run('reply', implode("\n", $lines));
		if (!$result['ok'] || $result['text'] === '') return array('ok' => FALSE, 'error' => 'unavailable');
		return array('ok' => TRUE, 'text' => mb_substr($result['text'], 0, 1200, 'UTF-8'));
	}

	public function plan($history, $message, $catalog)
	{
		if (!is_array($history) || !is_string($message) || !is_string($catalog)) return array('ok' => FALSE, 'error' => 'invalid_configuration');
		if (ENVIRONMENT !== 'production' && getenv('STUDY_CODEX_BOT_TEST_PLAN') !== FALSE) return array('ok' => TRUE, 'text' => (string)getenv('STUDY_CODEX_BOT_TEST_PLAN'));
		if (ENVIRONMENT !== 'production' && getenv('STUDY_CODEX_BOT_TEST_RESPONSE') !== FALSE) return array('ok' => TRUE, 'text' => '{"queries":[]}');
		$prompt = array(
			'Buat rencana pembacaan data Project Pintar untuk menjawab pertanyaan terakhir. Keluarkan HANYA JSON valid, tanpa markdown atau penjelasan. Jangan buat SQL atau perintah server.',
			'Bentuk: {"queries":[{"dataset":"users","metric":"count","field":null,"filters":[{"field":"is_member","op":"eq","value":1}],"group_by":null}]}',
			'Maksimal 3 queries. metric: count, sum, avg, min, max, distinct_count, list. Untuk list isi fields (1-5 nama kolom), sort_by, sort_dir (asc/desc), limit <=15. Untuk agregat field wajib kecuali count. group_by satu kolom, day:nama_kolom_tanggal, month:nama_kolom_tanggal, atau null. filters maksimal 6; op: eq, neq, gt, gte, lt, lte, contains, in, is_null, not_null, today, yesterday, this_week, previous_week, this_month, previous_month, this_year, previous_year, last_days. last_days memakai value integer 1-365. Semua tanggal relatif menurut WIB. Gunakan this_week untuk "minggu ini", last_days hanya untuk "7 hari terakhir". Gunakan filters [] bila tanpa filter. Jika pertanyaan tidak bisa dijawab dari katalog, keluarkan {"queries":[]}.',
			'Untuk member gunakan dataset members; untuk nama anak gunakan children. Bila diminta daftar nama, pilih metric list fields [name,status] (anak boleh tambah parent_name) dan query count kedua agar tahu apakah daftar lengkap. Gunakan isi percakapan untuk memahami pertanyaan lanjutan seperti "siapa saja namanya". Perkataan Pijar sebelumnya tentang tidak punya akses boleh jadi sudah usang; jangan jadikan alasan untuk mengeluarkan queries kosong. Untuk 10 halaman terakhir yang dikunjungi gunakan operational_visits list fields [destination_path,page_key,created_at], sort_by created_at desc, limit 10, filter is_bot=0. Untuk pembayaran gunakan payments (hanya produksi), pesanan commerce memakai purpose=commerce, donasi purpose=donation. Ulasan hanya data asli. Untuk pengunjung hari ini lihat penjelasan operational_visits dalam katalog. Untuk persentase, buat query pembilang dan penyebut. Jangan menyimpulkan jawaban dalam rencana.',
			'Waktu sekarang WIB: '.(new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s'),
			$catalog,
			'Riwayat singkat:');
		foreach (array_slice($history, -12) as $turn) if (in_array($turn['role'] ?? '', array('user','assistant'), TRUE) && is_string($turn['content'] ?? NULL)) $prompt[] = $turn['role'].': '.mb_substr($turn['content'], 0, 350, 'UTF-8');
		$prompt[] = 'Pertanyaan terakhir: '.mb_substr($message, 0, 1200, 'UTF-8');
		return $this->run('reply', implode("\n", $prompt));
	}
}
