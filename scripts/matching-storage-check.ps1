param(
	[Parameter(Mandatory=$true)][string] $Php81Path,
	[Parameter(Mandatory=$true)][string] $MysqlPath,
	[string] $BaseUrl = '',
	[string] $ChromePath = '',
	[switch] $CaptureScreenshots,
	[switch] $RunResilienceCheck
)
$ErrorActionPreference='Stop'
$suffix=[Guid]::NewGuid().ToString('N').Substring(0,12)
$learner="qa_matchingrun_$suffix"; $other="qa_matchingrun_other_$suffix"
$slug="qa-matching-run-$suffix"
$names="'$learner','$other'"
function Db([string] $Sql) {
	$result=& $MysqlPath '-uroot' '-D' 'db_study' '-N' '-B' '-e' $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Matching fixture SQL failed: $result" }
	return ($result -join "`n").Trim()
}
function Invoke-MatchingCli([ValidateSet('check_matching_transactions','check_matching')][string] $Controller, [int[]] $Identifiers) {
	$expectedCount = if ($Controller -eq 'check_matching_transactions') { 2 } else { 3 }
	if ($Identifiers.Count -ne $expectedCount -or @($Identifiers | Where-Object { $_ -lt 1 }).Count -gt 0) { throw 'Invalid matching CLI fixture identifiers.' }
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
		& $Php81Path index.php "cli/$Controller/index" @Identifiers
		if ($LASTEXITCODE -ne 0) { throw 'Matching CLI failed; this suite remains responsible for its synthetic fixture cleanup.' }
	}
	finally {
		foreach ($name in $environmentNames) { [Environment]::SetEnvironmentVariable($name,$previous[$name],'Process') }
		Pop-Location
	}
}
function Remove-MatchingProbeFixture {
	if ($suffix -cnotmatch '^[a-f0-9]{12}$' -or $learner -cne "qa_matchingrun_$suffix" -or $slug -cne "qa-matching-run-$suffix" -or $learnerId -lt 1 -or $lessonId -lt 1) { throw 'Refuse mismatched matching-probe cleanup scope.' }
	$probeKeySql = "SHA2('matching-transactions:$suffix',256)"
	$probeRunText = Db "SELECT r.id FROM st_matching_runs r JOIN st_users u ON u.id=r.learner_user_id JOIN st_lessons l ON l.id=r.lesson_id WHERE r.learner_user_id=$learnerId AND r.lesson_id=$lessonId AND r.start_key=$probeKeySql AND u.username='$learner' AND l.slug='$slug';"
	$probeRunId = 0
	if (-not [int]::TryParse($probeRunText,[ref]$probeRunId) -or $probeRunId -lt 1) { throw 'Exact matching-probe run could not be resolved for cleanup.' }
	$probeState = Db "SELECT CONCAT(r.status,'|',r.revision,'|',(SELECT COUNT(*) FROM st_matching_moves WHERE run_id=r.id),'|',(SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$learnerId AND entity_type='matching_run' AND entity_id=r.id AND event_type='matching.started')) FROM st_matching_runs r WHERE r.id=$probeRunId AND r.learner_user_id=$learnerId AND r.lesson_id=$lessonId AND r.start_key=$probeKeySql;"
	if ($probeState -cne 'in_progress|7|7|1') { throw 'Refuse unexpected matching-probe setup state during cleanup.' }
	$probeScope = "r.id=$probeRunId AND r.learner_user_id=$learnerId AND r.lesson_id=$lessonId AND r.start_key=$probeKeySql AND r.status='in_progress' AND r.revision=7 AND u.username='$learner'"
	Db @"
START TRANSACTION;
DELETE m FROM st_matching_moves m JOIN st_matching_runs r ON r.id=m.run_id JOIN st_users u ON u.id=r.learner_user_id WHERE $probeScope;
DELETE a FROM st_audit_events a JOIN st_matching_runs r ON r.id=a.entity_id JOIN st_users u ON u.id=r.learner_user_id WHERE $probeScope AND a.actor_user_id=$learnerId AND a.entity_type='matching_run' AND a.event_type='matching.started';
DELETE r FROM st_matching_runs r JOIN st_users u ON u.id=r.learner_user_id WHERE $probeScope;
COMMIT;
"@ | Out-Null
	$remaining = Db "SELECT CONCAT((SELECT COUNT(*) FROM st_matching_runs WHERE learner_user_id=$learnerId),'|',(SELECT COUNT(*) FROM st_matching_moves WHERE run_id=$probeRunId),'|',(SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$learnerId AND entity_type='matching_run'));"
	if ($remaining -cne '0|0|0') { throw 'Matching-probe cleanup left an unexpected run, move or audit row.' }
	Write-Output 'PASS only the exact probe setup run, seven moves and started audit removed; normal matching regression begins with no owned run/audit.'
}
$oldEnvironment=[Environment]::GetEnvironmentVariable('CI_ENV','Process')
try {
	Db @"
START TRANSACTION;
INSERT INTO st_users(username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES
('$learner','not-a-login-hash','Synthetic matching learner','child','primary','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$other','not-a-login-hash','Synthetic other learner','child','primary','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @learner=(SELECT id FROM st_users WHERE username='$learner');
INSERT INTO st_courses(author_user_id,title,slug,summary,learning_objectives,target_group,status,is_free,content_version,rights_status,source_note,created_at,updated_at) VALUES(@learner,'Matching storage fixture','$slug','Synthetic','Test','primary','published',1,1,'owned','QA only',UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @course=LAST_INSERT_ID();
INSERT INTO st_course_sections(course_id,title,sort_order,created_at,updated_at) VALUES(@course,'Fixture',1,UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @section=LAST_INSERT_ID();
INSERT INTO st_lessons(section_id,title,slug,body_text,status,content_version,is_preview,rights_status,source_note,created_at,updated_at) VALUES(@section,'Matching fixture','$slug','Synthetic','published',1,0,'owned','QA only',UTC_TIMESTAMP(),UTC_TIMESTAMP());
SET @lesson=LAST_INSERT_ID();
INSERT INTO st_flashcard_decks(lesson_id,title,created_by_user_id,created_at,updated_at) VALUES(@lesson,'Fixture',@learner,UTC_TIMESTAMP(),UTC_TIMESTAMP());
COMMIT;
"@ | Out-Null
	$learnerId=[int](Db "SELECT id FROM st_users WHERE username='$learner';")
	$otherId=[int](Db "SELECT id FROM st_users WHERE username='$other';")
	$lessonId=[int](Db "SELECT id FROM st_lessons WHERE slug='$slug';")
	$deckId=[int](Db "SELECT id FROM st_flashcard_decks WHERE lesson_id=$lessonId;")
	$rows=@(1..8 | ForEach-Object { "($deckId,'Card $_','Pair $_',$_,UTC_TIMESTAMP(),UTC_TIMESTAMP())" }) -join ','
	Db "INSERT INTO st_flashcards(deck_id,front_text,back_text,sort_order,created_at,updated_at) VALUES $rows;" | Out-Null
	[Environment]::SetEnvironmentVariable('CI_ENV','development','Process')
	Invoke-MatchingCli -Controller 'check_matching_transactions' -Identifiers @($learnerId,$lessonId)
	Remove-MatchingProbeFixture
	Invoke-MatchingCli -Controller 'check_matching' -Identifiers @($learnerId,$otherId,$lessonId)
	if ($BaseUrl) { . (Join-Path $PSScriptRoot 'matching-http-cases.ps1') }
	if ($RunResilienceCheck) {
		$racePassword="Temporary-Race-$suffix-123!"
		$raceHash=(& $Php81Path -r "echo password_hash('$racePassword', PASSWORD_DEFAULT);").Trim().Replace("'", "''")
		if ($LASTEXITCODE -ne 0) { throw 'Could not prepare resilience fixture authentication.' }
		Db "UPDATE st_users SET password_hash='$raceHash',status='active' WHERE id=$learnerId AND username='$learner'; UPDATE st_courses SET is_free=1,status='published' WHERE slug='$slug';" | Out-Null
		$oldRacePassword=[Environment]::GetEnvironmentVariable('STUDY_QA_RACE_PASSWORD','Process')
		try {
			[Environment]::SetEnvironmentVariable('STUDY_QA_RACE_PASSWORD',$racePassword,'Process')
			& node (Join-Path $PSScriptRoot 'matching-resilience-check.mjs') --php $Php81Path --mysql $MysqlPath --learner $learner --user-id $learnerId --lesson-id $lessonId --slug $slug
			if ($LASTEXITCODE -ne 0) { throw 'Concurrent/lost-response matching tests failed.' }
		} finally { [Environment]::SetEnvironmentVariable('STUDY_QA_RACE_PASSWORD',$oldRacePassword,'Process') }
	}
} finally {
	[Environment]::SetEnvironmentVariable('CI_ENV',$oldEnvironment,'Process')
	Db @"
START TRANSACTION;
DELETE m FROM st_matching_moves m JOIN st_matching_runs r ON r.id=m.run_id JOIN st_users u ON u.id=r.learner_user_id WHERE u.username IN ($names);
DELETE r FROM st_matching_runs r JOIN st_users u ON u.id=r.learner_user_id WHERE u.username IN ($names);
DELETE f FROM st_flashcards f JOIN st_flashcard_decks d ON d.id=f.deck_id JOIN st_lessons l ON l.id=d.lesson_id WHERE l.slug='$slug';
DELETE d FROM st_flashcard_decks d JOIN st_lessons l ON l.id=d.lesson_id WHERE l.slug='$slug';
DELETE FROM st_lessons WHERE slug='$slug';
DELETE s FROM st_course_sections s JOIN st_courses c ON c.id=s.course_id WHERE c.slug='$slug';
DELETE FROM st_courses WHERE slug='$slug';
DELETE a FROM st_audit_events a JOIN st_users u ON u.id=a.actor_user_id WHERE u.username IN ($names);
DELETE l FROM st_family_links l JOIN st_users u ON u.id=l.parent_user_id OR u.id=l.child_user_id WHERE u.username IN ($names);
DELETE FROM st_users WHERE username IN ($names);
COMMIT;
"@ | Out-Null
	Write-Output 'PASS exact temporary matching run/move/content/user/audit fixtures cleaned.'
}
