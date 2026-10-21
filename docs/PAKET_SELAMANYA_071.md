# Paket Selamanya dan top-up disetujui — 071

19 Oktober 2026. Pemilik menyetujui lima harga top-up dan Rp300.000 sekali bayar, selamanya, dua slot anak, tanpa kuota harian. Sudah dipasang dan diaktifkan di produksi. Tidak mengulang instalasi atau mereset data/ID.

## Produk aktif

| Produk | Harga rupiah | Manfaat | ID produksi |
| --- | ---: | --- | ---: |
| Top up | 20.000 | 500 kredit | 14 |
| Top up | 50.000 | 1.400 kredit | 15 |
| Top up | 100.000 | 3.000 kredit | 16 |
| Top up | 150.000 | 4.800 kredit | 17 |
| Top up | 200.000 | 7.000 kredit | 18 |
| Platinum Selamanya | 300.000 | Orang tua + dua anak, tanpa tanggal kedaluwarsa | 19 |

Harga/ketentuan snapshot tersimpan pada pesanan. Satu pembayaran, bukan tagihan berulang. Paket selamanya tidak menambah saldo kredit, tidak mencakup donasi atau kapasitas anak tambahan. Kursus tetap gratis untuk semua akun. Bank produksi masih kosong; paket tidak berarti materi/simulasi yang belum diterbitkan sudah tersedia.

## Cakupan dua anak dan kuota

- Dua slot **termasuk**, bukan tambahan atas dua slot dasar. Kapasitas tambahan yang pernah dibeli dan semua anak/progres lama tetap dipertahankan.
- Untuk keluarga dengan lebih dari dua anak, hak mengikuti **dua tautan anak aktif paling awal**, berdasarkan ID penautan; label ada pada halaman keluarga. Anak ketiga tidak otomatis mendapat Platinum. Saat tautan/akun anak dinonaktifkan, slot manfaat tersedia untuk anak aktif berikutnya; memulihkan anak lama mengembalikan urutan awal. Tidak ada tombol pemindahan hak ke keluarga lain.
- Orang tua dan dua anak tercakup memperoleh Arena, bank lanjutan semua kelas, Simulasi Ujian, serta latihan keluarga privat tanpa pembelian kredit tambahan. Pembelian fitur/kuota yang sudah tercakup menjadi no-op tanpa debit, tetapi otorisasi payer/penerima tetap diperiksa.
- Kuota harian **Latihan Mandiri dan percobaan Simulasi Ujian** dihapus untuk penerima Platinum. Pemakaian tetap dicatat untuk monitoring. Timer ujian, maksimal50soal/180menit, satu sesi berjalan, prasyarat progres Arena, moderasi, keamanan dan batas teknis battle tidak dihapus. Batas akun gratis tidak berubah.
- Hak tanpa tanggal akhir: `is_lifetime=1`, `ends_at=NULL`; bukan100tahun atau angka durasi besar. Hak berjangka lama tetap berjangka. Hak sandbox tidak berlaku di produksi; status akun/relasi/grant tetap diperiksa.

## Coba sebagai pengguna

1. Orang tua terverifikasi: `/akun/kredit` menampilkan enam produk di atas. Pilih produk dan centang persetujuan harga sebelum membuat QRIS. **Ini LIVE, menggunakan uang nyata.** Uji pembayaran nyata hanya dilakukan pemilik, bukan agen.
2. Buka pesanan yang sama untuk memeriksa status. Saldo/hak muncul hanya setelah server memverifikasi settlement. Timeout bukan alasan membuat pesanan/pembayaran baru. Paket selamanya yang sedang pending atau sudah dimiliki tidak dapat dibeli ulang dengan intent berbeda.
3. Setelah Platinum aktif, lihat banner `/akun/kredit`, badge `/keluarga/anak`, label tanpa kuota `/latihan/mandiri` dan `/latihan/ujian/{id}`. Bank/ujian perlu diisi dan diterbitkan agar dapat dimulai. Kursus gratis ada di `/kursus` dan `/latihan/kursus`.
4. Pengelola: `/kelola/paket`, produk `/kelola/produk/19`, pesanan `/kelola/pesanan`. Form menampilkan tipe selamanya, durasi0, dua anak dan tanpa kuota. Jenis masa akses tidak dapat diubah pada produk existing; buat produk baru bila ketentuan berbeda. Edit tidak mengubah snapshot pesanan lama.
5. Refund/sengketa ditandai untuk rekonsiliasi, memblokir transaksi baru; tidak otomatis menghapus anak, membuat saldo negatif atau mencabut riwayat. Penanganan manual mengikuti prosedur existing.

## Bukti pengujian

- `scripts/test-lifetime071.php`: **94 pemeriksaan lulus** di `study_qa_monitor_sso039`. Lima nominal top-up/saldo/ledger tepat dan exactly-once; settlement wajib authoritative; callback palsu/nominal salah/charge response ditolak; rollback grant+purchase+status pada kegagalan simpan, replay, pembelian ulang, stale stamp/quote, child/admin isolation, dua anak/anak ketiga/keluarga asing/unlink/nonaktif/revoked/future start, sandbox terpisah, akses tanpa debit, latihan di atas kuota, ujian berbayar di atas batas harian, monitor/latihan privat, refund-review. Pesanan Platinum tahunan lama tetap bisa diselesaikan sebagai berjangka.
- `scripts/test-lifetime071.mjs`: HTTP CSRF/izin; dua checkout bersamaan menghasilkan satu order; dua callback terverifikasi bersamaan satu grant; harga order tetapRp300.000 meski produk diubah/dijeda; UI/label dan keyboard pada1280/360 tanpa overflow atau JS/runtime error. Bug loader view dan overflow badge ditemukan di QA, diperbaiki sebelum deploy dan tes akhir lulus. Transport pembayaran hanya direktori sintetis `/tmp/study-payment-qa-5eKTYWUz`, tidak menghubungi Midtrans nyata.
- `test-sale-policy070.php` tetap lulus: produk course-only/voucher dan Platinum tahunan tidak ikut diaktifkan sebagai penjualan baru. Lint file071 dan diff-check lulus.
- Produksi schema50, enam produk active/live-approved, konfigurasi pembayaran terakhir production/checkout1/status_api_reached dipertahankan. PublikHTTP200, kredit tanpa login307, sourcePHP404, FPM aktif. Hash12tabel akun/keluarga/order/config/wallet/ledger/purchase/slot/kursus/materi/attempt/settings identik sebelum–sesudah. Tidak ada order atau grant pembayaran nyata yang dibuat untuk tes. Ini bukan klaim pembayaran bank nyata, UAT pemilik atau seluruh roadmap tuntas.

## Deployment dan preservasi

- Backup `/www/backup/projectpintar-lifetime071-1sATBqtH`: SQL/source sebelum dan sesudah. Staging `/www/study-private/lifetime071-NeCfQPnm` hanya QA, **bukan dependensi situs produksi**.
- Migration050 hanya menambah flag pada offers/grants dan membuat ends_at grant nullable. Akun DB web sengaja tidak diberi hakDDL; percobaan awal dengan akun web ditolak, tidak mengubah schema. Eksekusi berhasil memakai root lokal CLI terisolasi. Kredensial/izin web tidak diperluas.
- `cli/Activate_prices` hanyaCLI+DBallowlist+`STUDY_ACTIVATE_071=approved-2026-10-19`, transaksi/audit, memeriksa angka approved dan menjaga perubahan owner. Memperbarui ID14–18 dan menambah produk19; tidak mengubah produk12 tahunan atau order lama. Tidak memerlukan perubahan config/secret Midtrans.
- Hanya layanan `projectpintar-php-fpm` direload. Finance/Pustaka dan endpoint callback existing tidak diubah. Source produksi diperiksa sama dengan backup sebelum menyalin file071.

## Tetap terpisah / belum selesai

- **Gate penukaran kredit per fitur Arena/latihan masih nonaktif** (`akses_arena.enabled=0`, `latihan_mandiri.paid_enabled=0`). Persetujuan lima top-up bukan persetujuan seluruh draft harga/pemetaan level/kuota akun gratis. Platinum sudah memberi hak di atas meskipun gate kredit belum dinyalakan.
- Isi bank/simulasi, token/IDgrup Telegram, UAT, review pedagogis dan pembayaran nyata pemilik belum ditandai selesai. Jangan mengarang ulasan publik atau materi baru massal.
