<?php
/* Publish only imported sessions with a complete, playable question pool. */
if (PHP_SAPI !== 'cli' || getenv('STUDY_PUSTAKA_PUBLISH') !== '1') exit(2);
$apply = in_array('--apply', $argv, TRUE);
$qa = in_array('--qa', $argv, TRUE);
if ($qa) {
 $db = new mysqli('localhost', 'root', '', 'study_qa_monitor_sso039', 3308, '/tmp/mysql-study.sock');
} else {
 $client = parse_ini_file('/root/security-remediation-20261022/main-mysql-current.cnf', TRUE, INI_SCANNER_RAW)['client'];
 $db = new mysqli('localhost', $client['user'], trim($client['password'], '"'), 'db_study');
}
$db->set_charset('utf8mb4');
$db->query("SET time_zone = '+00:00'");
function run_sql(mysqli $db, string $sql): mysqli_result|bool {
 $result = $db->query($sql);
 if ($result === FALSE) throw new RuntimeException($db->error);
 return $result;
}
$records = run_sql($db, "SELECT p.source_session_id,e.id,e.status,e.draw_count,p.issue,
 (SELECT COUNT(*) FROM st_exam_questions x WHERE x.exam_id=e.id) AS selected_count
 FROM st_pustaka_import_sessions p JOIN st_exam_templates e ON e.id=p.exam_id ORDER BY e.id")->fetch_all(MYSQLI_ASSOC);
$ready = [];
$blocked = [];
foreach ($records as $record) {
 $id = (int)$record['id'];
 $count = (int)$record['selected_count'];
 if ($record['issue'] !== NULL || $count < (int)$record['draw_count'] || !$count) {
  $blocked[$id] = $record['issue'] ?: 'Kumpulan soal belum cukup';
  continue;
 }
 $invalid = run_sql($db, "SELECT x.question_id FROM st_exam_questions x
 JOIN st_questions q ON q.id=x.question_id JOIN st_exam_templates e ON e.id=x.exam_id
 LEFT JOIN (SELECT question_id,COUNT(*) n,SUM(is_correct=1) correct FROM st_question_options GROUP BY question_id) o ON o.question_id=q.id
 WHERE x.exam_id=$id AND (q.status NOT IN ('draft','published') OR o.n<>4 OR o.correct<>1
 OR NOT EXISTS(SELECT 1 FROM st_bank_scopes s WHERE s.question_id=q.id AND s.grade_id=e.grade_id)
 OR (e.question_difficulty IS NOT NULL AND (SELECT b.question_difficulty FROM st_bank_questions b WHERE b.question_id=q.id)<>e.question_difficulty))
 LIMIT 1")->num_rows;
 if ($invalid) $blocked[$id] = 'Ada soal yang belum siap atau tidak sesuai kelas/tag';
 else $ready[] = $id;
}
$ids = implode(',', $ready);
$questionIds = $ids === '' ? [] : array_map('intval', array_column(run_sql($db, "SELECT DISTINCT x.question_id FROM st_exam_questions x WHERE x.exam_id IN ($ids)")->fetch_all(MYSQLI_ASSOC), 'question_id'));
$questionList = implode(',', $questionIds);
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$start = $now->format('Y-m-d H:i:s');
$end = $now->modify('+1 year')->format('Y-m-d H:i:s');
$summary = ['sessions'=>count($records),'ready'=>count($ready),'held'=>count($blocked),'questions_in_ready'=>count($questionIds),'schedule_from_utc'=>$start,'schedule_until_utc'=>$end];
echo json_encode($summary, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
if (!$apply) exit(0);
$db->begin_transaction();
try {
 run_sql($db, 'SELECT id FROM st_bank_guard WHERE id=1 FOR UPDATE');
 // Audit the exact questions affected before changing them.
 if ($questionList !== '') {
  run_sql($db, "INSERT INTO st_audit_events(actor_user_id,event_type,entity_type,entity_id,details_json,created_at)
  SELECT 1,'bank.question_published_import','bank_question',q.id,JSON_OBJECT('source','pustaka','rights_status',q.rights_status),UTC_TIMESTAMP()
  FROM st_questions q WHERE q.id IN ($questionList) AND q.status='draft'");
  run_sql($db, "UPDATE st_questions SET status='published',content_version=content_version+1,updated_at=UTC_TIMESTAMP()
  WHERE id IN ($questionList) AND status='draft'");
 }
 foreach ($records as $record) {
  $id = (int)$record['id'];
  if ($record['status'] !== 'draft') continue;
  $status = in_array($id, $ready, TRUE) ? 'published' : 'draft';
  $before = run_sql($db, "SELECT status,parent_live_enabled,sheet_rules_json,available_from,available_until FROM st_exam_templates WHERE id=$id FOR UPDATE")->fetch_assoc();
  $sheet = json_decode($before['sheet_rules_json'] ?: '{}', TRUE);
  if (!is_array($sheet)) throw new RuntimeException('Invalid sheet rules for exam '.$id);
  if ($status === 'draft' && (int)$before['parent_live_enabled'] === 1 && ($sheet['view_mode'] ?? '') === 'ledger' && $before['available_from'] && $before['available_until']) continue;
  $sheet['view_mode'] = 'ledger';
  $sheetJson = $db->real_escape_string(json_encode($sheet, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
  $from = $before['available_from'] ?: $start;
  $until = $before['available_until'] ?: $end;
  $fromSql = $db->real_escape_string($from);
  $untilSql = $db->real_escape_string($until);
  run_sql($db, "UPDATE st_exam_templates SET status='$status',parent_live_enabled=1,sheet_rules_json='$sheetJson',
  available_from='$fromSql',available_until='$untilSql',revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE id=$id AND status='draft'");
  $details = $db->real_escape_string(json_encode(['source'=>'pustaka','source_session_id'=>(int)$record['source_session_id'],'status'=>$status,'parent_live_enabled'=>1,'view_mode'=>'ledger','available_from'=>$from,'available_until'=>$until,'selected_count'=>(int)$record['selected_count']], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
  run_sql($db, "INSERT INTO st_audit_events(actor_user_id,event_type,entity_type,entity_id,details_json,created_at)
  VALUES(1,'exam.pustaka_configured','exam_template',$id,'$details',UTC_TIMESTAMP())");
 }
 $db->commit();
 echo "APPLIED\n";
} catch (Throwable $error) {
 $db->rollback();
 fwrite(STDERR, $error->getMessage().PHP_EOL);
 exit(1);
}
