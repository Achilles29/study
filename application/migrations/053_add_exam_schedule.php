<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_exam_schedule extends CI_Migration
{
 public function up()
 {
  foreach(array('available_from','available_until') as $field){if(!$this->db->field_exists($field,'st_exam_templates')&&!$this->db->query("ALTER TABLE st_exam_templates ADD $field DATETIME NULL"))throw new RuntimeException('Exam schedule schema unavailable.');}
  // Widen storage only: no row/ID reset and no 50-question exam business cap.
  if(!$this->db->query('ALTER TABLE st_practice_attempts MODIFY question_count INT UNSIGNED NOT NULL DEFAULT 0, MODIFY correct_count INT UNSIGNED NOT NULL DEFAULT 0'))throw new RuntimeException('Exam count storage unavailable.');
 }
 public function down(){throw new RuntimeException('Preserve schedules and historical counts.');}
}
