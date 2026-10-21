<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Reviews extends MY_Controller
{
 public function preview()
 {
  $this->require_role(array('system_admin'));
  $this->require_http_methods(array('GET','HEAD'));
  $this->output->set_header('Cache-Control: private, no-store');
  $this->output->set_header('X-Robots-Tag: noindex, nofollow');
  $this->load->library('review_preview');
  $report=$this->review_preview->listing($this->get_positive_query_int('halaman'));
  $this->render_page('reviews/index',array('report'=>$report,'review_preview'=>TRUE,
   'page_title'=>'Pratinjau 1.000 ulasan contoh','is_content_preview'=>TRUE,'preview_theme'=>'all_ages'));
 }
 public function index()
 {
  $this->require_http_methods(array('GET','HEAD'));$this->load->model('Review_model');
  try{$report=$this->Review_model->listing('published',$this->get_positive_query_int('halaman'));$this->render_page('reviews/index',compact('report')+array('page_title'=>'Ulasan pengguna Project Pintar'));}catch(Throwable $e){show_error('Ulasan belum dapat dimuat.',503);}
 }
 public function write()
 {
  $this->require_login(array('adult','parent'));$this->require_http_methods(array('GET','HEAD','POST'));$this->load->model('Review_model');$error='';
  $record=$this->Review_model->own($this->current_user['id']);$values=$record?:array('display_name'=>'','body'=>'','rating'=>'5','revision'=>'0');
  if($this->input->method(TRUE)==='POST'){
   $v=$this->input->post(NULL,FALSE);$r=$this->Review_model->save($this->current_user,$v);if($r['ok']){$this->session->set_flashdata('success','Ulasan tersimpan untuk moderasi. Nama tampilan dan pesan dipublikasikan setelah diperiksa.');redirect('ulasan/tulis','location',303);return;}
   $this->output->set_status_header($r['code']??503);$error=($r['code']??0)===409?'Ulasan berubah. Muat ulang sebelum menyimpan.':'Periksa nama tampilan, bintang, pesan, persetujuan dan verifikasi email.';foreach(array('display_name','body','rating','revision') as $k){if(is_string($v[$k]??NULL)){$values[$k]=mb_substr($v[$k],0,2000);}}
  }
  $this->render_page('reviews/write',compact('record','values','error')+array('page_title'=>'Tulis ulasan'));
 }
 public function admin()
 {
  $this->require_role(array('system_admin'));$this->require_http_methods(array('GET','HEAD','POST'));$this->load->model('Review_model');
  if($this->input->method(TRUE)==='POST'){$r=$this->Review_model->moderate($this->current_user,$this->input->post('id',FALSE),$this->input->post('revision',FALSE),$this->input->post('status',FALSE),$this->input->post('reason',FALSE));if(!$r['ok']){show_error('Moderasi belum disimpan. Muat ulang dan periksa status, alasan serta versi ulasan.',$r['code']??503);return;}$this->session->set_flashdata('success','Moderasi tersimpan dan diaudit.');redirect('kelola/ulasan','location',303);return;}
  $status=$this->input->get('status',FALSE)??'pending';$demo=$this->input->get('simulasi',FALSE)??'0';if(!in_array($status,array('all','pending','published','hidden'),TRUE)||!in_array($demo,array('0','1'),TRUE)){show_error('Filter tidak valid.',400);return;}
  $report=$this->Review_model->listing($status,$this->get_positive_query_int('halaman'),TRUE,$demo==='1');$this->render_page('admin/reviews',compact('report','status','demo')+array('page_title'=>'Moderasi ulasan','admin_section'=>'reviews'));
 }
}
