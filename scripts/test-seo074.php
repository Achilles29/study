<?php
define('BASEPATH',__DIR__);
require dirname(__DIR__).'/application/helpers/study_admin_helper.php';
require dirname(__DIR__).'/application/libraries/Site_settings.php';
require dirname(__DIR__).'/application/libraries/Traffic_navigation.php';
function site_url($path='') { return 'https://example.test/'.ltrim($path,'/'); }
$n=0;
function t074($ok,$label) { global $n; if(!$ok)throw new RuntimeException($label);$n++; }
$settings=new Site_settings(); $defaults=$settings->defaults('umum');
foreach(array('',str_repeat('a',43),'abc_-'.str_repeat('1',30)) as $token) {t074(!$settings->validate('umum',array_replace($defaults,array('google_site_verification'=>$token)))['errors'],'Valid token');}
foreach(array('<meta content="bad">','short',str_repeat('a',161),"abc\n".str_repeat('b',40),array('x')) as $token) {t074(isset($settings->validate('umum',array_replace($defaults,array('google_site_verification'=>$token)))['errors']['google_site_verification']),'Invalid token');}
$data=array('page_title'=>'Matematika Kelas 1','path'=>'latihan-soal/kelas-1/matematika','intro'=>'Contoh dan pembahasan','public_lesson'=>TRUE,'lesson'=>array('slug'=>'contoh','title'=>'Bilangan','body_text'=>'Materi bilangan.'));
t074(study_page_seo('public_learning/index',$data,NULL)['index'],'Public landing');
t074(study_page_seo('catalog/lesson',$data,NULL)['index'],'Approved public lesson');
t074(!study_page_seo('catalog/lesson',array_replace($data,array('public_lesson'=>FALSE)),NULL)['index'],'Unapproved lesson');
t074(!study_page_seo('catalog/lesson',array_replace($data,array('is_content_preview'=>TRUE)),NULL)['index'],'Preview excluded');
t074(!study_page_seo('catalog/lesson',$data,array('id'=>1))['index'],'Member excluded');
$nav=new Traffic_navigation();
foreach(array('/latihan-soal','/latihan-soal/kelas-1','/latihan-soal/kelas-6/bahasa-inggris') as $path) {t074($nav->safe_path($path.'?token=secret')===$path,'Sanitized public path');}
foreach(array('/latihan-soal/kelas-7','/latihan-soal/kelas-1/akun','/latihan-soal/kelas-1/matematika/42') as $path) {t074($nav->safe_path($path)===NULL,'Reject unknown path');}
echo "PASS $n SEO074 unit checks.\n";
