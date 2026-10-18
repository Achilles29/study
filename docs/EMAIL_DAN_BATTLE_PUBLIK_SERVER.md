# Gmail, verifikasi akun, dan katalog battle — 16 Oktober 2026

Status server terbaru: migration **025**, database mandiri `db_study`, **43 tabel**, katalog Arena tetap 190. Migration 024 menambah konfigurasi/pencatatan pengiriman dan memperluas **tabel token existing**, bukan menggandakan sistem akun. Migration 025 hanya menambah metadata katalog/review battle. Tidak mengulang instalasi, migration lama, reset data/ID, atau mengubah Finance/Pustaka/callback pembayaran.

## Pengaturan Gmail yang dapat dicoba

URL: `/kelola/pengaturan/email`, hanya Superadmin aktif. Tautan tersedia di Pusat Pengaturan.

1. Siapkan akun **Gmail khusus aplikasi**, aktifkan verifikasi dua langkah, buat [App Password Google](https://support.google.com/accounts/answer/185833?hl=en) jika tersedia. Akun dengan pembatasan organisasi/Advanced Protection mungkin tidak menyediakan fitur ini. Jangan kirim rahasia lewat chat.
2. Masukkan alamat Gmail, App Password, dan **kata sandi akun Project Pintar** untuk mengonfirmasi perubahan. Simpan dengan status **Nonaktif** dahulu. Bukan password utama Gmail.
3. Klik kirim pesan uji; tujuan dibatasi ke alamat Gmail pengirim sendiri. Status `sent` berarti diterima SMTP, **bukan jaminan tiba di inbox**. Periksa inbox/spam.
4. Setelah pesan benar-benar diterima, pilih Aktif, centang konfirmasi inbox, kosongkan App Password untuk mempertahankan yang tersimpan, lalu simpan. Mengganti alamat/rahasia memerlukan mode nonaktif dan tes ulang.

SMTP dikunci ke `smtp.gmail.com:587`, STARTTLS wajib dan sertifikat diverifikasi. Bukan host/port bebas atau relay ke alamat tes sembarang. Runtime memakai cURL SMTP yang tersedia pada PHP server. Acuan [konfigurasi SMTP Google](https://support.google.com/mail/answer/7104828?hl=en); tidak perlu mengaktifkan POP untuk pengiriman ini.

App Password disimpan AES-256-GCM, nonce acak dan key derivation khusus email dari secret environment. Tidak ditampilkan ulang, tidak masuk audit/HTML/Git. Pengaturan memakai pemeriksaan peran di server, CSRF, autentikasi ulang kata sandi Project Pintar, konflik revisi, transaksi dan audit atomik. Tes konfigurasi lama tidak mengesahkan konfigurasi yang baru dirotasi.

Pengiriman awal tetap **nonaktif** dan kredensial kosong di produksi. Tidak ada akun Gmail yang dibuat otomatis, email nyata yang dikirim, atau klaim inbox sudah berhasil. Status verifikasi akun ngadmin yang ditemukan saat pemeriksaan **sudah terisi**; nilai existing dipertahankan, bukan diset/reset oleh pekerjaan ini. Ini mengoreksi asumsi catatan tahap 023 bahwa semua akun produksi belum terverifikasi; status bootstrap bukan bukti pengiriman SMTP baru.

## Verifikasi dan pemulihan akun

- `/akun/email`: dewasa/orang tua meminta verifikasi alamat saat ini atau perubahan alamat, dengan kata sandi saat ini. Alamat lama dan statusnya tetap berlaku sampai bukti email baru dikonfirmasi.
- `/akun/verifikasi`: tautan/kode sekali pakai berlaku 30 menit. GET/pemindai email tidak mengonsumsi token; pengguna harus masuk ke akun peminta dan mengonfirmasi melalui POST + CSRF.
- `/lupa-sandi` dan `/pulihkan-sandi`: akun dewasa/orang tua **terverifikasi** mendapat token pemulihan 15 menit. Respons permintaan sama untuk alamat tidak dikenal, belum terverifikasi, anak, terbatasi, atau tidak tersedia. Ini pengujian respons HTTP, bukan klaim bebas seluruh kanal timing.
- Reset mematuhi minimum sandi per akun/peran (Superadmin 16), maksimal 72 byte; token dan audit satu transaksi. Hash sandi baru membatalkan sesi lama melalui mekanisme auth stamp existing. Anak tidak diwajibkan punya email; reset anak oleh orang tua tetap pekerjaan terpisah, bukan dianggap selesai oleh fitur ini.

Token acak 32 byte disimpan **SHA-256 saja** pada `st_account_tokens`, terikat jenis, akun, alamat tujuan, hash kredensial dan alamat akun saat permintaan. Email/kata sandi yang berubah, token kedaluwarsa, sudah dipakai, pengiriman gagal atau token jenis lain ditolak. Kirim baru membatalkan token lama jenis sama; konfirmasi membatalkan token akun yang masih terbuka. Token tidak tampil dalam HTML respons server/audit/query URL. Link memakai fragmen `#token=`, dihapus dari bilah alamat oleh skrip lokal setelah diisi ke formulir password; tersedia input kode manual. Halaman memakai no-store/noindex/no-referrer.

Pembatasan server: jeda 60 detik dan 5 permintaan/jam per akun, 30/jam per hash jaringan, 80/jam dan 400/24 jam seluruh aplikasi. Autentikasi ulang dibatasi 10 kegagalan/15 menit per akun dan 50 per hash jaringan. Reservasi pengiriman dikunci lintas proses; tidak ada retry otomatis saat timeout karena hasil kirim bisa tidak pasti. Percobaan/pengiriman dicatat tanpa isi pesan atau token mentah; IP disimpan HMAC, bukan IP mentah dalam tabel email. Penghapusan/retensi otomatis belum diaktifkan tanpa kebijakan final.

Pendaftaran production **tetap tertutup** sampai pengiriman/pemulihan nyata, syarat/kebijakan dan UAT siap. SMTP Gmail ini **bukan Google SSO**; OAuth login masih checklist terbuka.

## Battle semua member gratis

- `/belajar/battle`: buat soal dan ruang gratis, pratinjau/publikasi, ruang saya/keluarga, kode undangan. Tidak ada pemeriksaan Platinum/kredit untuk membuat atau mengikuti battle.
- `/belajar/battle/umum`: katalog untuk **semua member yang masuk**, bukan daftar anak atau publikasi tanpa akun. Filter kategori dan pagination 12; hanya ruang disetujui, masih terbit, jadwal belum berakhir, penyelenggara aktif/terverifikasi. Kasus draf anak juga mensyaratkan relasi orang tua aktif dan jenis akun sesuai.
- `/belajar/battle/umum/{id}`: judul/ringkasan/jadwal/usia/jumlah soal/batas peserta; tidak ada kode privat, soal, kunci, alias/nama/kontak peserta. Member biasa bergabung langsung tanpa paket berbayar; orang tua memilih anak dan persetujuan per ruang. Anak tidak dapat mendaftar sendiri dengan memalsukan ID.
- Setelah terbit, penyelenggara dewasa terverifikasi mengajukan ringkasan/usia dan menyetujui akses soal/kunci/sumber untuk review. Draf privat yang **tidak diajukan** tetap tidak dapat dibaca dari endpoint reviewer.
- `/kelola/battle/katalog` dan `/kelola/battle/tinjau/{id}`: antrean/riwayat pending/disetujui/ditolak/disembunyikan; pratinjau seluruh soal yang diajukan, keputusan + alasan, checklist isi/hak/usia/privasi, audit atomik. Tidak membuka jawaban peserta. Self-review root hanya jika mode operator tunggal existing aktif, tetap wajib semua checklist dan audit `self_review`.
- Memperbarui pengajuan menyembunyikan ruang sampai ditinjau ulang. Penyelenggara dapat menarik; pengelola dapat menolak/menyembunyikan. Keikutsertaan dan sejarah yang sah tidak dihapus. Laporan/pembatalan sebelumnya tetap tersedia; pembatalan menghentikan ruang, sedangkan penarikan katalog saja tidak membatalkan peserta lama.

Versi ini tetap **terjadwal/asinkron**, bukan mode live serentak. Batas default 50 peserta/30 soal/3 ruang adalah batas teknis, bukan janji beban besar. Iklan belum dipasang, tidak ada syarat menonton iklan, hadiah uang atau reward poin pada soal komunitas. Review privasi, AdSense, live battle dan uji beban tetap terbuka.

## Pengujian dan deploy

QA terisolasi `study_qa_monitor_email224`, restore fixture sintetis 023; proses PHP loopback 8097/8098. Capture email hanya berjalan bila ENV development, nama DB berawalan QA, dan direktori `/tmp/study-mail-qa-*` cocok. Override tidak tersedia lewat UI dan tidak bisa aktif di production. Tidak mengirim email ke Google selama QA.

- `test-account-mail-http.mjs`: simpan/rotasi terenkripsi/redaksi, role/CSRF/reauth/revisi/audit rollback, probe/gerbang aktivasi, perubahan alamat setelah proof, token akun salah/expired/replay/reissue/jenis lain, kegagalan kirim, reset/sesi lama ditolak, respons pemulihan seragam, dua proses berebut kirim ulang.
- `test-mail-secrets.php`: nonce acak, roundtrip, ciphertext rusak/malformed dan key salah ditolak.
- `test-account-mail-browser.mjs`: 1280/360 px, form pengelola rahasia kosong, request-capture-fragment-konfirmasi keyboard, GET tidak mengubah akun, halaman pemulihan, tanpa overflow/galat JS.
- `test-battle-directory-http.mjs`: pengajuan/review/approve/reject/hide/withdraw/resubmit, consent reviewer, role/CSRF/revisi/audit rollback, katalog tidak bocor soal/kunci/kode/peserta, gratis/join idempoten, persetujuan dan isolasi anak, email publisher dicabut, self-review mode/checklist/audit.
- `test-battle-directory-browser.mjs`: 1280/360 px, penyelenggara mengajukan, admin membuka soal/checklist/menyetujui, member memfilter/menemukan/bergabung/menjawab memakai keyboard; tanpa overflow/galat JS. Screenshot sintetis `/tmp/study-mail-*.png` dan `/tmp/study-battle-{directory,public,review}-*.png`; tampilan setting Gmail dan detail battle 360 diperiksa langsung.
- `test-mail-directory-guards.mjs`: katalog draf anak hilang setelah relasi/jenis akun wali berubah; dua proses memakai token sama menghasilkan satu sukses dan satu penolakan; throttle reauth lintas sesi.

Restore QA awal masih berjalan ketika CLI upgrade pertama dicoba sehingga gagal pada tabel existing; setelah restore selesai versi 23 diverifikasi, hanya upgrade 024 lalu 025 yang diterapkan. Ini kejadian **QA**, bukan migrasi produksi; tidak ada reset produksi. Tidak mengulang matriks tes historis pembayaran/kursus.

Backup produksi sebelum perubahan: `before-email-024.sql` dan `before-battle-directory-025.sql` di `/www/backup/projectpintar-deploy-20261016/` (mode 600). Tidak menyalin konfigurasi SMTP atau rahasia dari aplikasi lain.

Pemeriksaan runtime akhir menggunakan kredensial aplikasi: versi 25/43 tabel, satu pengguna ID 1, 190 katalog, nol token/pengiriman email/ruang battle/soal bank produksi. Pengirim belum diisi, status email nonaktif dan belum diuji. Smoke HTTPS: beranda dan lupa sandi 200; pengaturan Gmail/katalog battle tanpa login 307. Lint 22 PHP terdampak, PHPCS, nama CI3 (268 file), sintaks JavaScript dan `git diff --check` lulus.

Kedua server QA dihentikan setelah PID/database diverifikasi. Hanya database sintetis `study_qa_monitor_email224` dan direktori capture `/tmp/study-mail-qa-GoRsjnJs` dihapus; dapat dipulihkan dari `mail-battle-qa-evidence-025.sql` dan `mail-qa-capture-025.tar.gz` di direktori backup privat yang sama (mode 600). Tidak menghapus data produksi.

## Gerbang yang masih terbuka

Pemilik mengisi Gmail/App Password melalui halaman pengaturan dan memeriksa inbox. Sesudah itu perlu uji nyata verifikasi/reset sebelum membuka pendaftaran. Roadmap keseluruhan **belum selesai**: Google SSO, reset anak/pemulihan operasional, trafik tiga tab dan kebijakan retensi/persetujuan, transaksi QRIS sandbox/produk/Platinum/slot, poin penukaran, live battle/AdSense serta UAT. Harga/durasi masih draft dan pembayaran live tidak diaktifkan. Materi bank soal sesi latihan tetap tidak diisi sesuai instruksi.
