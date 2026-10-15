param(
	[ValidateRange(1024, 65535)][int] $Port = 8081,
	[string] $PhpPath = 'C:\tools\php81-study\php.exe'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot

if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
	throw "PHP executable not found: $PhpPath"
}

$moduleLines = @(& $PhpPath -m 2>&1)
if ($LASTEXITCODE -ne 0) {
	throw "Could not inspect PHP modules: $($moduleLines -join ' ')"
}
foreach ($requiredModule in @('curl', 'fileinfo', 'mbstring', 'mysqli', 'openssl', 'session')) {
	if ($moduleLines -notcontains $requiredModule) {
		throw "PHP extension is required but unavailable: $requiredModule"
	}
}

$existingListener = @(Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue)
if ($existingListener.Count -gt 0) {
	throw "Port $Port is already in use; choose another local port."
}

$previousEnvironment = [Environment]::GetEnvironmentVariable('CI_ENV', 'Process')
$previousBaseUrl = [Environment]::GetEnvironmentVariable('APP_BASE_URL', 'Process')
Push-Location $projectRoot
try {
	$env:CI_ENV = 'development'
	$env:APP_BASE_URL = "http://127.0.0.1:$Port/"
	Write-Output "Study development server: http://127.0.0.1:$Port/"
	Write-Output 'Stop the server with Ctrl+C. This PHP built-in server is for local development only.'
	& $PhpPath -S "127.0.0.1:$Port" -t $projectRoot (Join-Path $PSScriptRoot 'php-dev-router.php')
}
finally {
	Pop-Location
	if ($null -eq $previousEnvironment) {
		Remove-Item Env:CI_ENV -ErrorAction SilentlyContinue
	}
	else {
		$env:CI_ENV = $previousEnvironment
	}
	if ($null -eq $previousBaseUrl) {
		Remove-Item Env:APP_BASE_URL -ErrorAction SilentlyContinue
	}
	else {
		$env:APP_BASE_URL = $previousBaseUrl
	}
}
