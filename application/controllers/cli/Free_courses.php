<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/** Explicit owner-authorized access policy; no curriculum, payment or progress writes. */
class Free_courses extends CI_Controller
{
 public function index($mode='check')
 {
  if (PHP_SAPI!=='cli' || ENVIRONMENT!=='development') { show_404(); exit(1); }
  if (!in_array($mode,array('check','apply'),TRUE)) { throw new RuntimeException('Invalid mode.'); }
  $this->load->database(); $this->db->db_debug=FALSE;
  if (!in_array($this->db->database,array('db_study','study_qa_monitor_sso039'),TRUE)) { throw new RuntimeException('Database denied.'); }
  if ($mode==='apply' && getenv('STUDY_FREE_COURSES')!=='063') { throw new RuntimeException('Explicit release gate required.'); }
  if (!$this->db->query("SELECT u.id FROM st_users u JOIN st_user_roles ur ON ur.user_id=u.id JOIN st_roles r ON r.id=ur.role_id WHERE u.id=1 AND u.status='active' AND r.role_code='system_admin'")->num_rows()) { throw new RuntimeException('Administrator required.'); }
  $this->load->library('study_transaction');
  $result=$this->study_transaction->run($this->db,function()use($mode){
   $rows=$this->db->query('SELECT id,is_free FROM st_courses WHERE is_free=0 ORDER BY id FOR UPDATE')->result_array();
   if($mode==='apply') foreach($rows as $row){
    $this->db->where('id',$row['id'])->update('st_courses',array('is_free'=>1,'updated_at'=>gmdate('Y-m-d H:i:s')));
    $this->db->insert('st_audit_events',array('actor_user_id'=>1,'event_type'=>'content.course_free_policy','entity_type'=>'course','entity_id'=>$row['id'],'details_json'=>json_encode(array('release'=>'063','before'=>0,'after'=>1,'content_and_progress_unchanged'=>TRUE)),'created_at'=>gmdate('Y-m-d H:i:s')));
   }
   return array('ok'=>TRUE,'mode'=>$mode,'courses'=>count($rows));
  });
  echo json_encode($result).PHP_EOL;
  if(!$result['ok']) { exit(1); }
 }
}
