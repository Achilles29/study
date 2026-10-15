<section class="container page-section">
	<div class="page-heading"><div><p class="eyebrow">Ruang kerja internal</p><h1 class="h2">Panel pengelola</h1><p class="text-secondary mb-0">Ringkasan di halaman ini disesuaikan dengan peran dan wewenang akunmu.</p></div><span class="welcome-mark" aria-hidden="true"><i class="bi bi-sliders"></i></span></div>
	<?php if ($counts): ?><div class="row g-3 mb-4">
		<?php if (isset($counts['courses'])): ?><div class="col-md-4"><div class="stat-card"><span class="stat-icon stat-purple"><i class="bi bi-journal-richtext" aria-hidden="true"></i></span><div><span class="stat-label">Kursus</span><strong><?php echo (int) $counts['courses']; ?></strong></div></div></div><?php endif; ?>
		<?php if (isset($counts['packages'])): ?><div class="col-md-4"><div class="stat-card"><span class="stat-icon stat-coral"><i class="bi bi-box-seam" aria-hidden="true"></i></span><div><span class="stat-label">Paket belajar</span><strong><?php echo (int) $counts['packages']; ?></strong></div></div></div><?php endif; ?>
		<?php if (isset($counts['orders'])): ?><div class="col-md-4"><div class="stat-card"><span class="stat-icon stat-green"><i class="bi bi-receipt" aria-hidden="true"></i></span><div><span class="stat-label">Pesanan pembayaran</span><strong><?php echo (int) $counts['orders']; ?></strong></div></div></div><?php endif; ?>
	</div><?php endif; ?>
	<div class="alert alert-info" role="status"><i class="bi bi-info-circle me-2" aria-hidden="true"></i>Pengelolaan paket dan transaksi belum dibuka. Jangan aktifkan penjualan sebelum harga, kebijakan, dan koneksi sandbox pembayaran selesai diuji.</div>
	<div class="d-flex flex-wrap gap-2 mb-4">
		<?php if (in_array('system_admin', $current_user['roles'], TRUE)): ?><a class="btn btn-primary rounded-pill" href="<?php echo site_url('kelola/pengguna'); ?>">Direktori pengguna</a><?php endif; ?>
		<?php if (in_array('system_admin', $current_user['roles'], TRUE)): ?><a class="btn btn-outline-primary rounded-pill" href="<?php echo site_url('kelola/audit'); ?>">Jejak audit</a><?php endif; ?>
		<?php if (array_intersect(array('system_admin', 'content_editor'), $current_user['roles'])): ?><a class="btn btn-outline-primary rounded-pill" href="<?php echo site_url('kelola/kursus'); ?>">Kursus dan konten</a><?php endif; ?>
	</div>
	<div class="feature-card"><h2 class="h5">Akun masuk</h2><p class="mb-2">Nama pengguna: <strong><?php echo html_escape($current_user['username']); ?></strong></p><p class="mb-0">Peran: <?php echo html_escape(implode(', ', $current_user['roles'])); ?></p></div>
</section>
