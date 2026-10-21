<?php
// Pure parser/model validation tests. No database, network, or production writes.
define('BASEPATH', __DIR__);
class CI_Model {}
function study_grades() { return array('sd_1'=>array('id'=>3,'name'=>'Kelas 1')); }
require dirname(__DIR__).'/application/libraries/Question_import.php';
require dirname(__DIR__).'/application/models/Bank_model.php';
class Import075Bank extends Bank_model {
 public function __construct() {}
 public function subjects() { return array(array('id'=>2,'slug'=>'matematika','name'=>'Matematika')); }
}
$n = 0;
function check075($ok, $message) { global $n; if (!$ok) { throw new RuntimeException($message); } $n++; }
$parser = new Question_import();
$model = new Import075Bank();
$defaults = array('mapel'=>'matematika','kelas'=>'sd_1','tingkat'=>'basic');
$tmp = tempnam(sys_get_temp_dir(), 'import075-');
function report075($text, $defaults_override=NULL, $images=array()) {
 global $parser,$model,$tmp,$defaults;
 file_put_contents($tmp, $text);
 return $model->inspect_import($parser->analyze($tmp,'txt',$defaults_override??$defaults),'licensed','Fixture pengujian dari pemilik; bukan publikasi soal lomba.',$images);
}
try {
 $sample = file_get_contents(__DIR__.'/fixtures/import075-user.txt');
 // This exact example failed with the previous parser.
 file_put_contents($tmp, $sample);
 try { $parser->read($tmp,'txt'); throw new RuntimeException('Legacy unexpectedly accepts narrative'); }
 catch (RuntimeException $e) { check075(strpos($e->getMessage(),'Gunakan label')!==FALSE,'Reproduce original strict-parser failure'); }
 $r = report075($sample);
 check075(count($r['items'])===40 && count($r['headings'])===3,'All 40 source questions and 3 headings recognized');
 check075(!$r['ready'] && $r['ready_count']===36,'Unfinished sample cannot be committed');
 check075(array_column(array_values(array_filter($r['items'],function($i){return (bool)$i['errors'];})),'number')===array(5,7,17,20),'Exact incomplete source numbers');
 check075($r['items'][4]['row']['kunci']==='-' && $r['items'][19]['row']['kunci']==='-','Missing keys never guessed');
 check075($r['items'][35]['row']['kunci']==='B','Even questionable source key retained; no automatic semantic changes');
 check075($r['items'][32]['warnings'] && !$r['items'][32]['errors'],'Textually described image gets warning');
 check075($r['items'][1]['row']['soal']==='Nilai A dan B yang memenuhi penjumlahan bersusun berikut adalah: 17 + AB = 82 (A dan B adalah digit bilangan AB).','Prompt preserved');
 check075($r['items'][39]['row']['d']==='1685, 1501, 1467, 1342, 1269','Numeric choices preserved');
 check075($r['warning_count']===40,'No explanation invented');
 $valid = "Judul contoh\n1 Soal Pilihan Ganda\n\n1. Kata bagian, sedang, mudah, bukan, kecuali, kunci jawaban tetap utuh.\nBaris kedua 3 < 5 & 7 > 2.\n\\KUNCI JAWABAN adalah teks biasa.\na. Satu\nlanjutan opsi\nb. Dua\nc. Tiga\nd. Empat\nKUNCI JAWABAN\n1.b\n";
 $r = report075($valid);
 check075($r['ready'],'Narrative without explanation valid');
 check075(strpos($r['items'][0]['row']['soal'],'kunci jawaban tetap utuh')!==FALSE && strpos($r['items'][0]['row']['soal'],"\nKUNCI JAWABAN adalah")!==FALSE,'Keyword/escaped line preserved');
 check075($r['items'][0]['row']['a']==="Satu\nlanjutan opsi",'Wrapped option preserved');
 check075($r['questions'][0]['correct']===1 && $r['questions'][0]['explanation']==='','Key correctly normalized; explanation blank');
 check075(report075("\xEF\xBB\xBF".str_replace("\n","\r\n",$valid))['ready'],'BOM and CRLF');
 foreach (array(
  str_replace('1.b','1.-',$valid),
  str_replace('1.b','1.e',$valid),
  str_replace('1.b','1.z',$valid),
  str_replace('1.b','1.b 1.a',$valid),
  str_replace('1.b','1.b 2.a',$valid),
  str_replace('1.b','1.b nonsense',$valid),
  str_replace("KUNCI JAWABAN\n1.b", '', $valid),
  str_replace("d. Empat\n", '', $valid),
  str_replace("b. Dua\n","a. Dua\n",$valid),
  str_replace('1 Soal','2 Soal',$valid),
  str_replace('d. Empat','d. Tiga',$valid),
  str_replace('d. Empat',"d. Empat\ne. Lima",$valid),
  str_replace('1. Kata','1. Kata'."\n1. Duplikat",$valid)
 ) as $i=>$invalid) { check075(!report075($invalid)['ready'],'Invalid batch blocked '.$i); }
 $r = report075($valid,array('mapel'=>'','kelas'=>'','tingkat'=>'basic'));
 check075(!$r['ready'] && count($r['errors'])===2,'No implicit grade from title');
 $variations = str_replace(array('1. Kata','a. Satu','b. Dua','c. Tiga','d. Empat','1.b'),array('7) Kata','(a) Satu','B) Dua','c: Tiga','d - Empat','7:B'),$valid);
 check075(report075($variations)['ready'],'Pustaka punctuation/case variants');
 $section = str_replace('1. Kata',"BAGIAN A - MUDAH (Soal 1-1)\n1. Kata",$valid);
 check075(report075($section)['questions'][0]['difficulty']==='basic','Easy section');
 check075(report075(str_replace('MUDAH','SEDANG',$section))['questions'][0]['difficulty']==='advanced','Medium maps to advanced explicitly');
 $image = str_replace('1. Kata','1. Lihat PDF asli. Kata',$valid);
 check075(!report075($image)['ready'],'Explicit PDF reference cannot silently publish');
 $image = str_replace("KUNCI JAWABAN\n1.b","GAMBAR: asli.png\nPEMBAHASAN: Uraian asli.\nKUNCI JAWABAN\n1.b",$image);
 check075(!report075($image)['ready'],'Missing named attachment');
 $r = report075($image,NULL,array('asli.png'=>array('sha256'=>str_repeat('a',64))));
 check075($r['ready'] && $r['questions'][0]['explanation']==='Uraian asli.','Named attachment and optional explanation');
 $duplicate = "1. Sama?\na. Satu\nb. Dua\nc. Tiga\nd. Empat\n2. Sama?\na. Satu\nb. Dua\nc. Tiga\nd. Empat\nKUNCI JAWABAN\n1.a 2.a";
 $r=report075($duplicate);
 check075($r['ready'] && strpos(implode(' ',$r['items'][1]['warnings']),'identik')!==FALSE,'Duplicate contents highlighted but same-ID import remains allowed');
 foreach (array('txt','csv','docx','xlsx') as $ext) {
  file_put_contents($tmp,$parser->template($ext));
  $r=$model->inspect_import($parser->analyze($tmp,$ext),'owned','QA original format');
  check075($r['ready'] && count($r['items'])===1,'Old template roundtrip '.$ext);
 }
 check075(report075($parser->template('naskah'))['ready'],'New downloadable narrative template');
 $many = ''; $keys = '';
 for ($i=1;$i<=500;$i++) { $many.="$i. Soal $i?\na. Satu\nb. Dua\nc. Tiga\nd. Empat\n";$keys.="$i.a "; }
 check075(report075($many."KUNCI JAWABAN\n".$keys)['ready'],'500 questions supported');
 try { report075($many."501. Tambahan\na. Satu\nb. Dua\nc. Tiga\nd. Empat\nKUNCI JAWABAN\n".$keys."501.a"); throw new RuntimeException('501 accepted'); }
 catch (RuntimeException $e) { check075(strpos($e->getMessage(),'500 soal')!==FALSE,'Reject 501 questions'); }
 try { report075("\xFF".$valid); throw new RuntimeException('Bad encoding accepted'); }
 catch (RuntimeException $e) { check075(strpos($e->getMessage(),'UTF-8')!==FALSE,'Invalid UTF-8 rejected'); }
 echo "PASS $n import075 checks: exact 40-question fixture, incomplete numbers 5/7/17/20, no guessed keys, preserved content, metadata, multiline, duplicates, images, bounds and old formats.\n";
} finally { unlink($tmp); }
