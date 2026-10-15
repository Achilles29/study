<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Check_media_backup extends CI_Controller
{
	private $qa_root;
	private $media_root;
	private $fixture_suffix;
	private $database_kind;
	private $phase = 'environment';
	private $table_names = array(
		'st_account_tokens', 'st_audit_events', 'st_categories', 'st_content_reviews',
		'st_course_enrollments', 'st_course_sections', 'st_courses', 'st_entitlements',
		'st_family_links', 'st_flashcard_decks', 'st_flashcard_progress', 'st_flashcards',
		'st_lesson_images', 'st_lesson_progress', 'st_lessons', 'st_login_failures',
		'st_matching_moves', 'st_matching_runs', 'st_package_courses', 'st_packages',
		'st_payment_orders', 'st_practice_answers', 'st_practice_attempts', 'st_practice_sessions',
		'st_question_options', 'st_questions', 'st_roles', 'st_session_questions',
		'st_user_roles', 'st_users', 'st_wallet_entries', 'st_wallets', 'study_migrations'
	);

	public function __construct()
	{
		// Validate environment and paths before the controller autoloads a database.
		if (PHP_SAPI !== 'cli' || ENVIRONMENT !== 'development') { show_404(); exit(1); }
		set_error_handler(static function ($severity, $message, $file, $line) {
			throw new ErrorException('Synthetic backup operation failed.', 0, $severity);
		});
		try
		{
			$database = getenv('DB_DATABASE');
			$this->require_true(getenv('DB_HOST') === '127.0.0.1' && is_string($database) && preg_match('/\Adb_study_media_(backup|restore)_qa_([a-f0-9]{12})\z/', $database, $matches), 'Synthetic backup environment rejected.');
			$this->database_kind = $matches[1];
			$this->fixture_suffix = $matches[2];
			$this->qa_root = $this->canonical_directory(getenv('STUDY_MEDIA_BACKUP_QA_ROOT'));
			$temp_root = $this->canonical_directory(realpath(sys_get_temp_dir()));
			$this->require_true($this->same_path(dirname($this->qa_root), $temp_root) && basename($this->qa_root) === 'study-media-backup-qa-'.$this->fixture_suffix, 'Synthetic backup environment rejected.');
			$this->media_root = $this->canonical_directory(getenv('STUDY_MEDIA_ROOT'));
			$expected = $this->qa_root.($this->database_kind === 'backup' ? '/source/media' : '/restored/media');
			$this->require_true($this->same_path($this->media_root, $expected), 'Synthetic backup environment rejected.');
			$this->phase = 'controller_autoload';
			parent::__construct();
			$this->db->db_debug = FALSE;
			$this->require_true($this->db->hostname === '127.0.0.1' && $this->db->database === $database, 'Synthetic backup environment rejected.');
			$actual = $this->rows('SELECT DATABASE() AS active_database');
			$this->require_true(count($actual) === 1 && $actual[0]['active_database'] === $database, 'Synthetic backup environment rejected.');
			$this->phase = 'load_models';
			$this->load->model('Content_model');
			$this->load->model('Lesson_media_model');
			$this->load->library('Lesson_image_store');
		}
		catch (Throwable $error) { $this->fail($error); }
	}

	private function fail($error)
	{
		$allowed = array('Synthetic backup environment rejected.', 'Synthetic restored media integrity check failed.', 'Synthetic media inventory check failed.', 'Synthetic backup schema check failed.', 'Synthetic backup fixture check failed.', 'Synthetic backup seed requires a fresh empty source.', 'Synthetic backup mode rejected.', 'Synthetic backup image prerequisites unavailable.', 'Synthetic backup fixture cleanup failed.');
		$message = in_array($error->getMessage(), $allowed, TRUE) ? $error->getMessage() : 'Synthetic backup operation failed.';
		restore_error_handler();
		// Phase is assigned only from fixed labels in this controller, never from input.
		fwrite(STDERR, $message.' Phase: '.$this->phase.'.'.($error instanceof ErrorException ? ' Warning converted to failure.' : '')."\n");
		exit(1);
	}

	private function require_true($condition, $message = 'Synthetic backup fixture check failed.')
	{
		if ( ! $condition) { throw new RuntimeException($message); }
	}

	private function same_path($first, $second)
	{
		$first = str_replace('\\', '/', $first);
		$second = str_replace('\\', '/', $second);
		return DIRECTORY_SEPARATOR === '\\' ? strcasecmp($first, $second) === 0 : $first === $second;
	}

	private function canonical_directory($path)
	{
		$this->require_true(is_string($path) && $path !== '' && ! preg_match('/[\x00-\x1F\x7F]/', $path), 'Synthetic backup environment rejected.');
		$path = str_replace('\\', '/', $path);
		$this->require_true(preg_match('/\A(?:[A-Za-z]:\/|\/[^\/])/', $path) && ! preg_match('/(?:\A|\/)\.{1,2}(?:\/|\z)/', $path) && strpos(substr($path, 2), ':') === FALSE, 'Synthetic backup environment rejected.');
		$real = realpath($path);
		$this->require_true(is_string($real) && is_dir($real) && $this->same_path($path, $real), 'Synthetic backup environment rejected.');
		$cursor = $real;
		do
		{
			$this->require_true( ! is_link($cursor), 'Synthetic backup environment rejected.');
			$parent = dirname($cursor);
			if ($parent === $cursor) { break; }
			$cursor = $parent;
		} while (TRUE);
		return str_replace('\\', '/', $real);
	}

	private function rows($sql, $parameters = array())
	{
		$query = $this->db->query($sql, $parameters);
		$this->require_true($query !== FALSE);
		return $query->result_array();
	}

	private function schema_state()
	{
		$rows = $this->rows('SELECT TABLE_NAME AS name, TABLE_TYPE AS kind FROM information_schema.tables WHERE table_schema = ? ORDER BY TABLE_NAME', array($this->db->database));
		$names = array_column($rows, 'name');
		// Metadata collation may sort underscores differently from PHP byte order.
		sort($names, SORT_STRING);
		$this->require_true($names === $this->table_names && count(array_filter($rows, static function ($row) {
			return $row['kind'] !== 'BASE TABLE';
		})) === 0, 'Synthetic backup schema check failed.');
		$version = $this->rows('SELECT version FROM study_migrations');
		$this->require_true(count($version) === 1 && (int) $version[0]['version'] === 13, 'Synthetic backup schema check failed.');
	}

	private function expected_counts($fresh = FALSE)
	{
		$counts = array_fill_keys($this->table_names, 0);
		$counts['st_roles'] = 4;
		$counts['study_migrations'] = 1;
		if ( ! $fresh)
		{
			$counts = array_replace($counts, array('st_users' => 4, 'st_user_roles' => 2, 'st_courses' => 2, 'st_course_sections' => 2, 'st_lessons' => 3, 'st_lesson_images' => 3, 'st_course_enrollments' => 2, 'st_content_reviews' => 2, 'st_audit_events' => 12));
		}
		foreach ($counts as $table => $expected)
		{
			$actual = $this->rows('SELECT COUNT(*) AS total FROM `'.$table.'`');
			$this->require_true((int) $actual[0]['total'] === $expected, $fresh ? 'Synthetic backup seed requires a fresh empty source.' : 'Synthetic backup fixture check failed.');
		}
		$roles = $this->rows('SELECT role_code FROM st_roles ORDER BY role_code');
		$this->require_true(array_column($roles, 'role_code') === array('content_editor', 'finance_operator', 'support_agent', 'system_admin'));
	}

	private function course_data($kind)
	{
		return array('category_id' => '', 'title' => 'QA media backup '.$this->fixture_suffix.' '.$kind, 'summary' => 'Ringkasan sintetis untuk uji pemulihan berpasangan.', 'learning_objectives' => 'Mengenali bentuk dan warna melalui bahan uji sintetis.', 'prerequisites' => 'Tidak ada prasyarat untuk fixture sintetis.', 'target_group' => 'adult', 'age_min' => '', 'age_max' => '', 'estimated_minutes' => 10, 'rights_status' => 'owned', 'source_note' => 'Dibuat khusus oleh harness pemulihan lokal.', 'is_free' => $kind === 'free' ? 1 : 0);
	}

	private function lesson_data($position)
	{
		return array('title' => 'Pelajaran sintetis '.$position, 'body_text' => "Bahan teks biasa untuk uji pemulihan {$position}.\nAmati bentuk dan warna pada gambar. Teks ini bukan materi pengguna.", 'estimated_minutes' => 5, 'is_preview' => 0, 'source_note' => 'Teks buatan harness pemulihan lokal.', 'rights_status' => 'owned');
	}

	private function image_data($position)
	{
		return array('alt_text' => 'Bentuk berwarna pada gambar sintetis '.$position, 'caption' => 'Gambar fixture pemulihan '.$position, 'source_note' => 'Gambar GD buatan harness pemulihan lokal.', 'rights_status' => 'owned');
	}

	private function seed_image($position)
	{
		$path = $this->qa_root.'/source/seed-input.tmp';
		$this->require_true( ! file_exists($path) && ! is_link($path));
		$stream = NULL;
		$canvas = NULL;
		$created = FALSE;
		try
		{
			$stream = fopen($path, 'x+b');
			$this->require_true(is_resource($stream));
			$created = TRUE;
			$this->require_true(chmod($path, 0600));
			$canvas = imagecreatetruecolor(32 + $position * 8, 20 + $position * 4);
			$this->require_true($canvas !== FALSE);
			$background = imagecolorallocate($canvas, 30 * $position, 60, 180);
			$shape = imagecolorallocate($canvas, 220, 190 - 20 * $position, 40);
			$this->require_true(imagefill($canvas, 0, 0, $background) && imagefilledrectangle($canvas, 4, 4, 14 + $position, 16, $shape));
			$jpeg = $position === 2;
			$this->require_true($jpeg ? imagejpeg($canvas, $stream, 85) : imagepng($canvas, $stream));
			$this->require_true(fflush($stream));
			fclose($stream);
			$stream = NULL;
			$result = $this->lesson_image_store->from_local_file($path, $jpeg ? 'synthetic.jpg' : 'synthetic.png');
			$this->require_true(($result['status'] ?? NULL) === 'ok');
			return $result['image'];
		}
		finally
		{
			if ($canvas !== NULL && $canvas !== FALSE) { imagedestroy($canvas); }
			if (is_resource($stream)) { fclose($stream); }
			if ($created)
			{
				$this->require_true( ! is_link($path) && is_file($path) && $this->same_path(realpath($path), $path) && unlink($path), 'Synthetic backup fixture cleanup failed.');
			}
		}
	}

	private function seed()
	{
		$this->phase = 'seed_empty_source';
		$this->require_true($this->database_kind === 'backup', 'Synthetic backup seed requires a fresh empty source.');
		$this->expected_counts(TRUE);
		$this->check_inventory(array());
		$this->require_true($this->lesson_image_store->upload_available(), 'Synthetic backup image prerequisites unavailable.');
		$this->phase = 'seed_users';
		$users = array();
		$now = gmdate('Y-m-d H:i:s');
		foreach (array('editor', 'reviewer', 'learner', 'outsider') as $kind)
		{
			$this->require_true($this->db->insert('st_users', array('username' => 'qa_media_backup_'.$this->fixture_suffix.'_'.$kind, 'password_hash' => 'not-a-real-login-hash', 'display_name' => 'Synthetic backup '.$kind, 'account_kind' => 'adult', 'learning_stage' => 'adult', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now)) !== FALSE);
			$users[$kind] = (int) $this->db->insert_id();
		}
		$this->phase = 'seed_roles';
		foreach (array('editor' => 'content_editor', 'reviewer' => 'system_admin') as $kind => $role)
		{
			$rows = $this->rows('SELECT id FROM st_roles WHERE role_code = ?', array($role));
			$this->require_true(count($rows) === 1 && $this->db->insert('st_user_roles', array('user_id' => $users[$kind], 'role_id' => (int) $rows[0]['id'], 'granted_by_user_id' => NULL, 'created_at' => $now)) !== FALSE);
		}
		$position = 0;
		foreach (array('free' => 1, 'paid' => 2) as $kind => $lesson_count)
		{
			$this->phase = 'seed_course';
			$course_id = $this->Content_model->create_course($this->course_data($kind), $users['editor']);
			$this->require_true(is_int($course_id) && $course_id > 0);
			for ($index = 0; $index < $lesson_count; $index++)
			{
				$position++;
				$this->phase = 'seed_lesson';
				$lesson_id = $this->Content_model->create_lesson($course_id, $this->lesson_data($position), $users['editor']);
				$this->require_true(is_int($lesson_id) && $lesson_id > 0);
				$this->phase = 'seed_image_store';
				$image = $this->seed_image($position);
				$this->phase = 'seed_image_metadata';
				$result = $this->Lesson_media_model->change($course_id, $lesson_id, $users['editor'], 1, 'upload', $this->image_data($position), $image);
				$this->require_true(($result['status'] ?? NULL) === 'ok' && $result['previous_key'] === NULL);
			}
			$this->phase = 'seed_submit';
			$submitted = $this->Content_model->submit_for_review($course_id, $users['editor']);
			$this->require_true(($submitted['ok'] ?? FALSE) === TRUE);
			$this->phase = 'seed_review';
			$reviewed = $this->Content_model->review_course($course_id, $users['reviewer'], 'approved', 'Tinjauan sintetis pemulihan berpasangan.');
			$this->require_true(($reviewed['ok'] ?? FALSE) === TRUE);
			if ($kind === 'paid')
			{
				$this->phase = 'seed_enrollment';
				foreach (array('learner' => 'active', 'outsider' => 'revoked') as $enrollee => $status)
				{
					$this->require_true($this->db->insert('st_course_enrollments', array('learner_user_id' => $users[$enrollee], 'course_id' => $course_id, 'source_type' => 'manual', 'status' => $status, 'enrolled_at' => $now, 'updated_at' => $now)) !== FALSE);
				}
			}
		}
	}

	private function check_inventory($images)
	{
		$this->canonical_directory($this->media_root);
		$expected = array_column($images, 'storage_key');
		sort($expected, SORT_STRING);
		$actual = array_values(array_diff(scandir($this->media_root), array('.', '..')));
		sort($actual, SORT_STRING);
		// A missing expected image is an integrity failure; extra entries are inventory failures.
		$this->require_true(count(array_diff($actual, $expected)) === 0, 'Synthetic media inventory check failed.');
		foreach ($actual as $key)
		{
			$path = $this->media_root.'/'.$key;
			$this->require_true(preg_match('/\A[a-f0-9]{64}\.(png|jpg)\z/', $key) && ! is_link($path) && is_file($path) && $this->same_path(realpath($path), $path), 'Synthetic media inventory check failed.');
		}
		$this->require_true($actual === $expected, 'Synthetic restored media integrity check failed.');
	}

	private function expect_audit($actor_id, $event, $entity, $entity_id, $details)
	{
		$rows = $this->rows('SELECT actor_user_id, correlation_id, details_json FROM st_audit_events WHERE event_type = ? AND entity_type = ? AND entity_id = ?', array($event, $entity, $entity_id));
		$this->require_true(count($rows) === 1 && (int) $rows[0]['actor_user_id'] === $actor_id && $rows[0]['correlation_id'] === NULL && json_decode($rows[0]['details_json'], TRUE, 512, JSON_THROW_ON_ERROR) === $details);
	}

	private function verify()
	{
		$this->phase = 'verify_counts';
		$this->expected_counts();
		$this->phase = 'verify_users';
		$users = array();
		foreach (array('editor', 'reviewer', 'learner', 'outsider') as $kind)
		{
			$rows = $this->rows('SELECT * FROM st_users WHERE username = ?', array('qa_media_backup_'.$this->fixture_suffix.'_'.$kind));
			$this->require_true(count($rows) === 1 && $rows[0]['status'] === 'active' && $rows[0]['account_kind'] === 'adult' && $rows[0]['learning_stage'] === 'adult' && $rows[0]['email'] === NULL && $rows[0]['password_hash'] === 'not-a-real-login-hash');
			$users[$kind] = (int) $rows[0]['id'];
			$roles = $this->rows('SELECT r.role_code FROM st_user_roles ur JOIN st_roles r ON r.id = ur.role_id WHERE ur.user_id = ? ORDER BY r.role_code', array($users[$kind]));
			$expected = $kind === 'editor' ? array('content_editor') : ($kind === 'reviewer' ? array('system_admin') : array());
			$this->require_true(array_column($roles, 'role_code') === $expected);
		}
		$this->phase = 'verify_image_inventory';
		$images = $this->rows('SELECT * FROM st_lesson_images ORDER BY storage_key');
		$this->check_inventory($images);
		$hashes = array();
		foreach ($images as $image)
		{
			$this->phase = 'verify_image_bytes';
			$bytes = $this->lesson_image_store->read($image);
			$this->require_true(is_string($bytes) && strlen($bytes) === (int) $image['byte_size'] && hash_equals($image['sha256'], hash('sha256', $bytes)), 'Synthetic restored media integrity check failed.');
			$hashes[] = $image['sha256'];
		}
		$this->require_true(count(array_unique($hashes)) === 3);
		$position = 0;
		foreach (array('free' => 1, 'paid' => 2) as $kind => $lesson_count)
		{
			$this->phase = 'verify_course';
			$data = $this->course_data($kind);
			$course = $this->Content_model->get_course_by_slug(url_title($data['title'], 'dash', TRUE));
			$this->require_true(is_array($course) && $course !== array());
			$course_id = (int) $course['id'];
			$revision = 1 + 2 * $lesson_count;
			$this->require_true($course['status'] === 'published' && (int) $course['content_version'] === $revision && (int) $course['author_user_id'] === $users['editor'] && (int) $course['updated_by_user_id'] === $users['reviewer'] && $course['published_at'] !== NULL && (int) $course['is_free'] === $data['is_free']);
			foreach (array('title', 'summary', 'learning_objectives', 'prerequisites', 'source_note', 'rights_status') as $field) { $this->require_true($course[$field] === $data[$field]); }
			$this->require_true($this->Content_model->can_access_course(0, $course) === ($kind === 'free') && $this->Content_model->can_access_course($users['learner'], $course) === TRUE && $this->Content_model->can_access_course($users['outsider'], $course) === ($kind === 'free') && $this->Content_model->can_access_course($users['editor'], $course) === ($kind === 'free'));
			$sections = $this->rows('SELECT * FROM st_course_sections WHERE course_id = ?', array($course_id));
			$this->require_true(count($sections) === 1 && $sections[0]['title'] === 'Bagian 1' && (int) $sections[0]['sort_order'] === 1);
			$lessons = $this->Content_model->get_course_lessons($course_id, TRUE);
			$this->require_true(count($lessons) === $lesson_count && $this->Lesson_media_model->ready_for_review($course_id));
			foreach ($lessons as $index => $lesson)
			{
				$this->phase = 'verify_lesson';
				$position++;
				$lesson_id = (int) $lesson['id'];
				$lesson_data = $this->lesson_data($position);
				$this->require_true((int) $lesson['sort_order'] === $index + 1 && (int) $lesson['content_version'] === 2 && $lesson['status'] === 'published' && (int) $lesson['is_preview'] === 0);
				foreach (array('title', 'body_text', 'rights_status', 'source_note') as $field) { $this->require_true($lesson[$field] === $lesson_data[$field]); }
				$published = $this->Content_model->get_published_lesson($lesson['slug']);
				$this->require_true($published && (int) $published['course_id'] === $course_id && $this->Content_model->can_access_lesson(0, $published) === ($kind === 'free') && $this->Content_model->can_access_lesson($users['learner'], $published) === TRUE && $this->Content_model->can_access_lesson($users['outsider'], $published) === ($kind === 'free') && $this->Content_model->can_access_lesson($users['editor'], $published) === ($kind === 'free'));
				$image = $this->Lesson_media_model->get_for_lesson($lesson_id);
				$this->require_true(is_array($image) && (int) $image['uploader_user_id'] === $users['editor'] && (int) $image['width'] === 32 + $position * 8 && (int) $image['height'] === 20 + $position * 4 && $image['mime_type'] === ($position === 2 ? 'image/jpeg' : 'image/png'));
				foreach ($this->image_data($position) as $field => $value) { $this->require_true($image[$field] === $value); }
				$previous = 1 + $index * 2;
				$this->phase = 'verify_lesson_audit';
				$this->expect_audit($users['editor'], 'content.lesson_created', 'lesson', $lesson_id, array('course_id' => $course_id, 'previous_course_version' => $previous, 'next_course_version' => $previous + 1));
				$this->expect_audit($users['editor'], 'content.lesson_image_changed', 'lesson', $lesson_id, array('action' => 'upload', 'course_id' => $course_id, 'lesson_id' => $lesson_id, 'previous_version' => 1, 'next_version' => 2, 'previous_course_version' => $previous + 1, 'next_course_version' => $previous + 2));
			}
			$this->phase = 'verify_course_audit';
			$reviews = $this->rows('SELECT * FROM st_content_reviews WHERE course_id = ?', array($course_id));
			$this->require_true(count($reviews) === 1 && (int) $reviews[0]['reviewer_user_id'] === $users['reviewer'] && $reviews[0]['decision'] === 'approved' && (int) $reviews[0]['content_version'] === $revision && $reviews[0]['notes'] === 'Tinjauan sintetis pemulihan berpasangan.');
			$this->expect_audit($users['editor'], 'content.course_created', 'course', $course_id, array('status' => 'draft'));
			$this->expect_audit($users['editor'], 'content.course_submitted', 'course', $course_id, array('content_version' => $revision));
			$this->expect_audit($users['reviewer'], 'content.course_reviewed', 'course', $course_id, array('decision' => 'approved', 'content_version' => $revision));
			$this->phase = 'verify_enrollment';
			$enrollments = $this->rows('SELECT * FROM st_course_enrollments WHERE course_id = ? ORDER BY learner_user_id', array($course_id));
			$this->require_true(count($enrollments) === ($kind === 'free' ? 0 : 2));
			foreach ($enrollments as $enrollment)
			{
				$learner_id = (int) $enrollment['learner_user_id'];
				$this->require_true(in_array($learner_id, array($users['learner'], $users['outsider']), TRUE) && $enrollment['status'] === ($learner_id === $users['learner'] ? 'active' : 'revoked') && $enrollment['source_type'] === 'manual' && $enrollment['package_id'] === NULL && $enrollment['entitlement_id'] === NULL);
			}
		}
		return $images;
	}

	private function digest($images)
	{
		$this->phase = 'digest';
		$tables = array();
		foreach ($this->table_names as $table)
		{
			$rows = $this->rows('SELECT * FROM `'.$table.'`');
			$encoded = array();
			foreach ($rows as $row)
			{
				ksort($row, SORT_STRING);
				$encoded[] = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
			}
			sort($encoded, SORT_STRING);
			$structure = $this->rows('SHOW CREATE TABLE `'.$table.'`');
			$this->require_true(count($structure) === 1 && isset($structure[0]['Create Table']));
			$tables[] = array('name' => $table, 'row_count' => count($rows), 'sha256' => hash('sha256', json_encode($encoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), 'structure_sha256' => hash('sha256', $structure[0]['Create Table']));
		}
		$files = array();
		foreach ($images as $image)
		{
			$files[] = array('storage_key' => $image['storage_key'], 'sha256' => $image['sha256'], 'byte_size' => (int) $image['byte_size'], 'mime_type' => $image['mime_type'], 'lesson_id' => (int) $image['lesson_id']);
		}
		return array('version' => 13, 'table_count' => count($tables), 'tables' => $tables, 'image_count' => count($images), 'files' => $files);
	}

	public function index($mode = '')
	{
		try
		{
			$this->phase = 'schema';
			$this->require_true(in_array($mode, array('seed', 'verify', 'digest'), TRUE), 'Synthetic backup mode rejected.');
			$this->schema_state();
			if ($mode === 'seed') { $this->seed(); }
			$images = $this->verify();
			if ($mode === 'digest') { fwrite(STDOUT, json_encode($this->digest($images), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n"); }
			else { fwrite(STDOUT, 'PASS synthetic media backup '.$mode.".\n"); }
			restore_error_handler();
		}
		catch (Throwable $error) { $this->fail($error); }
	}
}
