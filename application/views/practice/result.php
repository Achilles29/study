<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$total = (int)$attempt['question_count'];
$correct = (int)$attempt['correct_count'];
$answered = count(array_filter($questions, static function($q) { return $q['selected_option_id'] !== NULL; }));
$wrong = $answered - $correct;
$empty = $total - $answered;
$score_label = number_format((float)$attempt['score'], 2, ',', '.');
$started = strtotime($attempt['started_at'].' UTC');
$submitted = !empty($attempt['submitted_at']) ? strtotime($attempt['submitted_at'].' UTC') : FALSE;
$elapsed = $started !== FALSE && $submitted !== FALSE ? max(0, $submitted - $started) : NULL;
$duration = $elapsed === NULL ? 'Tidak tersedia' : ($elapsed >= 3600 ? intdiv($elapsed, 3600).' jam ' : '').intdiv($elapsed % 3600, 60).' menit '.($elapsed % 60).' detik';
$retry_path = $attempt['session_type'] === 'self_practice' ? 'latihan/mandiri' : ($attempt['session_type'] === 'assessment' ? 'latihan/ujian' : ($can_retry ? 'latihan/kursus/'.(int)$attempt['course_id'] : NULL));
$retry_label = $attempt['session_type'] === 'assessment' ? 'Pilih simulasi ujian' : 'Latihan lagi';
?>
<link rel="stylesheet" href="<?php echo base_url('assets/css/practice-sheet.css?v='.filemtime(FCPATH.'assets/css/practice-sheet.css')); ?>">
<section class="container page-section practice-result" id="practice-result">
 <a class="back-link mb-3" href="<?php echo site_url('latihan'); ?>">Riwayat latihan saya</a>
 <header class="result-hero">
  <div>
   <span class="result-badge"><i class="bi bi-check2-circle" aria-hidden="true"></i>Latihan selesai</span>
   <h1>Nilai latihanmu</h1>
   <p class="fw-semibold mb-2"><?php echo html_escape($attempt['title']); ?></p>
   <p class="mb-0">Terima kasih sudah berusaha! Lihat hasilmu, lalu pelajari pembahasan untuk memahami setiap langkah.</p>
  </div>
  <div class="result-score" aria-label="Nilai <?php echo html_escape($score_label); ?> dari 100"><strong data-result-score><?php echo html_escape($score_label); ?></strong><span>dari 100</span></div>
 </header>
 <div class="mt-4"><h2 class="h4 mb-2">Rincian jawaban</h2><p class="mb-0"><strong><?php echo $answered; ?> dari <?php echo $total; ?> soal</strong> dijawab. Hasil ini tersimpan di riwayat latihanmu.</p></div>
 <dl class="result-stats">
  <div class="result-stat correct"><dt><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Benar</dt><dd data-result-correct><?php echo $correct; ?></dd></div>
  <div class="result-stat wrong"><dt><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Salah</dt><dd data-result-wrong><?php echo $wrong; ?></dd></div>
  <div class="result-stat"><dt><i class="bi bi-dash-circle me-1" aria-hidden="true"></i>Belum dijawab</dt><dd data-result-empty><?php echo $empty; ?></dd></div>
 </dl>
 <section class="result-calculation" aria-labelledby="result-calculation-title">
  <h2 class="h4" id="result-calculation-title"><i class="bi bi-calculator me-2" aria-hidden="true"></i>Bagaimana nilainya dihitung?</h2>
  <p>Jumlah jawaban benar ÷ jumlah seluruh soal × 100.</p>
  <p class="result-equation" data-result-equation><?php echo $correct; ?> ÷ <?php echo $total; ?> × 100 = <?php echo html_escape($score_label); ?></p>
  <p class="small text-secondary">Setiap soal berbobot sama. Jawaban benar mendapat 1 poin; jawaban salah atau kosong mendapat 0 poin, tanpa pengurangan nilai. Nilai ditampilkan hingga dua angka desimal.</p>
  <dl class="result-meta mb-0">
   <div><dt>Selesai pada</dt><dd><?php echo $submitted !== FALSE ? html_escape(study_format_datetime($attempt['submitted_at'])) : 'Tidak tersedia'; ?></dd></div>
   <div><dt>Rentang pengerjaan</dt><dd><?php echo html_escape($duration); ?><span class="d-block small text-secondary">Dari mulai hingga dikumpulkan, termasuk jeda.</span></dd></div>
  </dl>
 </section>
 <?php if ($sheet['rules']['focus_guard'] === '1'): ?><div class="alert alert-info mt-3 mb-0"><strong>Catatan fokus: <?php echo (int)$sheet['focus_count']; ?> / <?php echo (int)$sheet['rules']['focus_limit']; ?> kejadian.</strong> <?php echo $sheet['focus_count'] >= (int)$sheet['rules']['focus_limit'] ? 'Sesi diselesaikan otomatis karena batas perpindahan tercapai.' : 'Sesi selesai sebelum batas perpindahan tercapai.'; ?> Catatan ini bukan bukti kecurangan.</div><?php endif; ?>
 <nav class="result-actions" aria-label="Langkah setelah latihan">
  <a class="btn btn-primary" href="<?php echo site_url('latihan/percobaan/'.(int)$attempt['id'].'/pembahasan'); ?>"><i class="bi bi-journal-text me-2" aria-hidden="true"></i>Lihat pembahasan</a>
  <?php if ($retry_path): ?><a class="btn btn-teal" href="<?php echo site_url($retry_path); ?>"><i class="bi bi-arrow-repeat me-2" aria-hidden="true"></i><?php echo $retry_label; ?></a><?php endif; ?>
  <a class="btn btn-outline-primary" href="<?php echo site_url('latihan'); ?>"><i class="bi bi-clock-history me-2" aria-hidden="true"></i>Riwayat latihan</a>
  <a class="btn btn-sun" href="<?php echo site_url('belajar/arena'); ?>"><i class="bi bi-controller me-2" aria-hidden="true"></i>Arena belajar</a>
 </nav>
 <?php if ($retry_path): ?><p class="small text-secondary mt-3">Latihan baru mengikuti kuota dan akses yang berlaku. Tombol di atas membuka pilihan latihan, tidak langsung memulai atau memotong kredit.</p><?php endif; ?>
</section>
