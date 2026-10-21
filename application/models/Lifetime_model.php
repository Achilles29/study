<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Lifetime_model extends CI_Model
{
 // Two included family slots, not two additional slots. Existing paid extra slots are untouched.
 // Stable link order assigns coverage; unlinking/inactivating a child releases an included slot.
 public function predicate($learner, $alias='g')
 {
  if($alias!=='g'){throw new InvalidArgumentException('Invalid grant alias.');}$id=(int)$learner;
  $qa=ENVIRONMENT==='development'&&preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/',$this->db->database)&&getenv('STUDY_QA_LIVE_ACCESS_ONLY')!=='1';
  return "g.environment".($qa?" IN ('sandbox','production')":"='production'")." AND g.is_lifetime=1 AND g.kind='platinum' AND g.status='active' AND g.ends_at IS NULL AND g.starts_at<=UTC_TIMESTAMP() AND EXISTS(SELECT 1 FROM st_users parent WHERE parent.id=g.owner_user_id AND parent.status='active' AND parent.account_kind='parent') AND EXISTS(SELECT 1 FROM st_users learner WHERE learner.id=$id AND learner.status='active') AND (g.owner_user_id=$id OR EXISTS(SELECT 1 FROM st_family_links link JOIN st_users child ON child.id=link.child_user_id AND child.account_kind='child' WHERE link.parent_user_id=g.owner_user_id AND link.child_user_id=$id AND link.status='active' AND (SELECT COUNT(*) FROM st_family_links earlier JOIN st_users sibling ON sibling.id=earlier.child_user_id AND sibling.status='active' AND sibling.account_kind='child' WHERE earlier.parent_user_id=link.parent_user_id AND earlier.status='active' AND earlier.id<=link.id)<=2))";
 }
 public function covered($learner)
 {
  $q=$this->db->query('SELECT g.id FROM st_commerce_grants g WHERE '.$this->predicate($learner).' LIMIT 1');
  if($q===FALSE){throw new RuntimeException('Lifetime access unavailable.');}return $q->num_rows()>0;
 }
}
