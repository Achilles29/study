<?php $rules=Practice_sheet::read($exam['sheet_rules_json']??NULL); ?>
<section class="container page-section"><div class="row justify-content-center"><div class="col-lg-8">
 <a class="back-link mb-3" href="<?php echo site_url('latihan/ujian'); ?>">Daftar Simulasi Ujian</a>
 <div class="learning-hero mb-4"><p class="eyebrow">Siap mencoba?</p><h1 class="h2"><?php echo html_escape($exam['title']); ?></h1><p class="mb-0"><?php echo html_escape(study_grade_label($exam['grade_id'])); ?> · <?php echo (int)$count; ?> soal · <?php echo html_escape(array('easy'=>'Mudah','medium'=>'Sedang','hard'=>'Susah')[$exam['question_difficulty']??'']??'Campuran'); ?></p></div>
 <?php if($error): ?><div class="alert alert-warning" role="alert"><?php echo html_escape($error); ?></div><?php endif; ?>
 <div class="feature-card"><h2 class="h4">Sebelum mulai</h2>
 <ul>
  <li><?php echo $exam['duration_minutes']?'Batas waktu '.(int)$exam['duration_minutes'].' menit sejak mulai. Membuka ulang tidak mereset waktu.':'Tanpa batas waktu.'; ?></li>
  <li><?php echo $unlimited?'Tanpa batas percobaan harian · Platinum Selamanya.':'Maksimal '.(int)$exam['max_daily'].' percobaan per hari WIB.'; ?></li>
  <li>Tampilan: <?php echo $rules['view_mode']==='single'?'per soal, navigasi nomor':'ledger, semua soal dengan gulir otomatis'; ?>.</li>
  <li>Jawaban tersimpan otomatis saat JavaScript aktif dan terhubung. Setelah waktu habis, hanya jawaban yang sudah tersimpan dihitung.</li>
  <li>Jangka aktif (WIB): <?php echo html_escape(str_replace('T',' ',Exam_schedule::to_form($exam['available_from']))?:'Sudah dibuka'); ?> sampai <?php echo html_escape(str_replace('T',' ',Exam_schedule::to_form($exam['available_until']))?:'tanpa batas tanggal'); ?>.</li>
  <li>Pembahasan muncul setelah selesai. Hasil tersimpan di riwayat dan rapor.</li>
 </ul>
 <?php if($rules['focus_guard']==='1'): ?>
 <div class="alert alert-warning"><strong>Anti-cheating / pantau fokus aktif.</strong> Sesi otomatis selesai pada perpindahan ke-<?php echo (int)$rules['focus_limit']; ?>. Pindah tab/aplikasi, kehilangan fokus jendela, dan layar tidak aktif dapat terhitung. Jeda browser lebih dari 30 detik juga dicatat; browser tidak selalu dapat membedakan sleep. Catatan ini bukan bukti kecurangan. Aktifkan JavaScript dan pastikan koneksi stabil. Saat offline, kejadian dikirim setelah tersambung.</div>
 <?php else: ?><p>Pantau fokus nonaktif.</p><?php endif; ?>
 <p class="small text-secondary">Pengaturan baru tidak mengubah sesi yang sudah dimulai.</p>
 <?php if($notice): ?><p class="alert alert-info"><?php echo html_escape($notice); ?></p><?php endif; ?>
 <?php if($active && (int)$active['exam_id']===(int)$exam['id']): ?><a class="btn btn-primary" href="<?php echo site_url('latihan/percobaan/'.$active['id']); ?>">Lanjutkan sesi saya</a>
 <?php elseif($available && $can_start): ?>
 <?php echo form_open('latihan/ujian/'.$exam['id']); ?><input type="hidden" name="start_token" value="<?php echo html_escape($token); ?>"><button class="btn btn-primary" type="submit">Mulai / lanjutkan</button><?php echo form_close(); ?>
 <?php elseif($available): ?><a class="btn btn-sun" href="<?php echo site_url('latihan/buka/exam/'.$exam['id']); ?>">Lihat akses · <?php echo (int)$exam['credit_price']; ?> kredit</a><?php endif; ?>
 </div></div></div></section>
