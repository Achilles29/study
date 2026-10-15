<section class="container page-section"><div class="row justify-content-center"><div class="col-lg-7">
	<div class="feature-card"><h1 class="h2">Hentikan permainan?</h1><p class="practice-question"><?php echo html_escape($run['title_snapshot']); ?></p>
		<?php if ($submit_error): ?><div class="alert alert-warning" role="alert"><?php echo html_escape($submit_error); ?></div><?php endif; ?>
		<?php if ($run['status'] === 'in_progress'): ?><p>Jika hanya ingin beristirahat, cukup tinggalkan halaman. Kamu bisa melanjutkan dari riwayat selama akses materi masih aktif.</p><p>Menghentikan permainan akan menyimpan catatan sebagai <strong>Dihentikan</strong>. Kamu tidak bisa melanjutkannya atau menganggapnya selesai. Kamu dapat memulai permainan baru setelahnya.</p>
		<?php echo form_open('permainan/tersimpan/'.$run['id'].'/berhenti'); ?><input type="hidden" name="revision" value="<?php echo (int) $run['revision']; ?>"><input type="hidden" name="action_token" value="<?php echo html_escape($action_token); ?>"><label class="practice-option"><input type="checkbox" name="confirm" value="1" required><span>Saya ingin menghentikan permainan ini, bukan sekadar beristirahat.</span></label><button class="btn btn-outline-danger rounded-pill mt-3" type="submit">Ya, hentikan permainan</button><?php echo form_close(); ?>
		<?php else: ?><p>Permainan sudah <?php echo html_escape(strtolower(study_matching_status_label($run['status']))); ?> dan tidak dapat dihentikan lagi.</p><?php endif; ?>
		<a class="btn btn-primary rounded-pill mt-3" href="<?php echo site_url('permainan/tersimpan/'.$run['id']); ?>">Kembali ke permainan</a>
	</div>
</div></div></section>
