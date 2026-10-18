<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_draft_products extends CI_Migration
{
	private function insert($table, $data)
	{
		if ( ! $this->db->insert($table, $data)) { throw new RuntimeException('Draft catalog unavailable.'); }
		return $this->db->insert_id();
	}
	private function offer($code, $title, $kind, $price, $package = NULL, $credits = 0, $days = 0, $voucher_days = 0)
	{
		if ($this->db->get_where('st_commerce_offers', array('draft_code' => $code))->num_rows()) { return; }
		$now = gmdate('Y-m-d H:i:s');
		$this->insert('st_commerce_offers', array('draft_code' => $code, 'title' => $title, 'kind' => $kind, 'price' => $price, 'package_id' => $package, 'credit_amount' => $credits, 'access_days' => $days, 'voucher_days' => $voucher_days, 'status' => 'draft', 'live_approved' => 0, 'revision' => 1, 'created_at' => $now, 'updated_at' => $now));
	}
	public function up()
	{
		$this->db->trans_begin();
		try
		{
			foreach (array('tk' => array('TK',40,500), 'sd' => array('SD',60,750), 'smp' => array('SMP',80,1000), 'sma' => array('SMA/SMK',100,1250)) as $code => $v)
			{
				$slug = 'draft-latihan-topik-'.$code;
				$p = $this->db->get_where('st_packages', array('slug' => $slug))->row_array();
				if ($p) { $id = $p['id']; }
				else
				{
					$now = gmdate('Y-m-d H:i:s');
					$id = $this->insert('st_packages', array('title' => 'Latihan Soal '.$v[0].' — Paket Topik (draft)', 'slug' => $slug, 'summary' => 'Rancangan satu paket topik untuk jenjang '.$v[0].'. Soal, topik, jumlah latihan dan cakupan kelas belum tersedia/belum disetujui. Bukan seluruh kurikulum jenjang. Akses usulan 365 hari untuk satu penerima. Jangan jual sebelum soal ditinjau, kursus terbit dan harga disetujui. Arena serta Battle gratis tidak termasuk objek penjualan ini.', 'status' => 'draft', 'is_official' => 1, 'requires_questions' => 1, 'point_price' => $v[2], 'point_days' => 365, 'created_at' => $now, 'updated_at' => $now));
				}
				$this->offer('draft-latihan-'.$code, 'Akses Latihan '.$v[0].' — 365 hari (draft)', 'package', $v[1], $id, 0, 365);
				$this->offer('draft-voucher-'.$code, 'Voucher Latihan '.$v[0].' (draft)', 'voucher', $v[1], $id, 0, 365, 30);
			}
			foreach (array(20000 => 200, 50000 => 500, 100000 => 1000) as $rupiah => $credits) { $this->offer('draft-topup-'.$credits, 'Top up '.$credits.' kredit (draft)', 'topup', $rupiah, NULL, $credits); }
			$this->offer('draft-platinum-365', 'Platinum Keluarga — 365 hari (draft)', 'platinum', 300000, NULL, 0, 365);
			$this->offer('draft-slot-anak', 'Satu Slot Anak Tambahan (draft)', 'slot', 10000);
			if ( ! $this->db->trans_status() || ! $this->db->trans_commit()) { throw new RuntimeException('Draft catalog not committed.'); }
		}
		catch (Throwable $error) { $this->db->trans_rollback(); throw $error; }
	}
	public function down() { throw new RuntimeException('Preserve owner-reviewed product drafts.'); }
}
