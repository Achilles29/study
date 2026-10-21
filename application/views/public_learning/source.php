<?php if (!empty($public_source)): ?>
<section class="feature-card mt-4" aria-label="Sumber dan penyusunan materi">
	<h2 class="h5">Sumber dan penyusunan materi</h2>
	<p class="small mb-2">Penerbit: <?php echo html_escape($site_settings['brand_name']); ?> · Pembaruan data materi: <?php echo html_escape(study_format_datetime($public_source['updated_at'])); ?>.</p>
	<p class="small">Materi melewati alur publikasi internal. Belum merupakan tinjauan pedagogis independen oleh guru atau jaminan seluruh kurikulum/buku sekolah tercakup.</p>
	<details><summary class="fw-semibold">Acuan, hak penggunaan, dan batas materi</summary>
		<p class="mt-3 small text-break"><?php echo nl2br(html_escape($public_source['source_note'] ?: 'Catatan sumber belum tersedia.')); ?></p>
		<p class="small mb-0"><?php echo html_escape(study_rights_status_label($public_source['rights_status'])); ?></p>
		<p class="small mt-2 mb-0">Catatan sumber dapat menyebut tahap draf saat penyusunan. Status tayang mengikuti publikasi kursus; tinjauan pendidik independen tetap terpisah.</p>
	</details>
</section>
<?php endif; ?>
