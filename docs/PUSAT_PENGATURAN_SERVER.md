# Pusat pengaturan dan pengaman keluarga — 16 Oktober 2026

Kelanjutan setelah panel B1–B2. Ini bukti bagian yang selesai, bukan klaim seluruh roadmap atau kesiapan peluncuran publik. Pendaftaran produksi dan pembayaran live tetap nonaktif. Harga, durasi paket, aturan poin dan kebijakan privasi belum dijadikan keputusan final.

## Alur yang dapat dicoba

Masuk dengan akun Superadmin existing di `https://www.projectpintar.com/masuk`.

- `/kelola/pengaturan/umum`: edit nama situs, simbol lokal (bintang/buku/ide), pesan footer, deskripsi SEO beranda dan email dukungan publik. Simpan → redirect 303 → nilai dipakai pada header/footer, identitas metadata, beranda dan rapor. Domain/TLS/environment tidak bisa diubah lewat UI. Logo unggahan khusus belum dibuat; gunakan simbol lokal yang benar-benar tersedia.
- `/kelola/pengaturan/pembelajaran`: pilih review operator tunggal. Default tetap reviewer berbeda. Saat diaktifkan, Superadmin yang menulis kursus dapat meninjau kursus berstatus review dengan tiga checklist (isi, hak, usia) dan catatan minimal 10 karakter. Kelengkapan materi, soal, flashcard dan gambar tetap diperiksa. Editor tidak mendapat pengecualian. Audit menyimpan keputusan, versi konten, flag self-review/checklist dan revisi setting; bukan menghapus review lama.
- `/kelola/pengaturan/pembayaran`: masukkan merchant ID dan server key **sandbox**; client key opsional untuk Core API. Secret tidak dipantulkan, termasuk ketika input gagal. Blank mempertahankan nilai sebelumnya; mengganti merchant membutuhkan server key baru dan tidak membawa client key merchant lama. Simpan membuat versi baru, tidak menimpa versi lama. Tombol uji memanggil GET status API sandbox dengan ID probe acak yang tidak membuat tagihan.
- `/keluarga/anak` dan `/keluarga/anak/tambah`: batas dua profil anak aktif diperiksa dalam transaksi dengan lock orang tua dan current read relasi. Dua permintaan paralel tidak dapat melampaui kuota. Akun lama yang melebihi kuota tidak dinonaktifkan; penambahan baru ditolak. Pembelian slot tambahan belum aktif. Ini pengaman kapasitas, bukan penyelesaian verifikasi kontak atau persetujuan orang tua.

## Keamanan penyimpanan

Pengaturan umum/publikasi disimpan dalam `st_site_settings` dengan optimistic revision. Edit menggunakan form lama mendapat 409, bukan menimpa perubahan baru. Validasi dilakukan ulang di model; izin Superadmin aktif diperiksa kembali saat transaksi. Penyimpanan dan audit satu transaksi; kegagalan audit membatalkan nilai/revisi. Pembacaan pengaturan publik gagal memakai identitas bawaan dengan log gangguan; layar pengaturan tidak menganggap kegagalan sebagai nilai tersimpan sukses.

`st_payment_configs` menyimpan konfigurasi versi immutable dan secret AES-256-GCM dengan nonce acak, tag autentikasi dan derivasi HKDF dari `APP_ENCRYPTION_KEY` privat di environment. Master key tidak berada di database; jangan merotasinya tanpa prosedur re-enkripsi/backup. UI/query daftar tidak mengambil ciphertext atau menampilkan kunci; audit hanya ID versi/flag perubahan/status probe. Tidak ada secret dalam dokumen, JavaScript atau log aplikasi yang dibuat fitur ini. Kunci live ditolak oleh validasi dan kolom environment hanya menerima sandbox. Belum ada endpoint yang mengkreditkan saldo atau menerima pembayaran.

Probe: host sandbox tetap, TLS diverifikasi, redirect tidak diikuti, timeout 10 detik, respons maksimal 64 KiB dan tidak ditampilkan mentah. Hasil 404 order probe hanya menunjukkan respons status API, **bukan** bukti merchant/QRIS aktif. 401 ditampilkan ditolak, jaringan/format tak terduga bukan sukses. Uji dibatasi sekali per versi per 60 detik dan diaudit. Probe gagal tidak mengubah konfigurasi atau membuat transaksi.

Referensi Finance dibaca saja: `application/views/pos/self_order_settings.php` dan pola field self-order pada `application/models/Pos_model.php`. Pengaturan tidak disalin beserta secret/database. Callback global merchant tidak diubah. Integrasi transaksi berikut harus menggunakan namespace order Project Pintar dan override notifikasi khusus per transaksi, bukan mengganti callback Finance. Rujukan resmi: [Midtrans webhook dan override notifikasi](https://docs.midtrans.com/docs/https-notification-webhooks), [penanganan notifikasi](https://docs.midtrans.com/reference/handle-notifications), [error autentikasi 401](https://docs.midtrans.com/docs/what-does-the-error-code-401-message-access-denied-due-to-unauthorized-transaction-please-check-client-key-or-server-key-mean). Mekanisme transaksi/callback tersebut belum dihitung selesai pada perubahan ini.

## Perubahan database dan deployment

- Migration tambahan **014** membuat `st_site_settings`; **015** membuat `st_payment_configs`. Tidak mengulang 001–013, restore, instalasi atau reset ID. Keduanya diuji di schema terisolasi sebelum diterapkan pada produksi. Rollback destruktif ditolak.
- Backup privat: `/www/backup/projectpintar-deploy-20261016/before-settings-014.sql` dan `before-payment-settings-015.sql`. Tidak berada dalam document root.
- Database produksi tetap `db_study` lokal port 3308, sekarang versi **15**, **35 tabel**, akun `ngadmin` ID 1. Tidak ada kursus, order atau konfigurasi merchant nyata yang dibuat oleh pengujian.
- Migrator CLI satu kali memakai admin socket lokal; runtime situs tetap `study_runtime` berhak minimum, PHP-FPM tetap production. Tidak mengubah service/database Finance atau Pustaka.
- Jangan menjalankan seluruh skrip fixture lama terhadap produksi. Field/aturan lama yang diasumsikan oleh tes lokal perlu disesuaikan dengan kuota dan mode review yang sekarang eksplisit.

## Bukti pengujian terarah

Database QA `study_qa_monitor_settings20261016` berisi fixture sintetis, dibuat dari schema tanpa data produksi. Server QA hanya loopback 8097. Server/database sementara dibersihkan setelah verifikasi; fixture bisa dibuat ulang dari skrip, bukan dipulihkan ke produksi.

- `scripts/test-settings-http.mjs`: sukses simpan/baca dan pemakaian branding publik, validasi/email/simbol, escaping, CSRF, ordinary/editor denied termasuk alias, konflik revisi, audit dan rollback ketika trigger QA menggagalkan audit. Review mandiri diuji mode mati, checklist kurang, editor sendiri ditolak, kualitas materi invalid, publikasi sukses, replay tidak menggandakan review serta audit gagal mengembalikan status/riwayat.
- `scripts/test-payment-secrets.php`: roundtrip dan nonce unik, penolakan ciphertext diubah/master key salah. Tidak mencetak secret.
- `scripts/test-payment-settings-http.mjs`: role/CSRF/validasi, mode live ditolak, secret tidak ada di HTML/audit/kolom plaintext, blank mempertahankan, rotasi menyimpan versi lama, konflik dan rollback audit. Opsi `STUDY_PROBE_NEGATIVE=1` mengirim kredensial sintetis tidak valid ke endpoint status sandbox: **401 ditolak** dan uji ulang segera **409**. Tidak ada charge, settlement, callback atau uji kredensial merchant nyata.
- Kuota: dua proses CLI PHP dengan akun QA yang mempunyai satu anak membuat tepat satu anak tambahan; proses lain ditolak. Akun dewasa bukan parent ditolak. Kegagalan audit mengembalikan akun/relasi, tanpa menghapus anak existing. Harness `cli/Check_family_quota.php` hanya CLI, development, nama DB `study_qa_monitor_*` dan socket QA yang diizinkan; bukan endpoint publik.
- Browser Chrome: pengaturan umum/pembelajaran/pembayaran pada desktop 1280 dan mobile 360, tanpa overflow, menu aktif, keyboard path, isian secret kosong dan tanpa exception JavaScript. Ini bukan audit WCAG penuh atau UAT pemilik.
- Lint PHP 8.4 server, hygiene/indentasi dan naming terarah lulus; sesi kredensial tetap lulus. HTTPS publik 200, rute pengaturan/keluarga tanpa autentikasi mengarah ke login; akun runtime dapat membaca kedua tabel baru. Pengujian form simpan memakai QA, bukan mengubah identitas/secret produksi.

## Yang masih membutuhkan jawaban/akses pemilik

1. Kredensial **Midtrans sandbox** untuk merchant yang dimaksud, dimasukkan sendiri melalui form atau disediakan di lokasi privat server. Jangan kirim key di chat. Konfigurasi produksi masih kosong; keberhasilan QRIS/top up/settlement tidak dapat dibuktikan memakai key palsu.
2. Google OAuth untuk domain ini dan konfigurasi SMTP yang dapat mengirim ke alamat uji milik pemilik. Environment Project Pintar belum memiliki keduanya. Jangan mengambil secret Finance tanpa arahan. Pendaftaran publik tidak dibuka sebelum verifikasi/pemulihan berhasil.
3. Keputusan pencatatan trafik: agregat anonim dahulu atau tetap nonaktif. Identitas pengguna/anak, retensi dan persetujuan membutuhkan kebijakan yang disetujui. Belum ada event analitik/ID pelacakan yang diaktifkan atau histori direkayasa.

B3 keseluruhan/B4, onboarding/persetujuan, transaksi QRIS/ledger/paket/Platinum/slot, poin, battle dan UAT tetap belum selesai. Harga/durasi draft dapat diprototipe berikutnya, tetapi tidak dianggap final dan tidak boleh dijual live. Tidak ada klaim bebas bug atau seluruh checklist selesai.
