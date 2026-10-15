# Keamanan akun sendiri — baseline final

15 Oktober 2026: `account-security-check.ps1` dengan Chrome memeriksa tema orang tua dan anak sekolah dasar di 360/768/1280 px, enam kombinasi. Semua lulus tanpa document overflow, masalah label heuristik atau kegagalan fokus skip-link.

`security-parent-1280.png` dan `security-child-360.png` ditinjau manual setelah formulir menggunakan kartu autentikasi bersama; capture awal yang terlalu lebar tidak dijadikan baseline. Nama akun adalah fixture sintetis yang telah dibersihkan, semua kolom kata sandi kosong. Username dapat membungkus, petunjuk minimum/batas byte terlihat, dan konsekuensi keluar dari semua perangkat dijelaskan sebelum submit.

Pengujian HTTP juga memeriksa akun nonaktif dan hitungan gagal yang tetap tersimpan sesudah perubahan berhasil. Bukti ini bukan audit aksesibilitas lengkap, semua kelompok usia, zoom 200%, screen reader, UAT atau uji Linux. Pembersihan fixture DB dan penghentian server PHP penguji berhasil; sesi PHP pengujian loopback memakai direktori sementara standar dan mengikuti pembersihan sesi runtime, bukan klaim semua berkas sesi sementara dihapus manual.
