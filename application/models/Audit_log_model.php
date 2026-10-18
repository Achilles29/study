<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Audit_log_model extends CI_Model
{
	public function event_options()
	{
		return array(
			'donation.created' => 'Pesanan donasi dibuat', 'donation.received' => 'Donasi terverifikasi diterima',
			'traffic.operational_viewed' => 'Log operasional dibaca Superadmin',
			'help.created' => 'Pesan bantuan dibuat', 'help.viewed' => 'Pesan bantuan dibaca pengelola', 'help.answered' => 'Pesan bantuan dijawab pengelola', 'help.replied' => 'Pesan bantuan dibalas pengirim',
			'family.child_password_reset' => 'Sandi anak dipulihkan oleh orang tua', 'account.status_changed' => 'Status akun diubah', 'account.course_access_changed' => 'Pembatasan kursus diubah',
			'google.configuration_saved' => 'Konfigurasi Google disimpan', 'google.probe_passed' => 'Uji Google berhasil', 'google.linked' => 'Google ditautkan', 'google.unlinked' => 'Google dilepas', 'google.login' => 'Masuk dengan Google', 'battle.live_started' => 'Battle live dimulai',
			'settings.updated' => 'Pengaturan diubah',
			'arena.availability_changed' => 'Ketersediaan set Arena diubah',
			'payment.config_saved' => 'Versi konfigurasi pembayaran disimpan', 'payment.config_probe' => 'Koneksi pembayaran diuji',
			'account.registered' => 'Akun didaftarkan', 'auth.login' => 'Masuk akun', 'auth.logout' => 'Keluar akun', 'auth.password_changed' => 'Kata sandi diubah', 'admin.initial_bootstrap' => 'Admin awal dibuat',
			'family.child_created' => 'Akun anak dibuat', 'family.child_learning_stage_updated' => 'Kelompok belajar anak diubah',
			'content.course_created' => 'Kursus dibuat', 'content.course_updated' => 'Kursus diubah', 'content.course_submitted' => 'Kursus diajukan', 'content.course_reviewed' => 'Kursus ditinjau', 'content.course_archived' => 'Kursus diarsipkan',
			'content.lesson_created' => 'Pelajaran dibuat', 'content.lesson_updated' => 'Pelajaran diubah',
			'content.structure_changed' => 'Susunan kursus diubah',
			'content.lesson_image_changed' => 'Gambar pelajaran diubah',
			'content.flashcard_deck_created' => 'Set kartu dibuat', 'content.flashcard_deck_updated' => 'Set kartu diubah', 'content.flashcard_created' => 'Kartu dibuat', 'content.flashcard_updated' => 'Kartu diubah', 'content.flashcard_deleted' => 'Kartu dihapus',
			'content.question_created' => 'Soal kursus dibuat', 'content.question_updated' => 'Soal kursus diubah', 'content.question_deleted' => 'Soal kursus dihapus',
			'practice.started' => 'Latihan dimulai', 'practice.graded' => 'Latihan dinilai',
			'family.practice_created' => 'Sesi keluarga dibuat', 'family.practice_title' => 'Judul sesi keluarga diubah', 'family.practice_save' => 'Soal sesi keluarga disimpan', 'family.practice_delete' => 'Soal sesi keluarga dihapus', 'family.practice_cancel' => 'Draf sesi keluarga dibatalkan', 'family.practice_delivered' => 'Sesi keluarga dikirim', 'family.practice_withdrawn' => 'Sesi keluarga ditarik', 'family.practice_copied' => 'Sesi keluarga disalin',
			'matching.started' => 'Permainan dimulai', 'matching.completed' => 'Permainan selesai', 'matching.abandoned' => 'Permainan dihentikan'
		);
	}

	public function entity_options()
	{
		return array('help_ticket' => 'Percakapan bantuan', 'arena_pack' => 'Set Arena', 'settings' => 'Pengaturan', 'payment_config' => 'Konfigurasi pembayaran', 'user' => 'Akun', 'course' => 'Kursus', 'lesson' => 'Pelajaran', 'flashcard_deck' => 'Set kartu', 'question' => 'Soal kursus', 'practice_session' => 'Sesi latihan', 'practice_attempt' => 'Percobaan latihan', 'matching_run' => 'Permainan');
	}

	public function search($filters, $bounds, $offset = 0, $count_only = FALSE)
	{
		$this->db->from('st_audit_events');
		foreach (array('aksi' => 'event_type', 'entitas' => 'entity_type', 'pelaku' => 'actor_user_id', 'id_entitas' => 'entity_id') as $key => $column)
		{
			if ($filters[$key] === 'other' && in_array($key, array('aksi', 'entitas'), TRUE))
			{
				$this->db->where_not_in($column, array_keys($key === 'aksi' ? $this->event_options() : $this->entity_options()));
			}
			elseif ($filters[$key] !== '')
			{
				$this->db->where($column, $filters[$key]);
			}
		}
		if ($bounds['mulai'] !== NULL)
		{
			$this->db->where('created_at >=', $bounds['mulai']);
		}
		if ($bounds['akhir'] !== NULL)
		{
			$this->db->where('created_at <', $bounds['akhir']);
		}
		if ($count_only)
		{
			return $this->db->count_all_results();
		}
		$direction = $filters['urutan'] === 'oldest' ? 'ASC' : 'DESC';
		// Do not load raw metadata or join private user/learning data for this report.
		return $this->db->select('id,actor_user_id,event_type,entity_type,entity_id,created_at')->order_by('created_at', $direction)->order_by('id', $direction)->limit(20, max(0, (int) $offset))->get()->result_array();
	}
}
