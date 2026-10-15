<?php if ($page_count > 1): ?>
	<?php $pagination_query = isset($pagination_query) && is_array($pagination_query) ? $pagination_query : array(); ?>
	<nav class="d-flex flex-wrap align-items-center gap-3 mt-4" aria-label="Halaman daftar">
		<?php if ($page > 1): ?><a class="btn btn-outline-primary rounded-pill" href="<?php echo html_escape(site_url($pagination_base.'?'.http_build_query(array('halaman' => $page - 1) + $pagination_query, '', '&', PHP_QUERY_RFC3986))); ?>">Sebelumnya</a><?php endif; ?>
		<span aria-current="page">Halaman <?php echo (int) $page; ?> dari <?php echo (int) $page_count; ?></span>
		<?php if ($page < $page_count): ?><a class="btn btn-outline-primary rounded-pill" href="<?php echo html_escape(site_url($pagination_base.'?'.http_build_query(array('halaman' => $page + 1) + $pagination_query, '', '&', PHP_QUERY_RFC3986))); ?>">Berikutnya</a><?php endif; ?>
	</nav>
<?php endif; ?>
