<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Site_settings
{
	public function definitions()
	{
		return array(
			'akses_arena'=>array('title'=>'Akses kredit Arena','fields'=>array(
				'enabled'=>array('label'=>'Persetujuan pemetaan level & harga Arena (lihat Kelola akses Arena dahulu)','type'=>'select','options'=>array('0'=>'Draft — permainan tetap mengikuti akses sebelumnya','1'=>'Setujui dan aktifkan: level 1–2 gratis, berikutnya kredit'),'default'=>'0')
			)),
			'latihan_mandiri' => array('title'=>'Latihan Mandiri & Simulasi Ujian','fields'=>array(
				'parent_live_enabled'=>array('label'=>'Orang tua tertaut dapat memantau soal dan jawaban latihan yang sedang berlangsung','type'=>'select','options'=>array('0'=>'Nonaktif','1'=>'Aktif (simulasi tetap memerlukan izin per ujian)'),'default'=>'0'),
				'daily_free'=>array('label'=>'Sesi Latihan Mandiri gratis per akun per hari WIB','type'=>'select','options'=>array('3'=>'3 sesi','5'=>'5 sesi','10'=>'10 sesi'),'default'=>'3'),
				'extra_count'=>array('label'=>'Tambahan sesi untuk satu penukaran kuota harian','type'=>'select','options'=>array('5'=>'5 sesi','10'=>'10 sesi'),'default'=>'5'),
				'extra_price'=>array('label'=>'Draft harga tambahan kuota (kredit, satu kali per penerima per hari)','type'=>'select','options'=>array('5'=>'5 kredit','10'=>'10 kredit','20'=>'20 kredit'),'default'=>'10'),
				'paid_enabled'=>array('label'=>'Persetujuan harga & aktivasi penukaran kredit latihan (bukan aktivasi Midtrans)','type'=>'select','options'=>array('0'=>'Draft, jangan potong kredit','1'=>'Saya menyetujui harga dan mengaktifkan penukaran'),'default'=>'0')
			)),
			'donasi' => array('title' => 'Donasi sukarela', 'fields' => array(
				'enabled' => array('label' => 'Modul donasi (pembayaran tetap memerlukan Midtrans aktif dan teruji)', 'type' => 'select', 'options' => array('0' => 'Jeda donasi baru', '1' => 'Aktif'), 'default' => '1'),
				'minimum' => array('label' => 'Nominal minimum per donasi (Rupiah)', 'type' => 'select', 'options' => array('1' => 'Rp1', '1000' => 'Rp1.000', '5000' => 'Rp5.000'), 'default' => '1'),
				'maximum' => array('label' => 'Nominal maksimum per donasi (Rupiah)', 'type' => 'select', 'options' => array('1000000' => 'Rp1.000.000', '5000000' => 'Rp5.000.000', '10000000' => 'Rp10.000.000'), 'default' => '10000000')
			)),
			'privasi' => array('title' => 'Privasi & trafik', 'fields' => array(
				'policy_version' => array('label' => 'Versi kebijakan (ubah saat teks/retensi berubah)', 'type' => 'text', 'max' => 40, 'required' => TRUE, 'default' => 'draft-1'),
				'policy_text' => array('label' => 'Kebijakan privasi yang sudah ditinjau (teks biasa)', 'type' => 'textarea', 'max' => 6000, 'required' => FALSE, 'default' => ''),
				'terms_text' => array('label' => 'Syarat layanan yang sudah ditinjau (teks biasa)', 'type' => 'textarea', 'max' => 6000, 'required' => FALSE, 'default' => ''),
				'approved' => array('label' => 'Pernyataan pemilik setelah review kebijakan', 'type' => 'select', 'options' => array('0' => 'Draft — belum disetujui', '1' => 'Teks, tujuan, hak pengguna dan retensi telah disetujui'), 'default' => '0'),
				'operational_enabled' => array('label' => 'Monitoring operasional tanpa cookie pelacakan (detail 7 hari; akun anak dikecualikan)', 'type' => 'select', 'options' => array('0' => 'Nonaktif', '1' => 'Aktif — tujuan dan data dijelaskan di kebijakan'), 'default' => '0'),
				'enabled' => array('label' => 'Analitik opsional dengan cookie browser/sesi (terpisah dari monitoring operasional)', 'type' => 'select', 'options' => array('0' => 'Nonaktif', '1' => 'Aktif hanya setelah persetujuan pengguna'), 'default' => '0'),
				'children' => array('label' => 'Trafik akun anak', 'type' => 'select', 'options' => array('0' => 'Tidak dicatat', '1' => 'Boleh hanya dengan persetujuan orang tua tertaut'), 'default' => '0'),
				'detail_days' => array('label' => 'Retensi detail trafik', 'type' => 'select', 'options' => array('7' => '7 hari', '30' => '30 hari'), 'default' => '30'),
				'aggregate_days' => array('label' => 'Retensi agregat harian tanpa identitas', 'type' => 'select', 'options' => array('90' => '90 hari', '365' => '365 hari'), 'default' => '365')
			)),
			'poin' => array('title' => 'Poin belajar & penukaran', 'fields' => array(
				'enabled' => array('label' => 'Hadiah & penukaran (aktifkan hanya setelah aturan disetujui)', 'type' => 'select', 'options' => array('0' => 'Nonaktif / draft kebijakan', '1' => 'Aktif untuk akun anak'), 'default' => '0'),
				'reward' => array('label' => 'Poin sekali untuk pertama kali memenuhi syarat tiap set', 'type' => 'select', 'options' => array('5' => '5 poin', '10' => '10 poin'), 'default' => '10'),
				'daily_cap' => array('label' => 'Batas hadiah per anak per hari WIB', 'type' => 'select', 'options' => array('10' => '10 poin', '20' => '20 poin', '30' => '30 poin'), 'default' => '30'),
				'pass_score' => array('label' => 'Minimum jawaban benar pada aktivitas terukur', 'type' => 'select', 'options' => array('80' => '80%', '100' => '100%'), 'default' => '80')
			)),
			'battle' => array('title' => 'Battle privat', 'fields' => array(
				'live_enabled' => array('label' => 'Battle live serentak (tanpa iklan/reward)', 'type' => 'select', 'options' => array('1' => 'Aktif; batas teknis berlaku', '0' => 'Jeda pembuatan/mulai/jawaban live'), 'default' => '1'),
				'live_seconds' => array('label' => 'Waktu per soal ruang live baru', 'type' => 'select', 'options' => array('20' => '20 detik', '30' => '30 detik', '60' => '60 detik'), 'default' => '30'),
				'enabled' => array('label' => 'Ketersediaan battle privat', 'type' => 'select', 'options' => array('1' => 'Aktif; publikasi tetap wajib email terverifikasi', '0' => 'Jeda ruang dan jawaban baru; riwayat tetap tersedia'), 'default' => '1'),
				'capacity' => array('label' => 'Peserta per ruang baru (batas teknis, bukan janji kapasitas live)', 'type' => 'select', 'options' => array('10' => '10 peserta', '20' => '20 peserta', '50' => '50 peserta'), 'default' => '50'),
				'questions' => array('label' => 'Maksimal soal saat publikasi', 'type' => 'select', 'options' => array('5' => '5 soal', '10' => '10 soal', '30' => '30 soal'), 'default' => '30'),
				'rooms' => array('label' => 'Ruang aktif/draf per pembuat', 'type' => 'select', 'options' => array('1' => '1 ruang', '3' => '3 ruang'), 'default' => '3')
			)),
			'quest' => array('title' => 'English Quest', 'fields' => array(
				'enabled' => array('label' => 'Ketersediaan petualangan', 'type' => 'select', 'options' => array('1' => 'Aktif', '0' => 'Jeda; riwayat tetap tersedia'), 'default' => '1'),
				'ordered' => array('label' => 'Urutan episode', 'type' => 'select', 'options' => array('1' => 'Selesaikan episode sebelumnya', '0' => 'Bebas memilih episode'), 'default' => '1'),
				'translation' => array('label' => 'Terjemahan dialog', 'type' => 'select', 'options' => array('1' => 'Terbuka', '0' => 'Dibuka peserta saat diperlukan'), 'default' => '1'),
				'audio' => array('label' => 'Bantuan suara perangkat', 'type' => 'select', 'options' => array('1' => 'Opsional jika suara Inggris lokal tersedia', '0' => 'Nonaktif'), 'default' => '1')
			)),
			'english' => array('title' => 'Kelas Bahasa Inggris', 'fields' => array(
				'enabled' => array('label' => 'Ketersediaan kelas', 'type' => 'select', 'options' => array('1' => 'Aktif', '0' => 'Jeda; riwayat tetap tersedia'), 'default' => '1'),
				'pass_score' => array('label' => 'Ambang evaluasi babak baru (bukan sertifikasi bahasa)', 'type' => 'select', 'options' => array('60' => '60%', '75' => '75%', '80' => '80%', '100' => '100%'), 'default' => '75'),
				'translation' => array('label' => 'Terjemahan pada materi baca', 'type' => 'select', 'options' => array('1' => 'Terbuka', '0' => 'Dibuka peserta saat diperlukan'), 'default' => '1'),
				'audio' => array('label' => 'Bantuan suara perangkat', 'type' => 'select', 'options' => array('1' => 'Opsional jika suara Inggris lokal tersedia', '0' => 'Nonaktif'), 'default' => '1')
			)),
			'umum' => array('title' => 'Umum & identitas situs', 'fields' => array(
				'brand_name' => array('label' => 'Nama situs', 'type' => 'text', 'max' => 60, 'required' => TRUE, 'default' => 'Project Pintar'),
				'tagline' => array('label' => 'Pesan footer', 'type' => 'text', 'max' => 180, 'required' => TRUE, 'default' => 'Belajar sedikit demi sedikit, tumbuh setiap hari.'),
				'seo_title' => array('label' => 'Judul beranda untuk mesin pencari (tanpa nama situs)', 'type' => 'text', 'max' => 100, 'required' => TRUE, 'default' => 'Latihan Soal Online SD Kelas 1–6 dan Materi Gratis'),
				'google_site_verification' => array('label'=>'Kode verifikasi Google Search Console (nilai content saja, opsional)', 'type'=>'text', 'max'=>160, 'required'=>FALSE, 'default'=>''),
				'seo_description' => array('label' => 'Deskripsi beranda untuk mesin pencari', 'type' => 'textarea', 'max' => 180, 'required' => TRUE, 'default' => 'Latihan soal online SD kelas 1–6 dengan pembahasan, materi gratis, dan flashcard. Belajar Matematika, Bahasa Indonesia, dan Bahasa Inggris bersama Project Pintar.'),
				'support_email' => array('label' => 'Email dukungan (opsional, tampil publik)', 'type' => 'email', 'max' => 190, 'required' => FALSE, 'default' => ''),
				'brand_icon' => array('label' => 'Simbol cadangan (logo resmi tetap Pijar)', 'type' => 'select', 'options' => array('bi-stars' => 'Bintang', 'bi-book' => 'Buku', 'bi-lightbulb' => 'Ide belajar'), 'default' => 'bi-stars')
			)),
			'arena' => array('title' => 'Arena Belajar', 'fields' => array(
				'cards' => array('label' => 'Flashcard mandiri per babak (TK selalu 5)', 'type' => 'select', 'options' => array('5' => '5 kartu', '10' => '10 kartu', '15' => 'Hingga 15 kartu'), 'default' => '15'),
				'expedition_pass' => array('label' => 'Ambang membuka level Math Expedition berikutnya (babak baru)', 'type' => 'select', 'options' => array('60' => '60% · 3 dari 5', '80' => '80% · 4 dari 5', '100' => '100% · 5 dari 5'), 'default' => '80'),
				'enabled' => array('label' => 'Ketersediaan Arena', 'type' => 'select', 'options' => array('1' => 'Aktif', '0' => 'Jeda permainan; riwayat tetap tersedia'), 'default' => '1'),
				'pairs' => array('label' => 'Pasangan per babak (TK selalu 4)', 'type' => 'select', 'options' => array('4' => '4 pasangan', '6' => '6 pasangan'), 'default' => '6'),
				'words' => array('label' => 'Kata per babak (TK selalu 5)', 'type' => 'select', 'options' => array('5' => '5 kata', '10' => '10 kata'), 'default' => '10'),
				'timer' => array('label' => 'Pilihan tantangan waktu Hitung Cepat', 'type' => 'select', 'options' => array('1' => 'Boleh dipilih SD/SMP/dewasa; santai tetap standar', '0' => 'Hanya mode santai tanpa timer'), 'default' => '1')
			)),
			'pembelajaran' => array('title' => 'Pembelajaran & publikasi', 'fields' => array(
				'solo_review' => array('label' => 'Review oleh penulis sendiri', 'type' => 'select', 'options' => array('0' => 'Reviewer berbeda (standar)', '1' => 'Izinkan hanya Superadmin, wajib checklist kualitas'), 'default' => '0')
			))
		);
	}

	public function defaults($section)
	{
		$result = array();
		foreach ($this->definitions()[$section]['fields'] as $key => $field) { $result[$key] = $field['default']; }
		return $result;
	}

	public function validate($section, $input)
	{
		$values = array(); $errors = array();
		foreach ($this->definitions()[$section]['fields'] as $key => $field)
		{
			$value = $input[$key] ?? NULL;
			// HTML textarea submissions use CRLF; normalize line endings before rejecting controls.
			if ($field['type'] === 'textarea' && is_string($value)) { $value = str_replace(array("\r\n", "\r"), "\n", $value); }
			if ( ! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0b-\x1f\x7f]/', $value))
			{
				$errors[$key] = 'Isi kolom dengan teks yang valid.'; $values[$key] = ''; continue;
			}
			$value = trim($value); $values[$key] = $value;
			if ($field['type'] === 'select')
			{
				if ( ! array_key_exists($value, $field['options'])) { $errors[$key] = 'Pilih opsi yang tersedia.'; }
			}
			elseif ((! empty($field['required']) && $value === '') || mb_strlen($value, 'UTF-8') > $field['max']) { $errors[$key] = 'Periksa isian; maksimal '.$field['max'].' karakter.'; }
			elseif ($field['type'] === 'email' && $value !== '' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) { $errors[$key] = 'Alamat email tidak valid.'; }
		}
		if ($section === 'umum' && ($values['google_site_verification'] ?? '') !== '' && !preg_match('/\A[A-Za-z0-9_-]{20,160}\z/', $values['google_site_verification']))
		{
			$errors['google_site_verification']='Tempel nilai content dari tag Google saja (20–160 huruf, angka, - atau _), bukan seluruh HTML, client ID atau client secret.';
		}
		if ($section === 'privasi')
		{
			if ( ! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,39}\z/', $values['policy_version'])) { $errors['policy_version'] = 'Gunakan huruf, angka, titik, garis bawah atau tanda hubung.'; }
			if ($values['approved'] === '1' && (strpos(strtolower($values['policy_version']), 'draft') === 0 || mb_strlen($values['policy_text']) < 200 || mb_strlen($values['terms_text']) < 200)) { $errors['approved'] = 'Isi kebijakan dan syarat yang telah ditinjau (minimal 200 karakter masing-masing), serta gunakan versi bukan draft.'; }
			if ($values['enabled'] === '1' && $values['approved'] !== '1') { $errors['enabled'] = 'Pencatatan tidak boleh aktif sebelum kebijakan disetujui.'; }
			if ($values['operational_enabled'] === '1' && $values['approved'] !== '1') { $errors['operational_enabled'] = 'Monitoring memerlukan kebijakan yang telah disetujui.'; }
		}
		return compact('values', 'errors');
	}
}
