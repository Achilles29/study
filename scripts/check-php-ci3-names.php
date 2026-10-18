<?php

function study_php_ci3_name_issues($source, $file_name)
{
	$tokens = token_get_all($source);
	$issues = array();
	$token_count = count($tokens);
	$allowed_magic_methods = array(
		'__construct', '__destruct', '__call', '__callStatic', '__get', '__set', '__isset', '__unset',
		'__sleep', '__wakeup', '__serialize', '__unserialize', '__toString', '__invoke', '__set_state',
		'__clone', '__debugInfo'
	);
	// CI3 dispatches this exact controller hook name (system/core/CodeIgniter.php).
	$framework_methods = array('_remap');

	for ($index = 0; $index < $token_count; $index++)
	{
		$token = $tokens[$index];
		if ( ! is_array($token) || $token[0] !== T_FUNCTION)
		{
			continue;
		}

		$name_index = $index + 1;
		while (isset($tokens[$name_index]))
		{
			$next = $tokens[$name_index];
			if (is_array($next) && in_array($next[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), TRUE))
			{
				$name_index++;
				continue;
			}
			$is_reference_marker = $next === '&';
			if (is_array($next) && defined('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG'))
			{
				$is_reference_marker = $is_reference_marker || $next[0] === T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG;
			}
			if ($is_reference_marker)
			{
				$name_index++;
				continue;
			}
			break;
		}

		if ( ! isset($tokens[$name_index]) || ! is_array($tokens[$name_index]) || $tokens[$name_index][0] !== T_STRING)
		{
			continue;
		}

		$function_name = $tokens[$name_index][1];
		if (in_array($function_name, $allowed_magic_methods, TRUE) || in_array($function_name, $framework_methods, TRUE) || preg_match('/\A[a-z][a-z0-9]*(?:_[a-z0-9]+)*\z/', $function_name))
		{
			continue;
		}

		$issues[] = array(
			'file' => $file_name,
			'line' => $tokens[$name_index][2],
			'name' => $function_name
		);
	}

	return $issues;
}

function study_php_ci3_variable_issues($source, $file_name)
{
	$tokens = token_get_all($source);
	$issues = array();
	$allowed_php_variables = array(
		'$this', '$GLOBALS', '$_SERVER', '$_GET', '$_POST', '$_FILES', '$_COOKIE', '$_SESSION', '$_REQUEST', '$_ENV',
		'$_doctypes', '$argc', '$argv', '$http_response_header', '$php_errormsg'
	);

	foreach ($tokens as $token)
	{
		if ( ! is_array($token) || $token[0] !== T_VARIABLE || in_array($token[1], $allowed_php_variables, TRUE))
		{
			continue;
		}

		$variable_name = substr($token[1], 1);
		if (preg_match('/\A[a-z][a-z0-9]*(?:_[a-z0-9]+)*\z/', $variable_name))
		{
			continue;
		}

		$issues[] = array(
			'file' => $file_name,
			'line' => $token[2],
			'name' => $token[1]
		);
	}

	return $issues;
}

function study_php_ci3_class_issues($source, $file_name)
{
	$tokens = token_get_all($source);
	$issues = array();
	$class_tokens = array(T_CLASS, T_INTERFACE, T_TRAIT);

	foreach ($tokens as $index => $token)
	{
		if ( ! is_array($token) || ! in_array($token[0], $class_tokens, TRUE))
		{
			continue;
		}

		$name_index = $index + 1;
		while (isset($tokens[$name_index]) && is_array($tokens[$name_index]) && in_array($tokens[$name_index][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), TRUE))
		{
			$name_index++;
		}

		if ( ! isset($tokens[$name_index]) || ! is_array($tokens[$name_index]) || $tokens[$name_index][0] !== T_STRING)
		{
			continue;
		}

		$class_name = $tokens[$name_index][1];
		if (preg_match('/\A[A-Z][A-Za-z0-9]*(?:_[A-Za-z0-9]+)*\z/', $class_name) && ! preg_match('/[a-z][A-Z]/', $class_name))
		{
			continue;
		}

		$issues[] = array(
			'file' => $file_name,
			'line' => $tokens[$name_index][2],
			'name' => $class_name
		);
	}

	return $issues;
}

function study_php_ci3_assert($condition, $message)
{
	if ( ! $condition)
	{
		fwrite(STDERR, 'FAIL PHP naming checker: ' . $message . "\n");
		exit(1);
	}
}

$valid_fixture = <<<'PHP'
<?php
class Study_model
{
	private $course_id;
	public function __construct() {}
	public function get_course($learner_id)
	{
		$this->course_id = $learner_id;
		return $_SERVER;
	}
	public function __toString() {}
	public function _remap($method, $params = array()) {}
}
function study_helper() {}
function &get_course_reference() {}
$callback = function () {};
PHP;
$invalid_class_fixture = <<<'PHP'
<?php
class StudyModel {}
interface child_contract {}
trait reusableBehavior {}
PHP;
$invalid_fixture = <<<'PHP'
<?php
class Study_model
{
	private $courseId;
	public function getCourse() {}
	public function find_course($courseId) { return $courseId; }
}
function sendReport() {}
function &sendReference() {}
PHP;

study_php_ci3_assert(count(study_php_ci3_name_issues($valid_fixture, 'valid-fixture.php')) === 0, 'accepts CI3 snake_case, PHP magic methods, and anonymous closures');
study_php_ci3_assert(count(study_php_ci3_name_issues($invalid_fixture, 'invalid-fixture.php')) === 3, 'rejects camelCase declarations, including reference-returning functions');
study_php_ci3_assert(count(study_php_ci3_variable_issues($valid_fixture, 'valid-fixture.php')) === 0, 'accepts snake_case properties/variables and PHP superglobals');
study_php_ci3_assert(count(study_php_ci3_variable_issues($invalid_fixture, 'invalid-fixture.php')) === 3, 'rejects camelCase properties and variables');
study_php_ci3_assert(count(study_php_ci3_class_issues($valid_fixture, 'valid-fixture.php')) === 0, 'accepts CI3 class names with an initial capital and underscore-separated words');
study_php_ci3_assert(count(study_php_ci3_class_issues($invalid_class_fixture, 'invalid-class-fixture.php')) === 3, 'rejects camelCase or lowercase class, interface, and trait names');

$project_root = realpath(dirname(__DIR__));
$issues = array();
$files_scanned = 0;
foreach (array('application', 'scripts') as $source_directory)
{
	$directory = $project_root . DIRECTORY_SEPARATOR . $source_directory;
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file_info)
	{
		if ( ! $file_info->isFile() || strtolower($file_info->getExtension()) !== 'php')
		{
			continue;
		}

		$relative_path = str_replace(DIRECTORY_SEPARATOR, '/', substr($file_info->getPathname(), strlen($project_root) + 1));
		// Composer-owned upstream code follows its own public API naming contract.
		if (strpos($relative_path, 'application/third_party/google_auth/vendor/') === 0) { continue; }
		$file_issues = study_php_ci3_name_issues(file_get_contents($file_info->getPathname()), $relative_path);
		$file_source = file_get_contents($file_info->getPathname());
		$file_issues = array_merge($file_issues, study_php_ci3_variable_issues($file_source, $relative_path), study_php_ci3_class_issues($file_source, $relative_path));
		$files_scanned++;
		$issues = array_merge($issues, $file_issues);
	}
}

if (count($issues) > 0)
{
	foreach ($issues as $issue)
	{
		fwrite(STDERR, $issue['file'] . ':' . $issue['line'] . ' symbol ' . $issue['name'] . ' must follow the CI3 identifier naming rule.' . "\n");
	}
	exit(1);
}

fwrite(STDOUT, 'PASS CI3 class/function/method/property/variable naming in ' . $files_scanned . ' PHP files (self-tests passed).' . "\n");
