# Kepastian penyimpanan permainan — 16 Oktober 2026

## Hasil dan lingkup

Bug direproduksi sebelum perubahan: `trans_status()` bernilai TRUE tetapi COMMIT FALSE masih menghasilkan `ok = TRUE` dari helper lama. Mulai, pilihan pasangan, dan berhenti pada `Matching_run_model` sekarang memakai `Study_transaction`, memeriksa hasil baca/tulis/audit dan menghentikan operasi pada kegagalan pertama. Pemeriksaan akses serta replay sesudah lock memakai current read. Aturan `matching_v1`, snapshot, jumlah pasangan menurut usia, revisi, idempotensi dan izin berhenti setelah akses materi dicabut tetap dipertahankan.

Controller mengirim 503 dengan pesan belum berhasil disimpan atau hasil belum pasti, tanpa query ulang pada cabang kegagalan model. GET menuju intro/run milik peserta dan riwayat tersedia; tidak ada retry POST otomatis. Komponen `components/transaction_recovery.php` dipakai bersama latihan dan permainan, dengan teks konteks tetap serta tema pengguna. Komponen tidak memuat form/token permainan, progres, feedback lama atau skrip simpan; form logout pada layout terautentikasi tetap diperbolehkan. Kegagalan preflight sebelum pemanggilan model tidak otomatis tercakup.

## Bukti unit dan regresi kode

| Pemeriksaan | Hasil |
| --- | --- |
| `test-matching-transactions.php` | 833 assertion, termasuk 140 injeksi boundary/baca/tulis/dependensi per runtime. Domain, replay, JSON, current read, penghentian setelah gagal dan hasil tidak pasti diuji. |
| `test-matching-recovery-controller.php` | 183 assertion / 28 kasus: 503 tanpa reread/flash/token, 303 sukses, URL pemulihan dan penolakan domain lama. |
| `test-matching-recovery-view.php` | 79 assertion / lima render: konteks, pesan, escaping dan tautan native tanpa mutasi. |
| `test-course-access-lock.php` | 90 assertion: flag lock pelajaran diteruskan, pembacaan biasa tidak meminta lock dan pratinjau tetap sesuai kontrak. |
| Regresi bersama | Runner transaksi 318, transaksi latihan 859, assignment 275, controller latihan 244, view latihan 199 assertion. |
| Fixture renderer Node/PHP | 39 pemeriksaan per runtime untuk dua konteks, argumen/URL loopback dan penolakan input tidak sah. |
| JavaScript | `npm run test:ui` lulus; respons 503 tidak diurai menjadi progres, tidak retry/navigasi otomatis dan revisi lama tetap terkunci. |

Unit PHP di atas lulus pada CLI 8.0.30, 8.1.34 dan kandidat 8.5. Unit transaksi permainan memerlukan `mbstring`: percobaan awal PHP 8.5 gagal karena ekstensi belum dimuat, lalu lulus dengan `-d extension=C:/tools/php85-study/ext/php_mbstring.dll`. Skrip kini mempunyai pemeriksaan prasyarat yang jelas; konfigurasi PHP global tidak diubah. Hasil ini bukan bukti kesiapan HTTP PHP 8.5.

## Database nyata dan alur HTTP

`matching-storage-check.ps1` dijalankan sendiri pada `db_study`, CLI PHP 8.1 dan Apache XAMPP PHP 8.0, tanpa opsi resilience worker. Helper CLI menolak HTTP dan hanya menerima fixture unik yang memenuhi guard kepemilikan serta isi sintetis.

- 19 injeksi pada mulai, 16 pada pilihan pasangan terakhir, dan 14 pada berhenti: **49 skenario lulus**. Digest seluruh baris terurut dari 19 tabel tidak berubah setelah tiap injeksi. COMMIT pada operasi yang diinjeksi tidak diteruskan ke koneksi nyata.
- Penyiapan terpisah melakukan satu mulai dan tujuh pilihan sah dengan delapan COMMIT normal, membentuk keadaan sebelum pasangan terakhir. ID, key, pemilik dan keadaan diverifikasi sebelum cleanup tepat sasaran; suite normal lalu dimulai dari fixture tanpa run.
- Enam kegagalan rollback memerlukan rollback eksplisit oleh harness. Ini kemampuan cleanup alat uji, bukan klaim model dapat memulihkan koneksi yang benar-benar putus. Auto-increment dapat bertambah walaupun baris dibatalkan.
- Regresi normal mulai/pilih/selesai/berhenti, snapshot, akses, replay, histori dan laporan keluarga lulus. Suite membersihkan akun, konten, run, move serta audit fixture miliknya.

Unit memakai recording fake, sedangkan injeksi database menjalankan SQL nyata di dalam transaksi fixture. Keduanya tidak membuktikan putus jaringan saat atau sesudah COMMIT database nyata, race paralel, driver-log sanitization ataupun perilaku MySQL produksi.

## Browser dan gambar

Chrome 155 pada Apache lokal memeriksa **39 kombinasi halaman/viewport terautentikasi**: mulai, pengerjaan, berhenti, hasil, histori, empat rangkaian interaksi dan empat tampilan keluarga, masing-masing pada 360/768/1280 px. Interaksi mencakup POST native/keyboard, offline sebelum request tanpa mutasi, respons hilang setelah 303 dengan tepat satu perubahan lalu pemulihan GET, fallback tanpa JavaScript dan navigasi/refresh. Kehilangan respons browser setelah 303 bukan kehilangan koneksi database selama COMMIT.

Renderer komponen memeriksa 12 kombinasi permainan dan 12 regresi latihan: dua status kepastian × tema dewasa/anak sekolah dasar × tiga lebar, termasuk 48 perhentian Tab. Dua run juga masing-masing memeriksa 12 kombinasi halaman publik; itu pengulangan set publik yang sama, bukan 24 halaman berbeda.

Tiga gambar berikut ditinjau dan menunjukkan pesan/aksi terbaca, tema sesuai dan tautan membungkus pada layar kecil:

- [Belum tersimpan, dewasa 360](matching-recovery-unavailable-theme-adult-360.png)
- [Belum pasti, anak 360](matching-recovery-uncertain-theme-child-primary-360.png)
- [Belum pasti, dewasa 1280](matching-recovery-uncertain-theme-adult-1280.png)

Delapan PNG publik lain adalah capture otomatis, tidak diklaim sudah ditinjau visual. Komponen pemulihan berasal dari view PHP produksi dan CSS aktual tetapi disisipkan ke DOM fixture anonim bertanda khusus; bukan screenshot respons HTTP 503 pada layout akun masuk. Controller/view 503 diuji terpisah. Tidak mengklaim pengujian pembaca layar, seluruh tema, zoom 200%, BFCache lintas browser atau UAT.

## Pemeriksaan tambahan dan batas rilis

Smoke/lint 357 berkas PHP pada tiga runtime, dua konfigurasi PHPCS, naming 180 berkas PHP dan parser 40 skrip PowerShell lulus. Penolakan HTTP helper CLI baru tercakup smoke. Tidak ada perubahan skema atau konfigurasi global runtime.

Regresi HTTP tambahan `practice-check.ps1`, `family-delivery-check.ps1`, `progress-check.ps1` dan `flashcard-check.ps1` dijalankan berurutan tanpa Chrome dan semuanya lulus. Ini mengulang pula 54 injeksi transaksi latihan/keluarga, bukan menambah jumlah 49 injeksi permainan di atas. Pemeriksaan akhir seluruh 33 tabel: empat role, satu baris migration, satu login failure lama ID 3067 dipertahankan; 30 tabel lain kosong, termasuk seluruh akun/konten/run/move/audit. Migration tetap 13, tidak ada skema `db_study_qa%`, folder log hanya berisi `index.html` 131 byte. Cleanup hanya menghapus fixture milik suite, bukan data pengguna lama.

Pemeriksaan dokumentasi lulus: 56 Markdown, 54 route eksplisit dengan 48 kontrak halaman/aksi, serta 560 berkas teks tanpa penanda konflik. Versi/lisensi aset, pemindaian rahasia dan kontrol positif/negatif pemindainya lulus. Pemeriksaan statis tersebut bukan audit keamanan menyeluruh.

Pengujian ini tidak menjalankan full `qa-local.ps1`, resilience worker, server baru PHP 8.1, Nginx runtime, Linux/staging, Midtrans ataupun produksi. Pengujian HTTP/browser memakai Apache PHP 8.0; target PHP 8.1 diuji CLI. Checklist seluruh roadmap dan kesiapan rilis tetap terbuka. Model transaksi lain tidak otomatis memperoleh perlindungan ini.
