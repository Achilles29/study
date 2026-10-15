# Pratinjau pelajaran internal — 15 Oktober 2026

Capture dari `scripts/flashcard-check.ps1 -CapturePreviewScreenshots` pada Apache XAMPP memakai akun editor dan kursus draf sintetis. Isi berlabel QA sengaja dipakai untuk pemeriksaan fungsi, bukan contoh materi atau identitas visual final. Seluruh fixture dibersihkan sesudah suite.

| Halaman | Ponsel | Desktop |
| --- | --- | --- |
| Pratinjau tema usia dini | [360 px](lesson-preview-360.png) | [1280 px](lesson-preview-1280.png) |

Kedua screenshot ditinjau: pemilih tema, penanda pratinjau, materi, kartu belajar, dan tautan kembali terlihat tanpa tumpang tindih. Pemeriksaan Chrome pada 360/768/1280 px lulus tanpa overflow dokumen, isu label pada pemeriksaan otomatis, atau kegagalan fokus skip-link. Tidak ada formulir progres, penyelesaian, atau autosave dalam pratinjau. Kelima tema diuji melalui HTTP; screenshot ini hanya untuk tema usia dini dan belum menjadi UAT konten per usia, pemeriksaan screen reader, atau audit kontras lengkap.
