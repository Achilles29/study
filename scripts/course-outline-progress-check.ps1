param(
	[Parameter(Mandatory=$true)][string] $Php81Path,
	[Parameter(Mandatory=$true)][string] $MysqlPath,
	[Parameter(Mandatory=$true)][string] $BaseUrl,
	[string] $ChromePath = '',
	[switch] $CaptureScreenshots
)
$ErrorActionPreference = 'Stop'
$parsedBaseUrl = $null
if (-not [Uri]::TryCreate($BaseUrl, [UriKind]::Absolute, [ref]$parsedBaseUrl) -or $parsedBaseUrl.Scheme -ne 'http' -or -not $parsedBaseUrl.IsLoopback -or $parsedBaseUrl.UserInfo -or $parsedBaseUrl.Query -or $parsedBaseUrl.Fragment) { throw 'Course outline QA requires an HTTP loopback URL without credentials, query or fragment.' }
foreach ($binary in @($Php81Path,$MysqlPath)) { if (-not (Test-Path -LiteralPath $binary -PathType Leaf)) { throw 'A required local CLI executable is missing.' } }
if ($ChromePath -and -not (Test-Path -LiteralPath $ChromePath -PathType Leaf)) { throw 'The selected Chrome executable is missing.' }
if ($CaptureScreenshots -and -not $ChromePath) { throw 'Course outline screenshots require an explicit Chrome path.' }
$root=$BaseUrl.TrimEnd('/')+'/'
$suffix=[Guid]::NewGuid().ToString('N').Substring(0,16)
$prefix='qa-outline-'+$suffix
$password='Synthetic-Outline-Check-123!'
$usernames=@{}; $userIds=@{}; $courseIds=@{}; $lessonIds=@{}
foreach ($kind in @('adult','child','other','parent')) { $usernames[$kind]='qa_outline_'+$kind+'_'+$suffix }
$userSql=(@($usernames.Values) | ForEach-Object { "'$_'" }) -join ','
$courseKeys=@('main','other','paid','draft','archived','empty')
$courseSql=($courseKeys | ForEach-Object { "'$prefix-$_'" }) -join ','
$lessonKeys=@('new','reading','done','explicit-new','later-first','later-second','lesson-draft','lesson-archived','foreign','preview','paid')
$lessonSql=($lessonKeys | ForEach-Object { "'$prefix-$_-lesson'" }) -join ','
$mainKeys=@('new','reading','done','explicit-new','later-first','later-second')
$adultStates=@('Belum dimulai','Sedang dipelajari','Selesai','Belum dimulai','Belum dimulai','Belum dimulai')
$childStates=@('Selesai','Belum dimulai','Sedang dipelajari','Belum dimulai','Belum dimulai','Belum dimulai')
$parentStates=@('Belum dimulai','Belum dimulai','Selesai','Belum dimulai','Belum dimulai','Belum dimulai')
$newStates=@('Belum dimulai','Belum dimulai','Belum dimulai','Belum dimulai','Belum dimulai','Belum dimulai')
$unsafeTitle='Belajar '+[char]0x00E9+' '+[char]::ConvertFromUtf32(0x1F308)+' & <b>synthetic</b> "quoted"'
$longTitle='BelajarTanpaJeda' * 5
$longSection='Bagian berikutnya untuk mengulang langkah, melihat contoh, dan mencoba lagi secara mandiri'
$packageSlug=$prefix+'-package'
$owned=$false; $browserAttempted=$false; $qaStage='preflight'; $httpStage='none'; $httpStatus=0; $primaryFailure=$null
$sessions=New-Object 'System.Collections.Generic.List[object]'
$cleanupFailures=New-Object 'System.Collections.Generic.List[string]'
$baselineDir=Join-Path (Split-Path -Parent $PSScriptRoot) ('docs/visual-baseline/'+(Get-Date -Format 'yyyy-MM-dd')+'-course-outline-progress-'+$suffix)
if ($CaptureScreenshots -and (Test-Path -LiteralPath $baselineDir)) { throw 'Refusing to reuse a course outline screenshot directory.' }
$passwordHash=(& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim()
if ($LASTEXITCODE -ne 0 -or -not $passwordHash) { throw 'Could not generate the synthetic password hash.' }

function Invoke-Db([string] $Sql) {
	$start=New-Object Diagnostics.ProcessStartInfo
	$start.FileName=[IO.Path]::GetFullPath($MysqlPath)
	$start.Arguments='--no-defaults --host=127.0.0.1 --protocol=TCP --default-character-set=utf8mb4 -uroot -D db_study -N -B'
	$start.UseShellExecute=$false; $start.CreateNoWindow=$true
	$start.RedirectStandardInput=$true; $start.RedirectStandardOutput=$true; $start.RedirectStandardError=$true
	$start.StandardOutputEncoding=New-Object Text.UTF8Encoding($false); $start.StandardErrorEncoding=New-Object Text.UTF8Encoding($false)
	$process=New-Object Diagnostics.Process; $process.StartInfo=$start; $started=$false
	try {
		$started=$process.Start(); if (-not $started) { throw 'Database process did not start.' }
		$stdout=$process.StandardOutput.ReadToEndAsync(); $stderr=$process.StandardError.ReadToEndAsync()
		$bytes=[Text.Encoding]::UTF8.GetBytes($Sql+[Environment]::NewLine)
		$inputTask=$process.StandardInput.BaseStream.WriteAsync($bytes,0,$bytes.Length)
		if (-not $inputTask.Wait(30000)) { throw 'Database input timed out.' }
		$process.StandardInput.Close()
		if (-not $process.WaitForExit(30000)) { throw 'Database operation timed out.' }
		$output=$stdout.GetAwaiter().GetResult(); $stderr.GetAwaiter().GetResult() | Out-Null
		if ($process.ExitCode -ne 0) { throw 'Database operation failed.' }
		return $output.Trim()
	} catch { throw 'Course outline fixture database operation failed; SQL and data are not printed.' }
	finally { if ($started -and -not $process.HasExited) { $process.Kill(); $process.WaitForExit(5000) | Out-Null }; $process.Dispose() }
}
function Sql-Text([string] $Value) { $hex=[BitConverter]::ToString([Text.Encoding]::UTF8.GetBytes($Value)).Replace('-',''); return "CONVERT(0x$hex USING utf8mb4)" }
function Invoke-Http($Session,[string] $Route,[string] $Method='GET',[hashtable] $Fields=@{}) {
	$label=if ($Route -match '^(kursus/|catalog/course/)') { 'course' } elseif ($Route -eq 'masuk') { 'login' } elseif ($Route -eq 'keluar') { 'logout' } else { 'other' }
	$script:httpStage=$Method+'-'+$label; $script:httpStatus=0
	$request=[Net.HttpWebRequest]::Create($root+$Route); $request.Method=$Method; $request.CookieContainer=$Session.Cookies; $request.AllowAutoRedirect=$false; $request.Timeout=15000
	if ($Method -eq 'POST') {
		$body=($Fields.GetEnumerator() | ForEach-Object { [Net.WebUtility]::UrlEncode($_.Key)+'='+[Net.WebUtility]::UrlEncode([string]$_.Value) }) -join '&'
		$bytes=[Text.Encoding]::UTF8.GetBytes($body); $request.ContentType='application/x-www-form-urlencoded'; $request.ContentLength=$bytes.Length
		$stream=$request.GetRequestStream(); try { $stream.Write($bytes,0,$bytes.Length) } finally { $stream.Close() }
	}
	try { $response=$request.GetResponse() } catch { $response=$_.Exception.Response; if (-not $response -and $_.Exception.InnerException) { $response=$_.Exception.InnerException.Response }; if (-not $response) { throw 'Course HTTP transport failed; POST is not retried.' } }
	try {
		$script:httpStatus=[int]$response.StatusCode
		$result=@{ Status=[int]$response.StatusCode; Content=''; Location=$response.Headers['Location']; Cache=$response.Headers['Cache-Control']; Robots=$response.Headers['X-Robots-Tag'] }
		$reader=New-Object IO.StreamReader($response.GetResponseStream()); try { $result.Content=$reader.ReadToEnd() } finally { $reader.Close() }
		return $result
	} finally { $response.Close() }
}
function Csrf([string] $Html) { $match=[regex]::Match($Html,'name="study_csrf_token"\s+value="([^"]+)"'); if (-not $match.Success) { throw 'Synthetic CSRF field is missing.' }; return [Net.WebUtility]::HtmlDecode($match.Groups[1].Value) }
function New-Session([string] $Kind='') {
	$session=New-Object Microsoft.PowerShell.Commands.WebRequestSession
	if ($Kind) {
		$sessions.Add($session); $page=Invoke-Http $session 'masuk'
		if ($page.Status -ne 200) { throw 'Synthetic sign-in page is unavailable.' }
		$login=Invoke-Http $session 'masuk' 'POST' @{ study_csrf_token=(Csrf $page.Content); identity=$usernames[$Kind]; password=$password }
		if ($login.Status -notin @(302,303) -or $login.Location -notmatch '/beranda$') { throw 'Synthetic account sign-in failed.' }
	}
	return $session
}
function Data-Digest {
	return Invoke-Db "SELECT 'progress',id,SHA2(CONCAT_WS('|',learner_user_id,lesson_id,status,last_position,COALESCE(started_at,''),COALESCE(completed_at,''),updated_at),256) FROM st_lesson_progress ORDER BY id; SELECT 'enrollment',id,SHA2(CONCAT_WS('|',learner_user_id,course_id,source_type,COALESCE(package_id,0),COALESCE(entitlement_id,0),status,enrolled_at,updated_at),256) FROM st_course_enrollments ORDER BY id; SELECT 'entitlement',id,SHA2(CONCAT_WS('|',user_id,package_id,COALESCE(payment_order_id,0),status,starts_at,COALESCE(ends_at,''),created_at),256) FROM st_entitlements ORDER BY id;"
}
function Audit-Digest([switch] $ExcludeLogin) {
	$where=if ($ExcludeLogin) { "WHERE NOT (COALESCE(actor_user_id,0) IN (SELECT id FROM st_users WHERE username IN ($userSql)) AND entity_type='user' AND actor_user_id=entity_id AND event_type='auth.login' AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.method')),'')='password')" } else { '' }
	return Invoke-Db "SELECT id,SHA2(CONCAT_WS('|',COALESCE(actor_user_id,0),event_type,entity_type,COALESCE(entity_id,0),COALESCE(correlation_id,''),COALESCE(details_json,''),created_at),256) FROM st_audit_events $where ORDER BY id;"
}
function Login-Count([string] $Kind) { return [int](Invoke-Db "SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$($userIds[$Kind]) AND entity_type='user' AND entity_id=$($userIds[$Kind]) AND event_type='auth.login' AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.method')),'')='password';") }
function Get-Course($Session,[string] $Route,[bool] $Authenticated=$true,[string] $Method='GET',[int] $ExpectedStatus=200) {
	$beforeData=Data-Digest; $beforeAudit=Audit-Digest; $response=Invoke-Http $Session $Route $Method
	if ($response.Status -ne $ExpectedStatus -or $response.Content -match 'A PHP Error was encountered|Undefined array key|Severity: Warning|Fatal error:') { throw 'Course page returned an unexpected status or PHP diagnostic.' }
	if ($Authenticated -and $ExpectedStatus -eq 200 -and ($response.Cache -notmatch '(?:^|,)\s*private\s*(?:,|$)' -or $response.Cache -notmatch '(?:^|,)\s*no-store\s*(?:,|$)' -or $response.Robots -notmatch '(?:^|,)\s*noindex\s*(?:,|$)' -or $response.Robots -notmatch '(?:^|,)\s*nofollow\s*(?:,|$)')) { throw 'Personalized course GET/HEAD must carry private/no-store/noindex/nofollow headers.' }
	if ((Data-Digest) -cne $beforeData -or (Audit-Digest) -cne $beforeAudit) { throw 'Course GET/HEAD changed progress, enrollments, entitlements or audit.' }
	if ($Method -eq 'HEAD' -and $response.Content) { throw 'HEAD must not return a body.' }
	return $response.Content
}
function Assert-Outline([string] $Html,[string[]] $Keys,[string[]] $States=@(),[string[]] $Locked=@(),[bool] $Guest=$false) {
	$rows=@([regex]::Matches($Html,'(?s)<li\b(?=[^>]*\bdata-lesson-id="([1-9][0-9]*)")[^>]*>.*?</li>'))
	$actual=@($rows | ForEach-Object { $_.Groups[1].Value }); $expected=@($Keys | ForEach-Object { [string]$lessonIds[$_] })
	if (($actual -join '|') -cne ($expected -join '|')) { throw 'Course outline does not match exact published lesson/section ordering.' }
	if (-not $Guest -and $States.Count -ne $Keys.Count) { throw 'Expected learner-state coverage is incomplete.' }
	for ($index=0; $index -lt $Keys.Count; $index++) {
		$row=$rows[$index].Value; $key=$Keys[$index]; $text=[Net.WebUtility]::HtmlDecode(([regex]::Replace($row,'<[^>]+>',' ')))
		$badges=@([regex]::Matches($row,'\bdata-lesson-progress="([^"]+)"'))
		if ($Guest) { if ($badges.Count -or $text -match 'Belum dimulai|Sedang dipelajari|Selesai|Baca ulang') { throw 'Guest outline exposed personal progress labels or attributes.' } }
		else {
			$stateCode=switch ($States[$index]) { 'Sedang dipelajari' { 'in_progress' }; 'Selesai' { 'completed' }; default { 'not_started' } }
			if (-not $text.Contains($States[$index]) -or $badges.Count -ne 1 -or $badges[0].Groups[1].Value -cne $stateCode) { throw 'Lesson state does not match the signed-in account saved progress.' }
		}
		$links=@([regex]::Matches($row,'href="([^"]+)"') | ForEach-Object { [Net.WebUtility]::HtmlDecode($_.Groups[1].Value) })
		if ($key -in $Locked) { if ($links.Count -or -not $text.Contains('Terkunci')) { throw 'Locked lesson gained a reading link or lost its lock label.' }; continue }
		$url=$root+'materi/'+$prefix+'-'+$key+'-lesson'
		if (-not $Guest -and $States[$index] -eq 'Sedang dipelajari') { $url+='?lanjut=1#reading-material' }
		if ($links.Count -ne 1 -or $links[0] -cne $url) { throw 'Outline action does not use the expected native reading/resume URL.' }
		if (-not $Guest) {
			$label=switch ($States[$index]) { 'Sedang dipelajari' { 'Lanjutkan' }; 'Selesai' { 'Baca ulang' }; default { 'Mulai belajar' } }
			if (-not $text.Contains($label) -or $row -notmatch 'class="visually-hidden"') { throw 'Personalized action label or lesson-specific accessible name is missing.' }
		}
	}
}
function Invoke-CourseBrowser([string] $Kind,[string] $CourseKey,[string] $Name) {
	if (-not $ChromePath) { return }
	$beforeData=Data-Digest; $beforeAudit=Audit-Digest -ExcludeLogin; $beforeLogin=if ($Kind) { Login-Count $Kind } else { 0 }
	$oldIdentity=[Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY','Process'); $oldPassword=[Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD','Process')
	try {
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY',$(if ($Kind) { $usernames[$Kind] } else { $null }),'Process')
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD',$(if ($Kind) { $password } else { $null }),'Process')
		$arguments=@((Join-Path $PSScriptRoot 'browser-viewport-check.mjs'),'--base-url',$root,'--chrome',$ChromePath,'--extra-path',('kursus/'+$prefix+'-'+$CourseKey),'--extra-name',$Name,'--only-extra-route','--exercise-navigation')
		if ($CaptureScreenshots) { $arguments+=@('--capture-screenshots','--screenshot-dir',(Join-Path $baselineDir $Name)) }
		$script:browserAttempted=$true; & node @arguments
		if ($LASTEXITCODE -ne 0) { throw 'Course outline browser regression failed.' }
	} finally { [Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY',$oldIdentity,'Process'); [Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD',$oldPassword,'Process') }
	if ((Data-Digest) -cne $beforeData -or (Audit-Digest -ExcludeLogin) -cne $beforeAudit) { throw 'Browser reads changed course-learning state or non-login audit.' }
	if ($Kind -and (Login-Count $Kind) -ne ($beforeLogin+1)) { throw 'Browser did not produce exactly one expected fixture login.' }
}

try {
	if ((Invoke-Db "SELECT (SELECT COUNT(*) FROM st_users WHERE username IN ($userSql))+(SELECT COUNT(*) FROM st_courses WHERE slug IN ($courseSql))+(SELECT COUNT(*) FROM st_lessons WHERE slug IN ($lessonSql))+(SELECT COUNT(*) FROM st_packages WHERE slug='$packageSlug');") -ne '0') { throw 'Refusing to reuse course outline fixture identities.' }
	$owned=$true; $sql=New-Object 'System.Collections.Generic.List[string]'; $sql.Add('START TRANSACTION;')
	foreach ($kind in @('adult','child','other','parent')) {
		$accountKind=if ($kind -eq 'other') { 'adult' } else { $kind }; $stage=if ($kind -eq 'child') { 'primary' } elseif ($kind -eq 'parent') { 'unset' } else { 'adult' }
		$sql.Add("INSERT INTO st_users(username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES('$($usernames[$kind])','$passwordHash','Synthetic outline $kind','$accountKind','$stage','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()); SET @outline_$kind=LAST_INSERT_ID();")
	}
	$sql.Add("INSERT INTO st_family_links(parent_user_id,child_user_id,status,created_at,updated_at) VALUES(@outline_parent,@outline_child,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP());")
	foreach ($key in $courseKeys) {
		$status=if ($key -in @('draft','archived')) { $key } else { 'published' }; $free=if ($key -eq 'paid') { 0 } else { 1 }
		$title=if ($key -eq 'main') { $unsafeTitle } else { 'Synthetic outline '+$key }
		$sql.Add("INSERT INTO st_courses(author_user_id,updated_by_user_id,title,slug,summary,learning_objectives,target_group,status,content_version,rights_status,source_note,is_free,published_at,created_at,updated_at) VALUES(@outline_adult,@outline_adult,$(Sql-Text $title),'$prefix-$key','Synthetic outline summary','Synthetic learning objective','all_ages','$status',1,'owned','Synthetic source',$free,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP()); SET @outline_course_$key=LAST_INSERT_ID();")
		if ($key -ne 'empty') { $sql.Add("INSERT INTO st_course_sections(course_id,title,sort_order,created_at,updated_at) VALUES(@outline_course_$key,'Synthetic section A',1,UTC_TIMESTAMP(),UTC_TIMESTAMP()); SET @outline_section_$key=LAST_INSERT_ID();") }
	}
	$sql.Add("INSERT INTO st_course_sections(course_id,title,sort_order,created_at,updated_at) VALUES(@outline_course_main,$(Sql-Text $longSection),1,UTC_TIMESTAMP(),UTC_TIMESTAMP()); SET @outline_section_later=LAST_INSERT_ID();")
	foreach ($key in $lessonKeys) {
		$section=if ($key -in @('later-first','later-second')) { '@outline_section_later' } elseif ($key -eq 'foreign') { '@outline_section_other' } elseif ($key -in @('preview','paid')) { '@outline_section_paid' } else { '@outline_section_main' }
		$status=if ($key -eq 'lesson-draft') { 'draft' } elseif ($key -eq 'lesson-archived') { 'archived' } else { 'published' }; $preview=if ($key -eq 'preview') { 1 } else { 0 }
		$title=if ($key -eq 'reading') { $unsafeTitle } elseif ($key -eq 'later-second') { $longTitle } else { 'Synthetic lesson '+$key }
		$sql.Add("INSERT INTO st_lessons(section_id,title,slug,body_text,estimated_minutes,sort_order,status,content_version,is_preview,source_note,rights_status,created_at,updated_at) VALUES($section,$(Sql-Text $title),'$prefix-$key-lesson','Synthetic text, not production learning material.',5,1,'$status',1,$preview,'Synthetic source','owned',UTC_TIMESTAMP(),UTC_TIMESTAMP());")
	}
	$progressCases=@(@('adult','reading','in_progress',3500),@('adult','done','completed',10000),@('adult','explicit-new','not_started',0),@('adult','foreign','completed',10000),@('adult','preview','in_progress',2200),@('adult','paid','completed',10000),@('adult','lesson-draft','completed',10000),@('adult','lesson-archived','in_progress',2000),@('child','new','completed',10000),@('child','done','in_progress',4800),@('parent','done','completed',10000))
	foreach ($entry in $progressCases) {
		$completed=if ($entry[2] -eq 'completed') { "'2026-01-02 00:00:00'" } else { 'NULL' }
		$sql.Add("INSERT INTO st_lesson_progress(learner_user_id,lesson_id,status,last_position,started_at,completed_at,updated_at) SELECT @outline_$($entry[0]),id,'$($entry[2])',$($entry[3]),'2026-01-01 00:00:00',$completed,'2026-01-02 00:00:00' FROM st_lessons WHERE slug='$prefix-$($entry[1])-lesson';")
	}
	$sql.Add("INSERT INTO st_packages(title,slug,summary,credit_price,access_days,status,created_at,updated_at) VALUES('Synthetic outline package','$packageSlug','QA only; no checkout',0,7,'published',UTC_TIMESTAMP(),UTC_TIMESTAMP()); SET @outline_package=LAST_INSERT_ID();")
	$sql.Add("INSERT INTO st_entitlements(user_id,package_id,payment_order_id,status,starts_at,ends_at,created_at) VALUES(@outline_adult,@outline_package,NULL,'active',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY),UTC_TIMESTAMP()); SET @outline_entitlement=LAST_INSERT_ID();")
	$sql.Add("INSERT INTO st_course_enrollments(learner_user_id,course_id,source_type,package_id,entitlement_id,status,enrolled_at,updated_at) VALUES(@outline_adult,@outline_course_paid,'package',@outline_package,@outline_entitlement,'revoked',UTC_TIMESTAMP(),UTC_TIMESTAMP()); COMMIT;")
	$qaStage='fixture-insert'; Invoke-Db ($sql -join "`n") | Out-Null
	foreach ($kind in @('adult','child','other','parent')) { $value=Invoke-Db "SELECT id FROM st_users WHERE username='$($usernames[$kind])';"; if ($value -notmatch '^[1-9][0-9]*$') { throw 'Fixture user identity is incomplete.' }; $userIds[$kind]=[long]$value }
	foreach ($key in $courseKeys) { $value=Invoke-Db "SELECT id FROM st_courses WHERE slug='$prefix-$key' AND author_user_id=$($userIds.adult);"; if ($value -notmatch '^[1-9][0-9]*$') { throw 'Fixture course identity is incomplete.' }; $courseIds[$key]=[long]$value }
	foreach ($key in $lessonKeys) { $value=Invoke-Db "SELECT id FROM st_lessons WHERE slug='$prefix-$key-lesson';"; if ($value -notmatch '^[1-9][0-9]*$') { throw 'Fixture lesson identity is incomplete.' }; $lessonIds[$key]=[long]$value }
	$qaStage='fixture-login'; $adult=New-Session 'adult'; $child=New-Session 'child'; $other=New-Session 'other'; $parent=New-Session 'parent'; $guest=New-Session
	$mainRoute='kursus/'+$prefix+'-main'; $paidRoute='kursus/'+$prefix+'-paid'
	$qaStage='own-main-states'; $main=Get-Course $adult $mainRoute; Assert-Outline $main $mainKeys $adultStates
	if ($main.Contains('<b>synthetic</b>') -or -not $main.Contains('&lt;b&gt;synthetic&lt;/b&gt;') -or -not ([Net.WebUtility]::HtmlDecode($main)).Contains($unsafeTitle) -or $main.Contains('&amp;lt;b&amp;gt;')) { throw 'Course/lesson title Unicode or exact-once escaping failed.' }
	foreach ($key in @('lesson-draft','lesson-archived','foreign')) { if ($main.Contains('data-lesson-id="'+$lessonIds[$key]+'"')) { throw 'Unpublished or foreign-course lesson leaked into the outline.' } }
	$qaStage='identity-isolation'; Assert-Outline (Get-Course $child $mainRoute) $mainKeys $childStates; Assert-Outline (Get-Course $parent $mainRoute) $mainKeys $parentStates; Assert-Outline (Get-Course $other $mainRoute) $mainKeys $newStates
	foreach ($query in @(('user_id='+$userIds.child),('learner_user_id='+$userIds.child),('anak='+$userIds.child),('learner_user_id%5B%5D='+$userIds.child))) { Assert-Outline (Get-Course $adult ($mainRoute+'?'+$query)) $mainKeys $adultStates }
	Assert-Outline (Get-Course $parent ($mainRoute+'?anak='+$userIds.child)) $mainKeys $parentStates
	Assert-Outline (Get-Course $adult ('catalog/course/'+$prefix+'-main')) $mainKeys $adultStates
	Get-Course $adult $mainRoute $true 'HEAD' | Out-Null
	$qaStage='guest-and-empty'; Assert-Outline (Get-Course $guest $mainRoute $false) $mainKeys @() @() $true
	Assert-Outline (Get-Course $guest $paidRoute $false) @('preview','paid') @() @('paid') $true
	foreach ($reader in @($other,$child,$parent)) { Assert-Outline (Get-Course $reader $paidRoute) @('preview','paid') @('Belum dimulai','Belum dimulai') @('paid') }
	Assert-Outline (Get-Course $adult ('kursus/'+$prefix+'-other')) @('foreign') @('Selesai')
	$empty=Get-Course $adult ('kursus/'+$prefix+'-empty'); Assert-Outline $empty @() @()
	if ($empty -notmatch 'Pelajaran untuk kursus ini sedang disiapkan' -or $empty -match 'belum dapat dimuat') { throw 'A genuine empty course must not be reported as a progress query failure.' }
	foreach ($key in @('draft','archived')) { Get-Course $adult ('kursus/'+$prefix+'-'+$key) $true 'GET' 404 | Out-Null; Get-Course $guest ('kursus/'+$prefix+'-'+$key) $false 'GET' 404 | Out-Null }
	$qaStage='browser-main'; Invoke-CourseBrowser 'adult' 'main' 'outline-adult-main'; Invoke-CourseBrowser 'child' 'main' 'outline-child-main'; Invoke-CourseBrowser 'parent' 'main' 'outline-parent-main'; Invoke-CourseBrowser '' 'paid' 'outline-guest-paid'
	$qaStage='paid-locked-histori'; Assert-Outline (Get-Course $adult $paidRoute) @('preview','paid') @('Sedang dipelajari','Selesai') @('paid')
	Invoke-CourseBrowser 'adult' 'paid' 'outline-adult-locked'
	$enrollmentScope="learner_user_id=$($userIds.adult) AND course_id=$($courseIds.paid)"
	$entitlementScope="user_id=$($userIds.adult) AND package_id=(SELECT id FROM st_packages WHERE slug='$packageSlug')"
	foreach ($source in @('manual','free','package')) {
		$qaStage='access-'+$source; Invoke-Db "UPDATE st_course_enrollments SET source_type='$source',status='active' WHERE $enrollmentScope;" | Out-Null
		Assert-Outline (Get-Course $adult $paidRoute) @('preview','paid') @('Sedang dipelajari','Selesai')
	}
	$qaStage='package-open-ended'; Invoke-Db "UPDATE st_entitlements SET ends_at=NULL WHERE $entitlementScope;" | Out-Null
	Assert-Outline (Get-Course $adult $paidRoute) @('preview','paid') @('Sedang dipelajari','Selesai')
	$negativeStates=@(@{Name='expired';Status='expired';Start='DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY)';End='DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)'},@{Name='ended';Status='active';Start='DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY)';End='DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)'},@{Name='future';Status='active';Start='DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY)';End='DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY)'},@{Name='revoked';Status='revoked';Start='DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY)';End='DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY)'})
	foreach ($state in $negativeStates) { $qaStage='package-'+$state.Name; Invoke-Db "UPDATE st_entitlements SET status='$($state.Status)',starts_at=$($state.Start),ends_at=$($state.End) WHERE $entitlementScope;" | Out-Null; Assert-Outline (Get-Course $adult $paidRoute) @('preview','paid') @('Sedang dipelajari','Selesai') @('paid') }
	$qaStage='enrollment-revocation'; Invoke-Db "UPDATE st_entitlements SET status='active',starts_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),ends_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE $entitlementScope; UPDATE st_course_enrollments SET status='revoked' WHERE $enrollmentScope;" | Out-Null
	Assert-Outline (Get-Course $adult $paidRoute) @('preview','paid') @('Sedang dipelajari','Selesai') @('paid')
	Get-Course $adult $paidRoute $true 'HEAD' | Out-Null
	Write-Output 'PASS course outline own states, stable section/lesson ordering, guest/child/parent isolation, escaped titles, native actions, private GET/HEAD and free/preview/enrollment/package/revoked access without learning-data writes.'
	if ($CaptureScreenshots) { Write-Output ('PASS new synthetic course outline screenshots: '+$baselineDir) }
} catch { $primaryFailure="Course outline QA failed at stage=$qaStage; http=$httpStage; status=$httpStatus; script-line=$([int]$_.InvocationInfo.ScriptLineNumber)." }
finally {
	foreach ($session in $sessions) {
		try { $page=Invoke-Http $session 'masuk'; if ($page.Status -in @(302,303,307)) { $page=Invoke-Http $session 'beranda' }; if ($page.Status -ne 200) { throw 'Logout token page unavailable.' }; $logout=Invoke-Http $session 'keluar' 'POST' @{ study_csrf_token=(Csrf $page.Content) }; if ($logout.Status -notin @(302,303)) { throw 'Fixture logout failed.' } }
		catch { $cleanupFailures.Add("Exact HTTP session cleanup failed; stage=$httpStage; status=$httpStatus; script-line=$([int]$_.InvocationInfo.ScriptLineNumber).") }
	}
	if ($owned) {
		try {
			# Exact reserved identities, lesson/course slugs and author ownership only;
			# no wildcard deletion, schema change, or login-throttle cleanup.
			Invoke-Db @"
START TRANSACTION;
DELETE FROM st_audit_events WHERE actor_user_id IN (SELECT id FROM st_users WHERE username IN ($userSql)) OR (entity_type='user' AND entity_id IN (SELECT id FROM st_users WHERE username IN ($userSql)));
DELETE p FROM st_lesson_progress p JOIN st_users u ON u.id=p.learner_user_id WHERE u.username IN ($userSql);
DELETE ce FROM st_course_enrollments ce JOIN st_users u ON u.id=ce.learner_user_id WHERE u.username IN ($userSql);
DELETE e FROM st_entitlements e JOIN st_users u ON u.id=e.user_id JOIN st_packages p ON p.id=e.package_id WHERE u.username IN ($userSql) AND p.slug='$packageSlug';
DELETE l FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id JOIN st_users u ON u.id=c.author_user_id WHERE l.slug IN ($lessonSql) AND c.slug IN ($courseSql) AND u.username='$($usernames.adult)';
DELETE s FROM st_course_sections s JOIN st_courses c ON c.id=s.course_id JOIN st_users u ON u.id=c.author_user_id WHERE c.slug IN ($courseSql) AND u.username='$($usernames.adult)';
DELETE c FROM st_courses c JOIN st_users u ON u.id=c.author_user_id WHERE c.slug IN ($courseSql) AND u.username='$($usernames.adult)';
DELETE FROM st_packages WHERE slug='$packageSlug';
DELETE f FROM st_family_links f JOIN st_users p ON p.id=f.parent_user_id JOIN st_users c ON c.id=f.child_user_id WHERE p.username='$($usernames.parent)' AND c.username='$($usernames.child)';
DELETE FROM st_users WHERE username IN ($userSql);
COMMIT;
"@ | Out-Null
			if ((Invoke-Db "SELECT (SELECT COUNT(*) FROM st_users WHERE username IN ($userSql))+(SELECT COUNT(*) FROM st_courses WHERE slug IN ($courseSql))+(SELECT COUNT(*) FROM st_lessons WHERE slug IN ($lessonSql))+(SELECT COUNT(*) FROM st_packages WHERE slug='$packageSlug');") -ne '0') { throw 'Exact fixture cleanup remains incomplete.' }
			if ($userIds.Count -eq 4) {
				$ids=(@($userIds.Values) | ForEach-Object { [long]$_ }) -join ','
				if ((Invoke-Db "SELECT (SELECT COUNT(*) FROM st_lesson_progress WHERE learner_user_id IN ($ids))+(SELECT COUNT(*) FROM st_course_enrollments WHERE learner_user_id IN ($ids))+(SELECT COUNT(*) FROM st_entitlements WHERE user_id IN ($ids))+(SELECT COUNT(*) FROM st_family_links WHERE parent_user_id IN ($ids) OR child_user_id IN ($ids))+(SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id IN ($ids) OR (entity_type='user' AND entity_id IN ($ids))); ") -ne '0') { throw 'Fixture dependent rows remain.' }
			}
			Write-Output 'PASS exact synthetic course outline users/content/progress/access/family/audit removed; unrelated data and login-failure rows preserved.'
		} catch { $cleanupFailures.Add("Exact database cleanup could not be confirmed; script-line=$([int]$_.InvocationInfo.ScriptLineNumber). No broad cleanup was attempted.") }
	}
	if ($browserAttempted) { Write-Output 'NOTE browser harness owns its temporary profiles; server-side browser session files are not scanned/deleted. Removed fixture accounts invalidate remaining sessions on their next authenticated request.' }
}
if ($primaryFailure) { foreach ($failure in $cleanupFailures) { Write-Output ('ADDITIONAL CLEANUP FAILURE: '+$failure) }; throw $primaryFailure }
if ($cleanupFailures.Count) { throw ($cleanupFailures -join ' ') }
