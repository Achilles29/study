param(
	[Parameter(Mandatory = $true)][string] $Php81Path,
	[Parameter(Mandatory = $true)][string] $MysqlPath,
	[Parameter(Mandatory = $true)][string] $BaseUrl,
	[string] $ChromePath = '',
	[switch] $CaptureScreenshots
)

$ErrorActionPreference = 'Stop'
$parsedBaseUrl = $null
if (-not [Uri]::TryCreate($BaseUrl, [UriKind]::Absolute, [ref] $parsedBaseUrl) -or $parsedBaseUrl.Scheme -ne 'http' -or -not $parsedBaseUrl.IsLoopback -or $parsedBaseUrl.UserInfo -or $parsedBaseUrl.Query -or $parsedBaseUrl.Fragment) {
	throw 'Unsaved-form QA requires an HTTP loopback URL without credentials, query or fragment.'
}
foreach ($binary in @($Php81Path, $MysqlPath)) { if (-not (Test-Path -LiteralPath $binary -PathType Leaf)) { throw 'A required local CLI executable is missing.' } }
if ($ChromePath -and -not (Test-Path -LiteralPath $ChromePath -PathType Leaf)) { throw 'The selected Chrome executable is missing.' }
if ($CaptureScreenshots -and -not $ChromePath) { throw 'Unsaved-form screenshots require an explicit Chrome path.' }
$root = $BaseUrl.TrimEnd('/') + '/'
$suffix = [Guid]::NewGuid().ToString('N').Substring(0, 16)
$username = 'qa_unsaved_' + $suffix
$slug = 'qa-unsaved-' + $suffix
$lessonSlug = $slug + '-lesson'
$captureDir = Join-Path (Split-Path -Parent $PSScriptRoot) ('docs/visual-baseline/' + (Get-Date -Format 'yyyy-MM-dd') + '-unsaved-forms-' + $suffix)
if ($CaptureScreenshots -and (Test-Path -LiteralPath $captureDir)) { throw 'Refusing to reuse an unsaved-form screenshot directory.' }
$storedCourseTitle = 'Synthetic & <b>course</b> "quoted" ' + $suffix
$storedLessonTitle = 'Synthetic & <b>lesson</b> "quoted" ' + $suffix
$password = 'Synthetic-Unsaved-Check-123!'
$hash = (& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim()
if ($LASTEXITCODE -ne 0 -or $hash -notmatch '\A\$2[ayb]\$[0-9]{2}\$[./A-Za-z0-9]{53}\z') { throw 'Synthetic credential setup failed.' }
$owned = $false
$actorId = 0
$courseId = 0
$lessonId = 0
$web = New-Object Microsoft.PowerShell.Commands.WebRequestSession

function Invoke-Db([string] $Sql) {
	$output = & $MysqlPath '--no-defaults' '--host=127.0.0.1' '--protocol=TCP' '--default-character-set=utf8mb4' '-uroot' '-D' 'db_study' '-N' '-B' '-e' $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw 'Unsaved-form database operation failed; no SQL/data are printed.' }
	return ($output -join "`n").Trim()
}
function Sql-Text([string] $Value) {
	$hex = [BitConverter]::ToString([Text.Encoding]::UTF8.GetBytes($Value)).Replace('-', '')
	return "CONVERT(0x$hex USING utf8mb4)"
}
function Request([string] $Route, [string] $Method = 'GET', [hashtable] $Fields = @{}) {
	$request = [Net.HttpWebRequest]::Create($root + $Route)
	$request.Method = $Method
	$request.CookieContainer = $web.Cookies
	$request.AllowAutoRedirect = $false
	$request.Timeout = 15000
	if ($Method -eq 'POST') {
		if (-not $Fields.ContainsKey('study_csrf_token')) {
			$cookie = $web.Cookies.GetCookies([Uri] $root)['study_csrf_cookie']
			if (-not $cookie) { throw 'Expected fixture CSRF cookie is absent.' }
			$Fields['study_csrf_token'] = $cookie.Value
		}
		$body = ($Fields.GetEnumerator() | ForEach-Object { [Net.WebUtility]::UrlEncode($_.Key) + '=' + [Net.WebUtility]::UrlEncode([string] $_.Value) }) -join '&'
		$bytes = [Text.Encoding]::UTF8.GetBytes($body)
		$request.ContentType = 'application/x-www-form-urlencoded'
		$request.ContentLength = $bytes.Length
		$stream = $request.GetRequestStream()
		try { $stream.Write($bytes, 0, $bytes.Length) } finally { $stream.Close() }
	}
	try { $response = $request.GetResponse() }
	catch {
		$response = $_.Exception.Response
		if (-not $response -and $_.Exception.InnerException) { $response = $_.Exception.InnerException.Response }
		if (-not $response) { throw 'Unsaved-form HTTP transport failed; no automatic POST retry.' }
	}
	try {
		$reader = New-Object IO.StreamReader($response.GetResponseStream())
		try { $content = $reader.ReadToEnd() } finally { $reader.Close() }
		return @{ Status = [int] $response.StatusCode; Content = $content }
	} finally { $response.Close() }
}
function Assert-Form($Response, [int] $ExpectedStatus, [bool] $Dirty) {
	if ($Response.Status -ne $ExpectedStatus -or $Response.Content -match 'A PHP Error was encountered|Undefined array key|Severity: Warning|Fatal error:') { throw 'Unsaved-form response has an unexpected status or PHP diagnostic.' }
	$forms = [regex]::Matches($Response.Content, '<form\b[^>]*\bdata-unsaved-form(?:\s|=|>)[^>]*>')
	if ($forms.Count -ne 1) { throw 'Exactly one content form must opt into unsaved-change detection.' }
	$initialDirty = $forms[0].Value -match 'data-unsaved-initial="true"'
	if ($initialDirty -ne $Dirty -or $forms[0].Value -notmatch 'data-unsaved-initial="(?:true|false)"') { throw 'Server unsaved-state marker does not match GET/failed-POST state.' }
	$status = [regex]::Match($Response.Content, '<[a-z]+\b[^>]*\bdata-unsaved-status(?:\s|=|>)[^>]*>')
	if (-not $status.Success -or $status.Value -notmatch 'role="status"' -or $status.Value -notmatch 'aria-live="polite"') { throw 'Unsaved state must have one polite accessible status region.' }
	if ($Response.Content -notmatch 'assets/js/unsaved-form\.js\?v=[0-9]+') { throw 'Content form must load the versioned unsaved-form script.' }
}
function Assert-Field([string] $Html, [string] $Name, [string] $Expected) {
	$namePattern = [regex]::Escape($Name)
	$input = [regex]::Match($Html, '<input\b(?=[^>]*\bname="' + $namePattern + '")[^>]*\bvalue="([^"]*)"[^>]*>')
	$area = [regex]::Match($Html, '(?s)<textarea\b(?=[^>]*\bname="' + $namePattern + '")[^>]*>(.*?)</textarea>')
	$found = if ($input.Success) { $input.Groups[1].Value } elseif ($area.Success) { $area.Groups[1].Value } else { throw 'Preserved input control is missing.' }
	if ([Net.WebUtility]::HtmlDecode($found) -cne $Expected) { throw 'Failed POST did not preserve the expected synthetic input.' }
}
function Course-Payload {
	return @{ title = ('Unsaved & <b>course</b> "quoted" ' + $suffix); summary = 'Synthetic & <b>summary</b> "quoted" retained after validation.'; learning_objectives = 'Preserve this & <b>objective</b> "quoted".'; prerequisites = 'Synthetic & <b>preparation</b> "quoted"'; target_group = 'adult'; category_id = ''; age_min = ''; age_max = ''; estimated_minutes = '0'; rights_status = 'owned'; source_note = 'Synthetic & <b>source</b> "quoted"' }
}
function Lesson-Payload([string] $Revision = '2', [string] $Duration = '0') {
	return @{ revision = $Revision; title = ('Unsaved & <b>lesson</b> "quoted" ' + $suffix); body_text = 'Synthetic & <b>lesson body</b> "quoted" retained after validation or stale revision.'; estimated_minutes = $Duration; rights_status = 'owned'; source_note = 'Synthetic & <b>source</b> "quoted"' }
}
function Fixture-Digest {
	return Invoke-Db "SELECT 'course',c.id,SHA2(CONCAT_WS('|',c.title,c.slug,c.summary,c.learning_objectives,COALESCE(c.prerequisites,''),c.target_group,c.status,c.content_version,c.is_free,c.rights_status,c.source_note,c.updated_by_user_id,c.created_at,c.updated_at),256) FROM st_courses c JOIN st_users u ON u.id=c.author_user_id WHERE u.username='$username' ORDER BY c.id; SELECT 'lesson',l.id,SHA2(CONCAT_WS('|',l.title,l.slug,l.body_text,l.estimated_minutes,l.sort_order,l.status,l.content_version,l.is_preview,l.rights_status,l.source_note,l.created_at,l.updated_at),256) FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id JOIN st_users u ON u.id=c.author_user_id WHERE u.username='$username' ORDER BY l.id; SELECT 'section',s.id,SHA2(CONCAT_WS('|',s.course_id,s.title,s.sort_order,s.created_at,s.updated_at),256) FROM st_course_sections s JOIN st_courses c ON c.id=s.course_id JOIN st_users u ON u.id=c.author_user_id WHERE u.username='$username' ORDER BY s.id;"
}
function Audit-Digest([switch] $ExcludeFixtureLogin) {
	$where = if ($ExcludeFixtureLogin) { "WHERE NOT COALESCE(actor_user_id=$actorId AND entity_type='user' AND entity_id=$actorId AND event_type='auth.login' AND JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.method'))='password',0)" } else { '' }
	return Invoke-Db "SELECT id,SHA2(CONCAT_WS('|',COALESCE(actor_user_id,0),event_type,COALESCE(entity_type,''),COALESCE(entity_id,0),COALESCE(details_json,''),created_at),256) FROM st_audit_events $where ORDER BY id;"
}
function Login-Count {
	return [int] (Invoke-Db "SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$actorId AND entity_type='user' AND entity_id=$actorId AND event_type='auth.login' AND JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.method'))='password';")
}

try {
	if ($username -notmatch '\Aqa_unsaved_[a-f0-9]{16}\z' -or $slug -notmatch '\Aqa-unsaved-[a-f0-9]{16}\z') { throw 'Invalid synthetic fixture names.' }
	if ((Invoke-Db "SELECT (SELECT COUNT(*) FROM st_users WHERE username='$username')+(SELECT COUNT(*) FROM st_courses WHERE slug='$slug')+(SELECT COUNT(*) FROM st_lessons WHERE slug='$lessonSlug');") -ne '0') { throw 'Refusing to reuse an unsaved-form fixture identity.' }
	$owned = $true
	$ids = (Invoke-Db @"
START TRANSACTION;
INSERT INTO st_users(username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES('$username','$hash','QA Unsaved Forms','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @unsaved_actor=LAST_INSERT_ID();
INSERT INTO st_user_roles(user_id,role_id,granted_by_user_id,created_at) SELECT @unsaved_actor,id,@unsaved_actor,UTC_TIMESTAMP() FROM st_roles WHERE role_code='content_editor';
INSERT INTO st_courses(author_user_id,updated_by_user_id,title,slug,summary,learning_objectives,target_group,status,content_version,rights_status,source_note,is_free,estimated_minutes,created_at,updated_at) VALUES(@unsaved_actor,@unsaved_actor,$(Sql-Text $storedCourseTitle),'$slug','Synthetic course summary for form checks.','Learn safe draft editing.','adult','draft',1,'owned','Synthetic QA source',1,5,UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @unsaved_course=LAST_INSERT_ID();
INSERT INTO st_course_sections(course_id,title,sort_order,created_at,updated_at) VALUES(@unsaved_course,'Synthetic section',1,UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @unsaved_section=LAST_INSERT_ID();
INSERT INTO st_lessons(section_id,title,slug,body_text,estimated_minutes,sort_order,status,content_version,is_preview,rights_status,source_note,created_at,updated_at) VALUES(@unsaved_section,$(Sql-Text $storedLessonTitle),'$lessonSlug','Synthetic lesson body for unsaved form checks.',5,1,'draft',2,1,'owned','Synthetic QA source',UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @unsaved_lesson=LAST_INSERT_ID();
COMMIT;
SELECT @unsaved_actor,@unsaved_course,@unsaved_lesson;
"@) -split '\s+'
	if ($ids.Count -ne 3 -or @($ids | Where-Object { $_ -notmatch '\A[1-9][0-9]*\z' }).Count) { throw 'Synthetic fixture ID inventory is invalid.' }
	$actorId = [int] $ids[0]; $courseId = [int] $ids[1]; $lessonId = [int] $ids[2]
	Request 'masuk' | Out-Null
	$login = Request 'masuk' 'POST' @{ identity = $username; password = $password }
	if ($login.Status -ne 303 -or (Login-Count) -ne 1) { throw 'Synthetic editor login did not finish with one expected audit event.' }
	$routes = @('kelola/kursus/tambah', "kelola/kursus/$courseId/edit", "kelola/kursus/$courseId/pelajaran/tambah", "kelola/kursus/$courseId/pelajaran/$lessonId/edit")
	$before = Fixture-Digest
	$beforeAudit = Audit-Digest
	Assert-Field (Request $routes[1]).Content 'title' $storedCourseTitle
	Assert-Field (Request $routes[3]).Content 'title' $storedLessonTitle
	foreach ($route in $routes) {
		Assert-Form (Request $route) 200 $false
		$isLesson = $route.Contains('/pelajaran/')
		$payload = if ($isLesson) { Lesson-Payload } else { Course-Payload }
		$invalid = Request $route 'POST' $payload
		Assert-Form $invalid 200 $true
		Assert-Field $invalid.Content 'title' $payload.title
		Assert-Field $invalid.Content 'estimated_minutes' '0'
		Assert-Field $invalid.Content $(if ($isLesson) { 'body_text' } else { 'summary' }) $(if ($isLesson) { $payload.body_text } else { $payload.summary })
		Assert-Field $invalid.Content 'source_note' $payload.source_note
		if (-not $isLesson) { Assert-Field $invalid.Content 'learning_objectives' $payload.learning_objectives; Assert-Field $invalid.Content 'prerequisites' $payload.prerequisites }
		if ($invalid.Content -notmatch 'id="estimated_minutes"[^>]*aria-invalid="true"') { throw 'Invalid duration must remain linked to its field error.' }
		$checkboxName = if ($isLesson) { 'is_preview' } else { 'is_free' }
		if ($invalid.Content -match ('id="' + $checkboxName + '"[^>]*checked')) { throw 'Failed POST must preserve an unchecked checkbox rather than restoring its stored value.' }
	}
	$stalePayload = Lesson-Payload '1' '5'
	$conflict = Request $routes[3] 'POST' $stalePayload
	Assert-Form $conflict 409 $true
	Assert-Field $conflict.Content 'title' $stalePayload.title
	Assert-Field $conflict.Content 'body_text' $stalePayload.body_text
	Assert-Field $conflict.Content 'revision' '1'
	if ($conflict.Content -notmatch 'type="submit" disabled' -or $conflict.Content -notmatch 'Muat ulang pelajaran') { throw 'Stale lesson must keep its disabled submit and recovery link.' }
	$malformed = Course-Payload
	$malformed.Remove('title'); $malformed['title[]'] = 'Synthetic invalid array input'
	$badEdit = Request $routes[1] 'POST' $malformed
	if ($badEdit.Status -ne 400 -or $badEdit.Content -match 'A PHP Error was encountered|Array to string conversion|Severity: Warning|Fatal error:|data-unsaved-form') { throw 'Non-scalar edit-course input must fail as a generic HTTP 400 without rendering unsafe values.' }
	foreach ($route in @('', 'masuk', 'kelola/kursus')) {
		$response = Request $route
		if ($response.Content -match 'data-unsaved-form|assets/js/unsaved-form\.js') { throw 'Unrelated routes must not opt into unsaved-content handling.' }
	}
	if ((Fixture-Digest) -cne $before -or (Audit-Digest) -cne $beforeAudit) { throw 'Form reads, invalid submissions or stale revision changed fixture content/audit.' }
	Write-Output 'PASS four opt-in content forms: clean GET, dirty failed validation, preserved text/checkbox/revision, dirty disabled stale form, unrelated routes excluded and no content/audit writes.'
	if ($ChromePath) {
		$oldIdentity = [Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY', 'Process')
		$oldPassword = [Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD', 'Process')
		$beforeBrowserAudit = Audit-Digest -ExcludeFixtureLogin
		$beforeLogin = Login-Count
		try {
			$env:STUDY_QA_LOGIN_IDENTITY = $username; $env:STUDY_QA_LOGIN_PASSWORD = $password
			$browserArguments = @((Join-Path $PSScriptRoot 'unsaved-form-browser-check.mjs'), '--base-url', $root, '--chrome', $ChromePath, '--course-id', $courseId, '--lesson-id', $lessonId, '--fixture-suffix', $suffix)
			if ($CaptureScreenshots) { $browserArguments += @('--capture-dir', $captureDir) }
			& node @browserArguments
			if ($LASTEXITCODE -ne 0) { throw 'Unsaved-form browser integration failed.' }
		} finally {
			[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY', $oldIdentity, 'Process')
			[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD', $oldPassword, 'Process')
		}
		if ((Fixture-Digest) -cne $before -or (Audit-Digest -ExcludeFixtureLogin) -cne $beforeBrowserAudit -or (Login-Count) -ne ($beforeLogin + 1)) { throw 'Browser changed content/audit beyond one expected fixture login.' }
	}
	else { Write-Warning 'Real browser unsaved-state checks skipped; pass -ChromePath to exercise loaded page JavaScript.' }
}
finally {
	if ($owned) {
		# Exact fixture course plus owner; no wildcard, real account or authentication-failure deletion.
		Invoke-Db @"
START TRANSACTION;
DELETE FROM st_audit_events WHERE actor_user_id IN (SELECT id FROM st_users WHERE username='$username') OR (entity_type='user' AND entity_id IN (SELECT id FROM st_users WHERE username='$username')) OR (entity_type='course' AND entity_id IN (SELECT c.id FROM st_courses c JOIN st_users u ON u.id=c.author_user_id WHERE c.slug='$slug' AND u.username='$username')) OR (entity_type='lesson' AND entity_id IN (SELECT l.id FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id JOIN st_users u ON u.id=c.author_user_id WHERE l.slug='$lessonSlug' AND c.slug='$slug' AND u.username='$username'));
DELETE l FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id JOIN st_users u ON u.id=c.author_user_id WHERE l.slug='$lessonSlug' AND c.slug='$slug' AND u.username='$username';
DELETE s FROM st_course_sections s JOIN st_courses c ON c.id=s.course_id JOIN st_users u ON u.id=c.author_user_id WHERE c.slug='$slug' AND u.username='$username';
DELETE c FROM st_courses c JOIN st_users u ON u.id=c.author_user_id WHERE c.slug='$slug' AND u.username='$username';
DELETE ur FROM st_user_roles ur JOIN st_users u ON u.id=ur.user_id WHERE u.username='$username';
DELETE FROM st_users WHERE username='$username';
COMMIT;
"@ | Out-Null
		if ((Invoke-Db "SELECT (SELECT COUNT(*) FROM st_users WHERE username='$username')+(SELECT COUNT(*) FROM st_courses WHERE slug='$slug')+(SELECT COUNT(*) FROM st_lessons WHERE slug='$lessonSlug');") -ne '0') { throw 'Unsaved-form fixture cleanup is incomplete.' }
		Write-Output 'PASS exact synthetic editor/course/section/lesson/roles/audit cleanup; login-failure records preserved.'
	}
}
