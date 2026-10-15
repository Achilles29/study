param(
	[Parameter(Mandatory = $true)][string] $MysqlPath,
	[Parameter(Mandatory = $true)][string] $MysqlDumpPath
)

$ErrorActionPreference = 'Stop'
foreach ($path in @($MysqlPath, $MysqlDumpPath)) {
	if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Required database tool was not found: $path" }
}

$suffix = [Guid]::NewGuid().ToString('N').Substring(0, 12)
$schemaDatabase = "db_study_backup_qa_$suffix"
$restoreDatabase = "db_study_restore_qa_$suffix"
if ($schemaDatabase -notmatch '\Adb_study_backup_qa_[a-f0-9]{12}\z' -or
	$restoreDatabase -notmatch '\Adb_study_restore_qa_[a-f0-9]{12}\z') {
	throw 'Refusing to use an unexpected scratch database name.'
}

$tempRoot = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath()).TrimEnd('\')
$tempDirectory = Join-Path $tempRoot "study-backup-check-$suffix"
if (Test-Path -LiteralPath $tempDirectory) { throw 'The generated backup QA directory already exists.' }
$sourceDump = Join-Path $tempDirectory 'db-study-full-backup.sql'
$dataDump = Join-Path $tempDirectory 'synthetic-data.sql'
$createdSchemaDatabase = $false
$createdRestoreDatabase = $false
$createdTempDirectory = $false

function Invoke-MySql([string] $Sql, [string] $Database = '') {
	$arguments = @('-uroot', '-N', '-B')
	if ($Database) { $arguments += "-D$Database" }
	$arguments += @('-e', $Sql)
	$output = & $MysqlPath @arguments 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Database backup QA query failed: $output" }
	return ($output -join "`n").Trim()
}

function Import-SqlDump([string] $Database, [string] $DumpPath) {
	$normalizedPath = $DumpPath.Replace('\', '/')
	$escapedPath = $normalizedPath.Replace('"', '\"')
	$sourceCommand = 'SOURCE "' + $escapedPath + '";'
	$output = & $MysqlPath '-uroot' "-D$Database" '-e' $sourceCommand 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Could not restore SQL dump into ${Database}: $output" }
}

function Get-TableRowCounts([string] $Database) {
	$tableNames = Invoke-MySql "SELECT table_name FROM information_schema.tables WHERE table_schema='$Database' AND table_type='BASE TABLE' ORDER BY table_name;"
	$counts = [ordered]@{}
	foreach ($table in ($tableNames -split "`r?`n" | Where-Object { $_ })) {
		$counts[$table] = Invoke-MySql "SELECT COUNT(*) FROM ``$Database``.``$table``;"
	}
	return ,$counts
}

try {
	if ((Invoke-MySql "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name IN ('$schemaDatabase','$restoreDatabase');") -ne '0') {
		throw 'One of the generated backup QA databases unexpectedly already exists.'
	}
	if (([int](Invoke-MySql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='db_study' AND table_type='BASE TABLE';")) -lt 1) {
		throw 'Source database db_study has no base tables to back up.'
	}
	$sourceRowCounts = Get-TableRowCounts 'db_study'
	$unexpectedDataTables = @($sourceRowCounts.Keys | Where-Object {
		[int]$sourceRowCounts[$_] -gt 0 -and $_ -notin @('study_migrations','st_roles')
	})
	if ($unexpectedDataTables.Count -gt 0) {
		throw "Refusing to write a temporary dump containing non-fixture data. Non-empty application tables: $($unexpectedDataTables -join ', ')."
	}

	New-Item -ItemType Directory -Path $tempDirectory | Out-Null
	$createdTempDirectory = $true
	Invoke-MySql "CREATE DATABASE ``$schemaDatabase`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null
	$createdSchemaDatabase = $true
	Invoke-MySql "CREATE DATABASE ``$restoreDatabase`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null
	$createdRestoreDatabase = $true

	$dumpOutput = & $MysqlDumpPath '-uroot' '--single-transaction' '--routines' '--triggers' '--events' "--result-file=$sourceDump" 'db_study' 2>&1
	if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $sourceDump -PathType Leaf)) {
		throw "Full backup of empty db_study failed: $dumpOutput"
	}
	Import-SqlDump $schemaDatabase $sourceDump
	$sourceSchema = Invoke-MySql "SELECT CONCAT((SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='db_study' AND table_type='BASE TABLE'),'|',(SELECT MAX(version) FROM db_study.study_migrations));"
	$restoredSchema = Invoke-MySql "SELECT CONCAT((SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$schemaDatabase' AND table_type='BASE TABLE'),'|',(SELECT MAX(version) FROM ``$schemaDatabase``.study_migrations));"
	if ($sourceSchema -ne $restoredSchema) { throw "Schema restore does not match db_study (source $sourceSchema; restored $restoredSchema)." }
	$restoredSourceCounts = Get-TableRowCounts $schemaDatabase
	if ((ConvertTo-Json -InputObject $sourceRowCounts -Compress) -ne (ConvertTo-Json -InputObject $restoredSourceCounts -Compress)) {
		throw 'Full backup restore did not preserve the per-table row counts from db_study.'
	}
	Write-Output "PASS full backup of empty db_study restored: $restoredSchema base tables; per-table row counts match exactly."

	$fixtureUsername = 'qa_backup_' + $suffix
	$fixtureSql = "INSERT INTO ``$schemaDatabase``.st_users (username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES ('$fixtureUsername','not-a-real-login-hash','Backup QA only','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());"
	Invoke-MySql $fixtureSql | Out-Null
	$dumpOutput = & $MysqlDumpPath '-uroot' '--single-transaction' '--routines' '--triggers' '--events' "--result-file=$dataDump" $schemaDatabase 2>&1
	if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $dataDump -PathType Leaf)) {
		throw "Synthetic-data backup of $schemaDatabase failed: $dumpOutput"
	}
	Import-SqlDump $restoreDatabase $dataDump
	$restoredFixtureCount = Invoke-MySql "SELECT COUNT(*) FROM ``$restoreDatabase``.st_users WHERE username='$fixtureUsername';"
	if ($restoredFixtureCount -ne '1') { throw 'The synthetic user was not restored exactly once.' }
	$restoredRows = Invoke-MySql "SELECT COUNT(*) FROM ``$restoreDatabase``.st_users;"
	if ($restoredRows -ne '1') { throw "The synthetic database should contain exactly one user after restore; found $restoredRows." }
	$expectedSyntheticCounts = [ordered]@{}
	foreach ($table in $sourceRowCounts.Keys) { $expectedSyntheticCounts[$table] = $sourceRowCounts[$table] }
	$expectedSyntheticCounts['st_users'] = '1'
	$restoredSyntheticCounts = Get-TableRowCounts $restoreDatabase
	if ((ConvertTo-Json -InputObject $expectedSyntheticCounts -Compress) -ne (ConvertTo-Json -InputObject $restoredSyntheticCounts -Compress)) {
		throw 'Synthetic-data backup restore did not preserve the exact expected per-table row counts.'
	}
	Write-Output 'PASS full synthetic-data backup/restore preserves exactly one test fixture and all reference rows.'
}
finally {
	if ($createdRestoreDatabase -and $restoreDatabase -match '\Adb_study_restore_qa_[a-f0-9]{12}\z') {
		Invoke-MySql "DROP DATABASE ``$restoreDatabase``;" | Out-Null
		Write-Output "PASS removed only temporary database $restoreDatabase."
	}
	if ($createdSchemaDatabase -and $schemaDatabase -match '\Adb_study_backup_qa_[a-f0-9]{12}\z') {
		Invoke-MySql "DROP DATABASE ``$schemaDatabase``;" | Out-Null
		Write-Output "PASS removed only temporary database $schemaDatabase."
	}
	if ($createdTempDirectory -and $tempDirectory.StartsWith($tempRoot + '\', [System.StringComparison]::OrdinalIgnoreCase) -and
		[System.IO.Path]::GetFileName($tempDirectory) -eq "study-backup-check-$suffix") {
		foreach ($file in @($sourceDump, $dataDump)) {
			if (Test-Path -LiteralPath $file -PathType Leaf) { Remove-Item -LiteralPath $file -Force }
		}
		Remove-Item -LiteralPath $tempDirectory -Force
		Write-Output 'PASS removed the temporary SQL dump files and their uniquely generated directory.'
	}
}
