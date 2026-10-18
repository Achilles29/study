<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Arena extends MY_Controller
{
	public function _remap($method, $params = array())
	{
		$this->require_login();
		$this->output->set_header('Cache-Control: private, no-store');
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		if ( ! in_array($method, array('index', 'game', 'choose', 'run', 'history', 'manage', 'family'), TRUE)) { show_404(); return; }
		$this->require_http_methods(in_array($method, array('choose', 'run', 'manage'), TRUE) ? array('GET', 'HEAD', 'POST') : array('GET', 'HEAD'));
		if ($method === 'manage') { $this->require_role(array('system_admin')); }
		$this->load->model('Arena_model', 'arena');
		$this->load->helper('study_arena');
		$this->db->db_debug = FALSE;
		try { call_user_func_array(array($this, $method), $params); }
		catch (Throwable $error) { log_message('error', 'Arena request failed without exposing content or credentials.'); show_error('Arena belum dapat dimuat. Progres tersimpan tetap dipertahankan. Coba muat ulang.', 503, 'Arena belum tersedia'); }
	}

	private function identifier($value)
	{
		if ( ! is_string($value) || ! preg_match('/\A[1-9][0-9]{0,17}\z/', $value)) { show_404(); exit; }
		return $value;
	}

	private function start_token($slug, $nonce)
	{
		return $nonce.'.'.hash_hmac('sha256', $this->current_user['id'].':arena:'.$slug.':'.$nonce, $this->config->item('encryption_key'));
	}

	private function error_message($result)
	{
		$this->output->set_status_header($result['code']);
		return array(400 => 'Isian tidak valid. Gunakan formulir yang tersedia.', 403 => 'Aktivitas sedang dinonaktifkan atau izin akun berubah. Riwayat tetap tersimpan.', 404 => 'Permainan tidak ditemukan.', 409 => 'Progres berubah di tab lain. Halaman ini sudah menampilkan keadaan terbaru; tinjau sebelum melanjutkan.', 503 => 'Penyimpanan belum dapat dipastikan. Muat ulang sebelum mencoba lagi.')[$result['code']] ?? 'Permintaan belum dapat diproses.';
	}

	public function index()
	{
		$this->render_page('arena/index', array('page_title' => 'Arena Belajar', 'games' => study_arena_games(), 'settings' => $this->arena->settings(), 'running' => $this->arena->running($this->current_user['id'])));
	}

	public function game($path = '')
	{
		$type = NULL; $game = NULL;
		foreach (study_arena_games() as $key => $entry) { if ($entry['path'] === $path) { $type = $key; $game = $entry; break; } }
		if ($game === NULL) { show_404(); return; }
		$kelas = study_grade_filter();
		if ($type === 'quest')
		{
			$this->render_page('arena/quest', array('page_title' => 'English Quest', 'kelas' => $kelas, 'packs' => study_grade_arena($this->arena->quest_catalog($this->current_user['id']), $kelas), 'settings' => $this->arena->quest_settings(), 'arena_settings' => $this->arena->settings(), 'running' => $this->arena->running($this->current_user['id']))); return;
		}
		if ($type === 'english')
		{
			$packs = study_grade_arena($this->arena->english_catalog($this->current_user['id']), $kelas); $settings = $this->arena->english_settings(); $arena_settings = $this->arena->settings();
			$this->render_page('arena/english', compact('packs', 'settings', 'arena_settings', 'kelas') + array('page_title' => 'Kelas Bahasa Inggris', 'running' => $this->arena->running($this->current_user['id']))); return;
		}
		$stage = $this->input->get('jenjang', FALSE) ?? '';
		if ( ! is_string($stage) || ! in_array($stage, array('', 'early_childhood', 'primary', 'secondary'), TRUE)) { show_error('Pilih jenjang yang tersedia.', 400); return; }
		$packs = array_values(array_filter(study_grade_arena($this->arena->catalog(), $kelas), static function ($pack) use ($type, $stage) {
			return $pack['game_type'] === $type && ($stage === '' || $pack['learning_stage'] === $stage); }));
		$total = count($packs); $page_count = max(1, (int) ceil($total / 12)); $page = min($page_count, $this->get_positive_query_int('halaman')); $packs = array_slice($packs, ($page - 1) * 12, 12);
		$progress = array_column($this->arena->progress($this->current_user['id']), NULL, 'pack_id');
		$levels = $type === 'expedition' ? study_grade_arena($this->arena->expedition_levels($this->current_user['id']), $kelas) : array();
		$card_progress = $type === 'flashcard' ? array_column($this->arena->card_summary($this->current_user['id']), NULL, 'pack_id') : array();
		$this->render_page('arena/game', compact('kelas', 'type', 'game', 'stage', 'packs', 'total', 'page', 'page_count', 'progress', 'levels', 'card_progress') + array('page_title' => $game['title'], 'settings' => $this->arena->settings()));
	}

	public function choose($slug = '')
	{
		$pack = $this->arena->pack($slug); if ( ! $pack) { show_404(); return; }
		$settings = $this->arena->settings(); $error = '';
		$unlocked = $pack['game_type'] !== 'expedition' || $this->arena->expedition_open($this->current_user['id'], $pack['id']);
		if ($pack['game_type'] === 'english')
		{
			$english = $this->arena->english_settings(); $settings['enabled'] = $settings['enabled'] === '1' && $english['enabled'] === '1' ? '1' : '0'; $settings['pass_score'] = $english['pass_score'];
			$unlocked = $this->arena->english_open($this->current_user['id'], $pack['id']);
		}
		if ($pack['game_type'] === 'quest')
		{
			$quest = $this->arena->quest_settings(); $settings['enabled'] = $settings['enabled'] === '1' && $quest['enabled'] === '1' ? '1' : '0';
			$unlocked = $this->arena->quest_open($this->current_user['id'], $pack['id']);
		}
		if ($this->input->method(TRUE) === 'POST')
		{
			$token = $this->input->post('start_token', FALSE);
			if ( ! is_string($token) || ! preg_match('/\A[a-f0-9]{32}\.[a-f0-9]{64}\z/', $token) || ! hash_equals($this->start_token($slug, substr($token, 0, 32)), $token)) { show_error('Formulir mulai tidak valid. Buka kembali pilihan permainan.', 400); return; }
			$result = $this->arena->start($this->current_user['id'], $slug, hash('sha256', $token), $this->input->post('mode', FALSE));
			if ($result['ok'])
			{
				if ( ! empty($result['resumed'])) { $this->session->set_flashdata('notice', 'Lanjutkan atau akhiri permainan yang masih berjalan sebelum memulai yang lain.'); }
				redirect('belajar/arena/main/'.$result['id'], 'location', 303); return;
			}
			$error = $this->error_message($result);
		}
		$this->render_page('arena/choose', compact('pack', 'settings', 'error', 'unlocked') + array('page_title' => $pack['title'], 'start_token' => $this->start_token($slug, bin2hex(random_bytes(16)))));
	}

	public function run($id = '')
	{
		$id = $this->identifier($id); $error = '';
		if ( ! $this->arena->owned($id, $this->current_user['id'])) { show_404(); return; }
		if ($this->input->method(TRUE) === 'POST')
		{
			$result = $this->arena->act($this->current_user['id'], $id, $this->input->post('revision', FALSE), $this->input->post('action', FALSE), $this->input->post('value', FALSE));
			if ($result['ok']) { redirect('belajar/arena/main/'.$id.'#permainan', 'location', 303); return; }
			$error = $this->error_message($result);
		}
		$run = $this->arena_engine->present($this->arena->owned($id, $this->current_user['id']));
		$view = array('english' => 'arena/english_run', 'quest' => 'arena/quest_run')[$run['game_type']] ?? 'arena/run';
		$this->render_page($view, compact('run', 'error') + array('page_title' => $run['title_snapshot']));
	}

	public function history()
	{
		$this->render_page('arena/history', $this->arena->history($this->current_user['id'], $this->get_positive_query_int('halaman')) + array('page_title' => 'Progres Arena Belajar', 'base' => 'belajar/arena/riwayat', 'child' => NULL));
	}

	public function family($id = '')
	{
		$this->require_login(array('parent'));
		$this->load->model('Family_practice_model');
		$child = $this->Family_practice_model->child($this->current_user['id'], $this->identifier($id));
		if ( ! $child) { show_404(); return; }
		$this->render_page('arena/history', $this->arena->history($child['id'], $this->get_positive_query_int('halaman'), $this->current_user['id']) + array('page_title' => 'Progres Arena anak', 'base' => 'keluarga/anak/'.$id.'/arena', 'child' => $child));
	}

	public function manage()
	{
		$kelas = study_grade_filter();
		$type = $this->input->get('jenis', FALSE) ?? '';
		if ( ! is_string($type) || ($type !== '' && ! isset(study_arena_games()[$type]))) { show_error('Jenis permainan tidak valid.', 400); return; }
		$stage = $this->input->get('jenjang', FALSE) ?? ''; $category = $this->input->get('kategori', FALSE) ?? '';
		if ( ! is_string($stage) || ! in_array($stage, array('', 'early_childhood', 'primary', 'secondary'), TRUE) || ! is_string($category) || ($category !== '' && ! preg_match('/\A[1-9][0-9]{0,8}\z/', $category))) { show_error('Filter Arena tidak valid.', 400); return; }
		$page = $this->get_positive_query_int('halaman');
		$error = '';
		if ($this->input->method(TRUE) === 'POST')
		{
			$id = $this->identifier($this->input->post('pack_id', FALSE));
			$result = $this->arena->toggle_pack($this->current_user['id'], $id, $this->input->post('revision', FALSE), $this->input->post('enabled', FALSE));
			if ($result['ok']) { $this->session->set_flashdata('success', 'Ketersediaan permainan diubah dan diaudit. Riwayat tidak dihapus.'); redirect('kelola/arena?'.http_build_query(array('jenis' => $type, 'jenjang' => $stage, 'kategori' => $category, 'halaman' => $page, 'kelas' => $kelas)), 'location', 303); return; }
			$error = $this->error_message($result);
		}
		$catalog = study_grade_arena($this->arena->management_catalog(), $kelas); $groups = array(); $master_categories = array();
		foreach (study_arena_games() as $key => $game) { $groups[$key] = array('total' => 0, 'enabled' => 0); }
		foreach ($catalog as $pack)
		{
			if (isset($groups[$pack['game_type']])) { $groups[$pack['game_type']]['total']++; $groups[$pack['game_type']]['enabled'] += (int) $pack['enabled']; }
			if ($pack['game_type'] === $type && $pack['master_category_id']) { $master_categories[$pack['master_category_id']] = $pack['master_category']; }
		}
		if ($category !== '' && ! isset($master_categories[$category])) { show_error('Kategori tidak tersedia untuk permainan ini.', 400); return; }
		$packs = array_values(array_filter($catalog, static function ($pack) use ($type, $stage, $category) {
			return $type !== '' && $pack['game_type'] === $type && ($stage === '' || $pack['learning_stage'] === $stage) && ($category === '' || (string) $pack['master_category_id'] === $category); }));
		$total = count($packs); $page_count = max(1, (int) ceil($total / 24)); $page = min($page, $page_count); $packs = array_slice($packs, ($page - 1) * 24, 24);
		$this->render_page('admin/arena', compact('kelas', 'packs', 'total', 'page', 'page_count', 'type', 'error', 'groups', 'master_categories', 'stage', 'category') + array('page_title' => 'Konten Arena Belajar', 'admin_section' => 'arena'));
	}
}
