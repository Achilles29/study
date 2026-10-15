# Dot-sourced by auth-input-check.ps1; all accounts are synthetic and exact-scoped.
$passwordNames=@(($username+'_pw'),($username+'_child'))
$passwordQuoted=($passwordNames | ForEach-Object { "'$_'" }) -join ','
$asciiBoundary='p'*72
$unicodeBoundary=([string][char]0x00e9)*36
function Password-Post($Path,$Web,$Body) {
	$page=Invoke-WebRequest -UseBasicParsing -Uri ($root+$Path) -WebSession $Web
	$Body['study_csrf_token']=Get-CsrfToken $page
	return Invoke-WebRequest -UseBasicParsing -Uri ($root+$Path) -WebSession $Web -Method Post -ContentType 'application/x-www-form-urlencoded; charset=UTF-8' -Body $Body
}
if ($passwordNames.Count -ne 2 -or (Invoke-Db "SELECT COUNT(*) FROM st_users WHERE username IN ($passwordQuoted);") -ne '0') { throw 'Password fixture collision or invalid fixture names.' }
try {
	$web=New-Object Microsoft.PowerShell.Commands.WebRequestSession
	foreach ($invalid in @(($asciiBoundary+'x'),($unicodeBoundary+[char]0x00e9))) {
		$result=Password-Post 'daftar' $web @{account_kind='parent';username=$passwordNames[0];email=($passwordNames[0]+'@example.test');display_name='Password QA';password=$invalid;password_confirm=$invalid;agree_terms='1'}
		Assert-InvalidField $result.Content 'password'
		if ($result.Content -notmatch 'maksimal 72 byte' -or $result.Content.Contains($invalid)) { throw 'Password limit message missing or value leaked.' }
		if ((Invoke-Db "SELECT COUNT(*) FROM st_users WHERE username IN ($passwordQuoted);") -ne '0') { throw 'Overlength sign-up created an account.' }
	}
	Password-Post 'daftar' $web @{account_kind='parent';username=$passwordNames[0];email=($passwordNames[0]+'@example.test');display_name='Password QA';password=$asciiBoundary;password_confirm=$asciiBoundary;agree_terms='1'} | Out-Null
	if ((Invoke-Db "SELECT COUNT(*) FROM st_users WHERE username='$($passwordNames[0])';") -ne '1') { throw '72-byte ASCII sign-up failed.' }
	$result=Password-Post 'masuk' $web @{identity=$passwordNames[0];password=($asciiBoundary+'x')}
	Assert-InvalidField $result.Content 'password'
	if ($result.Content -notmatch 'name="identity"') { throw 'Ignored ASCII suffix authenticated.' }
	$result=Password-Post 'masuk' $web @{identity=$passwordNames[0];password=$asciiBoundary}
	if ($result.Content -match 'name="identity"') { throw '72-byte ASCII login failed.' }
	foreach ($invalid in @(($asciiBoundary+'x'),($unicodeBoundary+[char]0x00e9))) {
		$result=Password-Post 'keluarga/anak/tambah' $web @{username=$passwordNames[1];display_name='Child QA';learning_stage='primary';password=$invalid;password_confirm=$invalid}
		Assert-InvalidField $result.Content 'password' 'field-error-password'
		if ((Invoke-Db "SELECT COUNT(*) FROM st_users WHERE username='$($passwordNames[1])';") -ne '0') { throw 'Overlength child password created an account.' }
	}
	Password-Post 'keluarga/anak/tambah' $web @{username=$passwordNames[1];display_name='Child QA';learning_stage='primary';password=$unicodeBoundary;password_confirm=$unicodeBoundary} | Out-Null
	if ((Invoke-Db "SELECT COUNT(*) FROM st_users WHERE username='$($passwordNames[1])';") -ne '1') { throw '72-byte UTF-8 child password rejected.' }
	$childWeb=New-Object Microsoft.PowerShell.Commands.WebRequestSession
	$result=Password-Post 'masuk' $childWeb @{identity=$passwordNames[1];password=($unicodeBoundary+[char]0x00e9)}
	Assert-InvalidField $result.Content 'password'
	$result=Password-Post 'masuk' $childWeb @{identity=$passwordNames[1];password=$unicodeBoundary}
	if ($result.Content -match 'name="identity"') { throw '72-byte UTF-8 child login failed.' }
	Write-Output 'PASS password HTTP: 72-byte ASCII/UTF-8 accepted; longer sign-up, child creation and login rejected without ignored suffix or echoed password.'
} finally {
	Invoke-Db "START TRANSACTION; DELETE a FROM st_audit_events a JOIN st_users u ON u.id=a.actor_user_id WHERE u.username IN ($passwordQuoted); DELETE l FROM st_family_links l JOIN st_users u ON u.id=l.parent_user_id WHERE u.username IN ($passwordQuoted); DELETE w FROM st_wallets w JOIN st_users u ON u.id=w.owner_user_id WHERE u.username IN ($passwordQuoted); DELETE FROM st_users WHERE username IN ($passwordQuoted); COMMIT;" | Out-Null
	Write-Output 'PASS exact password QA accounts, links, wallets and audit cleaned.'
}
