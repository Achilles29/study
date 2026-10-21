<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_drill_settings extends CI_Migration
{
 public function up()
 {
  if(!$this->db->field_exists('max_daily','st_exam_templates')&&!$this->db->query('ALTER TABLE st_exam_templates ADD max_daily INT UNSIGNED NOT NULL DEFAULT 3')){throw new RuntimeException('Exam daily limit unavailable.');}
  $this->load->library('site_settings');if(!$this->db->where('section','latihan_mandiri')->get('st_site_settings')->num_rows()){$id=(int)$this->db->query('SELECT COALESCE(MAX(id),0)+1 id FROM st_site_settings')->row_array()['id'];if(!$this->db->insert('st_site_settings',array('id'=>$id,'section'=>'latihan_mandiri','values_json'=>json_encode($this->site_settings->defaults('latihan_mandiri')),'revision'=>1,'updated_at'=>gmdate('Y-m-d H:i:s')))){throw new RuntimeException('Drill settings unavailable.');}}
 }
 public function down(){throw new RuntimeException('Preserve learning settings.');}
}
