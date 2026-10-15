# Uses exact synthetic data and helpers from matching-http-cases.ps1; cleanup belongs to matching-storage-check.ps1.
$parent="qa_matching_parent_$suffix"
$stranger="qa_matching_stranger_$suffix"
$emptyChild="qa_matching_empty_$suffix"
$names += ",'$parent','$stranger','$emptyChild'"
Db @"
INSERT INTO st_users(username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES
('$parent','$passwordHash','Orang Tua Uji','parent','unset','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$stranger','$passwordHash','Keluarga Lain','parent','unset','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()),
('$emptyChild','$passwordHash','Adik Uji','child','primary','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());
UPDATE st_users SET status='active' WHERE id=$otherId;
UPDATE st_users SET display_name='<b>Anak Uji</b>' WHERE id=$learnerId;
INSERT INTO st_family_links(parent_user_id,child_user_id,status,created_at,updated_at)
SELECT p.id,c.id,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_users p JOIN st_users c ON c.username IN ('$learner','$emptyChild') WHERE p.username='$parent';
INSERT INTO st_family_links(parent_user_id,child_user_id,status,created_at,updated_at)
SELECT p.id,c.id,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM st_users p JOIN st_users c ON c.username='$other' WHERE p.username='$stranger';
"@ | Out-Null
$parentId=[int](Db "SELECT id FROM st_users WHERE username='$parent';")
$strangerId=[int](Db "SELECT id FROM st_users WHERE username='$stranger';")
$emptyChildId=[int](Db "SELECT id FROM st_users WHERE username='$emptyChild';")
$parentWeb=Login $parent
$strangerWeb=Login $stranger
$activeId=Db "SELECT id FROM st_matching_runs WHERE learner_user_id=$learnerId AND status='in_progress';"
if (-not $activeId) {
	$token=Hidden (Page $startPath $web) 'action_token'
	Post $startPath $web @{action_token=$token} | Out-Null
	$activeId=Db "SELECT id FROM st_matching_runs WHERE learner_user_id=$learnerId AND status='in_progress';"
}
$reportPath="keluarga/anak/$learnerId/permainan"
$resultPath="$reportPath/$runId"
$activeReportPath="$reportPath/$activeId"
$sourceCounts=(Db "SELECT CONCAT(SUM(status='completed'),'|',SUM(status='in_progress'),'|',SUM(status='abandoned')) FROM st_matching_runs WHERE learner_user_id=$learnerId;") -split '\|'
function MatchingDigest {
	return Db "SET SESSION group_concat_max_len=65536; SELECT SHA2(GROUP_CONCAT(CONCAT(id,':',revision,':',status,':',SHA2(state_json,256),':',SHA2(snapshot_json,256)) ORDER BY id SEPARATOR '|'),256) FROM st_matching_runs WHERE learner_user_id IN ($learnerId,$otherId); SELECT COUNT(*) FROM st_matching_moves; SELECT COUNT(*) FROM st_audit_events WHERE entity_type='matching_run'; SELECT COUNT(*) FROM st_wallet_entries;"
}
$before=MatchingDigest
$report=Page $reportPath $parentWeb
Equal $report.Headers['Cache-Control'] 'private, no-store' 'Family game cache control missing'
Equal $report.Headers['X-Robots-Tag'] 'noindex, nofollow' 'Family game noindex missing'
$separator=[char]0xB7
$expectedSummary="$($sourceCounts[0]) selesai $separator $($sourceCounts[1]) sedang bermain $separator $($sourceCounts[2]) dihentikan"
if (-not $report.Content.Contains($expectedSummary) -or $report.Content -notmatch '&lt;b&gt;Anak Uji&lt;/b&gt;') { throw 'Family summary differs from source or child name is unescaped.' }
$result=Page $resultPath $parentWeb
if ($result.Content -notmatch '8 dari 8 pasangan' -or $result.Content -notmatch '9 pilihan' -or $result.Content -notmatch '1 pilihan' -or $result.Content -notmatch 'termasuk jeda' -or $result.Content -match 'Kartu_&lt;script&gt;|name="action_token"|name="left"|saved-matching-form|snapshot_json') { throw 'Family result counts or privacy boundary is invalid.' }
$activeReport=Page $activeReportPath $parentWeb
if ($activeReport.Content -notmatch 'progres sementara, bukan hasil akhir' -or $activeReport.Content -match 'name="action_token"|name="left"|Kartu_&lt;script&gt;') { throw 'Active family report leaked gameplay or claimed completion.' }
$abandoned=Page "$reportPath/$cancelId" $parentWeb
if ($abandoned.Content -notmatch 'dihentikan, bukan diselesaikan') { throw 'Abandoned game was misrepresented.' }
foreach ($path in @($reportPath,$resultPath,"family/matching/$learnerId","family/matching_result/$learnerId/$runId")) {
	Equal (Status { Page $path $strangerWeb }) 404 'Another family read a child game report'
	Equal (Status { Page $path $web }) 403 'Child used parent report route'
	Equal (Status { Post $path $parentWeb @{} }) 405 'Read-only family route accepted POST'
}
Equal (Status { Page "$reportPath/$youngRunId" $parentWeb }) 404 'Run from another child was disclosed'
Equal (Status { Page "family/matching/$($learnerId)bad" $parentWeb }) 404 'Malformed direct child ID accepted'
Equal (Status { Page "family/matching_result/$learnerId/$($runId)bad" $parentWeb }) 404 'Malformed direct run ID accepted'
Equal (Status { Post "permainan/tersimpan/$activeId" $parentWeb @{revision='0';action_token='forged';left='0';right='0'} }) 404 'Parent used child mutation route'
Equal (Status { Post "permainan/tersimpan/$activeId/berhenti" $parentWeb @{revision='0';action_token='forged';confirm='1'} }) 404 'Parent stopped child run'
$emptyReport=Page "keluarga/anak/$emptyChildId/permainan" $parentWeb
if ($emptyReport.Content -notmatch 'Belum ada permainan tersimpan' -or -not $emptyReport.Content.Contains("0 selesai $separator 0 sedang bermain $separator 0 dihentikan")) { throw 'Empty child report is incorrect.' }
$filtered=Page ($reportPath+'?q=100%25_%21&status=completed') $parentWeb
if ($filtered.Content -notmatch '12 permainan sesuai filter' -or $filtered.Content -notmatch 'halaman=2&amp;q=100%25_%21&amp;status=completed' -or -not $filtered.Content.Contains($expectedSummary)) { throw 'Family literal search, filter preservation or all-history summary failed.' }
$paged=Page ($reportPath+'?q=100%25_%21&status=completed&halaman=999999999') $parentWeb
if ($paged.Content -notmatch 'Halaman 2 dari 2') { throw 'Family game pagination did not clamp.' }
$emptyFilter=Page ($reportPath+'?q=tidak-ada') $parentWeb
if ($emptyFilter.Content -notmatch 'Tidak ada hasil yang sesuai') { throw 'Family filtered empty state missing.' }
$malformed=Page ($reportPath+'?q%5B%5D=x&status%5B%5D=x&halaman%5B%5D=2') $parentWeb
if ($malformed.Content -match 'Warning:|Array to string') { throw 'Malformed family game filters broke page.' }
Db "UPDATE st_courses SET is_free=0,status='archived' WHERE slug='$slug';" | Out-Null
Equal (Status { Page $resultPath $parentWeb }) 200 'Completed report disappeared after archive/access expiry'
Equal (Status { Page $activeReportPath $parentWeb }) 200 'Active read-only counts disappeared after archive'
Db "UPDATE st_courses SET is_free=1,status='published' WHERE slug='$slug'; UPDATE st_family_links SET status='revoked' WHERE parent_user_id=$parentId AND child_user_id=$learnerId;" | Out-Null
Equal (Status { Page $reportPath $parentWeb }) 404 'Revoked family link retained list access'
Equal (Status { Page $resultPath $parentWeb }) 404 'Revoked family link retained result access'
Db "UPDATE st_family_links SET status='active' WHERE parent_user_id=$parentId AND child_user_id=$learnerId; UPDATE st_users SET status='inactive' WHERE id=$learnerId;" | Out-Null
Equal (Status { Page $resultPath $parentWeb }) 404 'Inactive child retained report access'
Db "UPDATE st_users SET status='active' WHERE id=$learnerId; UPDATE st_users SET account_kind='adult' WHERE id=$parentId;" | Out-Null
Equal (Status { Page $reportPath $parentWeb }) 403 'Non-parent kind accessed report'
Db "UPDATE st_users SET account_kind='parent',status='inactive' WHERE id=$parentId;" | Out-Null
$inactiveParent=Page $resultPath $parentWeb
if ($inactiveParent.Content -notmatch 'name="identity"') { throw 'Inactive parent retained authenticated report.' }
Db "UPDATE st_users SET status='active' WHERE id=$parentId;" | Out-Null
$parentWeb=Login $parent
Db "INSERT INTO st_family_links(parent_user_id,child_user_id,status,created_at,updated_at) VALUES($strangerId,$learnerId,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP());" | Out-Null
Equal (Status { Page $resultPath $strangerWeb }) 200 'Second actively linked parent could not read shared course game history'
Equal (MatchingDigest) $before 'Family report mutated game state, log, matching audit or ledger'
$dashboard=Page 'beranda' $parentWeb
if (-not $dashboard.Content.Contains($reportPath)) { throw 'Parent dashboard is missing game-report navigation.' }
Browser $reportPath 'matching-family-report' $parent
Browser $resultPath 'matching-family-result' $parent
Browser $activeReportPath 'matching-family-active' $parent
Browser 'beranda' 'matching-family-dashboard' $parent
Write-Output 'PASS family game reports: source totals, all statuses, private snapshots/tokens, read-only endpoints, literal filters/paging, child/parent/link/role isolation, archive history, shared linked parent, dashboard links and no game/audit/ledger mutation.'
