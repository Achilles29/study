# Baseline pemulihan koneksi permainan — 15 Oktober 2026

Capture menggunakan Chrome headless, PHP 8.1 loopback, dan akun/konten sintetis. Kedua gambar ditinjau manual: pesan ketidakpastian terbaca, kartu/tombol pengiriman terkunci, tautan pemulihan tersedia, dan isi tidak melampaui layar.

- [Respons hilang — 360 px](matching-saved-recovery-uncertain-360.png).
- [Respons hilang — 1280 px](matching-saved-recovery-uncertain-1280.png).

Alur recovery diuji pada 360/768/1280 px. Offline sebelum POST menghasilkan nol mutasi; kegagalan respons setelah server memberi 303 menghasilkan satu mutasi. Layar tidak menaikkan hitungan sendiri. Tautan pemulihan benar-benar melakukan GET baru dan menunjukkan satu pilihan tambahan. Pengujian menemukan lalu memperbaiki navigasi fragmen yang sebelumnya hanya menggulir halaman; sekarang reload eksplisit digunakan, bukan mengandalkan tautan ke fragmen yang sama.

Alur terpisah menguji keyboard radio, pengiriman dengan JavaScript, dan formulir HTML dengan aset JavaScript diblokir; masing-masing menghasilkan tepat satu pilihan per viewport serta tetap terbaca setelah refresh. Unit mencakup satu request berlangsung, pengambilan data sebelum kontrol dinonaktifkan, redirect manual, respons tidak pasti, 403/409, timeout dan pemulihan.

Judul dengan markup dan nomor kartu adalah fixture escaping, bukan materi akhir. Tidak ada data pengguna nyata atau transaksi uang. Capture tidak membuktikan mode bermain offline, back-forward saat upload, pembaca layar lengkap, zoom 200%, perangkat fisik, staging Linux atau UAT.
