<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_child_grade extends CI_Migration
{
 public function up()
 {
  if (!$this->db->field_exists('grade_id', 'st_users') && !$this->db->query('ALTER TABLE st_users ADD grade_id BIGINT UNSIGNED NULL, ADD INDEX idx_user_grade(grade_id), ADD CONSTRAINT fk_user_grade FOREIGN KEY (grade_id) REFERENCES st_learning_grades(id)')) {
   throw new RuntimeException('Child grade schema unavailable.');
  }
 }
 public function down() { throw new RuntimeException('Preserve child classifications.'); }
}
