# Regresi daftar pengelola setelah konsistensi tombol

15 Oktober 2026, Apache XAMPP PHP 8.0.30, CLI PHP 8.1.34, Chrome 155. Suite `admin-course-list-check.ps1` selesai exit 0 setelah perubahan CSS tombol bersama. Cakupan HTTP mengikuti [baseline pagination pengelola](../2026-10-15-admin-course-list-pagination-32ed3752ecb84ff9/README.md), dengan fixture unik baru: satu admin, role dan 24 kursus sintetis.

Halaman pertama, kedua dan hasil filter kosong masing-masing diperiksa pada 360/768/1280 px, total sembilan kombinasi. Enam PNG disimpan pada 360/1280 px. Hanya [halaman kedua 1280 px](admin-courses-last-page-1280.png) ditinjau langsung: tombol utama/outline selaras dalam palet ungu, tabel dan pagination tidak tumpang tindih, payload markup tetap berupa teks. Lima gambar lain belum ditinjau satu per satu.

Urutan/filter/pagination 20+1, escaping, keadaan kosong, digest kursus/audit dan cleanup tepat lulus. Akun/role/kursus/audit sintetis dibersihkan; login-failure lama dipertahankan. File sesi server dari login browser tidak dipindai; penghapusan akun membuat sesi sintetis tidak dapat dipakai pada request autentikasi berikutnya. [Laporan tombol dan batas bukti](../2026-10-15-button-themes/README.md) bukan full QA, audit aksesibilitas atau validasi HTTP PHP 8.1/Linux.
