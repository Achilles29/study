# Baseline formulir dengan perubahan belum tersimpan

## Status dan lingkungan

Run kedua pada 15 Oktober 2026 selesai dengan **exit code 0**. Penguji HTTP, Chrome, pemeriksaan tanpa JavaScript, dan cleanup fixture/profil baru lulus. Tiga gambar di folder ini ditinjau langsung: teks dan indikator terbaca, tanpa overflow horizontal dokumen pada pengukuran yang dijalankan.

HTTP memakai Apache XAMPP yang sudah berjalan di `http://localhost/study/index.php/`, dengan PHP 8.0.30. CLI `C:\tools\php81-study\php.exe` dipakai oleh harness untuk membuat hash kata sandi fixture; ini **bukan** bukti HTTP PHP 8.1. Tidak ada server PHP tambahan, Nginx, atau pembayaran yang dijalankan.

## Gambar yang ditinjau

- [Edit kursus, perubahan belum tersimpan, 360 px](course-edit-dirty-360.png).
- [Edit kursus, perubahan belum tersimpan, 1280 px](course-edit-dirty-1280.png).
- [Edit pelajaran setelah validasi server menolak POST, 1280 px](lesson-failed-post-1280.png).

Seluruh nama, teks bertanda markup, dan konten merupakan fixture sintetis. Markup sengaja ditampilkan sebagai teks untuk memeriksa escaping; bukan contoh materi belajar final. Screenshot bukan bukti bahwa draf telah disimpan.

## Hasil pengujian

`scripts/unsaved-form-check.ps1` memeriksa empat formulir: tambah/edit kursus serta tambah/edit pelajaran. GET mempunyai penanda awal bersih; POST dengan durasi tidak valid mempertahankan judul, isi, sumber, dan checkbox yang tidak dipilih, lalu menandai formulir belum tersimpan. Karakter `&`, tanda kutip, dan tag sintetis tetap bernilai sama setelah HTML didekode sekali, termasuk pada GET editor; tidak ada double-escaping yang mengubah isian.

POST pelajaran dengan revisi lama mendapat 409, mempertahankan input/revisi, dan tetap menonaktifkan tombol simpan sambil menawarkan muat ulang. Input array pada editor kursus mendapat halaman 400 generik tanpa warning. Halaman yang tidak terkait tidak memuat penanda/skrip perubahan draf. Digest konten fixture dan audit yang diperiksa tidak berubah akibat GET, validasi gagal, atau konflik tersebut.

`scripts/unsaved-form-browser-check.mjs` menjalankan empat formulir pada lebar 360, 768, dan 1280 px, total 12 kombinasi. Pada DOM halaman yang sebenarnya, penguji membuktikan:

- Status awal bersih; perubahan input/checkbox membuatnya belum tersimpan; mengembalikan nilainya membuatnya bersih kembali.
- Perubahan field tersembunyi tidak dihitung sebagai perubahan materi.
- Submit yang dibatalkan dan submit yang ditolak validasi native tetap menjaga status serta peringatan perubahan.
- Indikator terlihat setelah peningkatan JavaScript, memakai `role="status"` dan `aria-live="polite"`.
- Dua editor yang dimuat dengan eksekusi JavaScript halaman dinonaktifkan tetap mempunyai form POST native, CSRF, input, tombol simpan dan petunjuk simpan manual. Indikator dinamis tetap tersembunyi; halaman tidak mengaku bersih secara keliru. Ini pemeriksaan GET/DOM tanpa JavaScript, bukan pengujian submit tanpa JavaScript melalui Chrome.
- Dua POST native nyata, masing-masing pada editor kursus dan pelajaran, memakai judul satu karakter yang lolos validasi browser tetapi ditolak aturan minimum server. Keduanya berpindah tanpa dialog peringatan meninggalkan halaman, mempertahankan input, dan kembali dengan keadaan awal belum tersimpan.

Perilaku kondisional `beforeunload` juga diperiksa memakai event sintetis yang dapat dibatalkan. Itu tidak menjamin dialog native akan selalu muncul pada setiap browser, perangkat, tab yang ditutup paksa, atau sistem operasi.

## Cleanup dan run sebelumnya

Fixture editor, kursus, bagian, pelajaran, peran, serta audit yang terkait dibersihkan dengan identitas tepat milik run, bukan wildcard. Catatan kegagalan login yang tidak teratribusi ke penguji tidak dihapus. Profil Chrome baru milik run kedua juga dibersihkan setelah proses selesai.

[Run pertama](../2026-10-15-unsaved-forms-46d98634028a43e7/README.md) lulus pemeriksaan fungsional tetapi berakhir gagal saat teardown. Penguji sebelumnya hanya memeriksa `exitCode`, sehingga proses yang berhenti melalui sinyal dapat keliru dianggap masih hidup. Perbaikan memakai event keluar serta `exitCode`/`signalCode` sebelum cleanup; run kedua ini membuktikan jalur teardown yang diperbaiki.

Profil lama `C:\Users\fuadm\AppData\Local\Temp\study-unsaved-browser-E3TXtk` tetap dipertahankan. Pemeriksaan setelah run pertama tidak menemukan proses Chrome yang masih memakai profil tersebut, tetapi upaya cleanup terarah ditolak oleh kebijakan lingkungan. Tidak dilakukan percobaan ulang atau cara penghapusan alternatif. Cleanup run kedua tidak menyentuh profil lama ini.

## Mengulang pengujian

Dari direktori proyek, gunakan server lokal yang sudah berjalan dan jangan menjalankan suite DB lain bersamaan, karena digest audit yang dibandingkan mencakup audit aplikasi.

```powershell
& scripts/unsaved-form-check.ps1 -Php81Path C:\tools\php81-study\php.exe -MysqlPath C:\xampp\mysql\bin\mysql.exe -BaseUrl http://localhost/study/index.php/ -ChromePath 'C:\Program Files\Google\Chrome\Application\chrome.exe' -CaptureScreenshots
```

Setiap capture membuat folder baru dengan tanggal/suffix acak di `docs/visual-baseline`; folder atau gambar lama tidak ditimpa. Tanpa `-ChromePath`, pemeriksaan browser secara eksplisit dilewati. Tanpa `-CaptureScreenshots`, interaksi Chrome tetap dapat diuji tanpa menambah gambar.

## Batas bukti

Ini bukan full QA, UAT, audit aksesibilitas/pembaca layar, bukti zoom 200%, pemulihan BFCache nyata, atau pengujian Linux/Nginx/PHP 8.1 HTTP. Tidak ada autosave, penyimpanan input ke localStorage, retry POST, atau jaminan pemulihan setelah browser ditutup. Pengujian sukses simpan seluruh alur dan konflik yang lebih luas tetap mengikuti suite konten/revisi masing-masing. Jumlah/hasil unit JavaScript dicatat terpisah, bukan disimpulkan dari screenshot ini.
