<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');
define('FCPATH', dirname(__DIR__).'/');
class CI_Controller {}
class Structure_test_redirect extends RuntimeException
{
	public $path;
	public $status;
	public function __construct($path, $status)
	{
		parent::__construct('Test redirect'); $this->path = $path; $this->status = $status;
	}
}
function redirect($path, $method = 'auto', $status = NULL)
{
	throw new Structure_test_redirect($path, $status);
}
function show_error($message, $status = 500, $heading = '')
{
	throw new RuntimeException($message, $status);
}
function show_404()
{
	throw new RuntimeException('Not found', 404);
}
function html_escape($value)
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
function site_url($path)
{
	return 'http://localhost/study/index.php/'.$path;
}
function base_url($path)
{
	return 'http://localhost/study/'.$path;
}
// Match CI3's action handling: form_open does not escape its action again.
function form_open($path, $attributes = array())
{
	return '<form action="'.site_url($path).'" method="post">';
}
function form_close()
{
	return '</form>';
}
require FCPATH.'application/core/MY_Controller.php';
require FCPATH.'application/controllers/Course_structure.php';
require FCPATH.'application/helpers/study_ui_helper.php';
class Structure_test_input
{
	public $query;
	public $fields;
	public function __construct($query, $fields)
	{
		$this->query = $query; $this->fields = $fields;
	}
	public function get($name, $clean = FALSE)
	{
		return $this->query[$name] ?? NULL;
	}
	public function post($name, $clean = FALSE)
	{
		return $this->fields[$name] ?? NULL;
	}
	public function method($upper = FALSE)
	{
		return $this->fields === NULL ? 'GET' : 'POST';
	}
}
class Structure_test_loader
{
	public $rendered_html = '';
	public function model($models)
	{
	}
	public function view($view, $data)
	{
		extract($data);
		ob_start();
		require FCPATH.'application/views/'.$view.'.php';
		$this->rendered_html .= ob_get_clean();
	}
}
class Structure_test_output
{
	public $status = 200;
	public function set_header($header)
	{
	}
	public function set_status_header($status)
	{
		$this->status = $status;
	}
}
class Structure_test_validation
{
	public function set_rules($field, $label, $rules)
	{
	}
	public function run()
	{
		return TRUE;
	}
	public function error_array()
	{
		return array();
	}
}
class Structure_test_session
{
	public function set_flashdata($key, $value)
	{
	}
	public function flashdata($key)
	{
		return NULL;
	}
}
class Structure_test_content
{
	public function get_course($id)
	{
		return array('id' => $id, 'author_user_id' => 7, 'status' => 'draft', 'title' => 'Draf <aman>', 'content_version' => 4);
	}
}
class Structure_test_model
{
	public $rows = array(array('id' => '11', 'title' => 'Bagian satu', 'sort_order' => 1), array('id' => '22', 'title' => 'Bagian dua', 'sort_order' => 2));
	public $total = 21;
	public $result = 'ok';
	public $total_after = NULL;
	public $calls = 0;
	public $read_calls = array();
	public $unreadable_after_change = FALSE;
	private function record_read($name)
	{
		$this->read_calls[] = $name;
		if ($this->unreadable_after_change && $this->calls > 0) { throw new RuntimeException('Persistence became unavailable after mutation.'); }
	}
	public function sections($course_id)
	{
		$this->record_read('sections');
		return $this->rows;
	}
	public function lesson_count($course_id)
	{
		$this->record_read('lesson_count');
		return $this->total;
	}
	public function lessons($course_id, $offset)
	{
		$this->record_read('lessons');
		return array(array('id' => '44', 'title' => 'Pelajaran <aman>', 'section_id' => '11', 'section_title' => 'Bagian satu', 'sort_order' => 1));
	}
	public function change($course_id, $actor_id, $revision, $action, $fields)
	{
		$this->calls++;
		if ($this->total_after !== NULL) { $this->total = $this->total_after; }
		return $this->result;
	}
}
class Structure_test_controller extends Course_structure
{
	public $input;
	public $load;
	public $output;
	public $form_validation;
	public $session;
	private $content_model;
	private $course_structure_model;
	public $rendered;
	public $use_real_renderer = FALSE;
	public function __construct($query = array(), $fields = NULL)
	{
		$this->current_user = array('id' => 7, 'roles' => array('content_editor'), 'account_kind' => 'adult', 'display_name' => 'Editor uji');
		$this->input = new Structure_test_input($query, $fields);
		$this->load = new Structure_test_loader();
		$this->output = new Structure_test_output();
		$this->form_validation = new Structure_test_validation();
		$this->session = new Structure_test_session();
		$this->content_model = new Structure_test_content();
		$this->course_structure_model = new Structure_test_model();
	}
	public function __get($name)
	{
		if ($name === 'Content_model') { return $this->content_model; }
		if ($name === 'Course_structure_model') { return $this->course_structure_model; }
		throw new RuntimeException('Unexpected fixture model property.');
	}
	protected function render_page($view, $data = array())
	{
		$this->rendered = $data;
		if ($this->use_real_renderer) { parent::render_page($view, $data); }
	}
}
function structure_context_assert($condition, $label)
{
	if ( ! $condition) { throw new RuntimeException($label); }
}
function structure_context_fields($action = 'update_section', $section_id = '22')
{
	return array('action' => $action, 'revision' => '4', 'section_id' => $section_id, 'lesson_id' => '44', 'title' => 'Judul baru', 'sort_order' => '3');
}
function structure_context_run($controller)
{
	try { $controller->index('5'); }
	catch (Structure_test_redirect $redirect) { return $redirect; }
	return NULL;
}
function structure_context_html($controller)
{
	extract($controller->rendered);
	ob_start();
	require FCPATH.'application/views/admin/structure.php';
	return ob_get_clean();
}
$controller = new Structure_test_controller(array('halaman' => '2', 'bagian' => '22', 'return' => 'https://evil.invalid/'));
structure_context_run($controller);
$html = structure_context_html($controller);
structure_context_assert($controller->rendered['structure_context_path'] === 'kelola/kursus/5/susunan?halaman=2&bagian=22', 'GET context should use the selected section and current page only');
structure_context_assert(substr_count($html, 'action="http://localhost/study/index.php/kelola/kursus/5/susunan?halaman=2&amp;bagian=22"') === 3, 'All three POST forms should preserve encoded context');
structure_context_assert(strpos($html, 'name="halaman" value="2"') !== FALSE, 'GET section selection should retain page');
structure_context_assert(strpos($html, '?halaman=1&amp;bagian=22') !== FALSE && strpos($html, '?halaman=3&amp;bagian=22') !== FALSE, 'Both pagination links should retain section');
structure_context_assert(strpos($html, 'evil.invalid') === FALSE && strpos($html, '<aman>') === FALSE, 'Unapproved query values and markup must not be echoed');
foreach (array(NULL, array('22'), '99', '22x', '00022', '220000000000000000000', '<script>') as $invalid_id)
{
	$controller = new Structure_test_controller(array('bagian' => $invalid_id, 'halaman' => '999'));
	structure_context_run($controller);
	structure_context_assert($controller->rendered['structure_context_path'] === 'kelola/kursus/5/susunan?halaman=3&bagian=11', 'Invalid selection must fall back to the first owned section and clamp page');
}
foreach (array(array('1'), '-2', 'bad', '0') as $invalid_page)
{
	$controller = new Structure_test_controller(array('bagian' => '22', 'halaman' => $invalid_page));
	structure_context_run($controller);
	structure_context_assert($controller->rendered['page'] === 1, 'Malformed page must fall back to page one');
}
foreach (array('ok', 'no_change') as $result)
{
	foreach (array('update_section', 'create_section', 'move_lesson') as $action)
	{
		$controller = new Structure_test_controller(array('halaman' => '2', 'bagian' => '11'), structure_context_fields($action));
		$controller->Course_structure_model->result = $result;
		$redirect = structure_context_run($controller);
		$expected_section = $action === 'update_section' ? '22' : '11';
		structure_context_assert($redirect && $redirect->status === 303 && $redirect->path === 'kelola/kursus/5/susunan?halaman=2&bagian='.$expected_section, 'POST should retain valid independent navigation context for '.$result.'/'.$action);
	}
}
$controller = new Structure_test_controller(array('halaman' => '999', 'bagian' => '22'), structure_context_fields('move_lesson', '11'));
$controller->Course_structure_model->total_after = 11;
$redirect = structure_context_run($controller);
structure_context_assert($redirect->path === 'kelola/kursus/5/susunan?halaman=3&bagian=22', 'Successful POST must use its already bounded context without post-commit reads');
parse_str(parse_url($redirect->path, PHP_URL_QUERY), $destination_query);
$destination = new Structure_test_controller($destination_query);
$destination->Course_structure_model->total = 11;
structure_context_run($destination);
structure_context_assert($destination->rendered['page'] === 2 && $destination->rendered['selected_section']['id'] === '22', 'Destination GET must refresh context and clamp if another change reduces its page count');
$controller = new Structure_test_controller(array('bagian' => '22', 'halaman' => '2'), structure_context_fields('update_section', '99'));
$controller->Course_structure_model->result = 'invalid';
structure_context_run($controller);
$html = structure_context_html($controller);
structure_context_assert($controller->output->status === 400 && $controller->rendered['has_invalid_section'], 'Invalid update selection should be explicit');
structure_context_assert(strpos($html, 'name="action" value="update_section"') === FALSE && strpos($html, 'Bagian pada kiriman tidak tersedia') !== FALSE, 'Foreign update must not silently edit fallback section');
structure_context_assert(strpos($html, '?halaman=2&amp;bagian=22">Muat ulang susunan') !== FALSE, 'Error reload must preserve validated context');
$controller = new Structure_test_controller(array('bagian' => '22', 'halaman' => '2'), structure_context_fields('move_lesson', '99'));
$controller->Course_structure_model->result = 'invalid';
structure_context_run($controller);
$html = structure_context_html($controller);
structure_context_assert(strpos($html, '<option value="" selected disabled>Pilih kembali bagian tujuan</option>') !== FALSE, 'Foreign destination should require an explicit new choice');
structure_context_assert(strpos($html, 'aria-describedby="lesson-section-error-44"') !== FALSE && strpos($html, 'id="lesson-section-error-44"') !== FALSE, 'Invalid destination needs an accessible inline error');
foreach (array('conflict' => 409, 'unavailable' => 503, 'uncertain' => 503) as $result => $status)
{
	$controller = new Structure_test_controller(array('bagian' => '22', 'halaman' => '2'), structure_context_fields('move_lesson'));
	$controller->Course_structure_model->result = $result;
	$controller->Course_structure_model->unreadable_after_change = TRUE;
	$controller->use_real_renderer = TRUE;
	$redirect = structure_context_run($controller);
	$html = $controller->load->rendered_html;
	structure_context_assert($redirect === NULL && $controller->Course_structure_model->calls === 1, 'Failure must render recovery without redirecting or repeating POST');
	structure_context_assert($controller->Course_structure_model->read_calls === array('sections', 'lesson_count', 'lessons'), 'Recovery and shared layout must not read persistence after mutation');
	structure_context_assert($controller->output->status === $status && substr_count($html, '<fieldset disabled>') === 3, 'Stale or unavailable result must disable all mutation forms');
	structure_context_assert(strpos($html, '<!doctype html>') !== FALSE && strpos($html, '</html>') !== FALSE && strpos($html, 'Pelajaran &lt;aman&gt;') !== FALSE, 'Real base renderer must produce the complete snapshot recovery page');
	structure_context_assert(strpos($html, '?halaman=2&amp;bagian=22">Muat ulang susunan') !== FALSE, 'Recovery link must retain navigation context');
	if ($status === 503) { structure_context_assert(strpos($html, 'keadaan sebelum upaya penyimpanan') !== FALSE, 'Unavailable recovery must identify its pre-mutation snapshot'); }
	if ($result === 'uncertain') { structure_context_assert(strpos($html, 'Status penyimpanan belum dapat dipastikan.') !== FALSE && strpos($html, 'Tidak ada perubahan yang diterapkan.') === FALSE, 'Uncertain commit must not promise rollback'); }
}
foreach (array('ok', 'no_change') as $result)
{
	$controller = new Structure_test_controller(array('bagian' => '22', 'halaman' => '2'), structure_context_fields('move_lesson'));
	$controller->Course_structure_model->result = $result;
	$controller->Course_structure_model->unreadable_after_change = TRUE;
	$redirect = structure_context_run($controller);
	structure_context_assert($redirect && $redirect->status === 303 && $redirect->path === 'kelola/kursus/5/susunan?halaman=2&bagian=22', 'Confirmed result must redirect even when later database reads would fail');
	structure_context_assert($controller->Course_structure_model->calls === 1 && $controller->Course_structure_model->read_calls === array('sections', 'lesson_count', 'lessons'), 'Confirmed result must not repeat mutation or read persistence after it');
}
$controller = new Structure_test_controller(array('bagian' => '22', 'halaman' => '2'));
$controller->Course_structure_model->rows = array();
$controller->Course_structure_model->total = 0;
structure_context_run($controller);
structure_context_assert($controller->rendered['structure_context_path'] === 'kelola/kursus/5/susunan?halaman=1', 'Empty course must omit a nonexistent selection');
echo "PASS course structure context: controller/view state, local redirects and destination clamps, all forms and pagination, invalid section recovery, locked conflict/unavailable/uncertain forms, and zero post-mutation reads through the shared layout or successful redirect. Uses stub persistence and validation; does not replace HTTP/database disconnect tests.\n";
