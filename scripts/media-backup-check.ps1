param(
	[Parameter(Mandatory=$true)][string]$Php81Path,
	[Parameter(Mandatory=$true)][string]$MysqlPath,
	[Parameter(Mandatory=$true)][string]$MysqlDumpPath
)
$ErrorActionPreference='Stop'
$projectRoot=Split-Path -Parent $PSScriptRoot
$suffix=[Guid]::NewGuid().ToString('N').Substring(0,12)
$sourceDatabase='db_study_media_backup_qa_'+$suffix
$restoreDatabase='db_study_media_restore_qa_'+$suffix
$tempRoot=[IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\','/')
$taskRoot=Join-Path $tempRoot ('study-media-backup-qa-'+$suffix)
$sourceMedia=Join-Path $taskRoot 'source/media'
$snapshotMedia=Join-Path $taskRoot 'snapshot/media'
$restoreMedia=Join-Path $taskRoot 'restored/media'
$dumpPath=Join-Path $taskRoot 'snapshot/database.sql'
$manifestPath=Join-Path $taskRoot 'snapshot/manifest.json'
$createdDatabases=New-Object 'System.Collections.Generic.List[string]'
$createdDirectories=New-Object 'System.Collections.Generic.List[string]'
$createdFiles=New-Object 'System.Collections.Generic.HashSet[string]' ([StringComparer]::OrdinalIgnoreCase)
$environmentNames=@('CI_ENV','DB_HOST','DB_USERNAME','DB_PASSWORD','DB_DATABASE','STUDY_MEDIA_ROOT','STUDY_MEDIA_BACKUP_QA_ROOT')
$previous=@{}
foreach($name in $environmentNames){$previous[$name]=[Environment]::GetEnvironmentVariable($name,'Process')}
foreach($binary in @($Php81Path,$MysqlPath,$MysqlDumpPath)){if(-not(Test-Path -LiteralPath $binary -PathType Leaf)){throw 'A required local CLI executable is missing.'}}

function Assert-Schema([string]$Name){
	if($Name -notin @($sourceDatabase,$restoreDatabase) -or $Name -notmatch '\Adb_study_media_(backup|restore)_qa_[a-f0-9]{12}\z'){throw 'Refusing a database outside this exact scratch run.'}
}
function Assert-Canonical([string]$Path){
	$full=[IO.Path]::GetFullPath($Path).TrimEnd('\','/')
	$item=Get-Item -LiteralPath $full -Force
	if(-not $item.FullName.TrimEnd('\','/').Equals($full,[StringComparison]::OrdinalIgnoreCase)){throw 'Noncanonical QA path rejected.'}
	$cursor=$full
	while($cursor){
		$entry=Get-Item -LiteralPath $cursor -Force
		if(($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0){throw 'Symlink or junction in QA path rejected.'}
		$parent=[IO.Path]::GetDirectoryName($cursor)
		if(-not $parent -or $parent -eq $cursor){break}
		$cursor=$parent
	}
	return $full
}
function Assert-InRun([string]$Path,[switch]$Existing){
	$full=[IO.Path]::GetFullPath($Path).TrimEnd('\','/')
	$expected=Join-Path $tempRoot ('study-media-backup-qa-'+$suffix)
	if($taskRoot -ne $expected -or [IO.Path]::GetFileName($taskRoot) -notmatch '\Astudy-media-backup-qa-[a-f0-9]{12}\z' -or ($full -ne $taskRoot -and -not $full.StartsWith($taskRoot+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase))){throw 'QA path escaped the exact temporary run directory.'}
	if($Existing){Assert-Canonical $full | Out-Null}else{Assert-Canonical ([IO.Path]::GetDirectoryName($full)) | Out-Null}
	return $full
}
function New-QADirectory([string]$Path){
	Assert-InRun $Path | Out-Null
	if(Test-Path -LiteralPath $Path){throw 'Refusing to reuse a QA directory.'}
	New-Item -ItemType Directory -Path $Path | Out-Null
	$createdDirectories.Add($Path)
	Assert-InRun $Path -Existing | Out-Null
}
function Invoke-Sql([string]$Sql,[string]$Database=''){
	$arguments=@('--no-defaults','--host=127.0.0.1','--protocol=TCP','--default-character-set=utf8mb4','-uroot','-N','-B')
	if($Database){Assert-Schema $Database;$arguments+=@('-D',$Database)}
	$output=& $MysqlPath @arguments '-e' $Sql 2>&1
	if($LASTEXITCODE -ne 0){throw 'Scratch database command failed; no SQL or credentials are printed.'}
	return ($output -join "`n").Trim()
}
function Set-QAEnvironment([string]$Database){
	Assert-Schema $Database
	$env:CI_ENV='development';$env:DB_HOST='127.0.0.1';$env:DB_USERNAME='root';$env:DB_PASSWORD='';$env:DB_DATABASE=$Database
	$env:STUDY_MEDIA_BACKUP_QA_ROOT=$taskRoot
	$env:STUDY_MEDIA_ROOT=if($Database -eq $sourceDatabase){$sourceMedia}else{$restoreMedia}
}
function Invoke-QA([string]$Database,[string]$Mode,[string[]]$ExpectedFailure=@(),[string]$GuardCase=''){
	Set-QAEnvironment $Database
	if($GuardCase){
		switch($GuardCase){
			'operational_database' {$env:DB_DATABASE='db_study'}
			'nonloopback_host' {$env:DB_HOST='example.invalid'}
			'wrong_suffix' {$env:DB_DATABASE='db_study_media_backup_qa_'+[Guid]::NewGuid().ToString('N').Substring(0,12)}
			'outside_root' {$env:STUDY_MEDIA_BACKUP_QA_ROOT=$tempRoot}
			'mixed_media_root' {$env:STUDY_MEDIA_ROOT=$restoreMedia}
			'path_traversal' {$env:STUDY_MEDIA_ROOT=$sourceMedia+'/../media'}
			default {throw 'Unknown guard probe.'}
		}
	}
	$previousPreference=$ErrorActionPreference
	try{
		# Windows PowerShell treats redirected native stderr as ErrorRecord; capture it
		# so an expected helper rejection is checked by both exit code and safe reason.
		$ErrorActionPreference='Continue'
		$output=& $Php81Path index.php cli/check_media_backup index $Mode 2>&1
		$exitCode=$LASTEXITCODE
	}finally{$ErrorActionPreference=$previousPreference}
	$joined=($output -join "`n").Trim()
	if($ExpectedFailure.Count){
		$matched=$false
		foreach($message in $ExpectedFailure){if($joined.Contains($message)){$matched=$true}}
		if($exitCode -eq 0 -or -not $matched){throw 'A negative restore probe did not fail for its expected integrity/inventory reason.'}
		if($GuardCase -and -not $joined.Contains('Phase: environment.')){throw 'A root/database guard probe reached controller autoload instead of rejecting early.'}
		return
	}
	if($exitCode -ne 0){
		$safeReasons=@('Synthetic backup environment rejected.','Synthetic restored media integrity check failed.','Synthetic media inventory check failed.','Synthetic backup schema check failed.','Synthetic backup fixture check failed.','Synthetic backup seed requires a fresh empty source.','Synthetic backup mode rejected.','Synthetic backup image prerequisites unavailable.','Synthetic backup fixture cleanup failed.','Synthetic backup operation failed.')
		$reason='No recognized safe diagnostic.'
		foreach($candidate in $safeReasons){if($joined.Contains($candidate)){$reason=$candidate;break}}
		if($joined -match 'Phase: ([a-z_]{1,64})\.'){ $reason+=' Phase: '+$Matches[1]+'.' }
		throw ('Isolated media backup helper failed in mode '+$Mode+': '+$reason)
	}
	return $joined
}
function Get-Digest([string]$Database){
	$result=(Invoke-QA $Database 'digest') | ConvertFrom-Json
	if($result.version -ne 13 -or $result.table_count -ne 33 -or $result.image_count -ne 3 -or @($result.tables).Count -ne 33 -or @($result.files).Count -ne 3){throw 'Unexpected complete schema/media digest inventory.'}
	$tableNames=@{}
	foreach($table in $result.tables){
		if($table.name -notmatch '\A(?:st_[a-z_]+|study_migrations)\z' -or $tableNames.ContainsKey($table.name) -or [string]$table.row_count -notmatch '\A[0-9]+\z' -or $table.sha256 -notmatch '\A[a-f0-9]{64}\z' -or $table.structure_sha256 -notmatch '\A[a-f0-9]{64}\z'){throw 'Invalid table manifest entry.'}
		$tableNames[$table.name]=$true
	}
	$keys=@{}
	foreach($file in $result.files){
		if($file.storage_key -notmatch '\A[a-f0-9]{64}\.(png|jpg)\z' -or $keys.ContainsKey($file.storage_key) -or $file.sha256 -notmatch '\A[a-f0-9]{64}\z' -or [long]$file.byte_size -lt 1 -or [long]$file.byte_size -gt 6291456 -or $file.mime_type -notin @('image/png','image/jpeg')){throw 'Invalid media manifest entry.'}
		$keys[$file.storage_key]=$true
	}
	return $result
}
function Copy-Exclusive([string]$Source,[string]$Destination){
	Assert-InRun $Source -Existing | Out-Null;Assert-InRun $Destination | Out-Null
	if(-not(Test-Path -LiteralPath $Source -PathType Leaf)){throw 'Only regular QA files may be copied.'}
	[IO.File]::Copy($Source,$Destination,$false)
	$createdFiles.Add($Destination) | Out-Null
	Assert-InRun $Destination -Existing | Out-Null
}
function Assert-MediaSet([string]$Directory,$Files){
	Assert-InRun $Directory -Existing | Out-Null
	$entries=@(Get-ChildItem -LiteralPath $Directory -Force)
	$expected=@($Files | ForEach-Object {$_.storage_key} | Sort-Object)
	$actual=@($entries | ForEach-Object {$_.Name} | Sort-Object)
	if(($expected -join '|') -ne ($actual -join '|')){throw 'Media file set does not match manifest exactly.'}
	foreach($file in $Files){
		$path=Join-Path $Directory $file.storage_key
		Assert-InRun $path -Existing | Out-Null
		if(-not(Test-Path -LiteralPath $path -PathType Leaf) -or (Get-Item -LiteralPath $path).Length -ne [long]$file.byte_size -or (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant() -ne $file.sha256){throw 'Media bytes do not match manifest.'}
	}
}
function Assert-Snapshot($Manifest){
	if($Manifest.format -ne 'study-media-restore-drill-v1' -or $Manifest.source_database -ne $sourceDatabase -or $Manifest.dump_sha256 -notmatch '\A[a-f0-9]{64}\z'){throw 'Invalid paired snapshot manifest.'}
	Assert-InRun $dumpPath -Existing | Out-Null
	if((Get-FileHash -LiteralPath $dumpPath -Algorithm SHA256).Hash.ToLowerInvariant() -ne $Manifest.dump_sha256){throw 'Database dump does not match manifest.'}
	Assert-MediaSet $snapshotMedia $Manifest.digest.files
}
function Restore-OneFile([string]$Key){
	$source=Join-Path $snapshotMedia $Key;$target=Join-Path $restoreMedia $Key
	Assert-InRun $source -Existing | Out-Null;Assert-InRun $target -Existing | Out-Null
	if(-not $createdFiles.Contains($target)){throw 'Refusing overwrite outside an owned restored fixture.'}
	[IO.File]::Copy($source,$target,$true)
}

Push-Location $projectRoot
try {
	Assert-Schema $sourceDatabase;Assert-Schema $restoreDatabase
	Assert-Canonical $tempRoot | Out-Null
	New-QADirectory $taskRoot
	foreach($folder in @('source','source/media','snapshot','snapshot/media','restored','restored/media')){New-QADirectory (Join-Path $taskRoot $folder)}
	foreach($guardCase in @('operational_database','nonloopback_host','wrong_suffix','outside_root','mixed_media_root','path_traversal')){
		Invoke-QA $sourceDatabase 'seed' @('Synthetic backup environment rejected.') $guardCase
	}
	Write-Output 'PASS six invalid schema/host/suffix/root/traversal targets rejected before controller/database autoload.'
	foreach($database in @($sourceDatabase,$restoreDatabase)){
		Invoke-Sql "CREATE DATABASE ``$database`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null
		$createdDatabases.Add($database)
	}
	Set-QAEnvironment $sourceDatabase
	$migration=& $Php81Path index.php cli/migrate 2>&1
	if($LASTEXITCODE -ne 0){throw 'Fresh isolated source migration failed.'}
	Invoke-QA $sourceDatabase 'seed' | Out-Null
	Invoke-QA $sourceDatabase 'verify' | Out-Null
	$before=Get-Digest $sourceDatabase
	Assert-MediaSet $sourceMedia $before.files
	foreach($file in $before.files){$createdFiles.Add((Join-Path $sourceMedia $file.storage_key)) | Out-Null}
	Write-Output 'PASS isolated v13 source: synthetic PNG/JPEG, revisions/audit, free and paid learner-access checks.'

	# Only this test writes the unique source schema/root; all seeding has stopped.
	Assert-InRun $dumpPath | Out-Null
	if(Test-Path -LiteralPath $dumpPath){throw 'Snapshot dump unexpectedly exists.'}
	$createdFiles.Add($dumpPath) | Out-Null
	$dumpOutput=& $MysqlDumpPath '--no-defaults' '--host=127.0.0.1' '--protocol=TCP' '--default-character-set=utf8mb4' '-uroot' '--single-transaction' '--skip-lock-tables' '--hex-blob' '--routines' '--triggers' '--events' "--result-file=$dumpPath" $sourceDatabase 2>&1
	if($LASTEXITCODE -ne 0 -or -not(Test-Path -LiteralPath $dumpPath -PathType Leaf)){throw 'Isolated single-schema dump failed.'}
	if(Select-String -LiteralPath $dumpPath -Pattern '^\s*(CREATE\s+DATABASE|USE\s+)' -Quiet){throw 'Dump must not create or select another database.'}
	foreach($file in $before.files){Copy-Exclusive (Join-Path $sourceMedia $file.storage_key) (Join-Path $snapshotMedia $file.storage_key)}
	$after=Get-Digest $sourceDatabase
	if((ConvertTo-Json $before -Depth 12 -Compress) -ne (ConvertTo-Json $after -Depth 12 -Compress)){throw 'Synthetic source changed while taking its paired snapshot.'}
	$manifest=[ordered]@{format='study-media-restore-drill-v1';source_database=$sourceDatabase;dump_sha256=(Get-FileHash -LiteralPath $dumpPath -Algorithm SHA256).Hash.ToLowerInvariant();digest=$before}
	Assert-InRun $manifestPath | Out-Null
	$manifestBytes=[Text.UTF8Encoding]::new($false).GetBytes((ConvertTo-Json $manifest -Depth 12))
	$manifestStream=[IO.File]::Open($manifestPath,[IO.FileMode]::CreateNew,[IO.FileAccess]::Write,[IO.FileShare]::None)
	$createdFiles.Add($manifestPath) | Out-Null
	try{$manifestStream.Write($manifestBytes,0,$manifestBytes.Length)}finally{$manifestStream.Dispose()}
	$manifest=Get-Content -LiteralPath $manifestPath -Raw -Encoding UTF8 | ConvertFrom-Json
	Assert-Snapshot $manifest
	$wrongManifest=$manifest | ConvertTo-Json -Depth 12 | ConvertFrom-Json
	$wrongManifest.dump_sha256=('0'*64)
	$hashRefused=$false
	try{Assert-Snapshot $wrongManifest}catch{if($_.Exception.Message -ne 'Database dump does not match manifest.'){throw};$hashRefused=$true}
	if(-not $hashRefused){throw 'Mismatched dump manifest was not refused before import.'}
	Write-Output 'PASS paired snapshot manifest, exact media set and before/after complete table digests; mismatched dump hash refused.'

	if((Invoke-Sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$restoreDatabase';") -ne '0'){throw 'Restore destination must be a new empty schema.'}
	Assert-Snapshot $manifest
	$escapedDump=$dumpPath.Replace('\','/').Replace('"','\"')
	Invoke-Sql ('SOURCE "'+$escapedDump+'";') $restoreDatabase | Out-Null
	foreach($file in $before.files){Copy-Exclusive (Join-Path $snapshotMedia $file.storage_key) (Join-Path $restoreMedia $file.storage_key)}
	Assert-MediaSet $restoreMedia $before.files
	Invoke-QA $restoreDatabase 'verify' | Out-Null
	$restored=Get-Digest $restoreDatabase
	if((ConvertTo-Json $before -Depth 12 -Compress) -ne (ConvertTo-Json $restored -Depth 12 -Compress)){throw 'Restored complete table/media digests differ from source.'}
	Write-Output 'PASS restore into a separate v13 schema/root: all 33 table row/structure digests, media bytes and model access checks match.'

	$first=$before.files[0];$firstPath=Join-Path $restoreMedia $first.storage_key
	$held=Join-Path $taskRoot 'restored/held-image.bin'
	Assert-InRun $firstPath -Existing | Out-Null;Assert-InRun $held | Out-Null
	if(Test-Path -LiteralPath $held){throw 'Held synthetic file unexpectedly exists.'}
	Move-Item -LiteralPath $firstPath -Destination $held
	$createdFiles.Add($held) | Out-Null
	try{Invoke-QA $restoreDatabase 'verify' @('Synthetic media inventory check failed.','Synthetic restored media integrity check failed.')}finally{
		Assert-InRun $held -Existing | Out-Null;Assert-InRun $firstPath | Out-Null
		if(Test-Path -LiteralPath $firstPath){throw 'Refusing to overwrite a newly appeared restored file.'}
		Move-Item -LiteralPath $held -Destination $firstPath
	}
	Assert-InRun $firstPath -Existing | Out-Null
	[IO.File]::WriteAllBytes($firstPath,[Text.Encoding]::ASCII.GetBytes('synthetic-corrupt-image'))
	try{Invoke-QA $restoreDatabase 'verify' @('Synthetic restored media integrity check failed.')}finally{Restore-OneFile $first.storage_key}
	$otherPath=Join-Path $snapshotMedia $before.files[1].storage_key
	Assert-InRun $otherPath -Existing | Out-Null;Assert-InRun $firstPath -Existing | Out-Null
	[IO.File]::Copy($otherPath,$firstPath,$true)
	try{Invoke-QA $restoreDatabase 'verify' @('Synthetic restored media integrity check failed.')}finally{Restore-OneFile $first.storage_key}
	$orphan=Join-Path $restoreMedia ([Guid]::NewGuid().ToString('N')+[Guid]::NewGuid().ToString('N')+'.png')
	Copy-Exclusive (Join-Path $snapshotMedia $first.storage_key) $orphan
	try{Invoke-QA $restoreDatabase 'verify' @('Synthetic media inventory check failed.')}finally{Assert-InRun $orphan -Existing | Out-Null;Remove-Item -LiteralPath $orphan -Force}
	Invoke-QA $restoreDatabase 'verify' | Out-Null
	Assert-Snapshot $manifest
	Assert-MediaSet $sourceMedia $before.files
	$final=Get-Digest $restoreDatabase
	if((ConvertTo-Json $before -Depth 12 -Compress) -ne (ConvertTo-Json $final -Depth 12 -Compress)){throw 'Restored state was not preserved after negative probes.'}
	Write-Output 'PASS missing, corrupt, swapped and unreferenced restored-media probes rejected; snapshot/source unchanged and restored copy recovered.'
	Write-Output 'LIMITATION quiescent synthetic CLI drill only: not live atomic backup, production restore, Linux, HTTP upload/access, encryption, retention or RPO/RTO certification.'
} finally {
	foreach($name in $environmentNames){[Environment]::SetEnvironmentVariable($name,$previous[$name],'Process')}
	Pop-Location
	$cleanupErrors=New-Object 'System.Collections.Generic.List[string]'
	foreach($database in $createdDatabases){
		try{Assert-Schema $database;Invoke-Sql "DROP DATABASE ``$database``;" | Out-Null;if((Invoke-Sql "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$database';") -ne '0'){throw 'Scratch schema remains.'}}
		catch{$cleanupErrors.Add('A scratch database could not be cleaned.')}
	}
	if($createdDirectories.Contains($taskRoot)){
		try{
			Assert-InRun $taskRoot -Existing | Out-Null
			# A seed failure may leave only newly-created randomized images; no recursive deletion.
			foreach($directory in @($sourceMedia,$snapshotMedia,$restoreMedia)){
				if($createdDirectories.Contains($directory) -and (Test-Path -LiteralPath $directory)){
					Assert-InRun $directory -Existing | Out-Null
					foreach($entry in @(Get-ChildItem -LiteralPath $directory -Force)){
						Assert-InRun $entry.FullName -Existing | Out-Null
						if($entry.PSIsContainer -or $entry.Name -notmatch '\A[a-f0-9]{64}\.(png|jpg)\z'){throw 'Unexpected entry retained in QA media directory.'}
						$createdFiles.Add($entry.FullName) | Out-Null
					}
				}
			}
			foreach($file in $createdFiles){if(Test-Path -LiteralPath $file){Assert-InRun $file -Existing | Out-Null;if(-not(Test-Path -LiteralPath $file -PathType Leaf)){throw 'Nonfile retained.'};Remove-Item -LiteralPath $file -Force}}
			for($index=$createdDirectories.Count-1;$index -ge 0;$index--){$directory=$createdDirectories[$index];Assert-InRun $directory -Existing | Out-Null;if(@(Get-ChildItem -LiteralPath $directory -Force).Count){throw 'Nonempty QA directory retained.'};Remove-Item -LiteralPath $directory -Force}
		}catch{$cleanupErrors.Add('QA files were retained because safe cleanup could not be confirmed.')}
	}
	if($cleanupErrors.Count){throw ($cleanupErrors -join ' ')}
	Write-Output 'PASS exact scratch databases, synthetic private images, manifest/dump and empty QA directories removed; process environment restored. No operational data was targeted.'
}
