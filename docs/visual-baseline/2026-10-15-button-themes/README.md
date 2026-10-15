# Konsistensi tombol Bootstrap, CoreUI dan tema usia

Tanggal: 15 Oktober 2026. Perubahan produksi hanya pada `assets/css/app.css`: tiga varian tombol merek dan indikator fokus label `btn-check`. Palet usia, template, izin, route, database dan aset vendor tidak diubah. Semua pengujian browser memakai Chrome 155.0.8059.79 dan Apache XAMPP PHP 8.0.30 yang sudah berjalan.

## Masalah dan hasil

Sebelumnya, tombol utama hanya memetakan sebagian variabel Bootstrap. Outline/link, tombol nonaktif, dan tombol CoreUI di panel pengelola masih dapat memakai warna vendor. CoreUI membaca namespace `--cui-btn-*`, bukan `--bs-btn-*`. Run browser sebelum perubahan gagal pada fokus tombol utama yang masih membawa halo vendor; temuan namespace dan state lain juga diverifikasi melalui CSS vendor lokal.

Sekarang warna normal, hover, aktif, fokus dan nonaktif mengikuti peta bersama. CoreUI merujuk variabel Bootstrap yang telah ditentukan aplikasi. Primary memakai putih di warna utama; hover/aktif memakai warna lebih gelap. Outline memakai teks gelap dan border utama, kemudian putih di warna gelap saat hover/aktif/fokus. Link tetap transparan dengan teks gelap. Varian sukses/bahaya/peringatan tidak diwarnai ulang. Opacity dan semantik nonaktif tetap mengikuti kontrol native/vendor.

Halo tombol merek digantikan outline bersama 3 px, jarak 3 px. Label setelah input `btn-check` juga mendapat outline ketika input menerima fokus keyboard. Perubahan ini tidak membuat tautan dengan `aria-disabled` otomatis aman: markup, urutan fokus dan pencegahan aktivasi tetap tanggung jawab halaman.

## Matriks komponen

`browser-viewport-check.mjs --exercise-button-themes` membuka halaman publik anonim, lalu membuat papan komponen sintetis hanya pada DOM profil browser uji. Tidak ada halaman baru, form POST, akun, pengaturan tema tersimpan, storage atau mutasi database. Untuk susunan admin, CSS CoreUI lokal dimuat setelah Bootstrap dan sebelum `app.css`, sama seperti urutan layout pengelola.

Tujuh kelas tema × dua susunan CSS × tiga lebar 360/768/1280 menghasilkan **42 kombinasi**. Public/adult/parent/admin berbagi palet umum; tiga palet anak tetap berbeda dan diperiksa terhadap nilai acuan independen agar hilangnya override anak tidak lolos sebagai tema ungu semua.

- Button dan anchor pada primary/outline/link: normal, hover, pressed, `.active`, fokus, hover bersama fokus, serta native disabled/anchor nonaktif.
- Sebelas perhentian Tab native per kombinasi, termasuk input `btn-check`; total 462 perhentian per run. Periksa urutan, `:focus-visible`, outline, posisi kontrol dalam viewport dan ketiadaan halo vendor. Ini bukan pengujian semua tombol pada seluruh halaman.
- Label checkbox yang belum dipilih tidak berubah menjadi filled hanya karena hover. Space memilihnya; pilihan aktif memakai warna aktif. Setelah disabled, klik native tidak mengubah pilihan; opacity, pointer dan palet sebelum opacity diperiksa.
- Tinggi kontrol minimal 44 px, batas horizontal dan luapan dokumen diperiksa. Tidak menambahkan klaim target 52 px untuk semua tombol anak.
- Kontras dihitung tanpa pembulatan ambang dari computed colors terhadap token permukaan putih, wash, lavender dan mint. Nilai minimum pada matriks: teks aktif **4,754474**, border relevan **4,054231**, outline **5,511049**. Angka tampilan ini dibulatkan hanya untuk laporan, bukan penentuan lulus.

Acuan batas pengujian: [W3C kontras teks](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html) dan [kontras nonteks](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html). Teks aktif memakai batas 4,5:1, border/indikator relevan 3:1. Kontras disabled setelah opacity tidak diklaim memenuhi batas teks aktif. Perhitungan bukan analisis gambar, gradien, overlay atau semua latar halaman.

Run tanpa capture pertama, run capture, dan run final setelah pemeriksaan palet independen/checked-disabled diperketat semuanya exit 0. Run capture sudah memeriksa opacity aktif dan posisi fokus dalam viewport; pengetatan terakhir hanya menambah assertion, tanpa perubahan CSS atau gambar. Dua kombinasi opsi tidak sah (route tambahan dan native zoom) ditolak sebelum pemeriksaan server/pembuatan profil/peluncuran browser.

Utilitas sRGB/compositing/kontras mempunyai 15 kelompok unit yang lulus, termasuk black/white, format invalid, alpha, ambang transfer sRGB, serta nilai tepat di sekitar 3 dan 4,5 tanpa pembulatan. Seluruh unit UI sebelumnya tetap lulus. Sintaks empat berkas JS terkait, parser 39 PowerShell, pemindaian rahasia, konflik, lisensi, dokumen dan kontrak route diperiksa. Tidak ada perubahan PHP dan lint PHP tidak diulang pada tahap CSS ini. Full `qa-local.ps1` tidak dijalankan; unit/matriks baru sudah dimasukkan ke orkestrator.

## Gambar yang disimpan

Folder ini memuat sepuluh capture papan komponen dan dua beranda publik. Papan berlabel fixture, **bukan desain halaman pengguna**. Tiga capture papan ditinjau langsung:

- [Usia dini 360 px](buttons-bootstrap-theme-child-early-360.png), reviewer kedua.
- [Sekolah dasar 360 px](buttons-bootstrap-theme-child-primary-360.png), reviewer utama.
- [CoreUI pengelola 1280 px](buttons-bootstrap-coreui-theme-admin-1280.png), reviewer kedua.

Ketiganya memperlihatkan hierarki primary/outline/link dan disabled tanpa tumpang tindih atau pemotongan yang tampak. Gambar menangkap keadaan diam, bukan bukti visual focus ring. Tujuh papan lain serta dua capture beranda tersedia, tetapi tidak dicatat sebagai sudah ditinjau satu per satu.

## Regresi halaman sebenarnya

- Beranda peserta: suite HTTP resume serta 15 kombinasi browser lulus; [capture dan catatan](../2026-10-15-dashboard-resume-6f06788f937c4f59/README.md). Capture anak sekolah dasar 360 px ditinjau: tombol riwayat/keluar kini hijau mengikuti tema, bukan biru vendor.
- Daftar kursus pengelola: suite HTTP daftar/filter/pagination serta sembilan kombinasi browser lulus; [capture dan catatan](../2026-10-15-admin-course-list-pagination-27f55fc1ea964748/README.md). Capture halaman kedua 1280 px ditinjau: tombol utama dan outline selaras tanpa mengubah susunan tabel/pagination.
- Empat halaman publik × tiga lebar dengan navigasi terbuka: 12 kombinasi lulus untuk geometri, heuristik label dan fokus awal. Matriks komponen sendiri juga memeriksa beranda publik pada tiga lebar sebelum mengganti DOM.

Fixture kedua suite DB dibersihkan berdasarkan identitas tepat. Pemeriksaan akhir menunjukkan v13/33 tabel, nol pengguna/kursus/progres/audit, empat peran seed dan satu login-failure lama yang dipertahankan. Log aplikasi hanya `index.html`. Tidak ada pengubahan skema, transaksi keuangan atau reset throttle massal. File sesi server browser tidak dipindai; akun sintetis yang telah dihapus tidak dapat melanjutkan autentikasi pada request berikutnya. Proses/profil lama yang kepemilikannya tidak pasti tidak disentuh.

## Cara mengulang dan batas bukti

```powershell
node scripts/browser-viewport-check.mjs --base-url http://localhost/study/index.php/ --chrome 'C:\Program Files\Google\Chrome\Application\chrome.exe' --exercise-button-themes
```

Untuk capture, tambahkan `--capture-screenshots --screenshot-dir` menuju subfolder baru `docs/visual-baseline`; jangan menimpa folder bukti ini. Mode ini menolak kredensial login, route tambahan, native zoom, serta mode exercise lain. Tidak menyalakan server web.

Matriks menggunakan reduced motion agar warna akhir dapat diukur; hover/pressed tambahan memakai pseudo-state browser. Tidak membuktikan timing transisi normal, seluruh alur pointer/keyboard, clipping/occlusion outline oleh semua ancestor, forced-colors, dark mode, zoom 200%, screen reader, browser seluler nyata atau UAT. HTTP PHP 8.1 dan Ubuntu/Nginx belum dibuktikan. Hasil ini bukan audit WCAG seluruh aplikasi atau jaminan bebas bug; butir standardisasi semua komponen tetap terbuka.
