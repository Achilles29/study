<?php if ( ! $tickets): ?><div class="card card-body"><p class="mb-0">Belum ada pesan<?php echo $status !== '' ? ' dengan status ini' : ''; ?>.</p></div><?php endif; ?>
<div class="d-grid gap-2">
<?php foreach ($tickets as $item): ?><article class="card card-body"><div class="d-flex flex-column flex-md-row justify-content-between gap-2"><div><p class="small text-secondary mb-1">#<?php echo (int) $item['id']; ?> · <?php echo html_escape($categories[$item['category']]); ?></p><h3 class="h5 text-break mb-2"><a href="<?php echo site_url(($help_admin ? 'kelola/bantuan/' : 'bantuan/tiket/').$item['id']); ?>"><?php echo html_escape($item['subject']); ?></a></h3></div><div><span class="badge text-bg-light"><?php echo html_escape($statuses[$item['status']]); ?></span><p class="small text-secondary mt-2 mb-0"><?php echo html_escape(gmdate('d/m/Y H:i', strtotime($item['updated_at'].' UTC') + 25200)); ?> WIB</p></div></div></article><?php endforeach; ?>
</div>
<nav class="d-flex flex-wrap gap-3 align-items-center mt-3" aria-label="Halaman pesan">
<?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="<?php echo site_url($list_path).'?'.http_build_query(array('status' => $status, 'halaman' => $page - 1)); ?>">Sebelumnya</a><?php endif; ?>
<span>Halaman <?php echo (int) $page; ?> dari <?php echo (int) $pages; ?></span>
<?php if ($page < $pages): ?><a class="btn btn-outline-secondary" href="<?php echo site_url($list_path).'?'.http_build_query(array('status' => $status, 'halaman' => $page + 1)); ?>">Berikutnya</a><?php endif; ?>
</nav>
