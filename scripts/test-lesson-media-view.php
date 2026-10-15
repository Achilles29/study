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
function form_open($path, $attributes = array())
{
	return '<form method="post" action="'.html_escape(site_url($path)).'">';
}
function form_open_multipart($path, $attributes = array())
{
	return '<form method="post" enctype="multipart/form-data" action="'.html_escape(site_url($path)).'">';
}
function form_close()
{
	return '</form>';
}
require FCPATH.'application/helpers/study_ui_helper.php';
function media_view_assert($condition, $label)
{
	if ( ! $condition) { throw new RuntimeException($label); }
}
function media_view_render($view, $data)
{
	extract($data, EXTR_SKIP);
	ob_start();
	require FCPATH.'application/views/'.$view.'.php';
	return ob_get_clean();
}
function media_view_form($html, $action)
{
	preg_match_all('/<form\b.*?<\/form>/s', $html, $forms);
	foreach ($forms[0] as $form) { if (strpos($form, 'name="action" value="'.$action.'"') !== FALSE) { return $form; } }
	throw new RuntimeException('Missing media form: '.$action);
}
function media_view_safe($html)
{
	media_view_assert(strpos($html, '<script>') === FALSE && strpos($html, ' onerror=') === FALSE, 'View must not execute injected markup');
	media_view_assert(strpos($html, 'PRIVATE_STORAGE_KEY') === FALSE && strpos($html, 'PRIVATE_HASH') === FALSE && strpos($html, 'PRIVATE_UPLOADER_ID') === FALSE, 'View must not expose private storage metadata');
	preg_match_all('/\bid="([^"]+)"/', $html, $ids);
	media_view_assert(count($ids[1]) === count(array_unique($ids[1])), 'HTML IDs must be unique');
}
$course = array('id' => '4', 'title' => 'Kursus <script>aman</script>', 'summary' => 'Ringkasan', 'status' => 'draft', 'content_version' => 12, 'target_group' => 'all_ages', 'age_min' => NULL, 'age_max' => NULL, 'learning_objectives' => 'Tujuan', 'prerequisites' => '', 'rights_status' => 'owned', 'source_note' => 'Sumber tim');
$lesson = array('id' => '7', 'course_id' => '4', 'course_title' => 'Kursus aman', 'course_slug' => 'kursus-aman', 'title' => 'Pelajaran <script>aman</script>', 'slug' => 'pelajaran-aman', 'content_version' => 9, 'section_title' => 'Bagian awal', 'estimated_minutes' => 5, 'body_text' => 'Teks utama <script>tetap aman</script>', 'status' => 'draft', 'rights_status' => 'owned', 'is_preview' => 0);
$image = array('alt_text' => 'Diagram <script>aman</script> "kutip"', 'caption' => "Keterangan <script>aman</script>\nBaris dua", 'source_note' => 'Sumber <script>aman</script>', 'rights_status' => 'licensed', 'width' => 1200, 'height' => 800, 'byte_size' => 12345, 'storage_key' => 'PRIVATE_STORAGE_KEY.png', 'sha256' => 'PRIVATE_HASH', 'uploader_user_id' => 'PRIVATE_UPLOADER_ID');
$editor = array('course' => $course, 'lesson' => $lesson, 'image' => $image, 'form_errors' => array(), 'submit_error' => NULL, 'has_conflict' => FALSE, 'posted_fields' => array(), 'active_action' => '', 'upload_available' => TRUE);
$html = media_view_render('admin/lesson_media', $editor);
media_view_safe($html);
media_view_assert(substr_count($html, 'name="revision" value="9"') === 3, 'Each mutation form must carry the lesson revision');
media_view_assert(strpos(media_view_form($html, 'upload'), 'enctype="multipart/form-data"') !== FALSE, 'Upload must use native multipart encoding');
media_view_assert(strpos(media_view_form($html, 'metadata'), 'enctype=') === FALSE, 'Metadata should not require a file upload');
media_view_assert(strpos(media_view_form($html, 'remove'), 'name="confirm_remove" value="yes" required') !== FALSE, 'Remove requires explicit confirmation');
preg_match('/<input\b[^>]*type="file"[^>]*>/', $html, $file_input);
media_view_assert(isset($file_input[0]) && strpos($file_input[0], 'name="image"') !== FALSE && strpos($file_input[0], ' value=') === FALSE, 'File control must not refill a server path or filename');
media_view_assert(strpos($html, '3.145.728 byte') !== FALSE && strpos($html, '4.000.000 piksel') !== FALSE, 'Upload constraints must be visible');
media_view_assert(strpos($html, 'width="1200" height="800"') !== FALSE && strpos($html, '/kelola/kursus/4/pelajaran/7/gambar/pratinjau') !== FALSE, 'Editor image needs private preview endpoint and reserved dimensions');
media_view_assert(strpos($html, 'alt="Diagram &lt;script&gt;aman&lt;/script&gt; &quot;kutip&quot;"') !== FALSE, 'Alternative text must be escaped as an attribute');
media_view_assert(strpos($html, 'Deskripsi gambar:</strong> Diagram &lt;script&gt;') !== FALSE, 'Description must remain visible independently of image rendering');

$html = media_view_render('admin/lesson_media', array_replace($editor, array('upload_available' => FALSE)));
media_view_safe($html);
media_view_assert(strpos(media_view_form($html, 'upload'), '<fieldset disabled>') !== FALSE, 'Unavailable upload must be disabled');
media_view_assert(strpos(media_view_form($html, 'metadata'), '<fieldset disabled>') === FALSE && strpos(media_view_form($html, 'remove'), '<fieldset disabled>') === FALSE, 'Missing upload capability must not disable metadata or removal');
media_view_assert(strpos($html, 'Pengolah gambar (GD) atau penyimpanan privat belum siap') !== FALSE, 'Unavailable capability must be explained');
$html = media_view_render('admin/lesson_media', array_replace($editor, array('has_conflict' => TRUE, 'submit_error' => 'Versi <script>lama</script>', 'posted_fields' => array('revision' => '8'))));
media_view_safe($html);
media_view_assert(substr_count($html, '<fieldset disabled>') === 3 && substr_count($html, 'name="revision" value="8"') === 3, 'Conflict must preserve the submitted revision and lock every mutation');
media_view_assert(strpos($html, '/kelola/kursus/4/pelajaran/7/gambar">Muat ulang gambar pendamping') !== FALSE, 'Conflict should offer a fixed GET recovery link');
media_view_assert(strpos($html, '<img ') === FALSE && strpos($html, 'Pratinjau gambar ditunda') !== FALSE && strpos($html, 'Deskripsi gambar:</strong> Diagram') !== FALSE, 'Locked recovery must retain text but not combine a fresh binary with stale metadata');

foreach (array('metadata', 'upload') as $action)
{
	$posted = array('revision' => '9', 'alt_text' => 'Isian <script>baru</script>', 'caption' => 'Keterangan baru', 'rights_status' => '<script>bad</script>', 'source_note' => 'Sumber baru');
	$errors = array('alt_text' => 'Periksa deskripsi.', 'rights_status' => 'Pilih hak.');
	if ($action === 'upload') { $errors['image'] = 'Pilih berkas lagi.'; }
	$html = media_view_render('admin/lesson_media', array_replace($editor, array('active_action' => $action, 'posted_fields' => $posted, 'form_errors' => $errors)));
	media_view_safe($html);
	$form = media_view_form($html, $action);
	media_view_assert(strpos($form, 'Isian &lt;script&gt;baru&lt;/script&gt;') !== FALSE && strpos($form, 'aria-describedby="field-error-alt_text"') !== FALSE, 'Submitted safe text and linked error must stay in the active form');
	media_view_assert(substr_count($html, 'id="field-error-alt_text"') === 1, 'Inactive form must not duplicate errors');
	media_view_assert(strpos($form, '<option value="" selected>Pilih status hak penggunaan</option>') !== FALSE, 'Invalid rights must require a new explicit choice');
	if ($action === 'upload') { media_view_assert(strpos($form, 'aria-describedby="field-error-image"') !== FALSE && strpos($form, 'id="field-error-image"') !== FALSE, 'File error must be linked to file input'); }
}
$html = media_view_render('admin/lesson_media', array_replace($editor, array('image' => NULL, 'upload_available' => FALSE)));
media_view_safe($html);
media_view_assert(substr_count($html, '<form ') === 1 && strpos($html, '<img ') === FALSE && strpos($html, 'Belum ada gambar pendamping') !== FALSE, 'Empty editor should show only the upload form and empty state');

$learner = array('lesson' => $lesson, 'lesson_image' => $image, 'lesson_progress' => NULL, 'next_lesson' => NULL, 'flashcard_deck' => NULL, 'flashcards' => array(), 'current_user' => NULL);
$html = media_view_render('catalog/lesson', $learner);
media_view_safe($html);
media_view_assert(strpos($html, 'src="http://localhost/study/index.php/materi/pelajaran-aman/gambar"') !== FALSE && strpos($html, '/gambar/pratinjau') === FALSE, 'Learner image must use the access-checked public lesson endpoint');
media_view_assert(strpos($html, 'Teks utama &lt;script&gt;tetap aman&lt;/script&gt;') !== FALSE && strpos($html, 'Deskripsi gambar:') !== FALSE && strpos($html, 'Sumber &lt;script&gt;aman&lt;/script&gt;') !== FALSE, 'Image must supplement escaped text, visible description and source');
$preview = array_replace($learner, array('is_content_preview' => TRUE, 'preview_course' => $course, 'preview_theme_options' => array('primary', 'all_ages'), 'preview_theme' => 'primary'));
$html = media_view_render('catalog/lesson', $preview);
media_view_safe($html);
media_view_assert(strpos($html, '/kelola/kursus/4/pelajaran/7/gambar/pratinjau') !== FALSE && strpos($html, '/materi/pelajaran-aman/gambar') === FALSE, 'Admin preview must use its own scoped image endpoint');
media_view_assert(strpos($html, '<details class="mt-2" open>') !== FALSE, 'Image rights must be open for the reviewer');
$html = media_view_render('catalog/lesson', array_replace($learner, array('lesson_image' => NULL)));
media_view_assert(strpos($html, '<img ') === FALSE && strpos($html, 'Teks utama') !== FALSE, 'Text-only lesson must remain supported');

$admin = array('course' => $course, 'lessons' => array($lesson), 'lesson_images' => array(7 => $image), 'flashcard_decks' => array(), 'reviews' => array(), 'can_edit' => TRUE, 'can_review' => FALSE, 'current_user' => array('roles' => array('content_editor')));
$html = media_view_render('admin/course', $admin);
media_view_safe($html);
media_view_assert(strpos($html, '/kelola/kursus/4/pelajaran/7/gambar">Gambar pendamping') !== FALSE && strpos($html, '/gambar/pratinjau') !== FALSE, 'Editor course should expose management link and private image preview');
media_view_assert(strpos($html, 'Hak gambar:</strong> Memiliki lisensi') !== FALSE && strpos($html, 'Sumber:</strong> Sumber &lt;script&gt;') !== FALSE, 'Reviewer needs rights and source alongside the image');
$html = media_view_render('admin/course', array_replace($admin, array('can_edit' => FALSE, 'can_review' => TRUE)));
media_view_safe($html);
media_view_assert(strpos($html, '/kelola/kursus/4/pelajaran/7/gambar">Gambar pendamping') === FALSE && strpos($html, '/gambar/pratinjau') !== FALSE, 'Reviewer without edit rights must see preview but not management action');
restore_error_handler();
echo "PASS lesson media views: native multipart/revision/removal forms, unavailable/conflict states, escaped metadata and accessible errors, no private storage fields, correct learner/editor endpoints, reviewer context and text-only fallback. Stub form helpers; no HTTP upload or database claims.\n";
