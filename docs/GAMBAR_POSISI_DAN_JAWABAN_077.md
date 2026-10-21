# Gambar di dalam soal dan pilihan jawaban — 077

20 Oktober 2026. Melanjutkan 076 tanpa migrasi, reset ID, atau impor konten ke produksi.

## Cara mengunggah

Buka `/kelola/bank-soal/impor`, unduh **TXT gambar dalam soal & jawaban**. Gunakan penanda `[GAMBAR: nama.png]` pada posisi yang diinginkan. Pilih/blok semua lampiran sekaligus, lalu Periksa berkas → tinjau pratinjau → konfirmasi Simpan ke bank.

```text
6. Perhatikan tabel berikut.
[GAMBAR: tabel6.png]
Nilai dari [GAMBAR: rumus6.png] adalah …
   a. 9
   b. 8
   c. 7
   d. 6

7. Gambar manakah yang menunjukkan segitiga?
   a. [GAMBAR: lingkaran.png]
   b. [GAMBAR: segitiga.png]
   c. [GAMBAR: persegi.png]
   d. [GAMBAR: persegi-panjang.png]

KUNCI JAWABAN
6.a 7.b
```

Unggah enam berkas dengan nama yang sama persis. Kunci di atas **hanya contoh format**, bukan penetapan jawaban soal pemilik yang gambarnya belum diberikan.

- Penanda boleh digunakan beberapa kali dalam soal atau opsi, termasuk gambar saja, teks + gambar, dan beberapa gambar dalam satu pilihan.
- Penanda pada baris tersendiri menempatkan gambar di antara baris teks. Penanda di dalam kalimat menempatkan gambar di sana; pada layar kecil gambar besar membungkus secara responsif.
- Tidak perlu mengulang nama penanda pada `GAMBAR:`. Baris `GAMBAR: satu.png | dua.png` tetap tersedia untuk galeri. Gambar yang sudah ditempatkan lewat penanda tidak diulang di galeri.
- TXT berlabel, CSV, tabel Word dan Excel memakai penanda yang sama pada `soal` dan kolom `a`–`d`. Kolom tidak berubah. Gambar tempelan Office tidak diambil; unggah PNG/JPEG terpisah.
- Pembahasan tetap teks, bukan penanda gambar. Ini mencegah gambar khusus pembahasan ikut dapat diakses sebelum latihan selesai.
- Batas tetap 500 lampiran/impor, 3 MiB/gambar, 32 MiB total, 4096 piksel/sisi, 4 juta piksel/gambar dan 64 juta piksel total. Nama hilang/penanda tidak lengkap/gambar pilihan identik dengan teks sama ditolak.

## Editor bank

Halaman tambah/edit mendukung banyak lampiran. Nama baru menambah gambar; nama yang sama memperbarui gambar itu saja. Hapus nama dari daftar beserta penandanya untuk melepas gambar. Kosongkan upload untuk mempertahankan gambar lama. Form editor menampilkan nama berkas dalam penanda, sedangkan penyimpanan memakai indeks internal. Pratinjau di bawah formulir menampilkan **versi tersimpan**, bukan edit yang belum disimpan.

## Kontrak data dan keamanan

- Tidak ada tabel/kolom baru. Metadata `image_json` mendapat `text_markers_version=1` hanya untuk soal dengan penanda; teks disimpan sebagai `[GAMBAR:1]`, dst. Konten lama tanpa versi ini tetap berupa teks/galeri biasa.
- Pilihan tetap terikat option ID dan penilaian existing. Pengacakan tidak memisahkan gambar dari jawaban. Snapshot menyimpan teks, opsi, dan seluruh gambar; edit bank tidak mengubah hasil atau gambar percobaan yang sudah dimulai.
- Fingerprint soal baru memakai posisi + hash isi gambar, pilihan/kunci dan galeri tersisa. Ganti nama berkas atau urutan pilihan dengan kunci yang sesuai tidak membuat ID baru. Fingerprint legacy tidak dihitung ulang.
- Rendering meng-escape teks dan hanya membuat URL privat internal dari indeks tervalidasi. Tidak mengeksekusi HTML/JavaScript, mengambil URL luar, atau membaca path dari penanda. Nama berkas/storage key/kunci aktif tidak dikirim ke peserta.
- Endpoint gambar tetap memakai izin pemilik/relasi keluarga/hasil final serta audit admin existing. Gambar jawaban bukan jalan belakang membuka hasil anak.

## Bukti uji

- `scripts/test-media077.php`: 23 pemeriksaan baru + 44 regresi075 + 15 regresi076. Posisi persis, pilihan gambar saja/jamak, galeri campuran, format TXT/CSV/DOCX/XLSX, nama/penanda invalid, duplikat gambar, escaping, identitas setelah rename/shuffle.
- `scripts/test-media077.mjs`: multipart nyata → analisis → pratinjau → commit → ID sama pada ujian → anak mengerjakan dengan pilihan acak → nilai100. Gambar privat benar-benar dimuat browser; admin/tamu/parent tidak terkait/outsider serta indeks tak tersedia diuji.
- Editor menerima dua lampiran sekaligus (mengganti nama lama dan menambah nama baru); gambar historis tetap sama. Melepas gambar yang masih dirujuk ditolak tanpa revisi berubah.
- Pratinjau/pengerjaan pada1280/360 piksel tanpa overflow atau galat JavaScript; klik pilihan bergambar, panah keyboard dan disclosure petunjuk lulus.
- Regresi HTTP/browser068: semua format lama, dedup antarformat, role/CSRF, gambar privat dan desktop/mobile lulus.20 render latihan dan85 assertion izin baca terisolasi lulus.
- Dua kegagalan awal tes diperbaiki pada skrip QA: redirect login GET sah memakai307, dan dialog keluar saat jawaban belum disimpan perlu diterima browser otomatis. Tidak mengubah autentikasi/pengaman unsaved atau mereset akun demi meloloskan tes.

## Operasional

Backup: `/www/backup/projectpintar-media077-lr2H4a3m`. Staging: `/www/study-private/media077-Wg264SEK` (bukan dependensi runtime). Tidak mengubah Finance/Pustaka, pembayaran, FPM/Nginx, atau data bisnis produksi. Media privat produksi tetap memakai lokasi existing.

Pemasangan18 berkas diverifikasi byte sama dengan staging, FPM ProjectPintar di-reload dan aktif. HEAD produksi: impor/template terlindungi303 ke login, CSS200, skrip tes404. Schema tetap51, bank tetap0; checksum soal, opsi, bank, klasifikasi, akun, order dan wallet sama sebelum/sesudah. Tidak melakukan unggah dengan akun admin produksi; pengujian terautentikasi memakai database QA. Konfigurasi FPM/Nginx tidak diubah.
