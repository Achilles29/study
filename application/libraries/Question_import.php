<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/** Local document parsing only; no HTML cleaning, formula execution or remote fetch. */
class Question_import
{
 const HEADERS=array('mapel','kelas','tingkat','soal','a','b','c','d','kunci','bahasan','gambar','kesulitan');
 public function sample(){return array('matematika','sd_1','basic','Ada 2 buku, lalu ditambah 1 buku. Berapa jumlahnya?','1 buku','2 buku','3 buku','4 buku','C','Dua ditambah satu sama dengan tiga.','','mudah');}
 /**
  * Analyze without saving questions. Narrative errors are retained per source
  * number, including unanswered keys; they must never become an assumed A.
  */
 public function analyze($path, $ext, $defaults = array())
 {
  if (!is_file($path) || filesize($path) < 1 || filesize($path) > 3145728) {
   throw new RuntimeException('Berkas harus berisi data, maksimal 3 MiB.');
  }
  if ($ext === 'txt') {
   $text = file_get_contents($path);
   if (!mb_check_encoding($text, 'UTF-8')) { throw new RuntimeException('TXT harus memakai UTF-8.'); }
   $text = str_replace(array("\r\n", "\r"), "\n", preg_replace('/\A\xEF\xBB\xBF/', '', $text));
   if (!preg_match('/\A\s*(?:MAPEL|KELAS|TINGKAT|KESULITAN|SOAL|A|B|C|D|KUNCI|BAHASAN|GAMBAR):/', $text)) {
    return $this->narrative($text, $defaults);
   }
  }
  $result = array('format'=>'berlabel / tabel', 'headings'=>array(), 'errors'=>array(), 'items'=>array());
  foreach ($this->read($path, $ext) as $i=>$row) {
   $result['items'][] = array('number'=>$i+1, 'line'=>NULL, 'row'=>$row, 'errors'=>array(), 'warnings'=>array());
  }
  return $result;
 }
 private function narrative($text, $defaults)
 {
  if (substr_count($text, "\n") > 20000) { throw new RuntimeException('TXT terlalu banyak baris; maksimal 20.000 baris untuk 500 soal.'); }
  $result = array('format'=>'TXT naskah (Pustaka)', 'headings'=>array(), 'errors'=>array(), 'items'=>array());
  $current = NULL;
  $field = 'soal';
  $in_keys = FALSE;
  $keys = array();
  $seen = array();
  $declared = NULL;
  $difficulty = $defaults['kesulitan'] ?? '';
  foreach (explode("\n", $text) as $offset=>$line) {
   $trim = trim($line);
   $line_number = $offset + 1;
   if ($trim === '') { continue; }
   if (!$in_keys && substr($trim, 0, 1) === '\\' && $current !== NULL) {
    $result['items'][$current]['row'][$field] .= "\n".substr($trim, 1);
    continue;
   }
   if (preg_match('/\A(?:KUNCI(?:\s+JAWABAN)?|ANSWER\s+KEY)\s*:?\s*(.*)\z/iu', $trim, $match)) {
    if ($in_keys) { $result['errors'][] = 'Baris '.$line_number.': judul kunci jawaban berulang.'; }
    $in_keys = TRUE;
    $trim = trim($match[1]);
    if ($trim === '') { continue; }
   }
   if ($in_keys) {
    // Consume the entire line: unknown tokens must not silently disappear.
    while ($trim !== '') {
     if (!preg_match('/\A([1-9][0-9]{0,5})\s*[.):\-]\s*([a-z?\-–—])(?=\s|[,;]|\z)/iu', $trim, $match)) {
      $result['errors'][] = 'Baris '.$line_number.': format kunci tidak dikenali. Gunakan 1.a 2.b; tanda - berarti belum lengkap.';
      break;
     }
     $number = (int)$match[1];
     if (count($keys) >= 500 && !isset($keys[$number])) { throw new RuntimeException('Maksimal 500 kunci jawaban per impor.'); }
     if (isset($keys[$number])) { $result['errors'][] = 'Kunci nomor '.$number.' ditulis lebih dari sekali.'; }
     else { $keys[$number] = strtoupper($match[2]); }
     $trim = ltrim(substr($trim, strlen($match[0])), " \t,;");
    }
    continue;
   }
   if (preg_match('/\A([1-9][0-9]{0,5})\s*(?:[.)]|-\s)\s*(.*)\z/u', $trim, $match)) {
    if (count($result['items']) >= 500) { throw new RuntimeException('Maksimal 500 soal per impor.'); }
    $number = (int)$match[1];
    $row = array_fill_keys(self::HEADERS, '');
    foreach (array('mapel', 'kelas') as $name) { $row[$name] = $defaults[$name] ?? ''; }
    $row['tingkat'] = $defaults['tingkat'] ?? '';
    $row['kesulitan'] = $difficulty;
    $row['soal'] = $match[2];
    $current = count($result['items']);
    $result['items'][] = array('number'=>$number, 'line'=>$line_number, 'row'=>$row, 'errors'=>array(), 'warnings'=>array());
    if (isset($seen[$number])) {
     $result['items'][$current]['errors'][] = 'Nomor soal berulang; gunakan nomor unik.';
     $result['items'][$seen[$number]]['errors'][] = 'Nomor soal berulang; gunakan nomor unik.';
    } else { $seen[$number] = $current; }
    $field = 'soal';
    continue;
   }
   if (preg_match('/\A(?:BAGIAN|BABAK)\s+[A-Z0-9IVX]+\s*[-:–—]\s*(MUDAH|SEDANG|SULIT)(?:\s*\(.*\))?\z/iu', $trim, $match)
    || preg_match('/\A(MUDAH|SEDANG|SULIT)\z/iu', $trim, $match)) {
    $difficulty = array('MUDAH'=>'mudah','SEDANG'=>'sedang','SULIT'=>'susah')[strtoupper($match[1])];
    $current = NULL;
    continue;
   }
   if ($current === NULL) {
    if (!$result['items']) {
     $result['headings'][] = $trim;
     if (count($result['headings']) > 30) { throw new RuntimeException('Judul terlalu panjang atau nomor soal tidak dikenali. Gunakan 1. Teks soal.'); }
     if (preg_match('/\A([0-9]+)\s+Soal(?:\s+Pilihan\s+Ganda)?\s*\z/iu', $trim, $match)) { $declared = (int)$match[1]; }
    } else {
     $result['errors'][] = 'Baris '.$line_number.': teks di luar soal tidak dikenali; awali soal dengan nomor.';
    }
    continue;
   }
   if (preg_match('/\A(?:([a-e])\s*[.):]|\(([a-e])\)|([a-e])\s+-)\s*(.*)\z/iu', $trim, $match)) {
    $option = strtolower($match[1] !== '' ? $match[1] : ($match[2] !== '' ? $match[2] : $match[3]));
    if ($option === 'e') { $result['items'][$current]['errors'][] = 'Pilihan E belum didukung. Bank memakai empat pilihan A–D; jangan menghapus pilihan tanpa meninjau soal.'; }
    if (isset($result['items'][$current]['seen_'.$option])) { $result['items'][$current]['errors'][] = 'Pilihan '.strtoupper($option).' berulang.'; }
    $result['items'][$current]['seen_'.$option] = TRUE;
    $field = $option;
    $result['items'][$current]['row'][$field] = ($result['items'][$current]['row'][$field] ?? '').
     (($result['items'][$current]['row'][$field] ?? '') !== '' ? "\n" : '').$match[4];
    continue;
   }
   if (preg_match('/\A(BAHASAN|PEMBAHASAN|GAMBAR|KESULITAN):\s*(.*)\z/iu', $trim, $match)) {
    $field = strtoupper($match[1]) === 'GAMBAR' ? 'gambar' : (strtoupper($match[1]) === 'KESULITAN' ? 'kesulitan' : 'bahasan');
    if (isset($result['items'][$current]['seen_'.$field])) { $result['items'][$current]['errors'][] = 'Label '.$match[1].' berulang.'; }
    $result['items'][$current]['seen_'.$field] = TRUE;
    $result['items'][$current]['row'][$field] = $match[2];
    continue;
   }
   $result['items'][$current]['row'][$field] .= "\n".$trim;
  }
  if (!$result['items']) { $result['errors'][] = 'Tidak ditemukan soal bernomor. Gunakan 1. Pertanyaan, a. Pilihan, lalu KUNCI JAWABAN.'; }
  if (!$in_keys) { $result['errors'][] = 'Bagian KUNCI JAWABAN belum ditemukan.'; }
  if ($declared !== NULL && $declared !== count($result['items'])) {
   $result['errors'][] = 'Judul menyebut '.$declared.' soal, tetapi terbaca '.count($result['items']).' soal.';
  }
  foreach ($keys as $number=>$key) {
   if (!isset($seen[$number])) { $result['errors'][] = 'Kunci nomor '.$number.' tidak mempunyai soal.'; }
  }
  foreach ($result['items'] as &$item) {
   $key = $keys[$item['number']] ?? '';
   $item['row']['kunci'] = $key;
   if (!in_array($key, array('A','B','C','D'), TRUE)) {
    $item['errors'][] = 'Kunci '.($key === '' ? 'belum ada' : '"'.$key.'" belum valid').'; lengkapi A, B, C atau D. Jawaban tidak ditebak.';
   }
   foreach (array('a','b','c','d') as $option) {
    if (trim($item['row'][$option]) === '') { $item['errors'][] = 'Pilihan '.strtoupper($option).' belum diisi.'; }
   }
  }
  unset($item);
  return $result;
 }
 private function xml($text)
 {
  if(!is_string($text)||strlen($text)>5242880||preg_match('/<!\s*(DOCTYPE|ENTITY)/i',$text)){throw new RuntimeException('XML tidak aman atau terlalu besar.');}
  $old=libxml_use_internal_errors(TRUE);try{$xml=simplexml_load_string($text,'SimpleXMLElement',LIBXML_NONET);if($xml===FALSE){throw new RuntimeException('XML dokumen tidak valid.');}return $xml;}finally{libxml_clear_errors();libxml_use_internal_errors($old);}
 }
 private function zip($path)
 {
  $z=new ZipArchive();if($z->open($path)!==TRUE){throw new RuntimeException('Dokumen Office tidak dapat dibaca.');}$total=0;
  try{if($z->numFiles>300){throw new RuntimeException('Dokumen terlalu kompleks.');}for($i=0;$i<$z->numFiles;$i++){$s=$z->statIndex($i);if(!$s||preg_match('~(^/|(^|/)\.\.(/|$)|\\\\|\x00)~',$s['name'])||$s['size']>5242880||$s['size']>max(1024,(int)$s['comp_size'])*200){throw new RuntimeException('Arsip tidak aman atau terlalu besar.');}$total+=$s['size'];}if($total>20971520){throw new RuntimeException('Ukuran isi arsip maksimal 20 MiB.');}return $z;}catch(Throwable $e){$z->close();throw $e;}
 }
 private function table($rows)
 {
  if(!$rows){throw new RuntimeException('Dokumen kosong.');}$header=array_map(static function($x){return mb_strtolower(trim($x));},array_shift($rows));$header[0]=ltrim($header[0]??'',"\xEF\xBB\xBF");
  $legacy=array_slice(self::HEADERS,0,-1);
  if(count($header)===count(self::HEADERS)&&end($header)===''&&array_slice($header,0,-1)===$legacy)$header=$legacy;
  if($header!==self::HEADERS&&$header!==$legacy){throw new RuntimeException('Kolom harus sama dengan template: '.implode(', ',self::HEADERS).'. Kolom kesulitan boleh tidak ada pada berkas lama.');}
  $out=array();foreach($rows as $i=>$r){if(count(array_filter($r,static function($v){return trim((string)$v)!=='';}))===0){continue;}if(count($r)>count($header)&&count(array_filter(array_slice($r,count($header)),static function($v){return trim((string)$v)!=='';}))){throw new RuntimeException('Kolom berlebih pada baris '.($i+2).'.');}$out[]=array_combine(self::HEADERS,array_pad(array_slice($r,0,count($header)),count(self::HEADERS),''));}return $out;
 }
 public function read($path,$ext)
 {
  if(!is_file($path)||filesize($path)<1||filesize($path)>3145728){throw new RuntimeException('Berkas harus berisi data, maksimal 3 MiB.');}
  if($ext==='txt'){$text=file_get_contents($path);if(!mb_check_encoding($text,'UTF-8')){throw new RuntimeException('TXT harus memakai UTF-8.');}$out=array();$row=array();$current=NULL;
   foreach(explode("\n",str_replace(array("\r\n","\r"),"\n",preg_replace('/\A\xEF\xBB\xBF/','',$text))) as $line){
    if(trim($line)==='---'){if($row){$out[]=array_replace(array_fill_keys(self::HEADERS,''),$row);}$row=array();$current=NULL;continue;}
    if(preg_match('/\A(MAPEL|KELAS|TINGKAT|KESULITAN|SOAL|A|B|C|D|KUNCI|BAHASAN|GAMBAR):[ \t]?(.*)\z/u',$line,$m)){$current=strtolower($m[1]);if(array_key_exists($current,$row)){throw new RuntimeException('Label '.$m[1].' berulang. Pisahkan soal dengan --- atau awali baris isi dengan backslash.');}$row[$current]=$m[2];}
    elseif($current!==NULL){$row[$current].="\n".(substr($line,0,1)==='\\'?substr($line,1):$line);}elseif(trim($line)!==''){throw new RuntimeException('Gunakan label pada template TXT.');}
   }if($row){$out[]=array_replace(array_fill_keys(self::HEADERS,''),$row);}
  }elseif($ext==='csv'){$h=fopen($path,'rb');$rows=array();try{while(($r=fgetcsv($h,0,',','"',''))!==FALSE){$rows[]=$r;if(count($rows)>501){throw new RuntimeException('Maksimal 500 soal per impor.');}}}finally{fclose($h);}$out=$this->table($rows);
  }elseif(in_array($ext,array('docx','xlsx'),TRUE)){$z=$this->zip($path);try{
   if($ext==='docx'){$xml=$this->xml($z->getFromName('word/document.xml'));$xml->registerXPathNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');if($xml->xpath('//w:drawing|//w:pict')){throw new RuntimeException('Untuk gambar, isi nama berkas pada kolom gambar dan lampirkan PNG/JPEG terpisah; jangan tempel gambar di Word.');}$rows=array();foreach($xml->xpath('//w:tbl/w:tr') as $tr){$tr->registerXPathNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');$row=array();foreach($tr->xpath('./w:tc') as $tc){$tc->registerXPathNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');$paragraphs=array();foreach($tc->xpath('./w:p') as $p){$p->registerXPathNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');$text='';foreach($p->xpath('.//w:t|.//w:br|.//w:tab') as $t){$text.=$t->getName()==='br'?"\n":($t->getName()==='tab'?"\t":(string)$t);}$paragraphs[]=$text;}$row[]=implode("\n",$paragraphs);}$rows[]=$row;}$out=$this->table($rows);
   }else{$shared=array();$ss=$z->getFromName('xl/sharedStrings.xml');if($ss!==FALSE){$xml=$this->xml($ss);foreach($xml->si as $si){$text='';foreach($si->xpath('.//*[local-name()="t"]') as $t){$text.=(string)$t;}$shared[]=$text;}}
    $xml=$this->xml($z->getFromName('xl/worksheets/sheet1.xml'));if(isset($xml->drawing)){throw new RuntimeException('Untuk gambar Excel, gunakan kolom gambar dan lampiran PNG/JPEG terpisah.');}$rows=array();foreach($xml->sheetData->row as $r){$row=array_fill(0,count(self::HEADERS),'');foreach($r->c as $c){if(isset($c->f)){throw new RuntimeException('Rumus Excel tidak dieksekusi. Ubah sel menjadi teks/nilai terlebih dahulu.');}if(!preg_match('/\A([A-L])[1-9][0-9]*\z/',(string)$c['r'],$ref)){throw new RuntimeException('Kolom Excel harus A–L sesuai template.');}$type=(string)$c['t'];if($type==='s'){$value=$shared[(int)$c->v]??NULL;if($value===NULL){throw new RuntimeException('Referensi teks Excel tidak valid.');}}elseif($type==='inlineStr'){$value='';foreach($c->is->xpath('.//*[local-name()="t"]') as $t){$value.=(string)$t;}}else{$value=(string)$c->v;}$row[ord($ref[1])-65]=$value;}$rows[]=$row;if(count($rows)>501){throw new RuntimeException('Maksimal 500 soal per impor.');}}$out=$this->table($rows);}
  }finally{$z->close();}}
  else{throw new RuntimeException('Gunakan TXT, CSV, DOCX atau XLSX sesuai template. Format DOC/XLS lama tidak didukung.');}
  if(!$out||count($out)>500){throw new RuntimeException('Impor harus berisi 1–500 soal.');}return $out;
 }
 public function template($ext)
 {
  if ($ext === 'naskah-gambar') {
   return "CONTOH GAMBAR DALAM SOAL DAN PILIHAN\nUnggah berkas sesuai nama penanda. Kunci hanya contoh; sesuaikan dengan isi gambar Anda.\n2 Soal Pilihan Ganda\n\n6. Perhatikan tabel berikut.\n[GAMBAR: tabel6.png]\nNilai dari [GAMBAR: rumus6.png] adalah …\n   a. 9\n   b. 8\n   c. 7\n   d. 6\n\n7. Gambar manakah yang menunjukkan segitiga?\n   a. [GAMBAR: lingkaran.png]\n   b. [GAMBAR: segitiga.png]\n   c. [GAMBAR: persegi.png]\n   d. [GAMBAR: persegi-panjang.png]\n\nKUNCI JAWABAN\n6.a 7.b\n";
  }
  if ($ext === 'naskah') {
   return "CONTOH NASKAH SOAL\nPilih mapel, kelas, tingkat, dan bila perlu kesulitan pada halaman unggah.\n1 Soal Pilihan Ganda\n\n1. Ada 2 buku, lalu ditambah 1 buku. Berapa jumlahnya?\n   a. 1 buku\n   b. 2 buku\n   c. 3 buku\n   d. 4 buku\nKESULITAN: mudah\nPEMBAHASAN: Dua ditambah satu sama dengan tiga.\n\nKUNCI JAWABAN\n1.c\n";
  }
  $rows=array(self::HEADERS,$this->sample());if($ext==='txt'){$out='';foreach(self::HEADERS as $i=>$k){$out.=strtoupper($k).': '.$rows[1][$i]."\n";}return $out."---\n";}
  if($ext==='csv'){$h=fopen('php://temp','w+');foreach($rows as $row){fputcsv($h,$row,',','"','');}rewind($h);$bytes=stream_get_contents($h);fclose($h);return $bytes;}
  if(!in_array($ext,array('docx','xlsx'),TRUE)){throw new RuntimeException('Format template tidak valid.');}
  $escape=static function($s){return htmlspecialchars($s,ENT_XML1|ENT_QUOTES,'UTF-8');};$path=tempnam(sys_get_temp_dir(),'study-bank-template-');$z=new ZipArchive();
  try{if($z->open($path,ZipArchive::OVERWRITE)!==TRUE){throw new RuntimeException('Template belum tersedia.');}
   $main=$ext==='docx'?'word/document.xml':'xl/workbook.xml';$type=$ext==='docx'?'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml';
   $z->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/'.$main.'" ContentType="'.$type.'"/>'.($ext==='xlsx'?'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>':'').'</Types>');$z->addFromString('_rels/.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="'.$main.'"/></Relationships>');
   if($ext==='docx'){$xml='<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl>';foreach($rows as $row){$xml.='<w:tr>';foreach($row as $cell){$xml.='<w:tc><w:p><w:r><w:t xml:space="preserve">'.$escape($cell).'</w:t></w:r></w:p></w:tc>';}$xml.='</w:tr>';}$z->addFromString($main,$xml.'</w:tbl><w:p/></w:body></w:document>');}
   else{$z->addFromString($main,'<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Bank Soal" sheetId="1" r:id="rId1"/></sheets></workbook>');$z->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');$xml='<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';foreach($rows as $i=>$row){$xml.='<row r="'.($i+1).'">';foreach($row as $j=>$cell){$xml.='<c r="'.chr(65+$j).($i+1).'" t="inlineStr"><is><t xml:space="preserve">'.$escape($cell).'</t></is></c>';}$xml.='</row>';}$z->addFromString('xl/worksheets/sheet1.xml',$xml.'</sheetData></worksheet>');}
   $z->close();return file_get_contents($path);
  }finally{if(is_file($path)){unlink($path);}}
 }
}
