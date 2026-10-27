<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_question_difficulty extends CI_Migration
{
 public function up()
 {
  if (!$this->db->field_exists('question_difficulty', 'st_bank_questions') && !$this->db->query("ALTER TABLE st_bank_questions ADD question_difficulty ENUM('easy','medium','hard') NULL DEFAULT NULL")) throw new RuntimeException('Question difficulty unavailable.');
  if (!$this->db->field_exists('question_difficulty', 'st_exam_templates') && !$this->db->query("ALTER TABLE st_exam_templates ADD question_difficulty ENUM('easy','medium','hard') NULL DEFAULT NULL")) throw new RuntimeException('Exam difficulty unavailable.');
 }
 public function down(){throw new RuntimeException('Preserve question difficulty tags.');}
}
