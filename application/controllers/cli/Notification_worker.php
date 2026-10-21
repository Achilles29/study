<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Notification_worker extends CI_Controller
{
 public function index()
 {
  if(PHP_SAPI!=='cli'){show_404();exit(1);}
  $this->load->database();$this->db->db_debug=FALSE;
  if(!in_array($this->db->database,array('db_study','study_qa_monitor_sso039'),TRUE)){exit(2);}
  $this->load->model('Notification_model');
  try{$collect=$this->Notification_model->collect();if(!$collect['ok']){throw new RuntimeException('Collection unavailable.');}$send=$this->Notification_model->deliver();echo json_encode(array('collection'=>$collect,'delivery'=>$send)).PHP_EOL;}
  catch(Throwable $e){fwrite(STDERR,"Notification worker unavailable; no secrets logged.\n");exit(1);}
 }
}
