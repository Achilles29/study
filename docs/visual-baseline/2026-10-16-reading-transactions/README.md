# Kepastian simpan progres baca — 16 Oktober 2026

## Perubahan dan reproduksi

Tes baru pertama kali gagal pada `complete_lesson`: COMMIT FALSE masih dikembalikan sebagai TRUE oleh model lama. Kedua mutasi (`complete_lesson` dan `save_lesson_position`) kemudian dipindahkan ke `Study_transaction`. Baca pelajaran dan hak akses memakai current read, hasil query/upsert diperiksa, dan sukses hanya dikembalikan setelah COMMIT terkonfirmasi. Upsert, posisi 0–10000, status selesai terminal dan waktu selesai pertama dipertahankan. Tidak ada migration atau tabel baru.

`Catalog` memakai hasil terstruktur: 503 belum tersimpan/belum pasti tanpa query ulang sesudah model, tanpa flash lama, tanpa form/token progres. AJAX 503 tidak mengirim token untuk retry. POST HTML sukses memakai 303; penolakan akses biasa mempertahankan kontrak lama. View `catalog/recovery` memakai komponen pemulihan bersama latihan/permainan, dengan teks progres baca serta GET materi dan beranda. Form logout layout bukan form progres dan tetap diperbolehkan. Preflight akun/controller sebelum model dimulai tidak tercakup.

Autosave berhenti setelah respons HTTP gagal, transport gagal, JSON tidak sah, sukses tanpa token, atau watchdog 20 detik. Antrean dibuang dan form posisi/selesai dikunci; tautan GET manual tersedia. Respons terlambat tidak membuka ulang antrean. Submit native kedua form ditahan selama autosave berlangsung. Setelah sukses, semua token CSRF form halaman diperbarui, bukan hanya token posisi. Submit native yang diterima menghentikan autosave dari DOM lama; pemulihan `pageshow.persisted` melakukan GET ulang.

## Bukti pengujian

- `test-reading-transactions.php`: 127 assertion pada PHP 8.0.30, 8.1.34, dan kandidat 8.5. TRUE/FALSE/exception transaksi, query, hasil baca, domain, nested transaction, current-read SQL, binding, upsert, posisi batas dan debug diuji menggunakan recording fake. Bukan SQL/rollback atau putus koneksi database nyata.
- `test-reading-recovery.php`: 89 assertion per runtime untuk dua controller, AJAX/HTML, 503, pesan konservatif untuk failure tidak dikenal, 303 sukses, 403 domain, input/metode salah, slug server/escaping, serta renderer tanpa form/token/progres. Dependency setelah mutasi dijaga agar query tambahan gagal tes.
- Regresi unit akses current read 90, view latihan 199 dan view permainan 79 assertion per runtime lulus. Renderer CLI tiga konteks lulus 47 pemeriksaan per runtime. Unit daftar isi dan lint/naming diperiksa terpisah.
- `test-reading-save.mjs` dan seluruh `npm run test:ui` lulus: antrean sukses berurutan, rotasi token seluruh form, tujuh kelas kegagalan, penghentian retry, penguncian manual/selesai, timeout dan respons terlambat. `pageshow` diuji melalui event VM, bukan bukti BFCache browser nyata.
- `progress-check.ps1` lulus HTTP Apache PHP 8.0: akses gratis/pratinjau/paket/kedaluwarsa/dicabut, isolasi keluarga, autosave berurutan, simpan native, selesai dan penolakan penurunan status. `flashcard-check.ps1` lulus sebagai regresi view materi/akses/kartu. Fixture masing-masing dibersihkan.
- Browser Chrome 155: `progress-check.ps1 -ExerciseReadingSave` lulus pada 360/768/1280 px, dua run dengan fixture berbeda (run terakhir setelah handler pageshow ditambahkan). Request posisi diintersep sebelum server dan diberi 503 sintetis: satu request, tidak ada retry setelah scroll, kedua submit native terkunci, GET pemulihan mempertahankan posisi sebelumnya. Sesudah pemulihan, autosave nyata lulus, token seluruh form sama dan perubahan posisi tetap ada setelah GET reload. Pengiriman selesai tidak dijalankan oleh browser ini; diuji lewat HTTP terpisah.

Jalankan tes browser melalui fixture pemilik, bukan akun pengguna:

```powershell
& scripts/progress-check.ps1 -Php81Path C:\tools\php81-study\php.exe -MysqlPath C:\xampp\mysql\bin\mysql.exe -BaseUrl http://localhost/study/index.php/ -ChromePath 'C:\Program Files\Google\Chrome\Application\chrome.exe' -ExerciseReadingSave
```

## Tampilan

Renderer produksi dalam DOM fixture anonim dengan CSS aktual lulus 12 kombinasi: dua pesan kepastian × dua tema (dewasa/anak sekolah dasar) × 360/768/1280. Dua tautan native per kombinasi lolos keyboard, tidak ada form mutasi/overflow/teks terpotong. Pemeriksaan publik 12 kombinasi juga lulus.

Tiga capture ditinjau manual:

- [Belum tersimpan, dewasa 360](reading-recovery-unavailable-theme-adult-360.png)
- [Belum pasti, anak 360](reading-recovery-uncertain-theme-child-primary-360.png)
- [Belum pasti, dewasa 1280](reading-recovery-uncertain-theme-adult-1280.png)

Delapan capture publik lain tidak diklaim sudah ditinjau visual. Komponen ini bukan screenshot HTTP 503 produksi, bukan layout sesi masuk dan bukan bukti semua tema/usia atau pembaca layar.

## Verifikasi lanjutan transaksi database

Putaran lanjutan menambahkan `cli/Check_reading_transactions` ke `progress-check.ps1`, menggunakan pelajaran kedua fixture yang tepat (berbayar, bukan pratinjau), akun dewasa sintetis pemilik kursus dan entitlement paket aktif. Guard mensyaratkan CLI development, PHP 8.1, koneksi loopback `db_study` dengan `study_app`, slug/username/sumber fixture yang cocok serta progres awal kosong. Tidak ada akun atau materi nyata yang dipilih.

**96 skenario lulus:** dua operasi × tiga baseline (kosong, berjalan, selesai) × 16 kegagalan FALSE/exception pada BEGIN, baca pelajaran, baca akses, upsert, status sebelum/sesudah tulis, COMMIT dan ROLLBACK. Query yang tidak diinjeksi berjalan pada database nyata. Digest serialisasi seluruh baris 33 tabel, diurutkan deterministik, sama sebelum/sesudah tiap kasus; tidak hanya menghitung jumlah baris. Alat menolak inventaris tabel yang tidak sesuai dan tidak mencetak isi/digest data.

Proxy tidak pernah meneruskan COMMIT operasi yang diinjeksi, termasuk jika marker fault tidak terjangkau. Tes menegaskan titik gagal benar-benar tercapai, jumlah BEGIN/ROLLBACK/tulis sesuai, debug kembali, serta tidak ada query setelah kegagalan pertama. Pada 12 simulasi rollback gagal, koneksi nyata masih aktif dan harness melakukan rollback eksplisit; ini tidak dikreditkan sebagai pemulihan model. Auto-increment dapat maju walaupun baris dibatalkan.

Dua COMMIT normal yang terpisah menyiapkan baseline `in_progress:3700` lalu `completed:10000`. Sesudah seluruh probe, digest awal tetap sama bila satu baris progres setup tepat itu dikecualikan. PowerShell memeriksa identitas peserta/slug pelajaran dan keadaan baris sebelum menghapus hanya baris setup tersebut; progres anak sintetis lain tetap ada untuk regresi berikutnya. Regresi HTTP progres/keluarga kemudian lulus dan cleanup suite selesai. Tidak ada perubahan model, controller pengguna, atau tampilan produksi pada putaran lanjutan ini.

`test-reading-probe.php` lulus 66 assertion pada PHP 8.0/8.1/8.5: COMMIT yang dikenali/tidak dikenali tidak pernah diteruskan, fault tepat, query lanjutan ditolak dan kegagalan rollback eksplisit. Unit model 127/controller-view 89 per runtime diulang dan lulus; dua PHPCS, naming 185 PHP dan parser 40 PowerShell lulus. Helper baru ditambahkan ke penolakan HTTP smoke dan uji probe ke QA lokal. Browser tidak diulang pada putaran lanjutan ini karena tidak ada perubahan UI/JavaScript; bukti browser di atas tetap hasil putaran sebelumnya.

Smoke/lint lanjutan lulus untuk 362 PHP pada tiga runtime; dua URL helper CLI baru ditolak HTTP 404. Lima URL suite yang tidak aman ditolak sebelum setup fixture. Pemeriksaan 57 Markdown/54 route/48 kontrak, 568 berkas teks tanpa konflik, lisensi serta secrets dan kontrol pemindainya lulus. Pemeriksaan akhir database diulang: migration 13/33 tabel, empat role, satu migration dan satu login failure lama ID 3067; 30 tabel lain kosong, nol skema QA, log hanya `index.html` 131 byte. Tidak ada penghapusan data lama; hanya fixture suite dan satu baris setup probe dibersihkan.

## Batas verifikasi

Smoke/lint 360 berkas PHP pada tiga runtime lulus, beserta dua konfigurasi PHPCS, naming 183 PHP, parser 40 PowerShell, 57 Markdown/54 route/48 kontrak, 566 berkas teks tanpa penanda konflik, lisensi aset serta pemindaian rahasia dan kontrol pemindainya. Naming awal menolak properti uppercase pada mock controller baru; mock diperbaiki memakai pemetaan service melalui `__get`, lalu unit/naming tiga runtime diulang dan lulus. Ini perbaikan alat uji, bukan perubahan alias model produksi.

Pemeriksaan akhir seluruh 33 tabel menunjukkan migration 13, empat role, satu baris migration, serta satu login failure lama ID 3067 yang dipertahankan. Tiga puluh tabel lain kosong, termasuk akun, konten, progres, audit dan transaksi. Tidak ada skema `db_study_qa%`; folder log hanya `index.html` 131 byte. Tidak ada perubahan data di luar fixture milik suite atau konfigurasi global PHP/MySQL.

Putaran awal belum menginjeksi transaksi pada database nyata; verifikasi lanjutan di atas kini menambah bukti rollback SQL fixture, tetapi kegagalan COMMIT tetap diinjeksi **sebelum** COMMIT diteruskan. Bukan putus koneksi server saat/sesudah COMMIT nyata, race paralel, DDL atomik atau crash recovery. Respons 503 browser adalah intersepsi sebelum penulisan; timeout, respons terlambat dan BFCache hanya bukti unit. Tidak menjalankan full QA, resilience worker, server PHP 8.1 baru, Nginx runtime, Linux/staging, Midtrans, UAT atau produksi. HTTP memakai Apache PHP 8.0, target PHP 8.1 diuji CLI. Perbaikan ini tidak otomatis memperbaiki transaksi flashcard/lifecycle akun/konten lain, dan tidak menjamin bebas bug.
