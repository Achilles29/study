<!doctype html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#f7f8ff">
	<link rel="icon" type="image/png" sizes="256x256" href="<?php echo base_url('assets/images/branding/ico.png'); ?>">
	<link rel="apple-touch-icon" href="<?php echo base_url('assets/images/branding/ico.png'); ?>">
	<?php if($light_public_assets): ?><script>document.documentElement.classList.add('js-public');</script><style>@media(max-width:1199.98px){.js-public .site-header .navbar-collapse:not(.show){display:none}.js-public .site-header .navbar-toggler[hidden]{display:block!important}}</style><?php endif; ?>
	<title><?php echo html_escape($page_title); ?> · <?php echo html_escape($site_settings['brand_name']); ?></title>
	<meta name="robots" content="<?php echo $seo['index'] ? 'index, follow' : 'noindex, nofollow'; ?>">
	<?php if ($seo['index']): ?>
	<?php if($navigation_view==='home/index' && preg_match('/\A[A-Za-z0-9_-]{20,160}\z/',$site_settings['google_site_verification']??'')): ?><meta name="google-site-verification" content="<?php echo html_escape($site_settings['google_site_verification']); ?>"><?php endif; ?>
	<meta name="description" content="<?php echo html_escape($seo['description']); ?>">
	<link rel="canonical" href="<?php echo html_escape($seo['canonical']); ?>">
	<meta property="og:site_name" content="<?php echo html_escape($site_settings['brand_name']); ?>">
	<meta property="og:title" content="<?php echo html_escape($page_title); ?> · <?php echo html_escape($site_settings['brand_name']); ?>">
	<meta property="og:description" content="<?php echo html_escape($seo['description']); ?>">
	<meta property="og:url" content="<?php echo html_escape($seo['canonical']); ?>">
	<meta property="og:type" content="website">
	<meta property="og:image" content="<?php echo base_url('assets/images/branding/project-pintar-pijar-v2.png'); ?>">
	<meta property="og:image:width" content="1254">
	<meta property="og:image:height" content="1254">
	<meta property="og:image:alt" content="Pijar, maskot Project Pintar">
	<?php if ($navigation_view === 'home/index'): ?><script type="application/ld+json"><?php echo json_encode(array('@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $site_settings['brand_name'], 'url' => site_url('/')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?></script><?php endif; ?>
	<?php if(!empty($breadcrumbs) && count($breadcrumbs)>1): $breadcrumb_items=array(); foreach($breadcrumbs as $i=>$crumb) { $breadcrumb_items[]=array('@type'=>'ListItem','position'=>$i+1,'name'=>$crumb['label'],'item'=>site_url($crumb['path'])); } ?>
	<script type="application/ld+json"><?php echo json_encode(array('@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>$breadcrumb_items),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES); ?></script>
	<?php endif; ?>
	<?php endif; ?>
	<?php $app_css_path = FCPATH.'assets/css/app.css'; $app_css_version = is_file($app_css_path) ? (string) filemtime($app_css_path) : '1'; ?>
	<link rel="stylesheet" href="<?php echo base_url('assets/vendor/bootstrap/css/bootstrap.min.css'); ?>">
	<?php if ($admin_theme): ?><link rel="stylesheet" href="<?php echo base_url('assets/vendor/coreui/css/coreui.min.css'); ?>"><?php endif; ?>
	<link rel="stylesheet" href="<?php echo base_url('assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css'); ?>">
	<link rel="stylesheet" href="<?php echo base_url('assets/css/app.css?v='.rawurlencode($app_css_version)); ?>">
	<?php if ($admin_theme || ! empty($load_report_styles)): ?><link rel="stylesheet" href="<?php echo base_url('assets/css/admin.css?v='.filemtime(FCPATH.'assets/css/admin.css')); ?>"><?php endif; ?>
	<link rel="stylesheet" href="<?php echo base_url('assets/css/experience.css?v='.filemtime(FCPATH.'assets/css/experience.css')); ?>">
</head>
<body class="<?php echo html_escape($theme_class); ?><?php echo $admin_theme ? ' admin-shell' : ''; ?>">
<a class="skip-link" href="#konten-utama">Lewati ke isi utama</a>
<?php if ($admin_theme): $this->load->view('layouts/admin_navigation'); else: ?>
<header class="site-header<?php echo $current_user ? ' site-header-member' : ''; ?>">
	<nav class="navbar navbar-expand-xl container py-3" aria-label="Navigasi utama">
		<a class="navbar-brand brand-lockup" href="<?php echo site_url('/'); ?>"<?php echo study_navigation_attributes($navigation_view, 'home', ! empty($is_content_preview)); ?>>
			<img class="brand-mark" src="<?php echo base_url('assets/images/branding/ico.png'); ?>" width="48" height="48" alt="">
			<span><?php echo html_escape($site_settings['brand_name']); ?></span>
		</a>
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navigasi-utama" aria-controls="navigasi-utama" aria-expanded="false" aria-label="Buka navigasi" hidden>
			<span class="navbar-toggler-icon"></span>
		</button>
		<?php if ($current_user): ?>
		<a class="btn btn-outline-primary notification-nav" href="<?php echo site_url('notifikasi'); ?>" aria-label="Kotak notifikasi<?php echo $notification_unread===NULL?', jumlah belum tersedia':', '.(int)$notification_unread.' belum dibaca'; ?>"><i class="bi bi-envelope" aria-hidden="true"></i><?php if($notification_unread): ?><span class="badge text-bg-danger ms-1"><?php echo min(99,(int)$notification_unread); ?><?php echo $notification_unread>99?'+':''; ?></span><?php endif; ?></a>
		<?php endif; ?>
		<?php if ($current_user && $current_user['account_kind'] !== 'child'): ?>
		<a class="member-donation-badge" href="<?php echo site_url('donasi'); ?>"<?php echo strpos($navigation_view, 'donations/') === 0 ? ' aria-current="page"' : ''; ?>><i class="bi bi-heart-fill" aria-hidden="true"></i><span>Donasi</span><span class="donation-caption">Seikhlasnya</span></a>
		<?php endif; ?>
		<div class="navbar-collapse" id="navigasi-utama">
			<div class="navbar-nav mx-auto gap-xl-2">
				<?php if(!$current_user): ?><a class="nav-link" href="<?php echo site_url('latihan-soal'); ?>"<?php echo $navigation_view==='public_learning/index'?' aria-current="page"':''; ?>>Latihan soal SD</a><?php endif; ?>
				<a class="nav-link" href="<?php echo site_url('kursus'); ?>"<?php echo study_navigation_attributes($navigation_view, 'catalog', ! empty($is_content_preview)); ?>>Jelajahi kursus</a>
				<a class="nav-link" href="<?php echo site_url('ulasan'); ?>"<?php echo strpos($navigation_view, 'reviews/') === 0 && empty($is_content_preview) ? ' aria-current="page"' : ''; ?>>Ulasan</a>
				<?php if ( ! $current_user): ?><a class="nav-link" href="<?php echo site_url('/'); ?>#cara-belajar">Cara belajar</a>
				<a class="nav-link" href="<?php echo site_url('/'); ?>#untuk-keluarga">Untuk keluarga</a><?php else: ?><a class="nav-link" href="<?php echo site_url('beranda'); ?>"<?php echo study_navigation_attributes($navigation_view, 'dashboard', ! empty($is_content_preview)); ?>>Beranda</a><?php endif; ?>
				<?php if ($current_user): ?><a class="nav-link" href="<?php echo site_url('belajar/arena'); ?>"<?php echo strpos($navigation_view, 'arena/') === 0 ? ' aria-current="page"' : ''; ?>>Arena Belajar</a><?php endif; ?>
				<?php if ($current_user): ?><a class="nav-link" href="<?php echo site_url('belajar/kelas-inggris'); ?>"<?php echo strpos($navigation_view, 'english_course/') === 0 ? ' aria-current="page"' : ''; ?>>English Hack</a><?php endif; ?>
				<?php if ($current_user && $current_user['account_kind'] === 'parent'): ?>
					<a class="nav-link" href="<?php echo site_url('keluarga/anak'); ?>"<?php echo study_navigation_attributes($navigation_view, 'family', ! empty($is_content_preview)); ?>>Akun anak</a>
				<?php endif; ?>
				<?php if ($current_user && ! empty(array_intersect(array('system_admin','content_editor','finance_operator','support_agent'), $current_user['roles']))): ?>
					<a class="nav-link" href="<?php echo site_url('kelola'); ?>"<?php echo study_navigation_attributes($navigation_view, 'admin', ! empty($is_content_preview)); ?>>Panel pengelola</a>
				<?php endif; ?>
			</div>
			<div class="d-flex align-items-center gap-2 mt-3 mt-xl-0">
				<?php if ($current_user): ?>
					<a class="nav-link user-greeting" href="<?php echo site_url('akun/pengaturan'); ?>"<?php echo $navigation_view === 'auth/account_settings' ? ' aria-current="page"' : ''; ?>><i class="bi bi-person-circle me-1" aria-hidden="true"></i>Akun saya</a>
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
<?php endif; ?>

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
