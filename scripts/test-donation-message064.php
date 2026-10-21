<?php
if(PHP_SAPI!=='cli' || getenv('DB_DATABASE')!=='study_qa_monitor_sso039') { exit(2); }
$_SERVER['argv']=array('index.php','cli/migrate');ob_start();require dirname(__DIR__).'/index.php';ob_end_clean();$ci =& get_instance();$ci->load->model(array('Commerce_model','Account_model'));$ci->Commerce_model->set_environment('production');$db=$ci->db;$db->db_debug=FALSE;
function check064($ok,$label){if(!$ok){throw new RuntimeException($label);}}
$u=$db->like('username','qa063_','after')->order_by('id','DESC')->get('st_users')->row_array();$stamp=study_credential_stamp($u);$revision=(string)$ci->Commerce_model->donation_settings()['revision'];$key=bin2hex(random_bytes(32));$model=$ci->Commerce_model;
$before=$db->query('CHECKSUM TABLE st_wallet_entries,st_commerce_grants,st_family_slots,st_point_entries,st_commerce_purchases EXTENDED')->result_array();
check064(!$model->donate($u['id'],$stamp,'20000',$revision,$key,array('bad'))['ok'],'Array message rejected');
check064(!$model->donate($u['id'],$stamp,'20000',$revision,$key,str_repeat('x',1001))['ok'],'Long message rejected');
check064(!$model->donate($u['id'],'stale','20000',$revision,$key,'Support')['ok'],'Stale credentials denied');
$message="Semangat belajar! <script>alert('test')</script>\nTerima kasih 😊";
$r=$model->donate($u['id'],$stamp,'20000',$revision,$key,$message);check064($r['ok'],'Create synthetic donation');$id=$r['id'];
$same=$model->donate($u['id'],$stamp,'20000',$revision,$key,$message);check064($same['ok'] && $same['id']===$id,'Replay same intent');
check064($model->donate($u['id'],$stamp,'20000',$revision,$key,'Different')['code']===409,'Changed message rejected on same intent');
$record=$model->order($id,$u['id']);check064(json_decode($record['snapshot_json'],TRUE)['message']===$message,'Message stored exactly');check064((int)$record['amount_idr']===20000 && (int)$record['credit_amount']===0,'No price/reward change');check064(!$model->order($id,2),'Other owner blocked');
$emptykey=bin2hex(random_bytes(32));$empty=$model->donate($u['id'],$stamp,'12345',$revision,$emptykey);check064($empty['ok'],'Legacy empty message');$row=$db->where('id',$empty['id'])->get('st_payment_orders')->row_array();check064($row['request_hash']===hash('sha256','donation|12345|'.$revision),'Legacy digest remains compatible');
$beforeCount=$db->count_all('st_payment_orders');check064($db->query("CREATE TRIGGER qa064_fail_donation_audit BEFORE INSERT ON st_audit_events FOR EACH ROW BEGIN IF NEW.event_type='donation.created' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA064 audit failure'; END IF; END"),'QA fault');
try{$r=$model->donate($u['id'],$stamp,'20000',$revision,bin2hex(random_bytes(32)),'Rollback');check064(!$r['ok'],'Failed audit rejects order');check064($db->count_all('st_payment_orders')===$beforeCount,'No orphan message/order');}finally{$db->query('DROP TRIGGER qa064_fail_donation_audit');}
check064($db->query('CHECKSUM TABLE st_wallet_entries,st_commerce_grants,st_family_slots,st_point_entries,st_commerce_purchases EXTENDED')->result_array()===$before,'Benefits untouched');
echo 'PASS 064: message validation, exact text/emoji, same-intent replay, different-message conflict, old digest, owner isolation, rollback, no rewards; no real Midtrans charge. QA order '.$id.PHP_EOL;
