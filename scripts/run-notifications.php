<?php
// Root-owned cron entrypoint; shares the existing FPM environment, never prints secrets.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$file='/etc/projectpintar/environment.conf';
if(!is_readable($file)){fwrite(STDERR,"Notification environment unavailable.\n");exit(1);}
$parsed=parse_ini_file($file,FALSE,INI_SCANNER_RAW);$values=$parsed['env']??NULL;
if(!is_array($values) || ($values['CI_ENV']??'')!=='production' || ($values['DB_DATABASE']??'')!=='db_study'){fwrite(STDERR,"Notification environment rejected.\n");exit(1);}
foreach(array('CI_ENV','APP_BASE_URL','APP_ENCRYPTION_KEY','APP_COOKIE_SECURE','DB_HOST','DB_PORT','DB_USERNAME','DB_PASSWORD','DB_DATABASE','STUDY_MEDIA_ROOT') as $key){if(isset($values[$key])){putenv($key.'='.$values[$key]);}}
unset($parsed,$values);chdir(dirname(__DIR__));$_SERVER['argv']=array('index.php','cli/notification_worker');require dirname(__DIR__).'/index.php';
