<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once dirname(__DIR__).'/libraries/Practice_sheet.php';
require_once dirname(__DIR__).'/libraries/Exam_schedule.php';
class Exam_model extends CI_Model
{
 public function __construct(){parent::__construct();$this->load->model('Bank_model');}
 public function get($id){return $this->Bank_model->rows('SELECT * FROM st_exam_templates WHERE id=?',array($id))[0]??NULL;}
 public function questions($id){return $this->Bank_model->rows('SELECT q.id,q.prompt,q.status FROM st_exam_questions x JOIN st_questions q ON q.id=x.question_id WHERE x.exam_id=? ORDER BY x.sort_order,q.id',array($id));}
 private function fail($message,$code=400){return array('ok'=>FALSE,'code'=>$code,'message'=>$message);}
 public function listing($grade,$admin=FALSE)
 {
  $args=array();$where=$admin?'1=1':"e.status='published' AND EXISTS(SELECT 1 FROM st_exam_questions x WHERE x.exam_id=e.id)";
  if($grade!==''){$where.=' AND e.grade_id=?';$args[]=study_grades()[$grade]['id']??0;}
  return $this->Bank_model->rows("SELECT e.*,(SELECT COUNT(*) FROM st_exam_questions x WHERE x.exam_id=e.id) question_count FROM st_exam_templates e WHERE $where ORDER BY e.id DESC LIMIT 100",$args);
 }
 public function save($user,$id,$v)
 {
  $sheet=Practice_sheet::validate($v);if(!$sheet)return $this->fail('Periksa tampilan lembar soal dan batas pantau fokus.');
  foreach(array('title','grade_id','duration_minutes','max_daily','credit_price','revision','status','question_shuffle','option_shuffle') as $k){if(!is_string($v[$k]??NULL))return $this->fail('Isian pengaturan harus berupa teks atau angka yang valid.');}
  $grade=study_child_grade($v['grade_id']);$title=trim($v['title']);
  if(!$grade)return $this->fail('Pilih kelas simulasi terlebih dahulu.');
  if(!mb_check_encoding($title,'UTF-8')||mb_strlen($title)<3||mb_strlen($title)>180||preg_match('/[\x00-\x1F\x7F]/',$title))return $this->fail('Judul harus berisi 3–180 karakter.');
  if(!ctype_digit($v['revision'])||!in_array($v['status'],array('draft','published','archived'),TRUE))return $this->fail('Status atau revisi tidak valid. Muat ulang halaman.');
  if($v['duration_minutes']==='')$v['duration_minutes']='0';
  foreach(array('duration_minutes'=>array(0,180),'max_daily'=>array(1,20),'credit_price'=>array(0,50),'question_shuffle'=>array(0,1),'option_shuffle'=>array(0,1)) as $k=>$range){if(!ctype_digit($v[$k])||(int)$v[$k]<$range[0]||(int)$v[$k]>$range[1])return $this->fail(array('duration_minutes'=>'Durasi harus 0–180 menit atau kosong.','max_daily'=>'Batas percobaan harus 1–20 per hari.','credit_price'=>'Harga kredit harus 0–50.','question_shuffle'=>'Pilihan acak soal tidak valid.','option_shuffle'=>'Pilihan acak jawaban tidak valid.')[$k]);}
  try{$from=Exam_schedule::from_form($v['available_from']??'');$until=Exam_schedule::from_form($v['available_until']??'');}catch(InvalidArgumentException $e){return $this->fail($e->getMessage());}
  if($from&&$until&&$until<=$from)return $this->fail('Tanggal berakhir harus setelah tanggal mulai.');
  return $this->study_transaction->run($this->db,function()use($user,$id,$v,$grade,$title,$sheet,$from,$until){
   $this->Bank_model->guard();if(!$this->Bank_model->administrator($user))return $this->fail('Akses pengelola tidak berlaku.',403);
   $old=$id?$this->get($id):NULL;if(($id&&!$old)||(int)$v['revision']!==(int)($old['revision']??0))return $this->fail('Pengaturan sudah berubah di halaman lain. Muat ulang sebelum menyimpan.',409);
   $qs=$old?$this->questions($id):array();
   if($qs&&(int)$grade['id']!==(int)$old['grade_id'])return $this->fail('Kelas belum bisa diubah karena ada soal tertaut. Lepas soal dahulu pada langkah Pilih soal.',409);
   $status=$old?$v['status']:'draft';
   if($status==='published'&&count(array_filter($qs,static function($q){return $q['status']!=='published';})))return $this->fail('Masih ada soal draft/arsip. Aktifkan di bank soal atau lepaskan dari simulasi, lalu coba lagi.',409);
   $now=gmdate('Y-m-d H:i:s');$data=array('title'=>$title,'grade_id'=>$grade['id'],'status'=>$status,'revision'=>(int)($old['revision']??0)+1,'updated_at'=>$now,'available_from'=>$from,'available_until'=>$until);
   foreach(array('duration_minutes','max_daily','credit_price','question_shuffle','option_shuffle') as $k)$data[$k]=(int)$v[$k];
   $data['sheet_rules_json']=json_encode($sheet,JSON_THROW_ON_ERROR);
   if($old)$this->db->where('id',$id)->update('st_exam_templates',$data);else{$data['created_at']=$now;$this->db->insert('st_exam_templates',$data);$id=(int)$this->db->insert_id();}
   $this->audit($user,$id,'exam.saved');return array('ok'=>TRUE,'id'=>$id);
  });
 }
 public function set_status($user,$id,$revision,$status)
 {
  if(!is_string($revision)||!in_array($status,array('draft','published','archived'),TRUE))return $this->fail('Status tidak valid.');
  $e=$this->get($id);if(!$e)return $this->fail('Simulasi tidak ditemukan.',404);
  $v=array();foreach(array('title','grade_id','duration_minutes','max_daily','credit_price','question_shuffle','option_shuffle') as $k)$v[$k]=(string)$e[$k];
  $v+=Practice_sheet::read($e['sheet_rules_json']);$v['revision']=$revision;$v['status']=$status;$v['available_from']=Exam_schedule::to_form($e['available_from']);$v['available_until']=Exam_schedule::to_form($e['available_until']);
  return $this->save($user,$id,$v);
 }
 public function select($user,$id,$revision,$ids,$remove=FALSE)
 {
  if(!is_string($revision)||!ctype_digit($revision)||!is_array($ids)||!$ids)return $this->fail('Centang sedikitnya satu soal yang ingin ditambahkan atau dilepas.');
  foreach($ids as $q)if(!is_string($q)||!preg_match('/\A[1-9][0-9]{0,18}\z/',$q))return $this->fail('ID soal tidak valid.');
  $ids=array_unique(array_map('intval',$ids));
  return $this->study_transaction->run($this->db,function()use($user,$id,$revision,$ids,$remove){
   $this->Bank_model->guard();if(!$this->Bank_model->administrator($user))return $this->fail('Akses pengelola tidak berlaku.',403);
   $e=$this->get($id);if(!$e||(int)$e['revision']!==(int)$revision)return $this->fail('Susunan soal sudah berubah. Muat ulang halaman.',409);
   if($e['status']!=='draft')return $this->fail('Klik Ubah susunan soal untuk membuka kembali draft terlebih dahulu.',409);
   $existing=array_map('intval',array_column($this->questions($id),'id'));$next=(int)$this->Bank_model->rows('SELECT COALESCE(MAX(sort_order),-1)+1 n FROM st_exam_questions WHERE exam_id=?',array($id))[0]['n'];
   foreach($ids as $q){if($remove){$this->db->where('exam_id',$id)->where('question_id',$q)->delete('st_exam_questions');continue;}
    if(!$this->Bank_model->rows('SELECT question_id FROM st_bank_scopes WHERE question_id=? AND grade_id=?',array($q,$e['grade_id'])))return $this->fail('Ada soal yang tidak sesuai kelas simulasi. Tidak ada perubahan yang disimpan.');
    if(in_array($q,$existing,TRUE))continue;$this->db->insert('st_exam_questions',array('exam_id'=>$id,'question_id'=>$q,'sort_order'=>$next++));$existing[]=$q;
   }
   $this->db->where('id',$id)->update('st_exam_templates',array('revision'=>(int)$e['revision']+1,'updated_at'=>gmdate('Y-m-d H:i:s')));$this->audit($user,$id,'exam.questions_selected');return array('ok'=>TRUE,'id'=>(int)$id);
  });
 }
 private function audit($u,$id,$event){$this->db->insert('st_audit_events',array('actor_user_id'=>$u['id'],'event_type'=>$event,'entity_type'=>'exam_template','entity_id'=>$id,'details_json'=>'{}','created_at'=>gmdate('Y-m-d H:i:s')));}
}
