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
	throw 'Admin course-list QA requires an HTTP loopback URL without credentials, query or fragment.'
}
foreach ($binary in @($Php81Path, $MysqlPath)) { if (-not (Test-Path -LiteralPath $binary -PathType Leaf)) { throw 'A required local CLI executable is missing.' } }
if ($ChromePath -and -not (Test-Path -LiteralPath $ChromePath -PathType Leaf)) { throw 'The selected local Chrome executable is missing.' }
if ($CaptureScreenshots -and -not $ChromePath) { throw 'Admin course-list screenshots require an explicit Chrome path.' }
$root = $BaseUrl.TrimEnd('/') + '/'
$suffix = [Guid]::NewGuid().ToString('N').Substring(0, 16)
$prefix = 'qa-admin-course-list-0-' + $suffix
$username = 'qa_admin_courses_' + $suffix
$slugs = @(1..24 | ForEach-Object { $prefix + '-' + $_.ToString('00') })
$slugSql = ($slugs | ForEach-Object { "'$_'" }) -join ','
$password = 'Synthetic-Admin-Courses-Check-123!'
$owned = $false
$adminId = 0
$courseIds = @()
$web = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$baselineDir = Join-Path (Split-Path -Parent $PSScriptRoot) ('docs/visual-baseline/' + (Get-Date -Format 'yyyy-MM-dd') + '-admin-course-list-pagination-' + $suffix)
if ($CaptureScreenshots -and (Test-Path -LiteralPath $baselineDir)) { throw 'Refusing to reuse an admin course-list screenshot directory.' }
$passwordHash = (& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim()
if ($LASTEXITCODE -ne 0 -or $passwordHash -notmatch '\A\$2[ayb]\$[0-9]{2}\$[./A-Za-z0-9]{53}\z') { throw 'Synthetic admin credential setup failed.' }

function Invoke-Db([string] $Sql) {
	$output = & $MysqlPath '--no-defaults' '--host=127.0.0.1' '--protocol=TCP' '--default-character-set=utf8mb4' '-uroot' '-D' 'db_study' '-N' '-B' '-e' $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw 'Admin course-list database operation failed; no SQL/data are printed.' }
	return ($output -join "`n").Trim()
}
function Sql-Text([string] $Value) {
	$hex = [BitConverter]::ToString([Text.Encoding]::UTF8.GetBytes($Value)).Replace('-', '')
	return "CONVERT(0x$hex USING utf8mb4)"
}
function Request($Session, [string] $Route, [string] $Method = 'GET', [hashtable] $Fields = @{}) {
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
		if (-not $response) { throw 'Admin course-list HTTP transport failed; no automatic POST retry.' }
	}
	try {
		$reader = New-Object IO.StreamReader($response.GetResponseStream())
		try { $content = $reader.ReadToEnd() } finally { $reader.Close() }
		return @{ Status = [int] $response.StatusCode; Content = $content; Location = $response.Headers['Location'] }
	} finally { $response.Close() }
}
function Get-Courses([string] $Query = '', [string] $Route = 'kelola/kursus') {
	$response = Request $web ($Route + $(if ($Query) { '?' + $Query } else { '' }))
	if ($response.Status -ne 200 -or $response.Content -match 'A PHP Error was encountered|Undefined array key|Severity: Warning|Fatal error:') { throw 'Admin course list returned an error or PHP diagnostic.' }
	return $response.Content
}
function Assert-Ids([string] $Html, [string[]] $Expected) {
	# Each responsive representation has one title link and one action link per course.
	# Do not globally deduplicate links: that would conceal duplicated/missing rows.
	$representations = @{
		mobile = @([regex]::Matches($Html, '(?s)<article\b[^>]*class="admin-course-card"[^>]*>.*?</article>') | ForEach-Object { $_.Value })
		desktop = @()
	}
	$body = [regex]::Matches($Html, '(?s)<tbody\b[^>]*>(.*?)</tbody>')
	if ($body.Count -gt 1) { throw 'Unexpected duplicate desktop course tables.' }
	if ($body.Count -eq 1) { $representations.desktop = @([regex]::Matches($body[0].Groups[1].Value, '(?s)<tr\b[^>]*>.*?</tr>') | ForEach-Object { $_.Value }) }
	foreach ($kind in @('mobile', 'desktop')) {
		$actual = @()
		foreach ($block in $representations[$kind]) {
			$links = @([regex]::Matches($block, 'href="[^"]*/kelola/kursus/([1-9][0-9]*)"') | ForEach-Object { $_.Groups[1].Value })
			if ($links.Count -ne 2 -or $links[0] -cne $links[1]) { throw 'A responsive course row does not contain exactly its matching title/action links.' }
			$actual += $links[0]
		}
		if (($actual -join '|') -cne ($Expected -join '|')) { throw 'Admin course order/set differs from its exact responsive fixture expectation.' }
	}
}
function Assert-Count([string] $Html, [int] $Expected) {
	if ($Html -notmatch ('\b' + $Expected + ' kursus ditemukan')) { throw 'Filtered admin course count is incorrect.' }
}
function Assert-Page([string] $Html, [int] $Expected, [int] $Total = 2, [switch] $AllowLegacyProbe) {
	if ($Html -match ('aria-current="page">Halaman ' + $Expected + ' dari ' + $Total + '</span>')) { return }
	if ($AllowLegacyProbe) {
		$legacy = [regex]::Match($Html, '<li class="page-item active">\s*<span class="page-link">([0-9]+)</span>')
		if ($legacy.Success -and [int] $legacy.Groups[1].Value -eq $Expected) { return }
		if ($Expected -eq 2 -and $legacy.Success -and $legacy.Groups[1].Value -eq '1') { throw 'Admin list returned content page 2 but indicator 1.' }
	}
	throw 'Visible admin pagination state differs from the server-normalized result page.'
}
function Assert-PagerLinks([string] $Html, [string] $ExpectedSearch, [string] $ExpectedStatus) {
	$pager = [regex]::Match($Html, '(?s)<nav\b[^>]*aria-label="Halaman daftar"[^>]*>(.*?)</nav>')
	if (-not $pager.Success) { throw 'Shared admin pagination navigation is missing.' }
	$links = @([regex]::Matches($pager.Value, 'href="([^"]+)"') | ForEach-Object { [Net.WebUtility]::HtmlDecode($_.Groups[1].Value) })
	if (-not $links.Count) { throw 'Admin pagination has no navigation link.' }
	foreach ($link in $links) {
		$uri = [Uri] $link
		if (-not $uri.IsAbsoluteUri -or $uri.GetLeftPart([UriPartial]::Path) -cne ($root + 'kelola/kursus')) { throw 'Pagination escaped the exact local admin list route.' }
		$parameters = @{}
		foreach ($part in $uri.Query.TrimStart('?').Split('&')) {
			$pair = $part -split '=', 2
			$key = [Net.WebUtility]::UrlDecode($pair[0])
			$value = if ($pair.Count -eq 2) { [Net.WebUtility]::UrlDecode($pair[1]) } else { '' }
			if ($key -notin @('q', 'status', 'halaman') -or $parameters.ContainsKey($key)) { throw 'Pagination forwarded an unknown or duplicate query parameter.' }
			$parameters[$key] = $value
		}
		if ($parameters.halaman -notmatch '\A[1-9][0-9]*\z' -or [string] $parameters.q -cne $ExpectedSearch -or [string] $parameters.status -cne $ExpectedStatus) { throw 'Pagination did not preserve only its normalized search/status context.' }
	}
}
function Fixture-Digest {
	return Invoke-Db "SELECT c.id,SHA2(CONCAT_WS('|',c.title,c.slug,COALESCE(c.summary,''),c.status,COALESCE(c.category_id,0),c.author_user_id,c.updated_by_user_id,c.content_version,c.rights_status,COALESCE(c.source_note,''),c.is_free,COALESCE(c.published_at,''),c.created_at,c.updated_at),256) FROM st_courses c JOIN st_users u ON u.id=c.author_user_id WHERE c.slug IN ($slugSql) AND u.username='$username' ORDER BY c.id;"
}
function Audit-Digest([switch] $ExcludeFixtureLogin) {
	$where = if ($ExcludeFixtureLogin) { "WHERE NOT (COALESCE(actor_user_id,0)=$adminId AND COALESCE(entity_type,'')='user' AND COALESCE(entity_id,0)=$adminId AND event_type='auth.login' AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.method')),'')='password')" } else { '' }
	return Invoke-Db "SELECT id,SHA2(CONCAT_WS('|',COALESCE(actor_user_id,0),event_type,COALESCE(entity_type,''),COALESCE(entity_id,0),COALESCE(correlation_id,''),COALESCE(details_json,''),created_at),256) FROM st_audit_events $where ORDER BY id;"
}
function Login-Count {
	return [int] (Invoke-Db "SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$adminId AND entity_type='user' AND entity_id=$adminId AND event_type='auth.login' AND JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.method'))='password';")
}
function Invoke-AdminBrowser([string] $Query, [string] $Name) {
	if (-not $ChromePath) { return }
	$beforeCourse = Fixture-Digest
	$beforeAudit = Audit-Digest -ExcludeFixtureLogin
	$beforeLogin = Login-Count
	$oldIdentity = [Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY', 'Process')
	$oldPassword = [Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD', 'Process')
	try {
		$env:STUDY_QA_LOGIN_IDENTITY = $username
		$env:STUDY_QA_LOGIN_PASSWORD = $password
		$arguments = @((Join-Path $PSScriptRoot 'browser-viewport-check.mjs'), '--base-url', $root, '--chrome', $ChromePath, '--extra-path', 'kelola/kursus', '--extra-query', $Query, '--extra-name', $Name, '--only-extra-route')
		if ($CaptureScreenshots) { $arguments += @('--capture-screenshots', '--screenshot-dir', $baselineDir) }
		& node @arguments
		if ($LASTEXITCODE -ne 0) { throw 'Admin course-list browser regression failed.' }
	} finally {
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY', $oldIdentity, 'Process')
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD', $oldPassword, 'Process')
	}
	if ((Fixture-Digest) -cne $beforeCourse -or (Audit-Digest -ExcludeFixtureLogin) -cne $beforeAudit -or (Login-Count) -ne ($beforeLogin + 1)) { throw 'Browser reads changed course/audit data beyond their one expected synthetic login.' }
}

try {
	if ($username -notmatch '\Aqa_admin_courses_[a-f0-9]{16}\z' -or $slugs.Count -ne 24) { throw 'Invalid exact admin course-list fixture identities.' }
	if ((Invoke-Db "SELECT (SELECT COUNT(*) FROM st_users WHERE username='$username')+(SELECT COUNT(*) FROM st_courses WHERE slug IN ($slugSql));") -ne '0') { throw 'Refusing to reuse an admin course-list fixture identity.' }
	$owned = $true
	$statements = New-Object 'System.Collections.Generic.List[string]'
	$statements.Add('START TRANSACTION;')
	$statements.Add("INSERT INTO st_users(username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES('$username','$passwordHash','QA Admin Courses','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()); SET @admin_courses_actor=LAST_INSERT_ID();")
	$statements.Add("INSERT INTO st_user_roles(user_id,role_id,granted_by_user_id,created_at) SELECT @admin_courses_actor,id,@admin_courses_actor,UTC_TIMESTAMP() FROM st_roles WHERE role_code='system_admin';")
	$markup = '<img src=x onerror=alert(1)>'
	$unicode = 'pelangi ' + [char]::ConvertFromUtf32(0x1F308) + ' ' + $suffix
	$summarySearch = $prefix + '-ringkasan-saja'
	for ($index = 1; $index -le 24; $index++) {
		$status = if ($index -le 21) { 'draft' } elseif ($index -eq 22) { 'review' } elseif ($index -eq 23) { 'published' } else { 'archived' }
		$title = $prefix + ' Kursus ' + $index.ToString('00')
		$summary = 'Ringkasan sintetis untuk daftar pengelola.'
		if ($index -eq 1) { $title = $prefix + ' %_! ' + $markup }
		if ($index -eq 2) { $summary += ' ' + $unicode }
		if ($index -eq 3) { $summary += ' ' + $summarySearch }
		if ($index -eq 4) { $title = $prefix + ' AX! wildcard near-match' }
		$statements.Add("INSERT INTO st_courses(author_user_id,updated_by_user_id,title,slug,summary,learning_objectives,target_group,status,content_version,rights_status,source_note,is_free,published_at,created_at,updated_at) VALUES(@admin_courses_actor,@admin_courses_actor,$(Sql-Text $title),'$($slugs[$index-1])',$(Sql-Text $summary),'Synthetic list regression only','adult','$status',1,'owned','Created only for admin course-list QA',1,NULL,'2026-01-01 00:00:00','2026-01-01 00:00:00');")
	}
	$statements.Add('COMMIT; SELECT @admin_courses_actor;')
	$actor = Invoke-Db ($statements -join "`n")
	if ($actor -notmatch '\A[1-9][0-9]*\z') { throw 'Synthetic admin ID is invalid.' }
	$adminId = [int] $actor
	$courseIds = @((Invoke-Db "SELECT id FROM st_courses WHERE author_user_id=$adminId AND slug IN ($slugSql) ORDER BY id;") -split '\s+')
	if ($courseIds.Count -ne 24 -or @($courseIds | Where-Object { $_ -notmatch '\A[1-9][0-9]*\z' }).Count) { throw 'Synthetic course ID inventory is invalid.' }
	if ((Invoke-Db "SELECT COUNT(*) FROM st_user_roles ur JOIN st_roles r ON r.id=ur.role_id WHERE ur.user_id=$adminId AND r.role_code='system_admin';") -ne '1') { throw 'Synthetic admin role is missing.' }
	$loginPage = Request $web 'masuk'
	$csrf = [regex]::Match($loginPage.Content, 'name="study_csrf_token"\s+value="([^"]+)"')
	if ($loginPage.Status -ne 200 -or -not $csrf.Success) { throw 'Synthetic admin login form is unavailable.' }
	$login = Request $web 'masuk' 'POST' @{ identity = $username; password = $password; study_csrf_token = [Net.WebUtility]::HtmlDecode($csrf.Groups[1].Value) }
	if ($login.Status -ne 303 -or (Login-Count) -ne 1) { throw 'Synthetic admin login did not complete with its expected audit event.' }
	$beforeDigest = Fixture-Digest
	$beforeAudit = Audit-Digest
	$query = 'q=' + $prefix + '&status=draft'
	# Run the old bug's meaningful counterexample before requiring the new markup.
	$probe = Get-Courses ($query + '&halaman=%202%20')
	Assert-Ids $probe @($courseIds[0])
	Assert-Count $probe 21
	Assert-Page $probe 2 -AllowLegacyProbe
	$pageOne = Get-Courses $query
	Assert-Ids $pageOne @($courseIds[20..1])
	Assert-Count $pageOne 21
	Assert-Page $pageOne 1
	Assert-PagerLinks $pageOne $prefix 'draft'
	$pageTwo = Get-Courses ($query + '&halaman=2')
	Assert-Ids $pageTwo @($courseIds[0])
	Assert-Count $pageTwo 21
	Assert-Page $pageTwo 2
	Assert-PagerLinks $pageTwo $prefix 'draft'
	if (-not $pageTwo.Contains('&lt;img src=x onerror=alert(1)&gt;') -or $pageTwo.Contains($markup)) { throw 'Course title markup was not rendered as escaped text.' }
	Assert-Ids (Get-Courses $query 'admin/courses') @($courseIds[20..1])
	foreach ($page in @('999999999999', '999999999999999999999999999999999', '2', '%202%20', '0002')) {
		$html = Get-Courses ($query + '&halaman=' + $page)
		Assert-Ids $html @($courseIds[0]); Assert-Page $html 2; Assert-PagerLinks $html $prefix 'draft'
	}
	foreach ($page in @('0', '-1', 'invalid', '000000000002', '')) {
		$html = Get-Courses ($query + '&halaman=' + $page)
		Assert-Ids $html @($courseIds[20..1]); Assert-Page $html 1
	}
	$html = Get-Courses ($query + '&halaman%5B%5D=2')
	Assert-Ids $html @($courseIds[20..1]); Assert-Page $html 1; Assert-PagerLinks $html $prefix 'draft'
	$html = Get-Courses ($query + '&qa_unused=do-not-forward&halaman=2')
	Assert-Ids $html @($courseIds[0]); Assert-Page $html 2; Assert-PagerLinks $html $prefix 'draft'
	if ($html.Contains('qa_unused') -or $html.Contains('do-not-forward')) { throw 'Unknown query parameters leaked into the admin list.' }
	foreach ($status in @('review', 'published', 'archived')) {
		$position = @{ review = 21; published = 22; archived = 23 }[$status]
		$html = Get-Courses ('q=' + $prefix + '&status=' + $status)
		Assert-Ids $html @($courseIds[$position]); Assert-Count $html 1
		if ($html.Contains('aria-current="page"')) { throw 'Single-page filtered results should not render a multi-page indicator.' }
	}
	foreach ($statusQuery in @('status=unknown', 'status=DRAFT', 'status%5B%5D=draft', 'status=')) {
		$html = Get-Courses ('q=' + $prefix + '&' + $statusQuery + '&halaman=2')
		Assert-Ids $html @($courseIds[3..0]); Assert-Count $html 24; Assert-Page $html 2; Assert-PagerLinks $html $prefix ''
	}
	foreach ($searchCase in @(@(($prefix + ' %_!'), $courseIds[0]), @($unicode, $courseIds[1]), @($summarySearch, $courseIds[2]))) {
		$html = Get-Courses ('q=' + [Uri]::EscapeDataString($searchCase[0]) + '&status=draft')
		Assert-Ids $html @($searchCase[1]); Assert-Count $html 1
		$searchField = [regex]::Match($html, '<input\b(?=[^>]*\bname="q")(?=[^>]*\bvalue="([^"]*)")[^>]*>')
		if (-not $searchField.Success -or [Net.WebUtility]::HtmlDecode($searchField.Groups[1].Value) -cne $searchCase[0]) { throw 'Search text was not preserved safely in its field.' }
	}
	$zero = Get-Courses 'q=0&status=draft'
	Assert-PagerLinks $zero '0' 'draft'
	$emptyQuery = 'q=' + $prefix + '-unmatched&status=draft'
	$empty = Get-Courses $emptyQuery
	Assert-Ids $empty @(); Assert-Count $empty 0
	if ($empty -notmatch 'Tidak ada kursus yang cocok' -or $empty -notmatch 'Hapus filter' -or $empty -match 'Belum ada kursus pada daftar ini') { throw 'Filtered empty-state recovery is missing.' }
	$reset = 'href="' + [regex]::Escape($root + 'kelola/kursus') + '"[^>]*>Atur ulang'
	$form = [regex]::Match($pageTwo, '(?s)<form\b(?=[^>]*method="get")(?=[^>]*action="' + [regex]::Escape($root + 'kelola/kursus') + '")[^>]*>.*?</form>')
	if ($pageTwo -notmatch $reset -or -not $form.Success -or $form.Value -match 'name="halaman"') { throw 'Filter/reset navigation does not clear the current page.' }
	if ((Fixture-Digest) -cne $beforeDigest -or (Audit-Digest) -cne $beforeAudit) { throw 'Admin GET requests unexpectedly changed course data or audit events.' }
	Write-Output 'PASS admin course list: separate mobile/table 20+1 ID ordering, normalized page data/indicator, literal/Unicode/summary/status filters, allowlisted context, reset and empty state; GET leaves courses/audit unchanged.'
	Invoke-AdminBrowser $query 'admin-courses-first-page'
	Invoke-AdminBrowser ($query + '&halaman=2') 'admin-courses-last-page'
	Invoke-AdminBrowser $emptyQuery 'admin-courses-filtered-empty'
	if ($CaptureScreenshots) { Write-Output ('PASS unique admin course-list screenshots saved in ' + $baselineDir) }
}
finally {
	if ($owned) {
		# Exact reserved slugs AND exact author; only this synthetic actor's role/auth audit.
		# Login-failure records are deliberately neither deleted nor reset by this harness.
		$cleanup = @"
START TRANSACTION;
DELETE FROM st_audit_events WHERE actor_user_id IN (SELECT id FROM st_users WHERE username='$username') OR (entity_type='user' AND entity_id IN (SELECT id FROM st_users WHERE username='$username')) OR (entity_type='course' AND entity_id IN (SELECT c.id FROM st_courses c JOIN st_users u ON u.id=c.author_user_id WHERE c.slug IN ($slugSql) AND u.username='$username'));
DELETE c FROM st_courses c JOIN st_users u ON u.id=c.author_user_id WHERE c.slug IN ($slugSql) AND u.username='$username';
DELETE ur FROM st_user_roles ur JOIN st_users u ON u.id=ur.user_id WHERE u.username='$username';
DELETE FROM st_users WHERE username='$username';
COMMIT;
"@
		Invoke-Db $cleanup | Out-Null
		$remaining = Invoke-Db "SELECT (SELECT COUNT(*) FROM st_courses WHERE slug IN ($slugSql))+(SELECT COUNT(*) FROM st_users WHERE username='$username');"
		if ($remaining -ne '0') { throw 'Admin course-list fixture cleanup is incomplete.' }
		if ($adminId -gt 0) {
			$courseList = ($courseIds | ForEach-Object { [long] $_ }) -join ','
			$courseAudit = if ($courseList) { " OR (entity_type='course' AND entity_id IN ($courseList))" } else { '' }
			if ((Invoke-Db "SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$adminId OR (entity_type='user' AND entity_id=$adminId)$courseAudit;") -ne '0') { throw 'Synthetic course/auth audit cleanup is incomplete.' }
		}
		Write-Output 'PASS exact synthetic admin, scoped role, 24 courses and fixture/auth audit events removed; unrelated data and login-failure records preserved.'
	}
}
