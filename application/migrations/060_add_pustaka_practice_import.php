<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_pustaka_practice_import extends CI_Migration
{
 public function up()
 {
  if (!$this->db->field_exists('draw_count','st_exam_templates') && !$this->db->query('ALTER TABLE st_exam_templates ADD draw_count INT UNSIGNED NOT NULL DEFAULT 0')) throw new RuntimeException('Exam question draw count unavailable.');
  foreach (array(
   "CREATE TABLE IF NOT EXISTS st_pustaka_import_sessions(source_session_id INT NOT NULL PRIMARY KEY,exam_id BIGINT UNSIGNED NULL UNIQUE,source_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,source_json LONGTEXT NOT NULL,issue TEXT NULL,imported_at DATETIME NOT NULL,INDEX(exam_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
   "CREATE TABLE IF NOT EXISTS st_pustaka_import_questions(source_question_id INT NOT NULL PRIMARY KEY,bank_question_id BIGINT UNSIGNED NULL,source_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,source_json LONGTEXT NOT NULL,issue TEXT NULL,imported_at DATETIME NOT NULL,INDEX(bank_question_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
   "CREATE TABLE IF NOT EXISTS st_pustaka_import_links(source_session_id INT NOT NULL,source_question_id INT NOT NULL,sort_order INT NOT NULL,score_override DECIMAL(5,2) NULL,bank_question_id BIGINT UNSIGNED NULL,issue TEXT NULL,PRIMARY KEY(source_session_id,source_question_id),INDEX(bank_question_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  ) as $sql) if (!$this->db->query($sql)) throw new RuntimeException('Pustaka practice import archive unavailable.');
 }
 public function down(){throw new RuntimeException('Preserve imported Pustaka practice data.');}
}
