<aside class="admin-sidebar" id="admin-sidebar" aria-label="Menu pengelola">
	<div class="admin-brand"><a href="<?php echo site_url('kelola'); ?>"><i class="bi <?php echo html_escape($site_settings['brand_icon']); ?>" aria-hidden="true"></i><span><?php echo html_escape($site_settings['brand_name']); ?><small>Ruang pengelola</small></span></a><button type="button" class="btn-close admin-drawer-close" aria-label="Tutup menu pengelola" hidden></button></div>
	<nav aria-label="Navigasi pengelola">
		<?php foreach ($admin_menu as $group => $items): ?>
		<div class="admin-nav-group"><p><?php echo html_escape($group); ?></p>
		<?php foreach ($items as $item): ?><a class="admin-nav-link<?php echo $item['active'] ? ' active' : ''; ?>" href="<?php echo site_url($item['path']); ?>"<?php echo $item['active'] ? ' aria-current="page"' : ''; ?>><i class="bi <?php echo html_escape($item['icon']); ?>" aria-hidden="true"></i><span><?php echo html_escape($item['label']); ?></span></a><?php endforeach; ?>
		</div><?php endforeach; ?>
	</nav>
	<div class="admin-sidebar-note"><i class="bi bi-shield-lock" aria-hidden="true"></i> Akses sesuai peran akun</div>
</aside>
<div class="admin-drawer-backdrop" hidden></div>
<div class="admin-workspace">
	<header class="admin-topbar">
		<a class="btn btn-outline-primary" href="<?php echo site_url('notifikasi'); ?>" aria-label="Kotak notifikasi"><i class="bi bi-envelope" aria-hidden="true"></i><?php if($notification_unread): ?><span class="badge text-bg-danger ms-1"><?php echo min(99,(int)$notification_unread); ?><?php echo $notification_unread>99?'+':''; ?></span><?php endif; ?></a>
		<div class="d-flex align-items-center gap-3"><button type="button" class="btn btn-outline-primary admin-menu-toggle" aria-controls="admin-sidebar" aria-expanded="true" aria-label="Buka atau ciutkan menu pengelola" hidden><i class="bi bi-list" aria-hidden="true"></i></button><nav aria-label="Breadcrumb"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="<?php echo site_url('kelola'); ?>">Pengelola</a></li><li class="breadcrumb-item active" aria-current="page"><?php echo html_escape($page_title); ?></li></ol></nav></div>
		<div class="admin-account"><a class="btn btn-sm btn-outline-primary" href="<?php echo site_url('beranda'); ?>">Buka tampilan pengguna</a><span class="small"><?php echo html_escape($current_user['username']); ?><strong class="d-block"><?php echo html_escape(implode(', ', array_map('study_role_label', $current_user['roles']))); ?></strong></span><?php echo form_open('keluar'); ?><button type="submit" class="btn btn-sm btn-outline-secondary">Keluar</button><?php echo form_close(); ?></div>
	</header>
