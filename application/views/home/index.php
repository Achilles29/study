<section class="hero-section">
	<div class="container py-5 py-lg-6">
		<div class="row align-items-center g-5">
			<div class="col-lg-7">
				<p class="eyebrow"><span class="eyebrow-dot"></span> <?php echo html_escape($site_settings['brand_name']); ?> · Belajar bersama keluarga</p>
				<h1 class="hero-title">Latihan soal online, <span class="text-gradient">belajar jadi lebih seru.</span></h1>
				<p class="hero-copy">Temukan latihan soal SD kelas 1–6 dengan pembahasan dan materi belajar gratis. Pelajari Matematika, Bahasa Indonesia, Bahasa Inggris, serta mata pelajaran lain sesuai kelas. Orang tua dapat mendampingi dan mengikuti progres belajar anak.</p>
				<div class="d-flex flex-column flex-sm-row gap-3 mt-4">
					<a class="btn btn-primary btn-lg rounded-pill px-4" href="<?php echo site_url('latihan-soal'); ?>">Pilih latihan soal <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></a>
					<a class="btn btn-light btn-lg rounded-pill px-4 border" href="#cara-belajar">Lihat cara belajar</a>
				</div>
				<div class="hero-trust mt-4"><i class="bi bi-stars me-2" aria-hidden="true"></i>Belajar setahap demi setahap, sesuai ritmemu.</div>
			</div>
			<div class="col-lg-5">
				<div class="learning-art" role="img" aria-label="Ilustrasi perjalanan belajar: satu langkah kecil dan terus mencoba.">
					<div class="art-sun"></div><div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
					<div class="art-card art-card-main"><span class="art-icon art-icon-purple"><i class="bi bi-lightbulb" aria-hidden="true"></i></span><span class="art-label">Ide baru hari ini</span><strong>1 langkah kecil</strong><div class="art-progress"><span></span></div></div>
					<div class="art-card art-card-note"><span class="art-icon art-icon-yellow"><i class="bi bi-stars" aria-hidden="true"></i></span><div><strong>Hebat!</strong><small>Kamu terus mencoba</small></div></div>
					<div class="art-spark spark-one">✦</div><div class="art-spark spark-two">✧</div><div class="art-spark spark-three">✦</div>
				</div>
			</div>
		</div>
	</div>
</section>

<section id="cara-belajar" class="container py-5 py-lg-6">
	<div class="section-heading text-center mx-auto mb-5">
		<p class="eyebrow justify-content-center">Belajar yang terasa dekat</p>
		<h2>Cara mulai latihan soal dengan pembahasan</h2>
		<p class="text-secondary">Mulai dari materi yang ingin dipahami, lalu coba latihan kursus untuk memeriksa pemahamanmu.</p>
	</div>
	<div class="row g-4">
		<div class="col-md-4"><article class="feature-card h-100"><span class="feature-icon feature-purple"><i class="bi bi-compass" aria-hidden="true"></i></span><h3>Pilih kelas dan pelajaran</h3><p>Buka katalog, pilih kelas, lalu temukan topik yang ingin kamu pelajari. Materi kursus dapat dibaca gratis.</p></article></div>
		<div class="col-md-4"><article class="feature-card h-100"><span class="feature-icon feature-coral"><i class="bi bi-pencil-square" aria-hidden="true"></i></span><h3>Coba latihan kursus</h3><p>Masuk atau daftar akun, buka kursus, lalu pilih tombol latihan. Kerjakan soal dan lihat pembahasan setelah latihan selesai.</p></article></div>
		<div class="col-md-4"><article class="feature-card h-100"><span class="feature-icon feature-green"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span><h3>Lihat perkembangan</h3><p>Hasil latihan dan progres materi tersimpan di akun. Orang tua dapat memantau kegiatan anak yang tertaut ke keluarganya.</p></article></div>
	</div>
</section>

<section id="latihan-soal-sd" class="container pb-5">
	<div class="section-heading mb-4">
		<p class="eyebrow">Temukan tingkat belajarmu</p>
		<h2>Materi dan latihan soal SD sesuai kelas</h2>
		<p class="text-secondary">Dari latihan soal Matematika hingga Bahasa Indonesia dan Bahasa Inggris, pilih kelas untuk melihat kursus yang tersedia. IPAS tersedia mulai kelas 3.</p>
	</div>
	<div class="row g-3">
		<?php for ($grade = 1; $grade <= 6; $grade++): ?>
			<div class="col-6 col-md-4 col-lg-2"><a class="btn btn-outline-primary w-100 py-3" href="<?php echo site_url('latihan-soal/kelas-'.$grade); ?>"><i class="bi bi-book me-2" aria-hidden="true"></i>Kelas <?php echo $grade; ?> SD</a></div>
		<?php endfor; ?>
	</div>
</section>

<section id="untuk-keluarga" class="family-band py-5 py-lg-6">
	<div class="container">
		<div class="row align-items-center g-4">
			<div class="col-lg-8"><p class="eyebrow">Belajar bersama keluarga</p><h2>Orang tua mendampingi. Anak tetap punya ruang untuk mencoba.</h2><p class="mb-0 text-secondary">Orang tua dapat memantau progres dan hasil latihan anak. Dengan hak fitur keluarga aktif, orang tua dapat menyusun soal privat, mengatur jadwal, dan mengirim latihan kepada anak. Fitur tambahan mengikuti hak akses dan paket yang aktif pada akun.</p></div>
			<div class="col-lg-4 text-lg-end"><a class="btn btn-dark rounded-pill px-4 py-3" href="<?php echo site_url('daftar'); ?>">Buat akun keluarga <i class="bi bi-arrow-up-right ms-2" aria-hidden="true"></i></a></div>
		</div>
	</div>
</section>

<section id="cerita-belajar" class="review-showcase py-5 py-lg-6" aria-labelledby="judul-cerita-belajar">
	<div class="container">
		<div class="review-showcase-heading d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-4 mb-4 mb-lg-5">
			<div class="section-heading mb-0">
				<p class="eyebrow"><i class="bi bi-chat-heart me-2" aria-hidden="true"></i>Surat kecil dari meja belajar</p>
				<h2 id="judul-cerita-belajar">Yang kecil-kecil ternyata terasa besar.</h2>
				<?php if ($review_highlights['rows']): ?><p class="text-secondary mb-0">Tiga cerita dipilih acak dari ulasan asli yang sudah diperiksa. Datang lagi nanti—ceritanya mungkin berbeda.</p><?php else: ?><p class="text-secondary mb-0">Kami menyiapkan ruang untuk pengalaman belajar yang jujur—baik yang menyenangkan maupun yang masih perlu diperbaiki.</p><?php endif; ?>
			</div>
			<?php if ((int)$review_highlights['summary']['total'] > 0): ?>
				<div class="review-score" aria-label="Rata-rata <?php echo number_format((float)$review_highlights['summary']['average'],1,',','.'); ?> dari 5, berdasarkan <?php echo (int)$review_highlights['summary']['total']; ?> ulasan">
					<span class="review-score-number"><?php echo number_format((float)$review_highlights['summary']['average'],1,',','.'); ?></span>
					<span><span class="review-stars" aria-hidden="true">★★★★★</span><small>dari <?php echo number_format((int)$review_highlights['summary']['total'],0,',','.'); ?> ulasan terbit</small></span>
				</div>
			<?php endif; ?>
		</div>

		<?php if ($review_highlights['rows']): ?>
			<div class="row g-4 review-card-grid">
				<?php foreach ($review_highlights['rows'] as $index => $review): ?>
					<div class="col-lg-4">
						<article class="review-story review-story-<?php echo ($index % 3) + 1; ?> h-100">
							<i class="bi bi-quote review-quote" aria-hidden="true"></i>
							<p class="review-stars mb-3" aria-label="<?php echo (int)$review['rating']; ?> dari 5 bintang"><?php for ($star=1;$star<=5;$star++): ?><i class="bi <?php echo $star<=(int)$review['rating']?'bi-star-fill':'bi-star'; ?>" aria-hidden="true"></i><?php endfor; ?></p>
							<blockquote class="mb-4"><p><?php echo nl2br(html_escape($review['body'])); ?></p></blockquote>
							<footer><span class="review-avatar" aria-hidden="true"><?php echo html_escape(mb_strtoupper(mb_substr(trim($review['display_name']),0,1))); ?></span><span><strong><?php echo html_escape($review['display_name']); ?></strong><small>Ulasan telah dimoderasi</small></span></footer>
						</article>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="d-flex flex-wrap justify-content-center gap-2 mt-4 mt-lg-5"><a class="btn btn-primary rounded-pill px-4" href="<?php echo site_url('ulasan'); ?>">Baca semua cerita</a><a class="btn btn-light border rounded-pill px-4" href="<?php echo site_url('ulasan/tulis'); ?>">Bagikan pengalamanmu</a></div>
		<?php else: ?>
			<div class="review-empty" data-review-empty>
				<div class="review-empty-orbit" aria-hidden="true"><span>✦</span><i class="bi bi-chat-heart"></i><span>✧</span></div>
				<div><p class="eyebrow mb-2">Satu tempat masih kosong</p><h3 class="h4">Cerita pertama mungkin datang darimu.</h3><p class="text-secondary mb-0">Ulasan akan tampil di sini setelah dikirim oleh akun terverifikasi dan diperiksa pengelola.</p></div>
				<a class="btn btn-sun rounded-pill px-4" href="<?php echo site_url('ulasan/tulis'); ?>">Tulis cerita pertama <i class="bi bi-arrow-up-right ms-2" aria-hidden="true"></i></a>
			</div>
		<?php endif; ?>
	</div>
</section>

<section class="container py-5 text-center">
	<p class="eyebrow justify-content-center">Ruang untuk terus bertumbuh</p>
	<h2 class="h3 mb-3">Belajar sedikit demi sedikit, mulai hari ini</h2>
	<p class="text-secondary mb-4"><?php echo (int) $course_count; ?> kursus telah diterbitkan. Pilih materi, coba latihan soal, dan lanjutkan belajar sesuai ritmemu.</p>
	<div class="d-flex justify-content-center flex-wrap gap-2"><a class="btn btn-primary rounded-pill px-4" href="<?php echo site_url('kursus'); ?>">Lihat materi dan latihan</a><a class="btn btn-outline-primary rounded-pill px-4" href="<?php echo site_url('daftar'); ?>">Buat akun</a></div>
</section>

<section class="container pb-5" aria-labelledby="pertanyaan-belajar">
	<h2 class="h3 mb-4" id="pertanyaan-belajar">Sebelum mulai belajar</h2>
	<div class="feature-card">
		<details class="mb-3"><summary class="fw-bold">Apakah latihan soal online ini gratis?</summary><p class="mt-2 mb-0">Materi dan latihan kursus yang ditandai Gratis dapat diakses tanpa membeli paket. Masuk atau daftar akun untuk mengerjakan latihan dan menyimpan hasil. Fitur tambahan di luar kursus mengikuti ketentuan akses masing-masing.</p></details>
		<details class="mb-3"><summary class="fw-bold">Bagaimana menemukan latihan soal SD kelas 1 sampai kelas 6?</summary><p class="mt-2 mb-0">Pilih kelas pada bagian di atas atau gunakan filter kelas di katalog. Buka mata pelajaran yang diinginkan, baca materinya, lalu pilih tombol Latihan kursus.</p></details>
		<details><summary class="fw-bold">Apakah latihan kursus sama dengan Latihan Mandiri dan Simulasi Ujian?</summary><p class="mt-2 mb-0">Berbeda. Latihan kursus mengikuti materi pada kursus yang dipilih. Latihan Mandiri mengambil soal acak dari bank soal, sedangkan Simulasi Ujian mengikuti sesi yang disusun pengelola. Ketersediaan dua jenis terakhir bergantung pada bank soal dan sesi yang sudah diterbitkan; keduanya belum ditawarkan sebagai isi latihan di halaman ini.</p></details>
	</div>
</section>
