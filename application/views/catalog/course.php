<?php
$show_lesson_progress = $current_user && isset($lesson_progress_map) && is_array($lesson_progress_map);
$lesson_progress_failed = $current_user && isset($lesson_progress_map) && $lesson_progress_map === FALSE;
$chapters = array();
foreach ($lessons as $chapter_lesson)
{
	$chapter_id = (int) $chapter_lesson['section_id'];
	if ( ! isset($chapters[$chapter_id])) { $chapters[$chapter_id] = array('title' => $chapter_lesson['section_title'], 'lessons' => array()); }
	$chapters[$chapter_id]['lessons'][] = $chapter_lesson;
}
?>
<section class="container page-section course-workspace">
	<?php $this->load->view('public_learning/breadcrumbs'); ?>
	<a class="back-link" href="<?php echo site_url('kursus'); ?>">
		<i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke katalog
	</a>
	<div class="row g-4 mt-2">
		<div class="col-lg-8">
			<p class="eyebrow">
				<?php echo html_escape($course['category_name'] ?: 'Kursus belajar'); ?>
				· <?php echo html_escape($target_group_label); ?> · <?php echo html_escape(study_grade_label($course['grade_id'])); ?>
			</p>
			<h1 class="display-6 fw-bold"><?php echo html_escape($course['title']); ?></h1>
			<?php if (($course['category_slug'] ?? '') === 'koding-ka'): ?><p class="badge rounded-pill text-bg-info mb-3">Mata pelajaran pilihan</p><?php elseif (in_array(study_grade_code($course['grade_id']), array('sma_11', 'sma_12'), TRUE) && in_array($course['category_slug'] ?? '', array('biologi', 'fisika', 'kimia', 'geografi', 'ekonomi', 'sosiologi', 'tik', 'matematika-lanjut'), TRUE)): ?><p class="badge rounded-pill text-bg-info mb-3">Mata pelajaran pilihan SMA</p><?php endif; ?>
			<p class="lead text-secondary"><?php echo html_escape($course['summary']); ?></p>
			<div class="d-flex flex-wrap gap-2 mb-4"><a class="btn btn-teal" href="#isi-kursus">Baca materi gratis</a><?php if ($question_count > 0 && $can_read_course): ?><a class="btn btn-primary" href="<?php echo site_url('latihan/kursus/'.(int)$course['id']); ?>"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Latihan kursus · <?php echo (int)$question_count; ?> soal</a><?php endif; ?></div>
			<?php $age_range_label = study_age_range_label($course['age_min'], $course['age_max']); if ($age_range_label !== ''): ?><p class="small text-secondary"><strong>Sasaran usia:</strong> <?php echo html_escape($age_range_label); ?></p><?php endif; ?>

			<div class="feature-card mb-4">
				<h2 class="h5">Tujuan belajar</h2>
				<p class="mb-0"><?php echo nl2br(html_escape((string) $course['learning_objectives'])); ?></p>
			</div>
			<?php if (trim((string) $course['prerequisites']) !== ''): ?><div class="feature-card mb-4"><h2 class="h5">Persiapan belajar</h2><p class="mb-0"><?php echo nl2br(html_escape($course['prerequisites'])); ?></p></div><?php endif; ?>

			<?php if(!empty($public_context)): ?><p><a class="btn btn-teal" href="<?php echo site_url($public_context['public_path']); ?>">Contoh soal <?php echo html_escape($public_context['subject_label']); ?> kelas <?php echo (int)$public_context['grade']; ?> dan pembahasan</a></p><?php endif; ?>
			<?php $this->load->view('public_learning/source'); ?>
			<div class="feature-card mt-4">
				<h2 class="h5" id="isi-kursus">Isi kursus</h2>
				<?php if ($chapters): ?><p class="small text-secondary"><?php echo count($chapters); ?> bab · <?php echo count($lessons); ?> sesi. Pilih bab untuk langsung melihat daftar materinya.</p><nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Pilih bab"><?php $chapter_number = 0; foreach ($chapters as $chapter_id => $chapter): $chapter_number++; ?><a class="btn btn-sm btn-outline-primary rounded-pill" href="#bab-<?php echo $chapter_id; ?>">Bab <?php echo $chapter_number; ?></a><?php endforeach; ?></nav><?php endif; ?>
				<?php if ($lessons && $lesson_progress_failed): ?>
					<div class="alert alert-warning" role="status"><p class="mb-2">Progres belajarmu belum dapat dimuat. Materi yang terbuka tetap bisa dibaca; progres tersimpan tidak dihapus.</p><a class="alert-link" href="<?php echo html_escape(site_url('kursus/'.$course['slug'])); ?>">Muat ulang progres</a></div>
				<?php elseif ($lessons && $show_lesson_progress): ?>
					<p class="small text-secondary">Progres akunmu untuk pelajaran yang terbit. Materi terkunci tetap memerlukan akses.</p>
				<?php endif; ?>
				<?php if ($lessons): ?>
					<?php $chapter_number = 0; foreach ($chapters as $chapter_id => $chapter): $chapter_number++; ?>
					<section class="mb-4" id="bab-<?php echo $chapter_id; ?>" aria-labelledby="bab-judul-<?php echo $chapter_id; ?>">
						<h3 class="h6 fw-bold mb-2" id="bab-judul-<?php echo $chapter_id; ?>"><?php echo html_escape($chapter['title']); ?></h3>
						<ol class="list-group list-group-numbered list-group-flush">
						<?php foreach ($chapter['lessons'] as $session_number => $lesson):
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
										<span class="small fw-semibold text-primary d-block mb-1">Sesi <?php echo (int) $session_number + 1; ?></span>
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
					</section>
					<?php endforeach; ?>
				<?php else: ?>
					<p class="text-secondary mb-0">Pelajaran untuk kursus ini sedang disiapkan.</p>
				<?php endif; ?>
			</div>
		</div>

		<aside class="col-lg-4">
			<?php if ($question_count > 0): ?><section class="feature-card mb-4"><h2 class="h5">Latihan soal kursus</h2><p><?php echo (int) $question_count; ?> soal pilihan ganda dengan pembahasan untuk materi <?php echo html_escape($course['title']); ?>. Kerjakan latihan soal online, simpan sementara, atau ulangi untuk belajar lagi.</p><?php if ($can_read_course): ?><a class="btn btn-primary rounded-pill" href="<?php echo site_url('latihan/kursus/'.$course['id']); ?>"><?php echo $current_user ? 'Buka latihan' : 'Masuk untuk berlatih'; ?></a><?php else: ?><p class="text-secondary mb-0">Latihan mengikuti akses kursus ini.</p><?php endif; ?></section><?php endif; ?>
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
