<?php

define('BASEPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);

if ( ! function_exists('html_escape'))
{
	function html_escape($value)
	{
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'study_ui_helper.php';

function study_test_same($expected, $actual, $description)
{
	if ($expected !== $actual)
	{
		fwrite(STDERR, 'FAIL ' . $description . "\n");
		exit(1);
	}
}

study_test_same(
	' aria-invalid="true" aria-describedby="field-error-email"',
	study_field_error_attributes(array('email' => 'Alamat email wajib diisi.'), 'email'),
	'field error attributes are associated with the input'
);
study_test_same('', study_field_error_attributes(NULL, 'email'), 'missing error data renders no attributes');
study_test_same(
	' aria-invalid="true" aria-describedby="field-error-user-name-"',
	study_field_error_attributes(array('user name/' => 'Tidak valid.'), 'user name/'),
	'unsafe field-name characters are normalized'
);
study_test_same(
	'<div class="field-error" id="field-error-name">alert(&quot;x&quot;)</div>',
	study_render_field_error(array('name' => '<script>alert("x")</script>'), 'name'),
	'field error output strips markup and escapes text'
);
study_test_same('Draf', study_course_status_label('draft'), 'known course state has a localized label');
study_test_same('Status belum dikenal', study_course_status_label('unexpected'), 'unknown course state has a safe fallback');
study_test_same('Sekolah dasar', study_target_group_label('primary'), 'known learner group has a localized label');
study_test_same('Usia 7–12 tahun', study_age_range_label('7', '12'), 'complete age range is formatted');
study_test_same('Mulai usia 13 tahun', study_age_range_label('13', NULL), 'minimum-only age range is formatted');
study_test_same('Sampai usia 5 tahun', study_age_range_label(NULL, '5'), 'maximum-only age range is formatted');
study_test_same('', study_age_range_label(NULL, NULL), 'missing age range stays empty');
study_test_same('Memiliki lisensi', study_rights_status_label('licensed'), 'content-rights state has a localized label');
study_test_same('15 Okt 2026, 14.00 WIB', study_format_datetime('2026-10-15 07:00:00'), 'UTC database timestamp is converted to Jakarta time and localized');
study_test_same('—', study_format_datetime('not-a-date'), 'invalid timestamp has a safe placeholder');
study_test_same('1.234.567', study_format_number('1234567'), 'integer uses Indonesian thousands separator without float precision loss');
study_test_same('Rp 12.500', study_format_rupiah(12500), 'Rupiah display uses Indonesian integer formatting');
study_test_same('2.000 kredit', study_format_credits('2000'), 'credits are displayed as a distinct unit');
study_test_same('75 poin', study_format_points(75), 'points are displayed as a distinct unit');
study_test_same('—', study_format_credits(-1), 'negative credit balance is not presented as valid');
study_test_same('—', study_format_number('1e6'), 'non-canonical numeric input is rejected');

$list_now = '2026-10-15 08:00:00';
study_test_same('Kedaluwarsa', study_practice_status_label(array('status' => 'available', 'available_until' => $list_now, 'available_from' => '2026-10-16 08:00:00'), $list_now), 'deadline equality is expired and takes precedence over future start');
study_test_same('Menunggu jadwal', study_practice_status_label(array('status' => 'available', 'available_until' => '2026-10-17 08:00:00', 'available_from' => '2026-10-16 08:00:00'), $list_now), 'future start is scheduled');
study_test_same('Siap dikerjakan', study_practice_status_label(array('status' => 'available', 'available_until' => '2026-10-17 08:00:00', 'available_from' => $list_now), $list_now), 'start equality is available');
study_test_same('Selesai', study_practice_status_label(array('status' => 'completed', 'available_until' => '2026-10-14 08:00:00'), $list_now), 'completed history does not become expired');
study_test_same('Dibatalkan', study_practice_status_label(array('status' => 'cancelled', 'available_until' => '2026-10-14 08:00:00'), $list_now), 'cancelled history does not become expired');

fwrite(STDOUT, "PASS isolated Study UI helper unit checks (25 assertions).\n");
