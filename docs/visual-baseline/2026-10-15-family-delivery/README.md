# Tinjauan pengiriman dan hasil sesi keluarga

Capture dari `scripts/family-delivery-check.ps1 -CaptureScreenshots`, PHP 8.1 loopback, 15 Oktober 2026. Seluruh pengguna, paket/entitlement, soal, jadwal, sesi, jawaban dan nilai merupakan fixture sintetis yang dibersihkan setelah uji.

- [Pengantar anak, 360 px](family-assigned-intro-360.png)
- [Hasil untuk orang tua, 360 px](family-assigned-result-360.png)

Kedua gambar ditinjau manual: tema anak/orang tua, judul dan jadwal terbaca, tombol tidak bertumpuk, hasil punya label “Pilihan anak” dan pembahasan, tanpa kontrol mengubah jawaban. Penanda `Penjelasan_privat_...` adalah penanda uji untuk membuktikan kunci/pembahasan tidak dikirim saat aktif; bukan materi produksi.

Browser terarah memeriksa pengantar, daftar tugas anak, attempt aktif, hasil pembuat dan status sesi pada 360/768/1280 px, tanpa overflow atau kegagalan heuristik label/fokus awal. Sesudah capture, preview pembuat juga diperketat agar membaca snapshot terkirim; perubahan itu diverifikasi lagi lewat regresi gabungan, bukan diklaim sebagai bagian gambar lama.

Ini bukan audit WCAG, uji pembaca layar, zoom 200%, load serentak, atau UAT. Screenshot editor sebelum pengiriman terdapat pada baseline draf terdahulu dan bukan bukti UI pengiriman final.
