<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_english_hack_course extends CI_Migration
{
 public function up()
 {
  $tables = array(
   "CREATE TABLE IF NOT EXISTS st_english_settings (id TINYINT UNSIGNED PRIMARY KEY, enabled TINYINT UNSIGNED NOT NULL DEFAULT 0, revision INT UNSIGNED NOT NULL DEFAULT 1, updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
   "CREATE TABLE IF NOT EXISTS st_english_levels (level_id VARCHAR(16) PRIMARY KEY, status VARCHAR(16) NOT NULL DEFAULT 'draft', pass_score TINYINT UNSIGNED NOT NULL DEFAULT 80, revision INT UNSIGNED NOT NULL DEFAULT 1, updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
   "CREATE TABLE IF NOT EXISTS st_english_progress (learner_user_id BIGINT UNSIGNED NOT NULL, unit_id VARCHAR(24) NOT NULL, content_version VARCHAR(16) NOT NULL, studied_at DATETIME NULL, best_score DECIMAL(5,2) NULL, attempt_count INT UNSIGNED NOT NULL DEFAULT 0, review_stage TINYINT UNSIGNED NOT NULL DEFAULT 0, review_due DATETIME NULL, last_review_at DATETIME NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(learner_user_id,unit_id,content_version), KEY english_due(learner_user_id,review_due), CONSTRAINT fk_english_progress_user FOREIGN KEY(learner_user_id) REFERENCES st_users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
   "CREATE TABLE IF NOT EXISTS st_english_attempts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, learner_user_id BIGINT UNSIGNED NOT NULL, level_id VARCHAR(16) NOT NULL, unit_id VARCHAR(24) NOT NULL, content_version VARCHAR(16) NOT NULL, kind VARCHAR(16) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'running', snapshot_json LONGTEXT NOT NULL, answers_json LONGTEXT NULL, score DECIMAL(5,2) NULL, start_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, revision INT UNSIGNED NOT NULL DEFAULT 1, started_at DATETIME NOT NULL, finished_at DATETIME NULL, UNIQUE KEY english_start(learner_user_id,start_key), KEY english_runs(learner_user_id,status,id), CONSTRAINT fk_english_attempt_user FOREIGN KEY(learner_user_id) REFERENCES st_users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
   "CREATE TABLE IF NOT EXISTS st_english_submissions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, learner_user_id BIGINT UNSIGNED NOT NULL, level_id VARCHAR(16) NOT NULL, unit_id VARCHAR(24) NOT NULL, content_version VARCHAR(16) NOT NULL, task_kind VARCHAR(16) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'pending', response_text MEDIUMTEXT NOT NULL, audio_file VARCHAR(80) NULL, audio_mime VARCHAR(40) NULL, task_json LONGTEXT NOT NULL, scores_json TEXT NULL, feedback TEXT NULL, reviewer_user_id BIGINT UNSIGNED NULL, revision INT UNSIGNED NOT NULL DEFAULT 1, start_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, created_at DATETIME NOT NULL, reviewed_at DATETIME NULL, UNIQUE KEY english_submission_key(learner_user_id,start_key), KEY english_review(status,id), KEY english_portfolio(learner_user_id,unit_id,content_version,id), CONSTRAINT fk_english_submission_user FOREIGN KEY(learner_user_id) REFERENCES st_users(id) ON DELETE RESTRICT, CONSTRAINT fk_english_reviewer FOREIGN KEY(reviewer_user_id) REFERENCES st_users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );
  foreach($tables as $sql) if(!$this->db->query($sql)) throw new RuntimeException('English course schema unavailable.');
  $now=gmdate('Y-m-d H:i:s');
  if(!$this->db->query('INSERT IGNORE INTO st_english_settings(id,enabled,revision,updated_at) VALUES(1,0,1,?)',array($now))) throw new RuntimeException('English settings unavailable.');
  foreach(array('foundation','a1','a2','b1','b2','c1','c2') as $id) if(!$this->db->query('INSERT IGNORE INTO st_english_levels(level_id,status,updated_at) VALUES(?,?,?)',array($id,'draft',$now))) throw new RuntimeException('English levels unavailable.');
 }
 public function down(){throw new RuntimeException('English learner history must be retained.');}
}
