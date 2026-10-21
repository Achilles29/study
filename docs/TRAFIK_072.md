# Trafik 072 — 20 Oktober 2026

## Hasil pemeriksaan awal, sekitar 12.43 WIB

- Aplikasi mencatat **143 akses dari 43 IP**, semuanya tanpa login. Hanya 3 event dikenali sebagai bot/alat dari user-agent. Ini bukan jumlah manusia.
- Nginx mencatat 476 request, termasuk 196 respons404 serta aset/redirect yang tidak masuk laporan aplikasi. Jangan menyamakan request server dengan kunjungan dashboard.
- Satu jaringan meminta **100 URL dalam 5 detik**, pada09.20.00–09.20.05 WIB, termasuk64alamat materi. **82 event aplikasi** berasal dari lonjakan ini. User-agent mengaku Chrome/Windows. Pola ini kuat mengindikasikan crawler/otomatisasi, bukan bukti identitas pasti.
- Jaringan lain membuat127request dalam72detik, termasuk33path seperti WordPress/berkas konfigurasi. Ada user-agent yang mengaku Applebot, Googlebot, OAI-SearchBot, GPTBot dan meta-webindexer; identitas vendor tidak diverifikasi dengan DNS/IPvendor.
- Saat pemeriksaan awal tidak ditemukan user-agent curl/HeadlessChrome/Node/Undici/ProjectPintar-QA hari ini. IP lonjakan5detik berbeda dari jaringan curl/HeadlessChrome pada pemeriksaan kemarin. Perujuk yang tersedia hanya domain situs sendiri atau kosong; tidak ada host mesin pencari eksternal. **Sisanya tidak dapat dipastikan manusia/organik.**
- Setelah pemasangan, agen membuat dua GET produksi (`/kursus`, `/privasi`) dengan user-agent `ProjectPintar-QA/072`. Keduanya berlabel bot/alat; total menjadi145event/44kelompokIP. Semua tes login/fixture dilakukan di QA, bukan dengan akun peserta nyata.

## Fitur dan URL mencoba

- `/kelola/trafik/pengguna`: akun login dikelompokkan perIDakun, tamu perIP. Satu IP bisa dipakai banyak orang; satu akun bisa memiliki beberapa IP. Aktivitas sebelum login tidak digabung ke akun. IP kosong ditandai sebagai gabungan catatan tanpaIP. Tautan detail mempertahankan akun atau guest+IP yang dipilih. IPv4/IPv6 divalidasi dan dinormalisasi; pagination20.
- `/kelola/trafik/log`: enam kolom — waktu, halaman tujuan, **IP di atas/pengunjung dan negara di bawah**, **perangkat di atas/browser dan sistem di bawah**, perujuk, indikasi bot. Tautan membuka halaman aman pada tab baru dengan `noopener noreferrer`.
- Perujuk dibedakan menjadi Internal, Mesin pencari, Situs lain, Langsung/tidak diketahui, atau tidak dapat dikenali. Domain luar disimpan tanpa path/query; internal hanya path yang diizinkan. Perujuk dapat dibatasi browser dan dipalsukan; “mesin pencari” juga bukan bukti trafik organik. [Mekanisme Referer, MDN](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Referer).
- Kepala tabel sticky di dalam area gulir; pagination tetap di luar area gulir. Keenam kolom muat pada desktop1280; ponsel360 memakai scroll tabel horizontal/vertikal dan mendukung keyboard. PerPengguna/PerHalaman juga memakai tabel ringkas.
- Data tujuan rinci/perujuk mulai dicatat setelah072. Data lama tetapNULL dengan label **“Belum direkam (data lama)”**. Tautan lama menuju kelompok halaman, bukan mengarang URL materi lama. URL sesi/akun privat juga diarahkan ke kelompok; tidak membuka aktivitas orang lain.

## Keamanan dan penyimpanan

Schema51 menambahkan empat kolom nullable pada `st_operational_events`: `destination_path`, `referrer_kind`, `referrer_host`, `referrer_path`. Tidak mengubah isi historis atau ID.

Tidak menyimpan query/fragment/token, rawuser-agent, path situs luar, URL sesi privat atau profil anak. Allowlist path berada pada `Traffic_navigation`. Superadmin saja; **kedua tab PerPengguna dan LogAkses diaudit sebelum membuka IP**. Gagal audit berarti503 tanpa data; gagal pencatatan tidak menggagalkan halaman belajar. No-store/noindex/no-referrer, escaping dan target same-site.

Pengecualian anak/pengelola/auth/pembayaran, retensi7hari dan analitik opsional tetap. Deteksi bot tetap indikasi user-agent, ditambah webindexer/penandaQA; tidak ada backfill klasifikasi lama atau pemblokiran otomatis IP.

Penjelasan monitoring pada kebijakan publik diperbarui menjadi `2026-10-20-v3`, melalui Settings_model+audit dan CLIguard072. Toggle harga/SSO/provider/retensi tidak berubah. Formulir pendaftaran dengan versi kebijakan lama perlu dimuat ulang sesuai mekanisme existing.

## Bukti pengujian dan preservasi

- `test-traffic-navigation072.php`:29pemeriksaan allowlist, URL privat, query/token, protocol/credentials/control characters, batas domain mesin pencari, data lama dan index.php.
- `test-operational-client042.php`: normalisasiIPv6, IP/country dari servertepercaya, bot/device/browser, invalidinput dan CRLF/NUL tetap lulus.
- `test-traffic072.mjs`: pencatatanperujuk HTTP, akun+tamu, detailIP, IPv6, pagination, role/pengecualiananak/admin, filterinjection, auditgagal tertutup kedua tab, pencatatan gagal tidak memutus halaman, analitik opsional tetap, browser1280/360, keyboard dan header/footer tetap. Setelah perapian CSS, tesUI terarah membuktikan semua kolom muat desktop dan mobile hanya menggulir tabel. Screenshot diperiksa.
- Notice diuji memakai teks kebijakan publik aktual di QA lalu fixture dipulihkan. Naskah awal terlalu panjang ditolak validator6000karakter; diringkas, diuji ulang, baru dipasang. Validator tidak dilonggarkan.
- Produksi `/kursus` dan `/privasi`200; traffic privat307 tanpa login. Dua eventprobe menyimpan path/perujuk/bot dengan benar; kegagalan pencatatan tetap0. Query kedua tab melalui akunDBweb lulus,20baris/page, dan diaudit. Kebijakan versi baru tampil.
- Checksum10tabel akun/keluarga/order/config/produk/wallet/ledger/grant/kursus/attempt identik sebelum–sesudah. Finance/Pustaka, callback, saldo dan harga tidak berubah.
- Backup: `/www/backup/projectpintar-traffic072-qZP0Ybiu` berisi SQL/source sebelum–sesudah dan screenshot. Staging `/www/study-private/traffic072-i47smzSG` hanyaQA, bukan dependensi produksi. Hanya FPMProjectPintar direload; tidak mengubahNginx.

Ini penyelesaian irisan trafik072, bukan seluruhroadmap, verifikasi identitas vendorbot, audit aksesibilitas menyeluruh atau kepastian jumlah manusia/konversi organik.
