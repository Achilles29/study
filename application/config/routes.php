<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'home';
$route['robots.txt'] = 'seo/robots';
$route['sitemap.xml'] = 'seo/sitemap';
$route['latihan-soal'] = 'public_learning/index';
$route['latihan-soal/kelas-([1-6])'] = 'public_learning/index/$1';
$route['latihan-soal/kelas-([1-6])/(:any)'] = 'public_learning/index/$1/$2';
$route['privasi'] = 'privacy/index';
$route['bantuan'] = 'help/index';
$route['bantuan/buka'] = 'help/recover';
$route['bantuan/tiket/(:num)'] = 'help/ticket/$1';
$route['kelola/bantuan'] = 'help/manage';
$route['kelola/bantuan/(:num)'] = 'help/manage_ticket/$1';
$route['preferensi-privasi'] = 'privacy/preferences';
$route['kelola/trafik'] = 'traffic/index';
$route['kelola/trafik/live'] = 'traffic/live';
$route['donasi'] = 'donations/index';
$route['donasi/pesanan/(:num)'] = 'donations/order/$1';
$route['kelola/donasi'] = 'donations/admin';
$route['kelola/trafik/(:any)'] = 'traffic/index/$1';
$route['masuk'] = 'auth/sign_in';
$route['daftar'] = 'auth/sign_up';
$route['keluar'] = 'auth/sign_out';
$route['akun/email'] = 'account_mail/index';
$route['akun/verifikasi'] = 'account_mail/verify';
$route['lupa-sandi'] = 'account_mail/forgot';
$route['pulihkan-sandi'] = 'account_mail/reset';
$route['beranda'] = 'dashboard/index';
$route['belajar'] = 'arena/index';
$route['belajar/poin'] = 'points/index';
$route['belajar/poin/tukar/(:num)'] = 'points/redeem/$1';
$route['keluarga/anak/(:num)/poin'] = 'points/child/$1';
$route['kelola/poin'] = 'points/admin';
$route['kelola/poin/(:num)'] = 'points/admin/$1';
$route['belajar/arena'] = 'arena/index';
$route['belajar/arena/game/(:any)'] = 'arena/game/$1';
$route['belajar/arena/riwayat'] = 'arena/history';
$route['belajar/arena/pilih/(:any)'] = 'arena/choose/$1';
$route['belajar/arena/main/(:num)'] = 'arena/run/$1';
$route['kelola/arena'] = 'arena/manage';
$route['kelola/kelas-inggris/konsep'] = 'english_hack/index';
$route['kelola/kelas-inggris/konsep/unduh/(:any)'] = 'english_hack/download/$1';
$route['belajar/kelas-inggris'] = 'english_course/index';
$route['belajar/kelas-inggris/unit/(:any)'] = 'english_course/unit/$1';
$route['belajar/kelas-inggris/mulai/(:any)/(:any)'] = 'english_course/start/$1/$2';
$route['belajar/kelas-inggris/latihan/(:num)'] = 'english_course/run/$1';
$route['belajar/kelas-inggris/riwayat'] = 'english_course/history';
$route['belajar/kelas-inggris/tugas/(:any)/(:any)'] = 'english_course/submit/$1/$2';
$route['belajar/kelas-inggris/karya/(:num)/audio'] = 'english_course/submission_audio/$1';
$route['belajar/kelas-inggris/karya/(:num)'] = 'english_course/submission/$1';
$route['belajar/kelas-inggris/audio/(:any)'] = 'english_course/audio/$1';
$route['keluarga/anak/(:num)/kelas-inggris'] = 'english_course/family/$1';
$route['kelola/kelas-inggris'] = 'english_course/manage';
$route['kelola/kelas-inggris/tinjau/(:num)'] = 'english_course/review/$1';
$route['keluarga/anak/(:num)/arena'] = 'arena/family/$1';
$route['kursus'] = 'catalog/index';
$route['permainan/pasangan/(:any)'] = 'games/matching/$1';
$route['permainan/riwayat'] = 'matching/index';
$route['permainan/tersimpan/pelajaran/(:any)'] = 'matching/lesson/$1';
$route['permainan/tersimpan/(:num)/berhenti'] = 'matching/stop/$1';
$route['permainan/tersimpan/(:num)'] = 'matching/run/$1';
$route['kursus/(:any)'] = 'catalog/course/$1';
$route['materi/(:any)/posisi'] = 'catalog/save_lesson_position/$1';
$route['materi/(:any)/selesai'] = 'catalog/complete_lesson/$1';
$route['materi/(:any)/flashcard/(:num)'] = 'catalog/save_flashcard_progress/$1/$2';
$route['materi/(:any)/gambar'] = 'lesson_media/show/$1';
$route['materi/(:any)'] = 'catalog/lesson/$1';
$route['kelola'] = 'admin/index';
$route['kelola/pengaturan'] = 'settings/index';
$route['belajar/battle'] = 'battle/index';
$route['belajar/battle/umum'] = 'battle/directory';
$route['belajar/battle/umum/(:num)'] = 'battle/public_room/$1';
$route['belajar/battle/buat'] = 'battle/create';
$route['belajar/battle/gabung'] = 'battle/join';
$route['belajar/battle/ruang/(:num)'] = 'battle/room/$1';
$route['kelola/battle'] = 'battle/manage';
$route['kelola/battle/katalog'] = 'battle/queue';
$route['kelola/battle/tinjau/(:num)'] = 'battle/review/$1';
$route['kelola/pengaturan/pembayaran'] = 'payment_settings/index';
$route['kelola/pengaturan/email'] = 'account_mail/settings';
$route['kelola/pengaturan/google'] = 'google_auth/settings';
$route['kelola/pengaturan/telegram'] = 'notifications/settings';
$route['kelola/pengaturan/(:any)'] = 'settings/index/$1';
$route['kelola/pembelajaran'] = 'learning_monitor/index';
$route['kelola/pembelajaran/modul'] = 'learning_monitor/modules';
$route['kelola/pembelajaran/modul/(:any)'] = 'learning_monitor/modules/$1';
$route['kelola/pembelajaran/pengguna/(:num)'] = 'learning_monitor/user/$1';
$route['kelola/pembelajaran/aktivitas/(:any)/(:num)'] = 'learning_monitor/activity/$1/$2';
$route['kelola/rapor'] = 'learning_monitor/reports';
$route['kelola/rapor/(:num)'] = 'learning_monitor/report/$1';
$route['keluarga/anak/(:num)/rapor'] = 'learning_monitor/family_report/$1';
$route['kelola/pengguna'] = 'user_directory/index';
$route['kelola/operasional'] = 'operations/index';
$route['akun/google'] = 'google_auth/account';
$route['auth/google/mulai'] = 'google_auth/start';
$route['auth/google/callback'] = 'google_auth/callback';
$route['auth/google/daftar'] = 'google_auth/register';
$route['belajar/battle/ruang/(:num)/status'] = 'battle/live_state/$1';
$route['kelola/pengguna/(:num)/akses'] = 'account_controls/manage/$1';
$route['keluarga/anak/(:num)/pulihkan-sandi'] = 'account_controls/child/$1';
$route['kelola/audit'] = 'audit_log/index';
$route['kelola/kursus'] = 'admin/courses';
$route['kelola/kursus/tambah'] = 'admin/create_course';
$route['kelola/kursus/(:num)/edit'] = 'admin/edit_course/$1';
$route['kelola/kursus/(:num)/soal'] = 'questions/index/$1';
$route['kelola/kursus/(:num)/susunan'] = 'course_structure/index/$1';
$route['kelola/kursus/(:num)'] = 'admin/course/$1';
$route['kelola/kursus/(:num)/pelajaran/tambah'] = 'admin/add_lesson/$1';
$route['kelola/kursus/(:num)/pelajaran/(:num)/edit'] = 'admin/edit_lesson/$1/$2';
$route['kelola/kursus/(:num)/pelajaran/(:num)/flashcard'] = 'admin/flashcards/$1/$2';
$route['kelola/kursus/(:num)/pelajaran/(:num)/pratinjau'] = 'admin/preview_lesson/$1/$2';
$route['kelola/kursus/(:num)/pelajaran/(:num)/gambar/pratinjau'] = 'lesson_media/preview/$1/$2';
$route['kelola/kursus/(:num)/pelajaran/(:num)/gambar'] = 'lesson_media/edit/$1/$2';
$route['kelola/kursus/(:num)/ajukan'] = 'admin/submit_course/$1';
$route['kelola/kursus/(:num)/tinjau'] = 'admin/review_course/$1';
$route['kelola/kursus/(:num)/arsip'] = 'admin/archive_course/$1';
$route['kelola/kategori'] = 'admin/categories';
$route['kelola/kategori/tambah'] = 'admin/create_category';
$route['keluarga/anak'] = 'family/children';
$route['akun/keamanan'] = 'account_security/index';
$route['akun/pengaturan'] = 'account_settings/index';
$route['keluarga/latihan'] = 'parent_practice/index';
$route['keluarga/latihan/(:num)'] = 'parent_practice/edit/$1';
$route['keluarga/latihan/(:num)/hasil'] = 'parent_practice/result/$1';
$route['keluarga/latihan/(:num)/salin'] = 'parent_practice/copy/$1';
$route['keluarga/anak/(:num)/latihan'] = 'family/practice/$1';
$route['keluarga/anak/(:num)/latihan/(:num)/live'] = 'family/practice_live/$1/$2';
$route['keluarga/anak/(:num)/latihan/(:num)/live/status'] = 'family/practice_live_status/$1/$2';
$route['keluarga/anak/(:num)/permainan'] = 'family/matching/$1';
$route['keluarga/anak/(:num)/permainan/(:num)'] = 'family/matching_result/$1/$2';
$route['keluarga/anak/(:num)/latihan/(:num)'] = 'family/practice_result/$1/$2';
$route['keluarga/anak/tambah'] = 'family/add_child';
$route['keluarga/anak/(:num)/kelompok'] = 'family/update_child_stage/$1';
$route['latihan'] = 'practice/index';
$route['notifikasi'] = 'notifications/index';
$route['ulasan'] = 'reviews/index';
$route['ulasan/tulis'] = 'reviews/write';
$route['kelola/ulasan'] = 'reviews/admin';
$route['kelola/ulasan/pratinjau'] = 'reviews/preview';
$route['kelola/bank-soal'] = 'bank/index';
$route['kelola/simulasi-ujian'] = 'exams/index';
$route['kelola/simulasi-ujian/impor-pustaka'] = 'exams/import_report';
$route['kelola/akses-arena'] = 'arena_access/index';
$route['kelola/simulasi-ujian/(:num)'] = 'exams/edit/$1';
$route['latihan/mandiri'] = 'drills/index';
$route['latihan/ujian'] = 'drills/exams';
$route['latihan/ujian/(:num)'] = 'drills/exam/$1';
$route['latihan/buka/(:any)/(:num)'] = 'drills/unlock/$1/$2';
$route['latihan/percobaan/(:num)/gambar/(:num)'] = 'practice/image/$1/$2';
$route['latihan/percobaan/(:num)/gambar/(:num)/(:num)'] = 'practice/image/$1/$2/$3';
$route['kelola/bank-soal/template/(:any)'] = 'bank/template/$1';
$route['kelola/bank-soal/impor'] = 'bank/upload';
$route['kelola/bank-soal/impor/(:any)/gambar/(:num)'] = 'bank/preview_image/$1/$2';
$route['kelola/bank-soal/impor/(:any)/gambar/(:num)/(:num)'] = 'bank/preview_image/$1/$2/$3';
$route['kelola/bank-soal/impor/(:any)'] = 'bank/preview/$1';
$route['kelola/bank-soal/(:num)/gambar'] = 'bank/image/$1';
$route['kelola/bank-soal/(:num)/gambar/(:num)'] = 'bank/image/$1/$2';
$route['kelola/bank-soal/(:num)'] = 'bank/edit/$1';
$route['latihan/kursus'] = 'practice/catalog';
$route['latihan/keluarga'] = 'practice/assignments';
$route['latihan/keluarga/(:num)'] = 'practice/assigned/$1';
$route['latihan/kursus/(:num)'] = 'practice/course/$1';
$route['latihan/percobaan/(:num)'] = 'practice/attempt/$1';
$route['latihan/percobaan/(:num)/pembahasan'] = 'practice/attempt/$1/review';
$route['akun/kredit'] = 'commerce/index';
$route['akun/kredit/checkout/(:num)'] = 'commerce/checkout/$1';
$route['akun/pesanan/(:num)'] = 'commerce/order/$1';
$route['kelola/pesanan'] = 'commerce/orders';
$route['kelola/kredit'] = 'commerce/wallets';
$route['kelola/kredit/(:num)'] = 'commerce/ledger/$1';
$route['kelola/voucher/(:num)/kompensasi'] = 'commerce/compensate/$1';
$route['pembayaran/notifikasi'] = 'payment_notify/index';
$route['akun/kredit/beli/(:num)'] = 'commerce/buy/$1';
$route['akun/voucher/(:num)/gunakan'] = 'commerce/redeem/$1';
$route['kelola/paket'] = 'commerce/admin';
$route['kelola/paket/(:num)'] = 'commerce/package/$1';
$route['kelola/produk/(:num)'] = 'commerce/offer/$1';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;
