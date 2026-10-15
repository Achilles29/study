# Lanjutkan belajar dari beranda

Tanggal: 15 Oktober 2026. Suite `scripts/dashboard-resume-check.ps1` selesai dengan exit code 0 pada Windows, MariaDB lokal, Apache XAMPP PHP 8.0.30 yang sudah berjalan, PHP 8.1.34 untuk CLI dan Chrome 155.0.8059.79. Ini bukti fitur lokal, bukan kelulusan seluruh roadmap atau rilis produksi.

## Hasil untuk pengguna

Peserta dewasa dan anak dapat melanjutkan paling banyak tiga materi sendiri dari beranda, tanpa mencari kembali melalui katalog. Hanya materi berjalan, terbit, dan masih dapat diakses yang ditawarkan. Tanggal menyatakan waktu progres terakhir disimpan dalam WIB; bukan durasi belajar atau persentase penguasaan. Tautan memakai halaman materi dan pemulihan posisi yang sudah ada. Ringkasan keluarga untuk orang tua tidak berubah.

Kartu terisi menggantikan ajakan memulai dari katalog sebagai langkah utama. Jika tidak ada materi yang dapat dilanjutkan, ajakan katalog tetap tersedia tanpa menghapus histori. Kegagalan query dibedakan dari daftar kosong melalui pesan dan tautan muat ulang; keadaan gagal ini diuji pada unit, bukan dengan merusak koneksi database HTTP.

## Cakupan pengujian

- Empat akun sintetis: dewasa, anak, dewasa lain tanpa progres, serta orang tua tertaut. Sembilan belas kursus/pelajaran menguji status dan jalur akses secara terpisah. Bukan konten atau akun pengguna nyata.
- Enam jalur positif diperiksa satu per satu: dua kursus gratis, materi pratinjau, enrollment manual, enrollment free, serta enrollment paket. Entitlement paket diuji dengan akhir berlaku dan tanpa akhir (`NULL`).
- Kandidat negatif sengaja lebih baru: belum mempunyai enrollment, enrollment revoked/completed, entitlement expired/future/revoked/ended, kursus/pelajaran draf atau arsip, serta progres completed/not_started. Penyaringan harus terjadi sebelum pembatasan tiga hasil. Waktu yang sama diurutkan dengan ID pelajaran menurun.
- Akun anak/dewasa lain/orang tua tidak melihat daftar milik peserta lain. Empat variasi parameter pengalih identitas, termasuk array, diabaikan. Orang tua tidak mendapat tautan pengerjaan atas nama anak.
- GET dan alias controller, HEAD tanpa body, penolakan POST ber-CSRF dengan 405/Allow, pengalihan anonim, header private/no-store/noindex/nofollow, Unicode dan escaping judul diperiksa.
- GET tujuan sah memuat posisi tersimpan `2700`. Setelah akses dicabut tanpa login ulang, kartu hilang dan tujuan lama ditolak. Progres tidak dihapus.
- Digest seluruh baris/kolom progres pelajaran dan audit dibandingkan sebelum/sesudah pembacaan yang diuji. Setiap peluncuran browser hanya mengecualikan catatan login password milik fixture, yang harus bertambah tepat satu. Ini bukan digest seluruh database, bukan bukti sesi tidak berubah, dan bukan pengujian pembayaran.
- Unit model/controller/view lulus pada PHP 8.0/8.1/8.5: kontrak query dan izin, sembilan kasus controller, daftar kosong/gagal/1–3 kartu, orang tua, escaping, formatter WIB, dan pemulihan pengaturan debug pada kegagalan query.

Regresi pendamping yang dijalankan: progres, matriks peran, smoke/lint 336 berkas PHP pada tiga runtime, dua ruleset PHPCS, naming 159 berkas pada tiga runtime, seluruh unit UI JavaScript, parser 39 skrip PowerShell, pemeriksaan dokumen/kontrak route, konflik, rahasia, lisensi aset, serta aturan Nginx statis. Orkestrator `qa-local.ps1` lengkap tidak dijalankan; suite dan unit baru sudah dihubungkan ke orkestrator tersebut untuk penggunaan berikutnya yang diizinkan.

## Tampilan dan artefak

Lima keadaan masing-masing diperiksa pada 360/768/1280 px: total 15 kombinasi. Pemeriksaan geometri, luapan horizontal, heuristik label, fokus skip-link dan navigasi terbuka lulus. Sepuluh PNG disimpan pada 360 dan 1280 px; tidak ada capture 768 px. Menu sengaja terbuka setelah pengujian navigasi, bukan gambaran keadaan awal menu.

| Keadaan | Ponsel | Desktop | Tinjauan gambar langsung |
| --- | --- | --- | --- |
| Dewasa, tiga materi | [360 px](resume-adult-populated-360.png) | [1280 px](resume-adult-populated-1280.png) | Keduanya |
| Anak sekolah dasar, satu materi | [360 px](resume-child-populated-360.png) | [1280 px](resume-child-populated-1280.png) | 360 px, reviewer kedua |
| Dewasa, belum belajar | [360 px](resume-adult-empty-360.png) | [1280 px](resume-adult-empty-1280.png) | 360 px, reviewer kedua |
| Dewasa, tidak ada materi yang dapat dilanjutkan | [360 px](resume-adult-locked-360.png) | [1280 px](resume-adult-locked-1280.png) | Artefak tersedia; belum ditinjau satu per satu |
| Anak, akses materi dicabut | [360 px](resume-child-locked-360.png) | [1280 px](resume-child-locked-1280.png) | 360 px |

Lima capture yang ditinjau tidak menunjukkan tumpang tindih atau pemotongan isi kartu. Judul berisi markup tampil sebagai teks. Tombol utama anak mengikuti warna tema sekolah dasar. Warna biru tombol outline lama, urutan shortcut akun baru, serta panjang ringkasan historis per kursus tetap peluang perbaikan terpisah; bukan bagian perubahan ini. Tidak ada aset baru, stylesheet terpisah atau publikasi hosting.

## Perbaikan harness dan pembersihan

Percobaan pertama gagal sebelum browser karena SQL fixture melampaui batas command-line Windows; SQL sekarang dikirim sebagai byte UTF-8 lewat stdin dengan batas waktu dan drain output/error bersamaan. Percobaan kedua berhenti sebelum browser karena tes belum menerima redirect GET 307 milik CI3, dan kegagalan logout menutupi pesan awal. Tes GET kini menerima perilaku framework; POST tetap diperiksa tersendiri. Diagnostik hanya menyimpan tahap, status dan nomor baris, tanpa SQL, kredensial atau body. Kedua percobaan awal bukan bukti kelulusan. Fixture database masing-masing dibersihkan; sesi HTTP percobaan kedua tidak seluruhnya dapat dikonfirmasi logout, tetapi akun sintetisnya telah dihapus sehingga tidak berlaku pada permintaan autentikasi berikutnya.

Run final lulus termasuk logout kelima cookie jar HTTP dan cleanup tepat berdasarkan identitas fixture. Akun/konten/progres/enrollment/entitlement/tautan keluarga/audit sintetis dihapus. File sesi server dari browser tidak dipindai atau dihapus massal; profil browser run ini menjadi tanggung jawab cleanup harness. Profil/proses lama yang kepemilikannya tidak pasti tidak disentuh.

Pemeriksaan sesudah run: migration tetap 13, 33 tabel; hanya empat peran seed, satu baris migration dan satu catatan pembatasan login lama yang tersisa. Tiga puluh tabel lain kosong. Tidak ada skema QA tersisa. Folder log aplikasi hanya berisi `index.html`. Tidak ada perubahan skema, kredit, order atau konfigurasi Midtrans.

## Mengulang dan batas bukti

Jalankan dari root proyek hanya terhadap database QA lokal yang boleh diberi fixture dan dibersihkan:

```powershell
& .\scripts\dashboard-resume-check.ps1 -Php81Path 'C:\tools\php81-study\php.exe' -MysqlPath 'C:\xampp\mysql\bin\mysql.exe' -BaseUrl 'http://localhost/study/index.php/' -ChromePath 'C:\Program Files\Google\Chrome\Application\chrome.exe' -CaptureScreenshots
```

Tanpa `-ChromePath`, hanya HTTP/DB dijalankan. Capture memerlukan Chrome dan selalu memakai folder unik baru. Jangan menjalankan suite mutasi lain secara paralel karena digest mencakup seluruh tabel progres/audit. Skrip tidak menyalakan server web atau dipakai untuk produksi.

HTTP PHP 8.1, GD pada runtime web, Ubuntu/Nginx/aaPanel, HTTPS, beban/race nyata, screen reader, keyboard seluruh alur, zoom 200%, variasi usia lain dan UAT belum dibuktikan oleh suite ini. Query gagal baru dibuktikan dengan unit; tidak ada simulasi database mati pada HTTP. Tidak ada klaim keamanan/aksesibilitas menyeluruh atau aplikasi bebas bug.
