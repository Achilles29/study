<section class="container page-section">
	<a class="back-link" href="<?php echo site_url('kelola/kursus/'.$course['id']); ?>">Kembali ke kursus</a>
	<div class="page-heading mt-3"><div><p class="eyebrow"><?php echo html_escape($course['title']); ?></p><h1 class="h2">Soal latihan</h1><p class="text-secondary">Empat pilihan, satu jawaban benar. Soal dan pembahasan ikut review kursus sebelum terbit.</p></div><span class="badge text-bg-light"><?php echo (int) $total_questions; ?> dari 50 soal</span></div>
	<?php if ($submit_error): ?><div class="alert alert-warning" role="alert"><?php echo html_escape($submit_error); ?></div><?php endif; ?>
	<div class="row g-4">
		<div class="<?php echo $can_edit ? 'col-lg-6' : 'col-12'; ?>">
			<h2 class="h4">Daftar soal</h2>
			<?php if ( ! $questions): ?><div class="empty-card"><h3 class="h5">Belum ada soal latihan</h3><p class="text-secondary mb-0">Kursus tetap dapat diterbitkan tanpa latihan. Tambahkan soal bila diperlukan untuk tujuan belajar.</p></div><?php endif; ?>
			<?php foreach ($questions as $question): ?>
				<article class="feature-card mb-3 practice-question" data-question-id="<?php echo (int) $question['id']; ?>">
					<h3 class="h5"><?php echo nl2br(html_escape($question['prompt'])); ?></h3>
					<ol type="A"><?php foreach ($question['options'] as $option): ?><li><?php echo html_escape($option['option_text']); ?><?php if ((int) $option['is_correct'] === 1): ?> <span class="badge text-bg-success">Jawaban benar</span><?php endif; ?></li><?php endforeach; ?></ol>
					<details><summary>Pembahasan dan sumber</summary><p class="mt-2"><?php echo nl2br(html_escape($question['explanation'])); ?></p><p class="small text-secondary"><?php echo html_escape(study_rights_status_label($question['rights_status'])); ?> · <?php echo html_escape($question['source_note']); ?> · Versi <?php echo (int) $question['content_version']; ?></p></details>
					<?php if ($can_edit): ?><div class="d-flex flex-wrap gap-3 align-items-start mt-3"><a class="btn btn-sm btn-outline-primary rounded-pill" href="<?php echo site_url('kelola/kursus/'.$course['id'].'/soal?edit='.$question['id'].'#form-soal'); ?>">Edit soal</a><details><summary>Hapus soal</summary><?php echo form_open('kelola/kursus/'.$course['id'].'/soal', array('class' => 'mt-2')); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="question_id" value="<?php echo (int) $question['id']; ?>"><label class="d-block small mb-2"><input type="checkbox" name="confirm_delete" value="1" required> Hapus soal ini dari draf</label><button class="btn btn-sm btn-outline-danger rounded-pill" type="submit" aria-label="Konfirmasi hapus: <?php echo html_escape(mb_strimwidth($question['prompt'], 0, 80, '…', 'UTF-8')); ?>">Konfirmasi hapus</button><?php echo form_close(); ?></details></div><?php endif; ?>
				</article>
			<?php endforeach; ?>
			<?php $pagination_base = 'kelola/kursus/'.$course['id'].'/soal'; $this->load->view('practice/pagination', compact('pagination_base', 'page', 'page_count')); ?>
		</div>
		<?php if ($can_edit): ?>
			<div class="col-lg-6"><section class="feature-card" id="form-soal" aria-labelledby="form-soal-heading">
				<h2 class="h4" id="form-soal-heading"><?php echo $selected_question ? 'Edit soal' : 'Tambah soal'; ?></h2>
				<?php echo form_open('kelola/kursus/'.$course['id'].'/soal'); ?>
					<input type="hidden" name="action" value="save"><input type="hidden" name="question_id" value="<?php echo $selected_question ? (int) $selected_question['id'] : 0; ?>">
					<?php foreach (array('prompt' => array('Pertanyaan', 3000), 'explanation' => array('Pembahasan setelah latihan selesai', 3000), 'source_note' => array('Sumber atau keterangan karya sendiri', 1000)) as $field => $settings): ?>
						<div class="mb-3"><label class="form-label" for="question-<?php echo $field; ?>"><?php echo html_escape($settings[0]); ?></label><textarea class="form-control" id="question-<?php echo $field; ?>" name="<?php echo $field; ?>" rows="3" maxlength="<?php echo $settings[1]; ?>" required<?php echo study_field_error_attributes($form_errors, $field); ?>><?php echo html_escape(set_value($field, $selected_question ? $selected_question[$field] : '', FALSE)); ?></textarea><?php echo study_render_field_error($form_errors, $field); ?></div>
					<?php endforeach; ?>
					<?php $correct_default = ''; foreach (range(0, 3) as $index): $field = 'option_'.$index; $existing_option = $selected_question && isset($selected_question['options'][$index]) ? $selected_question['options'][$index] : NULL; if ($existing_option && (int) $existing_option['is_correct'] === 1) { $correct_default = (string) $index; } ?>
						<div class="mb-3"><label class="form-label" for="<?php echo $field; ?>">Pilihan <?php echo chr(65 + $index); ?></label><textarea class="form-control" id="<?php echo $field; ?>" name="<?php echo $field; ?>" rows="2" maxlength="1000" required<?php echo study_field_error_attributes($form_errors, $field); ?>><?php echo html_escape(set_value($field, $existing_option ? $existing_option['option_text'] : '', FALSE)); ?></textarea><?php echo study_render_field_error($form_errors, $field); ?></div>
					<?php endforeach; ?>
					<div class="mb-3"><label class="form-label" for="correct">Jawaban benar</label><select class="form-select" name="correct" id="correct" required<?php echo study_field_error_attributes($form_errors, 'correct'); ?>><option value="">Pilih satu jawaban</option><?php foreach (range(0, 3) as $index): ?><option value="<?php echo $index; ?>"<?php echo (string) set_value('correct', $correct_default, FALSE) === (string) $index ? ' selected' : ''; ?>>Pilihan <?php echo chr(65 + $index); ?></option><?php endforeach; ?></select><?php echo study_render_field_error($form_errors, 'correct'); ?></div>
					<div class="mb-3"><label class="form-label" for="rights-status">Hak penggunaan</label><select class="form-select" name="rights_status" id="rights-status" required<?php echo study_field_error_attributes($form_errors, 'rights_status'); ?>><option value="">Pilih status hak</option><?php foreach (array('owned', 'licensed', 'public_domain') as $right): ?><option value="<?php echo $right; ?>"<?php echo set_value('rights_status', $selected_question ? $selected_question['rights_status'] : '', FALSE) === $right ? ' selected' : ''; ?>><?php echo html_escape(study_rights_status_label($right)); ?></option><?php endforeach; ?></select><?php echo study_render_field_error($form_errors, 'rights_status'); ?></div>
					<div class="d-flex flex-wrap gap-2"><button class="btn btn-primary rounded-pill" type="submit">Simpan soal</button><?php if ($selected_question): ?><a class="btn btn-light rounded-pill" href="<?php echo site_url('kelola/kursus/'.$course['id'].'/soal'); ?>">Batal edit</a><?php endif; ?></div>
				<?php echo form_close(); ?>
			</section></div>
		<?php endif; ?>
	</div>
</section>
