<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Update_traffic_notice extends CI_Controller
{
 public function index()
 {
  if(PHP_SAPI!=='cli'||getenv('STUDY_TRAFFIC_NOTICE')!=='072'){show_404();exit(1);}
  $this->load->database();if(!in_array($this->db->database,array('db_study','study_qa_monitor_sso039'),TRUE)){exit(2);}
  $this->load->model(array('Settings_model','Account_model'));$s=$this->Settings_model->get_section('privasi');$v=$s['values'];
  $old='Jenis halaman disimpan, bukan query URL, isi pesan/soal atau data formulir.';
  $new='Jenis dan alamat halaman konten yang diizinkan disimpan tanpa query, token, isi pesan/soal atau data formulir. Perujuk dicatat sebagai kategori asal, domain situs luar (tanpa path/query), atau alamat halaman internal yang diizinkan. Perujuk dapat tidak dikirim browser dan bukan bukti kunjungan organik. Tamu dikelompokkan per IP, tanpa dikaitkan ke akun sebelum login.';
  if(strpos($v['policy_text'],$new)!==FALSE){echo "Traffic notice already current.\n";return;}
  if(substr_count($v['policy_text'],$old)!==1){fwrite(STDERR,"Notice differs; preserved for review.\n");exit(3);}
  $v['policy_text']=str_replace($old,$new,$v['policy_text']);$v['policy_version']='2026-10-20-v3';
  $u=$this->Account_model->find_active_user(1);$r=$this->Settings_model->save_section('privasi',$v,$s['revision'],1,$u['auth_stamp']??'');
  echo json_encode(array('ok'=>$r['ok'],'version'=>$r['ok']?$v['policy_version']:NULL)).PHP_EOL;if(!$r['ok']){exit(1);}
 }
}
