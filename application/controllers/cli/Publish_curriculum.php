<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Owner-authorized release of explicitly reviewed, immutable batches. No access/price changes. */
class Publish_curriculum extends CI_Controller
{
 private function match_content($course, $expected)
 {
  foreach (array('title'=>'title','learning_objectives'=>'objectives','prerequisites'=>'prerequisites','source_note'=>'source') as $db=>$key)
  { if ($course[$db]!==$expected[$key]) { throw new RuntimeException('Course metadata changed; review manually.'); } }
  $lessons=$this->db->query('SELECT l.* FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id=? ORDER BY s.sort_order,s.id,l.sort_order,l.id',array($course['id']))->result_array(); $index=0; $questions=array();
  $sections=$this->db->where('course_id',$course['id'])->order_by('sort_order')->order_by('id')->get('st_course_sections')->result_array();
  if (count($sections)!==count($expected['sections'])) { throw new RuntimeException('Chapter count changed.'); }
  foreach ($expected['sections'] as $si=>$section)
  {
   if ($sections[$si]['title']!==$section['title']) { throw new RuntimeException('Chapter title changed.'); }
   foreach ($section['lessons'] as $lesson)
   {
    $row=$lessons[$index++] ?? NULL;
    if (!$row || $row['title']!==$lesson['title'] || $row['body_text']!==$lesson['body'] || (int)$row['section_id']!==(int)$sections[$si]['id'] || (int)$row['estimated_minutes']!==$lesson['minutes'])
    { throw new RuntimeException('Lesson content changed; review manually.'); }
    $deck=$this->db->where('lesson_id',$row['id'])->get('st_flashcard_decks')->row_array();
    $cards=$deck ? $this->db->where('deck_id',$deck['id'])->order_by('sort_order')->order_by('id')->get('st_flashcards')->result_array() : array();
    if (count($cards)!==count($lesson['cards'])) { throw new RuntimeException('Card count changed.'); }
    foreach ($lesson['cards'] as $i=>$card)
    { if (array($cards[$i]['front_text'],$cards[$i]['back_text'],$cards[$i]['hint_text'])!==$card) { throw new RuntimeException('Card changed.'); } }
    foreach ($lesson['questions'] as $q) { $questions[]=$q; }
   }
  }
  if ($index!==count($lessons)) { throw new RuntimeException('Extra lesson detected.'); }
  $rows=$this->Question_model->list_for_course($course['id']);
  if (count($rows)!==count($questions)) { throw new RuntimeException('Question count changed.'); }
  foreach ($questions as $i=>$q)
  {
   $row=$rows[$i];
   if ($row['prompt']!==$q[0] || $row['explanation']!==$q[3] || count($row['options'])!==4) { throw new RuntimeException('Question changed.'); }
   foreach ($q[1] as $j=>$option)
   { if ($row['options'][$j]['option_text']!==$option || (int)$row['options'][$j]['is_correct']!==($q[2]===$j?1:0)) { throw new RuntimeException('Answer changed.'); } }
  }
 }

 public function index($batch='050',$mode='check',$actor='1')
 {
  if (PHP_SAPI!=='cli' || ENVIRONMENT!=='development') { show_404(); exit(1); }
  if (!in_array($mode,array('check','apply'),TRUE) || !preg_match('/\A[1-9][0-9]{0,8}\z/',(string)$actor)) { throw new InvalidArgumentException('Invalid arguments.'); }
  if ($mode==='apply' && getenv('STUDY_PUBLISH_CURRICULUM')!==$batch) { throw new RuntimeException('Publication gate disabled.'); }
  $this->load->library('Curriculum_batch'); $courses=$this->curriculum_batch->load($batch);
  $this->load->database();$this->db->db_debug=FALSE;
  if (!in_array($this->db->database,array('db_study','study_qa_monitor_sso039'),TRUE)) { throw new RuntimeException('Database target denied.'); }
  $actor=(int)$actor;
  if (!$this->db->query("SELECT u.id FROM st_users u JOIN st_user_roles ur ON ur.user_id=u.id JOIN st_roles r ON r.id=ur.role_id WHERE u.id=? AND u.status='active' AND r.role_code='system_admin'",array($actor))->num_rows()) { throw new RuntimeException('Administrator required.'); }
  $this->load->model(array('Content_model','Question_model'));
  $lock='study_curriculum_batch_'.$batch;
  if ((int)$this->db->query('SELECT GET_LOCK(?,5) locked',array($lock))->row_array()['locked']!==1) { throw new RuntimeException('Batch busy.'); }
  try
  {
   $ready=array();$report=array();
   // Inspect the complete batch before starting any transition.
   foreach ($courses as $expected)
   {
    $course=$this->db->where('slug',$expected['slug'])->get('st_courses')->row_array();
    if (!$course) { throw new RuntimeException('Import batch first.'); }
    if ($course['status']==='published') { $report[]=array('id'=>(int)$course['id'],'status'=>'already_published');continue; }
    if (!in_array($course['status'],array('draft','review'),TRUE) || (int)$course['author_user_id']!==$actor) { throw new RuntimeException('Unexpected status/author.'); }
    $audit=$this->db->where('event_type','content.curriculum_imported')->where('entity_type','course')->where('entity_id',$course['id'])->get('st_audit_events')->row_array();
    $meta=$audit ? json_decode($audit['details_json'],TRUE) : NULL;
    if (!$meta || ($meta['batch']??'')!==$batch || ($meta['source_sha256']??'')!==$expected['source_sha256']) { throw new RuntimeException('Import provenance mismatch.'); }
    $this->match_content($course,$expected);
    $setting=$this->db->where('section','pembelajaran')->get('st_site_settings')->row_array();
    if (($setting ? json_decode($setting['values_json'],TRUE)['solo_review']??'0' : '0')!=='1') { throw new RuntimeException('Enable owner review in settings; no bypass.'); }
    $course['_expected_summary']=$expected['summary'];
    $ready[]=$course;
   }
   foreach ($ready as $course)
   {
    $version=(int)$course['content_version'];
    if ($mode==='apply')
    {
     if ($course['status']==='draft')
     {
      // This known editorial label is no longer accurate once approved. Preserve all other metadata/access.
      if ($batch==='050' && strpos($course['summary'],'Draf pengembangan, ')!==FALSE)
      {
       if ($course['summary']!==$course['_expected_summary']) { throw new RuntimeException('Summary changed; manual review required.'); }
       $data=array('grade_id'=>$course['grade_id'],'target_group'=>$course['target_group'],'category_id'=>$course['category_id'],
        'title'=>$course['title'],'summary'=>str_replace('Draf pengembangan, ','Materi pengembangan, ',$course['summary']),
        'learning_objectives'=>$course['learning_objectives'],'prerequisites'=>$course['prerequisites'],
        'age_min'=>$course['age_min']===NULL?'':$course['age_min'],'age_max'=>$course['age_max']===NULL?'':$course['age_max'],
        'estimated_minutes'=>$course['estimated_minutes'],'rights_status'=>$course['rights_status'],'source_note'=>$course['source_note'],'is_free'=>$course['is_free']);
       if (!$this->Content_model->update_course_draft($course['id'],$actor,$data,FALSE,$version)) { throw new RuntimeException('Editorial revision changed or failed.'); }
       $version++;
      }
      $r=$this->Content_model->submit_for_review($course['id'],$actor); if (!$r['ok']) { throw new RuntimeException($r['message']); }
     }
     $notes='Terbit atas instruksi pemilik 18 Oktober 2026, batch '.$batch.'. Pemeriksaan agen: isi dan kunci dibandingkan naskah yang ditinjau, hak original dan kesesuaian usia diperiksa; uji akses/layout lulus. Bukan tinjauan pendidik independen. Harga dan hak akses tidak diubah.';
     $r=$this->Content_model->review_course($course['id'],$actor,'approved',$notes,TRUE,$version);
     if (!$r['ok']) { throw new RuntimeException($r['message']); }
    }
    $report[]=array('id'=>(int)$course['id'],'slug'=>$course['slug'],'status'=>$mode==='apply'?'published':'checked','version'=>$version);
   }
   echo json_encode(array('batch'=>$batch,'mode'=>$mode,'courses'=>$report),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
  }
  finally { $this->db->query('SELECT RELEASE_LOCK(?)',array($lock)); }
 }
}
