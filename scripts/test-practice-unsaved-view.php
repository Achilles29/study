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
// Preserve supplied attributes so the test observes the view's actual opt-in.
// The synthetic CSRF field does not exercise CI's HTTP security middleware.
function form_open($path, $attributes = array())
{
	$html = '<form method="post" action="'.html_escape(site_url($path)).'"';
	foreach ($attributes as $name => $value) { $html .= ' '.html_escape($name).'="'.html_escape($value).'"'; }
	return $html.'><input type="hidden" name="study_csrf_token" value="synthetic-csrf">';
}
function form_close()
{
	return '</form>';
}
function practice_unsaved_assert($condition, $label)
{
	if ( ! $condition) { throw new RuntimeException($label); }
}
function practice_unsaved_render($data, $posted = array())
{
	$old_post = $_POST;
	$_POST = $posted;
	extract($data, EXTR_SKIP);
	ob_start();
	try
	{
		require FCPATH.'application/views/practice/attempt.php';
		return ob_get_contents();
	}
	finally
	{
		ob_end_clean();
		$_POST = $old_post;
	}
}
function practice_unsaved_attribute($tag, $name)
{
	preg_match('/\s'.preg_quote($name, '/').'="([^"]*)"/', $tag, $attribute);
	return isset($attribute[1]) ? html_entity_decode($attribute[1], ENT_QUOTES, 'UTF-8') : NULL;
}
function practice_unsaved_safe($html)
{
	practice_unsaved_assert( ! preg_match('/<(?:script|img|iframe)\b/i', $html), 'Supplied text must not inject executable markup');
	practice_unsaved_assert( ! preg_match('/<[^>]+\s(?:onclick|onerror|onload)=/i', $html), 'Rendered controls must not contain injected event handlers');
	practice_unsaved_assert(strpos($html, 'javascript:') === FALSE, 'Practice actions must remain native links/forms');
	preg_match_all('/(?:^|\s)id="([^"]+)"/', $html, $ids);
	practice_unsaved_assert(count($ids[1]) === count(array_unique($ids[1])), 'Practice HTML IDs must remain unique');
	foreach (array('PRIVATE_ANSWER_KEY', 'PRIVATE_OWNER_ID', 'PRIVATE_SNAPSHOT_JSON') as $private_value)
	{
		practice_unsaved_assert(strpos($html, $private_value) === FALSE, 'Unused private fixture metadata must not leak');
	}
}
function practice_unsaved_no_key($html)
{
	practice_unsaved_assert(strpos($html, 'EXPLANATION_ONLY_AFTER_GRADED') === FALSE && strpos($html, 'Jawaban benar') === FALSE, 'Unfinished or read-only ungraded attempts must not reveal explanations or correct answers');
	practice_unsaved_assert( ! preg_match('/\bdata-(?:correct|is-correct|answer-key)=|\bname="(?:is_correct|correct_option_id)"/i', $html), 'Correctness metadata must not be embedded in active controls');
}
function practice_unsaved_form($html, $revision)
{
	preg_match_all('/<form\b[^>]*>.*?<\/form>/s', $html, $forms);
	practice_unsaved_assert(count($forms[0]) === 1, 'An editable attempt must have exactly one native form');
	$form = $forms[0][0];
	preg_match('/<form\b[^>]*>/', $form, $opening);
	practice_unsaved_assert(practice_unsaved_attribute($opening[0], 'id') === 'practice-answer-form', 'Editable attempt must retain its stable form ID');
	practice_unsaved_assert(practice_unsaved_attribute($opening[0], 'method') === 'post' && practice_unsaved_attribute($opening[0], 'action') === site_url('latihan/percobaan/77'), 'Save/finish must share the existing native POST endpoint');
	practice_unsaved_assert(preg_match('/\sdata-unsaved-form(?:\s|=|>)/', $opening[0]) === 1, 'Only editable attempts must opt in to the existing unsaved guard');
	practice_unsaved_assert(practice_unsaved_attribute($opening[0], 'data-unsaved-initial') === 'false', 'Rendered server choices must begin clean, including 400/409 responses');
	practice_unsaved_assert(strpos($html, 'data-unsaved-initial="true"') === FALSE, 'Rejected posted choices must not become an initial-dirty snapshot');
	preg_match_all('/<[^>]+\sdata-unsaved-status(?:\s|=|>)[^>]*>/', $form, $statuses);
	practice_unsaved_assert(count($statuses[0]) === 1, 'The form must contain one unsaved status region');
	$status = $statuses[0][0];
	practice_unsaved_assert(practice_unsaved_attribute($status, 'role') === 'status' && practice_unsaved_attribute($status, 'aria-live') === 'polite', 'Unsaved status must use polite status semantics');
	practice_unsaved_assert(preg_match('/\shidden(?:\s|=|>)/', $status) === 1, 'Unsaved status must remain hidden before script enhancement');
	practice_unsaved_assert(strpos($form, 'Pilihan jawaban tidak tersimpan otomatis. Gunakan Simpan sementara sebelum beristirahat.') !== FALSE, 'The native form must explicitly explain manual temporary saving');
	preg_match_all('/<input\b[^>]*>/', $form, $inputs);
	$revisions = array();
	$selected = array();
	$radio_count = 0;
	foreach ($inputs[0] as $input)
	{
		$name = practice_unsaved_attribute($input, 'name');
		if ($name === 'revision')
		{
			practice_unsaved_assert(practice_unsaved_attribute($input, 'type') === 'hidden', 'Revision must remain a hidden server concurrency field');
			$revisions[] = practice_unsaved_attribute($input, 'value');
		}
		if (practice_unsaved_attribute($input, 'type') !== 'radio') { continue; }
		$radio_count++;
		practice_unsaved_assert(in_array($name, array('answers[101]', 'answers[102]'), TRUE), 'Answer groups must preserve the server question IDs');
		practice_unsaved_assert(preg_match('/<label\b[^>]*>\s*'.preg_quote($input, '/').'\s*<span>/s', $form) === 1, 'Each radio must retain a wrapping visible label');
		if (preg_match('/\schecked(?:\s|=|>)/', $input))
		{
			practice_unsaved_assert( ! array_key_exists($name, $selected), 'Each answer group must have at most one checked option');
			$selected[$name] = practice_unsaved_attribute($input, 'value');
		}
	}
	practice_unsaved_assert($revisions === array((string) $revision), 'Exactly one hidden revision must retain the current server value');
	practice_unsaved_assert($radio_count === 10 && $selected === array('answers[101]' => '1002', 'answers[102]' => ''), 'Saved answer and unanswered state must override any rejected POST choices');
	practice_unsaved_assert(strpos($form, 'name="study_csrf_token" value="synthetic-csrf"') !== FALSE, 'The native form helper must still be called');
	preg_match_all('/<button\b[^>]*>.*?<\/button>/s', $form, $buttons);
	$actions = array();
	foreach ($buttons[0] as $button)
	{
		practice_unsaved_assert(practice_unsaved_attribute($button, 'type') === 'submit' && practice_unsaved_attribute($button, 'name') === 'action', 'Save/finish must remain native named submit buttons');
		$actions[] = practice_unsaved_attribute($button, 'value');
	}
	practice_unsaved_assert($actions === array('save', 'finish'), 'Manual save and final submit must remain distinct native actions');
	practice_unsaved_assert(strpos($form, 'Simpan sementara') !== FALSE && strpos($form, 'Selesaikan dan lihat pembahasan') !== FALSE, 'Both actions need visible Indonesian labels');
	return $form;
}
function practice_unsaved_read_only($html)
{
	practice_unsaved_assert( ! preg_match('/<(?:form|input|button|textarea|select)\b/i', $html), 'Read-only attempt/report must not render mutation controls, including radios');
	practice_unsaved_assert(strpos($html, 'practice-answer-form') === FALSE && strpos($html, 'data-unsaved-') === FALSE, 'Read-only results must not opt in or leave an orphan unsaved marker');
	practice_unsaved_assert(strpos($html, 'Pilihan jawaban tidak tersimpan otomatis.') === FALSE, 'Read-only results must not claim that answers can be saved');
}

$questions = array();
foreach (array(101, 102) as $question_id)
{
	$options = array();
	for ($index = 1; $index <= 4; $index++)
	{
		$options[] = array('id' => ($question_id - 100) * 1000 + $index, 'option_text' => 'Pilihan '.$index.' <img src=x> "kutip" & 日本語', 'is_correct' => $index === 1 ? 1 : 0, 'correct_answer_id' => 'PRIVATE_ANSWER_KEY');
	}
	$questions[] = array('id' => $question_id, 'prompt' => "Soal $question_id <script>aman</script> & 日本語\nBaris kedua.", 'selected_option_id' => $question_id === 101 ? 1002 : NULL, 'options' => $options, 'is_correct' => 0, 'explanation' => 'EXPLANATION_ONLY_AFTER_GRADED <script>aman</script> "kutip" & 日本語', 'owner_user_id' => 'PRIVATE_OWNER_ID');
}
$base = array(
	'attempt' => array('id' => 77, 'status' => 'in_progress', 'title' => 'Latihan <script>aman</script> "kutip" & 日本語', 'answer_revision' => 17, 'course_id' => 4, 'session_type' => 'course_quiz', 'question_count' => 2, 'correct_count' => 0, 'score' => '0.00', 'snapshot_json' => 'PRIVATE_SNAPSHOT_JSON'),
	'questions' => $questions, 'submit_error' => NULL, 'can_retry' => TRUE
);
$rejected_post = array('answers' => array(101 => '1004', 102 => '2003'), 'revision' => '999', 'action' => 'finish');
$renders = 0;

foreach (array('course_quiz', 'parent_assigned') as $session_type)
{
	$data = $base;
	$data['attempt']['session_type'] = $session_type;
	if ($session_type === 'parent_assigned') { $data['attempt']['course_id'] = NULL; $data['can_retry'] = FALSE; }
	foreach (array(NULL, '400: Pilihan <script>ditolak</script> & jawaban tersimpan.', '409: Tab lain <script>lebih baru</script> & periksa jawaban.') as $submit_error)
	{
		$data['submit_error'] = $submit_error;
		$data['unsaved_initial'] = TRUE;
		$data['attempt']['answer_revision'] = strpos((string) $submit_error, '409:') === 0 ? 18 : 17;
		$html = practice_unsaved_render($data, $rejected_post);
		$renders++;
		practice_unsaved_safe($html);
		practice_unsaved_form($html, $data['attempt']['answer_revision']);
		practice_unsaved_no_key($html);
		practice_unsaved_assert(strpos($html, '<p class="eyebrow">'.($session_type === 'parent_assigned' ? 'Latihan keluarga' : 'Latihan mandiri').'</p>') !== FALSE, 'Active heading must distinguish family assignments from course practice');
		practice_unsaved_assert(strpos($html, html_escape($data['attempt']['title'])) !== FALSE, 'Attempt titles must preserve escaped Unicode');
		practice_unsaved_assert(strpos($html, html_escape(html_escape($data['attempt']['title']))) === FALSE, 'Attempt titles must not be double-escaped');
		foreach ($questions as $question)
		{
			practice_unsaved_assert(strpos($html, nl2br(html_escape($question['prompt']))) !== FALSE, 'Prompts must retain escaped text and line breaks');
			foreach ($question['options'] as $option) { practice_unsaved_assert(strpos($html, html_escape($option['option_text'])) !== FALSE, 'Visible options must remain safely escaped'); }
		}
		if ($submit_error !== NULL)
		{
			practice_unsaved_assert(preg_match('/<[^>]+role="alert"[^>]*>'.preg_quote(html_escape($submit_error), '/').'<\//', $html) === 1, '400/409 view data must display the escaped authoritative error as an alert');
		}
	}
}

// Also render the minimal public shape returned by display_questions before
// grading: no correctness/explanation fields are available for the view to read.
$minimal = $base;
foreach ($minimal['questions'] as &$question)
{
	unset($question['is_correct'], $question['explanation']);
	foreach ($question['options'] as &$option) { unset($option['is_correct'], $option['correct_answer_id']); }
	unset($option);
}
unset($question);
$html = practice_unsaved_render($minimal);
$renders++;
practice_unsaved_form($html, 17);
practice_unsaved_no_key($html);

foreach (array('graded', 'abandoned', 'submitted', 'IN_PROGRESS', 'unknown', '') as $status)
{
	foreach (array(FALSE, TRUE) as $family_report)
	{
		$data = $status === 'graded' ? $base : $minimal;
		$data['attempt']['status'] = $status;
		if ($family_report) { $data['report_child'] = array('id' => 9, 'display_name' => 'Anak <script>aman</script> & 日本語'); }
		$html = practice_unsaved_render($data, $rejected_post);
		$renders++;
		practice_unsaved_safe($html);
		practice_unsaved_read_only($html);
		if ($status === 'graded')
		{
			practice_unsaved_assert(strpos($html, 'Jawaban benar') !== FALSE && strpos($html, html_escape($questions[0]['explanation'])) !== FALSE, 'Graded results must retain escaped explanations and correct-answer badges');
			practice_unsaved_assert(strpos($html, $family_report ? 'Pilihan anak' : 'Pilihanmu') !== FALSE, 'Graded results must describe the saved selection in the right context');
		}
		else { practice_unsaved_no_key($html); }
		if ($family_report)
		{
			practice_unsaved_assert(strpos($html, html_escape($data['report_child']['display_name'])) !== FALSE && strpos($html, 'Laporan ini hanya untuk dibaca.') !== FALSE, 'Reports need escaped child context and read-only guidance');
			practice_unsaved_assert(strpos($html, 'href="'.site_url('keluarga/anak/9/latihan').'"') !== FALSE && strpos($html, 'Coba latihan lagi') === FALSE, 'Reports must return to the child history without a learner retry action');
		}
	}
}

// Even an accidentally supplied active report is display-only, never a child
// answer form. Rich extra metadata must not reveal the unfinished answer key.
$report = $base;
$report['attempt']['session_type'] = 'parent_assigned';
$report['attempt']['course_id'] = NULL;
$report['report_child'] = array('id' => 9, 'display_name' => 'Anak <script>aman</script> & 日本語');
$report['history_path_override'] = 'keluarga/latihan/31';
$html = practice_unsaved_render($report, $rejected_post);
$renders++;
practice_unsaved_safe($html);
practice_unsaved_read_only($html);
practice_unsaved_no_key($html);
practice_unsaved_assert(strpos($html, 'href="'.site_url('keluarga/latihan/31').'"') !== FALSE, 'Family-session report must preserve its existing local history destination');
practice_unsaved_assert(strpos($html, 'Coba latihan lagi') === FALSE, 'A malformed active report must not expose a learner retry action');

restore_error_handler();
fwrite(STDOUT, 'PASS '.$renders." practice view renders: active course/family native save/finish and clean unsaved opt-in, saved server choices/revision after error data, escaped text, no unfinished key leakage, graded/abandoned/unknown/report read-only states. Stub helpers; no HTTP status, JavaScript, database or browser claims.\n");
