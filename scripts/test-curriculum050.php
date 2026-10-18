<?php
define('BASEPATH',dirname(__DIR__).'/system/');
define('APPPATH',dirname(__DIR__).'/application/');
require APPPATH.'libraries/Curriculum_batch.php';
require BASEPATH.'core/Model.php';
require APPPATH.'models/Question_model.php';
$loader=new Curriculum_batch(); $courses=$loader->load('050'); $validator=new Question_model();
function require050($ok,$message){if(!$ok){throw new RuntimeException($message);}}
$counts=array(0,0,0,0);$answers=array();
foreach($courses as $course){
 require050(count($course['sections'])===6,'Six chapters per course');
 foreach($course['sections'] as $s){$counts[0]++; foreach($s['lessons'] as $l){$counts[1]++;$counts[2]+=count($l['cards']);
  foreach($l['questions'] as $q){$counts[3]++; require050($validator->valid_data(array('prompt'=>$q[0],'options'=>$q[1],'correct'=>$q[2],'explanation'=>$q[3],'rights_status'=>'owned','source_note'=>$course['source'])),'Question validator'); $answers[]=$q[1][$q[2]];}
 }}
}
require050($counts===array(12,24,48,48),'Exact batch counts');
$expected=array('5','0','13','17','7','6','3','9 + 4 = 13','6 = 3 + 3','3','merah','6','segitiga','lingkaran','kerucut','di bawah','pita B','kartu sama besar disusun tanpa celah','B','A','lingkaran','3','3','Kartu apel terbanyak dalam kumpulan itu','Good morning','Goodbye','What is your name?','Lani','bag','Ini sebuah kursi','menutup buku','Thank you','3','eight','hijau','It is red.','saudara laki-laki','Dito','senang','menanyakan kabar','head','dua tangan','walk','berdiri','fish','cat','Aku suka warna biru','Dito');
require050($answers===$expected,'Reviewed answer key/order');
// Mutation tests: reject incomplete lessons, invalid/duplicate keys, duplicate options and wrong answer indexes.
for($i=0;$i<5;$i++){
 $bad=$courses;
 if($i===0){$bad[0]['sections'][0]['lessons'][0]['body']='placeholder';}
 if($i===1){$bad[0]['sections'][0]['lessons'][0]['questions'][0][2]=4;}
 if($i===2){$bad[0]['sections'][0]['lessons'][0]['questions'][0][1]=array('A','a','B','C');}
 if($i===3){$bad[1]['slug']=$bad[0]['slug'];}
 if($i===4){$bad[0]['grade']='sd_7';}
 $rejected=FALSE;try{$loader->validate($bad);}catch(InvalidArgumentException $e){$rejected=TRUE;}
 require050($rejected,'Invalid fixture rejected '.$i);
}
try{$loader->load('../../config/database');throw new RuntimeException('Unsafe batch allowed');}catch(InvalidArgumentException $e){}
echo 'PASS 2 courses, 12 chapters, 24 lessons, 48 cards, 48 questions; answer key, application validation and invalid fixtures.',PHP_EOL;
