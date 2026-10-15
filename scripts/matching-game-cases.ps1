# Executed within flashcard-check.ps1; that suite owns fixture setup/cleanup.
if (-not $learnerWeb -or -not $courseId -or $courseSlug -notlike 'qa-flashcard-course-*') { throw 'Run flashcard-check.ps1, not this case file.' }
$gamePath = "permainan/pasangan/$lessonSlug"
$gameDirect = "games/matching/$lessonSlug"
$gameEmpty = Get-Page $gamePath $learnerWeb
if ($gameEmpty.Content -notmatch 'Pasangan belum cukup' -or $gameEmpty.Content -match 'id="matching-data"') { throw 'A one-card deck must not start a matching game.' }
$gameDeckId = [int](Invoke-Db "SELECT id FROM st_flashcard_decks WHERE lesson_id=$lessonId;")
$gameLearnerId = [int](Invoke-Db "SELECT id FROM st_users WHERE username='$learner';")
$gameRows = @(1..7 | ForEach-Object { "($gameDeckId,'Kartu permainan $_','Pasangan $_',$_,UTC_TIMESTAMP(),UTC_TIMESTAMP())" }) -join ','
Invoke-Db "INSERT INTO st_flashcards(deck_id,front_text,back_text,sort_order,created_at,updated_at) VALUES $gameRows; UPDATE st_flashcards SET back_text='<script>window.matchingInjected=1</script>' WHERE deck_id=$gameDeckId AND front_text='Kartu permainan 7';" | Out-Null
$gamePage = Get-Page $gamePath $learnerWeb
if ($gamePage.Content -match '<script>window.matchingInjected' -or $gamePage.Content -notmatch '\\u003Cscript\\u003E' -or $gamePage.Content -notmatch 'data-round-size="6"') { throw 'Game data must be safely embedded and use six pairs for school-age children.' }
if ($gamePage.Headers['Cache-Control'] -notmatch 'no-store') { throw 'Personalized game content must not be shared-cached.' }
if ($gamePage.Content -notmatch '<noscript>' -or $gamePage.Content -notmatch 'Baca pasangan untuk belajar' -or $gamePage.Content -notmatch '<dt>Kartu permainan 1</dt>') { throw 'Readable fallback must be present in server HTML, not created by JavaScript.' }
if ((Get-Page "materi/$lessonSlug" $learnerWeb).Content -notmatch 'Main Cocokkan Pasangan') { throw 'Eligible lesson must link to matching game.' }
Assert-Equal (Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$gamePath) }) 200 'Free game can be read by guests.'
foreach ($gameRoute in @($gamePath,$gameDirect)) {
	$gameCsrf = Get-CsrfToken (Get-Page 'beranda' $learnerWeb)
	Assert-Equal (Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$gameRoute) -WebSession $learnerWeb -Method Post -Body @{score=999999;study_csrf_token=$gameCsrf} }) 405 'No game score or reward submission endpoint is available.'
}
Invoke-Db "UPDATE st_users SET learning_stage='early_childhood' WHERE id=$gameLearnerId;" | Out-Null
if ((Get-Page $gamePath $learnerWeb).Content -notmatch 'data-round-size="4"' -or (Get-Page $gamePath $learnerWeb).Content -notmatch 'theme-child-early') { throw 'Early-childhood game must use the age theme and four-pair rounds.' }
Invoke-Db "UPDATE st_users SET learning_stage='primary' WHERE id=$gameLearnerId; UPDATE st_courses SET is_free=0 WHERE id=$courseId; UPDATE st_lessons SET is_preview=0 WHERE id=$lessonId;" | Out-Null
foreach ($gameRoute in @($gamePath,$gameDirect)) {
	Assert-Equal (Get-Status { Get-Page $gameRoute $learnerWeb }) 403 'Locked course must block game through both routes.'
	Assert-Equal (Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$gameRoute) }) 403 'Locked game cannot leak to guests.'
}
Invoke-Db "INSERT INTO st_course_enrollments(learner_user_id,course_id,source_type,status,enrolled_at,updated_at) VALUES($gameLearnerId,$courseId,'manual','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());" | Out-Null
Assert-Equal (Get-Status { Get-Page $gamePath $learnerWeb }) 200 'Authorized enrollment grants game access.'
Assert-Equal (Get-Status { Get-Page $gamePath $otherLearnerWeb }) 403 'Another learner cannot borrow enrollment.'
Invoke-Db "UPDATE st_course_enrollments SET status='revoked' WHERE learner_user_id=$gameLearnerId AND course_id=$courseId;" | Out-Null
Assert-Equal (Get-Status { Get-Page $gamePath $learnerWeb }) 403 'Revocation blocks the next game request.'
Invoke-Db "UPDATE st_lessons SET is_preview=1 WHERE id=$lessonId;" | Out-Null
Assert-Equal (Get-Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$gamePath) }) 200 'Explicit lesson preview also permits its readable game.'
foreach ($gameStatus in @('draft','archived')) {
	Invoke-Db "UPDATE st_courses SET status='$gameStatus' WHERE id=$courseId;" | Out-Null
	Assert-Equal (Get-Status { Get-Page $gamePath $learnerWeb }) 404 'Draft/archive cannot be opened as a game.'
}
Invoke-Db "UPDATE st_courses SET status='published',is_free=1 WHERE id=$courseId; UPDATE st_lessons SET is_preview=0 WHERE id=$lessonId;" | Out-Null
$gameBefore = Invoke-Db "SELECT CONCAT((SELECT COUNT(*) FROM st_wallet_entries),'|',(SELECT COUNT(*) FROM st_flashcard_progress),'|',(SELECT SUM(review_count) FROM st_flashcard_progress),'|',(SELECT COUNT(*) FROM st_lesson_progress),'|',(SELECT COUNT(*) FROM st_audit_events));"
if ($ChromePath) {
	$oldGameIdentity = [Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY','Process')
	$oldGamePassword = [Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD','Process')
	try {
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY',$learner,'Process')
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD',$password,'Process')
		# Login emits its own auth audit; compare progress/ledger separately after browser work.
		$gameArgs = @((Join-Path $PSScriptRoot 'browser-viewport-check.mjs'),'--base-url',$root,'--chrome',$ChromePath,'--extra-path',$gamePath,'--extra-name','matching-game','--only-extra-route','--exercise-matching')
		if ($CapturePreviewScreenshots) { $gameArgs += '--capture-screenshots' }
		& node @gameArgs
		if ($LASTEXITCODE -ne 0) { throw 'Matching game browser interaction/viewport failed.' }
	} finally {
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY',$oldGameIdentity,'Process')
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD',$oldGamePassword,'Process')
	}
}
Get-Page $gamePath $learnerWeb | Out-Null
$gameAfter = Invoke-Db "SELECT CONCAT((SELECT COUNT(*) FROM st_wallet_entries),'|',(SELECT COUNT(*) FROM st_flashcard_progress),'|',(SELECT SUM(review_count) FROM st_flashcard_progress),'|',(SELECT COUNT(*) FROM st_lesson_progress),'|',(SELECT COUNT(*) FROM st_audit_events));"
Assert-Equal (($gameAfter -split '\|')[0..3] -join '|') (($gameBefore -split '\|')[0..3] -join '|') 'Playing/refresh must not change ledger or authoritative progress.'
if (-not $ChromePath) { Assert-Equal $gameAfter $gameBefore 'Read-only game requests must not emit audit writes.' }
Write-Output 'PASS matching game: lesson access/preview/revocation, draft/archive isolation, GET-only, safe JSON/HTML, age sizes, no ledger/progress writes, and optional real-browser rounds/keyboard/reduced-motion/reset.'
