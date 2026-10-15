# Zoom browser 200% — 15 Oktober 2026

Halaman yang diperiksa: beranda, katalog kosong, masuk, daftar kosong dan 404. Tidak ada akun, kredensial, materi pengguna atau transaksi dalam capture ini. Nama produk masih sementara.

## Metode dan bukti zoom

Mode baru `--browser-zoom 100` atau `--browser-zoom 200` pada `scripts/browser-viewport-check.mjs` memakai profil Chrome sementara. Preferensi zoom halaman ditetapkan sebelum browser dibuka. Mode ini tidak menggunakan override viewport perangkat, CSS zoom, transform, atau pinch untuk memperbesar isi. Skala perangkat dikunci satu agar hasil tidak tercampur skala desktop Windows.

Pengukuran 200% membuktikan `cssVisualViewport.zoom = 2`, `devicePixelRatio = 2`, `visualViewport.scale = 1` dan CSS zoom elemen root `1`. Area isi browser 720/768/1280 piksel menjadi viewport CSS 360/384/640 piksel. Lebar dokumen dapat lebih kecil karena scrollbar; ini bukan hilangnya konten. Pengujian 100% adalah pembanding pada ukuran area browser yang sama, bukan pengganti mode responsif 360/768/1280 yang sudah ada.

Implementasi mengacu pada sumber resmi [preferensi zoom Chromium](https://github.com/chromium/chromium/blob/main/chrome/browser/ui/zoom/chrome_zoom_level_prefs.cc), [konversi level ke faktor zoom](https://github.com/chromium/chromium/blob/main/third_party/blink/common/page/page_zoom.cc), dan [metode pengaturan ukuran area browser](https://chromedevtools.github.io/devtools-protocol/tot/Browser/#method-setContentsSize). Faktor yang dibaca dari browser harus sesuai; preferensi yang tidak diterapkan menyebabkan tes gagal.

## Pemeriksaan dan hasil

- Lima halaman × tiga ukuran pada zoom 200% lulus di PHP 8.1 sebelum server lokal berhenti: tidak ada overflow tampak, teks yang terpotong oleh ancestor `overflow: hidden/clip`, masalah label dasar, atau kegagalan fokus awal.
- Tab menelusuri kontrol yang tersedia sampai siklus berulang; pusat kontrol tetap terlihat/tidak tertutup dan indikator outline fokus ada. Jumlah kontrol setelah skip-link pada 200%: beranda 7, katalog 5, masuk 6, daftar 11, 404 satu. Menu dalam keadaan tertutup; aktivasi menu, Shift+Tab, dan pembaca layar tidak termasuk klaim.
- Pemeriksaan menunggu gulir halus selesai sebelum menilai visibilitas fokus. Pembacaan terlalu dini sempat memberi hasil gagal pada tautan jauh di bawah layar; setelah memakai deteksi gulir stabil dengan batas waktu, pemeriksaan lulus tanpa mengubah CSS aplikasi.
- Form masuk/daftar kosong diperiksa dengan validasi native; isian invalid menerima fokus tanpa mengirim POST atau membuat akun. Teks terpotong, teks normal, dan teks dekoratif mempunyai kontrol positif/negatif di dalam halaman penguji.
- Pengujian final 100% dan 200% pada Apache lokal masing-masing lulus 15 kombinasi dengan Chrome `155.0.8059.79`. Percobaan awal pembanding melalui port 8081 mengalami timeout karena layanan lokal tidak lagi berjalan, bukan hasil lulus. Guard kesehatan server kini menolak sebelum membuka Chrome bila beranda tidak tersedia; kasus server mati dan nilai zoom tidak valid sudah diuji. Penantian berbasis animation frame pada perluasan pemeriksaan keyboard sempat timeout; polling posisi gulir dipindahkan ke proses penguji agar perpindahan fokus keluar halaman tidak menahan tes. Kedua tingkat zoom kemudian diulang dan lulus.
- Sepuluh capture di bawah ditinjau langsung. Judul, label, isian, tombol, ilustrasi dan footer tetap terbaca tanpa tumpang tindih. Capture diambil sebelum penambahan pemeriksaan Tab menyeluruh; kode tampilan aplikasi tidak diubah pada perluasan tes.

## Capture yang ditinjau

| Halaman | Area browser 720 px, viewport CSS 360 px | Area browser 1280 px, viewport CSS 640 px |
| --- | --- | --- |
| Beranda | [Capture](home-720-zoom200.png) | [Capture](home-1280-zoom200.png) |
| Katalog kosong | [Capture](catalog-720-zoom200.png) | [Capture](catalog-1280-zoom200.png) |
| Masuk | [Capture](sign-in-720-zoom200.png) | [Capture](sign-in-1280-zoom200.png) |
| Daftar | [Capture](sign-up-720-zoom200.png) | [Capture](sign-up-1280-zoom200.png) |
| Pemulihan 404 | [Capture](recovery-404-720-zoom200.png) | [Capture](recovery-404-1280-zoom200.png) |

## Menjalankan ulang

```powershell
node scripts/browser-viewport-check.mjs --base-url http://localhost/study/index.php/ --browser-zoom 100 --extra-path qa-recovery-missing-page --extra-name recovery-404
node scripts/browser-viewport-check.mjs --base-url http://localhost/study/index.php/ --browser-zoom 200 --extra-path qa-recovery-missing-page --extra-name recovery-404
```

Gunakan `--capture-screenshots --screenshot-dir docs/visual-baseline/<direktori-baru>` jika memerlukan capture baru; penguji tidak menimpa file screenshot yang sudah ada. `qa-local.ps1 -ChromePath ...` menyertakan kedua tingkat zoom pada halaman publik dan 404. Profil browser hanya milik penguji dan dibersihkan setelah proses berhenti.

Cakupan ini bukan seluruh aplikasi, zoom teks saja, browser seluler, Safari/Firefox, audit WCAG, pembaca layar, UAT anak, atau bukti Ubuntu. Halaman privat/admin, katalog berisi data panjang, hasil validasi server, menu terbuka, dan pengerjaan latihan/game masih perlu cakupan zoom tersendiri.
