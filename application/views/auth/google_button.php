<?php echo form_open('auth/google/mulai'); ?>
<input type="hidden" name="purpose" value="<?php echo $google_purpose === 'register' ? 'register' : 'login'; ?>">
<button class="google-signin-button w-100" type="submit"<?php echo empty($google_enabled) ? ' disabled aria-describedby="google-unavailable"' : ''; ?>><img src="<?php echo base_url('assets/images/google-g.png'); ?>" width="20" height="20" alt="" aria-hidden="true"><span><?php echo $google_purpose === 'register' ? 'Daftar dengan Google' : 'Masuk dengan Google'; ?></span></button>
<?php echo form_close(); ?>
<?php if (empty($google_enabled)): ?><p id="google-unavailable" class="small text-secondary mt-2">Google belum diaktifkan oleh pengelola. Silakan coba lagi nanti<?php echo $google_purpose === 'login' ? ' atau gunakan sandi akun Anda' : ''; ?>.</p><?php endif; ?>
