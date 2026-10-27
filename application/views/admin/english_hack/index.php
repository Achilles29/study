<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<link rel="stylesheet" href="<?php echo base_url('assets/css/english-hack.css?v='.filemtime(FCPATH.'assets/css/english-hack.css')); ?>">
<link rel="stylesheet" href="<?php echo base_url('assets/css/english-course.css?v='.filemtime(FCPATH.'assets/css/english-course.css')); ?>">
<div class="eh ec ec-admin-toolbar"><?php $this->load->view('admin/english_hack/nav'); ?></div>
<div class="eh-download-bar"><a href="<?php echo site_url('belajar/kelas-inggris'); ?>">Buka kelas pengguna</a> · <strong>Studio editorial · naskah kurikulum.</strong> <a href="<?php echo html_escape($curriculum['downloads']['pdf']); ?>">Unduh buku lengkap PDF</a> · <a href="<?php echo html_escape($curriculum['downloads']['book']); ?>">Versi HTML</a></div>
<?php readfile(APPPATH.'views/admin/english_hack/fragment.html'); ?>
<script type="application/json" id="eh-data"><?php echo json_encode($curriculum, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
<script src="<?php echo base_url('assets/js/english-hack.js?v='.filemtime(FCPATH.'assets/js/english-hack.js')); ?>" defer></script>
