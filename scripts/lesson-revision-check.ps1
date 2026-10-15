param(
	[Parameter(Mandatory=$true)][string]$Php81Path,
	[Parameter(Mandatory=$true)][string]$MysqlPath,
	[Parameter(Mandatory=$true)][string]$BaseUrl
)
$ErrorActionPreference='Stop'
$parsed=[Uri]$BaseUrl
if ($parsed.Scheme -ne 'http' -or -not $parsed.IsLoopback -or $parsed.UserInfo -or $parsed.Query -or $parsed.Fragment) { throw 'Lesson revision QA accepts only an HTTP loopback URL.' }
$root=$BaseUrl.TrimEnd('/')+'/'
$suffix=[Guid]::NewGuid().ToString('N').Substring(0,12)
$prefix='qa_lesson_revision_'+$suffix
$slug='qa-lesson-revision-'+$suffix
$names=(@('owner','other','admin') | ForEach-Object { "'${prefix}_$_'" }) -join ','
$password='Synthetic-Revision-Check-123!'
$hash=(& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim()
if ($LASTEXITCODE -ne 0 -or $hash -notmatch '^\$2[ayb]\$') { throw 'Synthetic credential setup failed.' }
function Db([string]$Sql) {
	$output=& $MysqlPath -uroot -D db_study -N -B -e $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw 'Lesson revision QA SQL operation failed; inspect locally without exposing credentials.' }
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
	try { $response=$request.GetResponse() } catch { $response=$_.Exception.Response; if (-not $response -and $_.Exception.InnerException) { $response=$_.Exception.InnerException.Response }; if (-not $response) { throw "HTTP transport failed at $Method $Route; no automatic POST retry." } }
	try { $reader=New-Object IO.StreamReader($response.GetResponseStream()); try { $content=$reader.ReadToEnd() } finally { $reader.Close() }; return @{Status=[int]$response.StatusCode; Content=$content; Location=$response.Headers['Location']} } finally { $response.Close() }
}
function Expect($Actual,$Expected,[string]$Message) { if ($Actual -ne $Expected) { throw "$Message (expected $Expected, received $Actual)" } }
function Payload([string]$Revision,[string]$Title='QA latest lesson') { return @{revision=$Revision;title=$Title;body_text='Synthetic lesson body with enough characters for validation.';estimated_minutes='5';rights_status='owned';source_note='Synthetic QA source';is_preview='yes'} }
function Course-Version { return Db "SELECT content_version FROM st_courses WHERE id=$courseId;" }
function Snapshot {
	return Db "SELECT SHA2(CONCAT_WS('|',c.content_version,c.updated_at,c.updated_by_user_id,l.content_version,l.title,l.body_text,l.estimated_minutes,l.source_note,l.rights_status,l.is_preview,l.section_id,l.sort_order,l.updated_at,(SELECT COUNT(*) FROM st_audit_events a WHERE a.actor_user_id IN (SELECT id FROM st_users WHERE username IN ($names)))),256) FROM st_courses c JOIN st_course_sections s ON s.course_id=c.id JOIN st_lessons l ON l.section_id=s.id WHERE c.id=$courseId ORDER BY l.id;"
}
try {
	foreach ($kind in @('owner','other','admin')) {
		Db "INSERT INTO st_users(username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES('${prefix}_$kind','$hash','QA lesson revision','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());" | Out-Null
		$role=if($kind -eq 'admin'){'system_admin'}else{'content_editor'}
		Db "INSERT INTO st_user_roles(user_id,role_id,granted_by_user_id,created_at) SELECT u.id,r.id,u.id,UTC_TIMESTAMP() FROM st_users u JOIN st_roles r ON r.role_code='$role' WHERE u.username='${prefix}_$kind';" | Out-Null
	}
	Db "INSERT INTO st_courses(author_user_id,updated_by_user_id,title,slug,summary,learning_objectives,target_group,status,content_version,rights_status,source_note,is_free,created_at,updated_at) SELECT id,id,'QA lesson revision course','$slug','Synthetic revision fixture','Learn revision checks','adult','draft',1,'owned','Synthetic QA',1,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_users WHERE username='${prefix}_owner'; INSERT INTO st_course_sections(course_id,title,sort_order,created_at,updated_at) SELECT id,'Initial section',1,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_courses WHERE slug='$slug';" | Out-Null
	$courseId=Db "SELECT id FROM st_courses WHERE slug='$slug';"
	$sectionId=Db "SELECT id FROM st_course_sections WHERE course_id=$courseId;"
	Db "INSERT INTO st_lessons(section_id,title,slug,body_text,estimated_minutes,sort_order,status,content_version,is_preview,rights_status,source_note,created_at,updated_at) VALUES($sectionId,'QA initial lesson','$slug-initial','Synthetic initial body for revision checks.',5,1,'draft',1,0,'owned','Synthetic QA',UTC_TIMESTAMP(),UTC_TIMESTAMP());" | Out-Null
	$lessonId=Db "SELECT id FROM st_lessons WHERE slug='$slug-initial';"
	$webs=@{}
	foreach ($kind in @('owner','other','admin')) { $web=New-Object Microsoft.PowerShell.Commands.WebRequestSession; Request $web 'masuk' | Out-Null; Expect (Request $web 'masuk' 'POST' @{identity="${prefix}_$kind";password=$password}).Status 303 'Fixture login'; $webs[$kind]=$web }
	$route="kelola/kursus/$courseId/pelajaran/$lessonId/edit"
	$structureRoute="kelola/kursus/$courseId/susunan"
	$initial=Request $webs.owner $route
	Expect $initial.Status 200 'Initial lesson form'
	if ($initial.Content -notmatch 'name="revision" value="1"') { throw 'Form must include the displayed lesson revision.' }
	Expect (Request $webs.owner "kelola/kursus/$courseId/pelajaran/tambah" 'POST' (Payload '' 'QA added lesson')).Status 303 'Create lesson remains functional'
	Expect (Course-Version) '2' 'Lesson creation advances course version'
	Expect (Db "SELECT COUNT(*) FROM st_audit_events a JOIN st_lessons l ON l.id=a.entity_id JOIN st_course_sections s ON s.id=l.section_id WHERE s.course_id=$courseId AND a.event_type='content.lesson_created' AND JSON_EXTRACT(a.details_json,'$.previous_course_version')=1 AND JSON_EXTRACT(a.details_json,'$.next_course_version')=2;") '1' 'Created lesson audit records course versions'
	$before=Snapshot
	Expect (Request $webs.owner $structureRoute 'POST' @{action='update_section';revision='1';section_id=$sectionId;title='Old structure tab';sort_order='2'}).Status 409 'Creating a lesson invalidates old structure forms'
	Expect (Snapshot) $before 'Stale structure form does not change lessons/course/audit'
	Expect (Request $webs.owner $route 'POST' (Payload '1')).Status 303 'Valid expected lesson revision accepted'
	Expect (Course-Version) '3' 'Lesson edit advances course version'
	Expect (Db "SELECT CONCAT(title,':',content_version) FROM st_lessons WHERE id=$lessonId;") 'QA latest lesson:2' 'Edited lesson content and version'
	$before=Snapshot
	$stalePayload=Payload '1' '<b>QA stale title</b>'; $stalePayload.body_text='Synthetic unsaved body to preserve on conflict.'; $stalePayload.Remove('is_preview')
	$stale=Request $webs.owner $route 'POST' $stalePayload
	Expect $stale.Status 409 'Old editor tab conflicts instead of overwriting'
	$conflictChecks=@{escapedTitle=($stale.Content -match '&lt;b&gt;QA stale title&lt;/b&gt;');body=$stale.Content.Contains($stalePayload.body_text);saveDisabled=($stale.Content -match 'type="submit" disabled>Simpan perubahan');reload=($stale.Content -match 'Muat ulang pelajaran');originalRevision=($stale.Content -match 'name="revision" value="1"');previewUnchecked=($stale.Content -notmatch 'id="is_preview"[^>]*checked')}
	foreach($key in $conflictChecks.Keys){ if(-not $conflictChecks[$key]){throw "Conflict form check failed: $key"} }
	Expect (Snapshot) $before 'Stale edit leaves full content/course/audit unchanged'
	$fresh=Request $webs.owner $route
	if ($fresh.Content -notmatch 'name="revision" value="2"' -or $fresh.Content -match 'type="submit" disabled>Simpan perubahan') { throw 'Reload must expose current revision and enable saving.' }
	foreach ($revision in @('','0','-1','01','1e0','2147483648')) { Expect (Request $webs.owner $route 'POST' (Payload $revision)).Status 400 'Malformed revision rejected' }
	$array=Payload '2'; $array.Remove('revision'); $array['revision[]']='2'
	Expect (Request $webs.owner $route 'POST' $array).Status 400 'Array revision rejected'
	$missing=Payload '2'; $missing.Remove('revision')
	Expect (Request $webs.owner $route 'POST' $missing).Status 400 'Missing revision rejected'
	$invalid=Payload '2' ''; $validation=Request $webs.owner $route 'POST' $invalid
	Expect $validation.Status 200 'Field validation retains established response'
	if ($validation.Content -notmatch 'aria-invalid="true"' -or $validation.Content -notmatch 'name="revision" value="2"') { throw 'Validation must preserve original revision and accessible field errors.' }
	Expect (Snapshot) $before 'Invalid revisions/fields do not mutate data'
	Expect (Request $webs.owner $structureRoute 'POST' @{action='move_lesson';revision=(Course-Version);section_id=$sectionId;lesson_id=$lessonId;sort_order='55'}).Status 303 'Move lesson advances revision'
	$before=Snapshot
	Expect (Request $webs.owner $route 'POST' (Payload '2' 'Old tab before move')).Status 409 'Moving a lesson invalidates its editor form'
	Expect (Snapshot) $before 'Post-move stale edit does not change data'
	Expect (Request $webs.admin $route 'POST' (Payload '3' 'QA admin latest')).Status 303 'Admin can edit owner lesson with current revision'
	Expect (Course-Version) '5' 'Course version reflects create/edit/move/edit'
	Expect (Db "SELECT content_version FROM st_lessons WHERE id=$lessonId;") '4' 'Lesson version reflects edit/move/edit'
	$before=Snapshot
	Expect (Request $webs.other $route 'POST' (Payload '4')).Status 403 'Foreign editor remains denied'
	Expect (Request $webs.owner $route 'POST' @{revision='4';title='No CSRF';study_csrf_token='invalid'}).Status 403 'Invalid CSRF remains denied'
	Expect (Snapshot) $before 'Authorization and CSRF failures do not mutate data'
	Db "UPDATE st_courses SET content_version=2147483647 WHERE id=$courseId;" | Out-Null
	$before=Snapshot
	Expect (Request $webs.owner $route 'POST' (Payload '4')).Status 409 'Exhausted course revision fails closed'
	Expect (Request $webs.owner "kelola/kursus/$courseId/pelajaran/tambah" 'POST' (Payload '' 'QA rejected overflow')).Status 200 'Create overflow shows established failure form'
	Expect (Snapshot) $before 'Exhausted course version permits no edits or insertion'
	Db "UPDATE st_courses SET content_version=5 WHERE id=$courseId;" | Out-Null
	Write-Output 'PASS create/edit aggregate versions, stale editor and structure conflicts, preserved escaped form values, move invalidation, malformed revisions, limits, admin/owner/foreign roles and CSRF.'
	$environmentNames=@('CI_ENV','DB_HOST','DB_USERNAME','DB_PASSWORD','DB_DATABASE'); $previous=@{}
	foreach($name in $environmentNames){$previous[$name]=[Environment]::GetEnvironmentVariable($name,'Process')}
	try {
		$env:CI_ENV='development'; $env:DB_HOST='127.0.0.1'; $env:DB_USERNAME='study_app'; $env:DB_PASSWORD=''; $env:DB_DATABASE='db_study'
		foreach ($operation in @('create','update')) { & $Php81Path index.php cli/check_lesson_revisions index $courseId $operation; if($LASTEXITCODE -ne 0){throw 'Lesson revision audit rollback CLI test failed'} }
	} finally {foreach($name in $environmentNames){[Environment]::SetEnvironmentVariable($name,$previous[$name],'Process')}}
} finally {
	Db "START TRANSACTION; DELETE a FROM st_audit_events a JOIN st_users u ON u.id=a.actor_user_id WHERE u.username IN ($names); DELETE l FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id WHERE c.slug='$slug'; DELETE s FROM st_course_sections s JOIN st_courses c ON c.id=s.course_id WHERE c.slug='$slug'; DELETE FROM st_courses WHERE slug='$slug'; DELETE ur FROM st_user_roles ur JOIN st_users u ON u.id=ur.user_id WHERE u.username IN ($names); DELETE FROM st_users WHERE username IN ($names); COMMIT;" | Out-Null
	Expect (Db "SELECT (SELECT COUNT(*) FROM st_users WHERE username IN ($names))+(SELECT COUNT(*) FROM st_courses WHERE slug='$slug');") '0' 'Exact synthetic cleanup'
	Write-Output 'PASS exact lesson-revision fixture accounts/courses/sections/lessons/role grants/audit cleaned.'
}
