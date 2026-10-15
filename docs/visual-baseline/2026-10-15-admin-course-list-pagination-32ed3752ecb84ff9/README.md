# Regresi pagination daftar kursus pengelola

Tanggal: 15 Oktober 2026. Lingkungan: Windows, MariaDB lokal, Apache XAMPP PHP 8.0.30 yang sudah berjalan, PHP 8.1.34 untuk CLI, dan Chrome 155. Suite HTTP dan sembilan kombinasi browser selesai dengan exit code 0; pembersihan fixture juga lulus. Ini bukti regresi fitur lokal, bukan persetujuan desain akhir atau UAT.

## Masalah yang direproduksi dan hasil perbaikan

Sebelum perubahan, permintaan `halaman=%202%20` benar-benar menghasilkan isi halaman kedua tetapi penanda pagination menyatakan halaman pertama. Pemeriksaan gagal pada ketidakcocokan tersebut, bukan sekadar karena tampilan baru belum ada; fixture percobaan dibersihkan.

Sesudah perubahan, daftar dan komponen pagination bersama memakai nomor halaman yang sudah dinormalisasi dan dibatasi server. Label **Halaman 1 dari 2** atau **Halaman 2 dari 2** sesuai dengan data yang tampil. Filter `q` dan `status` yang telah dinormalisasi dipertahankan; parameter query asing tidak diteruskan. Mengirim form filter atau membuka **Atur ulang** kembali ke awal daftar.

## Fixture dan cakupan HTTP

Suite `scripts/admin-course-list-check.ps1` membuat satu akun admin sistem sintetis beserta role-nya dan 24 kursus buatan: 21 draf, satu menunggu review, satu terbit, serta satu diarsipkan. Waktu pembaruan yang sama menguji urutan tambahan berdasarkan ID. Ini bukan konten maupun akun pengguna nyata.

- Daftar draf menghasilkan 20 kursus pada halaman pertama dan satu pada halaman kedua. ID dan urutan diperiksa secara terpisah pada setiap kartu ponsel dan baris tabel desktop, sehingga deduplikasi tautan tidak menyembunyikan baris ganda atau hilang.
- Halaman biasa, spasi di sekitar angka, nol di depan, angka sangat besar, nilai nol/negatif/teks/kosong, serta parameter array diperiksa sesuai perilaku normalisasi yang berlaku. Nomor yang dipakai query dan indikator harus sama.
- Status `review`, `published`, dan `archived` masing-masing menghasilkan satu kursus tanpa pager yang tidak perlu. Status tak dikenal, huruf besar yang tidak diizinkan, array, dan kosong mengikuti normalisasi server.
- Pencarian literal `%_!`, Unicode, kata yang hanya ada pada ringkasan, dan nilai `q=0` diperiksa. Filter gabungan, jumlah tersaring, nilai kontrol, konteks pagination, reset, dan hasil kosong juga diuji.
- Payload markup pada judul tampil sebagai teks ter-escape, bukan elemen gambar atau skrip yang aktif.
- Rute daftar pengelola `kelola/kursus` dan alias controller `admin/courses` diperiksa menggunakan akun fixture yang sudah masuk. Ini bukan pengujian lengkap penolakan semua peran atau keamanan autentikasi.

Setelah rangkaian GET, digest kolom kursus fixture yang dicakup harness dan digest audit tetap sama. Pada setiap peluncuran browser, digest kursus dan audit di luar catatan `auth.login` bermetode `password` milik akun fixture tetap sama; jumlah catatan login tersebut bertambah tepat satu sesuai login yang dilakukan harness. Pembuktian ini **bukan digest seluruh database**, bukan klaim bahwa sesi tidak berubah, dan bukan audit keamanan login menyeluruh.

Cleanup akhir menghapus akun sintetis, role miliknya, 24 kursus berdasarkan identitas/pemilik tepat, serta catatan audit fixture/login terkait. Data lain dan catatan pembatasan login yang tidak menjadi milik fixture dipertahankan. Tidak ada klaim bahwa seluruh database menjadi kosong.

## Pemeriksaan browser dan capture

Tiga keadaan—halaman pertama, halaman terakhir, dan hasil filter kosong—masing-masing diperiksa pada lebar 360, 768, dan 1280 piksel: total sembilan kombinasi. Pemeriksaan browser lulus untuk geometri/overflow, heuristik label kontrol, dan fokus skip-link. Enam capture disimpan pada lebar 360 dan 1280 piksel; tidak ada capture 768 piksel dalam folder ini.

| Keadaan | Ponsel 360 px | Desktop 1280 px | Capture yang ditinjau langsung |
| --- | --- | --- | --- |
| Halaman pertama, 20 kursus | [Lihat ponsel](admin-courses-first-page-360.png) | [Lihat desktop](admin-courses-first-page-1280.png) | Desktop 1280 px |
| Halaman terakhir, satu kursus | [Lihat ponsel](admin-courses-last-page-360.png) | [Lihat desktop](admin-courses-last-page-1280.png) | Ponsel 360 px |
| Hasil filter kosong | [Lihat ponsel](admin-courses-filtered-empty-360.png) | [Lihat desktop](admin-courses-filtered-empty-1280.png) | Ponsel 360 px |

Pada tiga capture yang ditinjau langsung tidak terlihat tumpang tindih. Tabel desktop dan kartu ponsel dapat dibaca; payload pada judul halaman terakhir terlihat sebagai teks. Tiga capture lainnya tersedia sebagai artefak, tetapi tidak dicatat sebagai sudah ditinjau visual satu per satu. Tangkapan panjang dapat digulir; jumlah baris dalam satu layar bukan ukuran jumlah seluruh hasil.

## Cara mengulang

Jalankan dari root proyek terhadap instalasi QA lokal yang memang diizinkan untuk membuat dan membersihkan fixture. Skrip ini memakai `db_study` lokal melalui MySQL loopback dan kredensial administrator QA setempat; ia bukan tes hanya-baca pada database produksi. URL HTTP wajib loopback tanpa kredensial, query, atau fragment. Jangan jalankan terhadap data/server produksi.

```powershell
Set-Location -LiteralPath 'C:\xampp\htdocs\study'
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/admin-course-list-check.ps1 `
  -Php81Path 'C:\tools\php81-study\php.exe' `
  -MysqlPath 'C:\xampp\mysql\bin\mysql.exe' `
  -BaseUrl 'http://localhost/study/index.php/' `
  -ChromePath 'C:\Program Files\Google\Chrome\Application\chrome.exe' `
  -CaptureScreenshots
```

`-ChromePath` opsional: tanpa parameter itu hanya bagian HTTP dijalankan. `-CaptureScreenshots` memerlukan Chrome dan membuat direktori baru dengan tanggal serta suffix acak unik. Jangan mengarahkan hasil baru untuk menimpa folder bukti ini; skrip menolak memakai ulang direktori capture yang sudah ada. Pemeriksaan tanpa capture tetap dapat menjalankan browser bila `-ChromePath` diberikan. Skrip tidak dimaksudkan menyalakan ulang server web PHP 8.1.

## Batas bukti

- HTTP pada suite ini melalui Apache PHP 8.0, bukan HTTP PHP 8.1. Kelulusan CLI PHP 8.1 tidak mengubah batas tersebut.
- Tidak membuktikan Ubuntu, Nginx, PHP-FPM, aaPanel, HTTPS produksi, beban besar, atau data dengan seluruh variasi konten nyata.
- Heuristik label/fokus dan tinjauan tiga capture bukan audit aksesibilitas penuh, navigasi keyboard seluruh alur, screen reader, zoom 200%, atau UAT.
- Fixture satu admin tidak membuktikan seluruh peran, revokasi, isolasi kepemilikan, ataupun keamanan autentikasi. Digest terbatas di atas tidak menggantikan audit seluruh efek database.
- Orkestrator QA lengkap tidak dijalankan ulang sebagai bagian dari bukti ini. Hasil hanya mencakup suite daftar kursus pengelola dan pemeriksaan pendamping yang dinyatakan secara khusus.
