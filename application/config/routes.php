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
$route['masuk'] = 'auth/sign_in';
$route['daftar'] = 'auth/sign_up';
$route['keluar'] = 'auth/sign_out';
$route['beranda'] = 'dashboard/index';
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
$route['kelola/pengguna'] = 'user_directory/index';
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
$route['keluarga/latihan'] = 'parent_practice/index';
$route['keluarga/latihan/(:num)'] = 'parent_practice/edit/$1';
$route['keluarga/latihan/(:num)/hasil'] = 'parent_practice/result/$1';
$route['keluarga/latihan/(:num)/salin'] = 'parent_practice/copy/$1';
$route['keluarga/anak/(:num)/latihan'] = 'family/practice/$1';
$route['keluarga/anak/(:num)/permainan'] = 'family/matching/$1';
$route['keluarga/anak/(:num)/permainan/(:num)'] = 'family/matching_result/$1/$2';
$route['keluarga/anak/(:num)/latihan/(:num)'] = 'family/practice_result/$1/$2';
$route['keluarga/anak/tambah'] = 'family/add_child';
$route['keluarga/anak/(:num)/kelompok'] = 'family/update_child_stage/$1';
$route['latihan'] = 'practice/index';
$route['latihan/keluarga'] = 'practice/assignments';
$route['latihan/keluarga/(:num)'] = 'practice/assigned/$1';
$route['latihan/kursus/(:num)'] = 'practice/course/$1';
$route['latihan/percobaan/(:num)'] = 'practice/attempt/$1';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;
