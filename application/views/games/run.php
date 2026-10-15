<section class="container page-section">
	<a class="back-link" href="<?php echo site_url('permainan/riwayat'); ?>">Riwayat permainan saya</a>
	<div class="page-heading mt-3"><div><p class="eyebrow">Cocokkan Pasangan · Tersimpan</p><h1 class="h2 practice-question"><?php echo html_escape($run['title_snapshot']); ?></h1><p class="mb-0"><?php echo html_escape(study_matching_status_label($run['status'])); ?> · Mulai <?php echo html_escape(study_format_datetime($run['started_at'])); ?></p></div></div>
	<div class="feature-card matching-game">
		<?php if ($submit_error): ?><div class="alert alert-warning" role="alert"><?php echo html_escape($submit_error); ?></div><?php endif; ?>
		<p id="hasil-pilihan" class="matching-message" tabindex="-1" role="status"><?php echo html_escape($feedback ?: ($run['status'] === 'completed' ? 'Semua pasangan ditemukan. Terima kasih sudah mencoba!' : 'Progres di bawah sudah tersimpan.')); ?></p>
		<label for="saved-matching-progress">Pasangan ditemukan: <?php echo count($state['matched']); ?> dari <?php echo count($snapshot['cards']); ?></label><progress class="w-100" id="saved-matching-progress" value="<?php echo count($state['matched']); ?>" max="<?php echo count($snapshot['cards']); ?>"></progress>
		<p class="small text-secondary"><?php echo (int) $state['move_count']; ?> pilihan dikirim · <?php echo (int) $state['mistake_count']; ?> belum cocok. Ini catatan latihan, bukan nilai ujian.</p>
		<?php if ($can_continue && $state['move_count'] < 10000): ?>
			<h2 class="h4">Babak <?php echo (int) $state['round_index'] + 1; ?> dari <?php echo count($snapshot['rounds']); ?></h2>
			<p id="saved-matching-help">Pilih satu kartu di setiap kolom, lalu tekan <strong>Periksa pasangan</strong>. Dengan keyboard, gunakan Tab dan tombol panah untuk memilih.</p>
			<?php echo form_open('permainan/tersimpan/'.$run['id'].'#hasil-pilihan', array('id' => 'saved-matching-form', 'aria-describedby' => 'saved-matching-help')); ?>
			<input type="hidden" name="revision" value="<?php echo (int) $run['revision']; ?>"><input type="hidden" name="action_token" value="<?php echo html_escape($action_token); ?>">
			<div class="matching-columns">
			<?php foreach (array('left' => $left_order, 'right' => $right_order) as $side => $order): ?>
				<fieldset class="matching-fieldset"><legend class="h5"><?php echo $side === 'left' ? 'Kartu' : 'Pasangan'; ?></legend><div class="matching-choices">
				<?php foreach ($order as $card_index): $matched = in_array($card_index, $state['matched'], TRUE); ?>
					<?php if ($matched): ?><div class="matching-choice is-matched"><span class="d-block small mb-1">✓ Cocok</span><?php echo html_escape($snapshot['cards'][$card_index][$side === 'left' ? 'front' : 'back']); ?></div>
					<?php else: ?><label class="matching-choice saved-matching-choice"><input type="radio" name="<?php echo $side; ?>" value="<?php echo (int) $card_index; ?>" required><span><?php echo html_escape($snapshot['cards'][$card_index][$side === 'left' ? 'front' : 'back']); ?></span></label><?php endif; ?>
				<?php endforeach; ?>
				</div></fieldset>
			<?php endforeach; ?>
			</div>
			<button class="btn btn-primary rounded-pill mt-4" type="submit">Periksa pasangan</button>
			<p id="matching-save-state" class="matching-message mt-3 mb-0" role="status" aria-live="polite" aria-atomic="true"></p>
			<a id="matching-save-recovery" class="btn btn-outline-primary rounded-pill mt-2" href="<?php echo site_url('permainan/tersimpan/'.$run['id'].'#hasil-pilihan'); ?>" hidden>Buka progres terbaru</a>
			<p class="small text-secondary mt-2 mb-0">Tunggu halaman menampilkan progres terbaru sebelum memilih lagi. Jika pengiriman terputus, muat ulang halaman; pasangan tidak dihitung dua kali.</p>
			<?php echo form_close(); ?>
		<?php elseif ($run['status'] === 'in_progress'): ?><div class="alert alert-info">Permainan belum dapat dilanjutkan karena akses materi berubah atau batas teknis langkah tercapai. Progres tetap tersimpan. Periksa akses materi, atau hentikan permainan sebelum memulai yang baru.</div>
		<?php elseif ($run['status'] === 'completed'): ?><p class="mb-0">Rentang waktu sejak mulai: <?php echo (int) $run['elapsed_seconds']; ?> detik, termasuk jeda. Ini bukan pengukuran waktu belajar aktif.</p>
		<?php else: ?><p class="mb-0">Permainan ini telah dihentikan. Pasangan yang ditemukan tetap dicatat, tanpa dianggap sebagai permainan selesai.</p><?php endif; ?>
	</div>
	<div class="d-flex flex-wrap gap-2 mt-4">
		<?php if ($run['status'] === 'in_progress'): ?><a class="btn btn-outline-secondary rounded-pill" href="<?php echo site_url('permainan/tersimpan/'.$run['id'].'/berhenti'); ?>">Hentikan permainan</a><?php endif; ?>
		<?php if ($lesson && $lesson['lesson_status'] === 'published' && $lesson['course_status'] === 'published'): ?><a class="btn btn-outline-primary rounded-pill" href="<?php echo site_url('materi/'.$lesson['slug']); ?>">Buka materi</a><?php if ($run['status'] !== 'in_progress'): ?><a class="btn btn-primary rounded-pill" href="<?php echo site_url('permainan/tersimpan/pelajaran/'.$lesson['slug']); ?>">Berlatih lagi</a><?php endif; ?><?php endif; ?>
	</div>
</section>
