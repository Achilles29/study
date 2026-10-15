<?php
if (PHP_SAPI !== 'cli')
{
	exit(1);
}
define('BASEPATH', dirname(__DIR__).'/system/');
define('FCPATH', dirname(__DIR__).'/');
function html_escape($value)
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
function base_url($path)
{
	return 'http://localhost/study/'.$path;
}
function site_url($path)
{
	return 'http://localhost/study/index.php/'.ltrim($path, '/');
}
function form_open($path, $attributes)
{
	return '<form method="post" action="'.html_escape(site_url($path)).'">';
}
function form_close()
{
	return '</form>';
}
require FCPATH.'application/helpers/study_ui_helper.php';
function navigation_assert($condition, $label)
{
	if ( ! $condition)
	{
		throw new RuntimeException($label);
	}
}
function navigation_header($view, $user = NULL, $preview = FALSE)
{
	$navigation_view = $view;
	$current_user = $user;
	$is_content_preview = $preview;
	$admin_theme = strpos($view, 'admin/') === 0;
	$theme_class = 'theme-public';
	$page_title = 'Synthetic navigation test';
	$flash_success = $flash_notice = $flash_error = NULL;
	ob_start();
	require FCPATH.'application/views/layouts/header.php';
	return ob_get_clean();
}
$parent = array('account_kind' => 'parent', 'display_name' => '<script>QA</script>', 'roles' => array());
$admin = array('account_kind' => 'adult', 'display_name' => 'QA admin', 'roles' => array('system_admin'));
$child = array('account_kind' => 'child', 'display_name' => 'QA child', 'roles' => array());
$cases = array(
	array('home/index', NULL, FALSE, '', 'page'),
	array('catalog/index', NULL, FALSE, 'kursus', 'page'),
	array('catalog/course', NULL, FALSE, 'kursus', 'location'),
	array('catalog/lesson', $child, FALSE, 'kursus', 'location'),
	array('auth/sign_in', NULL, FALSE, 'masuk', 'page'),
	array('auth/sign_up', NULL, FALSE, 'daftar', 'page'),
	array('dashboard/index', $parent, FALSE, 'beranda', 'page'),
	array('family/children', $parent, FALSE, 'keluarga/anak', 'page'),
	array('family/add_child', $parent, FALSE, 'keluarga/anak', 'location'),
	array('admin/index', $admin, FALSE, 'kelola', 'page'),
	array('admin/courses', $admin, FALSE, 'kelola', 'location'),
	array('catalog/lesson', $admin, TRUE, 'kelola', 'location')
);
foreach ($cases as $case)
{
	$html = navigation_header($case[0], $case[1], $case[2]);
	navigation_assert(strpos($html, 'class="navbar-collapse" id="navigasi-utama"') !== FALSE, 'Server menu must not be collapsed before enhancement');
	navigation_assert(preg_match('/<button class="navbar-toggler"[^>]* hidden>/', $html) === 1, 'Server menu toggle must be hidden before enhancement');
	preg_match_all('/<a\b[^>]*aria-current="(page|location)"[^>]*>/', $html, $matches);
	navigation_assert(count($matches[0]) === 1, 'Expected exactly one current navigation link: '.$case[0]);
	navigation_assert(strpos($matches[0][0], 'href="'.site_url($case[3]).'"') !== FALSE && $matches[1][0] === $case[4], 'Wrong current destination or semantics: '.$case[0]);
}
foreach (array(NULL, $child, $parent, $admin) as $user)
{
	$html = navigation_header('dashboard/index', $user);
	navigation_assert((strpos($html, '>Akun anak</a>') !== FALSE) === ($user === $parent), 'Family menu visibility changed');
	navigation_assert((strpos($html, '>Panel pengelola</a>') !== FALSE) === ($user === $admin), 'Admin menu visibility changed');
	navigation_assert(strpos($html, '<script>QA</script>') === FALSE, 'Display name was not escaped');
}
foreach (array('practice/attempt', 'games/run', 'auth/security', 'unknown/view') as $view)
{
	navigation_assert(strpos(navigation_header($view, $child), 'aria-current=') === FALSE, 'Unrelated menu item must not claim this page');
}
foreach (array(NULL, array('catalog/index'), 'catalogue/index', 'x/catalog/index', 'catalog/index?admin/index') as $view)
{
	navigation_assert(study_navigation_attributes($view, 'admin') === '', 'Unexpected input selected admin');
}
navigation_assert(study_navigation_attributes('catalog/index', '<script>') === '', 'Unknown item must not render input');
navigation_assert(study_navigation_attributes('catalog/index', array()) === '', 'Array item must be ignored');
echo "PASS navigation: 12 rendered destinations, page/location distinction, preview context, role-based menu visibility, escaping and unrelated/invalid inputs. No database or JavaScript required.\n";
