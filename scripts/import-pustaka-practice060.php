<?php
/* One-way, idempotent import. Pustaka is queried only; imported sessions stay draft. */
if (PHP_SAPI !== 'cli' || getenv('STUDY_PUSTAKA_IMPORT') !== '1' || !in_array(getenv('DB_DATABASE'),array('db_study','study_qa_monitor_sso039'),TRUE) || getenv('CI_ENV') !== 'development') exit(2);
$apply=in_array('--apply',$argv,TRUE);
$only=NULL;
foreach($argv as $arg) if(preg_match('/\A--session=([1-9][0-9]*)\z/',$arg,$m))$only=(int)$m[1];
$_SERVER['argv']=array('index.php','cli/migrate');
ob_start();require dirname(__DIR__).'/index.php';ob_end_clean();
$ci=&get_instance();$ci->load->model(array('Bank_model','Exam_model','Account_model'));
$ci->load->library('lesson_image_store');
$db=$ci->db;$db->db_debug=FALSE;$bank=$ci->Bank_model;$exams=$ci->Exam_model;$admin=$ci->Account_model->find_active_user(1);
if(!$admin)throw new RuntimeException('Administrator unavailable.');
$client=parse_ini_file('/root/security-remediation-20261022/main-mysql-current.cnf',TRUE,INI_SCANNER_RAW)['client']??NULL;
if(!$client||!isset($client['user'],$client['password']))throw new RuntimeException('Read-only Pustaka connection unavailable.');
$source=$ci->load->database(array('hostname'=>'127.0.0.1','port'=>3306,'username'=>$client['user'],'password'=>trim($client['password'],'"'),'database'=>'pustaka','dbdriver'=>'mysqli','dbprefix'=>'','pconnect'=>FALSE,'db_debug'=>FALSE,'char_set'=>'utf8mb4','dbcollat'=>'utf8mb4_unicode_ci'),TRUE);
if(!$source->conn_id)throw new RuntimeException('Pustaka connection unavailable.');
function psource($db,$sql,$args=array()){$r=$db->query($sql,$args);if(!$r)throw new RuntimeException('Pustaka read unavailable.');return $r->result_array();}
function pjson($v){return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);}
function phash($v){return hash('sha256',pjson($v));}
function pstore($db,$table,$keys,$data){$keys=(array)$keys;foreach($keys as $key)$db->where($key,$data[$key]);$old=$db->get($table)->row_array();if($old){foreach($keys as $key)unset($data[$key]);foreach($keys as $key)$db->where($key,$old[$key]);$db->update($table,$data);}else $db->insert($table,$data);if($db->trans_status()===FALSE)throw new RuntimeException('Import mapping unavailable.');}
function pmedia_owner($image){$key=$image['storage_key']??'';$root=realpath((string)getenv('STUDY_MEDIA_ROOT'));if(!$root||!preg_match('/\A[a-f0-9]{64}\.(?:jpg|png)\z/',$key))throw new RuntimeException('Imported media path invalid.');$path=$root.'/'.$key;if(!is_file($path)||realpath($path)!==$path||!chown($path,'www')||!chgrp($path,'www')||!chmod($path,0600))throw new RuntimeException('Imported media ownership unavailable.');}
$sessions=psource($source,'SELECT * FROM pustaka.quiz_sessions WHERE type=?'.($only?' AND id='.(int)$only:'').' ORDER BY id',array('practice'));
if($only&&!$sessions)throw new RuntimeException('Source session not found.');
$sid=array_column($sessions,'id');
$links=array();
foreach(array_chunk($sid,300) as $part){$place=implode(',',array_fill(0,count($part),'?'));foreach(psource($source,"SELECT * FROM pustaka.quiz_session_questions WHERE session_id IN ($place) ORDER BY session_id,sort_order,id",$part) as $link)$links[(int)$link['session_id']][]=$link;}
$shared=psource($source,"SELECT id,subject_id,grade_level_id,difficulty FROM pustaka.quiz_questions WHERE deleted_at IS NULL AND is_active=1 AND is_session_only=0 ORDER BY id");
$shared_by_scope=array();foreach($shared as $q)$shared_by_scope[(int)$q['subject_id'].':'.(int)$q['grade_level_id']][]=$q;
$source_ids=array();$needed=array();$candidates=array();
foreach($sessions as $session){
 $id=(int)$session['id'];foreach($links[$id]??array() as $link)$source_ids[(int)$link['question_id']]=TRUE;
 if($session['deleted_at']!==NULL){$candidates[$id]=array();continue;}
 $pool=array();
 if($session['question_source']==='bank_all'){
  foreach($shared_by_scope[(int)$session['subject_id'].':'.(int)$session['grade_level_id']]??array() as $q)if($session['difficulty_filter']==='mixed'||$q['difficulty']===$session['difficulty_filter'])$pool[]=(int)$q['id'];
  // Pustaka falls back to linked session-only questions when its bank pool is empty.
  if(!$pool)foreach($links[$id]??array() as $link)$pool[]=(int)$link['question_id'];
 }else foreach(array_slice($links[$id]??array(),0,max(1,(int)$session['question_count'])) as $link)$pool[]=(int)$link['question_id'];
 $candidates[$id]=array_values(array_unique($pool));foreach($pool as $qid){$source_ids[$qid]=TRUE;$needed[$qid]=TRUE;}
}
$questions=array();$options=array();$tags=array();
foreach(array_chunk(array_keys($source_ids),400) as $part){
 $place=implode(',',array_fill(0,count($part),'?'));
 foreach(psource($source,"SELECT q.*,sub.code subject_code,g.code grade_code FROM pustaka.quiz_questions q JOIN pustaka.quiz_subjects sub ON sub.id=q.subject_id JOIN pustaka.quiz_grade_levels g ON g.id=q.grade_level_id WHERE q.id IN ($place)",$part) as $q)$questions[(int)$q['id']]=$q;
 foreach(psource($source,"SELECT * FROM pustaka.quiz_question_options WHERE question_id IN ($place) ORDER BY question_id,option_index,id",$part) as $o)$options[(int)$o['question_id']][]=$o;
 foreach(psource($source,"SELECT qt.question_id,t.name FROM pustaka.quiz_question_tags qt JOIN pustaka.quiz_tags t ON t.id=qt.tag_id WHERE qt.question_id IN ($place)",$part) as $tag)$tags[(int)$tag['question_id']][]=$tag['name'];
}
$summary=array('sessions_source'=>count($sessions),'sessions_current'=>count(array_filter($sessions,static function($s){return $s['deleted_at']===NULL;})),'questions_needed'=>count($needed),'source_links'=>array_sum(array_map('count',$links)),'questions_imported'=>0,'questions_reused'=>0,'questions_unavailable'=>0,'sessions_imported'=>0,'sessions_archived_only'=>0,'links_imported'=>0,'session_issues'=>0);
if(!$apply){echo 'DRY RUN '.pjson($summary)."\n";exit(0);}
$subjects=array_column($bank->subjects(),NULL,'slug');$grades=study_grades();$mapped=array();
foreach($questions as $source_id=>$q){
 $raw=$q;$raw['options']=$options[$source_id]??array();$raw['tags']=$tags[$source_id]??array();$hash=phash($raw);
 $prior=$db->where('source_question_id',$source_id)->get('st_pustaka_import_questions')->row_array();
 if($prior&&$prior['bank_question_id']){$mapped[$source_id]=(int)$prior['bank_question_id'];if($prior['source_hash']!==$hash){$db->where('source_question_id',$source_id)->update('st_pustaka_import_questions',array('issue'=>'Sumber berubah sejak impor; periksa dan impor ulang secara manual.'));$summary['questions_unavailable']++;}continue;}
 $issue=NULL;$target=NULL;
 if(!isset($needed[$source_id]))$issue='Soal hanya tertaut pada sesi sumber yang dihapus atau di luar kumpulan aktif.';
 elseif($q['deleted_at']!==NULL||!(int)$q['is_active'])$issue='Soal sumber dihapus atau nonaktif.';
 elseif($q['type']!=='multiple_choice')$issue='Tipe soal belum didukung.';
 elseif(!isset($grades[$q['grade_code']])||!isset($subjects[str_replace('_','-',$q['subject_code'])]))$issue='Jenjang atau mata pelajaran tidak tersedia.';
 elseif(count($raw['options'])!==4||array_map('intval',array_column($raw['options'],'option_index'))!==array(0,1,2,3))$issue='Pilihan jawaban sumber tidak lengkap atau bukan A–D.';
 elseif(!in_array((int)$q['correct_option_index'],array(0,1,2,3),TRUE))$issue='Kunci jawaban sumber tidak valid.';
 elseif(preg_match('/\[Gambar\s+[^\]]+\]/iu',(string)$q['question_text'])&&!$q['question_image'])$issue='Soal merujuk gambar PDF asli yang tidak tersedia di sumber.';
 else{
  $image=NULL;$filename='';
  if($q['question_image']){
   $relative=$q['question_image'];$path='/www/wwwroot/pustaka/'.$relative;
   if(!preg_match('~\Aassets/uploads/quiz/questions/[A-Za-z0-9_./-]+\.(?:png|jpe?g)\z~i',$relative)||strpos($relative,'..')!==FALSE||!is_file($path))$issue='Gambar soal sumber hilang atau jalurnya tidak aman.';
   else{$filename=basename($path);$loaded=$ci->lesson_image_store->from_local_file($path,$filename);if($loaded['status']!=='ok')$issue='Gambar soal sumber tidak dapat disalin ke media aplikasi.';else{$image=$loaded['image'];pmedia_owner($image);$image['original_name']=$filename;}}
  }
  if($issue===NULL){
   $prompt=preg_replace('/\[Gambar(\s+[^\]]+)\]/iu','(Gambar$1)',(string)$q['question_text']);
   $row=array('mapel'=>str_replace('_','-',$q['subject_code']),'kelas'=>$q['grade_code'],'tingkat'=>'basic','kesulitan'=>$q['difficulty'],'soal'=>$prompt,'a'=>$raw['options'][0]['option_text'],'b'=>$raw['options'][1]['option_text'],'c'=>$raw['options'][2]['option_text'],'d'=>$raw['options'][3]['option_text'],'kunci'=>chr(65+(int)$q['correct_option_index']),'bahasan'=>$q['explanation']??'','gambar'=>$filename);
   try{$item=$bank->normalize_rows(array($row),'owned','Pustaka quiz_questions #'.$source_id.'; hak penggunaan menunggu tinjauan.',$image?array($filename=>$image):array())[0];$item['rights_status']='pending_review';$saved=$bank->save($admin,0,'0',$item,'draft');if(!$saved['ok'])$issue='Penyimpanan soal bank gagal (kode '.($saved['code']??0).').';else{$target=(int)$saved['id'];$mapped[$source_id]=$target;$summary[$saved['reused']?'questions_reused':'questions_imported']++;if($saved['reused']&&$image)$ci->lesson_image_store->remove($image['storage_key']);$existing=$bank->get($target);if($existing['question_difficulty']!==$q['difficulty'])$issue='Soal identik memakai ID lama dengan tag kesulitan berbeda; periksa tag di bank.';}}
   catch(Throwable $error){$issue='Isi soal tidak memenuhi validasi bank: '.mb_substr($error->getMessage(),0,180);}
  }
 }
 if($issue!==NULL){$summary['questions_unavailable']++;}
 pstore($db,'st_pustaka_import_questions','source_question_id',array('source_question_id'=>$source_id,'bank_question_id'=>$target,'source_hash'=>$hash,'source_json'=>pjson($raw),'issue'=>$issue,'imported_at'=>gmdate('Y-m-d H:i:s')));
 if(($source_id%100)===0)fwrite(STDERR,'Imported through source question '.$source_id."\n");
}
// Reused Study questions can be untagged. Assign a tag only when every mapped
// Pustaka source agrees and no existing fixed-difficulty exam would conflict.
$tag_rows=$db->query("SELECT p.bank_question_id,MIN(JSON_UNQUOTE(JSON_EXTRACT(p.source_json,'$.difficulty'))) source_tag,COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(p.source_json,'$.difficulty'))) tag_count FROM st_pustaka_import_questions p JOIN st_bank_questions b ON b.question_id=p.bank_question_id WHERE p.bank_question_id IS NOT NULL AND b.question_difficulty IS NULL GROUP BY p.bank_question_id HAVING tag_count=1")->result_array();
foreach($tag_rows as $row){
 $qid=(int)$row['bank_question_id'];$tag=$row['source_tag'];if(!in_array($tag,array('easy','medium','hard'),TRUE))continue;
 if($db->query('SELECT x.exam_id FROM st_exam_questions x JOIN st_exam_templates e ON e.id=x.exam_id WHERE x.question_id=? AND e.question_difficulty IS NOT NULL AND e.question_difficulty<>? LIMIT 1',array($qid,$tag))->num_rows())continue;
 $db->trans_begin();$bank->guard();$db->query('UPDATE st_bank_questions SET question_difficulty=? WHERE question_id=? AND question_difficulty IS NULL',array($tag,$qid));
 $db->where('id',$qid)->set('content_version','content_version+1',FALSE)->set('updated_at',gmdate('Y-m-d H:i:s'))->update('st_questions');
 $db->where('bank_question_id',$qid)->where('issue','Soal identik memakai ID lama dengan tag kesulitan berbeda; periksa tag di bank.')->update('st_pustaka_import_questions',array('issue'=>NULL));
 if($db->trans_status()===FALSE){$db->trans_rollback();throw new RuntimeException('Pustaka tag reconciliation failed.');}$db->trans_commit();
}
foreach($sessions as $session){
 $id=(int)$session['id'];$raw=$session;$raw['links']=$links[$id]??array();$hash=phash($raw);$prior=$db->where('source_session_id',$id)->get('st_pustaka_import_sessions')->row_array();
 foreach($links[$id]??array() as $link){$qid=(int)$link['question_id'];pstore($db,'st_pustaka_import_links',array('source_session_id','source_question_id'),array('source_session_id'=>$id,'source_question_id'=>$qid,'sort_order'=>(int)$link['sort_order'],'score_override'=>$link['score_override'],'bank_question_id'=>$mapped[$qid]??NULL,'issue'=>isset($mapped[$qid])?NULL:'Soal tidak masuk bank; lihat arsip pertanyaan.'));}
 if($prior&&$prior['exam_id']){
  if($prior['source_hash']!==$hash){$db->where('source_session_id',$id)->update('st_pustaka_import_sessions',array('issue'=>'Sesi sumber berubah sejak impor; tinjau ulang secara manual.'));$summary['session_issues']++;continue;}
  $target_exam=(int)$prior['exam_id'];$target=$exams->get($target_exam);$selected=array_map('intval',array_column($exams->questions($target_exam),'id'));$missing=array();
  foreach($candidates[$id]??array() as $qid){if(!isset($mapped[$qid])||in_array($mapped[$qid],$selected,TRUE))continue;$tq=$bank->get($mapped[$qid]);if(!$tq||($target['question_difficulty']!==NULL&&$tq['question_difficulty']!==$target['question_difficulty']))continue;if(!array_filter($tq['scopes'],static function($scope)use($target){return (int)$scope['grade_id']===(int)$target['grade_id'];}))continue;$missing[]=$mapped[$qid];}
  foreach(array_chunk(array_values(array_unique($missing)),100) as $part){$current=$exams->get($target_exam);$linked=$exams->select($admin,$target_exam,(string)$current['revision'],array_map('strval',$part));if(!$linked['ok'])break;$summary['links_imported']+=count($part);}
  $ready=count($exams->questions($target_exam));$expected=(int)$target['draw_count'];$db->where('source_session_id',$id)->update('st_pustaka_import_sessions',array('issue'=>$ready<$expected?'Soal siap '.$ready.' dari '.$expected.' yang diminta sumber.':NULL));if($ready<$expected)$summary['session_issues']++;
  continue;
 }
 $issue=array();$target_exam=NULL;
 if($session['deleted_at']!==NULL)$issue[]='Sesi dihapus di Pustaka; hanya metadata dan tautan sumber diarsipkan.';
 else{
  $pool=$candidates[$id]??array();$grade_code=NULL;
  if($session['grade_level_id'])foreach($questions as $q)if((int)$q['grade_level_id']===(int)$session['grade_level_id']){$grade_code=$q['grade_code'];break;}
  if(!$grade_code){$codes=array_unique(array_map(static function($qid)use($questions){return $questions[$qid]['grade_code']??'';},$pool));$codes=array_values(array_filter($codes));if(count($codes)===1)$grade_code=$codes[0];}
  if(!$grade_code){$grade_row=psource($source,'SELECT code FROM pustaka.quiz_grade_levels WHERE id=?',array($session['grade_level_id']))[0]??NULL;$grade_code=$grade_row['code']??NULL;}
  if(!$grade_code||!isset($grades[$grade_code]))$issue[]='Jenjang sesi tidak tersedia di aplikasi.';
  else{
   $valid_ids=array();$effective_tag=$session['difficulty_filter']==='mixed'?'':$session['difficulty_filter'];
   foreach($pool as $qid){if(!isset($mapped[$qid]))continue;$tq=$bank->get($mapped[$qid]);if(!$tq||!array_filter($tq['scopes'],static function($scope)use($grades,$grade_code){return (int)$scope['grade_id']===(int)$grades[$grade_code]['id'];}))continue;$valid_ids[]=(int)$mapped[$qid];if($effective_tag!==''&&$tq['question_difficulty']!==$effective_tag){$effective_tag='';$issue[]='Tag sumber tidak seragam pada kumpulan soal; simulasi disimpan sebagai campuran.';}}
   $valid_ids=array_values(array_unique($valid_ids));$expected=min(500,max(1,(int)$session['question_count']));
   if(count($valid_ids)<$expected)$issue[]='Soal siap '.count($valid_ids).' dari '.$expected.' yang diminta sumber.';
   $title=mb_substr('Pustaka · '.trim($session['title']),0,180);
   $v=array('title'=>$title,'grade_id'=>(string)$grades[$grade_code]['id'],'question_difficulty'=>$effective_tag,'draw_count'=>(string)$expected,'duration_minutes'=>(string)min(180,max(0,(int)$session['time_limit_minutes'])),'max_daily'=>(string)min(20,max(1,(int)$session['max_attempts'])),'credit_price'=>'0','revision'=>'0','status'=>'draft','question_shuffle'=>(string)(int)$session['shuffle_questions'],'option_shuffle'=>(string)(int)$session['shuffle_options'],'parent_live_enabled'=>'0','available_from'=>'','available_until'=>'')+Practice_sheet::defaults();
   $created=$exams->save($admin,0,$v);
   if(!$created['ok'])$issue[]='Gagal membuat draft simulasi (kode '.($created['code']??0).').';
   else{$target_exam=(int)$created['id'];$summary['sessions_imported']++;foreach(array_chunk($valid_ids,100) as $part){$exam=$exams->get($target_exam);$linked=$exams->select($admin,$target_exam,(string)$exam['revision'],array_map('strval',$part));if(!$linked['ok']){$issue[]='Sebagian tautan soal belum tersimpan (kode '.($linked['code']??0).').';break;}$summary['links_imported']+=count($part);}}
  }
 }
 if(!$target_exam)$summary['sessions_archived_only']++;
 if($issue)$summary['session_issues']++;
 pstore($db,'st_pustaka_import_sessions','source_session_id',array('source_session_id'=>$id,'exam_id'=>$target_exam,'source_hash'=>$hash,'source_json'=>pjson($raw),'issue'=>$issue?implode(' ',$issue):NULL,'imported_at'=>gmdate('Y-m-d H:i:s')));
}
echo 'IMPORT '.pjson($summary)."\n";
