# Standar Antarmuka dan Coding Aplikasi Belajar

## Lembar soal dan fokus — 080

Pembaruan082/083: Ledger default/pilihan pertama, Per soal kedua, tanpa menimpa snapshot atau template tersimpan. Navigasi ponsel sticky berupa satu baris nomor horizontal, offset mengikuti tinggi navbar dan scroll-margin soal; tombol submit/konfirmasi tidak boleh tertutup navigasi. Halaman Nilai terpisah dari pembahasan: tampilkan nilai tersimpan dan rumus equal-weight existing, benar/salah/kosong serta durasi termasuk jeda; jangan mengarang status lulus. Pembahasan hanya setelah graded, izin pemilik/read-only dipertahankan. Hero memiliki warna teks eksplisit agar gaya paragraf kartu tidak menurunkan kontras. [Bukti082/083](LEMBAR_SOAL_082_083.md).

Gunakan engine dan view bersama untuk Mandiri, ujian dan kursus. Status dijawab harus berbeda dari status tersimpan; navigasi tidak mengubah ID/urutan opsi snapshot. Mode per soal menyembunyikan dengan JS saja agar fallback native tetap dapat digunakan; ledger menggulir dan menghormati reduced-motion. Radio/nomor dapat dipakai dengan keyboard; jangan menutupi konten dengan bar transparan.

Aturan sesi harus tersalin server saat mulai; jangan percaya mode/ambang dari POST jawaban. Autosave mempertahankan CSRF/revision/ownership dan tidak menyimpan isi jawaban anak ke Web Storage. Event fokus token unik, dihitung atomik dan dedup blur/hidden; reload tidak menghitung ulang. Jangan melabeli kehilangan fokus sebagai bukti kecurangan atau menjanjikan deteksi sleep sempurna. [Kontrak080](LEMBAR_SOAL_080.md).

## Pencocokan lampiran otomatis — 079

Nama `soalN.ext` mengikuti nomor sumber dan tampil di atas teks, bukan mengikuti urutan upload atau ID bank. Banyak gambar diurutkan numerik dengan gambar utama tanpa suffix lebih dahulu. Referensi eksplisit di soal/opsi/galeri didahulukan dan tidak digandakan. Nama yang ambigu atau nomor ganda/tidak ada harus diperbaiki sebelum commit; jangan menebak berdasarkan isi gambar. Tampilkan hasil pencocokan pada pratinjau, bukan menambah panduan panjang yang terbuka default. [Kontrak079](GAMBAR_OTOMATIS_079.md).

## Penanda gambar soal dan jawaban — 077

Gunakan `[GAMBAR: nama.png]` di soal/opsi, dengan pratinjau posisi sebenarnya dan nama persis. Jangan merender HTML atau URL luar dari dokumen. Indeks media privat tidak boleh membocorkan storage key/nama/kunci peserta. Pilihan bergambar tetap radio berlabel, dapat diklik/dinavigasi keyboard dan membungkus di ponsel. Gambar yang sudah ditempatkan tidak digandakan di galeri. Form editor menjelaskan perbedaan versi tersimpan dan edit yang belum disimpan. [Kontrak dan pengujian077](GAMBAR_POSISI_DAN_JAWABAN_077.md).

## Impor bank soal — 075

Pembaruan076: hubungan gambar memakai nama berkas persis, bukan urutan memilih upload. Daftar GAMBAR dipisahkan `|`; tampilkan nomor/nama pada admin, hanya nomor pada peserta. Validasi semua referensi, nama ganda/unused dan total ukuran/resolusi; jangan memotong berkas diam-diam. Batas aplikasi/PHP/Nginx harus selaras. URL gambar pertama dan snapshot lama tetap kompatibel; galeri baru masuk fingerprint tanpa mengubah ID existing. [Ketentuan076](GAMBAR_IMPOR_076.md).

TXT naskah dan template berlabel/tabel harus dibedakan dengan jelas. Judul bukan klasifikasi otomatis. Analisis menampilkan nomor/baris asal, status, isi lengkap yang bisa dibuka, dan perbaikan spesifik; jangan menebak kunci atau menghilangkan kata/opsi tak didukung. Pertahankan isian formulir saat gagal; dokumen/lampiran harus dipilih ulang. Batch bermasalah tidak memperoleh token commit. Pratinjau lengkap tetap membutuhkan konfirmasi server/CSRF, hak penggunaan dan transaksi dedup. Pembahasan bank boleh kosong dengan catatan jujur, bukan teks buatan untuk meloloskan validator. “Struktur lengkap” tidak sama dengan “materi/kunci sudah benar”. Rincian075 di [panduan impor](IMPOR_TXT_075.md).

## SEO halaman publik — 073

Pembaruan074: satu hierarki pusat latihan→kelas→mapel→materi existing; jangan menggandakan materi menjadi halaman keyword tipis. Eligibility sitemap harus sama dengan noindex/halaman: published, gratis, hak jelas, kategori/kelas sesuai, isi substantif. Contoh soal hanya shared/published/non-target-anak/reviewed dengan satu kunci; tidak query attempt. BreadcrumbList harus sesuai breadcrumb terlihat, judul/deskripsi di-escape, akun/login/preview tetap noindex. Navigasi publik anonim ringan wajib diuji tanpa JS, keyboard, Escape, aria-expanded serta stabilitas layout; runtime member/admin tetap terpisah. Label SearchConsole membedakan “tag terpasang” dari verifikasi Google; pengukuran lab bukan CWVlapangan. [Bukti074](SEO_074.md).

Gunakan istilah pencarian secara alami pada judul, heading, deskripsi dan konten yang benar-benar tersedia; jangan menumpuk daftar keyword tersembunyi atau menjanjikan bank/sesi kosong. Judul beranda dari Pengaturan Umum ditambah nama situs otomatis; judul/ringkasan kursus tetap sumber metadata kursus. Cuplikan HTML/entities dinormalisasi menjadi teks dan dipotong pada batas kata normal; template tetap melakukan escaping. Perubahan metadata tidak mengubah noindex halaman privat/filter, canonical, hak akses, review atau hasil belajar. Klaim ranking/volume/organik memerlukan data, bukan hanya kunjungan crawler. [Pemetaan dan bukti073](SEO_073.md).

## Latihan dan transaksi fitur — 063–070

Pembaruan071: bedakan **Platinum Selamanya** dari Platinum berjangka. Tampilkan sekali bayar, dua slot anak termasuk (bukan tambahan), penerima berdasarkan urutan tautan aktif, dan tanpa kuota harian; jangan tampilkan durasi0 sebagai “0hari akses”. Gunakan ends_atNULL + flag eksplisit, bukan tanggal arbitrer. Label tanpa kuota harus sama pada server/form/ujian; pemakaian tetap dicatat. Badge keluarga wajib wrap di ponsel. Termasuk akses tidak berarti saldo kredit gratis, slot tak terbatas, atau hilangnya timer/prasyarat. [Ketentuan071](PAKET_SELAMANYA_071.md).

Bedakan label **Latihan kursus gratis**, **Latihan Mandiri** (acak bank) dan **Simulasi Ujian** (susunan pengelola). Jangan memakai “Latihan mandiri” untuk kuis kursus lagi. Pengaturan kelas anak menjadi default filter, bukan pembatasan akses kelas lain. Pada form bank, nyatakan klasifikasi bersama, deduplikasi, hak penggunaan, pratinjau seluruh baris, dan snapshot historis. Gambar embedded Word/Excel yang tidak didukung wajib ditolak jelas, tidak dihilangkan diam-diam.

Harga/penerima/masa berlaku/cakupan ditampilkan sebelum konfirmasi penukaran. Anak tidak diberi tombol pemotongan saldo. Quote berversi, saldo production terpisah dari sandbox, transaksi+ledger+audit atomik, replay/race serta refund-review wajib diuji. Harga draft bukan janji produk sudah dijual. Level akses Arena tanpa level bawaan bukan klaim tingkat kesulitan; grouped category→level→set dahulu.

Pengecualian dari pola kartu mobile lama: **Log Akses trafik menggunakan tabel/baris juga di ponsel**, dalam wrapper scroll horizontal berlabel, agar efisien sesuai permintaan pemilik. Form/hero latihan memakai aksen indigo/teal/emas, gambar lokal existing, teks kontras dan hover ringan hanya ketika reduced-motion tidak diminta. Tidak mengklaim audit WCAG penuh dari sampel viewport.

## Monitoring operasional dan analitik opsional — rilis 042

Pembaruan072: akun dan IPtamu pada PerPengguna tidak dikaitkan sebelum login. Audit kedua tab yang membukaIP; no-store/noindex/no-referrer. Tabel memakai stickythead dan pagination di luar area gulir. Tautan wajib same-site/allowlist; perujuk luar hanya host, internal hanya pathaman, tanpa query/token/privateID. Data lama tidak ditebak. “Belum terindikasi” bukan “manusia”; “Langsung/tidak diketahui” bukan bukti organik. [Detail072](TRAFIK_072.md).

Pisahkan status/sumber monitoring otomatis dari analitik berbasis persetujuan. Monitoring menampilkan empat tab Ringkasan, Per Halaman, Per Pengguna, Log Akses; filter konsisten, pagination 20, kartu mobile, nama kelompok halaman dan metadata ter-escape. Jangan melabeli IP sebagai orang atau sesi; negara/perangkat/browser adalah perkiraan. Tamu tidak ditebak identitasnya; akun anak dan pengelola dikecualikan dari log operasional. Tampilkan batas 7 hari, cakupan allowlist dan keadaan kosong/kegagalan secara jujur. Detail IP hanya Superadmin, no-store/noindex dan pembukaan diaudit sebelum respons.

Form textarea pengaturan menormalkan CRLF/CR menjadi LF sebelum validasi karakter kontrol. Jangan menormalisasi sandi atau mengendurkan penolakan NUL/karakter kontrol pada field lain. Perubahan kebijakan tetap berversi dan diperiksa terhadap revisi terkini. Acuan: [monitoring operasional](MONITORING_OPERASIONAL_042.md).

## Lapisan pengalaman belajar — UI041 (18 Oktober 2026)

Gunakan `assets/css/experience.css` setelah stylesheet aplikasi/pengelola untuk tombol berwarna, hero dominan dan teks kontras. Indigo adalah aksi utama; teal untuk riwayat/utilitas dan biru muda untuk kembali. Jangan mengganti makna sukses/peringatan/bahaya hanya demi dekorasi. Target sentuh tombol umum minimal 44 px; fokus keyboard tetap tampak. Kontras minimal 4,5:1 untuk teks biasa dan 3:1 untuk teks besar; pengujian terarah bukan klaim audit WCAG penuh.

Login/daftar memakai `auth/learning_panel`, dua kolom desktop dan header ilustrasi ringkas di ponsel; formulir penyelesaian Google menyembunyikan panel dekoratif pada ponsel agar input mudah dijangkau. Logo Google resmi tetap terpisah dari ilustrasi buatan AI. Panduan sandi tidak mengarang kewajiban huruf besar/angka/simbol jika validator hanya membatasi panjang. Batas byte tersedia dalam detail. `password-visibility.js` menambahkan tombol type=button dengan aria-controls/pressed/label, tidak menyimpan nilai dan menyembunyikan sandi kembali saat submit/pagehide; tanpa JS formulir biasa tetap berfungsi.

Beranda tidak menumpuk tautan email/Google: tempatkan pada `/akun/pengaturan`, tampilkan status verifikasi aktual di sana, dan pertahankan reautentikasi/verifikasi ketika mengubah akun. Pengaturan bukan jalan pintas melewati izin server; anak tidak ditawari kontrol akun dewasa atau pembayaran.

Arena menampilkan kartu kategori dengan ilustrasi dahulu; set/episode/tema tampil setelah kategori dipilih. Ilustrasi tema boleh dipakai ulang untuk konten relevan; gambar dekoratif memakai alt kosong karena judul sudah tersedia, dimensi eksplisit dan lazy loading. Aset WebP lokal berversi, daftar prompt serta asal pembuatan dicatat di [manifest ilustrasi](ILUSTRASI_PEMBELAJARAN_041.md). Gambar bukan jawaban atau indikator progres.

> **Pola kerja terbaru, 16 Oktober 2026:** ikuti bagian 13 [handover server](TASK_HANDOVER_SERVER.md) untuk pengujian berbasis risiko dan penghematan waktu/token. Jangan mengulang seluruh matriks untuk perubahan dokumentasi/UI kecil; regresi luas dilakukan pada milestone/rilis. Pengujian uang, otorisasi, privasi anak, idempotensi dan kegagalan simpan tetap wajib. Tidak ada klaim kode pasti bebas bug hanya dari pemeriksaan logika.

## Tambahan layout pengelola dan analitik

Panel pengelola harus memakai shell sidebar khusus desktop dan drawer mobile, bukan navbar promosi yang sama dengan peserta. Satu definisi menu/izin, indikator aktif, breadcrumb, fokus keyboard dan tombol kembali ke tampilan pengguna harus konsisten. Tema anak tetap di ruang belajar; tampilan admin fokus pada keterbacaan tabel, filter dan aksi. Spesifikasi halaman, monitoring dan pengaturan ada pada [handover bagian 3A](TASK_HANDOVER_SERVER.md#3a-tambahan-permintaan-pemilik-panel-admin-lengkap-dan-monitoring).

Tab laporan Ringkasan/Per Halaman/Per Pengguna harus mempertahankan filter periode dan konteks kembali dari detail, dengan pagination server-side dan state kosong/gagal yang berbeda. Metrik trafik bukan nilai/progres belajar; pengunjung unik adalah perkiraan, bukan orang terverifikasi. Belum ada data tidak boleh diisi angka demo seolah nyata. Detail identitas hanya untuk akun dan peran yang berhak; jangan taruh pencarian kontak/token/URL sensitif di alamat halaman.

## Batas kata sandi sesuai algoritma

Selama menggunakan `PASSWORD_DEFAULT` yang saat ini bcrypt, server wajib menolak kata sandi lebih dari 72 byte, bukan memotongnya. Minimum tetap 12 karakter UTF-8 untuk peserta dewasa/orang tua, 1 (wajib diisi, tanpa kombinasi karakter) untuk akun anak dan 16 untuk bootstrap admin; minimum dihitung sebagai karakter, maksimum sebagai byte. Formulir menjelaskan bahwa aksen/emoji bisa memakai beberapa byte. `maxlength` browser hanya bantuan, bukan pengganti validasi server. Helper `study_valid_password` dipakai formulir, model dan CLI; nilai tidak di-trim, di-hash ulang sebelum bcrypt, atau dipantulkan dalam pesan. Model menolak input non-string, UTF-8 tidak valid dan NUL sebelum hashing. Pengolahan input HTTP tetap mengikuti CI3; ini bukan klaim dukungan karakter kontrol dalam kata sandi.

Validasi saat login juga menolak kelebihan byte agar akhiran yang diabaikan bcrypt tidak diterima. Tidak ada akun nyata yang perlu dikonversi pada saat perbaikan lokal ini. Untuk migrasi sistem yang sudah berpengguna, jangan memotong kata sandi atau menebak bentuk aslinya dari hash; perlu alur pemulihan dan rencana algoritma tersendiri. Acuan: [PHP password_hash](https://www.php.net/manual/en/function.password-hash.php).

## Pola daftar audit internal

Panel audit menggunakan tabel desktop/kartu ponsel, 20 hasil, hitungan/rentang sesuai filter, tanggal berlabel WIB, disclosure filter lanjutan dan ringkasan filter aktif yang tetap terlihat. Tanggal/ID tidak valid harus memberi HTTP 400 dengan kesalahan tertaut ke kontrol serta tanpa hasil, bukan mengabaikan filter dan membuka seluruh histori. Urutan stabil memakai waktu lalu ID. Kode peristiwa/entitas asing ditampilkan sebagai label generik; metadata mentah dan identitas tambahan tidak dimuat. Halaman diberi no-store/noindex dan menyatakan batas cakupan audit secara jelas.

**Dokumen standar produk dan pengembangan**  
**Tanggal:** 14 Oktober 2026  
**Status:** Acuan awal untuk seluruh halaman aplikasi

## Pilihan fondasi tampilan

- Gunakan Bootstrap 5 sebagai sistem grid dan komponen dasar, dengan aset lokal yang versinya dipatok. Hindari ketergantungan CDN saat aplikasi berjalan agar tetap andal di server dan pada jaringan lambat.
- Pilihan tema panel pengelola yang layak dipertimbangkan:

| Pilihan | Kesan dan kekuatan | Pertimbangan |
|---|---|---|
| AdminLTE 4 | Panel admin lengkap, familiar, Bootstrap 5.3, mode terang/gelap, tanpa jQuery. | Komponennya banyak dan dapat membuat panel terasa padat; gunakan hanya untuk admin. |
| CoreUI Free | Rapi, profesional, komponen dashboard dan formulir yang cukup lengkap. | Jaga agar hanya memakai bagian gratis; aset/fitur PRO memiliki lisensi komersial. |
| Tabler | Minimal, bersih, ringan secara visual, tabel dan navigasi bagus. | Sangat bernuansa dashboard SaaS; bukan pilihan utama untuk layar belajar anak. |
| Bootstrap + Bootswatch | Paling bebas diracik; tema seperti Minty atau Sandstone memberi warna lebih hangat, Sketchy cocok sebagai inspirasi ilustrasi. | Ini kulit visual, bukan template aplikasi lengkap; tetap perlu merancang tata letak dan komponen sendiri. |

Rekomendasi awal: Bootstrap 5 dengan identitas visual dan komponen aplikasi yang dirancang khusus agar tidak terasa seperti produk dari template pasaran. Untuk mempercepat panel pengelola, gunakan CoreUI Free sebagai kerangka yang boleh disederhanakan dan diubah; jangan menyalin tampilan demo secara utuh. Tabler menjadi pembanding jika ingin panel admin yang lebih minimal. Area belajar anak, remaja, dewasa, dan orang tua tetap memakai komponen inti yang konsisten dengan tema serta ilustrasi sesuai penggunanya. Bootswatch seperti Minty atau Sandstone dapat dipakai sebagai inspirasi awal palet, bukan sebagai identitas final. Lisensi aset dan plugin tetap diperiksa masing-masing; referensi: [CoreUI](https://coreui.io/bootstrap/docs/templates/admin-dashboard/), [Tabler](https://docs.tabler.io/ui/getting-started), dan [Bootswatch](https://bootswatch.com/).
- Simpan warna, tipografi, jarak, bentuk tombol, dan status di token CSS bersama. Tema anak/remaja/dewasa mengubah token dan ilustrasi, bukan menggandakan seluruh stylesheet.
- Tombol merek (`btn-primary`, `btn-outline-primary`, `btn-link`) memakai satu peta warna normal/hover/aktif/nonaktif dalam `app.css`. Variabel `--cui-btn-*` mengacu pada `--bs-btn-*` yang sama, karena panel pengelola memuat CoreUI setelah Bootstrap. Jangan mengubah vendor atau membuat override per halaman. Teks outline/link memakai `--primary-dark` agar terbaca di permukaan pastel; tombol isi memakai `--on-primary`. Link tetap transparan dengan warna gelap yang sama ketika hover/fokus; dekorasi mengikuti semantik/kelas halaman yang sudah ada. Varian sukses/bahaya/peringatan tetap semantik, bukan mengikuti warna merek.
- Fokus tombol merek memakai outline bersama 3 px dengan jarak 3 px, bukan halo bawaan vendor. Label setelah `btn-check` juga memperlihatkan outline ketika input tersembunyi menerima fokus keyboard. `disabled` mempertahankan semantik native dan opacity vendor; untuk tautan, `aria-disabled`, urutan fokus dan pencegahan aktivasi harus ditangani di markup/perilaku, bukan hanya warna. Jangan memasang `forced-color-adjust: none`; mode kontras sistem tetap boleh mengubah warna. Pemetaan ini tidak menyatakan dukungan dark mode, yang perlu rancangan/pengujian tersendiri.
- Catat versi serta lisensi semua pustaka dan gambar pihak ketiga. Jangan memakai komponen berbayar dari contoh template tanpa lisensi yang sesuai.
- Acuan: [Bootstrap](https://getbootstrap.com/docs/5.3/) berlisensi MIT dan dirancang mobile-first; [Tabler](https://docs.tabler.io/ui/getting-started) adalah UI kit Bootstrap 5 responsif berlisensi MIT.

## Kompatibilitas lingkungan

Aplikasi dikembangkan di XAMPP Windows, lalu dijalankan di Ubuntu dengan Nginx, PHP-FPM, dan MySQL/MariaDB melalui aaPanel. Target kompatibilitas proyek yang dipilih saat ini adalah PHP 8.1 agar dapat diuji konsisten di kedua lingkungan. Namun, PHP 8.1 sudah berakhir dukungan keamanannya pada 31 Desember 2025, jadi ini hanya target kompatibilitas sementara dan tidak disarankan untuk layanan produksi publik. Sebelum peluncuran, rencanakan uji dan perpindahan ke cabang PHP yang masih mendapat pembaruan keamanan. CI3 3.1.13 secara eksplisit menyebut penyesuaian untuk PHP 8.0 dan 8.1; versi yang lebih baru wajib dibuktikan lewat uji aplikasi.

Simpan URL aplikasi, rahasia enkripsi, dan kredensial basis data dalam environment/config lokal server, bukan di repositori. Gunakan InnoDB dan utf8mb4 untuk skema baru. Rincian konfigurasi XAMPP, Nginx, hak tulis, serta pemeriksaan deployment ada di [Panduan Lingkungan dan Deployment](PANDUAN_LINGKUNGAN_DAN_DEPLOYMENT.md).

## Tujuan

Dokumen ini menjadi acuan agar halaman aplikasi terasa satu keluarga, mudah digunakan, nyaman di ponsel, dan tetap sesuai dengan kebutuhan anak, remaja, orang tua, serta peserta dewasa. Standar ini berlaku untuk halaman publik, area belajar, area orang tua, dan panel pengelola.

Konsistensi berarti pola dasarnya sama: judul jelas, tombol utama mudah ditemukan, pencarian dan penyaringan mudah dipahami, serta pesan saat berhasil atau gagal selalu membantu. Tampilan tiap kelompok pengguna boleh berbeda suasana, tetapi cara kerjanya tetap akrab.

## Prinsip tampilan

- Tampilkan hal terpenting lebih dahulu. Setiap halaman memiliki satu tujuan utama yang mudah dikenali.
- Pakai bahasa Indonesia yang sederhana, hangat, dan sesuai usia pengguna.
- Hindari memenuhi layar dengan kartu, angka, animasi, atau tombol yang tidak membantu tugas pengguna.
- Gunakan warna, ukuran teks, jarak, sudut komponen, dan ikon dari aturan bersama, bukan nilai acak per halaman.
- Gunakan ilustrasi untuk membantu memahami isi. Jangan menjadikan ilustrasi sebagai pengganti label atau petunjuk.
- Animasi singkat boleh dipakai untuk memberi umpan balik, tetapi tidak boleh menghambat akses atau membuat halaman berat.
- Sediakan cara untuk mengurangi gerakan animasi dan suara.

## Tampilan menyesuaikan pengguna

Semua pengguna memakai komponen dan pola navigasi yang sama, dengan gaya visual yang disesuaikan melalui pilihan usia atau kelompok pengguna di profil.

Uji tata letak setidaknya pada lebar 360 px (ponsel kecil), 768 px (tablet), dan 1280 px (desktop), termasuk saat teks diperbesar hingga 200%. Angka ini adalah titik pemeriksaan, bukan alasan membuat breakpoint kaku; tata letak harus mengikuti ruang yang tersedia dan tidak menimbulkan gulir horizontal untuk isi utama.

Pengujian zoom browser harus membuktikan faktor zoom benar-benar aktif, bukan hanya mengganti lebar viewport atau memakai CSS `zoom`/transform. Profil browser penguji harus sementara dan terpisah dari profil pengguna. Catat ukuran area browser sebelum zoom, viewport CSS setelah zoom, faktor piksel, dan skala pinch; uji overflow, teks/kontrol terpotong, fokus keyboard dan formulir. Bukti halaman publik tidak boleh dianggap mewakili halaman akun, pengelola atau latihan yang belum diuji.

Untuk kegagalan fokus yang tidak selalu muncul, jangan mengulang sampai mendapat hasil hijau lalu menutup temuan. Mode opt-in `--capture-focus-evidence` pada penguji viewport merekam setiap langkah Tab dan sampel gulir: elemen berindeks, kotak posisi, fokus dokumen/`:focus-visible`, outline, status font, serta hit-test. Saat gagal, simpan viewport PNG sebelum Tab berikutnya, blur, atau reset gulir. JSON tidak mengambil nilai input, teks, HTML, cookie, storage, maupun query URL; mode ini hanya untuk empat halaman publik tanpa login. Gunakan direktori artefak baru, jangan timpa bukti sebelumnya. Kontrol negatif `--focus-evidence-negative-control` sengaja meniadakan outline pada DOM browser uji dan harus tetap berakhir gagal; bukan bug aplikasi atau baseline desain. Kegagalan menyimpan gambar harus tercatat, bukan diubah menjadi hasil lulus. Rekaman tambahan dapat memengaruhi timing, sehingga tidak membuktikan bahwa masalah intermiten sudah hilang.

| Kelompok | Arah tampilan | Cara menyampaikan materi |
|---|---|---|
| Anak usia dini dan kelas awal | Warna cerah yang tetap nyaman dilihat, gambar atau maskot, tombol besar, sedikit pilihan per layar, umpan balik visual yang positif. | Kalimat singkat, instruksi satu per satu, contoh konkret, bantuan audio bila tersedia. Hindari menu berlapis dan teks panjang. |
| Anak usia sekolah | Ilustrasi, karakter, peta belajar, misi, koleksi lencana, dan progres yang mudah dilihat. | Bahasa akrab dan jelas. Tantangan boleh menarik, tetapi kegagalan harus disertai petunjuk dan kesempatan mencoba kembali. |
| Remaja | Tata letak lebih ringkas dan modern, progres serta tujuan belajar terlihat, gamifikasi lebih ringan dan dapat dikendalikan. | Bahasa langsung, tidak kekanak-kanakan. Beri kendali lebih besar atas target dan tampilan profil. |
| Dewasa | Tampilan bersih, fokus pada tujuan, waktu, manfaat praktis, dan kemajuan. Animasi dan elemen permainan tidak mendominasi. | Bahasa lugas. Tampilkan durasi, isi kursus, hasil yang dapat dicapai, dan status penyelesaian. |
| Orang tua | Ringkasan keluarga, kemajuan anak, latihan yang perlu ditindaklanjuti, dan transaksi mudah ditemukan. | Hindari istilah teknis dan penilaian yang menghakimi. Jelaskan saran dengan contoh yang dapat dilakukan. |
| Pengelola | Tampilan kerja yang padat tetapi teratur, dengan alat pencarian, filter, tabel, dan aksi yang konsisten. | Gunakan nama kolom dan aksi yang spesifik. Beri konfirmasi untuk perubahan penting. |

Kelompok usia menjadi variasi dari satu sistem komponen, bukan salinan aplikasi yang berbeda. Konten dan ilustrasi dapat berubah sesuai usia tanpa mengubah aturan dasar seperti pencarian, formulir, status, atau navigasi.

Pengelola dapat membuka **Lihat sebagai peserta** dari setiap pelajaran pada detail kursus. Halaman pratinjau menggunakan template materi dan kartu belajar yang sama dengan halaman peserta; pilihan tema usia dini, sekolah dasar, remaja, dewasa, dan umum hanya mengubah tampilan halaman itu. Pilihan ini tidak mengubah profil pengelola, kelompok sasaran kursus, atau hak akses. Kontrol pratinjau tetap berlabel jelas dan dapat digunakan tanpa JavaScript. Periksa lebar ponsel/tablet/desktop sebelum publikasi, lalu lakukan review kecocokan bahasa dan materi secara terpisah.

Pratinjau internal selalu menampilkan penanda dan tautan kembali ke pengelolaan. Draf dibatasi ke penulis/admin sistem; setelah masuk review, pengelola konten dapat meninjaunya. Cocokkan ID kursus dan pelajaran di server, gunakan header `Cache-Control: private, no-store` serta `X-Robots-Tag: noindex, nofollow`, dan jangan mengirim formulir penyelesaian/kartu atau skrip autosave di pratinjau. Pengunjung/peserta tetap mengikuti izin publik biasa; parameter tema tidak boleh membuka draf atau memberi akses premium.

Saat pertama masuk, pengguna memilih cara memakai aplikasi: belajar sendiri, menjadi orang tua, atau menjadi anak yang ditautkan ke orang tua. Untuk akun anak, orang tua memilih kelompok belajar (usia dini, sekolah dasar, atau remaja) saat membuat akun; simpan kelompok belajar, bukan tanggal lahir lengkap. Perubahan kelompok tersedia dari daftar akun anak dan hanya boleh dilakukan orang tua tertaut; server memeriksa tautan aktif dan mencatat perubahan pada audit log. Pengguna dewasa dapat memakai area belajar tanpa membuat akun keluarga.

## Kerangka halaman

Halaman daftar dan dashboard sebaiknya memiliki urutan berikut:

1. Judul halaman yang menyebutkan isinya.
2. Penjelasan singkat atau konteks bila pengguna memerlukannya.
3. Satu tombol utama, misalnya “Tambah kursus” atau “Mulai belajar”.
4. Ringkasan singkat bila membantu pengguna mengambil keputusan.
5. Pencarian dan penyaringan dekat dengan daftar yang dipengaruhi.
6. Daftar, tabel, kartu, atau isi belajar.
7. Navigasi halaman jika jumlah hasil banyak.

Tidak semua halaman membutuhkan semua bagian. Hilangkan ringkasan atau tombol yang tidak berguna untuk tujuan halaman tersebut.

Halaman belajar peserta berfokus pada satu langkah berikutnya: lanjutkan materi, mulai latihan, atau tinjau jawaban. Panel pengelola berfokus pada pekerjaan dan status data. Halaman checkout berfokus pada isi pembelian, jumlah kredit, harga Rupiah, dan cara membayar.

Beranda peserta menempatkan “Lanjutkan belajar” sebelum tautan riwayat jika ada materi berjalan yang masih dapat diakses. Tampilkan maksimal tiga kartu memakai komponen, tombol, dan token tema usia yang sama dengan halaman belajar; judul materi menjadi bagian nama aksesibel tautan agar beberapa tombol “Lanjutkan materi” dapat dibedakan pembaca layar. Tautan GET native harus tetap bekerja tanpa JavaScript. Waktu “Progres disimpan” memakai formatter WIB bersama, bukan klaim waktu terakhir membuka, durasi belajar, atau persentase penguasaan.

Jangan menampilkan ajakan “pelajaran pertama” ketika ada materi untuk dilanjutkan. Daftar kosong mengarahkan ke katalog sesuai ada/tidaknya aktivitas; kegagalan mengambil daftar menampilkan pesan gagal dan tautan muat ulang, bukan keadaan kosong. Dashboard orang tua tetap berfokus pada keluarga tanpa tautan mengerjakan atas nama anak. GET/HEAD dashboard tidak menulis progres, memakai identitas sesi server, serta mengirim `Cache-Control: private, no-store` dan `X-Robots-Tag: noindex, nofollow`. Uji kepemilikan, akses yang berubah, daftar kosong/gagal, keyboard, serta lebar 360/768/1280 px sebelum menyatakan perubahan selesai.

Daftar isi kursus mempertahankan urutan bagian/pelajaran dan menampilkan status akun sendiri setelah login: **Belum dimulai**, **Sedang dipelajari**, atau **Selesai**. Status memakai teks, bukan warna atau ikon saja; status dan judul panjang dapat membungkus, termasuk kata tanpa spasi. Tombol materi yang diizinkan menjadi **Mulai belajar**, **Lanjutkan**, atau **Baca ulang**, dengan judul lengkap pada nama aksesibelnya. Lanjutkan menggunakan posisi baca yang sudah tersimpan; baca ulang tidak menghapus tanda selesai. Susun aksi di bawah judul pada layar sempit, lalu sejajar bila ruang cukup.

Status tersimpan bukan hak akses: materi terkunci tetap tanpa tautan baca meskipun histori akun tersebut ditampilkan. Orang tua di katalog melihat progres dirinya, bukan anak dari query URL; laporan keluarga tetap melalui area keluarga. Tamu tidak mendapat label status pribadi. Query gagal menampilkan pesan sopan dan tautan GET **Muat ulang progres**, bukan melabeli semua materi belum dimulai; tautan yang diizinkan tetap berlabel netral. Gunakan satu query terikat peserta/kursus untuk peta status, bukan query per baris. GET/HEAD terpersonalisasi bersifat private/no-store/noindex dan tidak menulis progres atau audit konten. Ini daftar status pelajaran terbit, bukan persentase penguasaan atau kelulusan kursus.

Untuk pelajaran teks, tawarkan penyimpanan posisi dan pemulihan yang jelas. Posisi tersimpan tidak boleh bergantung pada ukuran layar; nilai server menyatakan persentase kemajuan vertikal dokumen dalam rentang 0–10000. Simpan otomatis setelah jeda membaca (debounce), hanya jika perubahan bermakna, secara berurutan, lewat POST ber-CSRF; kirimkan kembali token CSRF terbaru dari respons sebelum autosave berikutnya. Server tetap memeriksa login, status materi, enrollment/entitlement, dan rentang posisi. Tampilkan status “menyimpan/tersimpan/gagal” yang dapat dibaca pembaca layar, pertahankan tombol simpan manual sebagai cadangan, dan pastikan formulir manual tetap dapat dipakai tanpa JavaScript. Status selesai bersifat terminal dan tidak turun hanya karena posisi baca dikirim lagi. Uji perubahan ukuran layar, permintaan berurutan, token berganti, gagal/akses dicabut, pengiriman manual, serta status selesai.

Sebelum membuat halaman baru, tulis ringkasan halaman yang menjawab: siapa penggunanya, apa tujuan utamanya, data apa yang ditampilkan, aksi apa yang boleh dilakukan, izin apa yang diperlukan, serta keadaan kosong, memuat, gagal, dan berhasil yang harus tersedia. Ringkasan ini menjaga halaman tidak dibuat hanya sebagai kumpulan komponen tanpa alur.

Dashboard orang tua hanya menampilkan progres kursus milik anak dari tautan keluarga aktif. Batasi daftar ringkas per anak agar kartu tidak terlalu panjang, tampilkan jumlah pelajaran selesai/berjalan yang memang tercatat, dan jangan menampilkan kursus lain atau membuat klaim persentase bila denominator pelajaran yang dapat diakses belum dihitung.

Halaman latihan mandiri menjelaskan jumlah soal, aturan nilai, simpan sementara, dan konsekuensi jawaban kosong sebelum mulai. Setiap soal memakai `fieldset`/`legend` dengan pilihan radio berlabel penuh dan target sentuh minimal 52 px; warna pilihan/fokus mengikuti token tema. Sediakan pilihan “Belum dijawab” agar peserta dapat mengosongkan pilihan secara sengaja. Tombol “Simpan sementara” terpisah dari finalisasi. Setelah selesai, formulir hilang dan diganti hasil serta pembahasan dengan bahasa yang mengajar. Jangan mengandalkan CSS untuk menyembunyikan kunci: view aktif tidak boleh menerima kunci/pembahasan dari model.

Riwayat latihan bersifat pribadi dan memakai kartu responsif, tanggal WIB, serta pagination server-side sepuluh percobaan. Jangan menimpa percobaan lama saat latihan diulang. Untuk tab usang, jelaskan bahwa jawaban terbaru tidak ditimpa, muat data tersimpan yang terbaru, dan minta peserta memeriksa sebelum mengirim kembali. Tidak ada klaim autosave/offline pada latihan sebelum fitur tersebut tersedia; posisi baca materi tetap memiliki perilaku autosave tersendiri.

Pengerjaan latihan yang tepat berstatus `in_progress` menggunakan pengaman perubahan bersama `data-unsaved-form`. Tampilkan petunjuk simpan manual di dalam form; status live region disembunyikan sampai skrip siap. Hasil final dan laporan orang tua tidak memuat form, radio editable atau skrip pengaman. Berbeda dari editor materi yang mempertahankan isian gagal, respons 400/409 latihan menampilkan kembali **jawaban tersimpan dari server**: nilai itu menjadi acuan bersih, bukan POST yang ditolak. Jangan mengaktifkan `data-unsaved-initial=true` hanya karena respons error. Status selain berjalan/final tidak boleh membuka form pengerjaan. Peringatan keluar bukan autosave atau jaminan pemulihan pada penutupan paksa.

Untuk mulai/simpan/final latihan, penolakan domain 400/409 di atas berlaku setelah rollback dikonfirmasi. Kegagalan layanan memakai HTTP 503 dengan dua pesan berbeda: **Permintaan belum berhasil disimpan** bila operasi belum dimulai atau rollback terkonfirmasi, dan **Hasil penyimpanan belum dapat dipastikan** bila COMMIT/ROLLBACK tidak terkonfirmasi. Pada jalur 503 jangan membaca ulang DB dalam POST tersebut, menampilkan form/nilai seolah terbaru, atau membawa flash sukses lama. Sediakan tautan native GET **Periksa keadaan terbaru** serta **Riwayat latihan saya**, tanpa retry POST, autosubmit atau refresh otomatis. Pertahankan private/no-store/noindex, token tema usia, status berbentuk teks dan fokus keyboard. Sukses baru diarahkan dengan 303 setelah COMMIT terkonfirmasi; pesan memakai status hasil operasi, bukan query tambahan sesudah COMMIT. Pemulihan ini belum mencakup kegagalan preflight akun/controller sebelum operasi model dimulai.

Pemulihan transaksi permainan tersimpan mengikuti pola latihan: mulai, pilihan pasangan dan berhenti mengirim 503 terbedakan bila transaksi gagal, tanpa membaca ulang DB, membuat token/form permainan, memuat skrip simpan atau menampilkan feedback/progres lama. Intro memakai GET ke materi asal yang diambil server; pilihan/berhenti memakai GET ke run milik peserta; aksi kedua menuju riwayat permainan. Wrapper latihan/permainan memakai satu komponen `components/transaction_recovery.php` dengan teks konteks tetap, bukan menerima HTML bebas. Form keluar pada layout sesi masuk tetap diperbolehkan. JavaScript pilihan pasangan yang menerima 503 tetap mengunci revisi lama dan menawarkan GET manual tanpa retry/angka optimistis; ia tidak menganggap body respons sebagai progres terbaru. Penolakan domain 400/403/404/409 tetap mengikuti penanganan lama setelah rollback terkonfirmasi.

## Kepastian simpan posisi baca

Simpan posisi/tandai selesai memakai hasil transaksi terstruktur. Setelah COMMIT terkonfirmasi, AJAX mengirim `ok: true` dan token CSRF terbaru, sedangkan POST HTML memakai 303. Layanan gagal memberi 503 terbedakan antara belum tersimpan dan belum pasti, tanpa reread DB, form progres atau flash lama. Komponen pemulihan bersama memakai konteks **Periksa kembali progres baca**, GET materi asal serta beranda. Data pelajaran/akses dibaca terkunci di transaksi; status selesai dan waktu penyelesaian awal tidak diturunkan/ditimpa oleh upsert berikutnya.

Autosave hanya meneruskan antrean sesudah respons sukses valid. Kegagalan HTTP/transport/JSON atau watchdog 20 detik menghentikan autosave dan antreannya, mengunci dua form posisi/selesai, serta membuka tautan GET **Periksa keadaan terbaru**; respons terlambat tidak mengaktifkan pengiriman lagi. Timeout/abort bukan bukti server membatalkan transaksi. Saat autosave berlangsung, submit manual posisi/selesai ditolak sementara agar tidak balapan dengan token CSRF. Setelah sukses, perbarui token seluruh form halaman, termasuk selesai, kartu dan keluar. Submit native yang diterima menghentikan autosave dari DOM yang akan ditinggalkan. `pageshow.persisted` meminta GET ulang; bukti event unit tidak membuktikan BFCache nyata lintas browser. Tanpa JavaScript, form native tetap mengikuti validasi server; tidak ada klaim sinkronisasi offline.

## Kepastian penyimpanan kartu belajar

Status kartu `learning`/`known` disimpan melalui transaksi terluar yang memeriksa kartu/pelajaran/kursus terbit dan akses current read di dalam transaksi. Preflight controller bukan pengganti pemeriksaan mutasi. ID harus bilangan bulat positif kanonis; nilai array, pecahan atau ID berakhiran teks tidak boleh dipaksa menjadi ID valid. Upsert status sama mempertahankan `review_count` serta `last_reviewed_at`; perubahan status menaikkan hitungan sekali, tanpa XP/kredit.

Setelah sukses terkonfirmasi, arahkan POST dengan 303 ke bagian kartu. Kegagalan layanan memakai komponen pemulihan bersama bertajuk **Periksa kembali kartu belajar**, HTTP 503 private/no-store/noindex, tanpa reread database, flash lama, form kartu atau retry otomatis. GET materi memakai slug dari server dan fragmen tetap `#kartu-belajar`; tautan kedua menuju beranda. Perbedaan belum tersimpan/belum pasti harus jelas dan tidak mengklaim kartu sudah dikuasai. Penolakan input 400 dan akses/kartu 403 tetap berbeda dari layanan 503. Ini bukan implementasi antrean kartu offline atau bukti koordinasi semua form dengan autosave yang sedang berjalan.

## Format angka, waktu, dan satuan

- Simpan timestamp di database dalam UTC; saat ditampilkan, konversikan ke `Asia/Jakarta` dan gunakan format ringkas `15 Okt 2026, 14.00 WIB`. Jangan menempelkan label UTC pada waktu yang telah dikonversi.
- Tampilkan angka bulat dengan pemisah ribuan titik, misalnya `1.234.567`. Hindari konversi melalui floating point untuk saldo/angka bulat besar.
- Tampilkan Rupiah sebagai `Rp 12.500` tanpa pecahan sen; sumber nilai harus berupa jumlah Rupiah bulat yang ditentukan server.
- Tampilkan satuan kredit dan poin secara eksplisit, misalnya `2.000 kredit` dan `75 poin`. Kredit pembelian harus tetap dibedakan dari poin/XP permainan; jangan menganggap keduanya saling dapat ditukar.
- Gunakan `study_format_datetime()`, `study_format_number()`, `study_format_rupiah()`, `study_format_credits()`, dan `study_format_points()` dari helper `study_ui` untuk menjaga format konsisten. Nilai invalid/kosong harus memiliki fallback aman dan bukan dirender mentah.

Helper-format angka dan waktu telah memiliki unit test untuk locale, konversi UTC-ke-WIB, overflow presisi, satuan terpisah, input invalid, serta saldo kredit negatif. Ini belum berarti seluruh notifikasi, konfirmasi, dan halaman yang direncanakan sudah distandardisasi.

## Sistem visual bersama

Sebelum membuat halaman baru, tentukan nilai desain terpusat untuk:

- warna utama, warna pendukung, latar, teks, garis, status berhasil, peringatan, dan kesalahan;
- jenis dan ukuran teks untuk judul, subjudul, isi, label, dan keterangan;
- jarak antar bagian, lebar konten, tinggi kontrol, dan radius komponen;
- gaya tombol utama, tombol sekunder, tautan, ikon, badge, kartu, tabel, tab, dan formulir;
- ilustrasi, foto kursus, avatar, dan placeholder saat gambar belum tersedia.

Gunakan variabel desain bersama. Jangan menentukan warna atau jarak baru hanya untuk memperbaiki satu halaman tanpa menilai dampaknya terhadap halaman lain.

Implementasi awal token dan pola interaksi bersama berada di `assets/css/app.css`: warna/fokus, jarak section, radius kartu/kontrol, lebar baca, indikator fokus keyboard, tinggi minimum kontrol, tabel responsif, dan dukungan reduced motion. Ini adalah fondasi, belum berarti semua halaman telah memakai komponen yang seragam atau telah lolos review visual dan uji aksesibilitas. Perubahan pada fondasi ini harus diperiksa pada halaman publik, belajar, keluarga, dan admin sebelum dianggap selesai.

Field dengan kesalahan validasi server harus memakai `study_field_error_attributes()` untuk memasang `aria-invalid="true"` dan `aria-describedby`, serta `study_render_field_error()` untuk membuat elemen pesan dengan ID yang cocok. Pola ini sudah diterapkan pada formulir akun anak, kategori, kursus, dan pelajaran; tes HTTP harus memeriksa pasangan atribut dan ID, bukan hanya teks error yang terlihat. Pertahankan nilai aman yang sudah dimasukkan, jangan pernah memantulkan kata sandi, dan jangan memberi status invalid bila field tidak memiliki error.

Satu pustaka komponen bersama menjadi sumber utama tombol, formulir, kartu, badge, tabel, tab, dialog, navigasi, pesan, dan keadaan pemuatan. Setiap komponen yang memiliki tampilan khusus anak, dewasa, atau pengelola harus memakai aturan tema bersama, bukan salinan CSS per halaman.

## Form usang dan hasil simpan yang belum pasti

Editor yang dapat dibuka di beberapa tab harus mengirim versi data yang dilihat pengguna, memeriksanya kembali di dalam transaksi, dan menolak versi usang tanpa menimpa perubahan terbaru. Jangan mengganti versi lama dengan versi baru sambil mempertahankan payload lama lalu langsung menyimpannya. Tampilkan penjelasan konflik, pertahankan isian aman agar dapat disalin, dan sediakan tautan GET untuk memuat ulang. Tombol simpan pada form konflik dinonaktifkan; server tetap wajib menolak jika tombol dibuka melalui alat pengembang. Ini sudah diterapkan pada editor materi, susunan kursus, dan gambar pendamping, bukan pernyataan bahwa semua formulir sudah terlindungi.

Simpan konteks daftar yang aman, seperti halaman dan bagian terpilih, pada form, pagination, tautan pemulihan dan redirect. Validasi ulang ID terhadap pemilik/kursus dan batasi nomor halaman server-side. Jangan menerima URL tujuan bebas dari parameter pengguna. Jika pilihan pada kiriman sudah tidak tersedia, jelaskan dan minta pemilihan ulang; jangan menyimpan diam-diam ke pilihan pertama.

Bedakan kegagalan yang sudah dibatalkan dari hasil penyimpanan yang belum dapat dipastikan. Kegagalan COMMIT/koneksi tidak boleh otomatis dianggap tidak tersimpan atau dicoba ulang. Halaman harus meminta pengguna memeriksa data terbaru lebih dahulu. Catatan QA harus membedakan fault injection sebelum COMMIT, pengiriman ulang berurutan, dan konkurensi/putus jaringan nyata.

## Metode permintaan halaman

Halaman baca menerima GET/HEAD; halaman dengan formulir juga menerima POST. Controller yang belum mempunyai pembatas metode harus memakai helper `require_http_methods()` sesudah pemeriksaan login/peran yang diperlukan, sebelum membaca resource atau menjalankan aksi. Metode lain ditolak HTTP 405 dengan header `Allow`. Endpoint aksi lama yang sudah menolak selain POST dengan 404 tetap dipertahankan agar perubahan ini tidak memperluas perubahan perilaku; normalisasinya dapat ditinjau terpisah. Callback validasi internal tetap tidak boleh menjadi endpoint publik.

CSRF tetap wajib dan dapat menolak POST sebelum controller berjalan; whitelist metode bukan penggantinya. Header `X-HTTP-Method-Override` dan field `_method` tidak memberi izin baru. Uji alias route dan URL controller langsung, metode yang ditolak tanpa perubahan data, GET/HEAD yang tetap berfungsi, serta form POST dengan token sah/tidak sah. Penolakan metode saja tidak membuktikan seluruh otorisasi atau keamanan endpoint.

Pada jalur `show_error()` CI3, kirim `Allow` langsung dengan `header()` sebelum error ditampilkan: antrean `CI_Output::set_header()` tidak dijamin terkirim karena proses dihentikan. Nilai header harus berasal dari daftar metode tetap milik aplikasi, bukan input pengguna. Buktikan header pada respons HTTP nyata, bukan hanya unit test controller.

## Navigasi dan tab

Menu utama menentukan posisi aktif dari view yang dipilih controller, bukan pencocokan URL/query atau JavaScript. Tujuan yang sedang dibuka memakai `aria-current="page"`; halaman turunannya memakai `aria-current="location"`. Tampilkan garis bawah dan bobot teks selain warna, tetap pertahankan outline fokus. Jangan memberi penanda pada tautan anchor beranda ketika posisi gulir belum dilacak. Pratinjau konten pengelola berada di bagian pengelola, bukan katalog publik. Ini hanya penunjuk lokasi; otorisasi tetap di server dan menu yang tidak berhak tetap tidak dirender.

HTML navigasi harus langsung menampilkan tautan yang diizinkan tanpa JavaScript, dengan tombol buka/tutup tersembunyi. Skrip peningkatan baru memasang collapse dan menampilkan tombol setelah API Bootstrap tersedia. Jika Bootstrap atau skrip aplikasi gagal dimuat, pengguna tetap dapat menuju kursus, masuk/daftar atau area akunnya; jangan menampilkan tombol yang tidak berfungsi. Uji kondisi normal, skrip diblokir, Bootstrap tidak tersedia, inisialisasi berulang, menu ponsel dan desktop. Fallback tidak boleh menambah tautan yang sebelumnya dilarang oleh peran.

- Nama menu harus singkat, mudah dimengerti, dan konsisten antara menu, judul halaman, serta tombol.
- Menu aktif harus terlihat jelas. Di ponsel, menu dapat memakai navigasi ringkas yang mudah dijangkau ibu jari.
- Tab digunakan untuk membagi beberapa bagian yang setara dalam satu konteks, misalnya “Materi”, “Latihan”, dan “Ulasan”. Tab bukan pengganti navigasi utama.
- Tab aktif harus terlihat dan dapat dibedakan dengan lebih dari sekadar warna.
- Jika tab berisi daftar yang bisa dibagikan atau dibuka langsung, simpan tab aktif di URL, misalnya `?tab=latihan`, dan pertahankan pencarian serta filter yang terkait.
- Perpindahan tab tidak boleh menghapus perubahan formulir yang belum disimpan tanpa peringatan.
- Gunakan tombol tab yang dapat diakses dengan keyboard, memiliki status aktif yang jelas, dan memiliki hubungan yang benar ke isi tab.

## Pencarian

Daftar sesi keluarga menyediakan pencarian judul (maksimal 100 karakter), filter anak tertaut dan status yang terlihat pengguna, termasuk menunggu jadwal/kedaluwarsa. Semua filter digabung dengan AND, dijalankan di server bersama scope pembuat/tautan aktif, dan dipertahankan dalam pagination sepuluh sesi. `%`, `_`, dan karakter escape dicari sebagai teks literal, bukan wildcard. Hitungan, daftar dan label memakai waktu UTC yang sama untuk satu respons; membaca daftar tidak mengubah status database. Form GET mengembalikan halaman pertama dan menyediakan reset; hasil kosong terfilter dibedakan dari belum ada sesi. Parameter array/status tak dikenal diabaikan, sedangkan ID anak valid yang bukan milik pengguna menghasilkan nol sesi tanpa mengungkap identitasnya. Panel filter dapat dilipat di ponsel dan terbuka ketika ada filter aktif. Uji kombinasi filter, batas halaman, Unicode/markup, jadwal, status final, serta revokasi tautan dan lintas pembuat sebelum dianggap selesai.

- Letakkan kotak pencarian di dekat daftar yang dicari dan beri label atau petunjuk yang jelas, misalnya “Cari kursus atau topik”.
- Cari pada sisi server agar hasil tetap benar untuk data banyak dan tidak membocorkan data yang tidak boleh dilihat.
- Pencarian daftar admin boleh berjalan setelah jeda singkat saat pengguna mengetik; sediakan juga cara kirim langsung dengan tombol atau tombol Enter.
- Saat kata pencarian berubah, mulai kembali dari halaman pertama.
- Pertahankan kata pencarian saat pengguna membuka detail lalu kembali ke daftar.
- Tampilkan aksi untuk menghapus kata pencarian.
- Jika hasil kosong, jelaskan pencarian apa yang tidak menghasilkan data dan tawarkan cara memperbaikinya.
- Jangan menampilkan “tidak ada data” ketika sebenarnya permintaan gagal. Bedakan hasil kosong, sedang memuat, dan kesalahan.

## Filter dan pengurutan

- Tampilkan filter yang paling sering digunakan terlebih dahulu. Filter lanjutan dapat diringkas dalam panel “Filter lainnya”.
- Gunakan nama yang dikenal pengguna, seperti “Kategori”, “Usia”, “Tingkat”, “Status”, dan “Tanggal”.
- Tampilkan filter yang sedang aktif agar pengguna tahu mengapa hasil tertentu muncul.
- Sediakan “Hapus semua filter” tanpa menghapus pilihan lain yang bukan filter, seperti tab aktif.
- Untuk halaman daftar, simpan kata pencarian, filter, urutan, dan halaman pada URL menggunakan parameter yang dapat dibagikan.
- Hanya simpan parameter yang aman untuk ditampilkan di URL. Jangan masukkan kata sandi, token, jawaban latihan, data pribadi, atau informasi sensitif anak ke alamat halaman.
- Setiap perubahan filter mengembalikan pengguna ke halaman pertama.
- Filter harus dijalankan di server. Periksa bahwa pengguna hanya dapat menyaring data yang memang boleh ia akses.
- Pengurutan harus menunjukkan kolom dan arah urutan. Server hanya menerima nama kolom yang sudah diizinkan dan memakai urutan tambahan yang stabil agar data tidak berpindah secara acak antarhalaman.
- Pada layar kecil, filter ditampilkan sebagai tombol yang membuka panel sederhana; filter aktif tetap terlihat.

## Tabel dan daftar

Direktori pengguna internal memakai tabel desktop dan kartu ponsel dengan data yang sama, 20 hasil per halaman serta rentang/jumlah tersaring. Pencarian nama pengguna/ID dikirim POST ber-CSRF dan diarahkan 303 ke URL konteks sesi acak, sehingga teks pencarian tidak masuk tautan; konteks berbeda tidak saling menimpa antar-tab. Filter jenis/status/peran serta urutan memakai allowlist. Ringkas filter lanjutan dalam disclosure native dan tampilkan ringkasan filter aktif di luarnya. Jangan menampilkan kontak, nama lengkap, hash password, atau data belajar dalam daftar; jangan menambahkan tombol mutasi/ekspor sebelum kebijakan dan izin operasionalnya dibuat. Konteks hilang memberi pesan cari ulang, bukan kembali diam-diam ke seluruh akun.

Tabel digunakan untuk data yang memang perlu dibandingkan per baris, terutama pada panel pengelola. Untuk katalog kursus dan materi, kartu biasanya lebih mudah dipindai.

- Gunakan judul kolom yang jelas dan konsisten dengan formulir serta laporan.
- Angka, tanggal, status, dan teks panjang memakai format yang sama di semua halaman.
- Aksi utama per baris ditampilkan jelas. Aksi tambahan dapat dimasukkan ke menu, tetapi jangan sembunyikan aksi yang paling sering dipakai.
- Konfirmasi diperlukan untuk menghapus, menonaktifkan, membatalkan, atau tindakan yang sulit dipulihkan.
- Tampilkan status sebagai teks dan penanda visual, bukan warna saja.
- Tabel yang lebar boleh digeser secara horizontal di ponsel, tetapi kolom penting dan aksi utama harus tetap mudah ditemukan.
- Untuk daftar sederhana, ubah baris menjadi kartu pada layar ponsel dengan label nilai yang tetap jelas.
- Sediakan pesan kosong yang menyarankan langkah berikutnya, misalnya menambah kursus atau menghapus filter.
- Bedakan daftar yang memang belum berisi data dari pencarian/filter tanpa kecocokan. Untuk hasil filter kosong, sebutkan bahwa tidak ada hasil yang cocok dan sediakan tindakan untuk menghapus filter; jangan tampilkan ajakan membuat data seolah daftar dasarnya kosong.
- Jika pemuatan berlangsung, tampilkan indikator atau kerangka konten. Jika gagal, beri pesan dan tombol coba lagi.
- Aksi massal hanya muncul setelah baris dipilih, menjelaskan jumlah data yang terpilih, dan meminta konfirmasi sebelum perubahan penting.

## Pagination

Normalisasikan nomor halaman satu kali di controller; nilai yang sama harus menentukan offset query, indikator halaman dan tautan sebelumnya/berikutnya. Jangan menggabungkan offset dari helper tervalidasi dengan penunjuk pagination yang membaca GET mentah lagi. Daftar kursus pengelola sekarang memakai komponen `practice/pagination` seperti katalog/riwayat, tetap 20 hasil, dengan allowlist `q`/`status`/`halaman`. Pertahankan pencarian bernilai string `0`; penyaringan konteks tidak boleh membuangnya karena dianggap falsy. Uji dengan lebih dari satu halaman, timestamp sama, spasi/nol di depan, parameter array dan parameter asing. [Bukti pengelola](visual-baseline/2026-10-15-admin-course-list-pagination-32ed3752ecb84ff9/README.md).

- Gunakan pagination di sisi server untuk daftar yang bisa bertambah besar.
- Jumlah awal yang disarankan adalah 20 baris per halaman. Jika berguna, pengguna dapat memilih 20, 50, atau 100.
- Server memvalidasi nomor halaman dan jumlah baris serta menetapkan batas maksimum agar permintaan tidak mengambil data berlebihan.
- Tampilkan jumlah hasil dan rentang yang sedang dilihat, misalnya “Menampilkan 21–40 dari 138 kursus”.
- Tombol sebelumnya, berikutnya, dan nomor halaman harus mudah dipakai dengan sentuhan.
- Pertahankan pencarian, filter, tab, dan urutan saat berpindah halaman.
- Jika filter baru mengurangi jumlah halaman, arahkan ke halaman terakhir yang tersedia atau halaman pertama; jangan tampilkan daftar kosong yang membingungkan.
- URL halaman harus dapat dibagikan dan tombol kembali browser harus bekerja sesuai harapan.

## Kartu kursus dan katalog

Implementasi katalog saat ini memakai 12 kartu per halaman, hitungan hasil tersaring, tautan Atur ulang, serta pesan berbeda untuk katalog awal kosong dan filter tanpa hasil. Query wajib menyediakan semua field yang dibaca kartu, termasuk ringkasan ter-escape. Gunakan urutan stabil waktu terbit lalu ID menurun; daftar pengelola memakai waktu pembaruan lalu ID menurun. Pagination katalog memakai komponen bersama `practice/pagination`: satu nilai halaman yang sudah dinormalisasi dipakai untuk offset query dan indikator halaman, dengan query tautan allowlist `q`/`kategori`/`halaman`, bukan seluruh GET mentah. Pengujian harus memakai katalog berisi data, timestamp yang sama dan lebih dari satu halaman; katalog kosong tidak membuktikan kontrak kartu. [Bukti lokal dan batas](visual-baseline/2026-10-15-catalog-populated/README.md).

- Kartu menampilkan judul, kategori, tingkat pengguna, durasi atau jumlah materi, harga atau kredit, status akses, dan satu aksi utama.
- Gambar sampul menggunakan rasio dan aturan potong yang sama; gambar dekoratif memiliki deskripsi yang sesuai bila diperlukan.
- Jangan menaruh semua deskripsi panjang di kartu. Tampilkan ringkasan dan sediakan halaman detail.
- Bedakan dengan jelas paket gratis, paket yang sudah dimiliki, dan paket yang perlu dibuka.
- Halaman detail menyebutkan isi, tingkat, kebutuhan awal, waktu yang disarankan, metode belajar, harga kredit, serta masa akses.
- Pengguna harus melihat pembelian yang akan dilakukan dan sisa kredit sebelum mengonfirmasi.

## Formulir

- Setiap kolom mempunyai label yang terlihat; placeholder hanya menjadi contoh, bukan pengganti label.
- Kelompokkan pertanyaan yang berhubungan dan beri judul bagian bila formulir panjang.
- Jelaskan format yang diharapkan sebelum pengguna mengirim, bukan hanya setelah muncul kesalahan.
- Validasi di browser membantu pengguna memperbaiki kesalahan cepat, dan validasi server tetap wajib.
- Kesalahan ditampilkan dekat dengan kolom terkait dan merangkum apa yang harus diperbaiki.
- Jangan menghapus isian pengguna saat server mengembalikan kesalahan.
- Tampilkan indikator tersimpan atau belum tersimpan untuk formulir yang panjang.
- Pakai dialog untuk tindakan kecil. Pakai halaman penuh untuk pembuatan kursus, penyusunan soal panjang, atau pengaturan yang memerlukan banyak konteks.
- Tombol harus memakai kata kerja jelas, seperti “Simpan soal”, “Kirim latihan”, atau “Buka paket”.
- Untuk unggahan gambar, video, dan dokumen, tampilkan jenis serta ukuran berkas yang diterima, hasil gagal, dan cara mencoba lagi. Jika tersedia indikator kemajuan, gunakan data pengiriman yang nyata; jangan membuat persentase atau animasi yang menyatakan berkas telah tersimpan. Form unggah native tanpa indikator khusus aplikasi tetap harus berfungsi tanpa JavaScript.
- Setelah penyimpanan berhasil, tampilkan perubahan yang terjadi dan tindakan selanjutnya. Hindari pesan umum seperti “Berhasil” jika pengguna perlu tahu apa yang berhasil.

Untuk formulir tambah/edit kursus dan pelajaran, gunakan peningkatan JavaScript opt-in yang membandingkan kontrol pengguna dengan nilai bawaan markup server di memori halaman, bukan nilai yang mungkin dipulihkan browser. Status memakai live region sopan dan tidak mengumumkan ulang teks yang sama pada setiap ketukan. Jangan menyimpan materi di local/session storage atau menganggap submit sebagai keberhasilan. Respons POST gagal termasuk konflik versi tetap berstatus belum tersimpan; field tersembunyi CSRF/revisi tidak dihitung sebagai perubahan. Nilai `set_value` di-escape tepat sekali saat dirender; input array yang tidak sesuai kontrak scalar ditolak sebelum render.

Pasang listener `beforeunload` hanya saat formulir kotor, lepaskan saat semua nilai kembali seperti awal, dan evaluasi ulang ketika halaman kembali melalui history. Submit yang dibatalkan tidak boleh menonaktifkan peringatan; submit native yang diteruskan tidak memicu peringatan formulirnya sendiri. Form lain yang kotor tetap dilindungi. Status bukan janji pemulihan: dialog browser memerlukan interaksi dan tidak andal untuk penutupan paksa di ponsel, serta teksnya ditentukan browser ([MDN](https://developer.mozilla.org/en-US/docs/Web/API/Window/beforeunload_event)). Tetap tampilkan petunjuk bahwa belum ada simpan otomatis dan pertahankan submit native ketika skrip tidak dimuat. Penerapan pada form lain harus opt-in setelah diuji, bukan otomatis untuk login/pembayaran; latihan aktif mengikuti aturan khusus jawaban server di bagian pengalaman belajar.

## Gambar pendamping pelajaran

Pelajaran dapat mempunyai satu gambar PNG/JPEG yang melengkapi teks, bukan menggantikannya. Editor **Gambar pendamping** memakai tema pengelola: gambar/keterangan saat ini dan formulir berdampingan di desktop, bertumpuk di ponsel. Pisahkan tindakan unggah/ganti berkas, ubah keterangan tanpa unggah, serta lepas gambar. Pelepasan berada dalam disclosure dengan konfirmasi checkbox yang wajib; jelaskan bahwa teks dan progres tidak dihapus. Halaman tanpa gambar mempunyai keadaan kosong yang mengajak menambah gambar tanpa menyiratkan gambar wajib bagi semua kursus.

Form unggah memakai multipart native dan label file yang jelas. Tampilkan batas 3 MiB (3.145.728 byte), PNG/JPEG, 4.096 piksel per sisi, dan 4.000.000 piksel total; jelaskan nama berkas sederhana dan bahwa browser meminta pemilihan ulang file sesudah halaman dimuat kembali. Field file tidak pernah diberi nilai path/nama penyimpanan oleh server. Batas output normalisasi 6 MiB adalah urusan penyimpanan, bukan batas unggahan yang ditawarkan kepada pengguna. Tidak ada progress bar palsu, autosave, retry POST otomatis, atau klaim berhasil sebelum respons server.

Deskripsi alternatif wajib 3–300 karakter; keterangan tambahan opsional maksimal 1.000 karakter; catatan sumber/izin wajib 5–2.000 karakter; status hak hanya pilihan yang diizinkan. Jelaskan bahwa deskripsi harus menerangkan informasi penting, bukan nama file. Sumber dan lisensi terlihat kepada peserta yang mengakses materi, sehingga form mengingatkan agar tidak memasukkan data pribadi atau rahasia. Semua teks di-escape; tidak ada HTML kaya atau sumber gambar dari URL bebas. Perubahan metadata dan unggah mempunyai field HTML ber-ID berbeda; hanya form aktif menampilkan error melalui helper bersama agar `aria-describedby` tidak menunjuk ID yang ganda.

Jika GD/fileinfo atau penyimpanan belum siap, tampilkan alasan dan nonaktifkan hanya formulir unggah; metadata/pelepasan gambar yang sudah ada tetap dapat digunakan sepanjang izin dan layanan terkait masih berlaku. Hilangnya hak saat menyimpan menghasilkan penolakan umum, bukan menampilkan ulang draf privat. Konflik atau hasil simpan yang belum pasti mengunci formulir, mempertahankan isian aman untuk disalin, dan menyediakan tautan GET tetap untuk memuat ulang. Pada keadaan terkunci, jangan memuat `<img>` baru bersama metadata sebelum percobaan simpan: tampilkan penjelasan dan teks snapshot saja sampai GET baru agar gambar terbaru tidak memakai deskripsi lama.

Gambar peserta berada di area baca yang sama dengan teks, menggunakan `width`/`height` untuk ruang awal, `max-width: 100%` dan `height: auto` agar proporsinya tidak terpotong. Tampilkan `alt` yang ter-escape **dan** deskripsi teks yang tetap terlihat sebagai fallback. Keterangan tambahan tetap berupa teks; sumber/hak dapat diringkas dalam disclosure native dan dibuka pada pratinjau pengelola. Tinjauan kursus harus menampilkan gambar, deskripsi alternatif, keterangan, sumber dan hak, bukan sekadar thumbnail. Gambar kecil tidak wajib diperbesar; kesesuaian isi/usia tetap memerlukan review manusia.

URL gambar selalu melalui endpoint akses materi atau pratinjau pengelola yang sesuai. Jangan mengirim `storage_key`, hash internal, ID pengunggah, atau path filesystem ke HTML/JavaScript. Tampilan editor draf yang dapat diakses bukan izin publik untuk berkas gambarnya. Pelajaran tanpa gambar tetap berfungsi; kegagalan membaca metadata bukan keadaan “belum ada gambar”. Detail guard dan batas runtime mengacu pada [panduan lingkungan](PANDUAN_LINGKUNGAN_DAN_DEPLOYMENT.md#gambar-pendamping-pelajaran-lingkungan-dan-batas-bukti).

`scripts/test-lesson-media-view.php` memeriksa tampilan kosong, multipart/revisi/konfirmasi, metadata ter-escape, error tertaut, capability unggah yang tidak tersedia, form konflik, endpoint publik/pratinjau yang berbeda, dan tidak bocornya metadata penyimpanan. Tes ini memakai helper form tiruan dan bukan bukti CSRF atau unggah HTTP; suite HTTP memeriksa bagian tersebut secara terpisah. [Baseline gambar](visual-baseline/2026-10-15-lesson-media/README.md) mencatat enam kombinasi editor/pratinjau pada lebar 360/768/1280 piksel. Bukti lokal memakai gambar sintetis kecil, satu tema peserta, dan unggah yang dinonaktifkan karena Apache PHP 8.0 tidak mempunyai GD; unggah sukses HTTP PHP 8.1, gambar besar/portrait, zoom 200%, screen reader, dan UAT belum tercakup.

## Bahasa dan format data yang terlihat

- Gunakan Bahasa Indonesia yang konsisten. Pilih satu istilah untuk satu fungsi, misalnya “kredit belajar” untuk saldo berbayar dan “poin” untuk hadiah permainan.
- Format harga dengan Rupiah, misalnya `Rp25.000`, dan tampilkan jumlah kredit yang akan diterima atau dipakai di dekatnya.
- Gunakan format tanggal yang konsisten dan mudah dibaca pengguna Indonesia. Jika ada jam, tampilkan zona waktu yang berlaku.
- Angka hasil, durasi, jumlah soal, dan persentase menggunakan format yang sama di kartu, tabel, laporan, dan halaman detail.
- Hindari kode status seperti `PENDING`, `FAILED`, atau nama teknis di tampilan pengguna. Terjemahkan menjadi pesan yang menjelaskan keadaan dan langkah berikutnya.
- Pesan kesalahan tidak menyalahkan pengguna dan tidak membocorkan rincian teknis atau data akun lain.

## Pembayaran dan saldo kredit di tampilan

- Sebelum pengguna membuat QRIS, tampilkan jumlah Rupiah, kredit yang diterima, biaya tambahan bila ada, dan batas waktu pembayaran.
- Saat menunggu, tampilkan status yang dapat diperbarui dan cara melanjutkan pembayaran. Jangan menyebut pembayaran berhasil sebelum server mengonfirmasi statusnya.
- Setelah berhasil, tampilkan bukti atau nomor transaksi, kredit masuk, dan tombol untuk kembali memilih paket.
- Untuk paket anak, pembayar dan pemilik kredit harus terlihat jelas sebagai orang tua; anak tidak diminta memasukkan data pembayaran.
- Sebelum menggunakan kredit, tampilkan harga paket, saldo awal, sisa saldo, anak penerima paket bila relevan, serta masa akses.
- Pisahkan tampilan kredit berbayar dari XP, koin permainan, atau poin pencapaian. Nama, ikon, warna, dan saldo tidak boleh membuat pengguna mengira semuanya dapat dipakai membeli paket.

## Tampilan ponsel

- Rancang dan tinjau halaman mulai dari lebar ponsel; jangan hanya mengecilkan tampilan desktop.
- Konten utama mengisi lebar layar tanpa gulir horizontal yang tidak perlu.
- Tombol, pilihan jawaban, dan kontrol penting cukup besar untuk disentuh dengan nyaman serta mempunyai jarak yang cukup.
- Letakkan aksi utama di tempat yang mudah dijangkau. Hindari banyak tombol kecil berdempetan.
- Menu, tab, filter, tabel, dialog, grafik, dan permainan harus memiliki bentuk ponsel yang dirancang khusus.
- Hindari dialog yang melebihi layar. Dialog ponsel harus dapat digulir dan ditutup dengan mudah.
- Gambar, animasi, dan video tidak boleh memperlambat pembukaan halaman atau memotong teks penting.
- Uji halaman pada koneksi lambat dan perangkat dengan layar kecil.

## Kejelasan, aksesibilitas, dan keramahan anak

- Pastikan teks mudah dibaca, kontras teks dan latar cukup, dan ukuran teks dapat diperbesar.
- Seluruh fungsi utama dapat digunakan dengan keyboard dan teknologi bantu.
- Tombol ikon memiliki nama yang dapat dikenali pembaca layar.
- Gunakan teks alternatif untuk gambar yang membawa informasi; gambar dekoratif tidak mengulang isi yang sama.
- Jangan mengandalkan warna, suara, atau animasi saja untuk memberi tahu pengguna tentang hasil atau kesalahan.
- Sediakan jeda, pengulangan instruksi, dan pembahasan untuk latihan anak.
- Hindari peringkat yang mempermalukan, notifikasi mendesak, hitung mundur yang tidak perlu, dan mekanisme yang membuat anak takut kehilangan kemajuan.
- Jangan tampilkan pesan pembelian atau ajakan transaksi yang membingungkan anak. Proses pembayaran dikendalikan orang tua.
- Sediakan pengaturan untuk suara, animasi, pengingat, dan visibilitas profil.
- Identitas publik anak menggunakan nama tampilan. Jangan menampilkan nama lengkap, sekolah, lokasi, atau kontak dalam papan peringkat dan tantangan.
- Materi yang tampil untuk anak harus sesuai kelompok usia. Pengelola dapat menetapkan kelompok usia dan meninjau pratinjau sebelum menerbitkan konten.

## Pola coding halaman

Jika aplikasi memakai CodeIgniter 3 seperti Pustaka, gunakan susunan berikut sebagai dasar. Jika framework berubah, pertahankan pemisahan tanggung jawab yang sama.

```text
application/
  controllers/   menerima permintaan, memeriksa akses, menyiapkan data halaman
  models/        membaca dan mengubah data serta menjalankan aturan bisnis
  views/         menampilkan HTML, tanpa query database atau aturan bisnis
  libraries/     layanan bersama seperti pembayaran dan akses paket
  helpers/       fungsi kecil yang benar-benar dipakai lintas halaman
assets/
  css/           token tampilan dan gaya komponen bersama
  js/            interaksi halaman dan komponen bersama
  img/           ikon, ilustrasi, avatar, dan gambar
application/migrations/ perubahan database CI3 yang berurutan dan dapat ditinjau
sql/             skrip bantu/seed atau ekspor skema yang memang dibutuhkan
docs/            konsep, standar, keputusan, dan petunjuk pengoperasian
```

Aturan tanggung jawab:

- Controller memeriksa login, izin, input, dan menyiapkan respons. Controller tidak menjadi tempat query rumit atau perhitungan bisnis besar.
- Model memegang query dan aturan data. Transaksi kredit, pembayaran, akses paket, dan sesi latihan dijalankan secara atomik saat perlu.
- View hanya menyusun tampilan dari data yang telah disiapkan. Seluruh teks dari pengguna harus ditampilkan dengan aman.
- Komponen yang digunakan berulang dibuat satu kali dan memakai parameter yang jelas, bukan disalin ke banyak halaman.
- File CSS atau JavaScript satu halaman hanya ditambahkan bila perilakunya memang khusus; tampilan umum tetap menggunakan aturan bersama.
- Aset CSS/JavaScript milik aplikasi harus memiliki versi URL berdasarkan manifest versi/build atau fingerprint waktu modifikasi agar browser tidak memakai file lama setelah deploy. Versi library vendor tetap dikunci; jangan mengunduh ulang semua vendor pada setiap perubahan tampilan.
- Route, nama izin, halaman, dan menu dicatat konsisten. Jangan mengandalkan menu tersembunyi sebagai pengaman endpoint.

Aturan data dan layanan:

- Operasi mutasi latihan kursus dan mulai attempt keluarga menggunakan `Study_transaction` sebagai pemilik transaksi terluar. Tolak transaksi bertingkat tanpa mengambil alih/rollback transaksi pemanggil; callback tidak boleh menjalankan BEGIN/COMMIT sendiri. Periksa setiap hasil baca/tulis, status transaksi, COMMIT dan ROLLBACK; berhenti pada kegagalan pertama dan jangan menganggap `trans_status()` saja membuktikan persistensi. Pulihkan `db_debug` pada seluruh jalur. Log baru hanya diagnostik generik; logger SQL bawaan CI3 masih memerlukan kebijakan terpisah.
- Pemeriksaan akses mutasi setelah penguncian memakai current read (`FOR UPDATE` yang terparameterisasi), bukan snapshot lama dari pembacaan awal transaksi. Pertahankan urutan lock bersama, snapshot soal/kartu immutable, revisi jawaban/langkah dan replay idempoten. `can_access_lesson` meneruskan flag lock opsional ke akses kursus; default baca biasa serta pratinjau/gratis tidak berubah. Uji SQL shape/fault injection tidak menggantikan uji race paralel atau putus koneksi sesudah COMMIT nyata. Pembungkus digunakan pada `Practice_model::start/save_answers`, `Assignment_model::start`, dan `Matching_run_model::start/move/abandon`, bukan seluruh model, transaksi uang, atau penyusunan/kirim/salin/batal sesi orang tua.
- Setiap fitur memiliki pemilik data yang jelas, seperti pengguna, keluarga, kursus, sesi latihan, pesanan kredit, atau akses paket.
- Gunakan relasi dan batasan database untuk menjaga hubungan data. Saldo kredit disimpan sebagai jumlah bulat dan riwayat mutasi menjadi sumber audit.
- Perubahan saldo dan penerbitan akses paket harus memakai transaksi database agar tidak ada kredit terpotong tanpa akses atau akses terbit dua kali.
- Proses notifikasi pembayaran harus aman bila dipanggil ulang. Gunakan identitas transaksi unik dan catat hasil pemrosesan.
- Data soal, progres, dan transaksi hanya diambil setelah ruang lingkup pemilik diperiksa. Mengetahui nomor ID tidak boleh cukup untuk membuka data.
- Jangan mengandalkan aturan di browser untuk membatasi jumlah percobaan, harga, nilai kredit, jawaban benar, atau hak membuka paket.
- Simpan tanggal transaksi dalam format database yang konsisten dan tampilkan sesuai zona waktu aplikasi.

## Nama file dan kode

- Nama route publik ditulis pendek, huruf kecil, dan memakai tanda hubung, misalnya `/paket-belajar`.
- Nama metode mengikuti gaya framework dan pola proyek; satu istilah harus ditulis sama di route, controller, menu, dan dokumentasi.
- Hook controller CI3 `_remap` dikecualikan secara spesifik dari aturan snake_case tanpa awalan garis bawah, karena dispatcher framework memerlukan nama tersebut. Tetap batasi metode tujuan dengan allowlist dan periksa izin/metode HTTP server-side; pengecualian ini bukan izin melewati otorisasi.
- Nama tabel memakai awalan atau kelompok yang konsisten per fitur agar tabel belajar, kredit, transaksi, dan keluarga mudah dikenali.
- Perubahan struktur database dibuat sebagai berkas perubahan yang berurutan dan dapat ditinjau. Jangan mengubah skema produksi tanpa catatan perubahan.
- Hindari singkatan yang hanya dipahami pembuatnya. Nama variabel, fungsi, kolom, dan tombol harus menjelaskan maksudnya.
- Pesan untuk pengguna ditulis dalam Bahasa Indonesia. Istilah internal atau kode status tidak ditampilkan langsung.

### Gaya PHP untuk CodeIgniter 3

Ikuti gaya CodeIgniter 3.1.13 sebagai baseline agar konsisten dengan framework dan file yang sudah ada; jangan menerapkan PSR-12 atau PEAR secara mentah.

- Simpan source sebagai UTF-8 tanpa BOM dengan akhir baris LF; file yang hanya berisi PHP tidak menutup dengan `?>`.
- Gunakan tab untuk indentasi sesuai `.editorconfig`, dan letakkan kurung kurawal pada baris tersendiri (gaya Allman).
- Nama class/interface/trait diawali huruf besar dan kata berikutnya dipisahkan garis bawah, seperti `Content_model`; jangan memakai camelCase. Metode/fungsi dan variabel memakai huruf kecil dengan garis bawah, seperti `get_course()` dan `$course_id`.
- Nama konstanta memakai huruf besar. Untuk boolean/null, ikuti bentuk yang sudah dipakai framework CI3 pada area terkait; jangan mencampur gaya dalam file baru.
- Pilih perbandingan ketat (`===`/`!==`) saat tipe data bermakna; validasi dan konversi input di batas controller/model, bukan mengandalkan cast implisit.
- Pecah baris dan ekstrak komponen bila membantu keterbacaan; jangan memformat ulang massal hanya untuk memenuhi formatter yang tidak kompatibel.
- Tetap jalankan `php -l` pada PHP 8.0 lokal dan PHP 8.1 target. Baseline PHP_CodeSniffer di QA memeriksa BOM, LF, short tag, error suppression, dan indentasi tanpa spasi pada seluruh file PHP first-party. Ruleset terpisah memeriksa scope bertingkat berbasis tab dan Allman untuk deklarasi fungsi/method pada source PHP murni; pemeriksa tokenizer menegakkan nama class/interface/trait tanpa camelCase dan snake_case fungsi/method/properti/variabel pada seluruh file aplikasi/skrip, dengan pengecualian nama magic method, superglobal, dan variabel konfigurasi CI3. View campuran PHP/HTML tetap mendapat hygiene umum karena tiga view legacy memicu false positive pada sniff scope-indent.

Acuan: [CodeIgniter 3.1.13 PHP Style Guide](https://codeigniter.com/userguide3/general/styleguide.html). Ini panduan konvensi, bukan jaminan bahwa seluruh kode lama framework atau aplikasi telah memenuhi aturan secara otomatis.

## Keamanan dan data

- Periksa hak akses di server untuk setiap halaman, permintaan AJAX, perubahan, ekspor, dan file.
- Orang tua hanya boleh melihat akun anak yang sedang tertaut. Pemilik sesi latihan pribadi harus diverifikasi pada setiap akses.
- Validasi semua masukan di server; gunakan query berparameter dan batasi panjang serta jenis data.
- Tulis pesan validasi untuk pengguna dalam Bahasa Indonesia. Hubungkan setiap error inline ke kontrol memakai `aria-describedby` dan tandai kondisi kontrol dengan `aria-invalid`; jangan pernah mengisi ulang atau memantulkan nilai kata sandi setelah form gagal.
- Perlakukan nilai query pencarian/filter/paginasi sebagai input tidak tepercaya: tolak atau abaikan nilai non-scalar (termasuk bentuk `q[]`), validasi halaman sebagai bilangan bulat positif dan batasi maksimum, serta potong teks UTF-8 berdasarkan karakter agar byte multibyte tidak rusak.
- Lindungi perubahan data dengan token CSRF dan gunakan metode permintaan yang sesuai.
- Respons aplikasi dinamis menetapkan `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, dan `Referrer-Policy: strict-origin-when-cross-origin` dari front controller. Aturan server web tetap perlu menerapkan header yang sesuai pada aset statis dan diuji di staging; jangan menganggap header aplikasi menggantikan konfigurasi Nginx.
- Escape teks saat ditampilkan untuk mencegah konten berbahaya menjadi kode halaman.
- Jangan pernah mengirim kunci rahasia Midtrans ke browser. Status pembayaran ditentukan dari notifikasi server yang diverifikasi.
- Catat perubahan penting, seperti top up kredit, pemakaian kredit, pembukaan paket, perubahan sesi latihan, dan pengaitan akun keluarga.
- Simpan saldo kredit berdasarkan catatan mutasi yang tidak dapat diubah diam-diam; koreksi dicatat sebagai transaksi penyesuaian.
- Batasi akses ke tanggal lahir, kontak, hasil anak, dan informasi keluarga sesuai peran.
- Batasi percobaan login, pengiriman formulir, pembuatan sesi, dan pengiriman jawaban untuk mengurangi penyalahgunaan.
- Jangan menyimpan kunci, token, kata sandi, atau data pembayaran rahasia ke log umum.

## Status halaman yang wajib disiapkan

Setiap daftar, formulir, atau halaman belajar perlu menangani keadaan berikut sesuai kebutuhannya:

- pertama kali dibuka dan belum ada data;
- sedang memuat;
- berhasil memuat;
- hasil kosong setelah pencarian atau filter;
- gagal memuat dan dapat dicoba lagi;
- akses ditolak atau sesi login berakhir;
- berhasil menyimpan, mengirim, atau menyelesaikan latihan;
- kesalahan validasi dan cara memperbaikinya.

Pesan harus menyebut apa yang terjadi dan langkah berikutnya. Jangan hanya menampilkan kode kesalahan atau layar kosong.

### Halaman pemulihan HTTP mandiri

Halaman HTML 404 dan kesalahan umum CI3 harus memakai bahasa Indonesia, viewport ponsel, tipografi/warna merek, tautan lewati ke isi dan fokus keyboard yang terlihat. Gunakan tema netral yang ramah semua usia: jangan membaca sesi atau database hanya untuk menentukan tema ketika aplikasi sedang gagal. CSS kritis boleh berada di template ini agar halaman tetap terbaca tanpa aset, font eksternal, atau JavaScript.

- Pertahankan status HTTP asli; halaman cantik bukan alasan mengubah 404/403/429/500 menjadi 200. Respons HTML ini tidak boleh disimpan cache dan tidak boleh diindeks.
- 404 tidak memantulkan alamat yang diminta. Pesan aplikasi 4xx ditampilkan sebagai teks ter-escape; penolakan CSRF bawaan diterjemahkan dengan petunjuk membuka formulir kembali, dan URI tidak valid diberi petunjuk memakai tautan aplikasi. Pesan 5xx selalu generik, tanpa query, stack trace, path, atau rincian akun.
- Tautan kembali hanya memakai URL konfigurasi yang tervalidasi, tidak pernah `Host`, `Referer`, atau parameter permintaan. Jika konfigurasi URL tidak tersedia/aman, hilangkan tautannya. Jangan memuat konfigurasi baru saat menangani 5xx.
- Tidak ada kirim ulang POST, redirect otomatis, tombol bayar ulang, atau janji data pasti tersimpan. Untuk transaksi yang belum jelas, minta pengguna memeriksa hasil sebelum mengulang.
- Kriteria penerimaan: render 400/403/404/405/409/429/500/503, uji escaping dan URL tidak aman, HTTP nyata 403/404, serta pemeriksaan keyboard dan lebar 360/768/1280 piksel. Template debug PHP/database/exception dan kegagalan bootstrap bukan bagian klaim ini; pengamanan produksi tetap mengikuti panduan deployment.

## Pemeriksaan sebelum halaman dinyatakan selesai

- Judul, istilah, warna, tombol, jarak, ikon, dan status mengikuti komponen bersama.
- Halaman tampak tepat untuk anak, remaja, orang tua, atau pengguna dewasa yang dituju.
- Pencarian, filter, pengurutan, pagination, dan tab tetap bekerja bersama dan mempertahankan konteks.
- Halaman memiliki keadaan kosong, memuat, gagal, dan berhasil yang sesuai.
- Semua aksi memeriksa hak akses dan data yang dimiliki pengguna di server.
- Tampilan tetap dapat digunakan pada ponsel, keyboard, serta teks yang diperbesar.
- Gambar, video, dan animasi tidak menghalangi isi utama.
- Pesan kesalahan dan konfirmasi memakai bahasa yang jelas dan ramah.
- Harga, kredit, XP, dan poin permainan tidak tertukar.
- Periksa halaman pada peran yang tepat: anak, orang tua, peserta dewasa, dan pengelola.
- Periksa pratinjau anak untuk kelompok usia yang dituju sebelum kursus diterbitkan.

## Menjaga standar tetap berlaku

### Laporan latihan keluarga

- Sediakan tautan laporan pada kartu anak di dashboard. Gunakan tema orang tua, nama anak yang di-escape, keadaan kosong yang membantu, dan kartu dua kolom di desktop/satu kolom di ponsel.
- Ringkasan selalu menjelaskan cakupan semua halaman serta bahwa percobaan ulang dihitung terpisah. Jangan menyamakan persentase satu latihan dengan penguasaan seluruh kursus, atau menampilkan peringkat antar-anak.
- Tampilkan tanggal mulai/selesai dalam format waktu aplikasi, jumlah benar/soal, dan saran pendampingan yang tidak menghakimi. Detail hasil memakai komponen pembahasan yang sama dengan peserta, tetapi label menjadi “Pilihan anak” serta tanpa tombol mencoba/menjawab atas nama anak.
- Latihan berjalan hanya menampilkan status, bukan jawaban/kunci/pembahasan. Riwayat 10 per halaman memakai komponen pagination yang sama; halaman terlalu besar dibatasi ke halaman terakhir dan parameter array kembali ke halaman pertama.
- Laporan bersifat baca-saja dan tidak boleh disimpan cache bersama. Status tautan/akun dan identitas anak diperiksa server pada setiap query; menyembunyikan tautan saja bukan kontrol akses.

### Pemeliharaan standar

Halaman **Salin menjadi latihan baru** ditujukan untuk orang tua pembuat, dengan ringkasan sumber dan formulir tujuan berdampingan di desktop/bertumpuk di ponsel. Tampilkan nama sesi/anak sumber, anak tujuan, nama baru dan konfirmasi. Jelaskan bahwa soal disalin mandiri, tetapi jadwal/jawaban/nilai tidak ikut dan salinan belum dikirim. Hak paket habis menampilkan informasi tanpa formulir salin. Kosong/tidak lengkap, revisi lama dan kapasitas penuh harus memberi pesan, bukan salinan sebagian. Setelah berhasil, arahkan ke editor salinan dengan pesan untuk meninjau kesesuaian usia sebelum pengiriman. Semua label/escaping/error memakai komponen yang sama dengan draf; proses tetap berfungsi tanpa JavaScript.

Untuk sesi terkirim, tampilkan status jadwal yang mudah dipahami, tanggal mulai/akhir dalam WIB, dan aturan satu percobaan tanpa timer. Jadwal yang belum mulai, lewat, dibatalkan, atau haknya tidak aktif tidak boleh menawarkan tombol pengerjaan yang dapat memintas server. Daftar anak tidak memuat draf orang tua. Form pengiriman memakai `datetime-local`, label WIB eksplisit, konfirmasi setelah meninjau soal, serta pesan tanggal invalid yang terhubung ke kolom batas akhir. Hasil memakai komponen soal/penilaian yang sama dengan kursus; orang tua hanya membaca dan tidak dapat mengubah pilihan anak. Riwayat peserta tidak menawarkan tautan lanjut untuk attempt yang aksesnya tertutup. Materi/label status jangan menyamakan `abandoned` dengan nilai nol atau kelulusan gagal.

Penyusun latihan keluarga menggunakan tema orang tua, informasi “belum dikirim” yang eksplisit, dan keadaan terkunci yang menjelaskan kebutuhan kapabilitas paket tanpa tombol pembayaran yang belum tersedia. Form dan daftar soal berdampingan pada desktop, bertumpuk pada ponsel; tautan “Ke formulir soal” melewati daftar panjang dengan target yang dapat difokuskan. Pembatalan dan hapus soal membutuhkan checkbox konfirmasi. Judul/nama anak/pertanyaan/sumber di-escape, pesan kesalahan terhubung ke kontrol, dan setiap POST membawa revisi draf; konflik tidak boleh diam-diam menimpa data terbaru. Daftar sesi memakai SQL pagination 10; daftar soal dibatasi 50 dan ditampilkan 10 per halaman. Status batal/hak habis jelas sebagai baca-saja. Jangan tampilkan fungsi kirim/kerjakan sampai alurnya tersedia dan diuji.

- Perbarui dokumen ini jika pola bersama berubah; jangan membuat pengecualian diam-diam di satu halaman.
- Catat komponen baru dan alasan penggunaannya sebelum komponen itu dipakai di banyak tempat.
- Tinjau halaman yang sudah ada saat komponen bersama berubah agar tampilan lama tidak menyimpang.
- Gunakan daftar ringkasan halaman pada awal pengerjaan dan pemeriksaan akhir sebagai bagian dari alur pengembangan.
- Ikuti [Roadmap Pengembangan](ROADMAP_PENGEMBANGAN.md) untuk urutan implementasi, gerbang QA, dan pencatatan status pekerjaan.
- Gunakan [Keputusan MVP dan Asumsi Sementara](KEPUTUSAN_MVP_DAN_ASUMSI.md) agar placeholder dan fitur yang masih dinonaktifkan tidak dianggap sebagai kebijakan final.

### Permainan Cocokkan Pasangan — mode lokal

Gunakan tema profil yang sama dengan materi, ikon puzzle sebagai dekorasi tanpa menggantikan judul, dua kolom tombol dengan label Kartu/Pasangan, target sentuh minimal 52 px, dan teks yang membungkus tanpa pemotongan. Tidak memakai drag-and-drop wajib, timer, audio otomatis, atau efek berkedip. Pilihan memakai `aria-pressed`; pasangan selesai memakai simbol serta teks “Cocok”, bukan warna saja. Status hasil disampaikan melalui live region, dan fokus berpindah ke pilihan berikutnya/tombol babak setelah cocok. Animasi singkat menghormati reduced motion. Penjelasan sebelum bermain menyebut progres hanya pada halaman ini dan bukan XP/kredit/nilai resmi. Alternatif baca pasangan tetap tersedia tanpa JavaScript. Gunakan `textContent` untuk teks kartu dinamis dan JSON ber-HEX escaping untuk data inline, bukan interpolasi HTML. Permainan tidak memanggil endpoint penulisan ataupun local/session storage.

### Cocokkan Pasangan — mode tersimpan

Mode tersimpan adalah pilihan terpisah, bukan perubahan diam-diam terhadap permainan lokal. Jelaskan kebutuhan login/koneksi, apa yang dicatat dan bahwa progres lokal tidak dipindahkan. Tampilkan mulai atau lanjutkan sesuai run aktif; mulai ulang tidak boleh menghapus histori. Gunakan tema profil, dua fieldset Kartu/Pasangan, kartu radio berlabel dengan target sentuh besar, indikator pilihan native dan focus ring. Peserta memilih kedua sisi lalu menekan Periksa pasangan. Formulir harus tetap berfungsi tanpa JavaScript; tidak memerlukan drag-and-drop, animasi, atau timer.

Jangan tampilkan sukses sebelum server menyimpan. Gunakan POST + CSRF + token tindakan terikat peserta/run/revisi dan redirect 303. Setelah pengiriman, fokus/tautan fragmen diarahkan ke status hasil; tampilkan progres server, jumlah pilihan, dan ajakan mencoba lagi tanpa mempermalukan. Konflik tab menampilkan progres terbaru dan meminta pilihan ulang. Beri petunjuk menunggu halaman baru serta cara memeriksa ulang ketika koneksi terputus; jangan mengklaim dukungan offline/autosave.

Peningkatan JavaScript pada formulir tersimpan hanya membantu pengiriman, bukan menjadi sumber progres. Salin nilai formulir sebelum menonaktifkan kontrol, cegah pengiriman kedua, dan tampilkan status melalui live region. POST memakai kredensial same-origin dan redirect manual agar pesan hasil server tidak habis terbaca oleh request latar belakang. Setelah redirect, navigasi GET menampilkan hasil sebenarnya. Timeout 15 detik, gangguan jaringan, atau respons yang tidak dapat dipastikan harus menjelaskan bahwa pilihan mungkin sudah tersimpan; jangan menaikkan hitungan atau mengirim ulang otomatis. Kunci formulir revisi lama dan fokuskan tautan **Buka progres terbaru**. Respons 403 juga menyembunyikan kartu. GET baru diperlukan untuk memperoleh keadaan, CSRF, dan token tindakan yang segar. Jika JavaScript tidak dimuat, formulir HTML biasa tetap menjadi jalur yang didukung.

Bedakan beristirahat dengan menghentikan: beristirahat tidak membutuhkan mutasi. Hentikan memakai halaman konfirmasi, checkbox eksplisit, tombol sekunder berisiko dan tombol kembali; formulir usang ditolak. Saat akses hilang, jangan kirim kartu dalam HTML; tetap jelaskan progres tersimpan dan sediakan pilihan menghentikan. Hasil selesai/dihentikan hanya-baca. Durasi harus selalu berlabel rentang sejak mulai termasuk jeda, bukan waktu belajar aktif atau nilai ujian.

Saat halaman permainan aktif dipulihkan dari back/forward cache, handler `pageshow` dengan `persisted=true` harus melakukan reload GET. DOM yang tersimpan bisa memuat revisi lama atau tombol yang masih terkunci ketika pengguna meninggalkan halaman. Jangan mengaktifkan ulang formulir lama atau mengulangi POST dari handler ini. `persisted=false` tidak memicu reload, agar pemuatan normal tidak berulang tanpa akhir. Acuan perilaku browser: [MDN pageshow](https://developer.mozilla.org/en-US/docs/Web/API/Window/pageshow_event). Uji event secara terisolasi tidak membuktikan browser benar-benar memakai BFCache; pisahkan bukti unit dan uji navigasi browser nyata.

Riwayat memakai kartu responsif, judul ter-escape, tanggal WIB, status Indonesia, pencarian literal, filter status dan pagination 10 item. Pertahankan filter di tautan halaman, bedakan daftar kosong dengan hasil filter kosong, dan sediakan Reset. Gunakan route pemilik sendiri; ID pada URL bukan izin. Uji form native/keyboard, refresh, dua tab, CSRF, akses lintas akun, arsip, serta ukuran 360/768/1280. Uji request simultan, pemutusan jaringan, pembaca layar dan zoom 200% tetap diperlukan sebelum mengklaim cakupan penuh.

### Laporan permainan keluarga

Gunakan tema orang tua dan nama anak yang di-escape pada judul. Ringkasan seluruh histori tetap terpisah dari daftar yang terkena filter: beri label bahwa pencarian/status tidak mengubah ringkasan. Pertahankan pola input berlabel, filter AND, Reset, 10 kartu per halaman, urutan terbaru dan keadaan kosong yang membedakan belum ada aktivitas dengan filter tak cocok. Tanggal memakai WIB.

Detail tampil sebagai catatan hanya-baca: pasangan ditemukan, pilihan dikirim/belum cocok, status dan waktu. Jangan mengirim kartu, log pilihan per langkah, token mutasi atau tombol selesai/hentikan milik anak. Progres aktif dilabeli sementara; dihentikan bukan selesai. Waktu selesai disebut rentang termasuk jeda, bukan lama belajar aktif. Beri ajakan mendampingi dan menghargai usaha, tanpa ranking antar-anak. Tautkan laporan dari kartu anak di dashboard; tautan URL tidak menggantikan pemeriksaan hubungan aktif pada setiap query. Uji kedua parent tertaut, keluarga lain, anak lain, akun/peran berubah, revokasi, status arsip dan permintaan POST terlarang.

## Acuan dari Pustaka

Standar ini mengambil pelajaran dari struktur akses dan menu pada `pustaka/docs/RBAC_AND_SIDEBAR_STANDARD.md`, keamanan pada `pustaka/docs/SECURITY_AND_ACCESS.md`, serta pola formulir, tabel, filter, tab, dan responsif pada `pustaka/assets/css/pustaka.css`. Komponen yang berguna dapat diadaptasi, tetapi setiap halaman perlu disesuaikan untuk pengguna dan tujuan aplikasi belajar.
