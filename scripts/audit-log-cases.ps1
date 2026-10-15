# Dot-sourced by user-directory-check.ps1; reuse only its exact synthetic accounts.
$auditIds=New-Object System.Collections.Generic.List[int]
function Add-Audit([string]$When,[string]$Event='content.course_updated',[string]$Entity='course',[string]$Actor="$admin",[string]$EntityId="$child") {
	$id=[int](Db "INSERT INTO st_audit_events (actor_user_id,event_type,entity_type,entity_id,correlation_id,details_json,created_at) VALUES ($Actor,'$Event','$Entity',$EntityId,'qa-private-correlation-$suffix',JSON_OBJECT('private','qa-private-answer-$suffix'),'$When'); SELECT LAST_INSERT_ID();")
	$auditIds.Add($id); return $id
}
function Audit-Ids($Response) { return @([regex]::Matches($Response.Content,'data-audit-id="([0-9]+)"') | ForEach-Object { $_.Groups[1].Value }) }
try {
	$web=Login ($prefix+'_admin')
	$before=Add-Audit '2026-01-31 16:59:59' 'auth.login' 'user'
	$ties=@(); for ($i=0; $i -lt 21; $i++) { $ties+=Add-Audit '2026-01-31 17:00:00' }
	$end=Add-Audit '2026-02-01 16:59:59' 'matching.completed' 'matching_run'
	$after=Add-Audit '2026-02-01 17:00:00' 'auth.logout' 'user'
	$unknown=Add-Audit '2026-02-04 17:00:00' '<script>private-event</script>' '<b>private-entity</b>' 'NULL' 'NULL'
	$query="mulai=2026-02-01&akhir=2026-02-01&pelaku=$admin"
	$first=Page ('kelola/audit?'+$query) $web
	Equal (Audit-Ids $first).Count 20 'Audit first page size'
	Equal ((Audit-Ids $first) -join ',') ((@($end)+$ties[20..2]) -join ',') 'WIB day or descending tie ordering wrong'
	if ($first.Content -notmatch 'dari 22 peristiwa' -or $first.Content -notmatch '1 Feb 2026, 00.00 WIB' -or $first.Content -notmatch '1 Feb 2026, 23.59 WIB') { throw 'Audit filtered count/date labels incorrect.' }
	if ($first.Headers['Cache-Control'] -notmatch 'no-store' -or $first.Headers['X-Robots-Tag'] -notmatch 'noindex') { throw 'Audit privacy headers missing.' }
	$next=[System.Net.WebUtility]::HtmlDecode([regex]::Match($first.Content,'href="([^"]+)">Berikutnya').Groups[1].Value)
	if (-not $next -or $next -notmatch "pelaku=$admin" -or $next -notmatch 'mulai=2026-02-01') { throw 'Audit pagination lost filters.' }
	$second=Invoke-WebRequest -UseBasicParsing -Uri $next -WebSession $web
	Equal ((Audit-Ids $second) -join ',') (($ties[1..0]) -join ',') 'Audit second page wrong'
	Equal ((Audit-Ids (Page ('kelola/audit?'+$query+'&halaman=9999999999') $web)) -join ',') (($ties[1..0]) -join ',') 'Audit high page not clamped'
	Equal (Audit-Ids (Page ('kelola/audit?'+$query+'&halaman%5B%5D=x') $web)).Count 20 'Audit array page fallback wrong'
	$oldest=Page ('kelola/audit?'+$query+'&urutan=oldest') $web
	Equal ((Audit-Ids $oldest) -join ',') (($ties[0..19]) -join ',') 'Audit ascending tie order wrong'
	$combined=Page ('kelola/audit?'+$query+"&aksi=content.course_updated&entitas=course&id_entitas=$child") $web
	if ($combined.Content -notmatch 'dari 21 peristiwa') { throw 'Combined audit filters incorrect.' }
	Equal ((Audit-Ids (Page ('kelola/audit?'+$query+'&aksi=matching.completed') $web)) -join ',') "$end" 'Audit action filter wrong'
	Equal (Audit-Ids (Page ("kelola/audit?mulai=2026-02-01&akhir=2026-02-01&pelaku=$child") $web)).Count 0 'Audit actor filter wrong'
	$other=Page 'kelola/audit?mulai=2026-02-05&akhir=2026-02-05&aksi=other&entitas=other' $web
	Equal ((Audit-Ids $other) -join ',') "$unknown" 'Unknown audit event missing'
	if ($other.Content -notmatch 'Peristiwa lainnya' -or $other.Content -notmatch 'Tidak tercatat' -or $other.Content -match 'private-event|private-entity|qa-private-correlation|qa-private-answer|details_json|correlation_id') { throw 'Audit unknown labels or metadata privacy failed.' }
	if ($first.Content -match 'qa-private-answer|qa-private-correlation|@example.test') { throw 'Private audit values leaked.' }
	foreach ($bad in @('mulai=2026-02-30','mulai=2101-01-01','mulai=1969-12-31','mulai=2026-02-02&akhir=2026-02-01','mulai%5B%5D=x','akhir=not-a-date','pelaku=-1','pelaku=0001','id_entitas%5B%5D=x','id_entitas=9999999999999999999')) {
		Equal (Status { Page ('kelola/audit?'+$bad) $web }) 400 "Invalid audit filter accepted: $bad"
		try { Page ('kelola/audit?'+$bad) $web | Out-Null } catch {
			$reader=New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream()); $body=$reader.ReadToEnd(); $reader.Dispose()
			if ($body -notmatch 'aria-invalid="true"' -or $body -match 'data-audit-id=|Warning:|Array to string') { throw 'Invalid audit filter disclosed rows or lacked field errors.' }
		}
	}
	Equal (Status { Page 'kelola/audit?mulai=2024-02-29&akhir=2024-02-29' $web }) 200 'Valid leap day rejected'
	$invalidSort=Page ('kelola/audit?'+$query+'&urutan=id%20DESC%3BDROP&aksi%5B%5D=x') $web
	Equal ((Audit-Ids $invalidSort) -join ',') ((Audit-Ids $first) -join ',') 'Audit allowlist defaults failed'
	Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+'audit_log/index') -WebSession $web -Method Head }) 200 'Audit HEAD rejected'
	Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+'kelola/audit') -WebSession $web -Method Post -Body @{study_csrf_token=(Csrf (Page 'beranda' $web))} }) 405 'Audit accepted POST'
	Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+'audit_log/index') -WebSession $web -Method Delete }) 405 'Audit accepted DELETE'
	foreach ($tail in @('content_editor','finance_operator','support_agent','member','parent','child')) {
		$denied=Login ($prefix+'_'+$tail)
		foreach ($route in @('kelola/audit','audit_log/index')) { Equal (Status { Page $route $denied }) 403 "Audit exposed to role $tail" }
	}
	$ids=$auditIds -join ','
	$baseline=Db "SELECT COUNT(*),SUM(CRC32(CONCAT(id,COALESCE(actor_user_id,''),event_type,entity_type,COALESCE(entity_id,''),COALESCE(correlation_id,''),COALESCE(details_json,''),created_at))) FROM st_audit_events WHERE id IN ($ids); SELECT COUNT(*) FROM st_audit_events;"
	Page ('kelola/audit?'+$query) $web | Out-Null; Page 'audit_log/index?aksi=other' $web | Out-Null
	Equal (Db "SELECT COUNT(*),SUM(CRC32(CONCAT(id,COALESCE(actor_user_id,''),event_type,entity_type,COALESCE(entity_id,''),COALESCE(correlation_id,''),COALESCE(details_json,''),created_at))) FROM st_audit_events WHERE id IN ($ids); SELECT COUNT(*) FROM st_audit_events;") $baseline 'Audit read changed data or added audit rows'
	Db "DELETE FROM st_user_roles WHERE user_id=$admin;" | Out-Null
	Equal (Status { Page 'kelola/audit' $web }) 403 'Revoked admin retained audit access'
	Db "INSERT INTO st_user_roles (user_id,role_id,granted_by_user_id,created_at) SELECT $admin,id,$admin,UTC_TIMESTAMP() FROM st_roles WHERE role_code='system_admin'; UPDATE st_users SET status='suspended' WHERE id=$admin;" | Out-Null
	if ((Page 'kelola/audit' $web).Content -notmatch 'name="identity"') { throw 'Suspended admin retained audit access.' }
	Db "UPDATE st_users SET status='active' WHERE id=$admin;" | Out-Null
	if ($ChromePath) {
		$oldIdentity=$env:STUDY_QA_LOGIN_IDENTITY; $oldPassword=$env:STUDY_QA_LOGIN_PASSWORD
		try {
			$env:STUDY_QA_LOGIN_IDENTITY=$prefix+'_admin'; $env:STUDY_QA_LOGIN_PASSWORD=$password
			foreach ($case in @(@{Name='audit-list';Query=''},@{Name='audit-filtered';Query="mulai=2026-02-01&akhir=2026-02-01&aksi=matching.completed&pelaku=$admin"})) {
				$arguments=@((Join-Path $PSScriptRoot 'browser-viewport-check.mjs'),'--base-url',$root,'--chrome',$ChromePath,'--extra-path','kelola/audit','--extra-name',$case.Name,'--only-extra-route')
				if ($case.Query) { $arguments+=@('--extra-query',$case.Query) }
				if ($CaptureScreenshots) { $arguments+='--capture-screenshots' }
				& node @arguments
				if ($LASTEXITCODE -ne 0) { throw 'Audit browser check failed.' }
			}
		} finally { $env:STUDY_QA_LOGIN_IDENTITY=$oldIdentity; $env:STUDY_QA_LOGIN_PASSWORD=$oldPassword }
	}
	Write-Output 'PASS audit: admin-only, revocation, read-only methods/data, metadata exclusion, unknown/null labels, WIB boundaries, valid calendars, AND filters, stable sorts and pagination.'
} finally {
	if ($auditIds.Count) { Db ('DELETE FROM st_audit_events WHERE id IN ('+($auditIds -join ',')+');') | Out-Null }
	Write-Output 'PASS exact audit fixtures removed, including null-actor event.'
}
