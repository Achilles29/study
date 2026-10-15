# Baseline visual halaman publik — 15 Oktober 2026

Screenshot dibuat dengan Chrome headless melalui `scripts/browser-viewport-check.mjs --capture-screenshots`, lalu diperiksa secara visual. Ukuran gambar 360 px (ponsel) dan 1280 px (desktop); pemeriksaan ukuran 768 px dilakukan lewat pengukuran DOM dalam skrip yang sama. Screenshot diambil setelah fokus keyboard dilepas agar fokus skip-link tidak menutupi tampilan normal.

| Halaman | Ponsel 360 px | Desktop 1280 px |
| --- | --- | --- |
| Beranda | [home-360.png](home-360.png) | [home-1280.png](home-1280.png) |
| Katalog | [catalog-360.png](catalog-360.png) | [catalog-1280.png](catalog-1280.png) |
| Masuk | [sign-in-360.png](sign-in-360.png) | [sign-in-1280.png](sign-in-1280.png) |
| Daftar | [sign-up-360.png](sign-up-360.png) | [sign-up-1280.png](sign-up-1280.png) |

Detail kursus dan pelajaran dengan materi sintetis yang lebih representatif tersedia di [baseline halaman belajar](../2026-10-15-learning-pages-reviewed/README.md). Dashboard orang tua/anak/peserta dan panel pengelola masih belum menjadi baseline visual.

Dashboard anak dan orang tua yang masuk menggunakan akun sintetis QA tersedia di [baseline dashboard keluarga](../2026-10-15-family-dashboards/README.md). Baseline ini tidak memuat akun atau progres pengguna nyata.

Panel pengelola kursus dengan akun admin sintetis ada di [baseline panel pengelola](../2026-10-15-admin-panel-final/README.md). Pada 360 px, tampilan tabel diganti kartu agar status dan aksi utama tetap terlihat.

## Catatan review

- Keempat halaman tidak menimbulkan overflow horizontal dokumen pada pemeriksaan viewport 360/768/1280 px. Nama kontrol aksesibel dan fokus Tab pertama/skip-link juga lolos pemeriksaan heuristik.
- Review menemukan stiker ilustrasi beranda menimpa teks kecil pada kartu. Teks yang redundan dihapus dan posisi stiker disesuaikan; screenshot baseline ini diambil setelah koreksi dan teks utama tidak tertutup.
- Katalog pada waktu capture memiliki nol kursus terbit, jadi gambar katalog merekam keadaan kosong, bukan daftar kursus.
- Ini hanya baseline empat halaman publik. Detail kursus, materi, dashboard peserta/orang tua/anak, panel pengelola, zoom 200%, navigasi keyboard seluruh halaman, pembaca layar, dan UAT belum tercakup.

Perbarui baseline secara sengaja ketika perubahan desain disetujui; jangan mengganti screenshot hanya untuk menyamarkan regresi visual.
