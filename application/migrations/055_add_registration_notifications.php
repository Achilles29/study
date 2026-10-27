<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_registration_notifications extends CI_Migration
{
	public function up()
	{
		foreach (array('members', 'children') as $column)
		{
			if (!$this->db->field_exists($column, 'st_notification_config') && !$this->db->query('ALTER TABLE st_notification_config ADD '.$column.' TINYINT UNSIGNED NOT NULL DEFAULT 1'))
			{
				throw new RuntimeException('Registration notification setting unavailable.');
			}
		}
	}

	public function down()
	{
		throw new RuntimeException('Preserve notification settings.');
	}
}
