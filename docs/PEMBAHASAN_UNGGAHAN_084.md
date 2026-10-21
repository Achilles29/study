# Pembahasan unggahan KMSI — 084

21 Oktober 2026. Permintaan pemilik: buat pembahasan untuk soal hasil unggahan yang masih kosong. Sumber aktual: 30 soal bank ID729–758, Matematika SD Kelas2; naskah `docs/testgambar/KMSI 2025 D - MTK LEVEL 1.txt` dan enam gambar privat yang benar-benar terhubung pada record produksi. Bukan menambahkan soal baru atau mengisi ulang materi kursus.

## Hasil

- **29 pembahasan disimpan** pada `st_questions.explanation`; content_version1→2 dan updated_at diperbarui. Setiap pembaruan mempunyai audit `bank.explanation_added`, correlation_id `bank-explanations084`, actor NULL dengan asal operasi bantuan asisten atas permintaan pemilik. Tidak menyamar sebagai login admin atau mengubah reviewer.
- Nomor sumber8 / ID736 **tidak diperbarui** karena jawaban tidak tunggal. Pembahasan analisis disimpan sebagai draft pada [dataset084](data/pembahasan_kmsi_2025_d_084.json), dengan `ready=false`.
- ID, teks soal, opsi, kunci, gambar, klasifikasi, status published, sumber/hak penggunaan dan fingerprint deduplikasi dipertahankan. Tidak mereset data, memperbarui nilai, mengubah kuota atau menulis ke Finance/Pustaka.
- Snapshot sesi existing dipertahankan. **Sesi baru mengambil pembahasan baru; sesi lama tetap memakai salinan ketika dimulai**, termasuk bila pembahasannya saat itu kosong. Tidak melakukan backfill histori diam-diam.
- Pembahasan memakai isi jawaban, bukan huruf A/B/C/D, supaya tetap cocok ketika opsi diacak. Langkah disusun dengan bahasa sederhana, operasi hitung dan pemeriksaan balik. Asumsi pembagian sama rata disebutkan pada soal yang redaksinya belum tegas.

## Temuan yang memerlukan koreksi terpisah

1. **Nomor8 / ID736:** A (Hanik–Erin–Ghaitsa–Rosa–Putri) dan B (Hanik–Erin–Rosa–Ghaitsa–Putri) sama-sama memenuhi petunjuk. Yang pasti hanya Hanik pada posisi pertama. Usulan paling sesuai pertanyaan: opsi posisi pertama/kedua/ketiga/keempat. Alternatif untuk mempertahankan opsi urutan: ubah pertanyaan menjadi urutan lengkap dan tambahkan petunjuk Rosa di depan Ghaitsa. Menunggu persetujuan; kunci B existing dan nilai historis tidak diubah.
2. **Nomor17 / ID745:** gambar menunjukkan K7 + 3L =64; K2/L7, hasil K+L=9. Opsi benar tersimpan `9 6 4`, dengan `6 4` sisa tata letak hasil64. Pembahasan benar sudah diisi, opsi belum dirapikan.
3. **Nomor18 / ID746:** gambar 1M5−8N=66 memberi M5/N9, jumlah14. Opsi benar14 utuh; pengecoh terakhir `15 6 6` masih memuat sisa tata letak. Pembahasan sudah diisi, opsi belum dirapikan.
4. Nomor11 memakai nama Ali lalu Ari; pembahasan hanya menjelaskan jumlah masker tanpa mengarang dua orang. Nomor12/30 memerlukan asumsi pembagian merata; asumsi dinyatakan dalam pembahasan. Tidak mengubah redaksi sumber/hak materi dalam pekerjaan ini.

**Koreksi catatan komunikasi:** pemeriksaan awal sempat menyebut nomor6 bernilai8. Pemeriksaan ulang gambar dan enumerasi persamaan membuktikan segitiga2, segilima9 (3×2+2×9=24; 2+2×9=20). Catatan awal sudah dikoreksi kepada pemilik; kunci9 memang benar dan pembahasan9 yang disimpan.

## Verifikasi dan backup

- Backup privat `/www/backup/projectpintar-explanations084-HWN90HJM/before.json`: 30 record lengkap, opsi/scope, hash enam gambar, sumber/dataset, dan hash snapshot historis terkait. `after.json` menyimpan keadaan setelah transaksi. File baru dibuat eksklusif, privat; helper `apply084.php` memakai kredensial runtime environment tanpa mencetak secret.
- Preflight read-only: target tepat `db_study`/3306, ID729–758/version1/kosong/published, kunci cocok, dataset/sumber/gambar utuh,29 allowlisted dan satu ditahan. Pemeriksaan hitung termasuk enumerasi solusi gambar6/7/17/18, kemunculan digit3, dan pembuktian dua opsi valid pada nomor8.
- Pembaruan satu transaksi dengan lock `st_bank_guard`, pemeriksaan perubahan bersamaan, update kolom terbatas serta29 audit; kegagalan membatalkan transaksi. Eksekusi ulang ditolak bila sudah diterapkan. Verifikasi ulang29 teks persis/version2; seluruh kolom lain, opsi/scope dan snapshot sebelumnya identik.
- `scripts/test-explanations084.php`: 58 render aktif/graded untuk29 teks, tidak bocor sebelum selesai, seluruh paragraf tampil setelah graded, panjang/UTF-8/ID/aritmetika. Memakai helper20 render regresi existing. Ini uji render tanpa browser/HTTP, bukan klaim membuat attempt produksi atau menguji login admin.

Cek melalui `/kelola/bank-soal/{id}` atau mulai sesi baru di `/latihan/mandiri`, selesaikan, lalu **Lihat pembahasan**. Tidak perlu mengunggah ulang file; mengunggah soal identik tidak otomatis menimpa pembahasan existing.
