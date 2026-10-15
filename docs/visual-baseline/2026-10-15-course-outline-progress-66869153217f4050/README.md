# Progres pada daftar isi kursus

Status pencatatan: 16 Oktober 2026. Folder dan gambar mempertahankan tanggal 15 Oktober dari penamaan otomatis saat pengujian lokal; tidak mengganti bukti sebelumnya.

## Perubahan yang diperiksa

Daftar isi kursus menampilkan status pelajaran milik akun masuk serta tombol Mulai belajar, Lanjutkan, atau Baca ulang. Satu query terikat peserta/kursus membaca status pelajaran/kursus terbit; urutan tetap berasal dari indeks bagian/pelajaran. Orang tua di katalog melihat progres dirinya, bukan anak. Tamu tidak diberi status pribadi. Halaman terpersonalisasi mengirim private/no-store/noindex/nofollow.

Materi terkunci tetap tanpa tautan meskipun ada status tersimpan. Pratinjau dan hak baca lama tetap berlaku. Kegagalan membaca progres berbeda dari daftar kosong dan belum dimulai: pesan sopan dengan muat ulang GET serta aksi baca netral. Tidak ada skema, endpoint, aturan nilai, autosave, pembelian atau penulisan progres baru. Komponen, token tema dan tata letak responsif bersama dipertahankan mengikuti panduan Sites untuk repositori lokal; tidak ada publikasi atau sinkronisasi ke layanan hosting.

## Bukti pengujian

- Model: 82 assertion pada PHP CLI 8.0.30, 8.1.34 dan 8.5. Recording fake memeriksa satu SELECT dengan dua parameter, proyeksi status saja, publikasi/kepemilikan, 120 baris tanpa N+1, ID tidak sah, kegagalan query/exception/hasil malformed, diagnostik generik dan pemulihan `db_debug`. Ini bukan profiler query atau simulasi putus koneksi nyata.
- Controller: 118 assertion/24 kasus pada tiga runtime. Identitas sesi dan kursus hasil slug digunakan; query pengalih identitas diabaikan; parent-own, tamu, kosong/gagal, akses independen, guard GET/HEAD dan kursus tidak ditemukan diperiksa. Mock guard tidak menggantikan pengujian HTTP.
- View: 30 render pada tiga runtime. Semua status, akses/pratinjau/terkunci, guest dengan map keliru, gagal/kosong, fallback status tidak dikenal, escaping Unicode/URL, nama aksesibel penuh, urutan dan markup pembungkus diuji. Helper bersama juga lulus 25 assertion pada tiga runtime.
- Integrasi `course-outline-progress-check.ps1` lulus pada Apache XAMPP/PHP 8.0.30, MariaDB lokal `db_study`: empat akun sintetis, tautan keluarga aktif, enam kursus dan 11 pelajaran. Enam pelajaran terbit pada kursus utama mempunyai urutan bagian/ID seri; pelajaran draf/arsip dan kursus lain tidak tercampur. Progres dewasa/anak/orang tua berbeda; akun lain tanpa progres, parameter identitas scalar/array palsu, alias controller, Unicode/escaping, keadaan kosong dan 404 kursus nonpublik diperiksa.
- Akses: gratis, pratinjau, enrollment manual/free/paket aktif, entitlement tanpa tanggal akhir, kedaluwarsa, waktu akhir terlewati, belum mulai, dicabut, dan enrollment dicabut. Status sendiri tetap tampil pada baris terkunci tanpa memberikan tautan. Akun lain/anak/orang tua tanpa akses tidak menerima progres peserta pemilik fixture.
- GET/HEAD kursus tidak mengubah digest seluruh progres, enrollment, entitlement atau audit yang diperiksa; HEAD tanpa body. Browser dibandingkan dengan digest yang sama dan hanya satu audit login sintetis per akun/run dikecualikan. Ini tidak menyatakan seluruh tabel server atau berkas sesi tidak berubah.
- Chrome 155.0.8059.79: lima keadaan × 360/768/1280 px = **15 kombinasi** lulus pemeriksaan geometri, teks terpotong, pemeriksaan aksesibilitas terbatas, skip-link keyboard dan navigasi terbuka. Keadaan: dewasa/anak sekolah dasar/orang tua pada kursus utama, tamu pada kursus berbayar, dewasa dengan histori tetapi akses terkunci. Judul 80 huruf tanpa spasi dan judul bagian panjang tetap membungkus. Sepuluh PNG tersimpan; lima ditinjau di bawah.
- Regresi HTTP `progress-check.ps1` dan `dashboard-resume-check.ps1` lulus, mencakup posisi/simpan berurutan, status selesai terminal, navigasi materi berikutnya, pemisahan keluarga dan batas akses. Dashboard dijalankan tanpa browser tambahan pada putaran ini.
- Smoke/lint **341 PHP** pada tiga runtime, timezone, migration latest, rute/aset/header dan penolakan berkas privat lulus. Dua aturan PHPCS, penamaan 164 PHP, parser 40 PowerShell, unit JS bersama, pemeriksaan dokumen/kontrak, konflik dan secret scan lulus. `qa-local.ps1` sudah memanggil suite baru, tetapi **orkestrator penuh tidak dijalankan**.

Pemeriksaan format pertama menemukan lima fungsi test controller ditulis satu baris (20 laporan sniff yang tumpang tindih). Format diperbaiki; PHPCS dan test controller tiga runtime diulang dan lulus. Tidak ada perubahan perilaku produksi akibat perbaikan format tersebut. Suite HTTP/browser baru lulus pada percobaan pertama, bukan hasil mengabaikan assertion gagal.

## Tangkapan yang ditinjau

| Berkas | Hasil tinjauan |
| --- | --- |
| [Dewasa, desktop](outline-adult-main/outline-adult-main-1280.png) | Enam materi berurutan, tiga jenis status/aksi, teks uji di-escape, judul panjang membungkus dan aksi sejajar. |
| [Anak sekolah dasar, ponsel](outline-child-main/outline-child-main-360.png) | Palet hijau profil dipertahankan, status berbeda dari dewasa, aksi di bawah teks dan judul tanpa spasi membungkus. |
| [Orang tua, desktop](outline-parent-main/outline-parent-main-1280.png) | Status milik orang tua sendiri, bukan salinan progres anak; navigasi keluarga tetap ada. |
| [Tamu, ponsel](outline-guest-paid/outline-guest-paid-360.png) | Tidak ada status pribadi; pratinjau bisa dibaca dan materi lain terkunci. |
| [Histori terkunci, ponsel](outline-adult-locked/outline-adult-locked-360.png) | Pratinjau berjalan bisa dilanjutkan; materi selesai tetap terkunci tanpa tautan baca. |

Lima PNG lainnya dihasilkan untuk pasangan lebar 360/1280 masing-masing keadaan, tetapi tidak diklaim sudah ditinjau visual satu per satu. Tidak ada PNG 768. Semua judul/nama/progres pada gambar adalah fixture sintetis; teks `<b>synthetic</b>` sengaja menguji escaping, bukan konten produksi.

## Data dan pembersihan

Fixture hanya dibuat setelah pemeriksaan identitas unik kosong; cleanup dibatasi username/slug/penulis/paket yang dimiliki run, berikut dependensinya. Akun, konten, progres, enrollment/entitlement, keluarga dan audit sintetis telah dihapus dan jumlah sisanya diperiksa. Data uji sementara tersebut tidak dipertahankan; harness dapat membuat ulang fixture baru. Tidak menghapus baris kegagalan login lain atau mencari/menghapus berkas sesi server secara luas.

Pemeriksaan akhir setelah regresi: migration **13**, **33 tabel**. Hanya `st_roles` (4), `study_migrations` (1) dan `st_login_failures` (1, ID 3067 yang sudah ada sebelum run) berisi data; 30 tabel lain kosong. Tidak ada skema `db_study_qa%`. Folder log aplikasi hanya berisi `index.html` (131 byte). Profil browser sementara milik harness dibersihkan oleh harness; artefak/proses browser lama di luar run tidak disentuh.

## Cara mengulang dan batas

Pada XAMPP lokal yang sudah berjalan, gunakan PHP CLI 8.1 dan akun DB lokal yang disyaratkan harness. Skrip hanya menerima URL loopback dan tidak menyalakan server baru:

```powershell
./scripts/course-outline-progress-check.ps1 -Php81Path 'C:\tools\php81-study\php.exe' -MysqlPath 'C:\xampp\mysql\bin\mysql.exe' -BaseUrl 'http://localhost/study/index.php/' -ChromePath 'C:\Program Files\Google\Chrome\Application\chrome.exe' -CaptureScreenshots
```

`-ChromePath` dapat dihilangkan untuk integrasi HTTP saja; `-CaptureScreenshots` memerlukan Chrome. Setiap run memakai identitas dan folder baru. Unit terisolasi ada pada `test-course-outline-progress-model.php`, `test-course-outline-progress-controller.php`, dan `test-course-outline-progress-view.php` di `scripts`.

Belum membuktikan HTTP PHP 8.1/8.5, Linux/Nginx/aaPanel, PHP-FPM, pembayaran nyata, konten belajar resmi, beban/konkurensi, semua kelompok usia, zoom browser, pembaca layar nyata, atau UAT. Kegagalan query diuji melalui model/controller/view terisolasi, bukan mematikan DB. Tautan native diverifikasi lewat HTML/URL; putaran browser ini tidak mengklik setiap tautan materi atau menonaktifkan JavaScript. Pemulihan posisi baca memiliki regresi tersendiri; tidak ada klaim kelulusan seluruh Tahap 9 maupun keseluruhan roadmap.
