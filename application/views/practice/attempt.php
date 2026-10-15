<?php
$graded = $attempt['status'] === 'graded';
$family_report = isset($report_child);
$editable = $attempt['status'] === 'in_progress' && ! $family_report;
$history_path = $family_report ? 'keluarga/anak/'.$report_child['id'].'/latihan' : 'latihan';
$history_path = isset($history_path_override) ? $history_path_override : $history_path;
?>
<section class="container page-section">
	<div class="row justify-content-center"><div class="col-lg-9 col-xl-8">
		<a class="back-link" href="<?php echo site_url($history_path); ?>"><?php echo $family_report ? 'Kembali ke laporan anak' : 'Riwayat latihan saya'; ?></a>
		<?php if ($family_report): ?><div class="alert alert-info mt-3"><strong>Hasil latihan <?php echo html_escape($report_child['display_name']); ?></strong><p class="mb-0">Laporan ini hanya untuk dibaca. Dampingi anak memahami pembahasan tanpa mengubah jawabannya.</p></div><?php endif; ?>
		<div class="feature-card mt-3 mb-4"><p class="eyebrow"><?php echo $graded ? 'Hasil latihan' : ($attempt['session_type'] === 'parent_assigned' ? 'Latihan keluarga' : 'Latihan mandiri'); ?></p><h1 class="h2"><?php echo html_escape($attempt['title']); ?></h1>
			<?php if ($graded): ?><p class="h3 mt-3"><?php echo (int) $attempt['correct_count']; ?> dari <?php echo (int) $attempt['question_count']; ?> jawaban benar · <?php echo html_escape(str_replace('.', ',', $attempt['score'])); ?>%</p><p class="text-secondary mb-0"><?php echo $family_report ? 'Apresiasi usaha anak. Gunakan pembahasan ini untuk mendampingi, bukan membandingkan nilainya dengan anak lain.' : 'Terima kasih sudah berlatih. Baca pembahasan untuk memahami langkahnya, lalu coba lagi saat siap.'; ?></p><?php else: ?><p class="text-secondary mb-0">Pilih satu jawaban untuk setiap soal. Simpan sementara agar pilihanmu bisa dilanjutkan. Pembahasan muncul setelah kamu menyelesaikan latihan.</p><?php endif; ?>
		</div>
		<?php if ($submit_error): ?><div class="alert alert-warning" role="alert"><?php echo html_escape($submit_error); ?></div><?php endif; ?>
		<?php if ($editable): ?>
			<?php echo form_open('latihan/percobaan/'.$attempt['id'], array('id' => 'practice-answer-form', 'data-unsaved-form' => '', 'data-unsaved-initial' => 'false')); ?>
			<input type="hidden" name="revision" value="<?php echo (int) $attempt['answer_revision']; ?>">
			<div class="alert alert-info"><p class="mb-0">Pilihan jawaban tidak tersimpan otomatis. Gunakan Simpan sementara sebelum beristirahat.</p><p class="fw-semibold mt-2 mb-0" data-unsaved-status role="status" aria-live="polite" hidden></p></div>
		<?php endif; ?>
		<?php foreach ($questions as $index => $question): ?>
			<fieldset class="feature-card practice-question mb-4" data-question-id="<?php echo (int) $question['id']; ?>">
				<legend class="h5 float-none w-auto px-2">Soal <?php echo $index + 1; ?> dari <?php echo count($questions); ?></legend>
				<p class="fw-semibold"><?php echo nl2br(html_escape($question['prompt'])); ?></p>
				<?php foreach ($question['options'] as $option): ?>
							<?php if ( ! $editable): ?><div class="practice-option mb-2"><span><?php echo html_escape($option['option_text']); ?><?php if ((int) $question['selected_option_id'] === (int) $option['id']): ?> <span class="badge text-bg-light"><?php echo $family_report ? 'Pilihan anak' : 'Pilihanmu'; ?></span><?php endif; ?><?php if ($graded && $option['is_correct']): ?> <span class="badge text-bg-success">Jawaban benar</span><?php endif; ?></span></div><?php else: ?>
						<label class="practice-option mb-2"><input type="radio" name="answers[<?php echo (int) $question['id']; ?>]" value="<?php echo (int) $option['id']; ?>"<?php echo (int) $question['selected_option_id'] === (int) $option['id'] ? ' checked' : ''; ?>><span><?php echo html_escape($option['option_text']); ?></span></label>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php if ($graded): ?><div class="alert <?php echo $question['is_correct'] ? 'alert-success' : 'alert-info'; ?> mt-3 mb-0"><strong><?php echo $question['is_correct'] ? 'Sudah tepat.' : ($question['selected_option_id'] === NULL ? 'Belum dijawab. Yuk, pelajari caranya.' : 'Mari tinjau langkahnya.'); ?></strong><p class="mb-0 mt-2"><?php echo nl2br(html_escape($question['explanation'])); ?></p></div><?php elseif ($editable): ?><label class="practice-option small text-secondary"><input type="radio" name="answers[<?php echo (int) $question['id']; ?>]" value=""<?php echo $question['selected_option_id'] === NULL ? ' checked' : ''; ?>><span>Belum dijawab</span></label><?php endif; ?>
			</fieldset>
		<?php endforeach; ?>
		<?php if ($editable): ?><div class="feature-card"><p>Setelah diselesaikan, jawaban percobaan ini tidak bisa diubah. Soal yang belum dijawab dihitung belum benar.</p><div class="d-flex flex-column flex-sm-row gap-2"><button class="btn btn-outline-primary rounded-pill" type="submit" name="action" value="save">Simpan sementara</button><button class="btn btn-primary rounded-pill" type="submit" name="action" value="finish">Selesaikan dan lihat pembahasan</button></div></div><?php echo form_close(); ?><?php else: ?><div class="d-flex flex-wrap gap-2"><?php if ($graded && $can_retry && ! $family_report): ?><a class="btn btn-primary rounded-pill" href="<?php echo site_url('latihan/kursus/'.$attempt['course_id']); ?>">Coba latihan lagi</a><?php elseif ( ! $family_report): ?><p class="text-secondary">Hasil tetap tersimpan. Latihan baru mengikuti akses atau sesi yang tersedia.</p><?php endif; ?><a class="btn btn-outline-primary rounded-pill" href="<?php echo site_url($history_path); ?>">Lihat riwayat</a></div><?php endif; ?>
	</div></div>
</section>
