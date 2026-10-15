param(
	[Parameter(Mandatory = $false)][string] $MysqlPath = 'C:\xampp\mysql\bin\mysql.exe',
	[Parameter(Mandatory = $false)][string] $PhpPath = 'C:\tools\php81-study\php.exe'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$databaseSetupScript = Join-Path $PSScriptRoot 'setup-local-database.ps1'

foreach ($requiredPath in @($MysqlPath, $PhpPath, $databaseSetupScript)) {
	if (-not (Test-Path -LiteralPath $requiredPath -PathType Leaf)) {
		throw "Required setup file or executable was not found: $requiredPath"
	}
}

$phpVersion = (& $PhpPath -r 'echo PHP_MAJOR_VERSION, chr(46), PHP_MINOR_VERSION;') -join ''
if ($LASTEXITCODE -ne 0 -or $phpVersion -ne '8.1') {
	throw "Local setup requires the selected PHP 8.1 runtime; detected '$phpVersion'."
}

$phpModules = @(& $PhpPath -m | ForEach-Object { $_.Trim().ToLowerInvariant() })
if ($LASTEXITCODE -ne 0) { throw 'Could not inspect the configured PHP modules.' }
foreach ($requiredModule in @('mysqli','mbstring','openssl')) {
	if ($phpModules -notcontains $requiredModule) {
		throw "PHP 8.1 is missing the required '$requiredModule' extension."
	}
}

Write-Output '== Prepare the local Study database and restricted accounts =='
& $databaseSetupScript -MysqlPath $MysqlPath
if ($LASTEXITCODE -ne 0) { throw 'Local database/account setup did not complete successfully.' }

$environmentNames = @('CI_ENV','DB_HOST','DB_USERNAME','DB_PASSWORD','DB_DATABASE')
$previousEnvironment = @{}
foreach ($environmentName in $environmentNames) {
	$previousEnvironment[$environmentName] = [Environment]::GetEnvironmentVariable($environmentName, 'Process')
}

Push-Location $projectRoot
try {
	$env:CI_ENV = 'development'
	$env:DB_HOST = '127.0.0.1'
	$env:DB_USERNAME = 'study_migrator'
	$env:DB_PASSWORD = ''
	$env:DB_DATABASE = 'db_study'
	Write-Output '== Apply pending Study migrations with the schema-scoped migrator account =='
	& $PhpPath index.php cli/migrate
	if ($LASTEXITCODE -ne 0) { throw 'Study migration command failed.' }
}
finally {
	Pop-Location
	foreach ($environmentName in $environmentNames) {
		[Environment]::SetEnvironmentVariable($environmentName, $previousEnvironment[$environmentName], 'Process')
	}
}

Write-Output 'PASS local Study setup completed for db_study; no administrator was created and no other schema was migrated.'
