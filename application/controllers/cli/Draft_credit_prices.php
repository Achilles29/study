<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Draft_credit_prices extends CI_Controller
{
 public function index()
 {
  if(PHP_SAPI!=='cli'||ENVIRONMENT!=='development'||getenv('STUDY_PRICE_DRAFT')!=='067'){show_404();exit(1);}
  $this->load->database();$this->db->db_debug=FALSE;if(!in_array($this->db->database,array('db_study','study_qa_monitor_sso039'),TRUE)){exit(2);}
  $this->load->library('study_transaction');$r=$this->study_transaction->run($this->db,function(){
   $actor=$this->db->query("SELECT u.id FROM st_users u JOIN st_user_roles ur ON ur.user_id=u.id JOIN st_roles r ON r.id=ur.role_id WHERE u.id=1 AND u.status='active' AND r.role_code='system_admin' FOR UPDATE")->row_array();if(!$actor){return array('ok'=>FALSE,'code'=>403);}
   $now=gmdate('Y-m-d H:i:s');$added=0;
   foreach(array(20000=>500,50000=>1400,100000=>3000,150000=>4800,200000=>7000) as $price=>$credits){
    $code='draft067-topup-'.$price;if($this->db->where('draft_code',$code)->count_all_results('st_commerce_offers')){continue;}
    $this->db->insert('st_commerce_offers',array('draft_code'=>$code,'title'=>'Top up '.$credits.' kredit — rancangan baru','kind'=>'topup','status'=>'draft','price'=>$price,'credit_amount'=>$credits,'access_days'=>0,'voucher_days'=>0,'live_approved'=>0,'revision'=>1,'created_at'=>$now,'updated_at'=>$now));$id=$this->db->insert_id();$added++;
    $this->db->insert('st_audit_events',array('actor_user_id'=>1,'event_type'=>'commerce.price_draft067','entity_type'=>'commerce_offer','entity_id'=>$id,'details_json'=>json_encode(array('price'=>$price,'credits'=>$credits,'live'=>FALSE)),'created_at'=>$now));
   }
   // Only untouched original example drafts. Never overwrite an owner's edited/active offer.
   $old=$this->db->where_in('draft_code',array('draft-topup-200','draft-topup-500','draft-topup-1000','draft-platinum-365'))->where('revision',1)->where('status','draft')->where('live_approved',0)->get('st_commerce_offers')->result_array();
   foreach($old as $o){$this->db->where('id',$o['id'])->update('st_commerce_offers',array('status'=>'paused','revision'=>2,'updated_at'=>$now));$this->db->insert('st_audit_events',array('actor_user_id'=>1,'event_type'=>'commerce.old_draft_superseded','entity_type'=>'commerce_offer','entity_id'=>$o['id'],'details_json'=>'{"release":"067","reason":"Owner requested tiered credits and lifetime instead of annual draft; no paid grants changed"}','created_at'=>$now));}
   return array('ok'=>TRUE,'drafts_added'=>$added,'untouched_old_examples_paused'=>count($old),'live_activated'=>0);
  });echo json_encode($r).PHP_EOL;if(!$r['ok']){exit(1);}
 }
}
