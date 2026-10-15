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
function dashboard_resume_assert($condition, $label)
{
	if ( ! $condition) { throw new RuntimeException($label); }
}
function dashboard_resume_render($data)
{
	extract($data, EXTR_SKIP);
	ob_start();
	try
	{
		require FCPATH.'application/views/dashboard/index.php';
		return ob_get_contents();
	}
	finally { ob_end_clean(); }
}
function dashboard_resume_section($html)
{
	preg_match('/<section\b[^>]*\bid="resume-learning"[^>]*>(.*?)<\/section>/s', $html, $section);
	return isset($section[0]) ? $section[0] : '';
}
function dashboard_resume_safe($html)
{
	dashboard_resume_assert( ! preg_match('/<(?:script|img|form|input|textarea)\b/i', $html), 'Dashboard resume must stay read-only and must not render injected markup or hidden inputs');
	dashboard_resume_assert( ! preg_match('/\s(?:onclick|onerror|onload)=/i', $html), 'Dashboard resume must not add inline script handlers');
	dashboard_resume_assert(strpos($html, 'javascript:') === FALSE, 'Resume links must not require a JavaScript URL');
	preg_match_all('/(?:^|\s)id="([^"]+)"/', $html, $ids);
	dashboard_resume_assert(count($ids[1]) === count(array_unique($ids[1])), 'Dashboard HTML IDs must remain unique');
	preg_match_all('/\baria-labelledby="([^"]+)"/', $html, $labels);
	foreach ($labels[1] as $label)
	{
		foreach (explode(' ', $label) as $target) { dashboard_resume_assert(in_array($target, $ids[1], TRUE), 'Section headings must exist for aria-labelledby'); }
	}
	foreach (array('PRIVATE_LEARNER_ID', 'PRIVATE_PROGRESS_ID', 'PRIVATE_ENTITLEMENT_ID', 'PRIVATE_STORAGE_KEY', 'PRIVATE_BODY_TEXT', 'PRIVATE_COURSE_SLUG') as $private_value)
	{
		dashboard_resume_assert(strpos($html, $private_value) === FALSE, 'Dashboard must not expose unused private row fields: '.$private_value);
	}
}
function dashboard_resume_base($account_kind)
{
	return array(
		'current_user' => array('id' => 'PRIVATE_LEARNER_ID', 'account_kind' => $account_kind, 'display_name' => 'Ayu & Bima <script>aman</script> "kutip" 日本語', 'learning_stage' => 'primary'),
		'progress' => array('completed_lessons' => 0, 'in_progress_lessons' => 0),
		'course_progress' => array(),
		'children' => array(),
		'child_course_progress' => array(),
		'child_flashcard_progress' => array(),
		'resume_lessons' => array()
	);
}

$lessons = array();
for ($index = 1; $index <= 3; $index++)
{
	$lessons[] = array(
		'lesson_id' => 100 + $index,
		'lesson_slug' => 'pelajaran-'.$index,
		'lesson_title' => 'Pelajaran '.$index.' <script>aman</script> "kutip" & 日本語',
		'course_title' => 'Kursus '.$index.' <script>aman</script> "kutip" & Bahasa',
		'last_activity_at' => $index === 3 ? '2026-10-14 23:05:00' : '2026-10-15 13:15:00',
		'last_position' => $index === 1 ? 0 : ($index === 2 ? 3500 : 10000),
		'course_slug' => 'PRIVATE_COURSE_SLUG',
		'learner_user_id' => 'PRIVATE_LEARNER_ID',
		'progress_id' => 'PRIVATE_PROGRESS_ID',
		'entitlement_id' => 'PRIVATE_ENTITLEMENT_ID',
		'storage_key' => 'PRIVATE_STORAGE_KEY',
		'body_text' => 'PRIVATE_BODY_TEXT'
	);
}

foreach (array('adult', 'child') as $account_kind)
{
	$base = dashboard_resume_base($account_kind);
	foreach (array(FALSE, TRUE) as $has_activity)
	{
		$data = $base;
		if ($has_activity) { $data['progress'] = array('completed_lessons' => 2, 'in_progress_lessons' => 1); }
		$html = dashboard_resume_render($data);
		dashboard_resume_safe($html);
		dashboard_resume_assert(dashboard_resume_section($html) === '', 'An empty resume result must not create a resume section');
		$new_heading = $account_kind === 'child' ? 'Ayo temukan hal baru hari ini' : 'Pilih pelajaran pertamamu';
		$returning_heading = $account_kind === 'child' ? 'Belajarmu terus berkembang' : 'Progres belajarmu tersimpan';
		dashboard_resume_assert(strpos($html, $has_activity ? $returning_heading : $new_heading) !== FALSE, 'Empty resume must retain the appropriate new/returning learner guidance');
		dashboard_resume_assert(strpos($html, $has_activity ? $new_heading : $returning_heading) === FALSE, 'Empty resume must not confuse a returning learner with a first-time learner');
		dashboard_resume_assert(strpos($html, 'href="'.site_url('kursus').'"') !== FALSE && strpos($html, 'class="empty-card') !== FALSE, 'An empty resume result must keep the existing catalog entry point');
		dashboard_resume_assert(strpos($html, 'Daftar lanjut belajar belum dapat dimuat') === FALSE, 'A valid empty result must not be called a read failure');
		dashboard_resume_assert(strpos($html, html_escape($data['current_user']['display_name'])) !== FALSE, 'Learner greeting must preserve escaped Unicode text');
		if ($account_kind === 'child') { dashboard_resume_assert(strpos($html, 'href="'.site_url('latihan/keluarga').'"') !== FALSE, 'Child family assignments must remain reachable in the empty state'); }

		$data['resume_lessons'] = FALSE;
		$html = dashboard_resume_render($data);
		dashboard_resume_safe($html);
		$section = dashboard_resume_section($html);
		dashboard_resume_assert($section !== '' && strpos($section, 'Daftar lanjut belajar belum dapat dimuat.') !== FALSE, 'FALSE must show an explicit resume read failure, not be treated as zero rows');
		dashboard_resume_assert(strpos($section, 'role="status"') !== FALSE && strpos($section, 'Progres yang sudah tersimpan tidak dihapus.') !== FALSE, 'Read failure must be announced without claiming progress was erased');
		dashboard_resume_assert(preg_match('/<a\b[^>]*href="'.preg_quote(site_url('beranda'), '/').'"[^>]*>Muat ulang beranda<\/a>/', $section) === 1, 'Read failure must offer a fixed native GET refresh link');
		dashboard_resume_assert(strpos($html, $new_heading) === FALSE && strpos($html, $returning_heading) === FALSE && strpos($html, 'class="empty-card') === FALSE, 'Read failure must not render misleading empty-first or generic catalog cards');
		dashboard_resume_assert(strpos($html, 'Jelajahi kursus lain') !== FALSE && strpos($section, 'data-lesson-id=') === FALSE, 'Read failure may retain a catalog fallback but must not invent resume records');
		if ($account_kind === 'child') { dashboard_resume_assert(strpos($html, 'href="'.site_url('latihan/keluarga').'"') !== FALSE, 'Read failure must not hide child family assignments'); }
	}

	foreach (array(1, 2, 3) as $count)
	{
		$data = $base;
		$data['progress'] = array('completed_lessons' => 4, 'in_progress_lessons' => $count);
		$data['resume_lessons'] = array_slice($lessons, 0, $count);
		$html = dashboard_resume_render($data);
		dashboard_resume_safe($html);
		$section = dashboard_resume_section($html);
		dashboard_resume_assert(strpos($section, 'id="resume-learning-heading">Lanjutkan belajar</h2>') !== FALSE, 'Resume section needs its own visible and programmatic heading');
		dashboard_resume_assert(strpos($html, 'id="resume-learning"') < strpos($html, 'Riwayat latihan saya'), 'The next learning step must come before history shortcuts');
		dashboard_resume_assert(strpos($html, 'class="empty-card') === FALSE && strpos($html, 'Jelajahi kursus lain') !== FALSE, 'Populated resume cards must replace the large generic catalog card, not duplicate it');
		dashboard_resume_assert(strpos($section, 'progres yang terakhir disimpan') !== FALSE && stripos($section, 'terakhir dibuka') === FALSE, 'Activity timestamps describe saved progress, never a last-opened event');
		dashboard_resume_assert(strpos($section, '%') === FALSE && strpos($section, '<progress') === FALSE, 'Resume cards must not turn reading position into a completion percentage');
		preg_match_all('/<article\b[^>]*data-lesson-id="([^"]+)"[^>]*>(.*?)<\/article>/s', $section, $cards);
		dashboard_resume_assert(count($cards[1]) === $count && substr_count($section, 'class="col-md-6 col-xl-4"') === $count, 'Render each supplied bounded resume row once in the responsive grid');
		foreach ($data['resume_lessons'] as $index => $lesson)
		{
			$card = $cards[2][$index];
			dashboard_resume_assert($cards[1][$index] === (string) $lesson['lesson_id'], 'View must preserve the model-provided row order and public lesson identity');
			dashboard_resume_assert(strpos($card, html_escape($lesson['lesson_title'])) !== FALSE && strpos($card, html_escape($lesson['course_title'])) !== FALSE, 'Lesson/course names must be escaped exactly once and preserve Unicode');
			dashboard_resume_assert(strpos($card, html_escape(html_escape($lesson['lesson_title']))) === FALSE && strpos($card, html_escape(html_escape($lesson['course_title']))) === FALSE, 'Resume text must not be double-escaped');
			dashboard_resume_assert(strpos($card, 'Progres disimpan: '.html_escape(study_format_datetime($lesson['last_activity_at']))) !== FALSE, 'Saved activity must use the shared date formatter');
			dashboard_resume_assert(strpos($card, 'href="'.html_escape(site_url('materi/'.$lesson['lesson_slug'].'?lanjut=1#reading-material')).'"') !== FALSE, 'Each native action must preserve the existing resume query and reading anchor');
			dashboard_resume_assert(strpos($card, 'Lanjutkan materi<span class="visually-hidden">: '.html_escape($lesson['lesson_title']).'</span>') !== FALSE, 'Repeated resume actions need lesson-specific accessible names');
		}
		dashboard_resume_assert(strpos($section, '15 Okt 2026, 20.15 WIB') !== FALSE, 'UTC activity time must be presented in Jakarta time');
		if ($count === 3) { dashboard_resume_assert(strpos($section, '15 Okt 2026, 06.05 WIB') !== FALSE, 'Date formatting must handle the UTC-to-WIB date boundary'); }
		if ($account_kind === 'child') { dashboard_resume_assert(strpos($html, 'href="'.site_url('latihan/keluarga').'"') !== FALSE, 'Populated resume cards must retain the child family assignment entry point'); }
	}
}

$unsafe = dashboard_resume_base('adult');
$unsafe['resume_lessons'] = array($lessons[0]);
$unsafe['resume_lessons'][0]['lesson_slug'] = 'pelajaran-"kutip"&-日本語';
$unsafe['resume_lessons'][0]['last_activity_at'] = '<script>invalid-date</script>';
$html = dashboard_resume_render($unsafe);
dashboard_resume_safe($html);
$section = dashboard_resume_section($html);
$expected_url = site_url('materi/'.$unsafe['resume_lessons'][0]['lesson_slug'].'?lanjut=1#reading-material');
dashboard_resume_assert(strpos($section, 'href="'.html_escape($expected_url).'"') !== FALSE, 'A supplied slug containing quotes or ampersand must not escape its link attribute');
preg_match('/<a\b[^>]*href="([^"]+)"[^>]*>Lanjutkan materi/', $section, $action);
dashboard_resume_assert(isset($action[1]) && html_entity_decode($action[1], ENT_QUOTES, 'UTF-8') === $expected_url, 'Resume URL must be escaped exactly once');
dashboard_resume_assert(strpos($section, 'Progres disimpan: —') !== FALSE && strpos($section, 'invalid-date') === FALSE, 'Invalid timestamp must use the safe formatter fallback');

foreach (array(array(), $lessons, FALSE) as $unexpected_resume)
{
	$data = dashboard_resume_base('parent');
	unset($data['progress'], $data['course_progress']);
	$data['resume_lessons'] = $unexpected_resume;
	$html = dashboard_resume_render($data);
	dashboard_resume_safe($html);
	dashboard_resume_assert(dashboard_resume_section($html) === '' && strpos($html, 'data-lesson-id=') === FALSE && strpos($html, 'Daftar lanjut belajar belum dapat dimuat') === FALSE, 'Parent dashboard must not show participant resume data or its error state even if supplied accidentally');
	dashboard_resume_assert(strpos($html, html_escape($lessons[0]['lesson_title'])) === FALSE && strpos($html, '?lanjut=1') === FALSE, 'Accidentally supplied participant rows must not leak through a parent dashboard');
	dashboard_resume_assert(strpos($html, 'Keluargamu') !== FALSE && strpos($html, 'Belum ada akun anak') !== FALSE && strpos($html, 'Susun latihan anak') !== FALSE, 'Parent family guidance must remain unchanged');
}
restore_error_handler();
fwrite(STDOUT, "PASS dashboard resume view: adult/child new and returning empty states, explicit unavailable state, one-to-three cards before history, escaped text/native resume links, shared WIB dates, parent isolation and no private fields/forms. Model filtering, limits, HTTP, database and visual behavior are not tested here.\n");
