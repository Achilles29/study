# Monitoring operasional — rilis 042, 18 Oktober 2026

Pembaruan20Oktober: [rilis072](TRAFIK_072.md) menambahkan kelompok tamu perIP, perujuk tersanitasi, pathaman dan tabel tetap. Catatan042 di bawah adalah riwayat; versi kebijakan kini2026-10-20-v3, schema51. Retensi dan pengecualian akun tetap.

## Penggunaan

- Buka `/kelola/trafik` sebagai Superadmin. Tab: Ringkasan, Per Halaman, Per Pengguna, Log Akses. Filter tanggal WIB (7 hari), kelompok halaman, tamu/dewasa/orang tua, perangkat dan bot. Rincian halaman/pengguna menautkan Log Akses terfilter; pagination 20.
- `/kelola/pengaturan/privasi`: toggle **Monitoring operasional** terpisah dari **Analitik opsional** dan **Trafik akun anak**. Aktivasi produksi yang diminta pemilik hanya monitoring operasional. Menjeda tidak menghapus histori atau menghentikan retensi.
- `/kelola/trafik?sumber=opsional` mempertahankan laporan lama berbasis persetujuan. Menyalakan monitoring tidak memalsukan/mengisi persetujuan pengguna.
- Tamu ditampilkan sebagai tamu, bukan nama yang ditebak. Alamat IP berbeda bukan jumlah manusia/sesi; IP dapat dipakai bersama atau berganti. Negara adalah kode dua huruf perkiraan, bukan kota/GPS/alamat rumah. Negara kosong ditampilkan Tidak tersedia, tanpa lookup IP ke pihak ketiga. Perangkat/browser/OS hanya klasifikasi user-agent dan dapat dipalsukan.

## Tujuan, minimisasi dan batas

Permintaan pemilik: mengetahui akses dan memeriksa gangguan/penyalahgunaan layanan. Penilaian internal memilih log first-party terbatas sebagai monitoring operasional, bukan profiling iklan. Kebutuhan IP penuh dibatasi pada diagnosis jaringan/akses dan hanya tab Log Akses milik Superadmin; pembukaan tab diaudit sebelum detail ditampilkan. Waktu/kelompok halaman serta akun dewasa yang sedang login membantu diagnosis dukungan; tidak menggabungkan identitas tamu dengan akun atau membuat fingerprint/cookie pelacak.

Risiko: IP/akun/waktu dapat mengidentifikasi seseorang; metadata perangkat/lokasi tidak selalu akurat, dan usia tamu tidak diketahui. Pembatasan: hanya GET berhasil pada allowlist kelompok halaman, tanpa URL/query/referrer/user-agent mentah, ID konten individual, isi formulir/soal/pesan, auth, pembayaran, AJAX, aset, akun anak atau pengelola. Bot/alat diberi label, bukan dianggap pasti manusia. Kunjungan yang dilayani cache sebelum mencapai aplikasi tidak terlihat. Ini bukan log seluruh request HTTP atau catatan keamanan infrastruktur lengkap.

Tidak memasang iklan atau analitik anak. Data tidak diekspor/ditampilkan publik. Halaman laporan no-store/noindex; role, metode dan filter diperiksa server. Kegagalan audit membatasi pembukaan detail; kegagalan pencatatan tidak memutus halaman pengguna dan dicatat sebagai kegagalan tanpa data koneksi dalam log SQL. Penolakan analitik opsional tetap berlaku pada sumber opsional, bukan mematikan log operasional yang dijelaskan terpisah. Keberatan/akses/koreksi/penghapusan ditangani lewat Bantuan, bukan dijanjikan otomatis.

Penilaian ini keputusan implementasi terbatas, bukan opini hukum atau sertifikasi kepatuhan. Dasar rujukan: [UU 27/2022 Pasal 20, 25, 27–28](https://peraturan.bpk.go.id/Home/Download/224884/UU%20Nomor%2027%20Tahun%202022.pdf). Tinjau ulang sebelum memperluas tujuan, profil, retensi, kota/lokasi atau data anak.

## Teknis, retensi dan deployment

- Migration **041** aditif: `st_operational_events`, `st_operational_state`; rilis UI041 sebelumnya tetap berskema 40. Tidak reset data/ID. Versi rilis ini 042, skema 41.
- Detail berakhir setelah 7 x 24 jam dan langsung tidak ditampilkan bila kedaluwarsa. Timer retensi existing per jam menghapus maksimal 10.000 baris operasional per batch, termasuk saat toggle nonaktif. Backlog besar memerlukan pemantauan; tidak menjanjikan penghapusan tepat pada detik kedaluwarsa. Tidak mengubah jadwal/retensi tabel belajar, akun, transaksi atau log infrastruktur.
- Backup privat dapat memuat data yang belum kedaluwarsa saat dicadangkan; akses/retensi backup dikelola terpisah. Jika restore, jalankan retensi sebelum menghidupkan laporan agar data kedaluwarsa tidak digunakan kembali.
- IP hanya dari `REMOTE_ADDR` yang ditetapkan Nginx. Header HTTP IP kiriman langsung tidak dipercaya aplikasi. Negara dari parameter FastCGI `STUDY_COUNTRY`, diisi Nginx hanya untuk koneksi bridge localhost tepercaya; bukan langsung dari `HTTP_CF_IPCOUNTRY`. Konfigurasi hanya vhost Project Pintar, tidak mengubah Finance/Pustaka atau callback pembayaran. Rujukan: [Cloudflare HTTP headers](https://developers.cloudflare.com/fundamentals/reference/http-headers/).
- Kebijakan publik versi **2026-10-18-v2** menjelaskan data/tujuan/retensi/hak, footer dan Preferensi Privasi membedakan dua sumber. Hash kebijakan Google berubah sebagaimana desain existing: formulir onboarding lama harus dimuat ulang, kredensial/provider tidak diubah.
- Diperbaiki juga normalisasi CRLF textarea pengaturan sebelum validasi karakter kontrol; formulir browser multi-baris kini dapat disimpan. NUL/control lain tetap ditolak.
- Backup sebelum perubahan: `/www/backup/projectpintar-traffic042-i3eIJWOQ`. Skrip aktivasi/cadangan private ada di `/www/study-private/traffic042-KGLKrmrX`; jangan deploy `cli/Activate_operational.php` atau fixture QA. Rollback aman: nonaktifkan monitoring lewat pengaturan, pulihkan kode/vhost sesuai backup, pertahankan tabel baru dan data; jangan restore dump di atas transaksi baru.

## Bukti uji

- `test-operational-client042.php`: sumber IP/country tepercaya, IPv6, invalid input, klasifikasi, normalisasi CRLF dan penolakan NUL.
- `test-operational-traffic042.mjs`: on/off, tidak butuh cookie/persetujuan untuk log operasional, sumber opsional tidak berubah, tamu/member/bot, pengecualian anak/admin/auth, spoofing header, tanpa query secret, role/metode/CSRF/filter, audit gagal tertutup, write gagal tanpa memutus layanan, retensi terjadwal.
- `test-operational-browser042.mjs`: Chrome 1280/360, keempat tab berdata, pengaturan/preferensi, filter keyboard, simpan off/on nyata, tanpa overflow/JS error. Screenshot diperiksa; bukan audit aksesibilitas penuh.
- Regresi `test-google-registration039.mjs` lulus provider sintetis. Lint PHP berubah dan CI3 naming lulus. Google nyata baru/UAT pemilik tetap belum dibuktikan oleh tes ini.
