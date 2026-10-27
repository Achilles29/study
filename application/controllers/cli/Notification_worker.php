<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Notification_worker extends CI_Controller
{
 public function index()
 {
  if(PHP_SAPI!=='cli'){show_404();exit(1);}
  $this->load->database();$this->db->db_debug=FALSE;
  if(!in_array($this->db->database,array('db_study','study_qa_monitor_sso039'),TRUE)){exit(2);}
  $this->load->model(array('Notification_model','Telegram_bot_model'));
  try{$collect=$this->Notification_model->collect();if(!$collect['ok']){throw new RuntimeException('Collection unavailable.');}$send=$this->Notification_model->deliver();$poll=$this->Telegram_bot_model->poll();$reply=$this->Telegram_bot_model->respond();if(!empty($reply['processed'])){$this->Notification_model->collect();}$replies=$this->Notification_model->deliver();echo json_encode(array('collection'=>$collect,'delivery'=>$send,'bot_poll'=>$poll,'bot_reply'=>$reply,'reply_delivery'=>$replies)).PHP_EOL;}
  catch(Throwable $e){fwrite(STDERR,"Notification worker unavailable; no secrets logged.\n");exit(1);}
 }
}
