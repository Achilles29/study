# Panel dan monitoring Project Pintar — 16 Oktober 2026

Tahap B1–B2 handover selesai sebagai alur hanya-baca, bukan seluruh roadmap pengelola. Tidak menjalankan migration/instalasi, mereset data/ID, membuat materi kurikulum, mengaktifkan pembayaran, atau mengubah Finance/Pustaka. Database aktif tetap `db_study` lokal port 3308, migration 13/33 tabel.

Catatan versi di atas adalah snapshot B1–B2. Kelanjutan pengaturan memakai tambahan migration 014–015 (15/35 tabel), tanpa mengulang migrasi awal; lihat [pusat pengaturan server](PUSAT_PENGATURAN_SERVER.md).

## URL dan alur mencoba

Masuk melalui `https://www.projectpintar.com/masuk` dengan akun pengelola yang sudah ada.

- `/kelola`: ringkasan operasional dan aktivitas 30 hari; sidebar desktop dapat diciutkan, drawer mobile dapat ditutup dengan Escape dan mengembalikan fokus. Tombol **Buka tampilan pengguna** menuju ruang peserta yang terpisah.
- `/kelola/pembelajaran`: cari nama tampilan/username/ID; filter periode WIB, jenjang, modul, kursus/paket, status dan ada/belum aktivitas. Hasil 20 pengguna per halaman, urutan stabil. Buka **Detail** untuk ringkasan dan tab materi, latihan, flashcard, permainan, akses paket; buka aktivitas untuk waktu/status/hasil yang diizinkan.
- `/kelola/pembelajaran/modul`: ringkasan empat modul dan daftar konten/sesi berhalaman; tautan peserta membatasi pengguna ke konten yang dipilih, kemudian buka detail pengguna.
- `/kelola/rapor`: pilih pengguna lalu lihat rapor periode, ringkasan per kursus dan cetak browser. Daftar kursus 20 per halaman; cetak hanya halaman yang terlihat, bukan ekspor seluruh data.
- `/keluarga/anak/{id}/rapor`: laporan untuk orang tua dengan tautan anak aktif saja. Tautan tersedia di daftar anak; pengelola memakai rute pengelola, bukan bypass ke seluruh keluarga.

Seluruh halaman pengelola existing terhubung sesuai peran. Akun biasa dan editor konten tidak dapat membaca monitoring lintas pengguna melalui URL langsung. Keluarga tidak mendapat sidebar admin atau pilihan nama kursus draf yang tidak terkait. Tidak ada menu pengaturan/trafik/pembayaran kosong yang dihitung selesai.

## Makna angka dan batas data

- Materi: jumlah selesai dari catatan materi yang disentuh pada periode, bukan dari seluruh kurikulum/paket.
- Latihan: jumlah attempt berjalan/selesai/dihentikan, nilai terakhir dan terbaik hanya dari attempt `graded`. Belum dikerjakan tidak diubah menjadi nilai nol.
- Flashcard: kartu dikenal dari kartu yang memiliki catatan progres pada periode; bukan jumlah review harian atau ukuran penguasaan seluruh materi.
- Cocokkan Pasangan: babak tersimpan selesai dari babak pada periode; bukan nilai akademik, XP atau reward.
- Paket pada filter membatasi kursus anggota paket; tab akses menampilkan entitlement aktual beserta status/waktu berlaku, bukan bukti pembayaran baru.
- Periode memakai tanggal WIB inklusif, diterjemahkan menjadi batas UTC setengah terbuka; maksimal 367 hari. Materi memakai `updated_at`, kartu `last_reviewed_at`, latihan `submitted_at` atau `started_at`, game `finished_at` atau `started_at`. Materi/kartu menyimpan keadaan terakhir, sehingga bukan histori event lengkap dan bukan snapshot keadaan pada tanggal lampau. Tidak mengklaim durasi belajar dari waktu buka halaman.
- Query hanya memuat metadata laporan. Tidak memilih teks soal/jawaban/snapshot game; judul sesi keluarga diganti label privat. Data privat memakai no-store/noindex. Pencarian identitas melalui POST+CSRF dan konteks sesi acak (maksimal 10), bukan nama/kontak di URL. Konteks hilang saat logout atau sesi kredensial dicabut.
- Query gagal menjadi respons gagal, bukan laporan kosong sukses. Filter kursus/paket dibatasi 500 pilihan; perlu pencarian opsi server-side bila katalog tumbuh melewati batas ini. Belum ada uji beban besar, ekspor CSV/PDF server, reset histori atau mutasi akses.

## URL bersih, branding dan SEO

`application/config/config.php` memakai `index_page=''`; UI memakai **Project Pintar**. Nginx aaPanel aktif memakai pola berikut di konteks `http` (berkas vhost include), sebelum blok `server`:

```nginx
map "$request_method:$request_uri" $study_legacy_redirect {
    default "";
    ~^(?:GET|HEAD):/index\.php(?:/(?<study_clean_path>[^?]*))?(?<study_clean_query>\?.*)?$ /$study_clean_path$study_clean_query;
}
```

Di dalam server `www.projectpintar.com`:

```nginx
if ($study_legacy_redirect != "") {
    return 301 https://www.projectpintar.com$study_legacy_redirect;
}
```

Ini memeriksa URI permintaan asli, bukan rewrite internal, dan mempertahankan query. Jangan mengalihkan semua metode: POST/callback harus tetap diteruskan ke front controller. Konfigurasi fallback dan deny file privat tetap dipertahankan. Backup vhost sebelum perubahan berada di `/www/backup/projectpintar-deploy-20261016/nginx-before-clean-urls.conf`. Uji `nginx -t` sebelum reload; jangan menimpa vhost aktual dengan template generik.

SEO dasar: judul/deskripsi halaman, canonical, Open Graph dan WebSite JSON-LD publik, `/robots.txt`, `/sitemap.xml` berisi beranda/katalog/kursus terbit saja. Halaman akun/pengelola/keluarga dan hasil katalog tersaring tidak diindeks. Kata/frasa dipakai secara alami sesuai fitur: **belajar anak TK/SD/SMP**, **latihan soal**, **flashcard/kartu belajar**, **permainan edukasi**, **progres belajar anak**. Ini pemetaan relevansi fitur, bukan riset volume pencarian, jaminan peringkat atau alasan membuat materi massal. Sitemap awal dibatasi 10.000 kursus; buat indeks sitemap bila melewati batas. Pengiriman Search Console dan konten kurikulum nyata belum dilakukan. Rujukan: [panduan developer Google Search](https://developers.google.com/search/docs/fundamentals/get-started-developers) dan [meta tag yang didukung](https://developers.google.com/search/docs/crawling-indexing/special-tags).

## Bukti pengujian terarah

Seluruh fixture berada di database sementara `study_qa_monitor_20261016a` pada instance lokal, bukan `db_study`. Skema diambil tanpa data; seed sintetis 25 akun, dua kursus kecil (terbit/draf), materi, tiga attempt, dua kartu dan satu babak permainan. Tidak menyalin data Pustaka atau Finance. Server QA hanya bind loopback port 8097 dengan kunci dan sesi QA terpisah. Database/server sementara dibersihkan sesudah pengujian.

- `scripts/test-learning-monitor.php`: SQL model aktual, hitungan dan batas WIB, graded terakhir/terbaik, filter jenjang/kursus/paket/konten, literal `%`, pagination, privasi dan kegagalan query. Guard hanya menerima nama database `study_qa_monitor_*`; seed menolak tabel pengguna berisi. `--verify-only` tidak menambah seed.
- `scripts/test-monitor-http.mjs`: halaman/detail/tab, filter invalid 400, ID tidak ada 404, CSRF 403, pencarian terikat sesi/escaping, role biasa/editor ditolak termasuk alias controller, laporan keluarga sendiri vs keluarga lain, logout, canonical dan sitemap tanpa draf. Dengan `STUDY_MONITOR_TEST_DB` bernama QA, tes juga mencabut lalu memulihkan tautan fixture dan membuktikan sesi orang tua yang sama segera ditolak. Gunakan hanya server QA loopback dengan fixture di atas; sandi sintetis di script bukan kredensial produksi.
- `scripts/test-monitor-browser.mjs`: Chrome headless desktop 1280 dan mobile 360, tanpa overflow halaman, menu aktif/collapse, drawer/fokus/inert/Escape, fallback tanpa JavaScript, print dan logout, tanpa exception JavaScript. Memerlukan `puppeteer-core` yang sudah tersedia (`STUDY_PUPPETEER_MODULE` untuk lokasi lain), Chrome `/usr/bin/google-chrome`. Capture sintetis disimpan privat di `/tmp/study-monitor-qa-JyNYBv/` dan ditinjau visual; bukan bukti audit WCAG/pembaca layar/zoom menyeluruh.
- `scripts/test-session-credentials.php`: stamp hilang/invalid/kedaluwarsa atau akun tidak aktif ditolak, konteks direktori dan monitoring dibersihkan, ID sesi diganti; stamp valid diterima.
- Lint PHP 8.4 server dan ruleset coding terarah pada file berubah/baru; tidak mengulang matriks Windows/PHP historis.
- Smoke HTTPS produksi: login `ngadmin`, panel, monitoring pengguna/modul/detail, rapor, direktori, kursus dan audit berhasil; logout setelah verifikasi. GET/HEAD `/index.php/...` menjadi 301 URL bersih dengan query utuh; robots/sitemap/aset 200, konfigurasi Nginx valid. Tidak membuat aktivitas belajar produksi.

Pada pemeriksaan ini produksi hanya memiliki satu akun (`ngadmin`, ID 1) dan belum memiliki kursus/aktivitas belajar. Tampilan kosong adalah keadaan data nyata; angka terisi telah diuji pada database terisolasi. Checklist B1–B2 selesai, tetapi UAT pemilik tetap diperlukan.

## Kelanjutan

B3 pusat pengaturan nyata dengan validasi/audit, lalu B4 trafik tiga tab beserta kontrak privasi dan metrik. Akun/keluarga, Midtrans sandbox (referensi Finance self order baca-saja), paket/Platinum, slot anak, poin dan battle mengikuti gerbang handover. Harga/durasi draft, retensi/aktivasi pelacakan anak dan keputusan bisnis lain tidak dianggap final. Pembayaran live tidak diaktifkan.
