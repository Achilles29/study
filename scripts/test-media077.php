<?php
require __DIR__.'/test-images076.php';
$start=$n;
$names=array('tabel6.png','rumus6.png','lingkaran.png','segitiga.png','persegi.png','persegi-panjang.png');
$images=array();foreach($names as $i=>$name){$images[$name]=array('sha256'=>hash('sha256',$name),'original_name'=>$name,'storage_key'=>hash('sha256',$name).'.png');}
$tmp=tempnam(sys_get_temp_dir(),'media077-');
try{
 $text=$parser->template('naskah-gambar');
 $r=report075($text,NULL,$images);
 check075($r['ready']&&count($r['questions'])===2,'New downloadable image template fully parsed');
 $q=$r['questions'][0];$choices=$r['questions'][1];
 check075($q['prompt']==="Perhatikan tabel berikut.\n[GAMBAR:1]\nNilai dari [GAMBAR:2] adalah …",'Exact text/image/text positions');
 check075($choices['options']===array('[GAMBAR:1]','[GAMBAR:2]','[GAMBAR:3]','[GAMBAR:4]'),'Image-only answers compile');
 check075(Question_media::layout($q['image'],$q['prompt'],$q['options'])['gallery']===array(),'Positioned images not duplicated in gallery');
 $layout=Question_media::layout($choices['image'],$choices['prompt'],$choices['options']);
 $render=Question_media::render($choices['options'][1],$layout,static function($i){return '/private/'.$i;},'pilihan jawaban');
 check075(strpos($render,'src="/private/1"')!==FALSE&&strpos($render,'segitiga.png')===FALSE,'Option rendered at correct private index without filenames');
 check075(strpos(Question_media::render('<script>x</script> [GAMBAR:1]',$layout,static function(){return '/image?a=1&b=2';}),'&lt;script&gt;')!==FALSE,'Raw HTML never rendered');
 check075(strpos(Question_media::render('[GAMBAR:1]',array('enabled'=>FALSE),static function(){throw new RuntimeException('legacy fetched');}),'[GAMBAR:1]')!==FALSE,'Legacy markers remain literal');
 $renamed=array();foreach($images as $name=>$im){$renamed['renamed-'.$name]=$im;}
 check075(report075(str_replace($names,array_map(static function($n){return 'renamed-'.$n;},$names),$text),NULL,$renamed)['questions'][1]['fingerprint']===$choices['fingerprint'],'File rename reuses identity');
 $swapped=str_replace(array('a. [GAMBAR: lingkaran.png]','b. [GAMBAR: segitiga.png]','7.b'),array('a. [GAMBAR: segitiga.png]','b. [GAMBAR: lingkaran.png]','7.a'),$text);
 check075(report075($swapped,NULL,$images)['questions'][1]['fingerprint']===$choices['fingerprint'],'Shuffle with matching key preserves semantic identity');
 foreach(array(
  str_replace('rumus6.png','missing.png',$text),
  str_replace('[GAMBAR: rumus6.png]','[GAMBAR: rumus6.png',$text),
  str_replace('[GAMBAR: rumus6.png]','[GAMBAR:]',$text),
  str_replace('[GAMBAR: rumus6.png]','[GAMBAR: https://example.com/a.png]',$text),
  str_replace('[GAMBAR: rumus6.png]','[GAMBAR: ../../outside.png]',$text),
  str_replace('[GAMBAR: rumus6.png]','[GAMBAR:500]',$text),
  str_replace('7.b','PEMBAHASAN: [GAMBAR: rumus6.png]'."\n7.b",$text),
 ) as $bad){check075(!report075($bad,NULL,$images)['ready'],'Invalid marker or attachment rejected as whole batch');}
 $duplicateImages=$images;$duplicateImages['segitiga.png']['sha256']=$images['lingkaran.png']['sha256'];
 check075(!report075($text,NULL,$duplicateImages)['ready'],'Identical image-only choices rejected even with different names');
 $multi=str_replace('a. [GAMBAR: lingkaran.png]','a. Bentuk [GAMBAR: lingkaran.png] dan [GAMBAR: segitiga.png]',$text);
 check075(report075($multi,NULL,$images)['ready'],'Multiple images plus text in one choice');
 $mixed=str_replace('   a. 9',"   a. 9",$text);
 $mixed=str_replace("   d. 6\n","   d. 6\nGAMBAR: galeri.png\n",$mixed);
 $extra=$images+array('galeri.png'=>array('sha256'=>hash('sha256','gallery'),'original_name'=>'galeri.png'));
 $qr=report075($mixed,NULL,$extra)['questions'][0];
 check075(Question_media::layout($qr['image'],$qr['prompt'],$qr['options'])['gallery']===array(0),'Legacy gallery plus positioned images coexist');
 // Real CSV/DOCX/XLSX files with markers, preserving the existing 11 columns.
 $row=array_combine(Question_import::HEADERS,$parser->sample());$row['soal']='Nilai [GAMBAR: tabel6.png] dan [GAMBAR: rumus6.png]?';
 $row['a']='[GAMBAR: lingkaran.png]';$row['b']='[GAMBAR: segitiga.png]';$row['c']='[GAMBAR: persegi.png]';$row['d']='[GAMBAR: persegi-panjang.png]';$row['kunci']='B';
 class Media077Parser extends Question_import { public $fixture;public function sample(){return array_values($this->fixture);} }
 $tabParser=new Media077Parser();$tabParser->fixture=$row;
 foreach(array('txt','csv','docx','xlsx') as $ext){
  file_put_contents($tmp,$tabParser->template($ext));
  $result=$model->inspect_import($parser->analyze($tmp,$ext),'owned','Synthetic QA077',$images);
  check075($result['ready']&&$result['questions'][0]['options'][1]==='[GAMBAR:4]','Markers roundtrip '.$ext);
 }
}finally{unlink($tmp);}
echo 'PASS '.($n-$start)." additional077 checks: exact positions, image-only/multiple choices, legacy gallery, invalid markers, escaping, dedup/rename/shuffle, TXT/CSV/DOCX/XLSX.\n";
