<?php
$media_path = 'kelola/kursus/'.(int) $course['id'].'/pelajaran/'.(int) $lesson['id'].'/gambar';
$media_revision = $posted_fields['revision'] ?? $lesson['content_version'];
$media_actions = $image ? array('metadata', 'upload') : array('upload');
$rights_options = array('owned', 'licensed', 'public_domain');
?>
<section class="container page-section lesson-media-editor">
	<a class="back-link" href="<?php echo html_escape(site_url('kelola/kursus/'.(int) $course['id'])); ?>">Kembali ke kursus</a>
	<div class="page-heading mt-3"><div><p class="eyebrow">Draf pelajaran · versi <?php echo (int) $lesson['content_version']; ?></p><h1 class="h2">Gambar pendamping</h1><p class="text-secondary mb-0"><?php echo html_escape($lesson['title']); ?></p></div><a class="btn btn-light rounded-pill" href="<?php echo html_escape(site_url('kelola/kursus/'.(int) $course['id'].'/pelajaran/'.(int) $lesson['id'].'/pratinjau')); ?>">Lihat sebagai peserta</a></div>
	<p>Satu gambar dapat melengkapi setiap pelajaran. Isi utama tetap tersedia sebagai teks. Periksa kesesuaian usia, deskripsi gambar, sumber, dan hak penggunaan sebelum mengajukan kursus untuk review.</p>
	<?php if ($submit_error): ?><div class="alert alert-warning" role="alert"><?php echo html_escape($submit_error); ?> <a href="<?php echo html_escape(site_url($media_path)); ?>">Muat ulang gambar pendamping</a></div><?php endif; ?>
	<?php if ($form_errors): ?><div class="alert alert-warning" role="alert">Periksa kolom yang ditandai di bawah. Jika perlu mencoba unggah lagi, pilih berkas kembali setelah memeriksa hasil.</div><?php endif; ?>
	<?php if ( ! $upload_available): ?><div class="alert alert-info" role="status">Unggah gambar belum tersedia. Pengolah gambar (GD) atau penyimpanan privat belum siap pada lingkungan ini. Hubungi pengelola.<?php if ($image): ?> Keterangan gambar yang sudah ada tetap dapat diubah, dan gambar tetap dapat dilepas dari pelajaran.<?php endif; ?></div><?php endif; ?>
	<div class="row g-4">
		<div class="col-lg-5">
			<section class="feature-card" aria-labelledby="current-image-heading"><h2 class="h5" id="current-image-heading">Gambar saat ini</h2>
				<?php if ($image): ?>
					<figure class="lesson-image mb-0">
						<?php if ($has_conflict): ?><p class="alert alert-info" role="status">Pratinjau gambar ditunda agar berkas terbaru tidak ditampilkan bersama keterangan lama. Muat ulang halaman untuk memeriksa gambar dan keterangan yang tersimpan.</p><?php else: ?><img src="<?php echo html_escape(site_url($media_path.'/pratinjau')); ?>" alt="<?php echo html_escape($image['alt_text']); ?>" width="<?php echo max(1, (int) $image['width']); ?>" height="<?php echo max(1, (int) $image['height']); ?>" decoding="async"><?php endif; ?>
						<figcaption class="small mt-3"><p><strong>Deskripsi gambar:</strong> <?php echo html_escape($image['alt_text']); ?></p><?php if ($image['caption'] !== ''): ?><p><strong>Keterangan:</strong> <?php echo nl2br(html_escape($image['caption'])); ?></p><?php endif; ?><p><strong>Hak gambar:</strong> <?php echo html_escape(study_rights_status_label($image['rights_status'])); ?></p><p><strong>Sumber:</strong> <?php echo nl2br(html_escape($image['source_note'])); ?></p><p><?php echo html_escape(study_format_number((int) $image['width'])); ?> × <?php echo html_escape(study_format_number((int) $image['height'])); ?> piksel · <?php echo html_escape(study_format_number((int) $image['byte_size'])); ?> byte</p></figcaption>
					</figure>
				<?php else: ?><div class="empty-card"><h3 class="h6">Belum ada gambar pendamping</h3><p>Tambahkan ilustrasi, foto, atau diagram yang membantu peserta memahami materi. Tidak wajib untuk menerbitkan pelajaran berbasis teks.</p></div><?php endif; ?>
			</section>
			<?php if ($image): ?>
				<details class="feature-card mt-4"<?php echo $active_action === 'remove' ? ' open' : ''; ?>><summary class="fw-semibold">Lepas gambar dari pelajaran</summary><p class="small mt-3">Gambar dan keterangannya tidak lagi ditampilkan pada pelajaran. Teks materi dan progres belajar tidak dihapus. Simpan salinan sumber jika ingin mengunggahnya kembali.</p>
					<?php $remove_errors = $active_action === 'remove' ? $form_errors : array(); echo form_open($media_path, array('class' => 'mt-3')); ?>
						<fieldset<?php echo $has_conflict ? ' disabled' : ''; ?>><legend class="visually-hidden">Konfirmasi melepas gambar</legend><input type="hidden" name="action" value="remove"><input type="hidden" name="revision" value="<?php echo html_escape($media_revision); ?>">
							<div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="confirm-remove-image" name="confirm_remove" value="yes" required<?php echo study_field_error_attributes($remove_errors, 'confirm_remove'); ?>><label class="form-check-label" for="confirm-remove-image">Saya ingin melepas gambar pendamping ini.</label><?php echo study_render_field_error($remove_errors, 'confirm_remove'); ?></div>
							<button class="btn btn-outline-danger rounded-pill" type="submit">Lepas gambar</button>
						</fieldset>
					<?php echo form_close(); ?>
				</details>
			<?php endif; ?>
		</div>
		<div class="col-lg-7">
			<?php foreach ($media_actions as $media_action):
				$is_upload = $media_action === 'upload';
				$is_active = $active_action === $media_action;
				$media_errors = $is_active ? $form_errors : array();
				$media_values = array('alt_text' => $image['alt_text'] ?? '', 'caption' => $image['caption'] ?? '', 'source_note' => $image['source_note'] ?? '', 'rights_status' => $image['rights_status'] ?? '');
				if ($is_active) { foreach ($media_values as $field => $value) { $media_values[$field] = $posted_fields[$field] ?? ''; } }
			?>
				<?php if ($is_upload && $image): ?><details class="feature-card mt-4"<?php echo $is_active ? ' open' : ''; ?>><summary class="fw-semibold">Ganti berkas gambar</summary><?php else: ?><section class="feature-card"><?php endif; ?>
					<?php echo $is_upload ? form_open_multipart($media_path, array('class' => $image ? 'mt-3' : '')) : form_open($media_path); ?>
						<fieldset<?php echo $has_conflict || ($is_upload && ! $upload_available) ? ' disabled' : ''; ?>>
							<legend class="h5"><?php echo $is_upload ? ($image ? 'Gambar pengganti' : 'Unggah gambar pendamping') : 'Ubah keterangan gambar'; ?></legend>
							<input type="hidden" name="action" value="<?php echo html_escape($media_action); ?>"><input type="hidden" name="revision" value="<?php echo html_escape($media_revision); ?>">
							<?php if ($is_upload): ?>
								<p class="small mb-3">PNG atau JPEG, maksimal 3 MiB (3.145.728 byte). Sisi terpanjang maksimal 4.096 piksel; lebar × tinggi maksimal 4.000.000 piksel. SVG, GIF, animasi, dan format lain belum didukung.<?php if ($image): ?> Sesuaikan juga deskripsi dan sumber untuk gambar pengganti.<?php endif; ?></p>
								<div class="mb-3"><label class="form-label" for="lesson-image-file">Pilih berkas gambar</label><input class="form-control" type="file" id="lesson-image-file" name="image" accept=".png,.jpg,.jpeg,image/png,image/jpeg" required<?php echo study_field_error_attributes($media_errors, 'image'); ?>><?php echo study_render_field_error($media_errors, 'image'); ?><p class="form-text">Gunakan nama sederhana, misalnya diagram-pecahan.png. Browser tidak mengisi ulang pilihan berkas ketika halaman dimuat kembali. Pilih berkas lagi jika perlu mencoba unggah ulang.</p></div>
							<?php endif; ?>
							<div class="mb-3"><label class="form-label" for="image-<?php echo html_escape($media_action); ?>-alt">Deskripsi alternatif gambar</label><textarea class="form-control" id="image-<?php echo html_escape($media_action); ?>-alt" name="alt_text" rows="3" minlength="3" maxlength="300" required<?php echo study_field_error_attributes($media_errors, 'alt_text'); ?>><?php echo html_escape($media_values['alt_text']); ?></textarea><?php echo study_render_field_error($media_errors, 'alt_text'); ?><p class="form-text">3–300 karakter. Jelaskan informasi penting pada gambar, bukan nama berkas. Deskripsi juga ditampilkan bila gambar sulit dibaca atau tidak termuat.</p></div>
							<div class="mb-3"><label class="form-label" for="image-<?php echo html_escape($media_action); ?>-caption">Keterangan tambahan (opsional)</label><textarea class="form-control" id="image-<?php echo html_escape($media_action); ?>-caption" name="caption" rows="3" maxlength="1000"<?php echo study_field_error_attributes($media_errors, 'caption'); ?>><?php echo html_escape($media_values['caption']); ?></textarea><?php echo study_render_field_error($media_errors, 'caption'); ?><p class="form-text">Maksimal 1.000 karakter. Tambahkan konteks belajar; jangan hanya mengulang deskripsi alternatif.</p></div>
							<div class="mb-3"><label class="form-label" for="image-<?php echo html_escape($media_action); ?>-rights">Status hak penggunaan gambar</label><select class="form-select" id="image-<?php echo html_escape($media_action); ?>-rights" name="rights_status" required<?php echo study_field_error_attributes($media_errors, 'rights_status'); ?>><option value=""<?php echo ! in_array($media_values['rights_status'], $rights_options, TRUE) ? ' selected' : ''; ?>>Pilih status hak penggunaan</option><?php foreach ($rights_options as $right): ?><option value="<?php echo html_escape($right); ?>"<?php echo $media_values['rights_status'] === $right ? ' selected' : ''; ?>><?php echo html_escape(study_rights_status_label($right)); ?></option><?php endforeach; ?></select><?php echo study_render_field_error($media_errors, 'rights_status'); ?></div>
							<div class="mb-3"><label class="form-label" for="image-<?php echo html_escape($media_action); ?>-source">Catatan sumber dan izin penggunaan</label><textarea class="form-control" id="image-<?php echo html_escape($media_action); ?>-source" name="source_note" rows="4" minlength="5" maxlength="2000" required<?php echo study_field_error_attributes($media_errors, 'source_note'); ?>><?php echo html_escape($media_values['source_note']); ?></textarea><?php echo study_render_field_error($media_errors, 'source_note'); ?><p class="form-text">5–2.000 karakter. Cantumkan pembuat/sumber serta lisensi atau izin yang relevan. Catatan ini tampil kepada peserta: jangan masukkan data pribadi atau rahasia.</p></div>
							<p class="small mb-3">Semua keterangan disimpan sebagai teks biasa. Tunggu halaman hasil setelah menekan tombol; tidak ada penyimpanan otomatis atau pengiriman ulang otomatis.</p>
							<button class="btn btn-primary rounded-pill" type="submit"><?php echo $is_upload ? ($image ? 'Unggah gambar pengganti' : 'Unggah gambar') : 'Simpan keterangan gambar'; ?></button>
						</fieldset>
					<?php echo form_close(); ?>
				<?php if ($is_upload && $image): ?></details><?php else: ?></section><?php endif; ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
