<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Notification_model extends CI_Model
{
 public function __construct(){parent::__construct();$this->load->library(array('study_transaction','telegram_gateway'));$this->load->model('Telegram_schedule_model');}
 private function rows($sql,$args=array()){$q=$this->db->query($sql,$args);if($q===FALSE){throw new RuntimeException('Notification storage unavailable.');}return $q->result_array();}
 public function settings(){return $this->rows('SELECT * FROM st_notification_config WHERE id=1')[0];}
 public function save_message_policy($user,$v)
 {
  $validated=$this->telegram_message_policy->validate($v);if(!$validated['ok'])return $validated;
  return $this->study_transaction->run($this->db,function()use($user,$v,$validated){
   if(!$this->actor($user,TRUE))return array('ok'=>FALSE,'code'=>403);
   $this->rows('SELECT id FROM st_notification_config WHERE id=1 FOR UPDATE');
   $old=$this->rows('SELECT * FROM st_telegram_message_policies WHERE category=? FOR UPDATE',array($v['category']))[0];
   if((string)$old['revision']!==$v['revision'])return array('ok'=>FALSE,'code'=>409);
   $next=$validated['values'];
   $changed=$old['mode']!==$next['mode']||($next['mode']==='interval'&&(int)$old['interval_minutes']!==$next['interval_minutes'])||($next['mode']==='daily'&&$old['daily_time']!==$next['daily_time']);
   if($changed)$next['next_due_at']=$this->telegram_message_policy->next_due($next,time());
   $next['revision']=(int)$old['revision']+1;$next['updated_at']=gmdate('Y-m-d H:i:s');
   $this->db->where('category',$v['category'])->update('st_telegram_message_policies',$next);
   $this->db->insert('st_audit_events',array('actor_user_id'=>$user['id'],'event_type'=>'notification.message_policy_updated','entity_type'=>'notification_config','entity_id'=>1,'details_json'=>json_encode(array('category'=>$v['category'],'revision'=>$next['revision'],'mode'=>$next['mode'],'interval_minutes'=>$next['interval_minutes'],'daily_time'=>$next['daily_time'])),'created_at'=>gmdate('Y-m-d H:i:s')));
   return array('ok'=>TRUE);
  });
 }
 private function actor($user,$admin=FALSE)
 {
  $u=$this->rows("SELECT * FROM st_users WHERE id=? AND status='active' FOR UPDATE",array($user['id']))[0]??NULL;
  if(!$u || !hash_equals(study_credential_stamp($u),$user['auth_stamp'])){return FALSE;}
  return !$admin || (bool)$this->rows("SELECT ur.user_id FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.role_code='system_admin' FOR UPDATE",array($u['id']));
 }
 public function save_settings($user,$v)
 {
  foreach(array('enabled','payments','donations','help','visitors','members','children','confirm') as $k){if(!in_array($v[$k]??'0',array('0','1'),TRUE)){return array('ok'=>FALSE,'code'=>400,'reason'=>'invalid_form');}}
  if(($v['confirm']??'')!=='1'){return array('ok'=>FALSE,'code'=>400,'reason'=>'confirm_required');}
  if(!is_string($v['revision']??NULL) || !ctype_digit($v['revision']) || !is_string($v['token']??NULL) || !is_string($v['chat_id']??NULL)){return array('ok'=>FALSE,'code'=>400,'reason'=>'invalid_form');}
  $token=trim($v['token']);$chat=trim($v['chat_id']);
  if($token!=='' && !preg_match('/\A[0-9]{6,16}:[A-Za-z0-9_-]{25,80}\z/',$token)){return array('ok'=>FALSE,'code'=>400,'reason'=>'invalid_token');}
  if($chat!=='' && !$this->telegram_gateway->valid_chat_id($chat)){return array('ok'=>FALSE,'code'=>400,'reason'=>'invalid_chat_id');}
  return $this->study_transaction->run($this->db,function()use($user,$v,$token,$chat){
   if(!$this->actor($user,TRUE)){return array('ok'=>FALSE,'code'=>403);}
   $s=$this->rows('SELECT * FROM st_notification_config WHERE id=1 FOR UPDATE')[0];
   if((string)$s['revision']!==$v['revision']){return array('ok'=>FALSE,'code'=>409);}
   $activation_pending=($v['enabled']??'0')==='1' && ($chat==='' || ($token==='' && !$s['token_cipher']));
   $next=array('chat_id'=>$chat,'revision'=>(int)$s['revision']+1,'updated_at'=>gmdate('Y-m-d H:i:s'));
   foreach(array('enabled','payments','donations','help','visitors','members','children') as $k){$next[$k]=(int)($v[$k]??(in_array($k,array('members','children'),TRUE)?$s[$k]:'0'));}
   if($activation_pending){$next['enabled']=0;}
   if($token!==''){$next['token_cipher']=$this->telegram_gateway->seal($token);}
   $this->db->where('id',1)->update('st_notification_config',$next);
   if($token!==''){$this->db->where('id',1)->update('st_telegram_bot_state',array('next_update_id'=>0,'commands_set'=>0,'updated_at'=>gmdate('Y-m-d H:i:s')));$this->db->query('DELETE FROM st_telegram_pair_updates');$this->db->query('DELETE FROM st_telegram_bot_inbox');$this->db->query('DELETE FROM st_bot_ai_turns');}
   // A changed target/preferences never receives queued messages intended for the old config.
   $this->db->where('status','pending')->update('st_telegram_outbox',array('status'=>'cancelled','last_error'=>'settings_changed'));
   $this->db->query('DELETE FROM st_telegram_digest_events');
   $this->db->insert('st_audit_events',array('actor_user_id'=>$user['id'],'event_type'=>'notification.settings_updated','entity_type'=>'notification_config','entity_id'=>1,'details_json'=>json_encode(array('revision'=>$next['revision'],'enabled'=>$next['enabled'],'token_replaced'=>$token!=='')),'created_at'=>gmdate('Y-m-d H:i:s')));
   return array('ok'=>TRUE,'activation_pending'=>$activation_pending);
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
 private function plain_label($value,$fallback='Pengguna')
 {
  $label=is_string($value)?preg_replace('/[\x{0000}-\x{001F}\x{007F}-\x{009F}\x{2028}\x{2029}]+/u',' ',$value):'';
  $collapsed=is_string($label)?preg_replace('/\s+/u',' ',$label):NULL;
  $label=is_string($collapsed)?trim($collapsed):'';
  return $label===''?$fallback:str_replace('@','＠',mb_substr($label,0,70,'UTF-8'));
 }
 private function person($id,$fallback='Pengguna')
 {
  if(!$id){return $fallback;}
  $user=$this->rows('SELECT display_name FROM st_users WHERE id=?',array($id))[0]??NULL;
  return $user?$this->plain_label($user['display_name'],$fallback):$fallback;
 }
 private function notify($recipients,$key,$category,$title,$body,$path)
 {
  foreach(array_unique($recipients) as $user_id){$this->db->query('INSERT INTO st_notifications(user_id,event_key,category,title,body,path,created_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE id=id',array($user_id,$key,$category,$title,$body,$path));}
 }
 private function queue($s,$key,$category,$title,$body,$path)
 {
  if(!$s['enabled'] || empty($s[$category])){return;}
  $this->Telegram_schedule_model->enqueue($s,$key,$category,$title,$body,$path);
 }
 public function collect()
 {
  return $this->study_transaction->run($this->db,function(){
   $s=$this->rows('SELECT * FROM st_notification_config WHERE id=1 FOR UPDATE')[0];$admins=$this->admins();
   // Dedup journal rather than a moving MAX(id) cursor: late commits cannot be skipped.
   $events=$this->rows("SELECT a.* FROM st_audit_events a WHERE a.id>? AND a.created_at>UTC_TIMESTAMP()-INTERVAL 30 DAY AND a.event_type IN('payment.order_created','payment.status_verified','donation.created','help.created','help.answered','help.replied','feature.unlocked','commerce.purchased','commerce.voucher_redeemed','commerce.voucher_compensated','account.registered','google.registered','family.child_created') AND (a.event_type NOT IN('account.registered','google.registered','family.child_created') OR a.id>?) AND NOT EXISTS(SELECT 1 FROM st_notification_events n WHERE n.event_id=a.id) ORDER BY a.id LIMIT 100",array($s['event_floor'],$s['registration_floor']));
   foreach($events as $e){
    $key='audit:'.$e['id'];$meta=json_decode($e['details_json'],TRUE)?:array();
    if($e['entity_type']==='user' && in_array($e['event_type'],array('account.registered','google.registered','family.child_created'),TRUE)){
     $account=$this->rows('SELECT account_kind,display_name FROM st_users WHERE id=?',array($e['entity_id']))[0]??NULL;
     if($account && $e['event_type']==='family.child_created' && $account['account_kind']==='child'){
      $title='Akun anak baru ditambahkan';$body=$this->person($e['actor_user_id'],'Orang tua').' menambahkan '.$this->plain_label($account['display_name'],'anak').' sebagai akun anak. Buka detail akun untuk melihat pengaturannya.';$path='kelola/pengguna/'.$e['entity_id'].'/akses';
      $this->notify($admins,$key,'children',$title,$body,$path);$this->queue($s,$key,'children',$title,$body,$path);
     }elseif($account && in_array($e['event_type'],array('account.registered','google.registered'),TRUE) && in_array($account['account_kind'],array('adult','parent'),TRUE)){
      $kind=$account['account_kind']==='parent'?'orang tua':'dewasa';$via=$e['event_type']==='google.registered'?' lewat Google':'';$title='Anggota baru terdaftar';$body=$this->plain_label($account['display_name']).' baru mendaftar sebagai '.$kind.$via.'. Buka detail akun untuk melihat statusnya.';$path='kelola/pengguna/'.$e['entity_id'].'/akses';
      $this->notify($admins,$key,'members',$title,$body,$path);$this->queue($s,$key,'members',$title,$body,$path);
     }
    }elseif($e['entity_type']==='payment_order'){
     $o=$this->rows('SELECT id,user_id,environment,purpose,amount_idr,status FROM st_payment_orders WHERE id=?',array($e['entity_id']))[0]??NULL;
     if($o && $o['environment']==='production'){
      $category=$o['purpose']==='donation'?'donations':'payments';$isDonation=$category==='donations';$status=$e['event_type']==='payment.status_verified'?($meta['after']??'created'):'created';
      $labels=array('created'=>'dibuat','pending'=>'menunggu pembayaran','paid'=>'pembayaran terverifikasi','expired'=>'kedaluwarsa','failed'=>'gagal','cancelled'=>'dibatalkan','refunded'=>'dikembalikan');
      $title=($isDonation?'Donasi':'Pesanan').' #'.$o['id'].' · '.($labels[$status]??'status diperbarui');
      $person=$this->person($o['user_id']);$amount='Rp'.number_format((int)$o['amount_idr'],0,',','.');
      $body=$e['event_type']==='payment.status_verified'?('Status '.($isDonation?'donasi':'pesanan').' milik '.$person.' berubah menjadi '.($labels[$status]??'diperbarui').'. Nominal '.$amount.'.'):($person.' membuat '.($isDonation?'donasi':'pesanan').' senilai '.$amount.'.');
      $body.=' '.(!empty($meta['review_required'])?'Pesanan perlu ditinjau pengelola.':'Buka detail untuk informasi terbaru.');
      $path=($isDonation?'donasi/pesanan/':'akun/pesanan/').$o['id'];
      $this->notify(array_merge($admins,array($o['user_id'])),$key,$category,$title,$body,$path);$this->queue($s,$key,$category,$title,$body,$path);
     }
    }elseif($e['entity_type']==='feature_unlock'){
     $f=$this->rows('SELECT id,payer_user_id,learner_user_id,feature_type,price_credits FROM st_feature_unlocks WHERE id=?',array($e['entity_id']))[0]??NULL;
     if($f){$title='Akses fitur #'.$f['id'].' dibuka';$feature=array('quota'=>'tambahan sesi latihan','exam'=>'simulasi ujian','arena'=>'Arena','advanced'=>'fitur lanjutan')[$f['feature_type']]??'fitur belajar';$body=$this->person($f['payer_user_id']).' memakai '.(int)$f['price_credits'].' kredit untuk membuka '.$feature.' bagi '.$this->person($f['learner_user_id']).'. Buka riwayat kredit untuk melihat rinciannya.';$this->notify(array($f['payer_user_id']),$key,'payments',$title,$body,'akun/kredit?environment=production');$this->notify($admins,$key,'payments',$title,$body,'kelola/kredit?environment=production');$this->queue($s,$key,'payments',$title,$body,'kelola/kredit?environment=production');}
    }elseif($e['entity_type']==='commerce_purchase'){
     $p=$this->rows('SELECT p.id,p.owner_user_id,p.environment,o.title offer_title FROM st_commerce_purchases p LEFT JOIN st_commerce_offers o ON o.id=p.offer_id WHERE p.id=?',array($e['entity_id']))[0]??NULL;
     if($p&&$p['environment']==='production'){$label=array('commerce.purchased'=>'Penukaran kredit tercatat','commerce.voucher_redeemed'=>'Voucher digunakan','commerce.voucher_compensated'=>'Kompensasi voucher tercatat')[$e['event_type']];$title=$label.' #'.$p['id'];$body=$this->person($p['owner_user_id']).': '.mb_strtolower($label,'UTF-8').' untuk '.$this->plain_label($p['offer_title'],'produk').'. Buka riwayat transaksi untuk melihat status dan rinciannya.';$this->notify(array($p['owner_user_id']),$key,'payments',$title,$body,'akun/kredit?environment=production');$this->notify($admins,$key,'payments',$title,$body,'kelola/kredit?environment=production');$this->queue($s,$key,'payments',$title,$body,'kelola/kredit?environment=production');}
    }elseif($e['entity_type']==='help_ticket'){
     $t=$this->rows('SELECT id,user_id,subject,category FROM st_help_tickets WHERE id=?',array($e['entity_id']))[0]??NULL;
     if($t){$answered=$e['event_type']==='help.answered';$title='Bantuan #'.$t['id'].($answered?' sudah dibalas':' membutuhkan tanggapan');$person=$this->person($t['user_id'],'Pengunjung');$action=$answered?'Pengelola membalas pesan bantuan dari '.$person:($e['event_type']==='help.replied'?$person.' membalas percakapan bantuan':$person.' mengirim permintaan bantuan');$body=$action.'. Topik: '.$this->plain_label($t['subject'],'Tanpa judul').'. Buka tiket untuk membaca percakapannya.';
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
    if($s['visitors'] && (int)$n['visits']>0){$title='Ringkasan kunjungan per jam';$body='Dalam satu jam terakhir, situs dibuka '.$n['visits'].' kali; '.$n['bots'].' akses terdeteksi sebagai bot atau alat. Periode '.gmdate('d/m H:i',strtotime($from.' UTC')+7*3600).'–'.gmdate('H:i',strtotime($hour.' UTC')+7*3600).' WIB. Ini jumlah akses, bukan jumlah orang unik.';$key='traffic:'.$from;$this->notify($admins,$key,'visitors',$title,$body,'kelola/trafik');}
    $this->db->where('id',1)->update('st_notification_config',array('traffic_hour'=>$hour));
   }
   $this->Telegram_schedule_model->flush($s);
   $this->db->where('id',1)->update('st_notification_config',array('worker_at'=>gmdate('Y-m-d H:i:s')));
   return array('ok'=>TRUE,'events'=>count($events));
  });
 }
 public function test_message($user)
 {
  return $this->study_transaction->run($this->db,function()use($user){
   if(!$this->actor($user,TRUE)){return array('ok'=>FALSE,'code'=>403);}$s=$this->rows('SELECT * FROM st_notification_config WHERE id=1 FOR UPDATE')[0];
   if(!$s['enabled'] || !$s['token_cipher'] || !$s['chat_id']){return array('ok'=>FALSE,'code'=>409);}
   $s['test']=1;$this->queue($s,'test:'.bin2hex(random_bytes(8)),'test','Koneksi bot siap','Halo! Notifikasi Project Pintar sudah terhubung ke chat ini. Pengaturan dan riwayat pengiriman bisa dilihat di dashboard.','kelola/pengaturan/telegram');return array('ok'=>TRUE);
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
    if((int)$s['revision']!==(int)$job['config_revision'] || (!$s['enabled'] && $job['category']!=='bot_reply') || !$s['token_cipher'] || !$s['chat_id']){$this->db->where('id',$job['id'])->update('st_telegram_outbox',array('status'=>'cancelled','last_error'=>'settings_changed'));continue;}
    if(!$this->db->where('id',$job['id'])->where('status','pending')->update('st_telegram_outbox',array('status'=>'uncertain','attempts'=>(int)$job['attempts']+1,'last_error'=>'delivery_in_progress')) || $this->db->affected_rows()!==1){throw new RuntimeException('Could not claim delivery.');}
    try{$r=$this->telegram_gateway->send($this->telegram_gateway->open($s['token_cipher']),$s['chat_id'],$job['body'],$job['reply_to_message_id']===NULL?NULL:(int)$job['reply_to_message_id']);}catch(Throwable $e){$r=array('ok'=>FALSE,'error'=>'secret_unavailable');}
    $data=array('status'=>$r['ok']?'sent':(($r['error']??'')==='uncertain'?'uncertain':'failed'),'last_error'=>$r['ok']?NULL:$r['error']);
    if($r['ok']){$data['sent_at']=gmdate('Y-m-d H:i:s');$sent++;}
    elseif(($r['error']??'')==='rate_limited' && (int)$job['attempts']<4){$data['status']='pending';$data['next_at']=gmdate('Y-m-d H:i:s',time()+$r['retry']);}
    if(!$this->db->where('id',$job['id'])->update('st_telegram_outbox',$data)){throw new RuntimeException('Delivery confirmation unavailable.');}
   }return array('ok'=>TRUE,'sent'=>$sent);
  }finally{$this->db->query("SELECT RELEASE_LOCK('study_telegram_worker')");}
 }
 public function delivery_history(){return $this->rows('SELECT id,category,status,attempts,last_error,created_at,sent_at,next_at FROM st_telegram_outbox ORDER BY id DESC LIMIT 20');}
}
