# Regresi beranda setelah konsistensi tombol

15 Oktober 2026, Apache XAMPP PHP 8.0.30, CLI PHP 8.1.34, Chrome 155. Suite `dashboard-resume-check.ps1` selesai exit 0 setelah perubahan CSS tombol bersama. Cakupan HTTP/fixture mengikuti [baseline fitur resume](../2026-10-15-dashboard-resume-755f7e559a804526/README.md); ini run baru dengan fixture unik, bukan penimpaan artefak lama.

Lima keadaan beranda dewasa/anak masing-masing diuji pada 360/768/1280 px, total 15 kombinasi. Sepuluh PNG disimpan pada 360/1280 px. Hanya [anak terisi 360 px](resume-child-populated-360.png) ditinjau langsung dalam run ini: tombol riwayat dan keluar kini berwarna tema hijau, kartu/teks tetap terbaca, tanpa overlap tampak. Sembilan gambar lain tersedia sebagai artefak; tidak dianggap telah ditinjau satu per satu.

Pengujian akses, isolasi, urutan, resume, status, GET/HEAD, digest progres/audit dan cleanup tepat lulus. Sesi HTTP milik suite dilogout; akun/konten/progres sintetis dibersihkan. Tidak memindai file sesi server browser atau profil lama. [Laporan tombol dan batas bukti](../2026-10-15-button-themes/README.md) membedakan matriks komponen dari halaman sebenarnya. Bukan full QA, audit aksesibilitas atau validasi HTTP PHP 8.1/Linux.
