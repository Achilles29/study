<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Registration_notification_floor extends CI_Migration
{
	public function up()
	{
		if (!$this->db->field_exists('registration_floor', 'st_notification_config'))
		{
			if (!$this->db->query('ALTER TABLE st_notification_config ADD registration_floor BIGINT UNSIGNED NOT NULL DEFAULT 0'))
			{
				throw new RuntimeException('Registration notification floor unavailable.');
			}
			if (!$this->db->query('UPDATE st_notification_config SET registration_floor=(SELECT COALESCE(MAX(id),0) FROM st_audit_events) WHERE id=1'))
			{
				throw new RuntimeException('Registration notification floor not initialized.');
			}
		}
	}

	public function down()
	{
		throw new RuntimeException('Preserve notification settings.');
	}
}
