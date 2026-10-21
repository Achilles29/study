<?php
// Reuse 075's database-free parser/model fixtures and regression assertions.
require __DIR__.'/test-import075.php';
$start = $n;
$row = array_combine(Question_import::HEADERS, $parser->sample());
$first = array('sha256'=>str_repeat('a',64), 'storage_key'=>str_repeat('a',64).'.png');
$second = array('sha256'=>str_repeat('b',64), 'storage_key'=>str_repeat('b',64).'.png');
$row['gambar'] = 'one.png';
$single = $model->normalize_rows(array($row),'owned','QA original',array('one.png'=>$first))[0];
$row['gambar'] = 'one.png | two.png';
$multi = $model->normalize_rows(array($row),'owned','QA original',array('one.png'=>$first,'two.png'=>$second))[0];
check075($multi['image']['sha256']===$first['sha256'] && $multi['image']['additional_images'][0]===$second,'Multiple images preserve specified order');
check075($single['fingerprint']!==$multi['fingerprint'],'Extra image is part of identity');
$row['gambar'] = 'two.png | one.png';
$reversed = $model->normalize_rows(array($row),'owned','QA original',array('one.png'=>$first,'two.png'=>$second))[0];
check075($multi['fingerprint']!==$reversed['fingerprint'],'Image order affects identity');
$row['gambar'] = 'renamed.png | other.png';
$renamed = $model->normalize_rows(array($row),'owned','QA original',array('renamed.png'=>$first,'other.png'=>$second))[0];
check075($multi['fingerprint']===$renamed['fingerprint'],'Renaming identical files does not duplicate content');
$row['gambar']='question-image';
$preserved=$model->normalize_rows(array($row),'owned','QA original',array('question-image'=>$multi['image']))[0];
check075($preserved['fingerprint']===$multi['fingerprint'],'Editing without replacement preserves gallery');
check075($model->image_at($multi['image'],'0')['sha256']===$first['sha256'] && $model->image_at($multi['image'],'1')===$second,'Safe indexed lookup and old primary endpoint');
foreach(array('2','-1','01','1000',array('1')) as $index){check075($model->image_at($multi['image'],$index)===NULL,'Invalid index rejected');}
foreach(array('one.png | missing.png','one.png | one.png','one.png |') as $names){
 $row['gambar']=$names;
 try{$model->normalize_rows(array($row),'owned','QA original',array('one.png'=>$first));throw new RuntimeException('Accepted bad names');}
 catch(RuntimeException $e){check075($e->getMessage()!=='Accepted bad names','Missing/duplicate/blank filename rejected');}
}
require dirname(__DIR__).'/application/libraries/Question_image_upload.php';
$uploader=(new ReflectionClass('Question_image_upload'))->newInstanceWithoutConstructor();
try{$uploader->read(array('name'=>array_fill(0,501,'x.png')));throw new RuntimeException('501 accepted');}
catch(RuntimeException $e){check075(strpos($e->getMessage(),'500')!==FALSE,'Upload collector independently rejects 501 files');}
echo 'PASS '.($n-$start)." additional076 image identity/order/lookup checks.\n";
