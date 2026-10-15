param(
	[Parameter(Mandatory = $true)][string] $Php80Path,
	[Parameter(Mandatory = $true)][string] $Php81Path,
	[Parameter(Mandatory = $false)][string] $Php85Path = '',
	[Parameter(Mandatory = $true)][string] $MysqlPath,
	[Parameter(Mandatory = $true)][string] $BaseUrl,
	[Parameter(Mandatory = $false)][string] $AppEncryptionKey = '',
	[Parameter(Mandatory = $false)][switch] $RunBackupRestoreCheck,
	[Parameter(Mandatory = $false)][string] $ChromePath = '',
	[Parameter(Mandatory = $false)][switch] $AllowUnavailableMediaUpload,
	[Parameter(Mandatory = $false)][switch] $RunMediaBackupRestoreCheck
)

$ErrorActionPreference = 'Stop'
$scriptRoot = $PSScriptRoot
$parsedBaseUrl = $null
if ( ! [Uri]::TryCreate($BaseUrl, [UriKind]::Absolute, [ref] $parsedBaseUrl) -or
	$parsedBaseUrl.Scheme -ne 'http' -or ! $parsedBaseUrl.IsLoopback -or
	$parsedBaseUrl.UserInfo -ne '' -or $parsedBaseUrl.Query -ne '' -or $parsedBaseUrl.Fragment -ne '') {
	throw 'QA lokal hanya boleh diarahkan ke URL HTTP loopback tanpa kredensial, query, atau fragment.'
}

Write-Output '== Study local QA: PHP lint, HTTP smoke, and private-file checks =='
Write-Output '== Local database account privilege boundaries =='
& (Join-Path $scriptRoot 'database-privilege-check.ps1') -MysqlPath $MysqlPath
& (Join-Path $scriptRoot 'smoke-check.ps1') -Php80Path $Php80Path -Php81Path $Php81Path -Php85Path $Php85Path -BaseUrl $BaseUrl
& (Join-Path $scriptRoot 'catalog-check.ps1') -MysqlPath $MysqlPath -BaseUrl $BaseUrl -ChromePath $ChromePath
& node (Join-Path $scriptRoot 'recovery-http-check.mjs') --base-url $BaseUrl
if ($LASTEXITCODE -ne 0) { throw 'Recovery page HTTP regression failed.' }
& node (Join-Path $scriptRoot 'navigation-http-check.mjs') --base-url $BaseUrl
if ($LASTEXITCODE -ne 0) { throw 'Navigation HTTP regression failed.' }

Write-Output '== Authentication forms and privacy =='
& (Join-Path $scriptRoot 'http-method-check.ps1') -PhpPath $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl
& (Join-Path $scriptRoot 'auth-input-check.ps1') -MysqlPath $MysqlPath -BaseUrl $BaseUrl
& (Join-Path $scriptRoot 'password-bootstrap-check.ps1') -PhpPath $Php81Path -MysqlPath $MysqlPath
if ($AppEncryptionKey) {
	Write-Output '== Own-password change, session revocation and reauthentication =='
	& (Join-Path $scriptRoot 'account-security-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl -AppEncryptionKey $AppEncryptionKey -ChromePath $ChromePath
	Write-Output '== Login brute-force throttling =='
	& (Join-Path $scriptRoot 'auth-throttle-check.ps1') -MysqlPath $MysqlPath -BaseUrl $BaseUrl -AppEncryptionKey $AppEncryptionKey
}
else {
	Write-Warning 'Account-security and login-throttle integration tests skipped; provide -AppEncryptionKey matching the web process so only synthetic HMAC rows can be cleaned.'
}

Write-Output '== Admin workflows, validation, and role boundaries =='
& (Join-Path $scriptRoot 'admin-course-list-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl -ChromePath $ChromePath
& (Join-Path $scriptRoot 'admin-input-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl -ChromePath $ChromePath
& (Join-Path $scriptRoot 'unsaved-form-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl -ChromePath $ChromePath
& (Join-Path $scriptRoot 'course-structure-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl -ChromePath $ChromePath
& (Join-Path $scriptRoot 'lesson-revision-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl
& (Join-Path $scriptRoot 'lesson-media-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl -ChromePath $ChromePath -AllowUnavailableUpload:$AllowUnavailableMediaUpload
& (Join-Path $scriptRoot 'role-access-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl
& (Join-Path $scriptRoot 'user-directory-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl -ChromePath $ChromePath

Write-Output '== Flashcard authoring, review lifecycle, access, and learner progress =='
& (Join-Path $scriptRoot 'flashcard-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl -ChromePath $ChromePath

Write-Output '== Learning progress, family isolation, and autosave =='
& (Join-Path $scriptRoot 'progress-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl -ChromePath $ChromePath -ExerciseReadingSave:([bool]$ChromePath)
& (Join-Path $scriptRoot 'dashboard-resume-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl -ChromePath $ChromePath
& (Join-Path $scriptRoot 'course-outline-progress-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl -ChromePath $ChromePath

Write-Output '== Course question review, private practice attempts, scoring, and replay protection =='
& (Join-Path $scriptRoot 'practice-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl -ChromePath $ChromePath

Write-Output '== Private parent draft authoring and package capability boundaries =='
& (Join-Path $scriptRoot 'parent-practice-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl -ChromePath $ChromePath

Write-Output '== Family delivery, schedule, private child attempts and results =='
& (Join-Path $scriptRoot 'family-delivery-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl -ChromePath $ChromePath

Write-Output '== Server matching history, ownership, snapshots, and replay =='
& (Join-Path $scriptRoot 'matching-storage-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -BaseUrl $BaseUrl -ChromePath $ChromePath -RunResilienceCheck
Write-Output '== Clean-database migration and constraints =='
& (Join-Path $scriptRoot 'migration-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath
& (Join-Path $scriptRoot 'lesson-media-schema-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath
& (Join-Path $scriptRoot 'legacy-migration-guard-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath
Write-Output '== Environment-specific application logging =='
foreach ($appEnvironment in @('development', 'production')) {
	& $Php81Path (Join-Path $scriptRoot 'test-environment-config.php') $appEnvironment
	if ($LASTEXITCODE -ne 0) { throw "Environment logging configuration check failed for $appEnvironment." }
}
Write-Output '== Isolated UI helper unit tests =='
foreach ($unitTestPhp in @($Php80Path, $Php81Path)) {
	& $unitTestPhp (Join-Path $scriptRoot 'test-legacy-migration-failures.php')
	if ($LASTEXITCODE -ne 0) { throw "Legacy migration failure checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-lesson-image-store.php')
	if ($LASTEXITCODE -ne 0) { throw "Lesson image storage checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-lesson-media-view.php')
	if ($LASTEXITCODE -ne 0) { throw "Lesson image view checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-course-structure-context.php')
	if ($LASTEXITCODE -ne 0) { throw "Course structure context checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-course-list-query.php')
	if ($LASTEXITCODE -ne 0) { throw "Course list query checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-catalog-view.php')
	if ($LASTEXITCODE -ne 0) { throw "Catalog view checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-admin-course-list-view.php')
	if ($LASTEXITCODE -ne 0) { throw "Admin course-list view checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-dashboard-resume-model.php')
	if ($LASTEXITCODE -ne 0) { throw "Dashboard resume query checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-dashboard-resume-controller.php')
	if ($LASTEXITCODE -ne 0) { throw "Dashboard resume controller checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-dashboard-resume-view.php')
	if ($LASTEXITCODE -ne 0) { throw "Dashboard resume view checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-course-outline-progress-model.php')
	if ($LASTEXITCODE -ne 0) { throw "Course outline progress model checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-course-outline-progress-controller.php')
	if ($LASTEXITCODE -ne 0) { throw "Course outline progress controller checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-course-outline-progress-view.php')
	if ($LASTEXITCODE -ne 0) { throw "Course outline progress view checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-navigation.php')
	if ($LASTEXITCODE -ne 0) { throw "Navigation checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-http-methods.php')
	if ($LASTEXITCODE -ne 0) { throw "HTTP method helper checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-recovery-pages.php')
	if ($LASTEXITCODE -ne 0) { throw "Recovery page unit checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-recovery-pages.php') --without-config
	if ($LASTEXITCODE -ne 0) { throw "Bootstrapless recovery page checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-password-policy.php')
	if ($LASTEXITCODE -ne 0) { throw "Password boundary checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-session-credentials.php')
	if ($LASTEXITCODE -ne 0) { throw "Session credential checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-study-ui-helper.php')
	if ($LASTEXITCODE -ne 0) { throw "Study UI helper unit tests failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-practice-scoring.php')
	if ($LASTEXITCODE -ne 0) { throw "Practice scoring checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-practice-unsaved-view.php')
	if ($LASTEXITCODE -ne 0) { throw "Practice unsaved view checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-practice-read-access.php')
	if ($LASTEXITCODE -ne 0) { throw "Practice read-access checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-study-transaction.php')
	if ($LASTEXITCODE -ne 0) { throw "Transaction certainty checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-course-access-lock.php')
	if ($LASTEXITCODE -ne 0) { throw "Current course access read checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-reading-transactions.php')
	if ($LASTEXITCODE -ne 0) { throw "Reading transaction checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-reading-probe.php')
	if ($LASTEXITCODE -ne 0) { throw "Reading probe safety checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-reading-recovery.php')
	if ($LASTEXITCODE -ne 0) { throw "Reading recovery checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-flashcard-transactions.php')
	if ($LASTEXITCODE -ne 0) { throw "Flashcard transaction checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-practice-transactions.php')
	if ($LASTEXITCODE -ne 0) { throw "Practice transaction checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-assignment-transactions.php')
	if ($LASTEXITCODE -ne 0) { throw "Assignment transaction checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-practice-recovery-controller.php')
	if ($LASTEXITCODE -ne 0) { throw "Practice recovery controller checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-practice-recovery-view.php')
	if ($LASTEXITCODE -ne 0) { throw "Practice recovery view checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-matching-transactions.php')
	if ($LASTEXITCODE -ne 0) { throw "Matching transaction checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-matching-recovery-controller.php')
	if ($LASTEXITCODE -ne 0) { throw "Matching recovery controller checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-matching-recovery-view.php')
	if ($LASTEXITCODE -ne 0) { throw "Matching recovery view checks failed with $unitTestPhp." }
	& node (Join-Path $scriptRoot 'test-transaction-recovery-fixtures.mjs') --php-path $unitTestPhp
	if ($LASTEXITCODE -ne 0) { throw "Transaction recovery fixture checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-practice-schedule.php')
	if ($LASTEXITCODE -ne 0) { throw "Practice schedule checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-matching-cards.php')
	if ($LASTEXITCODE -ne 0) { throw "Matching card checks failed with $unitTestPhp." }
	& $unitTestPhp (Join-Path $scriptRoot 'test-matching-state.php')
	if ($LASTEXITCODE -ne 0) { throw "Server matching state checks failed with $unitTestPhp." }
}
if ($RunMediaBackupRestoreCheck) {
	Write-Output '== Isolated paired database/media recovery drill =='
	$mysqlDumpPath = Join-Path (Split-Path -Parent $MysqlPath) 'mysqldump.exe'
	if (-not (Test-Path -LiteralPath $mysqlDumpPath -PathType Leaf)) { throw "mysqldump executable was not found beside mysql: $mysqlDumpPath" }
	& (Join-Path $scriptRoot 'media-backup-check.ps1') -Php81Path $Php81Path -MysqlPath $MysqlPath -MysqlDumpPath $mysqlDumpPath
}
if ($RunBackupRestoreCheck) {
	Write-Output '== Empty database backup and restore =='
	$mysqlDumpPath = Join-Path (Split-Path -Parent $MysqlPath) 'mysqldump.exe'
	if (-not (Test-Path -LiteralPath $mysqlDumpPath -PathType Leaf)) { throw "mysqldump executable was not found beside mysql: $mysqlDumpPath" }
	& (Join-Path $scriptRoot 'database-backup-check.ps1') -MysqlPath $MysqlPath -MysqlDumpPath $mysqlDumpPath
}

Write-Output '== JavaScript UI behavior =='
& node (Join-Path $scriptRoot 'nginx-config-check.mjs') --check-rules
if ($LASTEXITCODE -ne 0) { throw 'Static Nginx rule regression failed.' }
$projectRoot = Split-Path -Parent $scriptRoot
Push-Location $projectRoot
try {
	& npm.cmd run check:docs
	if ($LASTEXITCODE -ne 0) { throw "Documentation link check failed with exit code $LASTEXITCODE." }
	& npm.cmd run check:conflicts
	if ($LASTEXITCODE -ne 0) { throw "Merge-conflict marker check failed with exit code $LASTEXITCODE." }
	Write-Output '== CI3-compatible PHP source hygiene =='
	$phpcsPath = Join-Path $scriptRoot 'tools/phpcs-4.0.4.phar'
	if (-not (Test-Path -LiteralPath $phpcsPath -PathType Leaf)) { throw "PHP_CodeSniffer tool is missing: $phpcsPath" }
	$expectedPhpCsHash = '4b010cd21d8bc8a17e2504792e3c77ef8259126f24caf7eafe7eddef1fa871e9'
	$actualPhpCsHash = (Get-FileHash -LiteralPath $phpcsPath -Algorithm SHA256).Hash.ToLowerInvariant()
	if ($actualPhpCsHash -ne $expectedPhpCsHash) { throw 'PHP_CodeSniffer checksum verification failed.' }
	$phpcsRuleset = Join-Path $projectRoot 'phpcs.xml.dist'
	& $Php81Path $phpcsPath "--standard=$phpcsRuleset" --report=summary
	if ($LASTEXITCODE -ne 0) { throw "CI3-compatible PHP source check failed with exit code $LASTEXITCODE." }
	$phpcsIndentRuleset = Join-Path $projectRoot 'phpcs-indent.xml.dist'
	& $Php81Path $phpcsPath "--standard=$phpcsIndentRuleset" --report=summary
	if ($LASTEXITCODE -ne 0) { throw "CI3-compatible PHP indentation check failed with exit code $LASTEXITCODE." }
	Write-Output '== CI3 PHP identifier naming =='
	$phpNameCheck = Join-Path $scriptRoot 'check-php-ci3-names.php'
	foreach ($nameCheckPhp in @($Php80Path, $Php81Path)) {
		& $nameCheckPhp $phpNameCheck
		if ($LASTEXITCODE -ne 0) { throw "CI3 PHP identifier naming check failed with $nameCheckPhp." }
	}
	& npm.cmd run check:secrets
	if ($LASTEXITCODE -ne 0) { throw "Secret scan failed. Findings are masked; review Secretlint output without copying secret values." }
	& npm.cmd run test:secrets
	if ($LASTEXITCODE -ne 0) { throw "Secret scanner positive/negative fixture test failed." }
	& npm.cmd run check:licenses
	if ($LASTEXITCODE -ne 0) { throw "Runtime asset license check failed with exit code $LASTEXITCODE." }
	& npm.cmd run test:ui
	if ($LASTEXITCODE -ne 0) { throw "UI test command failed with exit code $LASTEXITCODE." }
	if ($ChromePath) {
		if (-not (Test-Path -LiteralPath $ChromePath -PathType Leaf)) { throw "Chrome executable was not found: $ChromePath" }
		& node scripts/browser-viewport-check.mjs --base-url $BaseUrl --chrome $ChromePath
		if ($LASTEXITCODE -ne 0) { throw "Browser viewport/accessibility check failed with exit code $LASTEXITCODE." }
		& node scripts/browser-viewport-check.mjs --base-url $BaseUrl --chrome $ChromePath --exercise-button-themes
		if ($LASTEXITCODE -ne 0) { throw 'Shared button theme component checks failed.' }
		& node scripts/browser-viewport-check.mjs --base-url $BaseUrl --chrome $ChromePath --exercise-practice-recovery --php-path $Php81Path
		if ($LASTEXITCODE -ne 0) { throw 'Practice recovery component checks failed (isolated view, not an HTTP 503 fault).' }
		& node scripts/browser-viewport-check.mjs --base-url $BaseUrl --chrome $ChromePath --exercise-matching-transaction-recovery --php-path $Php81Path
		if ($LASTEXITCODE -ne 0) { throw 'Matching recovery component checks failed (isolated view, not an HTTP 503 fault).' }
		& node scripts/browser-viewport-check.mjs --base-url $BaseUrl --chrome $ChromePath --exercise-reading-recovery --php-path $Php81Path
		if ($LASTEXITCODE -ne 0) { throw 'Reading recovery component checks failed (isolated view, not an HTTP 503 fault).' }
		& node scripts/browser-viewport-check.mjs --base-url $BaseUrl --chrome $ChromePath --exercise-flashcard-recovery --php-path $Php81Path
		if ($LASTEXITCODE -ne 0) { throw 'Flashcard recovery component checks failed (isolated view, not an HTTP 503 fault).' }
		& node scripts/browser-viewport-check.mjs --base-url $BaseUrl --chrome $ChromePath --exercise-navigation
		if ($LASTEXITCODE -ne 0) { throw 'Expanded navigation browser checks failed.' }
		& node scripts/browser-viewport-check.mjs --base-url $BaseUrl --chrome $ChromePath --exercise-navigation --block-page-scripts
		if ($LASTEXITCODE -ne 0) { throw 'Navigation fallback without loaded page scripts failed.' }
		& node scripts/browser-viewport-check.mjs --base-url $BaseUrl --chrome $ChromePath --extra-path qa-recovery-missing-page --extra-name recovery-404 --only-extra-route
		if ($LASTEXITCODE -ne 0) { throw "Recovery page browser check failed with exit code $LASTEXITCODE." }
		foreach ($browserZoom in @(100, 200)) {
			& node scripts/browser-viewport-check.mjs --base-url $BaseUrl --chrome $ChromePath --browser-zoom $browserZoom --extra-path qa-recovery-missing-page --extra-name recovery-404
			if ($LASTEXITCODE -ne 0) { throw "Native browser zoom $browserZoom percent check failed with exit code $LASTEXITCODE." }
		}
	}
	else {
		Write-Warning 'Browser viewport/accessibility check skipped; pass -ChromePath to include it.'
	}
}
finally {
	Pop-Location
}

Write-Output 'PASS all local Study QA suites completed.'
