<section class="container page-section">
<div class="page-heading"><div><p class="eyebrow">Ruang kerja internal</p><h1 class="h2">Dashboard pengelola</h1><p class="text-secondary mb-0">Ringkasan dan akses kerja sesuai wewenang akun Anda.</p></div></div>
<?php if (is_array($learning_summary)): ?>
<div class="feature-card mb-4"><div class="d-flex flex-wrap justify-content-between gap-3"><div><p class="eyebrow">Pembelajaran · 30 hari terakhir</p><h2 class="h4"><?php echo (int) $learning_summary['learners']; ?> pengguna aktif belajar</h2><p class="mb-0 small">Diperbarui <?php echo html_escape((new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('d/m/Y H:i')); ?> WIB · <?php echo (int) $counts['review']; ?> kursus menunggu review · <?php echo (int) $counts['pending']; ?> pesanan pending</p></div><a class="btn btn-primary align-self-center" href="<?php echo site_url('kelola/pembelajaran'); ?>">Buka monitoring</a></div></div>
<?php elseif ($learning_summary === FALSE): ?><div class="alert alert-warning">Ringkasan belajar belum dapat dimuat. Coba buka monitoring kembali.</div><?php endif; ?>
<?php if ($counts): ?><div class="row g-3 mb-4">
<?php foreach (array('courses' => 'Kursus', 'packages' => 'Paket belajar', 'orders' => 'Pesanan pembayaran') as $key => $label): ?><?php if (isset($counts[$key])): ?><div class="col-sm-6 col-xl-4"><div class="feature-card admin-metric"><span><?php echo $label; ?></span><strong><?php echo (int) $counts[$key]; ?></strong></div></div><?php endif; ?><?php endforeach; ?>
</div><?php endif; ?>
<h2 class="h4 mb-3">Menu kerja</h2>
<div class="admin-card-grid">
<?php foreach (study_admin_menu($current_user['roles'], 'admin/index') as $group => $items): ?><?php if ($group === 'Ringkasan') { continue; } ?>
<article class="feature-card"><span class="feature-icon feature-purple" aria-hidden="true"><i class="bi <?php echo html_escape($items[0]['icon']); ?>"></i></span><h2 class="h5"><?php echo html_escape($group); ?></h2><ul class="admin-link-list"><li><a href="<?php echo site_url($items[0]['path']); ?>"><?php echo html_escape($items[0]['label']); ?><i class="bi bi-arrow-right" aria-hidden="true"></i></a></li></ul><?php if (count($items) > 1): ?><details class="w-100 mt-2"><summary><?php echo count($items) - 1; ?> menu lainnya</summary><ul class="admin-link-list mt-2"><?php foreach (array_slice($items, 1) as $item): ?><li><a href="<?php echo site_url($item['path']); ?>"><?php echo html_escape($item['label']); ?><i class="bi bi-arrow-right" aria-hidden="true"></i></a></li><?php endforeach; ?></ul></details><?php endif; ?></article>
<?php endforeach; ?>
</div>
<div class="feature-card mt-4"><h2 class="h5">Status layanan & akun</h2><p>Midtrans mendukung profil Live dan Sandbox terpisah. Draft harga/paket harus diverifikasi sebelum dijual; isi latihan yang kosong tidak dapat diterbitkan. Superadmin dapat memeriksa kesiapan dan status checkout melalui menu Pengaturan pembayaran.</p><p class="small mb-0">Masuk sebagai <strong><?php echo html_escape($current_user['username']); ?></strong> · <?php echo html_escape(implode(', ', array_map('study_role_label', $current_user['roles']))); ?></p></div>
</section>
