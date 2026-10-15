# Baseline laporan permainan keluarga — 15 Oktober 2026

Chrome headless, server PHP 8.1 loopback, hanya akun/konten sintetis. `matching-storage-check.ps1` dengan HTTP, Chrome dan capture lulus seluruh alur peserta serta empat halaman keluarga pada 360/768/1280 px. Pemeriksaan menemukan nol overflow, nol masalah label/nama kontrol pada heuristik, dan fokus skip-link bekerja. Semua fixture akun/tautan/run/log/konten/audit dibersihkan setelah pengujian.

Lima capture berikut ditinjau manual:

- [Daftar 360 px](matching-family-report-360.png) dan [1280 px](matching-family-report-1280.png): ringkasan 14 selesai, 1 aktif, 2 dihentikan; daftar 17 item dengan pagination 10. Ringkasan seluruh histori dan daftar terfilter diberi keterangan berbeda.
- [Hasil selesai 360 px](matching-family-result-360.png): 8 dari 8 pasangan, 9 pilihan, 1 belum cocok; rentang waktu termasuk jeda, tanpa isi kartu/tombol mutasi.
- [Progres aktif 360 px](matching-family-active-360.png): status sementara, 0 pasangan dan 3 pilihan belum cocok dari uji browser; tidak diklaim selesai.
- [Dashboard keluarga 360 px](matching-family-dashboard-360.png): tautan laporan per anak dan riwayat permainan pribadi membungkus dalam viewport.

Markup `<b>` pada judul/nama sengaja menjadi fixture escaping yang harus tampil sebagai teks. `Paging 100%_!` menguji pencarian literal; bukan konten pelajaran. Nama pengguna QA panjang juga bukan contoh nama final.

Uji HTTP membandingkan angka laporan dengan query sumber, mencakup semua status/kondisi kosong/filter, akun/peran/tautan aktif, lintas anak/keluarga, revokasi, arsip, parent kedua yang tertaut dan POST terlarang. Digest state/snapshot/revisi serta jumlah log/audit game/ledger sama sebelum/sesudah laporan. Ini belum audit pembaca layar, zoom 200%, UAT, Linux/Nginx, retensi atau uji request serentak.
