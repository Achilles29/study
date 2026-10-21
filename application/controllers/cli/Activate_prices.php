<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Activate_prices extends CI_Controller
{
 public function index()
 {
  if(PHP_SAPI!=='cli'||getenv('STUDY_ACTIVATE_071')!=='approved-2026-10-19'){show_404();exit(1);}
  $this->load->database();$this->db->db_debug=FALSE;if(!in_array($this->db->database,array('db_study','study_qa_monitor_sso039'),TRUE)){exit(2);}
  $this->load->library('study_transaction');$r=$this->study_transaction->run($this->db,function(){
   $actor=$this->db->query("SELECT u.id FROM st_users u JOIN st_user_roles ur ON ur.user_id=u.id JOIN st_roles r ON r.id=ur.role_id WHERE u.id=1 AND u.status='active' AND r.role_code='system_admin' FOR UPDATE")->row_array();if(!$actor){return array('ok'=>FALSE,'code'=>403);}
   $offers=array();$now=gmdate('Y-m-d H:i:s');
   foreach(array(20000=>500,50000=>1400,100000=>3000,150000=>4800,200000=>7000) as $price=>$credits){
    $o=$this->db->query('SELECT * FROM st_commerce_offers WHERE draft_code=? FOR UPDATE',array('draft067-topup-'.$price))->row_array();
    if(!$o||$o['kind']!=='topup'||(int)$o['price']!==$price||(int)$o['credit_amount']!==$credits||(int)$o['access_days']!==0||(int)$o['is_lifetime']!==0){return array('ok'=>FALSE,'code'=>409,'message'=>'Topup differs from approved terms; no changes made.');}
    if($o['status']==='active'&&(int)$o['live_approved']===1){continue;}
    if($o['status']!=='draft'||(int)$o['revision']!==1||(int)$o['live_approved']!==0){return array('ok'=>FALSE,'code'=>409,'message'=>'Owner-modified offer preserved.');}$offers[]=$o;
   }
   $l=$this->db->query('SELECT * FROM st_commerce_offers WHERE draft_code=? FOR UPDATE',array('approved071-lifetime-family2'))->row_array();
   if($l&&($l['kind']!=='platinum'||(int)$l['price']!==300000||(int)$l['is_lifetime']!==1||(int)$l['access_days']!==0||$l['status']!=='active'||(int)$l['live_approved']!==1)){return array('ok'=>FALSE,'code'=>409,'message'=>'Existing lifetime product differs; preserved.');}
   foreach($offers as $o){$data=array('title'=>'Top up '.$o['credit_amount'].' kredit','status'=>'active','live_approved'=>1,'revision'=>(int)$o['revision']+1,'updated_at'=>$now);$this->db->where('id',$o['id'])->update('st_commerce_offers',$data);$this->audit($o['id'],array('before'=>$o,'after'=>$data,'approval'=>'Owner 19 October 2026: approved tiered topups'));}
   if(!$l){$data=array('draft_code'=>'approved071-lifetime-family2','title'=>'Platinum Selamanya · Orang Tua + 2 Anak','kind'=>'platinum','status'=>'active','price'=>300000,'credit_amount'=>0,'access_days'=>0,'voucher_days'=>0,'is_lifetime'=>1,'live_approved'=>1,'revision'=>1,'created_at'=>$now,'updated_at'=>$now);$this->db->insert('st_commerce_offers',$data);$l=array('id'=>(int)$this->db->insert_id());$this->audit($l['id'],array('after'=>$data,'approval'=>'Owner 19 October 2026: lifetime, two included child slots, no daily practice quota'));}
   return array('ok'=>TRUE,'topups_activated'=>count($offers),'lifetime_offer_id'=>(int)$l['id'],'feature_gates_unchanged'=>TRUE);
  });echo json_encode($r).PHP_EOL;if(!$r['ok']){exit(1);}
 }
 private function audit($id,$details){$this->db->insert('st_audit_events',array('actor_user_id'=>1,'event_type'=>'commerce.owner_approved071','entity_type'=>'commerce_offer','entity_id'=>$id,'details_json'=>json_encode($details),'created_at'=>gmdate('Y-m-d H:i:s')));}
}
