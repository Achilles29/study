# Tinjauan pencarian dan filter sesi keluarga

Capture pada PHP 8.1 loopback, 15 Oktober 2026, dari `parent-practice-check.ps1 -ChromePath ... -CaptureScreenshots`. Semua nama anak, sesi dan hitungan adalah fixture sintetis yang dibersihkan suite.

- [Filter aktif dan pagination ponsel, 360 px](family-session-filters-360.png)
- [Hasil kosong terfilter desktop, 1280 px](family-session-empty-filter-1280.png)

Tinjauan manual memastikan field pencarian/anak/status berlabel dan bertumpuk pada ponsel, nilai filter terlihat, tombol terapkan/reset tidak bertumpuk, hitungan menyebut cakupan filter, dan pagination 10+1 tetap tampil. Hasil kosong mengarahkan pengguna mengubah/menghapus filter, bukan mengatakan akun belum mempunyai sesi. Daftar ponsel panjang karena memuat sepuluh kartu, bukan overflow horizontal.

Suite Chrome memeriksa enam halaman/state draf (termasuk filter aktif dan kosong) pada 360/768/1280 px tanpa overflow, isu label heuristik, atau kegagalan fokus skip-link pertama. Dukungan query dibatasi pada parameter daftar bernama `q/anak/status/halaman`; URL eksternal, traversal, key tak dikenal/ganda, dan encoding invalid ditolak sebelum browser diluncurkan. Pengujian HTTP turut mengikuti tautan halaman berikutnya yang benar-benar dirender, bukan hanya URL buatan tes.

Ini bukan audit WCAG penuh, zoom 200%, pembaca layar, uji load, atau UAT. Hasil regresi gabungan dicatat di Tahap 14 roadmap.
