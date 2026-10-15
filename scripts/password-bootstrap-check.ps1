param([Parameter(Mandatory=$true)][string]$PhpPath,[Parameter(Mandatory=$true)][string]$MysqlPath)
$ErrorActionPreference='Stop'
$keys=@('STUDY_ADMIN_USERNAME','STUDY_ADMIN_EMAIL','STUDY_ADMIN_NAME','STUDY_ADMIN_PASSWORD')
$previous=@{}
foreach ($key in $keys) { $previous[$key]=[Environment]::GetEnvironmentVariable($key,'Process') }
$name='qa_bootstrap_'+[Guid]::NewGuid().ToString('N').Substring(0,12)
try {
	$env:STUDY_ADMIN_USERNAME=$name
	$env:STUDY_ADMIN_EMAIL=$name+'@example.test'
	$env:STUDY_ADMIN_NAME='Invalid password QA'
	foreach ($value in @(('a'*73),(([string][char]0x00e9)*37),(([string][char]0x00e9)*15))) {
		$env:STUDY_ADMIN_PASSWORD=$value
		# Native stderr is expected for rejected input; capture without echoing submitted values.
		$oldPreference=$ErrorActionPreference; $ErrorActionPreference='Continue'
		try { $result=& $PhpPath (Join-Path $PSScriptRoot '..\index.php') cli/create_admin 2>&1; $code=$LASTEXITCODE }
		finally { $ErrorActionPreference=$oldPreference }
		$text=($result | ForEach-Object { "$_" }) -join "`n"
		if ($code -ne 2 -or $text -notmatch 'maximum 72 bytes' -or $text.Contains($value)) { throw 'Bootstrap did not reject an invalid password safely.' }
	}
	$count=& $MysqlPath -uroot -D db_study -N -B -e "SELECT COUNT(*) FROM st_users WHERE username='$name';"
	if ($LASTEXITCODE -ne 0 -or "$count".Trim() -ne '0') { throw 'Invalid bootstrap changed accounts.' }
	Write-Output 'PASS CLI bootstrap rejects byte overflow and fewer than 16 Unicode characters, without creating an administrator or echoing the input.'
} finally {
	foreach ($key in $keys) { [Environment]::SetEnvironmentVariable($key,$previous[$key],'Process') }
}
