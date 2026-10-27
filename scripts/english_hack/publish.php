<?php
/** Activate the reviewed course using the normal runtime identity, with an audit event. */
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--production')exit("Use --production\n");
foreach(file('/etc/projectpintar/environment.conf') as $line)if(preg_match('/^env\[([A-Z_]+)\]\s*=\s*(.*)$/',trim($line),$m))putenv($m[1].'='.trim($m[2],"\"'"));
if(getenv('DB_DATABASE')!=='db_study')throw new RuntimeException('Production database mismatch.');
chdir(dirname(__DIR__,2));$_SERVER['argv']=array('index.php','cli/migrate');ob_start();require 'index.php';ob_end_clean();
$ci=&get_instance();$ci->load->model('English_course_model','course');$ci->db->db_debug=FALSE;
$manifest=json_decode(file_get_contents(APPPATH.'content/english_hack/v1/audio/manifest.json'),TRUE,512,JSON_THROW_ON_ERROR);
if(count($ci->english_course_content->units)!==56||count($manifest)!==56)throw new RuntimeException('Incomplete curriculum.');
foreach($ci->english_course_content->units as $id=>$u){$file=APPPATH.'content/english_hack/v1/audio/'.$id.'.wav';if(!is_readable($file)||filesize($file)<100||($manifest[$id]['text_sha256']??'')!==hash('sha256',$u['context']))throw new RuntimeException('Audio does not match curriculum.');}
$result=$ci->study_transaction->run($ci->db,function()use($ci){
 $s=$ci->db->query('SELECT * FROM st_english_settings WHERE id=1 FOR UPDATE')->row_array();
 $levels=$ci->db->query('SELECT * FROM st_english_levels FOR UPDATE')->result_array();if(count($levels)!==7)return array('ok'=>FALSE,'code'=>409);
 $changed=FALSE;foreach($levels as $l)if($l['status']!=='published'){$changed=TRUE;$ci->db->where('level_id',$l['level_id'])->update('st_english_levels',array('status'=>'published','revision'=>(int)$l['revision']+1,'updated_at'=>gmdate('Y-m-d H:i:s')));}
 if(!(int)$s['enabled']){$changed=TRUE;$ci->db->where('id',1)->update('st_english_settings',array('enabled'=>1,'revision'=>(int)$s['revision']+1,'updated_at'=>gmdate('Y-m-d H:i:s')));}
 if($changed)$ci->db->insert('st_audit_events',array('actor_user_id'=>NULL,'event_type'=>'english.course_published','entity_type'=>'english_course','entity_id'=>1,'details_json'=>json_encode(array('origin'=>'user-authorized deployment','version'=>$ci->english_course_content->version(),'levels'=>7,'units'=>56,'audio'=>56)),'created_at'=>gmdate('Y-m-d H:i:s')));
 return array('ok'=>TRUE,'changed'=>$changed);
});
if(!$result['ok'])throw new RuntimeException('Publication did not succeed.');echo json_encode($result+array('levels'=>7,'units'=>56,'audio'=>56))."\n";
