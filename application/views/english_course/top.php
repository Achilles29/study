<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<link rel="stylesheet" href="<?php echo base_url('assets/css/english-hack.css?v='.filemtime(FCPATH.'assets/css/english-hack.css')); ?>">
<link rel="stylesheet" href="<?php echo base_url('assets/css/english-course.css?v='.filemtime(FCPATH.'assets/css/english-course.css')); ?>">
<div class="container page-section"><div class="eh ec">
<?php if(!empty($admin_theme)): $this->load->view('admin/english_hack/nav'); else: ?><nav class="ec-nav" aria-label="Navigasi English Hack"><a href="<?php echo site_url('belajar/kelas-inggris'); ?>"><strong>English <em>Hack</em></strong></a><a href="<?php echo site_url('belajar/kelas-inggris/riwayat'); ?>">Progres & karya</a><a href="<?php echo site_url('belajar/arena'); ?>">Arena Belajar</a><?php if(in_array('system_admin',$current_user['roles'],TRUE)): ?><a href="<?php echo site_url('kelola/kelas-inggris'); ?>">Kelola kelas</a><?php endif; ?></nav><?php endif; ?>
<?php if(!empty($error)): ?><div class="ec-error" role="alert"><?php echo html_escape($error); ?></div><?php endif; ?>
