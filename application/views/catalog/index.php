<section class="container page-section course-workspace">
	<div class="page-heading">
		<div>
			<p class="eyebrow">Belajar dengan ritmemu</p>
			<h1 class="h2">Materi kursus SD, SMP, dan SMA</h1>
			<p class="text-secondary mb-0">Temukan materi belajar dari kelas 1 SD sampai kelas 12 SMA. Pilih kelas dan mata pelajaran, lalu pelajari bab secara berurutan melalui sesi pemahaman, contoh, dan penerapan. Latihan soal tersedia pada kursus yang memilikinya.</p>
			<p class="mt-3 mb-0"><a class="btn btn-teal" href="#pilih-kelas">Pilih kelas untuk mulai belajar</a></p>
		</div>
	</div>
	<nav class="feature-card mb-4" id="pilih-kelas" aria-label="Pilih jenjang dan kelas">
		<h2 class="h5 mb-3">Pilih kelasmu</h2>
		<?php foreach (array('SD' => array(1,2,3,4,5,6), 'SMP' => array(7,8,9), 'SMA' => array(10,11,12)) as $stage => $numbers): ?>
			<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
				<strong class="small" style="min-width:3rem"><?php echo $stage; ?></strong>
				<?php foreach ($numbers as $number): $code = strtolower($stage).'_'.$number; ?><a class="btn btn-sm rounded-pill <?php echo $filters['kelas'] === $code ? 'btn-primary' : 'btn-outline-primary'; ?>" href="<?php echo html_escape(site_url('kursus?kelas='.$code)); ?>"<?php echo $filters['kelas'] === $code ? ' aria-current="page"' : ''; ?>>Kelas <?php echo $number; ?></a><?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</nav>

	<form class="feature-card mb-4" method="get" action="<?php echo html_escape(site_url('kursus')); ?>" role="search" aria-label="Pencarian kursus">
		<div class="row g-3 align-items-end">
			<div class="col-md-3">
				<label class="form-label" for="q">Cari kursus</label>
				<input class="form-control" type="search" name="q" id="q" maxlength="100" value="<?php echo html_escape($filters['q']); ?>" placeholder="Contoh: matematika, membaca">
			</div>
			<div class="col-md-3">
				<label class="form-label" for="kategori">Kategori</label>
				<select class="form-select" name="kategori" id="kategori">
					<option value="0">Semua kategori</option>
					<?php foreach ($categories as $category): ?>
						<option value="<?php echo (int) $category['id']; ?>" <?php echo (int) $filters['category_id'] === (int) $category['id'] ? 'selected' : ''; ?>><?php echo html_escape($category['name']); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-3"><?php echo study_grade_select($filters['kelas']); ?></div>
			<div class="col-12 d-flex flex-wrap gap-2">
				<button class="btn btn-primary rounded-pill flex-grow-1" type="submit">Cari</button>
				<a class="btn btn-light rounded-pill flex-grow-1" href="<?php echo html_escape(site_url('kursus')); ?>">Atur ulang</a>
			</div>
		</div>
	</form>

	<p class="small text-secondary mb-3">
		<?php echo (int) $total_rows; ?> kursus (mata pelajaran) ditemukan<?php if ($has_filters): ?> sesuai filter aktif<?php endif; ?>. Setiap kartu membuka daftar bab dan sesi materinya.
	</p>

	<?php if ($courses): ?>
		<div class="row g-4">
			<?php foreach ($courses as $course): ?>
				<div class="col-md-6 col-xl-4">
					<article class="feature-card h-100 d-flex flex-column">
						<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
							<span class="badge rounded-pill text-bg-light text-wrap text-break text-start mw-100"><?php echo html_escape(($course['category_name'] ?: 'Kursus').' · '.study_grade_label($course['grade_id'])); ?></span>
							<?php if ((int) $course['is_free'] === 1): ?>
								<span class="badge rounded-pill text-bg-success">Gratis</span>
							<?php endif; ?>
							<?php if ($course['category_slug'] === 'koding-ka' || (in_array(study_grade_code($course['grade_id']), array('sma_11', 'sma_12'), TRUE) && in_array($course['category_slug'], array('biologi', 'fisika', 'kimia', 'geografi', 'ekonomi', 'sosiologi', 'tik', 'matematika-lanjut'), TRUE))): ?><span class="badge rounded-pill text-bg-info">Mapel pilihan</span><?php endif; ?>
						</div>
						<h2 class="h5 text-break"><?php echo html_escape($course['title']); ?></h2>
						<p class="small fw-semibold text-primary mb-2"><?php echo (int) $course['chapter_count']; ?> bab · <?php echo (int) $course['lesson_count']; ?> sesi</p>
						<p class="text-secondary text-break flex-grow-1 mb-3"><?php echo html_escape($course['summary']); ?></p>
						<div class="small text-secondary text-break mb-3">
							<?php echo html_escape(isset($target_group_labels[$course['target_group']]) ? $target_group_labels[$course['target_group']] : 'Kelompok belum ditentukan'); ?>
							<?php $age_range_label = study_age_range_label($course['age_min'], $course['age_max']); ?>
							<?php if ($age_range_label !== ''): ?>
								· <?php echo html_escape($age_range_label); ?>
							<?php endif; ?>
							<?php if ($course['estimated_minutes']): ?>
								· <?php echo (int) $course['estimated_minutes']; ?> menit
							<?php endif; ?>
						</div>
						<a class="btn btn-outline-primary rounded-pill align-self-start" href="<?php echo html_escape(site_url('kursus/'.$course['slug'])); ?>">
							Lihat kursus <span class="visually-hidden"><?php echo html_escape($course['title']); ?></span>
						</a>
					</article>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		$pagination_base = 'kursus';
		$this->load->view('practice/pagination', compact('pagination_base', 'pagination_query', 'page', 'page_count'));
		?>
	<?php elseif ($has_filters): ?>
		<div class="empty-card text-center py-5">
			<span class="feature-icon feature-purple mx-auto mb-3"><i class="bi bi-search" aria-hidden="true"></i></span>
			<h2 class="h5">Belum ada kursus yang cocok</h2>
			<p class="text-secondary mb-3">Coba kata pencarian atau kategori lain, atau hapus filter untuk melihat semua kursus.</p>
			<a class="btn btn-outline-primary rounded-pill" href="<?php echo html_escape(site_url('kursus')); ?>">Hapus filter</a>
		</div>
	<?php else: ?>
		<div class="empty-card text-center py-5">
			<span class="feature-icon feature-purple mx-auto mb-3"><i class="bi bi-journal-richtext" aria-hidden="true"></i></span>
			<h2 class="h5">Kursus sedang disiapkan</h2>
			<p class="text-secondary mb-0">Materi akan muncul di sini setelah ditinjau dan diterbitkan. Silakan kembali lagi nanti.</p>
		</div>
	<?php endif; ?>
</section>
