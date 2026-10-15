# Penanda navigasi aktif — 15 Oktober 2026

Baseline ini memakai Apache XAMPP lokal, Chrome 155 dan katalog kosong. Tidak membuktikan PHP 8.1 HTTP, Linux, pembaca layar nyata, atau UAT.

## Perubahan

Menu utama menggunakan `aria-current="page"` untuk tujuan tepat dan `location` untuk bagian turunannya. Penanda berupa garis bawah dan teks tebal, bukan warna saja. Pratinjau admin tidak menandai katalog publik. Menu tetap dirender menurut akun/peran, tanpa perubahan izin controller.

Pemeriksaan awal menemukan isi menu ponsel menempel ke tepi. Versi final memberi jarak sisi dan tinggi tautan utama minimum 44 px. Capture awal pada direktori `2026-10-15-navigation` dan `2026-10-15-navigation-expanded` adalah keadaan sebelum koreksi ini, bukan baseline final.

## Bukti visual

- [Katalog, menu terbuka 360 px](catalog-360.png): ditinjau langsung; garis bawah terlihat, menu memiliki jarak sisi, teks tidak terpotong.
- [Daftar 1280 px](sign-up-1280.png): ditinjau langsung; CTA halaman aktif jelas, form dan navigasi tidak bertumpuk.
- Enam capture lain disimpan oleh penguji untuk beranda/masuk/daftar/katalog pada 360 dan 1280 px. Tidak diklaim seluruhnya ditinjau manual.

Penguji final `browser-viewport-check.mjs --exercise-navigation` lulus untuk empat halaman publik pada 360/768/1280 px (12 kombinasi). Ia membuka menu responsif, memeriksa `aria-current`, keterlihatan, underline 2 px dan bobot teks 750, overflow, label dasar serta fokus awal skip-link. Pada admin daftar kursus, tiga lebar juga lulus melalui fixture `admin-input-check.ps1` dengan Chrome; akun/konten uji dibersihkan. Uji template pada PHP 8.0/8.1/8.5 mencakup 12 tujuan, konteks preview, visibilitas menu per akun, escaping dan input tak dikenal. Sepuluh kasus HTTP publik memeriksa alias/controller langsung dan query yang tidak mengubah penanda.

Tidak semua halaman mempunyai menu utama yang cocok: misalnya pengerjaan latihan, game dan keamanan akun tidak dipaksa menandai tujuan yang berbeda. Navigasi kontekstual/breadcrumb menyeluruh tetap pekerjaan terpisah. Penanda aktif tidak memerlukan JavaScript. Perubahan berikutnya menambahkan [fallback menu tanpa skrip](../2026-10-15-navigation-fallback/README.md); baseline ini menggambarkan keadaan sebelum fallback tersebut.

## Zoom dan temuan yang masih terbuka

Pengulangan penuh terakhir pada native zoom 200% lulus 12 kombinasi publik dengan menu terbuka, viewport CSS 360/384/640, pemeriksaan teks/fokus keyboard dan validasi form kosong. DPR/page zoom 2 serta pinch/CSS zoom 1 diverifikasi. Namun run sebelumnya gagal pada pemeriksaan fokus halaman daftar; hasil sukses berikutnya tidak menjelaskan penyebab kegagalan itu. Diagnosis harness kini menyebut route/lebar dan hanya elemen gagal agar kasus berikutnya dapat diisolasi. Belum boleh menganggap kestabilan pemeriksaan fokus sepenuhnya selesai atau mengklaim telah memperbaiki penyebabnya.

## Mengulang pemeriksaan

```powershell
node scripts/navigation-http-check.mjs --base-url http://localhost/study/index.php/
node scripts/browser-viewport-check.mjs --base-url http://localhost/study/index.php/ --exercise-navigation
node scripts/browser-viewport-check.mjs --base-url http://localhost/study/index.php/ --exercise-navigation --browser-zoom 200
```

Gunakan direktori baru di bawah `docs/visual-baseline` bila mengambil capture ulang; penguji tidak menimpa gambar yang sudah ada. Kesalahan awal penempatan blok JavaScript pada harness menyebabkan satu run gagal sebelum capture. Blok dipindahkan ke lingkup pemeriksaan halaman dan run berikutnya lulus; kegagalan awal bukan kegagalan aplikasi.
