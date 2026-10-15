param(
	[Parameter(Mandatory=$true)][string]$Php81Path,
	[Parameter(Mandatory=$true)][string]$MysqlPath,
	[Parameter(Mandatory=$true)][string]$BaseUrl,
	[Parameter(Mandatory=$true)][string]$AppEncryptionKey,
	[string]$ChromePath='', [switch]$CaptureScreenshots
)
$ErrorActionPreference='Stop'
$parsed=$null
if (-not [Uri]::TryCreate($BaseUrl,[UriKind]::Absolute,[ref]$parsed) -or $parsed.Scheme -ne 'http' -or -not $parsed.IsLoopback -or $parsed.UserInfo -or $parsed.Query -or $parsed.Fragment -or -not $AppEncryptionKey) { throw 'Security QA requires HTTP loopback and the matching application key.' }
$root=$BaseUrl.TrimEnd('/')+'/'
$prefix='qa_security_'+[Guid]::NewGuid().ToString('N').Substring(0,12)
$old='QA-current-secret-16!'; $new='QA-replaced-secret-16!'
$hash=(& $Php81Path -r "echo password_hash('$old', PASSWORD_DEFAULT);").Trim()
if ($LASTEXITCODE -ne 0 -or -not $hash) { throw 'Cannot prepare password fixture.' }
$ids=@{}; $names=@(); $failureHashes=@()
function Db($Sql) { $out=& $MysqlPath -uroot -D db_study -N -B -e $Sql 2>&1; if ($LASTEXITCODE -ne 0) { throw 'Security QA database query failed.' }; return ($out -join "`n").Trim() }
function Equal($Actual,$Expected,$Label) { if ($Actual -ne $Expected) { throw "Security check failed: $Label" } }
function Digest($Kind,$Value) {
	$h=[Security.Cryptography.HMACSHA256]::new([Text.Encoding]::UTF8.GetBytes($AppEncryptionKey))
	try { return ([BitConverter]::ToString($h.ComputeHash([Text.Encoding]::UTF8.GetBytes($Kind+'|'+$Value)))).Replace('-','').ToLowerInvariant() } finally { $h.Dispose() }
}
function Page($Path,$Web,$Method='Get',$Body=$null) {
	try { return Invoke-WebRequest -UseBasicParsing -Uri ($root+$Path) -WebSession $Web -Method $Method -Body $Body }
	catch { if (-not $_.Exception.Response) { throw }; $response=$_.Exception.Response; $reader=[IO.StreamReader]::new($response.GetResponseStream()); try { $content=$reader.ReadToEnd() } finally { $reader.Dispose() }; return [pscustomobject]@{StatusCode=[int]$response.StatusCode;Content=$content;Headers=$response.Headers} }
}
function Token($Response) { $m=[regex]::Match($Response.Content,'name="study_csrf_token"\s+value="([^"]+)"'); if (-not $m.Success) { throw 'Security QA missing CSRF.' }; return [Net.WebUtility]::HtmlDecode($m.Groups[1].Value) }
function Login($Name,$Password=$old) {
	$web=New-Object Microsoft.PowerShell.Commands.WebRequestSession
	$result=Page 'masuk' $web 'Post' @{identity=$Name;password=$Password;study_csrf_token=(Token (Page 'masuk' $web))}
	if ($result.Content -match 'name="identity"') { throw 'Security fixture login failed.' }
	return $web
}
function Change($Web,$Current,$Next,$Confirm=$null,$Extra=@{}) {
	if ($null -eq $Confirm) { $Confirm=$Next }
	$body=@{current_password=$Current;new_password=$Next;password_confirm=$Confirm;study_csrf_token=(Token (Page 'akun/keamanan' $Web))}
	foreach ($key in $Extra.Keys) { $body[$key]=$Extra[$key] }
	return Page 'akun/keamanan' $Web 'Post' $body
}
function Hash-State($Id) { return Db "SELECT SHA2(password_hash,256) FROM st_users WHERE id=$Id;" }
function Audit-Count($Id) { return Db "SELECT COUNT(*) FROM st_audit_events WHERE actor_user_id=$Id AND event_type='auth.password_changed';" }
try {
	foreach ($kind in @('adult','parent','child','admin','race')) {
		$name=$prefix+'_'+$kind
		Equal (Db "SELECT COUNT(*) FROM st_users WHERE username='$name';") '0' 'fixture collision'
		$accountKind=if ($kind -in @('admin','race')) {'adult'} else {$kind}
		$id=[int](Db "INSERT INTO st_users (username,password_hash,display_name,account_kind,status,created_at,updated_at) VALUES ('$name','$hash','Security QA','$accountKind','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()); SELECT LAST_INSERT_ID();")
		$ids[$kind]=$id; $names+=$name; $failureHashes+=Digest 'password-change' "$id"; $failureHashes+=Digest 'identity' $name
		if ($kind -eq 'admin') { Db "INSERT INTO st_user_roles (user_id,role_id,created_at) SELECT $id,id,UTC_TIMESTAMP() FROM st_roles WHERE role_code='system_admin';" | Out-Null }
	}
	$anon=New-Object Microsoft.PowerShell.Commands.WebRequestSession
	foreach ($path in @('akun/keamanan','account_security/index')) { if ((Page $path $anon).Content -notmatch 'name="identity"') { throw 'Anonymous security route exposed.' } }
	foreach ($kind in $ids.Keys) {
		$web=Login ($prefix+'_'+$kind)
		$page=Page 'akun/keamanan' $web
		Equal $page.StatusCode 200 'security page role'
		$stamp=Digest 'credential' ($ids[$kind].ToString()+'|'+$hash)
		if ($page.Content.Contains($hash) -or $page.Content.Contains($stamp)) { throw 'Credential hash/stamp leaked to HTML.' }
		$minimum=if ($kind -eq 'admin') {16} elseif ($kind -eq 'child') {10} else {12}
		if ($page.Content -notmatch "Minimal $minimum karakter" -or $page.Headers['Cache-Control'] -notmatch 'no-store' -or $page.Headers['X-Robots-Tag'] -notmatch 'noindex') { throw 'Security page policy/privacy missing.' }
		Equal (Page 'account_security/index' $web 'Delete').StatusCode 405 'unexpected method'
	}
	$parentA=Login ($prefix+'_parent'); $parentB=Login ($prefix+'_parent'); $parentC=Login ($prefix+'_parent')
	$parentId=$ids.parent; $before=Hash-State $parentId; $childBefore=Hash-State $ids.child
	$arrayResult=Page 'akun/keamanan' $parentA 'Post' @{'current_password[]'='x';new_password=$new;password_confirm=$new;study_csrf_token=(Token (Page 'akun/keamanan' $parentA))}
	Equal $arrayResult.StatusCode 400 'array field accepted'
	foreach ($case in @(@{Old=$old;New=('x'*73);Confirm=('x'*73)},@{Old=$old;New='short';Confirm='short'},@{Old=$old;New=$new;Confirm='not-the-same'},@{Old=$old;New=$old;Confirm=$old})) {
		$result=Change $parentA $case.Old $case.New $case.Confirm
		Equal $result.StatusCode 400 'invalid form accepted'
		if ($result.Content -notmatch 'aria-invalid="true"' -or $result.Content.Contains($old) -or $result.Content.Contains($new)) { throw 'Security form errors leak secret or lack links.' }
	}
	Equal (Hash-State $parentId) $before 'invalid form changed hash'
	Equal (Audit-Count $parentId) '0' 'invalid form audited as success'
	Equal (Change $parentA $old $new $new @{study_csrf_token='invalid'}).StatusCode 403 'CSRF accepted'
	$staleToken=Token (Page 'akun/keamanan' $parentC)
	$result=Change $parentA $old $new $new @{user_id=$ids.child}
	if ($result.Content -notmatch 'Kata sandi berhasil diganti' -or $result.Content -notmatch 'name="identity"') { throw 'Password change did not sign out current device.' }
	if ((Hash-State $parentId) -eq $before) { throw 'Hash did not change.' }
	Equal (Hash-State $ids.child) $childBefore 'injected target changed another user'
	Equal (Audit-Count $parentId) '1' 'password change audit not exactly once'
	$details=Db "SELECT details_json FROM st_audit_events WHERE actor_user_id=$parentId AND event_type='auth.password_changed';"
	if ($details -notmatch 'sessions_revoked' -or $details.Contains($old) -or $details.Contains($new) -or $details.Contains($hash)) { throw 'Unsafe password change audit.' }
	if ((Page 'beranda' $parentB).Content -notmatch 'name="identity"') { throw 'Second device retained authentication.' }
	$replay=Page 'account_security/index' $parentC 'Post' @{current_password=$old;new_password=$new;password_confirm=$new;study_csrf_token=$staleToken}
	if ($replay.Content -notmatch 'name="identity"') { throw 'Stale authenticated POST was not rejected.' }
	Equal (Audit-Count $parentId) '1' 'stale POST duplicated audit'
	$oldLogin=New-Object Microsoft.PowerShell.Commands.WebRequestSession
	$result=Page 'masuk' $oldLogin 'Post' @{identity=($prefix+'_parent');password=$old;study_csrf_token=(Token (Page 'masuk' $oldLogin))}
	if ($result.Content -notmatch 'name="identity"') { throw 'Old password still authenticates.' }
	$newWeb=Login ($prefix+'_parent') $new
	$child=Login ($prefix+'_child'); Equal (Change $child $old 'Child-new!').StatusCode 200 'child minimum change'
	$admin=Login ($prefix+'_admin'); Equal (Change $admin $old 'TwelveChars!').StatusCode 400 'admin shorter password accepted'
	Equal (Change $admin $old $new).StatusCode 200 'admin change'
	$adultA=Login ($prefix+'_adult'); $adultB=Login ($prefix+'_adult'); $adultId=$ids.adult
	$adultDigest=Digest 'password-change' "$adultId"
	for ($i=0;$i -lt 10;$i++) { $web=if ($i%2) {$adultA} else {$adultB}; Equal (Change $web 'Wrong-secret!' $new).StatusCode 400 'incorrect password behavior' }
	Equal (Db "SELECT COUNT(*) FROM st_login_failures WHERE identity_hash='$adultDigest';") '10' 'cross-device failed count'
	Equal (Change $adultA $old $new).StatusCode 429 'account throttle missing'
	Equal (Audit-Count $adultId) '0' 'throttle changed password'
	Db "UPDATE st_login_failures SET attempted_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 16 MINUTE) WHERE identity_hash='$adultDigest';" | Out-Null
	Equal (Change $adultB $old $new).StatusCode 200 'expired window did not recover'
	Equal (Audit-Count $adultId) '1' 'recovered change audit'
	Equal (Db "SELECT COUNT(*) FROM st_login_failures WHERE identity_hash='$adultDigest';") '10' 'successful change erased failure history'
	$revoked=Login ($prefix+'_parent') $new
	Db "UPDATE st_users SET status='suspended' WHERE id=$parentId;" | Out-Null
	if ((Page 'akun/keamanan' $revoked).Content -notmatch 'name="identity"') { throw 'Inactive account retained security page access.' }
	Equal (Audit-Count $parentId) '1' 'inactive account changed audit'
	Db "UPDATE st_users SET status='active' WHERE id=$parentId;" | Out-Null
	$previousRacePassword=$env:STUDY_QA_SECURITY_PASSWORD
	try {
		$env:STUDY_QA_SECURITY_PASSWORD=$old
		& node (Join-Path $PSScriptRoot 'account-security-resilience.mjs') --php $Php81Path --mysql $MysqlPath --username ($prefix+'_race') --user-id $ids.race
		if ($LASTEXITCODE -ne 0) { throw 'Security race/rollback verification failed.' }
	} finally { $env:STUDY_QA_SECURITY_PASSWORD=$previousRacePassword }
	if ($ChromePath) {
		$priorIdentity=$env:STUDY_QA_LOGIN_IDENTITY; $priorPassword=$env:STUDY_QA_LOGIN_PASSWORD
		try {
			foreach ($kind in @('parent','child')) {
				$env:STUDY_QA_LOGIN_IDENTITY=$prefix+'_'+$kind; $env:STUDY_QA_LOGIN_PASSWORD=if ($kind -eq 'child') {'Child-new!'} else {$new}
				$browserArgs=@((Join-Path $PSScriptRoot 'browser-viewport-check.mjs'),'--base-url',$root,'--chrome',$ChromePath,'--extra-path','akun/keamanan','--extra-name',('security-'+$kind),'--only-extra-route')
				if ($CaptureScreenshots) { $browserArgs+='--capture-screenshots' }
				& node @browserArgs
				if ($LASTEXITCODE -ne 0) { throw 'Security browser check failed.' }
			}
		} finally { $env:STUDY_QA_LOGIN_IDENTITY=$priorIdentity; $env:STUDY_QA_LOGIN_PASSWORD=$priorPassword }
	}
	Write-Output 'PASS own-password changes: roles/minima, validation/CSRF, no target ID, audit/privacy, old-password rejection, multiple-device revocation, stale replay and cross-device throttle/recovery.'
} finally {
	if ($names.Count) {
		$quoted=($names | ForEach-Object { "'$_'" }) -join ','
		$failureList=($failureHashes | ForEach-Object { "'$_'" }) -join ','
		Db "START TRANSACTION; DELETE FROM st_login_failures WHERE identity_hash IN ($failureList); DELETE a FROM st_audit_events a JOIN st_users u ON u.id=a.actor_user_id WHERE u.username IN ($quoted); DELETE ur FROM st_user_roles ur JOIN st_users u ON u.id=ur.user_id WHERE u.username IN ($quoted); DELETE FROM st_users WHERE username IN ($quoted); COMMIT;" | Out-Null
	}
	Write-Output 'PASS exact security fixtures cleaned.'
}
