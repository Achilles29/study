# Donasi sukarela QRIS — rilis 043, 18 Oktober 2026

## Alur dan URL

- `/donasi`: dukungan seikhlasnya untuk pengembangan/operasional Project Pintar, nominal Rupiah bulat, pilihan cepat, konfirmasi, QRIS dinamis per pesanan dan riwayat sendiri.
- `/donasi/pesanan/{id}`: QRIS dan pemeriksaan status; hanya pemilik atau Superadmin. Akun dewasa/orang tua aktif dengan email terverifikasi dapat membuat donasi; akun anak ditolak. Tidak ada daftar nama/nominal donatur publik.
- `/kelola/donasi`: laporan terpisah Live/Sandbox, filter status, pagination 20, nominal bruto terverifikasi tanpa kasus refund/sengketa, detail dan rekonsiliasi.
- `/kelola/pengaturan/donasi`: aktif/jeda, minimum dan maksimum. Default Rp1–Rp10.000.000, sesuai batas QRIS provider; aplikasi pembayaran/merchant dapat membatasi lebih lanjut. Perubahan hanya untuk pesanan baru, berversi dan diaudit.
- Tautan pengguna ada pada footer dan pengaturan akun; menu Donasi tersedia pada panel pengelola.

Donasi bukan pembelian kredit, paket, Platinum, slot anak, poin, keuntungan permainan, penggalangan dana pihak ketiga, atau bukti pengurang pajak. Tidak ada biaya tambahan aplikasi; penerimaan merchant dapat dipotong biaya provider. Permasalahan/refund melalui Bantuan dan peninjauan merchant, bukan refund otomatis atau janji refund.

## Kesiapan pembayaran nyata

**Pembaruan 045, 18 Oktober 2026:** pemilik meminta aktivasi oleh operator. Bug parser HTTP200/API404 diperbaiki; probe nyata berhasil, profil Production ID2 checkout aktif, credential/merchant tetap. Modul donasi aktif. Instruksi aktivasi di bawah dipertahankan sebagai panduan untuk konfigurasi baru/rotasi, bukan langkah yang masih wajib diulang saat ini. Pembayaran QRIS/settlement uang nyata belum dicoba; produk draft tetap tidak dijual. Bukti dan backup lihat bagian rilis045 pada handover.

Pada pemeriksaan sebelum rilis, profil Production tersimpan tetapi `probe_result=NULL`, `checkout_enabled=0`, dan belum ada pesanan produksi. Jangan menyatakan donasi uang nyata sudah berhasil. Modul dapat aktif, tetapi formulir pembayaran tidak dibuka sebelum gateway siap; informasi publik menyatakan penerimaan sedang disiapkan.

Pemilik masuk sebagai Superadmin:

1. Buka `/kelola/pengaturan/pembayaran?environment=production`. Periksa Merchant ID dan kesiapan QRIS pada akun Midtrans yang benar. Kunci tersimpan tidak perlu diketik ulang.
2. Pada **Kesiapan integrasi**, isi sandi Superadmin dan klik **Uji koneksi status API Production / Live**. Uji tidak membuat tagihan; hasil sukses hanya membuktikan endpoint status merespons, bukan QRIS/merchant/settlement nyata.
3. Setelah sukses dan merchant diperiksa, kosongkan Server Key/Client Key untuk mempertahankan kunci, pilih **Pembuatan pembayaran nyata baru → Aktif**, centang konfirmasi, isi sandi Superadmin, lalu **Simpan konfigurasi Production / Live**. Ini mengaktifkan gateway bagi produk yang disetujui dan donasi yang modulnya aktif; tidak menerbitkan produk draft.
4. Pastikan modul Donasi aktif. Akun dewasa terverifikasi membuka `/donasi`, isi nominal kecil yang diinginkan, periksa merchant/nominal saat memindai, lalu bayar sekali. Pastikan status diterima muncul otomatis atau melalui **Periksa status Midtrans** dan cocok dengan Dashboard Midtrans serta laporan pengelola.
5. Jangan membayar ulang ketika status belum pasti. Periksa pesanan yang sama atau hubungi Bantuan. UAT uang nyata hanya dilakukan pemilik; agen tidak melakukan charge nyata atau melewati reautentikasi.

## Pemisahan data dan keamanan

Migration aditif 042 menambah `st_payment_orders.purpose` beserta indeks dan satu bagian pengaturan. Pesanan lama tetap `commerce`, donasi `donation`. Jumlah tabel tetap 68. Tidak mengulang instalasi/reset ID; tidak mengubah saldo/progres, credential Google/Midtrans, Finance/Pustaka maupun callback global merchant.

Donasi memakai mesin pembayaran existing: satu percobaan charge per request key, lock pemilik dan transaksi atomik, nominal/settings/config disimpan sebagai snapshot, maksimal 10 pesanan per akun per jam, CSRF, verifikasi akun dan otorisasi server. Callback memeriksa tanda tangan lalu GET status otoritatif; nominal, merchant, mata uang, lingkungan, metode dan ID transaksi harus cocok. Tidak mempercayai redirect/screenshot. Rotasi/jeda konfigurasi tidak memutus pesanan lama. Pemeriksaan manual dibatasi 15 detik; timeout tidak memicu charge ulang otomatis.

Penyelesaian donasi berhenti sebelum alur penerbitan kredit/hak. `credit_amount=0`, `offer_id=NULL`, snapshot tujuan diperiksa dan audit `donation.received` tepat sekali. Wallet nol mungkin dibuat hanya untuk FK existing; tidak ada ledger, pembelian, grant, poin atau slot dari donasi. Refund/sengketa memerlukan review dan dikeluarkan dari total penerimaan tanpa masalah; saldo laporan bukan saldo pencairan bank.

Halaman/detail privat no-store; identitas donatur tidak dipublikasikan. Data transaksi dan tujuannya dijelaskan sebelum submit. Route donasi tidak ditambahkan ke allowlist monitoring/analitik. Tidak menambah penyedia data atau mengubah persetujuan privasi/SSO existing.

## Bukti pengujian terarah

Produksi sudah diterapkan: skema 42 / 68 tabel, 26 berkas terarah. Cadangan sumber/SQL dan manifest privat: `/www/backup/projectpintar-donation043-fGccimme`. Fingerprint data existing/pengaturan selain donasi dan konfigurasi Google/Midtrans cocok sebelum/sesudah; nol pesanan nyata. HTTP donasi/masuk/daftar/privasi 200, route privat diarahkan login, sumber/docs/scripts ditolak. Chrome publik 1280/360 lulus tanpa overflow/JS error. Layanan aktif dan tidak ada error PHP/database baru pada smoke. Checkout tetap nonaktif, bukan pembayaran live yang sudah terbukti.

- `scripts/test-donations043.mjs`: lulus nominal/tipe/CSRF/revisi/konfirmasi, guest/parent/child/antarakun, verifikasi email, replay/race dua sesi, callback/signature/amount/merchant/currency/metode/environment/ID palsu, status otoritatif, rollback audit, settlement duplikat/terlambat, refund-review, timeout/reconcile/throttle, modul dijeda dan rotasi config pesanan lama, URL QR allowlist, saldo/hak tidak berubah. Regresi top up biasa menerbitkan kredit tepat sekali.
- `scripts/test-donations-browser043.mjs`: lulus Chrome 1280/360, tamu/dewasa/pengelola, detail QR sintetis, pilihan nominal/fokus, simpan pause/resume lewat keyboard dan persistensi, formulir tanpa JS, tanpa overflow/JS error. Screenshot mobile dan laporan desktop ditinjau.
- Semua charge/callback di database QA terisolasi dengan transport sintetis; bukan bukti pembayaran nyata. Fixture/kunci sintetis/seed CLI tidak disalin ke produksi. Tidak melakukan uji seluruh roadmap, audit aksesibilitas penuh, atau menjamin nihil bug.

## Referensi

Batas nominal mengacu [batas minimum/maksimum Midtrans](https://docs.midtrans.com/docs/berapa-jumlah-transaksi-minimal-dan-maksimal-yang-dapat-diterima-oleh-midtrans); kontrak charge mengacu [QRIS Core API](https://docs.midtrans.com/reference/qris), verifikasi pembayaran mengacu [HTTP notification webhook](https://docs.midtrans.com/docs/https-notification-webhooks). Ditinjau 18 Oktober 2026. Ketentuan merchant dan biaya perlu diperiksa pada akun pemilik.

Rollback aman: jeda modul donasi dan perbaiki maju. Jangan menghapus kolom/riwayat/pesanan atau memulihkan dump lama di atas transaksi baru. Kode lama tidak mengenali tujuan donasi; setelah donasi dibuat, jangan mengembalikan mesin fulfillment lama karena dapat salah memperlakukan transaksi. Backup sumber/SQL sebelum deploy disimpan privat dan dicatat pada handover.
