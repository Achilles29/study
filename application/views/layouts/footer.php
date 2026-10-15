</main>
<footer class="site-footer mt-5">
	<div class="container py-4 d-flex flex-column flex-md-row justify-content-between gap-2">
		<p class="mb-0 text-secondary">Belajar sedikit demi sedikit, tumbuh setiap hari.</p>
		<p class="mb-0 text-secondary small">Ruang Belajar · Versi awal</p>
	</div>
</footer>
<script src="<?php echo base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js'); ?>"></script>
<?php $navigation_js_path = FCPATH.'assets/js/navigation.js'; ?><script src="<?php echo base_url('assets/js/navigation.js?v='.rawurlencode((string) filemtime($navigation_js_path))); ?>" defer></script>
<?php if ( ! empty($load_matching_script)): $matching_path = FCPATH.'assets/js/matching-game.js'; ?><script type="module" src="<?php echo base_url('assets/js/matching-game.js?v='.rawurlencode((string) filemtime($matching_path))); ?>"></script><?php endif; ?>
<?php if ( ! empty($load_matching_save_script)): $matching_save_path = FCPATH.'assets/js/matching-save.js'; ?><script type="module" src="<?php echo base_url('assets/js/matching-save.js?v='.rawurlencode((string) filemtime($matching_save_path))); ?>"></script><?php endif; ?>
<?php if ( ! empty($load_lesson_progress_script)): $lesson_js_path = FCPATH.'assets/js/lesson-progress.js'; $lesson_js_version = is_file($lesson_js_path) ? (string) filemtime($lesson_js_path) : '1'; ?><script src="<?php echo base_url('assets/js/lesson-progress.js?v='.rawurlencode($lesson_js_version)); ?>" defer></script><?php endif; ?>
<?php if ( ! empty($load_unsaved_form_script)): $unsaved_js_path = FCPATH.'assets/js/unsaved-form.js'; ?><script src="<?php echo base_url('assets/js/unsaved-form.js?v='.rawurlencode((string) filemtime($unsaved_js_path))); ?>" defer></script><?php endif; ?>
</body>
</html>
