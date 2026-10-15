# Direktori pengguna internal — 15 Oktober 2026

Capture Chrome headless pada PHP 8.1 loopback memakai akun sintetis. Kedua gambar berikut ditinjau manual:

- [Ponsel, filter menunggu aktivasi](admin-users-filtered-360.png): ringkasan filter tetap terlihat ketika panel filter ditutup, label pencarian terbaca, nama pengguna panjang membungkus, satu hasil tampil sebagai kartu dengan label data.
- [Desktop, daftar 20 akun](admin-users-1280.png): tabel dengan heading, status teks, tanggal WIB, jumlah hasil dan pagination 20+11 terbaca. Markup pada username tampil sebagai teks, bukan HTML.

Suite `user-directory-check.ps1` menjalankan daftar biasa dan tersaring pada 360/768/1280 px (enam kombinasi) tanpa overflow, kegagalan label heuristik, atau kegagalan fokus skip-link. Screenshot daftar penuh di ponsel melebihi batas capture 8.000 px, sehingga tidak digunakan sebagai bukti seluruh halaman; baseline ponsel yang disimpan memakai hasil filter satu akun. Tidak ada pengguna nyata, email, atau hash password dalam capture. Nama tampilan pada header adalah nama akun QA yang sedang masuk, bukan kolom nama lengkap akun dalam direktori.

Pengujian HTTP mencakup pembatasan peran, konteks pencarian milik sesi, CSRF, pembatasan sepuluh konteks, Unicode, pencarian literal, sort/filter gabungan, pagination, pencabutan admin dan hanya-baca database. Ini bukan audit pembaca layar penuh, zoom 200%, UAT, load, atau bukti staging Linux. Semua fixture akun/peran/audit dibersihkan oleh suite.
