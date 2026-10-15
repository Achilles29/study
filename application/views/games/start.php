<section class="container page-section">
	<div class="row justify-content-center"><div class="col-lg-8">
		<a class="back-link" href="<?php echo site_url('permainan/pasangan/'.$lesson['slug']); ?>">Kembali ke permainan lokal</a>
		<div class="feature-card matching-game mt-4">
			<p class="eyebrow"><i class="bi bi-puzzle me-2" aria-hidden="true"></i>Cocokkan Pasangan</p><h1 class="h2">Simpan perjalanan bermainmu</h1>
			<p class="lead practice-question"><?php echo html_escape($lesson['title']); ?></p>
			<p>Pasangkan kartu, coba lagi jika belum cocok, dan lanjutkan kapan saja dari akun yang sama. Pilihan yang berhasil dikirim tersimpan di server, termasuk saat halaman dimuat ulang.</p>
			<ul class="text-secondary"><li>Tanpa batas waktu dan tanpa hukuman. Bukan ujian, XP, atau kredit.</li><li>Setiap pilihan perlu koneksi internet. Jika koneksi terputus, buka kembali permainan untuk memeriksa progres terakhir.</li><li>Catatan waktu menghitung jarak sejak mulai hingga selesai, termasuk jeda; bukan lama belajar aktif.</li><li>Akses materi harus tetap aktif untuk melanjutkan. Permainan yang dihentikan tidak dapat dilanjutkan, tetapi catatannya tetap ada.</li></ul>
			<?php if ($submit_error): ?><div class="alert alert-warning" role="alert"><?php echo html_escape($submit_error); ?></div><?php endif; ?>
			<?php if ($active_id): ?><a class="btn btn-primary rounded-pill" href="<?php echo site_url('permainan/tersimpan/'.$active_id); ?>">Lanjutkan permainan</a>
			<?php elseif ($card_count >= 2): ?><p><?php echo (int) $card_count; ?> pasangan tersedia, dibagi ke beberapa babak kecil.</p><?php echo form_open('permainan/tersimpan/pelajaran/'.$lesson['slug']); ?><input type="hidden" name="action_token" value="<?php echo html_escape($action_token); ?>"><button class="btn btn-primary rounded-pill" type="submit">Mulai permainan tersimpan</button><?php echo form_close(); ?>
			<?php else: ?><p class="alert alert-info mb-0">Belum cukup pasangan yang sesuai. Silakan kembali membaca kartu belajar.</p><?php endif; ?>
		</div>
		<a class="btn btn-outline-primary rounded-pill mt-3" href="<?php echo site_url('permainan/riwayat'); ?>">Riwayat permainan saya</a>
	</div></div>
</section>
