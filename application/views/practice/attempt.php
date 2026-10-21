<?php
require_once dirname(__DIR__,2).'/libraries/Question_media.php';
$graded = $attempt['status'] === 'graded';
$family_report = isset($report_child);
$editable = $attempt['status'] === 'in_progress' && ! $family_report;
$history_path = $family_report ? 'keluarga/anak/'.$report_child['id'].'/latihan' : 'latihan';
$history_path = isset($history_path_override) ? $history_path_override : $history_path;
$sheet = $sheet ?? array('rules'=>array('view_mode'=>'ledger','focus_guard'=>'0','focus_limit'=>'3'),'focus_count'=>0);
$answered_count=count(array_filter($questions,static function($q){return $q['selected_option_id']!==NULL;}));
?>
<link rel="stylesheet" href="<?php echo base_url('assets/css/practice-sheet.css?v='.filemtime(FCPATH.'assets/css/practice-sheet.css')); ?>">
<section class="container page-section practice-sheet" id="practice-sheet"
 data-mode="<?php echo html_escape($sheet['rules']['view_mode']); ?>"
 data-guard="<?php echo $editable?html_escape($sheet['rules']['focus_guard']):'0'; ?>"
 data-limit="<?php echo (int)$sheet['rules']['focus_limit']; ?>" data-focus-count="<?php echo (int)$sheet['focus_count']; ?>"
 data-attempt="<?php echo (int)$attempt['id']; ?>" data-server-time="<?php echo time(); ?>"
 data-deadline="<?php echo !empty($deadline)?(int)strtotime($deadline.' UTC'):0; ?>">
	<div class="row justify-content-center"><div class="col-12">
		<a class="back-link" href="<?php echo site_url($history_path); ?>"><?php echo $family_report ? 'Kembali ke laporan anak' : 'Riwayat latihan saya'; ?></a>
		<?php if ($graded && !empty($review_view) && !$family_report): ?><a class="btn btn-primary ms-2" href="<?php echo site_url('latihan/percobaan/'.$attempt['id']); ?>"><i class="bi bi-bar-chart-fill me-2" aria-hidden="true"></i>Kembali ke nilai</a><?php endif; ?>
		<?php if ($family_report): ?><div class="alert alert-info mt-3"><strong>Hasil latihan <?php echo html_escape($report_child['display_name']); ?></strong><p class="mb-0">Laporan ini hanya untuk dibaca. Dampingi anak memahami pembahasan tanpa mengubah jawabannya.</p></div><?php endif; ?>
		<div class="feature-card sheet-hero mt-3 mb-4"><p class="eyebrow"><?php echo $graded ? 'Hasil latihan' : (array('parent_assigned'=>'Latihan keluarga','self_practice'=>'Latihan mandiri','assessment'=>'Simulasi ujian','course_quiz'=>'Latihan kursus')[$attempt['session_type']]??'Latihan'); ?></p><h1 class="h2"><?php echo html_escape($attempt['title']); ?></h1>
			<?php if ($graded): ?><p class="h3 mt-3"><?php echo (int) $attempt['correct_count']; ?> dari <?php echo (int) $attempt['question_count']; ?> jawaban benar · <?php echo html_escape(str_replace('.', ',', $attempt['score'])); ?>%</p><p class="text-secondary mb-0"><?php echo $family_report ? 'Apresiasi usaha anak. Gunakan pembahasan ini untuk mendampingi, bukan membandingkan nilainya dengan anak lain.' : 'Terima kasih sudah berlatih. Baca pembahasan untuk memahami langkahnya, lalu coba lagi saat siap.'; ?></p><?php else: ?><p class="text-secondary mb-0">Pilih satu jawaban untuk setiap soal. Simpan sementara agar pilihanmu bisa dilanjutkan. Pembahasan muncul setelah kamu menyelesaikan latihan.</p><?php endif; ?>
		</div>
		<?php if ($submit_error): ?><div class="alert alert-warning" role="alert"><?php echo html_escape($submit_error); ?></div><?php endif; ?>
		<?php if($graded && $sheet['rules']['focus_guard']==='1'): ?><div class="alert alert-info">Catatan fokus: <?php echo (int)$sheet['focus_count']; ?> dari batas <?php echo (int)$sheet['rules']['focus_limit']; ?> kejadian. <?php echo $sheet['focus_count']>=(int)$sheet['rules']['focus_limit']?'Sesi diselesaikan otomatis karena batas tercapai.':'Sesi selesai sebelum batas tercapai.'; ?> Catatan perpindahan layar bukan bukti pasti kecurangan.</div><?php endif; ?>
		<?php if(!empty($deadline)&&!$graded): ?><div class="alert alert-warning"><strong>Batas waktu: <?php echo html_escape(study_format_datetime($deadline)); ?> WIB.</strong> Waktu tetap berjalan meskipun halaman ditutup. Simpan jawaban berkala. Setelah batas waktu, tombol di bawah menyelesaikan latihan memakai jawaban yang terakhir tersimpan; pilihan baru tidak dihitung.</div><?php endif; ?>
		<?php if ($editable): ?>
			<?php echo form_open('latihan/percobaan/'.$attempt['id'], array('id' => 'practice-answer-form')); ?>
			<input type="hidden" name="revision" value="<?php echo (int) $attempt['answer_revision']; ?>">
			<input type="hidden" name="answers_form_start" value="1">
			<div class="alert alert-info"><p class="mb-0" id="sheet-save-help">Gunakan Simpan sementara sebelum beristirahat. Penyimpanan otomatis tersedia saat JavaScript aktif.</p><p class="fw-semibold mt-2 mb-0" id="sheet-save-status" role="status" aria-live="polite">Jawaban yang tampil adalah simpanan terakhir.</p></div>
			<?php if($sheet['rules']['focus_guard']==='1'): ?><div class="alert alert-warning" id="sheet-focus-status" role="status">Pantau fokus aktif: <strong><span data-focus-count><?php echo (int)$sheet['focus_count']; ?></span> / <?php echo (int)$sheet['rules']['focus_limit']; ?> kejadian</strong>. Sesi selesai saat batas tercapai. Pindah tab/aplikasi atau layar tidak aktif (jeda browser lebih dari 30 detik) dapat terhitung. Bukan bukti pasti kecurangan.</div><noscript><p class="alert alert-danger">JavaScript harus diaktifkan agar pemantauan fokus berjalan. Jangan mulai mengerjakan sebelum mengaktifkannya.</p></noscript><?php endif; ?>
		<?php endif; ?>
		<div class="sheet-layout">
		<aside class="sheet-navigation feature-card" aria-label="Navigasi soal">
			<details open><summary class="h5">Nomor soal · <span data-answered-count><?php echo $answered_count; ?></span>/<?php echo count($questions); ?> dijawab</summary>
			<p class="small mb-2 sheet-nav-help">✓ Sudah dijawab · ○ Belum dijawab</p>
			<nav class="sheet-numbers" aria-label="Pilih nomor soal">
			<?php foreach($questions as $i=>$q): $answered=$q['selected_option_id']!==NULL; ?><a class="sheet-number<?php echo $answered?' is-answered':''; ?>" href="#sheet-q-<?php echo $i; ?>" data-nav-index="<?php echo $i; ?>" aria-label="Soal <?php echo $i+1; ?>, <?php echo $answered?'sudah':'belum'; ?> dijawab"><span><?php echo $i+1; ?></span><small aria-hidden="true"><?php echo $answered?'✓':'○'; ?></small></a><?php endforeach; ?>
			</nav></details>
			<?php if($editable): ?><p class="small mt-3 mb-0 sheet-mode-label"><?php echo $sheet['rules']['view_mode']==='single'?'Mode per soal':'Mode ledger · otomatis ke soal berikutnya'; ?></p><p class="fw-bold mt-2 mb-0" id="sheet-timer" role="timer"></p><?php endif; ?>
		</aside>
		<div class="sheet-questions">
		<?php foreach ($questions as $index => $question):
			$media_layout=$question['media_layout']??array('enabled'=>FALSE,'count'=>$question['image_count']??(!empty($question['has_image'])?1:0));
			$gallery=$media_layout['gallery']??($media_layout['count']?range(0,$media_layout['count']-1):array());
			$image_url=static function($i)use($attempt,$question){return site_url('latihan/percobaan/'.$attempt['id'].'/gambar/'.$question['id'].($i?'/'.$i:''));};
		?>
			<fieldset class="feature-card practice-question mb-4" id="sheet-q-<?php echo $index; ?>" tabindex="-1" data-question-id="<?php echo (int) $question['id']; ?>">
				<legend class="h5 float-none w-auto px-2">Soal <?php echo $index + 1; ?> dari <?php echo count($questions); ?></legend>
				<p class="fw-semibold question-rich-text"><?php echo Question_media::render($question['prompt'],$media_layout,$image_url,'soal '.($index+1)); ?></p>
				<?php if($gallery): ?><div class="row g-3 mb-3"><?php foreach($gallery as $image_index): ?><figure class="col-md-6 mb-0"><img class="img-fluid rounded bank-question-image" src="<?php echo $image_url($image_index); ?>" alt="Gambar <?php echo $image_index+1; ?> pendamping soal <?php echo $index+1; ?>" loading="lazy"><figcaption class="small">Gambar <?php echo $image_index+1; ?></figcaption></figure><?php endforeach; ?></div><?php endif; ?>
				<?php foreach ($question['options'] as $option): ?>
							<?php if ( ! $editable): ?><div class="practice-option mb-2"><span class="question-rich-text"><?php echo Question_media::render($option['option_text'],$media_layout,$image_url,'pilihan jawaban'); ?><?php if ((int) $question['selected_option_id'] === (int) $option['id']): ?> <span class="badge text-bg-light"><?php echo $family_report ? 'Pilihan anak' : 'Pilihanmu'; ?></span><?php endif; ?><?php if ($graded && $option['is_correct']): ?> <span class="badge text-bg-success">Jawaban benar</span><?php endif; ?></span></div><?php else: ?>
						<label class="practice-option mb-2"><input type="radio" name="answers[<?php echo (int) $question['id']; ?>]" value="<?php echo (int) $option['id']; ?>"<?php echo (int) $question['selected_option_id'] === (int) $option['id'] ? ' checked' : ''; ?>><span class="question-rich-text"><?php echo Question_media::render($option['option_text'],$media_layout,$image_url,'pilihan jawaban'); ?></span></label>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php if ($graded): ?><div class="alert <?php echo $question['is_correct'] ? 'alert-success' : 'alert-info'; ?> mt-3 mb-0"><strong><?php echo $question['is_correct'] ? 'Sudah tepat.' : ($question['selected_option_id'] === NULL ? 'Belum dijawab. Yuk, pelajari caranya.' : 'Mari tinjau langkahnya.'); ?></strong><p class="mb-0 mt-2"><?php echo trim((string)$question['explanation'])!==''?nl2br(html_escape($question['explanation'])):'Pembahasan belum tersedia. Tinjau jawaban benar bersama pendamping.'; ?></p></div><?php elseif ($editable): ?><label class="practice-option small text-secondary"><input type="radio" name="answers[<?php echo (int) $question['id']; ?>]" value=""<?php echo $question['selected_option_id'] === NULL ? ' checked' : ''; ?>><span>Belum dijawab</span></label><?php endif; ?>
			</fieldset>
		<?php endforeach; ?>
		</div></div>
		<?php if ($editable): ?><div class="sheet-controls feature-card">
			<div class="sheet-paging" hidden><button class="btn btn-outline-primary" type="button" data-sheet-prev>← Sebelumnya</button><button class="btn btn-outline-primary fw-bold" type="button" data-current-label aria-label="Buka navigasi nomor soal" title="Navigasi nomor soal"></button><button class="btn btn-primary" type="button" data-sheet-next>Berikutnya →</button></div>
			<p class="small mb-2">Setelah selesai, jawaban tidak bisa diubah. Soal kosong dihitung belum benar.</p><div class="d-flex flex-wrap gap-2"><button class="btn btn-teal" type="submit" name="action" value="save">Simpan sementara</button><button class="btn btn-primary" type="submit" name="action" value="finish">Selesaikan &amp; lihat hasil</button><a class="btn btn-outline-primary" href="<?php echo site_url('latihan/percobaan/'.$attempt['id']); ?>" id="sheet-reload" hidden>Muat simpanan terbaru</a></div></div><input type="hidden" name="answers_form_end" value="1"><?php echo form_close(); ?><?php else: ?><div class="d-flex flex-wrap gap-2"><?php if ($graded && $can_retry && ! $family_report): ?><a class="btn btn-primary rounded-pill" href="<?php echo site_url('latihan/kursus/'.$attempt['course_id']); ?>">Coba latihan lagi</a><?php elseif ( ! $family_report): ?><p class="text-secondary">Hasil tetap tersimpan. Latihan baru mengikuti akses atau sesi yang tersedia.</p><?php endif; ?><a class="btn btn-outline-primary rounded-pill" href="<?php echo site_url($history_path); ?>">Lihat riwayat</a></div><?php endif; ?>
	</div></div>
</section>
