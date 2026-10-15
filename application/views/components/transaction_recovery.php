<?php
$is_matching_recovery = $recovery_kind === 'matching';
$recovery_heading = $is_matching_recovery ? 'Periksa kembali permainan' : 'Periksa kembali latihan';
$recovery_eyebrow = $is_matching_recovery ? 'Permainan belajar' : 'Latihan belajar';
$recovery_uncertain_hint = $is_matching_recovery
	? 'Permintaanmu mungkin sudah diproses. Periksa keadaan terbaru sebelum memilih pasangan, memulai, atau menghentikan permainan lagi.'
	: 'Permintaanmu mungkin sudah diproses. Periksa keadaan terbaru sebelum mengirim jawaban atau memulai latihan lagi.';
$recovery_unavailable_hint = $is_matching_recovery
	? 'Layanan sedang mengalami kendala. Buka keadaan terbaru untuk memeriksa permainan dan progres yang sudah tersimpan sebelumnya.'
	: 'Layanan sedang mengalami kendala. Buka keadaan terbaru untuk memeriksa latihan dan jawaban yang sudah tersimpan sebelumnya.';
$recovery_history_path = $is_matching_recovery ? 'permainan/riwayat' : 'latihan';
$recovery_history_label = $is_matching_recovery ? 'Riwayat permainan saya' : 'Riwayat latihan saya';
$recovery_marker = $is_matching_recovery ? 'matching' : 'practice';
if ($recovery_kind === 'reading')
{
	$recovery_heading = 'Periksa kembali progres baca';
	$recovery_eyebrow = 'Belajar mandiri';
	$recovery_uncertain_hint = 'Permintaanmu mungkin sudah diproses. Periksa posisi dan status pelajaran terbaru sebelum menyimpan atau menandai selesai lagi.';
	$recovery_unavailable_hint = 'Layanan sedang mengalami kendala. Buka materi kembali untuk memeriksa posisi dan status pelajaran yang sudah tersimpan sebelumnya.';
	$recovery_history_path = 'beranda';
	$recovery_history_label = 'Beranda belajar saya';
	$recovery_marker = 'reading';
}
if ($recovery_kind === 'flashcard')
{
	$recovery_heading = 'Periksa kembali kartu belajar';
	$recovery_eyebrow = 'Latihan ingatan';
	$recovery_uncertain_hint = 'Permintaanmu mungkin sudah diproses. Periksa status kartu terbaru sebelum menandai sudah paham atau perlu diulang lagi.';
	$recovery_unavailable_hint = 'Layanan sedang mengalami kendala. Buka materi kembali untuk memeriksa status kartu yang sudah tersimpan sebelumnya.';
	$recovery_history_path = 'beranda';
	$recovery_history_label = 'Beranda belajar saya';
	$recovery_marker = 'flashcard';
}
?>
<section class="container page-section">
	<div class="row justify-content-center"><div class="col-lg-8">
		<div class="feature-card" data-<?php echo $recovery_marker; ?>-recovery="<?php echo $recovery_uncertain ? 'uncertain' : 'unavailable'; ?>">
			<span class="feature-icon feature-purple mb-3"><i class="bi bi-cloud-slash" aria-hidden="true"></i></span>
			<p class="eyebrow"><?php echo html_escape($recovery_eyebrow); ?></p>
			<h1 class="h2"><?php echo html_escape($recovery_heading); ?></h1>
			<div role="status">
				<?php if ($recovery_uncertain): ?>
					<p class="lead">Hasil penyimpanan belum dapat dipastikan.</p>
					<p><?php echo html_escape($recovery_uncertain_hint); ?></p>
				<?php else: ?>
					<p class="lead">Permintaan belum berhasil disimpan.</p>
					<p><?php echo html_escape($recovery_unavailable_hint); ?></p>
				<?php endif; ?>
			</div>
			<p class="text-secondary">Tautan di bawah hanya membuka halaman terbaru, tidak mengirim ulang permintaan. Jika kendala berlanjut, coba lagi nanti atau minta bantuan pengelola.</p>
			<div class="d-flex flex-wrap gap-2 mt-4">
				<a class="btn btn-primary rounded-pill" href="<?php echo html_escape(site_url($recovery_path)); ?>">Periksa keadaan terbaru</a>
				<a class="btn btn-outline-primary rounded-pill" href="<?php echo html_escape(site_url($recovery_history_path)); ?>"><?php echo html_escape($recovery_history_label); ?></a>
			</div>
		</div>
	</div></div>
</section>
