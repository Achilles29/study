# Katalog berisi kursus — bukti lokal 15 Oktober 2026

## Lingkup dan hasil

`scripts/catalog-check.ps1` lulus pada Apache XAMPP PHP 8.0.30, menggunakan Chrome 155 dan MariaDB lokal. Browser memeriksa tiga keadaan katalog pada lebar 360, 768, dan 1280 piksel: halaman pertama 12 kartu, halaman terakhir satu kartu, dan pencarian tanpa hasil. Sembilan kombinasi lulus pemeriksaan lebar dokumen, elemen keluar viewport, label heuristik, serta fokus awal skip-link. Ini bukan pengujian keyboard menyeluruh atau audit aksesibilitas formal.

Enam screenshot disimpan; empat telah ditinjau langsung: halaman pertama 360/1280, halaman terakhir 360, dan hasil kosong 360. Tata letak kartu, formulir dan tombol tidak bertumpuk atau keluar layar. Screenshot halaman pertama ponsel sangat tinggi karena menampilkan seluruh 12 kartu; bukan contoh panjang materi produksi.

- [Halaman pertama, ponsel](catalog-populated-360.png)
- [Halaman pertama, desktop](catalog-populated-1280.png)
- [Halaman terakhir, ponsel](catalog-last-page-360.png)
- [Halaman terakhir, desktop](catalog-last-page-1280.png)
- [Hasil kosong, ponsel](catalog-filtered-empty-360.png)
- [Hasil kosong, desktop](catalog-filtered-empty-1280.png)

## Data dan pengujian perilaku

Semua nama dan isi adalah fixture sintetis: satu penulis nonaktif, dua kategori, 13 kursus terbit kategori A, satu kursus terbit kategori B, dan masing-masing satu draf/review/arsip. Tidak ada materi, akun anak, transaksi atau kredensial nyata dalam capture. Teks `<img ...>` yang terlihat pada kartu terakhir adalah payload pengujian yang sengaja ditampilkan sebagai **teks**, bukan gambar atau skrip yang dieksekusi.

Uji HTTP membuktikan ringkasan diambil dari database dan di-escape, Unicode tetap utuh, pencarian `%_!` literal, filter gabungan dan hitungannya benar, serta status nonpublik tidak masuk hasil. Waktu terbit fixture sama: urutan ID menurun membagi 12+1 hasil tanpa duplikasi. Halaman ekstrem, negatif, array, spasi dan angka nol di depan tetap memakai hasil serta indikator halaman yang sama setelah normalisasi server. Tautan pagination hanya membawa `q`, `kategori`, dan `halaman`, bukan parameter asing. Reset serta pesan kosong awal/terfilter turut diperiksa.

Semua fixture database tepat milik penguji telah dihapus dan pemeriksaan sisa fixture bernilai nol. Kolom kursus yang diperiksa sebelum/sesudah pembacaan tetap sama; hitungan audit tidak berubah. Ini bukan digest seluruh database. Screenshot dipertahankan sebagai bukti pengujian, bukan seed katalog.

## Menjalankan ulang

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/catalog-check.ps1 `
  -MysqlPath 'C:\xampp\mysql\bin\mysql.exe' `
  -BaseUrl 'http://localhost/study/index.php/' `
  -ChromePath 'C:\Program Files\Google\Chrome\Application\chrome.exe'
```

Jalankan dari root proyek dengan MySQL dan Apache lokal yang sudah aktif. Penguji tidak menyalakan server HTTP. Tanpa `-ChromePath`, pemeriksaan HTTP/database tetap berjalan tanpa browser. Opsi `-CaptureScreenshots` menolak direktori baseline yang sudah ada; jangan menghapus atau menimpa bukti lama untuk mengulang uji rutin. Jalankan suite database satu per satu karena pemeriksaan hitungan audit global tidak menerima penulisan paralel.

## Batas yang masih terbuka

Ini bukti Windows/Apache lokal, bukan HTTP PHP 8.1, Ubuntu/Nginx/PHP-FPM, UAT materi/usia, pembaca layar, zoom 200%, koneksi lambat atau seluruh browser. Pada putaran capture ini perubahan hanya katalog dan urutan daftar kursus. Normalisasi pagination pengelola kemudian diperbaiki serta diuji pada [baseline terpisah](../2026-10-15-admin-course-list-pagination-32ed3752ecb84ff9/README.md); jangan memakai capture katalog sebagai bukti panel admin. Harga, sampul kursus, pembelian dan akses paket tidak ditambahkan oleh perbaikan ini.
