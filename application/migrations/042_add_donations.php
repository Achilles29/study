<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_donations extends CI_Migration
{
	public function up()
	{
		if ( ! $this->db->field_exists('purpose', 'st_payment_orders') && $this->db->query("ALTER TABLE st_payment_orders ADD purpose ENUM('commerce','donation') NOT NULL DEFAULT 'commerce', ADD INDEX idx_payment_purpose(environment,purpose,created_at)") === FALSE) { throw new RuntimeException('Donation schema unavailable.'); }
		$this->load->library('site_settings');
		if ( ! $this->db->where('section', 'donasi')->get('st_site_settings')->num_rows())
		{
			$id = (int) $this->db->query('SELECT COALESCE(MAX(id),0)+1 next_id FROM st_site_settings')->row_array()['next_id'];
			if ( ! $this->db->insert('st_site_settings', array('id' => $id, 'section' => 'donasi', 'values_json' => json_encode($this->site_settings->defaults('donasi')), 'revision' => 1, 'updated_at' => gmdate('Y-m-d H:i:s')))) { throw new RuntimeException('Donation settings unavailable.'); }
		}
	}
	public function down() { throw new RuntimeException('Preserve donation and payment history.'); }
}
