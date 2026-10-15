param(
	[Parameter(Mandatory=$true)][string]$Php81Path,
	[Parameter(Mandatory=$true)][string]$MysqlPath
)
$ErrorActionPreference='Stop'
$projectRoot=Split-Path -Parent $PSScriptRoot
$environmentNames=@('CI_ENV','DB_HOST','DB_USERNAME','DB_PASSWORD','DB_DATABASE')
$previous=@{}
foreach($name in $environmentNames){$previous[$name]=[Environment]::GetEnvironmentVariable($name,'Process')}
function Invoke-ScratchSql([string]$Sql){
	$output=& $MysqlPath '--no-defaults' '--host=127.0.0.1' '--protocol=TCP' '-uroot' '-N' '-B' '-e' $Sql 2>&1
	if($LASTEXITCODE -ne 0){throw 'Legacy migration scratch SQL check failed; inspect locally without exposing credentials.'}
	return ($output -join "`n").Trim()
}

# Each case owns a separate newly created scratch database. Never point the CLI
# at db_study or reuse a schema after a failed migration.
$tableCounts = @{ 0 = 1; 1 = 21; 2 = 22; 3 = 27; 4 = 27; 5 = 27; 6 = 27; 7 = 30; 8 = 30; 12 = 32; 13 = 33 }
foreach ($targetVersion in @(1, 2, 3, 4, 5, 6, 7, 8, 13)) {
	$modes = if ($targetVersion -eq 3) { @('nonempty') } else { @('nonempty', 'empty') }
	foreach ($caseMode in $modes) {
		$databaseName = 'db_study_legacy_migration_qa_' + [Guid]::NewGuid().ToString('N').Substring(0, 12)
		if ($databaseName -notmatch '\Adb_study_legacy_migration_qa_[a-f0-9]{12}\z') { throw 'Refusing unexpected one-step scratch database name.' }
		$createdDatabase = $false
		try {
			Invoke-ScratchSql "CREATE DATABASE ``$databaseName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null
			$createdDatabase = $true
			$env:CI_ENV = 'development'; $env:DB_HOST = '127.0.0.1'; $env:DB_USERNAME = 'root'; $env:DB_PASSWORD = ''; $env:DB_DATABASE = $databaseName
			Push-Location $projectRoot
			try {
				& $Php81Path index.php cli/check_legacy_migration_guards early $targetVersion $caseMode
				if ($LASTEXITCODE -ne 0) { throw 'One-step migration safety check failed; no automatic retry.' }
			} finally { Pop-Location }
			$expectedVersion = if ($caseMode -eq 'empty') { $targetVersion - 1 } else { $targetVersion }
			$expectedState = [string] $expectedVersion + '|' + [string] $tableCounts[$expectedVersion]
			$state = Invoke-ScratchSql "SELECT CONCAT((SELECT MAX(version) FROM ``$databaseName``.study_migrations),'|',(SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$databaseName' AND table_type='BASE TABLE'));"
			if ($state -cne $expectedState) { throw 'Independent scratch version/table inventory check failed.' }
			Write-Output "PASS independent one-step $targetVersion $caseMode final version/table inventory."
		} finally {
			foreach ($name in $environmentNames) { [Environment]::SetEnvironmentVariable($name, $previous[$name], 'Process') }
			if ($createdDatabase) {
				if ($databaseName -notmatch '\Adb_study_legacy_migration_qa_[a-f0-9]{12}\z') { throw 'Refusing unexpected scratch cleanup target.' }
				Invoke-ScratchSql "DROP DATABASE ``$databaseName``;" | Out-Null
				if ((Invoke-ScratchSql "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$databaseName';") -ne '0') { throw 'One-step scratch cleanup failed.' }
				Write-Output "PASS exact one-step scratch schema cleanup: $databaseName."
			}
		}
	}
}

$databaseName='db_study_legacy_migration_qa_'+[Guid]::NewGuid().ToString('N').Substring(0,12)
if($databaseName -notmatch '\Adb_study_legacy_migration_qa_[a-f0-9]{12}\z'){throw 'Unexpected scratch database name; refusing legacy migration QA.'}
$createdDatabase=$false
try {
	Invoke-ScratchSql "CREATE DATABASE ``$databaseName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null
	$createdDatabase=$true
	$env:CI_ENV='development'; $env:DB_HOST='127.0.0.1'; $env:DB_USERNAME='root'; $env:DB_PASSWORD=''; $env:DB_DATABASE=$databaseName
	Push-Location $projectRoot
	try {
		& $Php81Path index.php cli/check_legacy_migration_guards
		if($LASTEXITCODE -ne 0){throw 'Legacy migration guard CLI check failed.'}
	} finally {Pop-Location}
	$state=Invoke-ScratchSql "SELECT CONCAT((SELECT MAX(version) FROM ``$databaseName``.study_migrations),'|',(SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$databaseName' AND table_type='BASE TABLE'),'|',(SELECT COUNT(*) FROM ``$databaseName``.st_matching_runs),'|',(SELECT COUNT(*) FROM ``$databaseName``.st_users));"
	if($state -ne '12|32|0|0'){throw 'Final scratch schema is not preserved version 12 with cleaned synthetic data.'}
	Write-Output 'PASS final scratch version, 32 tables and zero synthetic users/runs independently verified.'
} finally {
	foreach($name in $environmentNames){[Environment]::SetEnvironmentVariable($name,$previous[$name],'Process')}
	if($createdDatabase){
		if($databaseName -notmatch '\Adb_study_legacy_migration_qa_[a-f0-9]{12}\z'){throw 'Refusing cleanup outside the exact legacy scratch schema pattern.'}
		Invoke-ScratchSql "DROP DATABASE ``$databaseName``;" | Out-Null
		if((Invoke-ScratchSql "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$databaseName';") -ne '0'){throw 'Exact temporary database cleanup failed.'}
		Write-Output "PASS removed only temporary database $databaseName; process environment restored."
	}
}
