<section class="container page-section course-workspace">
	<?php $this->load->view('public_learning/breadcrumbs'); ?>
	<div class="feature-card mb-4">
		<p class="eyebrow"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Belajar bertahap, pahami alasannya</p>
		<h1 class="display-6 fw-bold"><?php echo html_escape($page_title); ?></h1>
		<p class="lead text-secondary"><?php echo html_escape($intro); ?></p>
		<p class="mb-0">Contoh dan pembahasan dapat dibaca tanpa login. Untuk mengerjakan latihan kursus dan menyimpan hasil, <a href="<?php echo site_url('masuk'); ?>">masuk</a> atau <a href="<?php echo site_url('daftar'); ?>">buat akun</a>. Ini latihan kursus, bukan bank Latihan Mandiri atau Simulasi Ujian.</p>
	</div>
	<?php if($grade===NULL): ?>
		<h2 class="h3 mb-3">Pilih kelas SD</h2><div class="row g-3">
		<?php foreach(array_unique(array_column($courses,'grade')) as $number): ?>
			<div class="col-md-6 col-lg-4"><article class="feature-card h-100"><h3 class="h4">Kelas <?php echo (int)$number; ?> SD</h3><p><?php echo html_escape($this->Public_learning_model->grade_intro($number)); ?></p><a class="btn btn-primary" href="<?php echo site_url('latihan-soal/kelas-'.$number); ?>">Lihat latihan kelas <?php echo (int)$number; ?></a></article></div>
		<?php endforeach; ?></div>
	<?php elseif($subject===NULL): ?>
		<h2 class="h3 mb-3">Mata pelajaran kelas <?php echo (int)$grade; ?></h2><div class="row g-3">
		<?php foreach(array_unique(array_column($courses,'subject')) as $key): ?>
			<div class="col-md-6"><article class="feature-card h-100"><h3 class="h4"><?php echo html_escape($subjects[$key]['label']); ?></h3><p><?php echo html_escape($subjects[$key]['intro']); ?></p>
			<?php foreach($courses as $course): if($course['subject']!==$key)continue; ?><p class="small text-secondary"><?php echo html_escape($course['summary']); ?></p><?php endforeach; ?>
			<a class="btn btn-primary" href="<?php echo site_url('latihan-soal/kelas-'.$grade.'/'.$key); ?>">Contoh soal <?php echo html_escape($subjects[$key]['label']); ?></a></article></div>
		<?php endforeach; ?></div>
	<?php else: ?>
		<?php foreach($courses as $course): ?>
		<section class="mb-5" aria-labelledby="course-<?php echo (int)$course['id']; ?>">
			<h2 class="h3" id="course-<?php echo (int)$course['id']; ?>"><?php echo html_escape($course['title']); ?></h2>
			<p class="text-secondary"><?php echo html_escape($course['summary']); ?></p>
			<div class="row g-4"><div class="col-lg-7">
				<h3 class="h5">Contoh soal dan pembahasan</h3>
				<?php if(empty($samples[$course['id']])): ?><p>Contoh soal publik belum tersedia untuk kursus ini. Mulai dari materi di samping.</p><?php endif; ?>
				<?php foreach($samples[$course['id']] as $i=>$question): ?>
				<article class="feature-card mb-3"><h4 class="h6">Contoh <?php echo $i+1; ?></h4><p class="fw-semibold text-break"><?php echo nl2br(html_escape($question['prompt'])); ?></p>
					<ol type="A"><?php foreach($question['options'] as $option): ?><li class="mb-2 text-break"><?php echo nl2br(html_escape($option['option_text'])); ?></li><?php endforeach; ?></ol>
					<details><summary class="fw-semibold">Lihat jawaban dan pembahasan</summary><div class="mt-3">
						<?php foreach($question['options'] as $j=>$option): if(!$option['is_correct'])continue; ?><p><strong>Jawaban <?php echo chr(65+$j); ?>:</strong> <?php echo html_escape($option['option_text']); ?></p><?php endforeach; ?>
						<p class="mb-0 text-break"><?php echo nl2br(html_escape($question['explanation'])); ?></p>
					</div></details>
				</article>
				<?php endforeach; ?>
				<p class="small text-secondary">Contoh di atas diambil dari soal publik kursus ini; contoh dapat muncul kembali saat latihan. Membuka pembahasan tidak mencatat nilai atau progres.</p>
				<div class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="<?php echo site_url('latihan/kursus/'.(int)$course['id']); ?>">Mulai latihan kursus</a><a class="btn btn-teal" href="<?php echo site_url('kursus/'.$course['slug']); ?>">Baca materi kursus</a></div>
			</div><div class="col-lg-5">
				<section class="feature-card"><h3 class="h5">Pelajari topiknya</h3><ol class="mb-0">
				<?php foreach($lessons as $lesson): if((int)$lesson['course_id']!==(int)$course['id'])continue; ?><li class="mb-2"><a href="<?php echo site_url('materi/'.$lesson['slug']); ?>"><?php echo html_escape($lesson['title']); ?></a></li><?php endforeach; ?>
				</ol></section>
				<?php $this->load->view('public_learning/source',array('public_source'=>$course)); ?>
			</div></div>
		</section>
		<?php endforeach; ?>
	<?php endif; ?>
	<section class="feature-card mt-4"><h2 class="h5">Cara memakai latihan ini</h2><p class="mb-0">Baca materi atau contoh, coba tentukan jawaban sendiri, lalu buka pembahasan. Jika belum paham, kembali ke topik terkait. Nilai kuis menunjukkan hasil latihan saat itu, bukan sertifikasi penguasaan seluruh pelajaran.</p></section>
</section>
