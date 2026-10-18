<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Commerce_access_model extends CI_Model
{
	private function environment_scope($column)
	{
		// Sandbox entitlements are usable only inside isolated development QA, never on the public server.
		$qa = ENVIRONMENT === 'development' && preg_match('/\Astudy_qa_monitor_[a-z0-9]+\z/', $this->db->database) && getenv('STUDY_QA_LIVE_ACCESS_ONLY') !== '1';
		return $column.($qa ? " IN ('sandbox','production')" : "='production'");
	}
	public function course($learner, $course, $lock = FALSE)
	{
		$q = $this->db->query('SELECT g.id FROM st_commerce_grants g WHERE '.$this->course_predicate($learner, (int) $course).' LIMIT 1'.($lock ? ' FOR UPDATE' : ''));
		if ($q === FALSE) { throw new RuntimeException('Purchased access unavailable.'); }
		return $q->num_rows() > 0;
	}
	public function course_predicate($learner, $course)
	{
		$learner = (int) $learner;
		// The only column expression is a fixed internal alias, never browser input.
		$course = $course === 'c.id' ? 'c.id' : (string) (int) $course;
		return $this->environment_scope('g.environment')." AND g.status='active' AND g.starts_at<=UTC_TIMESTAMP() AND g.ends_at>UTC_TIMESTAMP() AND ((g.kind='package' AND g.learner_user_id={$learner} AND JSON_CONTAINS(g.course_ids_json,CAST({$course} AS CHAR))) OR (g.kind='platinum' AND (g.owner_user_id={$learner} OR EXISTS(SELECT 1 FROM st_family_links l JOIN st_users p ON p.id=l.parent_user_id WHERE l.parent_user_id=g.owner_user_id AND l.child_user_id={$learner} AND l.status='active' AND p.status='active' AND p.account_kind='parent')) AND EXISTS(SELECT 1 FROM st_package_courses pc JOIN st_packages p ON p.id=pc.package_id WHERE pc.course_id={$course} AND p.is_official=1 AND p.status='published')))";
	}
	public function family_practice($parent, $lock = FALSE)
	{
		$q = $this->db->query("SELECT g.id FROM st_commerce_grants g JOIN st_users u ON u.id=g.owner_user_id WHERE ".$this->environment_scope('g.environment')." AND g.owner_user_id=? AND g.learner_user_id=? AND u.status='active' AND u.account_kind='parent' AND g.status='active' AND g.starts_at<=UTC_TIMESTAMP() AND g.ends_at>UTC_TIMESTAMP() AND (g.kind='platinum' OR g.family_practice_enabled=1) LIMIT 1".($lock ? ' FOR UPDATE' : ''), array($parent, $parent));
		if ($q === FALSE) { throw new RuntimeException('Family access unavailable.'); }
		return $q->num_rows() > 0;
	}
	public function test_family($learner)
	{
		$q = $this->db->query("SELECT ur.user_id FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id JOIN st_users u ON u.id=ur.user_id WHERE r.role_code='system_admin' AND u.status='active' AND (ur.user_id=? OR EXISTS(SELECT 1 FROM st_family_links l WHERE l.parent_user_id=ur.user_id AND l.child_user_id=? AND l.status='active')) LIMIT 1", array($learner, $learner));
		return $q !== FALSE && $q->num_rows() > 0;
	}
	public function extra_slots($parent, $lock = FALSE)
	{
		$q = $this->db->query('SELECT id FROM st_family_slots WHERE '.$this->environment_scope('environment').' AND owner_user_id=?'.($lock ? ' FOR UPDATE' : ''), array($parent));
		if ($q === FALSE) { throw new RuntimeException('Slot capacity unavailable.'); } return $q->num_rows();
	}
}
