<section class="hero-section">
	<div class="container py-5 py-lg-6">
		<div class="row align-items-center g-5">
			<div class="col-lg-7">
				<p class="eyebrow"><span class="eyebrow-dot"></span> <?php echo html_escape($site_settings['brand_name']); ?> · Belajar anak TK, SD, dan SMP</p>
				<h1 class="hero-title">Rasa ingin tahu bisa <span class="text-gradient">membawamu jauh.</span></h1>
				<p class="hero-copy">Belajar online bersama keluarga lewat materi belajar anak, latihan soal, flashcard, dan permainan edukasi. Orang tua dapat mengikuti progres belajar anak dan memilih langkah berikutnya. Kegiatan tersedia sesuai materi dan akses akun.</p>
				<div class="d-flex flex-column flex-sm-row gap-3 mt-4">
					<a class="btn btn-primary btn-lg rounded-pill px-4" href="<?php echo site_url('daftar'); ?>">Mulai gratis <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></a>
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
		<h2>Mulai dari rasa ingin tahu</h2>
		<p class="text-secondary">Pilih, coba, pahami. Kemajuan dibangun dari pengalaman belajar yang konsisten.</p>
	</div>
	<div class="row g-4">
		<div class="col-md-4"><article class="feature-card h-100"><span class="feature-icon feature-purple"><i class="bi bi-compass" aria-hidden="true"></i></span><h3>Temukan topik</h3><p>Pilih materi yang sesuai tujuan dan tingkat kemampuanmu.</p></article></div>
		<div class="col-md-4"><article class="feature-card h-100"><span class="feature-icon feature-coral"><i class="bi bi-book-half" aria-hidden="true"></i></span><h3>Belajar satu materi</h3><p>Baca pelajaran, simpan posisi, dan tandai materi yang sudah selesai.</p></article></div>
		<div class="col-md-4"><article class="feature-card h-100"><span class="feature-icon feature-green"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span><h3>Lihat perkembangan</h3><p>Pantau jumlah pelajaran yang sedang dipelajari dan yang telah diselesaikan.</p></article></div>
	</div>
</section>

<section id="untuk-keluarga" class="family-band py-5 py-lg-6">
	<div class="container">
		<div class="row align-items-center g-4">
			<div class="col-lg-8"><p class="eyebrow">Belajar bersama keluarga</p><h2>Orang tua mendampingi. Anak tetap punya ruang untuk mencoba.</h2><p class="mb-0 text-secondary">Orang tua dapat memantau progres dan hasil latihan anak. Dengan hak fitur keluarga aktif, orang tua dapat menyusun soal privat, mengatur jadwal, dan mengirim latihan kepada anak. Pembelian paket belum dibuka.</p></div>
			<div class="col-lg-4 text-lg-end"><a class="btn btn-dark rounded-pill px-4 py-3" href="<?php echo site_url('daftar'); ?>">Buat akun keluarga <i class="bi bi-arrow-up-right ms-2" aria-hidden="true"></i></a></div>
		</div>
	</div>
</section>

<section class="container py-5 text-center">
	<p class="eyebrow justify-content-center">Ruang untuk terus bertumbuh</p>
	<h2 class="h3 mb-3">Materi belajar sedang kami siapkan</h2>
	<p class="text-secondary mb-4"><?php echo (int) $course_count; ?> kursus telah diterbitkan. Daftar untuk mengikuti perkembangan tahap awal platform.</p>
	<div class="d-flex justify-content-center flex-wrap gap-2"><a class="btn btn-primary rounded-pill px-4" href="<?php echo site_url('kursus'); ?>">Jelajahi kursus</a><a class="btn btn-outline-primary rounded-pill px-4" href="<?php echo site_url('daftar'); ?>">Buat akun</a></div>
</section>
