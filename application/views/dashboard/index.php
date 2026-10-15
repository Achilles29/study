<?php
$has_learning_activity = isset($progress) && ($progress['completed_lessons'] > 0 || $progress['in_progress_lessons'] > 0);
$has_resume_lessons = $current_user['account_kind'] !== 'parent' && ! empty($resume_lessons);
$resume_unavailable = $current_user['account_kind'] !== 'parent' && isset($resume_lessons) && $resume_lessons === FALSE;
?>
<section class="container page-section">
	<div class="dashboard-welcome mb-4">
		<div>
			<p class="eyebrow">Ruang belajarmu</p>
			<h1 class="h2 mb-2">Halo, <?php echo html_escape($current_user['display_name']); ?>!</h1>
			<p class="text-secondary mb-0">Ayo pilih satu langkah kecil untuk hari ini.</p>
		</div>
		<span class="welcome-mark" aria-hidden="true"><i class="bi bi-sun"></i></span>
	</div>

	<?php if ($has_resume_lessons || $resume_unavailable): ?>
		<section id="resume-learning" class="mb-5" aria-labelledby="resume-learning-heading">
			<div class="page-heading mb-3"><div><p class="eyebrow">Satu langkah lagi</p><h2 class="h4 mb-1" id="resume-learning-heading">Lanjutkan belajar</h2><p class="text-secondary mb-0">Materi yang belum selesai dan masih terbuka untukmu.</p></div></div>
			<?php if ($resume_unavailable): ?>
				<div class="alert alert-warning" role="status"><p class="mb-2">Daftar lanjut belajar belum dapat dimuat. Progres yang sudah tersimpan tidak dihapus.</p><a href="<?php echo site_url('beranda'); ?>" class="alert-link">Muat ulang beranda</a></div>
			<?php else: ?>
				<p class="small text-secondary">Hingga tiga materi, diurutkan dari progres yang terakhir disimpan.</p>
				<div class="row g-3">
					<?php foreach ($resume_lessons as $resume_lesson): ?>
						<div class="col-md-6 col-xl-4"><article class="feature-card h-100 d-flex flex-column" data-lesson-id="<?php echo (int) $resume_lesson['lesson_id']; ?>">
							<span class="stat-icon stat-purple mb-3" aria-hidden="true"><i class="bi bi-bookmark-heart"></i></span>
							<p class="small text-secondary mb-2"><?php echo html_escape($resume_lesson['course_title']); ?></p>
							<h3 class="h5"><?php echo html_escape($resume_lesson['lesson_title']); ?></h3>
							<p class="small text-secondary mb-3">Progres disimpan: <?php echo html_escape(study_format_datetime($resume_lesson['last_activity_at'])); ?></p>
							<a class="btn btn-primary rounded-pill mt-auto align-self-start" href="<?php echo html_escape(site_url('materi/'.$resume_lesson['lesson_slug'].'?lanjut=1#reading-material')); ?>">Lanjutkan materi<span class="visually-hidden">: <?php echo html_escape($resume_lesson['lesson_title']); ?></span></a>
						</article></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<p class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary rounded-pill" href="<?php echo site_url('latihan'); ?>">Riwayat latihan saya</a><a class="btn btn-outline-primary rounded-pill" href="<?php echo site_url('permainan/riwayat'); ?>">Riwayat permainan saya</a></p>
	<p><a href="<?php echo site_url('akun/keamanan'); ?>">Keamanan akun saya</a></p>
	<?php if ($current_user['account_kind'] === 'parent'): ?>
		<p><a class="btn btn-outline-primary rounded-pill" href="<?php echo site_url('keluarga/latihan'); ?>">Susun latihan anak</a></p>
		<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
			<div><h2 class="h4 mb-1">Keluargamu</h2><p class="text-secondary mb-0">Akun anak yang terhubung dengan akunmu.</p></div>
			<a class="btn btn-primary rounded-pill px-4" href="<?php echo site_url('keluarga/anak/tambah'); ?>"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Tambah anak</a>
		</div>
		<?php if ($children): ?>
			<div class="row g-3 mb-5">
				<?php foreach ($children as $child): $stage_labels = array('early_childhood' => 'Usia dini', 'primary' => 'Sekolah dasar', 'secondary' => 'Remaja'); $child_courses = isset($child_course_progress[$child['id']]) ? $child_course_progress[$child['id']] : array(); $child_flashcards = isset($child_flashcard_progress[$child['id']]) ? $child_flashcard_progress[$child['id']] : array(); ?>
					<div class="col-md-6 col-xl-4"><article class="child-card">
						<span class="child-avatar" aria-hidden="true"><i class="bi bi-person-heart"></i></span>
						<div class="flex-grow-1 min-w-0"><h3 class="h5 mb-1"><?php echo html_escape($child['display_name']); ?></h3><p class="text-secondary small mb-0">Nama pengguna: <?php echo html_escape($child['username']); ?></p><p class="small mb-0"><?php echo html_escape(isset($stage_labels[$child['learning_stage']]) ? $stage_labels[$child['learning_stage']] : 'Kelompok belum dipilih'); ?></p><p class="small mb-0"><?php echo (int) $child['completed_lessons']; ?> pelajaran selesai</p>
							<?php if ($child_courses): ?><ul class="list-unstyled small mt-2 mb-0" aria-label="Progres kursus <?php echo html_escape($child['display_name']); ?>"><?php foreach (array_slice($child_courses, 0, 3) as $child_course): ?><li class="mb-1"><span class="d-block text-truncate"><?php echo html_escape($child_course['course_title']); ?></span><span class="text-secondary"><?php echo (int) $child_course['completed_lessons']; ?> selesai<?php if ((int) $child_course['in_progress_lessons'] > 0): ?> · <?php echo (int) $child_course['in_progress_lessons']; ?> berjalan<?php endif; ?></span></li><?php endforeach; ?></ul><?php if (count($child_courses) > 3): ?><p class="small text-secondary mt-1 mb-0">dan <?php echo count($child_courses) - 3; ?> kursus lainnya</p><?php endif; ?><?php else: ?><p class="small text-secondary mt-2 mb-0">Belum ada progres per kursus.</p><?php endif; ?>
							<?php if ($child_flashcards): ?><ul class="list-unstyled small mt-2 mb-0" aria-label="Progres kartu belajar <?php echo html_escape($child['display_name']); ?>"><?php foreach (array_slice($child_flashcards, 0, 3) as $flashcard_progress): ?><li class="mb-1"><span class="d-block text-truncate"><?php echo html_escape($flashcard_progress['deck_title']); ?> · <?php echo html_escape($flashcard_progress['course_title']); ?></span><span class="text-secondary"><?php echo (int) $flashcard_progress['known_cards']; ?> dari <?php echo (int) $flashcard_progress['total_cards']; ?> dikuasai · <?php echo (int) $flashcard_progress['reviewed_cards']; ?> ditinjau</span></li><?php endforeach; ?></ul><?php if (count($child_flashcards) > 3): ?><p class="small text-secondary mt-1 mb-0">dan <?php echo count($child_flashcards) - 3; ?> set kartu lainnya</p><?php endif; ?><?php endif; ?>
							<p class="mt-3 mb-0"><a class="btn btn-sm btn-outline-primary rounded-pill" href="<?php echo site_url('keluarga/anak/'.$child['id'].'/latihan'); ?>">Laporan latihan <span class="visually-hidden"><?php echo html_escape($child['display_name']); ?></span></a></p>
							<p class="mt-2 mb-0"><a class="btn btn-sm btn-outline-primary rounded-pill" href="<?php echo site_url('keluarga/anak/'.$child['id'].'/permainan'); ?>">Laporan permainan <span class="visually-hidden"><?php echo html_escape($child['display_name']); ?></span></a></p>
						</div>
						<span class="badge text-bg-success ms-auto">Terhubung</span>
					</article></div>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<div class="empty-card mb-5"><span class="empty-icon"><i class="bi bi-people" aria-hidden="true"></i></span><h3 class="h5">Belum ada akun anak</h3><p class="text-secondary">Tambahkan akun anak agar nanti kamu dapat melihat perjalanan belajarnya di sini.</p><a class="btn btn-outline-primary rounded-pill" href="<?php echo site_url('keluarga/anak/tambah'); ?>">Tambahkan akun anak</a></div>
		<?php endif; ?>
	<?php elseif ($current_user['account_kind'] === 'child'): ?>
		<p><a class="btn btn-primary rounded-pill" href="<?php echo site_url('latihan/keluarga'); ?>">Latihan dari keluarga</a></p>
		<?php if ( ! $has_resume_lessons && ! $resume_unavailable): ?>
		<div class="empty-card mb-5">
			<span class="empty-icon"><i class="bi bi-book-half" aria-hidden="true"></i></span>
			<h2 class="h4"><?php echo $has_learning_activity ? 'Belajarmu terus berkembang' : 'Ayo temukan hal baru hari ini'; ?></h2>
			<?php if ($has_learning_activity): ?>
				<p class="text-secondary">Kamu sudah menyelesaikan <?php echo (int) $progress['completed_lessons']; ?> pelajaran. Lihat perkembanganmu per kursus dan pilih materi yang ingin kamu pelajari berikutnya.</p>
			<?php else: ?>
				<p class="text-secondary">Pilih kursus yang menarik untuk mulai belajar. Materi yang terbuka gratis bisa langsung kamu baca.</p>
			<?php endif; ?>
			<a href="<?php echo site_url('kursus'); ?>" class="btn btn-primary rounded-pill px-4">Jelajahi kursus</a>
		</div>
		<?php else: ?>
			<p class="mb-5"><a href="<?php echo site_url('kursus'); ?>">Jelajahi kursus lain</a></p>
		<?php endif; ?>
	<?php else: ?>
		<?php if ( ! $has_resume_lessons && ! $resume_unavailable): ?>
		<div class="empty-card mb-5">
			<span class="empty-icon"><i class="bi bi-journal-richtext" aria-hidden="true"></i></span>
			<h2 class="h4"><?php echo $has_learning_activity ? 'Progres belajarmu tersimpan' : 'Pilih pelajaran pertamamu'; ?></h2>
			<?php if ($has_learning_activity): ?>
				<p class="text-secondary">Kamu sudah menyelesaikan <?php echo (int) $progress['completed_lessons']; ?> pelajaran. Jelajahi katalog untuk memilih materi selanjutnya.</p>
			<?php else: ?>
				<p class="text-secondary">Jelajahi kursus yang tersedia. Progresmu tercatat saat kamu menyimpan posisi baca atau menandai materi selesai.</p>
			<?php endif; ?>
			<a href="<?php echo site_url('kursus'); ?>" class="btn btn-outline-primary rounded-pill">Jelajahi kursus</a>
		</div>
		<?php else: ?>
			<p class="mb-5"><a href="<?php echo site_url('kursus'); ?>">Jelajahi kursus lain</a></p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if (isset($progress)): ?>
		<div class="stat-card mt-4">
			<span class="stat-icon stat-purple"><i class="bi bi-journal-check" aria-hidden="true"></i></span>
			<div><span class="stat-label">Pelajaran selesai</span><strong><?php echo (int) $progress['completed_lessons']; ?></strong><?php if ($progress['in_progress_lessons'] > 0): ?><span class="small text-secondary"><?php echo (int) $progress['in_progress_lessons']; ?> sedang berjalan</span><?php endif; ?></div>
		</div>
	<?php endif; ?>

	<?php if ( ! empty($course_progress)): ?>
		<section class="mt-5" aria-labelledby="course-progress-heading">
			<div class="page-heading mb-3"><div><p class="eyebrow">Perjalanan belajarmu</p><h2 class="h4 mb-1" id="course-progress-heading">Progres per kursus</h2><p class="text-secondary mb-0">Ringkasan dihitung dari pelajaran yang sudah kamu mulai atau selesaikan.</p></div></div>
			<div class="row g-3">
				<?php foreach ($course_progress as $course): ?>
					<div class="col-md-6 col-xl-4"><article class="feature-card h-100"><h3 class="h5"><a class="text-decoration-none" href="<?php echo site_url('kursus/'.$course['course_slug']); ?>"><?php echo html_escape($course['course_title']); ?></a></h3><p class="text-secondary small mb-0"><?php echo (int) $course['completed_lessons']; ?> pelajaran selesai<?php if ((int) $course['in_progress_lessons'] > 0): ?> · <?php echo (int) $course['in_progress_lessons']; ?> sedang berjalan<?php endif; ?></p></article></div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>
</section>
