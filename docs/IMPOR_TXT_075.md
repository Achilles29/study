# Impor TXT naskah dan pemeriksaan kesiapan — 075

**Pembaruan076:** batas10 gambar/4MiB dan satu gambar per soal di riwayat ini sudah diganti500 lampiran/32MiB serta `GAMBAR: satu.png | dua.png`. Lihat panduan halaman impor dan [rilis076](GAMBAR_IMPOR_076.md).

20 Oktober 2026. Perbaikan terbatas untuk `/kelola/bank-soal/impor`; tidak ada migrasi, reset ID, impor materi produksi atau perubahan pembayaran. Skema tetap 51.

## Penyebab dan hasil

Pengimpor 068 hanya membaca label `MAPEL:`, `SOAL:`, `KUNCI:` dan pemisah `---`. TXT naskah Pustaka (`1.`, `a.`, blok `KUNCI JAWABAN`) ditolak dengan pesan “Gunakan label pada template TXT.” Perbaikan menambahkan deteksi naskah tanpa menghapus dukungan empat format lama.

- [x] Judul awal dipisahkan dari soal; nomor dan baris asal ditampilkan. Pertanyaan/pilihan multiline, BOM UTF-8, CRLF, variasi `1)`/`a)`/`(a)`/`a:` dan huruf besar/kecil didukung. Teks biasa seperti “bagian”, “mudah”, “bukan”, “kecuali” dan “kunci jawaban” tidak dihapus.
- [x] Mapel, kelas dan tingkat dipilih pada formulir naskah. “Level 1” tidak dianggap otomatis kelas 1. Header `BAGIAN A - MUDAH` menjadi dasar; SEDANG/SULIT menjadi lanjutan, sesuai penjelasan UI. Format berlabel/tabel memakai klasifikasi dari berkas.
- [x] Analisis per nomor: pilihan kosong/berulang/identik, kunci kosong/`-`/tidak valid, nomor/kunci ganda, kunci tanpa soal, jumlah judul berbeda, metadata tidak tersedia, serta gambar yang belum dilampirkan. Kunci tidak pernah ditebak atau diubah otomatis.
- [x] Jika ada error, seluruh batch ditahan: HTTP422 dengan rincian semua baris, tanpa token pratinjau dan tanpa soal baru. Isian klasifikasi/sumber tetap terlihat; berkas dan lampiran dipilih ulang setelah diperbaiki. Tidak ada impor sebagian otomatis.
- [x] Jika struktur lengkap, pratinjau lama tetap dipakai: token privat dua jam, pagination 20 soal, konfirmasi eksplisit dan pilihan draft/aktif, dedup memakai ID existing, transaksi atomik/replay. Nomor asli dan catatan tampil di pratinjau.
- [x] Pembahasan bank soal sekarang **opsional**, menggantikan ketentuan wajib pada catatan 068–070. Kosong diberi catatan; tidak dibuat otomatis. Editor bank mengizinkan kosong; hasil latihan menampilkan “Pembahasan belum tersedia” sesudah penilaian. Validator materi/kuis kursus tidak diubah.
- [x] Gambar naskah memakai `GAMBAR: nama.png` sesudah opsi dan lampiran PNG/JPEG terpisah. Bila opsi berupa gambar, gunakan satu gambar gabungan soal + opsi berlabel A–D. Rujukan eksplisit PDF asli/gambar kiri-kanan atau opsi `Gambar A` tanpa lampiran memblokir impor. Penyebutan gambar biasa tanpa lampiran memberi catatan untuk review manusia; tidak mengklaim deteksi semua kebutuhan gambar.

## Cara menggunakan

1. Buka `/kelola/bank-soal/impor`, unduh **TXT naskah (Pustaka)** (`/kelola/bank-soal/template/naskah`). Tautan TXT berlabel lama tetap `/kelola/bank-soal/template/txt`.
2. Isi judul opsional, soal bernomor, empat opsi A–D, serta kunci di bagian akhir. Pembahasan opsional memakai `PEMBAHASAN:` sesudah pilihan. Baris isi yang menyerupai nomor/opsi/label dapat diawali `\` agar dibaca literal.
3. Pilih mapel, kelas, tingkat, hak penggunaan dan sumber/izin; unggah TXT serta gambar jika diperlukan. Klik **Periksa berkas**.
4. Perbaiki semua error. “Struktur lengkap” bukan pemeriksaan kebenaran materi, jawaban atau pemberian izin penggunaan. Tinjau catatan dan seluruh soal sebelum konfirmasi **Simpan ke bank**.

Batas: 500 soal, 3 MiB dokumen, 20.000 baris TXT naskah, 500 kunci, empat opsi. Lampiran tetap maksimal 10, total 4 MiB. Opsi E ditolak dengan penjelasan dan tetap terlihat dalam laporan, bukan dibuang diam-diam. Belum ada penyuntingan langsung isi berkas di halaman analisis atau impor sebagian. Penyimpanan/garbage collection media mengikuti keterbatasan 070; jangan menghapus media privat tanpa rekonsiliasi referensi.

## Hasil terhadap contoh pemilik

Fixture internal `scripts/fixtures/import075-user.txt` menyimpan 40 soal yang diberikan pemilik untuk regresi, **bukan konten yang diterbitkan**. Semua 40 terbaca; 36 lolos struktur baris. Nomor **5, 7, 17 dan 20** terblokir karena kunci `-` serta gambar yang dirujuk belum ada. Nomor 33 mendapat catatan gambar; semua soal mendapat catatan pembahasan kosong. Kunci lainnya dipertahankan sesuai sumber, bukan disahkan benar secara matematika. Soal lomba tetap perlu review isi dan hak penggunaan sebelum diterbitkan.

## Bukti pengujian dan deployment

- PHP 8.4.21 Linux, lint tujuh file runtime berubah dan test PHP.
- `scripts/test-import075.php`: **44 pemeriksaan** tanpa DB/network, termasuk reproduksi kegagalan parser lama, contoh lengkap 40 soal, teks/multiline, metadata, duplikat, gambar, batas 500, invalid UTF-8 dan roundtrip empat format lama.
- `scripts/test-import075.mjs`, hanya `study_qa_monitor_sso039`: unggah multipart contoh40 -> HTTP422/36 lengkap/4 bermasalah, jumlah soal/bank/batch tetap; form terisi kembali; parent/anon ditolak; valid upload belum commit; CSRF/konfirmasi; source numbering/XSS; dedup/replay; lampiran privat; impor ke Simulasi Ujian menggunakan ID bank yang sama; peserta menjawab benar -> 100%, pembahasan kosong ditampilkan jujur, kunci tidak bocor ketika aktif. Browser1280/360, tidak overflow/JSerror, rincian dapat dibuka keyboard.
- Regresi `test-bank068.php` dan `.mjs` lulus: empat format, gambar, izin/CSRF/stale/owner, dedup lintas urutan/kelas, ID opsi tetap, audit fault rollback, replay, XXE/traversal. `test-practice-unsaved-view.php`:20 render lulus, termasuk graded dan perlindungan kunci sebelum selesai.
- Tes browser awal075 salah memilih tombol Keluar melalui selector terlalu umum; selector dipersempit ke form impor, lalu seluruh tes terkait lulus. Bukan perubahan aplikasi untuk meluluskan tes.
- Tujuh runtime file dan tiga file test/fixture produksi cocok byte dengan staging teruji. PHP-FPM **Project Pintar saja** di-reload, aktif. HEAD produksi: impor/template naskah303 ke login; fixture internal404. Uji admin multipart dijalankan di QA, tidak memakai sandi/sesi admin nyata.
- Checksum tujuh tabel produksi tetap: questions3963196383, options3027569709, bank0, scopes0, users4191112837, payment_orders3709692070, wallets713850174. Tidak ada soal contoh dimasukkan ke `db_study`. Finance/Pustaka dan callback tidak disentuh.

Cadangan: `/www/backup/projectpintar-import075-AfOxLG1I` (SQL sebelum rilis, source sebelum/sesudah, screenshot QA). Staging `/www/study-private/import075-hCPs75yD` bukan dependensi runtime; file produksi tetap di `/www/wwwroot/study`. Windows tidak diuji ulang untuk perbaikan server ini. UAT pemilik tetap diperlukan; ini bukan klaim seluruh roadmap selesai atau semua materi benar.
