# Run only within family-delivery-check.ps1: its exact-ID fixture cleanup owns these rows.
if (-not $parentWeb -or -not $sessionId -or -not $packageSlug -or $packageSlug -notlike 'qa-delivery-*') { throw 'Run family-delivery-check.ps1, not this case file directly.' }
function CopyBody([int] $SourceId, [int] $TargetId) {
	$view = Page "keluarga/latihan/$SourceId/salin" $parentWeb
	return @{title='Salinan latihan';child_id=$TargetId;copy_token=(Hidden $view 'copy_token');revision=(Hidden $view 'revision');confirm='1'}
}
function CopyPost([string] $Path, $Web, [hashtable] $Body) {
	$Body['study_csrf_token'] = Hidden (Page 'beranda' $Web) 'study_csrf_token'
	return Invoke-WebRequest -UseBasicParsing -Uri ($root+$Path) -WebSession $Web -Method Post -Body $Body
}
function CopyCounts {
	return Db "SELECT CONCAT((SELECT COUNT(*) FROM st_practice_sessions WHERE created_by_user_id=$parentId),'|',(SELECT COUNT(*) FROM st_questions WHERE created_by_user_id=$parentId),'|',(SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$parentId));"
}
Db "UPDATE st_entitlements SET status='active' WHERE user_id=$parentId;" | Out-Null
$siblingId = [int](Db "SELECT id FROM st_users WHERE username='$sibling';")
$copyPath = "keluarga/latihan/$sessionId/salin"
$copyBody = CopyBody $sessionId $siblingId
$copyBaseline = CopyCounts
foreach ($path in @($copyPath,"parent_practice/copy/$sessionId")) {
	Equal (Status { Page $path $otherWeb }) 404 'Other parent of same child cannot view copy form.'
	Equal (Status { CopyPost $path $otherWeb $copyBody.Clone() }) 404 'Other parent cannot copy private source by POST.'
	Equal (Status { Page $path $childWeb }) 403 'Child cannot copy private session.'
	Equal (Status { CopyPost $path $childWeb $copyBody.Clone() }) 403 'Child cannot copy through direct POST.'
}
$bad = $copyBody.Clone(); $bad.confirm='0'
Equal (Status { CopyPost $copyPath $parentWeb $bad }) 400 'Copy requires explicit confirmation.'
$bad = $copyBody.Clone(); $bad.copy_token='invalid'
Equal (Status { CopyPost $copyPath $parentWeb $bad }) 400 'Copy requires signed source token.'
$bad = CopyBody $cancelId $siblingId
Equal (Status { CopyPost $copyPath $parentWeb $bad }) 400 'Signed token for another source cannot be reused.'
$bad = $copyBody.Clone(); $bad.Remove('title'); $bad['title[]']='bad'
Equal (Status { CopyPost $copyPath $parentWeb $bad }) 400 'Copy rejects array title.'
$bad = $copyBody.Clone(); $bad.title=' '
Equal (Status { CopyPost $copyPath $parentWeb $bad }) 400 'Copy rejects blank title.'
$bad = $copyBody.Clone(); $bad.child_id=$parentId
Equal (Status { CopyPost $copyPath $parentWeb $bad }) 404 'Target must be linked child, not adult.'
$bad = $copyBody.Clone(); $bad.study_csrf_token='invalid'
Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$copyPath) -WebSession $parentWeb -Method Post -Body $bad }) 403 'Copy rejects invalid CSRF.'
foreach ($change in @("status='revoked'","status='active',ends_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND)","ends_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),starts_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR)")) {
	Db "UPDATE st_entitlements SET $change WHERE user_id=$parentId;" | Out-Null
	Equal (Status { CopyPost $copyPath $parentWeb $copyBody.Clone() }) 403 'Copy requires active and current entitlement.'
}
Db "UPDATE st_entitlements SET status='active',starts_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),ends_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE user_id=$parentId; UPDATE st_packages SET family_practice_enabled=0 WHERE slug='$packageSlug';" | Out-Null
Equal (Status { CopyPost $copyPath $parentWeb $copyBody.Clone() }) 403 'Ordinary package cannot copy.'
Db "UPDATE st_packages SET family_practice_enabled=1 WHERE slug='$packageSlug';" | Out-Null
foreach ($revokedChild in @($childId,$siblingId)) {
	Db "UPDATE st_family_links SET status='revoked' WHERE parent_user_id=$parentId AND child_user_id=$revokedChild;" | Out-Null
	Equal (Status { CopyPost $copyPath $parentWeb $copyBody.Clone() }) 404 'Both source and destination must retain active family link.'
	Db "UPDATE st_family_links SET status='active' WHERE parent_user_id=$parentId AND child_user_id=$revokedChild;" | Out-Null
}
Db "UPDATE st_users SET status='suspended' WHERE id=$siblingId;" | Out-Null
Equal (Status { CopyPost $copyPath $parentWeb $copyBody.Clone() }) 404 'Inactive target child cannot receive a copied draft.'
Db "UPDATE st_users SET status='active' WHERE id=$siblingId;" | Out-Null
Equal (CopyCounts) $copyBaseline 'Rejected copies must not create partial data or audit.'
Browser $copyPath $parent 'family-copy-form'
# Completed fixture source has already been mutated; copy must use its sent snapshot.
Db "UPDATE st_questions SET source_note='Changed source attribution',rights_status='licensed' WHERE id=$questionId;" | Out-Null
$copyBody.title='<img src=x onerror=alert(1)> Salinan'
CopyPost $copyPath $parentWeb $copyBody.Clone() | Out-Null
$copiedId = [int](Db "SELECT MAX(id) FROM st_practice_sessions WHERE created_by_user_id=$parentId;")
$copiedQuestion = [int](Db "SELECT question_id FROM st_session_questions WHERE session_id=$copiedId;")
if ($copiedQuestion -eq $questionId -or $copiedId -eq $sessionId) { throw 'Copy must use new source-independent IDs.' }
$copyAfter = CopyCounts
CopyPost $copyPath $parentWeb $copyBody.Clone() | Out-Null
Equal (CopyCounts) $copyAfter 'Copy replay must not create duplicate session, questions or audit.'
Equal (Db "SELECT CONCAT(status,'|',draft_revision,'|',learner_user_id,'|',available_from IS NULL,'|',available_until IS NULL) FROM st_practice_sessions WHERE id=$copiedId;") "draft|0|$siblingId|1|1" 'Copy must reset status, revision, schedule and target.'
Equal (Db "SELECT CONCAT(content_version,'|',target_child_user_id,'|',visibility,'|',source_note,'|',rights_status) FROM st_questions WHERE id=$copiedQuestion;") "1|$siblingId|family_private|Soal sintetis pengujian|owned" 'Copy retains snapshot attribution and resets version/ownership.'
Equal (Db "SELECT COUNT(*) FROM st_practice_attempts WHERE session_id=$copiedId;") '0' 'Copy cannot carry source attempt, answers or score.'
Equal (Db "SELECT COUNT(*) FROM st_session_questions WHERE session_id=$copiedId AND snapshot_json IS NOT NULL;") '0' 'Copy must be a draft, not a sent snapshot.'
Equal (Db "SELECT SUM(is_correct) FROM st_question_options WHERE question_id=$copiedQuestion;") '1' 'Copy uses original snapshot key, not mutated source options.'
Equal (Db "SELECT COUNT(*) FROM st_question_options a JOIN st_question_options b ON b.id=a.id WHERE a.question_id=$copiedQuestion AND b.question_id=$questionId;") '0' 'Options must have independent IDs.'
$copiedPage = Page "keluarga/latihan/$copiedId" $parentWeb
if ($copiedPage.Content -match '<img src=x|Changed source' -or $copiedPage.Content -notmatch '&lt;img' -or $copiedPage.Content -notmatch $secretExplanation) { throw 'Copied content must be escaped and use immutable snapshot.' }
Equal (Status { Page "latihan/keluarga/$copiedId" $siblingWeb }) 404 'Copied draft must not be delivered automatically.'
$copiedEdit = "keluarga/latihan/$copiedId"
$staleCopy = CopyBody $copiedId $siblingId
Post $copiedEdit $parentWeb @{action='save';revision='0';question_id=$copiedQuestion;prompt='Pertanyaan salinan baru';explanation='Pembahasan salinan';source_note='Karya uji';rights_status='owned';correct='1';option_0='1';option_1='2';option_2='3';option_3='4'} | Out-Null
Equal (Db "SELECT prompt FROM st_questions WHERE id=$questionId;") 'Changed source' 'Editing a copy must not modify source question.'
$beforeStale = CopyCounts
Equal (Status { CopyPost ($copiedEdit+'/salin') $parentWeb $staleCopy }) 409 'Stale source revision cannot copy unreviewed content.'
Equal (CopyCounts) $beforeStale 'Stale rejection must leave no partial draft.'
$draftCopy = CopyBody $copiedId $siblingId
CopyPost ($copiedEdit+'/salin') $parentWeb $draftCopy | Out-Null
$draftCopyId = [int](Db "SELECT MAX(id) FROM st_practice_sessions WHERE created_by_user_id=$parentId;")
Equal (Db "SELECT q.prompt FROM st_questions q JOIN st_session_questions sq ON sq.question_id=q.id WHERE sq.session_id=$draftCopyId;") 'Pertanyaan salinan baru' 'Unsent source copies current draft edits.'
$cancelCopy = CopyBody $cancelId $childId
CopyPost "keluarga/latihan/$cancelId/salin" $parentWeb $cancelCopy | Out-Null
$cancelCopyId = [int](Db "SELECT MAX(id) FROM st_practice_sessions WHERE created_by_user_id=$parentId;")
Equal (Db "SELECT status FROM st_practice_sessions WHERE id=$cancelCopyId;") 'draft' 'Cancelled source can be reused without reopening original.'
$emptyBody = CopyBody $emptyId $childId
$beforeEmpty = CopyCounts
Equal (Status { CopyPost "keluarga/latihan/$emptyId/salin" $parentWeb $emptyBody }) 409 'Empty source cannot be copied.'
Equal (CopyCounts) $beforeEmpty 'Empty rejection must not leave partial data.'
# Corrupt a fixture-only snapshot and restore it after rejection; no real data involved.
$sourceSnapshot = Db "SELECT HEX(snapshot_json) FROM st_session_questions WHERE session_id=$sessionId;"
Db "UPDATE st_session_questions SET snapshot_json='{}' WHERE session_id=$sessionId;" | Out-Null
$badSnapshotBody = CopyBody $sessionId $childId
Equal (Status { CopyPost $copyPath $parentWeb $badSnapshotBody }) 409 'Broken snapshot must fail closed.'
Equal (CopyCounts) $beforeEmpty 'Snapshot failure must not leave partial data.'
Db "UPDATE st_session_questions SET snapshot_json=UNHEX('$sourceSnapshot') WHERE session_id=$sessionId;" | Out-Null
# Older immutable snapshots predate attribution fields and remain readable/copyable.
Db "UPDATE st_session_questions SET snapshot_json=JSON_REMOVE(snapshot_json,'$.source_note','$.rights_status') WHERE session_id=$sessionId;" | Out-Null
$legacyBody = CopyBody $sessionId $childId
CopyPost $copyPath $parentWeb $legacyBody | Out-Null
$legacyId = [int](Db "SELECT MAX(id) FROM st_practice_sessions WHERE created_by_user_id=$parentId;")
Equal (Db "SELECT q.source_note FROM st_questions q JOIN st_session_questions sq ON sq.question_id=q.id WHERE sq.session_id=$legacyId;") 'Changed source attribution' 'Legacy snapshot retains locked source metadata fallback.'
Db "UPDATE st_session_questions SET snapshot_json=UNHEX('$sourceSnapshot') WHERE session_id=$sessionId;" | Out-Null
# Exercise the full question cap and ensure an invalid final row produces no partial copy.
foreach ($fillIndex in 1..49) {
	Db "START TRANSACTION; INSERT INTO st_questions(created_by_user_id,target_child_user_id,visibility,question_type,prompt,explanation,status,content_version,source_note,rights_status,created_at,updated_at) SELECT created_by_user_id,target_child_user_id,visibility,question_type,'Copy question $fillIndex',explanation,'draft',1,source_note,rights_status,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_questions WHERE id=$copiedQuestion; SET @copy_question=LAST_INSERT_ID(); INSERT INTO st_question_options(question_id,option_text,is_correct,sort_order) SELECT @copy_question,option_text,is_correct,sort_order FROM st_question_options WHERE question_id=$copiedQuestion; INSERT INTO st_session_questions(session_id,question_id,sort_order) VALUES($copiedId,@copy_question,$(1+$fillIndex)); COMMIT;" | Out-Null
}
$lastCopyQuestion = [int](Db "SELECT MAX(question_id) FROM st_session_questions WHERE session_id=$copiedId;")
Db "UPDATE st_questions SET prompt='' WHERE id=$lastCopyQuestion;" | Out-Null
$fiftyBody = CopyBody $copiedId $siblingId
$beforeFifty = CopyCounts
Equal (Status { CopyPost ($copiedEdit+'/salin') $parentWeb $fiftyBody.Clone() }) 409 'Invalid fiftieth question must reject entire copy.'
Equal (CopyCounts) $beforeFifty 'Earlier valid questions must not be partially copied.'
Db "UPDATE st_questions SET prompt='Copy question 49' WHERE id=$lastCopyQuestion;" | Out-Null
CopyPost ($copiedEdit+'/salin') $parentWeb $fiftyBody.Clone() | Out-Null
$fiftyId = [int](Db "SELECT MAX(id) FROM st_practice_sessions WHERE created_by_user_id=$parentId;")
Equal (Db "SELECT COUNT(*) FROM st_session_questions WHERE session_id=$fiftyId;") '50' 'Full fifty-question session must copy completely.'
Equal (Db "SELECT COUNT(*) FROM st_question_options o JOIN st_session_questions sq ON sq.question_id=o.question_id WHERE sq.session_id=$fiftyId;") '200' 'Every copied question needs its own four options.'
Db "INSERT INTO st_questions(created_by_user_id,target_child_user_id,visibility,question_type,prompt,explanation,status,content_version,source_note,rights_status,created_at,updated_at) SELECT created_by_user_id,target_child_user_id,visibility,question_type,'Over cap',explanation,'draft',1,source_note,rights_status,UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_questions WHERE id=$copiedQuestion; SET @copy_over=LAST_INSERT_ID(); INSERT INTO st_session_questions(session_id,question_id,sort_order) VALUES($copiedId,@copy_over,51);" | Out-Null
$overBody = CopyBody $copiedId $siblingId
$beforeOver = CopyCounts
Equal (Status { CopyPost ($copiedEdit+'/salin') $parentWeb $overBody }) 409 'Corrupt over-cap source cannot be silently truncated.'
Equal (CopyCounts) $beforeOver 'Over-cap source must leave no copy.'
# Restore the intentionally malformed source before testing replay redirects to that draft.
$overQuestionId = [int](Db "SELECT question_id FROM st_session_questions WHERE session_id=$copiedId AND sort_order=51;")
Db "START TRANSACTION; DELETE FROM st_session_questions WHERE session_id=$copiedId AND question_id=$overQuestionId; DELETE FROM st_questions WHERE id=$overQuestionId AND created_by_user_id=$parentId; COMMIT;" | Out-Null
$draftCount = [int](Db "SELECT COUNT(*) FROM st_practice_sessions WHERE created_by_user_id=$parentId AND status='draft';")
$capacityRows = @(1..(100-$draftCount) | ForEach-Object { "($parentId,$childId,'Copy capacity $_','parent_assigned','draft',UTC_TIMESTAMP(),UTC_TIMESTAMP())" }) -join ','
Db "INSERT INTO st_practice_sessions(created_by_user_id,learner_user_id,title,session_type,status,created_at,updated_at) VALUES $capacityRows;" | Out-Null
$capBody = CopyBody $sessionId $childId
$beforeCap = CopyCounts
Equal (Status { CopyPost $copyPath $parentWeb $capBody }) 409 'Copy must not bypass per-parent 100 draft cap.'
Equal (CopyCounts) $beforeCap 'Capacity failure must leave no partial data.'
CopyPost $copyPath $parentWeb $copyBody.Clone() | Out-Null
Equal (CopyCounts) $beforeCap 'An existing copy replays even when capacity is full.'
Equal (Db "SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$parentId AND event_type='family.practice_copied';") '5' 'Exactly five successful copies must be audited.'
$copyAudit = Db "SELECT details_json FROM st_audit_events WHERE actor_user_id=$parentId AND event_type='family.practice_copied';"
if ($copyAudit -match 'Pertanyaan|Penjelasan|onerror' -or $copyAudit -notmatch 'source_session_id') { throw 'Copy audit must contain provenance IDs but not private text.' }
Write-Output 'PASS family copies: immutable snapshots/attribution, new ownership/IDs, same child/sibling, draft/cancelled/completed, no answers/schedule, replay, revisions, access boundaries, capacity, and cleanup owned by delivery suite.'
