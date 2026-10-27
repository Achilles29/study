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
function study_grade_select($value)
{
	return '<label class="form-label" for="kelas">Kelas</label><select id="kelas" name="kelas"><option value="">Semua kelas</option></select>';
}
function study_grade_label($id)
{
	return 'SD Kelas 2';
}
require FCPATH.'application/helpers/study_ui_helper.php';
function admin_courses_view_assert($condition, $label)
{
	if ( ! $condition) { throw new RuntimeException($label); }
}
class Admin_courses_view_loader
{
	public $load;
	public function __construct()
	{
		$this->load = $this;
	}
	public function view($view, $data)
	{
		if ( ! in_array($view, array('admin/courses', 'practice/pagination'), TRUE)) { throw new RuntimeException('Unexpected view'); }
		extract($data, EXTR_SKIP);
		require FCPATH.'application/views/'.$view.'.php';
	}
}
function admin_courses_view_render($data)
{
	if (isset($data['filters']) && !isset($data['filters']['kelas'])) { $data['filters']['kelas'] = ''; }
	$loader = new Admin_courses_view_loader();
	ob_start();
	try
	{
		$loader->view('admin/courses', $data);
		return ob_get_contents();
	}
	finally { ob_end_clean(); }
}
function admin_courses_view_safe($html)
{
	admin_courses_view_assert(strpos($html, '<script') === FALSE && strpos($html, '<img') === FALSE, 'Untrusted list fields must not create markup');
	preg_match_all('/\bid="([^"]+)"/', $html, $ids);
	admin_courses_view_assert(count($ids[1]) === count(array_unique($ids[1])), 'Desktop/mobile render must not duplicate HTML IDs');
	preg_match_all('/<label\b[^>]*\bfor="([^"]+)"/', $html, $labels);
	admin_courses_view_assert(count($labels[1]) === 3, 'Query, status and grade need visible labels');
	foreach ($labels[1] as $label) { admin_courses_view_assert(in_array($label, $ids[1], TRUE), 'Each filter label must target a control'); }
	admin_courses_view_assert(strpos($html, 'tabindex="-1"') === FALSE, 'Native controls must remain in keyboard order');
	admin_courses_view_assert(strpos($html, 'Â·') === FALSE, 'Mobile metadata must use a normal middle dot, not mojibake');
}
function admin_courses_view_pager_url($page, $query, $status)
{
	return html_escape(site_url('kelola/kursus?'.http_build_query(array('halaman' => $page, 'q' => $query, 'status' => $status), '', '&', PHP_QUERY_RFC3986)));
}

$base = array('courses' => array(), 'filters' => array('q' => '', 'status' => '', 'kelas' => ''), 'total_rows' => 0, 'page' => 1, 'page_count' => 1, 'pagination_query' => array());
$html = admin_courses_view_render($base);
admin_courses_view_safe($html);
admin_courses_view_assert(strpos($html, 'Belum ada kursus pada daftar ini') !== FALSE && strpos($html, 'Tidak ada kursus yang cocok') === FALSE, 'Initial empty list must invite creating the first course');
admin_courses_view_assert(strpos($html, '0 kursus ditemukan') !== FALSE && strpos($html, 'Hapus filter') === FALSE, 'Initial empty state must show zero without claiming active filters');
admin_courses_view_assert(strpos($html, 'href="'.site_url('kelola/kursus/tambah').'">Buat kursus</a>') !== FALSE, 'Initial empty state must have a fixed course creation link');
admin_courses_view_assert(strpos($html, 'method="get" action="'.site_url('kelola/kursus').'"') !== FALSE, 'Filtering must use native GET to the admin list');
admin_courses_view_assert(strpos($html, 'href="'.site_url('kelola/kursus').'">Atur ulang</a>') !== FALSE, 'Reset must target the fixed list URL without old query context');
admin_courses_view_assert(strpos($html, 'name="halaman"') === FALSE && strpos($html, 'maxlength="100"') !== FALSE, 'Filter form must reset page and retain the query length limit');
admin_courses_view_assert(strpos($html, 'type="submit">Terapkan</button>') !== FALSE, 'Filtering must retain a native submit action');
admin_courses_view_assert(strpos($html, 'aria-current="page"') === FALSE, 'An empty single page must not render pagination');

$query = 'Judul <script>aman</script> "kutip" & 日本語';
foreach (array(array('q' => $query, 'status' => ''), array('q' => '', 'status' => 'review'), array('q' => $query, 'status' => 'review')) as $filters)
{
	$html = admin_courses_view_render(array_replace($base, array('filters' => $filters)));
	admin_courses_view_safe($html);
	admin_courses_view_assert(strpos($html, 'Tidak ada kursus yang cocok') !== FALSE && strpos($html, 'Belum ada kursus pada daftar ini') === FALSE, 'Filtered empty list must distinguish missing matches from an initial empty catalog');
	admin_courses_view_assert(strpos($html, 'href="'.site_url('kelola/kursus').'">Hapus filter</a>') !== FALSE, 'Filtered empty state needs a fixed reset action');
	admin_courses_view_assert(strpos($html, 'value="'.html_escape($filters['q']).'"') !== FALSE, 'Escaped Unicode query must remain in its control');
	if ($filters['status'] === 'review') { admin_courses_view_assert(strpos($html, '<option value="review" selected>Menunggu review</option>') !== FALSE, 'Valid status selection must remain visible'); }
}

$courses = array();
$statuses = array('draft', 'review', 'published', 'archived');
for ($index = 1; $index <= 20; $index++)
{
	$courses[] = array(
		'id' => 100 + $index,
		'title' => 'Kursus '.$index.' <script>aman</script> "kutip" & 日本語',
		'category_name' => $index === 2 ? NULL : 'Kategori '.$index.' <script>aman</script>',
		'author_name' => $index === 3 ? NULL : 'Penulis '.$index.' <script>aman</script>',
		'status' => $index === 20 ? '<script>unknown-status</script>' : $statuses[($index - 1) % 4],
		'grade_id' => 2,
		'chapter_count' => 2,
		'lesson_count' => 2,
		'all_lesson_count' => 3,
		'target_group' => $index === 20 ? '<script>unknown-group</script>' : 'primary',
		'is_free' => $index % 2,
		'updated_at' => $index === 20 ? '<script>invalid-date</script>' : '2026-10-15 13:15:00'
	);
}
$filtered_result = array_replace($base, array('courses' => $courses, 'filters' => array('q' => $query, 'status' => 'review'), 'total_rows' => 21, 'page_count' => 2, 'pagination_query' => array('q' => $query, 'status' => 'review')));
$html = admin_courses_view_render($filtered_result);
admin_courses_view_safe($html);
preg_match_all('/<article\b[^>]*class="[^"]*\badmin-course-card\b[^"]*"[^>]*>(.*?)<\/article>/s', $html, $cards);
preg_match('/<tbody\b[^>]*>(.*?)<\/tbody>/s', $html, $body);
admin_courses_view_assert(isset($body[1]), 'Desktop course list must contain a table body');
preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/s', $body[1], $rows);
admin_courses_view_assert(count($cards[1]) === 20 && count($rows[1]) === 20, 'Twenty supplied courses must render as twenty mobile cards and twenty desktop rows');
admin_courses_view_assert(strpos($html, 'd-md-none') !== FALSE && strpos($html, 'd-none d-md-block') !== FALSE, 'Mobile and desktop lists must retain their responsive visibility');
admin_courses_view_assert(substr_count($html, '<th scope="col">') === 6, 'Desktop columns must have semantic headers');
admin_courses_view_assert(strpos($html, '21 kursus ditemukan') !== FALSE, 'Total must describe matching rows, not the twenty-row page');
foreach ($courses as $index => $course)
{
	foreach (array($cards[1][$index], $rows[1][$index]) as $item)
	{
		admin_courses_view_assert(strpos($item, html_escape($course['title'])) !== FALSE, 'Both layouts must retain the same escaped course title');
		$metadata = html_escape(($course['category_name'] ?: 'Tanpa kategori').' · '.study_grade_label($course['grade_id'])).' · '.html_escape($course['author_name'] ?: 'Pengelola');
		admin_courses_view_assert(strpos($item, $metadata) !== FALSE, 'Both layouts must retain escaped category/author metadata and a normal separator');
		admin_courses_view_assert(strpos($item, '2 bab · 3 sesi · 2 terbit') !== FALSE, 'Both layouts must show chapter and all-session counts');
		admin_courses_view_assert(strpos($item, html_escape(study_course_status_label($course['status']))) !== FALSE && strpos($item, html_escape(study_target_group_label($course['target_group']))) !== FALSE, 'Both layouts must use known labels or safe fallbacks, not raw status/group codes');
		admin_courses_view_assert(strpos($item, html_escape(study_format_datetime($course['updated_at']))) !== FALSE, 'Both layouts must format dates safely');
		admin_courses_view_assert(strpos($item, (int) $course['is_free'] === 1 ? 'Gratis' : 'Paket') !== FALSE, 'Both layouts must retain the same access label');
		admin_courses_view_assert(strpos($item, 'href="'.site_url('kelola/kursus/'.$course['id']).'"') !== FALSE, 'Each item must link to its own course');
	}
}
admin_courses_view_assert(strpos($html, '15 Okt 2026, 20.15 WIB') !== FALSE, 'Valid stored UTC dates must be shown in WIB');
admin_courses_view_assert(strpos($html, 'unknown-status') === FALSE && strpos($html, 'unknown-group') === FALSE && strpos($html, 'invalid-date') === FALSE, 'Invalid metadata must not be echoed');
admin_courses_view_assert(strpos($html, 'aria-current="page">Halaman 1 dari 2</span>') !== FALSE && strpos($html, 'href="'.admin_courses_view_pager_url(2, $query, 'review').'">Berikutnya</a>') !== FALSE, 'Page one must use the real shared pager and encoded q/status context');
admin_courses_view_assert(strpos($html, '>Sebelumnya</a>') === FALSE, 'Page one must not offer an invalid previous page');

$html = admin_courses_view_render(array_replace($filtered_result, array('courses' => array($courses[0]), 'page' => 2)));
admin_courses_view_safe($html);
admin_courses_view_assert(strpos($html, 'aria-current="page">Halaman 2 dari 2</span>') !== FALSE && strpos($html, 'href="'.admin_courses_view_pager_url(1, $query, 'review').'">Sebelumnya</a>') !== FALSE, 'Last page must use the same supplied context for its previous link');
admin_courses_view_assert(strpos($html, '>Berikutnya</a>') === FALSE, 'Last page must not offer an invalid next page');

$html = admin_courses_view_render(array_replace($base, array('courses' => array($courses[0]), 'total_rows' => 1)));
admin_courses_view_safe($html);
admin_courses_view_assert(strpos($html, '1 kursus ditemukan') !== FALSE && strpos($html, 'aria-current="page"') === FALSE, 'A single populated page must show its total without unnecessary pagination');
restore_error_handler();
fwrite(STDOUT, "PASS admin course list twenty-row desktop/mobile parity, empty/filter states, escaped metadata, labels and shared pagination. This is not HTTP, database or visual QA.\n");
