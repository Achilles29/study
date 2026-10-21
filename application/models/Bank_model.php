<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once dirname(__DIR__).'/libraries/Question_media.php';
class Bank_model extends CI_Model
{
 public function __construct(){parent::__construct();$this->load->library('study_transaction');}
 public function rows($sql,$args=array()){$q=$this->db->query($sql,$args);if($q===FALSE){throw new RuntimeException('Question bank unavailable.');}return $q->result_array();}
 public function subjects(){return $this->rows("SELECT id,name,slug FROM st_categories WHERE status='active' ORDER BY sort_order,name");}
 public function administrator($user)
 {
  $u=$this->rows("SELECT * FROM st_users WHERE id=? AND status='active' FOR UPDATE",array($user['id']))[0]??NULL;
  return $u && hash_equals(study_credential_stamp($u),$user['auth_stamp']) && $this->rows("SELECT ur.user_id FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.role_code='system_admin' FOR UPDATE",array($u['id']));
 }
 public function guard(){$this->rows('SELECT id FROM st_bank_guard WHERE id=1 FOR UPDATE');}
 private function text($s,$min,$max){return is_string($s)&&mb_check_encoding($s,'UTF-8')&&mb_strlen(trim($s))>=$min&&mb_strlen($s)<=$max&&!preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$s);}
 private function normalized($s){$s=Normalizer::normalize(str_replace(array("\r\n","\r"),"\n",trim($s)),Normalizer::FORM_C);return preg_replace('/\s+/u',' ',$s);}
 public function fingerprint($q)
 {
  if(($q['image']['text_markers_version']??NULL)===1){
   $semantic=function($text)use($q){return $this->normalized(preg_replace_callback(Question_media::CANONICAL,function($m)use($q){return '[image:'.$this->image_at($q['image'],(string)((int)$m[1]-1))['sha256'].']';},$text));};
   $options=array();foreach($q['options'] as $i=>$option){$options[]=array($semantic($option),$i===$q['correct']);}usort($options,static function($a,$b){return strcmp($a[0],$b[0]);});
   $layout=Question_media::layout($q['image'],$q['prompt'],$q['options']);$gallery=array();
   foreach($layout['gallery'] as $i){$gallery[]=$this->image_at($q['image'],(string)$i)['sha256'];}
   return hash('sha256',json_encode(array('positioned-images-v1',$semantic($q['prompt']),$options,$gallery),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
  }
  $options=array();foreach($q['options'] as $i=>$option){$options[]=array($this->normalized($option),$i===$q['correct']);}usort($options,static function($a,$b){return strcmp($a[0],$b[0]);});
  $image_identity=$q['image']['sha256']??NULL;
  if(!empty($q['image']['additional_images'])){$image_identity=array_merge(array($image_identity),array_column($q['image']['additional_images'],'sha256'));}
  return hash('sha256',json_encode(array($this->normalized($q['prompt']),$options,$image_identity),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
 }
 public function image_at($image,$index='0')
 {
  if(!is_array($image)||!is_string($index)||!preg_match('/\A(?:0|[1-9][0-9]{0,2})\z/',$index)){return NULL;}
  return $index==='0'?$image:($image['additional_images'][(int)$index-1]??NULL);
 }
 public function normalize_rows($rows,$rights,$source,$images=array(),$subject_catalog=NULL)
 {
  if(!in_array($rights,array('owned','licensed','public_domain'),TRUE)||!$this->text($source,5,1000)){throw new RuntimeException('Pilih hak penggunaan dan isi sumber minimal 5 karakter.');}
  $subjects=$subject_catalog??array_column($this->subjects(),NULL,'slug');$grades=study_grades();$out=array();$errors=array();
  foreach($rows as $i=>$r){try{
   foreach(array('mapel','kelas','tingkat','soal','a','b','c','d','kunci','bahasan','gambar') as $k){if(!$this->text($r[$k]??NULL,in_array($k,array('gambar','bahasan'),TRUE)?0:1,in_array($k,array('soal','bahasan'),TRUE)?5000:1000)){throw new RuntimeException('Periksa isian '.$k.' (teks, batas panjang, karakter kontrol).');}}
   $subject=$subjects[trim($r['mapel'])]??NULL;$grade=$grades[trim($r['kelas'])]??NULL;$difficulty=array('basic'=>'basic','dasar'=>'basic','advanced'=>'advanced','lanjutan'=>'advanced')[trim($r['tingkat'])]??NULL;
   if(!$subject||!$grade||!$difficulty){throw new RuntimeException('Kode mapel, kelas atau tingkat tidak tersedia.');}
   $options=array_map('trim',array($r['a'],$r['b'],$r['c'],$r['d']));if(count(array_unique(array_map(array($this,'normalized'),$options)))!==4){throw new RuntimeException('Empat pilihan harus berbeda dan tidak kosong.');}
   $correct=array_search(strtoupper(trim($r['kunci'])),array('A','B','C','D'),TRUE);if($correct===FALSE){throw new RuntimeException('Kunci harus A, B, C atau D.');}
   // Keep explicit gallery order, then append images referenced in prompt/options.
   // The persisted text uses numeric indexes, so renaming a file cannot create duplicates.
   $image_name=trim($r['gambar']);$image=NULL;
   $names=$image_name!==''?array_map('trim',explode('|',$image_name)):array();
   if(in_array('',$names,TRUE)||count(array_unique($names))!==count($names)){throw new RuntimeException('Daftar GAMBAR harus berisi nama unik, dipisahkan | tanpa nama kosong.');}
   if(Question_media::references($r['bahasan'])){throw new RuntimeException('Penanda gambar saat ini digunakan pada teks soal dan pilihan A–D, bukan pembahasan.');}
   $uses_markers=FALSE;
   foreach(array_merge(array($r['soal']),$options) as $text){
    foreach(Question_media::references($text) as $ref){
     $uses_markers=TRUE;
     if(!preg_match('/\A[1-9][0-9]{0,2}\z/',$ref)&&!in_array($ref,$names,TRUE)){$names[]=$ref;}
    }
   }
   if(count($names)>500){throw new RuntimeException('Maksimal 500 gambar per soal.');}
   $image_name=implode(' | ',$names);
   if($image_name!==''){
    $attachments=array();
    foreach($names as $name){if(!isset($images[$name])){throw new RuntimeException('Lampirkan gambar dengan nama persis: '.$name);}$attachments[]=$images[$name];}
    $image=array_shift($attachments);
    if($attachments){$image['additional_images']=$attachments;}
   }
   // Existing editor sends an intact bundle under one pseudo-name.
   if(count($names)===1&&!empty($image['additional_images'])){
    $names=array_merge($names,array_map(static function($im){return $im['original_name']??'';},$image['additional_images']));
   }
   $prompt=trim($r['soal']);
   if($uses_markers){
    $prompt=Question_media::compile($prompt,$names);
    $options=array_map(static function($text)use($names){return Question_media::compile($text,$names);},$options);
    if(!$image){throw new RuntimeException('Penanda gambar membutuhkan lampiran.');}
    $image['text_markers_version']=1;
   }elseif($image){unset($image['text_markers_version']);}
   if($image&&strlen(json_encode($image,JSON_THROW_ON_ERROR))>60000){throw new RuntimeException('Metadata gambar per soal terlalu besar; kurangi jumlah gambar pada soal ini.');}
   $option_identities=array_map(function($text)use($image,$uses_markers){
    if($uses_markers){$text=preg_replace_callback(Question_media::CANONICAL,function($m)use($image){return '[image:'.($this->image_at($image,(string)((int)$m[1]-1))['sha256']??'missing').']';},$text);}
    return $this->normalized($text);
   },$options);
   if(count(array_unique($option_identities))!==4){throw new RuntimeException('Empat pilihan harus berbeda; gambar identik dengan teks yang sama bukan pilihan berbeda.');}
   $q=array('prompt'=>$prompt,'options'=>$options,'correct'=>$correct,'explanation'=>trim($r['bahasan']),'subject_id'=>(int)$subject['id'],'grade_id'=>(int)$grade['id'],'difficulty'=>$difficulty,'rights_status'=>$rights,'source_note'=>trim($source),'image'=>$image);$q['fingerprint']=$this->fingerprint($q);$out[]=$q;
  }catch(RuntimeException $e){$errors[]='Soal '.($i+1).': '.$e->getMessage();}}
  if($errors){throw new RuntimeException(implode("\n",array_slice($errors,0,12)));}return $out;
 }
 /** Read-only structural review; an invalid batch never gets a commit token. */
 public function inspect_import($analysis, $rights, $source, $images=array())
 {
  $subjects = array_column($this->subjects(), NULL, 'slug');
  $valid_rights = in_array($rights, array('owned','licensed','public_domain'), TRUE);
  $valid_source = $this->text($source, 5, 1000);
  if (!$valid_rights || !$valid_source) {
   $analysis['errors'][] = 'Pilih hak penggunaan dan isi sumber/izin minimal 5 karakter.';
  }
  if ($analysis['format'] === 'TXT naskah (Pustaka)' && $analysis['items']) {
   $first = $analysis['items'][0]['row'];
   if (!isset($subjects[$first['mapel']])) { $analysis['errors'][] = 'Pilih mata pelajaran yang tersedia pada formulir unggah.'; }
   if (!isset(study_grades()[$first['kelas']])) { $analysis['errors'][] = 'Pilih kelas yang tersedia pada formulir unggah; judul Level tidak otomatis menentukan kelas.'; }
  }
  $referenced=array();
  foreach($analysis['items'] as $item){
   foreach(explode('|',$item['row']['gambar']) as $name){if(trim($name)!==''){$referenced[trim($name)]=TRUE;}}
   foreach(array('soal','a','b','c','d') as $field){
    try{foreach(Question_media::references($item['row'][$field]) as $name){$referenced[$name]=TRUE;}}catch(RuntimeException $e){/* Row validation reports the exact malformed marker. */}
   }
  }
  // Match only unreferenced uploads against source question numbers, never DB
  // IDs, selection order, or an inferred option letter. Explicit links win.
  $by_number=array();$automatic=array();
  foreach($analysis['items'] as $index=>$item){$by_number[(int)$item['number']][]=$index;}
  $image_names=array_keys($images);
  $automatic_pattern='/\Asoal[ _-]?([1-9][0-9]{0,5})(?:[ _-]([1-9][0-9]{0,2}))?\.(?:png|jpe?g)\z/i';
  foreach($image_names as $name){
   if(isset($referenced[$name])){continue;}
   if(preg_match($automatic_pattern,$name,$match)){
    $targets=$by_number[(int)$match[1]]??array();
    if(count($targets)===1){$automatic[$targets[0]][]=$name;$referenced[$name]=TRUE;}
    elseif(count($targets)>1){$analysis['errors'][]='Lampiran "'.$name.'" cocok dengan nomor soal yang berulang. Perbaiki nomor agar gambar tidak salah dipasangkan.';$referenced[$name]=TRUE;}
   }
  }
  foreach($automatic as &$names){
   usort($names,static function($a,$b)use($automatic_pattern){
    preg_match($automatic_pattern,$a,$ma);preg_match($automatic_pattern,$b,$mb);
    return ((int)($ma[2]??0)<=>(int)($mb[2]??0))?:strnatcasecmp($a,$b)?:strcmp($a,$b);
   });
  }
  unset($names);
  foreach($images as $name=>$image){if(!isset($referenced[$name])){$analysis['errors'][]='Lampiran "'.$name.'" belum dapat dicocokkan. Gunakan nama soal7.jpg untuk nomor 7 (beberapa gambar: soal7-1.jpg, soal7-2.jpg), atau tulis [GAMBAR: '.$name.'] pada soal tujuan. Nomor harus ada dalam berkas.';}}
  $analysis['questions'] = array();
  $analysis['ready_count'] = 0;
  $analysis['warning_count'] = 0;
  $fingerprints = array();
  foreach ($analysis['items'] as $item_index=>&$item) {
   $row = $item['row'];
   $auto_images=$automatic[$item_index]??array();
   $item['auto_images']=$auto_images;
   if($auto_images){
    // Store the placement using the already versioned marker/snapshot mechanism.
    // No image positioning is re-inferred when rendering or shuffling questions.
    $prefix=array_map(static function($name){return '[GAMBAR: '.$name.']';},$auto_images);
    $row['soal']=implode("\n",$prefix)."\n".$row['soal'];
   }
   if (trim($row['bahasan']) === '') {
    $item['warnings'][] = 'Pembahasan belum diisi (opsional). Tambahkan melalui PEMBAHASAN: atau edit bank soal.';
   }
   $has_inline = preg_match('/\[GAMBAR:/iu', implode("\n",array_map(static function($f)use($row){return $row[$f];},array('soal','a','b','c','d'))));
   if (preg_match('/\bgambar\b/iu', $row['soal']) && trim($row['gambar']) === '' && !$has_inline) {
    $item['warnings'][] = 'Teks menyebut gambar tetapi belum ada lampiran. Periksa apakah soal dapat dijawab tanpa gambar.';
   }
   $needs_image = preg_match('/\bPDF\s+asli\b|\bgambar\s+(?:kiri|kanan|asli)\b/iu', $row['soal']);
   foreach (array('a','b','c','d') as $option) {
    if (preg_match('/\A\s*Gambar\s+[A-D]\s*\z/iu', $row[$option])) { $needs_image = TRUE; }
   }
   if ($needs_image && trim($row['gambar']) === '' && !$has_inline) {
    $item['errors'][] = 'Soal merujuk gambar/PDF asli yang belum dilampirkan. Isi GAMBAR: nama.png dan unggah gambar soal beserta pilihan A–D bila diperlukan.';
   }
   try {
    $q = $this->normalize_rows(array($row), $valid_rights?$rights:'owned', $valid_source?$source:'Pemeriksaan struktur', $images, $subjects)[0];
    if (isset($fingerprints[$q['fingerprint']])) {
     $item['warnings'][] = 'Isi identik dengan nomor '.$fingerprints[$q['fingerprint']].'; akan memakai ID yang sama.';
    } else { $fingerprints[$q['fingerprint']] = $item['number']; }
    if (!$item['errors']) {
     $q['import_number'] = $item['number'];
     $q['import_line'] = $item['line'];
     $q['import_warnings'] = $item['warnings'];
     $q['import_auto_images'] = $auto_images;
     $analysis['questions'][] = $q;
     $analysis['ready_count']++;
    }
   } catch (RuntimeException $e) {
    $item['errors'][] = preg_replace('/\ASoal 1: /', '', $e->getMessage());
   }
   $item['errors'] = array_values(array_unique($item['errors']));
   if ($item['warnings']) { $analysis['warning_count']++; }
  }
  unset($item);
  $analysis['ready'] = !$analysis['errors'] && count($analysis['items']) > 0 && $analysis['ready_count'] === count($analysis['items']);
  return $analysis;
 }
 public function get($id)
 {
  $q=$this->rows('SELECT q.*,b.fingerprint,b.image_json FROM st_bank_questions b JOIN st_questions q ON q.id=b.question_id WHERE q.id=?',array($id))[0]??NULL;if(!$q){return NULL;}
  $q['options']=$this->rows('SELECT * FROM st_question_options WHERE question_id=? ORDER BY sort_order,id',array($id));$q['scopes']=$this->rows('SELECT s.*,c.name subject_name,g.name grade_name FROM st_bank_scopes s JOIN st_categories c ON c.id=s.subject_id JOIN st_learning_grades g ON g.id=s.grade_id WHERE s.question_id=? ORDER BY g.sort_order,c.name',array($id));$q['image']=$q['image_json']?json_decode($q['image_json'],TRUE):NULL;return $q;
 }
 public function listing($grade,$subject,$status,$page)
 {
  $where=' FROM st_bank_questions b JOIN st_questions q ON q.id=b.question_id WHERE 1=1';$args=array();if($status!=='all'){$where.=' AND q.status=?';$args[]=$status;}
  if($grade!==''||$subject){$where.=' AND EXISTS(SELECT 1 FROM st_bank_scopes s WHERE s.question_id=q.id';if($grade!==''){$where.=' AND s.grade_id=?';$args[]=study_grades()[$grade]['id']??0;}if($subject){$where.=' AND s.subject_id=?';$args[]=$subject;}$where.=')';}
  $total=(int)$this->rows('SELECT COUNT(*) n'.$where,$args)[0]['n'];$page=min(max(1,(int)$page),max(1,(int)ceil($total/20)));return array('rows'=>$this->rows('SELECT q.id,q.prompt,q.status,q.content_version,b.image_json'.$where.' ORDER BY q.id DESC LIMIT 20 OFFSET '.(($page-1)*20),$args),'total'=>$total,'page'=>$page);
 }
 private function add_scope($id,$q){$this->db->query('INSERT INTO st_bank_scopes(question_id,subject_id,grade_id,difficulty) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE question_id=question_id',array($id,$q['subject_id'],$q['grade_id'],$q['difficulty']));}
 public function remove_scope($user,$id,$revision,$subject,$grade,$difficulty)
 {
  foreach(array($revision,$subject,$grade) as $value){if(!is_string($value)||!ctype_digit($value)){return array('ok'=>FALSE,'code'=>400);}}if(!in_array($difficulty,array('basic','advanced'),TRUE)){return array('ok'=>FALSE,'code'=>400);}
  return $this->study_transaction->run($this->db,function()use($user,$id,$revision,$subject,$grade,$difficulty){
   $this->guard();if(!$this->administrator($user)){return array('ok'=>FALSE,'code'=>403);}$q=$this->get($id);if(!$q||(int)$q['content_version']!==(int)$revision){return array('ok'=>FALSE,'code'=>409);}
   $remaining=array_filter($q['scopes'],static function($s)use($subject,$grade,$difficulty){return !((int)$s['subject_id']===(int)$subject&&(int)$s['grade_id']===(int)$grade&&$s['difficulty']===$difficulty);});if(!$remaining){return array('ok'=>FALSE,'code'=>409);}
   if(!array_filter($remaining,static function($s)use($grade){return (int)$s['grade_id']===(int)$grade;})&&$this->rows('SELECT x.exam_id FROM st_exam_questions x JOIN st_exam_templates e ON e.id=x.exam_id WHERE x.question_id=? AND e.grade_id=? LIMIT 1',array($id,$grade))){return array('ok'=>FALSE,'code'=>409);}
   $this->db->where('question_id',$id)->where('subject_id',$subject)->where('grade_id',$grade)->where('difficulty',$difficulty)->delete('st_bank_scopes');$this->db->where('id',$id)->update('st_questions',array('content_version'=>(int)$revision+1,'updated_at'=>gmdate('Y-m-d H:i:s')));$this->audit($user,'bank.scope_removed',$id,array('subject_id'=>(int)$subject,'grade_id'=>(int)$grade,'difficulty'=>$difficulty));return array('ok'=>TRUE);
  });
 }
 private function insert_or_reuse($q,$user,$status)
 {
  $existing=$this->rows('SELECT question_id FROM st_bank_questions WHERE fingerprint=?',array($q['fingerprint']))[0]??NULL;
  if($existing){$id=(int)$existing['question_id'];$this->add_scope($id,$q);return array('id'=>$id,'reused'=>TRUE);}
  $now=gmdate('Y-m-d H:i:s');$this->db->insert('st_questions',array('course_id'=>NULL,'created_by_user_id'=>$user['id'],'visibility'=>'shared','question_type'=>'single_choice','prompt'=>$q['prompt'],'explanation'=>$q['explanation'],'status'=>$status,'rights_status'=>$q['rights_status'],'source_note'=>$q['source_note'],'reviewed_by_user_id'=>$status==='published'?$user['id']:NULL,'created_at'=>$now,'updated_at'=>$now));$id=(int)$this->db->insert_id();
  foreach($q['options'] as $i=>$text){$this->db->insert('st_question_options',array('question_id'=>$id,'option_text'=>$text,'is_correct'=>$i===$q['correct']?1:0,'sort_order'=>$i));}
  $this->db->insert('st_bank_questions',array('question_id'=>$id,'fingerprint'=>$q['fingerprint'],'image_json'=>$q['image']?json_encode($q['image']):NULL));$this->add_scope($id,$q);return array('id'=>$id,'reused'=>FALSE);
 }
 public function save($user,$id,$revision,$q,$status)
 {
  if(!in_array($status,array('draft','published','archived'),TRUE)||!is_string($revision)||!ctype_digit($revision)){return array('ok'=>FALSE,'code'=>400);}
  return $this->study_transaction->run($this->db,function()use($user,$id,$revision,$q,$status){
   $this->guard();if(!$this->administrator($user)){return array('ok'=>FALSE,'code'=>403);}
   $old=$id?$this->get($id):NULL;if(($id&&!$old)||(int)$revision!==(int)($old['content_version']??0)){return array('ok'=>FALSE,'code'=>409);}
   if(!$old){$result=$this->insert_or_reuse($q,$user,$status);$id=$result['id'];}
   else{
    $other=$this->rows('SELECT question_id FROM st_bank_questions WHERE fingerprint=? AND question_id<>?',array($q['fingerprint'],$id));if($other){return array('ok'=>FALSE,'code'=>409,'existing_id'=>(int)$other[0]['question_id']);}
    $this->db->where('id',$id)->update('st_questions',array('prompt'=>$q['prompt'],'explanation'=>$q['explanation'],'status'=>$status,'rights_status'=>$q['rights_status'],'source_note'=>$q['source_note'],'reviewed_by_user_id'=>$status==='published'?$user['id']:NULL,'content_version'=>(int)$old['content_version']+1,'updated_at'=>gmdate('Y-m-d H:i:s')));
    if(count($old['options'])!==4){throw new RuntimeException('Invalid option count.');}foreach($old['options'] as $i=>$option){$this->db->where('id',$option['id'])->update('st_question_options',array('option_text'=>$q['options'][$i],'is_correct'=>$i===$q['correct']?1:0));}
    $this->db->where('question_id',$id)->update('st_bank_questions',array('fingerprint'=>$q['fingerprint'],'image_json'=>$q['image']?json_encode($q['image']):NULL));$this->add_scope($id,$q);
   }
   $this->audit($user,'bank.question_saved',$id,array('status'=>$status,'reused'=>$result['reused']??FALSE));return array('ok'=>TRUE,'id'=>$id,'reused'=>$result['reused']??FALSE);
  });
 }
 private function audit($user,$event,$id,$details){$this->db->insert('st_audit_events',array('actor_user_id'=>$user['id'],'event_type'=>$event,'entity_type'=>'bank_question','entity_id'=>$id,'details_json'=>json_encode($details),'created_at'=>gmdate('Y-m-d H:i:s')));}
 public function preview($user,$questions,$exam=NULL)
 {
  if(!$questions||count($questions)>500){return array('ok'=>FALSE,'code'=>400);}
  return $this->study_transaction->run($this->db,function()use($user,$questions,$exam){if(!$this->administrator($user)){return array('ok'=>FALSE,'code'=>403);}$template=$exam?$this->rows('SELECT id,revision,status FROM st_exam_templates WHERE id=?',array($exam))[0]??NULL:NULL;if($exam&&(!$template||$template['status']!=='draft')){return array('ok'=>FALSE,'code'=>409);}$token=bin2hex(random_bytes(32));$this->db->insert('st_bank_imports',array('owner_user_id'=>$user['id'],'token'=>$token,'payload_json'=>json_encode($questions,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'exam_id'=>$template['id']??NULL,'exam_revision'=>$template['revision']??NULL,'created_at'=>gmdate('Y-m-d H:i:s'),'expires_at'=>gmdate('Y-m-d H:i:s',time()+7200)));return array('ok'=>TRUE,'token'=>$token);});
 }
 public function import_record($user,$token){if(!is_string($token)||!preg_match('/\A[a-f0-9]{64}\z/',$token)){return NULL;}return $this->rows('SELECT * FROM st_bank_imports WHERE owner_user_id=? AND token=?',array($user,$token))[0]??NULL;}
 public function commit($user,$token,$publish)
 {
  return $this->study_transaction->run($this->db,function()use($user,$token,$publish){
   $this->guard();if(!$this->administrator($user)){return array('ok'=>FALSE,'code'=>403);}$batch=$this->import_record($user['id'],$token);if(!$batch){return array('ok'=>FALSE,'code'=>404);}if($batch['status']==='committed'){return array('ok'=>TRUE,'result'=>json_decode($batch['result_json'],TRUE),'existing'=>TRUE);}if($batch['expires_at']<gmdate('Y-m-d H:i:s')){return array('ok'=>FALSE,'code'=>409);}
   $questions=json_decode($batch['payload_json'],TRUE,512,JSON_THROW_ON_ERROR);$ids=array();$new=0;$reuse=0;$template=NULL;
   if($batch['exam_id']){$template=$this->rows('SELECT * FROM st_exam_templates WHERE id=? FOR UPDATE',array($batch['exam_id']))[0]??NULL;if(!$template||$template['status']!=='draft'||(int)$template['revision']!==(int)$batch['exam_revision']){return array('ok'=>FALSE,'code'=>409);}foreach($questions as $q){if((int)$q['grade_id']!==(int)$template['grade_id']){return array('ok'=>FALSE,'code'=>400);}}}
   foreach($questions as $q){$r=$this->insert_or_reuse($q,$user,$publish?'published':'draft');$ids[]=$r['id'];$r['reused']?$reuse++:$new++;}
   $ids=array_values(array_unique($ids));if($template){$next=(int)$this->rows('SELECT COALESCE(MAX(sort_order),-1)+1 n FROM st_exam_questions WHERE exam_id=?',array($template['id']))[0]['n'];foreach($ids as $id){$this->db->query('INSERT INTO st_exam_questions(exam_id,question_id,sort_order) VALUES(?,?,?) ON DUPLICATE KEY UPDATE question_id=question_id',array($template['id'],$id,$next++));}$this->db->where('id',$template['id'])->update('st_exam_templates',array('revision'=>(int)$template['revision']+1,'updated_at'=>gmdate('Y-m-d H:i:s')));}
   $result=array('new'=>$new,'reused'=>$reuse,'question_ids'=>$ids);$this->db->where('id',$batch['id'])->update('st_bank_imports',array('status'=>'committed','result_json'=>json_encode($result)));$this->audit($user,'bank.import_committed',NULL,array('batch_id'=>$batch['id'],'new'=>$new,'reused'=>$reuse,'exam_id'=>$batch['exam_id']));return array('ok'=>TRUE,'result'=>$result);
  });
 }
}
