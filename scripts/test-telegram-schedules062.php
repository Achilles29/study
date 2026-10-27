<?php
if(PHP_SAPI!=='cli'||getenv('DB_DATABASE')!=='study_qa_monitor_sso039')exit(2);
$_SERVER['argv']=array('index.php','cli/migrate');ob_start();require dirname(__DIR__).'/index.php';ob_end_clean();
$ci=&get_instance();$ci->load->model(array('Notification_model','Account_model'));$db=$ci->db;$db->db_debug=FALSE;$m=$ci->Notification_model;$scheduler=$ci->Telegram_schedule_model;$p=$ci->telegram_message_policy;
function check062($ok,$label){if(!$ok)throw new RuntimeException($label);}
function sql062($sql,$args=array()){global $db;$r=$db->query($sql,$args);if(!$r)throw new RuntimeException('QA query failed');return $r;}
function batch062($fn){global $ci,$db;return $ci->study_transaction->run($db,function()use($fn){$s=sql062('SELECT * FROM st_notification_config WHERE id=1 FOR UPDATE')->row_array();$fn($s);return array('ok'=>TRUE);});}
$admin=$ci->Account_model->find_active_user(1);$parent=$ci->Account_model->find_active_user(2);$oldConfig=$m->settings();$oldPolicies=$scheduler->policies();$startOut=(int)sql062('SELECT COALESCE(MAX(id),0) n FROM st_telegram_outbox')->row_array()['n'];$startInbox=(int)sql062('SELECT COALESCE(MAX(id),0) n FROM st_notifications')->row_array()['n'];$auditIds=array();
check062(!sql062('SELECT id FROM st_telegram_digest_events LIMIT 1')->num_rows(),'QA buffered events must be empty before fixture');
try{
 $floor=(int)sql062('SELECT MAX(id) n FROM st_audit_events')->row_array()['n'];
 sql062('UPDATE st_notification_config SET enabled=1,members=1,children=1,payments=1,donations=1,help=1,visitors=1,event_floor=?,registration_floor=?,traffic_hour=?,revision=revision+1 WHERE id=1',array($floor,$floor,gmdate('Y-m-d H:00:00')));
 foreach($p->categories() as $category=>$label)$db->where('category',$category)->update('st_telegram_message_policies',array('mode'=>$category==='visitors'?'interval':'realtime','template'=>$p->default_template($category),'interval_minutes'=>360,'last_window_at'=>gmdate('Y-m-d H:i:s',time()-60),'next_due_at'=>$category==='visitors'?gmdate('Y-m-d H:i:s',time()+21600):NULL));
 $base=array('category'=>'members','revision'=>(string)$scheduler->policies()['members']['revision'],'mode'=>'interval','interval_minutes'=>'360','daily_time'=>'08:00','template'=>"{judul}\n{isi}\n{periode}\n{jumlah}",'send_empty'=>'0');
 check062($m->save_message_policy($parent,$base)['code']===403,'Non-admin rejected');$stale=$admin;$stale['auth_stamp']='stale';check062($m->save_message_policy($stale,$base)['code']===403,'Stale credentials rejected');
 foreach(array(array('interval_minutes'=>'0'),array('interval_minutes'=>'10081'),array('daily_time'=>'24:00'),array('template'=>'{secret}'),array('template'=>'No details'),array('template'=>array('bad')),array('category'=>array('members'))) as $invalid)check062(!$m->save_message_policy($admin,array_replace($base,$invalid))['ok'],'Invalid values rejected');
 check062($m->save_message_policy($admin,$base)['ok'],'Interval schedule saved');check062($m->save_message_policy($admin,$base)['code']===409,'Revision conflict rejected');
 $saved=$scheduler->policies()['members'];check062(abs(strtotime($saved['next_due_at'].' UTC')-time()-21600)<3,'6-hour schedule');
 $custom=array_replace($base,array('revision'=>(string)$saved['revision'],'template'=>"ANGGOTA {jumlah}\n{isi}\n{periode}"));check062($m->save_message_policy($admin,$custom)['ok'],'Template saved');check062($scheduler->policies()['members']['next_due_at']===$saved['next_due_at'],'Template change retains due time');
 // Real collector path: one current registration, dedup, no delivery before due.
 $db->insert('st_audit_events',array('actor_user_id'=>2,'event_type'=>'account.registered','entity_type'=>'user','entity_id'=>2,'details_json'=>'{}','created_at'=>gmdate('Y-m-d H:i:s')));$eventId=(int)$db->insert_id();$auditIds[]=$eventId;
 check062($m->collect()['ok'],'Collector supports schedule');check062($m->collect()['ok'],'Collector replay');
 check062((int)sql062('SELECT COUNT(*) n FROM st_telegram_digest_events WHERE event_key=?',array('audit:'.$eventId))->row_array()['n']===1,'Exactly one buffered registration');
 check062(!sql062('SELECT id FROM st_telegram_outbox WHERE event_key=?',array('audit:'.$eventId))->num_rows(),'No early Telegram registration');
 check062(sql062('SELECT id FROM st_notifications WHERE event_key=?',array('audit:'.$eventId))->num_rows()>0,'Website notification remains immediate');
 check062(batch062(function($s)use($scheduler){$scheduler->enqueue($s,'qa062:second','members','Anggota kedua','Dimas baru mendaftar.','kelola/pengguna/2/akses');})['ok'],'Second event buffered');
 $db->where('category','members')->update('st_telegram_message_policies',array('next_due_at'=>gmdate('Y-m-d H:i:s',time()-1)));
 check062($m->collect()['ok'],'Due digest collected');$messages=sql062("SELECT * FROM st_telegram_outbox WHERE id>? AND category='members'",array($startOut))->result_array();check062(count($messages)===1&&strpos($messages[0]['body'],'ANGGOTA 2')!==FALSE&&strpos($messages[0]['body'],'Dimas')!==FALSE,'Two events combined with custom template');
 $count=(int)sql062('SELECT COUNT(*) n FROM st_telegram_outbox')->row_array()['n'];check062($m->collect()['ok'],'Second due check');check062((int)sql062('SELECT COUNT(*) n FROM st_telegram_outbox')->row_array()['n']===$count,'Digest not duplicated');
 // Daily scheduling, including UTC day rollover.
 $daily=array('mode'=>'daily','daily_time'=>'08:00');check062($p->next_due($daily,strtotime('2026-10-26 23:30:00 UTC'))==='2026-10-27 01:00:00','Daily time converted from WIB');check062($p->next_due($daily,strtotime('2026-10-27 01:00:00 UTC'))==='2026-10-28 01:00:00','Passed daily time moves to tomorrow');
 check062($p->next_due(array('mode'=>'daily','daily_time'=>'00:05'),strtotime('2026-10-27 16:59:00 UTC'))==='2026-10-27 17:05:00','WIB midnight boundary');
 // Traffic covers the complete interval, ignores records beyond its upper boundary.
 $now=time();$from=gmdate('Y-m-d H:i:s',$now-6*3600);$until=gmdate('Y-m-d H:i:s',$now);$n=sql062('SELECT COUNT(*) visits,COALESCE(SUM(is_bot),0) bots FROM st_operational_events WHERE created_at>=? AND created_at<?',array($from,$until))->row_array();
 $db->where('category','visitors')->update('st_telegram_message_policies',array('last_window_at'=>$from,'next_due_at'=>$until,'send_empty'=>1,'template'=>"{periode}\nTOTAL={kunjungan};BOT={bot};NONBOT={manusia}"));
 check062(batch062(function($s)use($scheduler,$now){$scheduler->flush($s,$now);})['ok'],'Traffic flush');$traffic=sql062("SELECT * FROM st_telegram_outbox WHERE id>? AND category='visitors' ORDER BY id DESC LIMIT 1",array($startOut))->row_array();check062(strpos($traffic['body'],'TOTAL='.$n['visits'].';BOT='.$n['bots'].';NONBOT='.((int)$n['visits']-(int)$n['bots']))!==FALSE,'Whole six-hour interval counts');check062($scheduler->policies()['visitors']['last_window_at']===$until,'Traffic cursor advanced atomically');
 $trafficCount=(int)sql062("SELECT COUNT(*) n FROM st_telegram_outbox WHERE category='visitors'")->row_array()['n'];batch062(function($s)use($scheduler,$now){$scheduler->flush($s,$now);});check062((int)sql062("SELECT COUNT(*) n FROM st_telegram_outbox WHERE category='visitors'")->row_array()['n']===$trafficCount,'Traffic not duplicated');
 // Empty suppression and recovery after downtime without sending each missed interval.
 $future=$now+3600;$empty=gmdate('Y-m-d H:i:s',$future);$db->where('category','visitors')->update('st_telegram_message_policies',array('last_window_at'=>$empty,'next_due_at'=>$empty,'send_empty'=>0));batch062(function($s)use($scheduler,$future){$scheduler->flush($s,$future+60);});check062((int)sql062("SELECT COUNT(*) n FROM st_telegram_outbox WHERE category='visitors'")->row_array()['n']===$trafficCount,'Empty report suppressed');
 $db->where('category','visitors')->update('st_telegram_message_policies',array('last_window_at'=>$empty,'next_due_at'=>$empty,'send_empty'=>1));batch062(function($s)use($scheduler,$future){$scheduler->flush($s,$future+86400);});check062((int)sql062("SELECT COUNT(*) n FROM st_telegram_outbox WHERE category='visitors'")->row_array()['n']===$trafficCount+1,'Downtime produces one report');check062(strtotime($scheduler->policies()['visitors']['next_due_at'].' UTC')>$future+86400,'Next due is in future');
 // Buffered data survives policy edits and moves to realtime after mode change.
 batch062(function($s)use($scheduler){$scheduler->enqueue($s,'qa062:mode-change','members','Mode change','Data tetap lengkap.','kelola');});
 $current=$scheduler->policies()['members'];$v=array_replace($base,array('revision'=>(string)$current['revision'],'mode'=>'realtime'));check062($m->save_message_policy($admin,$v)['ok'],'Change to realtime');check062(sql062("SELECT id FROM st_telegram_digest_events WHERE event_key='qa062:mode-change'")->num_rows()===1,'Buffered event preserved on save');batch062(function($s)use($scheduler){$scheduler->flush($s);});check062(sql062("SELECT id FROM st_telegram_outbox WHERE event_key='qa062:mode-change'")->num_rows()===1,'Buffered event released on next worker');
 // Split long reports without dropping text, using the existing gateway bound.
 $text=str_repeat("Anggota baru 😀 dan rincian lengkap.\n",180);$parts=$p->chunks($text);$joined='';foreach($parts as $part){check062(mb_strlen($part)<=1500,'Gateway length bound');$joined.=preg_replace('/\ABagian [0-9]+\/[0-9]+\n/','',$part);}check062($joined===$text,'All long-report content retained');
 check062($p->render('{isi}',array('isi'=>'{tautan}'))==='{tautan}','Replacement values are not templates');
 // Atomic rollback: fault after building outbox must leave events available for retry.
 $db->where('category','children')->update('st_telegram_message_policies',array('mode'=>'interval','next_due_at'=>gmdate('Y-m-d H:i:s',time()-1)));
 batch062(function($s)use($scheduler){$scheduler->enqueue($s,'qa062:rollback','children','Akun anak','Sari menambahkan Bima.','kelola');});
 $beforeFault=(int)sql062('SELECT COUNT(*) n FROM st_telegram_outbox')->row_array()['n'];sql062("CREATE TRIGGER qa062_fail_digest BEFORE DELETE ON st_telegram_digest_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA fault'");
 try{$r=batch062(function($s)use($scheduler){$scheduler->flush($s);});check062(!$r['ok'],'Injected failure rejected');check062((int)sql062('SELECT COUNT(*) n FROM st_telegram_outbox')->row_array()['n']===$beforeFault,'Outbox rolled back');check062(sql062("SELECT id FROM st_telegram_digest_events WHERE event_key='qa062:rollback'")->num_rows()===1,'Buffered data retained after rollback');}finally{sql062('DROP TRIGGER qa062_fail_digest');}
 check062(batch062(function($s)use($scheduler){$scheduler->flush($s);})['ok'],'Retry after rollback');
 // Exercise the existing delivery worker using the QA transport (never Telegram).
 $db->where('id',1)->update('st_notification_config',array('token_cipher'=>$ci->telegram_gateway->seal('123456789:'.str_repeat('a',35)),'chat_id'=>'123456789'));
 putenv('STUDY_TELEGRAM_TEST=success');check062($m->deliver()['ok'],'Scheduled delivery uses worker');
 check062(sql062("SELECT status FROM st_telegram_outbox WHERE id=?",array($traffic['id']))->row_array()['status']==='sent','Scheduled report marked sent through QA transport');
 check062(sql062('SELECT status FROM st_telegram_outbox WHERE id=?',array($messages[0]['id']))->row_array()['status']==='sent','Digest marked sent through QA transport');
 // A different target/preferences cannot receive queued digest data.
 batch062(function($s)use($scheduler){$scheduler->enqueue($s,'qa062:old-target','children','Old target','Private event','kelola');});$config=$m->settings();$settings=array('revision'=>(string)$config['revision'],'token'=>'','chat_id'=>(string)$config['chat_id'],'confirm'=>'1');foreach(array('enabled','members','children','payments','donations','help','visitors') as $key)$settings[$key]=(string)$config[$key];
 check062($m->save_settings($admin,$settings)['ok'],'Config changed');check062(!sql062('SELECT id FROM st_telegram_digest_events')->num_rows(),'Old-target data cancelled');
 echo "PASS 062: admin/stamp/revision guards, validation, live event collection, immediate website inbox, buffered dedup, digest batching, WIB daily and midnight boundaries, six-hour traffic window, empty reports, downtime recovery, mode changes, template retention, long-message splitting, atomic fault rollback, synthetic worker delivery, target cancellation. No external messages.\n";
}finally{
 $db->query('DROP TRIGGER IF EXISTS qa062_fail_digest');$db->where('id >',$startOut)->delete('st_telegram_outbox');$db->where('id >',$startInbox)->delete('st_notifications');
 $db->query('DELETE FROM st_telegram_digest_events');
 if($auditIds){$db->where_in('event_id',$auditIds)->delete('st_notification_events');$db->where_in('id',$auditIds)->delete('st_audit_events');}
 $db->where('id',1)->update('st_notification_config',$oldConfig);foreach($oldPolicies as $category=>$policy)$db->where('category',$category)->update('st_telegram_message_policies',$policy);
}
