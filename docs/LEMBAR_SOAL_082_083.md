# Navigasi mobile dan halaman Nilai — 082/083

21 Oktober 2026. Perubahan UI pada instalasi existing; produksi tetap `db_study` di 3306, schema 52. Tidak ada migrasi, reset data/ID, perubahan skor, kuota, kredit, pembayaran atau source Pustaka/Finance.

## Perubahan dan cara mencoba

- `/latihan/mandiri`, `/latihan/kursus/{id}`, dan pengaturan baru `/kelola/simulasi-ujian/0`: pilihan pertama/default **Ledger**, kedua **Per soal**. Pengaturan template yang sudah disimpan dan snapshot sesi berjalan tidak ditimpa.
- Mandiri, kursus dan simulasi memakai navigasi bersama. Mobile: bar sticky di bawah navbar, nomor satu baris yang dapat digeser horizontal, status centang/lingkaran, jumlah dijawab, disclosure untuk meringkas. Desktop: sidebar sticky. Tinggi navbar diukur, termasuk bar donasi orang tua dan buka/tutup menu; offset gulir soal menyesuaikan. Nomor aktif mengikuti gulir manual, sedangkan navigasi eksplisit dipertahankan selama animasi. Tombol pengumpulan berada di atas lapisan navigasi jika keduanya bertemu.
- Selesai latihan atau membuka hasil dari `/latihan`: `/latihan/percobaan/{id}` sekarang menampilkan **Nilai latihanmu**, bukan langsung seluruh pembahasan. Ada nilai tersimpan /100, jumlah benar/salah/kosong, jumlah dikerjakan, rumus, waktu selesai dan rentang pengerjaan termasuk jeda. Tidak menyimpulkan lulus/gagal tanpa aturan produk.
- Rumus existing tetap benar / seluruh soal ×100, bobot sama, salah/kosong nol tanpa penalti. Tampilan mengikuti nilai tersimpan dua desimal, bukan menghitung ulang histori. Rentang waktu bukan klaim waktu aktif belajar.
- Tombol **Lihat pembahasan** menuju `/latihan/percobaan/{id}/pembahasan`, hanya GET/HEAD, hanya pemilik dan hanya setelah graded. Pembahasan memiliki tombol kembali ke nilai. Tombol latihan lagi/pilih simulasi membuka formulir/katalog existing; tidak otomatis membuat sesi atau memotong kredit. Riwayat dan Arena tersedia.
- Halaman hasil memakai hero kontras tinggi. Warna paragraf hero lembar soal/pembahasan juga dikoreksi agar tidak ditimpa warna kartu umum. Laporan keluarga existing tetap memakai jalur izin/read-only-nya, tidak dialihkan ke URL milik peserta.

## Bukti pengujian

QA terisolasi `study_qa_monitor_sso039` pada socket instance lama 3308; bukan database produksi. Staging existing `/www/study-private/sheet080-SZ8RMFqb` hanya untuk uji, bukan dependensi runtime.

- `scripts/test-sheet082.mjs`: default/order formulir Mandiri/kursus/admin, template lama single tetap; browser 360/1280, ketiga jenis latihan, per-soal/ledger, scroll manual/jump ke nomor terakhir, keyboard, autosave/status, gulir setelah jawaban, collapse navigasi, buka/tutup navbar, orang tua dengan bar donasi. Tidak ada overflow horizontal halaman atau galat JavaScript. Uji menemukan dan memperbaiki tumpang tindih navigasi terhadap konfirmasi submit pada desktop.
- `scripts/test-result083.mjs`: Mandiri campuran (33,33), kursus seluruhnya kosong (0), simulasi seluruhnya benar (100); nilai/rincian cocok database QA; halaman Nilai tidak memuat lembar jawaban, tombol pembahasan/kembali/reload, desktop/mobile, warna teks hero, keyboard. Akun lain 404 pada hasil maupun pembahasan; pembahasan saat aktif 404; POST pembahasan dengan CSRF sah 405 dan skor tetap. Tidak ada galat JavaScript.
- `test-practice-read-access.php`: 85 pemeriksaan izin; `test-practice-unsaved-view.php`: 20 render dan escaping/read-only; `test-practice-scoring.php`: nol/sempurna/pembulatan/input invalid. Lint PHP dan pemeriksaan sintaks JS file terkait lulus.
- Test gambar historis `test-media077.mjs` diarahkan ke URL pembahasan baru untuk pemeriksaan setelah graded; hanya penyesuaian URL, bukan klaim mengulang seluruh regresi media. Script rilis lama lainnya tetap perlu dibaca menurut kontrak rilisnya, bukan dianggap seluruhnya cocok dengan produk terbaru.
- Tidak memakai akun produksi untuk pengujian interaktif, tidak menyisipkan soal/transaksi/attempt dummy produksi. Tidak mengulang seluruh pengujian pembayaran karena jalurnya tidak berubah.

## Deployment

Backup file sebelum 082 dan sebelum 083: `/www/backup/projectpintar-sheet082-FEKoZiUJ/` (subfolder `after082` menyimpan versi sebelum halaman Nilai). File diuji di staging, dibandingkan dengan sumber sebelum ditimpa, lalu disalin terarah. FPM Study direload/aktif; login HTTP200, aset HTTP200 dan URL hasil/pembahasan anonim mengarah login. File produksi sesuai staging. Snapshot DB/config dari pemindahan 081 tidak diubah.

Untuk mencoba default baru, mulai sesi baru. Sesi yang sedang berjalan tetap memakai mode yang dipilih saat mulai. Hasil lama yang sudah graded dapat langsung memakai halaman Nilai baru tanpa penilaian ulang.
