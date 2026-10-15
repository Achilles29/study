<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');
define('FCPATH', dirname(__DIR__).'/');
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
function html_escape($value)
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function site_url($path)
{
	return 'http://localhost/study/index.php/'.$path;
}
require FCPATH.'application/helpers/study_ui_helper.php';
function catalog_view_assert($condition, $label)
{
	if ( ! $condition) { throw new RuntimeException($label); }
}
class Catalog_view_loader
{
	public $load;
	public function __construct()
	{
		$this->load = $this;
	}
	public function view($view, $data)
	{
		if ( ! in_array($view, array('catalog/index', 'practice/pagination'), TRUE)) { throw new RuntimeException('Unexpected view'); }
		extract($data, EXTR_SKIP);
		require FCPATH.'application/views/'.$view.'.php';
	}
}
function catalog_view_render($data)
{
	$loader = new Catalog_view_loader();
	ob_start();
	$loader->view('catalog/index', $data);
	return ob_get_clean();
}
function catalog_view_safe($html)
{
	catalog_view_assert(strpos($html, '<script') === FALSE && strpos($html, '<img') === FALSE, 'Catalog data must not create executable or image markup');
	preg_match_all('/\bid="([^"]+)"/', $html, $ids);
	catalog_view_assert(count($ids[1]) === count(array_unique($ids[1])), 'Catalog HTML IDs must be unique');
	preg_match_all('/<label\b[^>]*\bfor="([^"]+)"/', $html, $labels);
	foreach ($labels[1] as $label) { catalog_view_assert(in_array($label, $ids[1], TRUE), 'Each filter label must target an existing control'); }
	catalog_view_assert(count($labels[1]) === 2, 'Search and category need visible labels');
	catalog_view_assert(strpos($html, 'tabindex="-1"') === FALSE, 'Native filter controls must remain in keyboard order');
}

$base = array(
	'courses' => array(),
	'categories' => array(array('id' => 7, 'name' => 'Bahasa <script>aman</script>')),
	'filters' => array('q' => '', 'category_id' => 0),
	'total_rows' => 0,
	'has_filters' => FALSE,
	'target_group_labels' => array('primary' => 'Sekolah dasar', 'adult' => 'Dewasa <script>aman</script>'),
	'page' => 1,
	'page_count' => 1,
	'pagination_query' => array()
);
$html = catalog_view_render($base);
catalog_view_safe($html);
catalog_view_assert(strpos($html, 'Kursus sedang disiapkan') !== FALSE && strpos($html, 'Belum ada kursus yang cocok') === FALSE, 'Initial empty catalog must not blame filters');
catalog_view_assert(strpos($html, '0 kursus ditemukan') !== FALSE && strpos($html, 'Hapus filter') === FALSE, 'Initial empty count must not offer nonexistent filter removal');
catalog_view_assert(strpos($html, 'href="'.site_url('kursus').'">Atur ulang</a>') !== FALSE, 'Reset must be a fixed GET link to catalog');
catalog_view_assert(strpos($html, 'method="get" action="'.site_url('kursus').'"') !== FALSE, 'Search must use native GET to catalog');
catalog_view_assert(strpos($html, 'name="halaman"') === FALSE && strpos($html, 'name="q" id="q" maxlength="100"') !== FALSE, 'Filter submission must reset pagination and retain query length limit');
catalog_view_assert(strpos($html, 'type="submit">Cari</button>') !== FALSE, 'Search must work with native submit without JavaScript');

$query = 'Belajar <script>aman</script> "kutip" & 日本語';
foreach (array(array('q' => $query, 'category_id' => 0), array('q' => '', 'category_id' => 7), array('q' => $query, 'category_id' => 7)) as $filters)
{
	$html = catalog_view_render(array_replace($base, array('filters' => $filters, 'has_filters' => TRUE)));
	catalog_view_safe($html);
	catalog_view_assert(strpos($html, 'Belum ada kursus yang cocok') !== FALSE && strpos($html, 'Kursus sedang disiapkan') === FALSE, 'Filtered empty catalog must describe no matching results');
	catalog_view_assert(strpos($html, '0 kursus ditemukan sesuai filter aktif.') !== FALSE, 'Filtered count must describe the active result scope');
	catalog_view_assert(strpos($html, 'href="'.site_url('kursus').'">Hapus filter</a>') !== FALSE, 'Filtered empty result must offer fixed filter reset');
	catalog_view_assert(strpos($html, 'value="'.html_escape($filters['q']).'"') !== FALSE, 'Safe Unicode and query text must remain in the search field');
	if ($filters['category_id'] === 7)
	{
		catalog_view_assert(strpos($html, '<option value="7" selected>Bahasa &lt;script&gt;aman&lt;/script&gt;</option>') !== FALSE, 'Category selection and escaped category name must remain visible');
	}
}

$courses = array();
for ($index = 1; $index <= 12; $index++)
{
	$courses[] = array(
		'category_name' => $index === 2 ? NULL : 'Kategori <script>aman</script>',
		'is_free' => $index % 2,
		'title' => 'Judul '.$index.' <script>aman</script>',
		'summary' => 'Ringkasan '.$index.' <script>aman</script> "kutip" & 日本語',
		'target_group' => $index === 2 ? 'unknown' : ($index === 3 ? 'adult' : 'primary'),
		'age_min' => $index === 2 ? NULL : 6,
		'age_max' => $index === 2 ? NULL : 9,
		'estimated_minutes' => $index === 2 ? 0 : 25,
		'slug' => 'kursus-'.$index
	);
}
$filtered_result = array_replace($base, array('courses' => $courses, 'filters' => array('q' => $query, 'category_id' => 7), 'total_rows' => 13, 'has_filters' => TRUE, 'page_count' => 2, 'pagination_query' => array('q' => $query, 'kategori' => 7)));
$html = catalog_view_render($filtered_result);
catalog_view_safe($html);
catalog_view_assert(substr_count($html, '<article ') === 12 && substr_count($html, 'class="col-md-6 col-xl-4"') === 12, 'View must render the twelve supplied cards in the responsive grid');
catalog_view_assert(strpos($html, '13 kursus ditemukan sesuai filter aktif.') !== FALSE, 'Count must use total matching rows rather than page size');
catalog_view_assert(strpos($html, html_escape($courses[0]['summary'])) !== FALSE && strpos($html, html_escape($courses[0]['title'])) !== FALSE, 'Summary and title markup must remain escaped text');
catalog_view_assert(strpos($html, 'Dewasa &lt;script&gt;aman&lt;/script&gt;') !== FALSE && strpos($html, 'Kelompok belum ditentukan') !== FALSE, 'Group metadata must be escaped and have an unknown-group fallback');
catalog_view_assert(strpos($html, '>Kursus</span>') !== FALSE && strpos($html, 'Usia 6–9 tahun') !== FALSE && strpos($html, '25 menit') !== FALSE, 'Category fallback, age range and duration must remain visible');
catalog_view_assert(substr_count($html, 'text-bg-success">Gratis</span>') === 6, 'Only free cards must receive the Gratis badge');
catalog_view_assert(strpos($html, '<span class="visually-hidden">'.html_escape($courses[0]['title']).'</span>') !== FALSE, 'Repeated detail actions must include course-specific accessible text');
$next_url = html_escape(site_url('kursus?'.http_build_query(array('halaman' => 2, 'q' => $query, 'kategori' => 7), '', '&', PHP_QUERY_RFC3986)));
catalog_view_assert(strpos($html, 'aria-current="page">Halaman 1 dari 2</span>') !== FALSE && strpos($html, 'href="'.$next_url.'">Berikutnya</a>') !== FALSE, 'Shared pagination must use supplied page and encoded filter context');
catalog_view_assert(strpos($html, '>Sebelumnya</a>') === FALSE, 'First page must not offer an out-of-range previous link');
catalog_view_assert(strpos($html, 'Belum ada kursus yang cocok') === FALSE && strpos($html, 'Kursus sedang disiapkan') === FALSE, 'Populated result must not show an empty state');
catalog_view_assert(strpos($html, 'text-break flex-grow-1 mb-3') !== FALSE && strpos($html, 'd-flex flex-wrap') !== FALSE, 'Existing wrap utilities must support long text and mobile controls');

$html = catalog_view_render(array_replace($filtered_result, array('courses' => array($courses[0]), 'page' => 2)));
catalog_view_safe($html);
$previous_url = html_escape(site_url('kursus?'.http_build_query(array('halaman' => 1, 'q' => $query, 'kategori' => 7), '', '&', PHP_QUERY_RFC3986)));
catalog_view_assert(strpos($html, 'aria-current="page">Halaman 2 dari 2</span>') !== FALSE && strpos($html, 'href="'.$previous_url.'">Sebelumnya</a>') !== FALSE, 'Last page must display its supplied page and preserve filters in previous link');
catalog_view_assert(strpos($html, '>Berikutnya</a>') === FALSE, 'Last page must not offer an out-of-range next link');

$html = catalog_view_render(array_replace($base, array('courses' => array($courses[0]), 'total_rows' => 1)));
catalog_view_safe($html);
catalog_view_assert(strpos($html, '1 kursus ditemukan.') !== FALSE && strpos($html, 'sesuai filter aktif') === FALSE, 'Unfiltered populated count must not claim active filters');
catalog_view_assert(strpos($html, 'aria-current="page"') === FALSE, 'A single page must not show unnecessary page navigation');
restore_error_handler();
fwrite(STDOUT, "PASS catalog view empty states, filtered counts, native reset/search, labels, escaped cards and server pagination. This is not HTTP, database or visual QA.\n");
