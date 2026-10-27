<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class English_course extends MY_Controller
{
 public function _remap($method,$params=array())
 {
  $allowed=array('index','unit','start','run','history','submit','submission','audio','submission_audio','family','manage','review');
  if(!in_array($method,$allowed,TRUE)){show_404();return;}
  $this->require_login();$this->output->set_header('Cache-Control: private, no-store')->set_header('X-Robots-Tag: noindex, nofollow');
  $this->require_http_methods(in_array($method,array('unit','run','submit','manage','review'),TRUE)?array('GET','HEAD','POST'):($method==='start'?array('POST'):array('GET','HEAD')));
  if(in_array($method,array('manage','review'),TRUE))$this->require_role(array('system_admin'));
  $this->load->model('English_course_model','course');$this->load->helper('english_course');$this->db->db_debug=FALSE;
  try{call_user_func_array(array($this,$method),$params);}catch(Throwable $e){log_message('error','English course request failed without exposing answers or credentials.');show_error('Kelas belum dapat dimuat. Progres tersimpan tetap tersedia; coba muat ulang.',503);}
 }
 private function id($id){if(!is_string($id)||!preg_match('/^[1-9][0-9]{0,17}$/D',$id)){show_404();exit;}return $id;}
 private function user(){return $this->current_user['id'];}
 private function token($scope,$nonce=NULL){$nonce=$nonce??bin2hex(random_bytes(16));return $nonce.'.'.hash_hmac('sha256',$this->user().':english:'.$scope.':'.$nonce,$this->config->item('encryption_key'));}
 private function verify($scope){$v=$this->input->post('start_token',FALSE);if(!is_string($v)||!preg_match('/^[a-f0-9]{32}\.[a-f0-9]{64}$/D',$v)||!hash_equals($this->token($scope,substr($v,0,32)),$v)){show_error('Formulir kedaluwarsa atau tidak valid. Buka kembali halaman.',400);exit;}return hash('sha256',$v);}
 private function enabled($level){if(!$this->course->available($level)){show_error('Level ini sedang dijeda atau belum diterbitkan. Riwayat belajar tetap tersimpan.',403);exit;}}
 private function fail($r){$this->output->set_status_header($r['code']);return array(400=>'Lengkapi jawaban/isian sesuai petunjuk.',403=>'Aksi tidak tersedia untuk akun atau level ini.',404=>'Data tidak ditemukan.',409=>'Data berubah, tugas serupa masih menunggu, atau syarat checkpoint belum terpenuhi. Muat ulang dan periksa kembali.',503=>'Penyimpanan belum dapat dipastikan. Muat ulang sebelum mencoba lagi.')[$r['code']]??'Permintaan tidak dapat diproses.';}
 private function page($view,$data=array()){$this->render_page($view,$data+array('page_title'=>'English Hack · Kelas Bahasa Inggris','content'=>$this->english_course_content->data));}
 public function index()
 {
  $level=$this->get_scalar_query_param('level',16);if(!$this->english_course_content->level($level))$level='foundation';
  $this->page('english_course/index',array('settings'=>$this->course->settings(),'levels'=>$this->course->levels(),'progress'=>$this->course->progress($this->user()),'summary'=>$this->course->summary($this->user()),'selected'=>$level,'q'=>$this->get_scalar_query_param('q',80),'checkpoint_token'=>$this->token('start:checkpoint:'.$level)));
 }
 public function unit($id='')
 {
  $unit=$this->english_course_content->unit($id);if(!$unit){show_404();return;}$this->enabled($unit['level_id']);$error='';
  if($this->input->method(TRUE)==='POST'){$r=$this->course->studied($this->user(),$id);if($r['ok']){$this->session->set_flashdata('success','Materi ditandai sudah dipelajari. Lanjutkan dengan latihan dan tugas.');redirect('belajar/kelas-inggris/unit/'.$id,'location',303);return;}$error=$this->fail($r);}
  $this->page('english_course/unit',array('unit'=>$unit,'level'=>$this->english_course_content->level($unit['level_id']),'progress'=>$this->course->progress($this->user())[$id]??NULL,'tokens'=>array('practice'=>$this->token('start:practice:'.$id),'review'=>$this->token('start:review:'.$id)),'portfolio'=>$this->course->portfolio($this->user(),$id),'error'=>$error));
 }
 public function start($kind='',$unit='')
 {
  $key=$this->verify('start:'.$kind.':'.$unit);$r=$this->course->start($this->user(),$unit,$kind,$key);
  if($r['ok']){redirect('belajar/kelas-inggris/latihan/'.$r['id'],'location',303);return;}show_error($this->fail($r),$r['code']);
 }
 public function run($id='')
 {
  $id=$this->id($id);$run=$this->course->attempt($this->user(),$id);if(!$run){show_404();return;}$error='';
  if($this->input->method(TRUE)==='POST'){$r=$this->course->finish($this->user(),$id,$this->input->post('revision',FALSE),$this->input->post('answers',FALSE));if($r['ok']){redirect('belajar/kelas-inggris/latihan/'.$id,'location',303);return;}$error=$this->fail($r);$run=$this->course->attempt($this->user(),$id);}
  $snapshot=json_decode($run['snapshot_json'],TRUE,512,JSON_THROW_ON_ERROR);$answers=json_decode($run['answers_json']??'{}',TRUE);unset($run['snapshot_json'],$run['answers_json'],$run['start_key']);
  if($run['status']==='running'){foreach($snapshot['questions'] as &$q){unset($q['answer_index'],$q['answers'],$q['explanation']);}unset($q);}
  $this->page('english_course/attempt',compact('run','snapshot','answers','error')+array('available'=>$this->course->available($run['level_id']),'submitted'=>$error!==''&&is_array($this->input->post('answers',FALSE))?$this->input->post('answers',FALSE):array()));
 }
 private function portfolio_data($user){$total=$this->course->portfolio_count($user);$page=min($this->get_positive_query_int('karya'),max(1,(int)ceil($total/20)));return array('portfolio'=>$this->course->portfolio($user,NULL,$page),'portfolio_page'=>$page,'portfolio_total'=>$total);}
 public function history()
 {
  $this->page('english_course/history',$this->course->history($this->user(),$this->get_positive_query_int('halaman'))+$this->portfolio_data($this->user())+array('child'=>NULL,'base'=>'belajar/kelas-inggris/riwayat','summary'=>$this->course->summary($this->user())));
 }
 private function upload()
 {
  if(empty($_FILES['recording'])||$_FILES['recording']['error']===UPLOAD_ERR_NO_FILE)return NULL;
  $f=$_FILES['recording'];if(!is_int($f['error'])||$f['error']!==UPLOAD_ERR_OK||!is_string($f['tmp_name'])||!is_uploaded_file($f['tmp_name'])||$f['size']>8*1024*1024||$f['size']<100)throw new InvalidArgumentException('Rekaman maksimal 8 MB. Periksa format dan ukuran berkas.');
  $type=english_audio_type($f['tmp_name']);if(!$type)throw new InvalidArgumentException('Berkas bukan rekaman audio MP3, WAV, WebM, OGG, atau M4A yang didukung.');$mime=$type['mime'];
  $name=bin2hex(random_bytes(24)).'.'.$type['extension'];$dir=APPPATH.'storage/english_hack/';if(!is_dir($dir)&&!mkdir($dir,0770,TRUE))throw new RuntimeException('Storage unavailable.');if(!move_uploaded_file($f['tmp_name'],$dir.$name))throw new RuntimeException('Upload unavailable.');return array('file'=>$name,'mime'=>$mime);
 }
 public function submit($kind='',$unit='')
 {
  $task=$this->english_course_content->task($unit,$kind);if(!$task){show_404();return;}$level=$kind==='capstone'?$unit:$this->english_course_content->unit($unit)['level_id'];$this->enabled($level);$error='';$text='';
  if($this->input->method(TRUE)==='POST'){$key=$this->verify('task:'.$kind.':'.$unit);$text=$this->input->post('response_text',FALSE);$audio=NULL;
   try{$audio=$this->upload();$r=$this->course->submit_task($this->user(),$unit,$kind,$text,$audio,$key);if($r['ok']){if(!empty($r['duplicate'])&&$audio)unlink(APPPATH.'storage/english_hack/'.$audio['file']);redirect('belajar/kelas-inggris/karya/'.$r['id'],'location',303);return;}$error=$this->fail($r);
    // Keep media on an uncertain commit; do not delete a potentially committed submission's audio.
    if($audio&&($r['code']!==503||($r['failure']??'')!=='uncertain'))unlink(APPPATH.'storage/english_hack/'.$audio['file']);
   }catch(InvalidArgumentException $e){$error=$e->getMessage();$this->output->set_status_header(400);}
  }
  $this->page('english_course/submit',array('task'=>$task,'kind'=>$kind,'unit_id'=>$unit,'error'=>$error,'text'=>is_string($text)?$text:'','start_token'=>$this->token('task:'.$kind.':'.$unit)));
 }
 private function can_view($submission)
 {
  if(!$submission)return FALSE;if((int)$submission['learner_user_id']===(int)$this->user()||in_array('system_admin',$this->current_user['roles'],TRUE))return TRUE;
  if($this->current_user['account_kind']!=='parent')return FALSE;$this->load->model('Family_practice_model');return (bool)$this->Family_practice_model->child($this->user(),$submission['learner_user_id']);
 }
 public function submission($id='')
 {
  $s=$this->course->submission($this->id($id));if(!$this->can_view($s)){show_404();return;}$this->page('english_course/submission',array('submission'=>$s,'review_mode'=>FALSE,'error'=>''));
 }
 public function family($id='')
 {
  $this->require_login(array('parent'));$this->load->model('Family_practice_model');$child=$this->Family_practice_model->child($this->user(),$this->id($id));if(!$child){show_404();return;}
  $this->page('english_course/history',$this->course->history($child['id'],$this->get_positive_query_int('halaman'))+$this->portfolio_data($child['id'])+array('child'=>$child,'base'=>'keluarga/anak/'.$id.'/kelas-inggris','summary'=>$this->course->summary($child['id'])));
 }
 private function stream($file,$mime)
 {
  if(!is_file($file)){show_404();return;}$size=filesize($file);$start=0;$end=$size-1;$status=200;$range=$this->input->server('HTTP_RANGE');
  if($range){if(!is_string($range)||!preg_match('/^bytes=(\d*)-(\d*)$/D',$range,$m)||($m[1]===''&&$m[2]==='')){header('Content-Range: bytes */'.$size,TRUE,416);exit;}
   if($m[1]===''){$length=(int)$m[2];$start=max(0,$size-$length);}else{$start=(int)$m[1];if($m[2]!=='')$end=min($end,(int)$m[2]);}
   if($start>$end||$start>=$size){header('Content-Range: bytes */'.$size,TRUE,416);exit;}$status=206;
  }
  header('Content-Type: '.$mime);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');header('Accept-Ranges: bytes');http_response_code($status);if($status===206)header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);header('Content-Length: '.($end-$start+1));
  if($this->input->method(TRUE)!=='HEAD'){$fp=fopen($file,'rb');fseek($fp,$start);$left=$end-$start+1;while($left>0&&!feof($fp)){$chunk=fread($fp,min(65536,$left));echo $chunk;$left-=strlen($chunk);}fclose($fp);}exit;
 }
 public function audio($unit=''){$u=$this->english_course_content->unit($unit);if(!$u){show_404();return;}$this->enabled($u['level_id']);$this->stream(APPPATH.'content/english_hack/v1/audio/'.$unit.'.wav','audio/wav');}
 public function submission_audio($id=''){$s=$this->course->submission($this->id($id));if(!$this->can_view($s)||empty($s['audio_file'])){show_404();return;}$this->stream(APPPATH.'storage/english_hack/'.$s['audio_file'],$s['audio_mime']);}
 public function manage()
 {
  $error='';if($this->input->method(TRUE)==='POST'){$r=$this->course->configure($this->user(),$this->input->post('level_id',FALSE),$this->input->post('revision',FALSE),$this->input->post('status',FALSE),$this->input->post('pass_score',FALSE));if($r['ok']){$this->session->set_flashdata('success','Pengaturan English Hack tersimpan.');redirect('kelola/kelas-inggris','location',303);return;}$error=$this->fail($r);}
  $status=$this->get_scalar_query_param('status',16);if(!in_array($status,array('pending','accepted','revise','all'),TRUE))$status='pending';$level=$this->get_scalar_query_param('level',16);if(!$this->english_course_content->level($level))$level='';
  $this->page('admin/english_hack/manage',array('admin_section'=>'english_course','admin_tab'=>$this->get_scalar_query_param('tab',16)==='penilaian'?'penilaian':'pengaturan','settings'=>$this->course->settings(),'levels'=>$this->course->levels(),'queue'=>$this->course->queue($status,$this->get_positive_query_int('halaman'),$level),'status'=>$status,'filter_level'=>$level,'error'=>$error));
 }
 public function review($id='')
 {
  $id=$this->id($id);$s=$this->course->submission($id);if(!$s){show_404();return;}$error='';
  if($this->input->method(TRUE)==='POST'){$r=$this->course->review($this->user(),$id,$this->input->post('revision',FALSE),$this->input->post('scores',FALSE),$this->input->post('feedback',FALSE));if($r['ok']){$this->session->set_flashdata('success','Penilaian dan umpan balik tersimpan.');redirect('kelola/kelas-inggris/tinjau/'.$id,'location',303);return;}$error=$this->fail($r);$s=$this->course->submission($id);}
  $this->page('admin/english_hack/review',array('admin_section'=>'english_course','admin_tab'=>'penilaian','submission'=>$s,'review_mode'=>TRUE,'error'=>$error));
 }
}
