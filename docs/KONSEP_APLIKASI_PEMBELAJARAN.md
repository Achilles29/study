# Konsep Aplikasi Pembelajaran Interaktif

> **Keputusan terbaru:** [TASK_HANDOVER_SERVER](TASK_HANDOVER_SERVER.md) merangkum arah Project Pintar, sasaran TK/SD/SMP, pembayaran, poin penukaran, keluarga dan battle. Dokumen tersebut mengungguli asumsi lama yang bertentangan; harga/durasi yang berlabel usulan belum berlaku sebagai penjualan live. Pembuatan konten sesuai kurikulum adalah pekerjaan terpisah. Pengembangan lokal dijeda untuk migrasi, 16 Oktober 2026.

**Dokumen konsep produk**  
**Tanggal:** 14 Oktober 2026  
**Nama aplikasi:** Project Pintar — https://www.projectpintar.com/

## Ringkasan

Aplikasi ini menjadi tempat belajar daring untuk anak, remaja, dan orang dewasa. Pengguna dapat mengikuti kursus, membaca materi, berlatih soal, bermain sambil belajar, dan melihat perkembangan dari waktu ke waktu. Aplikasi menyediakan akses gratis dengan batas tertentu dan paket berbayar yang dapat dibuka menggunakan kredit belajar.

Orang tua dan anak menggunakan akun terpisah yang saling ditautkan. Anak memakai aplikasi untuk belajar. Orang tua melihat kemajuan anak dan, pada layanan berbayar, dapat menyusun sesi latihan khusus menggunakan soal buatannya sendiri.

Konsep ini mengadaptasi modul pembelajaran yang sudah ada di Pustaka, lalu menyesuaikannya untuk pengguna umum dan kebutuhan komersial. Modul perpustakaan yang tidak berhubungan dengan belajar tidak perlu ikut dipindahkan.

## Tujuan aplikasi

- Membuat kegiatan belajar mandiri terasa ringan, terarah, dan menyenangkan.
- Menyatukan kursus, materi, kuis, latihan soal, permainan, dan laporan dalam satu akun.
- Membantu anak memahami materi melalui latihan dan pembahasan, bukan hanya mengumpulkan nilai.
- Memberi orang tua cara sederhana untuk mendampingi dan memantau belajar anak.
- Menyediakan cara pembayaran yang mudah melalui QRIS.

## Siapa yang menggunakan aplikasi

### Anak atau peserta belajar

Anak memilih materi atau paket belajar, mengikuti pelajaran, mengerjakan latihan, memainkan permainan edukatif, dan melihat hasil belajarnya sendiri.

### Peserta dewasa

Orang dewasa dapat memakai akun belajar mandiri tanpa membuat akun orang tua. Mereka dapat membeli paket, mengikuti kursus, berlatih, dan memantau kemajuan dari akunnya sendiri.

### Orang tua atau pendamping

Orang tua memiliki akun terpisah yang ditautkan dengan akun anak. Orang tua dapat melihat ringkasan kemajuan dan riwayat latihan anak. Pada paket berbayar, orang tua juga dapat membuat sesi latihan khusus untuk anak.

Satu akun orang tua sebaiknya dapat menautkan lebih dari satu akun anak, misalnya untuk kakak dan adik. Setiap anak tetap memiliki riwayat dan kemajuan masing-masing.

### Pengelola aplikasi

Pengelola menambahkan kursus, materi, soal, permainan, harga paket, dan aturan kredit. Pengelola juga menangani transaksi, laporan aktivitas, bantuan pengguna, dan peninjauan konten.

Pada tahap berikutnya, pengajar atau mitra dapat mengirim kursus untuk ditinjau dan diterbitkan. Hak mengelola konten tidak otomatis memberi akses ke data pribadi peserta.

## Pengalaman belajar

Alur utama dibuat sederhana:

1. Pengguna memilih tujuan atau topik yang ingin dipelajari.
2. Aplikasi menyarankan kursus atau jalur belajar yang sesuai.
3. Peserta membaca atau menonton materi singkat.
4. Peserta mencoba latihan, kuis, kartu belajar, atau permainan.
5. Setelah menjawab, peserta melihat hasil dan pembahasan yang membantu memahami kesalahan.
6. Kemajuan tersimpan dan aplikasi menyarankan langkah berikutnya.

Pelajaran dapat disusun menjadi beberapa bagian. Setiap bagian memiliki tujuan yang jelas dan latihan singkat agar pengguna mudah melanjutkan belajar saat punya waktu.

Susunan katalog yang disarankan adalah **kategori → paket → kursus → bagian → pelajaran → aktivitas**. Paket adalah pilihan yang dibeli atau dibuka dengan kredit; satu paket dapat berisi satu atau beberapa kursus. Halaman katalog tetap dapat menampilkan kursus gratis secara langsung agar pengguna bisa mencoba sebelum memilih paket.

## Modul belajar yang diadaptasi dari Pustaka

| Modul yang sudah ada | Bentuknya di aplikasi baru |
|---|---|
| Bank soal dan pengelompokan jenjang, mata pelajaran, kesulitan, serta tag | Katalog soal untuk berbagai topik dan tingkat kemampuan. Admin dapat menambah soal satu per satu atau mengimpor banyak soal. |
| Bank soal dengan impor berkas dan pengelolaan kelompok soal | Admin dapat membuat soal satu per satu atau mengimpor kumpulan soal memakai format contoh. Sistem menampilkan baris yang gagal agar mudah diperbaiki. |
| Sesi latihan dengan soal acak, soal terpilih, atau soal khusus sesi | Peserta mengerjakan latihan berdasarkan topik, jumlah soal, tingkat kesulitan, dan waktu. Sesi milik keluarga atau kompetisi dapat memakai daftar soal yang sudah ditetapkan. |
| Kuis, ujian, dan kompetisi | Selain latihan mandiri, pengelola dapat mengadakan kompetisi dengan jadwal, pendaftaran, jumlah percobaan, kode peserta, hasil, serta tinjauan jawaban esai. Fitur ini dikembangkan setelah MVP. |
| Soal pilihan ganda dan esai, penilaian, serta pembahasan | Jenis soal dapat dikembangkan bertahap. Jawaban dinilai dan dijelaskan sesuai jenis soalnya. Esai dapat ditinjau orang tua, pengajar, atau admin. |
| Permainan mini seperti mencocokkan pasangan, hitung cepat, susun kata, dan benar atau salah | Permainan singkat menjadi variasi latihan, dengan catatan skor dan waktu bermain. Jenis permainan dapat ditambah sesuai topik. |
| Poin, lencana, peringkat, dan kemajuan | XP, lencana, streak, target harian, serta pencapaian mendorong kebiasaan belajar. Peringkat umum sebaiknya opsional dan ramah anak. |
| Penukaran hadiah berbasis poin | Poin dapat ditukar dengan hadiah non-tunai seperti tema profil, avatar, atau akses bonus yang disetujui pengelola. Poin permainan tidak dapat ditukar menjadi kredit berbayar atau uang. |
| Kartu belajar | Kartu istilah dan penjelasan dengan penanda “sudah paham” atau “perlu diulang”. |
| Kuis berbasis bacaan | Bacaan pendek atau studi kasus diikuti pertanyaan pemahaman. |
| Mode Battle dua pemain | Tantangan dengan teman dapat ditambahkan setelah fitur belajar pribadi stabil. |
| Laporan dan riwayat aktivitas | Anak melihat kemajuannya; orang tua mendapat ringkasan yang mudah dipahami dan dapat dicetak. |
| Notifikasi dan pengumuman | Pengingat belajar, penyelesaian target, lencana baru, atau kompetisi. Pengingat dapat diatur dan pengumuman hanya dikirim kepada pengguna yang sesuai. |
| Pengawasan sesi kompetisi | Timer, pencatatan aktivitas sesi, dan peninjauan indikasi kecurangan dapat digunakan untuk ujian resmi. Latihan harian tetap bebas tekanan. |

Fitur pengawasan ujian yang ketat dari Pustaka sebaiknya tidak otomatis diterapkan pada semua latihan. Untuk pembelajaran harian, pengguna perlu merasa aman mencoba dan membuat kesalahan. Pengawasan tambahan hanya digunakan untuk ujian atau kompetisi yang memang membutuhkannya.

## Akun orang tua dan akun anak

### Menautkan akun

Orang tua membuat akun, lalu menambahkan akun anak melalui tautan undangan atau kode yang hanya dapat dipakai sekali. Orang tua dan anak masuk menggunakan akun masing-masing. Hubungan akun dapat dicabut jika diperlukan.

Anak dapat melihat paket yang tersedia dan meminta orang tua membelinya. Pembayaran dan persetujuan pembelian dilakukan dari akun orang tua. Jika anak sudah cukup umur dan ingin belajar mandiri, orang tua dapat melepaskan tautan keluarga sesuai aturan akun aplikasi.

### Yang dapat dilihat orang tua

- Materi atau kursus yang sedang dipelajari.
- Aktivitas belajar dan konsistensi anak.
- Nilai latihan, bagian yang sudah dikuasai, dan topik yang masih sulit.
- Lencana dan pencapaian anak.
- Saran kegiatan belajar berikutnya.

Laporan sebaiknya menjelaskan pola kemajuan dengan bahasa sederhana. Contohnya: “Anak sudah kuat di perkalian dasar; latihan pembagian masih perlu diulang.”

### Sesi latihan khusus dari orang tua

Pada paket yang mendukung fitur ini, orang tua dapat membuat sesi latihan anak dengan alur berikut:

1. Pilih anak yang akan mengerjakan latihan.
2. Buat sesi dan beri nama, misalnya “Latihan pecahan minggu ini”.
3. Tulis soal sendiri, atau pilih soal dari bank soal yang tersedia.
4. Tambahkan pilihan jawaban, kunci, pembahasan, tingkat kesulitan, dan jumlah percobaan bila diperlukan.
5. Simpan sesi sebagai draf atau kirim ke akun anak.
6. Anak mengerjakan dari akunnya.
7. Orang tua melihat jawaban, hasil, dan soal yang perlu dibahas lagi.

Soal pribadi milik orang tua hanya terlihat oleh keluarga tersebut, kecuali orang tua dengan sengaja memilih untuk membagikannya. Pada versi pertama, jenis soal khusus dapat dimulai dari pilihan ganda dan benar atau salah. Jenis lain menyusul setelah alur dasarnya mudah digunakan.

## Akses gratis dan paket berbayar

### Akses gratis

Mode gratis memberi kesempatan mencoba aplikasi tanpa pembayaran. Batasnya dapat mencakup:

- beberapa materi contoh;
- jumlah latihan atau kuis harian yang wajar;
- akses ke sebagian permainan dan kartu belajar;
- ringkasan kemajuan dasar;
- satu akun anak yang ditautkan untuk melihat contoh laporan orang tua.

Batas gratis perlu terasa cukup untuk merasakan manfaat aplikasi, tetapi tidak membuka seluruh materi premium. Jumlah materi dan latihan yang disediakan sebaiknya dapat diatur dari panel pengelola.

### Akses berbayar

Pengguna dapat memakai kredit belajar untuk membuka paket tertentu. Paket dapat berisi kursus, latihan, simulasi ujian, permainan, pembahasan lengkap, atau fitur keluarga.

Setiap halaman paket harus menampilkan dengan jelas:

- isi paket;
- jumlah materi atau latihan;
- masa akses;
- jumlah kredit yang dibutuhkan;
- apakah paket dibuka permanen atau hanya untuk jangka waktu tertentu.

Rekomendasi awal saya adalah menjual paket belajar satuan dengan akses yang jelas, lalu menambahkan langganan bulanan setelah kebutuhan dan pola penggunaan pengguna terlihat. Dengan begitu, pengguna dapat mencoba satu paket tanpa harus langsung berlangganan.

### Pemilik saldo dan akses keluarga

Untuk keluarga, akun orang tua menjadi pihak yang melakukan top up dan menyimpan kredit. Orang tua dapat memakai kredit itu untuk membuka paket bagi akun anak yang ditautkan. Akun anak tidak melakukan pembayaran sendiri. Peserta dewasa memakai saldo dan paket miliknya sendiri. Satu pembelian harus jelas apakah paket berlaku untuk satu anak atau dapat digunakan oleh beberapa anak dalam keluarga.

## Ide tambahan dari pembahasan awal

Beberapa gagasan awal belum dirinci menjadi bagian produk. Fitur-fitur ini dapat membuat belajar terasa seperti permainan tanpa mengubah kesalahan menjadi hukuman.

- **Peta perjalanan belajar:** paket dibagi menjadi tahapan atau dunia kecil. Peserta tahu apa yang sudah selesai dan apa langkah berikutnya.
- **Misi harian dan mingguan:** target ringan seperti menyelesaikan satu materi atau mengulang lima soal. Target dapat diatur dan dilewati tanpa kehilangan akses belajar.
- **Avatar dan hiasan pencapaian:** anak dapat memilih avatar dan membuka hiasan melalui aktivitas belajar. Hadiah kosmetik tidak memengaruhi nilai atau akses ke materi inti.
- **Poin, streak, dan papan peringkat:** gunakan untuk merayakan usaha dan konsistensi. Peringkat publik dibuat opsional, memakai nama tampilan, dan tidak menjadi ukuran utama kemampuan.
- **Petunjuk dan kesempatan mencoba lagi:** peserta dapat meminta petunjuk atau mengulang latihan. Koin permainan, bila digunakan, hanya untuk pilihan tambahan dan tidak boleh menghalangi penjelasan dasar.
- **Tingkat kesulitan yang menyesuaikan:** aplikasi dapat menyarankan soal berikutnya berdasarkan hasil sebelumnya. Anak tetap dapat memilih latihan yang lebih mudah atau lebih menantang.
- **Tantangan dengan teman:** adu kuis ringan atau berbagi tantangan dapat ditambahkan setelah pengalaman keluarga dan kontrol privasi matang.
- **Sertifikat penyelesaian:** paket tertentu dapat memberi sertifikat setelah peserta menyelesaikan materi dan memenuhi syarat yang ditampilkan.
- **Pengingat keluarga:** orang tua dapat mengatur pengingat belajar yang sopan dan memilih hari atau jamnya.

Sistem energi atau nyawa sebaiknya tidak mengunci materi inti. Jika nantinya dipakai untuk membuat permainan lebih seru, batasi pada permainan tertentu dan selalu sediakan latihan biasa tanpa batas yang menghukum.

## Pengelolaan kursus dan mutu konten

Konten perlu melalui alur yang dapat dipahami pengelola: draf, pratinjau, peninjauan, diterbitkan, diperbarui, lalu diarsipkan bila tidak lagi tersedia. Perubahan kursus yang sudah dibeli tidak boleh diam-diam menghapus materi yang menjadi bagian pembelian; pengelola perlu melihat siapa yang terdampak dan memberi pemberitahuan bila isi atau masa akses berubah.

Setiap kursus sebaiknya memiliki sasaran belajar, tingkat atau kelompok usia, prasyarat bila ada, estimasi waktu, daftar materi, jenis evaluasi, dan identitas pembuat. Gambar, video, soal, dan bacaan harus memiliki sumber atau izin penggunaan yang jelas. Bila pengajar atau mitra dapat menerbitkan kursus, konten mereka harus ditinjau sebelum tampil di katalog.

## Kredit belajar dan pembayaran QRIS

### Alur yang disarankan

1. Pengguna memilih jumlah kredit yang ingin dibeli.
2. Aplikasi menampilkan harga dalam Rupiah dan jumlah kredit yang diterima.
3. Pengguna membayar melalui QRIS Midtrans.
4. Setelah Midtrans mengirim status pembayaran yang sah, kredit masuk ke saldo akun.
5. Pengguna memilih paket belajar dan membayar dengan kredit.
6. Aplikasi membuka akses paket sesuai masa akses yang ditampilkan.

### Aturan kredit yang disarankan

- Tampilkan harga Rupiah dan kredit secara berdampingan agar nilainya mudah dimengerti.
- Gunakan jumlah kredit bulat dan nilai tukar yang konsisten. Contohnya, jika kelak dipilih 1 kredit = Rp100, tampilkan aturan itu pada halaman pembelian. Angka ini hanya contoh dan belum menjadi harga final.
- Simpan setiap penambahan dan pemakaian kredit sebagai riwayat terpisah. Saldo tidak boleh hanya disimpan sebagai angka yang dapat diubah tanpa catatan.
- Kredit tidak dapat dikirim ke pengguna lain atau ditarik menjadi uang pada versi awal.
- Sebaiknya kredit tidak kedaluwarsa. Jika kebijakan berubah, masa berlaku harus dijelaskan sebelum pengguna membeli.
- Pemakaian kredit dan pembukaan paket dilakukan sebagai satu proses, agar kredit tidak terpotong jika akses paket gagal dibuka.
- Tampilkan bukti transaksi, status pembayaran, dan riwayat penggunaan kredit.

Top up memberi fleksibilitas, tetapi membuat pengguna melewati dua langkah: membeli kredit lalu membeli paket. Untuk mengurangi hambatan, sediakan tombol “Beli paket ini” yang secara otomatis menampilkan kekurangan kredit dan menawarkan top up yang sesuai.

### Menggunakan akun Midtrans yang sama

Untuk tahap awal, aplikasi dapat memakai akun Midtrans yang sama seperti modul self order Finance, sesuai rencana. Pengaturan aplikasi belajar tetap dipisahkan di sisi aplikasi: gunakan identitas pesanan dengan awalan khusus, catatan transaksi tersendiri, konfigurasi lingkungan sandbox atau produksi yang jelas, serta alamat notifikasi pembayaran yang mengarah ke aplikasi belajar.

Kredit baru diberikan setelah server menerima dan memeriksa notifikasi pembayaran. Halaman “pembayaran berhasil” yang dilihat pengguna tidak cukup untuk mengubah saldo. Notifikasi harus diperiksa keasliannya dan diproses satu kali meskipun Midtrans mengirim ulang pemberitahuan. Status transaksi juga perlu diperiksa sebelum dianggap berhasil. Rekomendasi ini mengikuti panduan resmi Midtrans tentang HTTPS, pemeriksaan signature, status transaksi, dan penanganan notifikasi berulang.

Sebelum digunakan, alur yang sudah berjalan di modul Finance perlu dipastikan cocok dengan kebutuhan top up: aplikasi belajar membutuhkan pesanan kredit, notifikasi untuk pesanan itu, dan pencatatan saldo yang terpisah dari transaksi penjualan di kasir.

Setiap pesanan perlu memiliki status yang mudah dipahami seperti menunggu pembayaran, berhasil, kedaluwarsa, atau dibatalkan. Pesanan QRIS yang belum dibayar tidak menambah kredit. Notifikasi duplikat tidak boleh menambah saldo dua kali. Transaksi yang gagal atau kedaluwarsa tetap muncul di riwayat, dan proses bantuan atau pengembalian dana harus memiliki catatan yang dapat ditelusuri.

## Halaman utama aplikasi

### Untuk anak

- Beranda belajar dan lanjutkan materi terakhir.
- Katalog kursus dan paket belajar.
- Peta atau jalur belajar.
- Latihan soal dan kuis.
- Permainan edukatif dan kartu belajar.
- XP, lencana, target, dan riwayat hasil.
- Sesi latihan khusus yang dikirim orang tua.

### Untuk orang tua

- Daftar akun anak yang ditautkan.
- Ringkasan kemajuan setiap anak.
- Riwayat nilai dan topik yang perlu diperkuat.
- Pembuat sesi latihan khusus.
- Dompet kredit, pembelian paket, dan transaksi.
- Pengaturan pengingat dan akses akun keluarga.

### Untuk pengelola

- Katalog kursus dan paket.
- Pengelolaan materi dan bank soal.
- Pengaturan permainan, harga kredit, batas gratis, dan masa akses.
- Daftar pengguna dan hubungan orang tua-anak.
- Draf kursus, peninjauan konten, dan riwayat perubahan paket.
- Transaksi QRIS, pengisian kredit, dan pembukaan paket.
- Laporan penggunaan, pencapaian, dan aktivitas belajar.

## Tahapan pengembangan

### Tahap 1: Versi awal yang bisa dipakai

- Daftar dan masuk sebagai orang tua atau anak.
- Tautkan akun orang tua dengan akun anak.
- Katalog paket dan beberapa materi contoh gratis.
- Bank soal, kuis pilihan ganda, pembahasan, dan sesi latihan.
- Pembuatan sesi latihan khusus oleh orang tua untuk anak.
- Riwayat hasil dan ringkasan kemajuan orang tua.
- Top up kredit dengan QRIS Midtrans dan pembukaan paket berbayar.
- Panel pengelola untuk konten, paket, harga, dan transaksi.
- Tampilan dasar yang menyesuaikan usia: anak, remaja, dan dewasa.
- Alur status pembayaran dan riwayat kredit yang dapat ditelusuri.
- Alur draf, pratinjau, dan penerbitan kursus oleh pengelola.

### Tahap 2: Membuat belajar lebih menarik

- XP, lencana, streak, dan target harian.
- Kartu belajar, kuis bacaan, dan permainan mini.
- Rekomendasi latihan berdasarkan hasil sebelumnya.
- Laporan kemajuan yang lebih rinci.
- Pembelian paket untuk beberapa anak sekaligus.
- Misi belajar, avatar, dan hiasan pencapaian.
- Saran tingkat kesulitan berdasarkan hasil latihan.
- Susun kata, benar atau salah, dan penukaran poin dengan hadiah non-tunai.
- Tes pemetaan singkat untuk menyarankan kursus atau latihan awal yang sesuai.

### Tahap 3: Memperluas pilihan belajar

- Tantangan bersama teman atau mode Battle.
- Langganan bulanan atau tahunan.
- Sertifikat penyelesaian.
- Pengajar atau mitra yang dapat menerbitkan kursus.
- Aplikasi yang dapat dipasang di layar utama ponsel dan notifikasi yang lebih lengkap.
- Turnamen terjadwal dengan aturan, rentang usia, dan papan peringkat yang dapat dipilih atau disembunyikan pengguna.
- Kompetisi terjadwal, pendaftaran peserta, dan tinjauan hasil.
- Pengajar atau mitra dapat menerbitkan kursus setelah proses peninjauan tersedia.

## Hal yang perlu dijaga

- Kesalahan saat latihan harus menjadi kesempatan belajar dan selalu disertai penjelasan yang sesuai.
- Orang tua hanya boleh melihat anak yang sudah ditautkan ke akunnya.
- Anak tidak boleh bisa membuka soal pribadi keluarga lain.
- Saldo kredit, pembayaran, dan akses paket harus memiliki riwayat yang dapat diperiksa.
- Materi berbayar harus menyebutkan isi dan masa akses sebelum dibeli.
- Papan peringkat, profil publik, serta interaksi antar pengguna harus dapat dikendalikan dan disesuaikan dengan usia.
- Data anak hanya digunakan untuk menjalankan layanan belajar dan laporan yang dibutuhkan keluarga.

## Pelengkap konsep yang disarankan

- Ukur kemajuan dari penguasaan materi, konsistensi, dan topik yang perlu diulang, bukan nilai akhir saja. Sediakan pengulangan berkala agar materi yang mulai terlupa bisa dipelajari lagi.
- Rancang materi hemat kuota: teks tetap menjadi dasar, gambar dikompres, video tidak diputar otomatis, dan progres latihan disimpan sesering yang aman. Mode luring penuh dapat menjadi pengembangan lanjutan.
- Sediakan aksesibilitas sejak awal: navigasi papan ketik, kontras yang cukup, teks alternatif gambar, teks video bila tersedia, serta pengurangan gerak dan suara.
- Tetapkan aturan pelaporan, pemblokiran, moderasi, dan batas komunikasi sebelum membuka tantangan publik, komentar, atau papan peringkat untuk anak.
- Setiap materi harus memiliki sumber, pemilik hak, kelompok usia, versi, dan catatan perubahan. Perubahan materi tidak boleh menghapus riwayat nilai atau bukti pembelian peserta.
- Untuk langganan, jelaskan perpanjangan, tanggal tagih, cara berhenti, masa akses setelah berhenti, dan penanganan pengembalian dana sebelum pembayaran.
- Siapkan pemantauan operasional: kegagalan pembayaran, notifikasi Midtrans tertunda, lonjakan galat, dan pencadangan basis data harus terlihat oleh pengelola.

## Saran keputusan untuk memulai

1. Mulai dengan satu kelompok materi yang mudah disiapkan, misalnya latihan sekolah dasar atau satu kategori keterampilan umum.
2. Gunakan bank soal dan pembuat sesi latihan Pustaka sebagai fondasi utama.
3. Tampilkan laporan dasar kepada orang tua sejak awal; jadikan pembuatan soal atau sesi latihan pribadi sebagai manfaat paket berbayar.
4. Pertahankan model kredit, tetapi buat pembelian paket mudah dengan menawarkan top up sesuai kekurangan saldo.
5. Tunda fitur kompetisi langsung sampai pengalaman belajar pribadi dan transaksi sudah berjalan stabil.
6. Tetapkan batas gratis, nilai kredit, harga paket, dan masa akses melalui pengaturan agar mudah disesuaikan.
7. Sepakati batas usia tiap kelompok pengguna, kebijakan privasi anak, hak penggunaan materi, serta aturan refund sebelum membuka layanan ke publik.

## Acuan produk dan pembayaran

- Modul Pustaka yang ditinjau: `docs/learn_module_roadmap.md`, `docs/quiz_engine.sql`, modul `Quiz_sessions`, `Quiz_play`, permainan, flashcard, laporan, dan kemajuan belajar.
- Pemetaan teknis sumber ke skema, syarat adopsi, dan urutan integrasi Study: [Peta Adopsi Modul Pustaka](PETA_ADOPSI_MODUL_PUSTAKA.md).
- Pengaturan pembayaran yang ditinjau: `finance/application/views/pos/self_order_settings.php` dan `finance/application/models/Pos_model.php`.
- Acuan tampilan dan coding untuk setiap halaman: [Standar Antarmuka dan Coding Aplikasi Belajar](STANDAR_UI_DAN_CODING.md).
- [Panduan resmi Midtrans untuk notifikasi pembayaran](https://docs.midtrans.com/docs/https-notification-webhooks).
- [Panduan resmi Midtrans untuk memproses notifikasi secara aman](https://docs.midtrans.com/reference/best-practices-to-handle-notification).

Nama aplikasi, jumlah kredit, harga top up, batas fitur gratis, masa akses paket, dan kelompok materi pertama masih perlu ditetapkan sebelum implementasi.
