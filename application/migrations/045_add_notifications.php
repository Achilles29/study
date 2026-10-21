<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_notifications extends CI_Migration
{
 public function up()
 {
  foreach(array(
   "CREATE TABLE IF NOT EXISTS st_notification_config(id TINYINT UNSIGNED PRIMARY KEY,enabled TINYINT UNSIGNED NOT NULL DEFAULT 0,token_cipher TEXT NULL,chat_id VARCHAR(32) NOT NULL DEFAULT '',payments TINYINT UNSIGNED NOT NULL DEFAULT 1,donations TINYINT UNSIGNED NOT NULL DEFAULT 1,help TINYINT UNSIGNED NOT NULL DEFAULT 1,visitors TINYINT UNSIGNED NOT NULL DEFAULT 0,revision INT UNSIGNED NOT NULL DEFAULT 1,event_floor BIGINT UNSIGNED NOT NULL DEFAULT 0,traffic_hour DATETIME NOT NULL,worker_at DATETIME NULL,updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
   "INSERT IGNORE INTO st_notification_config(id,event_floor,traffic_hour,updated_at) SELECT 1,COALESCE(MAX(id),0),DATE_FORMAT(UTC_TIMESTAMP(),'%Y-%m-%d %H:00:00'),UTC_TIMESTAMP() FROM st_audit_events",
   "CREATE TABLE IF NOT EXISTS st_notification_events(event_id BIGINT UNSIGNED PRIMARY KEY,processed_at DATETIME NOT NULL,INDEX(processed_at)) ENGINE=InnoDB",
   "CREATE TABLE IF NOT EXISTS st_notifications(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,event_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,category VARCHAR(20) NOT NULL,title VARCHAR(160) NOT NULL,body VARCHAR(1000) NOT NULL,path VARCHAR(180) NOT NULL,read_at DATETIME NULL,created_at DATETIME NOT NULL,UNIQUE KEY uq_notification_event(user_id,event_key),INDEX idx_notification_inbox(user_id,read_at,id),CONSTRAINT fk_notification_user FOREIGN KEY(user_id) REFERENCES st_users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
   "CREATE TABLE IF NOT EXISTS st_telegram_outbox(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,event_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,category VARCHAR(20) NOT NULL,body VARCHAR(1500) NOT NULL,config_revision INT UNSIGNED NOT NULL,status ENUM('pending','sent','failed','cancelled','uncertain') NOT NULL DEFAULT 'pending',attempts INT UNSIGNED NOT NULL DEFAULT 0,next_at DATETIME NOT NULL,sent_at DATETIME NULL,last_error VARCHAR(80) NULL,created_at DATETIME NOT NULL,INDEX(status,next_at,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  ) as $sql) { if(!$this->db->query($sql)) { throw new RuntimeException('Notification schema unavailable.'); } }
 }
 public function down(){throw new RuntimeException('Preserve notification history.');}
}
