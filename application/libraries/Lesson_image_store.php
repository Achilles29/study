<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Lesson_image_store
{
	const MAX_UPLOAD_BYTES = 3145728;
	const MAX_STORED_BYTES = 6291456;
	const MAX_DIMENSION = 4096;
	const MAX_PIXELS = 4000000;
	private $root = '';

	public function __construct()
	{
		$ci =& get_instance();
		$ci->config->load('lesson_media', TRUE);
		$value = $ci->config->item('root', 'lesson_media');
		$this->root = is_string($value) ? $value : '';
	}

	private function guarded($callback)
	{
		set_error_handler(function ($severity, $message, $file, $line) {
			throw new ErrorException($message, 0, $severity, $file, $line);
		}, E_WARNING | E_NOTICE);
		try { return $callback(); }
		finally { restore_error_handler(); }
	}

	private function normalized_path($path)
	{
		$path = rtrim(str_replace('\\', '/', $path), '/');
		return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
	}

	private function within($path, $directory)
	{
		return $path === $directory || strpos($path, $directory.'/') === 0;
	}

	private function canonical_path($path)
	{
		if ( ! is_string($path) || $path === '' || preg_match('/[\x00-\x1F\x7F]/', $path)) { return FALSE; }
		$slash_path = str_replace('\\', '/', $path);
		if ( ! preg_match('~\A(?:[A-Za-z]:/|/(?!/))~', $slash_path) || preg_match('~(?:\A|/)\.{1,2}(?:/|\z)~', $slash_path) || strpos(substr($slash_path, preg_match('/\A[A-Za-z]:/', $slash_path) ? 2 : 0), ':') !== FALSE) { return FALSE; }
		clearstatcache(TRUE, $path);
		$resolved = realpath($path);
		if ($resolved === FALSE || is_link($path) || $this->normalized_path($resolved) !== $this->normalized_path($path)) { return FALSE; }
		// realpath equality above rejects aliases in the complete path. Also check
		// ancestors within PHP's permitted trees, but do not probe /www or / when
		// open_basedir forbids them: its warning would incorrectly reject valid media.
		$permitted = array();
		$basedir = (string) ini_get('open_basedir');
		if ($basedir !== '')
		{
			foreach (explode(PATH_SEPARATOR, $basedir) as $directory)
			{
				$boundary = realpath($directory);
				if ($boundary !== FALSE) { $permitted[] = $this->normalized_path($boundary); }
			}
		}
		$ancestor = $resolved;
		do {
			if ($basedir !== '')
			{
				$inside = FALSE;
				foreach ($permitted as $boundary) { if ($this->within($this->normalized_path($ancestor), $boundary)) { $inside = TRUE; break; } }
				if ( ! $inside) { break; }
			}
			if (is_link($ancestor)) { return FALSE; }
			$parent = dirname($ancestor);
			if ($parent === $ancestor) { break; }
			$ancestor = $parent;
		} while ($ancestor !== '');
		return $resolved;
	}

	private function storage_root($write = FALSE)
	{
		$root = $this->canonical_path($this->root);
		if ($root === FALSE || ! is_dir($root) || ! is_readable($root) || ($write && ! is_writable($root))) { return FALSE; }
		$blocked = array(FCPATH, dirname(rtrim(FCPATH, '/\\')));
		if (isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== '') { $blocked[] = $_SERVER['DOCUMENT_ROOT']; }
		$normal_root = $this->normalized_path($root);
		foreach ($blocked as $directory)
		{
			$real_directory = realpath($directory);
			if ($real_directory === FALSE) { return FALSE; }
			$normal_directory = $this->normalized_path($real_directory);
			if ($this->within($normal_root, $normal_directory) || $this->within($normal_directory, $normal_root)) { return FALSE; }
		}
		return $root;
	}

	public function upload_available()
	{
		if ( ! extension_loaded('gd') || ! function_exists('imagecreatefromstring') || ! function_exists('imagepng') || ! function_exists('imagejpeg') || ! function_exists('getimagesizefromstring')) { return FALSE; }
		try
		{
			return $this->guarded(function () {
				return $this->storage_root(TRUE) !== FALSE && (imagetypes() & IMG_JPG) !== 0 && (imagetypes() & IMG_PNG) !== 0;
			});
		}
		catch (Throwable $error) { return FALSE; }
	}

	private function failure($status = 'invalid')
	{
		return array('status' => $status, 'message' => $status === 'unavailable' ? 'Penyimpanan gambar belum tersedia. Coba lagi setelah pengelola memeriksa layanan.' : 'Gambar tidak dapat diterima. Gunakan PNG atau JPEG hingga 3 MiB, maksimal 4096 piksel per sisi dan 4 juta piksel, dengan nama berkas sederhana.');
	}

	private function name_extension($name)
	{
		if ( ! is_string($name) || strlen($name) > 200 || $name !== trim($name) || ! preg_match('//u', $name) || ! preg_match('~\A[^./\\\\:\x00-\x1F\x7F<>?"|]{1,180}\.(png|jpe?g)\z~iD', $name, $matches)) { return FALSE; }
		return strtolower($matches[1]) === 'png' ? 'png' : 'jpg';
	}

	public function from_upload($file)
	{
		if ( ! is_array($file)) { return $this->failure(); }
		foreach (array('name', 'type', 'tmp_name') as $field) { if ( ! isset($file[$field]) || ! is_string($file[$field])) { return $this->failure(); } }
		if ( ! isset($file['error'], $file['size']) || ! is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK || ! is_int($file['size']) || $file['size'] < 1 || $file['size'] > self::MAX_UPLOAD_BYTES || $this->name_extension($file['name']) === FALSE || strpos($file['tmp_name'], "\0") !== FALSE || (array_key_exists('full_path', $file) && ( ! is_string($file['full_path']) || $file['full_path'] !== $file['name'])) || ! is_uploaded_file($file['tmp_name'])) { return $this->failure(); }
		return $this->convert_file($file['tmp_name'], $file['name'], $file['size']);
	}

	public function from_local_file($path, $original_name)
	{
		if (PHP_SAPI !== 'cli' || ! defined('ENVIRONMENT') || ENVIRONMENT !== 'development') { return $this->failure(); }
		return $this->convert_file($path, $original_name, NULL);
	}

	private function positive_integer($value, $maximum)
	{
		return (is_int($value) || is_string($value)) && preg_match('/\A[1-9][0-9]{0,9}\z/', (string) $value) && (int) $value <= $maximum;
	}

	private function bounded_bytes($path, $maximum)
	{
		$resolved = $this->canonical_path($path);
		if ($resolved === FALSE || ! is_file($resolved)) { return FALSE; }
		$handle = fopen($resolved, 'rb');
		if ($handle === FALSE) { return FALSE; }
		try
		{
			$stat = fstat($handle);
			if ($stat === FALSE || ($stat['mode'] & 0170000) !== 0100000 || $stat['size'] < 1 || $stat['size'] > $maximum || $this->canonical_path($resolved) !== $resolved) { return FALSE; }
			$bytes = stream_get_contents($handle, $maximum + 1);
			return is_string($bytes) && strlen($bytes) === $stat['size'] ? $bytes : FALSE;
		}
		finally { fclose($handle); }
	}

	private function enough_memory($pixels)
	{
		$limit = trim((string) ini_get('memory_limit'));
		if ($limit === '-1') { return TRUE; }
		if ( ! preg_match('/\A([1-9][0-9]*)([KMG]?)\z/i', $limit, $matches)) { return FALSE; }
		$multiplier = array('' => 1, 'K' => 1024, 'M' => 1048576, 'G' => 1073741824);
		$available = (int) $matches[1] * $multiplier[strtoupper($matches[2])] - memory_get_usage(TRUE);
		return $available > $pixels * 16 + self::MAX_UPLOAD_BYTES + self::MAX_STORED_BYTES * 2 + 16777216;
	}

	private function convert_file($path, $name, $declared_size)
	{
		$extension = $this->name_extension($name);
		if ($extension === FALSE || ! is_string($path)) { return $this->failure(); }
		if ( ! $this->upload_available()) { return $this->failure('unavailable'); }
		try
		{
			return $this->guarded(function () use ($path, $extension, $declared_size) {
				$bytes = $this->bounded_bytes($path, self::MAX_UPLOAD_BYTES);
				if ($bytes === FALSE || ($declared_size !== NULL && strlen($bytes) !== $declared_size)) { return $this->failure(); }
				$mime = $extension === 'png' ? 'image/png' : 'image/jpeg';
				$magic_ok = $extension === 'png' ? substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n" : substr($bytes, 0, 3) === "\xff\xd8\xff";
				if ( ! $magic_ok) { return $this->failure(); }
				if (class_exists('finfo')) { $info = new finfo(FILEINFO_MIME_TYPE); if ($info->buffer($bytes) !== $mime) { return $this->failure(); } }
				$dimensions = getimagesizefromstring($bytes);
				if ($dimensions === FALSE || ! isset($dimensions[0], $dimensions[1], $dimensions['mime']) || $dimensions['mime'] !== $mime || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > self::MAX_DIMENSION || $dimensions[1] > self::MAX_DIMENSION || $dimensions[0] * $dimensions[1] > self::MAX_PIXELS) { return $this->failure(); }
				if ( ! $this->enough_memory($dimensions[0] * $dimensions[1])) { return $this->failure('unavailable'); }
				$decoded = imagecreatefromstring($bytes);
				if ($decoded === FALSE || imagesx($decoded) !== $dimensions[0] || imagesy($decoded) !== $dimensions[1]) { return $this->failure(); }
				unset($bytes);
				if ($extension === 'png') { imagealphablending($decoded, FALSE); imagesavealpha($decoded, TRUE); }
				ob_start();
				try
				{
					$encoded_ok = $extension === 'png' ? imagepng($decoded, NULL, 6) : imagejpeg($decoded, NULL, 85);
					$encoded = ob_get_contents();
				}
				finally { ob_end_clean(); }
				unset($decoded);
				if ( ! $encoded_ok || ! is_string($encoded) || strlen($encoded) < 1 || strlen($encoded) > self::MAX_STORED_BYTES) { return $this->failure(); }
				return $this->store_encoded($encoded, $extension, $mime, $dimensions[0], $dimensions[1]);
			});
		}
		catch (Throwable $error) { return $this->failure(); }
	}

	private function store_encoded($bytes, $extension, $mime, $width, $height)
	{
		$key = NULL;
		$handle = FALSE;
		$created = FALSE;
		$complete = FALSE;
		try
		{
			$root = $this->storage_root(TRUE);
			if ($root === FALSE) { return $this->failure('unavailable'); }
			$key = bin2hex(random_bytes(32)).'.'.$extension;
			$path = $root.DIRECTORY_SEPARATOR.$key;
			$handle = fopen($path, 'x+b');
			$created = is_resource($handle);
			if ($handle === FALSE || $this->storage_root(TRUE) !== $root || is_link($path)) { return $this->failure('unavailable'); }
			if ( ! chmod($path, 0600)) { return $this->failure('unavailable'); }
			$written = 0;
			$length = strlen($bytes);
			while ($written < $length)
			{
				$count = fwrite($handle, substr($bytes, $written));
				if ($count === FALSE || $count === 0) { return $this->failure('unavailable'); }
				$written += $count;
			}
			if ( ! fflush($handle) || $this->storage_root(TRUE) !== $root) { return $this->failure('unavailable'); }
			$image = array('storage_key' => $key, 'mime_type' => $mime, 'byte_size' => $length, 'width' => (int) $width, 'height' => (int) $height, 'sha256' => hash('sha256', $bytes));
			$complete = TRUE;
			return array('status' => 'ok', 'message' => 'Gambar siap disimpan pada pelajaran.', 'image' => $image);
		}
		catch (Throwable $error) { return $this->failure('unavailable'); }
		finally
		{
			if (is_resource($handle)) { fclose($handle); }
			if ( ! $complete && $created && $key !== NULL) { $this->remove($key); }
		}
	}

	private function valid_key($key)
	{
		return is_string($key) && preg_match('/\A[a-f0-9]{64}\.(png|jpg)\z/', $key);
	}

	public function read($image)
	{
		if ( ! is_array($image) || ! $this->valid_key($image['storage_key'] ?? NULL) || ! is_string($image['sha256'] ?? NULL) || ! preg_match('/\A[a-f0-9]{64}\z/', $image['sha256']) || ! $this->positive_integer($image['byte_size'] ?? NULL, self::MAX_STORED_BYTES) || ! $this->positive_integer($image['width'] ?? NULL, self::MAX_DIMENSION) || ! $this->positive_integer($image['height'] ?? NULL, self::MAX_DIMENSION) || (int) $image['width'] * (int) $image['height'] > self::MAX_PIXELS) { return FALSE; }
		$png = substr($image['storage_key'], -4) === '.png';
		if (($image['mime_type'] ?? NULL) !== ($png ? 'image/png' : 'image/jpeg')) { return FALSE; }
		try
		{
			return $this->guarded(function () use ($image, $png) {
				$root = $this->storage_root();
				if ($root === FALSE) { return FALSE; }
				$bytes = $this->bounded_bytes($root.DIRECTORY_SEPARATOR.$image['storage_key'], self::MAX_STORED_BYTES);
				if ($bytes === FALSE || strlen($bytes) !== (int) $image['byte_size'] || ! hash_equals($image['sha256'], hash('sha256', $bytes)) || $this->storage_root() !== $root) { return FALSE; }
				$magic_ok = $png ? substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n" : substr($bytes, 0, 3) === "\xff\xd8\xff" && substr($bytes, -2) === "\xff\xd9";
				return $magic_ok ? $bytes : FALSE;
			});
		}
		catch (Throwable $error) { return FALSE; }
	}

	public function remove($key)
	{
		if ( ! $this->valid_key($key)) { return FALSE; }
		try
		{
			return $this->guarded(function () use ($key) {
				$root = $this->storage_root(TRUE);
				if ($root === FALSE) { return FALSE; }
				$path = $root.DIRECTORY_SEPARATOR.$key;
				if ($this->canonical_path($path) === FALSE || ! is_file($path) || $this->storage_root(TRUE) !== $root) { return FALSE; }
				return unlink($path);
			});
		}
		catch (Throwable $error) { return FALSE; }
	}
}
