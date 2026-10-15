# Baseline visual panel pengelola — 15 Oktober 2026

Screenshot dibuat dengan `scripts/admin-input-check.ps1 -CaptureAdminVisualBaseline` memakai satu akun system admin dan satu kursus draf sintetis. Akun/course ditampilkan dengan nama uji yang mudah dibaca, lalu fixture dan event audit dihapus. Chrome login melalui form aplikasi dengan kredensial QA dari environment proses sementara; browser memakai profil terisolasi.

| Halaman | Ponsel 360 px | Desktop 1280 px |
| --- | --- | --- |
| Daftar kursus | [admin-courses-360.png](admin-courses-360.png) | [admin-courses-1280.png](admin-courses-1280.png) |

DOM diuji pada 360/768/1280 px, keyboard Tab awal, label aksesibel, dan geometri. Semua lulus tanpa overflow halaman. Screenshot ditinjau manual. Pada 360 px daftar tampil sebagai kartu supaya judul, status, informasi utama, dan aksi tetap terlihat; tabel dipakai mulai ukuran tablet/desktop. Ini tidak menggantikan UAT, audit WCAG/screen reader, zoom 200%, pemeriksaan halaman admin lain, ataupun visualisasi data banyak/pagination.
