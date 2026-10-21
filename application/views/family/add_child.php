<section class="auth-shell container py-5">
	<div class="auth-card mx-auto">
		<p class="alert alert-info">Dua anak aktif termasuk dalam akun keluarga. Slot tambahan yang sah menambah kapasitas, bukan otomatis akses paket.</p>
		<a class="back-link" href="<?php echo site_url('keluarga/anak'); ?>"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke akun anak</a>
		<div class="text-center mt-4 mb-4"><span class="feature-icon feature-coral mx-auto mb-3"><i class="bi bi-person-plus" aria-hidden="true"></i></span><p class="eyebrow justify-content-center">Akun keluarga</p><h1 class="h2">Tambahkan akun anak</h1><p class="text-secondary">Buat nama pengguna dan kata sandi awal. Bagikan kepada anak secara aman.</p></div>
		<?php if ($submit_error): ?><div class="alert alert-warning" role="alert"><?php echo html_escape($submit_error); ?></div><?php endif; ?>
		<?php echo form_open('keluarga/anak/tambah', array('autocomplete' => 'off')); ?>
			<div class="mb-3"><label class="form-label" for="display_name">Nama panggilan anak</label><input class="form-control form-control-lg" type="text" name="display_name" id="display_name" value="<?php echo html_escape(set_value('display_name')); ?>" maxlength="120" required<?php echo study_field_error_attributes($form_errors, 'display_name'); ?>><?php echo study_render_field_error($form_errors, 'display_name'); ?></div>
			<div class="mb-3"><?php echo study_grade_select(set_value('grade_id'), 'grade_id', TRUE); ?><p class="form-text">Pilih kelas anak. Materi akan mengikuti kelas ini, dan anak tetap bisa menjelajahi kelas lain.</p></div>
			<div class="mb-3"><label class="form-label" for="username">Nama pengguna</label><input class="form-control form-control-lg" type="text" name="username" id="username" value="<?php echo html_escape(set_value('username')); ?>" minlength="4" maxlength="80" autocomplete="off" required<?php echo study_field_error_attributes($form_errors, 'username'); ?>><?php echo study_render_field_error($form_errors, 'username'); ?></div>
			<div class="mb-3"><label class="form-label" for="password">Kata sandi awal</label><input class="form-control form-control-lg" type="password" name="password" id="password" maxlength="72" autocomplete="new-password" required<?php echo study_field_error_attributes($form_errors, 'password'); ?>><?php echo study_render_field_error($form_errors, 'password'); ?></div>
			<div class="mb-4"><label class="form-label" for="password_confirm">Ulangi kata sandi awal</label><input class="form-control form-control-lg" type="password" name="password_confirm" id="password_confirm" maxlength="72" autocomplete="new-password" required<?php echo study_field_error_attributes($form_errors, 'password_confirm'); ?>><?php echo study_render_field_error($form_errors, 'password_confirm'); ?></div>
			<button class="btn btn-primary btn-lg rounded-pill w-100" type="submit">Buat akun anak</button>
		<?php echo form_close(); ?>
	</div>
</section>
