<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Games extends MY_Controller
{
	public function matching($slug = '')
	{
		if ( ! in_array($this->input->method(TRUE), array('GET', 'HEAD'), TRUE))
		{
			header('Allow: GET, HEAD');
			show_error('Permainan ini tidak menerima pengiriman nilai.', 405, 'Metode tidak tersedia');
			return;
		}
		$this->load->model(array('Content_model', 'Flashcard_model'));
		$lesson = $this->Content_model->get_published_lesson($slug);
		if ( ! $lesson)
		{
			show_404();
			return;
		}
		if ( ! $this->Content_model->can_access_lesson($this->current_user ? $this->current_user['id'] : 0, $lesson))
		{
			show_error('Materi permainan ini belum terbuka untuk akun Anda.', 403, 'Akses dibatasi');
			return;
		}
		$deck = $this->Flashcard_model->get_published_deck_for_lesson($lesson['id']);
		$this->load->library('Matching_cards');
		$cards = $deck ? $this->Flashcard_model->get_published_cards($deck['id']) : array();
		$eligible = $this->matching_cards->eligible($cards);
		$this->output->set_header('Cache-Control: private, no-store');
		$this->output->set_header('X-Robots-Tag: noindex, nofollow');
		$this->render_page('games/matching', array('page_title' => 'Cocokkan Pasangan', 'lesson' => $lesson, 'game_cards' => $eligible, 'excluded_cards' => count($cards) - count($eligible), 'round_size' => $this->current_user && $this->current_user['learning_stage'] === 'early_childhood' ? 4 : 6, 'load_matching_script' => count($eligible) >= 2));
	}
}
