<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Commerce extends MY_Controller
{
	private $commerce_environment;
	public function _remap($method, $args = array())
	{
		if ( ! in_array($method, array('index','buy','redeem','admin','package','offer','checkout','order','orders','wallets','ledger','compensate'), TRUE)) { show_404(); return; }
		$this->require_login(array('adult','parent'));
		if (in_array($method, array('admin','package','offer','orders','wallets','ledger','compensate'), TRUE)) { $this->require_role(array('system_admin')); }
		$this->output->set_header('Cache-Control: private, no-store')->set_header('Referrer-Policy: no-referrer');
		$this->load->model(array('Commerce_model','Lifetime_model'));
		$this->commerce_environment = $this->input->post('environment', FALSE) ?? $this->input->get('environment', FALSE) ?? (ENVIRONMENT === 'development' ? 'sandbox' : 'production');
		if ( ! in_array($this->commerce_environment, array('sandbox','production'), TRUE)) { show_error('Lingkungan transaksi tidak valid.', 400); return; }
		$can_sandbox = $this->Commerce_model->sandbox_allowed($this->current_user['id']);
		if ($this->commerce_environment === 'sandbox' && ! $can_sandbox) { show_error('Sandbox khusus pengelola.', 403); return; }
		$this->Commerce_model->set_environment($this->commerce_environment);
		$this->load->vars(array('environment' => $this->commerce_environment, 'environment_label' => $this->commerce_environment === 'production' ? 'Live' : 'Sandbox', 'can_sandbox' => $can_sandbox));
		try { call_user_func_array(array($this, $method), $args); }
		catch (Throwable $error) { log_message('error', 'Commerce request unavailable.'); show_error('Data belum dapat dimuat. Muat ulang sebelum mencoba lagi.', 503); }
	}
	private function page()
	{
		$p = $this->input->get('halaman', FALSE) ?? '1';
		if ( ! $this->Commerce_model->number($p, 1, 10000)) { show_error('Halaman tidak valid.', 400); }
		return (int) $p;
	}
	private function values($fields)
	{
		$v = array(); foreach ($fields as $f) { $v[$f] = $this->input->post($f, FALSE); } return $v;
	}
	private function failure($result)
	{
		$code = $result['code'] ?? 503;
		show_error($result['message'] ?? ($code === 400 ? 'Isi formulir dan konfirmasi dengan benar.' : ($code === 403 ? 'Verifikasi email dan pastikan penerima berada dalam keluarga Anda.' : ($code === 409 ? 'Data berubah, voucher sudah digunakan/kedaluwarsa, atau saldo tidak cukup. Muat ulang riwayat sebelum mencoba lagi.' : 'Transaksi belum dapat dipastikan. Periksa riwayat sebelum mengulang.'))), $code);
	}
	public function index()
	{
		$this->require_http_methods(array('GET','HEAD')); $page = $this->page(); $id = $this->current_user['id'];
		$this->render_page('commerce/index', array('page_title' => 'Paket, kredit & voucher', 'offers' => $this->Commerce_model->offers(FALSE, $page), 'wallet' => $this->Commerce_model->wallet($id, $page), 'purchases' => $this->Commerce_model->purchases($id, $page), 'orders' => $this->Commerce_model->orders($id, $page), 'beneficiaries' => $this->Commerce_model->beneficiaries($id), 'page' => $page, 'sandbox_allowed' => $this->Commerce_model->sandbox_allowed($id)));
	}
	public function buy($id = '0')
	{
		$this->require_http_methods(array('POST'));
		if ( ! $this->Commerce_model->number((string) $id, 1) || $this->input->post('confirm') !== '1') { show_error('Konfirmasi pembelian diperlukan.', 400); return; }
		$r = $this->Commerce_model->buy($this->current_user['id'], $this->current_user['auth_stamp'], (int) $id, $this->input->post('revision', FALSE), $this->input->post('learner_id', FALSE), $this->input->post('request_key', FALSE), $this->input->post('package_revision', FALSE));
		if ( ! $r['ok']) { $this->failure($r); return; }
		$this->session->set_flashdata('success', 'Transaksi #'.$r['id'].' tersimpan. Voucher yang belum digunakan tersedia di riwayat.'); redirect('akun/kredit?environment='.$this->commerce_environment, 'location', 303);
	}
	public function redeem($id = '0')
	{
		$this->require_http_methods(array('POST'));
		if ( ! $this->Commerce_model->number((string) $id, 1) || $this->input->post('confirm') !== '1') { show_error('Konfirmasi penggunaan voucher diperlukan.', 400); return; }
		$r = $this->Commerce_model->redeem($this->current_user['id'], $this->current_user['auth_stamp'], (int) $id, $this->input->post('learner_id', FALSE));
		if ( ! $r['ok']) { $this->failure($r); return; }
		$this->session->set_flashdata('success', 'Voucher digunakan satu kali. Akses belajar tercatat untuk penerima yang dipilih.'); redirect('akun/kredit?environment='.$this->commerce_environment, 'location', 303);
	}
	public function admin()
	{
		$this->require_http_methods(array('GET','HEAD'));
		$page = $this->page();
		$this->render_page('admin/commerce/index', array('page_title' => 'Paket & produk', 'admin_section' => 'commerce', 'packages' => $this->Commerce_model->packages($page), 'offers' => $this->Commerce_model->offers(TRUE, $page), 'page' => $page));
	}
	public function package($id = '0')
	{
		$this->require_http_methods(array('GET','HEAD','POST'));
		if ( ! $this->Commerce_model->number((string) $id)) { show_404(); return; }
		$record = (int) $id ? $this->Commerce_model->package((int) $id) : array('id' => 0, 'title' => '', 'summary' => '', 'status' => 'draft', 'is_official' => 0, 'revision' => 0, 'courses' => array());
		if ( ! $record) { show_404(); return; }
		if ($this->input->method(TRUE) === 'POST')
		{
			$v = $this->values(array('title','summary','status','is_official','revision','confirm','family_practice_enabled','point_price','point_days','requires_questions')); foreach (array('family_practice_enabled','point_price','point_days','requires_questions') as $optional) { $v[$optional] = $v[$optional] ?? '0'; } $v['courses'] = $this->input->post('courses', FALSE) ?? array();
			$r = $this->Commerce_model->save_package($this->current_user['id'], $this->current_user['auth_stamp'], (int) $id, $v);
			if ( ! $r['ok']) { $this->failure($r); return; }
			$this->session->set_flashdata('success', 'Paket tersimpan. Isi transaksi yang sudah dibeli tidak berubah.'); redirect('kelola/paket/'.$r['id'], 'location', 303); return;
		}
		$this->render_page('admin/commerce/package', array('page_title' => 'Atur paket belajar', 'admin_section' => 'commerce', 'record' => $record, 'courses' => $this->Commerce_model->courses()));
	}
	public function offer($id = '0')
	{
		$this->require_http_methods(array('GET','HEAD','POST'));
		if ( ! $this->Commerce_model->number((string) $id)) { show_404(); return; }
		$record = (int) $id ? $this->Commerce_model->offer((int) $id) : array('id' => 0, 'title' => '', 'kind' => 'package', 'package_id' => NULL, 'price' => 0, 'credit_amount' => 0, 'access_days' => 0, 'voucher_days' => 0, 'status' => 'draft', 'revision' => 0);
		if ( ! $record) { show_404(); return; }
		if ($this->input->method(TRUE) === 'POST')
		{
			$v = $this->values(array('title','kind','package_id','price','credit_amount','access_days','voucher_days','status','revision','confirm','reason','live_approved','is_lifetime'));
			$v['live_approved'] = $v['live_approved'] ?? '0';
			$r = $this->Commerce_model->save_offer($this->current_user['id'], $this->current_user['auth_stamp'], (int) $id, $v);
			if ( ! $r['ok']) { $this->failure($r); return; }
			$this->session->set_flashdata('success', 'Produk tersimpan dan diaudit. Penjualan live memerlukan persetujuan produk, isi siap dan konfigurasi pembayaran aktif.'); redirect('kelola/produk/'.$r['id'], 'location', 303); return;
		}
		$this->render_page('admin/commerce/offer', array('page_title' => 'Atur produk & harga', 'admin_section' => 'commerce', 'record' => $record, 'packages' => $this->Commerce_model->packages()));
	}
	public function checkout($id = '0')
	{
		$this->require_http_methods(array('POST'));
		if ( ! $this->Commerce_model->number((string) $id, 1) || $this->input->post('confirm') !== '1') { show_error('Konfirmasi pembayaran diperlukan.', 400); return; }
		$r = $this->Commerce_model->checkout($this->current_user['id'], $this->current_user['auth_stamp'], (int) $id, $this->input->post('revision', FALSE), $this->input->post('request_key', FALSE));
		if ( ! $r['ok']) { $this->failure($r); return; }
		redirect('akun/pesanan/'.$r['id'], 'location', 303);
	}
	public function order($id = '0')
	{
		$this->require_http_methods(array('GET','HEAD','POST'));
		if ( ! $this->Commerce_model->number((string) $id, 1)) { show_404(); return; }
		$admin = in_array('system_admin', $this->current_user['roles'], TRUE);
		$record = $this->Commerce_model->order((int) $id, $admin ? NULL : $this->current_user['id']);
		if ( ! $record) { show_404(); return; }
		if ($record['purpose'] === 'donation') { redirect('donasi/pesanan/'.$id.'?environment='.$record['environment'], 'location', 303); return; }
		if ($this->input->method(TRUE) === 'POST')
		{
			$r = $this->Commerce_model->reconcile($this->current_user['id'], $this->current_user['auth_stamp'], (int) $id, $admin);
			if ( ! $r['ok']) { $this->failure($r); return; }
			$this->session->set_flashdata('success', 'Status diperiksa langsung ke Midtrans sesuai lingkungan pesanan.'); redirect('akun/pesanan/'.$id, 'location', 303); return;
		}
		$this->render_page($admin ? 'admin/commerce/order' : 'commerce/order', array('page_title' => 'Pesanan '.$record['environment'], 'admin_section' => 'orders', 'record' => $record));
	}
	public function orders()
	{
		$this->require_http_methods(array('GET','HEAD')); $page = $this->page(); $status = $this->input->get('status', FALSE) ?? '';
		if ( ! in_array($status, array('','created','pending','paid','expired','failed','cancelled','review'), TRUE)) { show_error('Filter tidak valid.', 400); return; }
		$this->render_page('admin/commerce/orders', array('page_title' => 'Pesanan & rekonsiliasi', 'admin_section' => 'orders', 'orders' => $this->Commerce_model->orders(NULL, $page, $status), 'page' => $page, 'status' => $status));
	}
	public function wallets()
	{
		$this->require_http_methods(array('GET','HEAD')); $page = $this->page();
		$this->render_page('admin/commerce/wallets', array('page_title' => 'Kredit & ledger', 'admin_section' => 'wallets', 'wallets' => $this->Commerce_model->wallets($page), 'page' => $page));
	}
	public function ledger($owner = '0')
	{
		$this->require_http_methods(array('GET','HEAD'));
		if ( ! $this->Commerce_model->number((string) $owner, 1)) { show_404(); return; }
		$page = $this->page(); $user = $this->Commerce_model->wallet_owner((int) $owner);
		if ( ! $user || $user['account_kind'] === 'child') { show_404(); return; }
		$this->render_page('admin/commerce/ledger', array('page_title' => 'Ledger kredit '.$this->commerce_environment, 'admin_section' => 'wallets', 'owner' => $owner, 'display_name' => $user['display_name'], 'wallet' => $this->Commerce_model->wallet((int) $owner, $page), 'purchases' => $this->Commerce_model->purchases((int) $owner, $page), 'page' => $page));
	}
	public function compensate($id = '0')
	{
		$this->require_http_methods(array('POST'));
		if ( ! $this->Commerce_model->number((string) $id, 1) || $this->input->post('confirm') !== '1') { show_error('Konfirmasi kompensasi diperlukan.', 400); return; }
		$this->load->model('Mail_model');
		$r = $this->Mail_model->reauthenticate($this->current_user['id'], $this->current_user['auth_stamp'], $this->input->post('password', FALSE), $this->input->ip_address());
		if ( ! $r['ok']) { $this->failure($r); return; }
		$r = $this->Commerce_model->compensate_voucher($this->current_user['id'], $this->current_user['auth_stamp'], (int) $id, $this->input->post('reason', FALSE));
		if ( ! $r['ok']) { $this->failure($r); return; }
		$this->session->set_flashdata('success', 'Kompensasi tercatat sebagai mutasi baru. Riwayat pembelian tetap disimpan.'); redirect('kelola/kredit/'.$r['owner'].'?environment='.$r['environment'], 'location', 303);
	}
}
