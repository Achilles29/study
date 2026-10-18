<?php $is_content_preview = ! empty($is_content_preview); $lesson_return_url = $is_content_preview ? site_url('kelola/kursus/'.$lesson['course_id']) : site_url('kursus/'.$lesson['course_slug']); ?>
<article class="container page-section course-workspace">
	<?php if ($is_content_preview): ?>
		<section class="feature-card mb-4" aria-labelledby="preview-heading">
			<p class="eyebrow mb-2">Pratinjau pengelola</p>
			<h2 class="h4" id="preview-heading">Lihat materi seperti peserta</h2>
			<p class="text-secondary mb-2">Gunakan pilihan tema untuk memeriksa tampilan sesuai kelompok belajar. Progres dan jawaban kartu tidak disimpan dalam pratinjau.</p>
			<p class="small mb-3">Kursus: <?php echo html_escape(study_course_status_label($preview_course['status'])); ?> · Versi <?php echo (int) $preview_course['content_version']; ?></p>
			<form method="get" action="<?php echo site_url('kelola/kursus/'.$lesson['course_id'].'/pelajaran/'.$lesson['id'].'/pratinjau'); ?>" class="d-flex flex-column flex-sm-row align-items-sm-end gap-2">
				<div class="flex-grow-1"><label for="preview-theme" class="form-label">Tema peserta</label><select class="form-select" name="tema" id="preview-theme"><?php foreach ($preview_theme_options as $theme_option): ?><option value="<?php echo html_escape($theme_option); ?>"<?php echo $preview_theme === $theme_option ? ' selected' : ''; ?>><?php echo html_escape(study_target_group_label($theme_option)); ?></option><?php endforeach; ?></select></div>
				<button class="btn btn-primary rounded-pill" type="submit">Terapkan tema</button>
			</form>
		</section>
	<?php endif; ?>
	<a class="back-link" href="<?php echo $lesson_return_url; ?>">
		<i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke <?php echo html_escape($lesson['course_title']); ?>
	</a>
	<div class="row justify-content-center mt-3">
		<div class="col-lg-9 col-xl-8">
			<p class="eyebrow"><?php echo html_escape($lesson['section_title']); ?> · <?php echo (int) $lesson['estimated_minutes']; ?> menit</p>
			<h1 class="display-6 fw-bold mb-4"><?php echo html_escape($lesson['title']); ?></h1>
			<?php if ($lesson_progress && $lesson_progress['status'] === 'in_progress' && (int) $lesson_progress['last_position'] > 0): ?>
				<div class="alert alert-info d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2" role="status">
					<span>Posisi terakhir tersimpan: <?php echo (int) round((int) $lesson_progress['last_position'] / 100); ?>%.</span>
					<a class="btn btn-sm btn-outline-primary rounded-pill align-self-start align-self-sm-center" href="<?php echo site_url('materi/'.$lesson['slug'].'?lanjut=1#reading-material'); ?>">Lanjutkan membaca</a>
				</div>
			<?php endif; ?>
			<div id="reading-material" class="feature-card lesson-reading" data-saved-position="<?php echo $lesson_progress ? (int) $lesson_progress['last_position'] : 0; ?>">
				<?php if ( ! empty($lesson_image)): $lesson_image_path = $is_content_preview ? 'kelola/kursus/'.(int) $lesson['course_id'].'/pelajaran/'.(int) $lesson['id'].'/gambar/pratinjau' : 'materi/'.rawurlencode($lesson['slug']).'/gambar'; ?>
					<figure class="lesson-image mb-4">
						<img src="<?php echo html_escape(site_url($lesson_image_path)); ?>" alt="<?php echo html_escape($lesson_image['alt_text']); ?>" width="<?php echo max(1, (int) $lesson_image['width']); ?>" height="<?php echo max(1, (int) $lesson_image['height']); ?>" decoding="async">
						<figcaption class="small mt-3">
							<?php if ($lesson_image['caption'] !== ''): ?><p><?php echo nl2br(html_escape($lesson_image['caption'])); ?></p><?php endif; ?>
							<p><strong>Deskripsi gambar:</strong> <?php echo html_escape($lesson_image['alt_text']); ?></p>
							<details class="mt-2"<?php echo $is_content_preview ? ' open' : ''; ?>><summary>Sumber dan hak penggunaan gambar</summary><p class="mt-2"><?php echo nl2br(html_escape($lesson_image['source_note'])); ?></p><p><?php echo html_escape(study_rights_status_label($lesson_image['rights_status'])); ?></p></details>
						</figcaption>
					</figure>
				<?php endif; ?>
				<div class="lesson-content"><?php echo study_render_lesson_text($lesson['body_text']); ?></div>
			</div>
			<?php if (! empty($flashcard_deck) && $flashcards): ?>
				<?php $known_flashcards = 0; foreach ($flashcards as $flashcard) { if ($flashcard['learner_status'] === 'known') { $known_flashcards++; } } ?>
				<section class="flashcard-section mt-5" id="kartu-belajar" aria-labelledby="flashcard-heading">
					<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-end gap-2 mb-3">
						<div><p class="eyebrow mb-2"><i class="bi bi-card-text" aria-hidden="true"></i> Latihan ingatan</p><h2 class="h3 mb-1" id="flashcard-heading"><?php echo html_escape($flashcard_deck['title']); ?></h2><?php if ($flashcard_deck['description']): ?><p class="text-secondary mb-0"><?php echo html_escape($flashcard_deck['description']); ?></p><?php endif; ?></div>
						<?php if ($current_user && ! $is_content_preview): ?><span class="badge rounded-pill text-bg-light p-2"><?php echo $known_flashcards; ?> dari <?php echo count($flashcards); ?> dikuasai</span><?php endif; ?>
					</div>
					<div class="row g-3">
						<?php if ( ! $is_content_preview && ! empty($can_play_matching)): ?><div class="col-12"><a class="btn btn-primary rounded-pill" href="<?php echo site_url('permainan/pasangan/'.$lesson['slug']); ?>"><i class="bi bi-puzzle me-2" aria-hidden="true"></i>Main Cocokkan Pasangan</a><p class="small text-secondary mt-2">Latihan ringan tanpa timer. Progres permainan tidak mengubah status penguasaan kartu.</p></div><?php endif; ?>
						<?php foreach ($flashcards as $index => $flashcard): ?>
							<div class="col-12 col-md-6">
								<article class="flashcard-card h-100">
									<p class="small text-secondary mb-2">Kartu <?php echo (int) ($index + 1); ?><?php if ($flashcard['learner_status'] === 'known'): ?> · <span class="text-success fw-semibold">Sudah dikuasai</span><?php elseif ($flashcard['learner_status'] === 'learning'): ?> · <span class="fw-semibold">Perlu diulang</span><?php endif; ?></p>
									<h3 class="h5 mb-3"><?php echo nl2br(html_escape($flashcard['front_text'])); ?></h3>
									<?php if ($flashcard['hint_text']): ?><p class="flashcard-hint small mb-3"><strong>Petunjuk:</strong> <?php echo nl2br(html_escape($flashcard['hint_text'])); ?></p><?php endif; ?>
									<details class="flashcard-answer"><summary>Lihat sisi belakang</summary><div class="pt-3"><?php echo nl2br(html_escape($flashcard['back_text'])); ?></div></details>
									<?php if ($current_user && ! $is_content_preview): ?><div class="d-flex flex-wrap gap-2 mt-3">
										<?php echo form_open('materi/'.$lesson['slug'].'/flashcard/'.$flashcard['id'], array('class' => 'm-0')); ?><input type="hidden" name="status" value="known"><button class="btn btn-success btn-sm rounded-pill" type="submit"<?php echo $flashcard['learner_status'] === 'known' ? ' disabled' : ''; ?>>Saya sudah paham</button><?php echo form_close(); ?>
										<?php echo form_open('materi/'.$lesson['slug'].'/flashcard/'.$flashcard['id'], array('class' => 'm-0')); ?><input type="hidden" name="status" value="learning"><button class="btn btn-outline-primary btn-sm rounded-pill" type="submit"<?php echo $flashcard['learner_status'] === 'learning' ? ' disabled' : ''; ?>>Perlu diulang</button><?php echo form_close(); ?>
									</div><?php elseif ( ! $is_content_preview): ?><p class="small text-secondary mt-3 mb-0"><a href="<?php echo site_url('masuk'); ?>">Masuk</a> untuk menyimpan kartu yang sudah kamu kuasai.</p><?php endif; ?>
								</article>
							</div>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
			<?php if ($is_content_preview && ! empty($flashcard_deck) && ! $flashcards): ?><div class="alert alert-warning mt-4" role="status">Set kartu belajar belum memiliki kartu. Lengkapi isinya sebelum mengajukan review.</div><?php endif; ?>
			<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mt-4">
				<?php if ($next_lesson): ?>
					<a class="btn btn-outline-primary rounded-pill" href="<?php echo site_url('materi/'.$next_lesson['slug']); ?>">Pelajaran berikutnya <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
				<?php else: ?>
					<a class="btn btn-outline-primary rounded-pill" href="<?php echo $lesson_return_url; ?>">Daftar pelajaran</a>
				<?php endif; ?>
				<?php if ($current_user && ! $is_content_preview): ?>
					<div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
						<?php if ( ! $lesson_progress || $lesson_progress['status'] !== 'completed'): ?>
							<?php echo form_open('materi/'.$lesson['slug'].'/posisi', array('class' => 'm-0', 'id' => 'save-reading-position')); ?>
								<input type="hidden" name="position" value="<?php echo $lesson_progress ? (int) $lesson_progress['last_position'] : 0; ?>">
								<button class="btn btn-outline-primary rounded-pill" type="submit" hidden>Simpan posisi baca</button>
							<?php echo form_close(); ?>
							<span id="reading-position-status" class="small text-secondary" role="status" aria-live="polite">Posisi baca tersimpan otomatis saat kamu membaca.</span>
							<a id="reading-position-recovery" class="btn btn-outline-primary rounded-pill" href="<?php echo html_escape(site_url('materi/'.rawurlencode($lesson['slug']))); ?>" hidden>Periksa keadaan terbaru</a>
							<noscript><style>#save-reading-position button[hidden]{display:inline-block!important}</style><span class="small text-secondary">JavaScript nonaktif. Tombol ini mengirim posisi tersimpan terakhir.</span></noscript>
							<?php echo form_open('materi/'.$lesson['slug'].'/selesai', array('class' => 'm-0', 'id' => 'complete-reading-lesson')); ?>
								<button class="btn btn-primary rounded-pill" type="submit">Tandai selesai</button>
							<?php echo form_close(); ?>
						<?php else: ?>
							<span class="badge rounded-pill text-bg-success p-2"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Pelajaran selesai</span>
						<?php endif; ?>
					</div>
				<?php elseif ( ! $is_content_preview): ?>
					<span class="small text-secondary">Masuk untuk menyimpan progres belajarmu.</span>
				<?php endif; ?>
			</div>
		</div>
	</div>
</article>
