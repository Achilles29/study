<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Family_practice_model extends CI_Model
{
	private function scope($parent_user_id, $child_user_id)
	{
		$this->db->from('st_family_links l');
		$this->db->join('st_users c', "c.id = l.child_user_id AND c.account_kind = 'child' AND c.status = 'active'");
		$this->db->join('st_users p', "p.id = l.parent_user_id AND p.account_kind = 'parent' AND p.status = 'active'");
		$this->db->where('l.parent_user_id', (int) $parent_user_id);
		$this->db->where('l.child_user_id', (int) $child_user_id);
		$this->db->where('l.status', 'active');
	}

	private function attempts_scope($parent_user_id, $child_user_id)
	{
		$this->scope($parent_user_id, $child_user_id);
		$this->db->join('st_practice_sessions s', "s.learner_user_id = c.id AND s.session_type IN ('course_quiz','self_practice','assessment')");
		$this->db->join('st_practice_attempts a', 'a.session_id = s.id AND a.learner_user_id = c.id');
	}

	public function child($parent_user_id, $child_user_id)
	{
		$this->scope($parent_user_id, $child_user_id);
		return $this->db->select('c.id, c.display_name')->get()->row_array();
	}

	public function summary($parent_user_id, $child_user_id)
	{
		$this->attempts_scope($parent_user_id, $child_user_id);
		$this->db->select("COUNT(*) AS total, COALESCE(SUM(a.status = 'graded'), 0) AS completed, COALESCE(SUM(a.status = 'in_progress'), 0) AS active", FALSE);
		return $this->db->get()->row_array();
	}

	public function history($parent_user_id, $child_user_id, $limit, $offset)
	{
		$this->attempts_scope($parent_user_id, $child_user_id);
		$this->db->select("a.id, a.status, a.question_count, a.started_at, a.submitted_at, s.title, CASE WHEN a.status = 'graded' THEN a.score ELSE NULL END AS score, CASE WHEN a.status = 'graded' THEN a.correct_count ELSE NULL END AS correct_count", FALSE);
		return $this->db->order_by('a.id', 'DESC')->limit(min(10, max(1, (int) $limit)), max(0, (int) $offset))->get()->result_array();
	}

	public function result($parent_user_id, $child_user_id, $attempt_id)
	{
		$this->attempts_scope($parent_user_id, $child_user_id);
		return $this->db->select('a.*, s.title, s.course_id, s.rules_version, s.session_type')->where('a.id', (int) $attempt_id)->where('a.status', 'graded')->get()->row_array();
	}
}
