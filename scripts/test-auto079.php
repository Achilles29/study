<?php
require __DIR__.'/test-media077.php';
$start=$n;$tmp=tempnam(sys_get_temp_dir(),'auto079-');
try{
 $text="7. Perhatikan gambar.\na. Satu\nb. Dua\nc. Tiga\nd. Empat\n18. Bandingkan [GAMBAR: khusus.png].\na. Lima\nb. Enam\nc. Tujuh\nd. Delapan\nKUNCI JAWABAN\n7.a 18.b";
 $image=static function($name){return array('sha256'=>hash('sha256',$name),'original_name'=>$name);};
 $images=array();foreach(array('soal18.jpg','soal7-10.jpg','soal7-2.png','khusus.png','soal7-1.jpg') as $name){$images[$name]=$image($name);}
 $r=report075($text,NULL,$images);
 check075($r['ready'],'Automatic + explicit batch accepted');
 check075($r['questions'][0]['import_auto_images']===array('soal7-1.jpg','soal7-2.png','soal7-10.jpg'),'Natural sorting independent of selection order');
 check075($r['questions'][0]['prompt']==="[GAMBAR:1]\n[GAMBAR:2]\n[GAMBAR:3]\nPerhatikan gambar.",'Automatic images precede original prompt');
 check075($r['questions'][1]['prompt']==="[GAMBAR:1]\nBandingkan [GAMBAR:2].",'Auto prepends without displacing named special marker');
 check075($r['questions'][1]['import_auto_images']===array('soal18.jpg'),'Use source number18, not row index2');
 check075(Question_media::layout($r['questions'][0]['image'],$r['questions'][0]['prompt'],$r['questions'][0]['options'])['gallery']===array(),'Auto does not duplicate into legacy gallery');
 $expected=$r['questions'][0]['fingerprint'];
 check075(report075($text,NULL,array_reverse($images,TRUE))['questions'][0]['fingerprint']===$expected,'Reversed upload order keeps fingerprint');
 $withBase=report075($text,NULL,$images+array('soal7.jpg'=>$image('soal7.jpg')));
 check075($withBase['questions'][0]['import_auto_images']===array('soal7.jpg','soal7-1.jpg','soal7-2.png','soal7-10.jpg'),'Unsuffixed main image precedes numbered extra images');
 $manual=str_replace('7. Perhatikan gambar.',"7. [GAMBAR: soal7-1.jpg]\n[GAMBAR: soal7-2.png]\n[GAMBAR: soal7-10.jpg]\nPerhatikan gambar.",$text);
 $r=report075($manual,NULL,$images);
 check075($r['ready']&&$r['questions'][0]['fingerprint']===$expected&&!$r['questions'][0]['import_auto_images'],'Equivalent manual placement dedups to same identity');
 $moved=str_replace('[GAMBAR: khusus.png]','[GAMBAR: soal7-1.jpg] dan [GAMBAR: khusus.png]',$text);
 $r=report075($moved,NULL,$images);
 check075($r['ready']&&count($r['questions'][0]['import_auto_images'])===2&&!in_array('soal7-1.jpg',$r['questions'][0]['import_auto_images'],TRUE),'Explicit reference on another question takes priority');
 foreach(array('random.png','soal99.png','soal7a.png','prefix-soal7.png','soal0.png') as $name){
  $r=report075($text,NULL,$images+array($name=>$image($name)));
  check075(!$r['ready']&&strpos(implode(' ',$r['errors']),$name)!==FALSE,'Unknown/ambiguous filename clearly blocked: '.$name);
 }
 $duplicate=str_replace('18. Bandingkan','7. Bandingkan',$text);
 $r=report075($duplicate,NULL,$images);check075(!$r['ready']&&strpos(implode(' ',$r['errors']),'berulang')!==FALSE,'Duplicate source number never guessed');
 foreach(array('soal7.jpg','SOAL7.PNG','soal_7.jpeg','soal-7.png','soal 7.png') as $name){
  $r=report075("7. Pertanyaan?\na. Satu\nb. Dua\nc. Tiga\nd. Empat\nKUNCI JAWABAN\n7.a",NULL,array($name=>$image($name)));
  check075($r['ready']&&$r['questions'][0]['import_auto_images']===array($name),'Supported number naming '.$name);
 }
 $r=report075("7. Pertanyaan?\na. [GAMBAR: soal7.jpg]\nb. Dua\nc. Tiga\nd. Empat\nKUNCI JAWABAN\n7.a",NULL,array('soal7.jpg'=>$image('soal7.jpg')));
 check075($r['ready']&&!$r['questions'][0]['import_auto_images']&&$r['questions'][0]['prompt']==='Pertanyaan?','Explicit answer image never moved to question top');
 foreach(array('txt','csv','docx','xlsx') as $ext){
  file_put_contents($tmp,$parser->template($ext));
  $r=$model->inspect_import($parser->analyze($tmp,$ext),'owned','QA auto079',array('soal1.png'=>$image('soal1.png')));
  check075($r['ready']&&strpos($r['questions'][0]['prompt'],"[GAMBAR:1]\n")===0,'Table/label question sequence auto '.$ext);
 }
}finally{unlink($tmp);}
echo 'PASS '.($n-$start)." additional079 checks: auto mapping/source numbers/natural ordering, explicit precedence, ambiguity, legacy formats and dedup.\n";
