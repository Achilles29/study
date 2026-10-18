# Panduan Lingkungan dan Deployment

## Lokasi produksi dan berkas saat pindah server — 18 Oktober 2026

Situs aktif memakai `/www/wwwroot/study`. Folder kerja QA seperti `/www/study-private/donation043-...` hanya salinan untuk pengujian dengan database terisolasi; bukan sumber runtime produksi dan tidak wajib dipindahkan. Jangan menyalin fixture/seed/kredensial sintetis QA ke produksi.

Namun **tidak semua isi study-private adalah pengujian**: konfigurasi runtime saat ini menunjuk `STUDY_MEDIA_ROOT=/www/study-private/lesson-images`. Folder media tersebut harus dicadangkan/dipulihkan berpasangan dengan database, walaupun berada di luar document root. Biarkan media privat di luar akses web langsung.

Set migrasi yang perlu disiapkan (bukan instruksi menjalankan migrasi ulang sekarang):

1. Folder situs `/www/wwwroot/study`, termasuk aset dan source terbaru.
2. Dump terbaru database `db_study`, bukan database QA dan bukan hanya menyalin folder source.
3. Media privat `STUDY_MEDIA_ROOT` di atas, termasuk bila lokasinya diubah kemudian.
4. Konfigurasi rahasia `/etc/projectpintar/` secara privat, terutama kunci enkripsi aplikasi yang sama agar credential terenkripsi Google/Midtrans/email tetap dapat dibuka. Jangan mempublikasikan atau menaruhnya dalam Git/document root. Sesuaikan koneksi database/path pada server tujuan tanpa mereset kunci/data secara sembarang.
5. Konfigurasi aaPanel/Nginx/PHP-FPM/TLS, service/worker terjadwal dan DNS yang diperlukan. Buat ulang pada server tujuan sesuai path/versi yang tersedia; salin konfigurasi secara terkontrol, bukan menjalankan instalasi lama untuk menimpa data.

Database, media dan konfigurasi harus dicadangkan konsisten; uji login, akses media, callback HTTPS dan tugas retensi sebelum perpindahan trafik. Backup `/www/backup/projectpintar-*` adalah cadangan pemulihan, bukan dependensi runtime. Tidak perlu memindahkan seluruh folder pengujian atau data aplikasi Finance/Pustaka agar Study berjalan.

> **Handover 16 Oktober 2026:** pengembangan lokal dijeda; domain tujuan https://www.projectpintar.com/. Baca [TASK_HANDOVER_SERVER](TASK_HANDOVER_SERVER.md) untuk urutan migrasi, akun admin uji, keputusan baru dan batas pekerjaan. Domain/DNS/TLS belum dikonfigurasi; jangan hardcode URL produksi pada instalasi localhost. Kredensial privat lokal tidak ikut deployment. Apache/MySQL bersama tidak dimatikan.

## Gambar pendamping pelajaran: lingkungan dan batas bukti

Migration `013_add_lesson_images` menambah metadata satu gambar per pelajaran; skema lokal saat ini versi 13 dengan 33 tabel. Berkas gambar disimpan terpisah dari database dan tidak berada di direktori publik. Ini implementasi gambar pendamping PNG/JPEG, bukan galeri, unggah video/PDF, editor HTML, atau layanan media umum. Rincian relasi dan kepemilikan ada di [model data](MODEL_DATA_DAN_KEPEMILIKAN.md#gambar-pendamping-pelajaran-pada-migration-13).

### Ekstensi dan batas unggahan

Unggah memerlukan `gd` dengan dukungan PNG/JPEG dan `fileinfo` pada **runtime web yang menerima POST**, serta penyimpanan privat yang dapat ditulis. Imagick saja tidak menggantikan GD dalam implementasi ini. Pembacaan berkas yang sudah dinormalisasi, perubahan keterangan, dan pelepasan gambar tidak memerlukan GD; izin, metadata, dan akses penyimpanan tetap diperiksa. PHP CLI dan Apache/PHP-FPM dapat memakai konfigurasi berbeda: hasil `php -m` pada CLI bukan bukti bahwa unggah HTTP siap.

| Lapisan | Batas saat ini | Makna |
| --- | --- | --- |
| Berkas masuk aplikasi | 3 MiB = 3.145.728 byte | Satu PNG/JPEG; ukuran berkas, jenis nyata, nama, dan hasil decoding diperiksa server. |
| Dimensi | Maksimal 4.096 piksel per sisi dan 4.000.000 piksel total | Batas sisi dan hasil perkalian lebar × tinggi harus sama-sama lolos. |
| Hasil normalisasi | Maksimal 6 MiB = 6.291.456 byte | Batas berkas hasil encode ulang di penyimpanan, bukan izin mengunggah berkas 6 MiB. |
| Body Nginx | `client_max_body_size 8m` pada template proyek | Mencakup berkas, seluruh field dan pembungkus multipart; aplikasi tetap membatasi berkas masuk 3 MiB. |

Contoh batas untuk pool/vhost Study yang terpisah, sebelum diuji pada SAPI target:

```ini
file_uploads = On
upload_max_filesize = 4M
post_max_size = 8M
memory_limit = 256M
```

Nilai ini adalah acuan konfigurasi, bukan perubahan `php.ini` global yang sudah dilakukan. Batas PHP 4M memberi ruang agar aplikasi menolak berkas di atas 3 MiB dengan validasinya sendiri. Body POST diberi ruang tambahan untuk metadata dan multipart. Jangan menyamakan `post_max_size` dengan batas berkas: PHP dapat mengosongkan `$_POST` dan `$_FILES` bila body terlalu besar. `upload_tmp_dir` harus dapat ditulis oleh akun PHP dan diizinkan oleh `open_basedir` bila pembatas tersebut dipakai. [Acuan PHP](https://www.php.net/manual/en/ini.core.php#ini.post-max-size).

Template [Nginx](nginx-study.example.conf) sudah memakai 8m; respons body berlebih ditolak pada lapisan Nginx dengan 413 sebelum validasi aplikasi. Perubahan batas ini belum dibuktikan melalui unggahan di Ubuntu/Nginx/PHP-FPM/aaPanel. Jangan mematikan batas ukuran untuk mengatasi kegagalan unggah. [Acuan Nginx](https://nginx.org/en/docs/http/ngx_http_core_module.html#client_max_body_size).

Pemeriksaan statis `node scripts/nginx-config-check.mjs --check-rules` sudah memeriksa ruang untuk berkas 3 MiB beserta multipart dan menolak contoh batas lama 2m. Kelulusan ini memeriksa konfigurasi, bukan mengirim berkas melalui Nginx atau membuktikan seluruh lapisan PHP-FPM siap.

Decode gambar membutuhkan memori lebih besar daripada ukuran PNG/JPEG terkompresi. Library memeriksa perkiraan ruang memori sebelum decoding dan menolak bila tidak cukup; 256M bukan jaminan kapasitas semua konfigurasi atau beban serentak. Periksa juga timeout penerimaan input pada koneksi lambat, ruang temporary, dan kapasitas disk tanpa mengubah batas global proyek XAMPP lain. [Batas unggahan PHP](https://www.php.net/manual/en/features.file-upload.common-pitfalls.php).

### Lokasi penyimpanan privat

Tetapkan `STUDY_MEDIA_ROOT` sebagai direktori absolut yang sudah dibuat operator. Dalam `development`, fallback konfigurasi untuk instalasi sekarang adalah `C:\xampp\study-private\lesson-images`; pada `testing`/`production` tidak ada fallback sehingga environment wajib diisi. Library tidak membuat folder operasional secara otomatis.

- Untuk Ubuntu, gunakan lokasi khusus seperti `/var/lib/study/lesson-images`, dengan pemilik/ACL yang sesuai akun PHP-FPM Study. Folder perlu dapat dibaca dan ditulis untuk unggah/penggantian; kode dan konfigurasi tetap tidak boleh ditulis proses web. Jangan menggunakan izin bebas untuk semua pengguna.
- Guard menolak lokasi yang sama dengan, berada di dalam, atau menjadi induk dari `FCPATH`, induk `FCPATH`, maupun `DOCUMENT_ROOT` yang tersedia. Artinya, sekadar berada di luar document root belum tentu cukup. Bila aplikasi berada di `/www/wwwroot/study`, `/www/wwwroot/study-private` masih ditolak karena berada di bawah induk aplikasi.
- Lokasi harus kanonis dan nyata; path relatif, `..`, alias symlink/junction, dan folder yang belum ada ditolak. Pemeriksaan symlink Linux masih memerlukan pengujian di Linux; pengujian pembuatan symlink lokal Windows dilewati bila izin tidak tersedia.
- Jangan membuat alias Nginx, virtual directory Apache, atau tautan publik ke folder tersebut. Gambar disajikan melalui controller yang memeriksa hak materi pada setiap GET/HEAD, bukan melalui URL berkas. Jangan masukkan folder ini, nama kunci acak, dump, atau isi gambarnya ke repositori/log diagnostik.

### Bukti lokal dan pekerjaan yang masih terbuka

Pada pemeriksaan 15 Oktober 2026, normalisasi PNG/JPEG melalui CLI PHP 8.1 lulus. `scripts/lesson-media-check.ps1` memakai fixture yang dinormalisasi melalui CLI untuk memeriksa akses, GET/HEAD biner/hash/header, perubahan metadata, revisi/no-op/replay, pelepasan, review/publikasi, dan pencabutan akses melalui Apache XAMPP PHP 8.0. Apache tersebut tidak mempunyai GD: unggahan multipart yang valid ditolak 503 tanpa perubahan data. Opsi `-AllowUnavailableUpload` mencatat keterbatasan ini, **bukan mengubah 503 menjadi bukti unggah sukses**.

Editor dan pratinjau tema sekolah dasar masing-masing lulus pemeriksaan browser 360/768/1280 piksel, total enam kombinasi. [Baseline gambar pendamping](visual-baseline/2026-10-15-lesson-media/README.md) memuat capture dan batas pemeriksaannya. Tes view terisolasi memeriksa escaping, formulir, deskripsi alternatif, dan tidak bocornya kunci berkas; ini bukan uji pengiriman file melalui HTTP.

Unggah multipart sukses melalui HTTP PHP 8.1 dengan GD/fileinfo **belum diverifikasi**, termasuk berkas dekat batas 3 MiB. Ubuntu, PHP-FPM, HTTPS/aaPanel, gambar besar/portrait, koneksi lambat, screen reader, zoom 200%, UAT, filesystem penuh, serta putus koneksi setelah COMMIT masih memerlukan bukti terpisah. Hasil QA runtime/fitur lain pada bagian historis panduan ini tidak otomatis mencakup modul gambar yang baru ditambahkan.

### Backup database dan berkas harus berpasangan

Transaksi database tidak membuat filesystem menjadi atomik. Berkas baru dinormalisasi sebelum perubahan metadata; berkas lama dibersihkan sesudah commit yang terkonfirmasi. Hasil transaksi yang belum pasti mempertahankan berkas terkait untuk pemeriksaan, sehingga orphan dapat tertinggal. Jangan menghapus berkas hanya karena satu permintaan menampilkan pesan gagal.

[Latihan pemulihan database dan gambar terisolasi](PANDUAN_PEMULIHAN_MEDIA.md) lulus pada 15 Oktober 2026 melalui CLI PHP 8.1 dan MySQL/MariaDB lokal: isi/struktur 33 tabel serta byte tiga gambar cocok sesudah restore, hak akses model tetap berlaku, dan salinan hilang/rusak/tertukar/berlebih ditolak. Sumber sintetis tidak menerima penulisan bersamaan; 22 tabel di dalam fixture masih kosong. Database serta media operasional tidak menjadi sumber atau tujuan latihan tersebut.

Acuan operasional berikut **masih harus diterapkan dan diuji pada lingkungan nyata**; kelulusan fixture bukan klaim bahwa backup produksi telah selesai:

1. Siapkan jendela tanpa penulisan konten/media dan ambil snapshot database serta `STUDY_MEDIA_ROOT` dari keadaan yang konsisten. Catat versi migration dan manifest kunci/ukuran/hash; lindungi kedua cadangan sebagai satu set.
2. Pulihkan pasangan database dan berkas ke lingkungan QA terisolasi dengan root media yang berbeda, lalu verifikasi setiap metadata menunjuk berkas yang sesuai ukuran/hash serta tetap mengikuti izin pengguna.
3. Laporkan metadata tanpa berkas, berkas berubah, dan berkas tanpa metadata. Periksa hasil transaksi belum pasti sebelum memutuskan pemulihan atau pembersihan; rekonsiliasi/orphan cleanup otomatis belum tersedia.
4. Uji penggantian/pelepasan, kegagalan filesystem, dan pemulihan set cadangan sebelum menetapkan jadwal, retensi, serta target waktu pemulihan produksi.

`scripts/database-backup-check.ps1` hanya memeriksa dump/pemulihan database pada kondisi QA yang dibatasinya. Ia belum mencadangkan atau memulihkan direktori gambar, sehingga kelulusan skrip tersebut tidak membuktikan restore media berpasangan.

Percobaan pemeriksaan database pada v13 melalui skrip lama ditolak secara aman oleh guard karena tabel aplikasi masih berisi catatan pembatasan login. Catatan tersebut tidak dihapus hanya untuk meluluskan tes; keberhasilan skrip lama tidak boleh dilaporkan. Bukti backup database v12 sebelum upgrade tetap historis. Skrip baru `scripts/media-backup-check.ps1` membuat kedua skema sumber/tujuan sintetis sendiri sehingga dapat memeriksa pasangan v13 tanpa menghapus atau membaca data operasional tersebut.

Opsi `qa-local.ps1 -RunMediaBackupRestoreCheck` menambahkan latihan baru secara terpisah dari `-RunBackupRestoreCheck`. Pada putaran ini latihan baru dan smoke dijalankan langsung, **bukan seluruh orkestrator QA**: beberapa subtes orkestrator membutuhkan worker web yang belum dapat diluncurkan. Gunakan perintah langsung dalam panduan pemulihan bila hanya hendak menjalankan latihan CLI; jangan menafsirkan tersedianya opsi sebagai bukti full QA atau izin mengubah runtime server.

## Perubahan autentikasi sesi

`account-security-check.ps1` kini juga menjalankan `account-security-resilience.mjs` dengan fixture akun terpisah. Penguji memakai dua worker HTTP PHP, sesi berbeda, dan penghalang lock akun. Tiga race wajib membuktikan dua waiter InnoDB sebelum dilepas: sandi baru sama/berbeda dan sisa satu kesempatan verifikasi. Pemeriksaan ini memerlukan instrumentasi MariaDB `INNODB_LOCK_WAITS`/`INNODB_TRX`; bukan bukti MySQL/PHP-FPM produksi. Helper `cli/check_account_security` hanya berjalan pada CLI development, `db_study` dan ID fixture `qa_security_<12 hex>_race`. Jalur helper melalui HTTP wajib 404.

Penguji kegagalan audit menyisipkan kueri SQL invalid pada koneksi/transaksi yang sama tepat saat model hendak menulis audit. Hash/timestamp/audit harus rollback dan sesi lama tetap valid. Ini bukan simulasi disk penuh, koneksi DB putus atau restart layanan. Worker dan direktori sesi privatnya dibersihkan; akun/audit milik fixture dihapus oleh skrip PowerShell pemiliknya. Tidak mengubah trigger, skema atau akun nyata.

Deploy fitur ganti sandi sendiri mewajibkan semua sesi lama tanpa `auth_stamp` untuk masuk ulang. Pergantian kunci aplikasi juga mencabut sesi; lakukan dalam jendela pemeliharaan yang dijelaskan kepada pengguna. Fitur ini tidak memerlukan migration baru saat diterapkan pada v12/32 tabel; penambahan gambar pendamping kemudian menaikkan skema ke v13/33 tabel. Jangan menaruh hash sandi, HMAC kredensial atau nilai kata sandi dalam log, HTML, dump diagnostik maupun URL.

`account-security-check.ps1` membutuhkan `-Php81Path`, `-MysqlPath`, `-BaseUrl` loopback dan `-AppEncryptionKey` yang sama dengan proses web; kunci digunakan untuk pembersihan tepat baris HMAC uji, tidak dicetak. Opsi `-ChromePath`/`-CaptureScreenshots` memeriksa tema orang tua/anak di tiga viewport. `qa-local.ps1` menjalankannya saat kunci diberikan; bila tidak, ada peringatan skip yang tidak boleh dianggap lulus. Unit `test-session-credentials.php` memeriksa penanda hilang/invalid/usang, akun nonaktif dan rotasi ID tanpa DB. Email pemberitahuan, pemulihan akun dan HTTPS produksi belum dituntaskan oleh fitur ini.

## Pengujian panel audit lokal

`scripts/user-directory-check.ps1` juga menjalankan `scripts/audit-log-cases.ps1`: hak admin/revokasi, hanya-baca, kerahasiaan metadata, tanggal WIB/kalender, filter gabungan dan pagination. Fixture audit dibuat dengan ID terpantau lalu dihapus secara tepat, termasuk event tanpa pelaku. Opsi `-ChromePath` memeriksa empat keadaan halaman (direktori dan audit, masing-masing daftar/hasil filter) pada tiga viewport, total 12 kombinasi; `-CaptureScreenshots` menyimpan capture sementara. Suite masuk ke `qa-local.ps1`; pengujian Windows Nginx/PHP-CGI tetap tidak membuktikan Ubuntu/PHP-FPM/TLS/aaPanel.

## Tujuan

Pemeriksaan pemulihan HTML 404/kesalahan umum tersedia di `scripts/test-recovery-pages.php` (jalankan juga dengan `--without-config`) dan `node scripts/recovery-http-check.mjs --base-url http://127.0.0.1:8081/index.php/`. Keduanya masuk QA lokal; tes HTTP menerima hanya loopback, tidak membuat akun, dan memeriksa GET/HEAD 404 serta penolakan POST tanpa CSRF. Template memakai CSS mandiri, status asli, no-store/noindex, teks Indonesia dan tautan dari konfigurasi yang tervalidasi. Pesan 5xx tidak memuat detail internal dan tidak memanggil konfigurasi baru. Tanpa `APP_BASE_URL` valid saat 5xx, tombol kembali sengaja tidak ditampilkan. Ini tidak mengganti kebijakan debug/log produksi atau template error PHP/database/exception; lihat [standar pemulihan](STANDAR_UI_DAN_CODING.md#halaman-pemulihan-http-mandiri) dan [baseline 404](visual-baseline/2026-10-15-recovery-pages/README.md).

Proyek memakai CodeIgniter 3.1.13 dari arsip yang tersedia. Lingkungan kerja saat ini adalah XAMPP Windows dengan PHP 8.0.30; PHP 8.1 tetap baseline kerja yang diminta, diuji lewat runtime terpisah. Karena PHP 8.1 sudah EOL, PHP 8.5.11 NTS juga diuji dalam instalasi terisolasi sebagai kandidat kompatibilitas yang masih didukung—belum menjadi keputusan penggantian target atau sertifikasi produksi. Pertahankan konfigurasi aplikasi yang sama; bedakan hanya nilai environment dan konfigurasi server.

Arsip sumber `CodeIgniter-3.1.13.zip` disimpan lokal dan tidak dimasukkan ke Git. SHA-256 arsip: `2E55FA5E1FC4BB22F2EA12058294EAFF5DEFD74347806ECAA4BD568094755778`.

CI3 adalah framework generasi lama; 3.1.13 dirilis pada 2022. Catatan resminya menyebut penyesuaian PHP 8.0 dan 8.1. PHP 8.1 berakhir dukungan keamanan upstream pada 31 Desember 2025, sehingga target 8.1 hanya dipakai untuk kompatibilitas/pengembangan dan bukan rekomendasi produksi publik. Sebelum peluncuran, lakukan uji kompatibilitas di PHP yang masih didukung dan rencanakan peningkatan versi. Jangan menganggap “bisa dibuka” berarti siap produksi.

### PHP 8.5 sebagai kandidat uji, bukan keputusan produksi

Pada 15 Oktober 2026, PHP 8.5.11 tersedia dari kanal unduhan Windows resmi. Versi NTS x64 diuji terpisah di `C:\tools\php85-study`; SHA-256 ZIP yang diunduh cocok dengan nilai yang dicantumkan PHP.net: `0ea96e0d2b9b737a6036f05cf4e95c49313faa6d0f27bd97edb2742503f0c043`. Runtime ini tidak mengganti PHP XAMPP atau PHP 8.1.

Bukti lokal diperbarui 15 Oktober 2026: seluruh `qa-local.ps1` dengan Chrome lulus ketika aplikasi dilayani PHP 8.5.11 (smoke HTTP, autentikasi, admin, role, progres/keluarga, migration/constraint, tes JavaScript, serta 12 kombinasi browser 360/768/1280 px dengan fokus keyboard). Enam halaman/rute awal diperiksa tanpa marker deprecation. Untuk mengatasi deprecation CI3, patch kompatibilitas lokal diterapkan pada `system/core/Exceptions.php`, `URI.php`, `Router.php`, `Controller.php`, `Loader.php`, `system/database/DB_driver.php`, dan `system/libraries/Session/Session.php`; `scripts/php-dev-router.php` membantu server lokal meneruskan aset langsung dan route aplikasi. Pemeriksaan terbaru `smoke-check.ps1` kini melintaskan lint 240 berkas PHP seluruh proyek pada PHP 8.0, 8.1, dan 8.5; seluruh lint, migration check, route dan aset smoke test lulus. PHP 8.5 masih kandidat uji, bukan target produksi yang telah disahkan: hasil Windows tidak membuktikan Ubuntu/Nginx/PHP-FPM. Audit ulang patch ini setiap pembaruan CI3.

Jika runtime ini tersedia, smoke check dapat melintaskan lint PHP 8.5 dengan parameter opsional. Nyalakan server PHP 8.5 dalam `CI_ENV=testing` di port lokal yang kosong dengan ekstensi database dan keamanan, lalu arahkan `-BaseUrl` ke server itu:

```powershell
$env:CI_ENV = 'testing'
$env:APP_BASE_URL = 'http://127.0.0.1:8088/'
& 'C:\tools\php85-study\php.exe' `
  -d extension_dir='C:\tools\php85-study\ext' `
  -d extension=mysqli -d extension=mbstring -d extension=openssl `
  -d extension=curl -d extension=fileinfo -d date.timezone=UTC `
  -S 127.0.0.1:8088 -t 'C:\xampp\htdocs\study'
```

Di terminal lain, jalankan smoke test dengan `-Php85Path 'C:\tools\php85-study\php.exe'`. Hentikan server sesudah pengujian. Gunakan hanya environment lokal/test; jangan memakai akun MySQL root pada staging/produksi.

## XAMPP Windows

1. Letakkan folder proyek di `C:\xampp\htdocs\study`.
2. Jalankan Apache dan MySQL dari XAMPP.
3. Basis data lokal aplikasi ini bernama `db_study`, memakai `utf8mb4` dan collation `utf8mb4_unicode_ci`.
4. Buat akun MariaDB lokal khusus untuk aplikasi, lalu salin `application/config/database.local.example.php` menjadi `application/config/database.local.php`. File lokal itu diabaikan Git. Jangan sambungkan aplikasi sehari-hari sebagai `root`.
5. Buka `http://localhost/study/`. Halaman bawaan CI3 harus tampil. URL ini belum berarti modul produk sudah dibuat.
6. XAMPP terpasang saat ini memakai PHP 8.0.30. Untuk target pengembangan PHP 8.1, gunakan distribusi/instalasi XAMPP yang menyediakan PHP 8.1 atau konfigurasi PHP terpisah. Jangan mengganti folder PHP XAMPP yang sedang dipakai aplikasi lain tanpa membuat cadangan dan menguji seluruh proyek lokal.

#### Baseline konfigurasi lokal yang telah diperiksa

Pemeriksaan 15 Oktober 2026 membandingkan PHP CLI XAMPP 8.0.30, PHP CLI terpisah 8.1.34, serta MariaDB 10.4.32. Kedua runtime lulus lint dan QA alur yang diperiksa pada baseline tersebut; ekstensi inti (`mysqli`, `mbstring`, `openssl`, `curl`, `fileinfo`, dan `session`) tersedia pada keduanya. Daftar modul tidak identik: PHP 8.0 memiliki `bz2`, `exif`, `ftp`, `gettext`, dan `pdo_sqlite` yang tidak ada pada PHP 8.1; PHP 8.1 memiliki `gd` yang tidak ada pada PHP 8.0. GD sekarang diperlukan oleh unggah gambar pendamping, sehingga kelulusan baseline lama tidak membuktikan kesetaraan unggah. Ulangi inventaris ini untuk SAPI Apache/PHP-FPM dan staging; hasil CLI tidak otomatis membuktikan modul web sama.

Konfigurasi `php.ini` global yang diperiksa memakai timezone berbeda (`Europe/Berlin` pada PHP 8.0 dan `Asia/Jakarta` pada PHP 8.1). Untuk menghindari perubahan global yang dapat mengganggu proyek lain, aplikasi Study menetapkan timezone sendiri ke `Asia/Jakarta` dan `time_reference` CI3 yang sama; `scripts/test-application-timezone.php` memeriksanya di kedua runtime. Koneksi lokal MariaDB memakai `SYSTEM` (teramati UTC+07:00). Default skema memakai `utf8mb4_unicode_ci`; migration 13 mempertahankan default tabel ini pada jumlah terkini 33 tabel, dengan kolom kunci/hash gambar ASCII yang ditetapkan khusus. `sql_mode` server lokal adalah `NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION`, sedangkan koneksi CI3 belum memaksa strict mode. Karena itu parity konfigurasi SQL dan uji versi DB produksi tetap terbuka sampai staging ditentukan.

### Menjalankan Study dengan runtime PHP 8.1 terpisah

Runtime PHP 8.1.34 saat ini disimpan di `C:\tools\php81-study`, tidak mengganti PHP Apache XAMPP. Jalankan server pengembangan hanya untuk proyek ini dari PowerShell:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/start-dev-server.ps1
```

Skrip memeriksa binary dan ekstensi wajib, menolak port yang sudah dipakai, hanya bind ke loopback `127.0.0.1`, menetapkan `CI_ENV=development` serta `APP_BASE_URL` sementara, memakai `scripts/php-dev-router.php` agar aset statis disajikan langsung dan route aplikasi diteruskan ke CI3, serta tidak menjalankan migration. Buka URL yang dicetak (default `http://127.0.0.1:8081/`); hentikan dengan `Ctrl+C`. Untuk memilih port/runtime lain gunakan `-Port 8090` atau `-PhpPath 'C:\lokasi\php.exe'`. Server bawaan PHP ini hanya untuk pengembangan, bukan untuk produksi. Koneksi aplikasi lokal memakai konfigurasi `database.local.php` dan akun terbatas `study_app`.

### Migration dan administrator awal

Pengguna `study_app` hanya memerlukan hak `SELECT`, `INSERT`, `UPDATE`, dan `DELETE` pada `db_study`. Buat pengguna lokal awal dari akun administrator MariaDB tepercaya. Akun contoh ini tanpa kata sandi hanya cocok untuk XAMPP di mesin pengembangan yang tidak dibuka ke jaringan:

Untuk menyiapkan database dan migration pada mesin XAMPP lokal, jalankan satu perintah dari PowerShell di root proyek setelah MySQL XAMPP aktif dan runtime PHP 8.1 terpasang:

```powershell
./scripts/setup-local.ps1
```

Skrip memeriksa PHP 8.1 serta ekstensi `mysqli`, `mbstring`, dan `openssl`, lalu memanggil penyiapan DB dan menjalankan migration dengan akun `study_migrator` yang hanya berhak pada skema `db_study`. Skrip aman dijalankan ulang, tidak menimpa file konfigurasi lokal, tidak membuat akun administrator, dan tidak menjalankan migration pada skema lain. Kredensial sementara dipulihkan setelah proses selesai. Sesuaikan `-MysqlPath` atau `-PhpPath` jika instalasi berada di lokasi lain.

Penyiapan akun/skema saja (tanpa menjalankan migration) tetap tersedia bila diperlukan:

```powershell
./scripts/setup-local-database.ps1
```

```sql
CREATE USER 'study_app'@'127.0.0.1' IDENTIFIED BY '';
GRANT SELECT, INSERT, UPDATE, DELETE ON db_study.* TO 'study_app'@'127.0.0.1';
CREATE USER 'study_migrator'@'127.0.0.1' IDENTIFIED BY '';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON db_study.* TO 'study_migrator'@'127.0.0.1';
```

`study_migrator` dibatasi pada satu skema dan hanya dipakai saat perubahan struktur; haknya hanya operasi data dan DDL yang diperlukan, tanpa hak global atau grant option. Pada staging/produksi, gunakan kata sandi acak dari secret manager/environment dan hak minimum yang sesuai; jangan gunakan akun lokal tanpa kata sandi.

Salin file contoh dan sesuaikan hanya untuk mesin lokal:

```powershell
Copy-Item application/config/database.local.example.php application/config/database.local.php
```

Migration tidak berjalan otomatis saat ada permintaan web. Jalankan dengan kredensial migrator terpisah:

```powershell
$env:DB_HOST = '127.0.0.1'
$env:DB_USERNAME = 'study_migrator'
$env:DB_PASSWORD = ''
$env:DB_DATABASE = 'db_study'
& 'C:\tools\php81-study\php.exe' index.php cli/migrate
Remove-Item Env:DB_HOST,Env:DB_USERNAME,Env:DB_PASSWORD,Env:DB_DATABASE -ErrorAction SilentlyContinue
```

Setelah migration selesai tanpa kegagalan, perintah ini boleh diulang untuk memeriksa bahwa skema sudah mutakhir. Jangan mengulang otomatis setelah migration gagal: DDL MySQL/MariaDB dapat meninggalkan perubahan parsial meski nomor versi belum berubah. Skrip smoke test memakai akun migrator terbatas yang sama; tes migration bersih menggunakan administrator hanya untuk membuat/menghapus skema QA sementara.

Administrator pertama hanya dibuat melalui CLI pada mesin tepercaya. Isi `STUDY_ADMIN_USERNAME`, `STUDY_ADMIN_EMAIL`, `STUDY_ADMIN_NAME`, dan `STUDY_ADMIN_PASSWORD` sementara pada proses PowerShell, lalu jalankan `& 'C:\tools\php81-study\php.exe' index.php cli/create_admin`. Gunakan sandi acak minimal 16 karakter UTF-8 dan maksimal 72 byte tanpa NUL; jangan menaruhnya di file atau membagikannya, lalu hapus keempat variabel dari environment proses. Perintah bootstrap hanya berhasil sekali; perubahan role selanjutnya harus mengikuti proses admin yang diaudit. `scripts/password-bootstrap-check.ps1` memeriksa penolakan batas invalid tanpa membuat administrator nyata, dan dijalankan oleh QA lokal.

Untuk pemeriksaan cepat setelah perubahan, nyalakan server yang hendak diuji lalu jalankan dari root proyek:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/smoke-check.ps1 `
  -Php80Path 'C:\xampp\php\php.exe' `
  -Php81Path 'C:\tools\php81-study\php.exe' `
  -BaseUrl 'http://127.0.0.1:8081/index.php/'
```

Untuk membuktikan migration dapat dipasang dari skema kosong tanpa mengubah `db_study`, jalankan `scripts/migration-check.ps1`. Script hanya membuat nama database sementara dengan pola `db_study_migration_qa_<acak>`, memasang migration dua kali, memeriksa 33 tabel/engine/collation (migration 13), menguji unique/FK serta rollback transaksi, lalu menghapus database yang dibuatnya. Jalankan hanya terhadap MySQL/MariaDB QA lokal yang memang memberi hak `CREATE DATABASE` dan `DROP DATABASE`; jangan arahkan pemeriksaan ini ke staging/produksi.

Penanganan kegagalan migration 001–013 memakai exception agar migrator CI3 berhenti, bukan hanya nilai balik FALSE. Pengaman terbaru pada `down()` 001–008 memeriksa semua tabel terdampak sebelum DDL pertama dan menolak bila ada baris, query gagal, atau hasil COUNT tidak sah; 013 kini memakai validasi COUNT ketat yang sama. Empat role seed pada 003 juga dilindungi, tanpa pengecualian atau penghapusan agar tes lolos. SQL `up()`, skema dan nomor versi tidak berubah.

Unit pada PHP 8.0/8.1/8.5 mencakup 26 jalur sukses, 142 kegagalan statement, 43 posisi COUNT sebelum write, 860 hasil nonempty/malformed, 86 exception pembacaan dan 43 kontrol integer nol. Penguji `legacy-migration-guard-check.ps1` membuat 18 skema sementara secara terpisah: sembilan penolakan satu langkah pada versi 1–8/13 dengan digest seluruh data/DDL/versi tetap sama, delapan rollback kosong ke baseline versi sebelumnya (tanpa 003), dan penguji histori 12→11 yang sudah ada. Semua dibersihkan tepat berdasarkan skema milik run; `db_study` tidak diturunkan. [Panduan keamanan migration](PANDUAN_KEAMANAN_MIGRATION.md) menjelaskan cakupan fixture, perintah uji dan batas bukti.

Jika migration gagal, hentikan penulisan aplikasi, catat pesan kegagalan dan versi, lalu bandingkan tabel/kolom/index yang benar-benar ada dengan migration sebelum memilih pemulihan dari cadangan atau perbaikan maju yang ditinjau. Jangan menambahkan `IF NOT EXISTS`, mengubah nomor versi secara manual, atau melakukan retry buta untuk menutupi DDL parsial. Guard ini berlaku per langkah, bukan transaksi seluruh rentang downgrade: langkah sebelumnya dapat sudah berhasil sebelum guard berikutnya menolak, dan nomor migration CI3 saat kegagalan multi-langkah tidak cukup membuktikan bentuk skema. Jangan menjalankan downgrade beberapa versi otomatis. Uji rencana pemulihan pada salinan database terlebih dahulu; keberhasilan unit tidak menggantikan backup, penghentian seluruh penulis, pengujian race atau prosedur pemulihan deployment.

Migration 12 menambahkan dua tabel histori game. Sebelum upgrade, jalankan backup/restore versi sebelumnya; sesudah upgrade ulangi QA pada versi terbaru. `scripts/matching-storage-check.ps1` menguji model dengan akun/konten sintetis lokal, bukan pengguna nyata. Dengan `-BaseUrl` HTTP loopback, skrip juga menguji alur peserta mulai/simpan/resume/hentikan, riwayat, dan laporan keluarga melalui `matching-family-cases.ps1`. `-ChromePath` menambah pemeriksaan delapan halaman/alur peserta dan empat halaman keluarga pada tiga lebar, termasuk radio keyboard, pengiriman dengan peningkatan JavaScript, refresh dan formulir native saat aset JavaScript diblokir. Alur recovery mengaktifkan offline Chrome sebelum pengiriman (nol mutasi), lalu menggagalkan respons POST setelah server menghasilkan 303 (satu mutasi); pesan ketidakpastian dan GET pemulihan harus mempertahankan hitungan yang benar. Ini bukan dukungan pengerjaan offline. `-CaptureScreenshots` menyimpan capture sementara untuk tinjauan. Controller `cli/check_matching` hanya boleh berjalan melalui CLI development dan menolak HTTP. Mode tersimpan tersedia bagi peserta masuk; laporan orang tua mensyaratkan akun/tautan aktif dan hanya-baca. Downgrade ditolak bila tabel histori berisi data. QA ini tidak boleh diarahkan ke produksi.

Uji ketahanan game lokal memakai opsi `-RunResilienceCheck` pada suite matching (juga diaktifkan oleh `qa-local.ps1`). Harness `scripts/matching-resilience-check.mjs` menjalankan dua server PHP 8.1 terisolasi di port loopback acak, dua sesi login untuk peserta sintetis yang sama, dan key sementara yang sama. Sesi PHP disimpan di direktori sementara khusus, bukan dicampur dengan sesi pengguna nyata. Akun runtime tetap `study_app`; pemeriksaan fixture dan observasi lock memakai root MySQL QA lokal.

Untuk membuktikan tumpang tindih, CLI `check_matching/hold` menahan lock pelajaran sintetis maksimal 20 detik. Dua POST dijalankan pada worker berbeda; tes wajib melihat dua transaksi menunggu di `INNODB_LOCK_WAITS` sebelum lock dilepas. Tanpa bukti dua waiter, tes gagal, bukan mengklaim request paralel. Cakupan: key mulai sama, key mulai berbeda, pilihan sama, pilihan dengan key berbeda pada satu revisi, pasangan terakhir ganda, dan hentikan bersamaan dengan pilihan. Mutasi memakai endpoint autentikasi/CSRF/HMAC aplikasi, bukan SQL untuk menggantikan aksi peserta.

Proxy loopback menguji dua titik kegagalan: request dibuang sebelum diteruskan (tidak boleh ada penulisan), atau response dibuang setelah backend mengirim 303 (penulisan harus tetap ada tepat satu kali). GET dari sesi kedua harus membaca revisi terbaru; retry pilihan yang sama dengan CSRF baru tidak boleh menggandakan langkah. Worker/barrier/proxy milik tes dihentikan, direktori sesi sementara tervalidasi dibersihkan, lalu pemilik fixture PowerShell membersihkan data QA.

Uji ini khusus lingkungan lokal Windows/MariaDB 10.4 dengan tabel instrumentasi lock tersebut. Ini **bukan** pembuktian Nginx/PHP-FPM/MySQL 8, beban banyak pengguna, refresh/back-forward browser saat upload, atau mode offline. Untuk MySQL 8, sediakan observasi lock yang sesuai pada staging terpisah; jangan melemahkan tes dengan mengabaikan bukti overlap. Jangan menjalankan harness terhadap data/server produksi.

Suite Chrome kini juga menambah alur peserta kesembilan, **navigasi saat menunggu respons** (total sembilan peserta + empat keluarga, masing-masing tiga lebar). Harness menahan respons POST pada 303, menjalankan Kembali/Maju melalui history browser, lalu mengulangi dengan refresh. Dua request per lebar harus menghasilkan dua revisi, tidak ada replay, dan formulir revisi terbaru kembali dapat dipakai. Pembuktian ini untuk navigasi setelah commit tetapi sebelum browser menerima respons, bukan navigasi selama upload sebelum commit. Handler BFCache diuji terisolasi melalui `pageshow.persisted`; hasil navigasi Chrome tidak boleh disebut bukti BFCache aktif tanpa observasi cache tersendiri.

Migration 9 menambah snapshot soal dan idempotensi latihan tanpa tabel baru. Terapkan melalui akun migrator sebelum memakai controller latihan. Downgrade sengaja ditolak bila sudah ada sesi latihan kursus agar histori tidak rusak; gunakan backup dan migration perbaikan maju. Jalankan `scripts/practice-check.ps1` untuk uji penulis → reviewer → peserta, hak akses, penilaian, serta histori. Skrip ini memakai fixture sintetis dan hanya menerima URL HTTP loopback. Opsi `-ChromePath` menguji tampilan; `-CaptureScreenshots` juga menyimpan capture sementara untuk tinjauan. Skrip telah masuk ke `qa-local.ps1`.

Laporan latihan keluarga menggunakan skema versi 9 yang sama, tanpa SQL tambahan. Suite latihan juga membuat dua akun orang tua dan dua anak sintetis untuk menguji laporan: hubungan aktif/dicabut, akun anak nonaktif, penolakan lintas keluarga/saudara, jawaban aktif tersembunyi, hasil selesai baca-saja, serta pagination dan total dari 11 percobaan. Semua fixture dan tautan keluarga dibersihkan di blok `finally`. Jangan mengarahkan skrip ini ke database pengguna nyata; parameter URL loopback bukan pengganti pemisahan database staging/produksi.

Penyusun draf latihan keluarga memerlukan migration 10: kolom kapabilitas paket `family_practice_enabled` dan `draft_revision`, tanpa tabel baru. Semua paket default tidak mengaktifkan fitur; jangan menyalakan flag atau membuat entitlement untuk pengguna nyata sebelum konfigurasi produk/pembayaran disepakati. Jalankan `scripts/parent-practice-check.ps1 -Php81Path ... -MysqlPath ... -BaseUrl ...` untuk CRUD draf, hak paket/waktu, batas 50 soal/100 draf, relasi keluarga, revisi/idempotensi, dan cleanup. Opsi Chrome/capture sama dengan suite latihan; skrip juga sudah diintegrasikan ke `qa-local.ps1`. Pengujian membuat paket/entitlement sintetis hanya untuk test, lalu menghapusnya. Upgrade lokal v9→v10 dan pemasangan bersih v10 telah dicoba setelah backup/restore v9 kosong/sintetis berhasil. Pengiriman dilanjutkan pada migration 11, bukan dianggap selesai oleh migration 10 saja.

Migration 11 menambah `available_from` dan unique key pasangan sesi/peserta di attempt. Upgrade lokal v10→v11 dilakukan setelah backup/restore v10 lulus; pemasangan bersih/replay dan penolakan attempt ganda diuji pada schema QA. `family-delivery-check.ps1` menjalankan orang tua membuat soal → kirim dengan jadwal → anak simpan/final → pembuat membaca hasil. Suite menguji waktu WIB/UTC, jadwal belum mulai/kedaluwarsa, hak paket/link dicabut saat aktif, snapshot setelah perubahan sumber, replay, pembatalan serta isolasi keluarga. Unit `test-practice-schedule.php` memeriksa tanggal invalid dan batas 90 hari. Keduanya sudah masuk orkestrator QA. Tidak diperlukan cron untuk menolak sesi kedaluwarsa: server memeriksa waktu pada setiap operasi. Akun/paket/entitlement uji bukan konfigurasi produksi dan selalu dibersihkan.

Penyalinan sesi keluarga tidak membutuhkan migration tambahan. Jalankan suite `family-delivery-check.ps1` yang juga memuat `family-copy-cases.ps1`; jangan menjalankan file cases secara terpisah karena memakai fixture/cleanup suite induk. Tes salin meliputi sumber draf/selesai/batal, anak sama/saudara, snapshot dan metadata lama/baru, replay, revisi, pembatasan akses, CSRF, kapasitas 50 soal/100 draf, dan penolakan data tidak lengkap tanpa salinan parsial. Opsi `-ChromePath` turut memeriksa formulir salin dan `-CaptureScreenshots` menyimpan capture lokal untuk ditinjau. Tidak ada hak paket atau akun produksi yang dibuat oleh tes.

Daftar sesi keluarga menggunakan `family-list-cases.ps1` sebagai bagian dari `parent-practice-check.ps1`, bukan skrip mandiri. Dataset sintetis mencakup pagination 10+1, pencarian literal `%/_/!`, gabungan anak/status, jadwal mendatang, kedaluwarsa, status final, parameter array/Unicode/markup, dan isolasi pembuat meski anaknya sama. Chrome menambahkan tampilan filter aktif serta hasil kosong ke suite draf. Helper status juga diuji dengan waktu tetap pada batas mulai/akhir. Tidak ada migration atau cron tambahan untuk filter.

Untuk XAMPP Apache, jalankan lagi dengan `-BaseUrl 'http://localhost/study/index.php/'`. Pemeriksaan ini meliputi lint dua runtime, status migration, halaman dasar, redirect rute privat, 404 kursus tak dikenal, serta aset CSS/JavaScript lokal; ini smoke test awal, bukan pengganti tes fitur, keamanan, atau UAT.

Game Cocokkan Pasangan tidak memerlukan migration. `flashcard-check.ps1` turut memuat `matching-game-cases.ps1` untuk akses materi gratis/premium/pratinjau, revokasi, draf/arsip, escaping, usia dan tidak adanya mutasi progres/ledger. Opsi Chrome menguji delapan pasangan/dua babak dengan input keyboard, salah/benar, klik ulang, reset dan reduced motion pada tiga ukuran. `test-matching-cards.php` memeriksa kelayakan kartu; `npm run test:ui` juga menjalankan `test-matching-game.mjs` untuk pembagian 2–100 kartu serta mesin interaksi. Semua masuk `qa-local.ps1`. Skrip JS dimuat sebagai module lokal hanya pada permainan yang cukup pasangan; pastikan server mengirim MIME JavaScript untuk `.js` saat staging. Isi contoh di suite adalah fixture, bukan materi produksi.

Untuk menguji pesan dan aksesibilitas error pada form masuk/daftar tanpa membuat akun:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/auth-input-check.ps1 `
  -MysqlPath 'C:\xampp\mysql\bin\mysql.exe' `
  -BaseUrl 'http://127.0.0.1:8081/index.php/'
```

Ulangi dengan base URL Apache `http://localhost/study/index.php/`. Script hanya mengirim nilai sintetis yang sengaja invalid, memastikan field error tertaut dan password tidak dipantulkan, lalu memeriksa tidak ada akun baru tersimpan.

Untuk uji integrasi progres baca dan entitlement paket aktif/kedaluwarsa dengan fixture sementara (script membuat lalu menghapus akun peserta/orang tua/anak, dua keluarga, kursus berbayar, paket, entitlement, enrollment, pelajaran, dan catatan progres QA), jalankan. Cakupan juga mencakup dashboard keluarga yang terisolasi, enrollment revoked, penolakan akses peserta lain, progres tersimpan otomatis dan manual, dua autosave berurutan dengan rotasi CSRF, serta penolakan autosave setelah akses dicabut. Jalankan juga `npm run test:ui` untuk debounce dan perilaku antarmuka autosave.

```powershell
npm run test:ui
```

Perintah di atas menguji perilaku JavaScript resume baca tanpa browser, termasuk skala posisi responsif dan pengaturan reduced motion.

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/progress-check.ps1 `
  -Php81Path 'C:\tools\php81-study\php.exe' `
  -MysqlPath 'C:\xampp\mysql\bin\mysql.exe' `
  -BaseUrl 'http://127.0.0.1:8081/index.php/'
```

Ulangi dengan `-BaseUrl 'http://localhost/study/index.php/'` untuk memeriksa Apache. Script memakai prefiks acak `qa_progress_` dan menghapus fixture saat berhasil maupun gagal; periksa hasil `PASS temporary ... cleaned` dan pastikan tidak menjalankannya bersamaan dengan proses pembersihan database lain.

Form pembuatan kursus/pelajaran pada panel admin dapat diuji dengan request array dan nilai scalar yang valid:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/admin-input-check.ps1 `
  -Php81Path 'C:\tools\php81-study\php.exe' `
  -MysqlPath 'C:\xampp\mysql\bin\mysql.exe' `
  -BaseUrl 'http://127.0.0.1:8081/index.php/'
```

Tes ini membuat administrator/konten sintetis sementara pada `db_study`, memeriksa atribut cookie sesi, rotasi ID saat login, audit login/logout tanpa rahasia, penolakan CSRF, serta validasi input kursus/pelajaran, lalu menghapus semua fixture dan audit terkait. Gunakan hanya database pengembangan lokal; verifikasi output cleanup setelah script selesai.

Untuk memverifikasi pembatasan role HTTP awal (editor konten, keuangan, dukungan, dan akun biasa), jalankan:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/role-access-check.ps1 `
  -Php81Path 'C:\tools\php81-study\php.exe' `
  -MysqlPath 'C:\xampp\mysql\bin\mysql.exe' `
  -BaseUrl 'http://127.0.0.1:8081/index.php/'
```

Ulangi dengan base URL Apache `http://localhost/study/index.php/`. Tes membuat lima akun sintetis, memeriksa izin panel umum/daftar kursus dan visibilitas hitungan ringkasan peran, logout, lalu menghapus akun/role/audit uji. Tes ini hanya mencakup rute awal; jangan menganggapnya sebagai bukti semua izin aplikasi.

Untuk menjalankan semua pemeriksaan lokal yang tersedia secara berurutan, gunakan orkestrator berikut setelah server Study aktif. Jalankan sekali untuk server PHP 8.1 dan sekali untuk Apache; gunakan hanya `db_study` pengembangan karena beberapa suite membuat dan menghapus fixture sintetis. Script menolak URL non-loopback, HTTPS, kredensial, query, serta fragment sebelum tes dimulai.

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/qa-local.ps1 `
  -Php80Path 'C:\xampp\php\php.exe' `
  -Php81Path 'C:\tools\php81-study\php.exe' `
  -MysqlPath 'C:\xampp\mysql\bin\mysql.exe' `
  -BaseUrl 'http://127.0.0.1:8081/index.php/' `
  -ChromePath 'C:\Program Files\Google\Chrome\Application\chrome.exe'
```

Untuk Apache, ganti `BaseUrl` menjadi `http://localhost/study/index.php/`. Orkestrator mencakup lint, smoke/private-file check, autentikasi, admin, role, progres/keluarga, migration database QA sementara, dan `npm run test:ui`. `-ChromePath` bersifat opsional; jika diberikan, pemeriksaan viewport dan aksesibilitas browser juga dijalankan. Untuk menyertakan uji pembatasan percobaan login, tambahkan `-AppEncryptionKey` dengan nilai yang sama persis seperti environment PHP web yang sedang diuji. Tes memeriksa ambang 10 per identitas/50 per alamat dan masa hitung 15 menit, hanya memakai identitas sintetis pada URL loopback, lalu menghapus catatan berdasarkan HMAC identitas itu. Gunakan kunci lokal sementara, jangan menyalin kunci produksi. Tanpa parameter ini, tes throttle dinyatakan dilewati. Jika `db_study` benar-benar masih kosong dari data aplikasi, `-RunBackupRestoreCheck` menjalankan `database-backup-check.ps1`: ia menolak dump bila ada tabel aplikasi berisi data, menyimpan dump sementara di direktori unik, memulihkan ke dua database QA, membandingkan jumlah baris setiap tabel, lalu hanya menghapus database/temp file yang dibuatnya. Jangan gunakan opsi ini setelah ada data pengguna/konten/pembayaran nyata. Ini memudahkan pemeriksaan kandidat lokal, bukan pipeline CI, pengganti UAT, ataupun bukti Linux/Nginx.

Untuk menguji kandidat PHP 8.5.11, jalankan server terisolasi pada port yang ditentukan di bagian PHP 8.5, aktifkan ekstensi yang diperlukan dengan opsi `-d`, lalu jalankan orkestrator yang sama dengan `-BaseUrl 'http://127.0.0.1:8088/' -Php85Path 'C:\tools\php85-study\php.exe'`. Opsi `Php85Path` menambahkan lint seluruh proyek di binary tersebut; HTTP/integration/browser test berjalan pada runtime yang disajikan oleh `BaseUrl`. PHP 8.5 tetap kandidat kompatibilitas Windows, bukan keputusan produksi atau validasi PHP-FPM Linux.

Untuk mengukur overflow horizontal dan pemeriksaan aksesibilitas dasar route publik pada Chrome lokal yang sudah terpasang, jalankan `node scripts/browser-viewport-check.mjs --base-url 'http://localhost/study/index.php/' --chrome 'C:\Program Files\Google\Chrome\Application\chrome.exe'`. Script menerima URL loopback HTTP saja, menggunakan profil Chrome sementara yang terpisah dari profil pengguna, menguji beranda/katalog/masuk/daftar pada lebar 360/768/1280 px, memeriksa nama kontrol/atribut gambar/urutan fokus, mengirim Tab pertama untuk memastikan skip-link terlihat, lalu membersihkan profil sementara. Hasil ini pemeriksaan geometri DOM dan heuristik aksesibilitas, bukan screenshot visual, tes zoom, navigasi keyboard seluruh halaman, pembaca layar, audit WCAG, atau UAT.

### Pemeriksaan Nginx terisolasi di Windows

Pada 15 Oktober 2026, WSL belum terpasang dan Docker/Podman tidak ditemukan pada PATH. Jangan menyamakan pemeriksaan berikut dengan Ubuntu, PHP-FPM, aaPanel, MySQL 8 atau HTTPS. Instalasi WSL/reboot atau akses server staging memerlukan langkah terpisah; tidak dilakukan oleh harness.

Nginx Windows 1.31.7 dipasang portabel di `C:\tools\nginx-study-qa\runtime\nginx-1.31.7`, tanpa mengganti Apache atau memasang service. Arsip dari [unduhan resmi](https://nginx.org/en/download.html) diverifikasi memakai tanda tangan `.asc` dengan kunci Sergey Kandaurov dari [daftar kunci resmi](https://nginx.org/en/pgp_keys.html). Fingerprint kunci: `D6786CE303D9A9022998DC6CC8464D549AF75C0A`; SHA-256 arsip ZIP: `7c717ccf861f8925be64431d06d57634512a5014bcc8d149f279928f5e8151bf`. Kunci diambil melalui HTTPS resmi; verifikasi signature ini bukan klaim trust Web-of-Trust independen. Runtime/signature/keyring berada di luar document root dan tidak masuk repo. Versi Windows memiliki keterbatasan dan bukan pengganti server Linux; lihat [dokumentasi Windows Nginx](https://nginx.org/en/docs/windows.html).

Jalankan dari folder Study:

```powershell
node scripts/nginx-config-check.mjs --nginx 'C:\tools\nginx-study-qa\runtime\nginx-1.31.7\nginx.exe' --php-cgi 'C:\tools\php81-study\php-cgi.exe'
```

Harness menurunkan konfigurasi sementara dari template yang sama: mengganti hanya listen/domain/root/TLS/environment/include/socket untuk HTTP loopback + PHP-CGI 8.1. Aturan location tetap diuji. Dua proses server memakai port acak, runtime DB `study_app` pada `db_study`, kunci QA acak dan direktori sesi/log/config temporer. Tidak memakai konfigurasi produksi atau port Apache. Tambahkan `--run-qa` untuk menjalankan orkestrator QA lewat Nginx termasuk throttle dengan kunci QA proses yang sama. Opsi ini membuat/menghapus fixture database lokal seperti `qa-local.ps1`, bukan untuk data produksi; tidak otomatis menjalankan Chrome atau backup/restore. Proses milik harness beserta turunannya dihentikan berdasarkan PID, lalu hanya direktori sementara yang dibuat harness dihapus.

Tes mereproduksi pola lama `/.(?!well-known...)` yang salah menganggap titik sebagai karakter apa pun sehingga `/masuk` mendapat 404. Pola baru `/\.(?!well-known...)` hanya menargetkan segmen file tersembunyi. Template kini hanya meneruskan eksekusi PHP melalui front controller `index.php`, mendukung URL `index.php/path`, melayani aset dari `/assets/` yang ada, serta tidak melayani berkas sembarang dari root. Uji positif dan negatif dibutuhkan bersama: 404 semua halaman bukan bukti keamanan yang berhasil. Aturan pemilihan location mengikuti [referensi resmi Nginx](https://nginx.org/en/docs/http/ngx_http_core_module.html#location).

### Pemeriksaan zoom sebelum deployment

Jalankan `node scripts/browser-viewport-check.mjs --base-url http://localhost/study/index.php/ --browser-zoom 100 --extra-path qa-recovery-missing-page --extra-name recovery-404`, kemudian ulangi dengan `--browser-zoom 200`. Mode ini memakai zoom halaman Chrome sungguhan pada profil sementara dan menolak hasil bila faktor zoom tidak terbukti. Ukuran area browser 720/768/1280 piksel menghasilkan viewport CSS 360/384/640 pada 200%; jangan menyebutnya ukuran perangkat seluler asli. Mode biasa tanpa argumen zoom tetap memeriksa viewport 360/768/1280.

`qa-local.ps1 -ChromePath ...` menjalankan dua tingkat zoom tersebut setelah pemeriksaan browser biasa. Guard kesehatan memerlukan beranda HTTP 200 sebelum browser dibuka. Tab memeriksa kontrol hingga berulang, menunggu posisi gulir stabil dari proses penguji (bukan menunggu animation frame pada halaman yang mungkin kehilangan fokus), dan memastikan fokus tampak. Form publik kosong diuji validasi native tanpa POST. Lihat [bukti dan batas cakupan zoom](visual-baseline/2026-10-15-browser-zoom/README.md); ini belum menguji halaman privat atau menggantikan UAT/pembaca layar.

## Ubuntu + Nginx + PHP-FPM + aaPanel

### Konfigurasi server Project Pintar — 16 Oktober 2026

Pembaruan fitur setelah deployment awal: migration tambahan 014–015 sudah diterapkan tanpa mengulang migrasi awal; sekarang `db_study` versi 15/35 tabel. Pengaturan umum, review operator tunggal, konfigurasi Midtrans sandbox dan pengaman kuota keluarga dijelaskan di [pusat pengaturan server](PUSAT_PENGATURAN_SERVER.md). Angka 13/33 pada catatan pemindahan berikut adalah snapshot deployment awal, bukan versi terkini.

- Source: `/www/wwwroot/study`; URL kanonis: `https://www.projectpintar.com/`.
- Database aktif adalah `db_study`, berisi 33 tabel, migration 13, dan akun `ngadmin` ID 1. Sesuai keputusan pemilik, database berjalan mandiri di server ini pada `127.0.0.1:3308`, tanpa koneksi replikasi atau ketergantungan server database lain. Seluruh baris dari database impor `study` telah dibandingkan dan cocok sebelum aplikasi dialihkan; akun dan hash sandi ikut dipindahkan. Tidak ada kursus pada pemeriksaan deployment.
- MariaDB khusus ini memakai service `projectpintar-mariadb`, konfigurasi `/etc/projectpintar/mariadb.cnf`, datadir `/www/server/data-study`, socket `/tmp/mysql-study.sock`, dan binary MariaDB aaPanel. Service aktif otomatis saat boot, hanya mendengarkan loopback, serta memakai `read_only=0`. Administrasi lokal oleh root: `/www/server/mysql/bin/mysql --defaults-file=/etc/projectpintar/mariadb.cnf -uroot db_study`. Ini instance terpisah dari MariaDB bawaan aaPanel di port 3306; pilih port 3308 saat menghubungkannya ke alat pengelolaan database.
- Runtime situs memakai PHP 8.4 aaPanel dalam service terpisah `projectpintar-php-fpm`, socket `/tmp/php-cgi-study.sock`. Konfigurasi berada di `/etc/projectpintar/php-fpm.conf`; environment produksi dan rahasia berada di `/etc/projectpintar/environment.conf`, hanya dapat dibaca root. `DB_DATABASE=db_study`, `DB_HOST=127.0.0.1`, dan `DB_PORT=3308`. Akun database `study_runtime` hanya memiliki SELECT/INSERT/UPDATE/DELETE pada `db_study`, tanpa hak admin global. Konfigurasi aplikasi mendukung `DB_PORT` dan tetap memakai 3306 jika tidak disetel.
- Sesi dan temporary upload berada di `/www/study-private/sessions` dan `/www/study-private/tmp`; folder media disiapkan di `/www/study-private/lesson-images`. Ekstensi `fileinfo` belum tersedia pada runtime, sehingga unggah gambar belum siap digunakan dan belum diuji.
- Vhost aaPanel `www.projectpintar.com.conf` menunjuk ke folder Study, meneruskan hanya front controller ke PHP, dan menolak source/config/dokumentasi internal. Vhost `projectpintar.com.conf` mengarahkan domain tanpa www ke URL kanonis. Salinan konfigurasi sebelum perubahan ada di `/www/backup/projectpintar-deploy-20261016/`.
- Cloudflare Tunnel harus memakai **HTTP** ke port 8080. Pemilik sudah mengubah service menjadi `http://localhost:8080`; alamat ini berfungsi dan tidak perlu diganti ke `127.0.0.1`. Beranda dan halaman masuk HTTPS publik terverifikasi HTTP 200. Bridge lokal port 8080 tidak melayani TLS.
- MariaDB bersama di port 3306 tetap `read_only=1`, dengan thread replikasi IO/SQL aktif setelah pemindahan. Database `study` lama tetap disimpan. Dump sebelum pemindahan: `/www/backup/projectpintar-deploy-20261016/study-before-db-study.sql`; salinan environment lama: `environment-before-db-study.conf` di direktori yang sama. Keduanya privat dan tidak berada dalam document root. Reset kata sandi akun ID 1 atas permintaan pemilik tercatat dalam audit; kata sandi tidak disimpan dalam dokumentasi.
- Validasi origin melalui bridge: beranda, masuk, katalog, CSS/font HTTP 200; route privat mengarah ke login; file internal HTTP 404; POST tanpa CSRF HTTP 403; cookie sesi Secure/HttpOnly. Konfigurasi Nginx/PHP-FPM valid, lint PHP 8.4 tanpa syntax error, tes environment/timezone/session credentials lulus. Setelah perpindahan, login `ngadmin` melalui HTTPS berhasil; beranda akun, panel pengelola, direktori pengguna dan daftar kursus HTTP 200. Sesi verifikasi sudah logout, dan audit login/logout tersimpan di `db_study`. Upload media dan alur belajar privat belum diuji.
- Pendaftaran publik tetap ditutup oleh guard produksi bawaan aplikasi. Jangan mengubah environment ke development untuk membukanya.
- Setelah mengubah konfigurasi, jalankan `/www/server/php/84/sbin/php-fpm -t -y /etc/projectpintar/php-fpm.conf` sebelum `systemctl reload projectpintar-php-fpm`, serta `/www/server/nginx/sbin/nginx -t` sebelum reload Nginx. Pertahankan socket khusus ini bila mengubah pengaturan PHP situs melalui aaPanel.

Direktori pengguna `kelola/pengguna` tidak memerlukan migration baru. `scripts/user-directory-check.ps1` memakai fixture lokal untuk peran, pencarian POST/CSRF dengan konteks sesi, batas konteks, literal search, sort/filter/paging, privasi, revokasi dan pembacaan tanpa mutasi akun. Skrip sudah masuk `qa-local.ps1`; `-ChromePath` menguji daftar biasa/tersaring pada tiga lebar, dan `-CaptureScreenshots` menyimpan capture sementara. Jangan menjalankannya pada produksi. Pada deployment, sesi harus tetap privat dan tidak dibagikan antarpengguna; kehilangan sesi membuat token pencarian tidak berlaku dan pengguna perlu mencari ulang. Direktori tidak memberi akses ekspor, perubahan akun, atau data belajar anak.

- Untuk tahap pengembangan yang disepakati, samakan PHP 8.1 di lokal dan server uji. Karena cabang 8.1 sudah EOL, jangan jadikan ini konfigurasi publik produksi; sebelum peluncuran, naikkan setelah uji kompatibilitas ke PHP yang masih mendapat pembaruan keamanan. Aktifkan sekurangnya `mysqli`, `mbstring`, `openssl`, `curl`, `fileinfo`, `json`, dan `session`; unggah gambar pendamping juga wajib memakai `gd` dengan PNG/JPEG, bukan sekadar Imagick. Periksa `xml` bila diperlukan oleh alat pengujian.
- Buat database dan pengguna khusus aplikasi dengan hak minimum pada database tersebut. Jangan gunakan akun root MySQL.
- Set environment PHP-FPM: `CI_ENV=production`, `APP_BASE_URL=https://domain-anda/`, `APP_ENCRYPTION_KEY` berupa kunci acak kuat, `APP_COOKIE_SECURE=1`, `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE`, dan `STUDY_MEDIA_ROOT` ke folder privat yang memenuhi guard di atas. Restart/reload PHP-FPM setelah mengubah pool.
- Jangan mengunggah ZIP arsip, file dump, `.env`, rahasia, atau cadangan ke document root publik.
- Idealnya document root Nginx menunjuk ke direktori publik khusus yang hanya berisi `index.php` dan `assets`; pindahkan `application`, `system`, `docs`, dan file konfigurasi ke luar document root, lalu sesuaikan `$application_folder` dan `$system_path` di `index.php`. Jika untuk tahap awal struktur CI3 masih satu folder, aturan Nginx wajib menolak akses web ke `application`, `system`, `docs`, `scripts`, `sql`, file `.env`, `.md`, `.sql`, `.zip`, `.log`, manifest dependency, dan file konfigurasi.
- Konfigurasi route Nginx harus meneruskan rute aplikasi ke front controller CI3 (fallback atau rewrite ke `index.php`), menggunakan socket/port PHP-FPM yang benar, dan meneruskan header HTTPS dengan benar. Terapkan lewat panel aaPanel atau konfigurasi vhost yang dikelola server; uji konfigurasi Nginx sebelum reload.
- Template awal dengan deny rules, front controller, static-asset headers, dan FastCGI tersedia di [`nginx-study.example.conf`](nginx-study.example.conf). Ini hanya contoh untuk struktur proyek datar saat ini: ganti domain, document root, sertifikat, socket PHP-FPM, dan PHP versi aman; simpan rahasia di konfigurasi pool, bukan vhost. Aturan location telah diuji melalui adaptasi HTTP Nginx Windows/PHP-CGI, tetapi template TLS asli belum diuji pada Linux/PHP-FPM dan belum boleh dianggap staging siap pakai. Konfigurasi port 80, redirect HTTPS serta challenge ACME harus mengikuti aaPanel dan diuji terpisah; template tidak menyediakan layanan challenge otomatis.
- Tambahkan header anti-sniffing, anti-framing, dan kebijakan referrer pada respons aset statis di Nginx. Respons dinamis sudah menetapkan baseline header dari `index.php`; verifikasi keduanya dari staging.
- Beri hak tulis hanya pada `application/cache`, `application/logs`, dan direktori unggahan yang memang diperlukan. Folder kode dan konfigurasi harus tidak dapat ditulis oleh proses web.
- Paksa HTTPS, atur backup otomatis terenkripsi, dan pastikan pemulihan backup pernah diuji. Jangan tampilkan galat PHP/SQL kepada pengunjung produksi.

## Catatan CodeIgniter

- `index.php` memakai `getenv('CI_ENV')` terlebih dahulu, lalu fallback ke `$_SERVER['CI_ENV']`; tanpa keduanya, default-nya `development`. Uji PHP-FPM/aaPanel tetap wajib untuk membuktikan pool benar-benar memberikan nilai `production`. Produksi tidak boleh berjalan sebagai `development`.
- `APP_BASE_URL` harus berakhiran garis miring. Pengaturan development bawaan adalah `http://localhost/study/`.
- Cookie aman hanya aktif jika `APP_COOKIE_SECURE=1`; gunakan nilai itu hanya di situs HTTPS.
- Kunci `APP_ENCRYPTION_KEY` bawaan hanya untuk pengembangan lokal dan wajib diganti sebelum staging/produksi. Jangan mengganti kunci setelah aplikasi menyimpan data terenkripsi tanpa rencana migrasi.
- `application/config/database.php` membaca variabel `DB_*` dan menyediakan nilai bawaan untuk XAMPP. Jangan menyimpan kata sandi produksi ke file yang ikut dibagikan.
- `index_page` sekarang kosong: URL publik tanpa `index.php` telah diuji di Nginx server Project Pintar pada 16 Oktober 2026. Redirect kompatibilitas hanya GET/HEAD; POST tidak dialihkan. Pola dan bukti ada di [panel monitoring server](PANEL_MONITORING_SERVER.md#url-bersih-branding-dan-seo). Contoh perintah XAMPP/Windows di atas adalah bukti historis; Apache perlu rewrite sebelum memakai konfigurasi URL bersih ini, dan belum diuji ulang pada perubahan server ini.
- `.htaccess` di root proyek menonaktifkan directory listing dan menolak akses Apache ke `docs`, `scripts`, `sql`, `.git`, file dokumentasi/arsip/konfigurasi tertentu, dan manifest dependency. Smoke test membuktikan empat contoh path mendapat 403 sedangkan aplikasi dan CSS tetap 200 pada XAMPP. Aturan ini tidak berlaku di server PHP bawaan atau Nginx; server bawaan development hanya bind ke loopback, dan Nginx tetap wajib memiliki deny rules sendiri yang diuji dari HTTP.

## Gerbang sebelum rilis

- Versi PHP, ekstensi, konfigurasi timezone, dan collation basis data terverifikasi sama dengan staging.
- Versi PHP produksi masih menerima pembaruan keamanan; PHP 8.1 hanya boleh dipakai untuk pengembangan/uji sementara.
- Halaman utama, login, sesi, unggah, unduh, tautan email, endpoint Midtrans, dan semua route diuji lewat HTTPS di Nginx.
- Notifikasi Midtrans diverifikasi di server, tahan terhadap pengiriman ulang, dan transaksi duplikat tidak menambah saldo dua kali.
- `CI_ENV=production`, debug database mati, rahasia tidak masuk repositori/log, dan direktori privat tidak dapat diakses langsung.
- Backup dan pemulihan, rotasi log, pemantauan kegagalan, serta prosedur rollback sudah dicoba.
- Untuk gambar, unggah multipart sukses pada SAPI target, batas PHP/Nginx, lokasi privat, serta restore database + berkas dan rekonsiliasinya sudah dibuktikan; kelulusan tes metadata/CLI saja tidak menutup gerbang ini.

## Rujukan versi

- [Catatan rilis resmi CodeIgniter 3.1.13](https://www.codeigniter.com/userguide3/changelog.html) menyebut perubahan kompatibilitas PHP 8.0 dan 8.1.
- [Versi PHP yang masih didukung](https://www.php.net/supported-versions.php) perlu diperiksa kembali sebelum memilih versi staging/produksi.
- [Unduhan resmi PHP untuk Windows](https://windows.php.net/download/) dan [riwayat rilis PHP](https://www.php.net/releases/) menjadi sumber versi serta checksum runtime uji.

