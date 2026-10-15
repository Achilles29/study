# Baseline panel jejak audit — 15 Oktober 2026

Screenshot `audit-list-1280.png` dan `audit-filtered-360.png` ditinjau manual: tabel desktop dan kartu ponsel terbaca, kontrol tanggal/filter tidak terpotong, ringkasan filter dan pagination terlihat. Semua akun, ID dan peristiwa adalah fixture sintetis yang sudah dibersihkan; nama pada navigasi desktop adalah nama akun penguji, bukan data pengguna nyata.

`user-directory-check.ps1` menjalankan `audit-log-cases.ps1`. Chrome memeriksa daftar dan hasil filter audit pada 360/768/1280 px: tidak ada document overflow, kegagalan label aksesibel heuristik atau fokus skip-link. Dua rute direktori pengguna juga diperiksa pada tiga ukuran tersebut. Bukti ini bukan audit aksesibilitas menyeluruh, zoom 200%, pembaca layar, atau UAT.

Panel hanya membaca ID, waktu, aksi dan entitas. Metadata mentah serta correlation ID tidak dimuat. Panel tidak menyatakan bahwa seluruh kegiatan aplikasi sudah diaudit.
