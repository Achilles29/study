<?php
define('BASEPATH',dirname(__DIR__).'/system/');define('APPPATH',dirname(__DIR__).'/application/');
require APPPATH.'libraries/Curriculum_batch.php';require BASEPATH.'core/Model.php';require APPPATH.'models/Question_model.php';
$lib=new Curriculum_batch();$courses=$lib->load('053');$validator=new Question_model();$keys=array();$count=0;
foreach($courses as $c){if(count($c['sections'])!==8){throw new RuntimeException('Chapter count');}foreach($c['sections'] as $s){foreach($s['lessons'] as $l){$count++;foreach($l['questions'] as $q){$keys[]=$q[1][$q[2]];if(!$validator->valid_data(array('prompt'=>$q[0],'options'=>$q[1],'correct'=>$q[2],'explanation'=>$q[3],'rights_status'=>'owned','source_note'=>$c['source']))){throw new RuntimeException('Question');}}}}}
$expected=array('60','85','54<71','48,81,84','18','10','5','lingkaran','seperempat','6','memakai satuan sama tanpa celah','sama','topi pesta berbentuk kerucut','segitiga','apel','4 buku','ember kecil','Rani','mengembalikan buku ke rak','warna rak','langkah penutup','Tolong letakkan buku di rak.','mengembalikannya','meminta pengulangan dengan santun','angin menjatuhkan kertas','Danu membantu Lala','Bolehkah aku memakai krayon setelah kamu?','Terima kasih sudah bercerita.','Mira membaca buku.','?','Aku menyimpan buku di rak.','membaca kembali dan memperbaiki','This is my friend.','Nice to meet you, too.','12','three pencils','The ball is under the chair.','Where is it?','Dodi','ibu','She can sing.','Aku tidak bisa berenang','Do you like apples?','No, I don’t.','hujan','jas hujan','on the shelf','Tomi dan Lala');
if($count!==24||$keys!==$expected){throw new RuntimeException('Reviewed keys/count');}
// Formatter emits all authored tasks and answers; its immutable source participates in provenance.
$raw=require APPPATH.'content_batches/053/math_sd2.php';
$hash=hash('sha256',hash_file('sha256',APPPATH.'content_batches/053/math_sd2.php').hash_file('sha256',APPPATH.'content_batches/lesson_builder_v1.php'));
if($courses[0]['source_sha256']!==$hash||strpos($raw['sections'][0]['lessons'][0]['body'],'5 puluhan dan8 satuan:50+8.')===FALSE){throw new RuntimeException('Formatter provenance/body');}
echo "PASS 053:3courses/24chapters/24lessons/48cards/48 reviewed questions, formatter body/provenance.\n";
