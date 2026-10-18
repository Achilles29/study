<?php
defined('BASEPATH') OR exit('No direct script access allowed');
// Immutable formatting only. Teaching content, examples and keys are authored per lesson.
return static function (array $course, array $rows) {
 $course['builder_version']=1;$course['sections']=array();
 foreach($rows as $r){
  if(count($r)!==9||count($r[5])!==3||count($r[6])!==3||count($r[8])!==2){throw new InvalidArgumentException('Incomplete authored lesson');}
  [$chapter,$title,$goal,$explanation,$example,$tasks,$answers,$guidance,$questions]=$r;
  $numbered=static function($items){$out=array();foreach($items as $i=>$text){$out[]=($i+1).'. '.$text;}return implode("\n",$out);};
  $body="## Tujuan belajar\n".$goal."\n\n## Ayo pahami\n".$explanation."\n\n## Contoh bersama\n".$example."\n\n## Coba sendiri\n".$numbered($tasks)."\n\n## Cek dan ceritakan\n".$numbered($answers)."\n\n## Untuk pendamping\n".$guidance."\n\n## Aku sudah bisa\nCeritakan kembali gagasan utama pelajaran ini dengan contohmu sendiri. Tunjukkan bagian yang masih perlu bantuan; ulangi contoh bersama sebelum melanjutkan.";
  $cards=array();for($i=0;$i<2;$i++){$cards[]=array($tasks[$i],'Jawaban: '.$answers[$i],'Coba jawab dahulu; kemudian periksa contoh pelajaran.');}
  $lesson=array('title'=>$title,'minutes'=>20,'body'=>$body,'cards'=>$cards,'questions'=>$questions);
  $last=count($course['sections'])-1;
  if($last<0||$course['sections'][$last]['title']!==$chapter){$course['sections'][]=array('title'=>$chapter,'lessons'=>array());$last++;}
  $course['sections'][$last]['lessons'][]=$lesson;
 }
 return $course;
};
