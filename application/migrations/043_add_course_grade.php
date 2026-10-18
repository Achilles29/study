<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_course_grade extends CI_Migration
{
 public function up()
 {
  if (!$this->db->field_exists('grade_id','st_courses')) {
   if(!$this->db->query('ALTER TABLE st_courses ADD grade_id BIGINT UNSIGNED NULL, ADD INDEX idx_course_grade_status(grade_id,status), ADD CONSTRAINT fk_course_grade FOREIGN KEY (grade_id) REFERENCES st_learning_grades(id)')) { throw new RuntimeException('Course grade schema unavailable.'); }
  }
  $this->db->trans_begin();
  $row=$this->db->query("SELECT c.id FROM st_courses c WHERE c.slug='matematika-kelas-1-mengenal-bilangan-1-20' AND c.target_group='primary' AND c.grade_id IS NULL FOR UPDATE")->row_array();
  $grade=$this->db->where('code','sd_1')->get('st_learning_grades')->row_array();
  if($row && $grade){
   $this->db->where('id',$row['id'])->update('st_courses',array('grade_id'=>$grade['id']));
   $this->db->insert('st_audit_events',array('actor_user_id'=>NULL,'event_type'=>'content.course_grade_mapped','entity_type'=>'course','entity_id'=>$row['id'],'details_json'=>json_encode(array('source'=>'migration043','grade_code'=>'sd_1','status_unchanged'=>TRUE)),'created_at'=>gmdate('Y-m-d H:i:s')));
  }
  if(!$this->db->trans_status()){ $this->db->trans_rollback(); throw new RuntimeException('Sample grade mapping failed.'); } $this->db->trans_commit();
 }
 public function down(){throw new RuntimeException('Preserve course classifications and history.');}
}
