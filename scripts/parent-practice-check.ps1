param(
	[Parameter(Mandatory = $true)][string] $Php81Path,
	[Parameter(Mandatory = $true)][string] $MysqlPath,
	[Parameter(Mandatory = $true)][string] $BaseUrl,
	[string] $ChromePath = '',
	[switch] $CaptureScreenshots
)

$ErrorActionPreference = 'Stop'
$parsedUrl = [Uri]$BaseUrl
if ($parsedUrl.Scheme -ne 'http' -or -not $parsedUrl.IsLoopback -or $parsedUrl.Query -or $parsedUrl.Fragment -or $parsedUrl.UserInfo) { throw 'Parent practice QA requires HTTP loopback.' }
if ($CaptureScreenshots -and -not $ChromePath) { throw 'CaptureScreenshots requires ChromePath.' }
$root = $BaseUrl.TrimEnd('/') + '/'
$suffix = [Guid]::NewGuid().ToString('N').Substring(0,12)
$parent = "qa_parentdraft_$suffix"
$other = "qa_parentother_$suffix"
$child = "qa_parentchild_$suffix"
$sibling = "qa_parentsibling_$suffix"
$admin = "qa_parentadmin_$suffix"
$names = "'$parent','$other','$child','$sibling','$admin'"
$packageSlug = "qa-parent-draft-$suffix"
$password = 'Temporary-Study-Parent-123!'
$passwordHash = (& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim().Replace("'", "''")
if ($LASTEXITCODE -ne 0) { throw 'Password fixture setup failed.' }

function Db([string] $Sql) {
	$result = & $MysqlPath '-uroot' '-D' 'db_study' '-N' '-B' '-e' $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Parent practice SQL failed: $result" }
	return ($result -join "`n").Trim()
}
function Page([string] $Path, $Web) { return Invoke-WebRequest -UseBasicParsing -Uri ($root+$Path) -WebSession $Web }
function Hidden($Response, [string] $Name) {
	$match = [regex]::Match($Response.Content, 'name="'+[regex]::Escape($Name)+'"\s+value="([^"]*)"')
	if (-not $match.Success) { throw "Missing field $Name" }
	return [System.Net.WebUtility]::HtmlDecode($match.Groups[1].Value)
}
function Post([string] $Path, $Web, [hashtable] $Body) {
	$Body['study_csrf_token'] = Hidden (Page $Path $Web) 'study_csrf_token'
	return Invoke-WebRequest -UseBasicParsing -Uri ($root+$Path) -WebSession $Web -Method Post -Body $Body
}
function Status([scriptblock] $Request) {
	try { return [int] ((& $Request).StatusCode) }
	catch { if ($_.Exception.Response) { return [int] $_.Exception.Response.StatusCode }; throw }
}
function Equal($Actual, $Expected, [string] $Message) { if ($Actual -ne $Expected) { throw "$Message (expected '$Expected', got '$Actual')." } }
function Login([string] $Identity) {
	$web = New-Object Microsoft.PowerShell.Commands.WebRequestSession
	Post 'masuk' $web @{identity=$Identity;password=$password} | Out-Null
	return $web
}
function Browser([string] $Path, [string] $Name) {
	if (-not $ChromePath) { return }
	$oldIdentity = [Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY','Process')
	$oldPassword = [Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD','Process')
	try {
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY',$parent,'Process')
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD',$password,'Process')
		$routeParts = $Path -split '\?',2
		$arguments = @((Join-Path $PSScriptRoot 'browser-viewport-check.mjs'),'--base-url',$root,'--chrome',$ChromePath,'--extra-path',$routeParts[0],'--extra-name',$Name,'--only-extra-route')
		if ($routeParts.Count -eq 2) { $arguments += @('--extra-query',$routeParts[1]) }
		if ($CaptureScreenshots) { $arguments += '--capture-screenshots' }
		& node @arguments
		if ($LASTEXITCODE -ne 0) { throw "Parent browser check failed for $Name." }
	} finally {
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY',$oldIdentity,'Process')
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD',$oldPassword,'Process')
	}
}

try {
	Db @"
START TRANSACTION;
INSERT INTO st_users(username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES
('$parent','$passwordHash','Ibu Peserta Uji','parent','unset','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$other','$passwordHash','Orang Tua Kedua','parent','unset','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$child','$passwordHash','Kakak Peserta','child','primary','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$sibling','$passwordHash','Adik Peserta','child','early_childhood','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$admin','$passwordHash','Admin Pengujian','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
INSERT INTO st_user_roles(user_id,role_id,granted_by_user_id,created_at) SELECT u.id,r.id,u.id,UTC_TIMESTAMP() FROM st_users u JOIN st_roles r ON r.role_code='system_admin' WHERE u.username='$admin';
INSERT INTO st_family_links(parent_user_id,child_user_id,status,created_at,updated_at) SELECT p.id,c.id,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_users p JOIN st_users c ON c.username IN ('$child','$sibling') WHERE p.username IN ('$parent','$other');
INSERT INTO st_packages(title,slug,summary,credit_price,status,family_practice_enabled,created_at,updated_at) VALUES ('Paket pengujian lokal','$packageSlug','Bukan paket yang dijual',0,'published',1,UTC_TIMESTAMP(),UTC_TIMESTAMP());
INSERT INTO st_entitlements(user_id,package_id,status,starts_at,ends_at,created_at) SELECT u.id,p.id,'active',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),UTC_TIMESTAMP() FROM st_users u JOIN st_packages p ON p.slug='$packageSlug' WHERE u.username IN ('$parent','$other');
COMMIT;
"@ | Out-Null
	$parentId = [int](Db "SELECT id FROM st_users WHERE username='$parent';")
	$childId = [int](Db "SELECT id FROM st_users WHERE username='$child';")
	$siblingId = [int](Db "SELECT id FROM st_users WHERE username='$sibling';")
	$parentWeb = Login $parent
	$otherWeb = Login $other
	$childWeb = Login $child
	$adminWeb = Login $admin
	$listPath = 'keluarga/latihan'
	foreach ($path in @($listPath,'parent_practice/index')) {
		foreach ($web in @($childWeb,$adminWeb)) { Equal (Status { Page $path $web }) 403 'Only parents can use private authoring.' }
	}
	$list = Page $listPath $parentWeb
	$token = Hidden $list 'create_token'
	Equal (Status { Post $listPath $parentWeb @{title='Percobaan';child_id=$childId;create_token='invalid'} }) 400 'Forged create key must be rejected.'
	Db "UPDATE st_packages SET family_practice_enabled=0 WHERE slug='$packageSlug';" | Out-Null
	Equal (Status { Post $listPath $parentWeb @{title='Percobaan';child_id=$childId;create_token=$token} }) 403 'Normal package must not grant authoring.'
	if ((Page $listPath $parentWeb).Content -notmatch 'Memerlukan paket') { throw 'Locked feature must explain why it is unavailable.' }
	Db "UPDATE st_packages SET family_practice_enabled=1 WHERE slug='$packageSlug';" | Out-Null
	Equal (Status { Post $listPath $parentWeb @{'title[]'='bad';child_id=$childId;create_token=$token} }) 400 'Array title must be rejected.'
	$invalid = Post $listPath $parentWeb @{title='';child_id='';create_token=$token}
	if ($invalid.Content -notmatch 'field-error-title' -or $invalid.Content -notmatch 'aria-invalid="true"') { throw 'Create validation must be accessible.' }
	Post $listPath $parentWeb @{title='Latihan bilangan bersama';child_id=$childId;create_token=$token} | Out-Null
	$sessionId = [int](Db "SELECT id FROM st_practice_sessions WHERE created_by_user_id=$parentId;")
	$editPath = "keluarga/latihan/$sessionId"
	Post $listPath $parentWeb @{title='Nama tidak boleh menimpa';child_id=$childId;create_token=$token} | Out-Null
	Equal (Db "SELECT COUNT(*) FROM st_practice_sessions WHERE created_by_user_id=$parentId;") '1' 'Repeated create must not duplicate drafts.'
	Equal (Db "SELECT title FROM st_practice_sessions WHERE id=$sessionId;") 'Latihan bilangan bersama' 'Create replay must not edit the earlier draft.'
	$emptyTitle = Post $editPath $parentWeb @{action='title';revision='0';title=''}
	if ($emptyTitle.Content -notmatch 'field-error-title') { throw 'Session title error must be linked to its input.' }
	Post $editPath $parentWeb @{action='title';revision='0';title='  Latihan bilangan bersama  '} | Out-Null
	Equal (Db "SELECT CONCAT(title,'|',draft_revision) FROM st_practice_sessions WHERE id=$sessionId;") 'Latihan bilangan bersama|1' 'Valid title edit trims text and advances draft revision.'
	foreach ($path in @($editPath,"parent_practice/edit/$sessionId")) {
		Equal (Status { Page $path $otherWeb }) 404 'Even another parent linked to the same child must not read private drafts.'
		foreach ($web in @($childWeb,$adminWeb)) { Equal (Status { Page $path $web }) 403 'Child/admin must not see draft answer keys.' }
		$csrf = Hidden (Page 'beranda' $otherWeb) 'study_csrf_token'
		Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$path) -WebSession $otherWeb -Method Post -Body @{action='cancel';revision='0';confirm='1';study_csrf_token=$csrf} }) 404 'Other linked parent must not cancel the draft.'
	}
	Equal (Status { Post $editPath $parentWeb @{action='save';revision='0';question_id='0';'prompt[]'='bad'} }) 400 'Nested fields must be rejected.'
	$emptyQuestion = Post $editPath $parentWeb @{action='save';revision='0';question_id='0';prompt=''}
	if ($emptyQuestion.Content -notmatch 'field-error-prompt') { throw 'Question validation must identify invalid controls.' }
	$form = @{action='save';revision='0';question_id='0';prompt='Berapa hasil 2 + 3?';explanation='Dua benda ditambah tiga benda menjadi lima.';source_note='Soal sintetis buatan pengujian';rights_status='owned';correct='0';option_0='5';option_1='4';option_2='6';option_3='7'}
	$form.revision = Hidden (Page $editPath $parentWeb) 'revision'
	$duplicate = $form.Clone(); $duplicate.option_1='5'
	Equal (Status { Post $editPath $parentWeb $duplicate }) 400 'Duplicate option text must be rejected.'
	foreach ($index in 1..11) {
		$form.revision = Hidden (Page $editPath $parentWeb) 'revision'
		$form.prompt = "Soal $index - Berapa hasil 2 + 3?"
		Post $editPath $parentWeb $form | Out-Null
	}
	Equal (Db "SELECT CONCAT(COUNT(*),'|',MIN(visibility),'|',MIN(target_child_user_id)) FROM st_questions WHERE created_by_user_id=$parentId;") "11|family_private|$childId" 'Questions must be private and bound to the selected child.'
	Equal (Db "SELECT COUNT(*) FROM st_questions WHERE created_by_user_id=$parentId AND course_id IS NOT NULL;") '0' 'Private questions must never enter the shared course bank.'
	$questionId = [int](Db "SELECT MIN(id) FROM st_questions WHERE created_by_user_id=$parentId;")
	$latest = Hidden (Page $editPath $parentWeb) 'revision'
	Equal (Status { Post $editPath $parentWeb @{action='title';revision='0';title='Stale'} }) 409 'Old tab cannot overwrite draft state.'
	$form.revision=$latest; $form.question_id=[string]$questionId; $form.prompt='Soal aman <img src=x onerror=alert(1)>'
	Post $editPath $parentWeb $form | Out-Null
	$edited = Page ($editPath+'?edit='+$questionId) $parentWeb
	if ($edited.Content -match '<img src=x onerror=alert\(1\)>' -or $edited.Content -notmatch '&lt;img') { throw 'Question text must be escaped in preview and form.' }
	Equal (Db "SELECT content_version FROM st_questions WHERE id=$questionId;") '2' 'Edit must advance question version.'
	Equal ([regex]::Matches((Page $editPath $parentWeb).Content,'data-question-id=').Count) 10 'Question list is paged by ten.'
	Equal ([regex]::Matches((Page ($editPath+'?halaman=999') $parentWeb).Content,'data-question-id=').Count) 1 'Last page retains the remaining question.'
	Browser $listPath 'parent-draft-list'
	Browser 'beranda' 'parent-draft-dashboard'
	Browser $editPath 'parent-draft-editor'
	$latest = Hidden (Page $editPath $parentWeb) 'revision'
	Equal (Status { Post $editPath $parentWeb @{action='delete';question_id=$questionId;revision=$latest} }) 400 'Deletion requires explicit confirmation.'
	Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$editPath) -WebSession $parentWeb -Method Post -Body @{action='delete';question_id=$questionId;revision=$latest;confirm='1';study_csrf_token='invalid'} }) 403 'CSRF failure must not delete a question.'
	Post $editPath $parentWeb @{action='delete';question_id=$questionId;revision=$latest;confirm='1'} | Out-Null
	Equal (Db "SELECT COUNT(*) FROM st_questions WHERE id=$questionId;") '0' 'Confirmed deletion removes only the draft question.'
	foreach ($condition in @("status='revoked'","status='active',ends_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND)","ends_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),starts_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR)")) {
		Db "UPDATE st_entitlements SET $condition WHERE user_id=$parentId;" | Out-Null
		Equal (Status { Page $editPath $parentWeb }) 200 'Expired feature access must retain read-only drafts.'
		Equal (Status { Post $editPath $parentWeb @{action='title';revision=(Hidden (Page $editPath $parentWeb) 'revision');title='Forbidden edit'} }) 403 'Revoked, expired and future entitlements must deny writes.'
	}
	Db "UPDATE st_entitlements SET status='active',starts_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),ends_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE user_id=$parentId; UPDATE st_family_links SET status='revoked' WHERE parent_user_id=$parentId AND child_user_id=$childId;" | Out-Null
	Equal (Status { Page $editPath $parentWeb }) 404 'Revoked family link must deny even read-only drafts.'
	if ((Page $listPath $parentWeb).Content -match 'Latihan bilangan bersama') { throw 'Revoked child draft must disappear from list.' }
	Db "UPDATE st_family_links SET status='active' WHERE parent_user_id=$parentId AND child_user_id=$childId;" | Out-Null
	$tokenTwo = Hidden (Page $listPath $parentWeb) 'create_token'
	Post $listPath $parentWeb @{title='Latihan adik';child_id=$siblingId;create_token=$tokenTwo} | Out-Null
	$siblingSession = [int](Db "SELECT MAX(id) FROM st_practice_sessions WHERE created_by_user_id=$parentId;")
	$siblingPath = "keluarga/latihan/$siblingSession"
	$remainingQuestionId = [int](Db "SELECT MIN(id) FROM st_questions WHERE created_by_user_id=$parentId;")
	$form.question_id=[string]$remainingQuestionId; $form.revision='0'
	Equal (Status { Post $siblingPath $parentWeb $form }) 404 'Question from sibling session cannot be edited via another session.'
	# Fill to the documented cap with only this run's exact private fixture IDs.
	$fillSql = 'START TRANSACTION;'
	foreach ($fillIndex in 1..40) {
		$fillSql += "INSERT INTO st_questions(created_by_user_id,target_child_user_id,visibility,question_type,prompt,explanation,status,content_version,source_note,rights_status,created_at,updated_at) SELECT created_by_user_id,target_child_user_id,visibility,question_type,'Capacity fixture $fillIndex',explanation,'draft',1,source_note,rights_status,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_questions WHERE id=$remainingQuestionId; SET @capacity_question=LAST_INSERT_ID(); INSERT INTO st_question_options(question_id,option_text,is_correct,sort_order) SELECT @capacity_question,option_text,is_correct,sort_order FROM st_question_options WHERE question_id=$remainingQuestionId; INSERT INTO st_session_questions(session_id,question_id,sort_order) VALUES($sessionId,@capacity_question,$(100+$fillIndex));"
	}
	Db ($fillSql+'COMMIT;') | Out-Null
	$form.question_id='0'; $form.revision = Hidden (Page $editPath $parentWeb) 'revision'
	Equal (Status { Post $editPath $parentWeb $form }) 400 'A fifty-first question must not be inserted.'
	Equal (Db "SELECT COUNT(*) FROM st_session_questions WHERE session_id=$sessionId;") '50' 'Question cap rejection must preserve exactly fifty questions.'
	$latest = Hidden (Page $editPath $parentWeb) 'revision'
	Db "UPDATE st_entitlements SET status='revoked' WHERE user_id=$parentId;" | Out-Null
	Post $editPath $parentWeb @{action='cancel';revision=$latest;confirm='1'} | Out-Null
	Equal (Db "SELECT CONCAT(status,'|',(SELECT COUNT(*) FROM st_questions WHERE created_by_user_id=$parentId)) FROM st_practice_sessions WHERE id=$sessionId;") 'cancelled|50' 'Cancel remains available without paid access and retains question history.'
	Equal (Status { Post $editPath $parentWeb @{action='title';revision=([int]$latest+1);title='Cannot reopen'} }) 409 'Cancelled draft must be immutable.'
	Browser $editPath 'parent-draft-readonly'
	Equal (Db "SELECT COUNT(*) FROM st_practice_attempts WHERE session_id IN ($sessionId,$siblingSession);") '0' 'Authoring must not create child attempts.'
	Equal (Db "SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$parentId AND event_type='family.practice_created';") '2' 'Create retries must not duplicate audit events.'
	if ((Db "SELECT details_json FROM st_audit_events WHERE actor_user_id=$parentId AND event_type LIKE 'family.practice_%';") -match 'Berapa|benda|Soal') { throw 'Audit must not contain private question text.' }
	Db "UPDATE st_entitlements SET status='active' WHERE user_id=$parentId;" | Out-Null
	$draftRows = @(1..99 | ForEach-Object { "($parentId,$siblingId,'Capacity draft $_','parent_assigned','draft',UTC_TIMESTAMP(),UTC_TIMESTAMP())" }) -join ','
	Db "INSERT INTO st_practice_sessions(created_by_user_id,learner_user_id,title,session_type,status,created_at,updated_at) VALUES $draftRows;" | Out-Null
	$capToken = Hidden (Page $listPath $parentWeb) 'create_token'
	Equal (Status { Post $listPath $parentWeb @{title='Over capacity';child_id=$childId;create_token=$capToken} }) 409 'A hundred-and-first active draft must be rejected.'
	Equal (Db "SELECT COUNT(*) FROM st_practice_sessions WHERE created_by_user_id=$parentId AND status='draft';") '100' 'Cap rejection must not create another draft.'
	Equal ([regex]::Matches((Page $listPath $parentWeb).Content,'href="[^"]*keluarga/latihan/[0-9]+"').Count) 10 'Session list must limit SQL results to ten.'
	Equal ([regex]::Matches((Page ($listPath+'?halaman=9999') $parentWeb).Content,'href="[^"]*keluarga/latihan/[0-9]+"').Count) 1 'Session list clamps to the last page, including cancelled history.'
	. (Join-Path $PSScriptRoot 'family-list-cases.ps1')
	Write-Output 'PASS private draft CRUD/replay/revisions, shared-child creator isolation, sibling question isolation, entitlement dates/status/capability, CSRF, escaping, paging, cancellation and audit.'
}
finally {
	Db @"
START TRANSACTION;
DELETE sq FROM st_session_questions sq JOIN st_practice_sessions s ON s.id=sq.session_id JOIN st_users u ON u.id=s.created_by_user_id WHERE u.username IN ($names);
DELETE o FROM st_question_options o JOIN st_questions q ON q.id=o.question_id JOIN st_users u ON u.id=q.created_by_user_id WHERE u.username IN ($names);
DELETE q FROM st_questions q JOIN st_users u ON u.id=q.created_by_user_id WHERE u.username IN ($names);
DELETE s FROM st_practice_sessions s JOIN st_users u ON u.id=s.created_by_user_id WHERE u.username IN ($names);
DELETE FROM st_entitlements WHERE user_id IN (SELECT id FROM st_users WHERE username IN ($names));
DELETE FROM st_packages WHERE slug='$packageSlug';
DELETE FROM st_family_links WHERE parent_user_id IN (SELECT id FROM st_users WHERE username IN ($names));
DELETE FROM st_audit_events WHERE actor_user_id IN (SELECT id FROM st_users WHERE username IN ($names));
DELETE FROM st_user_roles WHERE user_id IN (SELECT id FROM st_users WHERE username IN ($names));
DELETE FROM st_users WHERE username IN ($names);
COMMIT;
"@ | Out-Null
	Write-Output 'PASS temporary family draft users, links, entitlements, package, sessions, questions, options and audit events cleaned.'
}
