<section class="container page-section">
<form method="get" class="grade-filter d-flex flex-wrap align-items-end gap-2 mb-3"><input type="hidden" name="jenis" value="<?php echo html_escape($type); ?>"><div><?php echo study_grade_select($kelas); ?></div><button class="btn btn-primary">Filter kelas</button></form>
<p class="eyebrow">Pengelola · Pembelajaran</p><h1 class="h2"><?php echo $type === '' ? 'Arena Belajar' : html_escape(study_arena_type($type)); ?></h1>
<p class="text-secondary"><?php echo $type === '' ? 'Pilih kategori aktivitas untuk melihat dan mengatur isinya.' : 'Atur ketersediaan set pada kategori ini. Menonaktifkan set tidak menghapus progres atau riwayat.'; ?></p>
<div class="admin-toolbar"><?php if ($type !== ''): ?><a class="btn btn-outline-primary" href="<?php echo site_url('kelola/arena'); ?>">← Semua kategori</a><?php endif; ?><a class="btn btn-outline-primary" href="<?php echo html_escape(site_url('kelola/kategori').'?'.http_build_query(array('tab' => 'permainan') + ($type !== '' ? array('jenis' => $type) : array()))); ?>">Master kategori</a><a class="btn btn-primary" href="<?php echo site_url('kelola/pengaturan/arena'); ?>">Aturan Arena</a><a class="btn btn-outline-primary" href="<?php echo site_url('kelola/pembelajaran/modul/arena'); ?>">Monitoring</a><a class="btn btn-outline-primary" href="<?php echo site_url('belajar/arena'); ?>">Buka tampilan peserta</a></div>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php endif; ?>
<?php if ($type === ''): ?>
<div class="admin-card-grid">
<?php foreach (study_arena_games() as $key => $game): ?>
<article class="feature-card"><span class="feature-icon feature-purple" aria-hidden="true"><i class="bi <?php echo html_escape($game['icon']); ?>"></i></span><h2 class="h5"><?php echo html_escape($game['title']); ?></h2><p><?php echo html_escape($game['description']); ?></p><p class="small"><?php echo (int) $groups[$key]['total']; ?> aktivitas · <?php echo (int) $groups[$key]['enabled']; ?> aktif</p><a class="btn btn-outline-primary" href="<?php echo site_url('kelola/arena').'?'.http_build_query(array('jenis'=>$key,'kelas'=>$kelas)); ?>">Kelola <?php echo html_escape($game['title']); ?></a></article>
<?php endforeach; ?>
<article class="feature-card"><span class="feature-icon feature-purple" aria-hidden="true"><i class="bi bi-lightning"></i></span><h2 class="h5">Battle</h2><p>Kelola ruang, publikasi dan moderasi battle. Soal dan peserta tetap mengikuti izin masing-masing ruang.</p><a class="btn btn-outline-primary" href="<?php echo site_url('kelola/battle'); ?>">Kelola Battle</a></article>
</div>
<?php else: ?>
<form action="<?php echo site_url('kelola/arena'); ?>" method="get" class="feature-card mb-4">
<input type="hidden" name="jenis" value="<?php echo html_escape($type); ?>"><input type="hidden" name="kelas" value="<?php echo html_escape($kelas); ?>">
<div class="admin-filter-grid"><div><label class="form-label" for="arena-stage">Jenjang</label><select class="form-select" id="arena-stage" name="jenjang"><option value="">Semua jenjang</option><?php foreach (array('early_childhood','primary','secondary') as $s): ?><option value="<?php echo $s; ?>"<?php echo $stage === $s ? ' selected' : ''; ?>><?php echo study_arena_stage($s); ?></option><?php endforeach; ?></select></div>
<?php if ($master_categories): ?><div><label class="form-label" for="arena-category">Kategori materi</label><select class="form-select" id="arena-category" name="kategori"><option value="">Semua kategori materi</option><?php foreach ($master_categories as $id => $label): ?><option value="<?php echo (int) $id; ?>"<?php echo $category === (string) $id ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option><?php endforeach; ?></select></div><?php endif; ?></div>
<div class="admin-toolbar mb-0"><button class="btn btn-primary">Terapkan filter</button><a class="btn btn-outline-secondary" href="<?php echo site_url('kelola/arena').'?jenis='.rawurlencode($type); ?>">Reset</a></div></form>
<p class="text-secondary"><?php echo (int) $total; ?> aktivitas · halaman <?php echo (int) $page; ?> dari <?php echo (int) $page_count; ?>.</p>
<?php if ( ! $packs): ?><div class="feature-card"><h2 class="h5">Tidak ada aktivitas sesuai filter</h2><p>Ubah jenjang atau kategori materi untuk melihat aktivitas lainnya.</p></div><?php endif; ?>
<div class="admin-card-grid">
<?php $query = array('jenis' => $type, 'jenjang' => $stage, 'kategori' => $category, 'kelas' => $kelas); foreach ($packs as $pack): ?>
<article class="feature-card" data-arena-pack="<?php echo (int) $pack['id']; ?>"><p class="eyebrow"><?php echo study_arena_stage($pack['learning_stage']); ?></p><h2 class="h5"><?php echo html_escape($pack['title']); ?></h2>
<?php if ($pack['master_category']): ?><p class="small"><?php echo html_escape($pack['master_category'].' · '.$pack['grade_name'].' · '.$pack['subject_name']); ?></p><?php endif; ?>
<p><span class="badge <?php echo $pack['enabled'] ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo $pack['enabled'] ? 'Aktif' : 'Nonaktif'; ?></span> <span class="small">Konten v<?php echo (int) $pack['content_version']; ?></span></p>
<?php echo form_open('kelola/arena?'.http_build_query($query + array('halaman' => $page))); ?><input type="hidden" name="pack_id" value="<?php echo (int) $pack['id']; ?>"><input type="hidden" name="revision" value="<?php echo (int) $pack['revision']; ?>"><button class="btn btn-outline-primary" name="enabled" value="<?php echo $pack['enabled'] ? '0' : '1'; ?>"><?php echo $pack['enabled'] ? 'Nonaktifkan set' : 'Aktifkan set'; ?></button><?php echo form_close(); ?>
</article><?php endforeach; ?></div>
<nav class="d-flex flex-wrap gap-3 mt-4" aria-label="Halaman konten Arena"><?php if ($page > 1): ?><a href="<?php echo html_escape(site_url('kelola/arena').'?'.http_build_query($query + array('halaman' => $page - 1))); ?>">Sebelumnya</a><?php endif; ?><?php if ($page < $page_count): ?><a href="<?php echo html_escape(site_url('kelola/arena').'?'.http_build_query($query + array('halaman' => $page + 1))); ?>">Berikutnya</a><?php endif; ?></nav>
<?php endif; ?>
</section>
