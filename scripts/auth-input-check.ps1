param(
	[Parameter(Mandatory = $true)][string] $MysqlPath,
	[Parameter(Mandatory = $true)][string] $BaseUrl
)

$ErrorActionPreference = 'Stop'
$parsed=$null
if (-not [Uri]::TryCreate($BaseUrl,[UriKind]::Absolute,[ref]$parsed) -or $parsed.Scheme -ne 'http' -or -not $parsed.IsLoopback -or $parsed.UserInfo -or $parsed.Query -or $parsed.Fragment) { throw 'Authentication QA requires HTTP loopback without credentials/query/fragment.' }
$suffix = [Guid]::NewGuid().ToString('N').Substring(0, 12)
$username = "qa_auth_$suffix"

function Invoke-Db([string] $Sql) {
	$output = & $MysqlPath '-uroot' '-D' 'db_study' '-N' '-B' '-e' $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Authentication form QA database check failed: $output" }
	return ($output -join "`n").Trim()
}

function Get-CsrfToken($Response) {
	$match = [regex]::Match($Response.Content, 'name="study_csrf_token"\s+value="([^"]+)"')
	if (-not $match.Success) { throw 'CSRF hidden field was not found on the authentication form.' }
	return [System.Net.WebUtility]::HtmlDecode($match.Groups[1].Value)
}

function Assert-InvalidField($Html, [string] $fieldId, [string] $errorId = '') {
	if (-not $errorId) { $errorId = $fieldId + '-error' }
	$escapedId = [regex]::Escape($fieldId)
	$escapedErrorId = [regex]::Escape($errorId)
	$inputPattern = '<input\b(?=[^>]*\bid="' + $escapedId + '")(?=[^>]*\baria-invalid="true")(?=[^>]*\baria-describedby="' + $escapedErrorId + '")[^>]*>'
	$errorPattern = '<[^>]+\bid="' + $escapedErrorId + '"[^>]*>'
	if ($Html -notmatch $inputPattern -or $Html -notmatch $errorPattern) {
		throw "Field '$fieldId' is missing aria-invalid/aria-describedby linkage to its rendered error."
	}
}

function Assert-InvalidGroup($Html, [string] $groupId) {
	$escapedId = [regex]::Escape($groupId)
	$groupPattern = '<fieldset\b(?=[^>]*\baria-invalid="true")(?=[^>]*\baria-describedby="' + $escapedId + '-error")[^>]*>'
	$errorPattern = '<[^>]+\bid="' + $escapedId + '-error"[^>]*>'
	if ($Html -notmatch $groupPattern -or $Html -notmatch $errorPattern) {
		throw "Group '$groupId' is missing aria-invalid/aria-describedby linkage to its rendered error."
	}
}

$root = $BaseUrl.TrimEnd('/') + '/'
$signUpSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$signUpPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'daftar') -WebSession $signUpSession
$signUpResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'daftar') -Method Post -WebSession $signUpSession -Body @{
	username = $username
	display_name = ''
	email = 'not-an-email'
	password = 'short'
	password_confirm = 'different'
	study_csrf_token = Get-CsrfToken $signUpPage
}
Assert-InvalidGroup $signUpResult.Content 'account-kind'
foreach ($fieldId in @('email','password')) {
	Assert-InvalidField $signUpResult.Content $fieldId
}
Assert-InvalidField $signUpResult.Content 'display_name' 'display-name-error'
Assert-InvalidField $signUpResult.Content 'password_confirm' 'password-confirm-error'
Assert-InvalidField $signUpResult.Content 'agree_terms' 'agree-terms-error'
if ($signUpResult.Content -match 'The [A-Za-z].* field is|required field|is not a valid' -or
	$signUpResult.Content -notmatch 'Kolom Nama tampilan wajib diisi\.' -or
	$signUpResult.Content -notmatch 'Masukkan alamat email yang valid\.' -or
	$signUpResult.Content -notmatch 'minimal 12 karakter') {
	throw 'Authentication validation messages are not consistently written in Indonesian.'
}
if ($signUpResult.Content.Contains('short') -or $signUpResult.Content.Contains('different')) {
	throw 'Sign-up response reflected a submitted password value.'
}
if ((Invoke-Db "SELECT COUNT(*) FROM st_users WHERE username='$username';") -ne '0') {
	throw 'Invalid sign-up unexpectedly created a user account.'
}
Write-Output 'PASS sign-up errors use Indonesian copy linked to invalid controls; password values are not echoed and no account is created.'

$signInSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$signInPage = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -WebSession $signInSession
$signInResult = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'masuk') -Method Post -WebSession $signInSession -Body @{
	identity = ''
	password = ''
	study_csrf_token = Get-CsrfToken $signInPage
}
Assert-InvalidField $signInResult.Content 'identity'
Assert-InvalidField $signInResult.Content 'password'
Write-Output 'PASS sign-in field errors are programmatically associated with their controls.'
. (Join-Path $PSScriptRoot 'password-input-cases.ps1')
