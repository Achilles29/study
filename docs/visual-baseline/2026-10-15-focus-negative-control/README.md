# Kontrol negatif diagnostik fokus — bukan baseline desain

15 Oktober 2026, Chrome 155.0.8059.79, Apache XAMPP lokal.

Penguji dijalankan dengan `--focus-evidence-negative-control` pada halaman daftar, menu terbuka, native zoom 200%. Outline elemen pertama setelah skip-link sengaja dihapus hanya pada DOM browser sementara. Tidak ada perubahan CSS aplikasi.

Hasil yang diharapkan terjadi: exit 1, `focusIndicator: false`, JSON gagal dan viewport PNG tersimpan sebelum fokus dipindah atau gulir direset. Screenshot ditinjau langsung: posisi brand/menu cocok dengan metadata, bukan tangkapan akhir halaman setelah pembersihan.

Ini bukti awal jalur penangkapan kegagalan. Snapshot pada run ini belum memiliki indeks per-tag, hit-stack, dan status font yang kemudian ditambahkan. Bukan reproduksi masalah intermiten lama atau hasil uji akhir seluruh aplikasi. Rangkuman ada di [laporan diagnostik](../2026-10-15-focus-public-final/README.md).
