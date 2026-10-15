<?php
if (PHP_SAPI !== 'cli') { exit(1); }
define('BASEPATH', dirname(__DIR__).'/system/');
define('FCPATH', dirname(__DIR__).'/');
define('ENVIRONMENT', in_array('--production', $argv, TRUE) ? 'production' : 'development');
require dirname(__DIR__).'/application/libraries/Lesson_image_store.php';

class Lesson_image_test_config
{
	public $root = '';
	public function load($name, $group)
	{
		if ($name !== 'lesson_media' || $group !== TRUE) { throw new RuntimeException('Unexpected config load.'); }
	}
	public function item($name, $group)
	{
		if ($name !== 'root' || $group !== 'lesson_media') { throw new RuntimeException('Unexpected config field.'); }
		return $this->root;
	}
}
class Lesson_image_test_context
{
	public $config;
	public function __construct()
	{
		$this->config = new Lesson_image_test_config();
	}
}
$context = new Lesson_image_test_context();
function &get_instance()
{
	global $context;
	return $context;
}
function expect_image($condition, $message)
{
	if ( ! $condition) { throw new RuntimeException($message); }
}
function image_config_value()
{
	$config = array();
	require dirname(__DIR__).'/application/config/lesson_media.php';
	return $config['root'];
}
function count_image_files($directory)
{
	$entries = scandir($directory);
	return count(array_diff($entries, array('.', '..')));
}
function synthetic_png($width, $height)
{
	$image = imagecreatetruecolor($width, $height);
	imagealphablending($image, FALSE);
	imagesavealpha($image, TRUE);
	$color = imagecolorallocatealpha($image, 48, 112, 208, 50);
	imagefill($image, 0, 0, $color);
	ob_start();
	try { imagepng($image); return ob_get_contents(); }
	finally { ob_end_clean(); }
}
function synthetic_jpeg()
{
	$image = imagecreatetruecolor(12, 9);
	$color = imagecolorallocate($image, 220, 128, 50);
	imagefill($image, 0, 0, $color);
	ob_start();
	try { imagejpeg($image, NULL, 85); return ob_get_contents(); }
	finally { ob_end_clean(); }
}

$previous_root = getenv('STUDY_MEDIA_ROOT');
putenv('STUDY_MEDIA_ROOT');
expect_image(image_config_value() === (ENVIRONMENT === 'production' ? '' : dirname(rtrim(FCPATH, '/\\'), 2).'/study-private/lesson-images'), 'Environment-dependent default root mismatch.');
putenv('STUDY_MEDIA_ROOT=explicit-private-test-root');
expect_image(image_config_value() === 'explicit-private-test-root', 'Explicit root config must be preserved for later validation.');
if ($previous_root === FALSE) { putenv('STUDY_MEDIA_ROOT'); } else { putenv('STUDY_MEDIA_ROOT='.$previous_root); }

if (ENVIRONMENT === 'production')
{
	$store = new Lesson_image_store();
	expect_image($store->upload_available() === FALSE && $store->from_local_file(__FILE__, 'fixture.png')['status'] === 'invalid', 'Production must reject local fixtures and absent storage configuration.');
	echo "PASS production rejects local fixture API and missing private storage; config requires explicit root.\n";
	exit(0);
}

$temporary_parent = realpath(sys_get_temp_dir());
expect_image(is_string($temporary_parent), 'Temporary parent is unavailable.');
$web_root = realpath(dirname(rtrim(FCPATH, '/\\')));
$normal_temporary_parent = strtolower(str_replace('\\', '/', $temporary_parent));
$normal_web_root = strtolower(str_replace('\\', '/', $web_root));
expect_image($normal_temporary_parent !== $normal_web_root && strpos($normal_temporary_parent, $normal_web_root.'/') !== 0, 'Temporary fixtures must remain outside the full document root.');
$test_root = $temporary_parent.DIRECTORY_SEPARATOR.'study-lesson-image-'.bin2hex(random_bytes(12));
$storage = $test_root.DIRECTORY_SEPARATOR.'storage';
$source = $test_root.DIRECTORY_SEPARATOR.'source.png';
$links = array();
$created_files = array();
expect_image(mkdir($test_root, 0700) && mkdir($storage, 0700), 'Private test directories could not be created.');
try
{
	$context->config->root = $storage;
	$store = new Lesson_image_store();
	// This known 1px PNG also proves existing-file delivery without GD or fileinfo.
	$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==', TRUE);
	$key = str_repeat('a', 64).'.png';
	$stored_path = $storage.DIRECTORY_SEPARATOR.$key;
	$created_files[] = $stored_path;
	expect_image(file_put_contents($stored_path, $png) === strlen($png), 'Known PNG fixture write failed.');
	$metadata = array('storage_key' => $key, 'mime_type' => 'image/png', 'byte_size' => strlen($png), 'width' => 1, 'height' => 1, 'sha256' => hash('sha256', $png));
	expect_image($store->read($metadata) === $png, 'Existing normalized bytes must be readable without image extensions.');
	foreach (array(array('storage_key' => '../'.$key), array('storage_key' => $key.':hidden'), array('storage_key' => array($key)), array('byte_size' => 0), array('byte_size' => strlen($png) + 1), array('byte_size' => Lesson_image_store::MAX_STORED_BYTES + 1), array('byte_size' => array(1)), array('width' => 4097), array('width' => 2001, 'height' => 2000), array('mime_type' => 'image/jpeg'), array('sha256' => str_repeat('0', 64))) as $invalid)
	{
		expect_image($store->read(array_replace($metadata, $invalid)) === FALSE, 'Invalid metadata was read.');
	}
	foreach (array('../'.$key, $key.':hidden', $key."\0", strtoupper($key), str_repeat('b', 64).'.jpeg', array($key)) as $invalid)
	{
		expect_image($store->remove($invalid) === FALSE && is_file($stored_path), 'Invalid key removed a file.');
	}
	file_put_contents($stored_path, $png.'tampered');
	expect_image($store->read($metadata) === FALSE, 'Tampered size/hash was read.');
	file_put_contents($stored_path, $png);
	$png[20] = chr(ord($png[20]) ^ 1);
	file_put_contents($stored_path, $png);
	expect_image($store->read($metadata) === FALSE, 'Same-size hash mismatch was read.');
	file_put_contents($stored_path, 'not-an-image');
	$fake_metadata = array_replace($metadata, array('byte_size' => 12, 'sha256' => hash('sha256', 'not-an-image')));
	expect_image($store->read($fake_metadata) === FALSE, 'Invalid magic bytes were read.');
	expect_image($store->remove($key) === TRUE && ! is_file($stored_path) && $store->remove($key) === FALSE, 'Exact canonical deletion or absent-key rejection failed.');
	foreach (array(NULL, array(), array('name' => array('x.png')), array('name' => 'x.png', 'type' => 'image/png', 'tmp_name' => $source, 'size' => 1, 'error' => UPLOAD_ERR_PARTIAL), array('name' => 'x.png', 'type' => 'image/png', 'tmp_name' => $source, 'size' => 1, 'error' => 0)) as $file)
	{
		expect_image($store->from_upload($file)['status'] === 'invalid', 'Malformed or non-genuine upload was accepted.');
	}
	foreach (array('../x.png', '..\\x.png', 'x.php.png', 'x.png.php', 'x.png:evil', "x\0.png", 'x..png', '.png', 'x.svg', 'x.webp', 'x.gif', ' x.png', 'x.png ', array('x.png')) as $name)
	{
		expect_image($store->from_local_file($source, $name)['status'] === 'invalid', 'Unsafe original filename was accepted.');
	}
	foreach (array('', $test_root.'/missing', FCPATH, dirname(rtrim(FCPATH, '/\\')), dirname(dirname(rtrim(FCPATH, '/\\'))), $storage.'/../storage', $storage.':alternate', array($storage)) as $bad_root)
	{
		$context->config->root = $bad_root;
		$bad_store = new Lesson_image_store();
		expect_image($bad_store->upload_available() === FALSE && $bad_store->read($metadata) === FALSE && $bad_store->remove($key) === FALSE, 'Unsafe/missing private root was accepted.');
	}
	$context->config->root = $storage;
	$store = new Lesson_image_store();
	$has_image_support = extension_loaded('gd') && extension_loaded('fileinfo');
	if ( ! $has_image_support)
	{
		expect_image($store->upload_available() === FALSE && $store->from_local_file($source, 'fixture.png')['status'] === 'unavailable', 'Missing image dependencies must fail closed.');
		echo "PASS stored-image read/hash/size/magic, strict paths/keys/names, upload authenticity and missing-GD/fileinfo fail-closed; conversion not available on this PHP runtime.\n";
	}
	else
	{
		expect_image($store->upload_available(), 'GD/fileinfo with safe private root should enable upload.');
		$trailing = "<?php echo 'UNTRUSTED_TRAILER'; ?>";
		foreach (array('png', 'jpg', 'jpeg') as $extension)
		{
			$bytes = $extension === 'png' ? synthetic_png(12, 9) : synthetic_jpeg();
			// JPEG COM and PNG trailing bytes must not survive decode/re-encode.
			if ($extension !== 'png') { $comment = 'PRIVATE_JPEG_COMMENT'; $bytes = substr($bytes, 0, 2)."\xff\xfe".pack('n', strlen($comment) + 2).$comment.substr($bytes, 2); }
			$created_files[] = $source;
			file_put_contents($source, $bytes.$trailing);
			$result = $store->from_local_file($source, 'Gambar belajar.'.$extension);
			expect_image($result['status'] === 'ok', 'Valid synthetic image conversion failed: '.$extension.' ('.$result['status'].').');
			$image = $result['image'];
			$created_files[] = $storage.DIRECTORY_SEPARATOR.$image['storage_key'];
			expect_image(preg_match('/\A[a-f0-9]{64}\.(png|jpg)\z/', $image['storage_key']) && $image['width'] === 12 && $image['height'] === 9 && $image['byte_size'] <= Lesson_image_store::MAX_STORED_BYTES, 'Normalized metadata mismatch.');
			$read = $store->read($image);
			expect_image(is_string($read) && strpos($read, 'UNTRUSTED_TRAILER') === FALSE && strpos($read, 'PRIVATE_JPEG_COMMENT') === FALSE && hash('sha256', $read) === $image['sha256'], 'Re-encoding did not remove untrusted payload/metadata.');
			if ($extension === 'png') { $decoded = imagecreatefromstring($read); $color = imagecolorat($decoded, 0, 0); expect_image(($color >> 24 & 0x7f) === 50, 'PNG transparency was not preserved.'); unset($decoded); }
			$repeated = $store->from_local_file($source, 'Gambar belajar.'.$extension);
			expect_image($repeated['status'] === 'ok' && $repeated['image']['storage_key'] !== $image['storage_key'] && $repeated['image']['sha256'] === $image['sha256'], 'Repeated image must use a new random key without overwriting.');
			$created_files[] = $storage.DIRECTORY_SEPARATOR.$repeated['image']['storage_key'];
			expect_image($store->remove($image['storage_key']) && $store->remove($repeated['image']['storage_key']), 'Generated canonical image cleanup failed.');
		}
		$invalid_bytes = array('<svg xmlns="http://www.w3.org/2000/svg"></svg>', '<?php echo 1; ?>', str_repeat('x', Lesson_image_store::MAX_UPLOAD_BYTES + 1), substr(synthetic_png(12, 9), 0, 40), synthetic_png(4097, 1), synthetic_png(2001, 2000));
		foreach ($invalid_bytes as $bytes)
		{
			file_put_contents($source, $bytes);
			$before = count_image_files($storage);
			$result = $store->from_local_file($source, 'rejected.png');
			expect_image($result['status'] === 'invalid' && count_image_files($storage) === $before && strpos($result['message'], $storage) === FALSE, 'Invalid/corrupt/oversized/dimension image left storage files or leaked a path.');
		}
		file_put_contents($source, synthetic_png(12, 9));
		expect_image($store->from_local_file($source, 'wrong.jpg')['status'] === 'invalid', 'PNG with JPEG extension was accepted.');
		file_put_contents($source, synthetic_jpeg());
		expect_image($store->from_local_file($source, 'wrong.png')['status'] === 'invalid', 'JPEG with PNG extension was accepted.');
		$maximum_input = synthetic_png(1, 1);
		file_put_contents($source, $maximum_input.str_repeat('x', Lesson_image_store::MAX_UPLOAD_BYTES - strlen($maximum_input)));
		$maximum_result = $store->from_local_file($source, 'gambar-batas.PNG');
		expect_image($maximum_result['status'] === 'ok', 'Valid image at the exact input-byte limit must normalize successfully.');
		$created_files[] = $storage.DIRECTORY_SEPARATOR.$maximum_result['image']['storage_key'];
		expect_image($store->remove($maximum_result['image']['storage_key']), 'Boundary image cleanup failed.');
		file_put_contents($source, synthetic_png(2000, 2000));
		$previous_memory_limit = ini_get('memory_limit');
		try
		{
			ini_set('memory_limit', '64M');
			expect_image($store->from_local_file($source, 'memory-bound.png')['status'] === 'unavailable', 'Insufficient decode memory must fail closed before GD allocation.');
		}
		finally { ini_set('memory_limit', $previous_memory_limit); }
		$context->config->root = $source;
		$source_file_store_root = new Lesson_image_store();
		expect_image($source_file_store_root->upload_available() === FALSE, 'A file cannot serve as a storage directory.');
		$context->config->root = $storage;
		$previous_document_root = $_SERVER['DOCUMENT_ROOT'] ?? NULL;
		$_SERVER['DOCUMENT_ROOT'] = $test_root;
		expect_image($store->upload_available() === FALSE && $store->read($metadata) === FALSE, 'Explicit full document root must prevent storage beneath it.');
		if ($previous_document_root === NULL) { unset($_SERVER['DOCUMENT_ROOT']); } else { $_SERVER['DOCUMENT_ROOT'] = $previous_document_root; }
		echo "PASS PNG/JPEG decode/re-encode, transparency, metadata/trailing-payload removal, random keys, dimension/pixel/byte limits, extension mismatch and fail-closed storage roots.\n";
	}
	// Symlink tests run only when this OS/account supports creating them; no privilege elevation.
	$link = $test_root.DIRECTORY_SEPARATOR.'linked-storage';
	$can_link = FALSE;
	set_error_handler(function ($severity, $message, $file, $line) {
		throw new ErrorException($message, 0, $severity, $file, $line);
	}, E_WARNING);
	try { $can_link = symlink($storage, $link); }
	catch (Throwable $error) { $can_link = FALSE; }
	finally { restore_error_handler(); }
	if ($can_link)
	{
		$links[] = $link;
		$context->config->root = $link;
		$link_store = new Lesson_image_store();
		expect_image($link_store->upload_available() === FALSE && $link_store->read($metadata) === FALSE && $link_store->remove($key) === FALSE, 'Symlink storage root was accepted.');
		echo "PASS storage-root symlink rejected.\n";
	}
	else { echo "SKIP symlink creation unavailable for this OS/account; no symlink-test success claimed.\n"; }
}
finally
{
	// Exact paths created by this test only; no recursive or broad-directory deletion.
	foreach ($links as $link) { if (is_link($link)) { unlink($link); } }
	foreach (array_unique($created_files) as $file)
	{
		if (strpos($file, $test_root.DIRECTORY_SEPARATOR) !== 0) { throw new RuntimeException('Test cleanup path escaped its private directory.'); }
		if (is_file($file) && ! is_link($file)) { unlink($file); }
	}
	if (is_dir($storage)) { rmdir($storage); }
	if (is_dir($test_root)) { rmdir($test_root); }
	echo "PASS exact private temporary image fixtures removed.\n";
}
