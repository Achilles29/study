<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Add-only import; no publication, enrolment, package/pricing or account mutations. */
class Import_curriculum extends CI_Controller
{
 private function put($table, $data)
 {
  if (!$this->db->insert($table,$data)) { throw new RuntimeException('Insert failed; transaction must roll back.'); }
  return (int)$this->db->insert_id();
 }

 private function audit($actor, $event, $type, $id, $details)
 {
  $this->put('st_audit_events',array('actor_user_id'=>$actor,'event_type'=>$event,'entity_type'=>$type,'entity_id'=>$id,
   'details_json'=>json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'created_at'=>gmdate('Y-m-d H:i:s')));
 }

 public function index($batch='050', $mode='check', $actor='1')
 {
  if (PHP_SAPI!=='cli' || ENVIRONMENT!=='development') { show_404(); exit(1); }
  if (!in_array($mode,array('check','apply'),TRUE) || !preg_match('/\A[1-9][0-9]{0,8}\z/',(string)$actor))
  { throw new InvalidArgumentException('Invalid arguments.'); }
  if ($mode==='apply' && getenv('STUDY_IMPORT_CURRICULUM')!==$batch) { throw new RuntimeException('Apply gate disabled.'); }
  $this->load->library('Curriculum_batch');
  $courses=$this->curriculum_batch->load($batch);
  $this->load->database(); $this->db->db_debug=FALSE;
  if (!in_array($this->db->database,array('db_study','study_qa_monitor_sso039'),TRUE)) { throw new RuntimeException('Database target denied.'); }
  $actor=(int)$actor;
  if (!$this->db->query("SELECT u.id FROM st_users u JOIN st_user_roles ur ON ur.user_id=u.id JOIN st_roles r ON r.id=ur.role_id WHERE u.id=? AND u.status='active' AND r.role_code='system_admin'",array($actor))->num_rows())
  { throw new RuntimeException('Active administrator required.'); }
  $this->load->model(array('Question_model','Lesson_media_model'));
  $lock='study_curriculum_batch_'.$batch;
  if ((int)$this->db->query('SELECT GET_LOCK(?, 5) locked',array($lock))->row_array()['locked']!==1) { throw new RuntimeException('Import busy.'); }
  $started=FALSE; $report=array();
  try
  {
   // One transaction for the whole batch, including audit records.
   if (!$this->db->trans_begin()) { throw new RuntimeException('Transaction unavailable.'); } $started=TRUE;
   foreach ($courses as $course)
   {
    $existing=$this->db->query('SELECT id,status FROM st_courses WHERE slug=? FOR UPDATE',array($course['slug']))->row_array();
    if ($existing)
    {
     $audit=$this->db->where('event_type','content.curriculum_imported')->where('entity_type','course')->where('entity_id',$existing['id'])->get('st_audit_events')->row_array();
     $meta=$audit ? json_decode($audit['details_json'],TRUE) : NULL;
     if (!$meta || ($meta['batch'] ?? '')!==$batch || ($meta['key'] ?? '')!==$course['key'] || ($meta['source_sha256'] ?? '')!==$course['source_sha256'])
     { throw new RuntimeException('Existing slug/source conflict; preserve data and review manually.'); }
     $report[]=array('course_id'=>(int)$existing['id'],'slug'=>$course['slug'],'action'=>'preserved','status'=>$existing['status']);
     continue;
    }
    $category=$this->db->where('slug',$course['category'])->get('st_categories')->row_array();
    $grade=$this->db->where('code',$course['grade'])->get('st_learning_grades')->row_array();
    if (!$category || !$grade) { throw new RuntimeException('Required master missing.'); }
    $lesson_count=$question_count=$card_count=$minutes=0;
    foreach ($course['sections'] as $s) { foreach ($s['lessons'] as $l) {
     $lesson_count++; $question_count+=count($l['questions']); $card_count+=count($l['cards']); $minutes+=$l['minutes'];
     foreach ($l['questions'] as $q) {
      if (!$this->Question_model->valid_data(array('prompt'=>$q[0],'options'=>$q[1],'correct'=>$q[2],'explanation'=>$q[3],'rights_status'=>'owned','source_note'=>$course['source'])))
      { throw new RuntimeException('Question rejected by application validator.'); }
     }
    } }
    if ($mode==='check')
    {
     $report[]=array('slug'=>$course['slug'],'action'=>'would_create','chapters'=>count($course['sections']),'lessons'=>$lesson_count,'questions'=>$question_count,'cards'=>$card_count);
     continue;
    }
    $now=gmdate('Y-m-d H:i:s'); $dates=array('created_at'=>$now,'updated_at'=>$now);
    $id=$this->put('st_courses',array('category_id'=>$category['id'],'grade_id'=>$grade['id'],'author_user_id'=>$actor,'updated_by_user_id'=>$actor,
     'title'=>$course['title'],'slug'=>$course['slug'],'summary'=>$course['summary'],'learning_objectives'=>$course['objectives'],
     'prerequisites'=>$course['prerequisites'],'target_group'=>'primary','age_min'=>NULL,'age_max'=>NULL,'estimated_minutes'=>$minutes,
     'status'=>'draft','content_version'=>1,'rights_status'=>'owned','source_note'=>$course['source'],'is_free'=>0)+$dates);
    $ordinal=0; $lesson_ids=array();
    foreach ($course['sections'] as $si=>$section)
    {
     $section_id=$this->put('st_course_sections',array('course_id'=>$id,'title'=>$section['title'],'sort_order'=>$si+1)+$dates);
     foreach ($section['lessons'] as $li=>$lesson)
     {
      $ordinal++;
      $lesson_id=$this->put('st_lessons',array('section_id'=>$section_id,'title'=>$lesson['title'],'slug'=>$course['slug'].'-pelajaran-'.$ordinal,
       'body_text'=>$lesson['body'],'estimated_minutes'=>$lesson['minutes'],'sort_order'=>$li+1,'status'=>'draft','content_version'=>1,
       'is_preview'=>0,'rights_status'=>'owned','source_note'=>$course['source'])+$dates); $lesson_ids[]=$lesson_id;
      $deck=$this->put('st_flashcard_decks',array('lesson_id'=>$lesson_id,'title'=>'Ingat kembali — '.$lesson['title'],
       'description'=>'Coba jawab sebelum membuka sisi belakang. Tidak ada batas waktu.','created_by_user_id'=>$actor)+$dates);
      foreach ($lesson['cards'] as $ci=>$card)
      { $this->put('st_flashcards',array('deck_id'=>$deck,'front_text'=>$card[0],'back_text'=>$card[1],'hint_text'=>$card[2],'sort_order'=>$ci+1)+$dates); }
      foreach ($lesson['questions'] as $q)
      {
       $qid=$this->put('st_questions',array('course_id'=>$id,'created_by_user_id'=>$actor,'target_child_user_id'=>NULL,'visibility'=>'shared',
        'question_type'=>'single_choice','prompt'=>$q[0],'explanation'=>$q[3],'status'=>'draft','content_version'=>1,'rights_status'=>'owned','source_note'=>$course['source'])+$dates);
       foreach ($q[1] as $qi=>$option)
       { $this->put('st_question_options',array('question_id'=>$qid,'option_text'=>$option,'is_correct'=>$qi===$q[2]?1:0,'sort_order'=>$qi)); }
       $this->audit($actor,'content.question_created','question',$qid,array('course_id'=>$id,'batch'=>$batch,'lesson_id'=>$lesson_id));
      }
      $this->audit($actor,'content.lesson_created','lesson',$lesson_id,array('course_id'=>$id,'batch'=>$batch,'cards'=>count($lesson['cards'])));
     }
    }
    if (!$this->Question_model->ready_for_review($id) || !$this->Lesson_media_model->ready_for_review($id))
    { throw new RuntimeException('Application content checks failed.'); }
    $this->audit($actor,'content.curriculum_imported','course',$id,array('batch'=>$batch,'key'=>$course['key'],'source_sha256'=>$course['source_sha256'],
     'status'=>'draft','lessons'=>$lesson_count,'questions'=>$question_count,'cards'=>$card_count,'packages_changed'=>FALSE));
    $report[]=array('course_id'=>$id,'slug'=>$course['slug'],'action'=>'created_draft','lesson_ids'=>$lesson_ids,'questions'=>$question_count,'cards'=>$card_count);
   }
   if (!$this->db->trans_status()) { throw new RuntimeException('Transaction failed.'); }
   if ($mode==='check') { $this->db->trans_rollback(); }
   elseif (!$this->db->trans_commit()) { throw new RuntimeException('Commit uncertain; inspect before retry.'); }
   $started=FALSE;
   echo json_encode(array('batch'=>$batch,'mode'=>$mode,'courses'=>$report),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
  }
  catch (Throwable $e) { if ($started) { $this->db->trans_rollback(); } throw $e; }
  finally { $this->db->query('SELECT RELEASE_LOCK(?)',array($lock)); }
 }
}
