<section class="container page-section">
	<div class="page-heading">
		<div>
			<p class="eyebrow">Panel pengelola · Konten</p>
			<h1 class="h2">Kursus dan konten</h1>
			<p class="text-secondary mb-0">Kelola draf, review, dan materi yang sudah terbit.</p>
		</div>
		<a class="btn btn-primary rounded-pill px-4" href="<?php echo site_url('kelola/kursus/tambah'); ?>">
			<i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Buat kursus
		</a>
	</div>

	<div class="feature-card mb-4">
		<form method="get" action="<?php echo site_url('kelola/kursus'); ?>" class="row g-3 align-items-end">
			<div class="col-md-6">
				<label class="form-label" for="q">Cari kursus</label>
				<input class="form-control" type="search" name="q" id="q" value="<?php echo html_escape($filters['q']); ?>" maxlength="100" placeholder="Ketik judul atau ringkasan">
			</div>
			<div class="col-md-3">
				<label class="form-label" for="status">Status</label>
				<select class="form-select" name="status" id="status">
					<option value="">Semua status</option>
					<?php foreach (array('draft' => 'Draf', 'review' => 'Menunggu review', 'published' => 'Terbit', 'archived' => 'Diarsipkan') as $key => $label): ?>
						<option value="<?php echo html_escape($key); ?>" <?php echo $filters['status'] === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-3 d-flex gap-2">
				<button class="btn btn-outline-primary rounded-pill flex-grow-1" type="submit">Terapkan</button>
				<a class="btn btn-light rounded-pill" href="<?php echo site_url('kelola/kursus'); ?>">Atur ulang</a>
			</div>
		</form>
	</div>

	<div class="d-flex justify-content-between align-items-center mb-2">
		<p class="small text-secondary mb-0"><?php echo (int) $total_rows; ?> kursus ditemukan</p>
		<a class="small" href="<?php echo site_url('kelola/kategori'); ?>">Kelola kategori</a>
	</div>

	<?php if ($courses): ?>
		<div class="d-grid gap-3 d-md-none">
			<?php foreach ($courses as $course): ?>
				<article class="admin-course-card">
					<div class="d-flex align-items-start justify-content-between gap-3 mb-3">
						<div class="min-w-0">
							<h2 class="h6 mb-1"><a class="fw-semibold" href="<?php echo site_url('kelola/kursus/'.$course['id']); ?>"><?php echo html_escape($course['title']); ?></a></h2>
							<p class="small text-secondary mb-0"><?php echo html_escape($course['category_name'] ?: 'Tanpa kategori'); ?> · <?php echo html_escape($course['author_name'] ?: 'Pengelola'); ?></p>
						</div>
						<span class="badge rounded-pill flex-shrink-0 <?php echo $course['status'] === 'published' ? 'text-bg-success' : ($course['status'] === 'review' ? 'text-bg-warning' : 'text-bg-secondary'); ?>">
							<?php echo html_escape(study_course_status_label($course['status'])); ?>
						</span>
					</div>
					<dl class="row small mb-3">
						<div class="col-6"><dt class="text-secondary fw-normal">Kelompok</dt><dd class="mb-2"><?php echo html_escape(study_target_group_label($course['target_group'])); ?></dd></div>
						<div class="col-6"><dt class="text-secondary fw-normal">Akses</dt><dd class="mb-2"><?php echo (int) $course['is_free'] === 1 ? 'Gratis' : 'Paket'; ?></dd></div>
						<div class="col-12"><dt class="text-secondary fw-normal">Pembaruan</dt><dd class="mb-0"><?php echo html_escape(study_format_datetime($course['updated_at'])); ?></dd></div>
					</dl>
					<a class="btn btn-outline-primary rounded-pill w-100" href="<?php echo site_url('kelola/kursus/'.$course['id']); ?>">Buka kursus</a>
				</article>
			<?php endforeach; ?>
		</div>
		<div class="table-responsive bg-white border rounded-4 d-none d-md-block">
			<table class="table align-middle mb-0">
				<thead>
					<tr>
						<th scope="col">Kursus</th>
						<th scope="col">Kelompok</th>
						<th scope="col">Status</th>
						<th scope="col">Akses</th>
						<th scope="col">Pembaruan</th>
						<th scope="col"><span class="visually-hidden">Aksi</span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($courses as $course): ?>
						<tr>
							<td>
								<a class="fw-semibold" href="<?php echo site_url('kelola/kursus/'.$course['id']); ?>"><?php echo html_escape($course['title']); ?></a>
								<div class="small text-secondary"><?php echo html_escape($course['category_name'] ?: 'Tanpa kategori'); ?> · <?php echo html_escape($course['author_name'] ?: 'Pengelola'); ?></div>
							</td>
							<td><?php echo html_escape(study_target_group_label($course['target_group'])); ?></td>
							<td>
								<span class="badge rounded-pill <?php echo $course['status'] === 'published' ? 'text-bg-success' : ($course['status'] === 'review' ? 'text-bg-warning' : 'text-bg-secondary'); ?>">
									<?php echo html_escape(study_course_status_label($course['status'])); ?>
								</span>
							</td>
							<td><?php echo (int) $course['is_free'] === 1 ? 'Gratis' : 'Paket'; ?></td>
							<td class="small text-secondary"><?php echo html_escape(study_format_datetime($course['updated_at'])); ?></td>
							<td><a class="btn btn-sm btn-outline-primary rounded-pill" href="<?php echo site_url('kelola/kursus/'.$course['id']); ?>">Buka</a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php elseif ($filters['q'] !== '' || $filters['status'] !== ''): ?>
		<div class="empty-card">
			<span class="empty-icon"><i class="bi bi-search" aria-hidden="true"></i></span>
			<h2 class="h5">Tidak ada kursus yang cocok</h2>
			<p class="text-secondary">Coba kata kunci atau status lain, atau hapus filter untuk melihat seluruh daftar.</p>
			<a class="btn btn-outline-primary rounded-pill px-4" href="<?php echo site_url('kelola/kursus'); ?>">Hapus filter</a>
		</div>
	<?php else: ?>
		<div class="empty-card">
			<span class="empty-icon"><i class="bi bi-journal-richtext" aria-hidden="true"></i></span>
			<h2 class="h5">Belum ada kursus pada daftar ini</h2>
			<p class="text-secondary">Buat draf pertama, lalu tambahkan pelajaran dan minta review.</p>
			<a class="btn btn-primary rounded-pill px-4" href="<?php echo site_url('kelola/kursus/tambah'); ?>">Buat kursus</a>
		</div>
	<?php endif; ?>

	<?php $pagination_base = 'kelola/kursus'; $this->load->view('practice/pagination', compact('pagination_base', 'pagination_query', 'page', 'page_count')); ?>
</section>
