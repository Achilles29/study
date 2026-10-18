<?php
// Integration fixture/test for an EMPTY, explicitly named disposable database only.
if (PHP_SAPI !== 'cli' || ! preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', getenv('STUDY_MONITOR_TEST_DB') ?: '')) { exit("Set STUDY_MONITOR_TEST_DB to a disposable study_qa_monitor_* database.\n"); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$database = getenv('STUDY_MONITOR_TEST_DB');
$connection = new mysqli('localhost', 'root', '', $database, 3308, '/tmp/mysql-study.sock');
$connection->set_charset('utf8mb4');
if ( ! in_array('--verify-only', $argv, TRUE))
{
	if ((int) $connection->query('SELECT COUNT(*) FROM st_users')->fetch_row()[0] !== 0) { exit("Refusing to seed a nonempty database.\n"); }
	$password = password_hash('Monitor-QA-Only-2026!', PASSWORD_DEFAULT);
	$insert = function ($table, $data) use ($connection) {
		$keys = array_keys($data);
		$statement = $connection->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`', $keys).'`) VALUES ('.implode(',', array_fill(0, count($keys), '?')).')');
		$statement->execute(array_values($data));
	};
	$now = '2026-10-15 18:00:00';
	for ($id = 1; $id <= 25; $id++)
	{
		$insert('st_users', array('id' => $id, 'username' => 'monitor'.$id, 'password_hash' => $password, 'display_name' => $id === 3 ? 'Anak <uji> 100%' : 'Pengguna uji '.$id, 'account_kind' => $id === 2 ? 'parent' : (in_array($id, array(3,4), TRUE) ? 'child' : 'adult'), 'learning_stage' => $id === 3 ? 'primary' : ($id === 4 ? 'secondary' : 'adult'), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now));
	}
	$insert('st_roles', array('id' => 1, 'role_code' => 'system_admin', 'role_name' => 'Superadmin', 'created_at' => $now));
	$insert('st_roles', array('id' => 2, 'role_code' => 'content_editor', 'role_name' => 'Editor', 'created_at' => $now));
	$insert('st_user_roles', array('user_id' => 1, 'role_id' => 1, 'created_at' => $now));
	$insert('st_user_roles', array('user_id' => 6, 'role_id' => 2, 'created_at' => $now));
	$insert('st_family_links', array('parent_user_id' => 2, 'child_user_id' => 3, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now));
	$insert('st_courses', array('id' => 1, 'title' => 'Kursus QA', 'slug' => 'kursus-qa', 'summary' => 'Kursus sintetis untuk tes.', 'author_user_id' => 1, 'status' => 'published', 'is_free' => 1, 'created_at' => $now, 'updated_at' => $now));
	$insert('st_courses', array('id' => 2, 'title' => 'DRAF RAHASIA QA', 'slug' => 'draft-secret-qa', 'author_user_id' => 1, 'status' => 'draft', 'created_at' => $now, 'updated_at' => $now));
	$insert('st_course_sections', array('id' => 1, 'course_id' => 1, 'title' => 'Bagian QA', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now));
	foreach (array(1,2) as $id) { $insert('st_lessons', array('id' => $id, 'section_id' => 1, 'title' => 'Materi QA '.$id, 'slug' => 'materi-qa-'.$id, 'status' => 'published', 'sort_order' => $id, 'created_at' => $now, 'updated_at' => $now)); }
	$insert('st_lesson_progress', array('id' => 1, 'learner_user_id' => 3, 'lesson_id' => 1, 'status' => 'completed', 'started_at' => $now, 'completed_at' => '2026-10-15 17:00:00', 'updated_at' => '2026-10-15 17:00:00'));
	$insert('st_lesson_progress', array('id' => 2, 'learner_user_id' => 3, 'lesson_id' => 2, 'status' => 'in_progress', 'started_at' => '2026-10-15 16:00:00', 'updated_at' => '2026-10-15 16:59:59'));
	$insert('st_lesson_progress', array('id' => 3, 'learner_user_id' => 4, 'lesson_id' => 1, 'status' => 'completed', 'started_at' => $now, 'updated_at' => $now));
	$insert('st_flashcard_decks', array('id' => 1, 'lesson_id' => 1, 'title' => 'Kartu QA', 'created_by_user_id' => 1, 'created_at' => $now, 'updated_at' => $now));
	foreach (array(1,2) as $id)
	{
		$insert('st_flashcards', array('id' => $id, 'deck_id' => 1, 'front_text' => 'PRIVATE QUESTION', 'back_text' => 'PRIVATE ANSWER', 'sort_order' => $id, 'created_at' => $now, 'updated_at' => $now));
		$insert('st_flashcard_progress', array('learner_user_id' => 3, 'flashcard_id' => $id, 'status' => $id === 1 ? 'known' : 'learning', 'review_count' => 1, 'last_reviewed_at' => $now));
	}
	foreach (array(1,2,3) as $id)
	{
		$insert('st_practice_sessions', array('id' => $id, 'created_by_user_id' => 2, 'learner_user_id' => 3, 'title' => $id === 2 ? 'PRIVATE FAMILY TITLE' : 'Kuis QA '.$id, 'session_type' => $id === 2 ? 'parent_assigned' : 'course_quiz', 'course_id' => $id === 2 ? NULL : 1, 'created_at' => $now, 'updated_at' => $now));
		$insert('st_practice_attempts', array('id' => $id, 'session_id' => $id, 'learner_user_id' => 3, 'status' => $id === 3 ? 'in_progress' : 'graded', 'score' => $id === 1 ? 80 : ($id === 2 ? 60 : 99), 'question_count' => 5, 'correct_count' => $id === 1 ? 4 : 3, 'started_at' => $now, 'submitted_at' => $id === 3 ? NULL : '2026-10-15 19:00:0'.$id));
	}
	$insert('st_matching_runs', array('id' => 1, 'learner_user_id' => 3, 'lesson_id' => 1, 'title_snapshot' => 'Permainan QA', 'rules_version' => 'qa', 'snapshot_json' => '{"secret":"PRIVATE ANSWER"}', 'state_json' => '{}', 'status' => 'completed', 'start_key' => str_repeat('a',64), 'started_at' => $now, 'finished_at' => $now));

}
define('BASEPATH', dirname(__DIR__).'/system/');
class CI_Model
{
	public $db;
}
class Monitor_test_result
{
	private $result;
	public function __construct($result)
	{
		$this->result = $result; }
	public function result_array()
	{
		return $this->result->fetch_all(MYSQLI_ASSOC); }
}
class Monitor_test_database
{
	public function query($sql, $bindings)
	{
		global $connection;
		$statement = $connection->prepare($sql); $statement->execute($bindings);
		return new Monitor_test_result($statement->get_result());
	}
	public function escape_like_str($text)
	{
		return str_replace(array('!','%','_'), array('!!','!%','!_'), $text); }
}
require dirname(__DIR__).'/application/models/Learning_monitor_model.php';
$model = new Learning_monitor_model(); $model->db = new Monitor_test_database();
$filters = array('from_utc' => '2026-10-15 17:00:00', 'until_utc' => '2026-10-16 17:00:00', 'modul' => '', 'status' => '', 'kursus' => '', 'paket' => '', 'jenjang' => '', 'aktivitas' => '', 'urutan' => 'terbaru');
function check($condition, $message)
{
	if ( ! $condition) { throw new RuntimeException($message); } }
$summary = $model->summary($filters, 3);
check((int) $summary['reading_done'] === 1 && (int) $summary['reading_total'] === 1, 'WIB boundary / reading count');
check((int) $summary['quiz_done'] === 2 && (int) $summary['quiz_total'] === 3, 'Quiz status counts');
check((float) $summary['best_score'] === 80.0 && (float) $summary['latest_score'] === 60.0, 'Latest/best graded scores');
check((int) $summary['cards_known'] === 1 && (int) $summary['cards_total'] === 2 && (int) $summary['games_done'] === 1, 'Cards and games');
$page = $model->users($filters, '', 999); check($page['total'] === 25 && $page['page'] === 2 && count($page['users']) === 5, 'Pagination clamp');
check($model->users($filters, '%', 1)['total'] === 1, 'Literal percent search');
$selected = $filters; $selected['jenjang'] = 'primary'; check((int) $model->summary($selected)['learners'] === 1, 'Stage filtering');
$selected = $filters; $selected['kursus'] = '1'; check((int) $model->summary($selected, 3)['quiz_done'] === 1, 'Course excludes family-only practice');
$selected = $filters; $selected['paket'] = '999'; check((int) $model->summary($selected)['records'] === 0, 'Unknown package gives zero');
$private = $model->activity('latihan', 2); check($private['title'] === 'Latihan keluarga privat' && ! str_contains(json_encode($private), 'PRIVATE'), 'Private title omitted');
check($model->activity('latihan', 3)['score'] === NULL, 'Active score hidden');
check( ! str_contains(json_encode($model->activities($filters, 3, 1)), 'PRIVATE'), 'No questions/answers/snapshots selected');
$contents = $model->contents($filters, 1); check($contents['total'] === 6, 'Content/session grouping');
$selected = $filters; $selected['modul'] = 'materi'; $selected['konten'] = '1'; $selected['aktivitas'] = 'ada';
check($model->users($selected, '', 1)['total'] === 2, 'Content participant scope');
$courses = $model->courses($filters, 3, 999); check($courses['course_total'] === 2 && $courses['course_page'] === 1, 'Course summary pagination');
class Monitor_failure_database
{
	public function query($sql, $bindings)
	{
		return FALSE;
	}
}
$broken = new Learning_monitor_model(); $broken->db = new Monitor_failure_database();
$failed = FALSE; try { $broken->summary($filters, 3); } catch (RuntimeException $error) { $failed = TRUE; }
check($failed, 'A database failure must not become empty successful results');
echo "PASS isolated model: counts, WIB boundaries, status, latest/best, course/package/stage/content, pagination, literal search, private fields and failure handling.\n";
