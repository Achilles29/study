# Database aktif pada aaPanel3306 — 081

21 Oktober2026. Pemilik meminta memindahkan Project Pintar ke3306 dan menjelaskan bahwa replikasi hanya untuk `db_finance`. Ini pemindahan database existing, **bukan instalasi ulang/migration aplikasi/reset data**.

## Keadaan aktif

- Database **`db_study`**, MariaDB aaPanel `127.0.0.1:3306`, service `mysqld.service`, socket `/tmp/mysql.sock`, konfigurasi `/etc/my.cnf`.
- Runtime akun **`pintar_runtime`@`127.0.0.1`**, hanya SELECT/INSERT/UPDATE/DELETE pada database Study. Tidak memakai root atau hak bypass read-only; akses Finance ditolak dalam pemeriksaan. Nama akun lama pada3306 berbenturan sehingga tidak diubah/ditimpa.
- Environment aktif `/etc/projectpintar/environment.conf`: DB_PORT3306, DB_DATABASEdb_study, DB_USERNAMEpintar_runtime. File tetap root0600. Password database, kunci enkripsi aplikasi, URL, media privat, dan seluruh data konfigurasi pembayaran/SSO tidak dirotasi.
- FPM dan timer retensi mengacu layanan MySQL aaPanel, tidak bergantung pada service MariaDB3308. Worker notifikasi membaca environment yang sama. Tidak mengubah URL/callback pembayaran Finance atau Project Pintar.
- aaPanel mendapatkan **satu** entri database baru `db_study`, userpintar_runtime, akses127.0.0.1; metadata database lain tidak diubah. Tidak menjalankan sinkronisasi massal panel.
- Schema aplikasi tetap52,85tabel. Berkas aplikasi dan media tetap pada lokasi sebelumnya.

## Replikasi Finance

- Sebelum perubahan: read_only1 pada3306; filter lokal semuanya kosong; IOConnecting/error2003, SQLYes/error0. Kegagalan menghubungi sumber **sudah ada sebelumnya**.
- Setelah perubahan: read_only0 untuk mengizinkan Study lokal, **replicate_do_db=db_finance**, disimpan pula dalam `/etc/my.cnf` agar bertahan setelah boot. Thread sempat dijeda untuk memasang filter kemudian dijalankan kembali; server MariaDB bersama tidak direstart.
- Host, port, akun sumber, berkas log dan posisi IO/SQL sama pada pemeriksaan sebelum/sesudah perubahan filter. Tidak menjalankan RESET SLAVE/CHANGE MASTER, tidak menyalin atau mengubah data Finance, tidak mengubah grant akun Finance.
- Status akhir IOConnecting/error2003 dan SQLYes/error0. Artinya kanal Finance dipertahankan tetapi **bukan klaim replikasi sedang tersinkron**. Perbaikan jaringan/sumber replikasi perlu pekerjaan terpisah.
- `read_only` adalah pengaturan server, bukan per-database. Pembatasan database runtime dilakukan dengan grant. Filter `replicate_do_db` mengikuti aturan MariaDB, termasuk batasan statement-based cross-database; tidak mengubah format logging sumber. Referensi: [Read-only replicas](https://mariadb.com/docs/server/ha-and-performance/standard-replication/read-only-replicas), [replication filters](https://mariadb.com/docs/server/ha-and-performance/standard-replication/replication-filters).

## Bukti pemindahan

1. Backup konfigurasi/source docs, dump preflight, snapshot replikasi, serta backup metadata aaPanel tersimpan privat di `/www/backup/projectpintar-port081-7YDEVfbG/` (root0700; dump/rahasia0600).
2. Gerbang HTTP503 hanya situs Study, FPM Study dijeda, lock worker notifikasi dipegang, timer retensi dijeda. Tidak mematikan Nginx/MySQL bersama/aplikasi lain.
3. Dump final `db_study.frozen.sql` dibuat setelah tidak ada query penulis aktif, lalu dipulihkan ke schema3306 yang sebelumnya tidak ada. Target kosong diperiksa; helper menolak menimpa tabel target existing.
4. **Seluruh85tabel cocok**: jumlah baris, CHECKSUM EXTENDED, SHOW CREATE TABLE, engine/collation dan AUTO_INCREMENT. ID, jawaban, saldo, audit, pengguna dan histori tidak direset. Manifest privat `source.manifest.json`/`target.manifest.json` menyimpan bukti.
5. Akun runtime3306 berhasil baca dan transaksi UPDATE-no-op/ROLLBACK tanpa mengubah nilai; percobaan baca Finance ditolak. Tidak membuat akun/sesi latihan atau transaksi pembayaran dummy pada produksi.
6. FPM konfigurasi valid/aktif, environment privat tetap0600. HTTP beranda/login/kursus200, rute privat mengarah autentikasi (HEAD303). Nginx kembali identik dengan konfigurasi awal; gerbang sementara dihapus. Timer dan worker dilepas kembali.

## Navicat dan cadangan lama

- Gunakan koneksi ke port **3306**, lalu Refresh daftar database. Nama database tetap **db_study**. Jika memakai akun yang hanya diizinkan untuk Finance, akun tersebut tidak otomatis dapat melihat Study.
- Untuk akun aplikasi yang terbatas localhost, gunakan SSH tunnel menuju server; host database127.0.0.1, port3306. Kredensial dapat dikelola melalui aaPanel; jangan memasukkan password ke dokumentasi/pesan/log. Tidak membuka port/firewall tambahan.
- Instance3308 dan datadir `/www/server/data-study` dipertahankan untuk cadangan/QA. read_only1 diterapkan runtime dan konfigurasi; **db_study di3308 adalah snapshot lama, bukan produksi aktif**. Akun root administratif tetap dapat menulis meskipun read-only; pengujian wajib menargetkan nama databaseQA, bukan db_study.
- Script QA historis dengan nama databaseQA eksplisit boleh tetap menunjuk3308. `scripts/verify-release-restore.php` adalah verifier rilis032 dengan sumber3308 hardcoded: **jangan dipakai untuk mengesahkan backup produksi baru** sebelum disesuaikan. Pemeriksaan081 memakai helper privat dan manifest di atas.
- Setelah situs dibuka, data baru masuk3306. Jangan rollback hanya dengan mengganti port kembali ke3308: perlu jeda tulis, backup data3306 terbaru dan rekonsiliasi/pemindahan balik agar perubahan baru tidak hilang.
- Database lama `study` pada3306 dan schemaQA tidak dihapus. Tidak ada server/datadir yang dihapus.
