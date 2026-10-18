<?php $structure_path = 'kelola/kursus/'.$course['id'].'/susunan'; $form_revision = $posted_fields['revision'] ?? $course['content_version']; $section_query = $selected_section ? '&bagian='.(int) $selected_section['id'] : ''; $section_ids = array_map('strval', array_column($sections, 'id')); ?>
<section class="container page-section course-workspace">
	<a class="back-link" href="<?php echo site_url('kelola/kursus/'.$course['id']); ?>">Kembali ke kursus</a>
	<div class="page-heading mt-3"><div><p class="eyebrow">Susunan draf · versi <?php echo (int) $course['content_version']; ?></p><h1 class="h2">Susun bagian dan pelajaran</h1><p class="text-secondary mb-0"><?php echo html_escape($course['title']); ?></p></div></div>
	<p>Nomor lebih kecil tampil lebih dahulu. Jika nomornya sama, urutan mengikuti ID bagian lalu ID pelajaran. Perubahan tidak menghapus materi atau progres. Periksa susunan sebelum mengajukan review.</p>
	<?php if ($submit_error): ?><div class="alert alert-warning" role="alert"><?php echo html_escape($submit_error); ?> <a href="<?php echo html_escape(site_url($structure_context_path)); ?>">Muat ulang susunan</a></div><?php endif; ?>
	<div class="row g-4">
		<div class="col-lg-5">
			<section class="feature-card mb-4"><h2 class="h5">Bagian kursus</h2><p class="small mb-3"><?php echo count($sections); ?> dari maksimal 100 bagian. Bagian kosong boleh disiapkan lebih dahulu.</p>
				<?php if ($sections): ?>
				<form method="get" action="<?php echo site_url($structure_path); ?>" class="mb-3"><input type="hidden" name="halaman" value="<?php echo (int) $page; ?>"><label class="form-label" for="section-choice">Bagian yang ingin diubah</label><select class="form-select" name="bagian" id="section-choice"><?php foreach ($sections as $section): ?><option value="<?php echo (int) $section['id']; ?>"<?php echo (int) $section['id'] === (int) $selected_section['id'] ? ' selected' : ''; ?>><?php echo (int) $section['sort_order']; ?> · <?php echo html_escape($section['title']); ?></option><?php endforeach; ?></select><button class="btn btn-light mt-2" type="submit">Tampilkan bagian</button></form>
				<?php if ($has_invalid_section): ?><p class="alert alert-warning" role="alert">Bagian pada kiriman tidak tersedia untuk kursus ini. Pilih bagian lalu tekan Tampilkan bagian sebelum mengedit kembali.</p><?php else: ?>
				<?php $editing = $active_action === 'update_section'; $section_errors = $editing ? $form_errors : array(); ?>
				<?php echo form_open(html_escape($structure_context_path)); ?><fieldset<?php echo $has_conflict ? ' disabled' : ''; ?>><legend class="visually-hidden">Ubah bagian terpilih</legend>
					<input type="hidden" name="action" value="update_section"><input type="hidden" name="revision" value="<?php echo html_escape($form_revision); ?>"><input type="hidden" name="section_id" value="<?php echo html_escape($editing ? $posted_fields['section_id'] : $selected_section['id']); ?>">
					<label class="form-label" for="edit-title">Judul bagian</label><input class="form-control" id="edit-title" name="title" maxlength="180" required value="<?php echo html_escape($editing ? ($posted_fields['title'] ?? '') : $selected_section['title']); ?>"<?php echo study_field_error_attributes($section_errors, 'title'); ?>><?php echo study_render_field_error($section_errors, 'title'); ?>
					<label class="form-label mt-3" for="edit-order">Nomor urut bagian</label><input class="form-control" id="edit-order" name="sort_order" type="number" min="1" max="9999" required value="<?php echo html_escape($editing ? ($posted_fields['sort_order'] ?? '') : $selected_section['sort_order']); ?>"<?php echo study_field_error_attributes($section_errors, 'sort_order'); ?>><?php echo study_render_field_error($section_errors, 'sort_order'); ?>
					<button class="btn btn-primary rounded-pill mt-3" type="submit">Simpan bagian</button>
				</fieldset><?php echo form_close(); ?>
				<?php endif; ?>
				<?php else: ?><p>Belum ada bagian. Tambahkan bagian pertama di bawah.</p><?php endif; ?>
			</section>
			<details class="feature-card"<?php echo $active_action === 'create_section' ? ' open' : ''; ?>><summary class="fw-semibold">Tambah bagian baru</summary>
				<?php $creating = $active_action === 'create_section'; $create_errors = $creating ? $form_errors : array(); ?>
				<?php echo form_open(html_escape($structure_context_path), array('class' => 'mt-3')); ?><fieldset<?php echo $has_conflict ? ' disabled' : ''; ?>><legend class="visually-hidden">Bagian baru</legend>
					<input type="hidden" name="action" value="create_section"><input type="hidden" name="revision" value="<?php echo html_escape($form_revision); ?>">
					<label class="form-label" for="create-title">Judul bagian baru</label><input class="form-control" id="create-title" name="title" maxlength="180" required value="<?php echo html_escape($creating ? ($posted_fields['title'] ?? '') : ''); ?>"<?php echo study_field_error_attributes($create_errors, 'title'); ?>><?php echo study_render_field_error($create_errors, 'title'); ?>
					<label class="form-label mt-3" for="create-order">Nomor urut bagian baru</label><input class="form-control" id="create-order" name="sort_order" type="number" min="1" max="9999" required value="<?php echo html_escape($creating ? ($posted_fields['sort_order'] ?? '') : min(9999, count($sections) + 1)); ?>"<?php echo study_field_error_attributes($create_errors, 'sort_order'); ?>><?php echo study_render_field_error($create_errors, 'sort_order'); ?>
					<button class="btn btn-primary rounded-pill mt-3" type="submit">Tambah bagian</button>
				</fieldset><?php echo form_close(); ?>
			</details>
		</div>
		<div class="col-lg-7"><h2 class="h5">Urutan pelajaran</h2><p class="text-secondary"><?php echo (int) $total_lessons; ?> pelajaran · halaman <?php echo (int) $page; ?> dari <?php echo (int) $page_count; ?></p><p class="small">Setelah disimpan, halaman daftar tetap sama. Pelajaran yang dipindahkan mungkin tampil di halaman lain sesuai urutan barunya.</p>
			<?php if ( ! $lessons): ?><div class="empty-card"><h3 class="h5">Belum ada pelajaran</h3><p>Tambahkan pelajaran dari halaman kursus, kemudian atur bagian dan urutannya di sini.</p></div><?php endif; ?>
			<?php foreach ($lessons as $lesson): $moving = $active_action === 'move_lesson' && (string) ($posted_fields['lesson_id'] ?? '') === (string) $lesson['id']; $move_errors = $moving ? $form_errors : array(); $invalid_destination = $moving && ! in_array((string) ($posted_fields['section_id'] ?? ''), $section_ids, TRUE); ?>
				<article class="feature-card mb-3"><h3 class="h6 mt-0"><?php echo html_escape($lesson['title']); ?></h3><p class="small mb-3"><?php echo html_escape($lesson['section_title']); ?> · urutan <?php echo (int) $lesson['sort_order']; ?></p>
					<?php echo form_open(html_escape($structure_context_path)); ?><fieldset<?php echo $has_conflict ? ' disabled' : ''; ?>><legend class="visually-hidden">Pindahkan <?php echo html_escape($lesson['title']); ?></legend>
						<input type="hidden" name="action" value="move_lesson"><input type="hidden" name="revision" value="<?php echo html_escape($form_revision); ?>"><input type="hidden" name="lesson_id" value="<?php echo (int) $lesson['id']; ?>">
						<div class="row g-3"><div class="col-sm-8"><label class="form-label" for="lesson-section-<?php echo (int) $lesson['id']; ?>">Bagian tujuan</label><select class="form-select" name="section_id" id="lesson-section-<?php echo (int) $lesson['id']; ?>" required<?php echo $invalid_destination ? ' aria-invalid="true" aria-describedby="lesson-section-error-'.(int) $lesson['id'].'"' : ''; ?>><?php if ($invalid_destination): ?><option value="" selected disabled>Pilih kembali bagian tujuan</option><?php endif; ?><?php foreach ($sections as $section): ?><option value="<?php echo (int) $section['id']; ?>"<?php echo (string) $section['id'] === (string) ($moving ? $posted_fields['section_id'] : $lesson['section_id']) ? ' selected' : ''; ?>><?php echo (int) $section['sort_order']; ?> · <?php echo html_escape($section['title']); ?></option><?php endforeach; ?></select><?php if ($invalid_destination): ?><p class="text-danger small mt-2" id="lesson-section-error-<?php echo (int) $lesson['id']; ?>">Bagian tujuan pada kiriman tidak tersedia untuk kursus ini.</p><?php endif; ?></div>
						<div class="col-sm-4"><label class="form-label" for="lesson-order-<?php echo (int) $lesson['id']; ?>">Nomor urut</label><input class="form-control" name="sort_order" id="lesson-order-<?php echo (int) $lesson['id']; ?>" type="number" min="1" max="9999" required value="<?php echo html_escape($moving ? ($posted_fields['sort_order'] ?? '') : $lesson['sort_order']); ?>"<?php echo study_field_error_attributes($move_errors, 'sort_order'); ?>><?php echo study_render_field_error($move_errors, 'sort_order'); ?></div></div>
						<button class="btn btn-outline-primary rounded-pill mt-3" type="submit">Simpan posisi pelajaran</button>
					</fieldset><?php echo form_close(); ?>
				</article>
			<?php endforeach; ?>
			<?php if ($page_count > 1): ?><nav class="d-flex flex-wrap gap-2" aria-label="Halaman susunan pelajaran"><?php if ($page > 1): ?><a class="btn btn-outline-primary" href="<?php echo html_escape(site_url($structure_path.'?halaman='.($page - 1).$section_query)); ?>">Sebelumnya</a><?php endif; ?><?php if ($page < $page_count): ?><a class="btn btn-outline-primary" href="<?php echo html_escape(site_url($structure_path.'?halaman='.($page + 1).$section_query)); ?>">Berikutnya</a><?php endif; ?></nav><?php endif; ?>
		</div>
	</div>
</section>
