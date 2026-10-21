<?php
if(PHP_SAPI!=='cli' || getenv('DB_DATABASE')!=='study_qa_monitor_sso039') { exit(2); }
$_SERVER['argv']=array('index.php','cli/migrate');ob_start();require dirname(__DIR__).'/index.php';ob_end_clean();$ci =& get_instance();$ci->load->model('Account_model');$db=$ci->db;$db->db_debug=FALSE;
function check063($ok,$label){if(!$ok){throw new RuntimeException($label);}}
$parent=$db->like('username','qa063_','after')->order_by('id','DESC')->get('st_users')->row_array();$child=$db->query('SELECT u.* FROM st_users u JOIN st_family_links l ON l.child_user_id=u.id WHERE l.parent_user_id=? ORDER BY u.id DESC LIMIT 1',array($parent['id']))->row_array();check063($parent && $child,'Run HTTP fixture first');
$stamp=study_credential_stamp($parent);
check063(!$ci->Account_model->update_child_learning_stage($parent['id'],$child['id'],'primary','stale','8'),'Stale auth rejected');
check063(!$ci->Account_model->update_child_learning_stage($parent['id'],$child['id'],'secondary',$stamp,'8'),'Inconsistent grade-stage rejected');
check063(!$ci->Account_model->create_child($parent['id'],$child['username'],'Duplicate','a','primary',$stamp,'8'),'Duplicate child rejected');
$before=$db->where('id',$child['id'])->get('st_users')->row_array();
check063($db->query("CREATE TRIGGER qa063_fail_grade_audit BEFORE INSERT ON st_audit_events FOR EACH ROW BEGIN IF NEW.event_type='family.child_learning_stage_updated' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA063 audit failure'; END IF; END"),'Install QA fault');
try {check063(!$ci->Account_model->update_child_learning_stage($parent['id'],$child['id'],'primary',$stamp,'8'),'Failed audit must reject update');check063($db->where('id',$child['id'])->get('st_users')->row_array()===$before,'Failed audit must roll back grade');}
finally {$db->query('DROP TRIGGER qa063_fail_grade_audit');}
echo "PASS 063 model: stale credentials, duplicate child, invalid grade/group, audit-failure atomic rollback.\n";
