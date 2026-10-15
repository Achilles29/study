# Panduan Pemulihan Database dan Gambar Pelajaran

## Tujuan dan status bukti

Gambar pelajaran mempunyai dua bagian yang harus cocok: catatan di database dan berkas gambar di folder privat. Memulihkan salah satunya saja dapat menghasilkan gambar hilang, gambar yang salah, atau berkas yang tidak lagi mempunyai catatan. Panduan ini menjelaskan latihan pemulihan **pasangan database dan berkas**, bukan pengganti kebijakan backup produksi.

**Bukti 15 Oktober 2026:** drill terisolasi `scripts/media-backup-check.ps1` dengan helper CLI `Check_media_backup.php` lulus dua eksekusi lengkap dengan exit code 0. Eksekusi terakhir juga meluluskan enam pemeriksaan penolakan target tidak valid sebelum autoload controller/database. Skema yang diperiksa adalah migration 13 dengan 33 tabel. Keberhasilan ini berlaku untuk fixture lokal yang dijelaskan di bawah, bukan pemulihan produksi.

Panduan lingkungan, lokasi penyimpanan, dan batas unggahan tetap mengacu pada [Panduan Lingkungan dan Deployment](PANDUAN_LINGKUNGAN_DAN_DEPLOYMENT.md). Relasi gambar dan kepemilikannya dijelaskan dalam [Model Data dan Kepemilikan](MODEL_DATA_DAN_KEPEMILIKAN.md).

### Ringkasan hasil yang terkonfirmasi

- Seluruh 33 digest isi baris dan struktur `SHOW CREATE TABLE` hasil pemulihan cocok dengan sumber; byte ketiga gambar PNG/JPEG juga cocok.
- Pemeriksaan model untuk tamu, peserta aktif, pengguna tanpa enrollment, dan pengguna yang enrollment-nya dicabut memberikan hasil sesuai hak akses, sebelum dan sesudah pemulihan.
- Ketidakcocokan hash dump pada manifest ditolak sebelum impor. Berkas hasil pemulihan yang hilang, rusak, tertukar, atau bertambah tanpa metadata ditolak oleh pemeriksaan integritas/inventaris.
- Salinan hasil pemulihan kembali cocok setelah percobaan negatif. Digest sumber sebelum/sesudah snapshot cocok; pemeriksaan akhir mengonfirmasi integritas gambar sumber serta snapshot dump/gambar tetap terjaga.
- Dua database sementara, gambar buatan, dump/manifest, dan folder QA milik masing-masing eksekusi telah dibersihkan; environment proses dikembalikan. Database dan gambar operasional tidak menjadi target pemulihan atau pembersihan.

Smoke test akhir juga lulus dengan exit code 0: lint 330 berkas PHP pada PHP 8.0/8.1/8.5, penolakan HTTP 404 pada empat rute helper `check_media_backup`, serta pemeriksaan smoke Apache. Ini bukan pengujian ulang seluruh orkestrator QA, bukan unggah HTTP PHP 8.1, dan bukan uji Ubuntu/Nginx/PHP-FPM.

Dua percobaan awal berhenti karena urutan nama tabel dari collation `INFORMATION_SCHEMA` berbeda dari urutan perbandingan PHP. Perbaikan mengurutkan nama di PHP lalu tetap membandingkannya dengan daftar 33 tabel yang persis diizinkan; guard tidak dilonggarkan. Pembersihan percobaan yang gagal telah diverifikasi sebelum dua eksekusi lengkap yang lulus.

## Ruang lingkup yang aman

Drill tidak memakai isi `db_study`, tidak menulis ke database operasional, dan tidak mengambil maupun mengganti gambar operasional. Ia membuat data buatan dalam dua database baru serta folder sementara khusus. Nama acak satu eksekusi harus sama pada seluruh lokasi berikut:

| Bagian | Lokasi yang diizinkan |
| --- | --- |
| Database sumber buatan | `db_study_media_backup_qa_<12hex>` |
| Database hasil pemulihan | `db_study_media_restore_qa_<12hex>` |
| Akar folder sementara | `<systemTemp>/study-media-backup-qa-<12hex>` |
| Gambar sumber | `source/media` di bawah akar tersebut |
| Salinan cadangan | `snapshot/database.sql`, `snapshot/manifest.json`, dan `snapshot/media` |
| Gambar hasil pemulihan | `restored/media` di bawah akar tersebut |

`<12hex>` berarti tepat 12 karakter heksadesimal huruf kecil, bukan teks yang diketik operator. Guard database menerima pola `db_study_media_(backup|restore)_qa_[a-f0-9]{12}` **dan** harus cocok dengan dua nama yang dibuat eksekusi tersebut. Guard folder memeriksa path absolut/kanonis, batas folder eksekusi, dan symlink/junction pada jalurnya. Nama yang mirip saja bukan izin menghapusnya.

Enam target invalid berikut telah ditolak pada fase environment, sebelum autoload controller/database:

1. Nama database operasional `db_study`.
2. Host selain loopback yang diwajibkan.
3. Suffix database tidak cocok dengan folder eksekusi.
4. Akar system Temp dipakai langsung, bukan subfolder unik milik eksekusi.
5. Database sumber dipasangkan dengan direktori `restored/media`.
6. Path media memuat traversal `..`, meskipun tampak kembali ke lokasi yang sama.

Sumber dibuat khusus oleh drill dan tidak menerima penulisan lain setelah penyiapan selesai. Keadaan tanpa penulisan ini disebut *quiescent*. Kesamaan digest sebelum dan sesudah pengambilan cadangan diperiksa sebagai pengaman tambahan. Ini bukan snapshot atomik database dan filesystem ketika aplikasi sedang melayani penulisan pengguna.

## Prasyarat dan perintah lokal

Gunakan Windows QA lokal dengan PHP CLI 8.1, ekstensi database yang dipakai aplikasi, GD berkemampuan PNG/JPEG, serta fileinfo. MySQL/MariaDB harus tersedia melalui TCP loopback `127.0.0.1`. Versi skrip ini memakai akun administrator lokal `root` tanpa sandi untuk membuat dan menghapus dua database sementara; jangan arahkan ke server produksi, mengganti host menjadi server lain, atau melemahkan guard agar sesuai akun produksi.

Path PHP, MySQL, dan mysqldump yang tersedia pada lingkungan lokal saat panduan disusun:

```powershell
Set-Location -LiteralPath 'C:\xampp\htdocs\study'
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/media-backup-check.ps1 `
  -Php81Path 'C:\tools\php81-study\php.exe' `
  -MysqlPath 'C:\xampp\mysql\bin\mysql.exe' `
  -MysqlDumpPath 'C:\xampp\mysql\bin\mysqldump.exe'
```

Perintah ini hanya untuk latihan terisolasi tersebut. Jangan mengganti target database menjadi `db_study`. Jalankan satu eksekusi sampai selesai dan simpan keluaran ringkasnya; pesan `PASS` tahap awal belum berarti seluruh drill dan pembersihan lulus. Helper `cli/check_media_backup` ditujukan bagi skrip ini, bukan prosedur restore manual atau endpoint HTTP.

`scripts/qa-local.ps1` juga menyediakan opsi `-RunMediaBackupRestoreCheck` untuk menyertakan drill ini. Opsi tersebut terpisah dari `-RunBackupRestoreCheck`, yaitu pemeriksaan lama terhadap database aplikasi yang harus kosong dari data aplikasi. Jangan menukar keduanya atau membersihkan data operasional agar guard pemeriksaan lama meluluskan tes. Bukti pada panduan ini berasal dari eksekusi drill mandiri dan smoke test; orkestrator QA lengkap **tidak dijalankan ulang** pada finalisasi ini.

Drill tidak membutuhkan server web. Runtime web PHP 8.1 lokal saat penulisan panduan sedang tidak berjalan; panduan ini tidak meminta menyalakan ulang server tersebut. PHP CLI 8.1 dijalankan langsung untuk setiap pemeriksaan. Karena itu hasil drill tidak membuktikan unggah, autentikasi HTTP, header gambar, atau konfigurasi PHP-FPM/Nginx.

## Apa yang diperiksa

### 1. Menyiapkan sumber buatan

Skrip membuat skema sumber kosong, menjalankan migration 13, lalu menyiapkan empat pengguna buatan: editor, reviewer, peserta aktif, dan pengguna dengan enrollment dicabut. Fixture memuat dua kursus, tiga pelajaran, dan dua enrollment. Dua gambar PNG dan satu JPEG dinormalisasi melalui library aplikasi. Catatan revisi, publikasi/review, dan audit ikut diperiksa; data ini bukan materi produksi atau akun yang boleh dipakai pengguna.

Fixture mempunyai 11 tabel berisi dan 22 tabel kosong. Seluruh tabel tetap masuk dump dan perbandingan struktur/isi, tetapi tabel kosong tidak membuktikan pemulihan data nyata untuk semua fitur, seperti pembayaran atau histori latihan. Ukuran fixture juga tidak mewakili volume operasional.

Pemeriksaan akses dijalankan melalui model aplikasi pada CLI, meliputi:

- Tamu dapat mengakses materi gratis yang dipublikasikan.
- Tamu dan pengguna tanpa enrollment tidak mendapat akses materi berbayar.
- Peserta dengan enrollment aktif mendapat akses materi berbayar.
- Pengguna yang enrollment-nya dicabut tidak mendapat akses materi berbayar.

Hasil tersebut membuktikan pemeriksaan model dalam fixture, bukan seluruh lapisan otorisasi HTTP.

### 2. Membuat satu pasangan cadangan

Satu dump mencakup seluruh database sumber, tidak hanya tabel gambar. Dump tidak boleh berisi perintah `CREATE DATABASE` atau `USE` yang memilih database lain. Tujuan pemulihan harus berupa database sementara kedua yang masih kosong.

Manifest mencatat versi skema, inventaris 33 tabel, jumlah baris setiap tabel, digest SHA-256 seluruh isi baris, dan digest SHA-256 struktur `SHOW CREATE TABLE`. Jadi kesamaan jumlah baris saja tidak cukup. Ketiga gambar dicatat menurut kunci penyimpanan, MIME, jumlah byte, serta SHA-256; berkas salinan harus cocok persis dengan inventaris dan hash tersebut.

Hash dump juga disimpan dalam manifest dan diperiksa sebelum impor. Manifest dengan hash dump yang sengaja salah harus ditolak sebelum pemulihan. Hash di sini membantu mendeteksi ketidakcocokan/kerusakan; manifest lokal ini bukan tanda tangan digital, enkripsi, atau bukti bahwa pihak yang dapat mengubah cadangan tidak memalsukannya.

### 3. Memulihkan ke tujuan terpisah

Dump diimpor ke database pemulihan yang baru dan kosong, lalu gambar disalin ke `restored/media`. Aplikasi menggunakan `STUDY_MEDIA_ROOT` milik tujuan ini, bukan folder sumber maupun folder gambar operasional.

Sesudah pemulihan, seluruh digest isi dan struktur 33 tabel serta byte ketiga gambar harus sama dengan sumber. Pemeriksaan revisi, audit, metadata, integritas berkas, dan akses model dijalankan kembali. Drill tidak mengganti konfigurasi web agar menunjuk hasil pemulihan dan tidak mempromosikan database ini menjadi database aktif.

### 4. Menguji cadangan yang tidak cocok

Percobaan berikut hanya mengubah **salinan hasil pemulihan**, bukan sumber atau direktori snapshot:

| Percobaan | Hasil yang wajib terjadi |
| --- | --- |
| Salah satu berkas tidak ada | Inventaris atau integritas menolak keadaan tersebut. |
| Isi berkas rusak | Ukuran/hash atau pembacaan integritas menolak berkas. |
| Byte gambar ditukar dengan gambar lain | Nama berkas yang tampak benar tidak meloloskan hash yang berbeda. |
| Ada berkas tambahan tanpa metadata (*orphan*) | Inventaris tidak cocok dan pemeriksaan gagal. |

Setelah setiap percobaan, salinan dikembalikan dari snapshot lalu diperiksa kembali. Pemeriksaan akhir memastikan snapshot tidak berubah, sumber tetap sesuai, dan hasil pemulihan kembali cocok. Deteksi orphan pada drill bukan layanan pembersihan orphan operasional; aplikasi belum boleh menghapus berkas nyata hanya karena tidak terlihat dalam satu pembacaan database.

### 5. Membersihkan hanya milik eksekusi tersebut

Pembersihan dilakukan pada blok akhir, termasuk ketika pengujian gagal. Skrip hanya menghapus dua database yang tercatat dibuat eksekusi tersebut, berkas buatan yang tervalidasi, dan folder QA yang sudah kosong. Folder tidak dihapus secara rekursif secara membabi buta. Environment proses sementara dikembalikan ke nilai sebelumnya.

Jika ada path tidak kanonis, symlink/junction, berkas tak dikenal, atau hasil pemeriksaan yang tidak aman, pembersihan harus berhenti dan melaporkan data QA yang dipertahankan. Lulusnya pengujian inti tanpa kepastian pembersihan bukan hasil akhir yang bersih. Karena artefak normal dibersihkan setelah drill, latihan ini juga bukan mekanisme menyimpan cadangan jangka panjang.

## Bila guard menolak atau data QA tertinggal

1. Simpan nama tahap yang gagal dan keluaran kesalahan. Jangan mencetak kredensial, isi dump, kunci gambar, atau data pengguna dalam laporan umum.
2. Periksa secara hanya-baca nama database, suffix eksekusi, path absolut, jenis berkas, serta alasan penolakan. Pastikan objek benar-benar dibuat oleh eksekusi itu; kemiripan nama tidak cukup.
3. Jangan menghapus data operasional, catatan login, atau objek yang asalnya tidak terbukti untuk memaksa tes lulus. Drill ini terpisah dari pemeriksaan backup `db_study` yang mempunyai guard sendiri.
4. Jangan mengubah nomor migration, menambahkan `IF NOT EXISTS`, mengimpor ulang secara buta, atau melakukan `DROP`/hapus rekursif ke target umum. DDL yang gagal dapat memerlukan inspeksi skema dan rencana pemulihan tersendiri.
5. Setelah penyebab dipahami, tentukan pembersihan atau pemulihan dengan target tepat dan tinjauan operator. Data yang dipertahankan karena guard bukan alasan melemahkan guard. Rekonsiliasi dan pembersihan orphan otomatis belum tersedia.

## Checklist pemulihan untuk lingkungan nyata

Semua butir di bawah **belum dicentang untuk produksi**. Keberhasilan drill buatan tidak menyelesaikan checklist ini.

- [ ] Tetapkan penanggung jawab, cakupan database/media/config rahasia, jadwal, retensi, enkripsi, lokasi cadangan terpisah, dan akses operator yang tercatat.
- [ ] Tentukan batas kehilangan data yang dapat diterima (RPO) dan waktu pemulihan yang ditargetkan (RTO); ukur lewat latihan, bukan perkiraan.
- [ ] Pilih jendela tanpa penulisan atau mekanisme snapshot terkoordinasi yang dibuktikan. Hentikan penulisan konten/media yang relevan selama pengambilan pasangan; jangan menganggap `--single-transaction` membuat filesystem ikut atomik.
- [ ] Ambil database dan folder media sebagai satu set, beserta versi aplikasi/skema dan manifest inventaris/hash. Lindungi manifest bersama cadangan; tentukan verifikasi keaslian dan pengelolaan kunci enkripsinya.
- [ ] Siapkan lingkungan restore terisolasi dengan database, kredensial, root media, dan konfigurasi web berbeda. Matikan pengiriman pesan, pembayaran, atau pekerjaan terjadwal yang tidak boleh berjalan dari salinan.
- [ ] Pulihkan pasangan yang sama tanpa menimpa database/folder aktif. Verifikasi seluruh tabel/struktur, metadata, byte berkas, serta laporan berkas hilang/rusak/tambahan.
- [ ] Tinjau transaksi berstatus belum pasti sebelum menentukan bahwa suatu berkas boleh dibersihkan. Jangan memakai daftar orphan dari satu snapshot sebagai izin hapus otomatis.
- [ ] Buktikan hak akses filesystem dan guard `STUDY_MEDIA_ROOT` pada Ubuntu/PHP-FPM/aaPanel; pastikan gambar privat tidak dapat dibaca lewat alias atau URL berkas langsung.
- [ ] Uji akses HTTP tamu, peserta aktif, peserta dicabut, pemilik draf, dan reviewer; uji juga GET/HEAD, upload/ganti/lepas, metadata, konflik revisi, dan integritas gambar melalui runtime web target.
- [ ] Uji batas ukuran, kapasitas disk, kegagalan pemulihan, dan prosedur kembali ke keadaan aman. Jangan memulihkan sebagian lalu membuka aplikasi tanpa pemeriksaan.
- [ ] Catat durasi dan hasil nyata, persetujuan operator, rencana pergantian ke hasil restore, serta rencana pembatalannya sebelum mengaktifkan kembali penulisan.
- [ ] Ulangi latihan dengan cadangan nyata yang telah dilindungi dan salinan data yang sesuai kebijakan privasi; jadwalkan evaluasi berkala.

## Batas kesimpulan

Drill ini sengaja terbatas: data sintetis kecil, sumber tanpa penulisan bersamaan, CLI PHP 8.1, serta MySQL/MariaDB loopback lokal. Ia bukan daemon backup, penjadwal/retensi, backup terenkripsi, sertifikasi RPO/RTO, bukti beban besar, bukti Linux, ataupun pemulihan produksi. Ia juga tidak membuktikan unggah multipart sukses melalui HTTP PHP 8.1. Hasil dan batas ini harus tetap ditulis bersama agar satu pemeriksaan tidak disalahartikan sebagai kesiapan operasional seluruh aplikasi.
