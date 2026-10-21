<section class="container page-section course-workspace">
	<div class="page-heading">
		<div>
			<p class="eyebrow">Belajar dengan ritmemu</p>
			<h1 class="h2">Materi dan latihan soal online</h1>
			<p class="text-secondary mb-0">Temukan latihan soal SD dan materi belajar berdasarkan kelas atau mata pelajaran. Buka kursus untuk membaca materi dan mencoba latihan dengan pembahasan. Kursus gratis dan pratinjau ditandai dengan jelas.</p>
			<p class="mt-3 mb-0"><a class="btn btn-teal" href="<?php echo site_url('latihan-soal'); ?>">Pilih kelas dan baca contoh soal</a></p>
		</div>
	</div>

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
		<?php echo (int) $total_rows; ?> kursus ditemukan<?php if ($has_filters): ?> sesuai filter aktif<?php endif; ?>.
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
						</div>
						<h2 class="h5 text-break"><?php echo html_escape($course['title']); ?></h2>
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
