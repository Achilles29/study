# Pengelolaan susunan draf kursus — 15 Oktober 2026

Halaman `kelola/kursus/{id}/susunan` menyediakan tambah/ubah bagian serta pemindahan/urutan pelajaran dalam satu kursus draf. Editor pemilik dan admin sistem mendapat akses; status selain draf ditolak. Data screenshot adalah fixture sintetis yang sudah dibersihkan.

## Pemeriksaan visual

- [Ponsel 360 px](course-structure-360.png): form bagian dan pelajaran bertumpuk, label/tombol tetap terlihat, pagination tersedia di bawah.
- [Desktop 1280 px](course-structure-1280.png): pengelolaan bagian di kiri, pelajaran di kanan; tidak ada tumpang-tindih.

Kedua capture ditinjau untuk tata letak. Browser juga mengukur 768 px. Menu navigasi dan detail “Tambah bagian baru” dibuka; tiga lebar lulus tanpa overflow, masalah label dasar atau fokus awal skip-link. Ini bukan bukti keyboard-only lengkap, zoom 200%, pembaca layar, UAT atau konten nyata.

## Bukti perilaku

`scripts/course-structure-check.ps1` memeriksa akun/peran/kepemilikan, alias/controller langsung, metode/CSRF, tambah/ubah/pindah, versi kursus/pelajaran, form usang/replay, no-op, escaping/Unicode, ID bagian/pelajaran lintas kursus, input array/negatif/ekstrem, pagination 10+1 dan batas 100 bagian. Model diuji langsung untuk aktor nonaktif, tanpa peran dan pemilik lain. SQL error pada penulisan audit membuktikan rollback perubahan bagian/kursus/versi/timestamp dan tidak menambah audit. Status review/terbit/arsip ditolak. Katalog serta tautan pelajaran berikutnya mengikuti urutan bagian yang sama, termasuk nomor urut sama. Slug dan body pelajaran tidak berubah ketika dipindah.

Run dengan Chrome lulus setelah penguji diperbaiki; penambahan tes Unicode kemudian dijalankan lagi tanpa Chrome dan lulus. Regresi admin/progres, 376 penolakan metode, lint 315 berkas PHP pada 8.0/8.1/8.5, PHPCS/naming dan smoke lokal lulus. HTTP/browser memakai Apache XAMPP PHP 8.0; CLI model rollback memakai PHP 8.1. Tidak ada migration baru: versi 12/32 tabel dipertahankan. Suite masuk `qa-local.ps1`, tetapi full QA/Nginx/backup/race tidak diulang.

## Kegagalan yang ditemukan saat pengujian

Pemeriksa aksesibilitas awal memakai `innerText` untuk tombol di dalam `details` tertutup. Tombol tersebut tidak tampil dan terbaca seolah tanpa nama. Pemeriksa kini membedakan isi details tertutup dari summary dan isi details terbuka; kontrol negatif/positif disertakan. UI juga diuji dengan details dibuka, bukan hanya melewati tombol tersembunyi.

Tes batas 100 bagian sempat melaporkan koneksi terputus. Log Apache membuktikan respons 400 sebesar 82.443 byte, sedangkan `HttpWebRequest.DefaultMaximumErrorResponseLength` pada proses penguji bernilai 64 KiB. Menonaktifkan keep-alive tidak menyelesaikannya. Batas isi error penguji kemudian diubah sementara menjadi 1 MiB dan selalu dikembalikan dalam `finally`; suite lulus tanpa retry POST atau perubahan aturan aplikasi. Nama pengaturan tersedia dalam [sumber Microsoft HttpWebRequest](https://github.com/microsoft/referencesource/blob/main/System/net/System/Net/HttpWebRequest.cs).

## Mengulang

### Pemeriksaan lanjutan tanpa mengganti screenshot

Sesudah perbaikan versi lintas editor, konteks bagian/pagination dan penanganan hasil database yang belum pasti, suite HTTP/CLI serta browser tiga lebar dijalankan ulang dan lulus. Dua gambar di atas tetap capture awal; tidak dibuat atau diklaim sebagai screenshot baru. Bukti tambahan mencakup 15 probe kegagalan transaksi, dua rollback gagal yang dibersihkan oleh harness, serta tes renderer tanpa query sesudah mutasi. Hasil commit/rollback yang belum pasti meminta GET untuk memeriksa ulang, tanpa retry POST. Rincian terbaru dan batas pengujian ada pada Tahap 14 [roadmap](../../ROADMAP_PENGEMBANGAN.md).

```powershell
& scripts/course-structure-check.ps1 -Php81Path C:\tools\php81-study\php.exe -MysqlPath C:\xampp\mysql\bin\mysql.exe -BaseUrl http://localhost/study/index.php/ -ChromePath 'C:\Program Files\Google\Chrome\Application\chrome.exe'
```

Pengujian hanya untuk loopback dan menggunakan nama fixture unik. `-CaptureScreenshots` memakai direktori baseline tetap dan tidak menimpa gambar yang sudah ada. Penghapusan bagian/pelajaran, drag-and-drop, revisi konten terbit, race paralel, Linux/MySQL target dan UAT belum termasuk fitur/bukti ini. Temuan fokus zoom halaman daftar tetap terpisah dan belum ditutup.
