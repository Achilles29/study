param(
	[Parameter(Mandatory=$true)][string] $Php81Path,
	[Parameter(Mandatory=$true)][string] $MysqlPath,
	[Parameter(Mandatory=$true)][string] $BaseUrl,
	[string] $ChromePath = '',
	[switch] $CaptureScreenshots
)

$ErrorActionPreference = 'Stop'
$parsedBaseUrl = $null
if (-not [Uri]::TryCreate($BaseUrl, [UriKind]::Absolute, [ref] $parsedBaseUrl) -or $parsedBaseUrl.Scheme -ne 'http' -or -not $parsedBaseUrl.IsLoopback -or $parsedBaseUrl.UserInfo -or $parsedBaseUrl.Query -or $parsedBaseUrl.Fragment) { throw 'Dashboard resume QA requires an HTTP loopback URL without credentials, query or fragment.' }
foreach ($binary in @($Php81Path, $MysqlPath)) { if (-not (Test-Path -LiteralPath $binary -PathType Leaf)) { throw 'A required local CLI executable is missing.' } }
if ($ChromePath -and -not (Test-Path -LiteralPath $ChromePath -PathType Leaf)) { throw 'The selected Chrome executable is missing.' }
if ($CaptureScreenshots -and -not $ChromePath) { throw 'Dashboard screenshots require an explicit Chrome path.' }
$root = $BaseUrl.TrimEnd('/') + '/'
$suffix = [Guid]::NewGuid().ToString('N').Substring(0, 16)
$prefix = 'qa-resume-' + $suffix
$password = 'Synthetic-Resume-Check-123!'
$usernames = @{}
foreach ($kind in @('adult', 'child', 'other', 'parent')) { $usernames[$kind] = 'qa_resume_' + $kind + '_' + $suffix }
$userSql = (@($usernames.Values) | ForEach-Object { "'$_'" }) -join ','
$packageSlug = $prefix + '-package'
$owned = $false
$userIds = @{}
$lessonIds = @{}
$courseIds = @{}
$webSessions = New-Object 'System.Collections.Generic.List[object]'
$browserAttempted = $false
$qaStage = 'fixture-preflight'
$httpStage = 'not-requested'
$httpStatus = 0
$primaryFailure = $null
$cleanupFailures = New-Object 'System.Collections.Generic.List[string]'
$baselineDir = Join-Path (Split-Path -Parent $PSScriptRoot) ('docs/visual-baseline/' + (Get-Date -Format 'yyyy-MM-dd') + '-dashboard-resume-' + $suffix)
if ($CaptureScreenshots -and (Test-Path -LiteralPath $baselineDir)) { throw 'Refusing to reuse a dashboard screenshot directory.' }
$passwordHash = (& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim()
if ($LASTEXITCODE -ne 0 -or -not $passwordHash) { throw 'Could not generate a synthetic password hash.' }

# One lesson per course makes each access/status branch independent. Negatives
# are newer than every allowed candidate, exposing LIMIT-before-access bugs.
$cases = @(
	@{ Key='free_old'; Access='free'; Time='2026-01-01 00:00:00' },
	@{ Key='free_recent'; Access='free'; Time='2026-01-03 00:00:00' },
	@{ Key='preview'; Access='preview'; Time='2026-01-02 00:00:00' },
	@{ Key='manual'; Access='manual'; Time='2026-01-02 00:00:00' },
	@{ Key='free_enrollment'; Access='enrolled_free'; Time='2026-01-02 00:00:00' },
	@{ Key='package_active'; Access='package'; Time='2026-01-02 00:00:00' },
	@{ Key='package_expired'; Access='package'; Entitlement='expired' },
	@{ Key='package_future'; Access='package'; Entitlement='future' },
	@{ Key='package_revoked'; Access='package'; Entitlement='revoked' },
	@{ Key='package_ended'; Access='package'; Entitlement='ended' },
	@{ Key='enrollment_revoked'; Access='manual'; Enrollment='revoked' },
	@{ Key='enrollment_completed'; Access='enrolled_free'; Enrollment='completed' },
	@{ Key='no_enrollment'; Access='none' },
	@{ Key='course_draft'; Access='free'; CourseStatus='draft' },
	@{ Key='course_archived'; Access='free'; CourseStatus='archived' },
	@{ Key='lesson_draft'; Access='free'; LessonStatus='draft' },
	@{ Key='lesson_archived'; Access='free'; LessonStatus='archived' },
	@{ Key='completed'; Access='free'; Progress='completed' },
	@{ Key='not_started'; Access='free'; Progress='not_started' }
)
$positiveKeys = @('free_old', 'free_recent', 'preview', 'manual', 'free_enrollment', 'package_active')
$slugs = @($cases | ForEach-Object { $prefix + '-' + $_.Key.Replace('_', '-') })
$slugSql = ($slugs | ForEach-Object { "'$_'" }) -join ','
$lessonSlugs = @($slugs | ForEach-Object { $_ + '-lesson' })
$lessonSlugSql = ($lessonSlugs | ForEach-Object { "'$_'" }) -join ','
$unicode = 'Belajar ' + [char]::ConvertFromUtf32(0x1F308) + ' ' + [char]0x00E9
$unsafeTitle = $unicode + ' & <b>synthetic</b> "quoted"'

function Invoke-Db([string] $Sql) {
	# SQL goes through stdin, not the Windows command line (the fixture is over
	# 32 KiB). Drain both output streams concurrently before waiting for exit.
	$start = New-Object Diagnostics.ProcessStartInfo
	$start.FileName = [IO.Path]::GetFullPath($MysqlPath)
	$start.Arguments = '--no-defaults --host=127.0.0.1 --protocol=TCP --default-character-set=utf8mb4 -uroot -D db_study -N -B'
	$start.UseShellExecute = $false
	$start.CreateNoWindow = $true
	$start.RedirectStandardInput = $true
	$start.RedirectStandardOutput = $true
	$start.RedirectStandardError = $true
	$start.StandardOutputEncoding = New-Object Text.UTF8Encoding($false)
	$start.StandardErrorEncoding = New-Object Text.UTF8Encoding($false)
	$process = New-Object Diagnostics.Process
	$process.StartInfo = $start
	$started = $false
	try {
		$started = $process.Start()
		if (-not $started) { throw 'Database process did not start.' }
		$stdout = $process.StandardOutput.ReadToEndAsync()
		$stderr = $process.StandardError.ReadToEndAsync()
		$inputBytes = [Text.Encoding]::UTF8.GetBytes($Sql + [Environment]::NewLine)
		$stdin = $process.StandardInput.BaseStream.WriteAsync($inputBytes, 0, $inputBytes.Length)
		if (-not $stdin.Wait(30000)) { throw 'Database input exceeded its time limit.' }
		$process.StandardInput.Close()
		if (-not $process.WaitForExit(30000)) { throw 'Database process exceeded its time limit.' }
		$output = $stdout.GetAwaiter().GetResult()
		$stderr.GetAwaiter().GetResult() | Out-Null
		if ($process.ExitCode -ne 0) { throw 'Database process reported an error.' }
		return $output.Trim()
	} catch {
		throw 'Dashboard fixture database operation failed; SQL and data are not printed.'
	} finally {
		if ($started -and -not $process.HasExited) { $process.Kill(); $process.WaitForExit(5000) | Out-Null }
		$process.Dispose()
	}
}
function Sql-Text([string] $Value) {
	$hex = [BitConverter]::ToString([Text.Encoding]::UTF8.GetBytes($Value)).Replace('-', '')
	return "CONVERT(0x$hex USING utf8mb4)"
}
function Invoke-Http($Session, [string] $Route, [string] $Method = 'GET', [hashtable] $Fields = @{}) {
	# Diagnostics contain only fixed route categories, methods and status codes.
	# Never include the supplied URL, identity, request fields or response body.
	$routeLabel = switch -Regex (($Route -split '\?',2)[0]) {
		'^masuk$' { 'masuk'; break }
		'^keluar$' { 'keluar'; break }
		'^(beranda|dashboard/index)$' { 'dashboard'; break }
		'^materi/[a-z0-9-]+$' { 'materi'; break }
		default { 'other' }
	}
	$methodLabel = if ($Method -in @('GET','HEAD','POST')) { $Method } else { 'other' }
	$script:httpStage = $methodLabel + '-' + $routeLabel + '-request'
	$script:httpStatus = 0
	$request = [Net.HttpWebRequest]::Create($root + $Route)
	$request.Method = $Method
	$request.CookieContainer = $Session.Cookies
	$request.AllowAutoRedirect = $false
	$request.Timeout = 15000
	if ($Method -eq 'POST') {
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
		if (-not $response) { throw 'Dashboard HTTP transport failed; no automatic POST retry.' }
	}
	try {
		$script:httpStatus = [int]$response.StatusCode
		$script:httpStage = $methodLabel + '-' + $routeLabel + '-body'
		$result = @{ Status=[int]$response.StatusCode; Content=''; Location=$response.Headers['Location']; Allow=$response.Headers['Allow']; CacheControl=$response.Headers['Cache-Control']; Robots=$response.Headers['X-Robots-Tag'] }
		$reader = New-Object IO.StreamReader($response.GetResponseStream())
		try { $result.Content = $reader.ReadToEnd() } finally { $reader.Close() }
		$script:httpStage = $methodLabel + '-' + $routeLabel + '-complete'
		return $result
	} finally { $response.Close() }
}
function Csrf([string] $Html) {
	$match = [regex]::Match($Html, 'name="study_csrf_token"\s+value="([^"]+)"')
	if (-not $match.Success) { throw 'Synthetic session CSRF field is missing.' }
	return [Net.WebUtility]::HtmlDecode($match.Groups[1].Value)
}
function New-Session([string] $Identity = '') {
	$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
	$webSessions.Add($session)
	if ($Identity) {
		$page = Invoke-Http $session 'masuk'
		if ($page.Status -ne 200) { throw 'Synthetic sign-in page is unavailable.' }
		$login = Invoke-Http $session 'masuk' 'POST' @{ study_csrf_token=(Csrf $page.Content); identity=$Identity; password=$password }
		if ($login.Status -notin @(302,303) -or $login.Location -notmatch '/beranda$') { throw 'Synthetic dashboard login failed.' }
	}
	return $session
}
function Progress-Digest {
	return Invoke-Db "SELECT id,SHA2(CONCAT_WS('|',learner_user_id,lesson_id,status,last_position,COALESCE(started_at,''),COALESCE(completed_at,''),updated_at),256) FROM st_lesson_progress ORDER BY id;"
}
function Audit-Digest([switch] $ExcludeFixtureLogin) {
	$where = if ($ExcludeFixtureLogin) { "WHERE NOT (COALESCE(actor_user_id,0) IN (SELECT id FROM st_users WHERE username IN ($userSql)) AND entity_type='user' AND actor_user_id=entity_id AND event_type='auth.login' AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.method')),'')='password')" } else { '' }
	return Invoke-Db "SELECT id,SHA2(CONCAT_WS('|',COALESCE(actor_user_id,0),event_type,entity_type,COALESCE(entity_id,0),COALESCE(correlation_id,''),COALESCE(details_json,''),created_at),256) FROM st_audit_events $where ORDER BY id;"
}
function Login-Count([long] $UserId) {
	return [int](Invoke-Db "SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$UserId AND entity_type='user' AND entity_id=$UserId AND event_type='auth.login' AND JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.method'))='password';")
}
function Get-Dashboard($Session, [string] $Route = 'beranda', [string] $Method = 'GET') {
	$beforeProgress = Progress-Digest
	$beforeAudit = Audit-Digest
	$response = Invoke-Http $Session $Route $Method
	if ($response.Status -ne 200 -or $response.Content -match 'A PHP Error was encountered|Undefined array key|Severity: Warning|Fatal error:') { throw 'Dashboard returned an error or PHP diagnostic.' }
	if ($response.CacheControl -notmatch '(?:^|,)\s*private\s*(?:,|$)' -or $response.CacheControl -notmatch '(?:^|,)\s*no-store\s*(?:,|$)' -or $response.Robots -notmatch '(?:^|,)\s*noindex\s*(?:,|$)' -or $response.Robots -notmatch '(?:^|,)\s*nofollow\s*(?:,|$)') { throw 'Authenticated dashboard GET/HEAD must carry private no-store and noindex/nofollow headers.' }
	if ((Progress-Digest) -cne $beforeProgress -or (Audit-Digest) -cne $beforeAudit) { throw 'Dashboard GET/HEAD unexpectedly changed progress or audit data.' }
	if ($Method -eq 'HEAD' -and $response.Content) { throw 'HEAD unexpectedly returned a response body.' }
	return $response.Content
}
function Assert-Resume([string] $Html, [string[]] $Keys) {
	$sections = [regex]::Matches($Html, '(?s)<section\b(?=[^>]*\bid="resume-learning")[^>]*>.*?</section>')
	if (-not $Keys.Count) {
		if ($sections.Count -ne 0 -or $Html -match '\bdata-lesson-id=') { throw 'Empty or parent dashboard unexpectedly exposes resume cards.' }
		return ''
	}
	if ($sections.Count -ne 1) { throw 'Exactly one resume-learning section is required.' }
	$articles = @([regex]::Matches($sections[0].Value, '(?s)<article\b(?=[^>]*\bdata-lesson-id="([1-9][0-9]*)")[^>]*>.*?</article>'))
	$actual = @($articles | ForEach-Object { $_.Groups[1].Value })
	$expected = @($Keys | ForEach-Object { [string]$lessonIds[$_] })
	if ($actual.Count -gt 3 -or ($actual -join '|') -cne ($expected -join '|')) { throw 'Resume cards do not match exact learner/access/time/lesson-ID ordering.' }
	for ($index=0; $index -lt $Keys.Count; $index++) {
		$slug = $prefix + '-' + $Keys[$index].Replace('_','-') + '-lesson'
		$links = @([regex]::Matches($articles[$index].Value, 'href="([^"]+)"') | ForEach-Object { [Net.WebUtility]::HtmlDecode($_.Groups[1].Value) })
		if (($root + 'materi/' + $slug + '?lanjut=1#reading-material') -cnotin $links) { throw 'Resume link does not preserve the native lesson reading-position destination.' }
	}
	return $sections[0].Value
}
function Invoke-DashboardBrowser([string] $Kind, [string] $Name) {
	if (-not $ChromePath) { return }
	$beforeProgress = Progress-Digest
	$beforeAudit = Audit-Digest -ExcludeFixtureLogin
	$beforeLogin = Login-Count $userIds[$Kind]
	$oldIdentity = [Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY','Process')
	$oldPassword = [Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD','Process')
	try {
		$env:STUDY_QA_LOGIN_IDENTITY = $usernames[$Kind]
		$env:STUDY_QA_LOGIN_PASSWORD = $password
		$arguments = @((Join-Path $PSScriptRoot 'browser-viewport-check.mjs'), '--base-url', $root, '--chrome', $ChromePath, '--extra-path', 'beranda', '--extra-name', $Name, '--only-extra-route', '--exercise-navigation')
		if ($CaptureScreenshots) { $arguments += @('--capture-screenshots', '--screenshot-dir', $baselineDir) }
		$script:browserAttempted = $true
		& node @arguments
		if ($LASTEXITCODE -ne 0) { throw 'Dashboard resume browser regression failed.' }
	} finally {
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY',$oldIdentity,'Process')
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD',$oldPassword,'Process')
	}
	if ((Progress-Digest) -cne $beforeProgress -or (Audit-Digest -ExcludeFixtureLogin) -cne $beforeAudit -or (Login-Count $userIds[$Kind]) -ne ($beforeLogin+1)) { throw 'Browser reads changed progress/audit beyond one expected fixture login.' }
}

try {
	if ((Invoke-Db "SELECT (SELECT COUNT(*) FROM st_users WHERE username IN ($userSql))+(SELECT COUNT(*) FROM st_courses WHERE slug IN ($slugSql))+(SELECT COUNT(*) FROM st_lessons WHERE slug IN ($lessonSlugSql))+(SELECT COUNT(*) FROM st_packages WHERE slug='$packageSlug');") -ne '0') { throw 'Refusing to reuse dashboard fixture identities.' }
	$owned = $true
	$sql = New-Object 'System.Collections.Generic.List[string]'
	$sql.Add('START TRANSACTION;')
	foreach ($kind in @('adult','child','other','parent')) {
		$accountKind = if ($kind -eq 'other') { 'adult' } else { $kind }
		$stage = if ($kind -eq 'child') { 'primary' } elseif ($kind -eq 'parent') { 'unset' } else { 'adult' }
		$sql.Add("INSERT INTO st_users(username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES('$($usernames[$kind])','$passwordHash','Synthetic resume $kind','$accountKind','$stage','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()); SET @resume_$kind=LAST_INSERT_ID();")
	}
	$sql.Add("INSERT INTO st_family_links(parent_user_id,child_user_id,status,created_at,updated_at) VALUES(@resume_parent,@resume_child,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP());")
	$sql.Add("INSERT INTO st_packages(title,slug,summary,credit_price,access_days,status,created_at,updated_at) VALUES('Synthetic resume package','$packageSlug','QA only; not purchasable',0,7,'published',UTC_TIMESTAMP(),UTC_TIMESTAMP()); SET @resume_package=LAST_INSERT_ID();")
	foreach ($case in $cases) {
		$slug = $prefix + '-' + $case.Key.Replace('_','-')
		$courseStatus = if ($case.CourseStatus) { $case.CourseStatus } else { 'published' }
		$lessonStatus = if ($case.LessonStatus) { $case.LessonStatus } else { 'published' }
		$progressStatus = if ($case.Progress) { $case.Progress } else { 'in_progress' }
		$time = if ($case.Time) { $case.Time } else { '2026-01-10 00:00:00' }
		$isFree = if ($case.Access -eq 'free') { 1 } else { 0 }
		$isPreview = if ($case.Access -eq 'preview') { 1 } else { 0 }
		$title = if ($case.Key -eq 'package_active') { $unsafeTitle } else { 'Synthetic ' + $case.Key }
		$sql.Add("INSERT INTO st_courses(author_user_id,updated_by_user_id,title,slug,summary,learning_objectives,target_group,status,content_version,rights_status,source_note,is_free,published_at,created_at,updated_at) VALUES(@resume_adult,@resume_adult,$(Sql-Text ('Course ' + $title)),'$slug','Synthetic resume course','QA only','all_ages','$courseStatus',1,'owned','Synthetic source', $isFree,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP()); SET @resume_course=LAST_INSERT_ID();")
		$sql.Add("INSERT INTO st_course_sections(course_id,title,sort_order,created_at,updated_at) VALUES(@resume_course,'Synthetic section',1,UTC_TIMESTAMP(),UTC_TIMESTAMP()); SET @resume_section=LAST_INSERT_ID();")
		$sql.Add("INSERT INTO st_lessons(section_id,title,slug,body_text,estimated_minutes,sort_order,status,content_version,is_preview,source_note,rights_status,created_at,updated_at) VALUES(@resume_section,$(Sql-Text $title),'$slug-lesson','Synthetic content; no production material.',5,1,'$lessonStatus',1,$isPreview,'Synthetic source','owned',UTC_TIMESTAMP(),UTC_TIMESTAMP()); SET @resume_lesson=LAST_INSERT_ID();")
		$position = if ($progressStatus -eq 'completed') { 10000 } else { 2700 }
		$completedAt = if ($progressStatus -eq 'completed') { "'$time'" } else { 'NULL' }
		$sql.Add("INSERT INTO st_lesson_progress(learner_user_id,lesson_id,status,last_position,started_at,completed_at,updated_at) VALUES(@resume_adult,@resume_lesson,'$progressStatus',$position,'2026-01-01 00:00:00',$completedAt,'$time');")
		if ($case.Access -in @('manual','enrolled_free','package')) {
			$enrollment = if ($case.Enrollment) { $case.Enrollment } else { 'active' }
			$source = if ($case.Access -eq 'enrolled_free') { 'free' } else { $case.Access }
			$entitlement = 'NULL'; $package = 'NULL'
			if ($case.Access -eq 'package') {
				$entitlementStatus = if ($case.Entitlement -in @('expired','revoked')) { $case.Entitlement } else { 'active' }
				$starts = if ($case.Entitlement -eq 'future') { 'DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY)' } else { 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)' }
				$ends = if ($case.Entitlement -in @('expired','ended')) { 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)' } else { 'DATE_ADD(UTC_TIMESTAMP(), INTERVAL 3 DAY)' }
				$sql.Add("INSERT INTO st_entitlements(user_id,package_id,payment_order_id,status,starts_at,ends_at,created_at) VALUES(@resume_adult,@resume_package,NULL,'$entitlementStatus',$starts,$ends,UTC_TIMESTAMP()); SET @resume_entitlement=LAST_INSERT_ID();")
				$entitlement='@resume_entitlement'; $package='@resume_package'
			}
			$sql.Add("INSERT INTO st_course_enrollments(learner_user_id,course_id,source_type,package_id,entitlement_id,status,enrolled_at,updated_at) VALUES(@resume_adult,@resume_course,'$source',$package,$entitlement,'$enrollment',UTC_TIMESTAMP(),UTC_TIMESTAMP());")
		}
	}
	# Child/parent have different own rows; the unrelated adult has no progress.
	$sql.Add("INSERT INTO st_lesson_progress(learner_user_id,lesson_id,status,last_position,started_at,updated_at) SELECT @resume_child,id,'in_progress',4600,'2026-01-01 00:00:00','2026-01-05 00:00:00' FROM st_lessons WHERE slug='$prefix-free-old-lesson';")
	$sql.Add("INSERT INTO st_lesson_progress(learner_user_id,lesson_id,status,last_position,started_at,updated_at) SELECT @resume_parent,id,'in_progress',1200,'2026-01-01 00:00:00','2026-01-06 00:00:00' FROM st_lessons WHERE slug='$prefix-preview-lesson';")
	$sql.Add('COMMIT;')
	$qaStage = 'fixture-insert'
	Invoke-Db ($sql -join "`n") | Out-Null
	$qaStage = 'fixture-read-identifiers'
	foreach ($kind in @('adult','child','other','parent')) {
		$id = Invoke-Db "SELECT id FROM st_users WHERE username='$($usernames[$kind])';"
		if ($id -notmatch '\A[1-9][0-9]*\z') { throw 'Fixture user ID is invalid.' }
		$userIds[$kind]=[long]$id
	}
	foreach ($case in $cases) {
		$slug = $prefix + '-' + $case.Key.Replace('_','-')
		$ids = (Invoke-Db "SELECT l.id,c.id FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id WHERE l.slug='$slug-lesson' AND c.slug='$slug' AND c.author_user_id=$($userIds.adult);") -split '\s+'
		if ($ids.Count -ne 2 -or @($ids | Where-Object { $_ -notmatch '\A[1-9][0-9]*\z' }).Count) { throw 'Fixture lesson/course IDs are invalid.' }
		$lessonIds[$case.Key]=[long]$ids[0]; $courseIds[$case.Key]=[long]$ids[1]
	}
	$qaStage = 'login-adult'; $adult = New-Session $usernames.adult
	$qaStage = 'login-child'; $child = New-Session $usernames.child
	$qaStage = 'login-other'; $other = New-Session $usernames.other
	$qaStage = 'login-parent'; $parent = New-Session $usernames.parent
	$qaStage = 'adult-populated'
	$expected = @('free_recent','package_active','free_enrollment')
	$main = Get-Dashboard $adult
	$section = Assert-Resume $main $expected
	if (-not $section.Contains('&lt;b&gt;synthetic&lt;/b&gt;') -or $section.Contains('<b>synthetic</b>') -or -not $section.Contains($unicode) -or -not ([Net.WebUtility]::HtmlDecode($section)).Contains($unsafeTitle)) { throw 'Resume title/course Unicode or escaping is incorrect.' }
	if ($section -match 'penguasaan|durasi belajar|terakhir membuka' -or $main -match 'Pilih pelajaran pertamamu|Ayo temukan hal baru hari ini') { throw 'Populated dashboard still claims an empty first lesson or misleading progress semantics.' }
	$qaStage = 'dashboard-alias'
	Assert-Resume (Get-Dashboard $adult 'dashboard/index') $expected | Out-Null
	$identityQueries = @(('learner_user_id='+$userIds.child), ('user_id='+$userIds.child), ('anak='+$userIds.child), ('learner_user_id%5B%5D='+$userIds.child))
	if ($identityQueries.Count -ne 4) { throw 'Identity-isolation query coverage is incomplete.' }
	$qaStage = 'identity-query-isolation'
	foreach ($query in $identityQueries) { Assert-Resume (Get-Dashboard $adult ('beranda?'+$query)) $expected | Out-Null }
	$qaStage = 'account-isolation'
	Assert-Resume (Get-Dashboard $child) @('free_old') | Out-Null
	Assert-Resume (Get-Dashboard $other ('beranda?learner_user_id='+$userIds.adult)) @() | Out-Null
	Assert-Resume (Get-Dashboard $parent) @() | Out-Null
	$qaStage = 'dashboard-head'
	Get-Dashboard $adult 'beranda' 'HEAD' | Out-Null
	$qaStage = 'dashboard-post-rejection'
	$beforeProgress=Progress-Digest; $beforeAudit=Audit-Digest
	$post=Invoke-Http $adult 'beranda' 'POST' @{ study_csrf_token=(Csrf $main) }
	if ($post.Status -ne 405 -or $post.Allow -notmatch 'GET' -or $post.Allow -notmatch 'HEAD') { throw 'Authenticated dashboard POST must be rejected with 405 and Allow.' }
	if ((Progress-Digest) -cne $beforeProgress -or (Audit-Digest) -cne $beforeAudit) { throw 'Rejected dashboard POST mutated progress/audit.' }
	$qaStage = 'anonymous-redirect'
	$anonymous=New-Session
	$anonymousPage=Invoke-Http $anonymous 'beranda'
	# CI3's redirect helper uses 307 for GET on HTTP/1.1 and 303 for POST.
	if ($anonymousPage.Status -notin @(302,303,307) -or $anonymousPage.Location -notmatch '/masuk$') { throw 'Anonymous dashboard must require login.' }
	$qaStage = 'valid-resume-destination'
	$beforeProgress=Progress-Digest; $beforeAudit=Audit-Digest
	$destination=Invoke-Http $adult ('materi/'+$prefix+'-package-active-lesson?lanjut=1')
	if ($destination.Status -ne 200 -or $destination.Content -notmatch 'id="reading-material"[^>]*data-saved-position="2700"') { throw 'A valid resume destination did not render the existing saved reading position.' }
	if ((Progress-Digest) -cne $beforeProgress -or (Audit-Digest) -cne $beforeAudit) { throw 'Opening a valid resume destination mutated progress/audit.' }
	Write-Output 'PASS own unfinished published lessons: access filtering before max-three limit, timestamp/lesson-ID ordering, Unicode/escaping, resume URLs, identity isolation and GET/HEAD/POST behavior.'
	$qaStage = 'populated-browser'
	Invoke-DashboardBrowser 'adult' 'resume-adult-populated'
	Invoke-DashboardBrowser 'child' 'resume-child-populated'
	Invoke-DashboardBrowser 'other' 'resume-adult-empty'

	$positiveIds = ($positiveKeys | ForEach-Object { $lessonIds[$_] }) -join ','
	# Isolate each positive permission path while all newer denied candidates stay.
	Invoke-Db "UPDATE st_lesson_progress SET status='not_started' WHERE learner_user_id=$($userIds.adult) AND lesson_id IN ($positiveIds);" | Out-Null
	foreach ($key in $positiveKeys) {
		$qaStage = 'positive-access-' + $key
		Invoke-Db "UPDATE st_lesson_progress SET status='in_progress' WHERE learner_user_id=$($userIds.adult) AND lesson_id=$($lessonIds[$key]);" | Out-Null
		Assert-Resume (Get-Dashboard $adult) @($key) | Out-Null
		if ($key -eq 'package_active') {
			Invoke-Db "UPDATE st_entitlements e JOIN st_course_enrollments ce ON ce.entitlement_id=e.id SET e.ends_at=NULL WHERE ce.learner_user_id=$($userIds.adult) AND ce.course_id=$($courseIds.package_active) AND e.user_id=$($userIds.adult);" | Out-Null
			Assert-Resume (Get-Dashboard $adult) @($key) | Out-Null
		}
		Invoke-Db "UPDATE st_lesson_progress SET status='not_started' WHERE learner_user_id=$($userIds.adult) AND lesson_id=$($lessonIds[$key]);" | Out-Null
	}
	$qaStage = 'adult-locked'
	$locked=Get-Dashboard $adult
	Assert-Resume $locked @() | Out-Null
	if ($locked -notmatch 'Jelajahi kursus' -or $locked -match 'histori.*(?:dihapus|hilang)') { throw 'No-resumable fallback must offer catalog without claiming history deletion.' }
	Invoke-DashboardBrowser 'adult' 'resume-adult-locked'
	$qaStage = 'child-locked'
	Invoke-Db "UPDATE st_courses SET is_free=0 WHERE id=$($courseIds.free_old) AND author_user_id=$($userIds.adult);" | Out-Null
	$childLocked=Get-Dashboard $child
	Assert-Resume $childLocked @() | Out-Null
	if ($childLocked -notmatch 'Jelajahi kursus') { throw 'Child locked-only fallback must offer the catalog.' }
	Invoke-DashboardBrowser 'child' 'resume-child-locked'
	Invoke-Db "UPDATE st_courses SET is_free=1 WHERE id=$($courseIds.free_old) AND author_user_id=$($userIds.adult);" | Out-Null
	# An access revocation must take effect without re-login, with progress intact.
	$qaStage = 'revoked-resume-destination'
	Invoke-Db "UPDATE st_lesson_progress SET status='in_progress' WHERE learner_user_id=$($userIds.adult) AND lesson_id=$($lessonIds.manual);" | Out-Null
	Assert-Resume (Get-Dashboard $adult) @('manual') | Out-Null
	Invoke-Db "UPDATE st_course_enrollments SET status='revoked' WHERE learner_user_id=$($userIds.adult) AND course_id=$($courseIds.manual);" | Out-Null
	Assert-Resume (Get-Dashboard $adult) @() | Out-Null
	$beforeProgress=Progress-Digest; $beforeAudit=Audit-Digest
	$closed=Invoke-Http $adult ('materi/'+$prefix+'-manual-lesson?lanjut=1')
	if ($closed.Status -notin @(302,303,307) -or $closed.Location -notmatch ('/kursus/'+[regex]::Escape($prefix+'-manual')+'$')) { throw 'Opening an old resume destination bypassed revoked access.' }
	if ((Progress-Digest) -cne $beforeProgress -or (Audit-Digest) -cne $beforeAudit) { throw 'Revoked destination access mutated progress/audit.' }
	Write-Output 'PASS every allowed path independently, newer denied candidates, locked-only recovery and revocation without re-login; dashboard reads preserve full progress/audit digests.'
	if ($CaptureScreenshots) { Write-Output ('PASS unique dashboard captures saved in '+$baselineDir) }
}
catch {
	# Preserve the first failure before cleanup changes the HTTP diagnostic state.
	# A source line locates the failed assertion without exposing exception data.
	$failureLine = [int]$_.InvocationInfo.ScriptLineNumber
	$primaryFailure = "Dashboard resume QA failed at stage=$qaStage; http=$httpStage; status=$httpStatus; script-line=$failureLine."
}
finally {
	$sessionNumber=0
	foreach ($session in $webSessions) {
		$sessionNumber++
		$cleanupStage='masuk'
		try {
			# Native logout destroys only the HTTP session owned by this cookie jar.
			$page=Invoke-Http $session 'masuk'
			if ($page.Status -in @(302,303,307)) { $cleanupStage='beranda'; $page=Invoke-Http $session 'beranda' }
			if ($page.Status -ne 200) { throw 'Logout token page is unavailable.' }
			$cleanupStage += '-csrf'
			$logoutToken=Csrf $page.Content
			$cleanupStage='keluar'
			$logout=Invoke-Http $session 'keluar' 'POST' @{ study_csrf_token=$logoutToken }
			if ($logout.Status -notin @(302,303)) { throw 'Exact session logout failed.' }
		} catch {
			$cleanupFailures.Add("HTTP session $sessionNumber cleanup failed at stage=$cleanupStage; http=$httpStage; status=$httpStatus; script-line=$([int]$_.InvocationInfo.ScriptLineNumber).")
		}
	}
	if ($owned) {
		try {
			# No wildcard deletion or login-throttle reset. Exact reserved usernames,
			# slugs and author ownership scope all fixture-dependent removals.
			$cleanup=@"
START TRANSACTION;
DELETE FROM st_audit_events WHERE actor_user_id IN (SELECT id FROM st_users WHERE username IN ($userSql)) OR (entity_type='user' AND entity_id IN (SELECT id FROM st_users WHERE username IN ($userSql)));
DELETE p FROM st_lesson_progress p JOIN st_users u ON u.id=p.learner_user_id WHERE u.username IN ($userSql);
DELETE ce FROM st_course_enrollments ce JOIN st_users u ON u.id=ce.learner_user_id WHERE u.username IN ($userSql);
DELETE e FROM st_entitlements e JOIN st_users u ON u.id=e.user_id JOIN st_packages p ON p.id=e.package_id WHERE u.username IN ($userSql) AND p.slug='$packageSlug';
DELETE l FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id JOIN st_users u ON u.id=c.author_user_id WHERE l.slug IN ($lessonSlugSql) AND c.slug IN ($slugSql) AND u.username='$($usernames.adult)';
DELETE s FROM st_course_sections s JOIN st_courses c ON c.id=s.course_id JOIN st_users u ON u.id=c.author_user_id WHERE c.slug IN ($slugSql) AND u.username='$($usernames.adult)';
DELETE c FROM st_courses c JOIN st_users u ON u.id=c.author_user_id WHERE c.slug IN ($slugSql) AND u.username='$($usernames.adult)';
DELETE FROM st_packages WHERE slug='$packageSlug';
DELETE f FROM st_family_links f JOIN st_users p ON p.id=f.parent_user_id JOIN st_users c ON c.id=f.child_user_id WHERE p.username='$($usernames.parent)' AND c.username='$($usernames.child)';
DELETE FROM st_users WHERE username IN ($userSql);
COMMIT;
"@
			Invoke-Db $cleanup | Out-Null
			$remaining=Invoke-Db "SELECT (SELECT COUNT(*) FROM st_users WHERE username IN ($userSql))+(SELECT COUNT(*) FROM st_courses WHERE slug IN ($slugSql))+(SELECT COUNT(*) FROM st_lessons WHERE slug IN ($lessonSlugSql))+(SELECT COUNT(*) FROM st_packages WHERE slug='$packageSlug');"
			if ($remaining -ne '0') { throw 'Dashboard fixture cleanup is incomplete.' }
			if ($userIds.Count -eq 4) {
				$idList=(@($userIds.Values) | ForEach-Object { [long]$_ }) -join ','
				if ((Invoke-Db "SELECT (SELECT COUNT(*) FROM st_lesson_progress WHERE learner_user_id IN ($idList))+(SELECT COUNT(*) FROM st_course_enrollments WHERE learner_user_id IN ($idList))+(SELECT COUNT(*) FROM st_entitlements WHERE user_id IN ($idList))+(SELECT COUNT(*) FROM st_family_links WHERE parent_user_id IN ($idList) OR child_user_id IN ($idList))+(SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id IN ($idList) OR (entity_type='user' AND entity_id IN ($idList))); ") -ne '0') { throw 'Synthetic dependent rows or audit events remain.' }
			}
			Write-Output 'PASS exact synthetic users, courses/lessons, progress, enrollment/entitlement, family link and auth audit removed; unrelated data and login-failure rows preserved.'
		} catch {
			$cleanupFailures.Add("Exact fixture database cleanup could not be confirmed; script-line=$([int]$_.InvocationInfo.ScriptLineNumber). No automatic broad cleanup was attempted.")
		}
	}
	if ($browserAttempted) { Write-Output 'NOTE the browser harness owns closing/removing its profiles. Residual server-side browser session files are not scanned/deleted; removed synthetic accounts invalidate them on the next authenticated request.' }
}
if ($primaryFailure) {
	foreach ($cleanupFailure in $cleanupFailures) { Write-Output ('ADDITIONAL CLEANUP FAILURE: ' + $cleanupFailure) }
	throw $primaryFailure
}
if ($cleanupFailures.Count) { throw ($cleanupFailures -join ' ') }
