<section class="container page-section">
	<div class="row justify-content-center"><div class="col-lg-8">
		<a class="back-link" href="<?php echo site_url('kursus/'.$course['slug']); ?>">Kembali ke kursus</a>
		<div class="feature-card mt-4">
			<p class="eyebrow">Latihan kursus gratis</p><h1 class="h2"><?php echo html_escape($course['title']); ?></h1>
			<p class="lead"><?php echo (int) $question_count; ?> soal untuk mencoba pemahamanmu.</p>
			<ul class="text-secondary"><li>Tanpa batas waktu. Gunakan “Simpan sementara” sebelum meninggalkan halaman.</li><li>Setiap soal berbobot sama. Jawaban kosong dihitung belum benar saat latihan diselesaikan.</li><li>Pembahasan tersedia setelah selesai. Kamu boleh berlatih lagi; setiap hasil tersimpan terpisah.</li><li>Jika ada latihan yang masih berjalan, tombol di bawah akan melanjutkannya.</li></ul>
			<?php if ($can_start && $question_count > 0): ?><?php echo form_open('latihan/kursus/'.$course['id']); ?><input type="hidden" name="start_token" value="<?php echo html_escape($start_token); ?>"><?php $this->load->view('practice/sheet_settings'); ?><button class="btn btn-primary rounded-pill" type="submit">Mulai atau lanjutkan latihan</button><?php echo form_close(); ?><?php else: ?><div class="alert alert-info mb-0"><?php echo $can_start ? 'Soal latihan untuk kursus ini belum tersedia.' : 'Akses latihan mengikuti akses kursus. Periksa akses kursusmu terlebih dahulu.'; ?></div><?php endif; ?>
		</div>
		<a class="btn btn-outline-primary rounded-pill mt-3" href="<?php echo site_url('latihan'); ?>">Riwayat latihan saya</a>
	</div></div>
</section>
