<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_traffic_navigation extends CI_Migration
{
 public function up()
 {
  foreach(array('destination_path'=>'VARCHAR(255) NULL','referrer_kind'=>'VARCHAR(24) NULL','referrer_host'=>'VARCHAR(253) NULL','referrer_path'=>'VARCHAR(255) NULL') as $field=>$type){
   if(!$this->db->field_exists($field,'st_operational_events')&&!$this->db->query("ALTER TABLE st_operational_events ADD $field $type")){throw new RuntimeException('Traffic navigation schema unavailable.');}
  }
 }
 public function down(){throw new RuntimeException('Preserve traffic history until normal retention.');}
}
