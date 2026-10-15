param(
	[Parameter(Mandatory=$true)][string]$Php81Path,
	[Parameter(Mandatory=$true)][string]$MysqlPath,
	[Parameter(Mandatory=$true)][string]$BaseUrl,
	[string]$ChromePath='',
	[switch]$CaptureScreenshots
)
$ErrorActionPreference='Stop'
$parsed=[Uri]$BaseUrl
if ($parsed.Scheme -ne 'http' -or -not $parsed.IsLoopback -or $parsed.UserInfo -or $parsed.Query -or $parsed.Fragment) { throw 'Structure QA accepts only an HTTP loopback URL.' }
$root=$BaseUrl.TrimEnd('/')+'/'
if ($CaptureScreenshots -and -not $ChromePath) { throw 'Captures require ChromePath.' }
$suffix=[Guid]::NewGuid().ToString('N').Substring(0,12)
$prefix='qa_structure_'+$suffix
$slug='qa-structure-'+$suffix
$names=(@('owner','other','admin','member') | ForEach-Object { "'${prefix}_$_'" }) -join ','
$password='Synthetic-Structure-Check-123!'
$hash=(& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim()
if ($LASTEXITCODE -ne 0 -or $hash -notmatch '^\$2[ayb]\$') { throw 'Synthetic credential setup failed.' }
function Db([string]$Sql) {
	$output=& $MysqlPath -uroot -D db_study -N -B -e $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw 'Structure QA SQL operation failed; inspect locally without exposing credentials.' }
	return ($output -join "`n").Trim()
}
function Request($Web,[string]$Route,[string]$Method='GET',[hashtable]$Fields=@{}) {
	$request=[Net.HttpWebRequest]::Create($root+$Route)
	$request.Method=$Method; $request.CookieContainer=$Web.Cookies; $request.AllowAutoRedirect=$false; $request.Timeout=15000
	if ($Method -eq 'POST') {
		if (-not $Fields.ContainsKey('study_csrf_token')) { $Fields['study_csrf_token']=$Web.Cookies.GetCookies([Uri]$root)['study_csrf_cookie'].Value }
		$body=($Fields.GetEnumerator() | ForEach-Object { [Net.WebUtility]::UrlEncode($_.Key)+'='+[Net.WebUtility]::UrlEncode([string]$_.Value) }) -join '&'
		$bytes=[Text.Encoding]::UTF8.GetBytes($body); $request.ContentType='application/x-www-form-urlencoded'; $request.ContentLength=$bytes.Length
		$stream=$request.GetRequestStream(); try { $stream.Write($bytes,0,$bytes.Length) } finally { $stream.Close() }
	}
	try { $response=$request.GetResponse() } catch { $response=$_.Exception.Response; if (-not $response -and $_.Exception.InnerException) { $response=$_.Exception.InnerException.Response }; if (-not $response) { throw "HTTP transport failed at $Method $Route; no automatic POST retry. $($_.Exception.Message)" } }
	try { $reader=New-Object IO.StreamReader($response.GetResponseStream()); try { $content=$reader.ReadToEnd() } finally { $reader.Close() }; return @{Status=[int]$response.StatusCode; Content=$content; Allow=$response.Headers['Allow']; Location=$response.Headers['Location']} } finally { $response.Close() }
}
function Expect($Actual,$Expected,[string]$Message) { if ($Actual -ne $Expected) { throw "$Message (expected $Expected, received $Actual)" } }
function Revision { return Db "SELECT content_version FROM st_courses WHERE id=$courseId;" }
function Audit-Count { return Db "SELECT COUNT(*) FROM st_audit_events WHERE entity_type='course' AND entity_id=$courseId;" }
function Payload([string]$Action,[string]$Section,[string]$Title='Bagian QA',[string]$Order='1',[string]$Lesson='') { return @{action=$Action; revision=(Revision); section_id=$Section; title=$Title; sort_order=$Order; lesson_id=$Lesson} }

$previousErrorResponseLength=[Net.HttpWebRequest]::DefaultMaximumErrorResponseLength
try {
	# A capacity-error page can exceed the .NET default 64 KiB because it lists
	# 100 section choices. Bound the synthetic HTTP error body to 1 MiB and restore.
	[Net.HttpWebRequest]::DefaultMaximumErrorResponseLength=1024
	foreach ($kind in @('owner','other','admin','member')) {
		Db "INSERT INTO st_users(username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES('${prefix}_$kind','$hash','QA structure','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());" | Out-Null
		if ($kind -ne 'member') { $role=if($kind -eq 'admin'){'system_admin'}else{'content_editor'}; Db "INSERT INTO st_user_roles(user_id,role_id,granted_by_user_id,created_at) SELECT u.id,r.id,u.id,UTC_TIMESTAMP() FROM st_users u JOIN st_roles r ON r.role_code='$role' WHERE u.username='${prefix}_$kind';" | Out-Null }
	}
	foreach ($pair in @(@('main','owner'),@('other','other'))) {
		$courseSlug=$slug+'-'+$pair[0]; $ownerName=$prefix+'_'+$pair[1]
		Db "INSERT INTO st_courses(author_user_id,updated_by_user_id,title,slug,summary,learning_objectives,target_group,status,content_version,rights_status,source_note,is_free,created_at,updated_at) SELECT id,id,'QA structure course','$courseSlug','Synthetic structure fixture','Learn ordering','adult','draft',1,'owned','Synthetic QA',1,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_users WHERE username='$ownerName'; INSERT INTO st_course_sections(course_id,title,sort_order,created_at,updated_at) SELECT id,'Bagian awal',1,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_courses WHERE slug='$courseSlug';" | Out-Null
	}
	$courseId=Db "SELECT id FROM st_courses WHERE slug='$slug-main';"
	$sectionId=Db "SELECT id FROM st_course_sections WHERE course_id=$courseId;"
	$foreignSection=Db "SELECT s.id FROM st_course_sections s JOIN st_courses c ON c.id=s.course_id WHERE c.slug='$slug-other';"
	foreach ($number in 1..11) { Db "INSERT INTO st_lessons(section_id,title,slug,body_text,estimated_minutes,sort_order,status,content_version,is_preview,rights_status,source_note,created_at,updated_at) VALUES($sectionId,'QA Lesson $number','$slug-lesson-$number','Synthetic lesson body for structure QA.',5,$number,'draft',1,0,'owned','Synthetic QA',UTC_TIMESTAMP(),UTC_TIMESTAMP());" | Out-Null }
	Db "INSERT INTO st_lessons(section_id,title,slug,body_text,estimated_minutes,sort_order,status,content_version,is_preview,rights_status,source_note,created_at,updated_at) VALUES($foreignSection,'Other QA Lesson','$slug-foreign','Synthetic foreign lesson body.',5,1,'draft',1,0,'owned','Synthetic QA',UTC_TIMESTAMP(),UTC_TIMESTAMP());" | Out-Null
	$lessonId=Db "SELECT id FROM st_lessons WHERE slug='$slug-lesson-1';"
	$foreignLesson=Db "SELECT id FROM st_lessons WHERE slug='$slug-foreign';"
	$webs=@{}
	foreach ($kind in @('owner','other','admin','member')) { $web=New-Object Microsoft.PowerShell.Commands.WebRequestSession; Request $web 'masuk' | Out-Null; $login=Request $web 'masuk' 'POST' @{identity="${prefix}_$kind"; password=$password}; Expect $login.Status 303 'Fixture login'; $webs[$kind]=$web }
	$route="kelola/kursus/$courseId/susunan"
	$page=Request $webs.owner $route
	Expect $page.Status 200 'Owner structure page'
	Expect ([regex]::Matches($page.Content,'name="lesson_id"').Count) 10 'Server lesson pagination first page'
	Expect ([regex]::Matches((Request $webs.owner ($route+'?halaman=99999')).Content,'name="lesson_id"').Count) 1 'Pagination last-page clamp'
	Expect (Request $webs.admin $route).Status 200 'Admin can manage other owner draft'
	foreach ($kind in @('other','member')) { Expect (Request $webs[$kind] $route).Status 403 'Foreign editor/member denied'; Expect (Request $webs[$kind] "course_structure/index/$courseId" 'POST' (Payload 'create_section' '' 'Denied section')).Status 403 'Direct POST role/ownership denied' }
	$badMethod=Request $webs.owner $route 'DELETE'; Expect $badMethod.Status 405 'Method denied'; Expect $badMethod.Allow 'GET, HEAD, POST' 'Allow header'
	Expect (Request $webs.owner $route 'POST' @{action='create_section'; revision=(Revision); title='Invalid CSRF'; sort_order='1'; study_csrf_token='invalid'}).Status 403 'CSRF denied'
	$beforeRevision=Revision; $beforeAudit=Audit-Count
	$replay=Payload 'create_section' '' 'Bagian kedua' '1'
	Expect (Request $webs.owner $route 'POST' $replay).Status 303 'Create section'
	Expect (Revision) ([string]([int]$beforeRevision+1)) 'Course version after create'
	$newSection=Db "SELECT MAX(id) FROM st_course_sections WHERE course_id=$courseId;"
	$replay.Remove('study_csrf_token'); $conflict=Request $webs.owner $route 'POST' $replay
	Expect $conflict.Status 409 'Stale replay conflict'; if ($conflict.Content -notmatch '<fieldset disabled>') { throw 'Conflict must disable stale forms' }
	Expect (Audit-Count) ([string]([int]$beforeAudit+1)) 'Replay adds no audit'
	$noChangeVersion=Revision; $noChangeAudit=Audit-Count
	Expect (Request $webs.owner $route 'POST' (Payload 'update_section' $newSection 'Bagian kedua' '1')).Status 303 'No-op section update'
	Expect (Revision) $noChangeVersion 'No-op keeps revision'; Expect (Audit-Count) $noChangeAudit 'No-op keeps audit'
	Expect (Request $webs.admin $route 'POST' (Payload 'update_section' $newSection '<b>Bagian kedua</b>' '1')).Status 303 'Admin section update'
	$escaped=Request $webs.owner ($route+'?bagian='+$newSection)
	if ($escaped.Content -notmatch '&lt;b&gt;Bagian kedua&lt;/b&gt;' -or $escaped.Content -match '<b>Bagian kedua</b>') { throw 'Section title must be escaped' }
	$contextRoute=$route+'?halaman=99999&bagian='+$newSection
	$context=Request $webs.owner $contextRoute
	$decoded=[Net.WebUtility]::HtmlDecode($context.Content)
	$expectedContext=$root+$route+'?halaman=2&bagian='+$newSection
	if (-not $decoded.Contains('action="'+$expectedContext+'"') -or -not $decoded.Contains('name="halaman" value="2"') -or -not $decoded.Contains('href="'+$root+$route+'?halaman=1&bagian='+$newSection+'"')) { throw 'Forms, selector and pagination must retain validated context' }
	$contextVersion=Revision; $contextAudit=Audit-Count
	$savedContext=Request $webs.owner $contextRoute 'POST' (Payload 'update_section' $newSection '<b>Bagian kedua</b>' '1')
	Expect $savedContext.Status 303 'Context no-op update'; Expect $savedContext.Location $expectedContext 'Local canonical update redirect'
	$lastLesson=Db "SELECT id FROM st_lessons WHERE slug='$slug-lesson-11';"
	$moveContext=Request $webs.owner $contextRoute 'POST' (Payload 'move_lesson' $sectionId '' '11' $lastLesson)
	Expect $moveContext.Location $expectedContext 'No-op lesson retains independent selected section and page'
	$unsafeContext=$route+'?halaman[]=2&bagian='+$foreignSection+'&next=https%3A%2F%2Fexample.invalid%2F'
	$safeContext=Request $webs.owner $unsafeContext 'POST' (Payload 'move_lesson' $sectionId '' '11' $lastLesson)
	Expect $safeContext.Location ($root+$route+'?halaman=1&bagian='+$sectionId) 'Invalid context normalized without external redirect'
	Expect (Revision) $contextVersion 'Context-only requests keep revision'; Expect (Audit-Count) $contextAudit 'Context-only requests keep audit'
	Write-Output 'PASS selector/forms/pagination/no-op redirects preserve bounded local page/section context; foreign section and array page normalize safely.'
	$unicodeTitle='Bagian '+[char]0x00E9+' '+[char]0x4E00
	Expect (Request $webs.owner $route 'POST' (Payload 'update_section' $newSection $unicodeTitle '1')).Status 303 'Unicode section title accepted'
	if (-not (Request $webs.owner ($route+'?bagian='+$newSection)).Content.Contains($unicodeTitle)) { throw 'Unicode section title was not preserved' }
	$denyRevision=Revision; $denyAudit=Audit-Count
	$invalidSection=Request $webs.owner $route 'POST' (Payload 'update_section' $foreignSection 'Foreign denied' '1')
	Expect $invalidSection.Status 400 'Foreign section denied'
	if ($invalidSection.Content.Contains('value="update_section"') -or -not $invalidSection.Content.Contains('Pilih bagian lalu tekan Tampilkan bagian')) { throw 'Foreign edit must not silently target fallback section' }
	Expect (Request $webs.owner $route 'POST' (Payload 'move_lesson' $newSection '' '1' $foreignLesson)).Status 400 'Foreign lesson denied'
	$invalidMove=Request $webs.owner $route 'POST' (Payload 'move_lesson' $foreignSection '' '1' $lessonId)
	Expect $invalidMove.Status 400 'Cross-course move denied'
	if (-not $invalidMove.Content.Contains('<option value="" selected disabled>Pilih kembali bagian tujuan</option>')) { throw 'Invalid destination must require explicit reselection' }
	Expect (Request $webs.owner $route 'POST' @{action='create_section'; revision=(Revision); 'title[]'='bad'; sort_order='1'}).Status 400 'Array title denied'
	foreach ($order in @('0','-1','10000','abc')) { $invalid=Request $webs.owner $route 'POST' (Payload 'create_section' '' 'Invalid order' $order); if ($invalid.Content -notmatch 'aria-invalid="true"') { throw 'Invalid order error must be associated with input' } }
	Expect (Revision) $denyRevision 'Invalid requests preserve revision'; Expect (Audit-Count) $denyAudit 'Invalid requests preserve audit'
	$oldBody=Db "SELECT SHA2(CONCAT_WS('|',slug,body_text),256) FROM st_lessons WHERE id=$lessonId;"
	Expect (Request $webs.owner $route 'POST' (Payload 'move_lesson' $newSection '' '1' $lessonId)).Status 303 'Move lesson into same-course section'
	Expect (Db "SELECT CONCAT(section_id,':',sort_order,':',content_version) FROM st_lessons WHERE id=$lessonId;") "${newSection}:1:2" 'Move destination and lesson version'
	Expect (Db "SELECT SHA2(CONCAT_WS('|',slug,body_text),256) FROM st_lessons WHERE id=$lessonId;") $oldBody 'Move preserves slug and body'
	$ordered=Request $webs.owner $route; if ($ordered.Content.IndexOf('QA Lesson 2') -gt $ordered.Content.IndexOf('QA Lesson 3')) { throw 'Stable lesson order failed' }
	Write-Output 'PASS structure ownership/roles, method/CSRF, add/edit/move, version/replay/no-op, escaping, pagination, invalid IDs/input and unchanged lesson body/slug.'
	$environmentNames=@('CI_ENV','DB_HOST','DB_USERNAME','DB_PASSWORD','DB_DATABASE'); $previous=@{}
	foreach($name in $environmentNames){$previous[$name]=[Environment]::GetEnvironmentVariable($name,'Process')}
	try { $env:CI_ENV='development'; $env:DB_HOST='127.0.0.1'; $env:DB_USERNAME='study_app'; $env:DB_PASSWORD=''; $env:DB_DATABASE='db_study'; & $Php81Path index.php cli/check_course_structure index $courseId; if($LASTEXITCODE -ne 0){throw 'Audit rollback CLI test failed'} } finally {foreach($name in $environmentNames){[Environment]::SetEnvironmentVariable($name,$previous[$name],'Process')}}
	if ($ChromePath) {
		$oldIdentity=$env:STUDY_QA_LOGIN_IDENTITY; $oldPassword=$env:STUDY_QA_LOGIN_PASSWORD
		try {
			$env:STUDY_QA_LOGIN_IDENTITY="${prefix}_owner"; $env:STUDY_QA_LOGIN_PASSWORD=$password
			$browserArguments=@('scripts/browser-viewport-check.mjs','--base-url',$root,'--chrome',$ChromePath,'--extra-path',$route,'--extra-name','course-structure','--only-extra-route','--exercise-navigation','--open-details')
			if ($CaptureScreenshots) { $browserArguments+=@('--capture-screenshots','--screenshot-dir','docs/visual-baseline/2026-10-15-course-structure') }
			& node @browserArguments
			if($LASTEXITCODE -ne 0){throw 'Structure browser check failed'}
		} finally { $env:STUDY_QA_LOGIN_IDENTITY=$oldIdentity; $env:STUDY_QA_LOGIN_PASSWORD=$oldPassword }
	}
	$limitRevision=Revision; $limitAudit=Audit-Count
	foreach ($number in 3..100) { Db "INSERT INTO st_course_sections(course_id,title,sort_order,created_at,updated_at) VALUES($courseId,'QA capacity section $number',$number,UTC_TIMESTAMP(),UTC_TIMESTAMP());" | Out-Null }
	$limit=Request $webs.owner $route 'POST' (Payload 'create_section' '' 'Over capacity' '101')
	Expect $limit.Status 400 'Section capacity limit'; if($limit.Content -notmatch 'maksimal 100'){throw 'Section limit explanation missing'}
	Expect (Revision) $limitRevision 'Capacity rejection keeps revision'; Expect (Audit-Count) $limitAudit 'Capacity rejection keeps audit'
	Write-Output 'PASS 100-section capacity rejection preserves revision/audit and explains the limit.'
	Db "DELETE ur FROM st_user_roles ur JOIN st_users u ON u.id=ur.user_id WHERE u.username='${prefix}_owner';" | Out-Null
	Expect (Request $webs.owner $route 'POST' (Payload 'update_section' $sectionId 'Revoked role' '1')).Status 403 'Revoked editor role denied in existing session'
	Db "INSERT INTO st_user_roles(user_id,role_id,granted_by_user_id,created_at) SELECT u.id,r.id,u.id,UTC_TIMESTAMP() FROM st_users u JOIN st_roles r ON r.role_code='content_editor' WHERE u.username='${prefix}_owner';" | Out-Null
	foreach($state in @('review','published','archived')) { Db "UPDATE st_courses SET status='$state' WHERE id=$courseId;" | Out-Null; Expect (Request $webs.owner $route 'POST' (Payload 'update_section' $sectionId 'Locked course' '1')).Status 403 'Non-draft structure denied' }
	Db "UPDATE st_courses SET status='published' WHERE id=$courseId; UPDATE st_lessons l JOIN st_course_sections s ON s.id=l.section_id SET l.status='published' WHERE s.course_id=$courseId;" | Out-Null
	$public=New-Object Microsoft.PowerShell.Commands.WebRequestSession
	$catalog=Request $public "kursus/$slug-main"; Expect $catalog.Status 200 'Published course detail'
	$eleventh=$catalog.Content.IndexOf("materi/$slug-lesson-11"); $first=$catalog.Content.IndexOf("materi/$slug-lesson-1`"")
	if ($eleventh -lt 0 -or $first -lt 0 -or $eleventh -gt $first) { throw 'Catalog interleaved equal-order sections or missing lesson links' }
	$next=Request $public "materi/$slug-lesson-11"; Expect $next.Status 200 'Published lesson'
	if ($next.Content -notmatch [regex]::Escape("materi/$slug-lesson-1`"")) { throw 'Next lesson did not follow section order' }
	Write-Output 'PASS audit rollback, non-draft lock, deterministic public ordering and next-lesson navigation.'
} finally {
	[Net.HttpWebRequest]::DefaultMaximumErrorResponseLength=$previousErrorResponseLength
	Db "START TRANSACTION; DELETE a FROM st_audit_events a JOIN st_users u ON u.id=a.actor_user_id WHERE u.username IN ($names); DELETE l FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id WHERE c.slug IN ('$slug-main','$slug-other'); DELETE s FROM st_course_sections s JOIN st_courses c ON c.id=s.course_id WHERE c.slug IN ('$slug-main','$slug-other'); DELETE FROM st_courses WHERE slug IN ('$slug-main','$slug-other'); DELETE ur FROM st_user_roles ur JOIN st_users u ON u.id=ur.user_id WHERE u.username IN ($names); DELETE FROM st_users WHERE username IN ($names); COMMIT;" | Out-Null
	Write-Output 'PASS exact structure fixture accounts/courses/sections/lessons/role grants/audit cleaned.'
}
