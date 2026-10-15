param(
	[Parameter(Mandatory = $true)][string] $Php80Path,
	[Parameter(Mandatory = $true)][string] $Php81Path,
	[string] $Php85Path = '',
	[Parameter(Mandatory = $true)][string] $BaseUrl
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$applicationRoot = Join-Path $projectRoot 'application'
$frameworkRoot = Join-Path $projectRoot 'system'
$scriptsRoot = $PSScriptRoot
$entryPoint = Join-Path $projectRoot 'index.php'

$phpPaths = @($Php80Path, $Php81Path)
if ($Php85Path) { $phpPaths += $Php85Path }
foreach ($phpPath in $phpPaths) {
	if (-not (Test-Path -LiteralPath $phpPath)) {
		throw "PHP executable not found: $phpPath"
	}
	$phpFiles = @(
		Get-ChildItem -LiteralPath $applicationRoot,$frameworkRoot,$scriptsRoot -Recurse -File -Filter '*.php'
		Get-Item -LiteralPath $entryPoint
	)
	foreach ($file in $phpFiles) {
		$lint = & $phpPath -l $file.FullName 2>&1
		if ($LASTEXITCODE -ne 0) {
			throw "PHP lint failed for $($file.FullName): $lint"
		}
	}
	Write-Output "PASS PHP lint: $phpPath ($($phpFiles.Count) files)"
	$timezoneCheck = & $phpPath (Join-Path $scriptsRoot 'test-application-timezone.php') 2>&1
	if ($LASTEXITCODE -ne 0) { throw "Application timezone check failed on $phpPath`: $timezoneCheck" }
	Write-Output ($timezoneCheck -join "`n")
}

Push-Location $projectRoot
$databaseEnvironmentNames = @('DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE')
$previousDatabaseEnvironment = @{}
foreach ($name in $databaseEnvironmentNames) { $previousDatabaseEnvironment[$name] = [Environment]::GetEnvironmentVariable($name, 'Process') }
try {
	$env:DB_HOST = '127.0.0.1'
	$env:DB_USERNAME = 'study_migrator'
	$env:DB_PASSWORD = ''
	$env:DB_DATABASE = 'db_study'
	$migrationOutput = & $Php81Path index.php cli/migrate 2>&1
	if ($LASTEXITCODE -ne 0) {
		throw "Migration check failed: $migrationOutput"
	}
	Write-Output 'PASS CI3 migration latest check'
}
finally {
	foreach ($name in $databaseEnvironmentNames) {
		[Environment]::SetEnvironmentVariable($name, $previousDatabaseEnvironment[$name], 'Process')
	}
	Pop-Location
}

function Get-HttpStatus([string] $Url) {
	$request = [System.Net.HttpWebRequest]::Create($Url)
	$request.Method = 'GET'
	$request.AllowAutoRedirect = $false
	$request.Timeout = 10000
	try {
		$response = $request.GetResponse()
		try { return [int] $response.StatusCode }
		finally { $response.Close() }
	}
	catch [System.Net.WebException] {
		if ($null -eq $_.Exception.Response) { throw }
		try { return [int] $_.Exception.Response.StatusCode }
		finally { $_.Exception.Response.Close() }
	}
}

function Get-HttpHeaders([string] $Url) {
	function Copy-ResponseHeaders($Response) {
		$headers = @{}
		foreach ($name in $Response.Headers.AllKeys) { $headers[$name] = $Response.Headers[$name] }
		return $headers
	}

	$request = [System.Net.HttpWebRequest]::Create($Url)
	$request.Method = 'GET'
	$request.AllowAutoRedirect = $false
	$request.Timeout = 10000
	try {
		$response = $request.GetResponse()
		try { return (Copy-ResponseHeaders $response) }
		finally { $response.Close() }
	}
	catch [System.Net.WebException] {
		if ($null -eq $_.Exception.Response) { throw }
		$response = $_.Exception.Response
		try { return (Copy-ResponseHeaders $response) }
		finally { $response.Close() }
	}
}

function Assert-BaselineSecurityHeaders($Headers, [string] $Url) {
	foreach ($headerCheck in @(
		@{ Name = 'X-Content-Type-Options'; Value = 'nosniff' },
		@{ Name = 'X-Frame-Options'; Value = 'DENY' },
		@{ Name = 'Referrer-Policy'; Value = 'strict-origin-when-cross-origin' }
	)) {
		$actualHeader = [string] $Headers[$headerCheck.Name]
		if ($actualHeader -ne $headerCheck.Value) {
			throw "Expected $($headerCheck.Name): $($headerCheck.Value) at $Url, got '$actualHeader'."
		}
	}
}

$rootUrl = $BaseUrl.TrimEnd('/') + '/'
foreach ($route in @('', 'masuk', 'daftar', 'kursus')) {
	$url = $rootUrl + $route
	$status = Get-HttpStatus $url
	if ($status -ne 200) { throw "Expected HTTP 200 for $url, got $status" }
	Write-Output "PASS HTTP $status $url"
}

$homePage = Invoke-WebRequest -UseBasicParsing -Uri $rootUrl
Assert-BaselineSecurityHeaders $homePage.Headers $rootUrl
Write-Output 'PASS baseline anti-sniffing, anti-framing, and referrer-policy headers are sent.'
if ($homePage.Content -notmatch 'assets/css/app\.css\?v=[0-9]+') {
	throw 'Application stylesheet does not include a cache-busting version.'
}
Write-Output 'PASS application stylesheet URL is versioned to avoid stale deployed CSS.'
if ($homePage.Content -match 'Latihan dan permainan membantu ide baru' -or $homePage.Content -match 'menyiapkan latihan yang sesuai untuk anak') {
	throw 'Homepage copy claims a planned feature is already available.'
}
if ($homePage.Content -notmatch 'Cocokkan Pasangan tersedia pada materi dengan kartu belajar yang sesuai' -or $homePage.Content -notmatch 'latihan pilihan ganda pada kursus yang menyediakannya' -or $homePage.Content -notmatch 'Pembelian paket belum dibuka') {
	throw 'Homepage does not clearly label planned learning features as upcoming.'
}
Write-Output 'PASS homepage distinguishes course/family practice, eligible matching games, and unavailable package checkout.'

$privateUrl = $rootUrl + 'kelola'
$privateStatus = Get-HttpStatus $privateUrl
if ($privateStatus -notin @(301, 302, 303, 307, 308)) {
	throw "Expected anonymous admin route to redirect, got HTTP $privateStatus ($privateUrl)"
}
Assert-BaselineSecurityHeaders (Get-HttpHeaders $privateUrl) $privateUrl
Write-Output "PASS anonymous route redirects: HTTP $privateStatus $privateUrl"

foreach ($validatorPath in @('admin/valid_optional_age/12','admin/valid_category/1','admin/valid_checkbox/yes')) {
	$validatorUrl = $rootUrl + $validatorPath
	$validatorStatus = Get-HttpStatus $validatorUrl
	if ($validatorStatus -ne 404) { throw "Internal form-validation callback should not be a directly routable HTTP endpoint: $validatorUrl returned $validatorStatus." }
	Write-Output "PASS internal validation callback is not directly routable: $validatorUrl"
}

foreach ($protectedPath in @(
	'admin/index','user_directory/index','kelola/pengguna','audit_log/index','kelola/audit','account_security/index','akun/keamanan','admin/courses','admin/create_course','admin/edit_course/1','admin/categories',
	'admin/create_category','admin/course/1','admin/add_lesson/1','admin/edit_lesson/1/1',
	'admin/flashcards/1/1','admin/submit_course/1','admin/review_course/1','admin/archive_course/1',
	'dashboard/index','family/children','family/add_child','family/update_child_stage/1',
	'catalog/save_lesson_position/smoke-check','catalog/complete_lesson/smoke-check',
	'catalog/save_flashcard_progress/smoke-check/1'
)) {
	$protectedUrl = $rootUrl + $protectedPath
	$protectedStatus = Get-HttpStatus $protectedUrl
	if ($protectedStatus -notin @(301, 302, 303, 307, 308)) { throw "Direct controller route should require an authenticated role: $protectedUrl returned $protectedStatus." }
	Write-Output "PASS direct controller route requires authentication/role: $protectedUrl"
}

foreach ($cliPath in @('cli/migrate','cli/create_admin','cli/check_matching','cli/check_matching/index/1/2/3','cli/check_matching/hold/1/2','cli/check_account_security','cli/check_account_security/index/1','cli/check_account_security/hold/1','cli/check_course_structure','cli/check_course_structure/index/1','cli/check_lesson_revisions','cli/check_lesson_revisions/index/1','cli/check_lesson_media','cli/check_lesson_media/index/1/seed','cli/check_lesson_media/index/1/cleanup','cli/check_lesson_media_schema','cli/check_lesson_media_schema/index','cli/check_legacy_migration_guards','cli/check_legacy_migration_guards/index','cli/check_legacy_migration_guards/early/8/nonempty','cli/check_legacy_migration_guards/early/13/empty','cli/check_media_backup','cli/check_media_backup/index/seed','cli/check_media_backup/index/verify','cli/check_media_backup/index/digest')) {
	$cliUrl = $rootUrl + $cliPath
	$cliStatus = Get-HttpStatus $cliUrl
	if ($cliStatus -ne 404) { throw "CLI-only controller must not be reachable over HTTP: $cliUrl returned $cliStatus." }
	Write-Output "PASS CLI-only controller rejects HTTP access: $cliUrl"
}

foreach ($practiceCliPath in @('cli/check_practice_transactions', 'cli/check_practice_transactions/index/course_start/1/1', 'cli/check_practice_transactions/index/family_start/1/1', 'cli/check_practice_transactions/index/save/1/1')) {
	$practiceCliStatus = Get-HttpStatus ($rootUrl + $practiceCliPath)
	if ($practiceCliStatus -ne 404) { throw 'Practice transaction fault probes must not be reachable over HTTP.' }
}
Write-Output 'PASS practice transaction CLI fault probes reject HTTP access.'

foreach ($matchingCliPath in @('cli/check_matching_transactions', 'cli/check_matching_transactions/index/1/1')) {
	$matchingCliStatus = Get-HttpStatus ($rootUrl + $matchingCliPath)
	if ($matchingCliStatus -ne 404) { throw 'Matching transaction fault probes must not be reachable over HTTP.' }
}
Write-Output 'PASS matching transaction CLI fault probes reject HTTP access.'
foreach ($readingCliPath in @('cli/check_reading_transactions', 'cli/check_reading_transactions/index/1/1')) {
	$readingCliStatus = Get-HttpStatus ($rootUrl + $readingCliPath)
	if ($readingCliStatus -ne 404) { throw "Reading transaction CLI probe must reject HTTP: $readingCliPath" }
}
Write-Output 'PASS reading transaction CLI fault probes reject HTTP access.'

$missingUrl = $rootUrl + 'kursus/smoke-check-no-such-course'
$missingStatus = Get-HttpStatus $missingUrl
if ($missingStatus -ne 404) { throw "Expected HTTP 404 for $missingUrl, got $missingStatus" }
Assert-BaselineSecurityHeaders (Get-HttpHeaders $missingUrl) $missingUrl
Write-Output "PASS unknown course returns HTTP 404: $missingUrl"

$malformedFiltersUrl = $rootUrl + 'kursus?q%5B%5D=tidak-valid&kategori%5B%5D=1&halaman%5B%5D=2'
$malformedFilters = Invoke-WebRequest -UseBasicParsing -Uri $malformedFiltersUrl
if ([int] $malformedFilters.StatusCode -ne 200 -or $malformedFilters.Content -match 'Array to string conversion|Warning:') {
	throw "Malformed catalog query parameters caused an invalid response: $malformedFiltersUrl"
}
Write-Output 'PASS catalog safely ignores array-valued search, category, and pagination parameters.'
$longUnicodeQuery = [Uri]::EscapeDataString(('a' * 100) + [char]::ConvertFromUtf32(0x1F680))
$unicodeFilter = Invoke-WebRequest -UseBasicParsing -Uri ($rootUrl + 'kursus?q=' + $longUnicodeQuery)
if ([int] $unicodeFilter.StatusCode -ne 200 -or $unicodeFilter.Content -match 'Warning:|Array to string conversion') {
	throw 'Long UTF-8 catalog search input caused an invalid response.'
}
Write-Output 'PASS long UTF-8 catalog search input is truncated without breaking the request.'

$staticRoot = $BaseUrl -replace 'index\.php/?$', ''
foreach ($asset in @('assets/css/app.css', 'assets/js/navigation.js', 'assets/js/lesson-progress.js', 'assets/js/matching-game.js', 'assets/js/matching-save.js', 'assets/js/unsaved-form.js', 'assets/vendor/bootstrap/css/bootstrap.min.css', 'assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css')) {
	$assetUrl = $staticRoot.TrimEnd('/') + '/' + $asset
	$assetStatus = Get-HttpStatus $assetUrl
	if ($assetStatus -ne 200) { throw "Expected HTTP 200 for static asset $assetUrl, got $assetStatus" }
	Write-Output "PASS static asset HTTP $assetStatus $assetUrl"
}

$appCssUrl = $staticRoot.TrimEnd('/') + '/assets/css/app.css'
$appCss = (Invoke-WebRequest -UseBasicParsing -Uri $appCssUrl).Content
foreach ($requiredPattern in @('--focus-ring:', '--space-section:', 'select:focus-visible', '.table-responsive', 'prefers-reduced-motion')) {
	if ($appCss -notmatch [regex]::Escape($requiredPattern)) {
		throw "Shared UI/accessibility CSS pattern is missing: $requiredPattern"
	}
}
Write-Output 'PASS shared UI tokens, keyboard focus, responsive table, and reduced-motion CSS are served.'

if ($BaseUrl -match '^https?://localhost/study/') {
	$privatePaths = @(
		'application/','application/config/database.php','application/config/database.local.example.php',
		'application/controllers/Home.php','application/logs/','system/','system/core/CodeIgniter.php',
		'system/libraries/Session/Session.php','docs/KEPUTUSAN_MVP_DAN_ASUMSI.md',
		'scripts/smoke-check.ps1','scripts/tools/phpcs-4.0.4.phar','package.json','readme.rst'
	)
	if (Test-Path -LiteralPath (Join-Path $applicationRoot 'config/database.local.php') -PathType Leaf) {
		$privatePaths += 'application/config/database.local.php'
	}
	foreach ($privatePath in $privatePaths) {
		$privateUrl = $staticRoot.TrimEnd('/') + '/' + $privatePath
		$privateStatus = Get-HttpStatus $privateUrl
		if ($privateStatus -ne 403 -and $privateStatus -ne 404) { throw "Expected private project file to be denied by Apache: HTTP $privateStatus $privateUrl" }
		Write-Output "PASS Apache denies private project path: HTTP $privateStatus $privateUrl"
	}
}
