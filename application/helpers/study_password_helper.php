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
	return in_array('system_admin', $user['roles'], TRUE) ? 16 : ($user['account_kind'] === 'child' ? 10 : 12);
}
