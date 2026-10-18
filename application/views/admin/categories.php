<?php $master_url = static function ($params = array()) { return html_escape(site_url('kelola/kategori').($params ? '?'.http_build_query($params) : '')); }; ?>
<section class="container page-section">
<div class="page-heading"><div><p class="eyebrow">Pengelola · Pembelajaran</p><h1 class="h2">Data master pembelajaran</h1><p class="text-secondary mb-0">Pilih kelompok terlebih dahulu, lalu buka turunannya sesuai kebutuhan.</p></div><?php if ($tab === 'mapel'): ?><a class="btn btn-primary" href="<?php echo site_url('kelola/kategori/tambah'); ?>">Tambah kategori</a><?php endif; ?></div>
<?php if ($tab !== ''): ?>
<nav class="monitor-tabs" aria-label="Kelompok data master"><a href="<?php echo $master_url(); ?>">Semua master</a><?php foreach (array('mapel' => 'Mata pelajaran', 'jenjang' => 'Jenjang TK–SMA', 'permainan' => 'Kategori permainan') as $key => $label): ?><a href="<?php echo $master_url(array('tab' => $key)); ?>"<?php echo $tab === $key ? ' aria-current="page"' : ''; ?>><?php echo $label; ?></a><?php endforeach; ?></nav>
<?php endif; ?>
<?php if ($tab === ''): ?>
<div class="admin-card-grid">
<?php foreach (array('mapel' => array('Mata pelajaran', count($categories), 'kategori mata pelajaran', 'bi-book'), 'jenjang' => array('Jenjang pendidikan', count($grades), 'kelas / kelompok TK–SMA', 'bi-mortarboard'), 'permainan' => array('Kategori permainan', count($all_games), 'kategori materi dalam '.count($game_groups).' jenis permainan', 'bi-controller')) as $key => $group): ?>
<article class="feature-card" data-master-group="<?php echo $key; ?>"><span class="feature-icon feature-purple" aria-hidden="true"><i class="bi <?php echo $group[3]; ?>"></i></span><h2 class="h5"><?php echo $group[0]; ?></h2><p><?php echo $group[1].' '.$group[2]; ?></p><a class="btn btn-outline-primary" href="<?php echo $master_url(array('tab' => $key)); ?>">Buka <?php echo strtolower($group[0]); ?></a></article>
<?php endforeach; ?></div>
<?php elseif ($tab === 'mapel'): ?>
<h2 class="h4">Mata pelajaran</h2><form method="get" class="grade-filter d-flex flex-wrap align-items-end gap-2 mb-3"><input type="hidden" name="tab" value="mapel"><div><?php echo study_grade_select($grade_code); ?></div><button class="btn btn-primary">Filter kelas</button><a class="btn btn-outline-secondary" href="<?php echo site_url('kelola/kategori?tab=mapel'); ?>">Semua kelas</a></form><p class="small">Filter menampilkan mapel yang sudah memiliki kursus atau kategori permainan tertaut ke kelas tersebut; bukan daftar kurikulum wajib.</p><p><?php echo count($categories); ?> kategori tersedia untuk formulir kursus. Buka keterangan hanya jika diperlukan.</p>
<div class="admin-card-grid"><?php foreach ($categories as $category): ?><article class="feature-card"><h3 class="h5"><?php echo html_escape($category['name']); ?></h3><p class="small"><?php echo $category['status'] === 'active' ? 'Aktif' : 'Arsip'; ?></p><?php if ($category['description']): ?><details><summary>Keterangan</summary><p><?php echo html_escape($category['description']); ?></p></details><?php endif; ?></article><?php endforeach; ?></div>
<?php if ( ! $categories): ?><p>Belum ada kategori. Tambahkan kategori sesuai materi yang tersedia.</p><?php endif; ?>
<?php elseif ($tab === 'jenjang'): ?>
<?php if ($stage === ''): ?>
<h2 class="h4">Pilih jenjang pendidikan</h2><div class="admin-card-grid"><?php foreach ($stages as $key => $label): ?><?php $children = array_filter($grades, static function ($g) use ($key) { return $g['learning_stage'] === $key; }); ?>
<article class="feature-card" data-master-stage="<?php echo $key; ?>"><h3 class="h5"><?php echo $label; ?></h3><p><?php echo count($children); ?> kelas / kelompok</p><a class="btn btn-outline-primary" href="<?php echo $master_url(array('tab' => 'jenjang', 'jenjang' => $key)); ?>">Buka <?php echo $label; ?></a></article><?php endforeach; ?></div>
<?php else: ?>
<p><a href="<?php echo $master_url(array('tab' => 'jenjang')); ?>">← Semua jenjang</a></p><h2 class="h4"><?php echo $stages[$stage]; ?></h2>
<div class="admin-card-grid"><?php foreach ($grades as $grade): ?><?php if ($grade['learning_stage'] === $stage): ?><article class="feature-card" data-master-grade="<?php echo html_escape($grade['code']); ?>"><h3 class="h5"><?php echo html_escape($grade['name']); ?></h3><p class="small">Kode referensi: <?php echo html_escape($grade['code']); ?></p></article><?php endif; ?><?php endforeach; ?></div>
<?php endif; ?>
<?php else: ?>
<nav aria-label="Urutan kategori permainan" class="mb-3"><ol class="breadcrumb">
<li class="breadcrumb-item"><?php if ($game): ?><a href="<?php echo $master_url(array('tab' => 'permainan')); ?>">Jenis permainan</a><?php else: ?>Jenis permainan<?php endif; ?></li>
<?php if ($game): ?><li class="breadcrumb-item"><?php if ($stage): ?><a href="<?php echo $master_url(array('tab' => 'permainan', 'jenis' => $game)); ?>"><?php echo html_escape(study_arena_type($game)); ?></a><?php else: ?><?php echo html_escape(study_arena_type($game)); ?><?php endif; ?></li><?php endif; ?>
<?php if ($stage): ?><li class="breadcrumb-item"><?php if ($grade_code): ?><a href="<?php echo $master_url(array('tab' => 'permainan', 'jenis' => $game, 'jenjang' => $stage)); ?>"><?php echo $stages[$stage]; ?></a><?php else: ?><?php echo $stages[$stage]; ?><?php endif; ?></li><?php endif; ?>
<?php if ($selected_grade): ?><li class="breadcrumb-item" aria-current="page"><?php echo html_escape($selected_grade); ?></li><?php endif; ?>
</ol></nav>
<?php if ($game === ''): ?>
<p><?php echo count($game_groups); ?> jenis permainan · <?php echo count($all_games); ?> kategori materi · <?php echo array_sum(array_column($all_games, 'pack_count')); ?> set Arena tertaut. Kategori materi mengelompokkan set; jumlahnya tidak harus sama dengan jumlah permainan.</p>
<h2 class="h4">Pilih jenis permainan</h2><div class="admin-card-grid"><?php foreach ($game_groups as $key => $count): ?><article class="feature-card" data-master-game="<?php echo html_escape($key); ?>"><h3 class="h5"><?php echo html_escape(study_arena_type($key)); ?></h3><p><?php echo (int) $count; ?> kategori materi</p><a class="btn btn-outline-primary" href="<?php echo $master_url(array('tab' => 'permainan', 'jenis' => $key)); ?>">Buka kelompok</a></article><?php endforeach; ?></div>
<div class="feature-card mt-4" data-master-battle><h2 class="h5">Battle</h2><p>Aktivitas komunitas berbasis ruang dan soal penyelenggara, bukan set katalog Arena. Pengaturan dan moderasinya tetap terpisah; tidak menyalin soal privat ke data master.</p><?php if (in_array('system_admin', $current_user['roles'], TRUE)): ?><a class="btn btn-outline-primary" href="<?php echo site_url('kelola/battle'); ?>">Kelola Battle</a><?php endif; ?></div>
<?php elseif ($stage === ''): ?>
<h2 class="h4"><?php echo html_escape(study_arena_type($game)); ?> · pilih jenjang</h2><div class="admin-card-grid"><?php foreach ($stages as $key => $label): ?><?php if (isset($stage_groups[$key])): ?><article class="feature-card" data-master-stage="<?php echo $key; ?>"><h3 class="h5"><?php echo $label; ?></h3><p><?php echo (int) $stage_groups[$key]; ?> kategori materi</p><a class="btn btn-outline-primary" href="<?php echo $master_url(array('tab' => 'permainan', 'jenis' => $game, 'jenjang' => $key)); ?>">Buka <?php echo $label; ?></a></article><?php endif; ?><?php endforeach; ?></div>
<?php elseif ($grade_code === ''): ?>
<h2 class="h4"><?php echo $stages[$stage]; ?> · pilih kelas / kelompok</h2><div class="admin-card-grid"><?php foreach ($grade_groups as $key => $group): ?><article class="feature-card" data-master-grade="<?php echo html_escape($key); ?>"><h3 class="h5"><?php echo html_escape($group['name']); ?></h3><p><?php echo (int) $group['count']; ?> kategori materi</p><a class="btn btn-outline-primary" href="<?php echo $master_url(array('tab' => 'permainan', 'jenis' => $game, 'jenjang' => $stage, 'kelas' => $key)); ?>">Lihat kategori</a></article><?php endforeach; ?></div>
<?php else: ?>
<h2 class="h4">Kategori materi · <?php echo html_escape($selected_grade); ?></h2><div class="admin-card-grid"><?php foreach ($game_categories as $category): ?>
<article class="feature-card" data-master-category="<?php echo (int) $category['id']; ?>"><h3 class="h5"><?php echo html_escape($category['name']); ?></h3><p><?php echo html_escape($category['subject_name']); ?></p>
<?php if ($category['description']): ?><details class="mb-3"><summary>Keterangan kategori</summary><p class="mt-2"><?php echo html_escape($category['description']); ?></p></details><?php endif; ?>
<?php if ((int) $category['pack_count'] > 0): ?><p class="small"><?php echo (int) $category['pack_count']; ?> set tertaut</p><?php if (in_array('system_admin', $current_user['roles'], TRUE)): ?><a class="btn btn-outline-primary" href="<?php echo html_escape(site_url('kelola/arena').'?'.http_build_query(array('jenis' => $category['game_type'], 'kategori' => $category['id']))); ?>">Lihat aktivitas</a><?php endif; ?>
<?php else: ?><p class="small">Master tersedia. Belum ada set permainan tertaut; soal dan isi permainan tidak ikut diimpor.</p><?php endif; ?>
</article><?php endforeach; ?></div>
<?php endif; ?>
<?php endif; ?>
<p class="monitor-note mt-4">Referensi tersimpan mandiri di Project Pintar. Adopsi master tidak menyalin akun, soal, progres atau transaksi; tidak mengubah jenjang anak atau otomatis membuka materi SMA.</p>
</section>
