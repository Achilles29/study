# Baseline permainan tersimpan — 15 Oktober 2026

Capture berasal dari Chrome headless terhadap server PHP 8.1 loopback, akun/konten sintetis. Skrip `matching-storage-check.ps1` dengan `-BaseUrl`, `-ChromePath` dan `-CaptureScreenshots` lulus: halaman mulai, permainan aktif, konfirmasi berhenti, hasil, riwayat dan run interaksi pada 360/768/1280 px. Tidak ada overflow dokumen/elemen, kontrol tanpa label pada pemeriksaan heuristik, atau kegagalan fokus skip-link. Run interaksi memakai Space native pada radio, klik kirim formulir, membaca hasil server, lalu refresh; tiga pilihan benar-benar tercatat satu kali per viewport.

Gambar berikut ditinjau manual dan disalin dari output sementara:

- [Mulai — 360 px](matching-saved-start-360.png): penjelasan pencatatan, koneksi, jeda dan tombol mulai terbaca.
- [Permainan — 360 px](matching-saved-run-360.png) dan [1280 px](matching-saved-run-1280.png): kartu dua kolom, target sentuh/radio, teks panjang membungkus dan tidak menimpa tombol.
- [Hentikan — 360 px](matching-saved-stop-360.png): perbedaan istirahat/hentikan, checkbox konfirmasi dan tombol kembali jelas.
- [Hasil — 360 px](matching-saved-result-360.png): 8 pasangan, 9 pilihan dan 1 belum cocok; rentang waktu tidak diklaim sebagai waktu aktif.
- [Riwayat — 360 px](matching-saved-history-360.png): filter berlabel, 10 kartu, reset dan pagination tetap dalam viewport.

Teks `<b>`, `<script>`, nomor kartu teknis dan judul `Paging 100%_!` sengaja merupakan fixture escaping/pencarian, bukan materi pelajaran atau tampilan nama final. Seluruh fixture akun, run, pilihan, konten dan audit dibersihkan setelah tes. Capture tidak berisi pengguna nyata. Ini bukan bukti Linux/Nginx, UAT, pembaca layar, zoom 200%, konkurensi nyata atau simulasi putus jaringan.
