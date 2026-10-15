# Halaman pemulihan HTTP — 15 Oktober 2026

Baseline HTML 404 pada server lokal PHP 8.1, tanpa akun maupun data pengguna. Nama Study masih identitas sementara. Dua capture ditinjau langsung: [ponsel 360 piksel](recovery-404-360.png) dan [desktop 1280 piksel](recovery-404-1280.png).

Perintah dari root proyek:

```powershell
node scripts/browser-viewport-check.mjs --extra-path qa-recovery-missing-page --extra-name recovery-404 --only-extra-route --capture-screenshots --screenshot-dir docs/visual-baseline/2026-10-15-recovery-pages
```

- Lebar 360/768/1280: tidak ada overflow, kegagalan pemeriksaan aksesibilitas dasar, atau kegagalan fokus awal tautan lewati isi.
- Judul, penjelasan, kode halaman, tombol kembali dan petunjuk privasi terbaca tanpa tabrakan. Komponen responsif memakai CSS mandiri tanpa aset/JavaScript/animasi.
- `test-recovery-pages.php` memakai renderer CI3 asli dengan stub konfigurasi/HTTP di proses CLI untuk status 400/401/403/404/405/409/429/500/503, escaping, pesan 5xx generik dan URL tidak aman. Mode `--without-config` membuktikan template 503 dapat dirender tanpa helper/config/database. Lulus pada PHP 8.0/8.1/8.5.
- `recovery-http-check.mjs` lulus pada PHP 8.1 loopback, Apache lokal dan Nginx Windows/PHP-CGI: GET/HEAD 404, POST tanpa CSRF 403, serta karakter URI terlarang 400, dengan status benar, no-store, noindex dan penjelasan Indonesia. Gunakan karakter `|` literal untuk kasus 400: representasi persen tetap diterima filter URI CI3 dan menjadi 404, bukan 400. Uji tidak mengirim kredensial atau mengubah data aplikasi.

Capture hanya mencakup 404; tidak diklaim sebagai tinjauan visual seluruh pesan 4xx/5xx. Ini bukan uji pembaca layar nyata, Ubuntu/PHP-FPM, kegagalan bootstrap, atau template debug database/PHP/exception.
