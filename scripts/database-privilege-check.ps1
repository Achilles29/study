param(
	[Parameter(Mandatory = $true)][string] $MysqlPath,
	[Parameter(Mandatory = $false)][ValidateRange(0, 10000)][int] $MinimumTableCount = 30
)

$ErrorActionPreference = 'Stop'
if (-not (Test-Path -LiteralPath $MysqlPath -PathType Leaf)) {
	throw "MySQL client not found: $MysqlPath"
}

function Invoke-RootMySql([string] $Sql) {
	$output = & $MysqlPath -uroot -N -B -e $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Could not inspect local database grants: $output" }
	return ($output -join "`n").Trim()
}

$runtimeGrants = Invoke-RootMySql "SHOW GRANTS FOR 'study_app'@'127.0.0.1';"
$runtimeGrantText = $runtimeGrants.Replace('`', '').ToLowerInvariant()

function Test-ExactSchemaPrivileges([string] $GrantText, [string] $Account, [string[]] $ExpectedPrivileges) {
	$grantLines = @($GrantText -split "`n" | Where-Object { $_ -match "^grant .+ on db_study\.\* to $([regex]::Escape($Account))$" })
	if ($grantLines.Count -ne 1) { return $false }
	$privilegeList = ($grantLines[0] -replace '^grant ', '' -replace ' on db_study\.\* to .+$', '').Split(',') | ForEach-Object { $_.Trim() }
	$actual = @($privilegeList | Sort-Object)
	$expected = @($ExpectedPrivileges | Sort-Object)
	return -not (Compare-Object -ReferenceObject $expected -DifferenceObject $actual)
}

if (-not (Test-ExactSchemaPrivileges $runtimeGrantText 'study_app@127.0.0.1' @('select','insert','update','delete')) -or
	$runtimeGrantText -match 'grant (?!usage on \*\.\*).* on \*\.\*') {
	throw "The Study runtime account does not have the expected schema-scoped data privileges: $runtimeGrants"
}

$migratorGrants = Invoke-RootMySql "SHOW GRANTS FOR 'study_migrator'@'127.0.0.1';"
$migratorGrantText = $migratorGrants.Replace('`', '').ToLowerInvariant()

if (-not (Test-ExactSchemaPrivileges $migratorGrantText 'study_migrator@127.0.0.1' @('select','insert','update','delete','create','alter','drop','index','references')) -or
	$migratorGrantText -match 'grant (?!usage on \*\.\*).* on \*\.\*' -or
	$migratorGrantText -match 'grant option') {
	throw "The migration account must be scoped to db_study and must not have global or grant-option privileges: $migratorGrants"
}

$runtimeQuery = & $MysqlPath '--host=127.0.0.1' '--user=study_app' -N -B -e "USE db_study; SELECT CURRENT_USER(), COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE();" 2>&1
$runtimeQueryText = ($runtimeQuery -join "`n").Trim()
if ($LASTEXITCODE -ne 0 -or $runtimeQueryText -notmatch 'study_app@127\.0\.0\.1\s+(\d+)' -or [int] $Matches[1] -lt $MinimumTableCount) {
	throw "The runtime account cannot read the expected Study schema: $runtimeQuery"
}

$databaseProbe = 'db_study_runtime_privilege_probe'
$existingDatabaseProbe = Invoke-RootMySql "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = '$databaseProbe';"
if ($existingDatabaseProbe -ne '0') { throw "Refusing to test a pre-existing database named $databaseProbe." }

function Test-RuntimeCreateDenied([string] $Sql) {
	$commandOutput = $null
	$commandExitCode = 0
	try {
		$commandOutput = & $MysqlPath '--host=127.0.0.1' '--user=study_app' -e $Sql 2>&1
		$commandExitCode = $LASTEXITCODE
	}
	catch {
		$commandOutput = $_.Exception.Message
		$commandExitCode = $LASTEXITCODE
	}
	return [pscustomobject]@{ ExitCode = $commandExitCode; Output = $commandOutput }
}

$databaseCreate = Test-RuntimeCreateDenied "CREATE DATABASE $databaseProbe;"
if ($databaseCreate.ExitCode -eq 0) {
	Invoke-RootMySql "DROP DATABASE $databaseProbe;" | Out-Null
	throw 'The Study runtime account unexpectedly created a database; the test-created database was removed.'
}

$tableProbe = 'study_runtime_privilege_probe_20261015'
$existingTableProbe = Invoke-RootMySql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'db_study' AND table_name = '$tableProbe';"
if ($existingTableProbe -ne '0') { throw "Refusing to test a pre-existing table named $tableProbe." }
$tableCreate = Test-RuntimeCreateDenied "CREATE TABLE db_study.$tableProbe (id INT NOT NULL);"
if ($tableCreate.ExitCode -eq 0) {
	Invoke-RootMySql "DROP TABLE db_study.$tableProbe;" | Out-Null
	throw 'The Study runtime account unexpectedly created a table; the test-created table was removed.'
}

$migratorProbe = 'study_migrator_privilege_probe_20261015'
$existingMigratorProbe = Invoke-RootMySql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'db_study' AND table_name = '$migratorProbe';"
if ($existingMigratorProbe -ne '0') { throw "Refusing to test a pre-existing table named $migratorProbe." }
$migratorProbeCreated = $false
try {
	$createOutput = & $MysqlPath '--host=127.0.0.1' '--user=study_migrator' -e "CREATE TABLE db_study.$migratorProbe (id INT NOT NULL);" 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Migration account could not create a table in db_study: $createOutput" }
	$migratorProbeCreated = $true
	$alterOutput = & $MysqlPath '--host=127.0.0.1' '--user=study_migrator' -e "ALTER TABLE db_study.$migratorProbe ADD COLUMN label VARCHAR(20) NULL;" 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Migration account could not alter a table in db_study: $alterOutput" }
	$dropOutput = & $MysqlPath '--host=127.0.0.1' '--user=study_migrator' -e "DROP TABLE db_study.$migratorProbe;" 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Migration account could not drop its QA table in db_study: $dropOutput" }
	$migratorProbeCreated = $false
}
finally {
	if ($migratorProbeCreated) {
		Invoke-RootMySql "DROP TABLE IF EXISTS db_study.$migratorProbe;" | Out-Null
	}
}

Write-Output 'PASS Study runtime account can read its schema but cannot create databases or tables.'
Write-Output 'PASS Study migration account has schema-scoped privileges, can apply DDL, and has no global/grant-option access.'
