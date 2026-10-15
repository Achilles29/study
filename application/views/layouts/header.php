<!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#f7f8ff">
	<title><?php echo html_escape($page_title); ?> · Ruang Belajar</title>
	<?php $app_css_path = FCPATH.'assets/css/app.css'; $app_css_version = is_file($app_css_path) ? (string) filemtime($app_css_path) : '1'; ?>
	<link rel="stylesheet" href="<?php echo base_url('assets/vendor/bootstrap/css/bootstrap.min.css'); ?>">
	<?php if ($admin_theme): ?><link rel="stylesheet" href="<?php echo base_url('assets/vendor/coreui/css/coreui.min.css'); ?>"><?php endif; ?>
	<link rel="stylesheet" href="<?php echo base_url('assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css'); ?>">
	<link rel="stylesheet" href="<?php echo base_url('assets/css/app.css?v='.rawurlencode($app_css_version)); ?>">
</head>
<body class="<?php echo html_escape($theme_class); ?>">
<a class="skip-link" href="#konten-utama">Lewati ke isi utama</a>
<header class="site-header">
	<nav class="navbar navbar-expand-lg container py-3" aria-label="Navigasi utama">
		<a class="navbar-brand brand-lockup" href="<?php echo site_url('/'); ?>"<?php echo study_navigation_attributes($navigation_view, 'home', ! empty($is_content_preview)); ?>>
			<span class="brand-mark" aria-hidden="true"><i class="bi bi-stars"></i></span>
			<span>Ruang<span class="brand-accent">Belajar</span></span>
		</a>
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navigasi-utama" aria-controls="navigasi-utama" aria-expanded="false" aria-label="Buka navigasi" hidden>
			<span class="navbar-toggler-icon"></span>
		</button>
		<div class="navbar-collapse" id="navigasi-utama">
			<div class="navbar-nav mx-auto gap-lg-2">
				<a class="nav-link" href="<?php echo site_url('kursus'); ?>"<?php echo study_navigation_attributes($navigation_view, 'catalog', ! empty($is_content_preview)); ?>>Jelajahi kursus</a>
				<a class="nav-link" href="<?php echo site_url('/'); ?>#cara-belajar">Cara belajar</a>
				<a class="nav-link" href="<?php echo site_url('/'); ?>#untuk-keluarga">Untuk keluarga</a>
				<?php if ($current_user && $current_user['account_kind'] === 'parent'): ?>
					<a class="nav-link" href="<?php echo site_url('keluarga/anak'); ?>"<?php echo study_navigation_attributes($navigation_view, 'family', ! empty($is_content_preview)); ?>>Akun anak</a>
				<?php endif; ?>
				<?php if ($current_user && ! empty(array_intersect(array('system_admin','content_editor','finance_operator','support_agent'), $current_user['roles']))): ?>
					<a class="nav-link" href="<?php echo site_url('kelola'); ?>"<?php echo study_navigation_attributes($navigation_view, 'admin', ! empty($is_content_preview)); ?>>Panel pengelola</a>
				<?php endif; ?>
			</div>
			<div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
				<?php if ($current_user): ?>
					<a class="nav-link user-greeting" href="<?php echo site_url('beranda'); ?>"<?php echo study_navigation_attributes($navigation_view, 'dashboard', ! empty($is_content_preview)); ?>>Hai, <?php echo html_escape($current_user['display_name']); ?></a>
					<?php echo form_open('keluar', array('class' => 'd-inline')); ?>
						<button class="btn btn-outline-primary btn-sm rounded-pill px-3" type="submit">Keluar</button>
					<?php echo form_close(); ?>
				<?php else: ?>
					<a class="btn btn-link text-decoration-none" href="<?php echo site_url('masuk'); ?>"<?php echo study_navigation_attributes($navigation_view, 'sign_in', ! empty($is_content_preview)); ?>>Masuk</a>
					<a class="btn btn-primary rounded-pill px-4" href="<?php echo site_url('daftar'); ?>"<?php echo study_navigation_attributes($navigation_view, 'sign_up', ! empty($is_content_preview)); ?>>Mulai belajar</a>
				<?php endif; ?>
			</div>
		</div>
	</nav>
</header>

<main id="konten-utama">
	<div class="container pt-3" aria-live="polite">
		<?php foreach (array('success' => $flash_success, 'info' => $flash_notice, 'danger' => $flash_error) as $type => $message): ?>
			<?php if ($message): ?>
				<div class="alert alert-<?php echo html_escape($type); ?> alert-dismissible fade show" role="status">
					<?php echo html_escape($message); ?>
					<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup pesan"></button>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
