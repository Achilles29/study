<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Exams extends MY_Controller
{
 public function __construct(){parent::__construct();$this->require_role(array('system_admin'));$this->load->model('Exam_model');$this->output->set_header('Cache-Control: private, no-store')->set_header('X-Robots-Tag: noindex, nofollow');}
 public function index()
 {
  $this->require_http_methods(array('GET','HEAD'));
  $filters=array('grade'=>study_grade_filter(),'search'=>$this->get_scalar_query_param('q',100),'status'=>$this->get_scalar_query_param('status',12),'difficulty'=>$this->get_scalar_query_param('kesulitan',12),'parent'=>$this->get_scalar_query_param('pantau',3),'rows'=>(int)$this->get_scalar_query_param('baris',3),'page'=>$this->get_positive_query_int('halaman'));
  if(!in_array($filters['status'],array('','draft','published','archived'),TRUE)||!in_array($filters['difficulty'],array('','easy','medium','hard','mixed'),TRUE)||!in_array($filters['parent'],array('','0','1'),TRUE)||!in_array($filters['rows'],array(0,10,25,50,100),TRUE)){show_error('Filter simulasi tidak valid.',400);return;}
  if($filters['rows']===0)$filters['rows']=25;
  $report=$this->Exam_model->admin_listing($filters);
  $this->render_page('admin/exams/index',compact('filters','report')+array('page_title'=>'Simulasi Ujian','admin_section'=>'exams'));
 }
 public function import_report()
 {
  $this->require_http_methods(array('GET','HEAD'));
  $rows=$this->db->query('SELECT p.source_session_id,p.exam_id,p.source_json,p.issue,e.status target_status,(SELECT COUNT(*) FROM st_pustaka_import_links l WHERE l.source_session_id=p.source_session_id) source_links,(SELECT COUNT(*) FROM st_exam_questions x WHERE x.exam_id=p.exam_id) target_questions FROM st_pustaka_import_sessions p LEFT JOIN st_exam_templates e ON e.id=p.exam_id ORDER BY p.source_session_id DESC')->result_array();
  $filter=$this->get_scalar_query_param('filter',20);if(!in_array($filter,array('','issues','archive'),TRUE)){show_error('Filter impor tidak valid.',400);return;}
  $summary=array('sessions'=>count($rows),'active'=>0,'drafts'=>0,'archive_only'=>0,'issues'=>0);
  foreach($rows as &$row){$row['source']=json_decode($row['source_json'],TRUE)?:array();unset($row['source_json']);if(!$row['exam_id'])$summary['archive_only']++;elseif($row['target_status']==='published')$summary['active']++;else $summary['drafts']++;if($row['issue'])$summary['issues']++;}unset($row);
  if($filter==='issues')$rows=array_values(array_filter($rows,static function($r){return $r['issue']!==NULL;}));
  if($filter==='archive')$rows=array_values(array_filter($rows,static function($r){return $r['exam_id']===NULL;}));
  $this->render_page('admin/exams/import_report',compact('rows','summary','filter')+array('page_title'=>'Impor latihan Pustaka','admin_section'=>'exams'));
 }
 public function edit($id='0')
 {
  $this->require_http_methods(array('GET','HEAD','POST'));$exam=$id!=='0'?$this->Exam_model->get($id):NULL;if($id!=='0'&&!$exam){show_404();return;}
  $step=$this->get_scalar_query_param('langkah',20);if($step==='')$step='pengaturan';if(!in_array($step,array('pengaturan','soal','aktifkan'),TRUE)){show_error('Langkah tidak valid.',400);return;}if(!$exam)$step='pengaturan';
  $error='';$values=$exam??array('title'=>'','grade_id'=>'','question_difficulty'=>'','draw_count'=>'0','duration_minutes'=>'30','max_daily'=>'3','credit_price'=>'0','revision'=>'0','status'=>'draft','question_shuffle'=>'1','option_shuffle'=>'1','parent_live_enabled'=>'0');$values+=$exam?Practice_sheet::read($exam['sheet_rules_json']):Practice_sheet::defaults();$values['question_difficulty']=$values['question_difficulty']??'';
  foreach(array('available_from','available_until') as $k)$values[$k]=Exam_schedule::to_form($exam[$k]??NULL);
  if($this->input->method(TRUE)==='POST'){
   $action=$this->input->post('action',FALSE);
   if($action==='save'){foreach(array('title','grade_id','question_difficulty','draw_count','duration_minutes','max_daily','credit_price','revision','status','question_shuffle','option_shuffle','parent_live_enabled','view_mode','focus_guard','focus_limit','available_from','available_until') as $k)$values[$k]=$this->input->post($k,FALSE)??($values[$k]??NULL);$r=$this->Exam_model->save($this->current_user,(int)$id,$values);$next='soal';}
   elseif(in_array($action,array('add','remove'),TRUE)&&$exam){$r=$this->Exam_model->select($this->current_user,(int)$id,$this->input->post('revision',FALSE),$this->input->post('questions',FALSE),$action==='remove');$next='soal';}
   elseif(in_array($action,array('publish','draft','archive'),TRUE)&&$exam){$r=$this->Exam_model->set_status($this->current_user,(int)$id,$this->input->post('revision',FALSE),array('publish'=>'published','draft'=>'draft','archive'=>'archived')[$action]);$next=$action==='draft'?'soal':'aktifkan';}
   else{$r=array('ok'=>FALSE,'code'=>400,'message'=>'Aksi tidak valid.');$next=$step;}
   if($r['ok']){$this->session->set_flashdata('success',$action==='publish'?'Simulasi diaktifkan sesuai jadwal. Sesi tanpa soal belum dapat dikerjakan.':($action==='save'?'Pengaturan tersimpan. Izin pantau orang tua berlaku langsung pada sesi aktif. Lanjutkan memilih atau mengimpor soal.':'Perubahan tersimpan. Susunan soal percobaan yang sudah dimulai tidak berubah.'));redirect('kelola/simulasi-ujian/'.$r['id'].'?langkah='.$next,'location',303);return;}
   $this->output->set_status_header($r['code']??503);$error=$r['message']??'Penyimpanan belum berhasil. Muat ulang dan periksa kembali; tidak ada perubahan sebagian.';foreach($values as &$v)if(!is_string($v)&&!is_int($v))$v='';unset($v);
  }
  $selected=$exam?$this->Exam_model->questions($id):array();$selected_total=count($selected);$selected_page=min($this->get_positive_query_int('terpilih'),max(1,(int)ceil($selected_total/20)));$selected_ids=array_column($selected,'id');$not_ready=count(array_filter($selected,static fn($q)=>$q['status']!=='published'));$selected=array_slice($selected,($selected_page-1)*20,20);
  $subject=$this->get_scalar_query_param('mapel',10);$bank_difficulty=$this->get_scalar_query_param('kesulitan',12);if($subject!==''&&!ctype_digit($subject)||!in_array($bank_difficulty,array('','easy','medium','hard','untagged'),TRUE)){show_error('Filter soal tidak valid.',400);return;}$subjects=$this->Bank_model->subjects();$effective_difficulty=$exam['question_difficulty']??$bank_difficulty;$bank=$exam&&$step==='soal'?$this->Bank_model->listing(study_grade_code($exam['grade_id']),(int)$subject,'all',$this->get_positive_query_int('halaman'),$effective_difficulty):NULL;
  $import_origin=$exam?$this->db->where('exam_id',$exam['id'])->get('st_pustaka_import_sessions')->row_array():NULL;
  $this->render_page('admin/exams/edit',compact('exam','values','selected','selected_total','selected_page','selected_ids','not_ready','bank','subject','bank_difficulty','subjects','step','error','import_origin')+array('page_title'=>$exam?'Kelola Simulasi Ujian':'Buat Simulasi Ujian','admin_section'=>'exams'));
 }
}
