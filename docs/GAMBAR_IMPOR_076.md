# Banyak gambar per soal dan kapasitas unggahan — 076

**Pembaruan077:** gambar di dalam teks soal dan pada setiap pilihan jawaban sekarang didukung melalui `[GAMBAR: nama.png]`; editor juga menerima banyak lampiran. Batasan jawaban bergambar/editor satu gambar yang disebut pada riwayat076 di bawah sudah digantikan. Lihat [panduan terbaru077](GAMBAR_POSISI_DAN_JAWABAN_077.md).

20 Oktober2026. Dipasang tanpa migrasi/reset/impor konten produksi. Menggantikan batas10 gambar/4MiB dan satu gambar per soal pada rilis068–075.

## Cara memakai

1. Buka `/kelola/bank-soal/impor`. Panduan di bagian lampiran memuat langkah dan contoh yang membungkus pada ponsel.
2. Setelah pilihan soal5, tulis `GAMBAR: soal5a.png | soal5b.png`. Setelah pilihan soal7, tulis `GAMBAR: soal7.png`. Unggah ketiga berkas tersebut sekaligus.
3. Nama harus sama persis, termasuk huruf besar/kecil dan ekstensi. **Hubungan berdasarkan nama, bukan urutan memilih lampiran.** Urutan tampilan mengikuti daftar nama dalam GAMBAR. Satu berkas dapat dirujuk beberapa soal dan diunggah sekali.
4. Pada Word/Excel/CSV, isi kolom `gambar` dengan `soal5a.png | soal5b.png`, tanpa awalan GAMBAR. TXT berlabel lama juga menerima pemisah `|`. Jangan mengulang label GAMBAR dalam satu soal.
5. Klik Periksa berkas, tinjau gambar di bawah masing-masing soal, lalu konfirmasi simpan. Nama hilang/ganda, lampiran tidak dirujuk, ukuran/resolusi berlebih dan file bukan PNG/JPEG ditolak; tidak memilih pengganti atau mengabaikan gambar diam-diam.

Gambar tampil bersama di bawah pertanyaan, bukan otomatis disisipkan ke setiap opsi atau posisi tertentu dalam teks. Untuk opsi bergambar, gunakan teks pilihan “Gambar 1”, “Gambar 2”, dan seterusnya sesuai urutan daftar GAMBAR; nomor galeri tetap sama meskipun pilihan diacak. Gambar langsung di setiap tombol pilihan belum didukung. Validasi format tidak menjamin kebenaran kunci/materi/izin.

Tambahan petunjuk UI, 20 Oktober2026: blok/pilih seluruh gambar sekaligus lalu Buka/Open; Ctrl/Command+klik, Shift, pilih semua hanya bila seluruh folder diperlukan, serta cara pemilih berkas ponsel. Memilih ulang mengganti daftar sebelumnya. Contoh empat pilihan yang merujuk galeri tersedia di halaman impor. Uji terarah: lint PHP, halaman QA pada 1280/360 piksel tanpa overflow/galat JavaScript, disclosure dengan Enter dan relasi aria-describedby lulus. Tidak mengirim impor atau mengubah data produksi; perubahan hanya petunjuk tampilan.

## Implementasi dan batas

- [x] Banyak gambar per soal, galeri pada pratinjau/editor/peserta, nama berkas pada admin dan nomor gambar saja pada peserta. Seluruh endpoint gambar tetap privat dengan pemeriksaan akses dan index.
- [x] Fingerprint memakai isi dan urutan seluruh gambar, bukan nama berkas. Identitas soal satu gambar lama tidak berubah; tidak melakukan backfill/reset ID.
- [x] Snapshot menyimpan seluruh galeri. Perubahan bank tidak menghilangkan gambar riwayat latihan. Edit teks tanpa memilih pengganti mempertahankan galeri. Editor manual saat ini mengganti semua dengan **satu** berkas baru atau melepas semuanya, dengan keterangan jelas; editor tambah/hapus/urut per gambar belum dibuat.
- [x] Media baru dibersihkan saat validasi gagal sebelum upaya menulis DB. Jika upaya penulisan pratinjau sudah dilakukan, media dipertahankan ketika acknowledgement tidak pasti, agar tidak menghapus referensi yang mungkin sudah tersimpan. Media existing tidak dihapus.
- [x] Penghitung jumlah/ukuran browser dan validasi awal, dengan server tetap berwenang. Field expected-count mendeteksi upload terpotong; tanpa JS, validasi server tetap tersedia.

| Lapisan | Batas |
| --- | --- |
| Impor | 500 lampiran, total gambar32 MiB |
| Setiap gambar | PNG/JPEG ≤3 MiB, ≤4096 piksel per sisi, ≤4 juta piksel |
| Total resolusi | 64 juta piksel per batch untuk membatasi decoding |
| Dokumen | Tetap3 MiB/500 soal |
| Metadata per soal | ≤60.000 byte untuk kolom TEXT; field daftar nama tetap1000 karakter |
| FPM Project Pintar | post_max_size40M, upload_max_filesize4M, max_file_uploads502 |
| Vhost Project Pintar | client_max_body_size40m |

502 di PHP menyediakan500 gambar +dokumen +satu slot overflow supaya berkas berlebih dapat ditolak aplikasi. Batas pengguna tetap500 gambar, bukan502. Jika ukuran/resolusi terlalu besar, perkecil gambar atau bagi impor. Memilih lampiran kembali mengganti pilihan sebelumnya; pilih semua bersama.

Hanya `/etc/projectpintar/php-fpm.conf` dan vhost `/www/server/panel/vhost/nginx/www.projectpintar.com.conf` yang diubah. Shared php.ini, environment/secrets, Finance/Pustaka dan callback tidak diedit. FPM Project Pintar dan Nginx di-reload setelah syntax check; Nginx reload graceful.

## Bukti

- PHP8.4.21 Linux; lint10 file runtime PHP dan syntax JS lulus.
- `test-images076.php`:44 regresi075 +15 pemeriksaan identitas/order/rename/lookup/preservasi galeri, nama invalid dan collector menolak501 berkas.
- `test-images076.mjs` hanya DB `study_qa_monitor_sso039`:12 gambar dengan urutan upload dibalik, dua gambar pada soal pertama,500 lampiran multipart nyata, body PNG noise >8MiB berhasil. Total>32MiB ditolak tanpa penambahan bank/batch/media. Nama hilang/ganda/tidak terpakai, file palsu dan expected-count berbeda ditolak.
- Galeri primer/tambahan, index invalid, admin/parent/outsider, preview dan bank diuji. Simulasi menggunakan ID bank sama. Peserta melihat dua gambar; outsider404. Setelah bank melepas galeri, snapshot tetap menampilkan dua gambar; nilai100.00.
- Browser1280/360 tanpa overflow/JSerror; panduan keyboard, contoh membungkus, gambar benar-benar termuat, penghitung12 berkas dan validasi ukuran browser lulus. Screenshot ditinjau.
- Regresi HTTP/browser075 dan068,20 render practice view,85 unit read-access lulus. Fixture outsider dipilih dari akun QA yang sandinya masih sesuai, tanpa reset akun; pemeriksaan skor memakai DB100.00, bukan asumsi label UI100%.
- Source produksi cocok byte dengan staging. FPM aktif. Probe privat Unix socket produksi menerima500 gambar+1dokumen serta body12MiB utuh, tanpa aplikasi/DB/sesi; membuktikan setting runtime502/40M/4M. Probe sudah dipindah ke backup, bukan endpoint publik.
- HEAD produksi: impor303 ke login, JS helper200, script internal404. Multipart aplikasi terautentikasi diuji di QA; tidak memakai sesi admin nyata.

Skema51, bank/import produksi0. Checksum tujuh tabel tetap: questions3963196383, options3027569709, bank0, scopes0, users4191112837, payment_orders3709692070, wallets713850174. Tidak ada pembayaran nyata/data dummy produksi.

Backup `/www/backup/projectpintar-import076-gJ85dLB4`: SQL/source sebelum-sesudah, konfigurasi, probe privat dan screenshot. Staging `/www/study-private/import076-N1ORG3HE` bukan dependensi runtime. Saat pindah server, media privat existing dan limit runtime yang sesuai tetap dibutuhkan. Windows tidak diuji ulang; UAT pemilik tetap diperlukan.
