<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Telegram_bot_query_model extends CI_Model
{
	private function catalog()
	{
		$staff = "EXISTS (SELECT 1 FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.role_code IN ('system_admin','content_editor','finance_operator','support_agent'))";
		return array(
			'users' => array('from' => 'st_users u', 'fields' => array('id' => array('u.id','number'), 'account_kind' => array('u.account_kind','text'), 'status' => array('u.status','text'), 'learning_stage' => array('u.learning_stage','text'), 'grade_id' => array('u.grade_id','number'), 'created_at' => array('u.created_at','date'), 'last_login_at' => array('u.last_login_at','date'), 'is_staff' => array("IF($staff,1,0)",'number'), 'is_member' => array("IF(u.account_kind IN ('adult','parent') AND NOT $staff,1,0)",'number'), 'member_name' => array("IF(u.account_kind IN ('adult','parent') AND NOT $staff,u.display_name,NULL)",'text'))),
			'members' => array('from' => 'st_users u', 'where' => "u.account_kind IN ('adult','parent') AND NOT $staff", 'fields' => array('id' => array('u.id','number'), 'name' => array('u.display_name','text'), 'status' => array('u.status','text'), 'learning_stage' => array('u.learning_stage','text'), 'created_at' => array('u.created_at','date'), 'last_login_at' => array('u.last_login_at','date'))),
			'children' => array('from' => 'st_users u', 'where' => "u.account_kind='child'", 'fields' => array('id' => array('u.id','number'), 'name' => array('u.display_name','text'), 'parent_name' => array("(SELECT p.display_name FROM st_family_links fl JOIN st_users p ON p.id=fl.parent_user_id WHERE fl.child_user_id=u.id AND fl.status='active' ORDER BY fl.id LIMIT 1)",'text'), 'status' => array('u.status','text'), 'learning_stage' => array('u.learning_stage','text'), 'grade_id' => array('u.grade_id','number'), 'created_at' => array('u.created_at','date'), 'last_login_at' => array('u.last_login_at','date'))),
			'family_links' => array('from' => 'st_family_links t', 'fields' => array('status' => array('t.status','text'), 'created_at' => array('t.created_at','date'))),
			'courses' => array('from' => 'st_courses t', 'fields' => array('id' => array('t.id','number'), 'title' => array('t.title','text'), 'status' => array('t.status','text'), 'target_group' => array('t.target_group','text'), 'grade_id' => array('t.grade_id','number'), 'category_id' => array('t.category_id','number'), 'is_free' => array('t.is_free','number'), 'created_at' => array('t.created_at','date'), 'published_at' => array('t.published_at','date'))),
			'lessons' => array('from' => 'st_lessons t', 'fields' => array('id' => array('t.id','number'), 'title' => array('t.title','text'), 'status' => array('t.status','text'), 'created_at' => array('t.created_at','date'))),
			'questions' => array('from' => 'st_questions t', 'fields' => array('id' => array('t.id','number'), 'course_id' => array('t.course_id','number'), 'status' => array('t.status','text'), 'question_type' => array('t.question_type','text'), 'visibility' => array('t.visibility','text'), 'created_at' => array('t.created_at','date'))),
			'exams' => array('from' => 'st_exam_templates t', 'fields' => array('id' => array('t.id','number'), 'title' => array('t.title','text'), 'status' => array('t.status','text'), 'grade_id' => array('t.grade_id','number'), 'duration_minutes' => array('t.duration_minutes','number'), 'credit_price' => array('t.credit_price','number'), 'parent_live_enabled' => array('t.parent_live_enabled','number'), 'created_at' => array('t.created_at','date'))),
			'practice_sessions' => array('from' => 'st_practice_sessions t', 'fields' => array('id' => array('t.id','number'), 'title' => array('t.title','text'), 'status' => array('t.status','text'), 'session_type' => array('t.session_type','text'), 'course_id' => array('t.course_id','number'), 'created_at' => array('t.created_at','date'))),
			'practice_attempts' => array('from' => 'st_practice_attempts t JOIN st_practice_sessions s ON s.id=t.session_id', 'fields' => array('id' => array('t.id','number'), 'learner_user_id' => array('t.learner_user_id','number'), 'session_type' => array('s.session_type','text'), 'course_id' => array('s.course_id','number'), 'status' => array('t.status','text'), 'score' => array('t.score','number'), 'question_count' => array('t.question_count','number'), 'correct_count' => array('t.correct_count','number'), 'started_at' => array('t.started_at','date'), 'submitted_at' => array('t.submitted_at','date'))),
			'practice_answer_events' => array('from' => 'st_practice_answer_events t JOIN st_practice_attempts a ON a.id=t.attempt_id', 'fields' => array('learner_user_id' => array('a.learner_user_id','number'), 'attempt_id' => array('t.attempt_id','number'), 'elapsed_ms' => array('t.elapsed_ms','number'), 'created_at' => array('t.created_at','date'))),
			'enrollments' => array('from' => 'st_course_enrollments t', 'fields' => array('course_id' => array('t.course_id','number'), 'status' => array('t.status','text'), 'enrolled_at' => array('t.enrolled_at','date'))),
			'lesson_progress' => array('from' => 'st_lesson_progress t', 'fields' => array('learner_user_id' => array('t.learner_user_id','number'), 'status' => array('t.status','text'), 'started_at' => array('t.started_at','date'), 'completed_at' => array('t.completed_at','date'))),
			'payments' => array('from' => "st_payment_orders t", 'where' => "t.environment='production'", 'fields' => array('id' => array('t.id','number'), 'order_code' => array('t.order_code','text'), 'purpose' => array('t.purpose','text'), 'status' => array('t.status','text'), 'amount_idr' => array('t.amount_idr','number'), 'created_at' => array('t.created_at','date'), 'paid_at' => array('t.paid_at','date'))),
			'offers' => array('from' => 'st_commerce_offers t', 'fields' => array('id' => array('t.id','number'), 'title' => array('t.title','text'), 'kind' => array('t.kind','text'), 'status' => array('t.status','text'), 'price' => array('t.price','number'), 'created_at' => array('t.created_at','date'))),
			'packages' => array('from' => 'st_packages t', 'fields' => array('id' => array('t.id','number'), 'title' => array('t.title','text'), 'status' => array('t.status','text'), 'credit_price' => array('t.credit_price','number'), 'point_price' => array('t.point_price','number'), 'created_at' => array('t.created_at','date'))),
			'help_tickets' => array('from' => 'st_help_tickets t', 'fields' => array('id' => array('t.id','number'), 'category' => array('t.category','text'), 'status' => array('t.status','text'), 'created_at' => array('t.created_at','date'), 'updated_at' => array('t.updated_at','date'))),
			'reviews' => array('from' => 'st_reviews t', 'where' => 't.is_demo=0', 'fields' => array('id' => array('t.id','number'), 'status' => array('t.status','text'), 'rating' => array('t.rating','number'), 'created_at' => array('t.created_at','date'))),
			'arena_runs' => array('from' => 'st_arena_runs t', 'fields' => array('status' => array('t.status','text'), 'game_type' => array('t.game_type','text'), 'passed' => array('t.passed','number'), 'question_count' => array('t.question_count','number'), 'correct_count' => array('t.correct_count','number'), 'started_at' => array('t.started_at','date'), 'finished_at' => array('t.finished_at','date'))),
			'arena_packs' => array('from' => 'st_arena_packs t', 'fields' => array('id' => array('t.id','number'), 'title' => array('t.title','text'), 'game_type' => array('t.game_type','text'), 'enabled' => array('t.enabled','number'), 'created_at' => array('t.created_at','date'))),
			'arena_card_progress' => array('from' => 'st_arena_card_progress t', 'fields' => array('status' => array('t.status','text'), 'review_count' => array('t.review_count','number'), 'updated_at' => array('t.updated_at','date'))),
			'battle_rooms' => array('from' => 'st_battle_rooms t', 'fields' => array('id' => array('t.id','number'), 'title' => array('t.title','text'), 'status' => array('t.status','text'), 'play_mode' => array('t.play_mode','text'), 'created_at' => array('t.created_at','date'), 'starts_at' => array('t.starts_at','date'))),
			'battle_entries' => array('from' => 'st_battle_entries t', 'fields' => array('status' => array('t.status','text'), 'created_at' => array('t.created_at','date'), 'finished_at' => array('t.finished_at','date'))),
			'flashcard_decks' => array('from' => 'st_flashcard_decks t', 'fields' => array('id' => array('t.id','number'), 'title' => array('t.title','text'), 'created_at' => array('t.created_at','date'))),
			'flashcard_progress' => array('from' => 'st_flashcard_progress t', 'fields' => array('status' => array('t.status','text'), 'review_count' => array('t.review_count','number'), 'last_reviewed_at' => array('t.last_reviewed_at','date'))),
			'point_wallets' => array('from' => 'st_point_wallets t', 'fields' => array('learner_user_id' => array('t.learner_user_id','number'), 'balance_points' => array('t.balance_points','number'), 'updated_at' => array('t.updated_at','date'))),
			'point_entries' => array('from' => 'st_point_entries t', 'fields' => array('delta_points' => array('t.delta_points','number'), 'created_at' => array('t.created_at','date'))),
			'feature_unlocks' => array('from' => 'st_feature_unlocks t', 'fields' => array('feature_type' => array('t.feature_type','text'), 'price_credits' => array('t.price_credits','number'), 'created_at' => array('t.created_at','date'))),
			'notifications' => array('from' => 'st_notifications t', 'fields' => array('category' => array('t.category','text'), 'created_at' => array('t.created_at','date'), 'read_at' => array('t.read_at','date'))),
			'telegram_deliveries' => array('from' => 'st_telegram_outbox t', 'fields' => array('category' => array('t.category','text'), 'status' => array('t.status','text'), 'attempts' => array('t.attempts','number'), 'created_at' => array('t.created_at','date'), 'sent_at' => array('t.sent_at','date'))),
			'email_deliveries' => array('from' => 'st_mail_deliveries t', 'fields' => array('purpose' => array('t.purpose','text'), 'status' => array('t.status','text'), 'created_at' => array('t.created_at','date'))),
			'google_accounts' => array('from' => 'st_google_identities t', 'fields' => array('created_at' => array('t.created_at','date'))),
			'traffic_events' => array('from' => 'st_traffic_events t', 'fields' => array('visitor_key' => array('t.visitor_hash','opaque'), 'visit_key' => array('t.visit_hash','opaque'), 'page_key' => array('t.page_key','text'), 'account_kind' => array('t.account_kind','text'), 'created_at' => array('t.created_at','date'))),
			'operational_visits' => array('from' => 'st_operational_events t', 'where' => 't.expires_at>UTC_TIMESTAMP()', 'fields' => array('ip_key' => array('t.ip_address','opaque'), 'page_key' => array('t.page_key','text'), 'destination_path' => array('t.destination_path','text'), 'account_kind' => array('t.account_kind','text'), 'is_bot' => array('t.is_bot','number'), 'country' => array('t.country','text'), 'device' => array('t.device','text'), 'created_at' => array('t.created_at','date'))),
			'traffic_daily' => array('from' => 'st_traffic_daily t', 'fields' => array('day_wib' => array('t.day_wib','day'), 'page_key' => array('t.page_key','text'), 'account_kind' => array('t.account_kind','text'), 'views' => array('t.views','number'))),
			'audit_events' => array('from' => 'st_audit_events t', 'fields' => array('event_type' => array('t.event_type','text'), 'entity_type' => array('t.entity_type','text'), 'created_at' => array('t.created_at','date'))),
		);
	}

	public function catalog_text()
	{
		$lines = array('Dataset dan kolom yang boleh dipakai (semua waktu datetime dalam UTC; day_wib lokal):');
		foreach ($this->catalog() as $name => $dataset) $lines[] = $name.': '.implode(', ', array_keys($dataset['fields']));
		$lines[] = 'members: hanya member dewasa/orang tua, staf dikecualikan; name adalah nama asli member. children: hanya akun anak; name adalah nama anak dan parent_name nama orang tua yang terhubung. Gunakan keduanya untuk daftar nama. users: seluruh akun, staf = is_staff 1. payments: hanya produksi. reviews: hanya asli.';
		$lines[] = 'Pengunjung hari ini: gunakan operational_visits dengan is_bot=0 dan created_at=today, metric distinct_count field ip_key. Ini jumlah IP/jaringan unik, perkiraan pengunjung; count pada dataset yang sama adalah tampilan halaman. Untuk 10 halaman terakhir yang dikunjungi, gunakan operational_visits list fields [destination_path,page_key,created_at], sort_by created_at desc, limit 10, filter is_bot=0. traffic_events visitor_key adalah pengunjung unik yang menyetujui pelacakan; bisa nol walaupun situs dikunjungi. traffic_daily.views adalah arsip tampilan halaman, bukan pengunjung unik. Kolom key bersifat opaque: hanya boleh untuk distinct_count, jangan tampilkan nilainya.';
		$lines[] = 'telegram_deliveries: sent_at hanya terisi bila status sent; untuk status failed dengan periode gunakan created_at (waktu pesan dibuat), jangan menyebutnya waktu kegagalan. practice_attempts: gunakan submitted_at untuk nilai akhir; practice_answer_events.elapsed_ms adalah waktu jawaban yang tercatat dalam milidetik.';
		return implode("\n", $lines);
	}

	private function date_range($op, $value)
	{
		$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
		if ($op === 'today') $start = $now->setTime(0, 0);
		elseif ($op === 'yesterday') $start = $now->modify('-1 day')->setTime(0, 0);
		elseif ($op === 'this_month') $start = $now->modify('first day of this month')->setTime(0, 0);
		elseif ($op === 'previous_month') $start = $now->modify('first day of last month')->setTime(0, 0);
		elseif ($op === 'this_week') $start = $now->modify('monday this week')->setTime(0, 0);
		elseif ($op === 'previous_week') $start = $now->modify('monday last week')->setTime(0, 0);
		elseif ($op === 'this_year') $start = $now->setDate((int)$now->format('Y'), 1, 1)->setTime(0, 0);
		elseif ($op === 'previous_year') $start = $now->setDate((int)$now->format('Y') - 1, 1, 1)->setTime(0, 0);
		elseif ($op === 'last_days' && is_int($value) && $value >= 1 && $value <= 365) $start = $now->modify('-'.$value.' days');
		else throw new InvalidArgumentException('Invalid date filter.');
		if ($op === 'today') $end = $start->modify('+1 day');
		elseif ($op === 'yesterday') $end = $start->modify('+1 day');
		elseif ($op === 'this_month' || $op === 'previous_month') $end = $start->modify('+1 month');
		elseif ($op === 'this_week' || $op === 'previous_week') $end = $start->modify('+1 week');
		elseif ($op === 'this_year' || $op === 'previous_year') $end = $start->modify('+1 year');
		else $end = NULL;
		return array($start, $end);
	}

	private function verify_chat($chat_id)
	{
		if (PHP_SAPI !== 'cli' || !is_string($chat_id) || !preg_match('/\A[1-9][0-9]*\z/', $chat_id)) throw new RuntimeException('Bot data access denied.');
		$row = $this->db->query('SELECT chat_id FROM st_notification_config WHERE id=1')->row_array();
		if (!$row || $row['chat_id'] !== $chat_id) throw new RuntimeException('Bot data access denied.');
	}

	public function execute($chat_id, $plan)
	{
		$this->verify_chat($chat_id);
		if (!is_array($plan) || !is_array($plan['queries'] ?? NULL) || count($plan['queries']) < 1 || count($plan['queries']) > 3) throw new InvalidArgumentException('Invalid data plan.');
		$catalog = $this->catalog();
		$results = array();
		foreach ($plan['queries'] as $item)
		{
			if (!is_array($item) || !is_string($item['dataset'] ?? NULL) || !isset($catalog[$item['dataset']])) throw new InvalidArgumentException('Unknown dataset.');
			$dataset = $catalog[$item['dataset']]; $fields = $dataset['fields'];
			$metric = $item['metric'] ?? 'count';
			$field = $item['field'] ?? NULL;
			if (!in_array($metric, array('count','sum','avg','min','max','distinct_count','list'), TRUE)) throw new InvalidArgumentException('Invalid metric.');
			if ($metric !== 'count' && $metric !== 'list' && (!is_string($field) || !isset($fields[$field]))) throw new InvalidArgumentException('Invalid metric field.');
			if (in_array($metric, array('sum','avg'), TRUE) && $fields[$field][1] !== 'number') throw new InvalidArgumentException('Metric requires a number.');
			if ($metric !== 'distinct_count' && is_string($field) && isset($fields[$field]) && $fields[$field][1] === 'opaque') throw new InvalidArgumentException('Restricted metric field.');
			$group = $item['group_by'] ?? NULL;
			$group_sql = NULL;
			$date_group = FALSE;
			if ($group !== NULL)
			{
				if (!is_string($group) || $metric === 'list') throw new InvalidArgumentException('Invalid grouping.');
				if (preg_match('/\A(day|month):([a-z_]+)\z/', $group, $parts))
				{
					if (!isset($fields[$parts[2]]) || !in_array($fields[$parts[2]][1], array('date','day'), TRUE)) throw new InvalidArgumentException('Invalid date grouping.');
					$date_group = TRUE;
					$source = $fields[$parts[2]][0];
					$local = $fields[$parts[2]][1] === 'day' ? $source : "($source + INTERVAL 7 HOUR)";
					$group_sql = $parts[1] === 'day' ? "DATE($local)" : "DATE_FORMAT($local,'%Y-%m')";
				}
				else
				{
					if (!isset($fields[$group]) || $fields[$group][1] === 'opaque') throw new InvalidArgumentException('Invalid grouping.');
					$group_sql = $fields[$group][0];
				}
			}
			$bind = array(); $conditions = array($dataset['where'] ?? '1=1');
			$filters = $item['filters'] ?? array();
			if (!is_array($filters) || count($filters) > 6) throw new InvalidArgumentException('Too many filters.');
			foreach ($filters as $filter)
			{
				if (!is_array($filter) || !is_string($filter['field'] ?? NULL) || !isset($fields[$filter['field']]) || !is_string($filter['op'] ?? NULL)) throw new InvalidArgumentException('Invalid filter.');
				$column = $fields[$filter['field']][0]; $type = $fields[$filter['field']][1]; $op = $filter['op']; $value = $filter['value'] ?? NULL;
				if ($type === 'opaque') throw new InvalidArgumentException('Restricted filter field.');
				if (in_array($op, array('today','yesterday','this_week','previous_week','this_month','previous_month','this_year','previous_year','last_days'), TRUE))
				{
					if ($type !== 'date' && $type !== 'day') throw new InvalidArgumentException('Date filter needs date field.');
					list($start, $end) = $this->date_range($op, $value);
					if ($type === 'date') { $start = $start->setTimezone(new DateTimeZone('UTC')); if ($end) $end = $end->setTimezone(new DateTimeZone('UTC')); }
					$conditions[] = "$column >= ?"; $bind[] = $start->format($type === 'day' ? 'Y-m-d' : 'Y-m-d H:i:s');
					if ($end) { $conditions[] = "$column < ?"; $bind[] = $end->format($type === 'day' ? 'Y-m-d' : 'Y-m-d H:i:s'); }
				}
				elseif ($op === 'is_null' || $op === 'not_null') $conditions[] = "$column IS ".($op === 'is_null' ? '' : 'NOT ').'NULL';
				elseif ($op === 'in')
				{
					if (!is_array($value) || count($value) < 1 || count($value) > 10) throw new InvalidArgumentException('Invalid list filter.');
					foreach ($value as $v) if (!is_scalar($v) || strlen((string)$v) > 120 || ($type === 'number' && !preg_match('/\A-?[0-9]+(?:\.[0-9]+)?\z/', (string)$v))) throw new InvalidArgumentException('Invalid list value.');
					$conditions[] = "$column IN (".implode(',', array_fill(0, count($value), '?')).')';
					foreach ($value as $v) $bind[] = $v;
				}
				elseif (in_array($op, array('eq','neq','gt','gte','lt','lte','contains'), TRUE))
				{
					if (!is_scalar($value) || strlen((string)$value) > 120 || ($op === 'contains' && $type !== 'text') || ($type === 'number' && !preg_match('/\A-?[0-9]+(?:\.[0-9]+)?\z/', (string)$value))) throw new InvalidArgumentException('Invalid filter value.');
					$symbols = array('eq'=>'=','neq'=>'!=','gt'=>'>','gte'=>'>=','lt'=>'<','lte'=>'<=','contains'=>'LIKE');
					$conditions[] = "$column {$symbols[$op]} ?";
					$bind[] = $op === 'contains' ? '%'.$this->db->escape_like_str((string)$value).'%' : $value;
				}
				else throw new InvalidArgumentException('Invalid filter operator.');
			}
			$where = implode(' AND ', array_map(static function($condition) { return '('.$condition.')'; }, $conditions));
			if ($metric === 'list')
			{
				$selected = $item['fields'] ?? array();
				if (!is_array($selected) || count($selected) < 1 || count($selected) > 5) throw new InvalidArgumentException('Invalid selected fields.');
				$columns = array();
				foreach ($selected as $name) { if (!is_string($name) || !isset($fields[$name]) || $fields[$name][1] === 'opaque') throw new InvalidArgumentException('Unknown selected field.'); $columns[] = $fields[$name][0].' AS `'.$name.'`'; }
				$sort = $item['sort_by'] ?? $selected[0];
				if (!is_string($sort) || !isset($fields[$sort]) || $fields[$sort][1] === 'opaque') throw new InvalidArgumentException('Invalid sort field.');
				$order = ($item['sort_dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
				$sql = 'SELECT '.implode(',', $columns).' FROM '.$dataset['from'].' WHERE '.$where.' ORDER BY '.$fields[$sort][0].' '.$order.' LIMIT '.min(15, max(1, (int)($item['limit'] ?? 5)));
			}
			else
			{
				$expr = $metric === 'count' ? 'COUNT(*)' : ($metric === 'distinct_count' ? 'COUNT(DISTINCT '.$fields[$field][0].')' : strtoupper($metric).'('.$fields[$field][0].')');
				if ($metric === 'sum') $expr = 'COALESCE('.$expr.',0)';
				$sql = 'SELECT '.($group_sql ? $group_sql.' AS `group_value`, ' : '').$expr.' AS `value` FROM '.$dataset['from'].' WHERE '.$where;
				if ($group_sql) $sql .= ' GROUP BY '.$group_sql.' ORDER BY '.($date_group ? '`group_value`' : '`value`').' DESC LIMIT 20';
			}
			$query = $this->db->query($sql, $bind);
			if ($query === FALSE) throw new RuntimeException('Bot data query unavailable.');
			$results[] = array('dataset'=>$item['dataset'], 'metric'=>$metric, 'field'=>$field, 'group_by'=>$group, 'filters'=>$filters, 'rows'=>$query->result_array());
		}
		return array('checked_at'=>(new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('d/m/Y H:i:s').' WIB', 'results'=>$results);
	}
}
