<section class="container page-section">
	<a class="back-link" href="<?php echo site_url('beranda'); ?>">Kembali ke keluarga</a>
	<div class="page-heading mt-3"><div><p class="eyebrow">Dampingi perjalanan belajar</p><h1 class="h2">Latihan <?php echo html_escape($child['display_name']); ?></h1><p class="text-secondary mb-0">Lihat perkembangan dari setiap percobaan, bukan perbandingan dengan anak lain.</p></div></div>
	<div class="feature-card mb-4"><h2 class="h5">Ringkasan latihan</h2><p><?php echo (int) $summary['completed']; ?> percobaan selesai · <?php echo (int) $summary['active']; ?> masih berjalan</p><p class="small text-secondary mb-0">Ringkasan mencakup semua halaman. Percobaan berulang dihitung terpisah; ini bukan jumlah kursus yang dikuasai. Jawaban dan pembahasan baru tersedia setelah anak menyelesaikan latihan. Orang tua tidak dapat mengubah atau menyelesaikan jawaban anak.</p></div>
	<?php if ( ! $attempts): ?><div class="empty-card"><span class="empty-icon" aria-hidden="true"><i class="bi bi-journal-heart"></i></span><h2 class="h4">Belum ada latihan mandiri</h2><p class="text-secondary">Ajak anak memilih kursus yang menarik dan mencoba latihan saat siap.</p></div><?php endif; ?>
	<div class="row g-3">
		<?php foreach ($attempts as $attempt): ?>
			<div class="col-md-6"><article class="feature-card h-100 practice-question"><h2 class="h5"><?php echo html_escape($attempt['title']); ?></h2><p class="small text-secondary">Dimulai <?php echo html_escape(study_format_datetime($attempt['started_at'])); ?></p>
				<?php if ($attempt['status'] === 'graded'): ?>
					<p><?php echo (int) $attempt['correct_count']; ?> dari <?php echo (int) $attempt['question_count']; ?> benar · <?php echo html_escape(str_replace('.', ',', $attempt['score'])); ?>%</p><p class="small text-secondary">Selesai <?php echo html_escape(study_format_datetime($attempt['submitted_at'])); ?></p><p class="small"><?php echo (int) $attempt['correct_count'] === (int) $attempt['question_count'] ? 'Apresiasi usahanya dan ajak anak menjelaskan cara menemukan jawaban.' : 'Ajak anak memilih satu soal untuk dibahas bersama, tanpa terburu-buru.'; ?></p><a class="btn btn-outline-primary rounded-pill" href="<?php echo site_url('keluarga/anak/'.$child['id'].'/latihan/'.$attempt['id']); ?>">Lihat hasil dan pembahasan</a>
				<?php else: ?><p class="text-secondary">Latihan masih berjalan. Beri anak kesempatan melanjutkan saat siap.</p><?php endif; ?>
			</article></div>
		<?php endforeach; ?>
	</div>
	<?php $pagination_base = 'keluarga/anak/'.$child['id'].'/latihan'; $this->load->view('practice/pagination', compact('pagination_base', 'page', 'page_count')); ?>
</section>
