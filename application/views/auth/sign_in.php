<section class="auth-shell container py-5">
	<div class="auth-card mx-auto">
		<div class="text-center mb-4"><span class="feature-icon feature-purple mx-auto mb-3"><i class="bi bi-person-check" aria-hidden="true"></i></span><p class="eyebrow justify-content-center">Senang bertemu lagi</p><h1 class="h2">Masuk ke akunmu</h1><p class="text-secondary">Lanjutkan perjalanan belajarmu.</p></div>
		<?php if ($submit_error): ?><div class="alert alert-warning" role="alert"><?php echo html_escape($submit_error); ?></div><?php endif; ?>
		<?php echo form_open('masuk', array('autocomplete' => 'on')); ?>
			<div class="mb-3">
				<label class="form-label" for="identity">Nama pengguna atau email</label>
				<input class="form-control form-control-lg" type="text" name="identity" id="identity" value="<?php echo html_escape(set_value('identity')); ?>" autocomplete="username" maxlength="190" required aria-invalid="<?php echo isset($form_errors['identity']) ? 'true' : 'false'; ?>" <?php echo isset($form_errors['identity']) ? 'aria-describedby="identity-error"' : ''; ?>>
				<?php if (isset($form_errors['identity'])): ?><div class="field-error" id="identity-error"><?php echo html_escape(strip_tags($form_errors['identity'])); ?></div><?php endif; ?>
			</div>
			<div class="mb-4">
				<label class="form-label" for="password">Kata sandi</label>
				<input class="form-control form-control-lg" type="password" name="password" id="password" autocomplete="current-password" maxlength="72" required aria-invalid="<?php echo isset($form_errors['password']) ? 'true' : 'false'; ?>" <?php echo isset($form_errors['password']) ? 'aria-describedby="password-error"' : ''; ?>>
				<?php if (isset($form_errors['password'])): ?><div class="field-error" id="password-error"><?php echo html_escape(strip_tags($form_errors['password'])); ?></div><?php endif; ?>
			</div>
			<button class="btn btn-primary btn-lg rounded-pill w-100" type="submit">Masuk</button>
		<?php echo form_close(); ?>
		<p class="text-center text-secondary mt-4 mb-0">Belum punya akun? <a href="<?php echo site_url('daftar'); ?>">Buat akun</a></p>
	</div>
</section>
