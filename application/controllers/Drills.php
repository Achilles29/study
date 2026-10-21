<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Drills extends MY_Controller
{
 public function __construct(){parent::__construct();$this->require_login();$this->load->model('Standalone_model');$this->output->set_header('Cache-Control: private, no-store')->set_header('X-Robots-Tag: noindex, nofollow');}
 private function token($context){$nonce=bin2hex(random_bytes(16));return $nonce.'.'.hash_hmac('sha256',$this->current_user['id'].':'.$context.':'.$nonce,$this->config->item('encryption_key'));}
 private function key($context){$s=$this->input->post('start_token',FALSE);if(!is_string($s)||!preg_match('/\A([a-f0-9]{32})\.([a-f0-9]{64})\z/',$s,$m)||!hash_equals(hash_hmac('sha256',$this->current_user['id'].':'.$context.':'.$m[1],$this->config->item('encryption_key')),$m[2])){show_error('Muat ulang formulir sebelum memulai.',400);exit;}return hash('sha256',$s);}
 public function index()
 {
  $this->require_http_methods(array('GET','HEAD','POST'));$grade=study_learner_grade_filter($this->current_user);$error='';$values=array('grade_id'=>(string)(study_grades()[$grade]['id']??''),'subject_id'=>'','count'=>'10','minutes'=>'20','option_shuffle'=>'1')+Practice_sheet::defaults();
  if($this->input->method(TRUE)==='POST'){
   foreach(array_keys($values) as $k){$values[$k]=$this->input->post($k,FALSE)??(Practice_sheet::defaults()[$k]??NULL);}$r=$this->Standalone_model->start($this->current_user,$values,$this->key('mandiri'));
   if($r['ok']){redirect('latihan/percobaan/'.$r['id'],'location',303);return;}$this->output->set_status_header($r['code']??503);$error=$r['message']??'Latihan belum dapat dimulai. Periksa isian dan riwayat sebelum mencoba lagi; jangan mengirim berulang.';
   foreach($values as &$v){if(!is_string($v)){$v='';}}unset($v);
  }
  $available=$this->Standalone_model->availability($grade);$subjects=$this->Bank_model->subjects();$quota=$this->Standalone_model->quota($this->current_user['id']);$active=$this->Standalone_model->active($this->current_user['id']);$token=$this->token('mandiri');
  $this->render_page('drills/index',compact('grade','error','values','available','subjects','quota','active','token')+array('page_title'=>'Latihan Mandiri'));
 }
 public function exams()
 {
  $this->require_http_methods(array('GET','HEAD'));$this->load->model('Lifetime_model');$included=$this->Lifetime_model->covered($this->current_user['id']);$grade=study_learner_grade_filter($this->current_user);$exams=$this->Exam_model->listing($grade);$this->render_page('drills/exams',compact('grade','exams','included')+array('page_title'=>'Simulasi Ujian'));
 }
 public function exam($id='0')
 {
  $this->require_http_methods(array('GET','HEAD','POST'));$exam=$this->Exam_model->get($id);if(!$exam||$exam['status']!=='published'){show_404();return;}$error='';
  if($this->input->method(TRUE)==='POST'){$r=$this->Standalone_model->start($this->current_user,array(),$this->key('exam:'.$id),(int)$id);if($r['ok']){redirect('latihan/percobaan/'.$r['id'],'location',303);return;}$this->output->set_status_header($r['code']??503);$error=$r['message']??'Simulasi belum dapat dimulai. Periksa riwayat atau hubungi pengelola.';}
  $this->load->model('Lifetime_model');$unlimited=$this->Lifetime_model->covered($this->current_user['id']);$can_start=(int)$exam['credit_price']===0||$this->Feature_credit_model->owns($this->current_user['id'],'exam',$id);$count=count($this->Exam_model->questions($id));$available=Exam_schedule::is_open($exam)&&$count>0;$notice=Exam_schedule::message($exam);if(!$count)$notice='Belum ada soal yang dapat dikerjakan.';$active=$this->Standalone_model->active($this->current_user['id']);$token=$this->token('exam:'.$id);$this->render_page('drills/exam',compact('exam','can_start','count','token','error','unlimited','available','notice','active')+array('page_title'=>'Simulasi Ujian'));
 }
 public function unlock($type='',$id='0')
 {
  $this->require_http_methods(array('GET','HEAD','POST'));$quote=$this->Feature_credit_model->quote($type,$id);if(!$quote){show_404();return;}$error='';$beneficiaries=array();
  if($this->current_user['account_kind']!=='child'){$beneficiaries=$this->Feature_credit_model->beneficiaries($this->current_user);}
  if($this->input->method(TRUE)==='POST'){
   if($this->input->post('confirm')!=='1'){show_error('Konfirmasi penerima dan harga sebelum menukar kredit.',400);return;}
   $r=$this->Feature_credit_model->buy($this->current_user,$this->input->post('learner_id',FALSE),$type,(string)$id,$this->input->post('version',FALSE));
   if($r['ok']){$this->session->set_flashdata('success',!empty($r['existing'])?'Akses ini sudah dimiliki; tidak ada kredit tambahan dipotong.':'Akses tersimpan untuk penerima pilihan Anda. Lihat rincian pada halaman ini.');redirect('latihan/buka/'.$type.'/'.$id,'location',303);return;}$this->output->set_status_header($r['code']??503);$error=$r['message']??'Penukaran belum berhasil atau harga/akses berubah. Periksa saldo dan akses sebelum mengulang.';
  }
  $this->load->model('Lifetime_model');$included=$this->Lifetime_model->covered($this->current_user['id']);$owned=array();foreach($beneficiaries as $b){$owned[$b['id']]=$this->Feature_credit_model->owns($b['id'],$type,$id);}
  $this->render_page('drills/unlock',compact('quote','error','beneficiaries','owned','included')+array('page_title'=>'Buka fitur dengan kredit'));
 }
}
