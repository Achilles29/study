<aside class="admin-sidebar" id="admin-sidebar" aria-label="Menu pengelola">
	<div class="admin-brand"><a href="<?php echo site_url('kelola'); ?>"><img class="admin-brand-mark" src="<?php echo base_url('assets/images/branding/ico.png'); ?>" width="48" height="48" alt=""><span><?php echo html_escape($site_settings['brand_name']); ?><small>Ruang pengelola</small></span></a><button type="button" class="btn-close admin-drawer-close" aria-label="Tutup menu pengelola" hidden></button></div>
	<nav aria-label="Navigasi pengelola">
		<?php foreach ($admin_menu as $group => $items): ?>
		<div class="admin-nav-group"><p><?php echo html_escape($group); ?></p>
		<?php foreach ($items as $item): ?><a class="admin-nav-link<?php echo $item['active'] ? ' active' : ''; ?>" href="<?php echo site_url($item['path']); ?>"<?php echo $item['active'] ? ' aria-current="page"' : ''; ?>><i class="bi <?php echo html_escape($item['icon']); ?>" aria-hidden="true"></i><span><?php echo html_escape($item['label']); ?></span></a><?php endforeach; ?>
		</div><?php endforeach; ?>
	</nav>
	<div class="admin-sidebar-note"><i class="bi bi-person-check" aria-hidden="true"></i><span><?php echo html_escape($current_user['username']); ?><small><?php echo html_escape(implode(', ', array_map('study_role_label', $current_user['roles']))); ?></small></span></div>
</aside>
<div class="admin-drawer-backdrop" hidden></div>
<div class="admin-workspace">
	<header class="admin-topbar">
		<div class="admin-topbar-main"><button type="button" class="btn btn-outline-primary admin-menu-toggle admin-icon-button" aria-controls="admin-sidebar" aria-expanded="true" aria-label="Buka atau ciutkan menu pengelola" hidden><i class="bi bi-list" aria-hidden="true"></i></button><nav aria-label="Breadcrumb"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="<?php echo site_url('kelola'); ?>">Pengelola</a></li><li class="breadcrumb-item active" aria-current="page"><?php echo html_escape($page_title); ?></li></ol></nav></div>
		<div class="admin-account"><a class="btn btn-outline-primary admin-icon-button admin-notification" href="<?php echo site_url('notifikasi'); ?>" aria-label="Kotak notifikasi<?php echo $notification_unread?', '.(int)$notification_unread.' belum dibaca':''; ?>"><i class="bi bi-envelope" aria-hidden="true"></i><?php if($notification_unread): ?><span class="badge text-bg-danger"><?php echo min(99,(int)$notification_unread); ?><?php echo $notification_unread>99?'+':''; ?></span><?php endif; ?></a><a class="btn btn-outline-primary admin-user-view" href="<?php echo site_url('beranda'); ?>" aria-label="Buka tampilan pengguna"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span>Buka tampilan pengguna</span></a><span class="admin-account-name small"><?php echo html_escape($current_user['username']); ?><strong><?php echo html_escape(implode(', ', array_map('study_role_label', $current_user['roles']))); ?></strong></span><?php echo form_open('keluar'); ?><button type="submit" class="btn btn-outline-secondary admin-logout" aria-label="Keluar"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Keluar</span></button><?php echo form_close(); ?></div>
	</header>
