# Formulir daftar setelah perbaikan batas kata sandi

Pada 15 Oktober 2026, Chrome memeriksa beranda, katalog, masuk dan daftar pada 360/768/1280 px (12 kombinasi): tanpa document overflow, kegagalan label heuristik atau fokus skip-link. `sign-up-360.png` dan `sign-up-1280.png` ditinjau manual: penjelasan minimum karakter/maksimum byte terbaca tanpa menutup kontrol. Capture tidak mengandung akun atau kata sandi.

Ini bukti layout formulir daftar setelah perubahan copy, bukan bukti seluruh alur keyboard, pembaca layar, seluruh tema anak atau UAT. Pengujian perilaku batas ASCII/Unicode ada di `auth-input-check.ps1` melalui `password-input-cases.ps1`; helper/model dan CLI diuji terpisah.
