<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_arena_flashcards extends CI_Migration
{
	public function up()
	{
		if ($this->db->query("CREATE TABLE st_arena_card_progress (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			learner_user_id BIGINT UNSIGNED NOT NULL, pack_id BIGINT UNSIGNED NOT NULL,
			content_version INT UNSIGNED NOT NULL, card_key CHAR(64) CHARACTER SET ascii NOT NULL,
			status ENUM('known','repeat') NOT NULL, review_count INT UNSIGNED NOT NULL DEFAULT 1,
			last_run_id BIGINT UNSIGNED NOT NULL, updated_at DATETIME NOT NULL,
			UNIQUE KEY uq_arena_card (learner_user_id,pack_id,content_version,card_key),
			KEY idx_arena_cards_report (updated_at,pack_id,learner_user_id),
			CONSTRAINT fk_arena_card_owner FOREIGN KEY (learner_user_id) REFERENCES st_users(id) ON DELETE RESTRICT,
			CONSTRAINT fk_arena_card_pack FOREIGN KEY (pack_id) REFERENCES st_arena_packs(id) ON DELETE RESTRICT,
			CONSTRAINT fk_arena_card_run FOREIGN KEY (last_run_id) REFERENCES st_arena_runs(id) ON DELETE RESTRICT
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci") === FALSE) { throw new RuntimeException('Arena card progress unavailable.'); }
		$catalog = json_decode(file_get_contents(APPPATH.'config/arena_flashcards.json'), TRUE, 512, JSON_THROW_ON_ERROR);
		$now = gmdate('Y-m-d H:i:s');
		$this->db->trans_begin();
		foreach ($catalog['packs'] as $pack)
		{
			if ( ! $this->db->insert('st_arena_packs', array('slug' => $pack['slug'], 'game_type' => $pack['type'], 'title' => $pack['title'], 'learning_stage' => $pack['stage'], 'content_json' => json_encode($pack, JSON_UNESCAPED_UNICODE), 'content_version' => $catalog['version'], 'created_at' => $now, 'updated_at' => $now))) { $this->db->trans_rollback(); throw new RuntimeException('Arena cards not saved.'); }
		}
		if ($this->db->trans_status() !== TRUE || $this->db->trans_commit() !== TRUE) { throw new RuntimeException('Arena cards commit not confirmed.'); }
	}

	public function down()
	{
		throw new RuntimeException('Card progress must not be deleted automatically.');
	}
}
