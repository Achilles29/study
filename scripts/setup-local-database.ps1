param(
	[Parameter(Mandatory = $false)][string] $MysqlPath = 'C:\xampp\mysql\bin\mysql.exe'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$localConfigPath = Join-Path $projectRoot 'application/config/database.local.php'
$exampleConfigPath = Join-Path $projectRoot 'application/config/database.local.example.php'
$databaseName = 'db_study'

if (-not (Test-Path -LiteralPath $MysqlPath -PathType Leaf)) {
	throw "MySQL client not found: $MysqlPath"
}

function Invoke-RootMySql([string] $Sql) {
	$output = & $MysqlPath -uroot -N -B -e $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Local database setup failed: $output" }
	return ($output -join "`n").Trim()
}

Invoke-RootMySql "CREATE DATABASE IF NOT EXISTS db_study CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; ALTER DATABASE db_study CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null
Invoke-RootMySql "CREATE USER IF NOT EXISTS 'study_app'@'127.0.0.1' IDENTIFIED BY ''; CREATE USER IF NOT EXISTS 'study_migrator'@'127.0.0.1' IDENTIFIED BY '';" | Out-Null

foreach ($user in @('study_app', 'study_migrator')) {
	$grants = Invoke-RootMySql "SHOW GRANTS FOR '$user'@'127.0.0.1';"
	$grantText = $grants.Replace('`', '').ToLowerInvariant()
	if ($grantText -match 'grant (?!usage on \*\.\*).* on \*\.\*') {
		throw "Refusing to alter the existing global privileges for $user. Review SHOW GRANTS manually first."
	}
	if ($grantText -match 'with grant option') {
		throw "Refusing to alter the existing grant option for $user. Review SHOW GRANTS manually first."
	}
	if ($grantText -match ' on db_study\.\*') {
		Invoke-RootMySql "REVOKE ALL PRIVILEGES ON db_study.* FROM '$user'@'127.0.0.1';" | Out-Null
	}
}

Invoke-RootMySql "GRANT SELECT, INSERT, UPDATE, DELETE ON db_study.* TO 'study_app'@'127.0.0.1'; GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON db_study.* TO 'study_migrator'@'127.0.0.1'; FLUSH PRIVILEGES;" | Out-Null

if (-not (Test-Path -LiteralPath $localConfigPath -PathType Leaf)) {
	if (-not (Test-Path -LiteralPath $exampleConfigPath -PathType Leaf)) {
		throw "Database config example is missing: $exampleConfigPath"
	}
	Copy-Item -LiteralPath $exampleConfigPath -Destination $localConfigPath
	Write-Output 'Created ignored local DB config from the safe example.'
}
else {
	Write-Output 'Kept the existing ignored local DB config unchanged.'
}

& (Join-Path $PSScriptRoot 'database-privilege-check.ps1') -MysqlPath $MysqlPath -MinimumTableCount 0
Write-Output "PASS local schema $databaseName is ready; run migrations separately with the documented migration command."
