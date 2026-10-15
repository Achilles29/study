param(
	[Parameter(Mandatory = $true)][string] $Php81Path,
	[Parameter(Mandatory = $true)][string] $MysqlPath,
	[Parameter(Mandatory = $true)][string] $BaseUrl,
	[string] $ChromePath = '',
	[switch] $CaptureScreenshots
)
$ErrorActionPreference = 'Stop'
$parsedUrl = [Uri]$BaseUrl
if ($parsedUrl.Scheme -ne 'http' -or -not $parsedUrl.IsLoopback -or $parsedUrl.Query -or $parsedUrl.Fragment -or $parsedUrl.UserInfo) { throw 'Delivery QA requires HTTP loopback.' }
if ($CaptureScreenshots -and -not $ChromePath) { throw 'CaptureScreenshots requires ChromePath.' }
$root = $BaseUrl.TrimEnd('/')+'/'
$suffix = [Guid]::NewGuid().ToString('N').Substring(0,12)
$parent = "qa_delivery_parent_$suffix"
$other = "qa_delivery_other_$suffix"
$child = "qa_delivery_child_$suffix"
$sibling = "qa_delivery_sibling_$suffix"
$names = "'$parent','$other','$child','$sibling'"
$packageSlug = "qa-delivery-$suffix"
$secretExplanation = "Penjelasan_privat_$suffix"
$password = 'Temporary-Study-Delivery-123!'
$hash = (& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim().Replace("'", "''")
if ($LASTEXITCODE -ne 0) { throw 'Fixture password setup failed.' }
function Db([string] $Sql) {
	$result = & $MysqlPath '-uroot' '-D' 'db_study' '-N' '-B' '-e' $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Delivery SQL failed: $result" }
	return ($result -join "`n").Trim()
}
function Page([string] $Path, $Web) { return Invoke-WebRequest -UseBasicParsing -Uri ($root+$Path) -WebSession $Web }
function Hidden($Page, [string] $Name) {
	$match = [regex]::Match($Page.Content,'name="'+[regex]::Escape($Name)+'"\s+value="([^"]*)"')
	if (-not $match.Success) { throw "Missing $Name" }
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
function Invoke-PracticeTransactionProbe([string] $Mode, [int] $TargetId, [int] $LearnerId) {
	if ($Mode -notin @('family_start','save') -or $TargetId -lt 1 -or $LearnerId -lt 1) { throw 'Invalid family transaction probe target.' }
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
		& $Php81Path index.php cli/check_practice_transactions index $Mode $TargetId $LearnerId
		if ($LASTEXITCODE -ne 0) { throw 'Family transaction probe failed; fixture cleanup remains with this suite.' }
	}
	finally {
		foreach ($name in $environmentNames) { [Environment]::SetEnvironmentVariable($name,$previous[$name],'Process') }
		Pop-Location
	}
}
function LocalTime([int] $Hours) { return [DateTime]::UtcNow.AddHours(7+$Hours).ToString('yyyy-MM-ddTHH:mm') }
function CreateSession([string] $Title, [bool] $WithQuestion = $true) {
	$token = Hidden (Page 'keluarga/latihan' $parentWeb) 'create_token'
	Post 'keluarga/latihan' $parentWeb @{title=$Title;child_id=$childId;create_token=$token} | Out-Null
	$id = [int](Db "SELECT MAX(id) FROM st_practice_sessions WHERE created_by_user_id=$parentId;")
	if ($WithQuestion) {
		Post "keluarga/latihan/$id" $parentWeb @{action='save';revision='0';question_id='0';prompt='Berapa hasil 2 + 3?';explanation="$secretExplanation - Dua ditambah tiga menjadi lima.";source_note='Soal sintetis pengujian';rights_status='owned';correct='0';option_0='5';option_1='4';option_2='6';option_3='7'} | Out-Null
	}
	return $id
}
function DeliveryBody([int] $SessionId, [string] $From = '') {
	return @{action='deliver';revision=(Hidden (Page "keluarga/latihan/$SessionId" $parentWeb) 'revision');confirm='1';available_from=$From;available_until=(LocalTime 24)}
}
function Browser([string] $Path, [string] $Identity, [string] $Name) {
	if (-not $ChromePath) { return }
	$oldIdentity = [Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY','Process')
	$oldPassword = [Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD','Process')
	try {
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY',$Identity,'Process')
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD',$password,'Process')
		$arguments = @((Join-Path $PSScriptRoot 'browser-viewport-check.mjs'),'--base-url',$root,'--chrome',$ChromePath,'--extra-path',$Path,'--extra-name',$Name,'--only-extra-route')
		if ($Name -eq 'family-assigned-active') { $arguments += '--exercise-practice-unsaved' }
		if ($CaptureScreenshots) { $arguments += '--capture-screenshots' }
		& node @arguments
		if ($LASTEXITCODE -ne 0) { throw "Delivery browser check failed for $Name." }
	} finally {
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY',$oldIdentity,'Process')
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD',$oldPassword,'Process')
	}
}
try {
	Db @"
START TRANSACTION;
INSERT INTO st_users(username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES
('$parent','$hash','Orang Tua Peserta','parent','unset','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$other','$hash','Orang Tua Kedua','parent','unset','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$child','$hash','Peserta Latihan','child','primary','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$sibling','$hash','Saudara Peserta','child','primary','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
INSERT INTO st_family_links(parent_user_id,child_user_id,status,created_at,updated_at) SELECT p.id,c.id,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_users p JOIN st_users c ON c.username IN ('$child','$sibling') WHERE p.username IN ('$parent','$other');
INSERT INTO st_packages(title,slug,credit_price,status,family_practice_enabled,created_at,updated_at) VALUES('Paket uji kirim','$packageSlug',0,'published',1,UTC_TIMESTAMP(),UTC_TIMESTAMP());
INSERT INTO st_entitlements(user_id,package_id,status,starts_at,ends_at,created_at) SELECT u.id,p.id,'active',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY),UTC_TIMESTAMP() FROM st_users u JOIN st_packages p ON p.slug='$packageSlug' WHERE u.username IN ('$parent','$other');
COMMIT;
"@ | Out-Null
	$parentId = [int](Db "SELECT id FROM st_users WHERE username='$parent';")
	$childId = [int](Db "SELECT id FROM st_users WHERE username='$child';")
	$parentWeb = Login $parent; $otherWeb = Login $other; $childWeb = Login $child; $siblingWeb = Login $sibling
	$sessionId = CreateSession 'Latihan bilangan dari keluarga'
	$editPath = "keluarga/latihan/$sessionId"; $assignedPath = "latihan/keluarga/$sessionId"
	Equal (Status { Page $assignedPath $childWeb }) 404 'Draft must not be visible to the child.'
	if ((Page 'latihan/keluarga' $childWeb).Content -match 'Latihan bilangan dari keluarga') { throw 'Draft leaked into child inbox.' }
	$emptyId = CreateSession 'Sesi kosong' $false
	Equal (Status { Post "keluarga/latihan/$emptyId" $parentWeb (DeliveryBody $emptyId) }) 409 'Empty sessions cannot be sent.'
	$body = DeliveryBody $sessionId
	$bad = $body.Clone(); $bad.available_until='2026-02-30T10:00'
	Equal (Status { Post $editPath $parentWeb $bad }) 400 'Invalid calendar date must be rejected.'
	Equal (Db "SELECT status FROM st_practice_sessions WHERE id=$sessionId;") 'draft' 'Invalid calendar date must not send a session.'
	$bad = $body.Clone(); $bad.confirm='0'
	Equal (Status { Post $editPath $parentWeb $bad }) 400 'Delivery requires confirmation.'
	Db "UPDATE st_entitlements SET status='revoked' WHERE user_id=$parentId;" | Out-Null
	Equal (Status { Post $editPath $parentWeb $body }) 403 'Sending requires active package capability.'
	Db "UPDATE st_entitlements SET status='active' WHERE user_id=$parentId;" | Out-Null
	$scheduledFrom = LocalTime 1
	$body = DeliveryBody $sessionId $scheduledFrom
	Post $editPath $parentWeb $body | Out-Null
	Post $editPath $parentWeb $body | Out-Null
	Equal (Db "SELECT COUNT(*) FROM st_audit_events WHERE event_type='family.practice_delivered' AND entity_id=$sessionId;") '1' 'Repeated send must not duplicate audit or snapshots.'
	$expectedUtc = ([DateTime]::ParseExact($scheduledFrom,'yyyy-MM-ddTHH:mm',[Globalization.CultureInfo]::InvariantCulture)).AddHours(-7).ToString('yyyy-MM-dd HH:mm:ss')
	Equal (Db "SELECT available_from FROM st_practice_sessions WHERE id=$sessionId;") $expectedUtc 'WIB schedule must be stored as UTC.'
	Equal (Db "SELECT COUNT(*) FROM st_session_questions WHERE session_id=$sessionId AND snapshot_json IS NOT NULL;") '1' 'Sending must freeze the question snapshot.'
	Equal (Status { Post $editPath $parentWeb @{action='title';revision='2';title='No edit after send'} }) 409 'Sent session title must be immutable.'
	$sentQuestionId = [int](Db "SELECT question_id FROM st_session_questions WHERE session_id=$sessionId;")
	Equal (Status { Post $editPath $parentWeb @{action='delete';revision='2';question_id=$sentQuestionId;confirm='1'} }) 409 'Sent questions cannot be deleted from the session.'
	foreach ($path in @($assignedPath,"practice/assigned/$sessionId")) {
		Equal (Status { Page $path $siblingWeb }) 404 'Sibling must not read another child assignment.'
		$siblingCsrf = Hidden (Page 'beranda' $siblingWeb) 'study_csrf_token'
		Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$path) -WebSession $siblingWeb -Method Post -Body @{action='start';study_csrf_token=$siblingCsrf} }) 404 'Sibling must not start another child assignment.'
		Equal (Status { Page $path $parentWeb }) 403 'Parent must not impersonate child.'
	}
	$intro = Page $assignedPath $childWeb
	if ($intro.Content -match "$secretExplanation|is_correct|snapshot_json|Berapa hasil") { throw 'Assignment introduction leaked question data or keys.' }
	Equal (Status { Post $assignedPath $childWeb @{action='start'} }) 403 'Future schedule must block starting.'
	Equal (Db "SELECT COUNT(*) FROM st_practice_attempts WHERE session_id=$sessionId;") '0' 'Early start must not create an attempt.'
	Db "UPDATE st_practice_sessions SET available_from=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE) WHERE id=$sessionId;" | Out-Null
	Db "UPDATE st_entitlements SET status='revoked' WHERE user_id=$parentId;" | Out-Null
	Equal (Status { Post $assignedPath $childWeb @{action='start'} }) 403 'Starting also requires an active parent entitlement.'
	Db "UPDATE st_entitlements SET status='active' WHERE user_id=$parentId;" | Out-Null
	Browser $assignedPath $child 'family-assigned-intro'
	Browser 'latihan/keluarga' $child 'family-assigned-inbox'
	Invoke-PracticeTransactionProbe 'family_start' $sessionId $childId
	Post $assignedPath $childWeb @{action='start'} | Out-Null
	Post $assignedPath $childWeb @{action='start'} | Out-Null
	$attemptId = [int](Db "SELECT id FROM st_practice_attempts WHERE session_id=$sessionId;")
	$attemptPath = "latihan/percobaan/$attemptId"
	Invoke-PracticeTransactionProbe 'save' $attemptId $childId
	Equal (Db "SELECT COUNT(*) FROM st_practice_attempts WHERE session_id=$sessionId;") '1' 'Repeated start must reuse exactly one attempt.'
	Equal (Db "SELECT COUNT(*) FROM st_audit_events WHERE entity_id=$attemptId AND event_type='practice.started';") '1' 'Start replay must not duplicate audit.'
	if ((Page $attemptPath $childWeb).Content -match "$secretExplanation|is_correct|snapshot_json") { throw 'Active family attempt leaked keys.' }
	if ((Page $attemptPath $childWeb).Content -notmatch '<p class="eyebrow">Latihan keluarga</p>') { throw 'Assigned practice must clearly identify the family context.' }
	Equal (Status { Page ($editPath+'/hasil') $parentWeb }) 404 'Parent result must not expose live child answers.'
	$questionId = [int](Db "SELECT question_id FROM st_session_questions WHERE session_id=$sessionId;")
	$optionId = [int](Db "SELECT id FROM st_question_options WHERE question_id=$questionId AND is_correct=1;")
	$answer = @{action='save';revision='0';"answers[$questionId]"=$optionId}
	Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$attemptPath) -WebSession $childWeb -Method Post -Body @{action='finish';revision='0';study_csrf_token='invalid'} }) 403 'Invalid CSRF cannot finalize a family attempt.'
	Post $attemptPath $childWeb $answer | Out-Null
	Equal (Status { Post $attemptPath $childWeb $answer }) 409 'Family attempt rejects stale tab revision.'
	Equal (Db "SELECT CONCAT(answer_revision,'|',(SELECT COUNT(*) FROM st_practice_answers WHERE attempt_id=$attemptId AND is_correct IS NULL)) FROM st_practice_attempts WHERE id=$attemptId;") '1|1' 'Temporary answers remain ungraded.'
	$beforeBrowser = Db "SELECT * FROM st_practice_attempts WHERE id=$attemptId; SELECT * FROM st_practice_answers WHERE attempt_id=$attemptId ORDER BY id; SELECT * FROM st_session_questions WHERE session_id=$sessionId ORDER BY question_id; SELECT * FROM st_audit_events WHERE entity_type='practice_attempt' AND entity_id=$attemptId ORDER BY id;"
	Browser $attemptPath $child 'family-assigned-active'
	$afterBrowser = Db "SELECT * FROM st_practice_attempts WHERE id=$attemptId; SELECT * FROM st_practice_answers WHERE attempt_id=$attemptId ORDER BY id; SELECT * FROM st_session_questions WHERE session_id=$sessionId ORDER BY question_id; SELECT * FROM st_audit_events WHERE entity_type='practice_attempt' AND entity_id=$attemptId ORDER BY id;"
	if ($beforeBrowser -cne $afterBrowser) { throw 'Unsaved family browser checks changed answers, attempts, snapshots or audit.' }
	$csrf = Hidden (Page 'beranda' $childWeb) 'study_csrf_token'
	foreach ($change in @("UPDATE st_entitlements SET status='revoked' WHERE user_id=$parentId;","UPDATE st_entitlements SET ends_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE user_id=$parentId;","UPDATE st_practice_sessions SET available_until=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=$sessionId;","UPDATE st_family_links SET status='revoked' WHERE parent_user_id=$parentId AND child_user_id=$childId;")) {
		Db $change | Out-Null
		Equal (Status { Page $attemptPath $childWeb }) 403 'Revoked access or expired session must block active answers.'
		Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$attemptPath) -WebSession $childWeb -Method Post -Body @{action='finish';revision='1';study_csrf_token=$csrf} }) 403 'Server must reject final submit after access changes.'
		Equal (Db "SELECT CONCAT(status,'|',answer_revision) FROM st_practice_attempts WHERE id=$attemptId;") 'in_progress|1' 'Denied requests must retain state and saved answers.'
		Db "UPDATE st_entitlements SET status='active',ends_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY) WHERE user_id=$parentId; UPDATE st_practice_sessions SET available_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE id=$sessionId; UPDATE st_family_links SET status='active' WHERE parent_user_id=$parentId AND child_user_id=$childId;" | Out-Null
		$csrf = Hidden (Page 'beranda' $childWeb) 'study_csrf_token'
	}
	Db "UPDATE st_questions SET prompt='Changed source',explanation='Changed source explanation' WHERE id=$questionId; UPDATE st_question_options SET is_correct=IF(id=$optionId,0,1) WHERE question_id=$questionId;" | Out-Null
	$finish = @{action='finish';revision='1';"answers[$questionId]"=$optionId}
	Post $attemptPath $childWeb $finish | Out-Null
	Post $attemptPath $childWeb $finish | Out-Null
	Equal (Db "SELECT CONCAT(status,'|',score,'|',(SELECT COUNT(*) FROM st_audit_events WHERE entity_id=$attemptId AND event_type='practice.graded')) FROM st_practice_attempts WHERE id=$attemptId;") 'graded|100.00|1' 'Snapshot must grade correctly and final replay must be idempotent.'
	$result = Page ($editPath+'/hasil') $parentWeb
	$lockedPreview = Page $editPath $parentWeb
	if ($lockedPreview.Content -match 'Changed source' -or $lockedPreview.Content -notmatch $secretExplanation) { throw 'Parent preview after delivery must also use the frozen snapshot.' }
	Equal (Status { Post ($editPath+'/hasil') $parentWeb @{action='finish'} }) 405 'Parent results must be read-only on POST.'
	if ($result.Content -notmatch $secretExplanation -or $result.Content -match 'Changed source|name="answers\[' -or $result.Content -notmatch 'Pilihan anak') { throw 'Parent must receive frozen final result read-only.' }
	foreach ($path in @(($editPath+'/hasil'),"parent_practice/result/$sessionId")) { Equal (Status { Page $path $otherWeb }) 404 'Another parent of the same child must not see creator-private results.' }
	Equal (Status { Page $attemptPath $parentWeb }) 404 'Parent cannot read learner route.'
	Equal (Status { Post $editPath $parentWeb @{action='withdraw';revision='2';confirm='1'} }) 409 'Completed sessions cannot be cancelled.'
	Browser ($editPath+'/hasil') $parent 'family-assigned-result'
	Browser $editPath $parent 'family-delivery-status'
	Db "UPDATE st_entitlements SET status='revoked' WHERE user_id=$parentId; UPDATE st_family_links SET status='revoked' WHERE parent_user_id=$parentId AND child_user_id=$childId;" | Out-Null
	Equal (Status { Page $attemptPath $childWeb }) 200 'Child retains own completed history after relationship/package changes.'
	Equal (Status { Page ($editPath+'/hasil') $parentWeb }) 404 'Revoked creator link removes parent report access.'
	Db "UPDATE st_family_links SET status='active' WHERE parent_user_id=$parentId AND child_user_id=$childId;" | Out-Null
	Equal (Status { Page ($editPath+'/hasil') $parentWeb }) 200 'Linked creator retains completed history after entitlement expiry.'
	Db "UPDATE st_entitlements SET status='active' WHERE user_id=$parentId;" | Out-Null
	$cancelId = CreateSession 'Latihan yang dibatalkan'
	Post "keluarga/latihan/$cancelId" $parentWeb (DeliveryBody $cancelId) | Out-Null
	Post "latihan/keluarga/$cancelId" $childWeb @{action='start'} | Out-Null
	$cancelAttempt = [int](Db "SELECT id FROM st_practice_attempts WHERE session_id=$cancelId;")
	Post "latihan/percobaan/$cancelAttempt" $childWeb @{action='save';revision='0'} | Out-Null
	Db "UPDATE st_entitlements SET status='revoked' WHERE user_id=$parentId;" | Out-Null
	Post "keluarga/latihan/$cancelId" $parentWeb @{action='withdraw';revision='2';confirm='1'} | Out-Null
	Post "keluarga/latihan/$cancelId" $parentWeb @{action='withdraw';revision='2';confirm='1'} | Out-Null
	Equal (Db "SELECT CONCAT(s.status,'|',a.status,'|',a.answer_revision) FROM st_practice_sessions s JOIN st_practice_attempts a ON a.session_id=s.id WHERE s.id=$cancelId;") 'cancelled|abandoned|1' 'Cancellation keeps saved answers but closes the attempt.'
	Equal (Status { Page "latihan/percobaan/$cancelAttempt" $childWeb }) 403 'Cancelled attempt cannot be continued.'
	Equal (Status { Post "latihan/keluarga/$cancelId" $childWeb @{action='start'} }) 403 'Cancelled session cannot restart.'
	if ((Page 'latihan' $childWeb).Content -match ('href="[^"]*latihan/percobaan/'+$cancelAttempt+'"')) { throw 'Unavailable attempt must not offer a continue link.' }
	. (Join-Path $PSScriptRoot 'family-copy-cases.ps1')
	Write-Output 'PASS family delivery schedule, immutable snapshots, single start, save/final replay, private creator results, expiry/revocation mid-attempt, and cancellation.'
}
finally {
	Db @"
START TRANSACTION;
DELETE a FROM st_practice_answers a JOIN st_practice_attempts t ON t.id=a.attempt_id JOIN st_users u ON u.id=t.learner_user_id WHERE u.username IN ($names);
DELETE t FROM st_practice_attempts t JOIN st_users u ON u.id=t.learner_user_id WHERE u.username IN ($names);
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
	Write-Output 'PASS all temporary delivery users, package, entitlements, links, private sessions/questions, attempts, answers and audit rows cleaned.'
}
