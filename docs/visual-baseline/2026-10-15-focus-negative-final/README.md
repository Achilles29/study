# Kontrol negatif akhir — kegagalan yang disengaja

15 Oktober 2026. Setelah snapshot lengkap dan perbaikan penantian load diintegrasikan, halaman daftar kembali diuji pada native zoom 200% dengan `--focus-evidence-negative-control`.

Exit 1 yang diharapkan: elemen brand (A indeks 1) tetap terlihat, dokumen berfokus, `:focus-visible` aktif, tetapi outline sengaja dibuat `none` di DOM browser sementara. JSON berstatus gagal dan PNG viewport disimpan sebelum langkah Tab berikutnya. Snapshot mencakup indeks, hit-stack, warna outline, dan font `loaded`. Tidak ada perubahan CSS aplikasi. PNG run akhir ini belum ditinjau manual; screenshot kontrol awal telah ditinjau.

Ini bukan baseline desain dan bukan reproduksi gangguan intermiten. Lihat [laporan diagnostik](../2026-10-15-focus-public-final/README.md).
