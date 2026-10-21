<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Exams extends MY_Controller
{
 public function __construct(){parent::__construct();$this->require_role(array('system_admin'));$this->load->model('Exam_model');$this->output->set_header('Cache-Control: private, no-store')->set_header('X-Robots-Tag: noindex, nofollow');}
 public function index(){$this->require_http_methods(array('GET','HEAD'));$grade=study_grade_filter();$exams=$this->Exam_model->listing($grade,TRUE);$this->render_page('admin/exams/index',compact('grade','exams')+array('page_title'=>'Simulasi Ujian','admin_section'=>'exams'));}
 public function edit($id='0')
 {
  $this->require_http_methods(array('GET','HEAD','POST'));$exam=$id!=='0'?$this->Exam_model->get($id):NULL;if($id!=='0'&&!$exam){show_404();return;}
  $step=$this->get_scalar_query_param('langkah',20);if($step==='')$step='pengaturan';if(!in_array($step,array('pengaturan','soal','aktifkan'),TRUE)){show_error('Langkah tidak valid.',400);return;}if(!$exam)$step='pengaturan';
  $error='';$values=$exam??array('title'=>'','grade_id'=>'','duration_minutes'=>'30','max_daily'=>'3','credit_price'=>'0','revision'=>'0','status'=>'draft','question_shuffle'=>'1','option_shuffle'=>'1');$values+=$exam?Practice_sheet::read($exam['sheet_rules_json']):Practice_sheet::defaults();
  foreach(array('available_from','available_until') as $k)$values[$k]=Exam_schedule::to_form($exam[$k]??NULL);
  if($this->input->method(TRUE)==='POST'){
   $action=$this->input->post('action',FALSE);
   if($action==='save'){foreach(array('title','grade_id','duration_minutes','max_daily','credit_price','revision','status','question_shuffle','option_shuffle','view_mode','focus_guard','focus_limit','available_from','available_until') as $k)$values[$k]=$this->input->post($k,FALSE)??($values[$k]??NULL);$r=$this->Exam_model->save($this->current_user,(int)$id,$values);$next='soal';}
   elseif(in_array($action,array('add','remove'),TRUE)&&$exam){$r=$this->Exam_model->select($this->current_user,(int)$id,$this->input->post('revision',FALSE),$this->input->post('questions',FALSE),$action==='remove');$next='soal';}
   elseif(in_array($action,array('publish','draft','archive'),TRUE)&&$exam){$r=$this->Exam_model->set_status($this->current_user,(int)$id,$this->input->post('revision',FALSE),array('publish'=>'published','draft'=>'draft','archive'=>'archived')[$action]);$next=$action==='draft'?'soal':'aktifkan';}
   else{$r=array('ok'=>FALSE,'code'=>400,'message'=>'Aksi tidak valid.');$next=$step;}
   if($r['ok']){$this->session->set_flashdata('success',$action==='publish'?'Simulasi diaktifkan sesuai jadwal. Sesi tanpa soal belum dapat dikerjakan.':($action==='save'?'Pengaturan tersimpan. Lanjutkan memilih atau mengimpor soal.':'Perubahan tersimpan. Percobaan yang sudah dimulai tidak berubah.'));redirect('kelola/simulasi-ujian/'.$r['id'].'?langkah='.$next,'location',303);return;}
   $this->output->set_status_header($r['code']??503);$error=$r['message']??'Penyimpanan belum berhasil. Muat ulang dan periksa kembali; tidak ada perubahan sebagian.';foreach($values as &$v)if(!is_string($v)&&!is_int($v))$v='';unset($v);
  }
  $selected=$exam?$this->Exam_model->questions($id):array();$selected_total=count($selected);$selected_page=min($this->get_positive_query_int('terpilih'),max(1,(int)ceil($selected_total/20)));$selected_ids=array_column($selected,'id');$not_ready=count(array_filter($selected,static fn($q)=>$q['status']!=='published'));$selected=array_slice($selected,($selected_page-1)*20,20);
  $subject=$this->get_scalar_query_param('mapel',10);if($subject!==''&&!ctype_digit($subject)){show_error('Filter mapel tidak valid.',400);return;}$subjects=$this->Bank_model->subjects();$bank=$exam&&$step==='soal'?$this->Bank_model->listing(study_grade_code($exam['grade_id']),(int)$subject,'all',$this->get_positive_query_int('halaman')):NULL;
  $this->render_page('admin/exams/edit',compact('exam','values','selected','selected_total','selected_page','selected_ids','not_ready','bank','subject','subjects','step','error')+array('page_title'=>$exam?'Kelola Simulasi Ujian':'Buat Simulasi Ujian','admin_section'=>'exams'));
 }
}
