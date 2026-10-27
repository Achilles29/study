<?php $active=$admin_tab??'pengaturan'; ?>
<nav class="ec-admin-nav" aria-label="Pengelolaan English Hack">
 <a class="ec-admin-brand" href="<?php echo site_url('kelola/kelas-inggris'); ?>">English <em>Hack</em><small>Ruang pengelola</small></a>
 <div class="ec-admin-tabs">
 <?php foreach(array('pengaturan'=>array('Pengaturan kelas','kelola/kelas-inggris'),'penilaian'=>array('Penilaian tugas','kelola/kelas-inggris?tab=penilaian'),'kurikulum'=>array('Kurikulum & buku','kelola/kelas-inggris/konsep')) as $key=>$item): ?>
 <a href="<?php echo site_url($item[1]); ?>" <?php echo $active===$key?'aria-current="page"':''; ?>><?php echo $item[0]; ?></a>
 <?php endforeach; ?></div>
 <a class="eh-btn" href="<?php echo site_url('belajar/kelas-inggris'); ?>">Lihat kelas pengguna ↗</a>
</nav>
