<section class="container page-section">
<a class="btn btn-outline-secondary mb-3" href="<?php echo site_url($help_admin ? 'kelola/bantuan' : 'bantuan'); ?>">Kembali ke <?php echo $help_admin ? 'Kotak Bantuan' : 'Bantuan'; ?></a>
<h1 class="h3 text-break">#<?php echo (int) $ticket['id']; ?> — <?php echo html_escape($ticket['subject']); ?></h1>
<p><?php echo html_escape($categories[$ticket['category']]); ?> · <strong><?php echo html_escape($statuses[$ticket['status']]); ?></strong></p>
<?php if ($help_admin): ?><div class="alert alert-warning">Percakapan privat; pembukaan dan balasan Anda dicatat dalam audit. <?php if ($ticket['user_id']): ?>Dikirim dari akun #<?php echo (int) $ticket['user_id']; ?>.<?php else: ?>Pengirim tanpa akun; identitas belum terverifikasi.<?php endif; ?> Verifikasi kewenangan secara terpisah sebelum tindakan terkait akun, anak, atau pembayaran. Kode percakapan bukan bukti kepemilikan akun.</div><?php endif; ?>
<?php if ($help_code): ?><div class="alert alert-warning"><h2 class="h5">Simpan kode rahasia ini sekarang</h2><p>Kode hanya ditampilkan setelah pengiriman. Gunakan formulir “Buka balasan dengan kode” pada halaman Bantuan. Siapa pun yang memiliki kode dapat membaca dan membalas pesan ini.</p><label class="form-label" for="help-code">Kode akses</label><textarea id="help-code" class="form-control" rows="3" readonly spellcheck="false"><?php echo html_escape($help_code); ?></textarea></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert" id="help-error"><?php echo html_escape($error); ?> <a href="<?php echo site_url($path); ?>">Muat ulang percakapan</a></div><?php endif; ?>
<section aria-label="Isi percakapan" class="d-grid gap-3 mb-4">
<?php foreach ($messages as $message): ?><article class="card card-body"><div class="d-flex flex-wrap justify-content-between gap-2 mb-2"><strong><?php echo $message['is_admin'] ? 'Pengelola Project Pintar' : 'Pengirim'; ?></strong><span class="small text-secondary"><?php echo html_escape(gmdate('d/m/Y H:i', strtotime($message['created_at'].' UTC') + 25200)); ?> WIB</span></div><div class="text-break"><?php echo nl2br(html_escape($message['body'])); ?></div></article><?php endforeach; ?>
</section>
<nav class="d-flex flex-wrap gap-3 align-items-center mb-4" aria-label="Halaman percakapan">
<?php if ($page > 1): ?><a href="<?php echo site_url($path).'?halaman='.($page - 1); ?>">Pesan lebih baru</a><?php endif; ?>
<span>Halaman <?php echo (int) $page; ?> dari <?php echo (int) $pages; ?> (terbaru dahulu)</span>
<?php if ($page < $pages): ?><a href="<?php echo site_url($path).'?halaman='.($page + 1); ?>">Pesan sebelumnya</a><?php endif; ?>
</nav>
<?php if ($ticket['status'] !== 'closed' || $help_admin): ?><section class="card card-body"><h2 class="h4">Balas pesan<?php echo $help_admin && $ticket['status'] === 'closed' ? ' / buka kembali' : ''; ?></h2><p class="small text-secondary">Tidak ada pemberitahuan email otomatis. Jangan menyertakan sandi, kode Google, atau dokumen identitas.</p>
<?php echo form_open($path); ?><input type="hidden" name="request_key" value="<?php echo html_escape($request_key); ?>"><input type="hidden" name="revision" value="<?php echo (int) ($submitted_revision ?? $ticket['revision']); ?>">
<label for="body" class="form-label">Pesan (5–3.000 karakter)</label><textarea id="body" name="body" class="form-control mb-3" rows="6" minlength="5" maxlength="3000" required<?php echo study_field_error_attributes($errors, 'body'); ?>><?php echo html_escape($values['body'] ?? ''); ?></textarea><?php echo study_render_field_error($errors, 'body'); ?>
<?php if ($help_admin): ?><label for="status" class="form-label">Status setelah dibalas</label><select id="status" name="status" class="form-select mb-3"><?php foreach ($statuses as $key => $label): ?><option value="<?php echo $key; ?>" <?php echo $key === 'waiting' ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option><?php endforeach; ?></select><?php endif; ?>
<button type="submit" class="btn btn-primary" <?php echo ! empty($blocked) ? 'disabled' : ''; ?>>Simpan balasan</button><?php echo form_close(); ?></section>
<?php else: ?><div class="alert alert-info">Percakapan ditutup. Jika masih memerlukan bantuan, buat pesan baru dengan menyebutkan nomor percakapan ini (tanpa kode rahasia).</div><?php endif; ?>
</section>
