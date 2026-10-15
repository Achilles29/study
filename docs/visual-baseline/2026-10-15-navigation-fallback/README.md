# Navigasi tetap tersedia ketika skrip gagal — 15 Oktober 2026

Lingkungan: Apache XAMPP lokal, Chrome 155. Tidak ada akun/data nyata, transaksi atau layanan eksternal yang dipakai.

## Perilaku

HTML server menampilkan menu dan menyembunyikan tombol collapse. `assets/js/navigation.js` hanya mengaktifkan collapse setelah `bootstrap.Collapse` tersedia; inisialisasi kedua tidak mengubah menu lagi. Jika Bootstrap atau skrip aplikasi tidak dimuat, tautan yang diizinkan tetap terlihat dan tombol tidak berfungsi tidak ditampilkan. Tidak ada perubahan menu menurut peran atau otorisasi server.

## Bukti

- Browser dengan `--exercise-navigation --block-page-scripts`: empat halaman publik × 360/768/1280 px, 12 kombinasi lulus. Semua aset JavaScript halaman diblokir melalui jaringan; penguji masih memakai evaluasi DevTools untuk mengukur halaman. Ini bukan klaim bahwa mesin JavaScript browser dimatikan. Bukti menunjukkan API Bootstrap tidak tersedia, marker enhancement tidak dipasang, tombol hidden, dan seluruh tautan menu terlihat tanpa overflow/isu label dasar.
- Browser normal dengan `--exercise-navigation`: 12 kombinasi publik lulus, Bootstrap tersedia, enhancement aktif, menu ponsel dapat dibuka dan penanda tetap terlihat.
- Unit JavaScript memeriksa dependency kosong/invalid, elemen hilang, jalur normal, dan inisialisasi idempoten. Unit template tiga versi PHP serta 10 kasus HTTP publik memastikan markup fallback, escaping/visibilitas peran dan URL aset berversi.
- [Katalog ponsel 360 px](catalog-360.png) dan [masuk desktop 1280 px](sign-in-1280.png) ditinjau langsung: menu tetap terbaca, tombol collapse tidak tampil, dan halaman tidak bertumpuk. Enam capture lain tersedia tetapi tidak diklaim seluruhnya ditinjau manual.

```powershell
node scripts/browser-viewport-check.mjs --base-url http://localhost/study/index.php/ --exercise-navigation --block-page-scripts
node scripts/browser-viewport-check.mjs --base-url http://localhost/study/index.php/ --exercise-navigation
node scripts/test-navigation.mjs
```

Hasil ini tidak mencakup seluruh halaman privat, browser lain, pembaca layar nyata, Linux atau UAT. Jika semua skrip gagal, fitur yang memang memerlukan JavaScript tidak otomatis dianggap berfungsi; perbaikan ini khusus akses navigasi.

## Investigasi fokus terpisah

Sebelum perubahan fallback, lima run terpisah halaman daftar pada native zoom 200% lulus (15 kombinasi CSS 360/384/640, masing-masing 16 kontrol keyboard). Kegagalan sebelumnya belum terulang, bukan terbukti selesai. Diagnosis kini merekam rect, titik hit-test, elemen yang terkena, viewport layout/visual, posisi scroll dan status fokus dokumen saat gagal. Tidak ada perubahan kriteria agar kasus gagal diloloskan; butir investigasi di roadmap tetap terbuka.
