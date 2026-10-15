param(
	[Parameter(Mandatory = $true)][string] $Php81Path,
	[Parameter(Mandatory = $true)][string] $MysqlPath,
	[Parameter(Mandatory = $true)][string] $BaseUrl,
	[string] $ChromePath = '',
	[switch] $CaptureScreenshots
)

$ErrorActionPreference = 'Stop'
$parsedUrl = [Uri]$BaseUrl
if ($parsedUrl.Scheme -ne 'http' -or -not $parsedUrl.IsLoopback -or $parsedUrl.Query -or $parsedUrl.Fragment -or $parsedUrl.UserInfo) { throw 'Practice QA requires an HTTP loopback URL.' }
if ($CaptureScreenshots -and -not $ChromePath) { throw 'CaptureScreenshots requires ChromePath.' }
$root = $BaseUrl.TrimEnd('/') + '/'
$suffix = [Guid]::NewGuid().ToString('N').Substring(0, 12)
$editor = "qa_practice_editor_$suffix"
$reviewer = "qa_practice_reviewer_$suffix"
$learner = "qa_practice_child_$suffix"
$other = "qa_practice_other_$suffix"
$parent = "qa_practice_parent_$suffix"
$otherParent = "qa_practice_stranger_$suffix"
$emptyChild = "qa_practice_empty_$suffix"
$usernames = "'$editor','$reviewer','$learner','$other','$parent','$otherParent','$emptyChild'"
$courseSlug = "qa-practice-$suffix"
$lessonSlug = "qa-practice-lesson-$suffix"
$secretExplanation = "Pembahasan_privat_$suffix"
$password = 'Temporary-Study-Practice-123!'
$passwordHash = (& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim().Replace("'", "''")
if ($LASTEXITCODE -ne 0) { throw 'Could not generate a synthetic password hash.' }

function Invoke-Db([string] $Sql) {
	$result = & $MysqlPath '-uroot' '-D' 'db_study' '-N' '-B' '-e' $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Practice QA SQL failed: $result" }
	return ($result -join "`n").Trim()
}
function Get-Page([string] $Path, $Web) {
	return Invoke-WebRequest -UseBasicParsing -Uri ($root + $Path) -WebSession $Web
}
function Get-Hidden($Page, [string] $Name) {
	$match = [regex]::Match($Page.Content, 'name="' + [regex]::Escape($Name) + '"\s+value="([^"]*)"')
	if (-not $match.Success) { throw "Missing form field $Name." }
	return [System.Net.WebUtility]::HtmlDecode($match.Groups[1].Value)
}
function Send-Form([string] $Path, $Web, [hashtable] $Body) {
	$page = Get-Page $Path $Web
	$Body['study_csrf_token'] = Get-Hidden $page 'study_csrf_token'
	return Invoke-WebRequest -UseBasicParsing -Uri ($root + $Path) -Method Post -WebSession $Web -Body $Body
}
function Get-Status([scriptblock] $Request) {
	try { return [int] ((& $Request).StatusCode) }
	catch { if ($_.Exception.Response) { return [int] $_.Exception.Response.StatusCode }; throw }
}
function Assert-Equal($Actual, $Expected, [string] $Message) {
	if ($Actual -ne $Expected) { throw "$Message (expected $Expected, got $Actual)" }
}
function New-Login([string] $Identity) {
	$web = New-Object Microsoft.PowerShell.Commands.WebRequestSession
	Send-Form 'masuk' $web @{identity=$Identity; password=$password} | Out-Null
	return $web
}
function Invoke-PracticeTransactionProbe([string] $Mode, [int] $TargetId, [int] $LearnerId) {
	if ($Mode -notin @('course_start','save') -or $TargetId -lt 1 -or $LearnerId -lt 1) { throw 'Invalid practice transaction probe target.' }
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
		if ($LASTEXITCODE -ne 0) { throw 'Practice transaction probe failed; fixture cleanup remains with this suite.' }
	}
	finally {
		foreach ($name in $environmentNames) { [Environment]::SetEnvironmentVariable($name,$previous[$name],'Process') }
		Pop-Location
	}
}
function Invoke-Browser([string] $Path, [string] $Identity, [string] $Name) {
	if (-not $ChromePath) { return }
	$oldIdentity = [Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY','Process')
	$oldPassword = [Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD','Process')
	try {
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY',$Identity,'Process')
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD',$password,'Process')
		$arguments = @((Join-Path $PSScriptRoot 'browser-viewport-check.mjs'),'--base-url',$root,'--chrome',$ChromePath,'--extra-path',$Path,'--extra-name',$Name,'--only-extra-route')
		if ($Name -eq 'practice-active') { $arguments += '--exercise-practice-unsaved' }
		if ($CaptureScreenshots) { $arguments += '--capture-screenshots' }
		& node @arguments
		if ($LASTEXITCODE -ne 0) { throw "Browser practice check failed for $Name." }
	}
	finally {
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY',$oldIdentity,'Process')
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD',$oldPassword,'Process')
	}
}

try {
	$setup = @"
START TRANSACTION;
INSERT INTO st_users (username,email,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES
('$editor','$editor@example.test','$passwordHash','Tim Penulis Latihan','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$reviewer','$reviewer@example.test','$passwordHash','Tim Peninjau Latihan','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$learner',NULL,'$passwordHash','Peserta Uji','child','primary','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$other','$other@example.test','$passwordHash','Editor Lain','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$parent','$parent@example.test','$passwordHash','Orang Tua Peserta','parent','unset','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$otherParent','$otherParent@example.test','$passwordHash','Orang Tua Lain','parent','unset','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$emptyChild',NULL,'$passwordHash','Adik Peserta','child','primary','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
INSERT INTO st_family_links(parent_user_id,child_user_id,status,created_at,updated_at)
SELECT p.id,c.id,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_users p JOIN st_users c ON c.username IN ('$learner','$emptyChild') WHERE p.username='$parent';
SET @editor=(SELECT id FROM st_users WHERE username='$editor');
SET @reviewer=(SELECT id FROM st_users WHERE username='$reviewer');
SET @other=(SELECT id FROM st_users WHERE username='$other');
INSERT INTO st_user_roles(user_id,role_id,granted_by_user_id,created_at) VALUES
(@editor,(SELECT id FROM st_roles WHERE role_code='content_editor'),@editor,UTC_TIMESTAMP()),
(@reviewer,(SELECT id FROM st_roles WHERE role_code='system_admin'),@reviewer,UTC_TIMESTAMP()),
(@other,(SELECT id FROM st_roles WHERE role_code='content_editor'),@other,UTC_TIMESTAMP());
INSERT INTO st_courses(author_user_id,updated_by_user_id,title,slug,summary,learning_objectives,target_group,status,content_version,rights_status,source_note,is_free,created_at,updated_at)
VALUES (@editor,@editor,'Latihan Bilangan Dasar','$courseSlug','Materi sintetis untuk menguji latihan.','Mengenali penjumlahan bilangan.','primary','draft',1,'owned','Karya sintetis pengujian',1,UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @course=LAST_INSERT_ID();
INSERT INTO st_course_sections(course_id,title,sort_order,created_at,updated_at) VALUES(@course,'Bilangan',1,UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @section=LAST_INSERT_ID();
INSERT INTO st_lessons(section_id,title,slug,body_text,estimated_minutes,sort_order,status,content_version,is_preview,source_note,rights_status,created_at,updated_at)
VALUES(@section,'Mengenal Bilangan','$lessonSlug','Pelajaran sintetis untuk pengujian latihan.',3,1,'draft',1,0,'Karya sintetis pengujian','owned',UTC_TIMESTAMP(),UTC_TIMESTAMP());
COMMIT;
SELECT @course;
"@
	$courseId = [int](Invoke-Db $setup)
	$learnerId = [int](Invoke-Db "SELECT id FROM st_users WHERE username='$learner';")
	$editPath = "kelola/kursus/$courseId/soal"
	$coursePath = "latihan/kursus/$courseId"
	$editorWeb = New-Login $editor
	$reviewerWeb = New-Login $reviewer
	$otherWeb = New-Login $other
	$learnerWeb = New-Login $learner
	$parentWeb = New-Login $parent
	$otherParentWeb = New-Login $otherParent
	$emptyChildId = [int](Invoke-Db "SELECT id FROM st_users WHERE username='$emptyChild';")
	$parentId = [int](Invoke-Db "SELECT id FROM st_users WHERE username='$parent';")
	$reportPath = "keluarga/anak/$learnerId/latihan"
	$emptyReport = Get-Page "keluarga/anak/$emptyChildId/latihan" $parentWeb
	if ($emptyReport.Content -notmatch 'Belum ada latihan mandiri') { throw 'Empty child report must explain how to begin.' }
	if ((Get-Page 'beranda' $parentWeb).Content -notmatch "keluarga/anak/$learnerId/latihan") { throw 'Parent dashboard must link the child practice report.' }
	foreach ($path in @($reportPath,"family/practice/$learnerId")) {
		Assert-Equal (Get-Status { Get-Page $path $otherParentWeb }) 404 'Unlinked parent must not read child report.'
		foreach ($web in @($learnerWeb,$editorWeb,$reviewerWeb)) {
			Assert-Equal (Get-Status { Get-Page $path $web }) 403 'Non-parent roles must not use parent reports.'
		}
	}
	foreach ($path in @($editPath,"questions/index/$courseId")) {
		Assert-Equal (Get-Status { Get-Page $path $otherWeb }) 403 'Unrelated editor must not read question drafts.'
		Assert-Equal (Get-Status { Get-Page $path $learnerWeb }) 403 'Learner must not read the answer-key editor.'
		$deniedCsrf = Get-Hidden (Get-Page 'beranda' $otherWeb) 'study_csrf_token'
		Assert-Equal (Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$path) -Method Post -WebSession $otherWeb -Body @{action='delete';question_id='1';confirm_delete='1';study_csrf_token=$deniedCsrf} }) 403 'Unrelated editor must not mutate another course question.'
	}
	Assert-Equal (Get-Status { Get-Page $coursePath $learnerWeb }) 404 'Draft course practice must not be available.'
	$invalid = @{action='save'; question_id='0'; 'prompt[]'='unsafe'; correct='0'}
	Assert-Equal (Get-Status { Send-Form $editPath $editorWeb $invalid }) 400 'Array-shaped question fields must be rejected.'
	$invalidPage = Send-Form $editPath $editorWeb @{action='save'; question_id='0'; prompt=''; explanation=''; source_note=''; correct=''; rights_status=''}
	if ($invalidPage.Content -notmatch 'aria-invalid="true"' -or $invalidPage.Content -notmatch 'field-error-prompt') { throw 'Question validation errors are not accessible.' }
	foreach ($number in 1..4) {
		$form = @{action='save'; question_id='0'; prompt="Soal $number - Berapa hasil $number + 1?"; explanation="$secretExplanation - Tambahkan satu pada $number."; source_note='Karya sintetis pengujian'; rights_status='owned'; correct='0'; option_0=[string]($number+1); option_1=[string]($number+2); option_2=[string]($number+3); option_3=[string]($number+4)}
		Send-Form $editPath $editorWeb $form | Out-Null
	}
	$questionIds = @((Invoke-Db "SELECT id FROM st_questions WHERE course_id=$courseId ORDER BY id;") -split "\s+" | ForEach-Object { [int]$_ })
	Assert-Equal $questionIds.Count 4 'Four valid draft questions must be created.'
	$lastId = $questionIds[3]
	Send-Form $editPath $editorWeb @{action='delete'; question_id="$lastId"; confirm_delete='1'} | Out-Null
	$questionIds = $questionIds[0..2]
	$firstId = $questionIds[0]
	$firstEdit = @{action='save'; question_id="$firstId"; prompt='Soal 1 - Berapa hasil 1 + 1? <img src=x onerror=alert(1)>'; explanation="$secretExplanation - Dua benda ditambah dua? Periksa satu ditambah satu menjadi dua."; source_note='Karya sintetis pengujian'; rights_status='owned'; correct='0'; option_0='2'; option_1='3'; option_2='4'; option_3='5'}
	Send-Form $editPath $editorWeb $firstEdit | Out-Null
	$questionPage = Get-Page $editPath $editorWeb
	if ($questionPage.Content -match '<img src=x onerror=alert\(1\)>') { throw 'Question editor rendered executable markup.' }
	$duplicate = $firstEdit.Clone(); $duplicate['option_1']='2'
	Send-Form $editPath $editorWeb $duplicate | Out-Null
	Assert-Equal (Invoke-Db "SELECT COUNT(*) FROM st_question_options WHERE question_id=$firstId AND option_text='2';") '1' 'Duplicate options must not overwrite a valid question.'
	Invoke-Browser $editPath $editor 'question-editor'
	$optionRows = @((Invoke-Db "SELECT question_id,id,is_correct FROM st_question_options WHERE question_id IN ($($questionIds -join ',')) ORDER BY question_id,sort_order;") -split "`n")
	$correctOptions = @{}; $wrongOptions = @{}
	foreach ($row in $optionRows) { $parts=$row -split "\s+"; if ($parts[2] -eq '1') {$correctOptions[[int]$parts[0]]=$parts[1]} else {$wrongOptions[[int]$parts[0]]=$parts[1]} }
	Invoke-Db "UPDATE st_question_options SET is_correct=1 WHERE question_id=$firstId;" | Out-Null
	$csrfPage = Get-Page "kelola/kursus/$courseId" $editorWeb
	Invoke-WebRequest -UseBasicParsing -Uri ($root+"kelola/kursus/$courseId/ajukan") -Method Post -WebSession $editorWeb -Body @{study_csrf_token=(Get-Hidden $csrfPage 'study_csrf_token')} | Out-Null
	Assert-Equal (Invoke-Db "SELECT status FROM st_courses WHERE id=$courseId;") 'draft' 'Review submission must reject multiple answer keys.'
	Invoke-Db "UPDATE st_question_options SET is_correct=IF(id=$($correctOptions[$firstId]),1,0) WHERE question_id=$firstId;" | Out-Null
	$csrfPage = Get-Page "kelola/kursus/$courseId" $editorWeb
	Invoke-WebRequest -UseBasicParsing -Uri ($root+"kelola/kursus/$courseId/ajukan") -Method Post -WebSession $editorWeb -Body @{study_csrf_token=(Get-Hidden $csrfPage 'study_csrf_token')} | Out-Null
	Assert-Equal (Invoke-Db "SELECT COUNT(*) FROM st_questions WHERE course_id=$courseId AND status='review';") '3' 'Question status must follow course review.'
	$reviewQuestions = Get-Page $editPath $reviewerWeb
	if ($reviewQuestions.Content -notmatch $secretExplanation -or $reviewQuestions.Content -notmatch 'Jawaban benar') { throw 'Reviewer cannot inspect question keys and explanations.' }
	$csrfPage = Get-Page "kelola/kursus/$courseId" $reviewerWeb
	Invoke-WebRequest -UseBasicParsing -Uri ($root+"kelola/kursus/$courseId/tinjau") -Method Post -WebSession $reviewerWeb -Body @{decision='changes_requested'; notes='Periksa kembali penjelasan'; study_csrf_token=(Get-Hidden $csrfPage 'study_csrf_token')} | Out-Null
	Assert-Equal (Invoke-Db "SELECT COUNT(*) FROM st_questions WHERE course_id=$courseId AND status='draft';") '3' 'A revision request must return course questions to draft.'
	$csrfPage = Get-Page "kelola/kursus/$courseId" $editorWeb
	Invoke-WebRequest -UseBasicParsing -Uri ($root+"kelola/kursus/$courseId/ajukan") -Method Post -WebSession $editorWeb -Body @{study_csrf_token=(Get-Hidden $csrfPage 'study_csrf_token')} | Out-Null
	$csrfPage = Get-Page "kelola/kursus/$courseId" $reviewerWeb
	Invoke-WebRequest -UseBasicParsing -Uri ($root+"kelola/kursus/$courseId/tinjau") -Method Post -WebSession $reviewerWeb -Body @{decision='approved'; notes='QA'; study_csrf_token=(Get-Hidden $csrfPage 'study_csrf_token')} | Out-Null
	Assert-Equal (Invoke-Db "SELECT COUNT(*) FROM st_questions WHERE course_id=$courseId AND status='published' AND reviewed_by_user_id IS NOT NULL;") '3' 'Approved questions must be published with their reviewer.'
	Assert-Equal (Get-Status { Send-Form $editPath $editorWeb $firstEdit }) 403 'Published question content must be immutable.'
	Write-Output 'PASS question create/edit/delete, validation, draft ownership, review completeness, and immutable publication.'
	Invoke-Browser $editPath $reviewer 'question-review'

	$landing = Get-Page $coursePath $learnerWeb
	$firstToken = Get-Hidden $landing 'start_token'
	Assert-Equal (Get-Status { Send-Form $coursePath $learnerWeb @{start_token='forged'} }) 400 'A forged start token must be rejected.'
	Invoke-Browser $coursePath $learner 'practice-intro'
	Invoke-PracticeTransactionProbe 'course_start' $courseId $learnerId
	$start = @{start_token=$firstToken}
	Send-Form $coursePath $learnerWeb $start | Out-Null
	$attemptId = [int](Invoke-Db "SELECT MAX(id) FROM st_practice_attempts WHERE learner_user_id=$learnerId;")
	$attemptPath = "latihan/percobaan/$attemptId"
	Invoke-PracticeTransactionProbe 'save' $attemptId $learnerId
	Send-Form $coursePath $learnerWeb @{start_token=$firstToken} | Out-Null
	$secondToken = Get-Hidden (Get-Page $coursePath $learnerWeb) 'start_token'
	Send-Form $coursePath $learnerWeb @{start_token=$secondToken} | Out-Null
	Assert-Equal (Invoke-Db "SELECT COUNT(*) FROM st_practice_attempts WHERE learner_user_id=$learnerId;") '1' 'Duplicate starts and new tab starts must reuse the active attempt.'
	$attemptPage = Get-Page $attemptPath $learnerWeb
	if ($attemptPage.Content -notmatch 'id="practice-answer-form"[^>]*data-unsaved-form' -or $attemptPage.Content -notmatch 'data-unsaved-initial="false"' -or $attemptPage.Content -notmatch 'data-unsaved-status role="status" aria-live="polite" hidden' -or $attemptPage.Content -notmatch 'assets/js/unsaved-form\.js\?v=[0-9]+') { throw 'Active practice must opt into the accessible shared unsaved guard.' }
	$familyResultPath = "$reportPath/$attemptId"
	$activeReport = Get-Page $reportPath $parentWeb
	if ($activeReport.Content -notmatch '1 masih berjalan' -or $activeReport.Content -match "$secretExplanation|is_correct|snapshot_json|Soal 1|Lihat hasil dan pembahasan") { throw 'Parent active report must only reveal activity status, never answers or explanations.' }
	Assert-Equal (Get-Status { Get-Page $familyResultPath $parentWeb }) 404 'Even linked parents must not open active answer details.'
	Assert-Equal (Get-Status { Get-Page "family/practice_result/$learnerId/$attemptId" $parentWeb }) 404 'Direct controller path must also hide active answers.'
	Assert-Equal (Get-Status { Get-Page $attemptPath $parentWeb }) 404 'Parent must not impersonate the child on learner routes.'
	if ($attemptPage.Content -match "$secretExplanation|is_correct|snapshot_json|<img src=x onerror=alert\(1\)>") { throw 'Active attempt leaked answer keys/explanation or executable markup.' }
	foreach ($web in @($otherWeb,$editorWeb,$reviewerWeb)) {
		Assert-Equal (Get-Status { Get-Page $attemptPath $web }) 404 'Another account must not read the attempt, including admin.'
		$csrfPage = Get-Page 'beranda' $web
		Assert-Equal (Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$attemptPath) -Method Post -WebSession $web -Body @{revision='0'; action='finish'; study_csrf_token=(Get-Hidden $csrfPage 'study_csrf_token')} }) 404 'Another account must not submit an attempt.'
	}
	$save = @{revision='0'; action='save'; "answers[$firstId]"=$correctOptions[$firstId]}
	Send-Form $attemptPath $learnerWeb $save | Out-Null
	Assert-Equal (Invoke-Db "SELECT answer_revision FROM st_practice_attempts WHERE id=$attemptId;") '1' 'Temporary save must advance revision.'
	Assert-Equal (Invoke-Db "SELECT COUNT(*) FROM st_practice_answers WHERE attempt_id=$attemptId AND selected_option_id=$($correctOptions[$firstId]) AND is_correct IS NULL;") '1' 'Saved answer must persist without exposing a grade.'
	Assert-Equal (Get-Status { Send-Form $attemptPath $learnerWeb $save }) 409 'Stale tab must not overwrite newer answers.'
	Assert-Equal (Get-Status { Send-Form $attemptPath $learnerWeb @{revision='1'; action='save'; "answers[$firstId]"=$correctOptions[$questionIds[1]]} }) 400 'Option from another question must be rejected.'
	Assert-Equal (Get-Status { Send-Form $attemptPath $learnerWeb @{revision='1'; action='save'; "answers[$firstId][]"='bad'} }) 400 'Nested answer arrays must be rejected.'
	Assert-Equal (Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$attemptPath) -Method Post -WebSession $learnerWeb -Body @{revision='1'; action='finish'; study_csrf_token='invalid'} }) 403 'Invalid CSRF must not grade an attempt.'
	Invoke-Db "UPDATE st_courses SET is_free=0 WHERE id=$courseId;" | Out-Null
	Assert-Equal (Get-Status { Get-Page $attemptPath $learnerWeb }) 403 'Revoked course access must block continuing the attempt.'
	$revokedCsrf = Get-Hidden (Get-Page 'beranda' $learnerWeb) 'study_csrf_token'
	Assert-Equal (Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$attemptPath) -Method Post -WebSession $learnerWeb -Body @{revision='1';action='finish';study_csrf_token=$revokedCsrf} }) 403 'Revoked access must also block answer submission.'
	Assert-Equal (Invoke-Db "SELECT CONCAT(status,'|',answer_revision) FROM st_practice_attempts WHERE id=$attemptId;") 'in_progress|1' 'Denied submission must leave saved answers and state intact.'
	Invoke-Db "UPDATE st_courses SET is_free=1 WHERE id=$courseId;" | Out-Null
	foreach ($closedStatus in @('submitted','abandoned')) {
		Invoke-Db "UPDATE st_practice_attempts SET status='$closedStatus' WHERE id=$attemptId;" | Out-Null
		Assert-Equal (Get-Status { Get-Page $attemptPath $learnerWeb }) 403 'Non-active non-final attempts must not expose an editable form.'
		$closedCsrf = Get-Hidden (Get-Page 'beranda' $learnerWeb) 'study_csrf_token'
		Assert-Equal (Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$attemptPath) -Method Post -WebSession $learnerWeb -Body @{revision='1';action='save';study_csrf_token=$closedCsrf} }) 403 'Non-active attempts must reject POST before editing.'
		Assert-Equal (Invoke-Db "SELECT CONCAT(status,'|',answer_revision) FROM st_practice_attempts WHERE id=$attemptId;") ($closedStatus+'|1') 'Denied terminal attempt must retain its state.'
	}
	Invoke-Db "UPDATE st_practice_attempts SET status='in_progress' WHERE id=$attemptId;" | Out-Null
	$beforeBrowser = Invoke-Db "SELECT * FROM st_practice_attempts WHERE id=$attemptId; SELECT * FROM st_practice_answers WHERE attempt_id=$attemptId ORDER BY id; SELECT * FROM st_session_questions WHERE session_id=(SELECT session_id FROM st_practice_attempts WHERE id=$attemptId) ORDER BY question_id; SELECT * FROM st_audit_events WHERE entity_type='practice_attempt' AND entity_id=$attemptId ORDER BY id;"
	Invoke-Browser $attemptPath $learner 'practice-active'
	$afterBrowser = Invoke-Db "SELECT * FROM st_practice_attempts WHERE id=$attemptId; SELECT * FROM st_practice_answers WHERE attempt_id=$attemptId ORDER BY id; SELECT * FROM st_session_questions WHERE session_id=(SELECT session_id FROM st_practice_attempts WHERE id=$attemptId) ORDER BY question_id; SELECT * FROM st_audit_events WHERE entity_type='practice_attempt' AND entity_id=$attemptId ORDER BY id;"
	if ($beforeBrowser -cne $afterBrowser) { throw 'Unsaved browser interactions or rejected POST changed attempt, answers, snapshots or audit.' }
	# Bypass the authoring guard to prove grading and display rely on the immutable snapshot.
	Invoke-Db "UPDATE st_questions SET prompt='Changed after start',explanation='Changed explanation' WHERE id=$firstId; UPDATE st_question_options SET is_correct=IF(id=$($wrongOptions[$firstId]),1,0) WHERE question_id=$firstId;" | Out-Null
	$finish = @{revision='1'; action='finish'; "answers[$firstId]"=$correctOptions[$firstId]}
	Send-Form $attemptPath $learnerWeb $finish | Out-Null
	Assert-Equal (Invoke-Db "SELECT CONCAT(status,'|',score,'|',correct_count,'|',question_count) FROM st_practice_attempts WHERE id=$attemptId;") 'graded|33.33|1|3' 'Grading must use snapshot keys, count blank answers, and round 1/3 correctly.'
	Send-Form $attemptPath $learnerWeb @{revision='1'; action='finish'} | Out-Null
	Assert-Equal (Invoke-Db "SELECT CONCAT(score,'|',(SELECT COUNT(*) FROM st_audit_events WHERE event_type='practice.graded' AND entity_id=$attemptId)) FROM st_practice_attempts WHERE id=$attemptId;") '33.33|1' 'Repeated final submit must not change score or duplicate grading audit.'
	$resultPage = Get-Page $attemptPath $learnerWeb
	if ($resultPage.Content -match 'data-unsaved-form|assets/js/unsaved-form\.js|practice-answer-form') { throw 'Final results must not load an editing guard or form.' }
	if ($resultPage.Content -notmatch $secretExplanation -or $resultPage.Content -match 'Changed after start|Changed explanation' -or $resultPage.Content -match 'name="answers\[') { throw 'Final review did not preserve original content or remained editable.' }
	Assert-Equal (Get-Status { Get-Page $attemptPath $otherWeb }) 404 'Completed result must remain private.'
	Invoke-Browser $attemptPath $learner 'practice-result'
	$familyResult = Get-Page $familyResultPath $parentWeb
	if ($familyResult.Content -match 'data-unsaved-form|assets/js/unsaved-form\.js|practice-answer-form') { throw 'Parent report must remain read-only without an unsaved guard.' }
	if ($familyResult.Content -notmatch '33,33%' -or $familyResult.Content -notmatch $secretExplanation -or $familyResult.Content -notmatch 'Pilihan anak' -or $familyResult.Content -match 'name="answers\[|Coba latihan lagi|Changed explanation') { throw 'Parent final result must display immutable snapshot read-only with correct wording.' }
	if ($familyResult.Headers['Cache-Control'] -notmatch 'no-store') { throw 'Private family results must not be cached.' }
	if ((Get-Page $reportPath $parentWeb).Headers['Cache-Control'] -notmatch 'no-store') { throw 'Private family history must not be cached.' }
	foreach ($path in @($familyResultPath,"family/practice_result/$learnerId/$attemptId")) {
		Assert-Equal (Get-Status { Get-Page $path $otherParentWeb }) 404 'Unlinked parent must not read result via either route.'
		$reportCsrf = Get-Hidden (Get-Page 'beranda' $parentWeb) 'study_csrf_token'
		Assert-Equal (Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$path) -Method Post -WebSession $parentWeb -Body @{action='finish';revision='0';study_csrf_token=$reportCsrf} }) 405 'Parent report must reject mutation methods.'
	}
	Assert-Equal (Get-Status { Get-Page "keluarga/anak/$emptyChildId/latihan/$attemptId" $parentWeb }) 404 'Attempt must belong to the selected child, even for siblings.'
	Assert-Equal (Get-Status { Get-Page "keluarga/anak/$learnerId/latihan/0" $parentWeb }) 404 'Unknown result must not reveal another attempt.'
	$reportCsrf = Get-Hidden (Get-Page 'beranda' $parentWeb) 'study_csrf_token'
	Assert-Equal (Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$reportPath) -Method Post -WebSession $parentWeb -Body @{study_csrf_token=$reportCsrf} }) 405 'History report must reject POST too.'
	Invoke-Db "UPDATE st_practice_attempts SET learner_user_id=$emptyChildId WHERE id=$attemptId;" | Out-Null
	Assert-Equal (Get-Status { Get-Page $familyResultPath $parentWeb }) 404 'Inconsistent attempt/session ownership must fail closed.'
	Assert-Equal (Get-Status { Get-Page "keluarga/anak/$emptyChildId/latihan/$attemptId" $parentWeb }) 404 'Session ownership cannot be bypassed via a linked sibling.'
	Invoke-Db "UPDATE st_practice_attempts SET learner_user_id=$learnerId WHERE id=$attemptId;" | Out-Null
	Invoke-Browser $familyResultPath $parent 'family-practice-result'
	Send-Form $coursePath $learnerWeb @{start_token=$firstToken} | Out-Null
	Assert-Equal (Invoke-Db "SELECT COUNT(*) FROM st_practice_attempts WHERE learner_user_id=$learnerId;") '1' 'Replayed old start token after grading must not create a new attempt.'
	Invoke-Db "UPDATE st_question_options SET is_correct=IF(id=$($correctOptions[$firstId]),1,0) WHERE question_id=$firstId;" | Out-Null
	foreach ($mode in @('perfect','blank')) {
		$token = Get-Hidden (Get-Page $coursePath $learnerWeb) 'start_token'
		Send-Form $coursePath $learnerWeb @{start_token=$token} | Out-Null
		$newAttempt = [int](Invoke-Db "SELECT MAX(id) FROM st_practice_attempts WHERE learner_user_id=$learnerId;")
		$body = @{revision='0'; action='finish'}
		if ($mode -eq 'perfect') { foreach ($id in $questionIds) { $body["answers[$id]"]=$correctOptions[$id] } }
		Send-Form "latihan/percobaan/$newAttempt" $learnerWeb $body | Out-Null
		$expected = if ($mode -eq 'perfect') {'100.00'} else {'0.00'}
		Assert-Equal (Invoke-Db "SELECT score FROM st_practice_attempts WHERE id=$newAttempt;") $expected 'Perfect/blank attempts must grade correctly.'
	}
	$history = Get-Page 'latihan?halaman[]=9' $learnerWeb
	if ($history.Content -notmatch '33,33%' -or $history.Content -notmatch '100,00%' -or $history.Content -notmatch '0,00%') { throw 'History does not retain all attempts.' }
	$otherHistory = Get-Page 'latihan' $otherWeb
	if ($otherHistory.Content -match 'Latihan Bilangan Dasar') { throw 'Attempt history leaked to another user.' }
	foreach ($extraAttempt in 1..8) {
		$token = Get-Hidden (Get-Page $coursePath $learnerWeb) 'start_token'
		Send-Form $coursePath $learnerWeb @{start_token=$token} | Out-Null
		$extraId = [int](Invoke-Db "SELECT MAX(id) FROM st_practice_attempts WHERE learner_user_id=$learnerId;")
		Send-Form "latihan/percobaan/$extraId" $learnerWeb @{revision='0';action='finish'} | Out-Null
	}
	$historyFirst = Get-Page 'latihan' $learnerWeb
	$historyLast = Get-Page 'latihan?halaman=9999' $learnerWeb
	Assert-Equal ([regex]::Matches($historyFirst.Content,'href="[^"]*latihan/percobaan/[0-9]+"').Count) 10 'History first page must contain only ten attempts.'
	Assert-Equal ([regex]::Matches($historyLast.Content,'href="[^"]*latihan/percobaan/[0-9]+"').Count) 1 'Out-of-range history page must clamp to the last page.'
	if ($historyLast.Content -notmatch '33,33%') { throw 'History pagination must retain the oldest attempt.' }
	Invoke-Browser 'latihan' $learner 'practice-history'
	$familyHistoryFirst = Get-Page ($reportPath+'?halaman[]=9') $parentWeb
	$familyHistoryLast = Get-Page ($reportPath+'?halaman=9999') $parentWeb
	Assert-Equal ([regex]::Matches($familyHistoryFirst.Content,'href="[^"]*keluarga/anak/[0-9]+/latihan/[0-9]+"').Count) 10 'Family history first page must contain ten results.'
	Assert-Equal ([regex]::Matches($familyHistoryLast.Content,'href="[^"]*keluarga/anak/[0-9]+/latihan/[0-9]+"').Count) 1 'Family history last page must contain the remaining result.'
	if ($familyHistoryLast.Content -notmatch '33,33%' -or $familyHistoryFirst.Content -notmatch '11 percobaan selesai' -or $familyHistoryFirst.Content -notmatch '0 masih berjalan') { throw 'Family report totals and oldest result must match the 11 source attempts.' }
	Assert-Equal (Invoke-Db "SELECT COUNT(*) FROM st_practice_attempts WHERE learner_user_id=$learnerId AND status='graded';") '11' 'Report must match the source dataset.'
	Invoke-Browser $reportPath $parent 'family-practice-history'
	Invoke-Browser 'beranda' $parent 'family-practice-dashboard'
	Invoke-Db "UPDATE st_family_links SET status='revoked' WHERE parent_user_id=$parentId AND child_user_id=$learnerId;" | Out-Null
	foreach ($path in @($reportPath,$familyResultPath,"family/practice/$learnerId","family/practice_result/$learnerId/$attemptId")) {
		Assert-Equal (Get-Status { Get-Page $path $parentWeb }) 404 'Revoked family link must remove all report access without relogin.'
	}
	Invoke-Db "UPDATE st_family_links SET status='active' WHERE parent_user_id=$parentId AND child_user_id=$learnerId; UPDATE st_users SET status='suspended' WHERE id=$learnerId;" | Out-Null
	Assert-Equal (Get-Status { Get-Page $reportPath $parentWeb }) 404 'Inactive child must not remain in reports.'
	Assert-Equal (Get-Status { Get-Page $familyResultPath $parentWeb }) 404 'Inactive child result must be unavailable.'
	Invoke-Db "UPDATE st_users SET status='active' WHERE id=$learnerId;" | Out-Null
	Invoke-Db "UPDATE st_courses SET status='archived' WHERE id=$courseId;" | Out-Null
	Assert-Equal (Get-Status { Get-Page $attemptPath $learnerWeb }) 200 'Completed history remains readable after course archival.'
	if ((Get-Page $attemptPath $learnerWeb).Content -match 'Coba latihan lagi') { throw 'Archived course result must not offer an unavailable retry action.' }
	Assert-Equal (Get-Status { Get-Page $coursePath $learnerWeb }) 404 'Archived course must not allow a new practice attempt.'
	Assert-Equal (Get-Status { Get-Page $familyResultPath $parentWeb }) 200 'Linked parent may still read completed archived-course history.'
	Assert-Equal (Invoke-Db "SELECT CONCAT(score,'|',answer_revision) FROM st_practice_attempts WHERE id=$attemptId;") '33.33|2' 'Parent report actions must not alter child answers or score.'
	Write-Output 'PASS family reports: live link ownership, child identity, read-only final snapshots, hidden active answers, source totals, paging, revocation, inactive child, and archive history.'
	Write-Output 'PASS private attempts, no early keys, saved answers, stale-tab conflict, CSRF/IDOR, snapshot grading, start/submit replay, 0/100/rounding, history, and revoked access.'
}
finally {
	$cleanup = @"
START TRANSACTION;
DELETE a FROM st_practice_answers a JOIN st_practice_attempts p ON p.id=a.attempt_id JOIN st_users u ON u.id=p.learner_user_id WHERE u.username IN ($usernames);
DELETE p FROM st_practice_attempts p JOIN st_users u ON u.id=p.learner_user_id WHERE u.username IN ($usernames);
DELETE q FROM st_session_questions q JOIN st_practice_sessions s ON s.id=q.session_id JOIN st_users u ON u.id=s.learner_user_id WHERE u.username IN ($usernames);
DELETE s FROM st_practice_sessions s JOIN st_users u ON u.id=s.learner_user_id WHERE u.username IN ($usernames);
DELETE o FROM st_question_options o JOIN st_questions q ON q.id=o.question_id JOIN st_courses c ON c.id=q.course_id WHERE c.slug='$courseSlug';
DELETE q FROM st_questions q JOIN st_courses c ON c.id=q.course_id WHERE c.slug='$courseSlug';
DELETE FROM st_content_reviews WHERE course_id IN (SELECT id FROM st_courses WHERE slug='$courseSlug');
DELETE FROM st_lessons WHERE slug='$lessonSlug';
DELETE FROM st_course_sections WHERE course_id IN (SELECT id FROM st_courses WHERE slug='$courseSlug');
DELETE FROM st_courses WHERE slug='$courseSlug';
DELETE FROM st_audit_events WHERE actor_user_id IN (SELECT id FROM st_users WHERE username IN ($usernames));
DELETE FROM st_user_roles WHERE user_id IN (SELECT id FROM st_users WHERE username IN ($usernames));
DELETE FROM st_family_links WHERE parent_user_id IN (SELECT id FROM st_users WHERE username IN ($usernames));
DELETE FROM st_users WHERE username IN ($usernames);
COMMIT;
"@
	Invoke-Db $cleanup | Out-Null
	Write-Output 'PASS temporary practice users, course, questions, snapshots, attempts, answers, and audit events cleaned.'
}
