param(
	[Parameter(Mandatory = $true)][string] $Php81Path,
	[Parameter(Mandatory = $true)][string] $MysqlPath,
	[Parameter(Mandatory = $true)][string] $BaseUrl,
	[Parameter(Mandatory = $false)][string] $ChromePath = '',
	[Parameter(Mandatory = $false)][switch] $CaptureVisualBaseline,
	[Parameter(Mandatory = $false)][switch] $CaptureFamilyDashboardBaseline,
	[Parameter(Mandatory = $false)][switch] $ExerciseReadingSave
)

$ErrorActionPreference = 'Stop'
$parsedProgressUrl = [Uri]$BaseUrl
if ($parsedProgressUrl.Scheme -ne 'http' -or -not $parsedProgressUrl.IsLoopback -or $parsedProgressUrl.UserInfo -or $parsedProgressUrl.Query -or $parsedProgressUrl.Fragment) { throw 'Progress QA requires an HTTP loopback URL without credentials/query/fragment.' }
if ($ExerciseReadingSave -and (-not $ChromePath -or -not (Test-Path -LiteralPath $ChromePath -PathType Leaf))) { throw 'ExerciseReadingSave requires ChromePath.' }
$suffix = [Guid]::NewGuid().ToString('N').Substring(0, 16)
$username = "qa_progress_$suffix"
$otherUsername = "qa_other_$suffix"
$parentOneUsername = "qa_parent_one_$suffix"
$parentTwoUsername = "qa_parent_two_$suffix"
$childOneUsername = "qa_child_one_$suffix"
$childTwoUsername = "qa_child_two_$suffix"
$email = "$username@example.test"
$otherEmail = "$otherUsername@example.test"
$parentOneEmail = "$parentOneUsername@example.test"
$parentTwoEmail = "$parentTwoUsername@example.test"
$courseSlug = "qa-progress-course-$suffix"
$packageSlug = "qa-progress-package-$suffix"
$lessonSlug = "qa-progress-lesson-$suffix"
$nextLessonSlug = "qa-progress-next-lesson-$suffix"
$password = 'Temporary-Study-Progress-123!'
$visualBaselineDir = Join-Path (Split-Path -Parent $PSScriptRoot) 'docs/visual-baseline/2026-10-15-learning-pages-reviewed'
$familyVisualBaselineDir = Join-Path (Split-Path -Parent $PSScriptRoot) 'docs/visual-baseline/2026-10-15-family-dashboards'
if ($CaptureVisualBaseline) {
	if (-not $ChromePath -or -not (Test-Path -LiteralPath $ChromePath -PathType Leaf)) { throw 'A valid -ChromePath is required with -CaptureVisualBaseline.' }
	if (Test-Path -LiteralPath $visualBaselineDir -PathType Container) { throw "Visual baseline directory already exists; refusing to overwrite: $visualBaselineDir" }
}
if ($CaptureFamilyDashboardBaseline) {
	if (-not $ChromePath -or -not (Test-Path -LiteralPath $ChromePath -PathType Leaf)) { throw 'A valid -ChromePath is required with -CaptureFamilyDashboardBaseline.' }
	if (Test-Path -LiteralPath $familyVisualBaselineDir -PathType Container) { throw "Visual baseline directory already exists; refusing to overwrite: $familyVisualBaselineDir" }
}
$passwordHash = (& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim()
if ($LASTEXITCODE -ne 0 -or -not $passwordHash) { throw 'Could not create temporary QA password hash.' }

function Invoke-Db([string] $Sql) {
	$output = & $MysqlPath '-uroot' '-D' 'db_study' '-N' '-B' '-e' $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Database test setup/check failed: $output" }
	return ($output -join "`n").Trim()
}

function Invoke-ReadingTransactionProbe([int] $LearnerId, [int] $LessonId) {
	if ($LearnerId -lt 1 -or $LessonId -lt 1) { throw 'Invalid reading probe target.' }
	$environmentNames = @('CI_ENV','DB_HOST','DB_USERNAME','DB_PASSWORD','DB_DATABASE')
	$previous = @{}
	foreach ($name in $environmentNames) { $previous[$name] = [Environment]::GetEnvironmentVariable($name,'Process') }
	Push-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
	try {
		$env:CI_ENV = 'development'
		$env:DB_HOST = '127.0.0.1'
		$env:DB_USERNAME = 'study_app'
		$env:DB_PASSWORD = ''
		$env:DB_DATABASE = 'db_study'
		& $Php81Path index.php cli/check_reading_transactions index $LearnerId $LessonId
		if ($LASTEXITCODE -ne 0) { throw 'Reading transaction probe failed; owning-suite cleanup will follow.' }
	} finally {
		foreach ($name in $environmentNames) { [Environment]::SetEnvironmentVariable($name,$previous[$name],'Process') }
		Pop-Location
	}
}

function Get-CsrfToken($Response) {
	$match = [regex]::Match($Response.Content, 'name="study_csrf_token"\s+value="([^"]+)"')
	if (-not $match.Success) { throw 'CSRF hidden field was not found in rendered form.' }
	return [System.Net.WebUtility]::HtmlDecode($match.Groups[1].Value)
}

function Get-HttpStatus([scriptblock] $Request) {
	try {
		$response = & $Request
		return [int] $response.StatusCode
	} catch {
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

function Invoke-AuthenticatedVisualRoute([string] $Identity, [string] $RouteName, [string] $RoutePath) {
	$identityVariable = 'STUDY_QA_LOGIN_IDENTITY'
	$passwordVariable = 'STUDY_QA_LOGIN_PASSWORD'
	$oldIdentity = [Environment]::GetEnvironmentVariable($identityVariable, 'Process')
	$oldPassword = [Environment]::GetEnvironmentVariable($passwordVariable, 'Process')
	try {
		[Environment]::SetEnvironmentVariable($identityVariable, $Identity, 'Process')
		[Environment]::SetEnvironmentVariable($passwordVariable, $password, 'Process')
		$visualCheck = Join-Path $PSScriptRoot 'browser-viewport-check.mjs'
		& node $visualCheck --base-url $root --chrome $ChromePath --extra-path $RoutePath --extra-name $RouteName --only-extra-route --capture-screenshots --screenshot-dir $familyVisualBaselineDir
		if ($LASTEXITCODE -ne 0) { throw "Authenticated visual baseline browser check failed for $RouteName." }
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
VALUES ('$username','$email','$escapedHash','QA Progress','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @qa_user_id = LAST_INSERT_ID();
INSERT INTO st_users (username,email,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at)
VALUES ('$otherUsername','$otherEmail','$escapedHash','QA Other Learner','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @qa_other_user_id = LAST_INSERT_ID();
INSERT INTO st_courses (author_user_id,updated_by_user_id,title,slug,summary,learning_objectives,prerequisites,target_group,age_min,age_max,estimated_minutes,status,content_version,rights_status,source_note,is_free,published_at,created_at,updated_at)
VALUES (@qa_user_id,@qa_user_id,'QA Progress Course','$courseSlug','Course test','Resume reading','Mengenal penjumlahan dasar.','adult',18,65,25,'published',1,'owned','Synthetic QA fixture',0,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @qa_course_id = LAST_INSERT_ID();
INSERT INTO st_packages (title,slug,summary,credit_price,access_days,status,created_at,updated_at)
VALUES ('QA Progress Package','$packageSlug','Synthetic QA fixture',0,7,'published',UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @qa_package_id = LAST_INSERT_ID();
INSERT INTO st_package_courses (package_id,course_id,sort_order) VALUES (@qa_package_id,@qa_course_id,1);
INSERT INTO st_entitlements (user_id,package_id,payment_order_id,status,starts_at,ends_at,created_at)
VALUES (@qa_user_id,@qa_package_id,NULL,'active',DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY),UTC_TIMESTAMP());
SET @qa_entitlement_id = LAST_INSERT_ID();
INSERT INTO st_course_enrollments (learner_user_id,course_id,source_type,package_id,entitlement_id,status,enrolled_at,updated_at)
VALUES (@qa_user_id,@qa_course_id,'package',@qa_package_id,@qa_entitlement_id,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
INSERT INTO st_course_sections (course_id,title,sort_order,created_at,updated_at)
VALUES (@qa_course_id,'QA Section',1,UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @qa_section_id = LAST_INSERT_ID();
INSERT INTO st_lessons (section_id,title,slug,body_text,estimated_minutes,sort_order,status,content_version,is_preview,source_note,rights_status,created_at,updated_at)
VALUES (@qa_section_id,'QA Resume Lesson','$lessonSlug',REPEAT('Synthetic lesson text. ',200),5,1,'published',1,0,'Synthetic QA fixture','owned',UTC_TIMESTAMP(),UTC_TIMESTAMP());
INSERT INTO st_lessons (section_id,title,slug,body_text,estimated_minutes,sort_order,status,content_version,is_preview,source_note,rights_status,created_at,updated_at)
VALUES (@qa_section_id,'QA Next Lesson','$nextLessonSlug','Next lesson fixture',5,2,'published',1,0,'Synthetic QA fixture','owned',UTC_TIMESTAMP(),UTC_TIMESTAMP());
INSERT INTO st_users (username,email,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at)
VALUES ('$parentOneUsername','$parentOneEmail','$escapedHash','QA Parent Alpha','parent','unset','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @qa_parent_one_id = LAST_INSERT_ID();
INSERT INTO st_users (username,email,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at)
VALUES ('$parentTwoUsername','$parentTwoEmail','$escapedHash','QA Parent Beta','parent','unset','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @qa_parent_two_id = LAST_INSERT_ID();
INSERT INTO st_users (username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at)
VALUES ('$childOneUsername','$escapedHash','QA Child Alpha','child','primary','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @qa_child_one_id = LAST_INSERT_ID();
INSERT INTO st_users (username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at)
VALUES ('$childTwoUsername','$escapedHash','QA Child Beta','child','primary','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @qa_child_two_id = LAST_INSERT_ID();
INSERT INTO st_family_links (parent_user_id,child_user_id,status,created_at,updated_at)
VALUES (@qa_parent_one_id,@qa_child_one_id,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),(@qa_parent_two_id,@qa_child_two_id,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
INSERT INTO st_entitlements (user_id,package_id,payment_order_id,status,starts_at,ends_at,created_at)
VALUES (@qa_child_one_id,@qa_package_id,NULL,'active',DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY),UTC_TIMESTAMP());
SET @qa_child_entitlement_id = LAST_INSERT_ID();
INSERT INTO st_course_enrollments (learner_user_id,course_id,source_type,package_id,entitlement_id,status,enrolled_at,updated_at)
VALUES (@qa_child_one_id,@qa_course_id,'package',@qa_package_id,@qa_child_entitlement_id,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
INSERT INTO st_lesson_progress (learner_user_id,lesson_id,status,last_position,started_at,completed_at,updated_at)
VALUES (@qa_child_one_id,(SELECT id FROM st_lessons WHERE slug='$lessonSlug'),'completed',10000,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP());
COMMIT;
SELECT @qa_user_id, @qa_course_id, @qa_section_id, (SELECT id FROM st_lessons WHERE slug='$lessonSlug'), (SELECT id FROM st_lessons WHERE slug='$nextLessonSlug'), @qa_package_id, @qa_entitlement_id, @qa_other_user_id, @qa_parent_one_id, @qa_parent_two_id, @qa_child_one_id, @qa_child_two_id;
"@
	$ids = (Invoke-Db $setupSql) -split "\s+"
	if ($ids.Count -lt 4) { throw 'QA fixture IDs were not returned.' }
	$userId = [int] $ids[0]
	$courseId = [int] $ids[1]
	$sectionId = [int] $ids[2]
	$lessonId = [int] $ids[3]
	$nextLessonId = [int] $ids[4]
	$packageId = [int] $ids[5]
	$entitlementId = [int] $ids[6]
	$otherUserId = [int] $ids[7]
	$parentOneId = [int] $ids[8]
	$parentTwoId = [int] $ids[9]
	$childOneId = [int] $ids[10]
	$childTwoId = [int] $ids[11]
	Invoke-ReadingTransactionProbe $userId $nextLessonId
	$probeProgress = Invoke-Db "SELECT CONCAT(p.status,':',p.last_position,':',IF(p.completed_at IS NULL,0,1)) FROM st_lesson_progress p JOIN st_users u ON u.id=p.learner_user_id JOIN st_lessons l ON l.id=p.lesson_id WHERE p.learner_user_id=$userId AND p.lesson_id=$nextLessonId AND u.username='$username' AND l.slug='$nextLessonSlug';"
	Assert-Equal $probeProgress 'completed:10000:1' 'Exact reading probe setup row was not found'
	Invoke-Db "DELETE p FROM st_lesson_progress p JOIN st_users u ON u.id=p.learner_user_id JOIN st_lessons l ON l.id=p.lesson_id WHERE p.learner_user_id=$userId AND p.lesson_id=$nextLessonId AND u.username='$username' AND l.slug='$nextLessonSlug';" | Out-Null
	Assert-Equal (Invoke-Db "SELECT COUNT(*) FROM st_lesson_progress WHERE learner_user_id=$userId AND lesson_id=$nextLessonId;") '0' 'Reading setup progress cleanup failed'
	Write-Output 'PASS exact probe-only progress row removed before normal HTTP regression; other fixture progress retained.'
	$root = $BaseUrl.TrimEnd('/') + '/'
	$web = New-Object Microsoft.PowerShell.Commands.WebRequestSession

	$loginPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -WebSession $web
	$loginBody = @{ identity = $username; password = $password; study_csrf_token = (Get-CsrfToken $loginPage) }
	$loginResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -Method Post -WebSession $web -Body $loginBody
	if ($loginResult.Content -notmatch 'Hai, QA Progress') { throw 'Temporary learner did not complete the login flow.' }

	$otherWeb = New-Object Microsoft.PowerShell.Commands.WebRequestSession
	$otherLoginPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -WebSession $otherWeb
	$otherLoginBody = @{ identity = $otherUsername; password = $password; study_csrf_token = (Get-CsrfToken $otherLoginPage) }
	Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -Method Post -WebSession $otherWeb -Body $otherLoginBody | Out-Null
	$guestWeb = New-Object Microsoft.PowerShell.Commands.WebRequestSession
	$guestCoursePage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kursus/' + $courseSlug) -WebSession $guestWeb
	$ageLabelFound = $guestCoursePage.Content -match 'Sasaran usia:</strong> Usia 18[^0-9]+65 tahun'
	if ($guestCoursePage.Content -notmatch 'Belum ada materi pratinjau' -or $guestCoursePage.Content -match 'Sebagian materi tersedia sebagai pratinjau' -or ! $ageLabelFound -or $guestCoursePage.Content -notmatch 'Persiapan belajar</h2><p class="mb-0">Mengenal penjumlahan dasar\.') {
		$ageIndex = $guestCoursePage.Content.IndexOf('Sasaran usia')
		$ageSnippet = if ($ageIndex -ge 0) { $guestCoursePage.Content.Substring($ageIndex, [Math]::Min(120, $guestCoursePage.Content.Length - $ageIndex)) } else { 'not rendered' }
		throw "A paid course with no preview lessons made an inaccurate preview or age-range claim (age label found=$ageLabelFound; snippet=$ageSnippet)."
	}
	$catalogPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kursus') -WebSession $guestWeb
	if ($catalogPage.Content -notmatch 'Dewasa' -or $catalogPage.Content -notmatch 'Usia 18[^0-9]+65 tahun' -or $catalogPage.Content -notmatch '25 menit') { throw 'Published course cards should display the localized audience, age range, and duration.' }
	Invoke-Db "UPDATE st_lessons SET is_preview=1 WHERE id=$lessonId;" | Out-Null
	$previewCoursePage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kursus/' + $courseSlug) -WebSession $guestWeb
	if ($previewCoursePage.Content -notmatch 'Sebagian materi tersedia sebagai pratinjau' -or $previewCoursePage.Content -notmatch 'Coba pratinjau' -or $previewCoursePage.Content -notmatch 'Terkunci') { throw 'Course page did not distinguish its preview and locked lessons.' }
	$previewLessonPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $lessonSlug) -WebSession $guestWeb
	if ($previewLessonPage.Content -notmatch 'QA Resume Lesson' -or $previewLessonPage.Content -notmatch 'Synthetic lesson text') { throw 'A published preview lesson was not readable by a guest.' }
	$lockedLessonPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $nextLessonSlug) -WebSession $guestWeb
	if ($lockedLessonPage.Content -notmatch 'Terkunci' -or $lockedLessonPage.Content -match 'Next lesson fixture') { throw 'A non-preview paid lesson was exposed to a guest.' }
	Write-Output 'PASS course pages accurately distinguish no-preview, guest-readable preview, and locked paid lessons.'
	if ($CaptureVisualBaseline) {
		$visualContent = @"
UPDATE st_courses SET title='Matematika Seru: Pecahan dari Pizza', summary='Kenali pecahan lewat cerita berbagi pizza dan contoh yang mudah dibayangkan.', learning_objectives='Mengenali setengah dan seperempat melalui ilustrasi pizza.', target_group='primary', age_min=8, age_max=10, estimated_minutes=20 WHERE id=$courseId;
UPDATE st_course_sections SET title='Misi 1: Berbagi Pizza' WHERE course_id=$courseId;
UPDATE st_lessons SET title='Potongan yang Sama', body_text='Bayangkan satu pizza bundar dibagi menjadi dua bagian yang ukurannya sama. Satu dari dua bagian disebut setengah.\n\nSekarang bayangkan pizza dibagi menjadi empat potongan yang sama besar. Satu potong disebut seperempat. Dua potong dari empat bagian sama dengan setengah pizza.\n\nPecahan membantu kita menjelaskan bagian dari satu benda utuh. Pastikan semua bagiannya sama besar sebelum membandingkan pecahan.' WHERE id=$lessonId;
"@
		Invoke-Db $visualContent | Out-Null
		$visualCheck = Join-Path $PSScriptRoot 'browser-viewport-check.mjs'
		foreach ($visualRoute in @(@{ name = 'course-detail'; path = "kursus/$courseSlug" }, @{ name = 'lesson'; path = "materi/$lessonSlug" })) {
			& node $visualCheck --base-url $root --chrome $ChromePath --extra-path $visualRoute.path --extra-name $visualRoute.name --only-extra-route --capture-screenshots --screenshot-dir $visualBaselineDir
			if ($LASTEXITCODE -ne 0) { throw "Visual baseline browser check failed for $($visualRoute.name)." }
		}
		$restoreQaContent = @"
UPDATE st_courses SET title='QA Progress Course', summary='Course test', learning_objectives='Resume reading', target_group='adult', age_min=18, age_max=65, estimated_minutes=25 WHERE id=$courseId;
UPDATE st_course_sections SET title='QA Section' WHERE course_id=$courseId;
UPDATE st_lessons SET title='QA Resume Lesson', body_text=REPEAT('Synthetic lesson text. ',200) WHERE id=$lessonId;
"@
		Invoke-Db $restoreQaContent | Out-Null
		Write-Output "PASS synthetic course/lesson screenshots saved to $visualBaselineDir after 360/768/1280 px, keyboard, and accessibility checks."
	}
	$emptyDashboard = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'beranda') -WebSession $otherWeb
	if ($emptyDashboard.Content -match 'setelah fitur pencatatan progres selesai dibuat' -or $emptyDashboard.Content -notmatch 'Progresmu tercatat saat' -or $emptyDashboard.Content -match 'Progres per kursus') { throw 'Empty learner dashboard contains an outdated or inaccurate progress message.' }
	Write-Output 'PASS empty learner dashboard describes the actual progress behavior without promising a missing feature.'

	$childWeb = New-Object Microsoft.PowerShell.Commands.WebRequestSession
	$childLoginPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -WebSession $childWeb
	$childLoginBody = @{ identity = $childOneUsername; password = $password; study_csrf_token = (Get-CsrfToken $childLoginPage) }
	Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -Method Post -WebSession $childWeb -Body $childLoginBody | Out-Null
	$childDashboard = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'beranda') -WebSession $childWeb
	if ($childDashboard.Content -notmatch 'theme-child-primary' -or $childDashboard.Content -notmatch 'Belajarmu terus berkembang' -or $childDashboard.Content -notmatch 'Kamu sudah menyelesaikan 1 pelajaran' -or $childDashboard.Content -notmatch 'Progres per kursus' -or $childDashboard.Content -notmatch 'QA Progress Course') { throw 'Child dashboard does not reflect their learning theme, course progress, and actual progress.' }
	Write-Output 'PASS child dashboard displays the child theme and course progress belonging to that child.'
	if ($CaptureFamilyDashboardBaseline) {
		$familyVisualData = @"
UPDATE st_users SET display_name='Naya' WHERE id=$childOneId;
UPDATE st_users SET display_name='Bunda Rina' WHERE id=$parentOneId;
UPDATE st_courses SET title='Matematika Seru: Pecahan dari Pizza' WHERE id=$courseId;
UPDATE st_course_sections SET title='Misi 1: Berbagi Pizza' WHERE course_id=$courseId;
UPDATE st_lessons SET title='Potongan yang Sama' WHERE id=$lessonId;
"@
		Invoke-Db $familyVisualData | Out-Null
		Invoke-AuthenticatedVisualRoute $childOneUsername 'child-dashboard' 'beranda'
		Invoke-AuthenticatedVisualRoute $parentOneUsername 'parent-dashboard' 'beranda'
		$restoreFamilyVisualData = @"
UPDATE st_users SET display_name='QA Child Alpha' WHERE id=$childOneId;
UPDATE st_users SET display_name='QA Parent Alpha' WHERE id=$parentOneId;
UPDATE st_courses SET title='QA Progress Course' WHERE id=$courseId;
UPDATE st_course_sections SET title='QA Section' WHERE course_id=$courseId;
UPDATE st_lessons SET title='QA Resume Lesson' WHERE id=$lessonId;
"@
		Invoke-Db $restoreFamilyVisualData | Out-Null
		Write-Output "PASS authenticated child/parent dashboard screenshots saved to $familyVisualBaselineDir after 360/768/1280 px and keyboard/accessibility checks."
	}

	$parentOneWeb = New-Object Microsoft.PowerShell.Commands.WebRequestSession
	$parentOneLoginPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -WebSession $parentOneWeb
	$parentOneLoginBody = @{ identity = $parentOneUsername; password = $password; study_csrf_token = (Get-CsrfToken $parentOneLoginPage) }
	Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -Method Post -WebSession $parentOneWeb -Body $parentOneLoginBody | Out-Null
	$parentOneDashboard = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'beranda') -WebSession $parentOneWeb
	if ($parentOneDashboard.Content -notmatch 'QA Child Alpha' -or $parentOneDashboard.Content -notmatch '1 pelajaran selesai' -or $parentOneDashboard.Content -notmatch 'QA Progress Course' -or $parentOneDashboard.Content -match 'QA Child Beta') { throw 'Parent alpha dashboard does not show only their linked child and that child\x27s course progress.' }
	$storedDisplayNameXss = '<img src=x onerror=alert(1)>'
	$escapedDisplayNameXss = '&lt;img src=x onerror=alert(1)&gt;'
	Invoke-Db "UPDATE st_users SET display_name='$storedDisplayNameXss' WHERE id=$childOneId;" | Out-Null
	$parentXssDashboard = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'beranda') -WebSession $parentOneWeb
	if ($parentXssDashboard.Content.Contains($storedDisplayNameXss) -or -not $parentXssDashboard.Content.Contains($escapedDisplayNameXss)) { throw 'Stored child display-name markup was not escaped in the parent dashboard.' }
	$childXssDashboard = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'beranda') -WebSession $childWeb
	if ($childXssDashboard.Content.Contains($storedDisplayNameXss)) { throw 'Stored child display-name markup was rendered as active HTML in the child dashboard.' }
	$childLogoutCsrf = Get-CsrfToken $childXssDashboard
	Invoke-WebRequest -UseBasicParsing -Uri ($root + 'keluar') -Method Post -WebSession $childWeb -Body @{ study_csrf_token = $childLogoutCsrf } | Out-Null
	$childReloginPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -WebSession $childWeb
	$childReloginBody = @{ identity = $childOneUsername; password = $password; study_csrf_token = (Get-CsrfToken $childReloginPage) }
	Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -Method Post -WebSession $childWeb -Body $childReloginBody | Out-Null
	$childXssDashboard = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'beranda') -WebSession $childWeb
	if ($childXssDashboard.Content.Contains($storedDisplayNameXss) -or -not $childXssDashboard.Content.Contains($escapedDisplayNameXss)) { throw 'Stored child display-name markup was not escaped after loading the account from storage.' }
	Invoke-Db "UPDATE st_users SET display_name='QA Child Alpha' WHERE id=$childOneId;" | Out-Null
	Write-Output 'PASS stored child display-name markup is escaped in both parent and child dashboards after reloading persisted data.'
	$addChildPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'keluarga/anak/tambah') -WebSession $parentOneWeb
	$invalidChild = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'keluarga/anak/tambah') -Method Post -WebSession $parentOneWeb -Body @{
		display_name = ''
		learning_stage = 'invalid'
		username = '<script>alert(1)</script>'
		password = 'short'
		password_confirm = 'different'
		study_csrf_token = Get-CsrfToken $addChildPage
	}
	foreach ($field in @('display_name','learning_stage','username','password','password_confirm')) {
		Assert-AccessibleFieldError $invalidChild.Content $field
	}
	if ($invalidChild.Content.Contains('<script>alert(1)</script>')) {
		throw 'Invalid child username markup was reflected unsafely in the form response.'
	}
	Write-Output 'PASS child-account validation errors are accessible and invalid username markup is escaped.'

	$parentTwoWeb = New-Object Microsoft.PowerShell.Commands.WebRequestSession
	$parentTwoLoginPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -WebSession $parentTwoWeb
	$parentTwoLoginBody = @{ identity = $parentTwoUsername; password = $password; study_csrf_token = (Get-CsrfToken $parentTwoLoginPage) }
	Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -Method Post -WebSession $parentTwoWeb -Body $parentTwoLoginBody | Out-Null
	$parentTwoDashboard = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'beranda') -WebSession $parentTwoWeb
	if ($parentTwoDashboard.Content -notmatch 'QA Child Beta' -or $parentTwoDashboard.Content -match 'QA Child Alpha' -or $parentTwoDashboard.Content -match '1 pelajaran selesai' -or $parentTwoDashboard.Content -match 'QA Progress Course') { throw 'Parent beta dashboard exposed another family\x27s child/course progress.' }
	Write-Output 'PASS parent dashboards show only children and per-course progress belonging to their active family links.'

	$familyPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'keluarga/anak') -WebSession $parentOneWeb
	$ownStageBody = @{ learning_stage = 'secondary'; study_csrf_token = Get-CsrfToken $familyPage }
	$ownStageResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + "keluarga/anak/$childOneId/kelompok") -Method Post -WebSession $parentOneWeb -Body $ownStageBody
	$ownStageStatus = [int] $ownStageResult.StatusCode
	if ($ownStageStatus -ne 200) { throw "Linked parent could not update their child's learning stage (HTTP $ownStageStatus)." }
	if ($ownStageResult.Content -notmatch 'Kelompok belajar anak berhasil diperbarui') { throw 'The linked parent did not receive a success confirmation for the stage update.' }
	$stageState = Invoke-Db "SELECT CONCAT(learning_stage,'|',(SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$parentOneId AND entity_id=$childOneId AND event_type='family.child_learning_stage_updated')) FROM st_users WHERE id=$childOneId;"
	Assert-Equal $stageState 'secondary|1' 'Linked parent stage update should persist once and be audited'
	Write-Output 'PASS linked parent can change the child learning stage and the change is audited.'
	$stagePath = $root + "keluarga/anak/$childOneId/kelompok"
	$methodStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri $stagePath -WebSession $parentOneWeb }
	Assert-Equal $methodStatus 404 'Learning-stage update must not be accepted through GET'
	$invalidStagePage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'keluarga/anak') -WebSession $parentOneWeb
	$invalidStageBody = @{ learning_stage = 'invalid'; study_csrf_token = Get-CsrfToken $invalidStagePage }
	$invalidStageResult = Invoke-WebRequest -UseBasicParsing -Uri $stagePath -Method Post -WebSession $parentOneWeb -Body $invalidStageBody
	if ($invalidStageResult.Content -notmatch 'Kelompok belajar yang dipilih tidak valid') { throw 'Invalid child learning stage was not rejected with a clear validation message.' }
	$stageState = Invoke-Db "SELECT CONCAT(learning_stage,'|',(SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$parentOneId AND entity_id=$childOneId AND event_type='family.child_learning_stage_updated')) FROM st_users WHERE id=$childOneId;"
	Assert-Equal $stageState 'secondary|1' 'Invalid stage value must not modify or audit the child'
	$invalidCsrfBody = @{ learning_stage = 'primary'; study_csrf_token = 'invalid-csrf-token' }
	$invalidCsrfStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri $stagePath -Method Post -WebSession $parentOneWeb -Body $invalidCsrfBody }
	Assert-Equal $invalidCsrfStatus 403 'Learning-stage update must reject an invalid CSRF token'
	$stageState = Invoke-Db "SELECT CONCAT(learning_stage,'|',(SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$parentOneId AND entity_id=$childOneId AND event_type='family.child_learning_stage_updated')) FROM st_users WHERE id=$childOneId;"
	Assert-Equal $stageState 'secondary|1' 'Invalid CSRF token must not modify or audit the child'
	Write-Output 'PASS stage update rejects GET, invalid values, and invalid CSRF without data or audit changes.'

	$otherFamilyPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'keluarga/anak') -WebSession $parentTwoWeb
	$foreignStageBody = @{ learning_stage = 'early_childhood'; study_csrf_token = Get-CsrfToken $otherFamilyPage }
	$foreignStageResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + "keluarga/anak/$childOneId/kelompok") -Method Post -WebSession $parentTwoWeb -Body $foreignStageBody
	$foreignStageStatus = [int] $foreignStageResult.StatusCode
	if ($foreignStageStatus -ne 200) { throw "Foreign parent stage-change request did not complete its redirect flow (HTTP $foreignStageStatus)." }
	$stageState = Invoke-Db "SELECT CONCAT(learning_stage,'|',(SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$parentOneId AND entity_id=$childOneId AND event_type='family.child_learning_stage_updated')) FROM st_users WHERE id=$childOneId;"
	Assert-Equal $stageState 'secondary|1' 'A different parent must not change or audit a child from another family'
	if ($foreignStageResult.Content -notmatch 'tidak ditemukan pada daftar keluarga Anda') { throw 'Foreign parent was not shown a safe denial message for the attempted child update.' }
	$directFamilyPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'keluarga/anak') -WebSession $parentTwoWeb
	$directForeignStageBody = @{ learning_stage = 'early_childhood'; study_csrf_token = Get-CsrfToken $directFamilyPage }
	$directForeignStageResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + "family/update_child_stage/$childOneId") -Method Post -WebSession $parentTwoWeb -Body $directForeignStageBody
	if ($directForeignStageResult.Content -notmatch 'tidak ditemukan pada daftar keluarga Anda') { throw 'Direct Family controller route did not safely deny a child from another parent.' }
	$stageState = Invoke-Db "SELECT CONCAT(learning_stage,'|',(SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$parentOneId AND entity_id=$childOneId AND event_type='family.child_learning_stage_updated')) FROM st_users WHERE id=$childOneId;"
	Assert-Equal $stageState 'secondary|1' 'Direct controller IDOR attempt must not change or audit a child'
	$updatedChildDashboard = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'beranda') -WebSession $childWeb
	if ($updatedChildDashboard.Content -notmatch 'theme-child-secondary') { throw 'Child theme did not reflect the valid parent-selected learning stage.' }
	Write-Output 'PASS another family cannot change a child stage; denied request leaves data/audit unchanged and child theme follows the valid update.'
	$crossUserPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $nextLessonSlug) -WebSession $otherWeb
	if ($crossUserPage.Content -notmatch 'Terkunci' -or $crossUserPage.Content -match 'Next lesson fixture') { throw "A different learner gained access to another learner's paid lesson." }
	Write-Output "PASS a different authenticated learner cannot open another learner's paid course."

	$lessonPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $lessonSlug) -WebSession $web
	if ($lessonPage.Content -notmatch [regex]::Escape('materi/' + $nextLessonSlug) -or $lessonPage.Content -notmatch 'Pelajaran berikutnya') { throw 'Accessible next lesson link was not rendered.' }
	if ($lessonPage.Content -notmatch 'Posisi baca tersimpan otomatis') { throw 'Lesson page does not explain automatic progress saving.' }
	$lastLessonPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $nextLessonSlug) -WebSession $web
	if ($lastLessonPage.Content -match 'Pelajaran berikutnya') { throw 'A next lesson link was rendered on the final lesson.' }
	Write-Output 'PASS next-lesson navigation points to the next accessible lesson and is absent at course end.'

	Invoke-Db "UPDATE st_entitlements SET status='expired' WHERE id=$entitlementId;" | Out-Null
	$expiredPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $nextLessonSlug) -WebSession $web
	if ($expiredPage.Content -notmatch 'Terkunci' -or $expiredPage.Content -match 'Next lesson fixture') { throw 'Expired package entitlement did not lock paid course lessons.' }
	Invoke-Db "UPDATE st_entitlements SET status='active' WHERE id=$entitlementId;" | Out-Null
	Write-Output 'PASS active package entitlement grants access and an expired entitlement removes paid lesson access.'
	Invoke-Db "UPDATE st_course_enrollments SET status='revoked' WHERE learner_user_id=$userId AND course_id=$courseId;" | Out-Null
	$revokedPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $nextLessonSlug) -WebSession $web
	if ($revokedPage.Content -notmatch 'Terkunci') { throw 'Revoked enrollment did not remove paid lesson access.' }
	$revokedBody = 'position=5100&study_csrf_token=' + [System.Net.WebUtility]::UrlEncode((Get-CsrfToken $lessonPage))
	$revokedSaveStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $nextLessonSlug + '/posisi') -Method Post -WebSession $web -Headers @{ 'X-Requested-With' = 'XMLHttpRequest' } -ContentType 'application/x-www-form-urlencoded' -Body $revokedBody }
	Assert-Equal $revokedSaveStatus 403 'Autosave after enrollment revocation should be denied'
	Invoke-Db "UPDATE st_course_enrollments SET status='active' WHERE learner_user_id=$userId AND course_id=$courseId;" | Out-Null
	Write-Output 'PASS revoked enrollment removes course access and denies progress autosave.'
	if ($ExerciseReadingSave) {
		$previousIdentity = $env:STUDY_QA_LOGIN_IDENTITY
		$previousPassword = $env:STUDY_QA_LOGIN_PASSWORD
		try {
			$env:STUDY_QA_LOGIN_IDENTITY = $username
			$env:STUDY_QA_LOGIN_PASSWORD = $password
			& node (Join-Path $PSScriptRoot 'browser-viewport-check.mjs') --base-url $root --chrome $ChromePath --extra-path "materi/$lessonSlug" --extra-name reading-save --only-extra-route --exercise-reading-save
			if ($LASTEXITCODE -ne 0) { throw 'Reading save browser exercise failed.' }
		} finally {
			$env:STUDY_QA_LOGIN_IDENTITY = $previousIdentity
			$env:STUDY_QA_LOGIN_PASSWORD = $previousPassword
		}
	}

	$lessonPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $lessonSlug) -WebSession $web
	$positionBody = @{ position = '3500'; study_csrf_token = (Get-CsrfToken $lessonPage) }
	$ajaxBody = @{ position = '4200'; study_csrf_token = (Get-CsrfToken $lessonPage) }
	$ajaxResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $lessonSlug + '/posisi') -Method Post -WebSession $web -Headers @{ 'X-Requested-With' = 'XMLHttpRequest' } -ContentType 'application/x-www-form-urlencoded' -Body ([System.Net.WebUtility]::UrlEncode('position') + '=4200&study_csrf_token=' + [System.Net.WebUtility]::UrlEncode($ajaxBody.study_csrf_token))
	$ajaxData = $ajaxResult.Content | ConvertFrom-Json
	if (-not $ajaxData.ok -or -not $ajaxData.csrf_hash -or $ajaxResult.Headers['Content-Type'] -notmatch 'application/json') { throw 'Autosave response or rotated CSRF token is missing.' }
	$row = Invoke-Db "SELECT CONCAT(status,':',last_position) FROM st_lesson_progress WHERE learner_user_id=$userId AND lesson_id=$lessonId;"
	Assert-Equal $row 'in_progress:4200' 'Autosave did not persist the reading position'
	$secondAjaxBody = 'position=4600&study_csrf_token=' + [System.Net.WebUtility]::UrlEncode($ajaxData.csrf_hash)
	$secondAjaxResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $lessonSlug + '/posisi') -Method Post -WebSession $web -Headers @{ 'X-Requested-With' = 'XMLHttpRequest' } -ContentType 'application/x-www-form-urlencoded' -Body $secondAjaxBody
	$secondAjaxData = $secondAjaxResult.Content | ConvertFrom-Json
	if (-not $secondAjaxData.ok -or -not $secondAjaxData.csrf_hash) { throw 'A second autosave using the rotated CSRF token failed.' }
	$row = Invoke-Db "SELECT CONCAT(status,':',last_position) FROM st_lesson_progress WHERE learner_user_id=$userId AND lesson_id=$lessonId;"
	Assert-Equal $row 'in_progress:4600' 'Second autosave did not persist the latest position'
	Write-Output 'PASS sequential autosaves persist progress and rotate CSRF tokens safely.'
	$lessonPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $lessonSlug) -WebSession $web
	$positionBody = @{ position = '3500'; study_csrf_token = (Get-CsrfToken $lessonPage) }
	$saveResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $lessonSlug + '/posisi') -Method Post -WebSession $web -Body $positionBody
	if ($saveResult.StatusCode -ne 200) { throw 'Saving a valid reading position did not return to the lesson.' }
	$row = Invoke-Db "SELECT CONCAT(status,':',last_position,':',IF(started_at IS NOT NULL,1,0)) FROM st_lesson_progress WHERE learner_user_id=$userId AND lesson_id=$lessonId;"
Assert-Equal $row 'in_progress:3500:1' 'Saved progress record is incorrect'
	$inProgressDashboard = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'beranda') -WebSession $web
	if ($inProgressDashboard.Content -notmatch '1 sedang berjalan') { throw 'Dashboard did not show the current in-progress lesson count.' }
	Write-Output 'PASS dashboard shows a lesson as in progress after position save.'

	$resumePage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $lessonSlug) -WebSession $web
if ($resumePage.Content -notmatch 'Posisi terakhir tersimpan: 35%' -or $resumePage.Content -notmatch 'lanjut=1') { throw 'Saved resume prompt is missing or incorrect.' }
if ($resumePage.Content -notmatch 'assets/js/lesson-progress\.js\?v=[0-9]+') { throw 'Lesson progress script URL is not versioned.' }
Write-Output 'PASS saved position is persisted and resume link is rendered.'

	$invalidTokenPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $lessonSlug) -WebSession $web
	$invalidBody = @{ position = '10001'; study_csrf_token = (Get-CsrfToken $invalidTokenPage) }
$invalidStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $lessonSlug + '/posisi') -Method Post -WebSession $web -Body $invalidBody }
Assert-Equal $invalidStatus 400 'Out-of-range position should be rejected'
Write-Output 'PASS out-of-range reading position is rejected.'

	$arrayTokenPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $lessonSlug) -WebSession $web
	$arrayBody = @{ 'position[]' = @('1','2'); study_csrf_token = (Get-CsrfToken $arrayTokenPage) }
	$arrayStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $lessonSlug + '/posisi') -Method Post -WebSession $web -Body $arrayBody }
	Assert-Equal $arrayStatus 400 'Array position input should be rejected'
	Write-Output 'PASS array-shaped position input is rejected.'

	$completePage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $lessonSlug) -WebSession $web
	$completeBody = @{ study_csrf_token = (Get-CsrfToken $completePage) }
	$completeResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $lessonSlug + '/selesai') -Method Post -WebSession $web -Body $completeBody
	if ($completeResult.StatusCode -ne 200) { throw 'Completing a lesson did not return to the lesson.' }
	$row = Invoke-Db "SELECT CONCAT(status,':',last_position,':',IF(completed_at IS NOT NULL,1,0)) FROM st_lesson_progress WHERE learner_user_id=$userId AND lesson_id=$lessonId;"
	Assert-Equal $row 'completed:10000:1' 'Completion state or final position is incorrect'
	$completedDashboard = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'beranda') -WebSession $web
	if ($completedDashboard.Content -notmatch 'Kamu sudah menyelesaikan 1 pelajaran' -or $completedDashboard.Content -notmatch 'Progres per kursus' -or $completedDashboard.Content -notmatch 'QA Progress Course') { throw 'Dashboard did not reflect completed lesson and per-course progress.' }
	Write-Output 'PASS dashboard reflects the completed lesson count.'

	$completedPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $lessonSlug) -WebSession $web
	$positionAfterCompletion = @{ position = '1000'; study_csrf_token = (Get-CsrfToken $completedPage) }
	Invoke-WebRequest -UseBasicParsing -Uri ($root + 'materi/' + $lessonSlug + '/posisi') -Method Post -WebSession $web -Body $positionAfterCompletion | Out-Null
	$row = Invoke-Db "SELECT CONCAT(status,':',last_position) FROM st_lesson_progress WHERE learner_user_id=$userId AND lesson_id=$lessonId;"
	Assert-Equal $row 'completed:10000' 'Saving a position after completion must not regress progress'
	Write-Output 'PASS lesson completion is terminal and does not regress when position is saved later.'
}
finally {
	$cleanupSql = @"
DELETE FROM st_audit_events WHERE (entity_type='user' AND entity_id IN (SELECT id FROM st_users WHERE username IN ('$username','$otherUsername','$parentOneUsername','$parentTwoUsername','$childOneUsername','$childTwoUsername'))) OR actor_user_id IN (SELECT id FROM st_users WHERE username IN ('$username','$otherUsername','$parentOneUsername','$parentTwoUsername','$childOneUsername','$childTwoUsername'));
DELETE FROM st_lesson_progress WHERE learner_user_id IN (SELECT id FROM st_users WHERE username IN ('$username','$otherUsername','$parentOneUsername','$parentTwoUsername','$childOneUsername','$childTwoUsername'));
DELETE p FROM st_lesson_progress p JOIN st_lessons l ON l.id=p.lesson_id WHERE l.slug='$lessonSlug';
DELETE l FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id WHERE c.slug='$courseSlug';
DELETE FROM st_course_sections WHERE course_id IN (SELECT id FROM st_courses WHERE slug='$courseSlug');
DELETE FROM st_course_enrollments WHERE course_id IN (SELECT id FROM st_courses WHERE slug='$courseSlug');
DELETE FROM st_family_links WHERE parent_user_id IN (SELECT id FROM st_users WHERE username IN ('$parentOneUsername','$parentTwoUsername')) OR child_user_id IN (SELECT id FROM st_users WHERE username IN ('$childOneUsername','$childTwoUsername'));
DELETE FROM st_entitlements WHERE package_id IN (SELECT id FROM st_packages WHERE slug='$packageSlug');
DELETE FROM st_package_courses WHERE package_id IN (SELECT id FROM st_packages WHERE slug='$packageSlug');
DELETE FROM st_packages WHERE slug='$packageSlug';
DELETE FROM st_courses WHERE slug='$courseSlug';
DELETE FROM st_users WHERE username='$username';
	DELETE FROM st_users WHERE username='$otherUsername';
DELETE FROM st_users WHERE username IN ('$parentOneUsername','$parentTwoUsername','$childOneUsername','$childTwoUsername');
"@
	Invoke-Db $cleanupSql | Out-Null
	$leftovers = Invoke-Db "SELECT (SELECT COUNT(*) FROM st_users WHERE username IN ('$username','$otherUsername','$parentOneUsername','$parentTwoUsername','$childOneUsername','$childTwoUsername')) + (SELECT COUNT(*) FROM st_family_links WHERE parent_user_id IN (SELECT id FROM st_users WHERE username IN ('$parentOneUsername','$parentTwoUsername'))) + (SELECT COUNT(*) FROM st_courses WHERE slug='$courseSlug') + (SELECT COUNT(*) FROM st_lessons WHERE slug IN ('$lessonSlug','$nextLessonSlug'));"
	if ($leftovers -ne '0') { throw "QA cleanup left $leftovers fixture row(s)." }
	Write-Output 'PASS temporary learner, course, lesson, and progress fixtures are cleaned.'
}
