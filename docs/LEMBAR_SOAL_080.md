# Lembar soal terpadu — 080

21 Oktober 2026. Cakupan: Latihan Mandiri, Simulasi Ujian, latihan kursus. Pustaka `application/views/quiz/play/exam.php` dibaca sebagai referensi navigasi/status; tidak diubah dan tidak menjadi dependensi runtime.

## Cara mencoba

1. `/latihan/mandiri`: pilih kelas, mapel, jumlah, waktu, urutan opsi; pilih **Per soal** atau **Ledger**, pantau fokus aktif/nonaktif dan batas 1–10. Tidak ada pilihan atau pembelian akses Dasar/Lanjutan.
2. `/kelola/simulasi-ujian`: buka simulasi, atur tampilan dan fokus, simpan. Peserta membaca aturan sebelum mulai melalui `/latihan/ujian`. Aturan tersalin ke sesi; perubahan admin tidak mengubah sesi berjalan.
3. `/latihan/kursus`: pilih kursus. Pengguna dapat memilih tampilan/fokus sebelum memulai latihan kursus gratis. Melanjutkan sesi lama memakai aturan lamanya.
4. Pilih jawaban: nomor soal menjadi hijau bertanda centang; tunggu **Tersimpan**. Mode per soal menyediakan nomor, sebelumnya/berikutnya, dan tombol nomor di bar bawah untuk kembali ke navigasi. Ledger menggulir ke soal berikutnya setelah pilihan dibuat. Tetap tersedia Simpan sementara dan konfirmasi kumpulkan.
5. `/kelola/pengaturan/latihan_mandiri`: kuota gratis harian, jumlah/harga tambahan kuota dan aktivasi penukaran tetap digunakan. Platinum Selamanya tetap tanpa batas harian. Pengaturan harga akses Lanjutan dihapus; catatan pembelian/ledger historis tidak dihapus atau dikonversi.

Sesi yang sudah berjalan sebelum rilis menggunakan ledger dan pantau fokus nonaktif. Selesaikan sesi lama terlebih dahulu untuk mencoba pengaturan baru. Halaman hasil memakai nomor navigasi berbentuk tautan dan mempertahankan pembahasan; tidak menyediakan kontrol perubahan jawaban.

## Arti pantau fokus / anti-cheating

- Default nonaktif; jika dipilih aktif, batas 3 berarti selesai **pada kejadian ke-3**, bukan setelah tiga toleransi tambahan.
- Perpindahan tab/aplikasi, kehilangan fokus jendela, atau jeda timer browser lebih dari 30 detik dicatat. Blur dan hidden dalam satu episode tidak dihitung dua kali. Reload tidak menambah kejadian dan tidak mereset hitungan.
- Ini alat pendamping, **bukan bukti pasti kecurangan atau pengawasan anti-bypass**. Browser dapat menunda timer, JavaScript dapat dinonaktifkan, dan sleep tidak dapat dipastikan pada semua perangkat. Tidak memblokir pintasan aksesibilitas, tidak merekam layar/kamera, tidak membaca aplikasi/tab lain.
- Saat jaringan putus, token kejadian (tanpa isi jawaban) ditahan di sessionStorage tab dan dikirim ulang ketika terhubung. Browser mengunci pilihan ketika batas lokal tercapai; server menghitung token unik dan menyelesaikan sesi secara atomik setelah menerima kejadian. Tidak menjanjikan server bisa langsung tahu sleep saat perangkat offline/mati.
- Jawaban hanya dianggap tersimpan setelah respons sukses. Pilihan yang belum tersimpan tidak ditulis ke localStorage/sessionStorage. Ada peringatan sebelum meninggalkan pilihan belum tersimpan. Pada kedaluwarsa waktu, server hanya menilai jawaban yang sebelumnya tersimpan.
- Referensi batas kemampuan: [MDN Page Visibility API](https://developer.mozilla.org/en-US/docs/Web/API/Page_Visibility_API), [MDN RequestInit keepalive](https://developer.mozilla.org/en-US/docs/Web/API/RequestInit).

## Kontrak implementasi

- Migration **052**, hanya penambahan: `sheet_rules_json` nullable pada sesi dan template ujian; tabel `st_practice_focus_events` dengan PK `(attempt_id,event_token)` dan FK attempt. Tidak backfill aturan aktif ke sesi lama; rollback destruktif ditolak.
- `Practice_sheet` memvalidasi mode allowlist, aktif 0/1 dan batas 1–10. Perubahan aturan melalui POST jawaban diabaikan.
- Penyimpanan otomatis menggunakan endpoint attempt yang sama, kepemilikan + CSRF + optimistic revision tetap wajib. Semua opsi diverifikasi terhadap snapshot; kunci/pembahasan tidak dikirim sebelum final.
- CSRF berotasi: respons JSON memperbarui token, termasuk formulir navbar. Konflik tab tidak menimpa jawaban baru; UI meminta muat simpanan terbaru. Kejadian fokus dari revisi lama tetap dapat memicu batas, tetapi menggunakan jawaban tersimpan.
- Token kejadian idempotent, penghitungan di bawah lock attempt, finalisasi menggunakan mesin skor/progres/rapor yang sama. Pengiriman ulang setelah final tidak mengubah nilai/ledger.
- Mandiri mengambil ID unik dari seluruh tingkat bank pada mapel/kelas yang dipilih. Metadata tingkat bank untuk pengelolaan tetap ada. Tidak mengubah harga/akses ujian, Arena, paket atau callback Midtrans.

## Bukti pengujian

Database uji terisolasi `study_qa_monitor_sso039`, PHP8.4 + Chromium:

- `scripts/test-sheet080.mjs`: validasi aturan; Mandiri campuran dasar/lanjutan tanpa unlock; batas harian gagal tidak membuat attempt; snapshot kursus/ujian; admin menyimpan/publikasi ujian; aturan peserta tidak mengalahkan admin; token berulang; revisi stale; invalid option/event tanpa partial write; akses outsider/CSRF; replay final; sesi legacy; timeout memakai simpanan sebelumnya.
- Browser1280/360: kedua mode, radio/navigasi keyboard, status dijawab, autosave/reload, gulir otomatis, tanpa overflow/galat JS. Perpindahan tab nyata dihitung sekali; reload tidak menaikkan hitungan; event yang sudah diterima server tetapi dikirim ulang tidak membuat input terkunci; ambang menyelesaikan sesi.
- Browser terarah: offline/reconnect, jeda timer sintetis31detik (bukan klaim uji sleep perangkat fisik), konflik penyimpanan dan pembaruan CSRF antartab.
- `scripts/test-practice-read-access.php`:85 assertion akses/read-only; `scripts/test-practice-unsaved-view.php`:20 render, escaping, tidak membocorkan kunci saat aktif, revision/server choices, hasil/keluarga hanya baca.
- Regresi `scripts/test-auto079.mjs`: contoh30soal/6gambar hanya pratinjau, tidak commit contoh pemilik; fixture sintetis commit→ujian→anak→nilai100; gambar otomatis/inline/pilihan dan endpoint privat tetap benar, desktop/mobile.
- Form pengaturan Mandiri/kursus/admin ujian diperiksa1280/360. Tidak ada pembayaran nyata atau reset akun untuk pengujian.

## Deployment

- Backup source + DB sebelum pekerjaan: `/www/backup/projectpintar-sheet080-Y3wjfA8l/`.
- Staging `/www/study-private/sheet080-SZ8RMFqb` **bukan dependensi runtime**.
- Terpasang di produksi:27file sesuai salinan teruji, migration51→52 hanya additive; FPM Project Pintar direload dan aktif. Aset JS/CSS200; lima rute privat303 ke autentikasi. Akun DB runtime berhasil membaca kolom/tabel baru. Tidak login menyamar ke akun produksi.
- Checksum sebelum/sesudah identik: questions2683587441, options405183063, bank2898616616, scopes2726639149, users4191112837, wallets713850174, wallet_entries0, payment_orders3709692070. Bank30soal tetap;2attempt lama (1berjalan/1graded), keduanya mempertahankan rulesNULL;0event fokus produksi saat verifikasi.
- Snapshot DB tambahan `database-before-deploy.sql` ada dalam backup privat di atas. Jangan menyalin database QA atau staging ke produksi. Staging bukan pengganti folder media privat runtime.
