<section class="container page-section">
	<a class="back-link" href="<?php echo site_url('keluarga/latihan/'.$source_session['id']); ?>">Kembali ke sesi sumber</a>
	<div class="page-heading mt-3"><div><p class="eyebrow">Latihan keluarga</p><h1 class="h2">Salin menjadi latihan baru</h1><p class="text-secondary">Gunakan kembali soal untuk belajar ulang atau untuk anak lain dalam keluarga.</p></div></div>
	<div class="row g-4"><div class="col-lg-5"><div class="feature-card"><h2 class="h4">Sesi sumber</h2><p class="fw-semibold"><?php echo html_escape($source_session['title']); ?></p><p>Untuk <?php echo html_escape($source_session['child_name']); ?> · <?php echo html_escape(study_practice_status_label($source_session)); ?></p><p class="mb-0">Soal, pilihan, kunci, pembahasan dan sumber akan disalin. Jika sudah dikirim, isi memakai salinan saat pengiriman. Jadwal, jawaban anak dan nilai tidak ikut disalin.</p></div></div>
		<div class="col-lg-7"><div class="feature-card">
			<?php if ($submit_error): ?><div class="alert alert-warning" role="alert"><?php echo html_escape($submit_error); ?></div><?php endif; ?>
			<?php if ( ! $can_copy): ?><div class="alert alert-info mb-0">Hak paket penyusun latihan tidak aktif. Sesi sumber tetap tersimpan, tetapi belum dapat disalin.</div><?php else: ?>
				<h2 class="h4">Draf baru</h2><p class="text-secondary">Salinan berdiri sendiri. Mengubahnya tidak akan mengubah sesi sumber. Periksa isi dan kesesuaian usia sebelum mengirim.</p>
				<?php echo form_open('keluarga/latihan/'.$source_session['id'].'/salin'); ?>
				<input type="hidden" name="copy_token" value="<?php echo html_escape($copy_token); ?>"><input type="hidden" name="revision" value="<?php echo (int) $source_session['draft_revision']; ?>">
				<div class="mb-3"><label class="form-label" for="copy-title">Nama latihan baru</label><input class="form-control" id="copy-title" name="title" required maxlength="180" value="<?php echo html_escape(set_value('title', mb_substr($source_session['title'], 0, 170, 'UTF-8').' — salinan', FALSE)); ?>"<?php echo study_field_error_attributes($form_errors, 'title'); ?>><?php echo study_render_field_error($form_errors, 'title'); ?></div>
				<div class="mb-3"><label class="form-label" for="copy-child">Anak tujuan</label><select class="form-select" id="copy-child" name="child_id" required<?php echo study_field_error_attributes($form_errors, 'child_id'); ?>><?php foreach ($children as $child): ?><option value="<?php echo (int) $child['id']; ?>"<?php echo (string) set_value('child_id', $source_session['learner_user_id'], FALSE) === (string) $child['id'] ? ' selected' : ''; ?>><?php echo html_escape($child['display_name']); ?></option><?php endforeach; ?></select><?php echo study_render_field_error($form_errors, 'child_id'); ?></div>
				<label class="d-block mb-3"><input type="checkbox" name="confirm" value="1" required> Saya akan memeriksa salinan sebelum mengirimkannya</label>
				<div class="d-flex flex-wrap gap-2"><button class="btn btn-primary rounded-pill" type="submit">Buat salinan draf</button><a class="btn btn-light rounded-pill" href="<?php echo site_url('keluarga/latihan/'.$source_session['id']); ?>">Batal</a></div>
				<?php echo form_close(); ?>
			<?php endif; ?>
		</div></div>
	</div>
</section>
