<?php if (!empty($breadcrumbs)): ?>
<nav aria-label="Jejak halaman" class="mb-4"><ol class="breadcrumb flex-wrap small">
	<?php foreach($breadcrumbs as $i=>$crumb): ?><li class="breadcrumb-item text-break"><?php if($i<count($breadcrumbs)-1): ?><a href="<?php echo html_escape(site_url($crumb['path'])); ?>"><?php echo html_escape($crumb['label']); ?></a><?php else: ?><span aria-current="page"><?php echo html_escape($crumb['label']); ?></span><?php endif; ?></li><?php endforeach; ?>
</ol></nav>
<?php endif; ?>
