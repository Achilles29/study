<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Notifications extends MY_Controller
{
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
  try{
   $record=$this->Notification_model->settings();
   if($this->input->method(TRUE)==='POST'){
    $action=$this->input->post('action',FALSE);
    if($action==='save'){$r=$this->Notification_model->save_settings($this->current_user,$this->input->post(NULL,FALSE));}
    elseif($action==='test'){$r=$this->Notification_model->test_message($this->current_user);}
    else{$r=array('ok'=>FALSE,'code'=>400);}
    if($r['ok']){$this->session->set_flashdata('success',$action==='test'?'Pesan uji masuk antrean. Lihat status pengiriman setelah worker berjalan.':'Pengaturan tersimpan. Token tidak ditampilkan kembali; antrean lama yang belum dikirim dibatalkan.');redirect('kelola/pengaturan/telegram','location',303);return;}
    $this->output->set_status_header($r['code']??503);$error=($r['code']??503)===409?'Pengaturan berubah atau Telegram belum aktif. Muat ulang dan periksa pengaturan.':'Belum tersimpan. Periksa token bot, ID grup, konfirmasi dan izin akun.';
    $rev=$this->input->post('revision',FALSE);if(is_string($rev) && ctype_digit($rev)){$record['revision']=$rev;}
   }
   $history=$this->Notification_model->delivery_history();
   $this->render_page('admin/telegram',compact('record','error','history')+array('page_title'=>'Notifikasi Telegram','admin_section'=>'telegram'));
  }catch(Throwable $e){log_message('error','Notification settings unavailable; secret details omitted.');show_error('Pengaturan notifikasi belum dapat diproses.',503);}
 }
}
