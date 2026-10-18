<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function study_valid_password($value, $minimum = 1)
{
	// PASSWORD_DEFAULT currently uses bcrypt: never accept an ignored suffix.
	return is_string($value) && strlen($value) <= 72 && strpos($value, "\0") === FALSE
		&& mb_check_encoding($value, 'UTF-8') && mb_strlen($value, 'UTF-8') >= (int) $minimum;
}

function study_password_minimum($user)
{
	return in_array('system_admin', $user['roles'], TRUE) ? 16 : ($user['account_kind'] === 'child' ? study_child_password_minimum() : 12);
}

function study_child_password_minimum()
{
	// Owner-selected simple child passwords; nonempty and bcrypt byte limits remain.
	return 1;
}

function study_credential_stamp($user)
{
	$ci =& get_instance();
	$value = $user['id'].'|'.$user['password_hash'];
	// Version zero preserves existing sessions during the additive deployment.
	if ((int) ($user['auth_version'] ?? 0) > 0) { $value .= '|v'.(int) $user['auth_version']; }
	return hash_hmac('sha256', 'credential|'.$value, (string) $ci->config->item('encryption_key'));
}
