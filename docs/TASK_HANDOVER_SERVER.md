# TASK SERVER — Project Pintar

Tanggal handover: 16 Oktober 2026. Pemilik menghentikan pengembangan lokal untuk migrasi. Goal otomatis lokal sudah PAUSED. Dokumen ini adalah prompt kerja utama untuk agen di server; bukan bukti fitur di bawah sudah dibuat. Jangan melanjutkan pekerjaan lokal otomatis.

## 1. Instruksi langsung untuk agen server

Anda melanjutkan proyek Study, bukan membangun ulang. Baca dokumen ini dahulu. Temukan direktori proyek aktual, baca AGENTS.md jika ada, periksa keadaan kode/database/server, lalu laporkan ringkas sebelum perubahan. Jangan menganggap path Windows berlaku di Ubuntu. Pengembangan dilanjutkan hanya setelah pemilik memerintahkan melanjutkan di server.

Keputusan baru di sini mengungguli asumsi produk lama yang bertentangan. Jangan menghapus bukti historis atau mencentang pekerjaan yang baru direncanakan. Baca bagian relevan dari [roadmap](ROADMAP_PENGEMBANGAN.md), [standar](STANDAR_UI_DAN_CODING.md), [konsep](KONSEP_APLIKASI_PEMBELAJARAN.md), [keputusan lama](KEPUTUSAN_MVP_DAN_ASUMSI.md), dan [deployment](PANDUAN_LINGKUNGAN_DAN_DEPLOYMENT.md) sesuai pekerjaan; tidak perlu membaca ulang seluruh riwayat screenshot pada setiap tugas.

Sasaran: aplikasi belajar berbayar/gratis untuk anak TK, SD, SMP, orang tua dapat memantau dan menyusun latihan; kredit dan poin dapat membuka akses sesuai aturan berbeda. Nama kerja sekarang **Project Pintar**, alamat kanonis **https://www.projectpintar.com/**. DNS, TLS, hosting dan domain belum dikonfigurasi oleh agen lokal.

## 2. Status nyata saat diserahkan

| Bagian | Sudah ada | Belum selesai |
| --- | --- | --- |
| Fondasi | CI3 3.1.13, patch kompatibilitas PHP lokal, MySQL/MariaDB, migration 001–013, 33 tabel | Bukti Linux/Nginx/PHP-FPM/aaPanel, deploy produksi |
| UI | Bootstrap 5.3.8, Bootstrap Icons 1.13.1, CoreUI Free 5.6.0 panel, tema usia dan komponen bersama | Ilustrasi/animasi lengkap, semua halaman komersial, uji pengguna |
| Akun | Daftar dev/test, login/logout, ganti sandi, pembatasan login, peran, hubungan keluarga awal | Google SSO, email/WA verification, lupa sandi, undangan keluarga dan aturan dua anak |
| Konten | Kategori/kursus/bagian/pelajaran, review/publikasi, susunan/revisi, satu gambar pendamping privat | Editor media lebih luas, materi kurikulum nyata, paket komersial lengkap |
| Belajar | Progres baca, flashcard, pilihan ganda kursus, latihan privat orang tua, laporan keluarga | Bank lintas kursus, esai, kebijakan kelulusan final, reward poin |
| Game | Cocokkan Pasangan lokal/tersimpan, hasil dan laporan keluarga | Battle/lomba komunitas, moderasi, poin/lencana, iklan |
| Uang | Struktur awal wallet/order/entitlement; akses paket diuji dengan data sintetis | Top up QRIS, ledger operasional, checkout, Platinum, slot anak, refund, rekonsiliasi |
| Pengelola | Konten, direktori pengguna hanya-baca, audit hanya-baca; masih navigasi atas bersama | Sidebar admin lengkap, monitoring lintas pengguna/modul, operator tunggal tanpa reviewer kedua, pusat pengaturan, operasi saldo/order/suspend |
| Trafik | Belum ada modul analitik trafik di Study yang ditemukan saat inspeksi | Ringkasan, tab per halaman dan tab per pengguna; pencatatan baru, bukan histori yang bisa diisi mundur |

Banyak tes lokal telah lulus. Bukti terakhir sebelum handover: lint 364 file PHP pada 8.0/8.1/8.5, regresi HTTP kartu/progres dan pemulihan tampilan. Itu bukan jaminan bebas bug atau bukti kesiapan produksi. Jangan mengulang seluruh matriks hanya untuk mengubah dokumentasi.

Windows sumber: `C:\xampp\htdocs\study`. Apache bersama masih PHP 8.0.30, PHP CLI target 8.1.34 di `C:\tools\php81-study\php.exe`; kandidat 8.5 tersedia. Server uji PHP 8.1 port 8081 terakhir tidak aktif; upaya peluncuran ditolak alat dan tidak diakali. Jangan bawa asumsi proses Windows ke server baru. PHP 8.1 dipilih pemilik sebelumnya, tetapi risiko usia dukungannya harus diselesaikan sebelum publik; jangan mengganti versi diam-diam. Patch dalam `system/` milik proyek jangan ditimpa arsip CI3 standar tanpa review.

Database `db_study` migration 13. **Pembaruan 16 Oktober 2026 setelah permintaan penomoran ulang:** akun nyata lokal `superadmin` sekarang ID **1** (sebelumnya 3634), wallet ID **1** milik user 1 dengan saldo nol, relasi system_admin tetap, empat role ID 1–4, dan enam audit ID 1–6 setelah tes login/logout. Seluruh tabel lain kosong kecuali satu baris migration versi 13. Tabel kosong ber-AUTO_INCREMENT kembali mulai 1; berikutnya user/wallet 2, role 5 dan audit 7 pada pemeriksaan akhir. Jangan memakai asumsi database kosong atau menghapus akun ini sebagai fixture QA. Tidak ada kursus siap jual. Backup sebelum penomoran ada di `C:\xampp\study-private\db-backups\db_study-before-renumber-20261016-055743.sql`; ini snapshot **sebelum** reset, bukan dump terbaru untuk migrasi. Operator harus mengambil snapshot terbaru untuk memindahkan keadaan dengan ID baru. Detail ada di [panduan migration](PANDUAN_KEAMANAN_MIGRATION.md).

Git sudah diinisialisasi tetapi belum mempunyai commit/remote pada pemeriksaan sebelumnya. Jangan memaksa checkout/reset untuk merapikan keadaan ini. Arsip proyek harus memasukkan source yang belum tracked, bukan hanya hasil git archive.

## 3. Akses admin dan mode operator tunggal

- Akun uji lokal `superadmin` sudah dibuat dengan peran internal tertinggi `system_admin`; login dan panel pengelola HTTP berhasil, sesi uji sudah logout.
- Sandi ada di catatan lokal `docs/AKSES_ADMIN_LOKAL.private.md`, dikecualikan dari Git dan deployment. Jangan mencetak/mengirim sandi melalui laporan. File ini tidak wajib dan tidak boleh dipublikasikan di server. Email placeholder akun bukan kontak terverifikasi.
- Bila mengimpor DB, hash akun ikut: rotasi sandi dan set kontak pemilik sebelum publik. Bila DB baru, gunakan `application/controllers/cli/Create_admin.php` melalui `php index.php cli/create_admin`; isi STUDY_ADMIN_USERNAME, STUDY_ADMIN_EMAIL, STUDY_ADMIN_NAME, STUDY_ADMIN_PASSWORD dalam environment proses privat. Jangan menaruh secret pada command history atau output. Bootstrap hanya membuat admin pertama; jangan menghapus admin lama untuk melewati guard.
- **Tugas server prioritas:** beri label Superadmin pada `system_admin` dan gunakan sebagai peran pemilik semua fungsi pengelolaan. Tidak perlu membuat hierarchy admin baru. Peran editor/keuangan/dukungan boleh dipertahankan tanpa memperumit UI pemilik.
- Aturan lama melarang penulis menyetujui kontennya sendiri. Tambahkan pengecualian operator tunggal khusus Superadmin, dengan konfirmasi/checklist kualitas dan audit; editor biasa tetap tidak bisa self-review. Ini belum diubah lokal.
- Semua akses pengelolaan bukan berarti boleh mengubah jawaban/nilai/ledger diam-diam. Akses dukungan ke data privat anak, jika dibuat, harus eksplisit, minimal dan tercatat. Superadmin tetap tidak boleh melewati validasi transaksi. Jangan membuat bypass global yang membuka semua endpoint privat.

## 3A. Tambahan permintaan pemilik: panel admin lengkap dan monitoring

Pembaruan setelah pemilik mencoba panel lokal, 16 Oktober 2026. **Ini tugas wajib kelanjutan server, bukan sekadar opsi tampilan.** Tidak ada implementasi aplikasi pada pembaruan ini.

### Temuan inspeksi dan pemisahan ruang kerja

Study `Admin::index()` membatasi panel ke system_admin/content_editor/finance_operator/support_agent, bukan peserta biasa. `views/admin/index.php` baru menampilkan ringkasan jumlah kursus/paket/order dan tautan direktori pengguna, audit, kursus. `views/layouts/header.php` memakai navigasi atas bersama; memuat CSS CoreUI tidak berarti sidebar admin sudah tersedia. Pengaturan pembayaran belum ada, bukan tersembunyi karena akun superadmin salah. Fitur soal/flashcard yang sudah ada berada di dalam kursus/pelajaran, bukan menu monitoring terpusat.

Di Pustaka, inspeksi source menemukan grup sidebar Pembelajaran pada `sql/2026-08-04c_sidebar_quiz_learn.sql`: Bank Soal, Sesi Latihan, Kompetisi & Lomba, Konten Game, Poin & Lencana serta konfigurasi terkait. Perluasan progres/rapor tersedia lewat `controllers/Learn_progress.php`, `Learn_reports.php`, `Learn_config.php`, model `Learn_progress_model.php`/`Learn_scope_progress_model.php`, view `learn/progress/index.php`, `detail.php`, `scope.php`, `rpg_episode.php` dan `learn/reports/index.php`. Ada ringkasan pengguna, riwayat bertab per modul, progres per sesi/konten, serta aksi akses/reset. Ini hasil membaca source/registrasi menu, bukan audit browser atau daftar menu database Pustaka terkini.

Adopsi alur dan kelengkapan pengelolaan, bukan menyalin seluruh SQL, reset destruktif atau skema Pustaka. Modul bahasa Inggris/RPG/cerita yang hanya tersedia di Pustaka dicatat sebagai kandidat adopsi, tidak ditampilkan seolah sudah berfungsi di Study dan tidak menggeser target TK/SD/SMP. Saat server membaca Pustaka, petakan setiap modul: sudah ada padanan Study / perlu adaptasi / ditunda, dengan alasan singkat.

### Layout admin yang wajib dibuat

- Ruang **Pengelola** memakai shell CoreUI/Bootstrap khusus: sidebar kiri yang bisa diciutkan, topbar ringkas, breadcrumb dan area kerja lebar. Ponsel memakai drawer yang dapat ditutup dengan tombol/Escape, fokus kembali ke pemicu dan navigasi tetap dapat dipakai dengan keyboard. Jangan memakai navbar promosi sebagai navigasi utama admin.
- Ruang **Belajar/Keluarga** tetap memakai UI sesuai usia. Sediakan tautan "Buka tampilan pengguna" tanpa mengganti identitas atau menyamar sebagai anak. Pengguna biasa tidak mendapat menu pengelola; akses URL langsung tetap diperiksa server.
- Superadmin melihat seluruh fungsi pengelolaan yang sudah diimplementasikan. Peran lain hanya menu/aksi sesuai izin, tanpa membuat manajemen role kompleks menjadi prasyarat pemilik bisa bekerja.
- Satu definisi menu aktif/izin digunakan pada desktop dan mobile. Modul belum dibuat diberi status "Belum tersedia" tanpa tautan mati dan tanpa angka palsu; tidak dihitung selesai hanya karena menu muncul. Halaman kosong harus menjelaskan belum ada data dan menawarkan langkah yang tepat.

Struktur menu sasaran:

| Kelompok sidebar | Halaman/fungsi |
| --- | --- |
| Ringkasan | Dashboard operasional: pengguna belajar aktif, aktivitas/modul, pesanan pending dan tugas review; periode serta waktu pembaruan jelas |
| Pengguna & Keluarga | Direktori, detail pengguna/anak, hubungan keluarga, status verifikasi, kuota anak, akses paket |
| Pembelajaran | Kursus/materi, bank soal, sesi latihan, flashcard, game, battle/lomba, monitoring progres, rapor, aturan poin/lencana dan penukaran |
| Paket & Pembayaran | Paket/harga/Platinum, top up/order, kredit dan ledger, slot anak, refund/koreksi dan rekonsiliasi |
| Trafik & Analitik | Tab Ringkasan, Per Halaman, Per Pengguna; detail dan filter konsisten |
| Pengaturan | Umum/identitas situs, pembayaran Midtrans, Google SSO/email/WA, aturan belajar, keluarga, gratis/premium, poin, privasi/retensi, fitur dan iklan |
| Operasional & Keamanan | Jejak audit, moderasi/laporan, status integrasi/backup/error; tampilan aman tanpa secret atau log mentah |

### Monitoring pembelajaran yang wajib tersedia

1. **Daftar lintas pengguna:** filter periode, TK/SD/SMP, modul/kursus/paket dan status; pencarian identitas aman, urutan allowlist, pagination server-side. Tampilkan alias/nama tampilan, aktivitas terakhir, materi selesai, attempt kuis dan hasil, kartu dikenal, game/battle, poin sesuai fitur yang tersedia. Jangan menjumlahkan unit yang berbeda menjadi satu angka penguasaan.
2. **Detail per pengguna:** tab Ringkasan, Kursus & Materi, Kuis/Latihan, Flashcard, Game/Battle, Poin & Akses. Hubungkan ke tab Trafik dengan rentang waktu yang sama; pisahkan kunjungan halaman dari kegiatan belajar. Nilai terakhir/terbaik, waktu mulai/final, status dan sumber data harus jelas. Nilai tidak boleh disimpulkan dari page view.
3. **Detail per modul/konten/sesi:** daftar peserta, berjalan/selesai, hasil, aktivitas terakhir, filter/pagination, lalu drill-down ke pengguna. Gunakan data progres/attempt yang sudah ada, bukan menduplikasi mesin penilaian. Bedakan persentase penyelesaian materi dan persentase jawaban benar; tampilkan pembagi dan aturan.
4. **Rapor:** ringkasan progres per periode yang dapat dibaca pemilik, dan versi keluarga hanya anak yang sah terkait. Tidak menampilkan seluruh keluarga kepada orang tua biasa. Ekspor opsional belakangan dengan batas data/izin dan perlindungan formula CSV.
5. **Pengelolaan akses:** Superadmin dapat menonaktifkan/memulihkan akses modul dengan alasan, konfirmasi dan audit. Jika reset diperlukan, buat reset terbatas aktivitas/pengguna, pertahankan snapshot/riwayat lama dan jangan menghapus pembayaran/reward atau mengizinkan hadiah ulang. Tahap pertama monitoring read-only boleh diserahkan dahulu; reset bukan prasyarat laporan berfungsi.
6. **Privasi latihan keluarga:** ringkasan operasional tidak otomatis membuka isi soal privat dan jawaban aktif. Akses dukungan terperinci memerlukan aksi eksplisit/alasan/audit, bukan bypass seluruh data anak. Analitik trafik tidak boleh menjadi jalan belakang membaca soal/jawaban.

### Pusat pengaturan yang wajib dibuat

Pengaturan harus mempunyai formulir nyata dengan nilai tersimpan, validasi server, pesan hasil, audit perubahan dan batas akses Superadmin; bukan kumpulan kartu placeholder. Terapkan per modul ketika backend-nya tersedia:

- **Umum:** nama Project Pintar, logo, kontak dukungan dan informasi situs. URL/TLS/secret environment tidak bebas diedit lewat UI sehingga situs tidak terkunci atau diarahkan ke domain sembarang.
- **Pembayaran:** pengaturan Midtrans sandbox/live serta tes koneksi sebagaimana bagian 8, produk top up, nominal/kredit, harga/masa paket, Platinum, slot anak, status order/rekonsiliasi. Kunci disamarkan, tidak dapat ditampilkan kembali sembarangan.
- **Akun/keluarga:** Google SSO, SMTP/email verifikasi, WA bila dipilih, batas dua anak dan harga slot; tampilkan kesiapan integrasi, bukan toggle aktif yang diam-diam tidak bekerja.
- **Pembelajaran:** jenjang/mata pelajaran, nilai tuntas, percobaan, pembahasan, kuota gratis, aturan reward/penukaran, kapasitas/jadwal lomba. Perubahan aturan tidak menghitung ulang hasil atau order historis diam-diam.
- **Privasi/operasional:** teks kebijakan dan versi, retensi yang disetujui, preferensi analitik, moderasi, feature flag dan iklan default mati untuk anak. Pengaturan retensi bukan tombol hapus massal tanpa preview/konfirmasi/prosedur backup.

### Trafik: tiga tab dan definisi data

Bangun analitik first-party sederhana dahulu; tidak perlu layanan berbayar atau pelacak pihak ketiga. Tidak ada histori trafik aplikasi sebelum pencatatan diaktifkan: tampilkan tanggal mulai pencatatan dan cakupan, bukan mengarang data lama atau menyamakan audit login dengan page view.

| Tab | Isi wajib |
| --- | --- |
| Ringkasan | Grafik harian page view, sesi kunjungan, perkiraan pengunjung unik, akun login unik; perbandingan periode hanya jika data tersedia |
| Per Halaman | Judul/route kanonis, jumlah view, sesi/pengunjung unik, akun login unik, kunjungan terakhir; klik detail untuk tren dan daftar aktivitas yang boleh dibaca |
| Per Pengguna | Akun terautentikasi: identitas minimum, sesi/view, halaman berbeda, aktivitas terakhir; detail berisi urutan waktu dan halaman bertab dengan tautan ke progres belajar. Tamu tampil sebagai agregat anonim, bukan daftar orang yang ditebak identitasnya |

Filter bersama: hari ini/7/30 hari/rentang khusus, halaman/modul, tamu/login dan jenis akun/jenjang bila memang diketahui. Waktu disimpan UTC, ditampilkan WIB; rentang tanggal diterjemahkan konsisten. Filter/sort/page bertahan saat pindah tab dan kembali dari detail, tanpa kontak/token/identitas pencarian sensitif pada URL. Tabel responsif, pencarian aman, pagination serta batas ekspor mengikuti standar proyek.

Kontrak pencatatan awal:

- Hitung page view navigasi dokumen GET halaman yang berhasil, sekali per request; bukan request aset, HEAD, AJAX autosave, callback pembayaran atau POST form. Refresh adalah view baru. Pisahkan login/gagal/404 pada statistik operasional jika diperlukan, bukan dicampur dengan view konten sukses.
- Sesi analitik usulan berakhir setelah 30 menit tanpa aktivitas; bukan sesi autentikasi. Pengunjung unik memakai ID analitik acak berpseudonim dengan masa berlaku terbatas, bukan cookie session login/IP/browser fingerprint. Pembatasan izin/preferensi privasi diterapkan; jika tidak boleh memakai ID, catat agregat view saja dan tampilkan metrik unik tidak tersedia. Angka unik adalah perkiraan perangkat/browser, bukan jumlah manusia pasti.
- Hubungkan user_id hanya dari sesi login server, bukan parameter browser. Akun berbagi perangkat tidak boleh menyatu, aktivitas tamu sebelum login tidak otomatis digabung ke riwayat anak. Logout menghentikan atribusi akun sebelumnya. Jangan menjanjikan identitas tamu anonim dapat diketahui.
- Simpan route yang sudah dinormalisasi dan ID konten allowlist, bukan URL/query mentah, judul privat, token undangan/reset/OAuth, isi POST, soal, jawaban, email, nomor WA atau referrer lengkap. Bila sumber kunjungan diperlukan, hanya domain/label yang diizinkan. IP mentah bukan field analitik yang ditampilkan.
- Pisahkan trafik pengelola/test dari trafik peserta secara default; saring bot yang dikenali dan nyatakan penyaringan tidak sempurna. Tidak memakai session replay/rekaman layar/keystroke atau pelacakan lintas situs. Untuk anak gunakan data minimum dan kebijakan/persetujuan yang sesuai sebelum aktivasi.
- Usulan retensi event rinci 30 hari, agregat harian tanpa identitas 12 bulan, tunduk persetujuan kebijakan. Ini terpisah dari log keamanan 90 hari di bagian 10. Penghapusan akun harus memutus/menghapus riwayat identitas sesuai kebijakan tanpa merusak angka agregat anonim.
- Tidak mengklaim durasi belajar dari selisih dua view. Metrik waktu aktif/online bukan MVP; jika ditambahkan, definisikan heartbeat, tab tak aktif dan label perkiraan terlebih dahulu.
- Tambah migration/index khusus waktu/route/akun, agregasi terjadwal dan pembatasan rentang hasil. Kegagalan pencatatan tidak boleh menggagalkan belajar/pembayaran; laporkan gangguan serta periode data hilang, jangan menampilkan nol seolah benar. Hindari proses batch berat setiap page view.

### Urutan implementasi dan bukti penerimaan

- [ ] B1. Shell sidebar admin terpisah, seluruh halaman pengelola yang sudah ada terhubung, menu aktif benar; desktop/ponsel dan penolakan akun biasa diuji.
- [ ] B2. Monitoring lintas pengguna/modul dan detail bertab, memakai data existing; satu dataset kecil dengan hasil diketahui, tanpa membuat konten kurikulum besar.
- [ ] B3. Pusat pengaturan umum/akun/aturan belajar; bagian Midtrans/paket selesai bersama tahap C, bukan hanya menambah menu kosong.
- [ ] B4. Trafik Ringkasan/Per Halaman/Per Pengguna, detail dan preferensi/retensi; aktifkan pencatatan hanya setelah kontrak privasi dan definisi metrik siap.
- [ ] Uji terarah: dua akun + tamu + admin, kunjungan halaman/refresh/aset/AJAX/POST, login-logout pada perangkat sama; hitungan tabel cocok dengan event yang diharapkan, identitas tidak bocor dan URL sensitif tidak tersimpan. Uji izin akses langsung/filter ID pengguna serta kegagalan pencatatan tidak mengganggu alur utama.
- [ ] Pemilik mencoba satu alur lengkap: pilih anak → lihat progres per modul → buka aktivitas → lihat trafik akun; buka pengaturan pembayaran dan simpan sandbox dengan hasil validasi yang jelas.

Pembaruan ini tidak mengaktifkan goal lokal kembali. Implementasi tetap di server setelah instruksi melanjutkan. Lakukan satu irisan yang dapat dicoba, bukan seluruh kombinasi pengujian historis; patuhi bagian 13.

## 4. Keputusan pemilik yang sudah jelas

1. Domain www.projectpintar.com, HTTPS untuk publik.
2. Sasaran pertama TK, SD, SMP. Materi dan paket soal sesuai kurikulum dikerjakan terpisah nanti; jangan menghabiskan tahap ini untuk menghasilkan kurikulum atau ratusan soal. Pakai sedikit data demo orisinal berlabel demo di staging bila perlu.
3. Google SSO minimal; utamakan opsi tanpa biaya login per pengguna. Verifikasi kontak wajib untuk akun dewasa/orang tua. Tidak mengklaim kontak terverifikasi sebagai identitas resmi/KYC.
4. Dua anak termasuk dalam akun orang tua. Anak tambahan memerlukan pembelian slot; contoh Rp10.000 per slot. Batas ini harus ditegakkan server, bukan hanya tombol UI.
5. Pembayaran QRIS melalui akun Midtrans yang juga dipakai Finance; pengaturan merchant dibuat di panel seperti self order Finance. Pembayaran yang sah otomatis memberi kredit/hak, tidak menunggu persetujuan manual.
6. Kredit top up terjangkau; contoh Rp20.000. Ada Platinum all-in contoh Rp300.000, mencakup semua paket termasuk yang baru. Durasi, pemilik akses dan rincian harga final masih usulan di bagian berikut.
7. Poin hasil belajar bisa dikumpulkan untuk membuka paket terkunci. Jangan menolak kebutuhan ini karena dokumen lama menyebut poin bukan uang: implementasikan penukaran terpisah, bukan pencampuran saldo.
8. Pengguna dapat membuat soal, battle dan lomba gratis untuk banyak peserta. Iklan diharapkan menjadi pendapatan, tetapi kelayakan iklan/anak dan kapasitas perlu diuji sebelum janji publik.
9. Pola kerja harus lebih hemat waktu/token, berorientasi fitur selesai, bukan terus memperluas alat pengujian.

## 5. Usulan harga, akses dan poin — belum harga live

Berikut default rancangan yang disarankan agar bisa diprototipe. Tandai sebagai draft di pengaturan, nonaktifkan penjualan live sampai pemilik menyetujui durasi dan cakupan. Jangan membuat janji seumur hidup dari kata all-in.

| Produk | Usulan | Hak yang didapat |
| --- | --- | --- |
| Top up | Rp20.000 → 200 kredit, tanpa bonus awal | Saldo pembelian milik orang tua; bukan poin prestasi |
| Paket biasa | 40–100 kredit | Akses 12 bulan untuk satu anak yang dipilih; top up contoh membuka 2–5 paket tergantung harga |
| Platinum keluarga | Rp300.000 / 12 bulan, tanpa auto-renew | Semua paket belajar resmi sekarang dan baru selama aktif, untuk dua anak dan slot tambahan berbayar pada keluarga yang sama |
| Slot anak tambahan | Rp10.000 sekali bayar per slot | Kapasitas profil tambahan, bukan otomatis paket belajar di luar Platinum aktif |
| Penukaran poin | Contoh 500 poin belajar → satu paket kecil yang ditandai layak | Hak belajar anak pemilik poin selama 12 bulan, tanpa mengubah saldo kredit |

Platinum berakhir: progres/riwayat tidak dihapus; hak individual yang masih aktif tetap berlaku, akses lain terkunci dengan penjelasan. Pembelian paket lama tidak otomatis diuangkan ulang ketika upgrade. Kredit usulan tidak kedaluwarsa pada MVP, tidak ditransfer/diuangkan; aturan refund dan pencatatan harus diumumkan sebelum bayar. Harga yang sudah dibayar disimpan snapshot, perubahan harga tidak mengubah order lama. Angka di atas belum analisis keuntungan/biaya merchant.

Slot keluarga disimpan sebagai kapasitas 2 + slot berbayar. Nonaktifkan anak membebaskan kapasitas aktif tanpa menghapus sejarah, tetapi pergantian/tautan antar keluarga harus diaudit dan tidak boleh menggandakan hak paket. Dua permintaan membuat anak bersamaan tidak boleh melewati kuota. Jangan menonaktifkan akun anak lama otomatis saat menambahkan aturan baru; laporkan bila melebihi kapasitas.

Pisahkan tiga hal: kredit berbayar, poin belajar yang dapat ditukar, dan XP/lencana kosmetik bila diperlukan. Poin tersimpan per anak, tidak boleh membeli slot anak, top up, Platinum atau ditukar uang. Semua hadiah dihitung server, unik per anak/aktivitas/versi reward; pengiriman ulang tidak menambah hadiah. Usulan +10 poin untuk pertama kali tuntas sesuai kriteria, maksimal 30 poin reward per hari; pengulangan boleh tanpa hadiah tambahan. Tentukan harga poin per paket di panel, tampilkan syarat sebelum menukar, debit dan hak akses satu transaksi. Soal privat/battle buatan pengguna tidak menghasilkan poin penukaran pada versi pertama agar tidak bisa membuat soal mudah untuk mencetak poin. Tidak ada hadiah karena menonton/mengklik iklan.

## 6. Aturan belajar yang disarankan

- TK: aktivitas pendek 5–10 menit, visual/audio opsional, tanpa timer wajib; pesan "sudah mencoba/perlu latihan lagi", jangan memberi label anak gagal. Ketuntasan aktivitas berdasarkan interaksi yang dirancang, bukan hanya membuka halaman.
- SD/SMP: kuis formatif default tuntas 80%, tanpa penalti jawaban salah, boleh mengulang; soal latihan bukan sertifikasi resmi. Nilai ambang, jumlah soal dan durasi bisa diatur per paket oleh pemilik.
- Simpan seluruh attempt dan tampilkan nilai terakhir/terbaik dengan label jelas. Pembahasan setelah final; jawaban/kunci tidak dikirim sebelum final. Ulang tidak mengubah histori atau menghasilkan reward ganda.
- Kelulusan kursus usulan: seluruh aktivitas wajib selesai dan kuis wajib mencapai ambang. Membaca selesai bukan bukti penguasaan; progres baca dan nilai dipisah.
- Lomba memakai snapshot soal dan waktu server, satu attempt per peserta secara default, aturan nilai/seri diumumkan sebelum mulai. Hadiah uang, biaya masuk, dan sertifikat resmi tidak masuk MVP.

## 7. Google SSO dan verifikasi kontak

Gunakan Google Identity Services/OpenID Connect untuk **orang tua/dewasa**, bukan mewajibkan anak punya Google/email/nomor WA. Anak masuk melalui akun yang dibuat orang tua terverifikasi dan persetujuan orang tua. Pertahankan login lokal untuk pemilik/fallback.

Google SSO bukan jaminan satu orang hanya satu akun. Validasi ID token di server melalui library terawat: signature, issuer, audience, expiry, nonce/CSRF sesuai flow; gunakan `sub` sebagai identitas provider, jangan mempercayai email dari browser. Jangan menyambungkan akun lama hanya karena email sama tanpa autentikasi/konfirmasi yang sah. Untuk email pihak ketiga pada akun Google, pertimbangkan verifikasi email tambahan sesuai panduan Google. SSO tidak membutuhkan gateway SMS; biaya hosting/email dan kebijakan penyedia tetap terpisah. Jangan menyatakan WhatsApp OTP gratis tanpa mengecek tarif provider.

Default MVP: Google SSO + daftar email/password dengan tautan verifikasi sekali pakai (usulan 30 menit) + lupa sandi sekali pakai (15 menit). Token acak disimpan hash, rate-limit kirim ulang/per akun/per IP, respons tidak membocorkan keberadaan akun, honeypot/CAPTCHA saat risiko meningkat. Jangan mengunci semua keluarga di jaringan sekolah dengan batas IP terlalu keras. SMTP/domain email, SPF/DKIM/DMARC dan OAuth client memerlukan konfigurasi pemilik. Jangan menyalakan pendaftaran publik sebelum pengiriman dan pemulihan benar-benar diuji.

WhatsApp OTP opsi tahap berikut melalui layanan resmi, ada kemungkinan biaya per pesan/provider. Tanpa scraping WhatsApp Web atau gateway tak resmi. Verifikasi kontak bukan KYC identitas; jangan meminta KTP/selfie anak. Jika kelak perlu KYC formal, definisikan kebutuhan, dasar pemrosesan dan vendor secara terpisah.

## 8. Midtrans: adopsi Finance tanpa mengganggunya

Di server juga tersedia `finance` dan `pustaka`. Temukan path aktual; baca hanya bagian relevan. Referensi lokal yang ditemukan: Finance `application/controllers/Pos.php`, `Master.php`, `Pos_mobile.php`, `application/views/pos/self_order_settings.php`; Pustaka `Quiz_bank.php`, `Quiz_play.php`, `Quiz_sessions.php`, `Play_game.php` dan `views/game/latihan_list.php`. Nama file adalah titik awal inspeksi, bukan bukti implementasi siap salin. Jangan menyalin database anggota, rahasia, atau mengubah proyek sumber.

Panel Superadmin: mode sandbox/live, merchant/client key/server key, status QRIS, callback URL hanya-baca, tombol uji koneksi. Server key hanya server-side, terenkripsi dengan master key di luar database, dimasking saat baca, blank saat edit berarti tidak berubah; perubahan konfigurasi diaudit tanpa nilai secret. Catat konfigurasi versi pada order agar rotasi key tidak memutus order pending. Jangan menyalin secret ke Markdown/log/JavaScript.

Gunakan namespace order unik Project Pintar agar tidak bentrok dengan Finance. **Akun merchant bersama dapat mempunyai callback global yang dipakai Finance:** inspeksi pola existing dan dukungan override notifikasi per transaksi sebelum mengubah apa pun. Jangan menimpa callback merchant global tanpa rencana routing dua aplikasi dan persetujuan. Akun sama bukan database/wallet sama.

Alur: orang tua pilih top up → server menetapkan Rupiah/kredit dan order unik → tampil QRIS → status menunggu → notifikasi diverifikasi server → status settlement valid, order/nominal/metode/environment cocok → ledger kredit tercatat sekali → UI menampilkan saldo terbaru. Jangan kreditkan hanya karena redirect sukses/screenshot/polling browser. QRIS sukses menggunakan settlement sesuai API yang dipilih; jangan mencampur skema notifikasi SNAP dan Core API.

Callback duplikat/urutan terbalik, timeout dan pembayaran terlambat wajib aman. Terapkan unique reference, transaksi/lock dan idempotensi; untuk pembelian paket, debit dan entitlement atomik. Sediakan cek status server serta rekonsiliasi order pending. Refund memakai mutasi kompensasi, bukan hapus ledger; kredit yang sudah dipakai perlu pemeriksaan manual, bukan memaksa saldo negatif. Sandbox dahulu; live hanya setelah konfigurasi, harga dan alur berhasil disetujui.

## 9. Battle/lomba gratis dan iklan

Masukkan kembali battle ke rencana aktif produk, tetapi bertahap. Versi pertama: penyelenggara dewasa/orang tua terverifikasi membuat soal pilihan ganda, menjadwalkan lomba, membagikan kode/tautan privat, peserta bergabung, server menilai, hasil memakai alias. Anak bisa ikut dengan pengelolaan orang tua; pembuatan/publikasi oleh anak melalui draf privat yang disetujui orang tua, bukan unggah publik tanpa pengawasan.

Mulai model turnamen terjadwal/asinkron; battle live serentak sebagai langkah berikut, bukan dilupakan. Gratis dengan kuota teknis yang terlihat (usulan awal 50 peserta/ruang, 30 soal, 3 ruang aktif per penyelenggara); naikkan setelah uji beban. Jangan menjanjikan peserta tak terbatas atau live ribuan orang tanpa bukti kapasitas.

Pisahkan soal komunitas dari paket kurikulum resmi. Tambahkan pratinjau, sumber/hak soal, laporkan konten, antrean moderasi untuk daftar publik, suspend, pembatalan/arsip dan audit. Nilai dan deadline dari server, snapshot saat publish, perlindungan join/replay, tidak mengekspos kunci. Tanpa chat bebas, kontak peserta, pencarian nama anak, atau leaderboard anak publik default. Penyelenggara hanya melihat data minimum untuk lomba.

Monetisasi penerbit yang dimaksud adalah **Google AdSense**, bukan Google Ads untuk membeli iklan. Penerimaan situs dan pendapatan tidak dijamin. Usulan awal tanpa iklan pada akun anak, formulir login/pembayaran dan saat mengerjakan soal; pertimbangkan hanya halaman informasi dewasa setelah kebijakan cocok. Jangan mendorong klik iklan, menjadikan iklan syarat belajar, atau memakai iklan personal untuk anak. Google mendokumentasikan pembaruan penandaan age-restricted; verifikasi dokumentasi terkini saat integrasi, jangan salin flag lama tanpa pemeriksaan.

## 10. Usulan privasi dan layanan

Kumpulkan minimum: kontak orang tua terverifikasi, alias anak, kelompok usia/kelas dan progres yang diperlukan. Jangan minta alamat lengkap, sekolah, KTP, lokasi tepat atau tanggal lahir lengkap bila kelompok usia cukup. Rekam persetujuan orang tua dan versi kebijakan; tampilkan penjelasan pendek yang dimengerti anak. Jangan menjual data atau memasang pelacak pemasaran di halaman anak.

Sediakan permintaan akses/koreksi/penutupan akun, pencabutan tautan, kontak keluhan dan pelaporan soal. Usulan awal retensi: token sesuai masa berlaku; akun belum diverifikasi 7 hari; log keamanan 90 hari; data belajar selama akun aktif, penghapusan setelah permintaan terverifikasi dengan tenggat operasional 30 hari. **Ini usulan, bukan kebijakan live/pendapat hukum:** retensi transaksi, backup, sengketa dan kewajiban hukum perlu review tersendiri sebelum otomatis menghapus. Jangan menjanjikan semua transaksi langsung hilang ketika akun dihapus.

Syarat pembelian harus menjelaskan masa akses, siapa penerima, harga akhir/biaya, kredit versus poin, cakupan Platinum paket baru, refund, gangguan layanan dan penutupan layanan. Usulan penanganan: pembayaran ganda atau hak tidak terbit diperiksa/dikoreksi; refund konten yang sudah digunakan tidak otomatis, tetap mengikuti ketentuan yang berlaku dan mekanisme keluhan. Review UU PDP serta ketentuan perlindungan anak di sistem elektronik, termasuk PP 17/2025; jangan mengklaim patuh hanya karena checkbox persetujuan tersedia.

## 11. Urutan migrasi yang aman

1. Inventaris server: root aplikasi, versi PHP CLI/FPM dan ekstensi mysqli/mbstring/openssl/curl/fileinfo/GD, versi MySQL/MariaDB, user pool, DNS/TLS, Nginx dan lokasi sumber Finance/Pustaka. Jangan ubah service/database proyek lain.
2. Cadangkan source lengkap, DB `db_study` dan media privat sebagai satu keadaan konsisten. Sumber media lokal default `C:\xampp\study-private\lesson-images`; lokasi server contoh `/var/lib/study/lesson-images`, di luar webroot dan induk webroot sesuai guard. Jangan menganggap dump DB mencakup gambar. Jangan menyalin sesi aktif/cache/log/credential notes ke publik.
3. Buat staging tertutup, user DB aplikasi hak minimum terpisah dari migrator; restore tanpa menimpa DB yang sudah ada. Periksa versi 13/33 tabel dan akun uji. Bila DB kosong pakai migration existing, jangan membuat skema kedua dari nol. Backup sebelum `php index.php cli/migrate`; baca config migration dan environment terlebih dahulu. Jangan menjalankan rollback destruktif untuk menyamakan versi.
4. Pasang environment server: APP_BASE_URL=`https://www.projectpintar.com/` untuk produksi (staging memakai URL staging sendiri), APP_COOKIE_SECURE=1, APP_ENCRYPTION_KEY rahasia, DB_HOST/DB_USERNAME/DB_PASSWORD/DB_DATABASE=`db_study`, STUDY_MEDIA_ROOT absolut privat, CI_ENV sesuai lingkungan. Tidak mengubah base URL localhost di source menjadi hardcoded domain. Jangan menyalin database.local.php Windows ke produksi. Kunci lama diperlukan untuk data yang benar-benar dienkripsi; inventaris sebelum rotasi. Sesi lama tidak perlu dimigrasi.
5. Adaptasi `docs/nginx-study.example.conf` pada vhost aaPanel aktual; socket FPM, sertifikat dan root adalah placeholder. Pertahankan jalur `index.php/...` sampai rewrite diuji. Nginx tidak memakai .htaccess: wajib menolak docs/scripts/application/system/dump/secret/source privat. Atur redirect domain tanpa www dan HTTP ke HTTPS kanonis tanpa merusak POST callback. Jangan mengklaim domain sudah aktif dari isi dokumen.
6. Uji singkat: beranda/login, admin, satu kursus/materi/latihan, progres orang tua-anak, upload valid/invalid, dan GET file privat ditolak. Verifikasi DB/materi privat berpasangan. Uji sandbox pembayaran setelah fiturnya ada, bukan sebelum.
7. Rotasi sandi admin uji, set kontak pemilik dan verifikasi, pastikan pendaftaran publik belum terbuka sebelum siap. Buat backup/monitoring/error log dan rencana rollback. Tidak menjalankan skrip QA berfixture pada database produksi; sejumlah skrip lama mengasumsikan db_study loopback/Windows dan belum kompatibel Linux. Gunakan DB staging terisolasi dan adaptasi guard dengan aman.
8. Deployment publik/pembayaran live hanya setelah hasil staging, keputusan bisnis dan persetujuan pemilik. Jangan menghentikan Apache/MySQL bersama di Windows atau Finance/Pustaka di server. "Stop" pada handover ini berarti pengembangan otomatis dijeda; layanan bersama tidak dimatikan.

## 12. Urutan pengembangan setelah migrasi

- [x] Keputusan baru dan usulan bisnis dicatat; goal lokal dijeda.
- [x] Akun admin uji lokal dibuat, login/panel/logout diuji; sandi dipisah dari handover.
- [ ] A. Staging server berfungsi, akses privat dan backup terverifikasi, kredensial admin dirotasi.
- [ ] B. Panel Superadmin sidebar lengkap, monitoring pengguna/modul, pusat pengaturan dan trafik tiga tab sesuai bagian 3A; operator tunggal, Google SSO/email verification/reset, persetujuan orang tua dan kuota dua anak. Kerjakan sublangkah B1–B4 bertahap agar pemilik dapat mencoba hasilnya.
- [ ] C. Pengaturan Midtrans, top up QRIS dan ledger, harga draft, pembelian paket, Platinum serta slot anak. Semua diuji sandbox, callback Finance tetap aman.
- [ ] D. Reward poin dan penukaran paket, aturan ketuntasan per jenjang; materi kurikulum tetap pekerjaan terpisah.
- [ ] E. Battle/lomba gratis privat, pembuatan soal, moderasi, batas kapasitas; lalu live battle dan pertimbangan AdSense.
- [ ] F. Uji penerimaan pemilik dengan sedikit konten demo, review privasi/harga, pilot tertutup, baru publik.

Setiap tahap menghasilkan alur yang dapat dicoba pemilik, bukan hanya library atau alat penguji. Jangan menunggu semua fitur canggih sebelum menyerahkan alur utama yang berfungsi.

## 13. Pola kerja hemat waktu/token (wajib)

- Kerjakan satu irisan fitur sampai dapat dicoba; laporkan perubahan, URL uji, hasil dan hambatan dalam beberapa poin. Jangan membuat subagen tanpa permintaan pemilik.
- Cari/read bagian source relevan, jangan memuat seluruh docs/log atau mengulang discovery. Reuse komponen serta tes yang ada; jangan membuat framework QA baru untuk perubahan sederhana.
- Dokumentasi saja: cek tautan/konflik dan tinjau isi; tidak perlu lint seluruh PHP, browser tiga lebar atau tes DB. UI sederhana: cek halaman terdampak desktop + ponsel dan satu keyboard path, bukan seluruh variasi tema.
- Perubahan bisnis biasa: lint file berubah + tes terarah sukses/gagal + satu alur nyata. Batch regresi luas saat milestone/release, bukan setelah setiap paragraf/label.
- **Tetap wajib untuk uang/akun/anak:** izin server, CSRF/validasi, isolasi keluarga, transaksi atomik, duplikasi/replay, nominal salah, callback palsu, saldo tidak negatif, token kedaluwarsa dan kegagalan simpan. Tidak boleh dihapus demi penghematan.
- Setelah satu run relevan lulus, lanjut fitur berikutnya; ulang hanya jika kode/dependensi terkait berubah atau ada kegagalan yang perlu dibuktikan. Tidak perlu mengulang PHP 8.0/8.1/8.5 setiap edit; pakai versi target yang disetujui di server, matriks lain ketika perubahan kompatibilitas/rilis.
- Jangan menghabiskan siklus untuk semua kombinasi gangguan hipotetis pada fitur yang sudah cukup diuji sambil pembayaran/onboarding belum dibuat. Prioritaskan risiko nyata dan jalur utama, catat risiko kecil sebagai backlog dengan jelas.
- Jika keputusan/akses/kredensial benar-benar menghalangi, minta satu rangkuman pertanyaan dan berhenti pada blocker; jangan mengisi waktu dengan pekerjaan tambahan tak diminta. Jangan menjanjikan "tanpa bug"; berikan bukti pengujian yang sebanding risiko.
- Checklist hanya berdasarkan bukti. Jangan memperbarui goal menjadi complete karena satu bagian selesai. Di server mulailah tugas terbatas A, lalu minta arahan untuk tahap berikut bila instruksi pemilik belum mencakupnya.

## 14. Referensi resmi untuk keputusan integrasi

Ditinjau 16 Oktober 2026; cek perubahan saat implementasi. Rekomendasi produk/harga/retensi di atas adalah usulan, bukan kutipan vendor atau nasihat hukum.

- [Google Identity Services](https://developers.google.com/identity/gsi/web/guides/overview) dan [validasi ID token server](https://developers.google.com/identity/gsi/web/guides/verify-google-id-token): identitas provider, verifikasi token/email.
- [Midtrans webhook](https://docs.midtrans.com/docs/https-notification-webhooks) dan [QRIS](https://docs.midtrans.com/reference/qris): notifikasi dan status pembayaran, bukan mempercayai browser.
- [WhatsApp pricing resmi](https://developers.facebook.com/docs/whatsapp/pricing): halaman tidak dapat dimuat alat saat handover; tarif/kuota belum diverifikasi, jangan menjanjikan OTP gratis.
- [Google AdSense age-restricted treatment](https://support.google.com/adsense/answer/9007197?hl=en): pembatasan dan penandaan iklan harus diperiksa ulang, bukan menggunakan aturan lama secara otomatis.
- [UU 27/2022 Pelindungan Data Pribadi](https://jdih.komdigi.go.id/produk_hukum/view/id/832/t/undangundang%2Bnomor%2B27%2Btahun%2B2022?search=data+status) dan [PP 17/2025](https://peraturan.bpk.go.id/Download/377994/PP%20Nomor%2017%20Tahun%202025.pdf): acuan review pemrosesan data anak dan layanan elektronik; review profesional sebelum publik.

## Prompt singkat yang diberikan pemilik ke agen server

> Baca docs/TASK_HANDOVER_SERVER.md pada proyek Study/Project Pintar. Jangan membangun ulang. Validasi keadaan server dan proyek, lalu kerjakan tahap A migrasi/staging dengan hati-hati tanpa mengganggu Finance/Pustaka. Gunakan domain www.projectpintar.com, database db_study, keputusan produk dan pola kerja hemat di dokumen. Jangan mengaktifkan pembayaran live, menganggap harga draft final, atau mempublikasikan kredensial. Laporkan URL uji, hasil dan blocker singkat; perbarui checklist berdasarkan bukti. Materi kurikulum dikerjakan terpisah nanti.
