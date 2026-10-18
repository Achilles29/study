<section class="container page-section">
<div class="page-heading mb-4"><h1>Kotak Bantuan</h1><p class="text-secondary">Pesan privat pengunjung dan pengguna. Periksa kotak ini secara berkala; tidak ada notifikasi email otomatis.</p></div>
<form method="get" action="<?php echo site_url('kelola/bantuan'); ?>" class="card card-body mb-4"><div class="row g-3 align-items-end"><div class="col-md-8"><label for="status" class="form-label">Status</label><select id="status" name="status" class="form-select"><option value="">Semua status</option><?php foreach ($statuses as $key => $label): ?><option value="<?php echo $key; ?>" <?php echo $status === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option><?php endforeach; ?></select></div><div class="col-md-4"><button type="submit" class="btn btn-primary">Terapkan</button></div></div></form>
<?php $this->load->view('help/list', array('list_path' => 'kelola/bantuan')); ?>
</section>
