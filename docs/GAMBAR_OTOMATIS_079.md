# Gambar otomatis dari nomor soal — 079

20 Oktober 2026. Melanjutkan077/078; tanpa migrasi atau perubahan data produksi.

## Cara memakai

- Unggah `soal7.jpg`, `soal9.png`, `soal17.jpg`, `soal18.jpg` bersama dokumen. Nama tidak perlu dicantumkan di teks soal: gambar otomatis tampil **di atas teks soal** dengan nomor yang sesuai.
- Beberapa gambar: `soal7-1.jpg`, `soal7-2.jpg`, `soal7-10.jpg`; urutan numerik1,2,10, bukan urutan memilih berkas. Jika ada `soal7.jpg`, gambar utama ini tampil sebelum versi bernomor tambahan.
- PNG/JPG/JPEG didukung; format asli harus sesuai ekstensi. Nama `soal 7.png`, `soal_7.png`, `soal-7.png` dan huruf besar juga dikenali. Nama ambigu seperti `soal7a.png` tidak ditebak sebagai opsi A.
- TXT naskah menggunakan **nomor asli soal**, termasuk nomor yang tidak berurutan. Word/Excel/CSV/TXT berlabel menggunakan urutan soal mulai1, tanpa header. Bukan ID bank.
- Posisi khusus atau pilihan jawaban tetap memakai `[GAMBAR: nama.png]`. Referensi eksplisit (termasuk galeri `GAMBAR:`) didahulukan di seluruh dokumen; gambar yang sudah dirujuk tidak dipasang otomatis lagi.
- Nama bebas seperti `diagram.png`, nomor yang tidak ada, atau nomor soal ganda tidak dipasangkan sembarangan. Seluruh batch yang belum jelas tetap ditolak dengan petunjuk perbaikan.
- Pratinjau menunjukkan nama gambar yang dipasangkan otomatis dan posisi sebenarnya. Semua panduan tetap tertutup default; halaman utama hanya menambah satu petunjuk singkat.

## Implementasi

Pencocokan dilakukan hanya saat pemeriksaan impor, setelah referensi eksplisit dikumpulkan. Penempatan disimpan melalui penanda posisi077 pada awal prompt. Dengan demikian pengacakan soal/opsi, snapshot historis, editor dan URL privat tetap memakai mekanisme existing, tanpa inferensi ulang saat peserta membuka soal. Penempatan otomatis dan penanda manual yang setara menghasilkan fingerprint yang sama. Galeri lama dan soal yang sudah tersimpan tidak dipindah/backfill.

Validasi kunci, MIME/ekstensi, ukuran/resolusi, izin, konfirmasi commit, CSRF dan dedup tidak dilonggarkan. Tidak mengubah materi/teks/kunci dari dokumen, selain menambahkan penempatan gambar ke salinan yang dinormalisasi.

## Bukti uji

- `scripts/test-auto079.php`:26 pemeriksaan baru +82 regresi075/076/077. Nomor asli, format tabel/label, urutan alami dan gambar utama, prioritas penanda eksplisit lintas soal/opsi, nomor ganda/hilang/nama ambigu, rendering metadata tanpa galeri duplikat, serta dedup.
- `scripts/test-auto079.mjs`: contoh pemilik30 soal → pratinjau6 gambar,4 otomatis pada7/9/17/18,2 tetap inline di6; **tidak commit contoh pemilik**. Alias JPEG dan referensi ekstensi diperbaiki hanya dalam request QA, bukan file asli.
- Lampiran tanpa nomor tujuan menghasilkan422 dan tidak membuat record pratinjau. Konten sintetis diuji sampai commit → ujian → pengerjaan anak dengan opsi acak → nilai100. Gambar otomatis tetap mendahului teks, gambar khusus/opsi tetap di tempatnya, akses akun lain404.
- Browser1280/360 memuat gambar privat nyata, tanpa overflow/galat JavaScript. Panduan dan validasi lama tetap berlaku.
- Script HTTP membutuhkan contoh pemilik pada `docs/testgambar`; ini bukan fixture kurikulum untuk diterbitkan.

Backup sebelum perubahan: `/www/backup/projectpintar-auto079-M42glXHJ`. Staging `/www/study-private/auto079-UfLHfSmc` bukan dependensi runtime. Tidak menyentuh Finance/Pustaka atau pembayaran.
