param(
	[Parameter(Mandatory = $true)][string] $Php81Path,
	[Parameter(Mandatory = $true)][string] $MysqlPath,
	[Parameter(Mandatory = $true)][string] $BaseUrl,
	[Parameter(Mandatory = $false)][string] $ChromePath = '',
	[Parameter(Mandatory = $false)][switch] $CapturePreviewScreenshots
)

$ErrorActionPreference = 'Stop'
if ($CapturePreviewScreenshots -and -not $ChromePath) { throw '-CapturePreviewScreenshots requires -ChromePath.' }
$suffix = [Guid]::NewGuid().ToString('N').Substring(0, 14)
$editor = "qa_flashcard_editor_$suffix"
$reviewer = "qa_flashcard_reviewer_$suffix"
$learner = "qa_flashcard_learner_$suffix"
$otherLearner = "qa_flashcard_other_$suffix"
$otherEditorName = "qa_flashcard_other_editor_$suffix"
$parent = "qa_flashcard_parent_$suffix"
$otherParent = "qa_flashcard_other_parent_$suffix"
$editorEmail = "$editor@example.test"
$reviewerEmail = "$reviewer@example.test"
$learnerEmail = "$learner@example.test"
$otherLearnerEmail = "$otherLearner@example.test"
$parentEmail = "$parent@example.test"
$otherParentEmail = "$otherParent@example.test"
$courseSlug = "qa-flashcard-course-$suffix"
$lessonSlug = "qa-flashcard-lesson-$suffix"
$paidCourseSlug = "qa-flashcard-paid-$suffix"
$paidLessonSlug = "qa-flashcard-paid-lesson-$suffix"
$password = 'Temporary-Study-Flashcard-123!'
$passwordHash = (& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim()
if ($LASTEXITCODE -ne 0 -or -not $passwordHash) { throw 'Could not create temporary flashcard-test password hash.' }
$escapedHash = $passwordHash.Replace("'", "''")

function Invoke-Db([string] $Sql) {
	$output = & $MysqlPath '-uroot' '-D' 'db_study' '-N' '-B' '-e' $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Flashcard database test failed: $output" }
	return ($output -join "`n").Trim()
}

function Get-CsrfToken($Response) {
	$match = [regex]::Match($Response.Content, 'name="study_csrf_token"\s+value="([^"]+)"')
	if (-not $match.Success) { throw 'CSRF hidden field was not found on flashcard test page.' }
	return [System.Net.WebUtility]::HtmlDecode($match.Groups[1].Value)
}

function Get-Status([scriptblock] $Request) {
	try { return [int] ((& $Request).StatusCode) }
	catch {
		if ($null -ne $_.Exception.Response) { return [int] $_.Exception.Response.StatusCode }
		throw
	}
}

function Assert-Equal($Actual, $Expected, [string] $Message) {
	if ($Actual -ne $Expected) { throw "$Message (expected '$Expected', got '$Actual')." }
}

function New-LoggedInSession([string] $Identity) {
	$web = New-Object Microsoft.PowerShell.Commands.WebRequestSession
	$root = $BaseUrl.TrimEnd('/') + '/'
	$page = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -WebSession $web
	$body = @{ identity = $Identity; password = $password; study_csrf_token = (Get-CsrfToken $page) }
	$result = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -Method Post -WebSession $web -Body $body
	if ($result.Content -notmatch 'Panel pengelola|Dashboard|Hai,') { throw "Could not authenticate synthetic account $Identity." }
	return $web
}

function Get-Page([string] $Path, $WebSession) {
	return Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl.TrimEnd('/') + '/' + $Path.TrimStart('/')) -WebSession $WebSession
}

function Remove-Fixtures {
	$cleanup = @"
START TRANSACTION;
DELETE FROM st_lesson_progress WHERE lesson_id IN (SELECT id FROM st_lessons WHERE slug IN ('$lessonSlug','$paidLessonSlug'));
DELETE p FROM st_flashcard_progress p JOIN st_flashcards f ON f.id=p.flashcard_id JOIN st_flashcard_decks d ON d.id=f.deck_id JOIN st_lessons l ON l.id=d.lesson_id WHERE l.slug IN ('$lessonSlug','$paidLessonSlug');
DELETE f FROM st_flashcards f JOIN st_flashcard_decks d ON d.id=f.deck_id JOIN st_lessons l ON l.id=d.lesson_id WHERE l.slug IN ('$lessonSlug','$paidLessonSlug');
DELETE d FROM st_flashcard_decks d JOIN st_lessons l ON l.id=d.lesson_id WHERE l.slug IN ('$lessonSlug','$paidLessonSlug');
DELETE FROM st_content_reviews WHERE course_id IN (SELECT id FROM st_courses WHERE slug IN ('$courseSlug','$paidCourseSlug'));
DELETE FROM st_course_enrollments WHERE course_id IN (SELECT id FROM st_courses WHERE slug IN ('$courseSlug','$paidCourseSlug'));
DELETE FROM st_family_links WHERE parent_user_id IN (SELECT id FROM st_users WHERE username IN ('$parent','$otherParent')) OR child_user_id IN (SELECT id FROM st_users WHERE username IN ('$learner','$otherLearner'));
DELETE FROM st_audit_events WHERE actor_user_id IN (SELECT id FROM st_users WHERE username IN ('$editor','$reviewer','$learner','$otherLearner','$otherEditorName','$parent','$otherParent'));
DELETE FROM st_lessons WHERE slug IN ('$lessonSlug','$paidLessonSlug');
DELETE FROM st_course_sections WHERE course_id IN (SELECT id FROM st_courses WHERE slug IN ('$courseSlug','$paidCourseSlug'));
DELETE FROM st_courses WHERE slug IN ('$courseSlug','$paidCourseSlug');
DELETE FROM st_user_roles WHERE user_id IN (SELECT id FROM st_users WHERE username IN ('$editor','$reviewer','$learner','$otherLearner','$otherEditorName','$parent','$otherParent'));
DELETE FROM st_users WHERE username IN ('$editor','$reviewer','$learner','$otherLearner','$otherEditorName','$parent','$otherParent');
COMMIT;
"@
	Invoke-Db $cleanup | Out-Null
}

$root = $BaseUrl.TrimEnd('/') + '/'
try {
	$setup = @"
START TRANSACTION;
INSERT INTO st_users (username,email,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES
('$editor','$editorEmail','$escapedHash','QA Flashcard Editor','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$reviewer','$reviewerEmail','$escapedHash','QA Flashcard Reviewer','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$otherLearner','$otherLearnerEmail','$escapedHash','QA Other Learner','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
INSERT INTO st_users (username,email,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES
('$learner','$learnerEmail','$escapedHash','QA Flashcard Learner','child','primary','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$parent','$parentEmail','$escapedHash','QA Flashcard Parent','parent','unset','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$otherParent','$otherParentEmail','$escapedHash','QA Unrelated Parent','parent','unset','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @editor_id = (SELECT id FROM st_users WHERE username='$editor');
SET @reviewer_id = (SELECT id FROM st_users WHERE username='$reviewer');
SET @learner_id = (SELECT id FROM st_users WHERE username='$learner');
SET @parent_id = (SELECT id FROM st_users WHERE username='$parent');
INSERT INTO st_user_roles (user_id,role_id,granted_by_user_id,created_at) VALUES
(@editor_id,(SELECT id FROM st_roles WHERE role_code='content_editor'),@editor_id,UTC_TIMESTAMP()),
(@reviewer_id,(SELECT id FROM st_roles WHERE role_code='system_admin'),@reviewer_id,UTC_TIMESTAMP());
INSERT INTO st_family_links (parent_user_id,child_user_id,status,created_at,updated_at) VALUES (@parent_id,@learner_id,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
INSERT INTO st_courses (author_user_id,updated_by_user_id,title,slug,summary,learning_objectives,target_group,status,content_version,rights_status,source_note,is_free,created_at,updated_at)
VALUES (@editor_id,@editor_id,'QA Flashcard Course','$courseSlug','Synthetic flashcard fixture','Recall terms','adult','draft',1,'owned','Temporary QA content',1,UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @course_id = LAST_INSERT_ID();
INSERT INTO st_course_sections (course_id,title,sort_order,created_at,updated_at) VALUES (@course_id,'QA Section',1,UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @section_id = LAST_INSERT_ID();
INSERT INTO st_lessons (section_id,title,slug,body_text,estimated_minutes,sort_order,status,content_version,is_preview,source_note,rights_status,created_at,updated_at)
VALUES (@section_id,'QA Flashcard Lesson','$lessonSlug','Synthetic lesson text for a flashcard test.',5,1,'draft',1,0,'Temporary QA content','owned',UTC_TIMESTAMP(),UTC_TIMESTAMP());
INSERT INTO st_courses (author_user_id,updated_by_user_id,title,slug,summary,learning_objectives,target_group,status,content_version,rights_status,source_note,is_free,created_at,updated_at)
VALUES (@editor_id,@editor_id,'QA Paid Flashcard Course','$paidCourseSlug','Synthetic paid fixture','Recall terms','adult','published',1,'owned','Temporary QA content',0,UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @paid_course_id = LAST_INSERT_ID();
INSERT INTO st_course_sections (course_id,title,sort_order,created_at,updated_at) VALUES (@paid_course_id,'QA Paid Section',1,UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @paid_section_id = LAST_INSERT_ID();
INSERT INTO st_lessons (section_id,title,slug,body_text,estimated_minutes,sort_order,status,content_version,is_preview,source_note,rights_status,created_at,updated_at)
VALUES (@paid_section_id,'QA Paid Flashcard Lesson','$paidLessonSlug','Paid fixture.',5,1,'published',1,0,'Temporary QA content','owned',UTC_TIMESTAMP(),UTC_TIMESTAMP());
COMMIT;
SELECT @course_id, @section_id, (SELECT id FROM st_lessons WHERE slug='$lessonSlug'), @paid_course_id, (SELECT id FROM st_lessons WHERE slug='$paidLessonSlug');
"@
	$ids = (Invoke-Db $setup) -split "\s+"
	if ($ids.Count -lt 5) { throw 'Flashcard QA fixture IDs were not returned.' }
	$courseId = [int] $ids[0]
	$lessonId = [int] $ids[2]
	$paidLessonId = [int] $ids[4]

	$editorWeb = New-LoggedInSession $editor
	$editPath = "kelola/kursus/$courseId/pelajaran/$lessonId/flashcard"
	$page = Get-Page $editPath $editorWeb
	if ($page.Content -notmatch 'Kartu belajar' -or $page.StatusCode -ne 200) { throw 'Draft author cannot open the flashcard editor.' }
	Write-Output 'PASS draft author can open the flashcard editor.'

	$malformedStatus = Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root + $editPath) -Method Post -WebSession $editorWeb -ContentType 'application/x-www-form-urlencoded' -Body ('action=save_card&front_text%5B%5D=array&study_csrf_token=' + (Get-CsrfToken $page)) }
	Assert-Equal $malformedStatus 400 'Array-shaped flashcard input must be rejected.'
	Write-Output 'PASS array-shaped flashcard input is rejected.'

	$page = Get-Page $editPath $editorWeb
	$deckPost = @{ action='save_deck'; title='QA Term Cards'; description='Synthetic study cards'; study_csrf_token=(Get-CsrfToken $page) }
	Invoke-WebRequest -UseBasicParsing -Uri ($root + $editPath) -Method Post -WebSession $editorWeb -Body $deckPost | Out-Null
	$page = Get-Page $editPath $editorWeb
	if ($page.Content -notmatch 'QA Term Cards' -or $page.Content -notmatch 'Belum ada kartu') { throw 'Flashcard deck was not saved with its empty-draft warning.' }
	Write-Output 'PASS a draft deck is created and clearly warns when it has no cards.'
	$invalidCardIdStatus = Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root + $editPath) -Method Post -WebSession $editorWeb -Body @{ action='save_card'; card_id='-1'; front_text='Invalid ID'; back_text='Must not save'; hint_text=''; sort_order='0'; study_csrf_token=(Get-CsrfToken $page) } }
	Assert-Equal $invalidCardIdStatus 400 'Negative card ID must not be treated as a new card.'
	Write-Output 'PASS malformed or negative card IDs are rejected.'

	$coursePage = Get-Page "kelola/kursus/$courseId" $editorWeb
	Invoke-WebRequest -UseBasicParsing -Uri ($root + "kelola/kursus/$courseId/ajukan") -Method Post -WebSession $editorWeb -Body @{ study_csrf_token=(Get-CsrfToken $coursePage) } | Out-Null
	$coursePage = Get-Page "kelola/kursus/$courseId" $editorWeb
	if ($coursePage.Content -notmatch 'minimal satu kartu') { throw 'Course review submission did not block an empty flashcard deck.' }
	Write-Output 'PASS course cannot be submitted for review with an empty deck.'

	$otherEditorWeb = New-Object Microsoft.PowerShell.Commands.WebRequestSession
	$otherEditorEmail = "$otherEditorName@example.test"
	$escapedOtherHash = (& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim().Replace("'", "''")
	Invoke-Db "INSERT INTO st_users (username,email,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES ('$otherEditorName','$otherEditorEmail','$escapedOtherHash','QA Other Editor','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()); SET @other_editor_id=LAST_INSERT_ID(); INSERT INTO st_user_roles (user_id,role_id,granted_by_user_id,created_at) VALUES (@other_editor_id,(SELECT id FROM st_roles WHERE role_code='content_editor'),@other_editor_id,UTC_TIMESTAMP());" | Out-Null
	$otherEditorWeb = New-LoggedInSession $otherEditorName
	foreach ($privateDraftPath in @(
		"kelola/kursus/$courseId", "admin/course/$courseId",
		"kelola/kursus/$courseId/edit", "admin/edit_course/$courseId",
		"kelola/kursus/$courseId/pelajaran/$lessonId/edit", "admin/edit_lesson/$courseId/$lessonId",
		$editPath, "admin/flashcards/$courseId/$lessonId",
		"kelola/kursus/$courseId/pelajaran/$lessonId/pratinjau", "admin/preview_lesson/$courseId/$lessonId"
	)) {
		$otherStatus = Get-Status { Get-Page $privateDraftPath $otherEditorWeb }
		Assert-Equal $otherStatus 403 "Unrelated content editor must not access another author's draft via $privateDraftPath."
	}
	Write-Output 'PASS another content editor is denied course, lesson, and flashcard draft routes through aliases and direct controller paths.'

	$page = Get-Page $editPath $editorWeb
	$addCard = @{ action='save_card'; card_id=''; front_text='QA prompt'; back_text='QA answer'; hint_text='QA hint'; sort_order='0'; study_csrf_token=(Get-CsrfToken $page) }
	Invoke-WebRequest -UseBasicParsing -Uri ($root + $editPath) -Method Post -WebSession $editorWeb -Body $addCard | Out-Null
	$page = Get-Page $editPath $editorWeb
	if ($page.Content -notmatch 'QA prompt' -or $page.Content -notmatch 'QA answer') { throw 'Flashcard content was not rendered in the draft editor.' }
	Write-Output 'PASS card prompt, answer, and hint are saved into the reviewable course draft.'
	$cardId = [int] (Invoke-Db "SELECT f.id FROM st_flashcards f JOIN st_flashcard_decks d ON d.id=f.deck_id WHERE d.lesson_id=$lessonId;")
	$editCardPage = Get-Page "$editPath`?edit_kartu=$cardId" $editorWeb
	$editCardPost = @{ action='save_card'; card_id="$cardId"; front_text='QA prompt updated'; back_text='QA answer <script>alert(1)</script>'; hint_text='QA hint updated'; sort_order='0'; study_csrf_token=(Get-CsrfToken $editCardPage) }
	Invoke-WebRequest -UseBasicParsing -Uri ($root + $editPath) -Method Post -WebSession $editorWeb -Body $editCardPost | Out-Null
	$page = Get-Page $editPath $editorWeb
	if ($page.Content -notmatch 'QA prompt updated' -or $page.Content -notmatch 'QA hint updated') { throw 'Flashcard edit did not update the intended card.' }
	Write-Output 'PASS existing draft cards can be edited without changing another deck.'
	$addSecondCard = @{ action='save_card'; card_id=''; front_text='QA card to delete'; back_text='Temporary answer'; hint_text=''; sort_order='1'; study_csrf_token=(Get-CsrfToken $page) }
	Invoke-WebRequest -UseBasicParsing -Uri ($root + $editPath) -Method Post -WebSession $editorWeb -Body $addSecondCard | Out-Null
	$page = Get-Page $editPath $editorWeb
	$deleteCardId = [int] (Invoke-Db "SELECT id FROM st_flashcards WHERE front_text='QA card to delete' AND deck_id=(SELECT id FROM st_flashcard_decks WHERE lesson_id=$lessonId);")
	Invoke-WebRequest -UseBasicParsing -Uri ($root + $editPath) -Method Post -WebSession $editorWeb -Body @{ action='delete_card'; card_id="$deleteCardId"; study_csrf_token=(Get-CsrfToken $page) } | Out-Null
	$page = Get-Page $editPath $editorWeb
	if ($page.Content -match 'QA card to delete' -or (Invoke-Db "SELECT COUNT(*) FROM st_flashcards WHERE id=$deleteCardId;") -ne '0') { throw 'Draft card deletion did not remove only the selected card.' }
	Write-Output 'PASS a draft card can be deleted before publication.'

	$previewPath = "kelola/kursus/$courseId/pelajaran/$lessonId/pratinjau"
	$previewStateSql = "SELECT CONCAT(status,'|',content_version,'|',(SELECT COUNT(*) FROM st_lesson_progress WHERE lesson_id=$lessonId),'|',(SELECT COUNT(*) FROM st_flashcard_progress WHERE flashcard_id=$cardId)) FROM st_courses WHERE id=$courseId;"
	$beforePreviewState = Invoke-Db $previewStateSql
	$previewThemes = @{ early_childhood='theme-child-early'; primary='theme-child-primary'; secondary='theme-child-secondary'; adult='theme-adult'; all_ages='theme-public' }
	foreach ($theme in $previewThemes.Keys) {
		$previewPage = Get-Page "$previewPath`?tema=$theme" $editorWeb
		if ($previewPage.Content -notmatch ('<body class="' + $previewThemes[$theme] + '">') -or $previewPage.Content -notmatch 'Pratinjau pengelola' -or $previewPage.Content -notmatch 'QA prompt updated') { throw "Lesson preview did not render the selected participant theme $theme." }
		if ($previewPage.Content -notmatch 'QA answer' -or $previewPage.Content -notmatch 'Synthetic lesson text' -or $previewPage.Content -notmatch 'Lihat sisi belakang') { throw 'Preview omitted lesson text or draft flashcards.' }
		if ($previewPage.Content -match '<script>alert\(1\)</script>|save-reading-position|lesson-progress\.js|Tandai selesai|Saya sudah paham|/flashcard/[0-9]+') { throw 'Preview contains executable card markup or learner progress actions.' }
		if ($previewPage.Headers['Cache-Control'] -notmatch 'no-store' -or $previewPage.Headers['X-Robots-Tag'] -notmatch 'noindex') { throw 'Private preview is missing cache/indexing restrictions.' }
	}
	foreach ($invalidTheme in @('tema%5B%5D=primary', 'tema=unknown', 'tema=%22%3E%3Cimg%20src=x%20onerror=alert(1)%3E')) {
		$previewPage = Get-Page "$previewPath`?$invalidTheme" $editorWeb
		if ($previewPage.Content -notmatch '<body class="theme-adult">' -or $previewPage.Content -match '<img src=x onerror=alert\(1\)>|Array to string conversion') { throw 'Invalid preview theme did not fall back safely to course target.' }
	}
	Assert-Equal (Get-Status { Get-Page "admin/preview_lesson/$courseId/$lessonId" $editorWeb }) 200 'Direct preview route must allow the draft author.'
	Assert-Equal (Get-Status { Get-Page "kelola/kursus/$courseId/pelajaran/$paidLessonId/pratinjau" $editorWeb }) 404 'Preview must bind the lesson ID to its course.'
	Assert-Equal (Get-Status { Get-Page "admin/preview_lesson/$courseId/not-a-number" $editorWeb }) 404 'Direct preview route must reject malformed lesson IDs.'
	Assert-Equal (Get-Status { Get-Page 'admin/preview_lesson' $editorWeb }) 404 'Incomplete preview route must return 404, not a PHP error.'
	$guestPreview = Get-Page $previewPath $null
	if ($guestPreview.Content -match 'QA answer|Synthetic lesson text' -or $guestPreview.Content -notmatch 'name="identity"') { throw 'Guest preview did not redirect to login without draft data.' }
	Assert-Equal (Get-Status { Get-Page "materi/$lessonSlug" $null }) 404 'Draft lesson must remain unavailable through its public URL.'
	$previewPage = Get-Page $previewPath $editorWeb
	Assert-Equal (Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root + $previewPath) -Method Post -WebSession $editorWeb -Body @{ study_csrf_token=(Get-CsrfToken $previewPage) } }) 404 'Preview route is read-only.'
	$previewPage = Get-Page $previewPath $editorWeb
	Assert-Equal (Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root + "materi/$lessonSlug/selesai") -Method Post -WebSession $editorWeb -Body @{ study_csrf_token=(Get-CsrfToken $previewPage) } }) 404 'Preview authorization must not grant learner write access to a draft.'
	Assert-Equal (Invoke-Db $previewStateSql) $beforePreviewState 'Preview must not mutate course status/version or learning progress.'
	Write-Output 'PASS private lesson preview supports five themes, rejects cross-course IDs/POST, escapes content, disables progress, and leaves draft state unchanged.'
	if ($ChromePath) {
		$oldPreviewIdentity = [Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY', 'Process')
		$oldPreviewPassword = [Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD', 'Process')
		try {
			Invoke-Db "UPDATE st_courses SET target_group='early_childhood' WHERE id=$courseId;" | Out-Null
			[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY', $editor, 'Process')
			[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD', $password, 'Process')
			$previewBrowserArguments = @((Join-Path $PSScriptRoot 'browser-viewport-check.mjs'), '--base-url', $root, '--chrome', $ChromePath, '--extra-path', $previewPath, '--extra-name', 'lesson-preview', '--only-extra-route')
			if ($CapturePreviewScreenshots) { $previewBrowserArguments += '--capture-screenshots' }
			& node @previewBrowserArguments
			if ($LASTEXITCODE -ne 0) { throw 'Private lesson preview browser check failed.' }
		}
		finally {
			[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY', $oldPreviewIdentity, 'Process')
			[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD', $oldPreviewPassword, 'Process')
			Invoke-Db "UPDATE st_courses SET target_group='adult' WHERE id=$courseId;" | Out-Null
		}
	}

	$coursePage = Get-Page "kelola/kursus/$courseId" $editorWeb
	if ($coursePage.Content -notmatch 'QA prompt' -or $coursePage.Content -notmatch 'QA answer') { throw 'Course review page did not include flashcard content.' }
	Invoke-WebRequest -UseBasicParsing -Uri ($root + "kelola/kursus/$courseId/ajukan") -Method Post -WebSession $editorWeb -Body @{ study_csrf_token=(Get-CsrfToken $coursePage) } | Out-Null
	$reviewWeb = New-LoggedInSession $reviewer
	$reviewPage = Get-Page "kelola/kursus/$courseId" $reviewWeb
	if ($reviewPage.Content -notmatch 'QA prompt' -or $reviewPage.Content -notmatch 'QA answer') { throw 'Second reviewer could not inspect the flashcard content.' }
	foreach ($reviewSession in @($reviewWeb, $otherEditorWeb)) {
		$reviewPreview = Get-Page $previewPath $reviewSession
		if ($reviewPreview.Content -notmatch 'QA answer' -or $reviewPreview.Content -notmatch 'Pratinjau pengelola') { throw 'Reviewer cannot preview submitted lesson with participant template.' }
	}
	Write-Output 'PASS both system admin and content editor can preview submitted material before review.'
	Invoke-WebRequest -UseBasicParsing -Uri ($root + "kelola/kursus/$courseId/tinjau") -Method Post -WebSession $reviewWeb -Body @{ decision='approved'; notes=''; study_csrf_token=(Get-CsrfToken $reviewPage) } | Out-Null
	$publishedPage = Get-Page "materi/$lessonSlug" $null
	if ($publishedPage.Content -notmatch 'QA Term Cards' -or $publishedPage.Content -notmatch 'Lihat sisi belakang' -or $publishedPage.Content -notmatch 'QA answer') { throw 'Published free lesson does not show the approved flashcard deck.' }
	if ($publishedPage.Content -match '<script>alert\(1\)</script>') { throw 'Flashcard answer rendered as executable HTML instead of plain text.' }
	Write-Output 'PASS reviewer sees the cards before approval and the published lesson then displays the deck.'
	Write-Output 'PASS flashcard answer is not rendered as executable HTML.'
	if ($ChromePath) {
		& node (Join-Path $PSScriptRoot 'browser-viewport-check.mjs') --base-url $root --chrome $ChromePath --extra-path "materi/$lessonSlug" --only-extra-route
		if ($LASTEXITCODE -ne 0) { throw 'Flashcard lesson browser viewport/accessibility check failed.' }
		Write-Output 'PASS published flashcard lesson has no viewport overflow and passes browser accessibility heuristics.'
	}

	$editAfterPublishStatus = Get-Status { Get-Page $editPath $editorWeb }
	Assert-Equal $editAfterPublishStatus 403 'Published course flashcards must not be edited outside a review cycle.'
	Write-Output 'PASS published course cards are immutable through the draft editor.'

	$learnerWeb = New-LoggedInSession $learner
	$learnerPage = Get-Page "materi/$lessonSlug" $learnerWeb
	$cardMatch = [regex]::Match($learnerPage.Content, 'materi/' + [regex]::Escape($lessonSlug) + '/flashcard/([0-9]+)')
	if (-not $cardMatch.Success) { throw 'Learner card progress form was not rendered.' }
	$cardId = [int] $cardMatch.Groups[1].Value
	$progressPath = "materi/$lessonSlug/flashcard/$cardId"
	foreach ($attempt in 1..2) {
		$learnerPage = Get-Page "materi/$lessonSlug" $learnerWeb
		Invoke-WebRequest -UseBasicParsing -Uri ($root + $progressPath) -Method Post -WebSession $learnerWeb -Body @{ status='known'; study_csrf_token=(Get-CsrfToken $learnerPage) } | Out-Null
	}
	$progress = Invoke-Db "SELECT CONCAT(status,'|',review_count) FROM st_flashcard_progress WHERE learner_user_id=(SELECT id FROM st_users WHERE username='$learner') AND flashcard_id=$cardId;"
	Assert-Equal $progress 'known|1' 'Repeating the same card state must be idempotent.'
	Write-Output 'PASS learner can save card progress and duplicate submissions do not inflate review count.'
	$cardProgressScope = "learner_user_id=(SELECT id FROM st_users WHERE username='$learner') AND flashcard_id=$cardId"
	Invoke-Db "UPDATE st_flashcard_progress SET last_reviewed_at='2000-01-01 00:00:00' WHERE $cardProgressScope;" | Out-Null
	$learnerPage = Get-Page "materi/$lessonSlug" $learnerWeb
	Invoke-WebRequest -UseBasicParsing -Uri ($root + $progressPath) -Method Post -WebSession $learnerWeb -Body @{ status='known'; study_csrf_token=(Get-CsrfToken $learnerPage) } | Out-Null
	Assert-Equal (Invoke-Db "SELECT CONCAT(status,'|',review_count,'|',last_reviewed_at) FROM st_flashcard_progress WHERE $cardProgressScope;") 'known|1|2000-01-01 00:00:00' 'Repeated status must preserve review timestamp as well as count.'
	foreach ($transition in @(@{ Status='learning'; Count=2 }, @{ Status='learning'; Count=2 }, @{ Status='known'; Count=3 }, @{ Status='known'; Count=3 })) {
		$learnerPage = Get-Page "materi/$lessonSlug" $learnerWeb
		Invoke-WebRequest -UseBasicParsing -Uri ($root + $progressPath) -Method Post -WebSession $learnerWeb -Body @{ status=$transition.Status; study_csrf_token=(Get-CsrfToken $learnerPage) } | Out-Null
		Assert-Equal (Invoke-Db "SELECT CONCAT(status,'|',review_count) FROM st_flashcard_progress WHERE $cardProgressScope;") "$($transition.Status)|$($transition.Count)" 'Only changed card statuses may increment count.'
	}
	$beforeRejectedCard = Invoke-Db "SELECT CONCAT(status,'|',review_count,'|',last_reviewed_at) FROM st_flashcard_progress WHERE $cardProgressScope;"
	foreach ($badCardId in @('0','-1',"${cardId}oops",'01')) {
		$learnerPage = Get-Page "materi/$lessonSlug" $learnerWeb
		$invalidIdStatus = Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root + "catalog/save_flashcard_progress/$lessonSlug/$badCardId") -Method Post -WebSession $learnerWeb -Body @{ status='known'; study_csrf_token=(Get-CsrfToken $learnerPage) } }
		Assert-Equal $invalidIdStatus 400 'Malformed direct card identity must be rejected, not coerced.'
	}
	Assert-Equal (Invoke-Db "SELECT CONCAT(status,'|',review_count,'|',last_reviewed_at) FROM st_flashcard_progress WHERE $cardProgressScope;") $beforeRejectedCard 'Rejected card identities must not mutate progress.'
	Write-Output 'PASS card status transitions, stable replay timestamp and malformed direct-route ID rejection.'
	$learnerId = [int](Invoke-Db "SELECT id FROM st_users WHERE username='$learner';")
	Assert-Equal (Invoke-Db "SELECT COUNT(*) FROM st_course_enrollments WHERE learner_user_id=$learnerId AND course_id=$courseId;") '0' 'Paid-access probe needs an unassigned synthetic learner.'
	Invoke-Db "UPDATE st_courses SET is_free=0 WHERE id=$courseId AND slug='$courseSlug'; INSERT INTO st_course_enrollments(learner_user_id,course_id,source_type,status,enrolled_at,updated_at) VALUES($learnerId,$courseId,'manual','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());" | Out-Null
	$learnerPage = Get-Page "materi/$lessonSlug" $learnerWeb
	Invoke-WebRequest -UseBasicParsing -Uri ($root + $progressPath) -Method Post -WebSession $learnerWeb -Body @{ status='learning'; study_csrf_token=(Get-CsrfToken $learnerPage) } | Out-Null
	Assert-Equal (Invoke-Db "SELECT CONCAT(status,'|',review_count) FROM st_flashcard_progress WHERE $cardProgressScope;") 'learning|4' 'Paid current access must permit card write.'
	$learnerPage = Get-Page "materi/$lessonSlug" $learnerWeb
	Invoke-Db "UPDATE st_course_enrollments SET status='revoked' WHERE learner_user_id=$learnerId AND course_id=$courseId;" | Out-Null
	$revokedCardStatus = Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root + $progressPath) -Method Post -WebSession $learnerWeb -Body @{ status='known'; study_csrf_token=(Get-CsrfToken $learnerPage) } }
	Assert-Equal $revokedCardStatus 403 'Revoked card access must be rejected.'
	Assert-Equal (Invoke-Db "SELECT CONCAT(status,'|',review_count) FROM st_flashcard_progress WHERE $cardProgressScope;") 'learning|4' 'Revoked access must leave stored status/count unchanged.'
	Invoke-Db "UPDATE st_courses SET is_free=1 WHERE id=$courseId AND slug='$courseSlug'; DELETE FROM st_course_enrollments WHERE learner_user_id=$learnerId AND course_id=$courseId AND source_type='manual' AND status='revoked';" | Out-Null
	$learnerPage = Get-Page "materi/$lessonSlug" $learnerWeb
	Invoke-WebRequest -UseBasicParsing -Uri ($root + $progressPath) -Method Post -WebSession $learnerWeb -Body @{ status='known'; study_csrf_token=(Get-CsrfToken $learnerPage) } | Out-Null
	Assert-Equal (Invoke-Db "SELECT CONCAT(status,'|',review_count) FROM st_flashcard_progress WHERE $cardProgressScope;") 'known|5' 'Restored free access must use the same status rules.'
	Write-Output 'PASS card mutation on paid manual access, revocation rejection and restored free access.'
	$parentWeb = New-LoggedInSession $parent
	foreach ($nonContentSession in @($parentWeb, $learnerWeb)) {
		Assert-Equal (Get-Status { Get-Page $previewPath $nonContentSession }) 403 'Parent/child accounts must not open internal content preview.'
		Assert-Equal (Get-Status { Get-Page "admin/preview_lesson/$courseId/$lessonId" $nonContentSession }) 403 'Direct preview route must also deny parent/child accounts.'
	}
	$parentDashboard = Get-Page 'beranda' $parentWeb
	if ($parentDashboard.Content -notmatch 'QA Term Cards' -or $parentDashboard.Content -notmatch '1 dari 1 dikuasai') { throw 'Linked parent could not see the learner flashcard summary.' }
	$unrelatedParentWeb = New-LoggedInSession $otherParent
	$unrelatedDashboard = Get-Page 'beranda' $unrelatedParentWeb
	if ($unrelatedDashboard.Content -match 'QA Term Cards' -or $unrelatedDashboard.Content -match 'QA Flashcard Learner') { throw 'Flashcard summary leaked to an unrelated parent.' }
	Invoke-Db "UPDATE st_family_links SET status='revoked',updated_at=UTC_TIMESTAMP() WHERE parent_user_id=(SELECT id FROM st_users WHERE username='$parent') AND child_user_id=(SELECT id FROM st_users WHERE username='$learner');" | Out-Null
	$revokedDashboard = Get-Page 'beranda' $parentWeb
	if ($revokedDashboard.Content -match 'QA Term Cards' -or $revokedDashboard.Content -match 'QA Flashcard Learner') { throw 'Revoked family link continued to expose flashcard progress.' }
	Write-Output 'PASS only the currently linked parent can see flashcard progress; a revoked link removes access.'

	$otherLearnerWeb = New-LoggedInSession $otherLearner
	$otherPage = Get-Page "materi/$lessonSlug" $otherLearnerWeb
	if ($otherPage.Content -notmatch '0 dari 1 dikuasai' -or $otherPage.Content -match 'Sudah dikuasai') { throw 'Flashcard progress leaked to another learner.' }
	$crossLessonStatus = Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root + "materi/$paidLessonSlug/flashcard/$cardId") -Method Post -WebSession $otherLearnerWeb -Body @{ status='known'; study_csrf_token=(Get-CsrfToken $otherPage) } }
	Assert-Equal $crossLessonStatus 403 'Card ID must not be usable through another lesson route.'
	$lockedStatus = Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root + "materi/$paidLessonSlug/flashcard/$cardId") -Method Post -WebSession $learnerWeb -Body @{ status='known'; study_csrf_token=(Get-CsrfToken (Get-Page "materi/$paidLessonSlug" $learnerWeb)) } }
	Assert-Equal $lockedStatus 403 'Paid lesson without entitlement must not accept card progress.'
	Write-Output 'PASS card progress is private per learner, lesson-bound, and denied for a locked paid course.'

	$editorId = Invoke-Db "SELECT id FROM st_users WHERE username='$editor';"
	$courseVersion = Invoke-Db "SELECT content_version FROM st_courses WHERE id=$courseId;"
	$auditCount = Invoke-Db "SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$editorId AND entity_type='flashcard_deck' AND event_type LIKE 'content.flashcard_%';"
	if ([int] $courseVersion -lt 6 -or [int] $auditCount -lt 5) { throw 'Flashcard edits did not increment course content version and audit events.' }
	Write-Output 'PASS flashcard changes advance the course version and create audit events.'
	. (Join-Path $PSScriptRoot 'matching-game-cases.ps1')
}
finally {
	Remove-Fixtures
	Write-Output 'PASS temporary flashcard-test data is cleaned.'
}

