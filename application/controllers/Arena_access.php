<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Arena_access extends MY_Controller
{
 public function index()
 {
  $this->require_role(array('system_admin'));$this->require_http_methods(array('GET','HEAD','POST'));$this->load->model('Arena_credit_model');$this->load->helper('study_arena');$this->output->set_header('Cache-Control: private, no-store');$type=$this->get_scalar_query_param('jenis',32);if($type!==''&&!isset(study_arena_games()[$type])){show_error('Jenis permainan tidak valid.',400);return;}$error='';
  if($this->input->method(TRUE)==='POST'){$id=$this->input->post('group_id',FALSE);if(!is_string($id)||!ctype_digit($id)){show_error('ID akses tidak valid.',400);return;}$r=$this->Arena_credit_model->save($this->current_user,(int)$id,$this->input->post('price',FALSE),$this->input->post('revision',FALSE));if($r['ok']){$this->session->set_flashdata('success','Harga level tersimpan. Hak yang sudah dibeli dan progres tidak berubah.');redirect('kelola/akses-arena?jenis='.$type,'location',303);return;}$this->output->set_status_header($r['code']??503);$error='Belum tersimpan. Level 1–2 wajib gratis; lainnya 10–50 kredit. Muat ulang bila revisi berubah.';}
  $groups=$type!==''?$this->Arena_credit_model->groups($type):array();$settings=$this->Arena_credit_model->settings();$this->render_page('admin/arena_access',compact('type','groups','settings','error')+array('page_title'=>'Akses kredit Arena','admin_section'=>'arena_access'));
 }
}
