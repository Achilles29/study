<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Learning_monitor_model extends CI_Model
{
	public function rows($sql, $bindings = array())
	{
		$result = $this->db->query($sql, $bindings);
		if ($result === FALSE) { throw new RuntimeException('Learning report unavailable.'); }
		return $result->result_array();
	}

	// Only reporting fields: never select question text, answers, or game snapshots.
	private function activity_sql()
	{
		return "SELECT 'materi' module, p.id, p.learner_user_id user_id, c.id course_id, c.title course_title,
			l.title title, IF(p.status='completed','completed','running') state, NULL score, NULL correct_count, NULL question_count,
			p.started_at started_at, p.completed_at finished_at, p.updated_at touched_at, p.lesson_id content_id
			FROM st_lesson_progress p JOIN st_lessons l ON l.id=p.lesson_id JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id
			UNION ALL
			SELECT 'latihan', a.id, a.learner_user_id, c.id, CASE s.session_type WHEN 'self_practice' THEN 'Latihan Mandiri (bank soal)' WHEN 'assessment' THEN 'Simulasi Ujian (bank soal)' ELSE c.title END,
			IF(s.session_type IN ('course_quiz','self_practice','assessment'),s.title,'Latihan keluarga privat'),
			CASE WHEN a.status='graded' THEN 'completed' WHEN a.status='abandoned' THEN 'abandoned' ELSE 'running' END,
			IF(a.status='graded',a.score,NULL), IF(a.status='graded',a.correct_count,NULL), a.question_count,
			a.started_at, a.submitted_at, COALESCE(a.submitted_at,a.started_at), s.id
			FROM st_practice_attempts a JOIN st_practice_sessions s ON s.id=a.session_id LEFT JOIN st_courses c ON c.id=s.course_id
			UNION ALL
			SELECT 'flashcard', p.id, p.learner_user_id, c.id, c.title, d.title,
			IF(p.status='known','completed','running'), NULL, NULL, NULL, NULL, NULL, p.last_reviewed_at, d.id
			FROM st_flashcard_progress p JOIN st_flashcards f ON f.id=p.flashcard_id JOIN st_flashcard_decks d ON d.id=f.deck_id
			JOIN st_lessons l ON l.id=d.lesson_id JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id
			UNION ALL
			SELECT 'permainan', r.id, r.learner_user_id, c.id, c.title, r.title_snapshot,
			CASE WHEN r.status='completed' THEN 'completed' WHEN r.status='abandoned' THEN 'abandoned' ELSE 'running' END,
			NULL, NULL, NULL, r.started_at, r.finished_at, COALESCE(r.finished_at,r.started_at), r.lesson_id
			FROM st_matching_runs r JOIN st_lessons l ON l.id=r.lesson_id JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id
			UNION ALL
			SELECT 'arena', r.id, r.learner_user_id, NULL, 'Arena Belajar (di luar kursus)', r.title_snapshot,
			r.status, IF(r.status='completed' AND r.game_type<>'memory',ROUND(r.correct_count*100/r.question_count),NULL),
			IF(r.status='completed' AND r.game_type<>'memory',r.correct_count,NULL), IF(r.game_type<>'memory',r.question_count,NULL),
			r.started_at,r.finished_at,r.updated_at,r.pack_id FROM st_arena_runs r WHERE r.game_type NOT IN ('flashcard','english')
			UNION ALL
			SELECT 'kartu_arena', p.id, p.learner_user_id, NULL, 'Arena Belajar (di luar kursus)', d.title,
			IF(p.status='known','completed','running'), NULL, NULL, NULL, NULL, NULL, p.updated_at, p.pack_id
			FROM st_arena_card_progress p JOIN st_arena_packs d ON d.id=p.pack_id AND d.content_version=p.content_version
			UNION ALL
			SELECT 'english_lesson', r.id, r.learner_user_id, NULL, 'Kelas Bahasa Inggris · Stage 1', r.title_snapshot,
			r.status,NULL,NULL,NULL,r.started_at,r.finished_at,r.updated_at,r.pack_id
			FROM st_arena_runs r JOIN (SELECT learner_user_id,pack_id,content_version,COALESCE(MAX(IF(status='completed',id,NULL)),MAX(id)) id FROM st_arena_runs WHERE game_type='english' AND JSON_UNQUOTE(JSON_EXTRACT(snapshot_json,'$.kind'))='lesson' GROUP BY learner_user_id,pack_id,content_version) latest ON latest.id=r.id
			JOIN st_arena_packs p ON p.id=r.pack_id AND p.content_version=r.content_version
			UNION ALL
			SELECT 'english_test',r.id,r.learner_user_id,NULL,'Kelas Bahasa Inggris · Stage 1',r.title_snapshot,r.status,
			IF(r.status='completed',ROUND(r.correct_count*100/r.question_count),NULL),IF(r.status='completed',r.correct_count,NULL),r.question_count,
			r.started_at,r.finished_at,r.updated_at,r.pack_id FROM st_arena_runs r WHERE r.game_type='english' AND JSON_UNQUOTE(JSON_EXTRACT(r.snapshot_json,'$.kind'))<>'lesson'
			UNION ALL
			SELECT 'battle',e.id,e.learner_user_id,NULL,IF(r.play_mode='live','Battle live (di luar kursus)','Battle asinkron (di luar kursus)'),r.title,
			CASE WHEN r.cancelled_at IS NOT NULL OR e.revoked_at IS NOT NULL THEN 'abandoned' WHEN r.ends_at<=UTC_TIMESTAMP() THEN 'completed' ELSE 'running' END,
			IF(r.cancelled_at IS NULL AND e.revoked_at IS NULL AND r.ends_at<=UTC_TIMESTAMP(),ROUND(e.correct_count*100/NULLIF(e.question_count,0)),NULL),
			IF(r.cancelled_at IS NULL AND e.revoked_at IS NULL AND r.ends_at<=UTC_TIMESTAMP(),e.correct_count,NULL),e.question_count,
			e.started_at,IF(r.ends_at<=UTC_TIMESTAMP(),COALESCE(e.finished_at,r.ends_at),NULL),GREATEST(e.updated_at,r.updated_at,IF(r.ends_at<=UTC_TIMESTAMP(),r.ends_at,e.updated_at)),r.id
			FROM st_battle_entries e JOIN st_battle_rooms r ON r.id=e.room_id";
	}

	private function scope($filters, &$bindings)
	{
		$sql = ' FROM ('.$this->activity_sql().') a WHERE a.touched_at >= ? AND a.touched_at < ?';
		$bindings = array($filters['from_utc'], $filters['until_utc']);
		foreach (array('modul' => 'module', 'status' => 'state', 'kursus' => 'course_id') as $key => $column)
		{
			if ($filters[$key] !== '') { $sql .= ' AND a.'.$column.' = ?'; $bindings[] = $filters[$key]; }
		}
		if ($filters['paket'] !== '')
		{
			$sql .= ' AND EXISTS (SELECT 1 FROM st_package_courses pc WHERE pc.course_id=a.course_id AND pc.package_id=?)';
			$bindings[] = $filters['paket'];
		}
		if ($filters['jenjang'] !== '')
		{
			$sql .= ' AND EXISTS (SELECT 1 FROM st_users learner WHERE learner.id=a.user_id AND learner.learning_stage=?)';
			$bindings[] = $filters['jenjang'];
		}
		if ( ! empty($filters['konten'])) { $sql .= ' AND a.content_id=?'; $bindings[] = $filters['konten']; }
		return $sql;
	}

	private function metrics()
	{
		return "COUNT(*) records, MAX(a.touched_at) last_activity,
			SUM(a.module='materi') reading_total, SUM(a.module='materi' AND a.state='completed') reading_done,
			SUM(a.module='latihan') quiz_total, SUM(a.module='latihan' AND a.state='completed') quiz_done,
			SUM(a.module IN ('flashcard','kartu_arena')) cards_total, SUM(a.module IN ('flashcard','kartu_arena') AND a.state='completed') cards_known,
			SUM(a.module IN ('permainan','arena','battle')) games_total, SUM(a.module IN ('permainan','arena','battle') AND a.state='completed') games_done,
			SUM(a.module='english_lesson') english_total,SUM(a.module='english_lesson' AND a.state='completed') english_done,
			SUM(a.module='english_test') english_tests,SUM(a.module='english_test' AND a.state='completed') english_graded,
			MAX(IF(a.module='latihan',a.score,NULL)) best_score, SUM(a.state='running') running";
	}

	private function users_scope($filters, $query, &$bindings)
	{
		$scope = $this->scope($filters, $bindings);
		$sql = ' FROM st_users u LEFT JOIN (SELECT a.user_id, '.$this->metrics().$scope.' GROUP BY a.user_id) m ON m.user_id=u.id WHERE 1=1';
		if ($filters['jenjang'] !== '') { $sql .= ' AND u.learning_stage=?'; $bindings[] = $filters['jenjang']; }
		if ($filters['aktivitas'] === 'ada') { $sql .= ' AND m.user_id IS NOT NULL'; }
		if ($filters['aktivitas'] === 'belum') { $sql .= ' AND m.user_id IS NULL'; }
		if ($query !== '')
		{
			$sql .= " AND (u.username LIKE ? ESCAPE '!' OR u.display_name LIKE ? ESCAPE '!' OR CAST(u.id AS CHAR)=?)";
			$literal = '%'.$this->db->escape_like_str($query).'%';
			$bindings = array_merge($bindings, array($literal, $literal, $query));
		}
		return $sql;
	}

	public function users($filters, $query, $page)
	{
		$sql = $this->users_scope($filters, $query, $bindings);
		$total = (int) $this->rows('SELECT COUNT(*) total'.$sql, $bindings)[0]['total'];
		$page_count = max(1, (int) ceil($total / 20)); $page = min($page_count, max(1, (int) $page));
		$order = $filters['urutan'] === 'nama' ? 'u.display_name, u.id' : 'm.last_activity DESC, u.id DESC';
		$users = $this->rows('SELECT u.id,u.username,u.display_name,u.account_kind,u.learning_stage,u.status,m.*'.$sql.' ORDER BY '.$order.' LIMIT 20 OFFSET '.(($page - 1) * 20), $bindings);
		return compact('users', 'total', 'page', 'page_count');
	}

	public function user($id)
	{
		return $this->rows('SELECT id,username,display_name,account_kind,learning_stage,status FROM st_users WHERE id=?', array($id))[0] ?? NULL;
	}

	public function summary($filters, $id = NULL)
	{
		$sql = $this->scope($filters, $bindings);
		if ($id !== NULL) { $sql .= ' AND a.user_id=?'; $bindings[] = $id; }
		$row = $this->rows('SELECT '.$this->metrics().', COUNT(DISTINCT a.user_id) learners'.$sql, $bindings)[0];
		$latest = $this->rows('SELECT a.score'.$sql." AND a.module='latihan' AND a.state='completed' ORDER BY a.finished_at DESC,a.id DESC LIMIT 1", $bindings);
		$row['latest_score'] = $latest[0]['score'] ?? NULL;
		return $row;
	}

	public function courses($filters, $id, $page = 1)
	{
		$sql = $this->scope($filters, $bindings); $bindings[] = $id;
		$course_total = (int) $this->rows('SELECT COUNT(*) total FROM (SELECT a.course_id,a.course_title'.$sql.' AND a.user_id=? GROUP BY a.course_id,a.course_title) grouped_courses', $bindings)[0]['total'];
		$course_page_count = max(1, (int) ceil($course_total / 20)); $course_page = min($course_page_count, max(1, (int) $page));
		$course_rows = $this->rows('SELECT a.course_id,a.course_title,'.$this->metrics().$sql.' AND a.user_id=? GROUP BY a.course_id,a.course_title ORDER BY last_activity DESC,a.course_id LIMIT 20 OFFSET '.(($course_page - 1) * 20), $bindings);
		return compact('course_rows', 'course_total', 'course_page', 'course_page_count');
	}

	public function activities($filters, $id, $page)
	{
		$sql = $this->scope($filters, $bindings); $sql .= ' AND a.user_id=?'; $bindings[] = $id;
		$total = (int) $this->rows('SELECT COUNT(*) total'.$sql, $bindings)[0]['total'];
		$page_count = max(1, (int) ceil($total / 20)); $page = min($page_count, max(1, (int) $page));
		$activities = $this->rows('SELECT a.*'.$sql.' ORDER BY a.touched_at DESC,a.module,a.id DESC LIMIT 20 OFFSET '.(($page - 1) * 20), $bindings);
		return compact('activities', 'total', 'page', 'page_count');
	}

	public function activity($module, $id)
	{
		return $this->rows('SELECT a.*,u.display_name FROM ('.$this->activity_sql().') a JOIN st_users u ON u.id=a.user_id WHERE a.module=? AND a.id=?', array($module, $id))[0] ?? NULL;
	}

	public function options()
	{
		return array(
			'courses' => $this->rows('SELECT id,title FROM st_courses ORDER BY title,id LIMIT 500'),
			'packages' => $this->rows('SELECT id,title FROM st_packages ORDER BY title,id LIMIT 500')
		);
	}

	public function contents($filters, $page)
	{
		$sql = $this->scope($filters, $bindings);
		$total = (int) $this->rows('SELECT COUNT(*) total FROM (SELECT a.module,a.content_id'.$sql.' GROUP BY a.module,a.content_id) content_groups', $bindings)[0]['total'];
		$page_count = max(1, (int) ceil($total / 20)); $page = min($page_count, max(1, (int) $page));
		$contents = $this->rows('SELECT a.module,a.content_id,MAX(a.title) title,MAX(a.course_title) course_title,COUNT(DISTINCT a.user_id) learners,'.$this->metrics().$sql.' GROUP BY a.module,a.content_id ORDER BY last_activity DESC,a.module,a.content_id LIMIT 20 OFFSET '.(($page - 1) * 20), $bindings);
		return compact('contents', 'total', 'page', 'page_count');
	}

	public function entitlements($id, $page)
	{
		$this->load->model('Lifetime_model');$lifetime_scope=$this->Lifetime_model->predicate($id);
		$scope = "SELECT e.id,e.starts_at,e.ends_at,p.title,CASE WHEN e.status='revoked' THEN 'Dicabut' WHEN e.status='expired' OR e.ends_at<=UTC_TIMESTAMP() THEN 'Berakhir' WHEN e.starts_at>UTC_TIMESTAMP() THEN 'Belum mulai' ELSE 'Aktif' END access_status FROM st_entitlements e JOIN st_packages p ON p.id=e.package_id WHERE e.user_id=? UNION ALL SELECT g.id,g.starts_at,g.ends_at,CONCAT(IF(g.environment='production','Live · ','Sandbox · '),COALESCE(p.title,IF(g.is_lifetime=1,'Platinum Selamanya · 2 anak','Platinum keluarga'))),CASE WHEN g.status='revoked' THEN 'Dicabut' WHEN g.ends_at<=UTC_TIMESTAMP() THEN 'Berakhir' WHEN g.starts_at>UTC_TIMESTAMP() THEN 'Belum mulai' ELSE IF(g.environment='production','Aktif','Aktif (uji — tidak berlaku di live)') END FROM st_commerce_grants g LEFT JOIN st_packages p ON p.id=g.package_id WHERE g.learner_user_id=? OR (g.kind='platinum' AND (g.is_lifetime=0 OR ($lifetime_scope)) AND EXISTS(SELECT 1 FROM st_family_links l JOIN st_users u ON u.id=l.parent_user_id WHERE l.parent_user_id=g.owner_user_id AND l.child_user_id=? AND l.status='active' AND u.status='active' AND u.account_kind='parent'))";
		$scope .= " UNION ALL SELECT pr.id,pr.starts_at,pr.ends_at,CONCAT('Poin · ',JSON_UNQUOTE(JSON_EXTRACT(pr.snapshot_json,'$.title'))),CASE WHEN pr.status='revoked' THEN 'Dicabut' WHEN pr.ends_at<=UTC_TIMESTAMP() THEN 'Berakhir' WHEN pr.starts_at>UTC_TIMESTAMP() THEN 'Belum mulai' ELSE 'Aktif' END FROM st_point_redemptions pr WHERE pr.learner_user_id=?";
		$total = (int) $this->rows('SELECT COUNT(*) total FROM ('.$scope.') grants', array($id,$id,$id,$id))[0]['total'];
		$page_count = max(1, (int) ceil($total / 20)); $page = min($page_count, max(1, (int) $page));
		$access = $this->rows('SELECT * FROM ('.$scope.') grants ORDER BY starts_at DESC,id DESC LIMIT 20 OFFSET '.(($page - 1) * 20), array($id,$id,$id,$id));
		return compact('access', 'total', 'page', 'page_count');
	}
}
