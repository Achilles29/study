<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_google_registration extends CI_Migration
{
	private function execute($sql)
	{
		if ($this->db->query($sql) === FALSE) { throw new RuntimeException('Google registration schema unavailable.'); }
	}
	public function up()
	{
		if ( ! $this->db->field_exists('registration_enabled', 'st_google_config')) { $this->execute('ALTER TABLE st_google_config ADD registration_enabled TINYINT UNSIGNED NOT NULL DEFAULT 0'); }
		$this->execute("ALTER TABLE st_google_flows MODIFY purpose ENUM('login','link','probe','register') NOT NULL");
		foreach (array('registration_cipher' => 'TEXT NULL', 'completed_at' => 'DATETIME NULL') as $name => $type) { if ( ! $this->db->field_exists($name, 'st_google_flows')) { $this->execute('ALTER TABLE st_google_flows ADD '.$name.' '.$type); } }
	}
	public function down() { throw new RuntimeException('Preserve Google registrations.'); }
}
