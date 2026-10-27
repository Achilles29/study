<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_codex_bot_provider extends CI_Migration
{
	public function up()
	{
		if (!$this->db->field_exists('provider', 'st_bot_ai_config') && !$this->db->query("ALTER TABLE st_bot_ai_config ADD provider ENUM('api','codex') NOT NULL DEFAULT 'api' AFTER enabled"))
		{
			throw new RuntimeException('Bot provider storage unavailable.');
		}
	}

	public function down()
	{
		throw new RuntimeException('Preserve bot AI provider settings.');
	}
}
