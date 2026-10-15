<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Family_matching_model extends CI_Model
{
	private function source()
	{
		return " FROM st_matching_runs r
			JOIN st_users c ON c.id=r.learner_user_id AND c.account_kind='child' AND c.status='active'
			JOIN st_family_links l ON l.child_user_id=c.id AND l.status='active'
			JOIN st_users p ON p.id=l.parent_user_id AND p.account_kind='parent' AND p.status='active'
			WHERE p.id=? AND c.id=?";
	}

	public function summary($parent_user_id, $child_user_id)
	{
		return $this->db->query("SELECT COUNT(*) AS total, COALESCE(SUM(r.status='completed'),0) AS completed, COALESCE(SUM(r.status='in_progress'),0) AS active, COALESCE(SUM(r.status='abandoned'),0) AS abandoned".$this->source(), array((int) $parent_user_id, (int) $child_user_id))->row_array();
	}

	public function history($parent_user_id, $child_user_id, $query, $status, $offset = 0, $count_only = FALSE)
	{
		$sql = $this->source();
		$params = array((int) $parent_user_id, (int) $child_user_id);
		if ($query !== '')
		{
			$sql .= " AND r.title_snapshot LIKE ? ESCAPE '!'";
			$params[] = '%'.str_replace(array('!', '%', '_'), array('!!', '!%', '!_'), $query).'%';
		}
		if (in_array($status, array('in_progress', 'completed', 'abandoned'), TRUE))
		{
			$sql .= ' AND r.status=?';
			$params[] = $status;
		}
		if ($count_only)
		{
			return (int) $this->db->query('SELECT COUNT(*) AS total'.$sql, $params)->row_array()['total'];
		}
		return $this->db->query('SELECT r.id,r.title_snapshot,r.status,r.started_at'.$sql.' ORDER BY r.id DESC LIMIT 10 OFFSET '.max(0, (int) $offset), $params)->result_array();
	}

	public function result($parent_user_id, $child_user_id, $run_id)
	{
		$row = $this->db->query('SELECT r.title_snapshot,r.status,r.started_at,r.finished_at,r.elapsed_seconds,r.state_json,r.snapshot_json'.$this->source().' AND r.id=?', array((int) $parent_user_id, (int) $child_user_id, (int) $run_id))->row_array();
		if ( ! $row)
		{
			return NULL;
		}
		$state = json_decode($row['state_json'], TRUE);
		$snapshot = json_decode($row['snapshot_json'], TRUE);
		if ( ! is_array($state) || ! is_array($snapshot) || ! isset($state['matched'], $state['move_count'], $state['mistake_count'], $snapshot['cards']) || ! is_array($state['matched']) || ! is_array($snapshot['cards']))
		{
			return NULL;
		}
		// Only counts reach the report view; never expose card text, request keys or child mutation tokens.
		$row['matched_count'] = count($state['matched']);
		$row['pair_count'] = count($snapshot['cards']);
		$row['move_count'] = (int) $state['move_count'];
		$row['mistake_count'] = (int) $state['mistake_count'];
		unset($row['state_json'], $row['snapshot_json']);
		return $row;
	}
}
