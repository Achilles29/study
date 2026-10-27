<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class English_course_model extends CI_Model
{
 public function __construct(){parent::__construct();$this->load->library(array('english_course_content','study_transaction'));}
 private function rows($sql,$args=array()){$r=$this->db->query($sql,$args);if(!$r)throw new RuntimeException('English data unavailable.');return $r->result_array();}
 public function settings(){return $this->rows('SELECT * FROM st_english_settings WHERE id=1')[0];}
 public function levels(){return array_column($this->rows('SELECT * FROM st_english_levels'),NULL,'level_id');}
 public function available($level){$s=$this->settings();$l=$this->levels();return (int)$s['enabled']===1&&isset($l[$level])&&$l[$level]['status']==='published';}
 private function guard($user,$level)
 {
  $s=$this->rows('SELECT * FROM st_english_settings WHERE id=1 FOR UPDATE')[0];
  $l=$this->rows('SELECT * FROM st_english_levels WHERE level_id=? FOR UPDATE',array($level))[0]??NULL;
  $u=$this->rows('SELECT id,status FROM st_users WHERE id=? FOR UPDATE',array($user))[0]??NULL;
  return $u&&$u['status']==='active'&&(int)$s['enabled']===1&&$l&&$l['status']==='published'?$l:NULL;
 }
 public function progress($user){return array_column($this->rows('SELECT * FROM st_english_progress WHERE learner_user_id=? AND content_version=?',array($user,$this->english_course_content->version())),NULL,'unit_id');}
 public function portfolio($user,$unit=NULL,$page=1){$args=array($user,$this->english_course_content->version());$where='';if($unit!==NULL){$where=' AND unit_id=?';$args[]=$unit;}return $this->rows('SELECT id,unit_id,task_kind,status,created_at,reviewed_at,feedback,scores_json FROM st_english_submissions WHERE learner_user_id=? AND content_version=?'.$where.' ORDER BY id DESC LIMIT 20 OFFSET '.(max(0,(int)$page-1)*20),$args);}
 public function portfolio_count($user){return (int)$this->rows('SELECT COUNT(*) n FROM st_english_submissions WHERE learner_user_id=? AND content_version=?',array($user,$this->english_course_content->version()))[0]['n'];}

 public function summary($user)
 {
  $progress=$this->progress($user);$accepted=array();foreach($this->rows("SELECT DISTINCT unit_id,task_kind FROM st_english_submissions WHERE learner_user_id=? AND content_version=? AND status='accepted'",array($user,$this->english_course_content->version())) as $s)$accepted[$s['unit_id']][$s['task_kind']]=TRUE;
  $levels=$this->levels();$result=array();
  foreach($this->english_course_content->levels as $id=>$level){$n=0;$read=0;$due=0;foreach($level['units'] as $u){$p=$progress[$u['id']]??array();$read+=!empty($p['studied_at'])?1:0;$due+=!empty($p['review_due'])&&$p['review_due']<=gmdate('Y-m-d H:i:s')?1:0;if(isset($p['best_score'])&&(float)$p['best_score']>=$levels[$id]['pass_score']&&!empty($accepted[$u['id']]['speaking'])&&!empty($accepted[$u['id']]['writing']))$n++;}
   $cp=$progress['CP-'.$id]['best_score']??NULL;$result[$id]=array('read'=>$read,'mastered'=>$n,'due'=>$due,'checkpoint'=>$cp,'complete'=>$n===8&&$cp!==NULL&&(float)$cp>=$levels[$id]['pass_score']&&!empty($accepted[$id]['capstone']));
  }return $result;
 }
 private function ensure_progress($user,$unit)
 {
  $this->db->query('INSERT IGNORE INTO st_english_progress(learner_user_id,unit_id,content_version,updated_at) VALUES(?,?,?,?)',array($user,$unit,$this->english_course_content->version(),gmdate('Y-m-d H:i:s')));
 }
 public function studied($user,$unit)
 {
  $u=$this->english_course_content->unit($unit);if(!$u)return array('ok'=>FALSE,'code'=>404);
  return $this->study_transaction->run($this->db,function()use($user,$unit,$u){if(!$this->guard($user,$u['level_id']))return array('ok'=>FALSE,'code'=>403);$this->ensure_progress($user,$unit);$this->db->query('UPDATE st_english_progress SET studied_at=COALESCE(studied_at,?),updated_at=? WHERE learner_user_id=? AND unit_id=? AND content_version=?',array(gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s'),$user,$unit,$this->english_course_content->version()));return array('ok'=>TRUE);});
 }
 public function start($user,$unit,$kind,$key)
 {
  if(!in_array($kind,array('practice','review','checkpoint'),TRUE))return array('ok'=>FALSE,'code'=>400);
  $l=$kind==='checkpoint'?$this->english_course_content->level($unit):$this->english_course_content->unit($unit);if(!$l)return array('ok'=>FALSE,'code'=>404);$level=$kind==='checkpoint'?$unit:$l['level_id'];
  return $this->study_transaction->run($this->db,function()use($user,$unit,$kind,$key,$level){$setting=$this->guard($user,$level);if(!$setting)return array('ok'=>FALSE,'code'=>403);
   $old=$this->rows('SELECT id FROM st_english_attempts WHERE learner_user_id=? AND start_key=?',array($user,$key));if($old)return array('ok'=>TRUE,'id'=>$old[0]['id']);
   $old=$this->rows("SELECT id FROM st_english_attempts WHERE learner_user_id=? AND unit_id=? AND kind=? AND status='running' AND content_version=? ORDER BY id DESC LIMIT 1",array($user,$unit,$kind,$this->english_course_content->version()));if($old)return array('ok'=>TRUE,'id'=>$old[0]['id']);
   if($kind==='checkpoint'){$progress=$this->progress($user);foreach($this->english_course_content->level($level)['units'] as $u){$p=$progress[$u['id']]??NULL;if(!$p||$p['best_score']===NULL||(float)$p['best_score']<$setting['pass_score'])return array('ok'=>FALSE,'code'=>409);}}
   $snap=$this->english_course_content->snapshot($unit,$kind,$setting['pass_score']);
   $this->db->insert('st_english_attempts',array('learner_user_id'=>$user,'level_id'=>$level,'unit_id'=>$unit,'content_version'=>$this->english_course_content->version(),'kind'=>$kind,'snapshot_json'=>json_encode($snap,JSON_UNESCAPED_UNICODE),'start_key'=>$key,'started_at'=>gmdate('Y-m-d H:i:s')));return array('ok'=>TRUE,'id'=>$this->db->insert_id());
  });
 }
 public function attempt($user,$id){return $this->rows('SELECT * FROM st_english_attempts WHERE id=? AND learner_user_id=?',array($id,$user))[0]??NULL;}
 public function finish($user,$id,$revision,$answers)
 {
  if(!is_string($revision)||!ctype_digit($revision))return array('ok'=>FALSE,'code'=>400);
  $a=$this->attempt($user,$id);if(!$a)return array('ok'=>FALSE,'code'=>404);
  return $this->study_transaction->run($this->db,function()use($user,$id,$revision,$answers,$a){if(!$this->guard($user,$a['level_id']))return array('ok'=>FALSE,'code'=>403);
   $r=$this->rows('SELECT * FROM st_english_attempts WHERE id=? AND learner_user_id=? FOR UPDATE',array($id,$user))[0];if($r['status']==='completed')return array('ok'=>TRUE,'id'=>$id);if((int)$r['revision']!==(int)$revision)return array('ok'=>FALSE,'code'=>409);
   $snap=json_decode($r['snapshot_json'],TRUE,512,JSON_THROW_ON_ERROR);$g=$this->english_course_content->grade($snap,$answers);if(!$g)return array('ok'=>FALSE,'code'=>400);
   $now=gmdate('Y-m-d H:i:s');$this->db->where('id',$id)->update('st_english_attempts',array('status'=>'completed','answers_json'=>json_encode($g['answers'],JSON_UNESCAPED_UNICODE),'score'=>$g['score'],'finished_at'=>$now,'revision'=>(int)$r['revision']+1));
   // A changed curriculum does not reinterpret the history from an older snapshot.
   if($r['content_version']===$this->english_course_content->version()){
    $key=$r['kind']==='checkpoint'?'CP-'.$r['unit_id']:$r['unit_id'];$this->ensure_progress($user,$key);$p=$this->progress($user)[$key];$stage=(int)$p['review_stage'];$due=$p['review_due'];$last=$p['last_review_at'];
    if($r['kind']!=='checkpoint'){
     $passed=$g['score']>=$snap['pass_score'];if(!$passed){$stage=0;$due=gmdate('Y-m-d H:i:s',time()+86400);}elseif($r['kind']==='review'&&$due&&$due<=$now){$stage=min(4,$stage+1);$due=gmdate('Y-m-d H:i:s',time()+array(1,3,7,14,30)[$stage]*86400);$last=$now;}elseif(!$due){$due=gmdate('Y-m-d H:i:s',time()+86400);}
    }
    $this->db->where(array('learner_user_id'=>$user,'unit_id'=>$key,'content_version'=>$r['content_version']))->update('st_english_progress',array('best_score'=>max((float)($p['best_score']??0),$g['score']),'attempt_count'=>(int)$p['attempt_count']+1,'review_stage'=>$stage,'review_due'=>$due,'last_review_at'=>$last,'updated_at'=>$now));
   }
   return array('ok'=>TRUE,'id'=>$id);
  });
 }
 public function history($user,$page)
 {
  $total=(int)$this->rows('SELECT COUNT(*) n FROM st_english_attempts WHERE learner_user_id=?',array($user))[0]['n'];$page=min(max(1,$page),max(1,(int)ceil($total/20)));
  return array('total'=>$total,'page'=>$page,'rows'=>$this->rows('SELECT id,unit_id,kind,status,score,started_at,finished_at FROM st_english_attempts WHERE learner_user_id=? ORDER BY id DESC LIMIT 20 OFFSET '.(($page-1)*20),array($user)));
 }
 public function submit_task($user,$unit,$kind,$text,$audio,$key)
 {
  $task=$this->english_course_content->task($unit,$kind);if(!$task)return array('ok'=>FALSE,'code'=>404);
  if(!is_string($text)||!mb_check_encoding($text,'UTF-8')||mb_strlen(trim($text))<10||mb_strlen($text)>20000||($kind==='speaking'&&!$audio))return array('ok'=>FALSE,'code'=>400);
  $level=$kind==='capstone'?$unit:$this->english_course_content->unit($unit)['level_id'];
  return $this->study_transaction->run($this->db,function()use($user,$unit,$kind,$text,$audio,$key,$task,$level){if(!$this->guard($user,$level))return array('ok'=>FALSE,'code'=>403);
   $old=$this->rows('SELECT id,audio_file FROM st_english_submissions WHERE learner_user_id=? AND start_key=?',array($user,$key));if($old)return array('ok'=>TRUE,'id'=>$old[0]['id'],'duplicate'=>TRUE);
   $pending=$this->rows("SELECT id FROM st_english_submissions WHERE learner_user_id=? AND unit_id=? AND task_kind=? AND content_version=? AND status='pending' LIMIT 1",array($user,$unit,$kind,$this->english_course_content->version()));if($pending)return array('ok'=>FALSE,'code'=>409);
   $this->db->insert('st_english_submissions',array('learner_user_id'=>$user,'level_id'=>$level,'unit_id'=>$unit,'content_version'=>$this->english_course_content->version(),'task_kind'=>$kind,'response_text'=>trim($text),'audio_file'=>$audio['file']??NULL,'audio_mime'=>$audio['mime']??NULL,'task_json'=>json_encode($task,JSON_UNESCAPED_UNICODE),'start_key'=>$key,'created_at'=>gmdate('Y-m-d H:i:s')));return array('ok'=>TRUE,'id'=>$this->db->insert_id());
  });
 }
 public function submission($id){return $this->rows('SELECT s.*,u.display_name FROM st_english_submissions s JOIN st_users u ON u.id=s.learner_user_id WHERE s.id=?',array($id))[0]??NULL;}
 public function queue($status,$page,$level='')
 {
  $where=' WHERE 1=1';$args=array();if($status!=='all'){$where.=' AND s.status=?';$args[]=$status;}if($level!==''){$where.=' AND s.level_id=?';$args[]=$level;}
  $total=(int)$this->rows('SELECT COUNT(*) n FROM st_english_submissions s'.$where,$args)[0]['n'];$page=min(max(1,$page),max(1,(int)ceil($total/20)));
  return array('total'=>$total,'page'=>$page,'rows'=>$this->rows('SELECT s.id,s.unit_id,s.task_kind,s.status,s.created_at,u.display_name FROM st_english_submissions s JOIN st_users u ON u.id=s.learner_user_id'.$where.' ORDER BY s.id ASC LIMIT 20 OFFSET '.(($page-1)*20),$args));
 }
 private function audit($actor,$event,$entity,$id,$details=array()){$this->db->insert('st_audit_events',array('actor_user_id'=>$actor,'event_type'=>$event,'entity_type'=>$entity,'entity_id'=>$id,'details_json'=>json_encode($details),'created_at'=>gmdate('Y-m-d H:i:s')));}
 public function review($actor,$id,$revision,$scores,$feedback)
 {
  if(!is_array($scores)||count($scores)!==5||!is_string($feedback)||mb_strlen(trim($feedback))<10||mb_strlen($feedback)>5000||!is_string($revision)||!ctype_digit($revision))return array('ok'=>FALSE,'code'=>400);
  foreach($scores as $v)if(!is_string($v)||!preg_match('/^[0-4]$/D',$v))return array('ok'=>FALSE,'code'=>400);
  $scores=array_map('intval',array_values($scores));
  return $this->study_transaction->run($this->db,function()use($actor,$id,$revision,$scores,$feedback){$r=$this->rows('SELECT * FROM st_english_submissions WHERE id=? FOR UPDATE',array($id))[0]??NULL;if(!$r)return array('ok'=>FALSE,'code'=>404);if((int)$r['learner_user_id']===(int)$actor)return array('ok'=>FALSE,'code'=>403);if((int)$r['revision']!==(int)$revision)return array('ok'=>FALSE,'code'=>409);
   $status=min($scores)>=3?'accepted':'revise';$this->db->where('id',$id)->update('st_english_submissions',array('status'=>$status,'scores_json'=>json_encode($scores),'feedback'=>trim($feedback),'reviewer_user_id'=>$actor,'revision'=>(int)$r['revision']+1,'reviewed_at'=>gmdate('Y-m-d H:i:s')));$this->audit($actor,'english.task_reviewed','english_submission',$id,array('status'=>$status,'scores'=>$scores));return array('ok'=>TRUE);
  });
 }
 public function configure($actor,$level,$revision,$status,$score=NULL)
 {
  if(!is_string($level)||!is_string($revision)||!ctype_digit($revision))return array('ok'=>FALSE,'code'=>400);
  if($level==='global'){if(!in_array($status,array('0','1'),TRUE))return array('ok'=>FALSE,'code'=>400);}
  elseif(!$this->english_course_content->level($level)||!in_array($status,array('draft','published','archived'),TRUE)||!is_string($score)||!ctype_digit($score)||(int)$score<50||(int)$score>100)return array('ok'=>FALSE,'code'=>400);
  return $this->study_transaction->run($this->db,function()use($actor,$level,$revision,$status,$score){$s=$this->rows('SELECT * FROM st_english_settings WHERE id=1 FOR UPDATE')[0];$table=$level==='global'?'st_english_settings':'st_english_levels';$where=$level==='global'?array('id'=>1):array('level_id'=>$level);$r=$level==='global'?$s:$this->rows('SELECT * FROM st_english_levels WHERE level_id=? FOR UPDATE',array($level))[0];if((int)$r['revision']!==(int)$revision)return array('ok'=>FALSE,'code'=>409);$change=$level==='global'?array('enabled'=>(int)$status):array('status'=>$status,'pass_score'=>(int)$score);$change+=array('revision'=>(int)$r['revision']+1,'updated_at'=>gmdate('Y-m-d H:i:s'));$this->db->where($where)->update($table,$change);$this->audit($actor,'english.configured','english_course',1,array('level'=>$level,'status'=>$status,'pass_score'=>$score));return array('ok'=>TRUE);});
 }
}
