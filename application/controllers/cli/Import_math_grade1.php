<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Import_math_grade1 extends CI_Controller
{
 // Only trusted, locally drawn diagrams; this is not an upload path and accepts no external image.
 private function diagram($lesson, $root)
 {
  if(realpath($root)!==$root || is_link($root) || !is_writable($root)){throw new RuntimeException('Unsafe media path.');}
  $key=bin2hex(random_bytes(32)).'.png';$path=$root.'/'.$key;$handle=fopen($path,'x+b');
  if(!$handle){throw new RuntimeException('Diagram create failed.');}
  try
  {
   if(!chmod($path,0600)){throw new RuntimeException('Diagram permissions failed.');}
   study_math047_diagram($lesson,$handle);
   if(!fflush($handle)){throw new RuntimeException('Diagram flush failed.');}
  }
  finally{fclose($handle);}
  $info=getimagesize($path);$size=filesize($path);
  if(!$info || $info[0]!==1200 || $info[1]!==720 || $info['mime']!=='image/png' || $size<1 || $size>3145728){throw new RuntimeException('Invalid generated diagram.');}
  $image=array('storage_key'=>$key,'mime_type'=>'image/png','byte_size'=>$size,'width'=>1200,'height'=>720,'sha256'=>hash_file('sha256',$path));
  $owner=posix_getpwnam('www');
  if(!$owner || !chown($path,$owner['uid']) || !chgrp($path,$owner['gid']) || $this->lesson_image_store->read($image)===FALSE){throw new RuntimeException('Diagram ownership/read failed.');}
  return $image;
 }
 private function put($table, $values)
 {
  if ( ! $this->db->insert($table,$values)) { throw new RuntimeException('Content insert failed.'); } return (int)$this->db->insert_id();
 }
 private function audit($event, $type, $id, $data)
 {
  $this->put('st_audit_events',array('actor_user_id'=>1,'event_type'=>$event,'entity_type'=>$type,'entity_id'=>$id,'details_json'=>json_encode($data),'created_at'=>gmdate('Y-m-d H:i:s')));
 }
 public function index()
 {
  if (PHP_SAPI!=='cli' || ENVIRONMENT!=='development' || getenv('STUDY_IMPORT_MATH047')!=='1') { show_404(); exit(1); }
  $this->load->database(); if ( ! in_array($this->db->database,array('db_study','study_qa_monitor_sso039'),TRUE)) { throw new RuntimeException('Import target denied.'); }
  $this->db->db_debug=FALSE; $this->load->model(array('Question_model','Lesson_media_model')); $this->load->library('Lesson_image_store');
  $data=require APPPATH.'content_samples/math_grade1_047.php'; require FCPATH.'scripts/math-grade1-diagrams047.php';
  if (count($data['lessons'])!==6 || count($data['questions'])!==12) { throw new RuntimeException('Unexpected sample size.'); }
  $questions=array(); foreach($data['questions'] as $q){$v=array('prompt'=>$q[0],'options'=>$q[1],'correct'=>$q[2],'explanation'=>$q[3],'rights_status'=>'owned','source_note'=>'Soal dan pembahasan original Project Pintar, contoh bilangan 1–20, 18 Oktober 2026.');if(!$this->Question_model->valid_data($v)){throw new RuntimeException('Invalid question.');}$questions[]=$v;}
  if ((int)$this->db->query("SELECT GET_LOCK('study_math_grade1_047',5) locked")->row_array()['locked']!==1) { throw new RuntimeException('Import busy.'); }
  $started=FALSE;
  try
  {
   $existing=$this->db->where('slug',$data['slug'])->get('st_courses')->row_array();
   if($existing){echo json_encode(array('existing_course_id'=>(int)$existing['id'],'changed'=>FALSE,'message'=>'Existing content preserved; edit through panel.')),PHP_EOL;return;}
   if(!$this->db->query("SELECT u.id FROM st_users u JOIN st_user_roles ur ON ur.user_id=u.id JOIN st_roles r ON r.id=ur.role_id WHERE u.id=1 AND u.status='active' AND r.role_code='system_admin'")->num_rows()){throw new RuntimeException('Owner unavailable.');}
   $root=getenv('STUDY_MEDIA_ROOT'); if(!$root || !is_dir($root) || !extension_loaded('gd')){throw new RuntimeException('Private media unavailable.');}
   if($this->db->database==='db_study' && $root!=='/www/study-private/lesson-images'){throw new RuntimeException('Production media target mismatch.');}
   if($this->db->database!=='db_study' && strpos($root,'/www/study-media-qa047-')!==0){throw new RuntimeException('QA media target mismatch.');}
   $images=array(); foreach($data['lessons'] as $lesson){$images[]=$this->diagram($lesson,$root);}
   if(!$this->db->trans_begin()){throw new RuntimeException('Transaction unavailable.');}$started=TRUE;
   $package_id=$this->db->database==='db_study'?2:3;
   $package=$this->db->query('SELECT * FROM st_packages WHERE id=? FOR UPDATE',array($package_id))->row_array();
   $offers=$this->db->query('SELECT * FROM st_commerce_offers WHERE package_id=? ORDER BY id FOR UPDATE',array($package_id))->result_array();
   if(!$package || $package['title']!=='Latihan Soal SD — Paket Topik (draft)' || $package['status']!=='draft' || (int)$package['revision']!==1 || $this->db->where('package_id',$package_id)->count_all_results('st_package_courses')!==0 || count($offers)!==2){throw new RuntimeException('Package changed; manual review required.');}
   foreach($offers as $o){if($o['status']!=='draft'||$o['live_approved']||!in_array($o['kind'],array('package','voucher'),TRUE)||(int)$o['revision']!==1){throw new RuntimeException('Offer changed; manual review required.');}}
   $category=$this->db->where('slug','matematika')->get('st_categories')->row_array();if(!$category){throw new RuntimeException('Math category missing.');}
   $now=gmdate('Y-m-d H:i:s');$dates=array('created_at'=>$now,'updated_at'=>$now);
   $course=$this->put('st_courses',array('category_id'=>$category['id'],'author_user_id'=>1,'updated_by_user_id'=>1,'title'=>$data['title'],'slug'=>$data['slug'],'summary'=>$data['summary'],'learning_objectives'=>$data['objectives'],'prerequisites'=>$data['prerequisites'],'target_group'=>'primary','age_min'=>NULL,'age_max'=>NULL,'estimated_minutes'=>80,'status'=>'draft','content_version'=>1,'rights_status'=>'owned','source_note'=>$data['source'],'is_free'=>0)+$dates);
   $sections=array();foreach(array('Menghitung dan membaca bilangan','Kelompok sepuluh, perbandingan dan urutan') as $i=>$title){$sections[]=$this->put('st_course_sections',array('course_id'=>$course,'title'=>$title,'sort_order'=>$i+1)+$dates);}
   $lessons=array();foreach($data['lessons'] as $i=>$lesson)
   {
    $id=$this->put('st_lessons',array('section_id'=>$sections[$i<3?0:1],'title'=>$lesson['title'],'slug'=>$data['slug'].'-pelajaran-'.($i+1),'body_text'=>$lesson['body'],'estimated_minutes'=>$lesson['minutes'],'sort_order'=>$i+1,'status'=>'draft','content_version'=>1,'is_preview'=>$i===0?1:0,'source_note'=>$data['source'],'rights_status'=>'owned')+$dates);$lessons[]=$id;
    $this->put('st_lesson_images',$images[$i]+array('lesson_id'=>$id,'alt_text'=>$lesson['alt'],'caption'=>isset($lesson['diagram']['sequence'])?'Urutan bilangan 1–20. Baca dari kiri ke kanan, lalu lanjut ke baris berikutnya.':'Baca jumlah lingkaran yang terisi, bukan jumlah kotak. Diagram original untuk pelajaran ini.','source_note'=>'Diagram koordinat original Project Pintar; bilangan dan jumlah lingkaran dihasilkan tepat dari data. Bukan salinan gambar buku.','rights_status'=>'owned','uploader_user_id'=>1)+$dates);
    $deck=$this->put('st_flashcard_decks',array('lesson_id'=>$id,'title'=>'Ingat kembali — Pelajaran '.($i+1),'description'=>'Tiga kartu tanpa batas waktu. Coba jawab, balik kartu, lalu tandai sesuai pemahamanmu.','created_by_user_id'=>1)+$dates);
    foreach($lesson['cards'] as $j=>$card){$this->put('st_flashcards',array('deck_id'=>$deck,'front_text'=>$card[0],'back_text'=>$card[1],'hint_text'=>$card[2],'sort_order'=>$j+1)+$dates);}
    $this->audit('content.lesson_created','lesson',$id,array('course_id'=>$course,'source'=>'math_grade1_047','diagram'=>TRUE,'flashcards'=>3));
   }
   foreach($questions as $q){$id=$this->put('st_questions',array('course_id'=>$course,'created_by_user_id'=>1,'target_child_user_id'=>NULL,'visibility'=>'shared','question_type'=>'single_choice','prompt'=>$q['prompt'],'explanation'=>$q['explanation'],'status'=>'draft','content_version'=>1,'rights_status'=>'owned','source_note'=>$q['source_note'])+$dates);foreach($q['options'] as $j=>$option){$this->put('st_question_options',array('question_id'=>$id,'option_text'=>$option,'is_correct'=>$j===$q['correct']?1:0,'sort_order'=>$j));}}
   $title='Matematika Kelas 1 — Bilangan 1–20 (draft)';
   $this->db->where('id',$package_id)->update('st_packages',array('title'=>$title,'summary'=>'Satu topik kelas 1: enam pelajaran, enam diagram, 18 kartu belajar, 18 latihan terbimbing dan 12 soal evaluasi berpembahasan. Bukan seluruh materi SD. Harga/durasi belum disahkan; konten menunggu review.','revision'=>2,'updated_at'=>$now));
   $this->put('st_package_courses',array('package_id'=>$package_id,'course_id'=>$course,'sort_order'=>0));
   foreach($offers as $o){$this->db->where('id',$o['id'])->update('st_commerce_offers',array('title'=>($o['kind']==='voucher'?'Voucher ':'Akses ').$title,'revision'=>(int)$o['revision']+1,'updated_at'=>$now));}
   $this->audit('commerce.package_saved','package',$package_id,array('source'=>'math_grade1_047','previous_title'=>$package['title'],'course_ids'=>array($course),'pricing_changed'=>FALSE,'status'=>'draft'));
   $this->audit('content.course_created','course',$course,array('source'=>'math_grade1_047','status'=>'draft','lessons'=>6,'questions'=>12,'flashcards'=>18,'source_sha256'=>hash_file('sha256',APPPATH.'content_samples/math_grade1_047.php')));
   if(!$this->Question_model->ready_for_review($course)||!$this->Lesson_media_model->ready_for_review($course)||!$this->db->trans_status()){throw new RuntimeException('Content validation failed.');}
   if(!$this->db->trans_commit()){throw new RuntimeException('Commit uncertain; inspect before retry.');}$started=FALSE;
   echo json_encode(array('course_id'=>$course,'lesson_ids'=>$lessons,'package_id'=>$package_id,'status'=>'draft','lessons'=>6,'questions'=>12,'flashcards'=>18,'images'=>6,'media_root'=>$root)),PHP_EOL;
  }
  catch(Throwable $e){if($started){$this->db->trans_rollback();}throw $e;}
  finally{$this->db->query("SELECT RELEASE_LOCK('study_math_grade1_047')");}
 }
}
