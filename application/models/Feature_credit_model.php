<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once dirname(__DIR__).'/libraries/Exam_schedule.php';
class Feature_credit_model extends CI_Model
{
 public function __construct(){parent::__construct();$this->load->model(array('Bank_model','Settings_model'));$this->load->library('study_transaction');}
 public function settings(){return $this->Settings_model->get_section('latihan_mandiri');}
 public function owns($user,$type,$id){$this->load->model('Lifetime_model');return (in_array($type,array('advanced','quota','exam','arena'),TRUE)&&$this->Lifetime_model->covered($user))||(bool)$this->Bank_model->rows('SELECT id FROM st_feature_unlocks WHERE learner_user_id=? AND feature_type=? AND feature_id=?',array($user,$type,$id));}
 public function quote($type,$id,$lock=FALSE)
 {
  if($type==='advanced'){return NULL;} // Retain history, never sell retired access.
  if($type==='arena'){
   $this->load->model('Arena_credit_model');$this->load->helper('study_arena');$g=$this->Arena_credit_model->group((int)$id);$s=$this->Arena_credit_model->settings();
   if(!$g||(int)$g['price_credits']<1||!array_filter($g['packs'],static function($p){return (bool)$p['enabled'];})){return NULL;}
   return array('type'=>'arena','id'=>(int)$g['id'],'title'=>study_arena_type($g['game_type']).' · level akses '.$g['access_level'],'price'=>(int)$g['price_credits'],'detail'=>'Sekali buka untuk satu penerima, tanpa kedaluwarsa dan tanpa biaya mengulang. Meliputi set yang tercantum di bawah. Syarat progres level/episode sebelumnya tetap berlaku.','enabled'=>$s['values']['enabled']==='1','version'=>hash('sha256','arena:'.$g['id'].':'.$g['price_credits'].':'.$g['revision'].':'.$s['revision']),'packs'=>$g['packs']);
  }
  $s=$this->settings();$v=$s['values'];$revision=$s['revision'];$id=(int)$id;
  if($type==='quota'){if((string)$id!==date('Ymd')){return NULL;}$title='Tambahan '.$v['extra_count'].' sesi hari ini';$price=(int)$v['extra_price'];$detail='Berlaku sampai pukul 23.59 WIB hari ini. Satu pembelian tambahan per penerima per hari, bukan langganan.';}
  elseif($type==='exam'){$e=$this->Bank_model->rows("SELECT * FROM st_exam_templates WHERE id=? AND status='published'".($lock?' FOR UPDATE':''),array($id))[0]??NULL;if(!$e||!Exam_schedule::is_open($e)||(int)$e['credit_price']<1){return NULL;}$count=$this->Bank_model->rows("SELECT COUNT(*) total,SUM(q.status='published') ready FROM st_exam_questions x JOIN st_questions q ON q.id=x.question_id WHERE x.exam_id=?",array($id))[0];if(!$count['total']||(int)$count['total']!==(int)$count['ready']){return NULL;}$title=$e['title'];$price=(int)$e['credit_price'];$revision.=':'.$e['revision'];$detail='Akses Simulasi Ujian ini untuk satu penerima. Mulai sesi mengikuti jangka aktif yang ditetapkan pengelola; batas percobaan harian tetap berlaku.'.($e['available_until']?' Berakhir '.str_replace('T',' ',Exam_schedule::to_form($e['available_until'])).' WIB.':' Tanpa batas tanggal.');}
  else{return NULL;}
  return array('type'=>$type,'id'=>$id,'title'=>$title,'price'=>$price,'detail'=>$detail,'enabled'=>$v['paid_enabled']==='1','version'=>hash('sha256',$type.':'.$id.':'.$price.':'.$revision),'extra_count'=>(int)$v['extra_count']);
 }
 public function beneficiaries($user){$this->load->model('Commerce_model');return $this->Commerce_model->beneficiaries($user['id']);}
 public function buy($user,$learner,$type,$id,$version)
 {
  if(!in_array($type,array('quota','exam','arena'),TRUE)||!is_string($learner)||!ctype_digit($learner)||!is_string($id)||!ctype_digit($id)||!is_string($version)||!preg_match('/\A[a-f0-9]{64}\z/',$version)){return array('ok'=>FALSE,'code'=>400);}
  return $this->study_transaction->run($this->db,function()use($user,$learner,$type,$id,$version){
   $actor=$this->Bank_model->rows("SELECT * FROM st_users WHERE id=? AND status='active' AND account_kind IN('adult','parent') FOR UPDATE",array($user['id']))[0]??NULL;
   if(!$actor||!$actor['email_verified_at']||!hash_equals(study_credential_stamp($actor),$user['auth_stamp'])){return array('ok'=>FALSE,'code'=>403);}
   $target=$this->Bank_model->rows("SELECT * FROM st_users WHERE id=? AND status='active' FOR UPDATE",array($learner))[0]??NULL;
   if(!$target||((int)$learner!==(int)$actor['id']&&($actor['account_kind']!=='parent'||$target['account_kind']!=='child'||!$this->Bank_model->rows("SELECT id FROM st_family_links WHERE parent_user_id=? AND child_user_id=? AND status='active' FOR UPDATE",array($actor['id'],$learner))))){return array('ok'=>FALSE,'code'=>403);}
   $this->load->model('Lifetime_model');if($this->Lifetime_model->covered($learner)){return array('ok'=>TRUE,'existing'=>TRUE,'lifetime'=>TRUE);}
   $existing=$this->Bank_model->rows('SELECT id FROM st_feature_unlocks WHERE learner_user_id=? AND feature_type=? AND feature_id=? FOR UPDATE',array($learner,$type,$id));if($existing){return array('ok'=>TRUE,'existing'=>TRUE);}
   if($this->Bank_model->rows("SELECT id FROM st_payment_orders WHERE user_id=? AND environment='production' AND review_required=1 LIMIT 1 FOR UPDATE",array($actor['id']))){return array('ok'=>FALSE,'code'=>409,'message'=>'Penukaran baru dijeda selama refund/sengketa pembayaran masih ditinjau pengelola. Akses yang sudah dimiliki tetap tersedia.');}
   $this->Bank_model->rows('SELECT id FROM st_site_settings WHERE section=? FOR UPDATE',array($type==='arena'?'akses_arena':'latihan_mandiri'));if($type==='exam'){$this->Bank_model->rows('SELECT id FROM st_exam_templates WHERE id=? FOR UPDATE',array($id));}if($type==='arena'){$this->Bank_model->rows('SELECT id FROM st_arena_access_groups WHERE id=? FOR UPDATE',array($id));}
   $q=$this->quote($type,$id,TRUE);if(!$q||!$q['enabled']||!hash_equals($q['version'],$version)){return array('ok'=>FALSE,'code'=>409);}
   $w=$this->Bank_model->rows("SELECT * FROM st_wallets WHERE owner_user_id=? AND environment='production' FOR UPDATE",array($actor['id']))[0]??NULL;if(!$w||(int)$w['balance_credits']<$q['price']){return array('ok'=>FALSE,'code'=>409,'message'=>'Kredit belum cukup. Tidak ada saldo yang dipotong.');}
   $now=gmdate('Y-m-d H:i:s');$this->db->insert('st_feature_unlocks',array('learner_user_id'=>$learner,'payer_user_id'=>$actor['id'],'feature_type'=>$type,'feature_id'=>$id,'price_credits'=>$q['price'],'created_at'=>$now));$unlock=(int)$this->db->insert_id();
   if(!$this->db->query('UPDATE st_wallets SET balance_credits=balance_credits-?,updated_at=? WHERE id=? AND balance_credits>=?',array($q['price'],$now,$w['id'],$q['price']))||$this->db->affected_rows()!==1){throw new RuntimeException('Wallet debit unavailable.');}
   $this->db->insert('st_wallet_entries',array('wallet_id'=>$w['id'],'actor_user_id'=>$actor['id'],'entry_type'=>'package_purchase','delta_credits'=>-$q['price'],'idempotency_key'=>'feature-unlock:'.$unlock,'reference_type'=>'feature_unlock','reference_id'=>$unlock,'note'=>$q['title'],'created_at'=>$now));
   if($type==='quota'){$this->db->query('INSERT INTO st_drill_daily(learner_user_id,day_wib,extra_count) VALUES(?,?,?) ON DUPLICATE KEY UPDATE extra_count=extra_count+VALUES(extra_count)',array($learner,date('Y-m-d'),$q['extra_count']));}
   $this->db->insert('st_audit_events',array('actor_user_id'=>$actor['id'],'event_type'=>'feature.unlocked','entity_type'=>'feature_unlock','entity_id'=>$unlock,'details_json'=>json_encode(array('type'=>$type,'feature_id'=>(int)$id,'learner_id'=>(int)$learner,'credits'=>$q['price'])),'created_at'=>$now));return array('ok'=>TRUE,'id'=>$unlock);
  });
 }
}
