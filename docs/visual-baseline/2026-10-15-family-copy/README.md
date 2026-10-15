# Tinjauan formulir salin sesi keluarga

Capture lokal pada PHP 8.1, 15 Oktober 2026, menggunakan `family-delivery-check.ps1 -ChromePath ... -CaptureScreenshots`. Akun, sesi dan anak pada gambar semuanya fixture sintetis, bukan data pengguna nyata.

- [Formulir ponsel, 360 px](family-copy-form-360.png)
- [Formulir desktop, 1280 px](family-copy-form-1280.png)

Kedua gambar ditinjau manual: judul dan konteks sumber terbaca, anak tujuan mempunyai label, konfirmasi dan tombol tidak bertumpuk, serta formulir berpindah dari dua kolom ke satu kolom pada ponsel. Nama latihan panjang memakai perilaku scroll bawaan input, bukan memperlebar halaman. Halaman menjelaskan bahwa jadwal/jawaban/nilai tidak ikut disalin dan draf harus diperiksa sebelum dikirim.

Browser memeriksa halaman pada 360/768/1280 px tanpa overflow, masalah label heuristik, atau kegagalan fokus skip-link pertama. Sesudah capture, suite sempat terhenti saat menyiapkan 49 soal fixture karena batas panjang command-line Windows; perintah fixture dipecah, tanpa mengubah UI yang dicapture. Status akhir pengujian perilaku/regresi dicatat di Tahap 14 roadmap, terpisah dari bukti visual ini.

Ini bukan audit WCAG penuh, pembaca layar, zoom 200%, load simultan, maupun UAT orang tua nyata.
