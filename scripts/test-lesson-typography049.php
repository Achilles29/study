<?php
// Read-only renderer regression; run with the deployed PHP CLI.
define('BASEPATH', dirname(__DIR__).'/system/');
function config_item($key) { return $key === 'charset' ? 'UTF-8' : NULL; }
require BASEPATH.'core/Common.php';
require dirname(__DIR__).'/application/helpers/study_ui_helper.php';
function check049($condition, $label) { if (!$condition) { throw new RuntimeException($label); } }
$sample = require dirname(__DIR__).'/application/content_samples/math_grade1_047.php';
foreach ($sample['lessons'] as $lesson)
{
    $body = $lesson['body'];
    $html = study_render_lesson_text($body);
    preg_match_all('/^[A-Z]+(?: +[A-Z]+)+$/m', $body, $headings);
    check049(count($headings[0]) >= 7, 'Sample headings');
    check049(substr_count($html, '<h2>') === count($headings[0]), 'All sample headings formatted');
    foreach ($headings[0] as $heading) { check049(strpos($html, '<h2>'.$heading.'</h2>') !== FALSE, $heading); }
    $plain = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
    check049(preg_replace('/\s+/u', ' ', trim($plain)) === preg_replace('/\s+/u', ' ', trim($body)), 'All content preserved');
}
check049(study_render_lesson_text('') === '', 'Empty');
check049(study_render_lesson_text("SD\n1 + 1 = 2\n\nA B\nJawaban") === "<p>SD<br>\n1 + 1 = 2</p>\n<p>A B<br>\nJawaban</p>", 'No false math/acronym headings');
check049(study_render_lesson_text("## Bab 1\r\nIsi\r\n\r\n### Contoh\rA & B") === "<h2>Bab 1</h2>\n<p>Isi</p>\n<h3>Contoh</h3>\n<p>A &amp; B</p>", 'Explicit hierarchy and CRLF');
$unsafe = study_render_lesson_text("## <img src=x onerror=alert(1)>\n<script>alert(2)</script>\n\n### <svg onload=alert(3)>\n&\"'");
check049(strpos($unsafe, '<img') === FALSE && strpos($unsafe, '<script') === FALSE && strpos($unsafe, '<svg') === FALSE, 'HTML escaped in headings/body');
check049(strpos($unsafe, '&lt;script&gt;') !== FALSE && strpos($unsafe, '&amp;&quot;&#039;') !== FALSE, 'Escaping preserved');
echo "PASS six lessons: all headings/content preserved, explicit h2/h3, line endings, empty/plain/math text, XSS escaping.\n";
