<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_lifetime_access extends CI_Migration
{
 public function up()
 {
  foreach(array('st_commerce_offers','st_commerce_grants') as $table){
   if(!$this->db->field_exists('is_lifetime',$table)&&!$this->db->query("ALTER TABLE $table ADD is_lifetime TINYINT UNSIGNED NOT NULL DEFAULT 0")){throw new RuntimeException('Lifetime schema unavailable.');}
  }
  if(!$this->db->query('ALTER TABLE st_commerce_grants MODIFY ends_at DATETIME NULL')){throw new RuntimeException('Lifetime expiry schema unavailable.');}
 }
 public function down(){throw new RuntimeException('Preserve historical lifetime purchases.');}
}
