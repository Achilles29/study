<?php $preview = !empty($review_preview); ?>
<section class="container page-section">
 <?php if ($preview): ?>
 <aside class="alert alert-warning mb-4" aria-label="Informasi pratinjau">
  <h2 class="h5"><i class="bi bi-eye me-2" aria-hidden="true"></i>Pratinjau khusus pengelola — 1.000 ulasan contoh</h2>
  <p>Nama, pesan, bintang, dan tanggal berikut adalah data simulasi untuk melihat tampilan, bukan pengalaman pengguna nyata. Tidak disimpan ke database dan tidak memengaruhi rating publik.</p>
  <p class="mb-3">900 contoh bintang 5 · 100 contoh bintang 4 · tanggal contoh sepanjang 2025 · 20 ulasan per halaman.</p>
  <a class="btn btn-primary" href="<?php echo site_url('kelola/ulasan'); ?>">Kembali ke pengelolaan</a>
 </aside>
 <?php endif; ?>
 <div class="page-heading mb-4"><div><p class="eyebrow"><i class="bi bi-stars me-2" aria-hidden="true"></i>Cerita belajar bersama</p><h1 class="h2">Ulasan pengguna<?php echo $preview?' — pratinjau':''; ?></h1><p class="text-secondary mb-0">Pengalaman dan masukan pengguna Project Pintar. Bintang 1–5 sama-sama diterima.</p></div>
 <?php if (!$preview): ?><a class="btn btn-sun" href="<?php echo site_url('ulasan/tulis'); ?>">Tulis ulasan</a><?php endif; ?></div>
 <?php if ($report['summary']['total']): ?>
 <p class="alert alert-info"><strong><?php echo number_format((float)$report['summary']['average'],1,',','.'); ?> / 5</strong> dari <?php echo number_format((int)$report['summary']['total'],0,',','.'); ?> <?php echo $preview?'ulasan contoh. Rata-rata simulasi, bukan rating pengguna.':'ulasan yang telah dimoderasi. Bukan penilaian independen; data simulasi tidak dihitung.'; ?></p>
 <?php else: ?><div class="empty-card"><span class="empty-icon"><i class="bi bi-chat-heart" aria-hidden="true"></i></span><h2 class="h4">Jadilah yang pertama berbagi</h2><p>Belum ada ulasan yang dipublikasikan. Ceritakan pengalaman belajarmu dengan jujur.</p></div><?php endif; ?>
 <div class="row g-3"><?php foreach ($report['rows'] as $r): ?>
  <div class="col-md-6"><article class="feature-card h-100" data-review-card>
   <p class="review-stars" aria-label="<?php echo (int)$r['rating']; ?> dari 5 bintang"><?php for ($i=1;$i<=5;$i++): ?><i class="bi <?php echo $i<=$r['rating']?'bi-star-fill':'bi-star'; ?>" aria-hidden="true"></i><?php endfor; ?></p>
   <h2 class="h5"><?php echo html_escape($r['display_name']); ?></h2>
   <p class="small text-secondary"><?php echo html_escape(study_format_datetime($r['created_at'])); ?> · <?php echo $preview?'Profil contoh · Simulasi':'Telah dimoderasi'; ?></p>
   <p class="text-break mb-0"><?php echo nl2br(html_escape($r['body'])); ?></p>
  </article></div>
 <?php endforeach; ?></div>
 <?php $review_base=$preview?'kelola/ulasan/pratinjau':'ulasan';$review_query=array();$this->load->view('reviews/pagination',compact('report','review_base','review_query')); ?>
</section>
