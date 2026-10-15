# Latihan mandiri kursus — 15 Oktober 2026

Capture dibuat melalui `scripts/practice-check.ps1 -CaptureScreenshots` pada server lokal PHP 8.1. Semua akun, soal, hasil, dan judul merupakan fixture sintetis yang dibersihkan setelah pengujian. Teks markup dan penanda pembahasan sengaja ditampilkan sebagai teks dalam fixture untuk membuktikan escaping dan penyimpanan snapshot; ini bukan materi yang akan diterbitkan.

Empat screenshot berikut ditinjau secara visual:

| Halaman | Bukti |
| --- | --- |
| Editor soal, desktop | [1280 px](question-editor-1280.png) |
| Pengerjaan, ponsel | [360 px](practice-active-360.png) |
| Hasil dan pembahasan, ponsel | [360 px](practice-result-360.png) |
| Riwayat dengan pagination, desktop | [1280 px](practice-history-1280.png) |

Kontrol, pilihan, isi kartu, pembahasan, dan navigasi terlihat tanpa tumpang tindih. Warna radio/seleksi mengikuti tema anak, sementara editor memakai tema pengelola. Chrome juga memeriksa editor, tampilan reviewer, pengantar, pengerjaan, hasil, dan riwayat pada 360/768/1280 px; tidak ditemukan overflow dokumen, kegagalan fokus skip-link, atau isu label dalam pemeriksaan heuristik setelah tombol hapus memiliki nama aksesibel eksplisit.

Ini bukti layout dan perilaku fixture, bukan UAT materi per usia, audit pembaca layar lengkap, zoom 200%, ataupun uji banyak perangkat fisik. Perubahan bahasa, konten nyata, gambar, dan tema perlu ditinjau ulang ketika tersedia.
