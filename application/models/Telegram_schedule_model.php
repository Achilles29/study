<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Invoked under Notification_model's config-row lock and transaction. */
class Telegram_schedule_model extends CI_Model
{
 public function __construct(){parent::__construct();$this->load->library('telegram_message_policy');}
 private function rows($sql,$args=array()){$q=$this->db->query($sql,$args);if($q===FALSE)throw new RuntimeException('Telegram schedule storage unavailable.');return $q->result_array();}
 public function policies(){return array_column($this->rows('SELECT * FROM st_telegram_message_policies'),NULL,'category');}
 public function pending_counts(){return array_column($this->rows('SELECT category,COUNT(*) total FROM st_telegram_digest_events GROUP BY category'),'total','category');}
 public function enqueue($config,$key,$category,$title,$body,$path)
 {
  $policy=$this->rows('SELECT * FROM st_telegram_message_policies WHERE category=?',array($category))[0]??NULL;
  if(!$policy||$policy['mode']==='realtime'){
   $values=array('judul'=>$title,'isi'=>$body,'jenis'=>$this->telegram_message_policy->categories()[$category]??$category,'jumlah'=>1,'periode'=>$this->telegram_message_policy->local_time(gmdate('Y-m-d H:i:s')),'waktu'=>$this->telegram_message_policy->local_time(gmdate('Y-m-d H:i:s')),'tautan'=>'https://www.projectpintar.com/'.$path);
   $text=$this->telegram_message_policy->render($policy['template']??$this->telegram_message_policy->default_template($category),$values);
   $this->outbox($config,$key,$category,$text);
  }else{
   $this->db->query('INSERT INTO st_telegram_digest_events(event_key,category,title,body,path,config_revision,created_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE id=id',array($key,$category,$title,$body,$path,$config['revision']));
  }
 }
 private function outbox($config,$key,$category,$text)
 {
  foreach($this->telegram_message_policy->chunks($text) as $i=>$part){
   $this->db->query('INSERT INTO st_telegram_outbox(event_key,category,body,config_revision,next_at,created_at) VALUES(?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE id=id',array($key.($i?':p'.$i:''),$category,$part,$config['revision']));
  }
 }
 public function flush($config,$now=NULL)
 {
  $now=$now??time();$until=gmdate('Y-m-d H:i:s',$now);
  // Config changes cancel queued data, preventing a different chat from receiving old events.
  $this->db->where('config_revision !=',(int)$config['revision'])->delete('st_telegram_digest_events');
  foreach($this->policies() as $category=>$p){
   if(!$config['enabled']||empty($config[$category])){
    $this->db->where('category',$category)->delete('st_telegram_digest_events');
    $this->db->where('category',$category)->update('st_telegram_message_policies',array('last_window_at'=>$until,'next_due_at'=>$this->telegram_message_policy->next_due($p,$now)));
    continue;
   }
   if($p['mode']!=='realtime'&&$p['next_due_at']!==NULL&&$p['next_due_at']>$until)continue;
   $values=array('jenis'=>$this->telegram_message_policy->categories()[$category],'periode'=>$this->telegram_message_policy->local_time($p['last_window_at']).' – '.$this->telegram_message_policy->local_time($until),'waktu'=>$this->telegram_message_policy->local_time($until));
   $hasMore=FALSE;
   if($category==='visitors'){
    $n=$this->rows('SELECT COUNT(*) visits,COALESCE(SUM(is_bot),0) bots FROM st_operational_events WHERE created_at>=? AND created_at<?',array($p['last_window_at'],$until))[0];
    if((int)$n['visits']>0||$p['send_empty']){
     $values+=array('judul'=>'Laporan trafik Project Pintar','jumlah'=>$n['visits'],'kunjungan'=>$n['visits'],'manusia'=>(int)$n['visits']-(int)$n['bots'],'bot'=>$n['bots'],'tautan'=>'https://www.projectpintar.com/kelola/trafik','isi'=>'Situs diakses '.$n['visits'].' kali, termasuk '.$n['bots'].' akses bot / alat. Ini jumlah akses, bukan jumlah orang unik.');
     $this->outbox($config,'report:visitors:'.str_replace(array(' ',':'),array('T',''),$p['last_window_at']).':'.str_replace(array(' ',':'),array('T',''),$until),'visitors',$this->telegram_message_policy->render($p['template'],$values));
    }
   }else{
    $events=$this->rows('SELECT * FROM st_telegram_digest_events WHERE category=? AND config_revision=? ORDER BY id LIMIT 200',array($category,$config['revision']));
    if($events){
     if($p['mode']==='realtime'){
      foreach($events as $event)$this->enqueue($config,$event['event_key'],$category,$event['title'],$event['body'],$event['path']);
     }else{
      $lines=array();foreach($events as $i=>$event)$lines[]=($i+1).'. '.$this->telegram_message_policy->local_time($event['created_at'])."\n".$event['title']."\n".$event['body']."\nhttps://www.projectpintar.com/".$event['path'];
      $values+=array('judul'=>'Ringkasan '.$this->telegram_message_policy->categories()[$category].' · '.count($events).' kejadian','isi'=>implode("\n\n",$lines),'jumlah'=>count($events),'tautan'=>'https://www.projectpintar.com/kelola');
      $key='digest:'.$category.':'.$events[0]['id'].':'.$events[count($events)-1]['id'];
      $this->outbox($config,$key,$category,$this->telegram_message_policy->render($p['template'],$values));
     }
     $this->db->where_in('id',array_column($events,'id'))->delete('st_telegram_digest_events');
     $hasMore=(bool)$this->rows('SELECT id FROM st_telegram_digest_events WHERE category=? LIMIT 1',array($category));
    }
   }
   if(!$hasMore)$this->db->where('category',$category)->update('st_telegram_message_policies',array('last_window_at'=>$until,'next_due_at'=>$this->telegram_message_policy->next_due($p,$now)));
  }
 }
}
