# Kepastian penyimpanan dan pemulihan latihan

Status pencatatan: 16 Oktober 2026. Perubahan lokal, bukan persetujuan rilis atau pengaktifan pembayaran.

## Temuan dan perbaikan

Probe RED sebelum perbaikan membuktikan jalur commit Practice dan mulai Assignment dapat mengembalikan `ok=true` ketika `trans_status()` benar tetapi `trans_commit()` mengembalikan FALSE. Ini bukan klaim bahwa setiap query gagal sebelumnya disimpan: CI3 biasanya menandai transaksi gagal dan jalur lama memeriksa status tersebut. Celahnya adalah kepastian COMMIT dan penanganan hasil/exception lain yang belum konsisten.

`Study_transaction` kini memiliki transaksi terluar, menolak transaksi bertingkat tanpa mengambil alih milik pemanggil, memeriksa BEGIN/status/COMMIT/ROLLBACK dan memulihkan `db_debug`. Practice mulai/simpan/final serta Assignment mulai memeriksa hasil baca/tulis/snapshot/audit dan berhenti pada kegagalan pertama. Penolakan domain dipertahankan setelah rollback terkonfirmasi; kegagalan layanan dan hasil COMMIT/ROLLBACK yang belum pasti dibedakan. Replay tetap memakai status operasi yang benar. Pemeriksaan akses mutasi dan pembacaan attempt keluarga memakai current read agar tidak bergantung pada snapshot sebelum menunggu lock.

Pada kegagalan transaksi, tiga POST latihan mengirim 503 tanpa membaca ulang DB, menampilkan form latihan/nilai, atau membawa flash lama. View memberi dua tautan GET untuk memeriksa keadaan terbaru dan riwayat; tidak ada retry POST otomatis. Form keluar milik layout sesi masuk tetap ada. Sukses memakai 303 dan hasil operasi yang sudah dikonfirmasi, tanpa query tambahan sesudah COMMIT. Tidak menambah route, skema, timer, autosave, harga atau kebijakan nilai.

Komponen, Bootstrap lokal dan token tema bersama dipertahankan mengikuti panduan Sites untuk checkout lokal. Tidak ada publikasi atau sinkronisasi hosting.

## Bukti terisolasi

Seluruh unit berikut lulus pada PHP CLI 8.0.30, 8.1.34 dan kandidat 8.5: **2.022 assertion per runtime**, **6.066 total**, 21 invocation dengan exit 0.

| Unit di `scripts` | Cakupan/angka per runtime |
| --- | --- |
| `test-study-transaction.php` | 318 assertion: ownership, begin/status/commit/rollback FALSE/exception, hasil malformed/domain, restore debug dan tidak retry. |
| `test-course-access-lock.php` | 46: lock hanya bila boolean TRUE, binding/predikat hak tetap, query gagal generik; bukan bukti isolasi paralel. |
| `test-practice-transactions.php` | 859, termasuk 168 injeksi matriks kegagalan baca/tulis/audit/snapshot serta kontrol JSON malformed dan kontrak status/replay terpisah. |
| `test-assignment-transactions.php` | 275: mulai keluarga, locking read, kegagalan transaksi dan replay. |
| `test-practice-recovery-controller.php` | 244/40 kasus: 503 berhenti tanpa query/render form lanjutan; path tetap, sukses 303/status tanpa reread. |
| `test-practice-recovery-view.php` | 199: tujuh render recovery dan 15 kasus `MY_Controller::render_page` nyata, lima peran × flag flash. |
| `test-practice-read-access.php` | 81 regresi akses baca dan status attempt. |

## Database dan regresi HTTP

`practice-check.ps1` lalu `family-delivery-check.ps1` dijalankan satu per satu pada Apache XAMPP PHP 8.0.30 dan MariaDB lokal. Masing-masing memanggil helper CLI PHP 8.1 hanya terhadap fixture run tersebut. Helper ditolak di HTTP/non-development/non-PHP-8.1/non-loopback/non-`db_study`; identitas anak, penulis/pembuat, tipe sesi, status dan kepemilikan soal diperiksa sebelum injeksi.

**54 skenario lulus:** 13 mulai kursus, 13 mulai keluarga, serta 14 simpan/final pada masing-masing konteks. Digest seluruh baris terurut di 19 tabel sama sebelum/sesudah setiap skenario; tepat satu titik kegagalan tercapai, tidak ada COMMIT nyata dan debug dipulihkan. Dua skenario rollback gagal pada masing-masing empat pemanggilan helper (**delapan total**) memerlukan rollback eksplisit oleh harness melalui koneksi asli—bukan keberhasilan rollback model. Kegagalan COMMIT disimulasikan sebelum pengiriman COMMIT nyata; auto-increment dapat maju walaupun baris dibatalkan. Ini bukan uji pemutusan koneksi nyata.

Kedua suite normal tetap lulus: authoring/review soal, snapshot, pilihan kosong/asing, simpan/final/replay, revisi usang, nilai 0/100/33,33%, CSRF/IDOR, akses dicabut, pagination, laporan keluarga, jadwal, pembatalan dan penyalinan sesi. Data sintetis dibersihkan oleh cleanup terikat identitas unik masing-masing suite. Tidak menghapus data pengguna atau baris login gagal lain.

Chrome 155.0.8059.79 memeriksa **45 kombinasi halaman terautentikasi/viewport**: sembilan halaman kursus/laporan dan enam halaman keluarga × 360/768/1280. Enam kombinasi pengerjaan mengulang pengaman pilihan belum tersimpan, dialog keluar native, POST usang 409 dan fallback tanpa JavaScript. Geometri, teks terpotong, heuristik aksesibilitas dan skip-link lulus; bukan audit aksesibilitas lengkap.

## Tampilan pemulihan

Renderer PHP memuat view produksi tanpa CI/DB/sesi. Harness anonymous menyisipkan hasilnya ke DOM dengan CSS produksi dan penanda **Fixture uji; bukan respons HTTP 503**. Dua keadaan × dua tema (dewasa/sekolah dasar) × tiga lebar = **12 kombinasi** lulus. Tepat dua tautan native GET, pesan berbeda, tanpa form/script, target minimal 44 px, 24 perhentian Tab dengan fokus terlihat, serta tidak ada teks terpotong atau overflow horizontal.

Tiga PNG komponen berikut sudah ditinjau manual:

| Gambar | Hasil |
| --- | --- |
| [Belum pasti, desktop dewasa](practice-recovery-uncertain-theme-adult-1280.png) | Pesan tidak menjanjikan data tersimpan/dibatalkan; dua aksi sejajar dan hierarki jelas. |
| [Belum pasti, ponsel anak](practice-recovery-uncertain-theme-child-primary-360.png) | Token hijau diwarisi, teks membungkus dan aksi turun tanpa terpotong. |
| [Belum berhasil, ponsel dewasa](practice-recovery-unavailable-theme-adult-360.png) | Pesan berbeda dari ketidakpastian, tautan riwayat tetap tersedia. |

Regresi publik beranda/katalog/masuk/daftar × tiga lebar = **12 kombinasi** juga lulus. Delapan PNG publik ikut tersimpan, tetapi tidak diklaim telah ditinjau satu per satu. Screenshot komponen tidak membuktikan respons HTTP 503 nyata, kegagalan DB jaringan, layout lengkap sesi masuk atau navigasi tautan; kontrak controller/view memiliki unit terpisah.

## Pemeriksaan akhir dan pembersihan

Regresi HTTP peran, progres baca, lanjut belajar dashboard dan status daftar isi lulus tanpa browser tambahan. Smoke/lint **351 PHP** pada ketiga runtime, timezone, migration latest, rute/aset/header, berkas privat serta empat URL helper CLI yang ditolak lewat HTTP lulus. Kedua aturan PHPCS seluruh scope, naming **174 PHP** pada tiga runtime, parser **40 PowerShell**, parser dua JavaScript berubah dan seluruh unit UI bersama lulus.

Pemeriksaan akhir dokumen **55 Markdown**, cakupan **54 route/48 kontrak**, konflik **552 berkas teks**, secret scan beserta fixture pemindainya, dan lisensi aset juga lulus. Pemeriksaan tautan/kontrak hanya memvalidasi inventaris, bukan perilaku endpoint.

Query akhir: migration **13**, **33 tabel**; hanya `st_roles` (4), `study_migrations` (1), dan `st_login_failures` (1, ID 3067 yang sudah ada sebelum run) berisi baris. Tiga puluh tabel lain kosong; skema `db_study_qa%` tidak ada. Folder log hanya `index.html` (131 byte). Data uji sementara dihapus oleh pemilik suite dan tidak dipertahankan; harness dapat membuat ulang fixture baru. Tidak menghapus catatan login gagal, media atau berkas sesi lain. Profil browser run ini dibersihkan harness; artefak/proses lama di luar run tidak disentuh.

Pemeriksaan format awal menemukan fungsi/fake test yang belum mengikuti indentasi/penamaan, lalu diperbaiki dan unit serta kedua PHPCS diulang. Guard fixture keluarga disesuaikan ke nilai skema `family_private` sebelum suite dijalankan. Semua suite database/HTTP/browser pada versi akhir lulus percobaan pertamanya; tidak mengabaikan assertion gagal. Bukti RED commit berasal dari sebelum implementasi, bukan kegagalan yang disembunyikan dari hasil akhir.

## Cara mengulang

Gunakan Apache lokal yang sudah berjalan. Jangan jalankan dua suite mutasi bersamaan karena probe membandingkan seluruh baris 19 tabel. Setiap run membuat fixture baru; helper CLI tidak boleh diarahkan ke data nyata.

```powershell
./scripts/practice-check.ps1 -Php81Path 'C:\tools\php81-study\php.exe' -MysqlPath 'C:\xampp\mysql\bin\mysql.exe' -BaseUrl 'http://localhost/study/index.php/' -ChromePath 'C:\Program Files\Google\Chrome\Application\chrome.exe'
./scripts/family-delivery-check.ps1 -Php81Path 'C:\tools\php81-study\php.exe' -MysqlPath 'C:\xampp\mysql\bin\mysql.exe' -BaseUrl 'http://localhost/study/index.php/' -ChromePath 'C:\Program Files\Google\Chrome\Application\chrome.exe'
node scripts/browser-viewport-check.mjs --base-url http://localhost/study/index.php/ --chrome 'C:\Program Files\Google\Chrome\Application\chrome.exe' --exercise-practice-recovery --php-path C:/tools/php81-study/php.exe
```

Untuk tangkapan baru, tambahkan `--capture-screenshots --screenshot-dir` menuju subfolder baseline baru; jangan timpa folder ini. `qa-local.ps1` sudah memasukkan unit, hooks dan komponen, tetapi orkestrator penuh tidak dijalankan pada putaran ini.

## Batas yang tetap terbuka

Belum membuktikan COMMIT diterima server lalu koneksi terputus, race paralel, beban, HTTP PHP 8.1/8.5, Linux/Nginx/aaPanel, native zoom, semua usia, pembaca layar nyata atau UAT. Pembacaan preflight akun/controller sebelum operasi model masih memakai penanganan lama. Log baru generik, tetapi logger SQL bawaan CI3 belum dinyatakan tersanitasi. Pembungkus baru tidak otomatis diterapkan pada authoring/kirim/salin/batal keluarga, game tersimpan, seluruh model, atau transaksi uang. Pemulihan offline dan temuan fokus zoom intermiten sebelumnya tetap terbuka. Tidak ada klaim seluruh Tahap 9/10/14 atau roadmap selesai.
