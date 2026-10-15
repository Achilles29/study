# Dot-sourced only by matching-storage-check.ps1; fixture ownership and cleanup remain there.
$parsedUrl = [Uri]$BaseUrl
if ($parsedUrl.Scheme -ne 'http' -or -not $parsedUrl.IsLoopback -or $parsedUrl.UserInfo -or $parsedUrl.Query -or $parsedUrl.Fragment) { throw 'Matching HTTP QA requires a loopback URL.' }
$root = $BaseUrl.TrimEnd('/') + '/'
$password = 'Temporary-Matching-Check-123!'
$passwordHash = (& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim().Replace("'", "''")
if ($LASTEXITCODE -ne 0) { throw 'Could not prepare fixture authentication.' }
Db "UPDATE st_users SET password_hash='$passwordHash' WHERE username IN ($names); UPDATE st_courses SET is_free=1 WHERE slug='$slug'; UPDATE st_lessons SET title='Awan & <b>Belajar</b>' WHERE id=$lessonId; UPDATE st_flashcards SET front_text='Kartu_<script>uji</script>' WHERE deck_id=$deckId ORDER BY id LIMIT 1;" | Out-Null
function Page([string] $Path, $Web) { return Invoke-WebRequest -UseBasicParsing -Uri ($root + $Path) -WebSession $Web }
function Hidden($Page, [string] $Name) {
	$match = [regex]::Match($Page.Content, 'name="' + [regex]::Escape($Name) + '"\s+value="([^"]*)"')
	if (-not $match.Success) { throw "Missing matching form field $Name." }
	return [System.Net.WebUtility]::HtmlDecode($match.Groups[1].Value)
}
function Post([string] $Path, $Web, [hashtable] $Body, [string] $CsrfPath = 'permainan/riwayat') {
	$Body['study_csrf_token'] = Hidden (Page $CsrfPath $Web) 'study_csrf_token'
	return Invoke-WebRequest -UseBasicParsing -Uri ($root + $Path) -Method Post -WebSession $Web -Body $Body
}
function Status([scriptblock] $Request) {
	try { return [int]((& $Request).StatusCode) } catch { if ($_.Exception.Response) { return [int]$_.Exception.Response.StatusCode }; throw }
}
function Equal($Actual, $Expected, [string] $Message) { if ($Actual -ne $Expected) { throw "$Message (expected $Expected, got $Actual)" } }
function Login([string] $Identity) {
	$web = New-Object Microsoft.PowerShell.Commands.WebRequestSession
	Post 'masuk' $web @{identity=$Identity;password=$password} 'masuk' | Out-Null
	return $web
}
function Browser([string] $Path, [string] $Name, [string] $Identity = $learner) {
	if (-not $ChromePath) { return }
	$oldIdentity=[Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY','Process')
	$oldPassword=[Environment]::GetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD','Process')
	try {
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY',$Identity,'Process')
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD',$password,'Process')
		$arguments=@((Join-Path $PSScriptRoot 'browser-viewport-check.mjs'),'--base-url',$root,'--chrome',$ChromePath,'--extra-path',$Path,'--extra-name',$Name,'--only-extra-route')
		if ($Name -eq 'matching-saved-interaction') { $arguments += '--exercise-saved-matching' }
		if ($Name -eq 'matching-saved-recovery') { $arguments += '--exercise-saved-matching-recovery' }
		if ($Name -eq 'matching-saved-native') { $arguments += '--exercise-saved-matching'; $arguments += '--block-page-scripts' }
		if ($Name -eq 'matching-saved-navigation') { $arguments += '--exercise-saved-matching-navigation' }
		if ($CaptureScreenshots) { $arguments += '--capture-screenshots' }
		& node @arguments
		if ($LASTEXITCODE -ne 0) { throw "Matching browser check failed: $Name" }
	} finally {
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_IDENTITY',$oldIdentity,'Process')
		[Environment]::SetEnvironmentVariable('STUDY_QA_LOGIN_PASSWORD',$oldPassword,'Process')
	}
}
$web=Login $learner
$otherWeb=Login $other
$startPath="permainan/tersimpan/pelajaran/$slug"
$startPage=Page $startPath $web
Equal (Db "SELECT COUNT(*) FROM st_matching_runs WHERE learner_user_id=$learnerId;") '2' 'GET created a run'
Browser $startPath 'matching-saved-start'
$startToken=Hidden $startPage 'action_token'
$startOtherTab=Hidden (Page $startPath $web) 'action_token'
Equal (Status { Post $startPath $web @{action_token='forged'} }) 400 'Forged start token accepted'
Equal (Status { Post $startPath $otherWeb @{action_token=$startToken} }) 400 'Start token was not bound to its owner'
Post $startPath $web @{action_token=$startToken} | Out-Null
$runId=[int](Db "SELECT id FROM st_matching_runs WHERE learner_user_id=$learnerId AND status='in_progress';")
$runPath="permainan/tersimpan/$runId"
$stopPath="$runPath/berhenti"
Post $startPath $web @{action_token=$startToken} | Out-Null
Equal (Db "SELECT COUNT(*) FROM st_matching_runs WHERE learner_user_id=$learnerId;") '3' 'Start retry duplicated run'
Equal (Status { Post $startPath $web @{action_token=$startOtherTab} }) 409 'Different start key accepted while active'
foreach ($path in @($runPath,"matching/run/$runId",$stopPath,"matching/stop/$runId")) {
	Equal (Status { Page $path $otherWeb }) 404 'Foreign run disclosed'
}
$anonymous=New-Object Microsoft.PowerShell.Commands.WebRequestSession
$anon=Page $runPath $anonymous
if ($anon.Content -notmatch 'name="identity"' -or $anon.Content -match 'Kartu_') { throw 'Anonymous matching route failed authentication.' }
$page=Page $runPath $web
Equal $page.Headers['Cache-Control'] 'private, no-store' 'Run cache control missing'
if ($page.Content -notmatch 'Awan &amp; &lt;b&gt;Belajar&lt;/b&gt;' -or $page.Content -match '<script>uji</script>') { throw 'Stored game markup was not escaped.' }
Browser $runPath 'matching-saved-run'
$stopPage=Page $stopPath $web
Browser $stopPath 'matching-saved-stop'
$staleStopToken=Hidden $stopPage 'action_token'
$left=@([regex]::Matches($page.Content,'name="left" value="([0-9]+)"') | ForEach-Object { [int]$_.Groups[1].Value })
Equal $left.Count 6 'Primary group did not use six pairs'
$moveToken=Hidden $page 'action_token'
$staleToken=Hidden (Page $runPath $web) 'action_token'
$wrong=@{revision='0';action_token=$moveToken;left=[string]$left[0];right=[string]$left[1]}
Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+$runPath) -Method Post -WebSession $web -Body @{study_csrf_token='invalid';revision='0';action_token=$moveToken;left='0';right='0'} }) 403 'Invalid CSRF accepted'
Post $runPath $web $wrong | Out-Null
Post $runPath $web $wrong | Out-Null
Equal (Db "SELECT revision FROM st_matching_runs WHERE id=$runId;") '1' 'Move retry duplicated revision'
Equal (Db "SELECT COUNT(*) FROM st_matching_moves WHERE run_id=$runId;") '1' 'Move retry duplicated log'
Equal (Status { Post $runPath $web @{revision='0';action_token=$staleToken;left=[string]$left[0];right=[string]$left[0]} }) 409 'Stale tab accepted'
Equal (Status { Post $stopPath $web @{revision='0';action_token=$staleStopToken;confirm='1'} }) 409 'Stale stop cancelled newer progress'
$page=Page $runPath $web
Equal (Hidden $page 'revision') '1' 'Refresh did not resume persisted revision'
Equal (Status { Post $runPath $web @{revision='1';action_token=(Hidden $page 'action_token');left=@('0','1');right='0'} }) 400 'Array-shaped selection accepted'
Equal (Status { Post $runPath $otherWeb @{revision='1';action_token=(Hidden $page 'action_token');left='0';right='0'} }) 404 'Foreign POST accepted'
Db "UPDATE st_courses SET is_free=0 WHERE slug='$slug';" | Out-Null
Equal (Status { Page $runPath $web }) 403 'Access loss allowed active snapshot'
Equal (Status { Post $runPath $web @{revision='1';action_token=(Hidden $page 'action_token');left=[string]$left[0];right=[string]$left[0]} }) 403 'Access loss allowed saved choice'
Equal (Db "SELECT revision FROM st_matching_runs WHERE id=$runId;") '1' 'Access loss changed revision'
Db "UPDATE st_courses SET is_free=1 WHERE slug='$slug';" | Out-Null
for ($choice=0; $choice -lt 8; $choice++) {
	$page=Page $runPath $web
	$pair=[regex]::Match($page.Content,'name="left" value="([0-9]+)"').Groups[1].Value
	if ($pair -eq '') { throw 'Missing next playable pair.' }
	$body=@{revision=(Hidden $page 'revision');action_token=(Hidden $page 'action_token');left=$pair;right=$pair}
	Post $runPath $web $body | Out-Null
}
Post $runPath $web $body | Out-Null
Equal (Db "SELECT CONCAT(status,'|',revision) FROM st_matching_runs WHERE id=$runId;") 'completed|9' 'Finalization or retry duplicated score'
$done=Page $runPath $web
if ($done.Content -notmatch 'Semua pasangan ditemukan|Selesai' -or $done.Content -match 'id="saved-matching-form"') { throw 'Completed view remains editable.' }
Browser $runPath 'matching-saved-result'
Db "UPDATE st_courses SET status='archived' WHERE slug='$slug';" | Out-Null
Equal (Status { Page $runPath $web }) 200 'Owner lost completed history after archive'
Equal (Status { Page $startPath $web }) 404 'Archived lesson started a run'
Db "UPDATE st_courses SET status='published' WHERE slug='$slug';" | Out-Null
$newToken=Hidden (Page $startPath $web) 'action_token'
Post $startPath $web @{action_token=$newToken} | Out-Null
$cancelId=[int](Db "SELECT id FROM st_matching_runs WHERE learner_user_id=$learnerId AND status='in_progress';")
$cancelPath="permainan/tersimpan/$cancelId/berhenti"
$cancelPage=Page $cancelPath $web
$cancelBody=@{revision='0';action_token=(Hidden $cancelPage 'action_token')}
Equal (Status { Post $cancelPath $web $cancelBody }) 400 'Stop without confirmation accepted'
Db "UPDATE st_courses SET is_free=0 WHERE slug='$slug';" | Out-Null
$cancelBody['confirm']='1'
Post $cancelPath $web $cancelBody | Out-Null
Post $cancelPath $web $cancelBody | Out-Null
Equal (Db "SELECT CONCAT(status,'|',revision) FROM st_matching_runs WHERE id=$cancelId;") 'abandoned|1' 'Stop after expiry or retry failed'
Equal (Db "SELECT COUNT(*) FROM st_matching_moves WHERE run_id=$runId;") '9' 'Stop damaged completed history'
Db "UPDATE st_courses SET is_free=1 WHERE slug='$slug';" | Out-Null
for ($clone=1; $clone -le 12; $clone++) {
	Db "INSERT INTO st_matching_runs(learner_user_id,lesson_id,title_snapshot,rules_version,snapshot_json,state_json,status,revision,start_key,started_at,finished_at,elapsed_seconds) SELECT learner_user_id,lesson_id,'Paging 100%_!','matching_v1',snapshot_json,state_json,'completed',revision,SHA2('qa-$suffix-$clone',256),started_at,finished_at,elapsed_seconds FROM st_matching_runs WHERE id=$runId;" | Out-Null
}
$history=Page 'permainan/riwayat?q=100%25_%21&status=completed' $web
if ($history.Content -notmatch '12 permainan ditemukan' -or $history.Content -notmatch 'halaman=2&amp;q=100%25_%21&amp;status=completed') { throw 'Literal search or preserved pagination failed.' }
$page2=Page 'permainan/riwayat?q=100%25_%21&status=completed&halaman=999999999' $web
if ($page2.Content -notmatch 'Halaman 2 dari 2') { throw 'Extreme pagination was not clamped.' }
$empty=Page 'permainan/riwayat?q=tidak-ada' $web
if ($empty.Content -notmatch 'Tidak ada hasil yang sesuai') { throw 'Filtered empty state missing.' }
$otherHistory=Page 'permainan/riwayat' $otherWeb
if ($otherHistory.Content -notmatch 'Belum ada permainan tersimpan' -or $otherHistory.Content -match 'Paging 100|Awan &amp;') { throw 'History leaked between learners.' }
Db "UPDATE st_users SET learning_stage='early_childhood' WHERE id=$otherId;" | Out-Null
$youngStartToken=Hidden (Page $startPath $otherWeb) 'action_token'
Post $startPath $otherWeb @{action_token=$youngStartToken} | Out-Null
$youngRunId=[int](Db "SELECT id FROM st_matching_runs WHERE learner_user_id=$otherId AND status='in_progress';")
$youngPage=Page "permainan/tersimpan/$youngRunId" $otherWeb
Equal ([regex]::Matches($youngPage.Content,'name="left" value="([0-9]+)"').Count) 4 'Early-childhood saved game did not use four pairs'
if ($youngPage.Content -notmatch 'theme-child-early') { throw 'Saved game did not inherit early-childhood theme.' }
Db "UPDATE st_users SET status='inactive' WHERE id=$otherId;" | Out-Null
$inactive=Page "permainan/tersimpan/$youngRunId" $otherWeb
if ($inactive.Content -notmatch 'name="identity"' -or $inactive.Content -match 'id="saved-matching-form"') { throw 'Inactive learner could read a saved game.' }
$malformed=Page 'permainan/riwayat?q%5B%5D=x&status%5B%5D=x&halaman%5B%5D=2' $web
if ($malformed.Content -match 'Warning:|Array to string') { throw 'Malformed list inputs broke history.' }
Equal (Status { Post 'permainan/riwayat' $web @{} }) 405 'History accepted POST'
Equal (Status { Page 'matching/run/1oops' $web }) 404 'Malformed direct run ID accepted'
Browser 'permainan/riwayat' 'matching-saved-history'
if ($ChromePath) {
	$interactionToken=Hidden (Page $startPath $web) 'action_token'
	Post $startPath $web @{action_token=$interactionToken} | Out-Null
	$interactionId=[int](Db "SELECT id FROM st_matching_runs WHERE learner_user_id=$learnerId AND status='in_progress';")
	Browser "permainan/tersimpan/$interactionId" 'matching-saved-interaction'
	Equal (Db "SELECT revision FROM st_matching_runs WHERE id=$interactionId;") '3' 'Browser submissions were not persisted once per viewport'
	Browser "permainan/tersimpan/$interactionId" 'matching-saved-recovery'
	Equal (Db "SELECT revision FROM st_matching_runs WHERE id=$interactionId;") '6' 'Browser transport recovery duplicated or lost a submitted choice'
	Browser "permainan/tersimpan/$interactionId" 'matching-saved-native'
	Equal (Db "SELECT revision FROM st_matching_runs WHERE id=$interactionId;") '9' 'Native form fallback failed without page scripts'
	Browser "permainan/tersimpan/$interactionId" 'matching-saved-navigation'
	Equal (Db "SELECT revision FROM st_matching_runs WHERE id=$interactionId;") '15' 'History navigation or refresh duplicated or lost a submitted choice'
}
Equal (Db "SELECT COUNT(*) FROM st_wallet_entries WHERE actor_user_id IN ($learnerId,$otherId);") '0' 'Game wrote credit ledger'
Write-Output 'PASS saved matching HTTP: signed starts/moves/stops, CSRF, ownership, escaping, resume, replay/stale tabs, access loss, completion/archive, confirmation, read-only search/filter/pagination and no credits.'
. (Join-Path $PSScriptRoot 'matching-family-cases.ps1')
