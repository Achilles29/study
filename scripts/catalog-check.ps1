param(
	[Parameter(Mandatory = $true)][string] $MysqlPath,
	[Parameter(Mandatory = $true)][string] $BaseUrl,
	[Parameter(Mandatory = $false)][string] $ChromePath = '',
	[Parameter(Mandatory = $false)][switch] $CaptureScreenshots
)

$ErrorActionPreference = 'Stop'
$parsedBaseUrl = $null
if (-not [Uri]::TryCreate($BaseUrl, [UriKind]::Absolute, [ref] $parsedBaseUrl) -or
	$parsedBaseUrl.Scheme -ne 'http' -or -not $parsedBaseUrl.IsLoopback -or
	$parsedBaseUrl.UserInfo -or $parsedBaseUrl.Query -or $parsedBaseUrl.Fragment) {
	throw 'Catalog QA requires an HTTP loopback URL without credentials, query or fragment.'
}
if ($CaptureScreenshots -and -not $ChromePath) { throw 'Catalog screenshots require an explicit Chrome path.' }
$root = $BaseUrl.TrimEnd('/') + '/'
$suffix = [Guid]::NewGuid().ToString('N').Substring(0, 16)
$prefix = 'qa-catalog-' + $suffix
$username = 'qa_catalog_' + $suffix
$categorySlugs = @(($prefix + '-a'), ($prefix + '-b'))
if ($categorySlugs.Count -ne 2 -or $categorySlugs[0] -cne ($prefix + '-a') -or $categorySlugs[1] -cne ($prefix + '-b')) { throw 'Invalid exact catalog category identities.' }
$slugs = @(1..17 | ForEach-Object { $prefix + '-' + $_.ToString('00') })
$slugSql = ($slugs | ForEach-Object { "'$_'" }) -join ','
$owned = $false
$baselineDir = Join-Path (Split-Path -Parent $PSScriptRoot) 'docs/visual-baseline/2026-10-15-catalog-populated'
if ($CaptureScreenshots -and (Test-Path -LiteralPath $baselineDir)) { throw 'Refusing to reuse catalog screenshot directory.' }

function Invoke-Db([string] $Sql) {
	$output = & $MysqlPath '--no-defaults' '--host=127.0.0.1' '--protocol=TCP' '--default-character-set=utf8mb4' '-uroot' '-D' 'db_study' '-N' '-B' '-e' $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw 'Catalog fixture database operation failed; no SQL/data are printed.' }
	return ($output -join "`n").Trim()
}
function Sql-Text([string] $Value) {
	$hex = [BitConverter]::ToString([Text.Encoding]::UTF8.GetBytes($Value)).Replace('-', '')
	return "CONVERT(0x$hex USING utf8mb4)"
}
function Get-Catalog([string] $Query = '') {
	$response = Invoke-WebRequest -UseBasicParsing -Uri ($root + 'kursus' + $(if ($Query) { '?' + $Query } else { '' })) -MaximumRedirection 0
	if ($response.StatusCode -ne 200 -or $response.Content -match 'A PHP Error was encountered|Undefined array key|Severity: Warning|Fatal error:') {
		throw 'Catalog returned an error or PHP diagnostic.'
	}
	return $response.Content
}
function Assert-Slugs([string] $Html, [string[]] $Expected) {
	$matches = [regex]::Matches($Html, 'href="[^"]*/kursus/(' + [regex]::Escape($prefix) + '-[0-9]{2})"')
	$actual = @($matches | ForEach-Object { $_.Groups[1].Value })
	if (($actual -join '|') -cne ($Expected -join '|')) { throw 'Catalog course order/set does not match the exact fixture expectation.' }
}
function Assert-Count([string] $Html, [int] $Expected) {
	if ($Html -notmatch ('\b' + $Expected + ' kursus ditemukan')) { throw 'Filtered catalog count is incorrect.' }
}
function Assert-Page([string] $Html, [int] $Expected) {
	if ($Html -notmatch ('aria-current="page">Halaman ' + $Expected + ' dari 2</span>')) { throw 'Visible pagination state differs from the clamped result page.' }
}
function Fixture-Digest {
	return Invoke-Db "SELECT id, SHA2(CONCAT_WS('|',title,slug,summary,status,category_id,author_user_id,content_version,published_at,updated_at),256) FROM st_courses WHERE slug IN ($slugSql) ORDER BY id;"
}
function Invoke-CatalogBrowser([string] $Query, [string] $Name) {
	if (-not $ChromePath) { return }
	$arguments = @((Join-Path $PSScriptRoot 'browser-viewport-check.mjs'), '--base-url', $root, '--chrome', $ChromePath, '--extra-path', 'kursus', '--extra-query', $Query, '--extra-name', $Name, '--only-extra-route')
	if ($CaptureScreenshots) { $arguments += @('--capture-screenshots', '--screenshot-dir', $baselineDir) }
	& node @arguments
	if ($LASTEXITCODE -ne 0) { throw 'Catalog browser regression failed.' }
}

try {
	$beforePublished = [int](Invoke-Db "SELECT COUNT(*) FROM st_courses WHERE status='published';")
	$beforeAudit = Invoke-Db 'SELECT COUNT(*) FROM st_audit_events;'
	$initial = Get-Catalog
	if ($beforePublished -eq 0 -and ($initial -notmatch 'Kursus sedang disiapkan' -or $initial -match 'Belum ada kursus yang cocok')) {
		throw 'Unfiltered empty catalog is not distinguished from an empty search.'
	}
	if ((Invoke-Db "SELECT (SELECT COUNT(*) FROM st_users WHERE username='$username')+(SELECT COUNT(*) FROM st_courses WHERE slug IN ($slugSql))+(SELECT COUNT(*) FROM st_categories WHERE slug IN ('$($categorySlugs[0])','$($categorySlugs[1])')); ") -ne '0') {
		throw 'Refusing to reuse a catalog fixture identity.'
	}
	# Reserve only these exact random identities. A failed transaction has no committed fixture rows.
	$owned = $true
	$statements = New-Object 'System.Collections.Generic.List[string]'
	$statements.Add('START TRANSACTION;')
	$statements.Add("INSERT INTO st_users(username,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES('$username','unusable-synthetic-hash','QA catalog author','adult','adult','suspended',UTC_TIMESTAMP(),UTC_TIMESTAMP()); SET @catalog_author=LAST_INSERT_ID();")
	$statements.Add("INSERT INTO st_categories(name,slug,description,sort_order,status,created_at,updated_at) VALUES('QA Catalog A','$($categorySlugs[0])','Synthetic catalog fixture',0,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP()); SET @catalog_a=LAST_INSERT_ID();")
	$statements.Add("INSERT INTO st_categories(name,slug,description,sort_order,status,created_at,updated_at) VALUES('QA Catalog B','$($categorySlugs[1])','Synthetic catalog fixture',0,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP()); SET @catalog_b=LAST_INSERT_ID();")
	$markup = '<img src=x onerror=alert(1)>'
	$unicode = 'pelangi ' + [char]::ConvertFromUtf32(0x1F308)
	for ($index = 1; $index -le 17; $index++) {
		$status = if ($index -le 14) { 'published' } elseif ($index -eq 15) { 'draft' } elseif ($index -eq 16) { 'review' } else { 'archived' }
		$category = if ($index -eq 14) { '@catalog_b' } else { '@catalog_a' }
		$title = $prefix + ' Kursus ' + $index.ToString('00')
		$summary = 'Ringkasan sintetis ' + $index + ' untuk katalog.'
		if ($index -eq 1) { $title += ' Tanda %_!'; $summary += ' ' + $unicode + ' ' + $markup }
		$statements.Add("INSERT INTO st_courses(category_id,author_user_id,updated_by_user_id,title,slug,summary,learning_objectives,target_group,status,content_version,rights_status,source_note,is_free,published_at,created_at,updated_at) VALUES($category,@catalog_author,@catalog_author,$(Sql-Text $title),'$($slugs[$index-1])',$(Sql-Text $summary),'Synthetic QA only','adult','$status',1,'owned','Created only for catalog regression',1,'2026-01-01 00:00:00','2026-01-01 00:00:00','2026-01-01 00:00:00');")
	}
	$statements.Add('COMMIT; SELECT @catalog_author,@catalog_a,@catalog_b;')
	$ids = (Invoke-Db ($statements -join "`n")) -split '\s+'
	if ($ids.Count -ne 3 -or ($ids | Where-Object { $_ -notmatch '^[1-9][0-9]*$' })) { throw 'Catalog fixture IDs are invalid.' }
	$categoryA = [int]$ids[1]
	$categoryB = [int]$ids[2]
	$beforeDigest = Fixture-Digest
	$query = 'q=' + $prefix + '&kategori=' + $categoryA
	$pageOne = Get-Catalog $query
	Assert-Slugs $pageOne @($slugs[12..1])
	Assert-Count $pageOne 13
	Assert-Page $pageOne 1
	$pageTwo = Get-Catalog ($query + '&halaman=2')
	Assert-Slugs $pageTwo @($slugs[0])
	Assert-Count $pageTwo 13
	Assert-Page $pageTwo 2
	if (-not $pageTwo.Contains('&lt;img src=x onerror=alert(1)&gt;') -or $pageTwo.Contains($markup) -or -not $pageTwo.Contains($unicode)) { throw 'Summary Unicode/markup was not rendered as escaped text.' }
	$links = [regex]::Matches($pageOne, 'href="([^"]+)"') | ForEach-Object { [Net.WebUtility]::HtmlDecode($_.Groups[1].Value) }
	$nextLink = @($links | Where-Object { $_ -match '[?&]halaman=2(?:&|$)' })
	if (-not $nextLink.Count -or $nextLink[0] -notmatch ('[?&]q=' + $prefix + '(?:&|$)') -or $nextLink[0] -notmatch ('[?&]kategori=' + $categoryA + '(?:&|$)')) { throw 'Pagination lost its search/category context.' }
	foreach ($page in @('999999999999', '2', '%202%20')) {
		$pageHtml = Get-Catalog ($query + '&halaman=' + $page)
		Assert-Slugs $pageHtml @($slugs[0])
		Assert-Page $pageHtml 2
	}
	foreach ($page in @('0', '-1', 'invalid', '000000000002')) {
		$pageHtml = Get-Catalog ($query + '&halaman=' + $page)
		Assert-Slugs $pageHtml @($slugs[12..1])
		Assert-Page $pageHtml 1
	}
	$arrayPage = Get-Catalog ($query + '&halaman%5B%5D=2')
	Assert-Slugs $arrayPage @($slugs[12..1])
	Assert-Page $arrayPage 1
	$unknownPage = Get-Catalog ($query + '&qa_unused=do-not-forward&halaman=2')
	Assert-Slugs $unknownPage @($slugs[0])
	if ($unknownPage.Contains('qa_unused') -or $unknownPage.Contains('do-not-forward')) { throw 'Unknown query parameters leaked into catalog pagination.' }
	$queryArrayPage = Get-Catalog ('q%5B%5D=invalid&kategori=' + $categoryA)
	Assert-Slugs $queryArrayPage @($slugs[12..1])
	Assert-Count $queryArrayPage 13
	$categoryArrayPage = Get-Catalog ('q=' + $prefix + '&kategori%5B%5D=' + $categoryA)
	Assert-Slugs $categoryArrayPage @($slugs[13..2])
	Assert-Count $categoryArrayPage 14
	$categoryPage = Get-Catalog ('q=' + $prefix + '&kategori=' + $categoryB)
	Assert-Slugs $categoryPage @($slugs[13])
	Assert-Count $categoryPage 1
	foreach ($literal in @('%_!', $unicode)) {
		$literalPage = Get-Catalog ('q=' + [Uri]::EscapeDataString($literal) + '&kategori=' + $categoryA)
		Assert-Slugs $literalPage @($slugs[0])
		Assert-Count $literalPage 1
	}
	$empty = Get-Catalog ('q=' + $prefix + '-unmatched&kategori=' + $categoryA)
	Assert-Slugs $empty @()
	Assert-Count $empty 0
	if ($empty -notmatch 'Belum ada kursus yang cocok' -or $empty -notmatch 'Hapus filter' -or $empty -match 'Kursus sedang disiapkan') { throw 'Filtered empty-state recovery is missing.' }
	if ($pageOne -notmatch ('href="' + [regex]::Escape($root + 'kursus') + '"[^>]*>Atur ulang')) { throw 'Reset link does not clear all list parameters.' }
	Write-Output 'PASS populated catalog: escaped summary/Unicode, published-only visibility, literal/combined filters, 12+1 stable ordering, page boundaries, context/reset and empty states.'
	Invoke-CatalogBrowser $query 'catalog-populated'
	Invoke-CatalogBrowser ($query + '&halaman=2') 'catalog-last-page'
	Invoke-CatalogBrowser ('q=' + $prefix + '-unmatched&kategori=' + $categoryA) 'catalog-filtered-empty'
	if ((Fixture-Digest) -cne $beforeDigest -or (Invoke-Db 'SELECT COUNT(*) FROM st_audit_events;') -ne $beforeAudit) { throw 'Catalog GET/browser checks unexpectedly changed fixture data or audit count.' }
	Write-Output 'PASS catalog reads do not change fixture rows or write audit events.'
}
finally {
	if ($owned) {
		# Exact randomly reserved slugs plus exact fixture author, never wildcard deletion.
		Invoke-Db "START TRANSACTION; DELETE c FROM st_courses c JOIN st_users u ON u.id=c.author_user_id WHERE c.slug IN ($slugSql) AND u.username='$username'; DELETE FROM st_categories WHERE slug IN ('$($categorySlugs[0])','$($categorySlugs[1])'); DELETE FROM st_users WHERE username='$username'; COMMIT;" | Out-Null
		$remaining = Invoke-Db "SELECT (SELECT COUNT(*) FROM st_courses WHERE slug IN ($slugSql))+(SELECT COUNT(*) FROM st_categories WHERE slug IN ('$($categorySlugs[0])','$($categorySlugs[1])'))+(SELECT COUNT(*) FROM st_users WHERE username='$username');"
		if ($remaining -ne '0') { throw 'Catalog fixture cleanup is incomplete.' }
		Write-Output 'PASS exact synthetic catalog courses, categories and inactive author removed; other application data preserved.'
	}
}
