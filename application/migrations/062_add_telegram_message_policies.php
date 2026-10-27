<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_telegram_message_policies extends CI_Migration
{
 public function up()
 {
  foreach(array(
   "CREATE TABLE IF NOT EXISTS st_telegram_message_policies(category VARCHAR(20) PRIMARY KEY,mode VARCHAR(12) NOT NULL DEFAULT 'realtime',interval_minutes INT UNSIGNED NOT NULL DEFAULT 60,daily_time CHAR(5) NOT NULL DEFAULT '08:00',template TEXT NOT NULL,send_empty TINYINT UNSIGNED NOT NULL DEFAULT 0,revision INT UNSIGNED NOT NULL DEFAULT 1,last_window_at DATETIME NOT NULL,next_due_at DATETIME NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
   "CREATE TABLE IF NOT EXISTS st_telegram_digest_events(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,category VARCHAR(20) NOT NULL,title VARCHAR(160) NOT NULL,body VARCHAR(1000) NOT NULL,path VARCHAR(180) NOT NULL,config_revision INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,KEY digest_category(category,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  ) as $sql)if(!$this->db->query($sql))throw new RuntimeException('Telegram scheduling schema unavailable.');
  $this->load->library('telegram_message_policy');
  foreach($this->telegram_message_policy->categories() as $category=>$label){
   $traffic=$category==='visitors';
   if(!$this->db->query('INSERT IGNORE INTO st_telegram_message_policies(category,mode,interval_minutes,template,last_window_at,next_due_at,updated_at) SELECT ?,?,?,?,traffic_hour,?,UTC_TIMESTAMP() FROM st_notification_config WHERE id=1',array($category,$traffic?'interval':'realtime',$traffic?360:60,$this->telegram_message_policy->default_template($category),$traffic?gmdate('Y-m-d H:i:s',time()+21600):NULL)))throw new RuntimeException('Telegram schedules unavailable.');
  }
 }
 public function down(){throw new RuntimeException('Preserve Telegram schedules and pending events.');}
}
