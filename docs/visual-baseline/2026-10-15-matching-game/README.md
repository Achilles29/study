# Tinjauan Cocokkan Pasangan

Capture pada PHP 8.1 loopback, 15 Oktober 2026, melalui `flashcard-check.ps1 -ChromePath ... -CapturePreviewScreenshots`. Kartu/nama peserta seluruhnya sintetis; teks bertanda `script`, `QA` dan `[removed]` sengaja dipakai untuk menguji escaping, bukan konten pembelajaran final.

- [Permainan pada ponsel, 360 px](matching-game-360.png)
- [Permainan pada desktop, 1280 px](matching-game-1280.png)

Tinjauan manual: tema anak sekolah, judul/instruksi terbaca, kedua kolom tetap muat, tombol cukup besar, teks panjang membungkus tanpa keluar kartu, progres dan mulai ulang terlihat, serta alternatif baca pasangan tersedia. Warna hasil dipadukan dengan teks/simbol; tidak ada timer, harga, atau klaim XP/nilai resmi. Screenshot sebelum interaksi memperlihatkan posisi awal; urutan kartu dapat berbeda karena pengacakan.

Browser 360/768/1280 px lulus tanpa overflow/isu label heuristik dan fokus awal skip-link. Uji interaksi memakai Enter nyata, pilihan salah lalu benar, menyelesaikan delapan pasangan dalam dua babak, penolakan klik berulang pada pasangan selesai, reset, dan media reduced motion; semua lulus. Uji Enter awal belum menyertakan karakter pada event keyboard CDP; setelah event dilengkapi, aktivasi tombol native terbukti tanpa handler keyboard khusus aplikasi.

Fallback baca pasangan tersedia pada HTML server; belum ada audit menyeluruh pembaca layar, zoom 200%, atau UAT anak/orang tua dengan konten nyata. Pengujian ini tidak membuktikan game berhadiah/anti-cheat: permainan memang tidak mengirim skor atau mengubah saldo/progres resmi.
