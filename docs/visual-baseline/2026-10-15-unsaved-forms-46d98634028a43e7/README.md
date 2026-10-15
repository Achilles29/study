# Capture awal — bukan baseline final

Run pertama pada 15 Oktober 2026 lulus pemeriksaan HTTP, 12 kombinasi formulir/viewport Chrome, dua GET editor tanpa JavaScript, serta dua POST native yang ditolak validasi server. Tiga gambar sempat tersimpan. Namun proses penguji berakhir **exit code 1** saat teardown dengan pesan `Chrome did not stop`; karena itu folder ini bukan bukti run selesai atau baseline final.

Penyebabnya pada penguji: pemeriksaan proses hanya memakai `exitCode === null`, padahal proses yang dihentikan melalui sinyal dapat sudah selesai dengan `signalCode` terisi. Pemeriksaan lifecycle diperbaiki menggunakan event keluar dan kedua properti tersebut. [Run kedua yang selesai dan ditinjau](../2026-10-15-unsaved-forms-ba3d268359454894/README.md) adalah baseline yang digunakan.

Fixture database milik run pertama dibersihkan tepat oleh blok `finally`. Catatan kegagalan login yang tidak teratribusi tidak dihapus. Profil sementara `C:\Users\fuadm\AppData\Local\Temp\study-unsaved-browser-E3TXtk` masih dipertahankan: pemeriksaan setelah run tidak menemukan Chrome yang menggunakannya, tetapi cleanup terarah ditolak kebijakan lingkungan. Tidak ada percobaan ulang atau penghapusan alternatif. Run kedua hanya membersihkan profil baru miliknya sendiri.

HTTP pada run ini memakai Apache XAMPP/PHP 8.0.30; CLI PHP 8.1 dipakai untuk hash kredensial fixture. Hasil fungsional ini bukan full QA, bukti Linux/Nginx/PHP 8.1 HTTP, UAT, BFCache nyata, atau jaminan dialog meninggalkan halaman pada semua browser. Gambar dan catatan dipertahankan sebagai jejak iterasi, bukan sebagai klaim cleanup penuh.
