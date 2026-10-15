# Fixture lifecycle and helpers are owned by parent-practice-check.ps1.
if (-not $parentWeb -or -not $parentId -or $packageSlug -notlike 'qa-parent-draft-*') { throw 'Run parent-practice-check.ps1, not this case file.' }
function ListResult([string] $Query, [int] $Expected, $Web = $parentWeb) {
	$response = Page ('keluarga/latihan?'+$Query) $Web
	$match = [regex]::Match($response.Content,'data-session-total="([0-9]+)"')
	if (-not $match.Success) { throw 'Missing filtered session total.' }
	Equal ([int]$match.Groups[1].Value) $Expected "Filtered total differs from dataset: $Query"
	return $response
}
$otherId = [int](Db "SELECT id FROM st_users WHERE username='$other';")
$filterRows = @(1..11 | ForEach-Object { "($parentId,$childId,'Daftar latihan $_','parent_assigned','draft',NULL,NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP())" })
$filterRows += "($parentId,$siblingId,'Daftar latihan adik','parent_assigned','draft',NULL,NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP())"
$filterRows += "($parentId,$childId,'Daftar latihan nanti','parent_assigned','available',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY),UTC_TIMESTAMP(),UTC_TIMESTAMP())"
$filterRows += "($parentId,$childId,'Daftar latihan siap','parent_assigned','available',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY),UTC_TIMESTAMP(),UTC_TIMESTAMP())"
$filterRows += "($parentId,$childId,'Daftar latihan berjalan','parent_assigned','in_progress',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY),UTC_TIMESTAMP(),UTC_TIMESTAMP())"
$filterRows += "($parentId,$childId,'Daftar latihan lewat','parent_assigned','available',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),UTC_TIMESTAMP(),UTC_TIMESTAMP())"
$filterRows += "($parentId,$childId,'Daftar latihan terlambat','parent_assigned','in_progress',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),UTC_TIMESTAMP(),UTC_TIMESTAMP())"
$filterRows += "($parentId,$childId,'Daftar latihan selesai','parent_assigned','completed',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),UTC_TIMESTAMP(),UTC_TIMESTAMP())"
$filterRows += "($parentId,$childId,'Daftar latihan batal','parent_assigned','cancelled',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),UTC_TIMESTAMP(),UTC_TIMESTAMP())"
$filterRows += "($parentId,$childId,'Daftar latihan expired','parent_assigned','expired',NULL,NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP())"
$filterRows += "($otherId,$childId,'Daftar latihan rahasia orang tua kedua','parent_assigned','draft',NULL,NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP())"
$filterRows += "($parentId,$childId,'Literal %_! 0','parent_assigned','draft',NULL,NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP())"
Db ('INSERT INTO st_practice_sessions(created_by_user_id,learner_user_id,title,session_type,status,available_from,available_until,created_at,updated_at) VALUES '+($filterRows -join ',')+';') | Out-Null
$query = 'q=Daftar%20latihan&status=draft&anak='+$childId
$first = ListResult $query 11
Equal ([regex]::Matches($first.Content,'href="[^"]*keluarga/latihan/[0-9]+"').Count) 10 'Filtered first page has ten cards.'
$nextMatch = [regex]::Match($first.Content,'href="([^"]+)"[^>]*>Berikutnya</a>')
if (-not $nextMatch.Success) { throw 'Missing filtered next page.' }
$nextUrl = [System.Net.WebUtility]::HtmlDecode($nextMatch.Groups[1].Value)
foreach ($parameter in @('halaman=2','q=Daftar%20latihan','status=draft',('anak='+$childId))) { if (-not $nextUrl.Contains($parameter)) { throw "Pagination lost filter $parameter" } }
$nextPage = Invoke-WebRequest -UseBasicParsing -Uri $nextUrl -WebSession $parentWeb
Equal ([regex]::Matches($nextPage.Content,'href="[^"]*keluarga/latihan/[0-9]+"').Count) 1 'Following real link preserves filters and last-page count.'
if ($nextPage.Content -notmatch 'Halaman 2 dari 2' -or $nextPage.Content -match 'rahasia orang tua kedua') { throw 'Filtered paging leaked other creator or wrong page.' }
$last = ListResult ($query+'&halaman=9999999999') 11
if ($last.Content -notmatch 'Halaman 2 dari 2') { throw 'Extreme page must clamp to filtered last page.' }
$arrayPage = ListResult ($query+'&halaman%5B%5D=2') 11
if ($arrayPage.Content -notmatch 'Halaman 1 dari 2') { throw 'Array page must default to first.' }
foreach ($pair in @(@('draft',12),@('scheduled',1),@('available',1),@('in_progress',1),@('completed',1),@('cancelled',1),@('expired',3))) {
	$filtered = ListResult ('q=Daftar%20latihan&status='+$pair[0]) $pair[1]
}
ListResult ('q=Daftar%20latihan&anak='+$siblingId) 1 | Out-Null
ListResult 'q=Daftar%20latihan' 20 | Out-Null
ListResult 'q=Daftar%20latihan' 1 $otherWeb | Out-Null
$none = ListResult ('q=Daftar%20latihan&anak='+$parentId) 0
if ($none.Content -notmatch 'Tidak ada sesi sesuai filter|Anak tidak tersedia' -or $none.Content -match '<h3 class="h5">Belum ada sesi') { throw 'Filtered empty state must not imply empty account.' }
ListResult 'q=%25' 1 | Out-Null
ListResult 'q=%5F' 1 | Out-Null
ListResult 'q=%21' 1 | Out-Null
ListResult 'q=%25%5F%21' 1 | Out-Null
ListResult 'q=Literal%20%25%5F%21%200' 1 | Out-Null
$zeroExpected = [int](Db "SELECT COUNT(*) FROM st_practice_sessions WHERE created_by_user_id=$parentId AND title LIKE '%0%';")
ListResult 'q=0' $zeroExpected | Out-Null
ListResult 'q=%27%20OR%201%3D1%20--' 0 | Out-Null
ListResult 'q=Daftar%20latihan&status=unknown&anak=invalid' 20 | Out-Null
ListResult 'q=Daftar%20latihan&status%5B%5D=draft&anak%5B%5D=1' 20 | Out-Null
$allExpected = [int](Db "SELECT COUNT(*) FROM st_practice_sessions WHERE created_by_user_id=$parentId;")
ListResult 'q%5B%5D=Daftar' $allExpected | Out-Null
$unicode = [string]::new([char]0x00e9,110)
$unicodePage = ListResult ('q='+[Uri]::EscapeDataString($unicode)) 0
$searchValue = [regex]::Match($unicodePage.Content,'id="session-search"[^>]*value="([^"]*)"').Groups[1].Value
Equal ([System.Net.WebUtility]::HtmlDecode($searchValue).Length) 100 'Search truncates UTF-8 by characters, not bytes.'
$markup = ListResult ('q='+[Uri]::EscapeDataString('<svg onload=alert(1)>')) 0
if ($markup.Content -match '<svg onload=alert' -or $markup.Content -match 'Severity: Warning|PHP Warning') { throw 'Search must not execute markup or trigger warnings.' }
$beforeRead = Db "SELECT CONCAT(COUNT(*),'|',SUM(draft_revision),'|',(SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$parentId)) FROM st_practice_sessions WHERE created_by_user_id=$parentId;"
ListResult 'q=Daftar%20latihan&status=expired' 3 | Out-Null
$afterRead = Db "SELECT CONCAT(COUNT(*),'|',SUM(draft_revision),'|',(SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$parentId)) FROM st_practice_sessions WHERE created_by_user_id=$parentId;"
Equal $afterRead $beforeRead 'Filtering must not mutate sessions or create audits.'
Db "UPDATE st_family_links SET status='revoked' WHERE parent_user_id=$parentId AND child_user_id=$childId;" | Out-Null
ListResult ('q=Daftar%20latihan&anak='+$childId) 0 | Out-Null
ListResult 'q=Daftar%20latihan' 1 | Out-Null
Db "UPDATE st_family_links SET status='active' WHERE parent_user_id=$parentId AND child_user_id=$childId;" | Out-Null
Db "UPDATE st_entitlements SET status='revoked' WHERE user_id=$parentId;" | Out-Null
ListResult $query 11 | Out-Null
Db "UPDATE st_entitlements SET status='active' WHERE user_id=$parentId;" | Out-Null
Browser ('keluarga/latihan?'+$query) 'family-session-filters'
Browser ('keluarga/latihan?q=tidak-ada-kecocokan') 'family-session-empty-filter'
Write-Output 'PASS family list: literal search, child/status combinations, derived schedule/expiry, scoped counts, pagination links/bounds, malformed input, Unicode, privacy, revocation, and read-only filtering.'
