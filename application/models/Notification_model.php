<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Notification_model extends CI_Model
{
 public function __construct(){parent::__construct();$this->load->library(array('study_transaction','telegram_gateway'));}
 private function rows($sql,$args=array()){$q=$this->db->query($sql,$args);if($q===FALSE){throw new RuntimeException('Notification storage unavailable.');}return $q->result_array();}
 public function settings(){return $this->rows('SELECT * FROM st_notification_config WHERE id=1')[0];}
 private function actor($user,$admin=FALSE)
 {
  $u=$this->rows("SELECT * FROM st_users WHERE id=? AND status='active' FOR UPDATE",array($user['id']))[0]??NULL;
  if(!$u || !hash_equals(study_credential_stamp($u),$user['auth_stamp'])){return FALSE;}
  return !$admin || (bool)$this->rows("SELECT ur.user_id FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.role_code='system_admin' FOR UPDATE",array($u['id']));
 }
 public function save_settings($user,$v)
 {
  foreach(array('enabled','payments','donations','help','visitors','confirm') as $k){if(!in_array($v[$k]??'0',array('0','1'),TRUE)){return array('ok'=>FALSE,'code'=>400);}}
  if(($v['confirm']??'')!=='1' || !is_string($v['revision']??NULL) || !ctype_digit($v['revision']) || !is_string($v['token']??NULL) || !is_string($v['chat_id']??NULL)){return array('ok'=>FALSE,'code'=>400);}
  $token=trim($v['token']);$chat=trim($v['chat_id']);
  if(($token!=='' && !preg_match('/\A[0-9]{6,16}:[A-Za-z0-9_-]{25,80}\z/',$token)) || ($chat!=='' && !preg_match('/\A-[1-9][0-9]{4,18}\z/',$chat))){return array('ok'=>FALSE,'code'=>400);}
  return $this->study_transaction->run($this->db,function()use($user,$v,$token,$chat){
   if(!$this->actor($user,TRUE)){return array('ok'=>FALSE,'code'=>403);}
   $s=$this->rows('SELECT * FROM st_notification_config WHERE id=1 FOR UPDATE')[0];
   if((string)$s['revision']!==$v['revision']){return array('ok'=>FALSE,'code'=>409);}
   if(($v['enabled']??'0')==='1' && ($chat==='' || ($token==='' && !$s['token_cipher']))){return array('ok'=>FALSE,'code'=>400);}
   $next=array('chat_id'=>$chat,'revision'=>(int)$s['revision']+1,'updated_at'=>gmdate('Y-m-d H:i:s'));
   foreach(array('enabled','payments','donations','help','visitors') as $k){$next[$k]=(int)($v[$k]??'0');}
   if($token!==''){$next['token_cipher']=$this->telegram_gateway->seal($token);}
   $this->db->where('id',1)->update('st_notification_config',$next);
   // A changed target/preferences never receives queued messages intended for the old config.
   $this->db->where('status','pending')->update('st_telegram_outbox',array('status'=>'cancelled','last_error'=>'settings_changed'));
   $this->db->insert('st_audit_events',array('actor_user_id'=>$user['id'],'event_type'=>'notification.settings_updated','entity_type'=>'notification_config','entity_id'=>1,'details_json'=>json_encode(array('revision'=>$next['revision'],'enabled'=>$next['enabled'],'token_replaced'=>$token!=='')),'created_at'=>gmdate('Y-m-d H:i:s')));
   return array('ok'=>TRUE);
  });
 }
 public function unread($user_id)
 {
  $debug=$this->db->db_debug;$this->db->db_debug=FALSE;
  try{return (int)$this->rows('SELECT COUNT(*) n FROM st_notifications WHERE user_id=? AND read_at IS NULL',array($user_id))[0]['n'];}
  catch(Throwable $e){return NULL;}finally{$this->db->db_debug=$debug;}
 }
 public function inbox($user_id,$status,$page)
 {
  $where=' WHERE user_id=?'.($status==='unread'?' AND read_at IS NULL':($status==='read'?' AND read_at IS NOT NULL':''));
  $count=(int)$this->rows('SELECT COUNT(*) n FROM st_notifications'.$where,array($user_id))[0]['n'];
  $max=(int)$this->rows('SELECT COALESCE(MAX(id),0) n FROM st_notifications WHERE user_id=?',array($user_id))[0]['n'];
  $page=min(max(1,(int)$page),max(1,(int)ceil($count/20)));
  return array('rows'=>$this->rows('SELECT * FROM st_notifications'.$where.' ORDER BY id DESC LIMIT 20 OFFSET '.(($page-1)*20),array($user_id)),'total'=>$count,'page'=>$page,'max_id'=>$max);
 }
 public function mark($user,$id,$all)
 {
  if(!is_string($id) || !preg_match('/\A[0-9]{1,18}\z/',$id)){return array('ok'=>FALSE,'code'=>400);}
  return $this->study_transaction->run($this->db,function()use($user,$id,$all){
   if(!$this->actor($user)){return array('ok'=>FALSE,'code'=>403);}
   if(!$all && !$this->rows('SELECT id FROM st_notifications WHERE id=? AND user_id=? FOR UPDATE',array($id,$user['id']))){return array('ok'=>FALSE,'code'=>404);}
   $this->db->query('UPDATE st_notifications SET read_at=UTC_TIMESTAMP() WHERE user_id=? AND read_at IS NULL AND id'.($all?'<=?':'=?'),array($user['id'],$id));return array('ok'=>TRUE);
  });
 }
 private function admins(){return array_column($this->rows("SELECT DISTINCT u.id FROM st_users u JOIN st_user_roles ur ON ur.user_id=u.id JOIN st_roles r ON r.id=ur.role_id WHERE u.status='active' AND u.account_kind<>'child' AND r.role_code='system_admin'"),'id');}
 private function notify($recipients,$key,$category,$title,$body,$path)
 {
  foreach(array_unique($recipients) as $user_id){$this->db->query('INSERT INTO st_notifications(user_id,event_key,category,title,body,path,created_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE id=id',array($user_id,$key,$category,$title,$body,$path));}
 }
 private function queue($s,$key,$category,$title,$body,$path)
 {
  if(!$s['enabled'] || empty($s[$category])){return;}
  $this->db->query('INSERT INTO st_telegram_outbox(event_key,category,body,config_revision,next_at,created_at) VALUES(?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE id=id',array($key,$category,'Project Pintar — '.$title."\n".$body."\n".'https://www.projectpintar.com/'.$path,$s['revision']));
 }
 public function collect()
 {
  return $this->study_transaction->run($this->db,function(){
   $s=$this->rows('SELECT * FROM st_notification_config WHERE id=1 FOR UPDATE')[0];$admins=$this->admins();
   // Dedup journal rather than a moving MAX(id) cursor: late commits cannot be skipped.
   $events=$this->rows("SELECT a.* FROM st_audit_events a WHERE a.id>? AND a.created_at>UTC_TIMESTAMP()-INTERVAL 30 DAY AND a.event_type IN('payment.order_created','payment.status_verified','donation.created','help.created','help.answered','help.replied','feature.unlocked','commerce.purchased','commerce.voucher_redeemed','commerce.voucher_compensated') AND NOT EXISTS(SELECT 1 FROM st_notification_events n WHERE n.event_id=a.id) ORDER BY a.id LIMIT 100",array($s['event_floor']));
   foreach($events as $e){
    $key='audit:'.$e['id'];$meta=json_decode($e['details_json'],TRUE)?:array();
    if($e['entity_type']==='payment_order'){
     $o=$this->rows('SELECT id,user_id,environment,purpose,amount_idr,status FROM st_payment_orders WHERE id=?',array($e['entity_id']))[0]??NULL;
     if($o && $o['environment']==='production'){
      $category=$o['purpose']==='donation'?'donations':'payments';$isDonation=$category==='donations';$status=$e['event_type']==='payment.status_verified'?($meta['after']??'created'):'created';
      $labels=array('created'=>'dibuat','pending'=>'menunggu pembayaran','paid'=>'pembayaran terverifikasi','expired'=>'kedaluwarsa','failed'=>'gagal','cancelled'=>'dibatalkan','refunded'=>'dikembalikan');
      $title=($isDonation?'Donasi':'Pesanan').' #'.$o['id'].' · '.($labels[$status]??'status diperbarui');
      $body='Nominal Rp'.number_format((int)$o['amount_idr'],0,',','.').'. '.(!empty($meta['review_required'])?'Perlu peninjauan pengelola.':'Buka detail untuk status terbaru.');
      $path=($isDonation?'donasi/pesanan/':'akun/pesanan/').$o['id'];
      $this->notify(array_merge($admins,array($o['user_id'])),$key,$category,$title,$body,$path);$this->queue($s,$key,$category,$title,$body,$path);
     }
    }elseif($e['entity_type']==='feature_unlock'){
     $f=$this->rows('SELECT id,payer_user_id,price_credits FROM st_feature_unlocks WHERE id=?',array($e['entity_id']))[0]??NULL;
     if($f){$title='Akses fitur #'.$f['id'].' dibuka';$body=(int)$f['price_credits'].' kredit terpakai. Periksa penerima dan rincian melalui akun; data anak tidak dikirim ke grup.';$this->notify(array($f['payer_user_id']),$key,'payments',$title,$body,'akun/kredit?environment=production');$this->notify($admins,$key,'payments',$title,$body,'kelola/kredit?environment=production');$this->queue($s,$key,'payments',$title,$body,'kelola/kredit?environment=production');}
    }elseif($e['entity_type']==='commerce_purchase'){
     $p=$this->rows('SELECT id,owner_user_id,environment FROM st_commerce_purchases WHERE id=?',array($e['entity_id']))[0]??NULL;
     if($p&&$p['environment']==='production'){$label=array('commerce.purchased'=>'Penukaran kredit tercatat','commerce.voucher_redeemed'=>'Voucher digunakan','commerce.voucher_compensated'=>'Kompensasi voucher tercatat')[$e['event_type']];$title=$label.' #'.$p['id'];$body='Buka riwayat transaksi untuk detail dan status terbaru.';$this->notify(array($p['owner_user_id']),$key,'payments',$title,$body,'akun/kredit?environment=production');$this->notify($admins,$key,'payments',$title,$body,'kelola/kredit?environment=production');$this->queue($s,$key,'payments',$title,$body,'kelola/kredit?environment=production');}
    }elseif($e['entity_type']==='help_ticket'){
     $t=$this->rows('SELECT id,user_id FROM st_help_tickets WHERE id=?',array($e['entity_id']))[0]??NULL;
     if($t){$answered=$e['event_type']==='help.answered';$title='Bantuan #'.$t['id'].($answered?' sudah dibalas':' membutuhkan tanggapan');$body='Buka kotak bantuan untuk membaca pesan dengan aman.';
      $this->notify($admins,$key,'help',$title,$body,'kelola/bantuan/'.$t['id']);
      if($answered && $t['user_id']){$this->notify(array($t['user_id']),$key,'help',$title,$body,'bantuan/tiket/'.$t['id']);}
      if(!$answered){$this->queue($s,$key,'help',$title,$body,'kelola/bantuan/'.$t['id']);}
     }
    }
    $this->db->insert('st_notification_events',array('event_id'=>$e['id'],'processed_at'=>gmdate('Y-m-d H:i:s')));
   }
   $hour=gmdate('Y-m-d H:00:00');
   if($s['traffic_hour']<$hour){
    $from=gmdate('Y-m-d H:00:00',time()-3600);$n=$this->rows('SELECT COUNT(*) visits,COALESCE(SUM(is_bot),0) bots FROM st_operational_events WHERE created_at>=? AND created_at<?',array($from,$hour))[0];
    if($s['visitors'] && (int)$n['visits']>0){$title='Ringkasan kunjungan per jam';$body=$n['visits'].' akses halaman; '.$n['bots'].' terdeteksi bot/alat. Rentang '.gmdate('d/m H:i',strtotime($from.' UTC')+7*3600).'–'.gmdate('H:i',strtotime($hour.' UTC')+7*3600).' WIB. Bukan jumlah orang unik.';$key='traffic:'.$from;$this->notify($admins,$key,'visitors',$title,$body,'kelola/trafik');$this->queue($s,$key,'visitors',$title,$body,'kelola/trafik');}
    $this->db->where('id',1)->update('st_notification_config',array('traffic_hour'=>$hour));
   }
   $this->db->where('id',1)->update('st_notification_config',array('worker_at'=>gmdate('Y-m-d H:i:s')));
   return array('ok'=>TRUE,'events'=>count($events));
  });
 }
 public function test_message($user)
 {
  return $this->study_transaction->run($this->db,function()use($user){
   if(!$this->actor($user,TRUE)){return array('ok'=>FALSE,'code'=>403);}$s=$this->rows('SELECT * FROM st_notification_config WHERE id=1 FOR UPDATE')[0];
   if(!$s['enabled'] || !$s['token_cipher'] || !$s['chat_id']){return array('ok'=>FALSE,'code'=>409);}
   $s['test']=1;$this->queue($s,'test:'.gmdate('YmdHi'),'test','Uji koneksi','Pesan uji dari pengelola. Tidak berisi data pengguna.','kelola/pengaturan/telegram');return array('ok'=>TRUE);
  });
 }
 public function deliver()
 {
  if(PHP_SAPI!=='cli'){throw new RuntimeException('Worker only.');}
  if((int)$this->rows("SELECT GET_LOCK('study_telegram_worker',0) locked")[0]['locked']!==1){return array('ok'=>TRUE,'busy'=>TRUE);}
  try{
   // A worker crash after send is ambiguous; never automatically send it twice.
   $s=$this->settings();$jobs=$this->rows("SELECT * FROM st_telegram_outbox WHERE status='pending' AND next_at<=UTC_TIMESTAMP() ORDER BY id LIMIT 10");$sent=0;
   foreach($jobs as $job){
    $s=$this->settings();
    if(!$s['enabled'] || (int)$s['revision']!==(int)$job['config_revision']){$this->db->where('id',$job['id'])->update('st_telegram_outbox',array('status'=>'cancelled','last_error'=>'settings_changed'));continue;}
    if(!$this->db->where('id',$job['id'])->where('status','pending')->update('st_telegram_outbox',array('status'=>'uncertain','attempts'=>(int)$job['attempts']+1,'last_error'=>'delivery_in_progress')) || $this->db->affected_rows()!==1){throw new RuntimeException('Could not claim delivery.');}
    try{$r=$this->telegram_gateway->send($this->telegram_gateway->open($s['token_cipher']),$s['chat_id'],$job['body']);}catch(Throwable $e){$r=array('ok'=>FALSE,'error'=>'secret_unavailable');}
    $data=array('status'=>$r['ok']?'sent':(($r['error']??'')==='uncertain'?'uncertain':'failed'),'last_error'=>$r['ok']?NULL:$r['error']);
    if($r['ok']){$data['sent_at']=gmdate('Y-m-d H:i:s');$sent++;}
    elseif(($r['error']??'')==='rate_limited' && (int)$job['attempts']<4){$data['status']='pending';$data['next_at']=gmdate('Y-m-d H:i:s',time()+$r['retry']);}
    if(!$this->db->where('id',$job['id'])->update('st_telegram_outbox',$data)){throw new RuntimeException('Delivery confirmation unavailable.');}
   }return array('ok'=>TRUE,'sent'=>$sent);
  }finally{$this->db->query("SELECT RELEASE_LOCK('study_telegram_worker')");}
 }
 public function delivery_history(){return $this->rows('SELECT id,category,status,attempts,last_error,created_at,sent_at FROM st_telegram_outbox ORDER BY id DESC LIMIT 20');}
}
