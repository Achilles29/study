<?php
$show_lesson_progress = $current_user && isset($lesson_progress_map) && is_array($lesson_progress_map);
$lesson_progress_failed = $current_user && isset($lesson_progress_map) && $lesson_progress_map === FALSE;
?>
<section class="container page-section">
	<a class="back-link" href="<?php echo site_url('kursus'); ?>">
		<i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke katalog
	</a>
	<div class="row g-4 mt-2">
		<div class="col-lg-8">
			<p class="eyebrow">
				<?php echo html_escape($course['category_name'] ?: 'Kursus belajar'); ?>
				· <?php echo html_escape($target_group_label); ?>
			</p>
			<h1 class="display-6 fw-bold"><?php echo html_escape($course['title']); ?></h1>
			<p class="lead text-secondary"><?php echo html_escape($course['summary']); ?></p>
			<?php $age_range_label = study_age_range_label($course['age_min'], $course['age_max']); if ($age_range_label !== ''): ?><p class="small text-secondary"><strong>Sasaran usia:</strong> <?php echo html_escape($age_range_label); ?></p><?php endif; ?>

			<div class="feature-card mb-4">
				<h2 class="h5">Tujuan belajar</h2>
				<p class="mb-0"><?php echo nl2br(html_escape($course['learning_objectives'])); ?></p>
			</div>
			<?php if (trim((string) $course['prerequisites']) !== ''): ?><div class="feature-card mb-4"><h2 class="h5">Persiapan belajar</h2><p class="mb-0"><?php echo nl2br(html_escape($course['prerequisites'])); ?></p></div><?php endif; ?>

			<div class="feature-card">
				<h2 class="h5">Isi kursus</h2>
				<?php if ($lessons && $lesson_progress_failed): ?>
					<div class="alert alert-warning" role="status"><p class="mb-2">Progres belajarmu belum dapat dimuat. Materi yang terbuka tetap bisa dibaca; progres tersimpan tidak dihapus.</p><a class="alert-link" href="<?php echo html_escape(site_url('kursus/'.$course['slug'])); ?>">Muat ulang progres</a></div>
				<?php elseif ($lessons && $show_lesson_progress): ?>
					<p class="small text-secondary">Progres akunmu untuk pelajaran yang terbit. Materi terkunci tetap memerlukan akses.</p>
				<?php endif; ?>
				<?php if ($lessons): ?>
					<ol class="list-group list-group-numbered list-group-flush">
						<?php foreach ($lessons as $lesson):
							$lesson_status = $show_lesson_progress ? (isset($lesson_progress_map[$lesson['id']]) ? $lesson_progress_map[$lesson['id']] : 'not_started') : NULL;
							$lesson_action = (int) $lesson['is_preview'] === 1 && ! $can_read_course ? 'Coba pratinjau' : 'Baca';
							$lesson_path = 'materi/'.$lesson['slug'];
							if ($show_lesson_progress)
							{
								if ($lesson_status === 'in_progress') { $lesson_action = 'Lanjutkan'; $lesson_path .= '?lanjut=1#reading-material'; }
								elseif ($lesson_status === 'completed') { $lesson_action = 'Baca ulang'; }
								elseif ($lesson_status === 'not_started') { $lesson_action = 'Mulai belajar'; }
							}
						?>
							<li class="list-group-item px-0" data-lesson-id="<?php echo (int) $lesson['id']; ?>">
								<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3">
									<div class="min-w-0 text-break">
										<strong><?php echo html_escape($lesson['title']); ?></strong>
										<div class="small text-secondary">
											<?php echo html_escape($lesson['section_title']); ?>
											<?php if ($lesson['estimated_minutes']): ?>
												· <?php echo (int) $lesson['estimated_minutes']; ?> menit
											<?php endif; ?>
										</div>
										<?php if ($show_lesson_progress): ?><span class="badge rounded-pill text-wrap text-start mt-2 <?php echo $lesson_status === 'completed' ? 'text-bg-success' : 'text-bg-light'; ?>" data-lesson-progress="<?php echo html_escape($lesson_status); ?>"><?php if ($lesson_status === 'completed'): ?><i class="bi bi-check-circle me-1" aria-hidden="true"></i><?php endif; ?><?php echo html_escape(study_lesson_progress_label($lesson_status)); ?></span><?php endif; ?>
									</div>
									<?php if ($can_read_course || (int) $lesson['is_preview'] === 1): ?>
										<a class="btn btn-sm btn-outline-primary rounded-pill flex-shrink-0" href="<?php echo html_escape(site_url($lesson_path)); ?>">
											<?php echo html_escape($lesson_action); ?><span class="visually-hidden">: <?php echo html_escape($lesson['title']); ?></span>
										</a>
									<?php else: ?>
										<span class="badge text-bg-light flex-shrink-0">Terkunci</span>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php else: ?>
					<p class="text-secondary mb-0">Pelajaran untuk kursus ini sedang disiapkan.</p>
				<?php endif; ?>
			</div>
		</div>

		<aside class="col-lg-4">
			<?php if ($question_count > 0): ?><section class="feature-card mb-4"><h2 class="h5">Coba pemahamanmu</h2><p><?php echo (int) $question_count; ?> soal pilihan ganda dengan pembahasan. Simpan sementara atau ulangi latihan untuk belajar lagi.</p><?php if ($can_read_course): ?><a class="btn btn-primary rounded-pill" href="<?php echo site_url('latihan/kursus/'.$course['id']); ?>"><?php echo $current_user ? 'Buka latihan' : 'Masuk untuk berlatih'; ?></a><?php else: ?><p class="text-secondary mb-0">Latihan mengikuti akses kursus ini.</p><?php endif; ?></section><?php endif; ?>
			<div class="feature-card position-sticky" style="top:1rem">
				<span class="feature-icon feature-purple mb-3"><i class="bi bi-book-half" aria-hidden="true"></i></span>
				<h2 class="h5">Mulai belajar</h2>
				<?php if ((int) $course['is_free'] === 1): ?>
					<p>Kursus ini dapat dibaca gratis.</p>
				<?php elseif ($can_read_course): ?>
					<p>Kursus ini tersedia untuk akunmu.</p>
				<?php elseif ($has_preview_lessons): ?>
					<p>Sebagian materi tersedia sebagai pratinjau. Akses paket belum dibuka.</p>
				<?php else: ?>
					<p>Belum ada materi pratinjau untuk kursus ini. Akses paket belum dibuka pada tahap awal.</p>
				<?php endif; ?>
				<p class="small text-secondary mb-0">
					<?php echo (int) count($lessons); ?> pelajaran tersedia
					<?php if ($course['estimated_minutes']): ?>
						· sekitar <?php echo (int) $course['estimated_minutes']; ?> menit
					<?php endif; ?>
				</p>
			</div>
		</aside>
	</div>
</section>
