<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_live_commerce extends CI_Migration
{
	public function up()
	{
		foreach (array('st_payment_configs' => "ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox'", 'st_wallets' => "ENUM('legacy','sandbox','production') NOT NULL DEFAULT 'legacy'", 'st_payment_orders' => "ENUM('legacy','sandbox','production') NOT NULL DEFAULT 'legacy'", 'st_commerce_purchases' => "ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox'", 'st_commerce_grants' => "ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox'", 'st_family_slots' => "ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox'") as $table => $type)
		{
			if ($this->db->query('ALTER TABLE '.$table.' MODIFY environment '.$type) === FALSE) { throw new RuntimeException('Live schema unavailable.'); }
		}
		foreach (array('st_payment_configs' => array('checkout_enabled' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0'), 'st_commerce_offers' => array('live_approved' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0', 'draft_code' => 'VARCHAR(80) NULL UNIQUE'), 'st_packages' => array('requires_questions' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0')) as $table => $columns)
		{
			foreach ($columns as $name => $type) { if ( ! $this->db->field_exists($name, $table) && $this->db->query('ALTER TABLE '.$table.' ADD '.$name.' '.$type) === FALSE) { throw new RuntimeException('Live approval schema unavailable.'); } }
		}
	}
	public function down() { throw new RuntimeException('Preserve payment history and environments.'); }
}
