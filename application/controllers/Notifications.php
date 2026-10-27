<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Notifications extends MY_Controller
{
 private function pairing_error($reason)
 {
  return array(
   'not_found'=>'Chat pribadi belum terdeteksi. Kirim perintah /start berkode yang ditampilkan di bawah ke bot, lalu coba periksa lagi.',
   'updates_full'=>'Bot memiliki terlalu banyak pesan tertunda untuk dicari tanpa mengubah antreannya. Gunakan bot khusus atau isi ID chat secara manual.',
   'webhook_active'=>'Bot sedang memakai webhook sehingga pencarian otomatis tidak tersedia. Gunakan ID chat manual atau bot khusus tanpa webhook.',
   'invalid_token'=>'Token bot tidak berlaku. Perbarui token dan simpan dahulu.',
   'invalid_bot'=>'Akun bot tidak dapat dikenali. Periksa token yang tersimpan.',
  )[$reason]??'Telegram belum dapat diperiksa. Coba lagi nanti atau isi ID chat secara manual.';
 }
 public function __construct(){parent::__construct();$this->require_login();$this->load->model('Notification_model');$this->output->set_header('Cache-Control: private, no-store')->set_header('X-Robots-Tag: noindex, nofollow');}
 public function index()
 {
  $this->require_http_methods(array('GET','HEAD','POST'));
  if($this->input->method(TRUE)==='POST'){
   $action=$this->input->post('action',FALSE);if(!in_array($action,array('read','all'),TRUE)){show_error('Aksi tidak valid.',400);return;}
   if($action==='all' && $this->input->post('confirm')!=='1'){show_error('Centang konfirmasi tandai semua dibaca.',400);return;}
   $r=$this->Notification_model->mark($this->current_user,$this->input->post('id',FALSE),$action==='all');
   if(!$r['ok']){show_error('Pesan belum dapat diperbarui. Muat ulang halaman.', $r['code']??503);return;}
   redirect('notifikasi','location',303);return;
  }
  $status=$this->input->get('status',FALSE)??'unread';if(!in_array($status,array('all','unread','read'),TRUE)){show_error('Tab tidak valid.',400);return;}
  try{$inbox=$this->Notification_model->inbox($this->current_user['id'],$status,$this->get_positive_query_int('halaman'));}
  catch(Throwable $e){show_error('Pesan belum dapat dimuat. Coba kembali.',503);return;}
  $admin=in_array('system_admin',$this->current_user['roles'],TRUE);
  $this->render_page($admin?'admin/notifications':'notifications/index',compact('inbox','status')+array('page_title'=>'Kotak notifikasi','admin_section'=>'notifications'));
 }
 public function settings()
 {
  $this->require_role(array('system_admin'));$this->require_http_methods(array('GET','HEAD','POST'));$error='';
  $tab=$this->input->get('tab',FALSE)??'connection';
  if(!in_array($tab,array('connection','notifications','messages','ai','history'),TRUE)){$tab='connection';}
  try{
   $record=$this->Notification_model->settings();
   $this->load->model('Telegram_bot_model');$ai=$this->Telegram_bot_model->ai_settings();
   $pairing=$this->session->userdata('telegram_pair');
   if(!is_array($pairing) || ($pairing['user_id']??NULL)!==(int)$this->current_user['id'] || ($pairing['revision']??NULL)!==(int)$record['revision'] || ($pairing['expires']??0)<=time()){
    $pairing=NULL;$this->session->unset_userdata('telegram_pair');
   }
   if($this->input->method(TRUE)==='POST'){
    $action=$this->input->post('action',FALSE);
    if($action==='save_message_policy'){
     $tab='messages';$submitted_policy=$this->input->post(NULL,FALSE);
     $saved=$this->Notification_model->save_message_policy($this->current_user,$submitted_policy);
     if($saved['ok']){$this->session->set_flashdata('success','Jadwal dan template pesan tersimpan. Kejadian yang sedang dikumpulkan mengikuti jadwal ini; pesan yang sudah masuk antrean kirim tetap diproses.');redirect('kelola/pengaturan/telegram?tab=messages&category='.rawurlencode($submitted_policy['category']),'location',303);return;}
     $this->output->set_status_header($saved['code']??503);
     $error=array('invalid_policy'=>'Jenis pesan atau formulir tidak valid. Muat ulang halaman.','invalid_mode'=>'Pilih mode pengiriman yang tersedia. Trafik dikirim sebagai laporan berkala atau harian.','invalid_schedule'=>'Interval harus 5–10.080 menit dan jam harus dalam format HH:MM.','invalid_template'=>'Template wajib diisi, maksimal 600 karakter.','unknown_placeholder'=>'Ada variabel template yang tidak dikenal. Gunakan variabel yang tercantum di bawah formulir.','missing_details'=>'Sertakan {isi} untuk rincian kejadian. Template trafik boleh memakai {kunjungan} sebagai gantinya.')[$saved['reason']??'']??(($saved['code']??503)===409?'Pengaturan pesan telah berubah. Muat ulang halaman sebelum menyimpan kembali.':'Pengaturan pesan belum tersimpan. Coba lagi.');
    }elseif($action==='ai_save'){
     $tab='ai';
     $saved=$this->Telegram_bot_model->save_ai_settings($this->current_user,$this->input->post(NULL,FALSE));
     if($saved['ok']){$this->session->set_flashdata('success','Pengaturan percakapan AI tersimpan. Jika memilih Codex, selesaikan login akun ChatGPT untuk bot sebelum mencoba percakapan.');redirect('kelola/pengaturan/telegram?tab=ai','location',303);return;}
     $this->output->set_status_header($saved['code']??503);
     $error=array('invalid_key'=>'Format kunci OpenAI tidak valid. Salin kunci API lengkap dari OpenAI Platform.','key_required'=>'Masukkan kunci OpenAI sebelum mengaktifkan percakapan AI.','invalid_form'=>'Periksa batas harian, konfirmasi, dan kolom pengaturan AI.')[$saved['reason']??'']??(($saved['code']??503)===409?'Pengaturan AI berubah. Muat ulang halaman sebelum menyimpan lagi.':'Pengaturan AI belum dapat disimpan. Coba lagi.');
    }elseif($action==='pair_start'){
     $tab='connection';
     if(!$record['token_cipher']){$error='Simpan token bot terlebih dahulu, lalu mulai pemasangan chat pribadi.';}
     elseif($pairing){redirect('kelola/pengaturan/telegram?tab=connection','location',303);return;}
     else{
      $bot=$this->telegram_gateway->bot_username($this->telegram_gateway->open($record['token_cipher']));
      if(!$bot['ok']){$error=$this->pairing_error($bot['error']??'unavailable');}
      else{
       $now=time();$pairing=array('user_id'=>(int)$this->current_user['id'],'revision'=>(int)$record['revision'],'username'=>$bot['username'],'code'=>bin2hex(random_bytes(16)),'created'=>$now,'expires'=>$now+600,'last_checked'=>0);
       $this->session->set_userdata('telegram_pair',$pairing);
       redirect('kelola/pengaturan/telegram?tab=connection','location',303);return;
      }
     }
    }elseif($action==='pair_check'){
     $tab='connection';
     if(!$pairing){$error='Pemasangan sudah kedaluwarsa. Mulai lagi untuk memperoleh tautan baru.';}
     elseif($this->input->post('confirm')!=='1'){$error='Setujui tujuan chat pribadi sebelum menyimpannya.';}
     elseif((int)$pairing['last_checked']>time()-3){$error='Tunggu beberapa detik sebelum memeriksa kembali.';}
     else{
      $pairing['last_checked']=time();$this->session->set_userdata('telegram_pair',$pairing);
      $found=$this->telegram_gateway->find_private_chat($this->telegram_gateway->open($record['token_cipher']),$pairing['code'],(int)$pairing['created']);
      if(!$found['ok']){$error=$this->pairing_error($found['error']??'unavailable');}
      else{
       $values=array('revision'=>(string)$record['revision'],'token'=>'','chat_id'=>$found['chat_id'],'confirm'=>'1');
       foreach(array('enabled','payments','donations','help','visitors','members','children') as $key){$values[$key]=(string)$record[$key];}
       $saved=$this->Notification_model->save_settings($this->current_user,$values);
       if($saved['ok']){
        $this->session->unset_userdata('telegram_pair');
        $this->session->set_flashdata('success','Chat pribadi berhasil terhubung tanpa memasukkan ID. Jika pengiriman belum aktif, aktifkan pada tab Notifikasi.');
        redirect('kelola/pengaturan/telegram?tab=connection','location',303);return;
       }
       $error=($saved['code']??503)===409?'Pengaturan berubah. Muat ulang lalu mulai pemasangan lagi.':'Chat ditemukan, tetapi pengaturan belum tersimpan. Coba lagi.';
      }
     }
    }else{
     if($action==='save_connection'){
      $tab='connection';$values=$this->input->post(NULL,FALSE);
      foreach(array('enabled','payments','donations','help','visitors','members','children') as $key){$values[$key]=(string)$record[$key];}
      $r=$this->Notification_model->save_settings($this->current_user,$values);
     }
     elseif($action==='save_preferences'){
      $tab='notifications';$values=$this->input->post(NULL,FALSE);
      $values['token']='';$values['chat_id']=(string)$record['chat_id'];
      $r=$this->Notification_model->save_settings($this->current_user,$values);
     }
     elseif($action==='save'){$tab='notifications';$r=$this->Notification_model->save_settings($this->current_user,$this->input->post(NULL,FALSE));}
     elseif($action==='test'){$tab='history';$r=$this->Notification_model->test_message($this->current_user);}
     else{$r=array('ok'=>FALSE,'code'=>400);}
     if($r['ok']){
      if($action==='save' || $action==='save_connection'){$this->session->unset_userdata('telegram_pair');}
      $success=$action==='test'?'Pesan uji masuk antrean. Pengiriman diperiksa sekitar setiap 10 detik; muat ulang halaman untuk melihat status Terkirim atau Gagal.':(!empty($r['activation_pending'])?'Pengaturan tersimpan. Pengiriman tetap nonaktif sampai token bot dan chat tujuan tersedia. Hubungkan chat pribadi, lalu aktifkan pengiriman.':'Pengaturan tersimpan. Antrean lama yang belum dikirim dibatalkan.');
      $this->session->set_flashdata('success',$success);redirect('kelola/pengaturan/telegram?tab='.$tab,'location',303);return;
     }
     $this->output->set_status_header($r['code']??503);
     $error=array('invalid_token'=>'Token bot tidak sesuai format dari BotFather. Salin token lengkap tanpa awalan "bot" atau teks lain.','invalid_chat_id'=>'ID chat harus angka positif untuk pribadi atau angka negatif untuk grup. Kosongkan jika ingin menghubungkan chat otomatis.','confirm_required'=>'Centang persetujuan di bawah formulir sebelum menyimpan.','invalid_form'=>'Formulir tidak lengkap. Muat ulang halaman lalu coba lagi.')[$r['reason']??'']??(($r['code']??503)===409?($action==='test'?'Pesan uji belum bisa dikirim. Hubungkan chat tujuan, aktifkan pengiriman, lalu simpan pengaturan.':'Pengaturan berubah. Muat ulang halaman lalu coba lagi.'):'Belum tersimpan. Periksa token bot, ID chat tujuan, konfirmasi dan izin akun.');
    }
   }
   $history=$this->Notification_model->delivery_history();$policies=$this->Telegram_schedule_model->policies();$pending_counts=$this->Telegram_schedule_model->pending_counts();$message_categories=$this->telegram_message_policy->categories();
   $category=$submitted_policy['category']??$this->input->get('category',FALSE)??'visitors';if(!is_string($category)||!isset($message_categories[$category]))$category='visitors';
   $message_policy=$policies[$category];
   if(isset($submitted_policy))foreach(array('revision','mode','interval_minutes','daily_time','template','send_empty') as $field)if(isset($submitted_policy[$field])&&is_string($submitted_policy[$field]))$message_policy[$field]=$submitted_policy[$field];
   $this->render_page('admin/telegram',compact('record','error','history','pairing','ai','tab','policies','pending_counts','message_categories','category','message_policy')+array('page_title'=>'Notifikasi Telegram','admin_section'=>'telegram'));
  }catch(Throwable $e){log_message('error','Notification settings unavailable; secret details omitted.');show_error('Pengaturan notifikasi belum dapat diproses.',503);}
 }
}
