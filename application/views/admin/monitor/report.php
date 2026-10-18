<section class="container page-section">
	<div class="page-heading"><div><p class="eyebrow"><?php echo html_escape($site_settings['brand_name']); ?> · Rapor belajar</p><h1 class="h2"><?php echo html_escape($user['display_name']); ?></h1><p>Periode <?php echo html_escape($filters['mulai'].' s.d. '.$filters['sampai']); ?> WIB</p></div><a class="btn btn-outline-primary no-print" href="<?php echo html_escape($family ? site_url('keluarga/anak') : study_monitor_url('kelola/pembelajaran/pengguna/'.$user['id'], $filters)); ?>">Kembali</a></div>
	<?php $this->load->view('admin/monitor/filters'); $this->load->view('admin/monitor/stats'); ?>
	<div class="feature-card"><h2 class="h5">Hasil latihan</h2><p>Nilai terakhir: <strong><?php echo $summary['latest_score'] === NULL ? 'Belum dinilai' : html_escape($summary['latest_score']).'%'; ?></strong> · Nilai terbaik: <strong><?php echo $summary['best_score'] === NULL ? 'Belum dinilai' : html_escape($summary['best_score']).'%'; ?></strong></p><p class="mb-0">Gunakan hasil ini untuk memilih kegiatan berikutnya bersama anak. Belum ada catatan bukan berarti anak gagal.</p></div>
	<?php $this->load->view('admin/monitor/course_rows'); ?>
	<p class="small text-secondary mt-4">Disusun <?php echo html_escape(date('d/m/Y H:i')); ?> WIB dari progres materi, kartu, percobaan latihan, dan sesi permainan tersimpan. Rapor ini bukan sertifikat kelulusan.</p>
	<p class="no-print small">Gunakan menu Cetak pada browser (Ctrl+P / ⌘P) untuk mencetak atau menyimpan PDF.</p>
</section>
