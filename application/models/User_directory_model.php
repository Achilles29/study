<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_directory_model extends CI_Model
{
	public function search($query, $filters, $offset = 0, $count_only = FALSE)
	{
		$sql = ' FROM st_users u WHERE 1=1';
		$params = array();
		if ($query !== '')
		{
			$sql .= " AND (u.username LIKE ? ESCAPE '!' OR CAST(u.id AS CHAR)=?)";
			$params[] = '%'.str_replace(array('!', '%', '_'), array('!!', '!%', '!_'), $query).'%';
			$params[] = $query;
		}
		foreach (array('jenis' => 'account_kind', 'status' => 'status') as $key => $column)
		{
			if ($filters[$key] !== '')
			{
				$sql .= ' AND u.'.$column.'=?';
				$params[] = $filters[$key];
			}
		}
		if ($filters['peran'] === 'none')
		{
			$sql .= ' AND NOT EXISTS (SELECT 1 FROM st_user_roles ur WHERE ur.user_id=u.id)';
		}
		elseif ($filters['peran'] !== '')
		{
			$sql .= ' AND EXISTS (SELECT 1 FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.role_code=?)';
			$params[] = $filters['peran'];
		}
		if ($count_only)
		{
			return (int) $this->db->query('SELECT COUNT(*) AS total'.$sql, $params)->row_array()['total'];
		}
		$orders = array('newest' => 'u.created_at DESC,u.id DESC', 'oldest' => 'u.created_at ASC,u.id ASC', 'username' => 'u.username ASC,u.id ASC');
		$order = isset($orders[$filters['urutan']]) ? $orders[$filters['urutan']] : $orders['newest'];
		// Deliberately exclude email, display name, credentials, learning data and family links.
		$users = $this->db->query('SELECT u.id,u.username,u.account_kind,u.status,u.created_at'.$sql.' ORDER BY '.$order.' LIMIT 20 OFFSET '.max(0, (int) $offset), $params)->result_array();
		$roles = array();
		if ($users)
		{
			$rows = $this->db->select('ur.user_id,r.role_code')->from('st_user_roles ur')->join('st_roles r', 'r.id=ur.role_id')->where_in('ur.user_id', array_column($users, 'id'))->order_by('r.id', 'ASC')->get()->result_array();
			foreach ($rows as $row)
			{
				$roles[$row['user_id']][] = $row['role_code'];
			}
		}
		foreach ($users as &$user)
		{
			$user['roles'] = isset($roles[$user['id']]) ? $roles[$user['id']] : array();
		}
		unset($user);
		return $users;
	}
}
