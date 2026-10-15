# Baseline visual halaman belajar — 15 Oktober 2026

Screenshot diambil dengan Chrome headless lewat `scripts/progress-check.ps1 -CaptureVisualBaseline`. Script membuat kursus dan pelajaran sintetis yang hanya aktif selama capture, mengubah sementara teks fixture menjadi contoh pecahan untuk tinjauan layar, lalu memulihkan nilai QA dan menjalankan pembersihan fixture pada blok `finally`.

| Halaman | Ponsel 360 px | Desktop 1280 px |
| --- | --- | --- |
| Detail kursus | [course-detail-360.png](course-detail-360.png) | [course-detail-1280.png](course-detail-1280.png) |
| Pelajaran | [lesson-360.png](lesson-360.png) | [lesson-1280.png](lesson-1280.png) |

Pemeriksaan DOM juga dilakukan pada 768 px. Semua halaman yang ditangkap lulus tanpa overflow dokumen, masalah aksesibilitas heuristik, atau kegagalan fokus Tab pertama/skip-link. Screenshot diperiksa manual: judul, ringkasan, tujuan, materi, dan aksi tetap terbaca; tidak ada konten yang saling menimpa. Capture ini menggunakan akun tamu dan kursus pratinjau, bukan pengalaman pengguna yang masuk.

Ini baseline untuk detail kursus dan materi saja; dashboard peserta/orang tua/anak, panel pengelola, zoom 200%, pembaca layar, navigasi keyboard penuh, serta UAT belum dicakup.
