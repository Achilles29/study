param(
	[Parameter(Mandatory = $true)][string] $Php81Path,
	[Parameter(Mandatory = $true)][string] $MysqlPath,
	[Parameter(Mandatory = $true)][string] $BaseUrl,
	[Parameter(Mandatory = $false)][string] $ChromePath = '',
	[Parameter(Mandatory = $false)][switch] $CaptureAdminVisualBaseline
)

$ErrorActionPreference = 'Stop'
$suffix = [Guid]::NewGuid().ToString('N').Substring(0, 14)
$username = "qa_admin_$suffix"
$email = "$username@example.test"
$fixtureCourseSlug = "qa-admin-input-draft-$suffix"
$validCourseSlug = "qa-valid-course-$suffix"
$password = 'Temporary-Study-Admin-Input-123!'
$adminVisualBaselineDir = Join-Path (Split-Path -Parent $PSScriptRoot) 'docs/visual-baseline/2026-10-15-admin-panel-final'
if ($ChromePath -and -not (Test-Path -LiteralPath $ChromePath -PathType Leaf)) { throw "Chrome executable was not found: $ChromePath" }
if ($CaptureAdminVisualBaseline) {
	if (-not $ChromePath -or -not (Test-Path -LiteralPath $ChromePath -PathType Leaf)) { throw 'A valid -ChromePath is required with -CaptureAdminVisualBaseline.' }
	if (Test-Path -LiteralPath $adminVisualBaselineDir -PathType Container) { throw "Visual baseline directory already exists; refusing to overwrite: $adminVisualBaselineDir" }
}
$passwordHash = (& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim()
if ($LASTEXITCODE -ne 0 -or -not $passwordHash) { throw 'Could not create temporary QA password hash.' }

function Invoke-Db([string] $Sql) {
	$output = & $MysqlPath '-uroot' '-D' 'db_study' '-N' '-B' '-e' $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Database QA operation failed: $output" }
	return ($output -join "`n").Trim()
}

function Get-CsrfToken($Response) {
	$match = [regex]::Match($Response.Content, 'name="study_csrf_token"\s+value="([^"]+)"')
	if (-not $match.Success) { throw 'CSRF hidden field was not found in rendered form.' }
	return [System.Net.WebUtility]::HtmlDecode($match.Groups[1].Value)
}

function Get-HttpStatus([scriptblock] $Request) {
	try { return [int] ((& $Request).StatusCode) }
	catch {
		if ($null -ne $_.Exception.Response) { return [int] $_.Exception.Response.StatusCode }
		throw
	}
}

function Assert-Equal($Actual, $Expected, [string] $Message) {
	if ($Actual -ne $Expected) { throw "$Message (expected '$Expected', got '$Actual')." }
}

function Assert-AccessibleFieldError($Html, [string] $Field) {
	$fieldId = [regex]::Escape($Field)
	$errorId = [regex]::Escape('field-error-' + $Field)
	$control = '<(?:input|select|textarea)\b(?=[^>]*\bname="' + $fieldId + '")(?=[^>]*\baria-invalid="true")(?=[^>]*\baria-describedby="' + $errorId + '")[^>]*>'
	$error = '<[^>]+\bid="' + $errorId + '"[^>]*>'
	if ($Html -notmatch $control -or $Html -notmatch $error) { throw "Field '$Field' is missing its accessible validation-message relationship." }
}

function Invoke-AdminBrowserCheck([string] $RootUrl, [string] $Identity) {
	$identityVariable = 'STUDY_QA_LOGIN_IDENTITY'
	$passwordVariable = 'STUDY_QA_LOGIN_PASSWORD'
	$oldIdentity = [Environment]::GetEnvironmentVariable($identityVariable, 'Process')
	$oldPassword = [Environment]::GetEnvironmentVariable($passwordVariable, 'Process')
	try {
		[Environment]::SetEnvironmentVariable($identityVariable, $Identity, 'Process')
		[Environment]::SetEnvironmentVariable($passwordVariable, $password, 'Process')
		$visualCheck = Join-Path $PSScriptRoot 'browser-viewport-check.mjs'
		$browserArguments = @($visualCheck, '--base-url', $RootUrl, '--chrome', $ChromePath, '--extra-path', 'kelola/kursus', '--extra-name', 'admin-courses', '--only-extra-route', '--exercise-navigation')
		if ($CaptureAdminVisualBaseline) { $browserArguments += @('--capture-screenshots', '--screenshot-dir', $adminVisualBaselineDir) }
		& node @browserArguments
		if ($LASTEXITCODE -ne 0) { throw 'Admin course-list visual baseline browser check failed.' }
	}
	finally {
		[Environment]::SetEnvironmentVariable($identityVariable, $oldIdentity, 'Process')
		[Environment]::SetEnvironmentVariable($passwordVariable, $oldPassword, 'Process')
	}
}

try {
	$escapedHash = $passwordHash.Replace("'", "''")
	$setupSql = @"
START TRANSACTION;
INSERT INTO st_users (username,email,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at)
VALUES ('$username','$email','$escapedHash','QA Admin Input','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @qa_admin_id = LAST_INSERT_ID();
INSERT INTO st_user_roles (user_id,role_id,granted_by_user_id,created_at)
VALUES (@qa_admin_id,(SELECT id FROM st_roles WHERE role_code='system_admin'),@qa_admin_id,UTC_TIMESTAMP());
INSERT INTO st_courses (author_user_id,updated_by_user_id,title,slug,summary,learning_objectives,target_group,status,content_version,rights_status,source_note,is_free,created_at,updated_at)
VALUES (@qa_admin_id,@qa_admin_id,'QA Array Draft','$fixtureCourseSlug','Synthetic admin input validation fixture','Test safe form input','adult','draft',1,'owned','Temporary QA fixture',1,UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @qa_fixture_course_id = LAST_INSERT_ID();
INSERT INTO st_course_sections (course_id,title,sort_order,created_at,updated_at)
VALUES (@qa_fixture_course_id,'QA Section',1,UTC_TIMESTAMP(),UTC_TIMESTAMP());
COMMIT;
SELECT @qa_admin_id, @qa_fixture_course_id;
"@
	$ids = (Invoke-Db $setupSql) -split "\s+"
	if ($ids.Count -lt 2) { throw 'QA admin/course fixture IDs were not returned.' }
	$adminId = [int] $ids[0]
	$fixtureCourseId = [int] $ids[1]
	$root = $BaseUrl.TrimEnd('/') + '/'
	$web = New-Object Microsoft.PowerShell.Commands.WebRequestSession

	$loginPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -WebSession $web
	$cookieHeader = [string] $loginPage.Headers['Set-Cookie']
	$cookieParts = [regex]::Split($cookieHeader, ',(?=[^;,]+=)')
	$sessionCookieHeader = @($cookieParts | Where-Object { $_ -match '^study_session=' })[0]
	if (-not $sessionCookieHeader -or $sessionCookieHeader -notmatch '(?i)HttpOnly' -or $sessionCookieHeader -notmatch '(?i)SameSite=Lax') { throw 'Session cookie is missing HttpOnly or SameSite=Lax.' }
	$sessionBeforeLogin = $web.Cookies.GetCookies([Uri] $root)['study_session'].Value
	$loginBody = @{ identity = $username; password = $password; study_csrf_token = (Get-CsrfToken $loginPage) }
	$loginResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -Method Post -WebSession $web -Body $loginBody
	if ($loginResult.Content -notmatch 'Panel pengelola') { throw 'Temporary admin did not complete the login flow.' }
	$sessionAfterLogin = $web.Cookies.GetCookies([Uri] $root)['study_session'].Value
	if ( ! $sessionBeforeLogin -or ! $sessionAfterLogin -or $sessionBeforeLogin -eq $sessionAfterLogin) { throw 'Session identifier did not rotate after login.' }
	Write-Output 'PASS session cookie uses HttpOnly/SameSite=Lax and session ID rotates at login.'
	if ($ChromePath) {
		if ($CaptureAdminVisualBaseline) {
		$baselineContent = @"
UPDATE st_users SET display_name='Tim Konten' WHERE id=$adminId;
UPDATE st_courses SET title='Matematika SD: Bilangan Dasar', summary='Materi awal untuk mengenal bilangan dan operasi dasar.', learning_objectives='Memahami bilangan asli dan penjumlahan sederhana.', target_group='primary' WHERE id=$fixtureCourseId;
"@
		Invoke-Db $baselineContent | Out-Null
		}
		Invoke-AdminBrowserCheck $root $username
		if ($CaptureAdminVisualBaseline) {
		Invoke-Db "UPDATE st_users SET display_name='QA Admin Input' WHERE id=$adminId; UPDATE st_courses SET title='QA Array Draft', summary='Synthetic admin input validation fixture', learning_objectives='Test safe form input', target_group='adult' WHERE id=$fixtureCourseId;" | Out-Null
		Write-Output "PASS admin course-list screenshots saved to $adminVisualBaselineDir after 360/768/1280 px and keyboard/accessibility checks."
		}
	}
	$loginAudit = Invoke-Db "SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$adminId AND entity_type='user' AND entity_id=$adminId AND event_type='auth.login' AND JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.method'))='password';"
	$lastLogin = Invoke-Db "SELECT COUNT(*) FROM st_users WHERE id=$adminId AND last_login_at IS NOT NULL;"
	$expectedLoginAudits = if ($ChromePath) { '2' } else { '1' }
	if ($loginAudit -ne $expectedLoginAudits -or $lastLogin -ne '1') { throw 'Successful login was not recorded without credentials in the audit details.' }
	Write-Output 'PASS successful login timestamp and non-sensitive audit event are recorded.'
	$malformedList = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kelola/kursus?q%5B%5D=tidak-valid&status%5B%5D=draft&halaman%5B%5D=9') -WebSession $web
	if ([int] $malformedList.StatusCode -ne 200 -or $malformedList.Content -match 'Array to string conversion|Warning:') { throw 'Array-valued admin list filters caused an invalid response.' }
	Write-Output 'PASS admin list safely ignores array-valued search, status, and pagination parameters.'
	$noMatchQuery = [System.Uri]::EscapeDataString("StudyNoMatch-$suffix")
	$noMatchList = Invoke-WebRequest -UseBasicParsing -Uri ($root + "kelola/kursus?q=$noMatchQuery") -WebSession $web
	if ($noMatchList.Content -notmatch 'Tidak ada kursus yang cocok' -or $noMatchList.Content -notmatch 'Hapus filter' -or $noMatchList.Content -match 'Belum ada kursus pada daftar ini') {
		throw 'An empty filtered course list should offer a filter-specific message and a reset action.'
	}
	Write-Output 'PASS an unmatched course search shows a filter-specific empty state and reset action.'
	# Bypass request validation deliberately: this fixture proves stored values are escaped at output.
	$storedXss = '<img src=x onerror=alert(1)>'
	Invoke-Db "UPDATE st_courses SET title='$storedXss', summary='$storedXss', learning_objectives='$storedXss', source_note='$storedXss' WHERE slug='$fixtureCourseSlug';" | Out-Null
	$fixtureCourseId = [int] (Invoke-Db "SELECT id FROM st_courses WHERE slug='$fixtureCourseSlug';")
	$unsafeList = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kelola/kursus') -WebSession $web
	$unsafeDetail = Invoke-WebRequest -UseBasicParsing -Uri ($root + "kelola/kursus/$fixtureCourseId") -WebSession $web
	$escapedXss = '&lt;img src=x onerror=alert(1)&gt;'
	if ($unsafeList.Content.Contains($storedXss) -or -not $unsafeList.Content.Contains($escapedXss)) { throw 'Stored course title was not escaped in the admin list.' }
	if ($unsafeDetail.Content.Contains($storedXss) -or -not $unsafeDetail.Content.Contains($escapedXss)) { throw 'Stored course fields were not escaped in the admin detail page.' }
	Write-Output 'PASS stored markup in course title, summary, objectives, and source is rendered as escaped text in list/detail pages.'
	$categoryPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kelola/kategori/tambah') -WebSession $web
	$invalidCategory = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kelola/kategori/tambah') -Method Post -WebSession $web -Body @{
		name = ''
		study_csrf_token = Get-CsrfToken $categoryPage
	}
	Assert-AccessibleFieldError $invalidCategory.Content 'name'
	Write-Output 'PASS category validation errors are linked to their controls for assistive technology.'

	$createPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kelola/kursus/tambah') -WebSession $web
	$createBody = @{
		'prerequisites[]' = @('unexpected','array');
		study_csrf_token = Get-CsrfToken $createPage
	}
	$createArrayStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kelola/kursus/tambah') -Method Post -WebSession $web -Body $createBody }
	Assert-Equal $createArrayStatus 400 'Array-shaped course prerequisites should be rejected'
	Write-Output 'PASS array-shaped course prerequisites input is rejected.'
	$invalidCoursePage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kelola/kursus/tambah') -WebSession $web
	$invalidCourse = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kelola/kursus/tambah') -Method Post -WebSession $web -Body @{
		title = ''
		study_csrf_token = Get-CsrfToken $invalidCoursePage
	}
	foreach ($field in @('title','summary','learning_objectives','target_group','estimated_minutes','rights_status','source_note')) {
		Assert-AccessibleFieldError $invalidCourse.Content $field
	}
	Write-Output 'PASS course validation errors are linked to their controls for assistive technology.'

	$lessonPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + "kelola/kursus/$fixtureCourseId/pelajaran/tambah") -WebSession $web
	$lessonBody = @{
		title = 'QA array lesson';
		'body_text[]' = @('unexpected','array');
		estimated_minutes = '5';
		rights_status = 'owned';
		source_note = 'Temporary QA source';
		study_csrf_token = Get-CsrfToken $lessonPage
	}
	$lessonArrayStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + "kelola/kursus/$fixtureCourseId/pelajaran/tambah") -Method Post -WebSession $web -Body $lessonBody }
	Assert-Equal $lessonArrayStatus 400 'Array-shaped lesson body should be rejected'
	Write-Output 'PASS array-shaped lesson creation input is rejected.'
	$invalidLessonPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + "kelola/kursus/$fixtureCourseId/pelajaran/tambah") -WebSession $web
	$invalidLesson = Invoke-WebRequest -UseBasicParsing -Uri ($root + "kelola/kursus/$fixtureCourseId/pelajaran/tambah") -Method Post -WebSession $web -Body @{
		title = ''
		body_text = ''
		estimated_minutes = '0'
		rights_status = ''
		source_note = ''
		study_csrf_token = Get-CsrfToken $invalidLessonPage
	}
	foreach ($field in @('title','body_text','estimated_minutes','rights_status','source_note')) {
		Assert-AccessibleFieldError $invalidLesson.Content $field
	}
	Write-Output 'PASS lesson validation errors are linked to their controls for assistive technology.'

	$validPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kelola/kursus/tambah') -WebSession $web
	$validBody = @{
		title = "QA Valid Course $suffix";
		summary = 'A sufficiently detailed synthetic summary for QA.';
		learning_objectives = 'Test scalar course form values.';
		prerequisites = 'Mengenal bilangan bulat dasar.';
		target_group = 'adult';
		category_id = '';
		age_min = '18';
		age_max = '65';
		estimated_minutes = '15';
		rights_status = 'owned';
		source_note = 'Temporary QA-owned synthetic content.';
		is_free = 'yes';
		study_csrf_token = Get-CsrfToken $validPage
	}
	$validResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kelola/kursus/tambah') -Method Post -WebSession $web -Body $validBody
	if ($validResult.StatusCode -ne 200 -or $validResult.Content -notmatch [regex]::Escape("QA Valid Course $suffix") -or $validResult.Content -notmatch 'Persiapan belajar|Prasyarat/persiapan:</strong> Mengenal bilangan bulat dasar\.') { throw 'A valid scalar course form or its optional prerequisites metadata failed.' }
	$hasIndonesianLabels = $validResult.Content -match 'Draf' -and $validResult.Content -match 'Kelompok:</strong> Dewasa' -and $validResult.Content -match 'Hak materi:</strong> Dibuat sendiri/tim' -and $validResult.Content -match 'Sasaran usia:</strong> Usia 18[^0-9]+65 tahun'
	$hasRawLabels = $validResult.Content -match 'draft|Kelompok:</strong> adult|Hak materi:</strong> owned'
	if ( ! $hasIndonesianLabels -or $hasRawLabels) { throw "Course detail labels mismatch (translated=$hasIndonesianLabels, raw=$hasRawLabels)." }
	$validCourseId = [int] (Invoke-Db "SELECT id FROM st_courses WHERE slug='$validCourseSlug';")
	if ($validCourseId -lt 1) { throw 'Valid course was not stored with the expected slug.' }
	$updatedCourseList = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kelola/kursus?q=' + [Uri]::EscapeDataString("QA Valid Course $suffix")) -WebSession $web
	if ($updatedCourseList.Content -notmatch '\d{1,2} [A-Z][a-z]{2} \d{4}, \d{2}\.\d{2} WIB' -or $updatedCourseList.Content -match 'UTC') { throw 'Admin course list should format its stored UTC timestamp as Indonesian Jakarta time.' }
	Write-Output 'PASS valid course creation remains functional and internal course metadata keys are translated to Indonesian labels.'
	Write-Output 'PASS admin course list formats update timestamps in Indonesian Jakarta time.'
	$editPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + "kelola/kursus/$validCourseId/edit") -WebSession $web
	$editBody = $validBody.Clone()
	$editBody.prerequisites = 'Bekal yang disarankan: operasi pengurangan sederhana.'
	$editBody.study_csrf_token = Get-CsrfToken $editPage
	$editResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + "kelola/kursus/$validCourseId/edit") -Method Post -WebSession $web -Body $editBody
	$editedPrerequisite = Invoke-Db "SELECT prerequisites FROM st_courses WHERE id=$validCourseId;"
	$editedVersion = Invoke-Db "SELECT content_version FROM st_courses WHERE id=$validCourseId;"
	if ($editResult.StatusCode -ne 200 -or $editResult.Content -notmatch 'Prasyarat/persiapan:</strong> Bekal yang disarankan: operasi pengurangan sederhana\.' -or $editedPrerequisite -ne 'Bekal yang disarankan: operasi pengurangan sederhana.' -or $editedVersion -ne '2') { throw 'Editing course prerequisites should persist, display, and advance the content version.' }
	Write-Output 'PASS optional course prerequisites can be edited, persist in the draft, and advance the content version.'

	$validLessonPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + "kelola/kursus/$validCourseId/pelajaran/tambah") -WebSession $web
	$validLessonBody = @{
		title = 'QA Valid Lesson';
		body_text = 'This synthetic lesson body has enough characters for the QA validation rule.';
		estimated_minutes = '5';
		rights_status = 'owned';
		source_note = 'Temporary QA-owned synthetic content.';
		study_csrf_token = Get-CsrfToken $validLessonPage
	}
	$validLessonResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + "kelola/kursus/$validCourseId/pelajaran/tambah") -Method Post -WebSession $web -Body $validLessonBody
	if ($validLessonResult.StatusCode -ne 200 -or (Invoke-Db "SELECT COUNT(*) FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id=$validCourseId;") -ne '1') {
		throw 'A valid scalar lesson form submission failed.'
	}
	if ($validLessonResult.Content -notmatch 'Draf' -or $validLessonResult.Content -notmatch 'Dibuat sendiri/tim' -or $validLessonResult.Content -match 'Hak materi:</strong> owned|>draft<') { throw 'Lesson status and rights should use Indonesian labels on the course detail page.' }
	Write-Output 'PASS valid scalar lesson creation remains functional and its status/rights are labeled in Indonesian.'
	$validLessonId = [int] (Invoke-Db "SELECT l.id FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id=$validCourseId;")
	$lessonEditRoute = "kelola/kursus/$validCourseId/pelajaran/$validLessonId/edit"
	$lessonEditPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + $lessonEditRoute) -WebSession $web
	$lessonRevisionMatch = [regex]::Match($lessonEditPage.Content, 'name="revision" value="([0-9]+)"')
	if ( ! $lessonRevisionMatch.Success) { throw 'Edit lesson form must carry the displayed revision.' }
	$lessonEditBody = $validLessonBody.Clone()
	$lessonEditBody.revision = $lessonRevisionMatch.Groups[1].Value
	$lessonEditBody.title = 'QA Updated Lesson'
	$lessonEditBody.study_csrf_token = Get-CsrfToken $lessonEditPage
	$lessonEditResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + $lessonEditRoute) -Method Post -WebSession $web -Body $lessonEditBody
	if ($lessonEditResult.StatusCode -ne 200 -or (Invoke-Db "SELECT CONCAT(title,'|',content_version) FROM st_lessons WHERE id=$validLessonId;") -ne 'QA Updated Lesson|2' -or (Invoke-Db "SELECT content_version FROM st_courses WHERE id=$validCourseId;") -ne '4') { throw 'A current lesson revision must save and advance both lesson/course versions.' }
	$staleLessonPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + $lessonEditRoute) -WebSession $web
	$lessonEditBody.study_csrf_token = Get-CsrfToken $staleLessonPage
	$staleLessonStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + $lessonEditRoute) -Method Post -WebSession $web -Body $lessonEditBody }
	Assert-Equal $staleLessonStatus 409 'An old lesson form must not overwrite a newer revision'
	if ((Invoke-Db "SELECT content_version FROM st_lessons WHERE id=$validLessonId;") -ne '2' -or (Invoke-Db "SELECT content_version FROM st_courses WHERE id=$validCourseId;") -ne '4') { throw 'Stale lesson edit unexpectedly changed a version.' }
	Write-Output 'PASS current lesson edit saves with its revision, advances lesson/course versions, and rejects a stale replay.'

	$invalidLogoutStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + 'keluar') -Method Post -WebSession $web -Body @{ study_csrf_token = 'invalid-csrf-token' } }
	if ($invalidLogoutStatus -ne 403) { throw "Invalid CSRF token should be rejected (expected 403, got $invalidLogoutStatus)." }
	Write-Output 'PASS invalid CSRF token is rejected on logout.'
	$dashboardPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'beranda') -WebSession $web
	$logoutResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'keluar') -Method Post -WebSession $web -Body @{ study_csrf_token = Get-CsrfToken $dashboardPage }
	if ($logoutResult.Content -match 'Hai, QA Admin Input') { throw 'User remained authenticated after logout.' }
	$logoutAudit = Invoke-Db "SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$adminId AND entity_type='user' AND entity_id=$adminId AND event_type='auth.logout';"
	if ($logoutAudit -ne '1') { throw 'Successful logout was not recorded in the audit trail.' }
	$afterLogout = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'beranda') -WebSession $web
	if ($afterLogout.Content -notmatch 'Masuk ke akunmu') { throw 'Protected dashboard did not require login after logout.' }
	Write-Output 'PASS valid logout is audited, clears authentication, and protects the dashboard.'
}
finally {
	$courseIds = Invoke-Db "SELECT GROUP_CONCAT(id) FROM st_courses WHERE slug IN ('$fixtureCourseSlug','$validCourseSlug');"
	$lessonIds = Invoke-Db "SELECT GROUP_CONCAT(l.id) FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id WHERE c.slug IN ('$fixtureCourseSlug','$validCourseSlug');"
	foreach ($idList in @($courseIds, $lessonIds)) {
		$idListText = [string] $idList
		if ($idListText -and $idListText -ne 'NULL' -and $idListText -notmatch '\A[0-9]+(,[0-9]+)*\z') { throw 'Unexpected QA entity ID list; refusing cleanup.' }
	}
	$cleanupSql = @"
DELETE FROM st_audit_events
WHERE (entity_type='course' AND entity_id IN (SELECT id FROM st_courses WHERE slug IN ('$fixtureCourseSlug','$validCourseSlug')))
OR (entity_type='lesson' AND entity_id IN (
	SELECT l.id FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id
	WHERE c.slug IN ('$fixtureCourseSlug','$validCourseSlug')
));
DELETE l FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id WHERE c.slug IN ('$fixtureCourseSlug','$validCourseSlug');
DELETE FROM st_course_sections WHERE course_id IN (SELECT id FROM st_courses WHERE slug IN ('$fixtureCourseSlug','$validCourseSlug'));
DELETE FROM st_courses WHERE slug IN ('$fixtureCourseSlug','$validCourseSlug');
DELETE FROM st_audit_events WHERE (entity_type='user' AND entity_id IN (SELECT id FROM st_users WHERE username='$username')) OR actor_user_id IN (SELECT id FROM st_users WHERE username='$username');
DELETE FROM st_user_roles WHERE user_id IN (SELECT id FROM st_users WHERE username='$username');
DELETE FROM st_users WHERE username='$username';
"@
	Invoke-Db $cleanupSql | Out-Null
	$leftovers = Invoke-Db "SELECT (SELECT COUNT(*) FROM st_users WHERE username='$username') + (SELECT COUNT(*) FROM st_courses WHERE slug IN ('$fixtureCourseSlug','$validCourseSlug')) + (SELECT COUNT(*) FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id IN (SELECT id FROM st_courses WHERE slug IN ('$fixtureCourseSlug','$validCourseSlug')));"
	if ($leftovers -ne '0') { throw "QA cleanup left $leftovers fixture row(s)." }
	$auditLeftovers = 0
	if ($courseIds) { $auditLeftovers += [int] (Invoke-Db "SELECT COUNT(*) FROM st_audit_events WHERE entity_type='course' AND entity_id IN ($courseIds);") }
	if ($lessonIds) { $auditLeftovers += [int] (Invoke-Db "SELECT COUNT(*) FROM st_audit_events WHERE entity_type='lesson' AND entity_id IN ($lessonIds);") }
	if ($auditLeftovers -ne 0) { throw "QA cleanup left $auditLeftovers audit fixture row(s)." }
	Write-Output 'PASS temporary admin, courses, lessons, role, and their audit events are cleaned.'
}
