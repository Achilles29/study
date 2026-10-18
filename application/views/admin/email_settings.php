<?php $is_smtp = $record['provider'] === 'smtp'; $selected = $record['provider'] === $record['active_provider']; ?>
<section class="container page-section">
<p><a href="<?php echo site_url('kelola/pengaturan'); ?>">← Pusat pengaturan</a></p>
<h1 class="h2">Email & verifikasi akun</h1>
<p>Pilih satu penyedia untuk verifikasi dan pemulihan sandi. Kedua profil disimpan terpisah. Bukan Google Sign-in atau email pemasaran; pendaftaran publik tidak otomatis dibuka.</p>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php endif; ?>
<p>Penyedia terpilih: <strong><?php echo $record['active_provider'] === 'smtp' ? 'Mail server / email domain' : 'Gmail'; ?></strong>. Membuka profil lain tidak mengubah layanan.</p>
<nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Profil pengiriman email">
<a class="btn <?php echo ! $is_smtp ? 'btn-primary' : 'btn-outline-primary'; ?>" <?php echo ! $is_smtp ? 'aria-current="page"' : ''; ?> href="<?php echo site_url('kelola/pengaturan/email').'?provider=gmail'; ?>">1. Gmail</a>
<a class="btn <?php echo $is_smtp ? 'btn-primary' : 'btn-outline-primary'; ?>" <?php echo $is_smtp ? 'aria-current="page"' : ''; ?> href="<?php echo site_url('kelola/pengaturan/email').'?provider=smtp'; ?>">2. Mail server / email domain</a>
</nav>
<div class="feature-card mb-4">
<h2 class="h4">Simpan <?php echo $is_smtp ? 'email domain' : 'Gmail'; ?></h2>
<?php if ($is_smtp): ?>
<p>Bisa memakai SMTP hosting email atau mail server sendiri. Halaman ini tidak memasang mail server. Gunakan hostname publik dengan alamat IPv4 dan sertifikat TLS sah; localhost, IP privat dan port 25 tidak didukung. Siapkan SPF, DKIM, DMARC dan reputasi pengirim bersama pengelola email.</p>
<?php else: ?>
<p>Gunakan Gmail khusus Project Pintar. Aktifkan verifikasi dua langkah lalu buat <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener noreferrer">App Password Google</a> jika tersedia. Jangan gunakan password utama Gmail. Koneksi tetap <code>smtp.gmail.com:587</code> dengan STARTTLS dan pemeriksaan sertifikat.</p>
<?php endif; ?>
<?php echo form_open('kelola/pengaturan/email'); ?>
<input type="hidden" name="action" value="save"><input type="hidden" name="provider" value="<?php echo html_escape($record['provider']); ?>"><input type="hidden" name="revision" value="<?php echo (int) $record['revision']; ?>">
<label class="form-label" for="mail-sender">Alamat email pengirim</label>
<input class="form-control mb-3" id="mail-sender" name="sender_email" type="email" maxlength="190" value="<?php echo html_escape($record['sender_email']); ?>" required autocomplete="off" placeholder="<?php echo $is_smtp ? 'noreply@projectpintar.com' : 'akun@gmail.com'; ?>">
<?php if ($is_smtp): ?>
<label class="form-label" for="mail-host">Hostname SMTP (bukan URL)</label>
<input class="form-control mb-3" id="mail-host" name="smtp_host" maxlength="253" value="<?php echo html_escape($record['smtp_host']); ?>" placeholder="mail.projectpintar.com" required autocomplete="off">
<label class="form-label" for="mail-port">Port & keamanan</label>
<select class="form-select mb-3" id="mail-port" name="smtp_port"><option value="587"<?php echo (int) $record['smtp_port'] === 587 ? ' selected' : ''; ?>>587 — STARTTLS wajib</option><option value="465"<?php echo (int) $record['smtp_port'] === 465 ? ' selected' : ''; ?>>465 — TLS langsung</option></select>
<label class="form-label" for="mail-username">Username SMTP</label>
<input class="form-control mb-3" id="mail-username" name="smtp_username" maxlength="190" value="<?php echo html_escape($record['smtp_username']); ?>" required autocomplete="off">
<?php endif; ?>
<label class="form-label" for="mail-secret"><?php echo $is_smtp ? 'Password SMTP' : 'App Password (16 karakter)'; ?></label>
<input class="form-control mb-2" id="mail-secret" name="app_password" type="password" autocomplete="new-password" maxlength="<?php echo $is_smtp ? 512 : 32; ?>">
<p class="small">Kosongkan untuk mempertahankan rahasia profil ini. Wajib isi saat mengganti pengirim, host atau username. Rahasia terenkripsi dan tidak ditampilkan kembali. Pergantian penyedia/koneksi wajib disimpan nonaktif lalu diuji ulang. Tidak ada fallback otomatis ke penyedia lain.</p>
<label class="form-label" for="mail-enabled">Status pengiriman pengguna</label>
<select class="form-select mb-3" id="mail-enabled" name="enabled"><option value="0"<?php echo ! $record['enabled'] ? ' selected' : ''; ?>>Nonaktif / konfigurasi dan pengujian</option><option value="1"<?php echo $record['enabled'] ? ' selected' : ''; ?>>Aktif setelah tes berhasil</option></select>
<div class="form-check mb-3"><input class="form-check-input" id="mail-confirm" name="inbox_confirm" value="1" type="checkbox"><label class="form-check-label" for="mail-confirm">Jika mengaktifkan: saya sudah menerima pesan uji di kotak masuk pengirim.</label></div>
<label class="form-label" for="mail-admin-password">Kata sandi akun Project Pintar Anda</label><input class="form-control mb-3" id="mail-admin-password" name="current_password" type="password" autocomplete="current-password" required>
<button class="btn btn-primary">Simpan & pilih profil ini</button>
<?php echo form_close(); ?>
</div>
<div class="feature-card">
<h2 class="h4">Uji pengiriman, lalu aktifkan</h2>
<p>Profil: <?php echo $record['configured'] ? 'tersimpan' : 'belum diisi'; ?> · <?php echo $selected ? ($record['enabled'] ? 'layanan aktif' : 'layanan nonaktif') : 'bukan penyedia terpilih'; ?> · revisi <?php echo (int) $record['revision']; ?>.</p>
<p>Hasil SMTP: <?php echo array('untested' => 'belum diuji', 'sent' => 'diterima server SMTP; cek inbox', 'failed' => 'gagal/tidak dapat dipastikan')[$record['probe_status']] ?? 'belum tersedia'; ?><?php if ($record['probe_at']): ?> · <?php echo html_escape(study_format_datetime($record['probe_at'])); ?><?php endif; ?>.</p>
<p>Pesan uji hanya dikirim ke alamat pengirim sendiri. Simpan dan pilih profil terlebih dahulu. Tes tidak memverifikasi akun dan tidak menjamin pesan masuk inbox.</p>
<?php echo form_open('kelola/pengaturan/email'); ?><input type="hidden" name="action" value="probe"><input type="hidden" name="revision" value="<?php echo (int) $record['revision']; ?>">
<label class="form-label" for="mail-probe-password">Kata sandi Project Pintar</label><input class="form-control mb-3" id="mail-probe-password" name="current_password" type="password" autocomplete="current-password" required>
<button class="btn btn-outline-primary"<?php echo ! $record['configured'] || ! $selected ? ' disabled' : ''; ?>>Kirim pesan uji ke pengirim</button><?php echo form_close(); ?>
<p class="small mt-3">Batas bersama kedua penyedia: 60 detik antar permintaan, 5/jam per akun, 30/jam per jaringan, 80/jam dan 400/24 jam aplikasi. Penyedia dapat menerapkan batas tambahan. Tidak ada pengiriman ulang otomatis setelah timeout.</p>
<a href="<?php echo site_url('akun/email'); ?>">Verifikasi email akun saya</a>
</div>
</section>
