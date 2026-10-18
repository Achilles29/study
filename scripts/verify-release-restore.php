<?php
// Root CLI only: verifies an already restored isolated database/media pair.
if (PHP_SAPI !== 'cli' || ! function_exists('posix_geteuid') || posix_geteuid() !== 0) { exit(2); }
$target = $argv[1] ?? ''; $restored_media = $argv[2] ?? '';
if ( ! preg_match('/\Astudy_qa_restore_[a-z0-9]+\z/', $target) || ! preg_match('#\A/www/backup/projectpintar-restore-[A-Za-z0-9]+/lesson-images\z#', $restored_media) || realpath($restored_media) !== $restored_media) { exit(2); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '', 'db_study', 3308, '/tmp/mysql-study.sock'); $db->set_charset('utf8mb4');
$tables = array_column($db->query('SHOW TABLES FROM db_study')->fetch_all(MYSQLI_NUM), 0);
$restored_tables = array_column($db->query('SHOW TABLES FROM `'.$target.'`')->fetch_all(MYSQLI_NUM), 0);
if ($tables !== $restored_tables) { throw new RuntimeException('Restored table inventory differs.'); }
foreach ($tables as $table)
{
	if ( ! preg_match('/\A[a-z_0-9]+\z/', $table)) { throw new RuntimeException('Unexpected table name.'); }
	$source = '`db_study`.`'.$table.'`'; $copy = '`'.$target.'`.`'.$table.'`';
	$original = $db->query('CHECKSUM TABLE '.$source.' EXTENDED')->fetch_assoc()['Checksum']; $restored = $db->query('CHECKSUM TABLE '.$copy.' EXTENDED')->fetch_assoc()['Checksum'];
	if ($original === NULL || (string) $original !== (string) $restored) { throw new RuntimeException('Restored data differs: '.$table); }
	if ($db->query('SHOW CREATE TABLE '.$source)->fetch_row()[1] !== $db->query('SHOW CREATE TABLE '.$copy)->fetch_row()[1]) { throw new RuntimeException('Restored schema differs: '.$table); }
}
function study_media_manifest($root)
{
	$files = array(); $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $entry) { if ($entry->isLink()) { throw new RuntimeException('Unexpected media symlink.'); } if ($entry->isFile()) { $files[substr($entry->getPathname(), strlen($root) + 1)] = hash_file('sha256', $entry->getPathname()); } } ksort($files); return $files;
}
$media = study_media_manifest('/www/study-private/lesson-images'); if ($media !== study_media_manifest($restored_media)) { throw new RuntimeException('Restored media differs.'); }
foreach (array('release032-source.tar.gz','release032-private-media.tar.gz') as $archive)
{
	$process = proc_open(array('/usr/bin/gzip', '-t', '/www/backup/projectpintar-deploy-20261016/'.$archive), array(0 => array('pipe','r'), 1 => array('pipe','w'), 2 => array('pipe','w')), $pipes);
	if ( ! is_resource($process)) { throw new RuntimeException('Archive check unavailable.'); } fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]); if (proc_close($process) !== 0) { throw new RuntimeException('Archive integrity check failed.'); }
}
$evidence = array('database' => 'db_study', 'checked_at' => gmdate('Y-m-d H:i:s'), 'migration' => (int) $db->query('SELECT version FROM study_migrations')->fetch_row()[0], 'tables' => count($tables), 'media_files' => count($media), 'restore_verified' => TRUE);
$directory = '/www/study-private/operations'; if ( ! is_dir($directory) || is_link($directory) || fileowner($directory) !== 0) { throw new RuntimeException('Private operations directory unavailable.'); }
$temporary = $directory.'/restore-evidence.'.bin2hex(random_bytes(8)).'.tmp'; if (file_put_contents($temporary, json_encode($evidence), LOCK_EX) === FALSE || ! chmod($temporary, 0640) || ! chgrp($temporary, 'www') || ! rename($temporary, $directory.'/restore-evidence.json')) { throw new RuntimeException('Evidence not saved.'); }
echo 'PASS isolated restore: '.count($tables).' tables schema/data checksums, '.count($media)." private media files, source/media gzip integrity. Production was read-only.\n";
