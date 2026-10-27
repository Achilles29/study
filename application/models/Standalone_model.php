<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once dirname(__DIR__).'/libraries/Practice_sheet.php';

class Standalone_model extends CI_Model
{
 public function __construct(){parent::__construct();$this->load->model(array('Bank_model','Feature_credit_model','Exam_model'));}
 public function availability($grade)
 {
  $args=array();$where='';if($grade!==''){$where=' AND s.grade_id=?';$args[]=study_grades()[$grade]['id']??0;}
  return $this->Bank_model->rows("SELECT s.subject_id,s.grade_id,c.name subject_name,g.name grade_name,COUNT(DISTINCT s.question_id) total,COUNT(DISTINCT CASE WHEN b.question_difficulty='easy' THEN s.question_id END) easy_total,COUNT(DISTINCT CASE WHEN b.question_difficulty='medium' THEN s.question_id END) medium_total,COUNT(DISTINCT CASE WHEN b.question_difficulty='hard' THEN s.question_id END) hard_total,COUNT(DISTINCT CASE WHEN b.question_difficulty IS NULL THEN s.question_id END) untagged_total FROM st_bank_scopes s JOIN st_questions q ON q.id=s.question_id JOIN st_bank_questions b ON b.question_id=q.id JOIN st_categories c ON c.id=s.subject_id JOIN st_learning_grades g ON g.id=s.grade_id WHERE q.status='published' AND c.status='active'$where GROUP BY s.subject_id,s.grade_id,c.name,g.name ORDER BY g.sort_order,c.name",$args);
 }
 public function quota($user)
 {
  $v=$this->Feature_credit_model->settings()['values'];$row=$this->Bank_model->rows('SELECT * FROM st_drill_daily WHERE learner_user_id=? AND day_wib=?',array($user,date('Y-m-d')))[0]??array('used_count'=>0,'extra_count'=>0);
  $this->load->model('Lifetime_model');$unlimited=$this->Lifetime_model->covered($user);
  return array('used'=>(int)$row['used_count'],'limit'=>$unlimited?NULL:(int)$v['daily_free']+(int)$row['extra_count'],'unlimited'=>$unlimited);
 }
 public function active($user){return $this->Bank_model->rows("SELECT a.id,s.title,d.exam_id,d.expires_at FROM st_practice_attempts a JOIN st_practice_sessions s ON s.id=a.session_id JOIN st_drill_sessions d ON d.session_id=s.id WHERE a.learner_user_id=? AND a.status='in_progress' ORDER BY a.id DESC LIMIT 1",array($user))[0]??NULL;}
 public function start($user,$v,$key,$exam_id=NULL)
 {
  if(!is_string($key)||!preg_match('/\A[a-f0-9]{64}\z/',$key)){return array('ok'=>FALSE,'code'=>400);}
  if(!$exam_id){
   unset($v['difficulty']); // Legacy Dasar/Lanjutan field never filters access.
   $v['question_difficulty']=$v['question_difficulty']??'';
   foreach(array('grade_id','subject_id','count','minutes','option_shuffle') as $k){if(!is_string($v[$k]??NULL)){return array('ok'=>FALSE,'code'=>400);}}
   if(!Practice_sheet::validate($v)||!study_child_grade($v['grade_id'])||!ctype_digit($v['subject_id'])||!ctype_digit($v['count'])||(int)$v['count']<1||(int)$v['count']>50||!ctype_digit($v['minutes'])||(int)$v['minutes']>180||!in_array($v['option_shuffle'],array('0','1'),TRUE)||!is_string($v['question_difficulty'])||!in_array($v['question_difficulty'],array('','easy','medium','hard'),TRUE)){return array('ok'=>FALSE,'code'=>400);}
  }
  return $this->study_transaction->run($this->db,function()use($user,$v,$key,$exam_id){
   // Bank guard serializes snapshots with central question edits; learner lock protects quotas.
   $this->Bank_model->guard();$actor=$this->Bank_model->rows("SELECT * FROM st_users WHERE id=? AND status='active' FOR UPDATE",array($user['id']))[0]??NULL;
   if(!$actor||!hash_equals(study_credential_stamp($actor),$user['auth_stamp'])){return array('ok'=>FALSE,'code'=>403);}
   $previous=$this->Bank_model->rows('SELECT a.id FROM st_practice_sessions s JOIN st_practice_attempts a ON a.session_id=s.id JOIN st_drill_sessions d ON d.session_id=s.id WHERE s.learner_user_id=? AND s.idempotency_key=?',array($actor['id'],$key));if($previous){return array('ok'=>TRUE,'id'=>(int)$previous[0]['id']);}
   $active=$this->active($actor['id']);if($active){return array('ok'=>TRUE,'id'=>(int)$active['id'],'resumed'=>TRUE);}
   $exam=NULL;$now=gmdate('Y-m-d H:i:s');
   if($exam_id){
    $exam=$this->Exam_model->get($exam_id);if(!$exam||$exam['status']!=='published'){return array('ok'=>FALSE,'code'=>404);}
    if(!Exam_schedule::is_open($exam)){return array('ok'=>FALSE,'code'=>409,'message'=>Exam_schedule::message($exam));}
    if((int)$exam['credit_price']>0&&!$this->Feature_credit_model->owns($actor['id'],'exam',$exam_id)){return array('ok'=>FALSE,'code'=>403,'message'=>'Buka akses Simulasi Ujian ini terlebih dahulu.');}
    $start=(new DateTimeImmutable('today',new DateTimeZone('Asia/Jakarta')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $this->load->model('Lifetime_model');$used=(int)$this->Bank_model->rows('SELECT COUNT(*) n FROM st_drill_sessions d JOIN st_practice_sessions s ON s.id=d.session_id WHERE d.exam_id=? AND s.learner_user_id=? AND s.created_at>=?',array($exam_id,$actor['id'],$start))[0]['n'];if(!$this->Lifetime_model->covered($actor['id'])&&$used>=(int)$exam['max_daily']){return array('ok'=>FALSE,'code'=>409,'message'=>'Batas percobaan simulasi ini untuk hari ini sudah tercapai.');}
    $ids=array_column($this->Exam_model->questions($exam_id),'id');$minutes=(int)$exam['duration_minutes'];$shuffle=(bool)$exam['option_shuffle'];$title='Simulasi Ujian · '.$exam['title'];if($exam['question_shuffle']){shuffle($ids);}if((int)($exam['draw_count']??0)>0){if(count($ids)<(int)$exam['draw_count'])return array('ok'=>FALSE,'code'=>409,'message'=>'Kumpulan soal simulasi belum mencukupi jumlah per sesi.');$ids=array_slice($ids,0,(int)$exam['draw_count']);}
    $settings=array('exam_revision'=>(int)$exam['revision'],'grade_id'=>(int)$exam['grade_id']);
   }else{
    $quota=$this->quota($actor['id']);if(!$quota['unlimited']&&$quota['used']>=$quota['limit']){return array('ok'=>FALSE,'code'=>409,'message'=>'Kuota hari ini habis. Lanjut besok atau minta orang tua menambah kuota.');}
    $subject=$this->Bank_model->rows("SELECT name FROM st_categories WHERE id=? AND status='active'",array($v['subject_id']))[0]??NULL;if(!$subject){return array('ok'=>FALSE,'code'=>400);}
    $args=array($v['subject_id'],$v['grade_id']);$tag_filter='';if($v['question_difficulty']!==''){$tag_filter=' AND b.question_difficulty=?';$args[]=$v['question_difficulty'];}
    $ids=array_column($this->Bank_model->rows("SELECT q.id FROM st_questions q JOIN st_bank_questions b ON b.question_id=q.id WHERE q.status='published' AND EXISTS(SELECT 1 FROM st_bank_scopes s WHERE s.question_id=q.id AND s.subject_id=? AND s.grade_id=?)$tag_filter ORDER BY RAND() LIMIT ".(int)$v['count'],$args),'id');
    if(count($ids)!==(int)$v['count']){return array('ok'=>FALSE,'code'=>409,'message'=>'Soal aktif pada kelas, mapel, dan kesulitan pilihan belum cukup. Pilih jumlah lebih kecil atau gunakan campuran. Kuota tidak berkurang.');}
    $minutes=(int)$v['minutes'];$shuffle=$v['option_shuffle']==='1';$title='Latihan Mandiri · '.$subject['name'].' · '.study_grade_label($v['grade_id']).($v['question_difficulty']!==''?' · '.array('easy'=>'Mudah','medium'=>'Sedang','hard'=>'Susah')[$v['question_difficulty']]:'');$settings=$v;
   }
   if(!$ids||(!$exam&&count($ids)>50)){return array('ok'=>FALSE,'code'=>409,'message'=>'Belum ada soal yang dapat dikerjakan. Hubungi pengelola.');}$snapshots=array();
   foreach($ids as $id){$q=$this->Bank_model->get($id);if(!$q||$q['status']!=='published'||count($q['options'])!==4||array_sum(array_column($q['options'],'is_correct'))!==1){return array('ok'=>FALSE,'code'=>409,'message'=>'Ada soal yang belum siap. Pengelola perlu memeriksanya.');}if($shuffle){shuffle($q['options']);}$snapshots[$id]=array('prompt'=>$q['prompt'],'explanation'=>$q['explanation'],'content_version'=>(int)$q['content_version'],'options'=>$q['options'],'image'=>$q['image']);}
   $this->db->insert('st_practice_sessions',array('created_by_user_id'=>$actor['id'],'learner_user_id'=>$actor['id'],'title'=>$title,'session_type'=>$exam?'assessment':'self_practice','status'=>'in_progress','idempotency_key'=>$key,'rules_version'=>'equal_weight_v1','created_at'=>$now,'updated_at'=>$now));$session=(int)$this->db->insert_id();
   $sheet=$exam?Practice_sheet::read($exam['sheet_rules_json']):Practice_sheet::validate($v);
   $this->db->where('id',$session)->update('st_practice_sessions',array('sheet_rules_json'=>json_encode($sheet,JSON_THROW_ON_ERROR)));
   $this->db->insert('st_drill_sessions',array('session_id'=>$session,'exam_id'=>$exam_id,'expires_at'=>$minutes?gmdate('Y-m-d H:i:s',time()+$minutes*60):NULL,'settings_json'=>json_encode($settings,JSON_THROW_ON_ERROR)));
   foreach(array_values($ids) as $i=>$id){$this->db->insert('st_session_questions',array('session_id'=>$session,'question_id'=>$id,'sort_order'=>$i,'snapshot_json'=>json_encode($snapshots[$id],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)));}
   $this->db->insert('st_practice_attempts',array('session_id'=>$session,'learner_user_id'=>$actor['id'],'status'=>'in_progress','question_count'=>count($ids),'started_at'=>$now));$attempt=(int)$this->db->insert_id();
   if(!$exam){$this->db->query('INSERT INTO st_drill_daily(learner_user_id,day_wib,used_count) VALUES(?,?,1) ON DUPLICATE KEY UPDATE used_count=used_count+1',array($actor['id'],date('Y-m-d')));}
   $this->db->insert('st_audit_events',array('actor_user_id'=>$actor['id'],'event_type'=>'practice.started','entity_type'=>'practice_attempt','entity_id'=>$attempt,'details_json'=>json_encode(array('kind'=>$exam?'assessment':'self_practice','count'=>count($ids))),'created_at'=>$now));return array('ok'=>TRUE,'id'=>$attempt);
  });
 }
}
