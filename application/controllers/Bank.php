<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Bank extends MY_Controller
{
 public function __construct(){parent::__construct();$this->require_role(array('system_admin'));$this->load->model('Bank_model');$this->load->library(array('question_import','lesson_image_store','question_image_upload'));$this->output->set_header('Cache-Control: private, no-store')->set_header('X-Robots-Tag: noindex, nofollow');}
 public function index()
 {
  $this->require_http_methods(array('GET','HEAD'));$grade=study_grade_filter();$subject=$this->get_scalar_query_param('mapel',10);$status=$this->input->get('status',FALSE)??'all';if(!in_array($status,array('all','draft','published','archived'),TRUE)||($subject!==''&&!ctype_digit($subject))){show_error('Filter bank soal tidak valid.',400);return;}
  $report=$this->Bank_model->listing($grade,(int)$subject,$status,$this->get_positive_query_int('halaman'));$subjects=$this->Bank_model->subjects();$this->render_page('admin/bank/index',compact('report','grade','subject','status','subjects')+array('page_title'=>'Bank soal mandiri','admin_section'=>'bank'));
 }
 public function edit($id='0')
 {
  $this->require_http_methods(array('GET','HEAD','POST'));$record=$id!=='0'?$this->Bank_model->get($id):NULL;if($id!=='0'&&!$record){show_404();return;}$subjects=$this->Bank_model->subjects();$error='';
  if($this->input->method(TRUE)==='POST'&&$this->input->post('action')==='remove_scope'){
   if(!$record||$this->input->post('confirm')!=='1'){show_error('Konfirmasi pelepasan klasifikasi diperlukan.',400);return;}$r=$this->Bank_model->remove_scope($this->current_user,(int)$id,$this->input->post('revision',FALSE),$this->input->post('subject_id',FALSE),$this->input->post('grade_id',FALSE),$this->input->post('difficulty',FALSE));
   if(!$r['ok']){show_error('Klasifikasi belum dilepas. Sisakan minimal satu klasifikasi; kelas yang masih dirujuk simulasi tidak boleh dilepas. Muat ulang jika revisi berubah.', $r['code']??503);return;}$this->session->set_flashdata('success','Klasifikasi dilepas untuk pemilihan sesi baru. Soal dan hasil lama tetap tersimpan.');redirect('kelola/bank-soal/'.$id,'location',303);return;
  }
  $values=array_fill_keys(Question_import::HEADERS,'');$values+=array('rights_status'=>'owned','source_note'=>'','status'=>'draft','revision'=>'0');$values['tingkat']='basic';$values['kunci']='A';
  if($record){$scope=$record['scopes'][0];$values=array_replace($values,array('soal'=>$record['prompt'],'bahasan'=>$record['explanation'],'rights_status'=>$record['rights_status'],'source_note'=>$record['source_note'],'status'=>$record['status'],'revision'=>(string)$record['content_version'],'mapel'=>array_column($subjects,'slug','id')[$scope['subject_id']]??'','kelas'=>study_grade_code($scope['grade_id']),'tingkat'=>$scope['difficulty']));foreach($record['options'] as $i=>$o){$values[strtolower(chr(65+$i))]=$o['option_text'];if($o['is_correct']){$values['kunci']=chr(65+$i);}}}
  $existing_images=array();
  if(!empty($record['image'])){
   foreach(array_merge(array($record['image']),$record['image']['additional_images']??array()) as $i=>$im){
    unset($im['additional_images'],$im['text_markers_version']);
    $name=$im['original_name']??('gambar-'.($i+1).'.png');$existing_images[$name]=$im;
   }
   $values['gambar']=implode(' | ',array_keys($existing_images));
   if(($record['image']['text_markers_version']??NULL)===1){
    $names=array_keys($existing_images);
    foreach(array('soal','a','b','c','d') as $field){$values[$field]=preg_replace_callback(Question_media::CANONICAL,static function($m)use($names){return '[GAMBAR: '.($names[(int)$m[1]-1]??$m[1]).']';},$values[$field]);}
   }
  }
  if($this->input->method(TRUE)==='POST'){
   $uploaded=array();$keep_images=FALSE;
   try{foreach(array_keys($values) as $k){$v=$this->input->post($k,FALSE);if(!is_string($v??'')){throw new RuntimeException('Isian harus berupa teks.');}if($k==='gambar'&&$v===NULL){continue;}$values[$k]=$v??'';}if($this->input->post('confirm')!=='1'){throw new RuntimeException('Konfirmasi hak penggunaan dan pemeriksaan soal.');}
    $images=$existing_images;
    if($this->input->post('remove_image')==='1'){$images=array();$values['gambar']='';}
    $uploaded=$this->images();
    foreach($uploaded as $name=>$im){
     $images[$name]=$im;
     if(!in_array($name,array_map('trim',explode('|',$values['gambar'])),TRUE)){$values['gambar'].=($values['gambar']!==''?' | ':'').$name;}
    }
    // Older single-image clients remain compatible.
    if(isset($_FILES['image']) && ($_FILES['image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$upload=$this->lesson_image_store->from_upload($_FILES['image']);if($upload['status']!=='ok'){throw new RuntimeException($upload['message']);}$uploaded['question-image']=$upload['image'];$images=array('question-image'=>$upload['image']);$values['gambar']='question-image';}
    $questions=$this->Bank_model->normalize_rows(array($values),$values['rights_status'],$values['source_note'],$images);
    $keep_images=TRUE;$r=$this->Bank_model->save($this->current_user,(int)$id,$values['revision'],$questions[0],$values['status']);
    if(!$r['ok']){$this->output->set_status_header($r['code']??503);throw new RuntimeException(isset($r['existing_id'])?'Isi sama dengan soal #'.$r['existing_id'].'. Gunakan ID itu; penggabungan riwayat tidak dilakukan otomatis.':'Soal berubah atau penyimpanan belum berhasil. Muat ulang sebelum mencoba lagi.');}
    $this->session->set_flashdata('success',!empty($r['reused'])?'Soal identik sudah ada; ID tersebut dipakai dan kelas/mapel ditambahkan. Isi/status lama tidak ditimpa.':'Soal tersimpan. Percobaan yang sudah dimulai tetap memakai salinan historis.');redirect('kelola/bank-soal/'.$r['id'],'location',303);return;
   }catch(RuntimeException $e){$error=$e->getMessage();if(http_response_code()<400){$this->output->set_status_header(400);}}
   finally{if(!$keep_images){$this->question_image_upload->discard($uploaded);}}
  }
  $this->render_page('admin/bank/edit',compact('record','values','subjects','error')+array('page_title'=>$record?'Edit soal #'.$id:'Tambah soal mandiri','admin_section'=>'bank'));
 }
 private function images()
 {
  $expected=$this->input->post('images_expected',FALSE);
  if($expected!==NULL&&$expected!==''){
   if(!is_string($expected)||!preg_match('/\A(?:0|[1-9][0-9]{0,2})\z/',$expected)||(int)$expected>Question_image_upload::MAX_FILES){throw new RuntimeException('Pilih maksimal 500 lampiran per impor.');}
   $errors=$_FILES['images']['error']??array();if(!is_array($errors)){throw new RuntimeException('Pilih ulang lampiran melalui kolom gambar.');}
   $received=0;foreach($errors as $error){if($error!==UPLOAD_ERR_NO_FILE){$received++;}}
   if((int)$expected!==$received){throw new RuntimeException('Jumlah gambar yang diterima server berbeda dari pilihan Anda. Unggah ulang; tidak ada soal yang disimpan.');}
  }
  return $this->question_image_upload->read($_FILES['images']??NULL);
 }
 public function upload()
 {
  $this->require_http_methods(array('GET','HEAD','POST'));$error='';$analysis=NULL;$values=array('mapel'=>'','kelas'=>'','tingkat'=>'basic','rights_status'=>'owned','source_note'=>'');$exam=$this->get_scalar_query_param('ujian',18);if($exam!==''&&!ctype_digit($exam)){show_error('ID ujian tidak valid.',400);return;}
  $exam_context=NULL;if($exam!==''){$this->load->model('Exam_model');$exam_context=$this->Exam_model->get($exam);if(!$exam_context){show_404();return;}if($exam_context['status']!=='draft'){show_error('Buka draft melalui langkah Pilih soal sebelum mengimpor.',409);return;}$values['kelas']=study_grade_code($exam_context['grade_id']);}
  if($this->input->method(TRUE)==='POST'){
   $images=array();$keep_images=FALSE;
   try{
    foreach($values as $name=>$default){$value=$this->input->post($name,FALSE);if($value!==NULL&&!is_string($value)){throw new RuntimeException('Isian '.$name.' harus berupa teks.');}$values[$name]=$value??$default;}
    $f=$_FILES['document']??NULL;if(!is_array($f)||!is_string($f['tmp_name']??NULL)||!is_string($f['name']??NULL)||($f['error']??NULL)!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name'])){throw new RuntimeException('Pilih berkas soal yang valid; periksa batas unggahan server.');}
    $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
    $analysis=$this->question_import->analyze($f['tmp_name'],$ext,$values);
    $images=$this->images();
    $analysis=$this->Bank_model->inspect_import($analysis,$values['rights_status'],$values['source_note'],$images);
    if(!$analysis['ready']){$this->output->set_status_header(422);}
    else{
     $questions=$analysis['questions'];
     if($exam_context){foreach($questions as $question){if((int)$question['grade_id']!==(int)$exam_context['grade_id'])throw new RuntimeException('Kelas soal harus sesuai kelas simulasi. Periksa kelas pada berkas dan pengaturan.');}}
     if($analysis['headings']){$questions[0]['import_headings']=$analysis['headings'];}
     // Retain media once a database write is attempted, even if acknowledgement
     // is uncertain. Never remove an image a committed preview might reference.
     $keep_images=TRUE;
     $r=$this->Bank_model->preview($this->current_user,$questions,$exam!==''?(int)$exam:NULL);if(!$r['ok']){throw new RuntimeException('Pratinjau belum dapat disimpan atau ujian bukan draft.');}redirect('kelola/bank-soal/impor/'.$r['token'],'location',303);return;
    }
   }
   catch(RuntimeException $e){$error=$e->getMessage();$this->output->set_status_header(400);}
   finally{if(!$keep_images){$this->question_image_upload->discard($images);}}
  }
  $subjects=$this->Bank_model->subjects();$this->render_page('admin/bank/import',compact('error','subjects','exam','exam_context','analysis','values')+array('page_title'=>'Impor bank soal','admin_section'=>'bank'));
 }
 public function preview($token='')
 {
  $this->require_http_methods(array('GET','HEAD','POST'));$record=$this->Bank_model->import_record($this->current_user['id'],$token);if(!$record){show_404();return;}$error='';
  if($this->input->method(TRUE)==='POST'){
   if($this->input->post('confirm')!=='1'){show_error('Konfirmasi pemeriksaan seluruh soal terlebih dahulu.',400);return;}$r=$this->Bank_model->commit($this->current_user,$token,$this->input->post('publish')==='1');if($r['ok']){$this->session->set_flashdata('success','Impor selesai: '.$r['result']['new'].' soal baru, '.$r['result']['reused'].' baris memakai ID yang sudah ada.');redirect($record['exam_id']?'kelola/simulasi-ujian/'.$record['exam_id'].'?langkah=soal':'kelola/bank-soal/impor/'.$token,'location',303);return;}$this->output->set_status_header($r['code']??503);$error='Impor belum disimpan: pratinjau kedaluwarsa, ujian berubah/berbeda kelas, atau layanan belum tersedia. Tidak ada sebagian soal yang disimpan.';
  }
  $questions=json_decode($record['payload_json'],TRUE);$known=array_column($this->Bank_model->rows('SELECT question_id,fingerprint FROM st_bank_questions WHERE fingerprint IN ('.implode(',',array_fill(0,count($questions),'?')).')',array_column($questions,'fingerprint')),'question_id','fingerprint');$page=min(max(1,$this->get_positive_query_int('halaman')),max(1,(int)ceil(count($questions)/20)));
  $this->render_page('admin/bank/preview',compact('record','questions','known','page','error')+array('page_title'=>'Periksa impor soal','admin_section'=>'bank'));
 }
 public function template($ext='txt')
 {
  $this->require_http_methods(array('GET','HEAD'));if(!in_array($ext,array('naskah','naskah-gambar','txt','csv','docx','xlsx'),TRUE)){show_404();return;}$bytes=$this->question_import->template($ext);$this->output->set_header('X-Content-Type-Options: nosniff')->set_header('Content-Disposition: attachment; filename="template-bank-soal'.(strpos($ext,'naskah')===0?'-'.$ext.'.txt':'.'.$ext).'"')->set_content_type(array('naskah'=>'text/plain','naskah-gambar'=>'text/plain','txt'=>'text/plain','csv'=>'text/csv','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')[$ext])->set_output($bytes);
 }
 public function image($id='0',$image_index='0')
 {
  $this->require_http_methods(array('GET','HEAD'));$q=$this->Bank_model->get($id);$image=$this->Bank_model->image_at($q['image']??NULL,$image_index);if(!$image){show_404();return;}$this->image_output($image);
 }
 public function preview_image($token='',$index='0',$image_index='0')
 {
  $this->require_http_methods(array('GET','HEAD'));$r=$this->Bank_model->import_record($this->current_user['id'],$token);$qs=$r?json_decode($r['payload_json'],TRUE):array();$image=$this->Bank_model->image_at($qs[(int)$index]['image']??NULL,$image_index);if(!$image){show_404();return;}$this->image_output($image);
 }
 private function image_output($image){$bytes=$this->lesson_image_store->read($image);if($bytes===FALSE){show_404();return;}$this->output->set_header('X-Content-Type-Options: nosniff')->set_content_type($image['mime_type'])->set_output($bytes);}
}
