<section class="container page-section">
	<div class="page-heading">
		<div>
			<p class="eyebrow">Panel pengelola · <?php echo html_escape(study_course_status_label($course['status'])); ?></p>
			<h1 class="h2"><?php echo html_escape($course['title']); ?></h1>
			<p class="text-secondary mb-0"><?php echo html_escape($course['summary']); ?></p>
		</div>
		<a class="btn btn-light rounded-pill" href="<?php echo site_url('kelola/kursus'); ?>">Kembali ke daftar</a>
	</div>
	<div class="row g-4">
		<div class="col-lg-8">
			<div class="feature-card mb-4">
				<div class="d-flex justify-content-between align-items-start gap-3">
					<div>
						<h2 class="h5">Rincian kursus</h2>
						<p class="mb-1"><strong>Kelompok:</strong> <?php echo html_escape(study_target_group_label($course['target_group'])); ?></p>
						<?php $age_range_label = study_age_range_label($course['age_min'], $course['age_max']); if ($age_range_label !== ''): ?><p class="mb-1"><strong>Sasaran usia:</strong> <?php echo html_escape($age_range_label); ?></p><?php endif; ?>
						<p class="mb-1"><strong>Tujuan:</strong> <?php echo nl2br(html_escape($course['learning_objectives'])); ?></p>
						<?php if (trim((string) $course['prerequisites']) !== ''): ?><p class="mb-1"><strong>Prasyarat/persiapan:</strong> <?php echo nl2br(html_escape($course['prerequisites'])); ?></p><?php endif; ?>
						<p class="mb-1"><strong>Hak materi:</strong> <?php echo html_escape(study_rights_status_label($course['rights_status'])); ?></p>
						<p class="mb-0"><strong>Sumber:</strong> <?php echo html_escape($course['source_note']); ?></p>
						<a class="btn btn-outline-primary rounded-pill mt-3" href="<?php echo site_url('kelola/kursus/'.$course['id'].'/soal'); ?>"><?php echo $can_edit ? 'Kelola soal latihan' : 'Tinjau soal dan pembahasan'; ?></a>
					</div>
					<span class="badge rounded-pill text-bg-secondary">Versi <?php echo (int) $course['content_version']; ?></span>
				</div>
			</div>
			<div class="feature-card">
				<div class="d-flex justify-content-between align-items-center gap-3 mb-3">
					<div><h2 class="h5 mb-1">Pelajaran</h2><p class="small text-secondary mb-0">Teks, gambar pendamping, dan kartu belajar ditinjau sebagai satu versi kursus.</p></div>
					<?php if ($can_edit): ?><div class="d-flex flex-wrap gap-2">
							<a class="btn btn-outline-secondary btn-sm rounded-pill" href="<?php echo site_url('kelola/kursus/'.$course['id'].'/susunan'); ?>">Susun bagian dan pelajaran</a>
						<a class="btn btn-outline-secondary btn-sm rounded-pill" href="<?php echo site_url('kelola/kursus/'.$course['id'].'/edit'); ?>">Edit kursus</a>
						<a class="btn btn-outline-primary btn-sm rounded-pill" href="<?php echo site_url('kelola/kursus/'.$course['id'].'/pelajaran/tambah'); ?>">Tambah pelajaran</a>
					</div><?php endif; ?>
				</div>
				<?php if ($lessons): ?>
					<div class="list-group list-group-flush">
						<?php foreach ($lessons as $lesson): ?>
							<article class="list-group-item px-0">
								<div class="d-flex flex-column flex-sm-row justify-content-between gap-3">
									<div>
										<h3 class="h6 mb-1"><?php echo html_escape($lesson['title']); ?></h3>
										<p class="small text-secondary mb-2"><?php echo html_escape($lesson['section_title']); ?> · <?php echo html_escape(study_rights_status_label($lesson['rights_status'])); ?> · <?php echo (int) $lesson['estimated_minutes']; ?> menit<?php if ((int) $lesson['is_preview'] === 1): ?> · pratinjau gratis<?php endif; ?></p>
										<p class="mb-0 text-secondary"><?php $plain = trim(strip_tags($lesson['body_text'])); echo html_escape(mb_strimwidth($plain, 0, 280, '…', 'UTF-8')); ?></p>
									</div>
									<div class="d-flex flex-column align-items-end gap-2">
										<span class="badge text-bg-light"><?php echo html_escape(study_course_status_label($lesson['status'])); ?> · v<?php echo (int) $lesson['content_version']; ?></span>
										<a class="btn btn-outline-primary btn-sm rounded-pill" href="<?php echo site_url('kelola/kursus/'.$course['id'].'/pelajaran/'.$lesson['id'].'/pratinjau'); ?>">Lihat sebagai peserta</a>
										<?php if ($can_edit): ?>
											<a class="btn btn-outline-primary btn-sm rounded-pill" href="<?php echo site_url('kelola/kursus/'.$course['id'].'/pelajaran/'.$lesson['id'].'/edit'); ?>">Edit pelajaran</a>
											<a class="btn btn-outline-secondary btn-sm rounded-pill" href="<?php echo site_url('kelola/kursus/'.$course['id'].'/pelajaran/'.$lesson['id'].'/gambar'); ?>">Gambar pendamping</a>
											<a class="btn btn-outline-secondary btn-sm rounded-pill" href="<?php echo site_url('kelola/kursus/'.$course['id'].'/pelajaran/'.$lesson['id'].'/flashcard'); ?>">Kartu belajar</a>
										<?php endif; ?>
									</div>
								</div>
								<?php if (isset($lesson_images[(int) $lesson['id']])): $image_preview = $lesson_images[(int) $lesson['id']]; ?>
									<div class="border rounded-4 p-3 mt-3 bg-light-subtle"><h4 class="h6 mb-3">Gambar pendamping</h4>
										<figure class="lesson-image lesson-image-review mb-0">
											<img src="<?php echo html_escape(site_url('kelola/kursus/'.(int) $course['id'].'/pelajaran/'.(int) $lesson['id'].'/gambar/pratinjau')); ?>" alt="<?php echo html_escape($image_preview['alt_text']); ?>" width="<?php echo max(1, (int) $image_preview['width']); ?>" height="<?php echo max(1, (int) $image_preview['height']); ?>" loading="lazy" decoding="async">
											<figcaption class="small mt-3"><p><strong>Deskripsi gambar:</strong> <?php echo html_escape($image_preview['alt_text']); ?></p><?php if ($image_preview['caption'] !== ''): ?><p><strong>Keterangan:</strong> <?php echo nl2br(html_escape($image_preview['caption'])); ?></p><?php endif; ?><p><strong>Hak gambar:</strong> <?php echo html_escape(study_rights_status_label($image_preview['rights_status'])); ?></p><p><strong>Sumber:</strong> <?php echo nl2br(html_escape($image_preview['source_note'])); ?></p></figcaption>
										</figure>
									</div>
								<?php endif; ?>
								<?php if (isset($flashcard_decks[(int) $lesson['id']])): $deck_preview = $flashcard_decks[(int) $lesson['id']]; ?>
									<div class="border rounded-4 p-3 mt-3 bg-light-subtle">
										<h4 class="h6 mb-1"><i class="bi bi-card-text me-2" aria-hidden="true"></i><?php echo html_escape($deck_preview['title']); ?></h4>
										<?php if ($deck_preview['description']): ?><p class="small text-secondary"><?php echo html_escape($deck_preview['description']); ?></p><?php endif; ?>
										<?php if ($deck_preview['cards']): ?><ol class="small mb-0">
											<?php foreach ($deck_preview['cards'] as $preview_card): ?><li class="mb-2"><strong><?php echo html_escape($preview_card['front_text']); ?></strong><br><?php echo nl2br(html_escape($preview_card['back_text'])); ?><?php if ($preview_card['hint_text']): ?><br><span class="text-secondary">Petunjuk: <?php echo html_escape($preview_card['hint_text']); ?></span><?php endif; ?></li><?php endforeach; ?>
										</ol><?php else: ?><p class="small text-danger mb-0">Set kartu belum memiliki isi; tambahkan minimal satu kartu sebelum mengajukan review.</p><?php endif; ?>
									</div>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>
					</div>
				<?php else: ?><div class="empty-card"><h3 class="h6">Belum ada pelajaran</h3><p class="text-secondary">Tambahkan pelajaran sebelum kursus dikirim untuk review.</p></div><?php endif; ?>
			</div>
			<?php if ($reviews): ?><div class="feature-card mt-4"><h2 class="h5">Riwayat review</h2><?php foreach ($reviews as $review): ?><div class="border-top py-3"><div class="d-flex justify-content-between"><strong><?php echo html_escape($review['reviewer_name']); ?></strong><span class="badge text-bg-light"><?php echo html_escape($review['decision']); ?> · v<?php echo (int) $review['content_version']; ?></span></div><p class="small text-secondary mb-0 mt-2"><?php echo nl2br(html_escape($review['notes'] ?: 'Tidak ada catatan tambahan.')); ?></p></div><?php endforeach; ?></div><?php endif; ?>
		</div>
		<div class="col-lg-4"><div class="feature-card"><h2 class="h5">Tindakan</h2><p class="small text-secondary">Status saat ini: <strong><?php echo html_escape(study_course_status_label($course['status'])); ?></strong>.</p>
			<?php if ($can_edit): ?><?php echo form_open('kelola/kursus/'.$course['id'].'/ajukan'); ?><button class="btn btn-primary rounded-pill w-100" type="submit">Ajukan untuk review</button><?php echo form_close(); ?><p class="form-text mt-2">Kursus baru terbit setelah ditinjau oleh pengelola lain.</p><?php endif; ?>
			<?php if ($can_review): ?><hr><p class="small">Periksa tujuan, persiapan, usia sasaran, isi pelajaran, gambar beserta deskripsi alternatifnya, kartu belajar, sumber, serta hak materi. Pembuat kursus tidak dapat meninjau kursusnya sendiri.</p><?php echo form_open('kelola/kursus/'.$course['id'].'/tinjau'); ?><label class="form-label" for="notes">Catatan review</label><textarea class="form-control mb-3" name="notes" id="notes" rows="4" maxlength="5000" placeholder="Tuliskan alasan atau saran perubahan"></textarea><div class="d-grid gap-2"><button class="btn btn-success rounded-pill" type="submit" name="decision" value="approved">Setujui dan terbitkan</button><button class="btn btn-outline-primary rounded-pill" type="submit" name="decision" value="changes_requested">Minta perbaikan</button></div><?php echo form_close(); ?><?php endif; ?>
			<?php if ($course['status'] === 'published' && in_array('system_admin', $current_user['roles'], TRUE)): ?><hr><?php echo form_open('kelola/kursus/'.$course['id'].'/arsip'); ?><button class="btn btn-outline-danger rounded-pill w-100" type="submit">Arsipkan kursus</button><?php echo form_close(); ?><p class="form-text mt-2">Riwayat dan pembelian peserta tidak dihapus.</p><?php endif; ?>
			<?php if ( ! $can_edit && ! $can_review && $course['status'] !== 'published'): ?><div class="alert alert-light mb-0">Tidak ada tindakan yang tersedia untuk peran atau status saat ini.</div><?php endif; ?>
		</div></div>
	</div>
</section>
