# Adopsi lanjutan Arena — 16 Oktober 2026

Catatan ini merekam tahap 020–023. Status lebih baru Gmail/verifikasi/reset dan katalog battle semua member ada di [tahap 024–025](EMAIL_DAN_BATTLE_PUBLIK_SERVER.md); batas belum-tersedia pada bagian historis di bawah tidak menggantikan dokumen terbaru.

Status: migration **023**, runtime mandiri `db_study`, 41 tabel dan 190 katalog. Melanjutkan implementasi existing; bukan instalasi/migrasi ulang. Akun ngadmin ID 1, ID/ketersediaan katalog lama dan snapshot hasil dipertahankan. Finance/Pustaka/callback tidak diubah. Semua pengujian mutasi memakai database sintetis terisolasi, bukan akun pemilik.

## Alur yang dapat dicoba

- `/belajar/arena`: sembilan judul; rincian tidak menumpuk di hub. Login diperlukan, desktop/ponsel/keyboard didukung.
- `/belajar/arena/game/kelas-inggris`: 12 bab, 60 pelajaran (600 langkah), 12 checkpoint, dua review, satu final; total 75 aktivitas dan 214 pertanyaan evaluasi. Pelajaran bebas dipilih; evaluasi mensyaratkan pelajaran terkait selesai ditinjau. Pilihan/susun kalimat mendapat umpan balik, misi memakai refleksi. Pelajaran ditinjau **bukan** klaim penguasaan/sertifikasi.
- `/belajar/arena/game/english-quest`: 15 episode aktif/225 adegan. Dialog, tantangan pilihan, dengar dengan teks alternatif, susun kalimat dan pilihan cerita; umpan balik sebelum lanjut, resume, jurnal akhir. Pilihan cerita tidak dinilai benar/salah. Koleksi lima jenis kosmetik diturunkan dari progres unik episode, bukan saldo/reward yang dapat ditukar; pengulangan tidak menambah koleksi berulang.
- `/belajar/arena/game/flashcard`: 29 set/376 kartu, termasuk 15 deck kosakata episode/225 kartu. Perilaku reveal/refleksi diri/progres unik tetap sama.
- `/belajar/arena/game/story-quiz`: 22 bacaan/101 pertanyaan; halaman/filter jenjang dan pembahasan akhir.
- `/belajar/battle`: draf soal pilihan ganda, metadata sumber/hak, edit judul/jadwal WIB, pratinjau, hapus/tambah soal, publikasi undangan privat, bergabung, pengerjaan dan hasil. **Akun nyata belum dapat menerbitkan tanpa email terverifikasi**; draf dapat dipakai sekarang. Backend publikasi diuji dengan akun sintetis terverifikasi saja.
- `/kelola/arena`: filter jenis dan pagination 24, aktif/nonaktif per katalog tanpa menghapus sejarah.
- `/kelola/pengaturan/english`, `/quest`, `/battle`: jeda, ambang evaluasi, terjemahan/audio, urutan episode dan kuota teknis. Angka batas battle bukan harga atau janji kapasitas live.
- `/kelola/battle`: moderasi laporan privat, keputusan dan pembatalan atomik dengan audit; memakai sidebar/drawer Superadmin.
- `/kelola/pembelajaran`, `/kelola/rapor/{id}`, `/keluarga/anak/{id}/rapor`: Inggris pelajaran unik terpisah dari attempt evaluasi; Quest masuk Arena; battle punya modul sendiri. Kuis kursus tidak tercampur dengan nilai permainan. Query laporan tidak mengambil snapshot/kunci/isi jawaban.

## Sumber dan penyesuaian mutu

Inggris berasal dari dua file **data literal** `english_course_stage1.php` dan `_enrichment.php` Pustaka. Script `build-arena-english.php` tidak memuat model sumber yang memiliki bootstrap mutasi. Pengulangan prompt checkpoint dari generator modulo diperbaiki; review menyebar bab. Misi identitas menggunakan tokoh rekaan, tidak meminta kontak anak. Input susun kalimat dinilai lalu dibuang; hanya penanda sesuai/perlu belajar disimpan.

Quest dan Story membaca **kolom konten saja** dari database Pustaka dalam transaksi baca-saja; tidak menyalin akun, hasil, inventori, pembayaran atau konfigurasi. JSON lokal mencatat sumber/hash; runtime tidak menghubungi Pustaka. Seluruh 15 episode aktif Season 1 diambil; Season 2 tanpa episode tidak dibuat menu kosong. Toko/nyawa/equipment/daily quest/XP tidak disalin; konteks Study memakai latihan nonpunitif dan koleksi kosmetik, tanpa mekanisme bayar untuk menang.

Story aktif SD–SMP memiliki 45 entri, tetapi hanya 22 teks unik. Duplikasi lintas jenjang digabung, sembilan pertanyaan berulang dihilangkan. Migration 022 menaikkan versi 10 katalog lama hanya jika cocok persis dengan konten bawaan terdahulu; bila sudah diedit, proses berhenti tanpa menimpa. Dua belas bacaan baru ditambahkan. ID, enabled dan snapshot lama tidak diubah. Tidak membuat bacaan massal baru atau soal sesi latihan; SMA di luar sasaran tidak dibawa.

Audio memakai suara Inggris **lokal** perangkat, klik eksplisit, tanpa mikrofon/autoplay; jika tidak tersedia, teks tetap dapat digunakan. Kode memeriksa `SpeechSynthesisVoice.localService` sesuai [spesifikasi Web Speech](https://webaudio.github.io/web-speech-api/#dom-speechsynthesisvoice-localservice). Fallback tanpa suara diuji di Chrome server; kualitas pelafalan perangkat nyata belum diuji dan tidak diklaim sebagai penilaian pronunciation.

## Pengamanan battle

Versi pertama asinkron terjadwal, bukan live. Default teknis 50 peserta, 30 soal dan 3 ruang aktif/draf; pilihan batas lebih rendah tersedia. Dewasa/orang tua aktif dengan email terverifikasi yang menerbitkan. Anak dapat membuat draf privat; hanya orang tua tertaut dapat menyetujui publikasi. Root tidak mempunyai bypass email atau akses seluruh soal privat.

Orang tua terverifikasi mendaftarkan anak dengan persetujuan per ruang `battle-private-v1`, dapat mencabutnya, dan tidak dapat menjawab atas nama anak. Relasi keluarga/email diperiksa lagi saat akses/jawaban. Penyelenggara hanya melihat alias, status, serta nilai setelah jadwal; tidak ada daftar anak publik, chat, rekaman, iklan atau reward penukaran. Pembatalan/pencabutan tidak menghapus sejarah atau memberi kesempatan mengulang skor.

Token HMAC membuat pembuatan draf idempoten; unique constraint mencegah peserta ganda. CSRF, revisi, pemilik aktif dan transaksi server berlaku pada mutasi. Kapasitas diputuskan dengan penguncian ruang **dan locking read peserta** (menghindari snapshot usang REPEATABLE READ). Percobaan kode dibatasi 10/menit per sesi; limiter lintas sesi/akun dan uji beban besar masih backlog sebelum pembukaan luas.

Soal/jadwal dibekukan saat publikasi. Penyelenggara/penerbit tidak dapat menjadi peserta. Server menilai, deadline server menolak kredit jawaban terlambat, soal tidak dijawab tetap masuk pembagi. Selesai lebih awal tidak membuka nilai/kunci; baru sesudah jadwal berakhir. Monitoring mengikuti aturan sama, termasuk attempt yang kedaluwarsa tanpa peserta membuka halaman lagi. Ruang dibatalkan atau persetujuan dicabut tidak menyumbang nilai selesai. Tidak ada bonus kecepatan/leaderboard publik.

## Bukti uji terarah

QA `study_qa_monitor_english220`, PHP 8.4/MariaDB Study, loopback 8097; proses 8098 digunakan khusus race kapasitas. Tidak mengulang seluruh matriks platform historis.

- `test-english-engine.php`: 75 aktivitas dua jalur benar/salah, 600 langkah/214 evaluasi, larangan skip/duplikat, input bebas tidak disimpan; batas 8/12 gagal dan 9/12 lulus pada 75%.
- `test-english-http.mjs`: seluruh 75 aktivitas selesai; penguncian final, CSRF/IDOR/replay/409/rollback, nilai server dan agregasi 60 pelajaran unik/15 evaluasi; bank soal tidak bertambah.
- `test-quest-engine.php` + `test-quest-http.mjs`: seluruh 225 adegan; 13 tantangan bernilai dan 2 pilihan cerita netral per episode, umpan balik/next/jurnal, urutan, replay/409/CSRF/IDOR/rollback, nilai dan bank soal tetap.
- `test-story-http.mjs`: 22 bacaan/101 pertanyaan hingga hasil; hash snapshot versi lama dan jumlah bank soal tetap.
- `test-battle-http.mjs`: draf/edit/publish, email gate, persetujuan orang tua dan cabut, IDOR/CSRF/replay/revisi, snapshot beku, deadline, nilai server/kunci tersembunyi termasuk laporan, isolasi rapor keluarga, pembatalan/moderasi, rollback entry/audit. Race **dua proses** untuk satu slot pertama menemukan snapshot count usang; setelah perbaikan hasil tepat satu sukses dan satu ditolak.
- `test-adopted-settings.mjs`: validasi/role, threshold/audio/terjemahan snapshot Inggris, jeda/stop aman, Quest urut/bebas/jeda, battle jeda dan kapasitas lama tidak ditulis ulang, laporan terkait.
- Browser Inggris/Quest/Battle 1280 dan 360 px: navigasi keyboard, pilih aktivitas, read/choice/feedback/next/stop/jurnal, draf/pratinjau/cancel/archive/join/answer, tampilan persetujuan, pengaturan dan monitoring. Tidak ada overflow/galat JS. Screenshot sintetis di `/tmp/study-{english,quest,battle}-*.png`; tampilan adegan dan draf ponsel diperiksa langsung. Tes logout browser sempat memilih tombol header tersembunyi di ponsel; harness diperbaiki, bukan melewati otorisasi aplikasi.

## Deploy dan pekerjaan yang belum selesai

Migration tambahan 020/021/022/023 sudah diterapkan satu kali. Backup privat sebelum masing-masing: `before-english-020.sql`, `before-quest-021.sql`, `before-stories-022.sql`, `before-battle-023.sql` di `/www/backup/projectpintar-deploy-20261016/` (mode 600). Tidak menjalankan ulang migration lama atau mereset ID/data.

Pemeriksaan akhir menggunakan kredensial runtime: database `db_study`, versi 23/41 tabel/190 katalog, satu pengguna ID 1, nol babak/progres kartu/ruang battle/soal bank QA. HTTPS beranda 200, Arena/Battle tanpa login 307, JSON privat 404. Lint 27 file terdampak, PHPCS, penamaan CI3 252 file dan `git diff --check` lulus. Regresi browser katalog/flashcard juga lulus dengan sembilan judul dan halaman ketiga deck kosakata.

Setelah tes, kedua server QA loopback dihentikan dan **hanya** database sementara `study_qa_monitor_english220` dihapus. Bukti sintetis dapat dipulihkan dari `arena-qa-evidence-023.sql` pada direktori backup privat yang sama (mode 600). Produksi tidak dibersihkan.

**Belum seluruh roadmap selesai.** Verifikasi email/pemulihan akun dan layanan pengirim masih diperlukan untuk aktivasi battle nyata; pemilik diminta memilih layanan tanpa mengirim secret melalui chat. Live battle, poin/penukaran, trafik, onboarding/SSO, QRIS transaksi/Platinum/slot komersial, review privasi dan UAT pemilik tetap mengikuti checklist terbuka. Pembayaran live tidak diaktifkan; harga/durasi draft tidak disahkan. Bank soal sesi latihan sengaja tetap kosong sesuai instruksi.

Pemilik kemudian memilih kriteria gratis. Rekomendasi awal: Brevo Free, 300 email/hari dan SMTP transaksi berdasarkan halaman vendor yang diperiksa; belum ada pendaftaran atau pengiriman email produksi. Pemilik meminta pertanyaan dikirim melalui Telegram; alat/koneksi Telegram tidak tersedia dalam sesi ini (pencarian integrasi tidak menemukan hasil), sehingga belum ada pesan dikirim ke Telegram.
