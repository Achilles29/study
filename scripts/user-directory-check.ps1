param(
	[Parameter(Mandatory=$true)][string] $Php81Path,
	[Parameter(Mandatory=$true)][string] $MysqlPath,
	[Parameter(Mandatory=$true)][string] $BaseUrl,
	[string] $ChromePath = '',
	[switch] $CaptureScreenshots
)
$ErrorActionPreference='Stop'
$parsed=$null
if (-not [Uri]::TryCreate($BaseUrl,[UriKind]::Absolute,[ref]$parsed) -or $parsed.Scheme -ne 'http' -or -not $parsed.IsLoopback -or $parsed.UserInfo -or $parsed.Query -or $parsed.Fragment) { throw 'Directory QA requires HTTP loopback without credentials/query/fragment.' }
$root=$BaseUrl.TrimEnd('/')+'/'
$suffix=[Guid]::NewGuid().ToString('N').Substring(0,12)
$prefix="qa_directory_$suffix"
$password='Temporary-Directory-QA-123!'
$hash=(& $Php81Path -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim().Replace("'","''")
if ($LASTEXITCODE -ne 0 -or -not $hash) { throw 'Cannot prepare fixture authentication.' }
$names=New-Object System.Collections.Generic.List[string]
function Db([string]$Sql) {
	$result=& $MysqlPath '-uroot' '-D' 'db_study' '-N' '-B' '-e' $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Directory QA SQL failed: $result" }
	return ($result -join "`n").Trim()
}
function Equal($Actual,$Expected,[string]$Message) { if ($Actual -ne $Expected) { throw "$Message (expected $Expected, got $Actual)" } }
function Page([string]$Path,$Web) { Invoke-WebRequest -UseBasicParsing -Uri ($root+$Path) -WebSession $Web }
function Csrf($Response) {
	$match=[regex]::Match($Response.Content,'name="study_csrf_token"\s+value="([^"]+)"')
	if (-not $match.Success) { throw 'Missing CSRF field.' }
	return [System.Net.WebUtility]::HtmlDecode($match.Groups[1].Value)
}
function Status([scriptblock]$Action) { try { return [int]((& $Action).StatusCode) } catch { if ($_.Exception.Response) { return [int]$_.Exception.Response.StatusCode }; throw } }
function Login([string]$Name) {
	$web=New-Object Microsoft.PowerShell.Commands.WebRequestSession
	$login=Page 'masuk' $web
	Invoke-WebRequest -UseBasicParsing -Uri ($root+'masuk') -WebSession $web -Method Post -Body @{identity=$Name;password=$password;study_csrf_token=(Csrf $login)} | Out-Null
	return $web
}
function Search($Web,[string]$Query,[hashtable]$Filters=@{}) {
	$body=@{q=$Query;study_csrf_token=(Csrf (Page 'kelola/pengguna' $Web))}
	foreach ($key in $Filters.Keys) { $body[$key]=$Filters[$key] }
	$response=Invoke-WebRequest -UseBasicParsing -Uri ($root+'kelola/pengguna') -WebSession $Web -Method Post -Body $body
	if ($response.BaseResponse.ResponseUri.Query -match '(\?|&)q=' -or ($Query -and $response.BaseResponse.ResponseUri.AbsoluteUri.Contains([Uri]::EscapeDataString($Query)))) { throw 'Search text leaked to redirect URL.' }
	return $response
}
function Ids($Page) { return @([regex]::Matches($Page.Content,'data-directory-id="([0-9]+)"') | ForEach-Object { $_.Groups[1].Value }) }
function Add-Fixture([string]$Tail,[string]$Kind='adult',[string]$Status='active',[string]$Role='') {
	$name=$prefix+'_'+$Tail; $names.Add($name)
	$id=[int](Db "INSERT INTO st_users (username,email,password_hash,display_name,account_kind,status,created_at,updated_at) VALUES ('$name','$name@example.test','$hash','Private Full Name $Tail','$Kind','$Status','2026-01-01 00:00:00','2026-01-01 00:00:00'); SELECT LAST_INSERT_ID();")
	if ($Role) { Db "INSERT INTO st_user_roles (user_id,role_id,granted_by_user_id,created_at) SELECT $id,id,$id,UTC_TIMESTAMP() FROM st_roles WHERE role_code='$Role';" | Out-Null }
	return $id
}
try {
	$admin=Add-Fixture 'admin' 'adult' 'active' 'system_admin'
	$secondAdmin=Add-Fixture 'admin2' 'adult' 'active' 'system_admin'
	foreach ($role in @('content_editor','finance_operator','support_agent')) { Add-Fixture $role 'adult' 'active' $role | Out-Null }
	Add-Fixture 'member' | Out-Null
	Add-Fixture 'parent' 'parent' | Out-Null
	$child=Add-Fixture 'child' 'child'
	$fixtureIds=@()
	for ($i=1; $i -le 21; $i++) { $fixtureIds+=Add-Fixture ('list_'+$i.ToString('D2')) 'child' $(if ($i -le 20) {'active'} else {'pending'}) }
	$special=Add-Fixture 'literal%_!'
	$markup=Add-Fixture '<b>markup</b>'
	Db "INSERT INTO st_user_roles (user_id,role_id,granted_by_user_id,created_at) SELECT $secondAdmin,id,$admin,UTC_TIMESTAMP() FROM st_roles WHERE role_code='content_editor';" | Out-Null
	$web=Login ($prefix+'_admin')
	$otherWeb=Login ($prefix+'_admin2')
	foreach ($tail in @('content_editor','finance_operator','support_agent','member','parent','child')) {
		$denied=Login ($prefix+'_'+$tail)
		foreach ($path in @('kelola/pengguna','user_directory/index')) { Equal (Status { Page $path $denied }) 403 "Forbidden role $tail gained directory access" }
		Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+'kelola/pengguna') -WebSession $denied -Method Post -Body @{q='x';study_csrf_token=(Csrf (Page 'beranda' $denied))} }) 403 "Forbidden role $tail could search"
		if ((Page 'beranda' $denied).Content -match 'href="[^"]*kelola/pengguna"') { throw 'Directory link leaked to non-admin dashboard.' }
	}
	$quoted=($names | ForEach-Object { "'$_'" }) -join ','
	$page=Search $web ($prefix+'_list_')
	Equal (Ids $page).Count 20 'First page size'
	Equal ((Ids $page) -join ',') (($fixtureIds[20..1] | ForEach-Object {"$_"}) -join ',') 'Stable newest order with tied dates'
	if ($page.Headers['Cache-Control'] -notmatch 'no-store' -or $page.Headers['X-Robots-Tag'] -notmatch 'noindex') { throw 'Directory privacy headers missing.' }
	if ($page.Content -match '@example.test|Private Full Name list_|password_hash|email_verified_at') { throw 'Private fields exposed.' }
	$contextUri=$page.BaseResponse.ResponseUri
	if ($page.Content -match ('href="[^"]*'+[regex]::Escape($prefix+'_list_'))) { throw 'Search text leaked to page link.' }
	$next=[regex]::Match($page.Content,'href="([^"]+)">Berikutnya').Groups[1].Value
	if (-not $next) { throw 'Missing next-page link.' }
	$page2=Invoke-WebRequest -UseBasicParsing -Uri ([System.Net.WebUtility]::HtmlDecode($next)) -WebSession $web
	Equal ((Ids $page2) -join ',') "$($fixtureIds[0])" 'Second page content'
	$last=Invoke-WebRequest -UseBasicParsing -Uri ($contextUri.AbsoluteUri+'&halaman=9999999999') -WebSession $web
	Equal ((Ids $last) -join ',') "$($fixtureIds[0])" 'High page not clamped'
	$arrayPage=Invoke-WebRequest -UseBasicParsing -Uri ($contextUri.AbsoluteUri+'&halaman%5B%5D=2') -WebSession $web
	Equal (Ids $arrayPage).Count 20 'Array page did not default to first'
	$filtered=Search $web ($prefix+'_list_') @{jenis='child';status='active';peran='none';urutan='oldest'}
	Equal ((Ids $filtered) -join ',') (($fixtureIds[0..19] | ForEach-Object {"$_"}) -join ',') 'Combined filters or ascending tie order'
	Equal ((Ids (Search $web ($prefix+'_list_') @{status='pending'})) -join ',') "$($fixtureIds[20])" 'Pending status filter'
	Equal ((Ids (Search $web ($prefix+'_list_') @{jenis='parent'})).Count) 0 'Filter combination did not intersect'
	Equal ((Ids (Search $web ($prefix+'_literal%'))).Count) 1 'Literal percent search'
	Equal ((Ids (Search $web ($prefix+'_literal_'))).Count) 0 'Underscore acted as wildcard'
	Equal ((Ids (Search $web ($prefix+'_literal%_!'))) -join ',') "$special" 'Literal special-character search'
	if ((Ids (Search $web "$child")) -notcontains "$child") { throw 'Exact ID search did not include the requested account.' }
	$escaped=Search $web ($prefix+'_<b>markup</b>')
	if ($escaped.Content -notmatch '&lt;b&gt;markup&lt;/b&gt;' -or $escaped.Content -match ($prefix+'_<b>markup</b>')) { throw 'Stored markup was not escaped.' }
	$rolePage=Search $web $prefix @{peran='system_admin'}
	Equal ((Ids $rolePage).Count) 2 'Multi-role duplicated directory rows'
	$sorted=Search $web ($prefix+'_list_') @{urutan='username'}
	Equal ((Ids $sorted) -join ',') (($fixtureIds[0..19] | ForEach-Object {"$_"}) -join ',') 'Username sorting failed'
	$invalid=Search $web ($prefix+'_list_') @{jenis='bad';status='bad';peran='bad';urutan='id DESC; DROP TABLE st_users'}
	Equal ((Ids $invalid).Count) 20 'Allowlist defaults failed'
	$contextUri=(Search $web ($prefix+'_list_')).BaseResponse.ResponseUri
	Search $web ($prefix+'_literal%_!') | Out-Null
	Equal (Ids (Invoke-WebRequest -UseBasicParsing -Uri $contextUri.AbsoluteUri -WebSession $web)).Count 20 'Another search overwrote an existing tab context'
	Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri $contextUri.AbsoluteUri -WebSession $web }) 200 'Fresh search context failed for owner'
	Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri $contextUri.AbsoluteUri -WebSession $otherWeb }) 400 'Search context leaked to another session/admin'
	$secondSame=Login ($prefix+'_admin')
	Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri $contextUri.AbsoluteUri -WebSession $secondSame }) 400 'Search context leaked to independent login of same admin'
	foreach ($query in @('pencarian=unknown','pencarian%5B%5D=x')) { Equal (Status { Page ('kelola/pengguna?'+$query) $web }) 400 'Invalid context opened full directory' }
	Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+'kelola/pengguna') -WebSession $web -Method Post -Body @{q='x';study_csrf_token='invalid'} }) 403 'Invalid CSRF accepted'
	Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+'kelola/pengguna') -WebSession $web -Method Post -Body @{'q[]'='x';study_csrf_token=(Csrf (Page 'kelola/pengguna' $web))} }) 400 'Array search accepted'
	$firstContext=(Search $web 'no-result-first').BaseResponse.ResponseUri.AbsoluteUri
	for ($i=0; $i -lt 10; $i++) { Search $web ('no-result-'+$i) | Out-Null }
	Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri $firstContext -WebSession $web }) 400 'Search context bound not enforced'
	$long=Search $web (([string][char]0x754C)*120)
	if ($long.Content -match 'Warning:|Array to string') { throw 'Long UTF-8 broke directory.' }
	$searchValue=[System.Net.WebUtility]::HtmlDecode([regex]::Match($long.Content,'name="q" maxlength="100" value="([^"]*)"').Groups[1].Value)
	Equal $searchValue.Length 100 'UTF-8 query was not truncated at a character boundary'
	Equal (Ids (Search $web ($prefix+'_list_01@example.test'))).Count 0 'Email field was searchable'
	Equal (Ids (Search $web 'Private Full Name list_01')).Count 0 'Full name field was searchable'
	Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+'kelola/pengguna') -WebSession $web -Method Head }) 200 'HEAD rejected'
	Equal (Status { Invoke-WebRequest -UseBasicParsing -Uri ($root+'user_directory/index') -WebSession $web -Method Delete }) 405 'Unexpected method accepted'
	$empty=Search $web ($prefix+'_nothing')
	if ($empty.Content -notmatch 'Tidak ada akun yang sesuai') { throw 'Missing filtered empty state.' }
	# Login itself legitimately updates last-login/audit; take a new baseline after the second session login.
	$beforeRead=Db "SELECT SUM(CRC32(CONCAT(id,username,status,account_kind,COALESCE(last_login_at,''),updated_at))),COUNT(*) FROM st_users WHERE username IN ($quoted); SELECT COUNT(*) FROM st_user_roles WHERE user_id IN (SELECT id FROM st_users WHERE username IN ($quoted)); SELECT COUNT(*) FROM st_audit_events;"
	Page 'user_directory/index' $web | Out-Null
	Search $web $prefix @{status='active'} | Out-Null
	$afterRead=Db "SELECT SUM(CRC32(CONCAT(id,username,status,account_kind,COALESCE(last_login_at,''),updated_at))),COUNT(*) FROM st_users WHERE username IN ($quoted); SELECT COUNT(*) FROM st_user_roles WHERE user_id IN (SELECT id FROM st_users WHERE username IN ($quoted)); SELECT COUNT(*) FROM st_audit_events;"
	Equal $afterRead $beforeRead 'Read/search mutated user, roles or audit'
	Db "DELETE FROM st_user_roles WHERE user_id=$secondAdmin AND role_id=(SELECT id FROM st_roles WHERE role_code='system_admin');" | Out-Null
	Equal (Status { Page 'kelola/pengguna' $otherWeb }) 403 'Revoked admin retained directory access'
	Db "UPDATE st_users SET status='suspended' WHERE id=$admin;" | Out-Null
	if ((Page 'kelola/pengguna' $web).Content -notmatch 'name="identity"') { throw 'Suspended admin retained access.' }
	Db "UPDATE st_users SET status='active' WHERE id=$admin;" | Out-Null
	. (Join-Path $PSScriptRoot 'audit-log-cases.ps1')
	if ($ChromePath) {
		$oldIdentity=$env:STUDY_QA_LOGIN_IDENTITY; $oldPassword=$env:STUDY_QA_LOGIN_PASSWORD
		try {
			$env:STUDY_QA_LOGIN_IDENTITY=$prefix+'_admin'; $env:STUDY_QA_LOGIN_PASSWORD=$password
			$args=@((Join-Path $PSScriptRoot 'browser-viewport-check.mjs'),'--base-url',$root,'--chrome',$ChromePath,'--extra-path','kelola/pengguna','--extra-name','admin-users','--only-extra-route')
			if ($CaptureScreenshots) { $args+='--capture-screenshots' }
			& node @args
			if ($LASTEXITCODE -ne 0) { throw 'Directory browser check failed.' }
			$filteredArgs=@((Join-Path $PSScriptRoot 'browser-viewport-check.mjs'),'--base-url',$root,'--chrome',$ChromePath,'--extra-path','kelola/pengguna','--extra-query','status=pending','--extra-name','admin-users-filtered','--only-extra-route')
			if ($CaptureScreenshots) { $filteredArgs+='--capture-screenshots' }
			& node @filteredArgs
			if ($LASTEXITCODE -ne 0) { throw 'Filtered directory browser check failed.' }
		} finally { $env:STUDY_QA_LOGIN_IDENTITY=$oldIdentity; $env:STUDY_QA_LOGIN_PASSWORD=$oldPassword }
	}
	Write-Output 'PASS directory role isolation, privacy, private session search, literal/combined filters, pagination/sorts, escaping, CSRF, context bounds, revocation, and read-only database behavior.'
} finally {
	if ($names.Count) {
		$quoted=($names | ForEach-Object { "'$_'" }) -join ','
		Db "START TRANSACTION; DELETE a FROM st_audit_events a JOIN st_users u ON u.id=a.actor_user_id WHERE u.username IN ($quoted); DELETE ur FROM st_user_roles ur JOIN st_users u ON u.id=ur.user_id WHERE u.username IN ($quoted); DELETE FROM st_users WHERE username IN ($quoted); COMMIT;" | Out-Null
		Equal (Db "SELECT COUNT(*) FROM st_users WHERE username IN ($quoted);") '0' 'Directory fixtures remain'
	}
	Write-Output 'PASS exact directory fixtures cleaned.'
}
