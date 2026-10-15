# Kepastian simpan status kartu — 16 Oktober 2026

## Perubahan

Tes baru gagal sebelum perbaikan karena COMMIT FALSE masih menghasilkan TRUE pada `set_card_status`. Pembacaan kartu juga berada sebelum transaksi dan pemeriksaan akses mutasi hanya bergantung pada preflight controller.

Model sekarang memakai `Study_transaction`: validasi ID positif kanonis/status, transaksi terluar, baca kartu/pelajaran/kursus terbit terkunci, pemeriksaan akses current read, upsert yang diperiksa dan COMMIT terkonfirmasi. Tidak mengubah aturan penghitungan review: status sama mempertahankan hitungan/waktu, perubahan status menambah satu. Tidak menulis XP, nilai resmi, kredit atau konten. Transaksi editor deck/kartu belum diubah.

`Catalog` mengirim 303 setelah sukses, tanpa query sesudah COMMIT. Penolakan input 400 dan kartu/akses 403 terpisah dari kegagalan layanan 503. Halaman `catalog/card_recovery` memakai komponen bersama dengan konteks **Periksa kembali kartu belajar**, GET ke slug materi dari server + `#kartu-belajar`, serta beranda. Tidak ada reread sesudah hasil gagal model, form kartu, flash lama, skrip simpan atau retry POST otomatis. Form logout layout akun masuk tetap diperbolehkan; kegagalan preflight sebelum operasi model tidak dicakup.

## Bukti

- `test-flashcard-transactions.php`: **155 assertion** pada PHP 8.0.30/8.1.34/kandidat 8.5. FALSE/exception BEGIN/baca kartu/akses/upsert/status sebelum-sesudah tulis/COMMIT, ekstraksi hasil, rollback gagal, nested transaction, free/preview, validasi identitas/status, binding/current-read SQL dan invarians upsert. Model akses dan runner aktual digunakan dengan recording fake; bukan SQL atau koneksi putus nyata.
- `test-reading-recovery.php` diperluas memakai fixture controller Catalog bersama: **46 assertion kartu**, ditambah regresi **89 assertion baca**, per runtime. Mencakup kedua status, unavailable/uncertain/failure tidak dikenal, 303 sukses, 400/403, preflight 404, slug dari server, escaping dan renderer dua tautan tanpa form/progres/token. Pembacaan layanan setelah mutasi dilarang oleh fixture.
- Regresi view latihan 199, permainan 79 dan akses current read 90 assertion per runtime lulus. Fixture renderer empat konteks lulus 55 pemeriksaan per runtime. Seluruh `npm run test:ui` lulus; JavaScript produk tidak diubah pada pekerjaan ini.
- `flashcard-check.ps1` lulus di Apache PHP 8.0: lifecycle draf/review/terbit, perubahan known→learning→known, pengiriman status sama, hitungan tepat, timestamp lama sintetis tetap saat replay, empat ID direct-route tidak sah ditolak tanpa perubahan, akses manual berbayar berhasil, revokasi ditolak tanpa perubahan, lalu akses gratis dipulihkan. Ringkasan orang tua, isolasi keluarga/peserta, kartu lintas pelajaran, preview dan regresi game lokal juga lulus. Revokasi diuji berurutan sebelum POST, bukan race paralel antara preflight dan COMMIT.
- Regresi `progress-check.ps1` lulus, termasuk 96 injeksi database **progres baca** dan HTTP normal. Ini bukan 96 injeksi flashcard; jangan mengatribusikan bukti model baca kepada mutasi kartu.

## Tampilan

Chrome 155 memeriksa renderer view produksi pada DOM fixture anonim dan CSS aktual: dua keadaan kepastian × dua tema (dewasa/anak sekolah dasar) × 360/768/1280, total **12 kombinasi/24 perhentian Tab**. Pesan, dua tautan native, wrapping, teks dan overflow lulus. Set publik 12 kombinasi juga lulus.

Tiga capture ditinjau manual:

- [Belum tersimpan, dewasa 360](flashcard-recovery-unavailable-theme-adult-360.png)
- [Belum pasti, anak 360](flashcard-recovery-uncertain-theme-child-primary-360.png)
- [Belum pasti, dewasa 1280](flashcard-recovery-uncertain-theme-adult-1280.png)

Delapan capture publik lain tidak diklaim telah ditinjau. Komponen bukan screenshot HTTP 503 nyata atau layout akun masuk; status/controller diuji terpisah. Tidak ada klaim seluruh tema/usia, zoom 200%, pembaca layar atau UAT.

## Batas dan pekerjaan tersisa

Pemeriksaan akhir: smoke HTTP dan lint 364 file PHP pada tiga runtime lulus; dua konfigurasi PHPCS, naming 187 file, parser 40 skrip PowerShell, tautan 59 dokumen, 54 route/48 kontrak, konflik 572 file, lisensi aset dan pemindai rahasia beserta kontrolnya lulus. Pemeriksaan kontrak statis bukan pengganti uji perilaku.

Database akhir tetap migration 13/33 tabel: empat role, satu baris migration dan satu catatan login gagal lama ID 3067; 30 tabel lainnya kosong. Nol schema `db_study_qa%` tersisa. Direktori log hanya berisi `index.html` (131 byte). Tidak ada penghapusan data lama atau perubahan skema pada pekerjaan ini.

Belum ada injeksi transaksi flashcard pada database nyata, koneksi putus saat/sesudah COMMIT, atau request paralel staging. Interaksi submit kartu ketika autosave posisi masih berjalan perlu pengujian koordinasi khusus; pembaruan token seluruh form setelah autosave sukses tidak membuktikan bebas balapan sebelum respons tiba. Tidak mengubah atau mengklaim dukungan offline, resolusi tab usang, editor kartu, retensi atau kelulusan belajar.

Tidak menjalankan full QA, resilience worker, server PHP 8.1 baru, Nginx runtime, Linux/staging, Midtrans atau produksi. HTTP memakai Apache PHP 8.0 dan target PHP 8.1 diuji CLI. Checklist rilis tetap terbuka.
