<?php require_once dirname(__DIR__, 2).'/libraries/Question_media.php'; ?>
<section class="container page-section" id="parent-practice-live" data-status-url="<?php echo site_url('keluarga/anak/'.$child['id'].'/latihan/'.$attempt['id'].'/live/status'); ?>">
	<a class="back-link" href="<?php echo site_url('keluarga/anak/'.$child['id'].'/latihan'); ?>">Kembali ke latihan anak</a>
	<div class="feature-card mt-3 mb-4">
		<p class="eyebrow">Pantau latihan · <?php echo html_escape($child['display_name']); ?></p>
		<h1 class="h2"><?php echo html_escape($attempt['title']); ?></h1>
		<p class="mb-2" id="parent-live-summary" role="status" aria-live="polite">Memuat jawaban tersimpan…</p>
		<p class="small text-secondary mb-0" id="parent-live-presence">Memeriksa aktivitas terakhir…</p>
		<p class="small text-secondary mt-2 mb-0">Pilihan muncul setelah tersimpan di server, diperbarui kira-kira setiap 3 detik. Waktu jawaban adalah perkiraan dari soal dibuka sampai pilihan tersimpan; jeda jaringan dan perpindahan tab dapat memengaruhinya. Kunci dan pembahasan tetap tersembunyi selama latihan.</p>
		<a class="btn btn-outline-primary mt-3" id="parent-live-result" href="<?php echo site_url('keluarga/anak/'.$child['id'].'/latihan/'.$attempt['id']); ?>" hidden>Lihat hasil setelah selesai</a>
	</div>
	<div class="row g-3">
	<?php foreach ($questions as $index => $question):
		$layout = $question['media_layout'];
		$gallery = $layout['gallery'] ?? ($question['image_count'] ? range(0, $question['image_count'] - 1) : array());
		$image_url = static function($i) use ($attempt, $question) { return site_url('latihan/percobaan/'.$attempt['id'].'/gambar/'.$question['id'].($i ? '/'.$i : '')); };
	?>
		<div class="col-lg-6"><article class="feature-card h-100 practice-question" data-live-question="<?php echo (int)$question['id']; ?>">
			<div class="d-flex justify-content-between align-items-start gap-2"><h2 class="h5">Soal <?php echo $index + 1; ?></h2><span class="badge text-bg-primary" data-live-current hidden>Sedang dibuka</span></div>
			<p class="fw-semibold question-rich-text"><?php echo Question_media::render($question['prompt'], $layout, $image_url, 'soal '.($index + 1)); ?></p>
			<?php if ($gallery): ?><div class="row g-2 mb-3"><?php foreach ($gallery as $image_index): ?><div class="col-6"><img class="img-fluid rounded bank-question-image" src="<?php echo $image_url($image_index); ?>" alt="Gambar <?php echo $image_index + 1; ?> pendamping soal <?php echo $index + 1; ?>" loading="lazy"></div><?php endforeach; ?></div><?php endif; ?>
			<?php foreach ($question['options'] as $option): ?><div class="practice-option mb-2" data-live-option="<?php echo (int)$option['id']; ?>"><span class="question-rich-text"><?php echo Question_media::render($option['option_text'], $layout, $image_url, 'pilihan jawaban'); ?></span> <span class="badge text-bg-info" data-live-selected hidden>Pilihan anak</span></div><?php endforeach; ?>
			<p class="small text-secondary mt-3 mb-0" data-live-timing>Belum dijawab atau belum tersimpan.</p>
		</article></div>
	<?php endforeach; ?>
	</div>
</section>
