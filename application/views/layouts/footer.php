</main>
<footer class="site-footer mt-5">
	<div class="container py-4 d-flex flex-column flex-md-row justify-content-between gap-2">
		<p class="mb-0 text-secondary"><?php echo html_escape($site_settings['tagline']); ?></p>
		<p class="mb-0 small"><a href="<?php echo site_url('bantuan'); ?>">Bantuan</a> · <a href="<?php echo site_url('privasi'); ?>">Privasi, monitoring &amp; syarat</a> · <a href="<?php echo site_url('preferensi-privasi'); ?>">Preferensi privasi</a><?php if ( ! $current_user || $current_user['account_kind'] !== 'child'): ?> · <a href="<?php echo site_url('donasi'); ?>">Donasi sukarela</a><?php endif; ?></p>
		<p class="mb-0 text-secondary small"><?php echo html_escape($site_settings['brand_name']); ?><?php if ($site_settings['support_email'] !== ''): ?> · <a href="mailto:<?php echo html_escape($site_settings['support_email']); ?>">Hubungi dukungan</a><?php endif; ?></p>
	</div>
</footer>
<?php if ($admin_theme): ?></div><?php endif; ?>
<?php if($light_public_assets): ?>
<script src="<?php echo base_url('assets/js/public-navigation.js?v='.filemtime(FCPATH.'assets/js/public-navigation.js')); ?>" defer></script>
<?php else: ?>
<script src="<?php echo base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
<script src="<?php echo base_url('assets/js/password-visibility.js?v='.filemtime(FCPATH.'assets/js/password-visibility.js')); ?>" defer></script>
<?php endif; ?>
<?php if ($admin_theme): ?><script src="<?php echo base_url('assets/js/admin-navigation.js?v='.filemtime(FCPATH.'assets/js/admin-navigation.js')); ?>" defer></script><?php endif; ?>
<?php if(!$light_public_assets): $navigation_js_path = FCPATH.'assets/js/navigation.js'; ?><script src="<?php echo base_url('assets/js/navigation.js?v='.rawurlencode((string) filemtime($navigation_js_path))); ?>" defer></script><?php endif; ?>
<?php if ( ! empty($load_matching_script)): $matching_path = FCPATH.'assets/js/matching-game.js'; ?><script type="module" src="<?php echo base_url('assets/js/matching-game.js?v='.rawurlencode((string) filemtime($matching_path))); ?>"></script><?php endif; ?>
<?php if ( ! empty($load_matching_save_script)): $matching_save_path = FCPATH.'assets/js/matching-save.js'; ?><script type="module" src="<?php echo base_url('assets/js/matching-save.js?v='.rawurlencode((string) filemtime($matching_save_path))); ?>"></script><?php endif; ?>
<?php if ( ! empty($load_lesson_progress_script) && $current_user): $lesson_js_path = FCPATH.'assets/js/lesson-progress.js'; $lesson_js_version = is_file($lesson_js_path) ? (string) filemtime($lesson_js_path) : '1'; ?><script src="<?php echo base_url('assets/js/lesson-progress.js?v='.rawurlencode($lesson_js_version)); ?>" defer></script><?php endif; ?>
<?php if ( ! empty($load_unsaved_form_script)): $unsaved_js_path = FCPATH.'assets/js/unsaved-form.js'; ?><script src="<?php echo base_url('assets/js/unsaved-form.js?v='.rawurlencode((string) filemtime($unsaved_js_path))); ?>" defer></script><?php endif; ?>
<?php if (!empty($load_practice_sheet_script)): ?><script src="<?php echo base_url('assets/js/practice-sheet.js?v='.filemtime(FCPATH.'assets/js/practice-sheet.js')); ?>" defer></script><?php endif; ?>
<?php if (!empty($load_traffic_live_script)): ?><script src="<?php echo base_url('assets/js/traffic-live.js?v='.filemtime(FCPATH.'assets/js/traffic-live.js')); ?>" defer></script><?php endif; ?>
</body>
</html>
