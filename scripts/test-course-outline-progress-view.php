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
function study_grade_label($id)
{
	return 'SD Kelas 2';
}
function study_grade_code($id)
{
	return 'sd_2';
}
require FCPATH.'application/helpers/study_ui_helper.php';
function outline_progress_assert($condition, $label)
{
	if ( ! $condition) { throw new RuntimeException($label); }
}
class Outline_progress_view_loader
{
	public $load;
	public function __construct()
	{
		$this->load = new class { public function view($name) {} };
	}
	public function render($data)
	{
		$old_get = $_GET;
		$_GET = array('learner_user_id' => 'PRIVATE_QUERY_LEARNER', 'user_id' => 'PRIVATE_QUERY_USER', 'anak' => 'PRIVATE_QUERY_CHILD');
		extract($data, EXTR_SKIP);
		ob_start();
		try
		{
			require FCPATH.'application/views/catalog/course.php';
			return ob_get_contents();
		}
		finally
		{
			ob_end_clean();
			$_GET = $old_get;
		}
	}
}
function outline_progress_render($data)
{
	return (new Outline_progress_view_loader())->render($data);
}
function outline_progress_safe($html)
{
	outline_progress_assert( ! preg_match('/<(?:script|img|iframe|form|input|textarea|button)\b/i', $html), 'Course outline must remain read-only without injected markup or mutation controls');
	outline_progress_assert( ! preg_match('/<[^>]+\s(?:onclick|onerror|onload)=/i', $html) && strpos($html, 'javascript:') === FALSE, 'Course links must not use injected or inline JavaScript');
	outline_progress_assert(strpos($html, '<progress') === FALSE && strpos($html, 'aria-valuenow=') === FALSE, 'Lesson status must not become a mastery percentage or progress meter');
	preg_match_all('/(?:^|\s)id="([^"]+)"/', $html, $ids);
	outline_progress_assert(count($ids[1]) === count(array_unique($ids[1])), 'Outline HTML IDs must remain unique');
	foreach (array('PRIVATE_OWNER_ID', 'PRIVATE_PROGRESS_ID', 'PRIVATE_QUERY_LEARNER', 'PRIVATE_QUERY_USER', 'PRIVATE_QUERY_CHILD', 'PRIVATE_UNUSED_STATUS') as $private_value)
	{
		outline_progress_assert(strpos($html, $private_value) === FALSE, 'Unused identities, progress row metadata and URL identity parameters must not render');
	}
}
function outline_progress_visible_text($html)
{
	$html = preg_replace('/<span\b[^>]*class="[^"]*\bvisually-hidden\b[^"]*"[^>]*>.*?<\/span>/s', '', $html);
	return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
}
function outline_progress_rows($html, $data, $states)
{
	preg_match_all('/<li\b(?=[^>]*\bdata-lesson-id="([1-9][0-9]*)")[^>]*>.*?<\/li>/s', $html, $rows);
	outline_progress_assert(count($rows[0]) === count($data['lessons']), 'Each supplied lesson must render exactly one list row');
	$labels = array('not_started' => 'Belum dimulai', 'in_progress' => 'Sedang dipelajari', 'completed' => 'Selesai');
	$actions = array('not_started' => 'Mulai belajar', 'in_progress' => 'Lanjutkan', 'completed' => 'Baca ulang');
	foreach ($data['lessons'] as $index => $lesson)
	{
		$row = $rows[0][$index];
		outline_progress_assert($rows[1][$index] === (string) $lesson['id'], 'The view must preserve the supplied section/lesson order, not sort by lesson ID or status');
		outline_progress_assert(strpos($row, html_escape($lesson['title'])) !== FALSE && strpos($row, html_escape($lesson['section_title'])) !== FALSE, 'Lesson and section titles must preserve escaped Unicode');
		outline_progress_assert(strpos($row, html_escape(html_escape($lesson['title']))) === FALSE, 'Lesson title must not be double-escaped');
		outline_progress_assert(strpos($row, 'text-truncate') === FALSE && preg_match('/class="[^"]*\bflex-(?:column|wrap)\b[^"]*"/', $row) === 1, 'Lesson rows must retain a stacking/wrapping mobile layout without truncating titles');
		outline_progress_assert(preg_match('/<div\b[^>]*class="[^"]*\btext-break\b[^"]*"[^>]*>\s*<span[^>]*>Sesi [0-9]+<\/span>\s*<strong>/', $row) === 1, 'Lesson title wrapper must retain the session label and allow long words to wrap');
		if ((int) $lesson['estimated_minutes'] > 0) { outline_progress_assert(strpos($row, (int) $lesson['estimated_minutes'].' menit') !== FALSE, 'Existing estimated reading time must remain visible'); }
		$status = $states === NULL ? NULL : $states[$lesson['id']];
		preg_match_all('/<span\b[^>]*\bdata-lesson-progress="([^"]*)"[^>]*>(.*?)<\/span>/s', $row, $badges);
		if ($status === NULL)
		{
			outline_progress_assert(count($badges[0]) === 0, 'Guests and failed progress reads must not display personal status badges');
			foreach ($labels as $label) { outline_progress_assert(strpos($row, $label) === FALSE, 'No status label may be inferred when personal progress is unavailable'); }
		}
		else
		{
			$label = isset($labels[$status]) ? $labels[$status] : 'Progres belum diketahui';
			outline_progress_assert(count($badges[0]) === 1 && html_entity_decode($badges[1][0], ENT_QUOTES, 'UTF-8') === $status, 'Every logged-in lesson must have exactly its own supplied/defaulted status');
			outline_progress_assert(outline_progress_visible_text($badges[2][0]) === $label, 'Status wording must use the shared Indonesian labels or safe unknown fallback');
			outline_progress_assert(strpos($badges[0][0], 'text-wrap') !== FALSE, 'Status badges must allow wrapping instead of clipping long labels');
			if ($status === 'completed') { outline_progress_assert(strpos($badges[0][0], 'aria-hidden="true"') !== FALSE, 'A decorative completion icon must not replace or duplicate the text label'); }
		}
		preg_match_all('/<a\b[^>]*\bhref="([^"]+)"[^>]*>(.*?)<\/a>/s', $row, $links);
		$can_read = $data['can_read_course'] || (int) $lesson['is_preview'] === 1;
		if ( ! $can_read)
		{
			outline_progress_assert(count($links[0]) === 0 && strpos($row, 'Terkunci') !== FALSE, 'Saved status must never grant a locked lesson a reading link');
			outline_progress_assert(strpos($row, '?lanjut=1') === FALSE, 'Locked rows must not hide a resume destination in markup');
			continue;
		}
		outline_progress_assert(count($links[0]) === 1 && strpos($row, 'Terkunci') === FALSE, 'Each allowed lesson or preview must have exactly one native reading action');
		$neutral_action = (int) $lesson['is_preview'] === 1 && ! $data['can_read_course'] ? 'Coba pratinjau' : 'Baca';
		$action = $status !== NULL && isset($actions[$status]) ? $actions[$status] : $neutral_action;
		outline_progress_assert(outline_progress_visible_text($links[2][0]) === $action, 'Action labels must reflect saved status only when that status is available');
		$suffix = $status === 'in_progress' ? '?lanjut=1#reading-material' : '';
		outline_progress_assert(html_entity_decode($links[1][0], ENT_QUOTES, 'UTF-8') === site_url('materi/'.$lesson['slug'].$suffix), 'Only in-progress actions may add the existing native reading-position query/anchor');
		preg_match('/<span\b[^>]*class="[^"]*\bvisually-hidden\b[^"]*"[^>]*>(.*?)<\/span>/s', $links[2][0], $hidden_title);
		outline_progress_assert(isset($hidden_title[1]) && strpos($hidden_title[1], html_escape($lesson['title'])) !== FALSE, 'Every repeated material action needs its escaped full lesson title in its accessible name');
	}
}

$lessons = array();
foreach (array(14, 12, 15, 11) as $index => $lesson_id)
{
	$lessons[] = array('id' => $lesson_id, 'title' => 'Materi '.$lesson_id.' <script>aman</script> "kutip" & 日本語 '.str_repeat('judul panjang ', 8), 'slug' => 'materi-'.$lesson_id, 'section_id' => $index < 2 ? 1 : 2, 'section_title' => 'Bagian '.($index < 2 ? 'A' : 'B').' <script>aman</script> & 日本語', 'estimated_minutes' => $index === 0 ? 0 : 5 + $index, 'is_preview' => in_array($lesson_id, array(14, 15), TRUE) ? 1 : 0, 'learner_user_id' => 'PRIVATE_OWNER_ID', 'progress_id' => 'PRIVATE_PROGRESS_ID');
}
$course = array('id' => 4, 'slug' => 'kursus-aman', 'grade_id' => 2, 'category_name' => 'Kategori <script>aman</script>', 'title' => 'Kursus <script>aman</script> & 日本語', 'summary' => 'Ringkasan <script>aman</script> "kutip" & 日本語', 'learning_objectives' => "Tujuan <script>aman</script>\nBaris kedua.", 'prerequisites' => 'Persiapan <script>aman</script>', 'age_min' => 7, 'age_max' => 12, 'estimated_minutes' => 20, 'is_free' => 0);
$base = array('course' => $course, 'lessons' => $lessons, 'target_group_label' => 'Sekolah dasar', 'question_count' => 0, 'has_preview_lessons' => TRUE, 'can_read_course' => TRUE, 'current_user' => array('id' => 'PRIVATE_OWNER_ID', 'account_kind' => 'adult'), 'lesson_progress_map' => array());
$map = array(12 => 'not_started', 15 => 'in_progress', 11 => 'completed', 999 => 'PRIVATE_UNUSED_STATUS');
$states = array(14 => 'not_started', 12 => 'not_started', 15 => 'in_progress', 11 => 'completed');
$empty_states = array(14 => 'not_started', 12 => 'not_started', 15 => 'not_started', 11 => 'not_started');
$renders = 0;
foreach (array('not_started' => 'Belum dimulai', 'in_progress' => 'Sedang dipelajari', 'completed' => 'Selesai') as $status => $label)
{
	outline_progress_assert(study_lesson_progress_label($status) === $label, 'Shared lesson progress labels must use the agreed wording');
}

foreach (array('adult', 'child', 'parent') as $account_kind)
{
	foreach (array(FALSE, TRUE) as $can_read)
	{
		foreach (array(FALSE, TRUE) as $has_saved_rows)
		{
			$data = $base;
			$data['current_user']['account_kind'] = $account_kind;
			$data['can_read_course'] = $can_read;
			$data['lesson_progress_map'] = $has_saved_rows ? $map : array();
			$html = outline_progress_render($data);
			$renders++;
			outline_progress_safe($html);
			outline_progress_rows($html, $data, $has_saved_rows ? $states : $empty_states);
			outline_progress_assert(strpos($html, 'Progres akunmu') !== FALSE && strpos($html, 'Muat ulang progres') === FALSE, 'A successful personal map must describe own-account progress, not a failure');
			outline_progress_assert(strpos($html, 'Progres anak') === FALSE, 'Parent catalog must not relabel the supplied own-account map as child progress');
			foreach (array('title', 'summary', 'category_name') as $field) { outline_progress_assert(strpos($html, html_escape($course[$field])) !== FALSE, 'Existing escaped course metadata must remain visible'); }
		}
		$data['lesson_progress_map'] = FALSE;
		$html = outline_progress_render($data);
		$renders++;
		outline_progress_safe($html);
		outline_progress_rows($html, $data, NULL);
		outline_progress_assert(preg_match('/<div\b[^>]*role="status"[^>]*>.*?Progres belajarmu belum dapat dimuat\..*?<\/div>/s', $html) === 1, 'Query failure needs a polite status message, not invented not-started badges');
		outline_progress_assert(strpos($html, 'progres tersimpan tidak dihapus') !== FALSE && strpos($html, 'href="'.html_escape(site_url('kursus/'.$course['slug'])).'">Muat ulang progres</a>') !== FALSE, 'Failure must offer a fixed native course GET retry without claiming history deletion');
		outline_progress_assert(strpos($html, 'Progres akunmu untuk pelajaran') === FALSE && strpos($html, 'Pelajaran untuk kursus ini sedang disiapkan.') === FALSE, 'Populated failed progress must not look like an empty course or a successful personal map');
	}
}

// Guest rendering must ignore even an accidentally supplied personal map/error.
foreach (array(NULL, $map, FALSE) as $unexpected_map)
{
	foreach (array(FALSE, TRUE) as $can_read)
	{
		$data = $base;
		$data['current_user'] = NULL;
		$data['lesson_progress_map'] = $unexpected_map;
		$data['can_read_course'] = $can_read;
		$html = outline_progress_render($data);
		$renders++;
		outline_progress_safe($html);
		outline_progress_rows($html, $data, NULL);
		outline_progress_assert(strpos($html, 'Progres akunmu') === FALSE && strpos($html, 'Muat ulang progres') === FALSE && strpos($html, 'Progres belajarmu belum dapat dimuat') === FALSE, 'Guests must not receive personal progress labels or personal-load error states');
	}
}

foreach (array(NULL, array(), FALSE) as $empty_map)
{
	$data = $base;
	$data['lessons'] = array();
	$data['has_preview_lessons'] = FALSE;
	$data['lesson_progress_map'] = $empty_map;
	if ($empty_map === NULL) { $data['current_user'] = NULL; }
	$html = outline_progress_render($data);
	$renders++;
	outline_progress_safe($html);
	outline_progress_rows($html, $data, NULL);
	outline_progress_assert(strpos($html, 'Pelajaran untuk kursus ini sedang disiapkan.') !== FALSE && strpos($html, 'data-lesson-progress=') === FALSE && strpos($html, 'Muat ulang progres') === FALSE, 'Empty lesson lists must keep their separate preparation state without a fabricated progress failure');
}

$unsafe = $base;
$unsafe['course']['slug'] = 'kursus-"kutip"&-日本語';
$unsafe['lessons'][2]['slug'] = 'materi-"kutip"&-日本語';
$unsafe['lesson_progress_map'] = $map;
$html = outline_progress_render($unsafe);
$renders++;
outline_progress_safe($html);
outline_progress_rows($html, $unsafe, $states);
$unsafe['lesson_progress_map'] = FALSE;
$html = outline_progress_render($unsafe);
$renders++;
outline_progress_safe($html);
outline_progress_rows($html, $unsafe, NULL);
outline_progress_assert(strpos($html, 'href="'.html_escape(site_url('kursus/'.$unsafe['course']['slug'])).'">Muat ulang progres</a>') !== FALSE, 'Retry URL must escape quote/ampersand/Unicode without accepting a raw return URL');

$unknown = $base;
$unknown['lesson_progress_map'] = $map;
$unknown['lesson_progress_map'][15] = 'unknown-"<script>aman</script>';
$unknown_states = $states;
$unknown_states[15] = $unknown['lesson_progress_map'][15];
$html = outline_progress_render($unknown);
$renders++;
outline_progress_safe($html);
outline_progress_rows($html, $unknown, $unknown_states);

restore_error_handler();
fwrite(STDOUT, 'PASS '.$renders." course outline renders: own-account statuses/defaults, guest isolation, read-failure/empty distinction, allowed native resume/reread actions, locked-row denial, escaped Unicode/URLs and accessible mobile markup. Stub URL helper; no identity-query model, HTTP, database or visual claims.\n");
