param(
	[Parameter(Mandatory = $true)][string] $Php81Path,
	[Parameter(Mandatory = $true)][string] $MysqlPath,
	[Parameter(Mandatory = $true)][string] $BaseUrl
)

$ErrorActionPreference = 'Stop'
$suffix = [Guid]::NewGuid().ToString('N').Substring(0, 12)
$password = 'Temporary-Study-Role-QA-123!'
$passwordHash = (& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim()
if ($LASTEXITCODE -ne 0 -or -not $passwordHash) { throw 'Could not create temporary QA password hash.' }

$roleMatrix = @(
	@{ Code = 'system_admin'; Username = "qa_role_system_$suffix"; AdminIndex = 200; CourseList = 200; Counts = @('Kursus','Paket belajar','Pesanan pembayaran') },
	@{ Code = 'content_editor'; Username = "qa_role_editor_$suffix"; AdminIndex = 200; CourseList = 200 },
	@{ Code = 'finance_operator'; Username = "qa_role_finance_$suffix"; AdminIndex = 200; CourseList = 403 },
	@{ Code = 'support_agent'; Username = "qa_role_support_$suffix"; AdminIndex = 200; CourseList = 403 },
	@{ Code = ''; Username = "qa_role_member_$suffix"; AdminIndex = 403; CourseList = 403 }
)
$quotedUsernames = ($roleMatrix | ForEach-Object { "'$( $_.Username )'" }) -join ','
$archiveCourseSlug = "qa-role-archive-$suffix"
$archiveCourseId = 0
$draftCourseSlug = "qa-role-draft-$suffix"
$draftLessonSlug = "qa-role-draft-lesson-$suffix"
$draftCourseId = 0
$draftLessonId = 0

function Invoke-Db([string] $Sql) {
	$output = & $MysqlPath '-uroot' '-D' 'db_study' '-N' '-B' '-e' $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Role-access QA database operation failed: $output" }
	return ($output -join "`n").Trim()
}

function Get-CsrfToken($Response) {
	$match = [regex]::Match($Response.Content, 'name="study_csrf_token"\s+value="([^"]+)"')
	if (-not $match.Success) { throw 'CSRF hidden field was not found on the sign-in form.' }
	return [System.Net.WebUtility]::HtmlDecode($match.Groups[1].Value)
}

function Get-HttpStatus([scriptblock] $Request) {
	try { return [int] ((& $Request).StatusCode) }
	catch {
		if ($null -ne $_.Exception.Response) { return [int] $_.Exception.Response.StatusCode }
		throw
	}
}

$fixtureCreated = $false
try {
	$escapedHash = $passwordHash.Replace("'", "''")
	$fixtureCreated = $true
	foreach ($fixture in $roleMatrix) {
		$roleInsert = if ($fixture.Code) {
			"INSERT INTO st_user_roles (user_id,role_id,granted_by_user_id,created_at) SELECT @qa_role_user_id,id,@qa_role_user_id,UTC_TIMESTAMP() FROM st_roles WHERE role_code='$($fixture.Code)';"
		} else { '' }
		$sql = @"
START TRANSACTION;
INSERT INTO st_users (username,email,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at)
VALUES ('$($fixture.Username)','$($fixture.Username)@example.test','$escapedHash','QA Role $($fixture.Code)','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @qa_role_user_id = LAST_INSERT_ID();
$roleInsert
COMMIT;
SELECT @qa_role_user_id;
"@
		$userId = [int] (Invoke-Db $sql)
		if ($userId -lt 1) { throw "Could not create role fixture for $($fixture.Code)." }
	}
	$archiveCourseId = [int] (Invoke-Db "INSERT INTO st_courses (author_user_id,title,slug,summary,learning_objectives,target_group,status,content_version,rights_status,source_note,is_free,published_at,created_at,updated_at) VALUES ((SELECT id FROM st_users WHERE username='qa_role_system_$suffix'),'QA Role Archive $suffix','$archiveCourseSlug','Synthetic role fixture','Test archive authorization','adult','published',1,'owned','Synthetic QA fixture',1,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP()); SELECT LAST_INSERT_ID();")
	if ($archiveCourseId -lt 1) { throw 'Could not create the synthetic published course for archive-role testing.' }
	$draftCourseId = [int] (Invoke-Db "INSERT INTO st_courses (author_user_id,title,slug,summary,learning_objectives,target_group,status,content_version,rights_status,source_note,is_free,created_at,updated_at) VALUES ((SELECT id FROM st_users WHERE username='qa_role_editor_$suffix'),'QA Role Draft $suffix','$draftCourseSlug','Synthetic draft role fixture','Test draft ownership','adult','draft',1,'owned','Synthetic QA fixture',1,UTC_TIMESTAMP(),UTC_TIMESTAMP()); SELECT LAST_INSERT_ID();")
	if ($draftCourseId -lt 1) { throw 'Could not create the synthetic editor-owned draft course.' }
	$sectionId = [int] (Invoke-Db "INSERT INTO st_course_sections (course_id,title,sort_order,created_at,updated_at) VALUES ($draftCourseId,'QA Role Section',1,UTC_TIMESTAMP(),UTC_TIMESTAMP()); SELECT LAST_INSERT_ID();")
	$draftLessonId = [int] (Invoke-Db "INSERT INTO st_lessons (section_id,title,slug,body_text,estimated_minutes,sort_order,status,content_version,is_preview,source_note,rights_status,created_at,updated_at) VALUES ($sectionId,'QA Role Lesson','$draftLessonSlug','Synthetic lesson for role permission checks.',5,1,'draft',1,0,'Synthetic QA fixture','owned',UTC_TIMESTAMP(),UTC_TIMESTAMP()); SELECT LAST_INSERT_ID();")
	if ($sectionId -lt 1 -or $draftLessonId -lt 1) { throw 'Could not create the synthetic draft lesson hierarchy.' }
	$root = $BaseUrl.TrimEnd('/') + '/'

	foreach ($fixture in $roleMatrix) {
		$web = New-Object Microsoft.PowerShell.Commands.WebRequestSession
		$loginPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -WebSession $web
		$login = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -Method Post -WebSession $web -Body @{
			identity = $fixture.Username
			password = $password
			study_csrf_token = Get-CsrfToken $loginPage
		}
		if ($login.StatusCode -ne 200) { throw "Login failed for role fixture $($fixture.Code)." }

		$adminPage = $null
		$adminStatus = Get-HttpStatus { $adminPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kelola') -WebSession $web; return $adminPage }
		if ($adminStatus -ne $fixture.AdminIndex) { throw "Role $($fixture.Code) expected /kelola $($fixture.AdminIndex), got $adminStatus." }
		if ($adminPage) {
			if ($adminPage.Content -match 'Kelola konten dan pantau kesiapan layanan' -or $adminPage.Content -notmatch 'disesuaikan dengan peran dan wewenang') {
				throw "Dashboard copy does not accurately describe permissions for role $($fixture.Code)."
			}
			foreach ($metric in @('Kursus','Paket belajar','Pesanan pembayaran')) {
				$hasMetric = $adminPage.Content -match ('class="stat-label">' + [regex]::Escape($metric) + '</span>')
				$shouldHaveMetric = $fixture.Counts -contains $metric
				if ($hasMetric -ne $shouldHaveMetric) { throw "Role $($fixture.Code) has unexpected visibility for dashboard metric '$metric'." }
			}
		}
		$courseStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kelola/kursus') -WebSession $web }
		if ($courseStatus -ne $fixture.CourseList) { throw "Role $($fixture.Code) expected /kelola/kursus $($fixture.CourseList), got $courseStatus." }
		$directCourseStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + 'admin/courses') -WebSession $web }
		if ($directCourseStatus -ne $fixture.CourseList) { throw "Role $($fixture.Code) expected direct /admin/courses $($fixture.CourseList), got $directCourseStatus." }
		$canManageContent = @('system_admin','content_editor') -contains $fixture.Code
		$contentManagementExpected = if ($canManageContent) { 200 } else { 403 }
		foreach ($contentPath in @('kelola/kategori','admin/categories','kelola/kategori/tambah','admin/create_category','kelola/kursus/tambah','admin/create_course')) {
			$contentStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + $contentPath) -WebSession $web }
			if ($contentStatus -ne $contentManagementExpected) { throw "Role $($fixture.Code) expected $contentPath $contentManagementExpected, got $contentStatus." }
		}
		foreach ($createPath in @('kelola/kategori/tambah','admin/create_category','kelola/kursus/tambah','admin/create_course')) {
			$createCsrfPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + '/') -WebSession $web
			$createBody = @{ study_csrf_token = Get-CsrfToken $createCsrfPage }
			$createStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + $createPath) -Method Post -WebSession $web -Body $createBody }
			if ($createStatus -ne $contentManagementExpected) { throw "Role $($fixture.Code) expected invalid POST $createPath $contentManagementExpected, got $createStatus." }
		}
		foreach ($detailPath in @("kelola/kursus/$archiveCourseId", "admin/course/$archiveCourseId")) {
			$detailStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + $detailPath) -WebSession $web }
			if ($detailStatus -ne $contentManagementExpected) { throw "Role $($fixture.Code) expected $detailPath $contentManagementExpected, got $detailStatus." }
		}
		$draftAccessExpected = if ($fixture.Code -in @('system_admin','content_editor')) { 200 } else { 403 }
		foreach ($draftPath in @(
			"kelola/kursus/$draftCourseId", "admin/course/$draftCourseId",
			"kelola/kursus/$draftCourseId/soal", "questions/index/$draftCourseId",
			"kelola/kursus/$draftCourseId/edit", "admin/edit_course/$draftCourseId",
			"kelola/kursus/$draftCourseId/pelajaran/tambah", "admin/add_lesson/$draftCourseId",
			"kelola/kursus/$draftCourseId/pelajaran/$draftLessonId/edit", "admin/edit_lesson/$draftCourseId/$draftLessonId",
			"kelola/kursus/$draftCourseId/pelajaran/$draftLessonId/flashcard", "admin/flashcards/$draftCourseId/$draftLessonId",
			"kelola/kursus/$draftCourseId/pelajaran/$draftLessonId/pratinjau", "admin/preview_lesson/$draftCourseId/$draftLessonId"
		)) {
			$draftStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + $draftPath) -WebSession $web }
			if ($draftStatus -ne $draftAccessExpected) { throw "Role $($fixture.Code) expected owned draft route $draftPath $draftAccessExpected, got $draftStatus." }
		}
		foreach ($lessonWritePath in @(
			"kelola/kursus/$draftCourseId/pelajaran/tambah", "admin/add_lesson/$draftCourseId",
			"kelola/kursus/$draftCourseId/pelajaran/$draftLessonId/edit", "admin/edit_lesson/$draftCourseId/$draftLessonId"
		)) {
			$lessonCsrfPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + '/') -WebSession $web
			$lessonPostStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + $lessonWritePath) -Method Post -WebSession $web -Body @{ study_csrf_token = Get-CsrfToken $lessonCsrfPage } }
			if ($lessonPostStatus -ne $draftAccessExpected) { throw "Role $($fixture.Code) expected invalid POST $lessonWritePath $draftAccessExpected, got $lessonPostStatus." }
			$draftLessonState = Invoke-Db "SELECT CONCAT(COUNT(*),'|',MAX(title)) FROM st_lessons WHERE section_id IN (SELECT id FROM st_course_sections WHERE course_id=$draftCourseId);"
			if ($draftLessonState -ne '1|QA Role Lesson') { throw "Invalid lesson write by $($fixture.Code) changed the owned draft fixture: $draftLessonState." }
		}
		$allowedMethodStatus = if ($canManageContent) { 404 } else { 403 }
		foreach ($actionPath in @("kelola/kursus/$archiveCourseId/ajukan", "admin/submit_course/$archiveCourseId", "kelola/kursus/$archiveCourseId/tinjau", "admin/review_course/$archiveCourseId")) {
			$actionGetStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + $actionPath) -WebSession $web }
			if ($actionGetStatus -ne $allowedMethodStatus) { throw "Role $($fixture.Code) expected GET $actionPath $allowedMethodStatus, got $actionGetStatus." }
		}
		foreach ($actionPath in @("kelola/kursus/$archiveCourseId/ajukan", "admin/submit_course/$archiveCourseId", "kelola/kursus/$archiveCourseId/tinjau", "admin/review_course/$archiveCourseId")) {
			$actionCsrfPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + '/') -WebSession $web
			$actionBody = @{ decision = 'approved'; notes = 'Synthetic role test'; study_csrf_token = Get-CsrfToken $actionCsrfPage }
			$actionPostStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + $actionPath) -Method Post -WebSession $web -Body $actionBody }
			$expectedActionPost = if ($canManageContent) { 200 } else { 403 }
			if ($actionPostStatus -ne $expectedActionPost) { throw "Role $($fixture.Code) expected POST $actionPath $expectedActionPost, got $actionPostStatus." }
			$actionState = Invoke-Db "SELECT CONCAT(status,'|',(SELECT COUNT(*) FROM st_audit_events WHERE entity_type='course' AND entity_id=$archiveCourseId AND event_type IN ('content.course_submitted','content.course_reviewed'))) FROM st_courses WHERE id=$archiveCourseId;"
			if ($actionState -ne 'published|0') { throw "Invalid workflow request by $($fixture.Code) changed the published course or audit trail: $actionState." }
		}
		$archiveGetExpected = if ($fixture.Code -eq 'system_admin') { 404 } else { 403 }
		foreach ($archivePath in @("kelola/kursus/$archiveCourseId/arsip", "admin/archive_course/$archiveCourseId")) {
			$archiveGetStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + $archivePath) -WebSession $web }
			if ($archiveGetStatus -ne $archiveGetExpected) { throw "Role $($fixture.Code) expected GET $archivePath $archiveGetExpected, got $archiveGetStatus." }
		}
		$familyAliasStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + 'keluarga/anak') -WebSession $web }
		$familyControllerStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + 'family/children') -WebSession $web }
		if ($familyAliasStatus -ne 403 -or $familyControllerStatus -ne 403) { throw "Non-parent account role $($fixture.Code) must be denied both family routes (alias=$familyAliasStatus, direct=$familyControllerStatus)." }
		if ($fixture.Code -ne 'system_admin') {
			foreach ($archivePath in @("kelola/kursus/$archiveCourseId/arsip", "admin/archive_course/$archiveCourseId")) {
				$csrfPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + '/') -WebSession $web
				$archiveBody = @{ study_csrf_token = Get-CsrfToken $csrfPage }
				$archiveStatus = Get-HttpStatus { Invoke-WebRequest -UseBasicParsing -Uri ($root + $archivePath) -Method Post -WebSession $web -Body $archiveBody }
				if ($archiveStatus -ne 403) { throw "Role $($fixture.Code) must be denied archive route $archivePath, got HTTP $archiveStatus." }
				$archiveState = Invoke-Db "SELECT CONCAT(status,'|',(SELECT COUNT(*) FROM st_audit_events WHERE entity_type='course' AND entity_id=$archiveCourseId AND event_type='content.course_archived')) FROM st_courses WHERE id=$archiveCourseId;"
				if ($archiveState -ne 'published|0') { throw "Unauthorized role $($fixture.Code) changed the archive fixture or audit trail: $archiveState." }
			}
		}
		$displayRole = if ($fixture.Code) { $fixture.Code } else { 'member' }
		Write-Output "PASS role '$displayRole': panel $adminStatus, content list $courseStatus/$directCourseStatus, non-parent family routes 403/403."
		Write-Output "PASS role '$displayRole': category/course views and invalid creates, GET/POST submit/review, and archive permissions were checked on aliases and direct controller routes."
		Write-Output "PASS role '$displayRole': draft ownership controls course/lesson/flashcard pages and invalid lesson POSTs without changing the fixture."

		$dashboard = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'beranda') -WebSession $web
		Invoke-WebRequest -UseBasicParsing -Uri ($root + 'keluar') -Method Post -WebSession $web -Body @{ study_csrf_token = Get-CsrfToken $dashboard } | Out-Null
	}
	$systemFixture = $roleMatrix | Where-Object { $_.Code -eq 'system_admin' } | Select-Object -First 1
	$systemWeb = New-Object Microsoft.PowerShell.Commands.WebRequestSession
	$systemLoginPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -WebSession $systemWeb
	Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -Method Post -WebSession $systemWeb -Body @{ identity = $systemFixture.Username; password = $password; study_csrf_token = Get-CsrfToken $systemLoginPage } | Out-Null
	$archiveCsrfPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + '/') -WebSession $systemWeb
	$archiveResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + "admin/archive_course/$archiveCourseId") -Method Post -WebSession $systemWeb -Body @{ study_csrf_token = Get-CsrfToken $archiveCsrfPage }
	if ($archiveResult.StatusCode -ne 200) { throw 'System administrator did not complete the direct archive action.' }
	$archiveState = Invoke-Db "SELECT CONCAT(status,'|',(SELECT COUNT(*) FROM st_audit_events WHERE entity_type='course' AND entity_id=$archiveCourseId AND event_type='content.course_archived')) FROM st_courses WHERE id=$archiveCourseId;"
	if ($archiveState -ne 'archived|1') { throw "System administrator archive action should archive and audit exactly once, got $archiveState." }
	Write-Output 'PASS only system_admin can archive a published course; denied alias/direct POSTs leave course/audit unchanged, GET is rejected, and allowed action is audited.'
}
finally {
	if ($fixtureCreated) {
		$cleanupSql = @"
DELETE FROM st_audit_events WHERE entity_type='course' AND entity_id IN (SELECT id FROM st_courses WHERE slug='$archiveCourseSlug');
DELETE FROM st_audit_events WHERE entity_type='course' AND entity_id IN (SELECT id FROM st_courses WHERE slug='$draftCourseSlug');
DELETE FROM st_lessons WHERE slug='$draftLessonSlug';
DELETE FROM st_course_sections WHERE course_id IN (SELECT id FROM st_courses WHERE slug='$draftCourseSlug');
DELETE FROM st_courses WHERE slug='$draftCourseSlug';
DELETE FROM st_courses WHERE slug='$archiveCourseSlug';
DELETE FROM st_audit_events WHERE actor_user_id IN (SELECT id FROM st_users WHERE username IN ($quotedUsernames))
	OR (entity_type='user' AND entity_id IN (SELECT id FROM st_users WHERE username IN ($quotedUsernames)));
DELETE FROM st_user_roles WHERE user_id IN (SELECT id FROM st_users WHERE username IN ($quotedUsernames));
DELETE FROM st_users WHERE username IN ($quotedUsernames);
"@
		Invoke-Db $cleanupSql | Out-Null
		$leftovers = Invoke-Db "SELECT (SELECT COUNT(*) FROM st_users WHERE username IN ($quotedUsernames)) + (SELECT COUNT(*) FROM st_user_roles ur JOIN st_users u ON u.id=ur.user_id WHERE u.username IN ($quotedUsernames)) + (SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id IN (SELECT id FROM st_users WHERE username IN ($quotedUsernames))) + (SELECT COUNT(*) FROM st_courses WHERE slug IN ('$archiveCourseSlug','$draftCourseSlug')) + (SELECT COUNT(*) FROM st_lessons WHERE slug='$draftLessonSlug') + (SELECT COUNT(*) FROM st_course_sections WHERE course_id=$draftCourseId) + (SELECT COUNT(*) FROM st_audit_events WHERE entity_type='course' AND entity_id IN ($archiveCourseId,$draftCourseId));"
		if ($leftovers -ne '0') { throw "Role-access QA cleanup left $leftovers fixture row(s)." }
		Write-Output 'PASS temporary role users, role grants, archive course, and audit events are cleaned.'
	}
}
