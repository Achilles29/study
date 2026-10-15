<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');

class CI_Model
{
	public $db;
}
require __DIR__.'/../application/models/Content_model.php';

class Course_list_test_result
{
	public function result_array()
	{
		return array(array('id' => '17', 'title' => 'Synthetic course', 'summary' => 'Synthetic summary'));
	}
}

class Course_list_test_builder
{
	public $calls = array();

	public function __call($method, $arguments)
	{
		if ( ! in_array($method, array('select', 'from', 'join', 'where', 'group_start', 'like', 'or_like', 'group_end', 'order_by', 'limit', 'get', 'count_all_results'), TRUE))
		{
			throw new RuntimeException('Unexpected query-builder operation.');
		}
		$this->calls[] = array($method, $arguments);
		if ($method === 'get') { return new Course_list_test_result(); }
		if ($method === 'count_all_results') { return 17; }
		return $this;
	}

	public function matching_calls($methods)
	{
		return array_values(array_filter($this->calls, static function ($call) use ($methods) {
			return in_array($call[0], $methods, TRUE);
		}));
	}
}

$failures = array();
function course_list_assert($condition, $message)
{
	global $failures;
	if ( ! $condition) { $failures[$message] = TRUE; }
}

$search = "50%_ ' OR 1=1 --";
$search_calls = array(array('group_start', array()), array('like', array('c.title', $search)), array('or_like', array('c.summary', $search)), array('group_end', array()));
$published_filter = array(array('where', array('c.status', 'published')));
$cases = array(
	array('public defaults', TRUE, array(), $published_filter, 12, 0),
	array('public category and literal search', TRUE, array('status' => 'draft', 'category_id' => '7', 'q' => $search), array_merge($published_filter, array(array('where', array('c.category_id', 7))), $search_calls), '12', '24'),
	array('public empty filters', TRUE, array('category_id' => '0', 'q' => '', 'status' => ''), $published_filter, 6, 18),
	array('public negative category', TRUE, array('category_id' => -2), $published_filter, 9, 9),
	array('admin defaults', FALSE, array(), array(), 20, 0),
	array('admin status and literal search', FALSE, array('status' => 'review', 'category_id' => 7, 'q' => $search), array_merge(array(array('where', array('c.status', 'review'))), $search_calls), '20', '40'),
	array('admin empty filters', FALSE, array('status' => '', 'q' => '', 'category_id' => 7), array(), 20, 60),
	array('admin zero search is not empty', FALSE, array('q' => '0'), array(array('group_start', array()), array('like', array('c.title', '0')), array('or_like', array('c.summary', '0')), array('group_end', array())), 15, 30)
);
$required_fields = array('c.id', 'c.title', 'c.slug', 'c.summary', 'c.target_group', 'c.age_min', 'c.age_max', 'c.estimated_minutes', 'c.status', 'c.is_free', 'c.updated_at', 'cat.name AS category_name', 'u.display_name AS author_name');
$filter_methods = array('from', 'where', 'group_start', 'like', 'or_like', 'group_end');
foreach ($cases as $case)
{
	list($label, $published_only, $filters, $expected_filters, $limit, $offset) = $case;
	$model = new Content_model();
	$list_builder = new Course_list_test_builder();
	$model->db = $list_builder;
	$rows = $model->list_courses($filters, $limit, $offset, $published_only);
	course_list_assert($rows === (new Course_list_test_result())->result_array(), $label.': result rows must pass through unchanged.');
	$selects = $list_builder->matching_calls(array('select'));
	course_list_assert(count($selects) === 1 && count($selects[0][1]) === 1 && is_string($selects[0][1][0]), 'List must use one explicit field projection.');
	$projection = $selects[0][1][0] ?? '';
	$selected_fields = preg_split('/\s*,\s*/', trim($projection));
	course_list_assert(strpos($projection, '*') === FALSE, 'Course list must not select a wildcard.');
	foreach ($required_fields as $field)
	{
		course_list_assert(in_array($field, $selected_fields, TRUE), 'Course list projection missing required field: '.$field.'.');
	}
	course_list_assert($list_builder->matching_calls(array('join')) === array(array('join', array('st_categories cat', 'cat.id = c.category_id', 'left')), array('join', array('st_users u', 'u.id = c.author_user_id', 'left'))), $label.': category/author aliases require their left joins.');
	$expected_filters = array_merge(array(array('from', array('st_courses c'))), $expected_filters);
	course_list_assert($list_builder->matching_calls($filter_methods) === $expected_filters, $label.': list filter grouping or values differ.');
	$timestamp = $published_only ? 'c.published_at' : 'c.updated_at';
	course_list_assert($list_builder->matching_calls(array('order_by')) === array(array('order_by', array($timestamp, 'DESC')), array('order_by', array('c.id', 'DESC'))), ($published_only ? 'Public' : 'Admin').' list needs timestamp DESC then unique id DESC ordering.');
	course_list_assert($list_builder->matching_calls(array('limit')) === array(array('limit', array((int) $limit, (int) $offset))), $label.': limit/offset must be integer values in the correct order.');
	course_list_assert(end($list_builder->calls) === array('get', array()), $label.': list must execute the composed query.');
	$count_builder = new Course_list_test_builder();
	$model->db = $count_builder;
	course_list_assert($model->count_courses($filters, $published_only) === 17, $label.': count result must pass through unchanged.');
	course_list_assert($count_builder->matching_calls($filter_methods) === $expected_filters && $count_builder->matching_calls($filter_methods) === $list_builder->matching_calls($filter_methods), $label.': list and count must use identical scoped filters.');
	course_list_assert($count_builder->matching_calls(array('limit', 'order_by', 'select', 'join')) === array(), $label.': count must not inherit pagination, sorting or list-only joins.');
	course_list_assert(end($count_builder->calls) === array('count_all_results', array()), $label.': count must execute the composed query.');
}
if ($failures)
{
	foreach (array_keys($failures) as $failure) { fwrite(STDERR, 'FAIL '.$failure."\n"); }
	exit(1);
}
fwrite(STDOUT, 'PASS course list query: '.count($cases)." cases, explicit card fields including summary, scoped list/count filters, stable timestamp/id ordering and integer pagination.\n");
fwrite(STDOUT, "LIMITATION recording query-builder unit only; no database SQL execution, collation, or real HTTP rendering is exercised.\n");
