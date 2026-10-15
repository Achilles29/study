param(
	[Parameter(Mandatory = $true)][string] $MysqlPath,
	[Parameter(Mandatory = $true)][string] $BaseUrl,
	[Parameter(Mandatory = $true)][string] $AppEncryptionKey
)

$ErrorActionPreference = 'Stop'
$parsedBaseUrl = $null
if ( ! [Uri]::TryCreate($BaseUrl, [UriKind]::Absolute, [ref] $parsedBaseUrl) -or
	$parsedBaseUrl.Scheme -ne 'http' -or ! $parsedBaseUrl.IsLoopback -or
	$parsedBaseUrl.UserInfo -ne '' -or $parsedBaseUrl.Query -ne '' -or $parsedBaseUrl.Fragment -ne '') {
	throw 'Authentication throttle QA only accepts an HTTP loopback URL without credentials, query, or fragment.'
}
if ([string]::IsNullOrWhiteSpace($AppEncryptionKey)) { throw 'The application encryption key used by the web process is required for exact cleanup.' }

function Invoke-Db([string] $Sql) {
	$output = & $MysqlPath '-uroot' '-D' 'db_study' '-N' '-B' '-e' $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Authentication throttle QA database check failed: $output" }
	return ($output -join "`n").Trim()
}

function Get-Digest([string] $Kind, [string] $Value) {
	$hmac = [System.Security.Cryptography.HMACSHA256]::new([System.Text.Encoding]::UTF8.GetBytes($AppEncryptionKey))
	try {
		$bytes = $hmac.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($Kind + '|' + $Value))
		return ([System.BitConverter]::ToString($bytes)).Replace('-', '').ToLowerInvariant()
	}
	finally { $hmac.Dispose() }
}

function Get-CsrfToken($Response) {
	$match = [regex]::Match($Response.Content, 'name="study_csrf_token"\s+value="([^"]+)"')
	if (-not $match.Success) { throw 'CSRF hidden field was not found on the authentication form.' }
	return [System.Net.WebUtility]::HtmlDecode($match.Groups[1].Value)
}

function Invoke-FailedLogin([string] $Identity, $WebSession, [string] $Token) {
	return Invoke-WebRequest -UseBasicParsing -Uri ($script:root + 'masuk') -Method Post -WebSession $WebSession -Body @{
		identity = $Identity
		password = 'invalid-qa-password-never-valid'
		study_csrf_token = $Token
	}
}

$root = $BaseUrl.TrimEnd('/') + '/'
$script:root = $root
$identityTest = 'qa-throttle_identity_' + [Guid]::NewGuid().ToString('N')
$identityHash = Get-Digest 'identity' $identityTest
$addressIdentities = @()
for ($index = 0; $index -lt 51; $index++) {
	$addressIdentities += 'qa-throttle_address_' + [Guid]::NewGuid().ToString('N')
}
$addressHashes = @($addressIdentities | ForEach-Object { Get-Digest 'identity' $_ })
$allHashes = @($identityHash) + $addressHashes
$hashList = "'" + ($allHashes -join "','") + "'"
$web = New-Object Microsoft.PowerShell.Commands.WebRequestSession

try {
	$page = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -WebSession $web
	$token = Get-CsrfToken $page
	for ($attempt = 1; $attempt -le 10; $attempt++) {
		$result = Invoke-FailedLogin $identityTest $web $token
		if ($result.Content -notmatch 'Nama pengguna/email atau kata sandi belum benar\.' -or
			$result.Content -match 'Terlalu banyak percobaan masuk') {
			throw "Identity throttle changed behavior before the expected threshold (attempt $attempt)."
		}
		$token = Get-CsrfToken $result
	}
	$blocked = Invoke-FailedLogin $identityTest $web $token
	if ($blocked.Content -notmatch 'Terlalu banyak percobaan masuk') {
		throw 'The 11th failed login for one identity was not throttled.'
	}
	$storedIdentityFailures = Invoke-Db "SELECT COUNT(*) FROM st_login_failures WHERE identity_hash='$identityHash';"
	if ($storedIdentityFailures -ne '10') { throw "Expected exactly ten stored failed attempts for the synthetic identity; got $storedIdentityFailures." }
	Write-Output 'PASS one identity receives the generic error for ten failures and is throttled on the next attempt.'

	Invoke-Db "UPDATE st_login_failures SET attempted_at=UTC_TIMESTAMP()-INTERVAL 16 MINUTE WHERE identity_hash='$identityHash';" | Out-Null
	$token = Get-CsrfToken $blocked
	$expiredIdentityAttempt = Invoke-FailedLogin $identityTest $web $token
	if ($expiredIdentityAttempt.Content -notmatch 'Nama pengguna/email atau kata sandi belum benar\.' -or
		$expiredIdentityAttempt.Content -match 'Terlalu banyak percobaan masuk') {
		throw 'Expired identity failures continued to trigger the throttle.'
	}
	Write-Output 'PASS identity throttle window ignores attempts older than fifteen minutes.'

	Invoke-Db "DELETE FROM st_login_failures WHERE identity_hash='$identityHash';" | Out-Null
	$page = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -WebSession $web
	$token = Get-CsrfToken $page
	for ($attempt = 0; $attempt -lt 50; $attempt++) {
		$result = Invoke-FailedLogin $addressIdentities[$attempt] $web $token
		if ($result.Content -notmatch 'Nama pengguna/email atau kata sandi belum benar\.' -or
			$result.Content -match 'Terlalu banyak percobaan masuk') {
			throw "Address throttle changed behavior before fifty failures (attempt $($attempt + 1))."
		}
		$token = Get-CsrfToken $result
	}
	$blocked = Invoke-FailedLogin $addressIdentities[50] $web $token
	if ($blocked.Content -notmatch 'Terlalu banyak percobaan masuk') {
		throw 'The next failed login after fifty attempts from one address was not throttled.'
	}
	$storedAddressFailures = Invoke-Db "SELECT COUNT(*) FROM st_login_failures WHERE identity_hash IN ($hashList);"
	if ($storedAddressFailures -ne '50') { throw "Expected exactly fifty stored failed attempts for unique synthetic identities; got $storedAddressFailures." }
	Write-Output 'PASS an address receives the generic error for fifty unique identities and is throttled on the next attempt.'

	Invoke-Db "UPDATE st_login_failures SET attempted_at=UTC_TIMESTAMP()-INTERVAL 16 MINUTE WHERE identity_hash IN ($hashList);" | Out-Null
	$token = Get-CsrfToken $blocked
	$expiredAddressAttempt = Invoke-FailedLogin $addressIdentities[50] $web $token
	if ($expiredAddressAttempt.Content -notmatch 'Nama pengguna/email atau kata sandi belum benar\.' -or
		$expiredAddressAttempt.Content -match 'Terlalu banyak percobaan masuk') {
		throw 'Expired address failures continued to trigger the throttle.'
	}
	Write-Output 'PASS address throttle window ignores attempts older than fifteen minutes.'
}
finally {
	Invoke-Db "DELETE FROM st_login_failures WHERE identity_hash IN ($hashList);" | Out-Null
}

$remaining = Invoke-Db "SELECT COUNT(*) FROM st_login_failures WHERE identity_hash IN ($hashList);"
if ($remaining -ne '0') { throw 'Synthetic authentication throttle rows were not fully cleaned.' }
Write-Output 'PASS all synthetic login-throttle records were removed by exact HMAC identity.'
