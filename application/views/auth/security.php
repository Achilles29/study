<section class="auth-shell container py-5">
<p><a class="btn btn-outline-secondary" href="<?php echo site_url('akun/pengaturan'); ?>"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Pengaturan akun</a></p>
	<div class="auth-card mx-auto">
		<a class="back-link" href="<?php echo site_url('beranda'); ?>">Kembali ke ruang belajar</a>
		<div class="text-center mt-4 mb-4"><span class="feature-icon feature-green mx-auto mb-3"><i class="bi bi-shield-lock" aria-hidden="true"></i></span><p class="eyebrow justify-content-center">Lindungi akunmu</p><h1 class="h2">Ganti kata sandi</h1><p class="text-secondary">Untuk akunmu sendiri: <strong class="text-break"><?php echo html_escape($current_user['username']); ?></strong></p></div>
		<div class="alert alert-info">Setelah berhasil, kamu perlu masuk kembali di semua perangkat. Kemajuan belajarmu tetap tersimpan. Ini bukan pemulihan lupa kata sandi.</div>
		<?php if ($current_user['account_kind'] === 'child'): ?><p>Jika bingung, minta orang tua mendampingimu. Jangan bagikan kata sandi kepada orang lain.</p><?php endif; ?>
		<?php if ($submit_error): ?><div class="alert alert-warning" role="alert"><?php echo html_escape($submit_error); ?></div><?php endif; ?>
		<?php echo form_open('akun/keamanan'); ?>
		<?php foreach (array('current_password' => 'Kata sandi sekarang', 'new_password' => 'Kata sandi baru', 'password_confirm' => 'Ulangi kata sandi baru') as $field => $label): ?>
		<div class="mb-3"><label class="form-label" for="<?php echo $field; ?>"><?php echo $label; ?></label><input class="form-control form-control-lg" type="password" id="<?php echo $field; ?>" name="<?php echo $field; ?>" maxlength="72" autocomplete="<?php echo $field === 'current_password' ? 'current-password' : 'new-password'; ?>" required<?php echo study_field_error_attributes($form_errors, $field); ?>><?php if ($field === 'new_password'): ?><p class="form-text">Minimal <?php echo (int) $minimum; ?> karakter, maksimal 72 byte. Huruf beraksen dan emoji bisa memakai beberapa byte.</p><?php endif; ?><?php echo study_render_field_error($form_errors, $field); ?></div>
		<?php endforeach; ?>
		<button class="btn btn-primary btn-lg rounded-pill w-100" type="submit">Ganti sandi dan masuk ulang</button>
		<?php echo form_close(); ?>
	</div>
</section>
