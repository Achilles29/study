# Tinjauan visual laporan latihan keluarga

Capture berasal dari `scripts/practice-check.ps1 -CaptureScreenshots` pada server development PHP 8.1, 15 Oktober 2026. Pengujian memakai akun keluarga dan 11 percobaan sintetis yang dibersihkan setelah selesai; bukan data anak nyata atau materi produksi.

- [Riwayat ponsel 360 px](family-practice-history-360.png)
- [Riwayat desktop 1280 px](family-practice-history-1280.png)

Kedua screenshot ditinjau manual: satu kolom di ponsel, dua kolom di desktop, ringkasan seluruh percobaan, kartu hasil, dan pagination terlihat tanpa tumpang tindih/overflow. Skor nol berulang sengaja berasal dari fixture untuk menguji halaman 10+1, bukan rancangan konten kursus. Browser juga memeriksa riwayat dan detail hasil pada 360/768/1280 px; pemeriksaan geometri, nama kontrol, dan fokus skip-link lulus.

Detail hasil 360 px ditinjau pada capture sementara; sesudah tinjauan, kalimat apresiasi diubah agar menyapa orang tua, sedangkan label pilihan sudah menggunakan “Pilihan anak”. Screenshot detail awal tidak dijadikan baseline final. Uji browser pascaperubahan teks termasuk dalam regresi akhir.

Ini bukan bukti aksesibilitas WCAG penuh, pembaca layar, zoom 200%, atau UAT. Halaman data kosong/latihan berjalan diuji melalui HTTP; capture di sini khusus riwayat yang sudah memiliki hasil.
