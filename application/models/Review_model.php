<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Review_model extends CI_Model
{
 private function rows($sql,$args=array()){$q=$this->db->query($sql,$args);if($q===FALSE){throw new RuntimeException('Review storage unavailable.');}return $q->result_array();}
 public function own($user){return $this->rows('SELECT * FROM st_reviews WHERE user_id=? AND is_demo=0',array($user))[0]??NULL;}
 public function listing($status,$page,$admin=FALSE,$demo=FALSE)
 {
  $where=$admin?' WHERE is_demo='.(int)$demo:" WHERE is_demo=0 AND status='published'";$args=array();if($admin && $status!=='all'){$where.=' AND status=?';$args[]=$status;}
  $total=(int)$this->rows('SELECT COUNT(*) n FROM st_reviews'.$where,$args)[0]['n'];$page=min(max(1,(int)$page),max(1,(int)ceil($total/20)));
  $summary=$this->rows("SELECT COUNT(*) total,COALESCE(AVG(rating),0) average FROM st_reviews WHERE is_demo=0 AND status='published'")[0];
  return array('rows'=>$this->rows('SELECT id,display_name,rating,body,status,revision,is_demo,created_at,updated_at FROM st_reviews'.$where.' ORDER BY created_at DESC,id DESC LIMIT 20 OFFSET '.(($page-1)*20),$args),'total'=>$total,'page'=>$page,'summary'=>$summary);
 }
 public function landing_sample($limit=3)
 {
  $limit=max(1,min(6,(int)$limit));
  $summary=$this->rows("SELECT COUNT(*) total,COALESCE(AVG(rating),0) average FROM st_reviews WHERE is_demo=0 AND status='published'")[0];
  $total=(int)$summary['total'];$rows=array();
  if($total>0){
   $take=min($limit,$total);$offsets=array();
   while(count($offsets)<$take){$offsets[random_int(0,$total-1)]=TRUE;}
   foreach(array_keys($offsets) as $offset){$row=$this->rows("SELECT id,display_name,rating,body,created_at FROM st_reviews WHERE is_demo=0 AND status='published' ORDER BY id ASC LIMIT 1 OFFSET ".(int)$offset);if($row){$rows[]=$row[0];}}
  }
  return array('rows'=>$rows,'summary'=>$summary);
 }
 public function save($user,$v)
 {
  foreach(array('display_name'=>array(2,80),'body'=>array(5,2000)) as $k=>$length){$value=$v[$k]??NULL;if(!is_string($value)||!mb_check_encoding($value,'UTF-8')||mb_strlen(trim($value))<$length[0]||mb_strlen($value)>$length[1]||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$value)){return array('ok'=>FALSE,'code'=>400);}}
  if(!in_array($v['rating']??NULL,array('1','2','3','4','5'),TRUE)||($v['consent']??'')!=='1'||!is_string($v['revision']??NULL)||!ctype_digit($v['revision'])){return array('ok'=>FALSE,'code'=>400);}
  $this->load->library('study_transaction');return $this->study_transaction->run($this->db,function()use($user,$v){
   $u=$this->rows("SELECT * FROM st_users WHERE id=? AND status='active' AND account_kind IN('adult','parent') FOR UPDATE",array($user['id']))[0]??NULL;
   if(!$u||!$u['email_verified_at']||!hash_equals(study_credential_stamp($u),$user['auth_stamp'])){return array('ok'=>FALSE,'code'=>403);}
   $old=$this->rows('SELECT * FROM st_reviews WHERE user_id=? FOR UPDATE',array($u['id']))[0]??NULL;
   if((int)$v['revision']!==(int)($old['revision']??0)){return array('ok'=>FALSE,'code'=>409);}
   $now=gmdate('Y-m-d H:i:s');$data=array('display_name'=>trim($v['display_name']),'rating'=>(int)$v['rating'],'body'=>trim($v['body']),'status'=>'pending','moderation_reason'=>NULL,'revision'=>(int)($old['revision']??0)+1,'updated_at'=>$now);
   if($old){$this->db->where('id',$old['id'])->update('st_reviews',$data);$id=$old['id'];}else{$this->db->insert('st_reviews',$data+array('user_id'=>$u['id'],'created_at'=>$now));$id=$this->db->insert_id();}
   $this->db->insert('st_audit_events',array('actor_user_id'=>$u['id'],'event_type'=>'review.submitted','entity_type'=>'review','entity_id'=>$id,'details_json'=>json_encode(array('revision'=>$data['revision'],'public_consent'=>'review-066')),'created_at'=>$now));return array('ok'=>TRUE);
  });
 }
 public function moderate($user,$id,$revision,$status,$reason)
 {
  if(!is_string($id)||!ctype_digit($id)||!is_string($revision)||!ctype_digit($revision)||!in_array($status,array('published','hidden'),TRUE)||!is_string($reason)||!mb_check_encoding($reason,'UTF-8')||mb_strlen(trim($reason))<5||mb_strlen($reason)>300){return array('ok'=>FALSE,'code'=>400);}
  $this->load->library('study_transaction');return $this->study_transaction->run($this->db,function()use($user,$id,$revision,$status,$reason){
   $u=$this->rows("SELECT * FROM st_users WHERE id=? AND status='active' FOR UPDATE",array($user['id']))[0]??NULL;
   if(!$u||!hash_equals(study_credential_stamp($u),$user['auth_stamp'])||!$this->rows("SELECT ur.user_id FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.role_code='system_admin' FOR UPDATE",array($user['id']))){return array('ok'=>FALSE,'code'=>403);}
   $r=$this->rows('SELECT * FROM st_reviews WHERE id=? FOR UPDATE',array($id))[0]??NULL;if(!$r || $r['is_demo']){return array('ok'=>FALSE,'code'=>404);}if((string)$r['revision']!==$revision){return array('ok'=>FALSE,'code'=>409);}
   $this->db->where('id',$id)->update('st_reviews',array('status'=>$status,'moderation_reason'=>trim($reason),'revision'=>(int)$revision+1,'updated_at'=>gmdate('Y-m-d H:i:s')));
   $this->db->insert('st_audit_events',array('actor_user_id'=>$user['id'],'event_type'=>'review.moderated','entity_type'=>'review','entity_id'=>$id,'details_json'=>json_encode(array('status'=>$status,'reason'=>trim($reason))),'created_at'=>gmdate('Y-m-d H:i:s')));return array('ok'=>TRUE);
  });
 }
}
