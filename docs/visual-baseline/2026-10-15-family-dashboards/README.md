# Baseline visual dashboard keluarga — 15 Oktober 2026

Screenshot dibuat dengan `scripts/progress-check.ps1 -CaptureFamilyDashboardBaseline` memakai dua akun sementara buatan tes: seorang anak dan orang tua yang tertaut. Nama tampilan, judul kursus, dan bagian materi diubah sementara menjadi teks sintetis yang mudah dibaca untuk capture, lalu dipulihkan; blok `finally` menghapus akun, tautan keluarga, kursus, pelajaran, progres, dan audit QA. Pemeriksa Chrome login melalui form aplikasi menggunakan kredensial sintetis yang diberikan hanya sebagai environment proses, lalu profile browser sementara dibersihkan.

| Halaman | Ponsel 360 px | Desktop 1280 px |
| --- | --- | --- |
| Dashboard anak | [child-dashboard-360.png](child-dashboard-360.png) | [child-dashboard-1280.png](child-dashboard-1280.png) |
| Dashboard orang tua | [parent-dashboard-360.png](parent-dashboard-360.png) | [parent-dashboard-1280.png](parent-dashboard-1280.png) |

DOM diuji pada 360, 768, dan 1280 px. Semua route lulus tanpa overflow dokumen, isu aksesibilitas heuristik, atau kegagalan fokus Tab awal. Screenshot diperiksa manual. Pada ponsel, username QA yang panjang membungkus menjadi beberapa baris tetapi tidak memotong layout; nilai ini hanya nama uji, bukan contoh username produk.

Ini baseline dashboard keluarga saja; detail pengaturan keluarga, halaman edit anak, dashboard peserta dewasa, panel pengelola, zoom 200%, screen reader, navigasi keyboard penuh, serta UAT masih terbuka.
