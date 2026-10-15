<section class="auth-shell container py-5">
	<div class="auth-card auth-card-wide mx-auto">
		<a class="back-link" href="<?php echo site_url('kelola/kursus/'.$course['id']); ?>"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke kursus</a>
		<div class="mt-4 mb-4">
			<p class="eyebrow">Draf kursus · <?php echo html_escape($course['title']); ?></p>
			<h1 class="h2">Kartu belajar</h1>
			<p class="text-secondary">Set dan seluruh kartunya akan ikut diperiksa dalam review kursus. Isi kartu memakai teks polos dan hanya tersedia setelah kursus terbit.</p>
		</div>
		<?php if ($submit_error): ?><div class="alert alert-warning" role="alert"><?php echo html_escape($submit_error); ?></div><?php endif; ?>
		<section class="feature-card mb-4" aria-labelledby="nama-set-heading">
			<h2 class="h5" id="nama-set-heading">Set kartu untuk: <?php echo html_escape($lesson['title']); ?></h2>
			<?php echo form_open('kelola/kursus/'.$course['id'].'/pelajaran/'.$lesson['id'].'/flashcard'); ?>
				<input type="hidden" name="action" value="save_deck">
				<div class="mb-3"><label class="form-label" for="deck-title">Nama set kartu</label><input class="form-control" name="title" id="deck-title" maxlength="180" required value="<?php echo html_escape(set_value('title', $deck ? $deck['title'] : '')); ?>"<?php echo study_field_error_attributes($form_errors, 'title'); ?>><?php echo study_render_field_error($form_errors, 'title'); ?></div>
				<div class="mb-3"><label class="form-label" for="deck-description">Petunjuk singkat <span class="text-secondary">(opsional)</span></label><textarea class="form-control" name="description" id="deck-description" rows="2" maxlength="1000"<?php echo study_field_error_attributes($form_errors, 'description'); ?>><?php echo html_escape(set_value('description', $deck ? $deck['description'] : '')); ?></textarea><?php echo study_render_field_error($form_errors, 'description'); ?></div>
				<button class="btn btn-outline-primary rounded-pill" type="submit">Simpan nama set</button>
			<?php echo form_close(); ?>
		</section>
		<?php if ($deck): ?>
			<section class="feature-card mb-4" aria-labelledby="daftar-kartu-heading">
				<div class="d-flex justify-content-between align-items-start gap-3 mb-3"><div><h2 class="h5 mb-1" id="daftar-kartu-heading">Isi set</h2><p class="small text-secondary mb-0"><?php echo count($cards); ?> dari 100 kartu</p></div></div>
				<?php if ($cards): ?><ol class="list-group list-group-numbered list-group-flush">
					<?php foreach ($cards as $card): ?><li class="list-group-item px-0">
						<div class="d-flex flex-column flex-md-row justify-content-between gap-3">
							<div class="flex-grow-1"><strong><?php echo html_escape($card['front_text']); ?></strong><p class="mb-1 mt-1"><?php echo nl2br(html_escape($card['back_text'])); ?></p><?php if ($card['hint_text']): ?><p class="small text-secondary mb-0">Petunjuk: <?php echo html_escape($card['hint_text']); ?></p><?php endif; ?></div>
							<div class="d-flex align-items-start gap-2"><a class="btn btn-outline-secondary btn-sm rounded-pill" href="<?php echo site_url('kelola/kursus/'.$course['id'].'/pelajaran/'.$lesson['id'].'/flashcard?edit_kartu='.(int) $card['id']); ?>">Edit</a><?php echo form_open('kelola/kursus/'.$course['id'].'/pelajaran/'.$lesson['id'].'/flashcard'); ?><input type="hidden" name="action" value="delete_card"><input type="hidden" name="card_id" value="<?php echo (int) $card['id']; ?>"><button class="btn btn-outline-danger btn-sm rounded-pill" type="submit" aria-label="Hapus kartu <?php echo html_escape($card['front_text']); ?>">Hapus</button><?php echo form_close(); ?></div>
						</div>
					</li><?php endforeach; ?>
				</ol><?php else: ?><div class="alert alert-info mb-0">Belum ada kartu. Tambahkan minimal satu kartu agar kursus dapat diajukan untuk review.</div><?php endif; ?>
			</section>
			<section class="feature-card" aria-labelledby="form-kartu-heading">
				<h2 class="h5" id="form-kartu-heading"><?php echo $selected_card ? 'Edit kartu' : 'Tambah kartu'; ?></h2>
				<?php echo form_open('kelola/kursus/'.$course['id'].'/pelajaran/'.$lesson['id'].'/flashcard'); ?>
					<input type="hidden" name="action" value="save_card"><input type="hidden" name="card_id" value="<?php echo $selected_card ? (int) $selected_card['id'] : ''; ?>">
					<div class="mb-3"><label class="form-label" for="front-text">Sisi depan · istilah atau pertanyaan</label><textarea class="form-control" name="front_text" id="front-text" rows="2" maxlength="500" required<?php echo study_field_error_attributes($form_errors, 'front_text'); ?>><?php echo html_escape(set_value('front_text', $selected_card ? $selected_card['front_text'] : '')); ?></textarea><?php echo study_render_field_error($form_errors, 'front_text'); ?></div>
					<div class="mb-3"><label class="form-label" for="back-text">Sisi belakang · arti atau jawaban</label><textarea class="form-control" name="back_text" id="back-text" rows="3" maxlength="2000" required<?php echo study_field_error_attributes($form_errors, 'back_text'); ?>><?php echo html_escape(set_value('back_text', $selected_card ? $selected_card['back_text'] : '')); ?></textarea><?php echo study_render_field_error($form_errors, 'back_text'); ?></div>
					<div class="mb-3"><label class="form-label" for="hint-text">Petunjuk <span class="text-secondary">(opsional)</span></label><textarea class="form-control" name="hint_text" id="hint-text" rows="2" maxlength="500"<?php echo study_field_error_attributes($form_errors, 'hint_text'); ?>><?php echo html_escape(set_value('hint_text', $selected_card ? $selected_card['hint_text'] : '')); ?></textarea><?php echo study_render_field_error($form_errors, 'hint_text'); ?></div>
					<div class="mb-3"><label class="form-label" for="sort-order">Urutan tampil</label><input class="form-control" type="number" min="0" max="1000" name="sort_order" id="sort-order" value="<?php echo html_escape(set_value('sort_order', $selected_card ? $selected_card['sort_order'] : count($cards))); ?>" required<?php echo study_field_error_attributes($form_errors, 'sort_order'); ?>><?php echo study_render_field_error($form_errors, 'sort_order'); ?></div>
					<div class="d-flex flex-wrap justify-content-end gap-2"><a class="btn btn-light rounded-pill px-4" href="<?php echo site_url('kelola/kursus/'.$course['id'].'/pelajaran/'.$lesson['id'].'/flashcard'); ?>">Batal</a><button class="btn btn-primary rounded-pill px-4" type="submit"><?php echo $selected_card ? 'Simpan perubahan' : 'Tambahkan kartu'; ?></button></div>
				<?php echo form_close(); ?>
			</section>
		<?php else: ?><div class="alert alert-info">Simpan nama set terlebih dahulu sebelum menambahkan kartu.</div><?php endif; ?>
	</div>
</section>
