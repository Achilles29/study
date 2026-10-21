<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_reviews extends CI_Migration
{
 public function up(){if(!$this->db->query("CREATE TABLE IF NOT EXISTS st_reviews(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NULL UNIQUE,demo_key VARCHAR(40) NULL UNIQUE,is_demo TINYINT UNSIGNED NOT NULL DEFAULT 0,display_name VARCHAR(80) NOT NULL,rating TINYINT UNSIGNED NOT NULL,body TEXT NOT NULL,status ENUM('pending','published','hidden') NOT NULL DEFAULT 'pending',revision INT UNSIGNED NOT NULL DEFAULT 1,moderation_reason VARCHAR(300) NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,INDEX(status,is_demo,id),CONSTRAINT fk_review_user FOREIGN KEY(user_id) REFERENCES st_users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")){throw new RuntimeException('Review schema unavailable.');}}
 public function down(){throw new RuntimeException('Preserve genuine review history.');}
}
