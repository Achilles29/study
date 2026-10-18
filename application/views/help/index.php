<section class="container page-section">
<div class="page-heading mb-4"><h1>Bantuan Project Pintar</h1><p class="text-secondary">Hubungi pengelola untuk kendala akun, pembelajaran, pembayaran, atau permintaan privasi.</p></div>
<div class="alert alert-info">Balasan dibaca di situs ini, bukan melalui email. Jangan kirim sandi, PIN, kode Google, dokumen identitas, atau data pribadi anak. Permintaan terkait akun/data diperiksa kewenangannya sebelum ditindaklanjuti.</div>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert" id="help-error"><?php echo html_escape($error); ?> <a href="<?php echo site_url('bantuan'); ?>">Muat ulang</a></div><?php endif; ?>
<div class="row g-4">
<section class="col-lg-7"><div class="card card-body"><h2 class="h4">Kirim pesan baru</h2>
<?php echo form_open('bantuan'); ?>
<input type="hidden" name="request_key" value="<?php echo html_escape($request_key); ?>">
<div class="d-none" aria-hidden="true"><label>Situs web<input name="website" tabindex="-1" autocomplete="off"></label></div>
<div class="mb-3"><label for="category" class="form-label">Topik</label><select id="category" name="category" class="form-select"<?php echo study_field_error_attributes($errors, 'category'); ?>><?php foreach ($categories as $key => $label): ?><option value="<?php echo $key; ?>" <?php echo ($values['category'] ?? '') === $key ? 'selected' : ''; ?>><?php echo html_escape($label); ?></option><?php endforeach; ?></select><?php echo study_render_field_error($errors, 'category'); ?></div>
<div class="mb-3"><label for="subject" class="form-label">Judul singkat</label><input id="subject" name="subject" class="form-control" minlength="5" maxlength="120" required value="<?php echo html_escape($values['subject'] ?? ''); ?>"<?php echo study_field_error_attributes($errors, 'subject'); ?>><?php echo study_render_field_error($errors, 'subject'); ?></div>
<div class="mb-3"><label for="body" class="form-label">Pesan (20–3.000 karakter)</label><textarea id="body" name="body" class="form-control" rows="7" minlength="20" maxlength="3000" required<?php echo study_field_error_attributes($errors, 'body'); ?>><?php echo html_escape($values['body'] ?? ''); ?></textarea><?php echo study_render_field_error($errors, 'body'); ?></div>
<div class="form-check mb-3"><input id="consent" name="consent" value="1" type="checkbox" class="form-check-input" required<?php echo study_field_error_attributes($errors, 'consent'); ?>><label for="consent" class="form-check-label">Saya orang dewasa/orang tua/wali yang berwenang. Saya memahami pesan disimpan dan dibaca pengelola untuk menangani permintaan ini, sesuai <a href="<?php echo site_url('privasi'); ?>">kebijakan privasi</a>.</label><?php echo study_render_field_error($errors, 'consent'); ?></div>
<?php if ( ! $current_user): ?><p class="small text-secondary">Setelah mengirim, simpan kode rahasia yang diberikan. Tanpa kode tersebut, balasan tidak dapat dibuka dari sesi/perangkat lain. Jika sudah punya akun, <a href="<?php echo site_url('masuk'); ?>">masuk terlebih dahulu</a> agar pesan tersimpan di akun.</p><?php endif; ?>
<button class="btn btn-primary" type="submit">Kirim pesan</button>
<?php echo form_close(); ?></div></section>
<section class="col-lg-5"><div class="card card-body"><h2 class="h4">Buka balasan dengan kode</h2><p>Untuk pesan yang dikirim tanpa masuk akun. Kode bersifat rahasia; jangan bagikan atau kirim kepada pengelola.</p>
<?php echo form_open('bantuan/buka'); ?><label class="form-label" for="code">Kode akses percakapan</label><input class="form-control mb-3" type="password" id="code" name="code" maxlength="83" autocomplete="off" required><button class="btn btn-outline-primary" type="submit">Buka percakapan</button><?php echo form_close(); ?></div></section>
</div>
<?php if ($current_user): ?><section class="mt-4"><h2 class="h4">Pesan saya</h2><?php $this->load->view('help/list', array('list_path' => 'bantuan', 'status' => '')); ?></section><?php endif; ?>
</section>
