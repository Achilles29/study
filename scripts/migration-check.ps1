param(
	[Parameter(Mandatory = $true)][string] $Php81Path,
	[Parameter(Mandatory = $true)][string] $MysqlPath
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$databaseName = 'db_study_migration_qa_' + [Guid]::NewGuid().ToString('N').Substring(0, 12)
if ($databaseName -notmatch '\Adb_study_migration_qa_[a-f0-9]{12}\z') {
	throw 'Refusing to use an unexpected scratch database name.'
}
$createdDatabase = $false
$environmentNames = @('DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE')
$previousEnvironment = @{}
foreach ($name in $environmentNames) { $previousEnvironment[$name] = [Environment]::GetEnvironmentVariable($name, 'Process') }

function Invoke-MySql([string] $Sql) {
	$output = & $MysqlPath '-uroot' '-N' '-B' '-e' $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw "MySQL migration check failed: $output" }
	return ($output -join "`n").Trim()
}

try {
	Invoke-MySql "CREATE DATABASE ``$databaseName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null
	$createdDatabase = $true
	$env:DB_HOST = '127.0.0.1'
	$env:DB_USERNAME = 'root'
	$env:DB_PASSWORD = ''
	$env:DB_DATABASE = $databaseName

	Push-Location $projectRoot
	try {
		$migrationOutput = & $Php81Path index.php cli/migrate 2>&1
		if ($LASTEXITCODE -ne 0) { throw "Clean-database migration failed: $migrationOutput" }
		Write-Output $migrationOutput
		$repeatOutput = & $Php81Path index.php cli/migrate 2>&1
		if ($LASTEXITCODE -ne 0) { throw "Repeated latest migration check failed: $repeatOutput" }
		Write-Output 'PASS migration command is repeatable at the latest schema version.'
	} finally {
		Pop-Location
	}

	$result = Invoke-MySql "SELECT CONCAT((SELECT MAX(version) FROM ``$databaseName``.study_migrations),'|',(SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$databaseName' AND table_type='BASE TABLE'),'|',(SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$databaseName' AND table_type='BASE TABLE' AND (engine <> 'InnoDB' OR table_collation NOT LIKE 'utf8mb4%')));"
	$parts = $result -split '\|'
	if ($parts.Count -ne 3 -or $parts[0] -ne '13' -or $parts[1] -ne '33' -or $parts[2] -ne '0') {
		throw "Unexpected clean migration result: $result (expected migration 13, 33 InnoDB/utf8mb4 tables, and zero incompatible tables)."
	}
	Write-Output "PASS clean database migrated to version $($parts[0]) with $($parts[1]) InnoDB/utf8mb4 tables."

	$fixtureUser = 'qa_migration_' + $databaseName.Substring($databaseName.Length - 12)
	$fixtureUserIdOutput = Invoke-MySql "INSERT INTO ``$databaseName``.st_users (username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES ('$fixtureUser','not-a-real-login-hash','Migration QA','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()); SELECT LAST_INSERT_ID();"
	$fixtureUserId = [int] (($fixtureUserIdOutput -split "`n")[-1])
	$duplicateError = $null
	try {
		Invoke-MySql "INSERT INTO ``$databaseName``.st_users (username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES ('$fixtureUser','not-a-real-login-hash','Duplicate QA','adult','adult','active',UTC_TIMESTAMP(),UTC_TIMESTAMP());"
	} catch { $duplicateError = $_.Exception.Message }
	if ($null -eq $duplicateError -or $duplicateError -notmatch 'Duplicate entry') { throw 'The unique username constraint did not reject a duplicate fixture.' }
	Write-Output 'PASS unique username constraint rejects duplicate data.'

	$foreignKeyError = $null
	try {
		Invoke-MySql "INSERT INTO ``$databaseName``.st_lesson_progress (learner_user_id,lesson_id,status,last_position,updated_at) VALUES ($fixtureUserId,999999999,'in_progress',0,UTC_TIMESTAMP());"
	} catch { $foreignKeyError = $_.Exception.Message }
	if ($null -eq $foreignKeyError -or $foreignKeyError -notmatch 'foreign key constraint fails') { throw 'The lesson-progress foreign key did not reject an unknown lesson.' }
	Write-Output 'PASS foreign key rejects progress for an unknown lesson.'

	$rollbackCount = Invoke-MySql "START TRANSACTION; INSERT INTO ``$databaseName``.st_categories (name,slug,description,sort_order,status,created_at,updated_at) VALUES ('Rollback QA','rollback-qa','temporary',0,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP()); ROLLBACK; SELECT COUNT(*) FROM ``$databaseName``.st_categories WHERE slug='rollback-qa';"
	if ($rollbackCount -ne '0') { throw 'Database transaction rollback left test data behind.' }
	Write-Output 'PASS transaction rollback removes uncommitted test data.'

	$snapshotColumns = Invoke-MySql "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$databaseName' AND ((table_name='st_practice_sessions' AND column_name IN ('course_id','idempotency_key','rules_version')) OR (table_name='st_session_questions' AND column_name='snapshot_json') OR (table_name='st_practice_attempts' AND column_name='answer_revision'));"
	if ($snapshotColumns -ne '5') { throw 'Course practice snapshot/revision columns are missing.' }
	$sessionInsert = "INSERT INTO ``$databaseName``.st_practice_sessions (created_by_user_id,learner_user_id,title,idempotency_key,created_at,updated_at) VALUES ($fixtureUserId,$fixtureUserId,'Practice replay constraint QA','qa-idempotency-constraint',UTC_TIMESTAMP(),UTC_TIMESTAMP());"
	Invoke-MySql $sessionInsert | Out-Null
	$duplicateStartError = $null
	try { Invoke-MySql $sessionInsert | Out-Null } catch { $duplicateStartError = $_.Exception.Message }
	if ($null -eq $duplicateStartError -or $duplicateStartError -notmatch 'Duplicate entry') { throw 'A duplicated learner/start idempotency key must be rejected by the database.' }
	Write-Output 'PASS practice snapshot/revision columns exist and duplicate learner/start keys are rejected by a database constraint.'
	$familyColumns = Invoke-MySql "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$databaseName' AND ((table_name='st_packages' AND column_name='family_practice_enabled' AND column_default='0') OR (table_name='st_practice_sessions' AND column_name='draft_revision' AND column_default='0'));"
	if ($familyColumns -ne '2') { throw 'Family authoring columns must default to zero, never silently grant access.' }
	Write-Output 'PASS family feature capability and draft revision columns default to zero.'
	$deliveryColumn = Invoke-MySql "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='$databaseName' AND table_name='st_practice_sessions' AND column_name='available_from';"
	if ($deliveryColumn -ne '1') { throw 'Family delivery start time is missing.' }
	$attemptInsert = "INSERT INTO ``$databaseName``.st_practice_attempts(session_id,learner_user_id,status,question_count,started_at) SELECT id,$fixtureUserId,'in_progress',1,UTC_TIMESTAMP() FROM ``$databaseName``.st_practice_sessions WHERE idempotency_key='qa-idempotency-constraint';"
	Invoke-MySql $attemptInsert | Out-Null
	$duplicateAttemptError = $null
	try { Invoke-MySql $attemptInsert | Out-Null } catch { $duplicateAttemptError = $_.Exception.Message }
	if ($null -eq $duplicateAttemptError -or $duplicateAttemptError -notmatch 'Duplicate entry') { throw 'Duplicate session/learner attempt must be rejected by the database.' }
	Write-Output 'PASS delivery schedule column and unique session/learner attempt constraint.'
}
finally {
	foreach ($name in $environmentNames) {
		[Environment]::SetEnvironmentVariable($name, $previousEnvironment[$name], 'Process')
	}
	if ($createdDatabase) {
		if ($databaseName -match '\Adb_study_migration_qa_[a-f0-9]{12}\z') {
			Invoke-MySql "DROP DATABASE ``$databaseName``;" | Out-Null
			Write-Output "PASS removed only temporary database $databaseName."
		} else {
			throw 'Refusing to remove a database with an unexpected name.'
		}
	}
}
