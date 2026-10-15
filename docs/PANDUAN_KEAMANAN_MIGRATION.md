# Panduan Pengaman Migration Database

## Pemeliharaan satu kali: penomoran ID lokal 16 Oktober 2026

Atas permintaan pemilik, data existing `db_study` dinomori ulang mulai 1 tanpa mengubah isi bisnis atau password. Ini **bukan migration aplikasi**, tidak perlu diulang di server dan tidak boleh dijalankan berkala. ID besar akibat fixture yang dihapus bukan kerusakan database; uji selanjutnya harus memakai database QA terisolasi agar tidak kembali menaikkan counter operasional.

Sebelum operasi, akses HTTP Study saja ditutup sementara dengan 503 dan backup lengkap dibuat di luar webroot: `C:\xampp\study-private\db-backups\db_study-before-renumber-20261016-055743.sql` (47.888 byte; SHA-256 `C9BCCDE8B4A2607A3E7FD8A8F78A7F47216FFD64479169FEF2FD0BA8EB3C60F2`). Backup mengandung data/hash akun: jangan commit atau sajikan melalui web. File ada dan hash diperiksa; restore backup ini belum diuji. Ia mengembalikan **ID lama**, bukan keadaan akhir untuk deployment. Jangan restore di atas data baru tanpa review dan backup tambahan.

Skrip sekali jalan di folder privat yang sama memeriksa baseline tepat, mengunci baris, membaca data, lalu DELETE/INSERT tabel berisi di dalam transaksi (bukan TRUNCATE). Semua kolom dipertahankan kecuali ID dan referensi yang perlu dipetakan. FK checks hanya dinonaktifkan pada koneksi pemeliharaan dan diaktifkan kembali; 55 relasi diperiksa eksplisit agar tidak ada orphan. Referensi polimorfik `st_audit_events.entity_id` juga diperbarui, bukan hanya FK database. Isi seluruh 33 tabel dibandingkan dengan hasil pemetaan yang diharapkan sebelum COMMIT dan sesudah DDL counter. Reset AUTO_INCREMENT pada 29 tabel dilakukan terpisah setelah COMMIT karena ALTER TABLE tidak ikut rollback DML.

| Data | Sebelum | Sesudah |
| --- | --- | --- |
| Superadmin | ID 3634 | ID 1; username/password/peran tetap |
| Dompet Superadmin | ID 20, pemilik 3634 | ID 1, pemilik 1; saldo tetap 0 |
| Audit existing | ID 8053–8056 | ID 1–4; actor dan entity user 1; waktu/payload tetap |
| Role | ID 1–4 | Tetap ID 1–4 |
| Relasi user-role | user 3634, role 1 | user 1, role 1 |
| Migration | versi 13 | Tetap 13, bukan diubah menjadi 1 |

Setelah maintenance dilepas, login/panel/logout Superadmin berhasil dan menambah dua audit sah ID 5–6. Saat pemeriksaan akhir: user/wallet next ID 2, role 5, audit 7; 25 tabel kosong yang mempunyai AUTO_INCREMENT kembali 1. Empat tabel lain tidak memakai AUTO_INCREMENT (migration dan tiga tabel penghubung), sehingga tidak dibuatkan ID baru. Tidak ada baris login failure saat snapshot operasi. Data tidak dibuang; hanya penomoran ulang sesuai permintaan, backup awal tetap tersedia. Apache/MySQL proyek lain tidak dihentikan.

Sesi lama yang mengacu ID 3634 harus login ulang. Catatan akses privat dan handover diperbarui. Jangan mengubah tautan/data aplikasi lain berdasarkan pemetaan ini; cakupan hanya `db_study` lokal. Jangan memakai backup sebelum reset sebagai dump terbaru untuk migrasi server.

## Tujuan dan status

Migration mengubah susunan tabel atau kolom database. `up()` menerapkan perubahan; `down()` membatalkannya. Membatalkan perubahan dapat menghapus data, sehingga kegagalan atau penolakan harus menghentikan proses, bukan dianggap berhasil.

Pengaman yang diperbarui pada **15 Oktober 2026** menolak `down()` migration 001–008 bila salah satu tabel target masih berisi baris. Seluruh pemeriksaan dilakukan sebelum perintah perubahan pertama. Validasi hasil pemeriksaan pada migration 013 juga diperketat. SQL `up()`, versi skema, dan bentuk tabel tidak diubah oleh perbaikan ini: skema terbaru tetap migration 13 dengan 33 tabel.

Bukti yang selesai adalah pengujian lokal Windows, PHP CLI 8.1 dan MariaDB 10.4.32, ditambah unit tanpa database pada PHP 8.0/8.1/8.5. Database operasional `db_study` tidak diturunkan versinya. Hasil ini bukan bukti HTTP PHP 8.1, Linux/Nginx, konkurensi, atau kelulusan ulang seluruh `qa-local.ps1`.

Panduan ini melengkapi [Panduan Lingkungan dan Deployment](PANDUAN_LINGKUNGAN_DAN_DEPLOYMENT.md) serta [Panduan Pemulihan Database dan Gambar Pelajaran](PANDUAN_PEMULIHAN_MEDIA.md).

## Aturan penolakan

- Tabel target dihitung seluruh barisnya, bukan hanya baris berstatus aktif. Baris arsip maupun data bawaan tetap termasuk.
- Bila ada data, query COUNT gagal, atau hasil COUNT tidak valid, migration melempar exception dan berhenti sebelum menjalankan DDL, insert, atau update pada langkah tersebut.
- Hasil yang diterima adalah bilangan bulat nol atau string digit bernilai nol. Nilai hilang, `NULL`, array, boolean, pecahan, negatif, maupun string tidak valid tidak dianggap sebagai tabel kosong.
- Pada migration yang mempunyai beberapa target, semua COUNT diselesaikan dahulu. Pemeriksaan tidak diselingi penghapusan tabel atau kolom.
- Migration 009–012 mempertahankan pemeriksaan sesuai data fitur masing-masing. Pengaman pada kelompok ini tidak berarti setiap tabel harus kosong seluruhnya; jangan menyamakan cakupannya dengan 001–008 dan 013.

### Target yang diperketat

| Migration dan satu langkah pembatalan | Tabel yang wajib kosong sebelum perubahan | Perubahan yang dilindungi |
| --- | --- | --- |
| 001: 1 → 0 | 20 tabel inti; nama lengkap tercantum di bawah | Penghapusan skema inti |
| 002: 2 → 1 | `st_login_failures` | Penghapusan catatan pembatasan masuk |
| 003: 3 → 2 | `st_account_tokens`, `st_content_reviews`, `st_course_enrollments`, `st_user_roles`, `st_roles` | Penghapusan tabel peran dan akses |
| 004: 4 → 3 | `st_content_reviews`, `st_questions`, `st_lessons`, `st_courses` | Penghapusan metadata review, sumber, versi, dan akses |
| 005: 5 → 4 | `st_lessons` | Penghapusan hak materi dan penggantian nama kolom isi |
| 006: 6 → 5 | `st_users` | Penghapusan tahap belajar pengguna |
| 007: 7 → 6 | `st_flashcard_progress`, `st_flashcards`, `st_flashcard_decks` | Penghapusan kartu belajar dan progresnya |
| 008: 8 → 7 | `st_courses` | Penghapusan prasyarat kursus |
| 013: 13 → 12 | `st_lesson_images` | Penghapusan metadata gambar pelajaran |

Nama 20 tabel inti migration 001, ditulis lengkap agar target dapat dibandingkan dengan skema nyata:

```text
st_audit_events
st_lesson_progress
st_practice_answers
st_practice_attempts
st_session_questions
st_practice_sessions
st_entitlements
st_payment_orders
st_wallet_entries
st_wallets
st_package_courses
st_packages
st_question_options
st_questions
st_lessons
st_course_sections
st_courses
st_categories
st_family_links
st_users
```

`study_migrations` adalah tabel penanda versi, bukan salah satu dari 20 target penghapusan di atas. Pengujian tetap memasukkannya dalam perbandingan sebelum/sesudah.

### Empat role bawaan juga dilindungi

Migration 003 membuat empat role bawaan. Keberadaan empat baris tersebut sudah cukup untuk menolak 3 → 2, meskipun belum ada pengguna aplikasi. Tidak ada pengecualian otomatis untuk menghapus role bawaan. Karena itu, suite tidak menghapus empat role demi memaksa kasus “kosong” lulus dan tidak menjalankan kasus sukses kosong untuk migration 003.

Jangan mengosongkan tabel, menghapus role, atau melemahkan guard hanya agar perintah pembatalan berhasil. Penolakan adalah hasil pengamanan yang benar, bukan instruksi membersihkan data.

## Bukti pengujian yang selesai

### Unit tanpa database

`scripts/test-legacy-migration-failures.php` lulus pada PHP 8.0, 8.1, dan 8.5. Sebelum patch migration diterapkan, tes baru gagal pada inventaris `001 down()` yang belum memiliki preflight; setelah patch, seluruh pemeriksaan lulus:

- 26 jalur sukses `up/down` untuk migration 001–013.
- 142 kegagalan statement SQL/penulisan: exception menghentikan statement berikutnya.
- 43 lokasi COUNT: seluruh guard harus berada sebelum penulisan pertama.
- 860 kasus COUNT nonempty atau hasil rusak: tidak ada DDL, insert, update, maupun penulisan terdahulu sebelum guard menolak.
- 86 exception saat query atau pembacaan baris COUNT: tetap tanpa penulisan.
- 43 kontrol integer nol diterima; jalur sukses utama juga memakai string nol.
- Status transaksi seed role yang gagal harus menghentikan migration.

Untuk 001–008 dan 013, tes juga memastikan target COUNT sama persis dengan target DROP/ALTER, tanpa WHERE, target hilang, atau duplikasi COUNT yang menyamarkan tabel belum diperiksa. Nilai empat baris termasuk kasus nonempty. Peringatan PHP tidak diterima sebagai pengganti exception penolakan yang benar.

### Integrasi pada database sementara

`scripts/legacy-migration-guard-check.ps1` lulus dengan exit code 0. Satu eksekusi memakai **18 database sementara terpisah**:

1. Sembilan kasus nonempty: satu langkah pembatalan pada versi 1–8 dan 13 ditolak. Inventaris seluruh tabel, seluruh isi baris, digest struktur `SHOW CREATE TABLE`, dan penanda migration sebelum/sesudah sama persis.
2. Delapan kasus target kosong: versi 1, 2, 4, 5, 6, 7, 8, dan 13 dapat kembali satu langkah. Seluruh digest data, struktur, dan versi dibandingkan dengan baseline versi N−1 yang direkam sebelum naik ke N. Versi 0 berarti hanya tabel penanda migration.
3. Satu kasus histori matching yang sudah ada: 12 → 11 ditolak, versi 12, jumlah 32 tabel, dan baris histori yang diperiksa tetap sama. Cakupan kasus lama ini bukan perbandingan digest seluruh tabel.

Setiap database menggunakan nama baru berpola `db_study_legacy_migration_qa_<12hex>`. Pembungkus PowerShell memeriksa ulang versi/jumlah tabel melalui MySQL CLI, menghapus hanya nama scratch yang dibuat kasus tersebut, memastikan nama itu sudah tidak ada, lalu memulihkan environment proses. Seluruh 18 scratch schema dibersihkan dengan pemeriksaan target persis; tidak ada downgrade pada `db_study`.

Fixture integrasi memakai satu pola data sintetis per versi, bukan satu kasus terpisah untuk setiap tabel. Cakupan semua 43 posisi guard berasal dari unit; jangan mengklaim seluruh kombinasi data nyata telah diuji. Fixture gambar pada kasus ini hanya metadata buatan di scratch schema, tanpa mengubah berkas gambar operasional.

Regresi terarah berikut juga lulus dengan exit code 0:

- `scripts/migration-check.ps1`: pemasangan bersih versi 13/33 tabel, pemanggilan latest berulang, serta pemeriksaan constraint/transaksi yang tersedia pada suite.
- `scripts/lesson-media-schema-check.ps1`: constraint gambar, penolakan 13 → 12 saat metadata tersimpan, kegagalan CREATE, serta pembatalan kosong dan pemasangan ulang di scratch schema.

Ini hasil suite terarah, bukan pengujian ulang seluruh QA aplikasi.

## Menjalankan ulang secara lokal

Gunakan PowerShell dari folder proyek. Tes unit berikut tidak membuka koneksi database:

```powershell
Set-Location -LiteralPath 'C:\xampp\htdocs\study'
foreach ($migrationPhp in @(
  'C:\xampp\php\php.exe',
  'C:\tools\php81-study\php.exe',
  'C:\tools\php85-study\php.exe'
)) {
  & $migrationPhp scripts/test-legacy-migration-failures.php
  if ($LASTEXITCODE -ne 0) { throw 'Unit pengaman migration gagal.' }
}
```

Tes integrasi memerlukan MySQL/MariaDB QA lokal sudah aktif dan PHP CLI 8.1 dengan ekstensi aplikasi. Suite pengaman memakai TCP loopback `127.0.0.1`, akun administrator QA lokal `root` tanpa sandi untuk CREATE/DROP database sementara, serta environment development. Ini bukan konfigurasi produksi. Jangan mengganti target menjadi database operasional, host jarak jauh, atau menjalankan helper CLI sendiri dengan environment produksi.

Jalankan suite secara terpisah dan periksa exit code sebelum melanjutkan:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/legacy-migration-guard-check.ps1 `
  -Php81Path 'C:\tools\php81-study\php.exe' `
  -MysqlPath 'C:\xampp\mysql\bin\mysql.exe'
if ($LASTEXITCODE -ne 0) { throw 'Hentikan: pemeriksaan pengaman migration gagal.' }

powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/migration-check.ps1 `
  -Php81Path 'C:\tools\php81-study\php.exe' `
  -MysqlPath 'C:\xampp\mysql\bin\mysql.exe'
if ($LASTEXITCODE -ne 0) { throw 'Hentikan: regresi migration bersih gagal.' }

powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/lesson-media-schema-check.ps1 `
  -Php81Path 'C:\tools\php81-study\php.exe' `
  -MysqlPath 'C:\xampp\mysql\bin\mysql.exe'
if ($LASTEXITCODE -ne 0) { throw 'Hentikan: regresi skema gambar gagal.' }
```

Ketiga suite integrasi membuat dan menghapus database QA miliknya. Pola target masing-masing adalah `db_study_legacy_migration_qa_<12hex>`, `db_study_migration_qa_<12hex>`, dan `db_study_media_schema_qa_<12hex>`. Nama berpola serupa yang sudah ada bukan izin untuk menghapusnya secara manual atau massal.

## Batas penting: bukan rollback atomik

Yang dibuktikan adalah **penolakan sebelum perubahan pada satu langkah** ketika kondisi pengaman ditemukan. Bukan kemampuan membalik seluruh DDL yang sudah berjalan. MariaDB menjelaskan bahwa perintah seperti `ALTER TABLE`, `CREATE TABLE`, dan `DROP TABLE` menyebabkan implicit commit; transaksi biasa tidak menjadi pembungkus rollback untuk rangkaian perubahan tersebut. Lihat [dokumentasi MariaDB tentang implicit commit](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit).

Runner CI3 menjalankan migration satu per satu dan memperbarui penanda versi selama proses. Pada penurunan beberapa versi sekaligus, langkah awal dapat sudah mengubah skema sebelum guard pada langkah berikutnya menolak. Penyesuaian penanda akhir juga belum tentu tercapai bila proses berhenti. Karena itu:

- Guard per langkah tidak menjamin seluruh batch bebas perubahan.
- Nomor dalam `study_migrations` saja tidak cukup untuk menyimpulkan keadaan setelah kegagalan. Periksa tabel, kolom, index, constraint, dan isi yang benar-benar ada.
- COUNT bukan penguncian terhadap penulisan bersamaan. Hasil lokal tanpa penulisan paralel tidak membuktikan aman ketika aplikasi atau worker masih menulis.
- Hentikan penulisan aplikasi terlebih dahulu, siapkan cadangan yang dapat dipulihkan, dan uji rencana pada salinan terisolasi. Untuk gambar, database dan berkas privat harus dicadangkan sebagai pasangan yang konsisten.
- Jangan melakukan retry otomatis, menambahkan `IF NOT EXISTS` untuk menyembunyikan perubahan parsial, atau mengubah penanda versi secara manual agar tampak selesai.

## Checklist sebelum menyentuh lingkungan nyata

Checklist ini sengaja belum dicentang; bukti lokal tidak menggantikan persiapan lingkungan yang akan diubah.

- [ ] Pastikan host, nama database, versi aktual, dan akun yang dipakai benar; catat pemilik persetujuan perubahan.
- [ ] Hentikan semua penulisan aplikasi/worker dan verifikasi jendela pemeliharaan berjalan.
- [ ] Buat cadangan database serta media yang konsisten, lalu uji pemulihannya pada target terisolasi.
- [ ] Bandingkan skema aktual dengan migration; uji langkah yang direncanakan dan kegagalannya pada salinan data.
- [ ] Jika guard menolak, pertahankan data dan investigasi penyebabnya. Pilih perbaikan maju atau pemulihan yang ditinjau; jangan hapus data untuk melewati guard.
- [ ] Setelah tindakan yang disetujui, periksa kembali struktur/data, hak akses, dan smoke test sebelum membuka penulisan pengguna.

Uji Linux, konfigurasi database produksi, konkurensi, simulasi gangguan layanan, dan prosedur pemulihan operasional tetap merupakan gerbang terpisah.
