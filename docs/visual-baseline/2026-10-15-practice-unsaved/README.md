# Jawaban latihan yang belum tersimpan

Tanggal: 15 Oktober 2026. Bukti lokal Windows/XAMPP Apache PHP 8.0.30, MariaDB dan Chrome 155.0.8059.79. PHP 8.1 tetap target yang diminta; perubahan ini belum diuji lewat HTTP PHP 8.1 karena runtime terpisah belum tersedia kembali.

## Perubahan dan batasnya

- Pengerjaan `in_progress` milik peserta memakai komponen `unsaved-form.js` yang sudah dipakai editor; kode komponen bersama tidak diubah. Form kursus dan sesi keluarga mempunyai petunjuk simpan manual serta status perubahan yang sopan dan baru terlihat ketika skrip siap.
- Pilihan yang diubah memasang pengaman keluar. Mengembalikan pilihan ke nilai server menghapus pengaman. Field CSRF/revisi bukan perubahan jawaban.
- Submit native tidak dianggap berhasil oleh skrip. Respons konflik menampilkan kembali jawaban/revisi server sebagai acuan bersih, disertai pesan penolakan. Tidak memulihkan POST yang ditolak, tidak menambah autosave, penyimpanan browser, endpoint atau transaksi baru.
- Model menolak status selain `in_progress`/`graded`; sebelumnya percobaan kursus `submitted`/`abandoned` dengan akses kursus masih dapat dibaca sebagai form walau simpan selalu ditolak. Alur normal kursus belum menghasilkan dua status tersebut; penolakan ini menutup inkonsistensi laten, bukan mengubah histori atau menambah alur pembatalan.
- Hasil final/laporan orang tua tetap hanya-baca. Label pengerjaan sesi orang tua kini “Latihan keluarga”, bukan “Latihan mandiri”.

## Bukti pengujian

| Pemeriksaan | Hasil dan cakupan |
| --- | --- |
| Unit model akses | 81 assertion pada PHP 8.0/8.1/8.5: pemilik, status tepat, histori final, kursus/keluarga dan batas lookup. Tes mereproduksi kegagalan status kursus sebelum perbaikan lalu lulus sesudahnya. |
| Unit view | 20 render pada ketiga runtime: kedua jenis latihan, jawaban/revisi server setelah data error 400/409, markup aman, kunci tidak tampil sebelum final, hasil/laporan/status lain tanpa kontrol tulis. Setelah label keluarga diperbaiki, unit diulang pada ketiganya. Ini renderer dengan helper tiruan, bukan respons HTTP. |
| Unit UI | Seluruh `npm run test:ui` lulus, termasuk 15 kelompok komponen perubahan isian. Guard tidak melakukan network/storage pada unit tersebut. |
| HTTP kursus | `practice-check.ps1` lulus: authoring/review, snapshot, pilihan invalid, CSRF/IDOR, simpan/final/replay, skor 0/100/33,33%, riwayat, akses dicabut dan laporan keluarga. Tambahan GET/POST pada status `submitted`/`abandoned` mendapat 403 tanpa mengubah status/revisi; hasil final dan laporan tidak memuat guard. |
| HTTP keluarga | `family-delivery-check.ps1` lulus termasuk jadwal, entitlement/tautan, simpan/final, pembatalan, salin sesi dan cleanup. Diulang penuh setelah perubahan label keluarga. |
| Browser halaman | Kursus: sembilan keadaan × tiga lebar = 27 kombinasi; keluarga: enam keadaan × tiga lebar = 18. Tidak ditemukan overflow dokumen, kegagalan label heuristik atau fokus skip-link. Regresi keluarga 18 kombinasi diulang setelah perubahan label; bukan 18 keadaan baru. |
| Browser pengaman | Dua jenis attempt × 360/768/1280 = enam kombinasi: radio ubah/kembalikan, Space native, hidden revision diabaikan, dialog keluar nyata dibatalkan dan pilihan tetap ada, tautan riwayat saat bersih, kembali dengan GET, serta POST native `save` versi usang benar-benar HTTP 409 tanpa dialog keluar. Pilihan/revisi server kembali identik. Fallback tanpa JavaScript tetap mempunyai form/CSRF/tombol manual dan status tersembunyi. |
| Preservasi data | Isi attempt, jawaban, snapshot soal dan audit attempt dibandingkan tepat sebelum/sesudah interaksi browser. Tidak berubah. Login fixture tetap mempunyai efek login/audit normal; ini bukan klaim seluruh database tidak pernah ditulis sepanjang suite. |
| Regresi peran | `role-access-check.ps1` lulus untuk admin sistem, editor, keuangan, dukungan dan akun biasa, termasuk alias/controller langsung serta audit tindakan arsip. Fixture dibersihkan. |
| Sintaks dan hygiene | Smoke/lint 338 PHP pada 8.0/8.1/8.5, timezone, aset dan penolakan path privat lulus. Label final diperiksa ulang dengan lint/unit view ketiganya. Kedua PHPCS serta penamaan 161 source lulus; parser 39 PowerShell, sintaks JS, docs/konflik/secrets lulus. Full `qa-local.ps1` tidak dijalankan. |

Penguji browser hanya menerima akun sintetis dengan pola khusus dan satu route attempt. Dua pemanggilan tanpa konfigurasi yang disyaratkan ditolak sebelum Chrome diluncurkan. Penguji memakai server Apache yang sudah ada; tidak menyalakan server PHP/Nginx baru. Profil browser milik run dibersihkan oleh harness; profil/proses lama tidak disentuh.

Pemeriksaan akhir seluruh 33 tabel: hanya empat peran, satu baris migration versi 13 dan satu kegagalan login yang sudah ada sebelumnya (ID 3067). Tiga puluh tabel lain kosong; tidak ada skema dengan prefix QA yang diperiksa. Log aplikasi hanya `index.html`. Data sintetis milik suite sudah dihapus setelah pengujian; tidak ada data pengguna nyata yang dihapus. Screenshot tetap disimpan sebagai bukti. Pemeriksaan dokumen terakhir lulus 53 dokumen/54 route/48 kontrak, 535 berkas teks tanpa marker konflik dan pemindaian rahasia lulus.

### Percobaan awal yang belum lulus

1. Query pembanding penguji memakai `ORDER BY id` pada `st_session_questions`, padahal kuncinya gabungan `session_id/question_id`. Proses gagal, fixture dibersihkan; urutan diganti `question_id` dan suite diulang.
2. Pemeriksaan browser memakai `form.action`, yang tertutupi kontrol bernama `action`. Penguji diperbaiki membaca atribut form, bukan mengubah endpoint produk atau melonggarkan tujuan yang diizinkan. Validasi direktori capture juga diselaraskan dengan direktori sementara kanonis milik harness.
3. Review gambar setelah run hijau menemukan label keluarga masih memakai label kursus. View, assertion unit dan assertion HTTP diperbaiki; suite keluarga/Chrome diulang sampai lulus. Capture keluarga di folder ini berasal dari run terakhir, bukan gambar label lama.

## Capture yang ditinjau

Kelima berkas di bawah diperiksa langsung. Pesan simpan/perubahan terbaca, pilihan dan fokus tidak bertumpuk, tombol tetap terjangkau, dan hasil akhir tidak menampilkan form atau pesan perubahan. Pemeriksaan screenshot tidak membuktikan dialog native atau penulisan data; bukti tersebut berasal dari tes perilaku/HTTP.

- [Kursus, perubahan belum disimpan, 360](course-dirty-360.png).
- [Kursus, perubahan belum disimpan, 1280](course-dirty-1280.png).
- [Keluarga, perubahan belum disimpan, 360](family-dirty-360.png).
- [Keluarga, perubahan belum disimpan, 1280](family-dirty-1280.png).
- [Hasil kursus hanya-baca, 1280](course-result-1280.png).

Semua identitas/soal adalah fixture sintetis yang dibersihkan, bukan konten resmi. Teks markup pada soal kursus sengaja menguji escaping; penanda pembahasan sintetis sengaja membuktikan kunci tidak bocor sebelum final. Jangan memakai capture sebagai contoh mutu materi. Lima PNG disalin dari output penguji ke folder baru tanpa menimpa berkas, dan hash sumber/tujuan cocok. Capture halaman lain dalam suite tidak dinyatakan telah ditinjau satu per satu.

## Mengulang

Jalankan dari root `study` pada server lokal yang sudah aktif, **berurutan**, bukan bersamaan dengan suite fixture lain:

```powershell
& scripts/practice-check.ps1 -Php81Path C:\tools\php81-study\php.exe -MysqlPath C:\xampp\mysql\bin\mysql.exe -BaseUrl http://localhost/study/index.php/ -ChromePath 'C:\Program Files\Google\Chrome\Application\chrome.exe' -CaptureScreenshots
& scripts/family-delivery-check.ps1 -Php81Path C:\tools\php81-study\php.exe -MysqlPath C:\xampp\mysql\bin\mysql.exe -BaseUrl http://localhost/study/index.php/ -ChromePath 'C:\Program Files\Google\Chrome\Application\chrome.exe' -CaptureScreenshots
```

Kedua suite memanggil mode pengaman browser hanya pada attempt aktif sintetis. Helper browser tidak mengirim finalisasi/simpan berhasil; hanya satu POST `save` dengan revisi sengaja usang per lebar. Simpan/final berhasil tetap diuji melalui suite HTTP, bukan diklaim sebagai interaksi browser baru. Perubahan jawaban yang benar-benar disimpan tetap melalui server seperti sebelumnya.

Belum dibuktikan: offline, penutupan paksa/browser mobile, BFCache nyata lintas browser, pembaca layar, zoom halaman privat, gangguan jaringan saat submit, request paralel baru, PHP 8.1 HTTP, Ubuntu/Nginx/PHP-FPM, UAT atau rilis produksi. Pengujian history pada helper ini memakai tautan riwayat dan GET eksplisit kembali, bukan tombol Back/Forward atau pemulihan BFCache. Tidak ada perubahan skema, pembayaran, harga atau hak paket.
