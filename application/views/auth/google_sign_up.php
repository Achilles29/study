<section class="auth-shell container py-5"><div class="auth-layout">
<?php $this->load->view('auth/learning_panel'); ?>
<div class="auth-card">
<span class="feature-icon feature-purple mb-3"><i class="bi bi-person-plus" aria-hidden="true"></i></span>
<p class="eyebrow">Mulai perjalananmu</p><h1 class="h2">Belajar jadi lebih seru.</h1>
<p class="text-secondary">Buat akun Project Pintar dengan Google. Pilih belajar mandiri atau dampingi anak sebagai orang tua.</p>
<ol class="signup-steps"><li><span>1</span>Verifikasi dengan Google</li><li><span>2</span>Lengkapi akunmu</li><li><span>3</span>Mulai menjelajah</li></ol>
<?php $this->load->view('auth/google_button', array('google_purpose' => 'register')); ?>
<p class="small text-secondary mt-3"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>Gunakan Gmail atau Google Workspace. Kami tidak meminta sandi Google.</p>
<p class="small text-secondary">Akun anak dibuat dari akun orang tua setelah masuk.</p>
<details class="auth-help mb-4"><summary>Sudah punya akun dengan email yang sama?</summary><p class="small mt-2 mb-0">Masuk memakai sandi lama, lalu tautkan Google di Pengaturan akun. Akun tidak digabung otomatis.</p></details>
<p class="small"><a href="<?php echo site_url('privasi'); ?>">Privasi &amp; syarat layanan</a></p>
<p class="mb-0">Sudah punya akun? <a class="fw-semibold" href="<?php echo site_url('masuk'); ?>">Masuk di sini</a></p>
</div></div></section>
