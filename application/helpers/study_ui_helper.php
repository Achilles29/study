<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function study_navigation_attributes($view, $item, $is_preview = FALSE)
{
	$pages = array(
		'home' => 'home/index',
		'catalog' => 'catalog/index',
		'family' => 'family/children',
		'admin' => 'admin/index',
		'dashboard' => 'dashboard/index',
		'sign_in' => 'auth/sign_in',
		'sign_up' => 'auth/sign_up'
	);
	if ( ! is_string($view) || ! is_string($item) || ! isset($pages[$item]))
	{
		return '';
	}
	if ($is_preview)
	{
		return $item === 'admin' ? ' aria-current="location"' : '';
	}
	if ($view === $pages[$item])
	{
		return ' aria-current="page"';
	}
	$sections = array('catalog' => 'catalog/', 'family' => 'family/', 'admin' => 'admin/');
	if (isset($sections[$item]) && strpos($view, $sections[$item]) === 0)
	{
		return ' aria-current="location"';
	}
	return '';
}

function study_matching_status_label($status)
{
	$labels = array('in_progress' => 'Sedang bermain', 'completed' => 'Selesai', 'abandoned' => 'Dihentikan');
	return isset($labels[$status]) ? $labels[$status] : 'Status tidak dikenal';
}

function study_lesson_progress_label($status)
{
	$labels = array('not_started' => 'Belum dimulai', 'in_progress' => 'Sedang dipelajari', 'completed' => 'Selesai');
	return is_string($status) && isset($labels[$status]) ? $labels[$status] : 'Progres belum diketahui';
}

if ( ! function_exists('study_field_error_attributes'))
{
	function study_field_error_attributes($errors, $field)
	{
		if ( ! is_array($errors) || ! isset($errors[$field]))
		{
			return '';
		}

		$safe_field = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $field);
		return ' aria-invalid="true" aria-describedby="field-error-'.$safe_field.'"';
	}
}

if ( ! function_exists('study_render_field_error'))
{
	function study_render_field_error($errors, $field)
	{
		if ( ! is_array($errors) || ! isset($errors[$field]))
		{
			return '';
		}

		$safe_field = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $field);
		$message = html_escape(strip_tags((string) $errors[$field]));
		return '<div class="field-error" id="field-error-'.$safe_field.'">'.$message.'</div>';
	}
}

if ( ! function_exists('study_course_status_label'))
{
	function study_course_status_label($status)
	{
		$labels = array(
			'draft' => 'Draf',
			'review' => 'Menunggu review',
			'published' => 'Terbit',
			'archived' => 'Diarsipkan'
		);

		return isset($labels[$status]) ? $labels[$status] : 'Status belum dikenal';
	}
}

if ( ! function_exists('study_target_group_label'))
{
	function study_target_group_label($target_group)
	{
		$labels = array(
			'early_childhood' => 'Usia dini',
			'primary' => 'Sekolah dasar',
			'secondary' => 'Remaja',
			'adult' => 'Dewasa',
			'all_ages' => 'Semua usia'
		);

		return isset($labels[$target_group]) ? $labels[$target_group] : 'Kelompok belum ditentukan';
	}
}

if ( ! function_exists('study_age_range_label'))
{
	function study_age_range_label($minimum_age, $maximum_age)
	{
		$has_minimum = $minimum_age !== NULL && $minimum_age !== '';
		$has_maximum = $maximum_age !== NULL && $maximum_age !== '';
		if ($has_minimum && $has_maximum)
		{
			return 'Usia '.(int) $minimum_age.'–'.(int) $maximum_age.' tahun';
		}
		if ($has_minimum)
		{
			return 'Mulai usia '.(int) $minimum_age.' tahun';
		}
		if ($has_maximum)
		{
			return 'Sampai usia '.(int) $maximum_age.' tahun';
		}
		return '';
	}
}

if ( ! function_exists('study_rights_status_label'))
{
	function study_rights_status_label($rights_status)
	{
		$labels = array(
			'pending_review' => 'Menunggu verifikasi hak',
			'owned' => 'Dibuat sendiri/tim',
			'licensed' => 'Memiliki lisensi',
			'public_domain' => 'Domain publik'
		);

		return isset($labels[$rights_status]) ? $labels[$rights_status] : 'Hak materi belum dikenal';
	}
}

if ( ! function_exists('study_format_datetime'))
{
	function study_format_datetime($value)
	{
		if ( ! is_string($value) || trim($value) === '')
		{
			return '—';
		}

		try
		{
			$date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
		}
		catch (Exception $exception)
		{
			return '—';
		}

		$months = array('Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des');
		$local_date = $date->setTimezone(new DateTimeZone('Asia/Jakarta'));
		return $local_date->format('j').' '.$months[(int) $local_date->format('n') - 1].' '.$local_date->format('Y, H.i').' WIB';
	}
}

if ( ! function_exists('study_format_number'))
{
	function study_format_number($value)
	{
		if (is_int($value))
		{
			$value = (string) $value;
		}
		if ( ! is_string($value) || ! preg_match('/\A-?(?:0|[1-9][0-9]*)\z/', $value))
		{
			return '—';
		}

		$is_negative = substr($value, 0, 1) === '-';
		$digits = $is_negative ? substr($value, 1) : $value;
		$formatted = preg_replace('/\B(?=(?:[0-9]{3})+(?![0-9]))/', '.', $digits);
		return ($is_negative && $digits !== '0' ? '-' : '').$formatted;
	}
}

if ( ! function_exists('study_format_rupiah'))
{
	function study_format_rupiah($amount)
	{
		$formatted = study_format_number($amount);
		return $formatted === '—' ? $formatted : 'Rp '.$formatted;
	}
}

if ( ! function_exists('study_format_credits'))
{
	function study_format_credits($credits)
	{
		$formatted = study_format_number($credits);
		return $formatted === '—' || substr($formatted, 0, 1) === '-' ? '—' : $formatted.' kredit';
	}
}

if ( ! function_exists('study_practice_status_options'))
{
	function study_practice_status_options()
	{
		return array('draft' => 'Draf', 'scheduled' => 'Menunggu jadwal', 'available' => 'Siap dikerjakan', 'in_progress' => 'Sedang dikerjakan', 'completed' => 'Selesai', 'expired' => 'Kedaluwarsa', 'cancelled' => 'Dibatalkan');
	}
}

if ( ! function_exists('study_practice_status_label'))
{
	function study_practice_status_label($session, $now = NULL)
	{
		$labels = study_practice_status_options();
		if (in_array($session['status'], array('available', 'in_progress'), TRUE))
		{
			$now = $now === NULL ? gmdate('Y-m-d H:i:s') : $now;
			if ( ! empty($session['available_until']) && $session['available_until'] <= $now)
			{
				return 'Kedaluwarsa';
			}
			if ( ! empty($session['available_from']) && $session['available_from'] > $now)
			{
				return 'Menunggu jadwal';
			}
		}
		return isset($labels[$session['status']]) ? $labels[$session['status']] : 'Tidak tersedia';
	}
}

if ( ! function_exists('study_format_points'))
{
	function study_format_points($points)
	{
		$formatted = study_format_number($points);
		return $formatted === '—' || substr($formatted, 0, 1) === '-' ? '—' : $formatted.' poin';
	}
}
