# Pemeriksaan tampilan gambar pendamping pelajaran

Tanggal: 15 Oktober 2026. Chrome 155, Windows, Apache XAMPP PHP 8.0.30; PHP 8.1.34 dipakai untuk normalisasi fixture melalui CLI. Ini pemeriksaan tampilan fitur lokal, bukan persetujuan desain konten akhir atau UAT.

## Bukti yang diperiksa

- [Editor 360 px](lesson-media-360.png) dan [editor 1280 px](lesson-media-1280.png): gambar saat ini, perubahan keterangan, konfirmasi pelepasan, dan formulir unggah yang dinonaktifkan bila GD tidak tersedia.
- [Pratinjau 360 px](lesson-image-preview-360.png) dan [pratinjau 1280 px](lesson-image-preview-1280.png): tema sekolah dasar, gambar, keterangan, deskripsi alternatif, sumber dan hak penggunaan, serta teks pelajaran.
- Keempat capture ditinjau: tidak terlihat tumpang tindih atau teks terpotong. Editor yang panjang dapat digulir; tabel desktop tidak dipaksakan ke ponsel. Menu dan bagian detail dibuka oleh harness pemeriksaan.
- Pemeriksaan browser editor dan pratinjau pada masing-masing 360/768/1280 px lulus: tanpa overflow, kegagalan heuristik label atau fokus skip-link. Pemeriksaan ini bukan audit aksesibilitas penuh, screen reader, keyboard seluruh alur, atau zoom 200%.

Gambar 32 × 20 piksel sengaja berupa PNG sintetis kecil; ini bukan ilustrasi pembelajaran atau bukti mutu tampilan gambar besar. Teks markup pada keterangan/deskripsi adalah fixture stored-XSS yang terlihat sebagai teks biasa, bukan HTML aktif. Nama akun/kursus/materi adalah data sintetis. Tidak ada data anak nyata atau kunci penyimpanan pada halaman. Berkas dan baris fixture dibersihkan sesudah suite.

## Cara mengulang

Jalankan `scripts/lesson-media-check.ps1` dengan `-Php81Path`, `-MysqlPath`, `-BaseUrl` loopback, dan `-ChromePath`. Tambahkan `-CaptureScreenshots` hanya untuk capture; pasangan screenshot yang sudah lengkap dipertahankan, pemeriksaan browser tetap diulang. Jangan menimpa bukti lama secara diam-diam.

Pada Apache lokal tanpa GD, `-AllowUnavailableUpload` menerima respons 503 yang memang diharapkan untuk unggahan dan mencatat keterbatasan tersebut; opsi itu **tidak mengubah hasil menjadi unggah sukses**. PNG fixture dibuat dan dinormalisasi melalui CLI 8.1, kemudian endpoint HTTP diuji untuk akses, metadata, revisi, pelepasan, review/publikasi, dan pembacaan.

## Batas yang masih terbuka

- Unggahan multipart sukses melalui HTTP PHP 8.1 dengan GD/fileinfo, termasuk PNG/JPEG dekat batas 3 MiB, wajib diuji sebelum fitur dinyatakan lengkap.
- Capture hanya satu tema peserta dan gambar kecil. Gambar besar/portrait, berbagai usia, kontras lengkap, screen reader dan UAT perlu diuji tersendiri.
- Uji library mencakup PNG/JPEG, normalisasi dan batas berkas; pembuatan symlink pada lingkungan Windows ini dilewati karena izin tidak tersedia. Bukan bukti perilaku symlink Linux.
- Backup/restore berpasangan antara database dan berkas, rekonsiliasi orphan, filesystem penuh serta putus koneksi setelah COMMIT belum terbukti oleh capture atau suite ini.
