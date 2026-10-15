param(
	[Parameter(Mandatory=$true)][string]$Php81Path,
	[Parameter(Mandatory=$true)][string]$MysqlPath,
	[Parameter(Mandatory=$true)][string]$BaseUrl,
	[string]$ChromePath='',
	[switch]$CaptureScreenshots,
	[switch]$AllowUnavailableUpload
)
$ErrorActionPreference='Stop'
$parsed=[Uri]$BaseUrl
if($parsed.Scheme -ne 'http' -or -not $parsed.IsLoopback -or $parsed.UserInfo -or $parsed.Query -or $parsed.Fragment){throw 'Media QA accepts HTTP loopback only.'}
$root=$BaseUrl.TrimEnd('/')+'/'
if($CaptureScreenshots -and -not $ChromePath){throw 'Media screenshots require ChromePath.'}
$suffix=[Guid]::NewGuid().ToString('N').Substring(0,12)
$prefix='qa_media_'+$suffix
$slug='qa-media-'+$suffix
$names=(@('owner','other','admin','member') | ForEach-Object {"'${prefix}_$_'"}) -join ','
$password='Synthetic-Media-Check-123!'
$hash=(& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim()
if($LASTEXITCODE -ne 0 -or $hash -notmatch '^\$2[ayb]\$'){throw 'Synthetic credential setup failed.'}
function Db([string]$Sql){$output=& $MysqlPath --no-defaults --host=127.0.0.1 --protocol=TCP -uroot -D db_study -N -B -e $Sql 2>&1; if($LASTEXITCODE -ne 0){throw 'Media QA SQL failed; inspect locally without exposing data.'}; return ($output -join "`n").Trim()}
function Expect($Actual,$Expected,[string]$Label){if($Actual -ne $Expected){throw "$Label (expected $Expected, got $Actual)"}}
function Revision {return Db "SELECT content_version FROM st_lessons WHERE id=$lessonId;"}
function State {return Db "SELECT CONCAT(c.content_version,':',l.content_version,':',(SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id IN(SELECT id FROM st_users WHERE username IN($names))),':',(SELECT COALESCE(SHA2(CONCAT_WS('|',storage_key,alt_text,caption,source_note,rights_status),256),'none') FROM st_lesson_images WHERE lesson_id=l.id)) FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id WHERE l.id=$lessonId;"}
function Fields([string]$Action='metadata'){return @{action=$Action;revision=(Revision);alt_text='Diagram QA untuk belajar';caption='Keterangan sintetis';source_note='Dibuat sendiri untuk QA';rights_status='owned'}}
function Invoke-MediaProbe([string]$Mode){
	$variables=@('CI_ENV','DB_HOST','DB_USERNAME','DB_PASSWORD','DB_DATABASE');$previous=@{}
	foreach($name in $variables){$previous[$name]=[Environment]::GetEnvironmentVariable($name,'Process')}
	try{$env:CI_ENV='development';$env:DB_HOST='127.0.0.1';$env:DB_USERNAME='study_app';$env:DB_PASSWORD='';$env:DB_DATABASE='db_study'; & $Php81Path index.php cli/check_lesson_media index $courseId $Mode; if($LASTEXITCODE -ne 0){throw "Media CLI $Mode failed"}}
	finally{foreach($name in $variables){[Environment]::SetEnvironmentVariable($name,$previous[$name],'Process')}}
}
function Request($Web,[string]$Route,[string]$Method='GET',[hashtable]$Data=@{},[byte[]]$Upload=$null,[string]$Filename='qa.png'){
	$request=[Net.HttpWebRequest]::Create($root+$Route);$request.Method=$Method;$request.CookieContainer=$Web.Cookies;$request.AllowAutoRedirect=$false;$request.Timeout=15000
	if($Method -eq 'POST'){
		if(-not $Data.ContainsKey('study_csrf_token')){$Data['study_csrf_token']=$Web.Cookies.GetCookies([Uri]$root)['study_csrf_cookie'].Value}
		if($null -ne $Upload){
			$boundary='StudyQa'+[Guid]::NewGuid().ToString('N');$buffer=New-Object IO.MemoryStream
			try{
				foreach($item in $Data.GetEnumerator()){$part=[Text.Encoding]::UTF8.GetBytes("--$boundary`r`nContent-Disposition: form-data; name=`"$($item.Key)`"`r`n`r`n$($item.Value)`r`n");$buffer.Write($part,0,$part.Length)}
				$part=[Text.Encoding]::UTF8.GetBytes("--$boundary`r`nContent-Disposition: form-data; name=`"image`"; filename=`"$Filename`"`r`nContent-Type: image/png`r`n`r`n");$buffer.Write($part,0,$part.Length);$buffer.Write($Upload,0,$Upload.Length)
				$part=[Text.Encoding]::UTF8.GetBytes("`r`n--$boundary--`r`n");$buffer.Write($part,0,$part.Length);$bytes=$buffer.ToArray()
			}finally{$buffer.Dispose()}
			$request.ContentType='multipart/form-data; boundary='+$boundary
		}else{$body=($Data.GetEnumerator()|ForEach-Object{[Net.WebUtility]::UrlEncode($_.Key)+'='+[Net.WebUtility]::UrlEncode([string]$_.Value)})-join '&';$bytes=[Text.Encoding]::UTF8.GetBytes($body);$request.ContentType='application/x-www-form-urlencoded'}
		$request.ContentLength=$bytes.Length;$stream=$request.GetRequestStream();try{$stream.Write($bytes,0,$bytes.Length)}finally{$stream.Close()}
	}
	try{$response=$request.GetResponse()}catch{$response=$_.Exception.Response;if(-not $response -and $_.Exception.InnerException){$response=$_.Exception.InnerException.Response};if(-not $response){throw "Media HTTP transport failed at $Method $Route; no POST retry."}}
	try{$buffer=New-Object IO.MemoryStream;try{$response.GetResponseStream().CopyTo($buffer);$resultBytes=$buffer.ToArray()}finally{$buffer.Dispose()};return @{Status=[int]$response.StatusCode;Content=[Text.Encoding]::UTF8.GetString($resultBytes);Bytes=$resultBytes;Mime=$response.ContentType;Cache=$response.Headers['Cache-Control'];NoSniff=$response.Headers['X-Content-Type-Options'];Length=$response.ContentLength;Location=$response.Headers['Location'];Allow=$response.Headers['Allow']}}finally{$response.Close()}
}

$courseId=$null
try{
	foreach($kind in @('owner','other','admin','member')){
		Db "INSERT INTO st_users(username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES('${prefix}_$kind','$hash','QA media','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());" | Out-Null
		if($kind -ne 'member'){$role=if($kind -eq 'admin'){'system_admin'}else{'content_editor'};Db "INSERT INTO st_user_roles(user_id,role_id,granted_by_user_id,created_at) SELECT u.id,r.id,u.id,UTC_TIMESTAMP() FROM st_users u JOIN st_roles r ON r.role_code='$role' WHERE u.username='${prefix}_$kind';" | Out-Null}
	}
	foreach($pair in @(@('main','owner','lesson'),@('other','other','foreign'))){
		$courseSlug=$slug+'-'+$pair[0];$ownerName=$prefix+'_'+$pair[1];$lessonSlug=$slug+'-'+$pair[2]
		Db "INSERT INTO st_courses(author_user_id,updated_by_user_id,title,slug,summary,learning_objectives,target_group,status,content_version,rights_status,source_note,is_free,created_at,updated_at) SELECT id,id,'QA media course','$courseSlug','Synthetic media fixture','Understand a diagram','primary','draft',1,'owned','Synthetic QA source',1,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_users WHERE username='$ownerName'; INSERT INTO st_course_sections(course_id,title,sort_order,created_at,updated_at) SELECT id,'Bagian awal',1,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_courses WHERE slug='$courseSlug'; INSERT INTO st_lessons(section_id,title,slug,body_text,estimated_minutes,sort_order,status,content_version,is_preview,rights_status,source_note,created_at,updated_at) SELECT s.id,'QA image lesson','$lessonSlug','Synthetic lesson body with enough readable text for review.',5,1,'draft',1,0,'owned','Synthetic QA source',UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_course_sections s JOIN st_courses c ON c.id=s.course_id WHERE c.slug='$courseSlug';" | Out-Null
	}
	$courseId=Db "SELECT id FROM st_courses WHERE slug='$slug-main';";$lessonId=Db "SELECT id FROM st_lessons WHERE slug='$slug-lesson';";$foreignId=Db "SELECT id FROM st_lessons WHERE slug='$slug-foreign';"
	$webs=@{};foreach($kind in @('owner','other','admin','member')){$web=New-Object Microsoft.PowerShell.Commands.WebRequestSession;Request $web 'masuk'|Out-Null;Expect (Request $web 'masuk' 'POST' @{identity="${prefix}_$kind";password=$password}).Status 303 'Media fixture login';$webs[$kind]=$web}
	$guest=New-Object Microsoft.PowerShell.Commands.WebRequestSession
	$route="kelola/kursus/$courseId/pelajaran/$lessonId/gambar";$preview=$route+'/pratinjau';$public="materi/$slug-lesson/gambar"
	Expect (Request $webs.owner $route).Status 200 'Image editor GET';Expect (Request $webs.owner $preview).Status 404 'No image initially';Expect (Request $guest $public).Status 404 'Draft image not public'
	foreach($kind in @('other','member')){Expect (Request $webs[$kind] $route).Status 403 'Foreign editor/member cannot edit';Expect (Request $webs[$kind] $preview).Status 403 'Draft preview ownership';Expect (Request $webs[$kind] "lesson_media/edit/$courseId/$lessonId" 'POST' (Fields)).Status 403 'Direct controller POST denied'}
	Expect (Request $webs.owner "lesson_media/preview/$courseId/$foreignId").Status 404 'Cross-course preview denied'
	$method=Request $webs.owner $route 'DELETE';Expect $method.Status 405 'Editor method guard';Expect $method.Allow 'GET, HEAD, POST' 'Editor Allow'
	Expect (Request $webs.owner $route 'POST' @{action='metadata';revision=(Revision);study_csrf_token='invalid'}).Status 403 'CSRF invalid'
	Invoke-MediaProbe 'seed'
	$binary=Request $webs.owner $preview;Expect $binary.Status 200 'Private image GET';Expect $binary.Mime 'image/png' 'Normalized PNG MIME';Expect $binary.NoSniff 'nosniff' 'No sniff';if($binary.Cache -notmatch 'no-store'){throw 'Image cache must be private/no-store'}
	$sha=[Security.Cryptography.SHA256]::Create();try{$actualHash=([BitConverter]::ToString($sha.ComputeHash($binary.Bytes))).Replace('-','').ToLowerInvariant()}finally{$sha.Dispose()}
	Expect $actualHash (Db "SELECT sha256 FROM st_lesson_images WHERE lesson_id=$lessonId;") 'Image bytes match stored hash'
	$head=Request $webs.owner $preview 'HEAD';Expect $head.Status 200 'HEAD image';Expect $head.Bytes.Length 0 'HEAD no body';Expect $head.Length $binary.Bytes.Length 'HEAD full resource length'
	$key=Db "SELECT storage_key FROM st_lesson_images WHERE lesson_id=$lessonId;";$html=Request $webs.owner $route;if($html.Content.Contains($key) -or $html.Content.Contains('study-private')){throw 'Physical storage information leaked in HTML'}
	$before=State;$upload=Request $webs.owner $route 'POST' (Fields 'upload') $binary.Bytes
	if($upload.Status -eq 503 -and $AllowUnavailableUpload){Expect (State) $before 'Unavailable upload leaves DB unchanged';Write-Output 'LIMITATION successful HTTP upload not verified: server reports GD/storage unavailable; valid fixture was normalized through PHP81 CLI.'}else{Expect $upload.Status 303 'Successful genuine multipart HTTP upload'}
	$metadata=Fields;$metadata.alt_text='<b>Diagram QA aman</b>';$metadata.caption='Caption <script>not executable</script>'
	$oldRevision=$metadata.revision;Expect (Request $webs.owner $route 'POST' $metadata).Status 303 'Metadata saved'
	$after=State;$metadata.Remove('study_csrf_token');Expect (Request $webs.owner $route 'POST' $metadata).Status 409 'Stale metadata replay';Expect (State) $after 'Stale metadata no mutation'
	$html=Request $webs.owner $route;if(-not $html.Content.Contains('&lt;b&gt;Diagram QA aman&lt;/b&gt;') -or $html.Content.Contains('<script>not executable</script>')){throw 'Image metadata not escaped'}
	$noop=Fields;$noop.alt_text=$metadata.alt_text;$noop.caption=$metadata.caption;Expect (Request $webs.owner $route 'POST' $noop).Status 303 'Metadata no-op';Expect (State) $after 'No-op no revision/audit change'
	foreach($bad in @(@{action='metadata';revision=(Revision);'alt_text[]'='bad'},@{action='metadata';revision='0'},@{action='remove';revision=(Revision)})){Expect (Request $webs.owner $route 'POST' $bad).Status 400 'Invalid input/confirmation rejected'}
	Expect (State) $after 'Invalid requests no mutation'
	Invoke-MediaProbe 'verify'
	Write-Output 'PASS media editor role/ownership/CSRF/methods, private GET/HEAD bytes and headers, escaping, revisions/replay/no-op and invalid requests.'
	if($ChromePath){
		$oldIdentity=$env:STUDY_QA_LOGIN_IDENTITY;$oldPassword=$env:STUDY_QA_LOGIN_PASSWORD
		try{
			$env:STUDY_QA_LOGIN_IDENTITY="${prefix}_owner";$env:STUDY_QA_LOGIN_PASSWORD=$password
			foreach($browserCase in @(@($route,'lesson-media'),@("kelola/kursus/$courseId/pelajaran/$lessonId/pratinjau",'lesson-image-preview'))){
				$browserArgs=@('scripts/browser-viewport-check.mjs','--base-url',$root,'--chrome',$ChromePath,'--extra-path',$browserCase[0],'--extra-name',$browserCase[1],'--only-extra-route','--exercise-navigation','--open-details')
				if($CaptureScreenshots){
					$shotDirectory='docs/visual-baseline/2026-10-15-lesson-media';$shotPhone=Join-Path $shotDirectory ($browserCase[1]+'-360.png');$shotDesktop=Join-Path $shotDirectory ($browserCase[1]+'-1280.png')
					if((Test-Path -LiteralPath $shotPhone) -and (Test-Path -LiteralPath $shotDesktop)){Write-Output 'CAPTURE retained existing baseline pair; browser checks still rerun.'}
					elseif((Test-Path -LiteralPath $shotPhone) -or (Test-Path -LiteralPath $shotDesktop)){throw 'Incomplete screenshot pair exists; review it before choosing a new baseline directory.'}
					else{$browserArgs+=@('--capture-screenshots','--screenshot-dir',$shotDirectory)}
				}
				& node @browserArgs;if($LASTEXITCODE -ne 0){throw 'Media browser check failed'}
			}
		}finally{$env:STUDY_QA_LOGIN_IDENTITY=$oldIdentity;$env:STUDY_QA_LOGIN_PASSWORD=$oldPassword}
	}
	$remove=Fields 'remove';$remove.confirm_remove='yes';Expect (Request $webs.owner $route 'POST' $remove).Status 303 'Confirmed remove';Expect (Db "SELECT COUNT(*) FROM st_lesson_images WHERE lesson_id=$lessonId;") '0' 'Image link removed';Expect (Request $webs.owner $preview).Status 404 'Removed image unavailable'
	Invoke-MediaProbe 'seed'
	$storedKey=Db "SELECT storage_key FROM st_lesson_images WHERE lesson_id=$lessonId;";$missingKey=[Guid]::NewGuid().ToString('N')+[Guid]::NewGuid().ToString('N')+'.png'
	Db "UPDATE st_lesson_images SET storage_key='$missingKey' WHERE lesson_id=$lessonId;"|Out-Null;Expect (Request $webs.owner $preview).Status 503 'Missing image fails closed';Request $webs.owner "kelola/kursus/$courseId/ajukan" 'POST' @{}|Out-Null;Expect (Db "SELECT status FROM st_courses WHERE id=$courseId;") 'draft' 'Missing image blocks submission';Db "UPDATE st_lesson_images SET storage_key='$storedKey' WHERE lesson_id=$lessonId;"|Out-Null
	$storedHash=Db "SELECT sha256 FROM st_lesson_images WHERE lesson_id=$lessonId;";Db "UPDATE st_lesson_images SET sha256=REPEAT('0',64) WHERE lesson_id=$lessonId;"|Out-Null
	Expect (Request $webs.owner $preview).Status 503 'Corrupt image fails closed';Request $webs.owner "kelola/kursus/$courseId/ajukan" 'POST' @{}|Out-Null;Expect (Db "SELECT status FROM st_courses WHERE id=$courseId;") 'draft' 'Broken image blocks submission'
	Db "UPDATE st_lesson_images SET sha256='$storedHash' WHERE lesson_id=$lessonId;"|Out-Null
	Request $webs.owner "kelola/kursus/$courseId/ajukan" 'POST' @{}|Out-Null;Expect (Db "SELECT status FROM st_courses WHERE id=$courseId;") 'review' 'Image course submitted';Expect (Request $webs.other $preview).Status 200 'Reviewer image preview';Expect (Request $webs.owner $route).Status 403 'Review image locked'
	Db "UPDATE st_lesson_images SET sha256=REPEAT('0',64) WHERE lesson_id=$lessonId;"|Out-Null;Request $webs.other "kelola/kursus/$courseId/tinjau" 'POST' @{decision='approved';notes='Synthetic review'}|Out-Null;Expect (Db "SELECT status FROM st_courses WHERE id=$courseId;") 'review' 'Broken image blocks approval';Db "UPDATE st_lesson_images SET sha256='$storedHash' WHERE lesson_id=$lessonId;"|Out-Null
	Request $webs.other "kelola/kursus/$courseId/tinjau" 'POST' @{decision='approved';notes='Synthetic review'}|Out-Null;Expect (Db "SELECT status FROM st_courses WHERE id=$courseId;") 'published' 'Reviewed course published'
	Expect (Request $guest $public).Status 200 'Published free image';Expect (Request $guest "lesson_media/show/$slug-lesson").Status 200 'Direct public image';Expect (Request $guest $public 'PUT').Status 405 'Image method guard'
	$lessonPage=Request $guest "materi/$slug-lesson";if(-not $lessonPage.Content.Contains($public)){throw 'Public lesson image link missing'}
	Db "UPDATE st_courses SET is_free=0 WHERE id=$courseId;"|Out-Null;Expect (Request $guest $public).Status 403 'Locked image guest denied';Expect (Request $webs.member $public).Status 403 'Locked image member denied'
	Db "UPDATE st_lessons SET is_preview=1 WHERE id=$lessonId;"|Out-Null;Expect (Request $guest $public).Status 200 'Preview image public';Db "UPDATE st_lessons SET is_preview=0 WHERE id=$lessonId; INSERT INTO st_course_enrollments(learner_user_id,course_id,source_type,package_id,entitlement_id,status,enrolled_at,updated_at) SELECT id,$courseId,'manual',NULL,NULL,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_users WHERE username='${prefix}_member';"|Out-Null
	Expect (Request $webs.member $public).Status 200 'Active enrolled member image';Db "UPDATE st_course_enrollments SET status='revoked' WHERE course_id=$courseId;"|Out-Null;Expect (Request $webs.member $public).Status 403 'Revoked enrollment image denied'
	Request $webs.admin "kelola/kursus/$courseId/arsip" 'POST' @{}|Out-Null;Expect (Db "SELECT status FROM st_courses WHERE id=$courseId;") 'archived' 'Course archived';Expect (Request $guest $public).Status 404 'Archived image no public access';Expect (Request $webs.admin $preview).Status 200 'Admin archive preview'
	Write-Output 'PASS remove, broken-image submission/approval gates, separate reviewer, publication, free/preview/locked/enrolled/revoked/archived image access.'
}finally{
	if($courseId){Invoke-MediaProbe 'cleanup'}
	Db "DELETE i FROM st_lesson_images i JOIN st_lessons l ON l.id=i.lesson_id JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id WHERE c.slug IN('$slug-main','$slug-other'); DELETE FROM st_content_reviews WHERE course_id IN(SELECT id FROM st_courses WHERE slug IN('$slug-main','$slug-other')); DELETE FROM st_course_enrollments WHERE course_id IN(SELECT id FROM st_courses WHERE slug IN('$slug-main','$slug-other')); DELETE FROM st_audit_events WHERE actor_user_id IN(SELECT id FROM st_users WHERE username IN($names)); DELETE l FROM st_lessons l JOIN st_course_sections s ON s.id=l.section_id JOIN st_courses c ON c.id=s.course_id WHERE c.slug IN('$slug-main','$slug-other'); DELETE s FROM st_course_sections s JOIN st_courses c ON c.id=s.course_id WHERE c.slug IN('$slug-main','$slug-other'); DELETE FROM st_courses WHERE slug IN('$slug-main','$slug-other'); DELETE FROM st_user_roles WHERE user_id IN(SELECT id FROM st_users WHERE username IN($names)); DELETE FROM st_users WHERE username IN($names);"|Out-Null
	Write-Output 'PASS exact media fixture rows and current media files cleaned.'
}
