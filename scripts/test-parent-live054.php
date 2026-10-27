<?php
if (PHP_SAPI !== 'cli' || getenv('DB_DATABASE') !== 'study_qa_monitor_sso039') exit(2);
$_SERVER['argv'] = array('index.php', 'cli/migrate');
ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean();
$ci =& get_instance();
$ci->load->model(array('Settings_model', 'Practice_live_model', 'Practice_model', 'Family_practice_model'));
$db = $ci->db;
$db->db_debug = FALSE;
function check054($condition, $message) { if (!$condition) throw new RuntimeException($message); }

$db->trans_begin();
try {
	$attempt = $db->query("SELECT a.*,s.session_type,s.course_id,s.title,s.rules_version FROM st_practice_attempts a JOIN st_practice_sessions s ON s.id=a.session_id JOIN st_session_questions sq ON sq.session_id=s.id JOIN st_family_links l ON l.child_user_id=a.learner_user_id AND l.status='active' WHERE s.session_type='course_quiz' GROUP BY a.id LIMIT 1")->row_array();
	check054((bool)$attempt, 'Course practice fixture missing');
	$db->where('id', $attempt['id'])->update('st_practice_attempts', array('status'=>'in_progress'));
	$attempt['status'] = 'in_progress';
	$setting = $db->where('section', 'latihan_mandiri')->get('st_site_settings')->row_array();
	check054(!$ci->Practice_live_model->enabled_for_attempt($attempt), 'Monitoring should default off');
	$values = json_decode($setting['values_json'], TRUE);
	$values['parent_live_enabled'] = '1';
	$db->where('id', $setting['id'])->update('st_site_settings', array('values_json'=>json_encode($values)));
	check054($ci->Practice_live_model->enabled_for_attempt($attempt), 'Global switch should enable course practice');
	$link = $db->where('child_user_id', $attempt['learner_user_id'])->where('status', 'active')->get('st_family_links')->row_array();
	check054((bool)$link, 'Linked parent fixture missing');
	check054((bool)$ci->Family_practice_model->live_attempt($link['parent_user_id'], $attempt['learner_user_id'], $attempt['id']), 'Linked parent should find active attempt');
	check054(!$ci->Family_practice_model->live_attempt(999999999, $attempt['learner_user_id'], $attempt['id']), 'Unlinked parent must be denied');
	$questions = $ci->Practice_model->display_questions($attempt);
	check054((bool)$questions && !isset($questions[0]['explanation']) && !isset($questions[0]['options'][0]['is_correct']), 'Active question must not expose answer key: '.json_encode(array('status'=>$attempt['status'], 'question_keys'=>array_keys($questions[0] ?? array()), 'option_keys'=>array_keys($questions[0]['options'][0] ?? array()))));
	$q = $questions[0];
	$answer_map = $ci->Practice_live_model->answers($attempt);
	check054(is_array($answer_map) && array_key_exists((int)$q['id'], $answer_map), 'Live status must contain only saved selections');
	check054(!$ci->Practice_live_model->mark_seen($attempt, 999999999), 'Unknown question must be denied');
	check054($ci->Practice_live_model->mark_seen($attempt, $q['id']), 'Valid presence must save');
	$db->where('attempt_id', $attempt['id'])->update('st_practice_live_state', array('opened_at'=>gmdate('Y-m-d H:i:s', time()-4)));
	$selected = NULL;
	foreach ($q['options'] as $option) if ((int)$option['id'] !== (int)$q['selected_option_id']) { $selected = (string)$option['id']; break; }
	check054($selected !== NULL, 'Alternative option missing');
	$ci->Practice_live_model->record_changes($attempt, array($q['id']=>$selected), gmdate('Y-m-d H:i:s'));
	$timing = $ci->Practice_live_model->answer_timings($attempt['id'])[$q['id']] ?? NULL;
	check054($timing && $timing['first_ms'] >= 3000 && $timing['first_ms'] <= 6000, 'First answer timing unavailable');
	$exam = $db->query("SELECT a.*,s.session_type,s.course_id,s.title,s.rules_version,d.exam_id FROM st_practice_attempts a JOIN st_practice_sessions s ON s.id=a.session_id JOIN st_drill_sessions d ON d.session_id=s.id WHERE s.session_type='assessment' AND d.exam_id IS NOT NULL LIMIT 1")->row_array();
	check054((bool)$exam, 'Exam fixture missing');
	$exam['status'] = 'in_progress';
	check054(!$ci->Practice_live_model->enabled_for_attempt($exam), 'Exam monitoring should default off');
	$db->where('id', $exam['exam_id'])->update('st_exam_templates', array('parent_live_enabled'=>1));
	check054($ci->Practice_live_model->enabled_for_attempt($exam), 'Exam-specific switch should permit monitoring');
	$values['parent_live_enabled'] = '0';
	$db->where('id', $setting['id'])->update('st_site_settings', array('values_json'=>json_encode($values)));
	check054(!$ci->Practice_live_model->enabled_for_attempt($exam), 'Global off should revoke exam monitoring');
	check054(!$ci->Practice_live_model->mark_seen($attempt, $q['id']), 'Global off should reject presence updates');
} finally { $db->trans_rollback(); }
echo "PASS 054: global and exam switches, linked parent scope, no active answer key, presence, first-answer timing.\n";
