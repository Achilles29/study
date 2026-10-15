# Diagnostik fokus keyboard pada zoom browser

Tanggal: 15 Oktober 2026. Lingkungan: Windows, Chrome 155.0.8059.79, Node 22.22.3, Apache XAMPP lokal (PHP HTTP 8.0.30). Tidak menjalankan server PHP 8.1, Nginx atau server baru.

## Status yang benar

Penangkapan bukti kegagalan sudah ditambahkan dan diuji. **Penyebab kegagalan intermiten lama pada halaman daftar belum ditemukan dan item investigasinya tetap terbuka.** Tidak ada perubahan PHP, CSS, aturan bisnis, skema, harga, kredit atau pembayaran pada pekerjaan ini.

Sebelumnya penguji menunggu seluruh siklus Tab, mereset gulir, lalu melempar error sebelum blok screenshot dijalankan. Sekarang elemen pertama yang gagal tetap menggagalkan proses, tetapi JSON dan PNG viewport dapat disimpan sebelum Tab berikutnya, blur atau reset gulir. Tidak ada retry interaksi untuk mengubah kegagalan menjadi lulus.

## Bukti browser

| Run | Cakupan dan hasil | Artefak |
| --- | --- | --- |
| Kontrol negatif awal | Outline sengaja dihapus pada DOM browser uji; exit 1 yang diharapkan, JSON/PNG gagal tersimpan | [Catatan kontrol](../2026-10-15-focus-negative-control/README.md) |
| Pengulangan daftar awal | Native 200%, menu terbuka, tiga lebar; exit 0, 16 kontrol per lebar | [Catatan awal](../2026-10-15-focus-signup-run01/README.md) |
| Empat halaman publik | Beranda/katalog/masuk/daftar, native 200%, menu terbuka, 12 kombinasi; exit 0 | 12 JSON dan 8 PNG dalam folder ini |
| Pembanding daftar | Native 100%, tiga lebar; exit 0, 16/16/15 kontrol | [Catatan pembanding](../2026-10-15-focus-signup-100/README.md) |
| Kontrol negatif akhir | Instrumen lengkap dan penantian load baru; tetap exit 1 dengan bukti kegagalan | [Catatan kontrol akhir](../2026-10-15-focus-negative-final/README.md) |
| Regresi daftar akhir | Native 200%, tiga lebar; exit 0, 16 kontrol per lebar | [Catatan regresi akhir](../2026-10-15-focus-signup-final/README.md) |
| Regresi responsif biasa | Empat halaman publik pada 360/768/1280 px, tanpa mode bukti; 12 kombinasi exit 0 | Output penguji; tidak membuat screenshot baru |

Untuk 200%, ukuran area browser 720/768/1280 px menghasilkan viewport CSS 360/384/640 px. Page zoom/DPR 2, pinch/CSS zoom 1. Dua belas laporan di folder ini menunjukkan 12/11/11/16 kontrol per halaman pada setiap lebar; semua kontrol yang diperiksa terlihat, ber-outline, dan dokumennya memiliki fokus. Sampel font seluruh run ini berstatus `loaded`. Tidak ada overflow/teks terpotong, isu aksesibilitas heuristik atau kegagalan skip-link yang dilaporkan. Validasi form kosong masuk/daftar lulus tanpa POST autentikasi atau pendaftaran.

Screenshot yang ditinjau langsung pada rangkaian ini: kontrol negatif awal, daftar 720 px run awal, dan daftar 1280 px di folder ini. Label/kontrol terbaca dan tidak bertumpuk. PNG lain tersimpan tetapi tidak semuanya telah ditinjau manual. Screenshot sukses diambil setelah fokus/gulir dibersihkan; **hanya screenshot gagal** yang merepresentasikan kondisi elemen gagal.

## Pengujian instrumen dan pembersihan

`npm run test:ui` lulus termasuk 15 kelompok unit bukti fokus dan sembilan kelompok penantian load. Unit membuktikan penolakan route/kredensial, larangan pembacaan nilai/teks/storage, geometri, penulisan PNG lalu JSON dengan `wx`, kegagalan gambar yang tetap dicatat, dan kegagalan penulisan JSON yang tetap dilempar. Unit load mencakup sukses/timeout/koneksi tertutup/batal, menunggu promise yang sudah gagal tanpa `unhandledRejection`, serta pembersihan timer/listener masing-masing waiter. Ini simulasi, bukan pemutusan renderer nyata.

Review menemukan pola lama membuat promise timeout sebelum caller selesai menunggu perintah navigasi. Bila navigasi tersendat, timeout bisa menjadi penolakan tanpa handler dan menghentikan Node sebelum `finally`. Penantian baru memasang handler segera tetapi tetap meneruskan kegagalan kepada caller; `navigateTo` menunggu command dan event bersama lalu membatalkan waiter yang tersisa. Perintah CDP juga dibatasi 10 detik. Kegagalan proses Chrome ditangani, profil baru dibuat setelah validasi direktori, dan profil tidak dihapus bila proses miliknya belum dipastikan berhenti.

Tiga pemeriksaan argumen nyata menolak route privat, direktori bukti yang sudah ada, dan kontrol negatif tanpa mode bukti. Tidak ada profil browser baru yang tertinggal. Run shell pertama untuk pemeriksaan ini berhenti karena PowerShell memperlakukan stderr penolakan yang diharapkan sebagai error; wrapper observasinya diperbaiki lalu ketiga kasus dijalankan ulang dan lulus. Kriteria penolakan Node tidak berubah.

Probe executable Chrome yang tidak ada juga berakhir gagal dan menghapus hanya profil barunya; himpunan profil sebelum/sesudah identik. Probe awal menemukan pesan startup bergantung pada kapan event `error` tiba saat membaca berkas port; pengecekan error ditambahkan sesudah pembacaan asinkron agar pesan konsisten, lalu probe diulang dan lulus. Pemeriksaan akhir menemukan tiga direktori profil viewport dari pekerjaan lebih awal dan satu browser induk lama dengan anak prosesnya; semuanya berada di luar run ini dan dibiarkan utuh. Jangan menafsirkan cleanup run sebagai klaim bahwa semua proses/profil QA di komputer sudah tidak ada.

Pemeriksaan dokumen (48 berkas, 54 route/48 kontrak), konflik (520 teks), secret scanner, dan sintaks skrip lulus. Query baca akhir masih menunjukkan migration 13/33 tabel, nol pengguna/kursus dan schema QA, serta satu catatan login gagal yang sudah ada. Tidak menghapus catatan tersebut. Tidak menjalankan ulang full QA, migration, backup atau pengujian transaksi karena perubahan terbatas pada instrumen browser/dokumentasi.

## Menjalankan ulang

Jalankan dari root `study`, dengan Apache yang memang sudah tersedia. Pilih nama direktori baru setiap run; jangan menghapus bukti lama demi mengulang perintah.

```powershell
node scripts/browser-viewport-check.mjs --base-url http://localhost/study/index.php/ --exercise-navigation --browser-zoom 200 --extra-path daftar --extra-name sign-up --only-extra-route --capture-screenshots --capture-focus-evidence --screenshot-dir C:\xampp\htdocs\study\docs\visual-baseline\focus-daftar-run-baru
```

Untuk kontrol negatif, tambahkan `--focus-evidence-negative-control` dan gunakan folder berbeda. Opsi ini sengaja menghilangkan outline elemen pertama setelah skip-link pada browser sementara; exit 1 wajib dipertahankan, bukan dilaporkan sebagai bug aplikasi baru. Tanpa opsi tersebut, tidak ada manipulasi outline.

## Privasi dan batas bukti

- Mode bukti fokus memerlukan native zoom, opt-in screenshot, tanpa kredensial login, dan hanya route publik beranda/katalog/masuk/daftar tanpa query. Route privat/terkueri ditolak sebelum browser dimulai.
- JSON berisi urutan Tab, indeks elemen sejenis, rect, titik tengah asli/terbatas, maksimum delapan elemen hit-test, outline, status font, viewport, fokus dokumen dan sampel gulir. Tidak membaca nilai input, teks, HTML, cookie, storage, atau URL query. Ini bukan jaminan privasi untuk seluruh output mode lama atau screenshot halaman privat di penguji lain.
- Screenshot tetap merupakan gambar isi halaman publik. Jangan memasukkan data pribadi ke halaman yang akan direkam. Mode ini menggunakan profil baru tanpa login/autofill.
- Folder tujuan eksplisit harus merupakan anak langsung baru dari `docs/visual-baseline` yang bukan jalur teralihkan. File dibuat eksklusif (`wx`); kegagalan gambar dicatat di JSON, kegagalan asli tidak disembunyikan. Tanpa direktori eksplisit, gunakan direktori sementara baru milik run.
- Rekaman tambahan mengubah beban/timing penguji. Hasil hijau tidak membuktikan bahwa gangguan intermiten tidak akan muncul lagi. Pemeriksaan outline/hit-test dan ambang stabilitas gulir tidak dilonggarkan.
- Perulangan masih berhenti pada elemen yang sudah pernah dikunjungi. Ini tidak membuktikan kelengkapan seluruh urutan tab, penilaian kontras menyeluruh, atau aksesibilitas pembaca layar.
- Unit menggunakan DOM/penulis file tiruan, bukan bukti semua kondisi browser/disk. Hasil lokal ini bukan full QA, halaman privat/berisi data, HTTP PHP 8.1, Linux/Nginx, screen reader, atau UAT.
