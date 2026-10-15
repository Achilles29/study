_abaikan
terlalu lama progressnya dan memakan terlalu banyak token.
saya stop dulu lalu nanti dilanjutkan dengan ketentuan:

- alamat yang digunakan adalah www.projectpintar.com
- boleh pisahkan user admin berdasarkan beberapa tingkatan, tapi sebenarnya saya belum terlalu butuh karena saya menghandle semua sendiri, jadi buatkan role superadmin yang bisa mengeleloa semua. tuliskan user dan password nya di docs agar saya bisa tes aplikasi ini sambil jalan
- saya ingin membuat opsi pendaftaran dengan sso google minimal, atau lainnya juga, tapi yang gratis. bagaimana?
- saya juga butuh pendaftaran dengan KYC, jadi minimal dengan email atau watsapp verifikasi, agar user valid dan terverifikasi tidak membludak tapi invalid semua.
- stop dulu aplikasi, akan saya migrasi ke server, buatkan handover nya secara jelas, agar server juga memahami konteks kita, dan tau apa yang sudah dikerjakan dan belum dikerjakan. buat prompt nya di 1 task md, jadi saya tinggal nyuruh server baca disana.
- di server tujuan juga ada pustaka dan folder finance yang dijadikan adopsi, jadi kalau ada tugas yang belum selesai terkait itu bisa diambil darisana.
- untuk metode pembayaran buat pengaturannya seperti di finance self oder, jadi saya memasukkan akun midtrans disana, dan user tinggal memilih qris untuk melakukan pembayaran, dan otomatis pembayaran berhasil, kredit masuk.
- untuk harga kredit nanti buat saja misal 20.000 dapat berapa kredit yang dapat membuka beberapa paket, agar tidak terlalu mahal. dan paket all in misal 300.000 sudah masuk member platinum bisa membuka semua dan paket, dan jika ada paket baru otomatis dia bisa akses.
- target pertama saya ingin membuat untuk anak TK, SD, SMP. khusus pembuatan materi  paket soal nanti saja terpisah karena harus sesuai dengan kurikulum juga.
- 1 orang tua bisa membuat maksimal 2 anak, dan jika lebih harus unlok anak, misal 1 anak 10 ribu
- aturan kelulusan dan lainnya kamu sarankan yang terbaik bagaimana.
- dan jangan lupa untuk point yang di dapat setelah siswa berhasil atau lulus soal bisa dikumpulkan untuk membuka paket soal yang masih terkunci
- kebijakan privasi dan lainnya kamu sarankan yang terbaik.
- nah seperti battle ini gimana ya, saya juga ingin orang bisa akses untuk membuat battle, membuat soal, dan bisa membuat lomba yang dipakai banyak user, gratis, harapan saya nanti juga bisa saya bisa pasang iklan googleads disana. 


- modifikasi pola pengerjaannya agar tidak terlalu buang waktu dan buang token, mungkin kurangi pengujian yang tidak terlalu penting, yang penting secara logika penyusunan kode benar benar dipastikan valid tanpa bug


- saya coba masuk panel pengelola, tapi kok simple sekali, tidak ada halaman monitoring untuk modul modul nya seperti kalau di pustaka kan kita bisa memonitor progresss masing masing user. hanya ada direktory pengguna, jejak audit, dan kursus dan konten. coba cek pustaka di kelompok sidebar Pembelajaran, disana admin bisa mengelola semua modul. lalu untuk mode pengaturan lainnya juga mana? misal pengaturan pembayaran dan lain lain. panel pengelola itu untuj superadmin atau untuk user?
- kalau tampilan panel pengelola untuk superadmin/admin apakah memang seperti itu? atau dibuat mode sidebar?
- kalau belum ada saya juga butuh data trafik pengunjung, tab detail per halaman dan tab detail per user
- lakukan lagi penyesuaian task yang diperlukan untuk mengakomodir pertanyaan saya diatas