<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payment_settings extends MY_Controller
{
	public function index()
	{
		$this->require_role(array('system_admin'));
		$this->require_http_methods(array('GET', 'HEAD', 'POST'));
		$environment = $this->input->post('environment', FALSE) ?? $this->input->get('environment', FALSE) ?? (ENVIRONMENT === 'development' ? 'sandbox' : 'production');
		if ( ! in_array($environment, array('sandbox','production'), TRUE)) { show_error('Lingkungan pembayaran tidak valid.', 400); return; }
		$this->load->model('Payment_config_model');
		try { $record = $this->Payment_config_model->latest($environment); }
		catch (RuntimeException $error) { show_error('Konfigurasi pembayaran belum dapat dimuat.', 503); return; }
		$error = ''; $field_errors = array(); $merchant_value = $record['merchant_id'] ?? '';
		if ($this->input->method(TRUE) === 'POST')
		{
			$id = $this->input->post('previous_id', FALSE); $action = $this->input->post('action', FALSE);
			if ( ! is_string($id) || ! preg_match('/\\A(?:0|[1-9][0-9]{0,17})\\z/', $id) || ! in_array($action, array('save', 'probe'), TRUE)) { show_error('Formulir tidak valid.', 400); return; }
			$result = NULL;
			if ($environment === 'production')
			{
				$this->load->model('Mail_model');
				$auth = $this->Mail_model->reauthenticate($this->current_user['id'], $this->current_user['auth_stamp'], $this->input->post('password', FALSE), $this->input->ip_address());
				if ( ! $auth['ok']) { $result = array('ok' => FALSE, 'code' => $auth['code'] ?? 403, 'errors' => array('password' => 'Konfirmasi kata sandi Superadmin gagal atau dibatasi.')); }
			}
			if ($action === 'save' && $result === NULL)
			{
				$values = array(); foreach (array('merchant_id','server_key','client_key','confirm_live') as $key) { $values[$key] = $this->input->post($key, FALSE); }
				$values['environment'] = $environment; $values['checkout_enabled'] = $this->input->post('checkout_enabled', FALSE) ?? '0';
				$merchant = is_string($values['merchant_id']) ? trim($values['merchant_id'], " \t\r\n") : '';
				if (preg_match('/\\A[A-Za-z0-9_-]{1,80}\\z/', $merchant) && ! preg_match('/\\A(?:SB-)?Mid-(?:server|client)-/', $merchant)) { $merchant_value = $merchant; }
				$result = $this->Payment_config_model->save($values, $id, $this->current_user['id'], $this->current_user['auth_stamp']);
			}
			elseif ($result === NULL) { $result = $this->Payment_config_model->probe($id, $this->current_user['id'], $environment, $this->current_user['auth_stamp']); }
			if ($result['ok'])
			{
				$probe_ready = $action === 'probe' && ($result['status'] ?? '') === 'status_api_reached';
				$this->session->set_flashdata($action === 'save' || $probe_ready ? 'success' : 'info', $action === 'save' ? 'Konfigurasi tersimpan terenkripsi. Produk draft tidak otomatis dijual; versi pesanan lama tetap berlaku.' : ($probe_ready ? 'Uji koneksi status API berhasil. Ini tidak membuat tagihan dan belum membuktikan transaksi QRIS berhasil.' : 'Uji koneksi belum berhasil. Periksa hasil pada Kesiapan integrasi; checkout belum dapat diaktifkan.'));
				redirect('kelola/pengaturan/pembayaran?environment='.$environment, 'location', 303); return;
			}
			$code = $result['code'] ?? 503; $field_errors = $result['errors'] ?? array();
			$error = $code === 400 ? 'Konfigurasi belum tersimpan. Periksa kolom yang ditandai. Kunci tidak diisi ulang setelah gagal.' : ($code === 409 ? 'Versi telah berubah atau uji baru dilakukan. Muat ulang; tunggu 60 detik sebelum uji ulang.' : ($code === 403 ? 'Autentikasi/izin perlu diperiksa.' : 'Penyimpanan atau hasil uji belum dapat dipastikan. Muat ulang sebelum mencoba lagi.'));
			$this->output->set_status_header($code);
		}
		$this->render_page('admin/payment_settings', compact('record','error','field_errors','merchant_value','environment') + array('page_title' => 'Pengaturan Midtrans', 'admin_section' => 'settings'));
	}
}
