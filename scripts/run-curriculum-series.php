<?php
// Root-only CLI entrypoint. Uses the active application's database; prints no credentials.
if (PHP_SAPI!=='cli' || $argc!==4 || ! in_array($argv[1],array('import_series','publish_series','add_series_flashcards'),TRUE) || ! preg_match('/\A(?:sd_[56]|smp_[789]|sma_1[012])\z/',$argv[2]) || ! in_array($argv[3],array('check','apply'),TRUE))
{ fwrite(STDERR,"Usage: php scripts/run-curriculum-series.php import_series|publish_series|add_series_flashcards sd_5|sd_6|smp_7|...|sma_12 check|apply\n"); exit(2); }
$file='/etc/projectpintar/environment.conf';
if ( ! is_readable($file)) { fwrite(STDERR,"Application environment unavailable.\n"); exit(1); }
$parsed=parse_ini_file($file,FALSE,INI_SCANNER_RAW); $values=$parsed['env']??NULL;
if ( ! is_array($values) || ($values['DB_DATABASE']??'')!=='db_study' || ($values['DB_PORT']??'')!=='3306') { fwrite(STDERR,"Active database target rejected.\n"); exit(1); }
foreach (array('APP_BASE_URL','APP_ENCRYPTION_KEY','APP_COOKIE_SECURE','DB_HOST','DB_PORT','DB_USERNAME','DB_PASSWORD','DB_DATABASE','STUDY_MEDIA_ROOT') as $key)
{ if (isset($values[$key])) { putenv($key.'='.$values[$key]); } }
putenv('CI_ENV=development');
unset($parsed,$values);
chdir(dirname(__DIR__));
$_SERVER['argv']=array('index.php','cli/'.$argv[1],'index',$argv[2],$argv[3]);
require dirname(__DIR__).'/index.php';
