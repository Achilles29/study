# Arena Belajar — adopsi server 16 Oktober 2026

Tambahan pemilik meminta aktivitas dan isi permainan Pustaka diadopsi, kecuali bank soal sesi latihan. Instruksi ini mengizinkan konten game yang dibaca dari source; tidak mengizinkan penyalinan akun, hasil anggota, secret, callback, atau database Pustaka. Status berikut bukan klaim seluruh roadmap selesai.

## Status terbaru — migration 025

Hub kini sembilan judul aktivitas, 190 katalog: 75 aktivitas Inggris, 15 episode Quest, 29 flashcard, 22 Story, serta mini games/ekspedisi sebelumnya. Battle gratis mendukung undangan privat dan katalog semua member melalui review. Gmail/verifikasi/reset sudah tersedia; pengiriman nyata menunggu pengisian kredensial dan uji inbox. Lihat [email & battle publik 024–025](EMAIL_DAN_BATTLE_PUBLIK_SERVER.md) serta [adopsi konten 020–023](ADOPSI_LANJUTAN_SERVER.md). Bagian 016–019 di bawah adalah bukti historis.

## Selesai pada tahap 019 (historis)

- `/belajar/arena` (juga `/belajar`): hub peserta berisi **enam judul jenis permainan**, tanpa menumpuk seluruh set/level. Klik kartu game membuka rincian di `/belajar/arena/game/{jenis}`; desktop tiga kolom, ponsel satu kolom.
- Halaman rincian: `flashcard`, `pasangan`, `susun-kata`, `hitung-cepat`, `math-expedition`, `story-quiz`. Set biasa memiliki filter jenjang dan pagination 12; ekspedisi mempunyai peta 12 level. Progres dan tautan kembali berada dalam konteks game yang dipilih.
- `/belajar/arena/riwayat`: riwayat privat, hasil/pembahasan final, pagination 20. Satu babak aktif per akun; kembali nanti untuk resume.
- `/kelola/arena`: ketersediaan setiap set, revisi optimistis dan audit atomik; tidak menghapus hasil lama.
- `/kelola/pengaturan/arena`: aktif/jeda, 4/6 pasangan, 5/10 kata, 5/10/15 flashcard, pilihan timer Hitung Cepat, ambang ekspedisi. Akun TK tetap tanpa timer, 4 pasangan/5 kata/kartu. Perubahan jumlah hanya berlaku pada babak baru.
- `/kelola/pembelajaran/modul/arena`, detail pengguna tab Arena, dan rapor: sumber `st_arena_runs`, tanpa snapshot/kunci/jawaban aktif. Skor permainan tidak menggantikan nilai terbaik/terakhir kuis kursus.
- `/keluarga/anak/{id}/arena`: hanya orang tua tertaut aktif; laporan agregat/riwayat tanpa mutasi babak anak. Ringkasan juga masuk rapor keluarga.

| Modul | Isi yang dibawa | Penyesuaian |
| --- | --- | --- |
| Cocokkan Pasangan/Memory Match | 15 set TK–SMP, 138 pasangan sumber | Board tersembunyi, buka kartu/validasi server; definisi duplikat dalam satu babak disaring supaya jawaban identik tidak dianggap salah. Hasil berupa pasangan/langkah, bukan nilai penguasaan |
| Susun Kata | 14 set TK–SMP, 140 kata/petunjuk | Huruf acak dan jawaban dinilai server; pembahasan setelah final; teks di-escape |
| Hitung Cepat | 8 level/config sumber; 10 operasi acak per babak | Penjumlahan/pengurangan/perkalian/pembagian hasil bulat, timer pilihan 60–90 detik; mode santai standar. Batas waktu server, bukan jam browser |
| Math Expedition | 12 level, 60 tantangan/petunjuk/pembahasan/tips sumber | Tanpa timer, pilihan diacak, level berurutan tanpa bypass admin. Ambang babak baru default teknis 80% (4/5), dapat diatur 60/80/100; snapshot aturan dan tanda tuntas lama tetap berlaku |
| Story Quiz | 10 bacaan SD–SMP, 21 pertanyaan pemahaman sumber | Bacaan tersimpan dalam snapshot, boleh dibaca ulang selama babak, pilihan diacak dan dinilai server, pembahasan setelah final |
| Flashcard mandiri | 14 set SD–SMP, 151 kartu/definisi/petunjuk sumber | Baca depan, buka belakang, tandai sudah paham/perlu diulang. Refleksi diri, bukan skor ujian. Status terbaru per kartu/versi, jumlah tinjauan, resume dan riwayat babak |

Total katalog setelah migration 019: **73 set/level** (sebelumnya 59 pada 018). Ini isi **permainan**, bukan seed kursus/paket berbayar atau bank sesi latihan. Konten SMA disisihkan sesuai sasaran TK/SD/SMP. Hasil belum menghasilkan poin/kredit, leaderboard, sertifikat, atau ketuntasan kursus. Tidak ada iklan/telemetri baru.

Flashcard masuk `/kelola/pembelajaran/modul/kartu_arena`, tab Flashcard Arena per pengguna, rapor dan rapor keluarga tertaut. Laporan menghitung kartu unik versi konten saat ini; babaknya tidak dihitung lagi sebagai permainan dan refleksi diri tidak menjadi nilai kuis. Snapshot/isi kartu tidak dipilih query monitoring. Riwayat babak lama tetap dipertahankan saat status kartu diperbarui.

## Sumber dan batas adopsi

Pustaka hanya dibaca: `views/game/lobby.php`, `controllers/Play_game.php`, `models/Learn_games_model.php`, serta SQL konten `2026-08-12j_memory_match_seed.sql`, `2026-08-12k_word_scramble_seed.sql`, `2026-08-13e_word_scramble_sd_sma.sql`, `2026-08-13f_memory_match_sd_sma.sql`, `2026-08-15a_math_expedition.sql`. SQL sumber **tidak dieksekusi**, prosedur/drop/delete sumber tidak dibawa. Script build mengekstrak teks ke JSON lokal beserta hash sumber; aplikasi produksi hanya membaca database `db_study`, tanpa koneksi ke Pustaka. Petunjuk AYAM diperjelas untuk membedakan ayam jantan.

Katalog yang dibawa adalah versi source SQL tersebut, bukan klaim sinkron seluruh perubahan editor di database Pustaka. Penambahan konten baru harus append/versioned; jangan menjalankan ulang migration atau menimpa snapshot babak lama.

Story Quiz mengambil bacaan asli dari `2026-08-06b_story_quiz.sql` dan `2026-08-13c_story_quiz_catalog.sql`, tanpa menjalankan DELETE/registrasi menu sumber. Ekspansi template `2026-08-13d_story_quiz_5x5_catalog.sql` belum dibawa: banyak bacaan/pertanyaan otomatis mengulang struktur yang sama dan masih memerlukan tinjauan mutu, bukan dibuat menjadi kurikulum massal. Isi bacaan yang diadopsi dapat dicoba, bukan placeholder.

Flashcard diambil dari `2026-08-06a_flashcards.sql`, `2026-08-13a_flashcard_catalog_expansion.sql`, dan `2026-08-13b_flashcard_secondary_school.sql` melalui `scripts/build-arena-flashcards.mjs`; hash sumber tercatat dalam JSON lokal. Isi kartu dibawa, akun/progres sumber tidak. Identitas kartu SHA-256 dari depan/belakang bersama versi konten; unique constraint mencegah penghitungan ganda saat ditinjau ulang.

Belum selesai: kelas Inggris khusus, English Quest/RPG, battle/lomba dan reward poin. Tidak membuat tautan mati untuk modul-modul tersebut. Mini game selesai bukan berarti seluruh tab “Pilih aktivitas hari ini” sudah setara Pustaka. Kelas Inggris sumber mempunyai bootstrap yang mengubah tabel/konten/progres saat model dimuat; model tersebut **tidak boleh dijalankan atau disalin langsung**. Inspeksi hanya membaca source/data, tidak memuat model Pustaka ke proses aplikasi.

## Pembaruan pengujian 019 dan pemisahan tampilan

QA terisolasi `study_qa_monitor_arena219`, hasil restore fixture sintetis 018 (bukan data anggota Pustaka). Pengujian mutasi dijalankan berurutan.

- `test-flashcard-arena-http.mjs`: seluruh 14 set/151 kartu ditinjau hingga selesai; sisi belakang tidak dikirim sebelum reveal, rate sebelum reveal ditolak, replay mulai, tab usang, CSRF, akses akun lain, status repeat memperbarui kartu yang sama, rollback run/progres saat trigger gagal, bank soal tidak berubah. Iterasi awal menemukan deskripsi set dasar tidak tersedia (diberi fallback judul) dan token CSRF harness perlu disegarkan sebelum replay; hasil akhir lulus.
- `test-flashcard-arena-reports.mjs`: jumlah kartu dikenal 150/151 sesuai fixture, tidak ada isi kartu dalam monitoring, batas 5/10/15, snapshot tidak ditulis ulang oleh pengaturan, pause/stop, TK maksimal 5, orang tua tidak dapat memainkan babak anak, laporan keluarga sesuai tautan dan revokasi lulus.
- `test-arena-engine.php`: regresi 37 set mini game/12 ekspedisi serta 14 set flashcard × batas 5/10/15 lulus; Unicode snapshot, validasi rating dan final review diperiksa. Pengacakan huruf kini hanya untuk Susun Kata agar teks Unicode kartu tidak rusak.
- `test-arena-http.mjs`: regresi terarah mini games/akses/audit/rollback/settings lulus setelah field pengaturan kartu ditambahkan.
- `test-arena-catalog-browser.mjs`: enam judul pada hub tanpa tautan set langsung, semua rincian game, filter jenjang/pagination, keyboard, flashcard reveal/rate/stop, monitoring/settings, route invalid; 1280/360 px tanpa overflow/galat JS. Screenshot hub 360 dan rincian 1280 ditinjau langsung; capture sintetis `/tmp/study-arena-{hub,sets,flashcard}-{360,1280}.png`.
- PHPCS file terdampak dan pemeriksaan nama CI3 (231 PHP) lulus. Database produksi lewat kredensial runtime: `db_study` version 19, 73 katalog, satu pengguna ID 1, tanpa babak/progres kartu QA dan bank soal tetap kosong. UAT pemilik masih terbuka.

Migration **tambahan** 019 membuat `st_arena_card_progress` dan menambahkan katalog kartu; tidak mengulang instalasi/migration lama. Backup sebelum deploy: `/www/backup/projectpintar-deploy-20261016/before-flashcards-019.sql`. Tidak menyentuh Finance/Pustaka atau callback pembayaran.

Smoke HTTPS akhir: beranda 200; Arena dan rincian flashcard tanpa login 307; JSON privat 404. Setelah tes, server loopback 8097 dihentikan dan **hanya** database QA `study_qa_monitor_arena219` dihapus. Fixture sintetis dapat dipulihkan dari backup privat `arena-qa-evidence-019.sql` di direktori backup yang sama (mode 600); produksi tidak dibersihkan/direset. Seluruh tes fitur baru memakai QA, bukan simulasi transaksi pada akun pemilik.

## Bukti pengujian

QA memakai `study_qa_monitor_arena20261016` pada layanan MariaDB Study, server loopback 8097; tidak memakai fixture pada produksi. Satu pengujian paralel ekspedisi sempat terkena trigger kegagalan simpan yang sengaja dipasang tes mini game; tes kemudian dijalankan **berurutan**, fixture ekspedisi QA dibersihkan tanpa reset ID, dan seluruh alurnya lulus.

- `test-arena-engine.php`: 37 set × 10 babak acak; aritmetika, kartu tidak ambigu, payload tanpa kunci, ketuntasan, expiry. Ekspedisi 12 level × hasil 0/60/80/100%, validasi opsi, ambang snapshot dan pembahasan final lulus.
- `test-arena-http.mjs`: alur tiga mini game, start replay, resume, tab lama 409, CSRF, akun lain/orang tua tidak dapat memainkan babak anak, revokasi keluarga, timer server/TK, escaping, aktif/nonaktif, konflik revisi dan rollback audit/simpan; pengaturan aktif/jeda/jumlah kata dan laporan nyata lulus.
- `test-expedition-http.mjs`: seluruh 12 level/60 jawaban dijalankan, percobaan kurang dari ambang tetap terkunci, token lintas set ditolak, token valid pada level terkunci ditolak server, perubahan ambang tidak menghitung ulang babak berjalan, seluruh progres level terbuka sesuai urutan.
- Browser mini games dan ekspedisi: 1280/360 px, input/flip kartu/pilihan dengan keyboard, petunjuk, resume/hasil, admin/pengaturan/monitoring; tanpa overflow/galat JavaScript. Screenshot sintetis `/tmp/study-arena-{mobile,memory-mobile,expedition-mobile}.png` diperiksa.
- `test-story-http.mjs`: seluruh 10 bacaan/21 pertanyaan dimainkan hingga final, bacaan snapshot/pilihan/pembahasan benar, jumlah soal bank tidak berubah. Browser Story 1280/360 px dan keyboard diuji terpisah.
- Runtime produksi melalui kredensial aplikasi (bukan root): migration 18, 59 katalog, 0 babak uji, 1 pengguna ID 1, 0 soal bank. HTTPS beranda 200, Arena tanpa login 307 menuju masuk, JSON konten di `application/` ditolak 404.

## Deployment

Migration tambahan 016 membuat `st_arena_packs`, `st_arena_runs` dan pengaturan Arena. Migration 017 menambah penanda tuntas/index serta katalog ekspedisi; 018 hanya menambahkan katalog cerita. Tidak mengulang migrasi awal/instalasi, tidak mengubah ID lama. Backup sebelum perubahan: `/www/backup/projectpintar-deploy-20261016/before-arena-016.sql`, `before-expedition-017.sql`, dan `before-story-018.sql`. Produksi tetap akun ngadmin ID 1; tidak ada akun/babak QA atau kredensial pembayaran yang disisipkan.

Setelah pengujian, server QA loopback 8097 dihentikan dan database sementara `study_qa_monitor_arena20261016` dihapus. Bukti fixture sintetis dapat dipulihkan dari backup privat `arena-qa-evidence-018.sql` dalam direktori backup yang sama. `db_study`, Pustaka dan Finance tidak dihapus/direset. Lint PHP 8.4 pada 20 file terdampak, hygiene PHP, pemeriksaan nama CI3 dan `git diff --check` lulus; tidak mengulang seluruh matriks platform historis.
