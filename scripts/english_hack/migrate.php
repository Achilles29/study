<?php
if(PHP_SAPI!=='cli'||!in_array($argv[1]??'',array('--qa','--production'),TRUE))exit("Use --qa or --production\n");
$qa=$argv[1]==='--qa';
foreach(file('/etc/projectpintar/environment.conf') as $line)if(preg_match('/^env\[([A-Z_]+)\]\s*=\s*(.*)$/',trim($line),$m))putenv($m[1].'='.trim($m[2],"\"'"));
if($qa)foreach(array('CI_ENV'=>'development','DB_HOST'=>'localhost','DB_PORT'=>'3308','DB_USERNAME'=>'root','DB_PASSWORD'=>'','DB_DATABASE'=>'study_qa_monitor_sso039','APP_BASE_URL'=>'http://127.0.0.1:8098/','APP_COOKIE_SECURE'=>'0') as $k=>$v)putenv($k.'='.$v);
else {
 $client=parse_ini_file('/root/security-remediation-20261022/main-mysql-current.cnf',TRUE,INI_SCANNER_RAW)['client'];
 foreach(array('DB_HOST'=>'127.0.0.1','DB_PORT'=>'3306','DB_USERNAME'=>$client['user'],'DB_PASSWORD'=>trim($client['password'],'"'),'DB_DATABASE'=>'db_study') as $k=>$v)putenv($k.'='.$v);
}
chdir(dirname(__DIR__,2));$_SERVER['argv']=array('index.php','cli/migrate');require 'index.php';
