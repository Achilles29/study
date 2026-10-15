param(
	[Parameter(Mandatory=$true)][string]$Php81Path,
	[Parameter(Mandatory=$true)][string]$MysqlPath
)
$ErrorActionPreference='Stop'
$projectRoot=Split-Path -Parent $PSScriptRoot
$databaseName='db_study_media_schema_qa_'+[Guid]::NewGuid().ToString('N').Substring(0,12)
if($databaseName -notmatch '\Adb_study_media_schema_qa_[a-f0-9]{12}\z'){throw 'Unexpected scratch database name; refusing schema QA.'}
$createdDatabase=$false
$environmentNames=@('CI_ENV','DB_HOST','DB_USERNAME','DB_PASSWORD','DB_DATABASE')
$previous=@{}
foreach($name in $environmentNames){$previous[$name]=[Environment]::GetEnvironmentVariable($name,'Process')}
function Invoke-ScratchSql([string]$Sql){
	$output=& $MysqlPath '--no-defaults' '--host=127.0.0.1' '--protocol=TCP' '-uroot' '-N' '-B' '-e' $Sql 2>&1
	if($LASTEXITCODE -ne 0){throw 'Lesson media schema SQL check failed; inspect locally without exposing credentials.'}
	return ($output -join "`n").Trim()
}
try {
	Invoke-ScratchSql "CREATE DATABASE ``$databaseName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null
	$createdDatabase=$true
	$env:CI_ENV='development'; $env:DB_HOST='127.0.0.1'; $env:DB_USERNAME='root'; $env:DB_PASSWORD=''; $env:DB_DATABASE=$databaseName
	Push-Location $projectRoot
	try {
		& $Php81Path index.php cli/check_lesson_media_schema
		if($LASTEXITCODE -ne 0){throw 'Lesson media schema CLI check failed.'}
	} finally {Pop-Location}
	$state=Invoke-ScratchSql "SELECT CONCAT((SELECT MAX(version) FROM ``$databaseName``.study_migrations),'|',(SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$databaseName' AND table_type='BASE TABLE'),'|',(SELECT COUNT(*) FROM ``$databaseName``.st_lesson_images));"
	if($state -ne '13|33|0'){throw 'Final scratch schema state is not empty-image version 13 with 33 tables.'}
	Write-Output 'PASS final scratch version/table count/image-row count independently verified by MySQL CLI.'
} finally {
	foreach($name in $environmentNames){[Environment]::SetEnvironmentVariable($name,$previous[$name],'Process')}
	if($createdDatabase){
		if($databaseName -notmatch '\Adb_study_media_schema_qa_[a-f0-9]{12}\z'){throw 'Refusing cleanup outside the exact scratch schema pattern.'}
		Invoke-ScratchSql "DROP DATABASE ``$databaseName``;" | Out-Null
		$remaining=Invoke-ScratchSql "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$databaseName';"
		if($remaining -ne '0'){throw 'Exact temporary database cleanup failed.'}
		Write-Output "PASS removed only temporary database $databaseName; process environment restored."
	}
}
