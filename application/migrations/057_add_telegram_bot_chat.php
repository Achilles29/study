<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_telegram_bot_chat extends CI_Migration
{
	public function up()
	{
		$statements = array(
			"CREATE TABLE IF NOT EXISTS st_telegram_bot_state (id TINYINT UNSIGNED PRIMARY KEY, next_update_id BIGINT UNSIGNED NOT NULL DEFAULT 0, commands_set TINYINT UNSIGNED NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
			"INSERT IGNORE INTO st_telegram_bot_state(id,updated_at) VALUES(1,UTC_TIMESTAMP())",
			"CREATE TABLE IF NOT EXISTS st_telegram_pair_updates (code_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, chat_id VARCHAR(32) NOT NULL, message_at DATETIME NOT NULL, created_at DATETIME NOT NULL, KEY idx_pair_expiry(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
			"CREATE TABLE IF NOT EXISTS st_telegram_bot_inbox (update_id BIGINT UNSIGNED PRIMARY KEY, chat_id VARCHAR(32) NOT NULL, message_id BIGINT UNSIGNED NOT NULL, body_cipher TEXT NOT NULL, status ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending', error VARCHAR(80) NULL, created_at DATETIME NOT NULL, processed_at DATETIME NULL, KEY idx_bot_inbox(status,update_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
			"CREATE TABLE IF NOT EXISTS st_bot_ai_config (id TINYINT UNSIGNED PRIMARY KEY, enabled TINYINT UNSIGNED NOT NULL DEFAULT 0, key_cipher TEXT NULL, model VARCHAR(40) NOT NULL DEFAULT 'gpt-6-luna', daily_limit SMALLINT UNSIGNED NOT NULL DEFAULT 30, usage_day DATE NULL, used_count SMALLINT UNSIGNED NOT NULL DEFAULT 0, revision INT UNSIGNED NOT NULL DEFAULT 1, updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
			"INSERT IGNORE INTO st_bot_ai_config(id,updated_at) VALUES(1,UTC_TIMESTAMP())",
			"CREATE TABLE IF NOT EXISTS st_bot_ai_turns (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, chat_id VARCHAR(32) NOT NULL, role ENUM('user','assistant') NOT NULL, content_cipher TEXT NOT NULL, created_at DATETIME NOT NULL, KEY idx_bot_ai_chat(chat_id,id), KEY idx_bot_ai_age(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
		);
		foreach ($statements as $sql) if (!$this->db->query($sql)) throw new RuntimeException('Telegram bot chat storage unavailable.');
		if (!$this->db->field_exists('reply_to_message_id', 'st_telegram_outbox') && !$this->db->query('ALTER TABLE st_telegram_outbox ADD reply_to_message_id BIGINT UNSIGNED NULL'))
		{
			throw new RuntimeException('Telegram reply storage unavailable.');
		}
	}

	public function down()
	{
		throw new RuntimeException('Preserve bot conversation and notification history.');
	}
}
