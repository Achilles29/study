param(
	[Parameter(Mandatory = $true)][string] $PhpPath,
	[Parameter(Mandatory = $true)][string] $MysqlPath,
	[Parameter(Mandatory = $true)][string] $BaseUrl
)
$ErrorActionPreference = 'Stop'
$parsed = $null
if ( ! [Uri]::TryCreate($BaseUrl, [UriKind]::Absolute, [ref]$parsed) -or $parsed.Scheme -ne 'http' -or ! $parsed.IsLoopback -or $parsed.UserInfo -or $parsed.Query -or $parsed.Fragment) { throw 'Use an HTTP loopback Study base URL without credentials/query/fragment.' }
$root = $BaseUrl.TrimEnd('/') + '/'
$suffix = [Guid]::NewGuid().ToString('N').Substring(0,12)
$prefix = 'qa_methods_' + $suffix
$password = 'Synthetic-Methods-Check-123!'
$hash = (& $PhpPath -r "echo password_hash('$password', PASSWORD_DEFAULT);").Trim()
if ($LASTEXITCODE -ne 0 -or $hash -notmatch '^\$2[ayb]\$') { throw 'Unable to hash the synthetic fixture password.' }
$names = @('adult','parent','child','admin') | ForEach-Object { $prefix + '_' + $_ }
$quotedNames = ($names | ForEach-Object { "'$_'" }) -join ','
$requestCount = 0

function Db([string]$Sql) {
	$output = & $MysqlPath '-uroot' '-D' 'db_study' '-N' '-B' '-e' $Sql 2>&1
	if ($LASTEXITCODE -ne 0) { throw 'HTTP-method QA database operation failed; inspect the local database without printing credential values.' }
	return ($output -join "`n").Trim()
}
function Request([string]$Route, [string]$Verb, $Web, [string]$Body = '', [string]$Override = 'POST') {
	$request = [Net.HttpWebRequest]::Create($root + $Route)
	$request.Method = $Verb; $request.CookieContainer = $Web.Cookies
	$request.AllowAutoRedirect = $false; $request.Timeout = 15000
	$request.Headers['X-HTTP-Method-Override'] = $Override
	if ($Body) {
		$bytes = [Text.Encoding]::UTF8.GetBytes($Body)
		$request.ContentType = 'application/x-www-form-urlencoded'; $request.ContentLength = $bytes.Length
		$stream = $request.GetRequestStream(); try { $stream.Write($bytes,0,$bytes.Length) } finally { $stream.Close() }
	}
	try { $response = $request.GetResponse() } catch {
		$response = $_.Exception.Response
		if (-not $response -and $_.Exception.InnerException) { $response = $_.Exception.InnerException.Response }
		if (-not $response) { throw }
	}
	try {
		$reader = New-Object IO.StreamReader($response.GetResponseStream())
		try { $bodyText = $reader.ReadToEnd() } finally { $reader.Close() }
		return @{ Status = [int]$response.StatusCode; Content = $bodyText; Allow = $response.Headers['Allow'] }
	} finally { $response.Close() }
}
function Token($Web) {
	$cookie = $Web.Cookies.GetCookies([Uri]$root) | Where-Object { $_.Name -eq 'study_csrf_cookie' } | Select-Object -First 1
	if (-not $cookie -or $cookie.Value -notmatch '^[a-f0-9]+$') { throw 'Expected a local CSRF cookie.' }
	return $cookie.Value
}
function Snapshot {
	$tables = (Db "SELECT table_name FROM information_schema.tables WHERE table_schema='db_study' ORDER BY table_name;") -split "`n"
	$counts = foreach ($table in $tables) {
		if ($table -notmatch '^(st_[a-z_]+|study_migrations)$') { throw 'Unexpected Study table identifier.' }
		$table + ':' + (Db "SELECT COUNT(*) FROM $table;")
	}
	$credentialDigest = Db "SELECT SHA2(GROUP_CONCAT(CONCAT_WS(':',id,username,password_hash,status,account_kind,updated_at,last_login_at) ORDER BY id SEPARATOR '|'),256) FROM st_users WHERE username IN ($quotedNames);"
	return (($counts -join ',') + ':' + $credentialDigest)
}
function Check-Methods($Web, [string[]]$Paths, [bool]$HasPost, [bool]$SharedMessage = $true) {
	$allowed = if ($HasPost) { 'GET, HEAD, POST' } else { 'GET, HEAD' }
	foreach ($route in $Paths) {
		foreach ($verb in @('PUT','PATCH','DELETE','OPTIONS')) {
			$result = Request $route $verb $Web
			if ($result.Status -ne 405 -or $result.Allow -ne $allowed -or ($SharedMessage -and $result.Content -notmatch 'Metode permintaan ini tidak tersedia')) { throw "Incorrect method boundary: $verb $route ($($result.Status), Allow $($result.Allow))." }
			$script:requestCount++
		}
		if (-not $HasPost) {
			$result = Request $route 'POST' $Web ('study_csrf_token=' + (Token $Web) + '&_method=GET') 'GET'
			if ($result.Status -ne 405 -or $result.Allow -ne $allowed) { throw "POST or method override bypassed read-only route $route." }
			$script:requestCount++
		}
	}
}

try {
	$webs = @{}
	foreach ($kind in @('adult','parent','child','admin')) {
		$name = $prefix + '_' + $kind
		$accountKind = if ($kind -eq 'admin') { 'adult' } else { $kind }
		$stage = if ($kind -eq 'child') { 'primary' } else { 'adult' }
		$userId = Db "INSERT INTO st_users (username,email,password_hash,display_name,account_kind,learning_stage,status,created_at,updated_at) VALUES ('$name','$name@example.test','$hash','QA methods','$accountKind','$stage','active',UTC_TIMESTAMP(),UTC_TIMESTAMP()); SELECT LAST_INSERT_ID();"
		if ($userId -notmatch '^[1-9][0-9]*$') { throw 'Invalid fixture account ID.' }
		if ($kind -eq 'admin') { Db "INSERT INTO st_user_roles (user_id,role_id,granted_by_user_id,created_at) SELECT $userId,id,$userId,UTC_TIMESTAMP() FROM st_roles WHERE role_code='system_admin';" | Out-Null }
		$web = New-Object Microsoft.PowerShell.Commands.WebRequestSession
		Request 'masuk' 'GET' $web | Out-Null
		$login = Request 'masuk' 'POST' $web ('identity=' + $name + '&password=' + [Net.WebUtility]::UrlEncode($password) + '&study_csrf_token=' + (Token $web))
		if ($login.Status -notin @(302,303)) { throw 'Synthetic account login failed.' }
		if ((Request 'beranda' 'GET' $web).Status -ne 200) { throw 'Synthetic login did not reach its own dashboard.' }
		$webs[$kind] = $web
	}
	$anonymous = New-Object Microsoft.PowerShell.Commands.WebRequestSession
	foreach ($route in @('','masuk','daftar','kursus')) {
		if ((Request $route 'GET' $anonymous).Status -ne 200) { throw "Public GET failed: $route" }
		$head = Request $route 'HEAD' $anonymous
		if ($head.Status -ne 200 -or $head.Content -ne '') { throw "Public HEAD failed: $route" }
	}
	$before = Snapshot
	Check-Methods $anonymous @('','home/index','kursus','catalog/index','kursus/qa-method-missing','catalog/course/qa-method-missing','materi/qa-method-missing','catalog/lesson/qa-method-missing') $false
	Check-Methods $anonymous @('masuk','auth/sign_in','daftar','auth/sign_up') $true
	Check-Methods $webs.adult @('beranda','dashboard/index','latihan','practice/index') $false
	Check-Methods $webs.adult @('latihan/kursus/1','practice/course/1','latihan/percobaan/1','practice/attempt/1') $true
	Check-Methods $webs.parent @('keluarga/anak','family/children') $false
	Check-Methods $webs.parent @('keluarga/anak/tambah','family/add_child','keluarga/latihan','parent_practice/index','keluarga/latihan/1','parent_practice/edit/1') $true
	Check-Methods $webs.child @('latihan/keluarga','practice/assignments') $false
	Check-Methods $webs.child @('latihan/keluarga/1','practice/assigned/1') $true
	Check-Methods $webs.admin @('kelola','admin/index','kelola/kursus','admin/courses','kelola/kategori','admin/categories','kelola/kursus/1','admin/course/1') $false
	Check-Methods $webs.admin @('kelola/kursus/tambah','admin/create_course','kelola/kursus/1/edit','admin/edit_course/1','kelola/kategori/tambah','admin/create_category','kelola/kursus/1/pelajaran/tambah','admin/add_lesson/1','kelola/kursus/1/pelajaran/1/edit','admin/edit_lesson/1/1','kelola/kursus/1/pelajaran/1/flashcard','admin/flashcards/1/1','kelola/kursus/1/soal','questions/index/1') $true
	foreach ($route in @('masuk','daftar')) {
		$invalid = Request $route 'POST' $anonymous ('study_csrf_token=' + (Token $anonymous) + '&identity=&password=&account_kind=&username=&email=&display_name=&password_confirm=&agree_terms=')
		if ($invalid.Status -ne 200 -or $invalid.Content -notmatch 'aria-invalid="true"') { throw "Valid-token POST did not reach ordinary form validation: $route" }
	}
	Check-Methods $webs.admin @('kelola/kursus/1/susunan','course_structure/index/1') $true
	Check-Methods $anonymous @('materi/qa-method-missing/gambar','lesson_media/show/qa-method-missing') $false
	Check-Methods $webs.admin @('kelola/kursus/1/pelajaran/1/gambar/pratinjau','lesson_media/preview/1/1') $false
	Check-Methods $webs.admin @('kelola/kursus/1/pelajaran/1/gambar','lesson_media/edit/1/1') $true
	Check-Methods $anonymous @('permainan/pasangan/qa-method-missing','games/matching/qa-method-missing') $false $false
	Check-Methods $webs.adult @('akun/keamanan','account_security/index','permainan/tersimpan/pelajaran/qa-method-missing','matching/lesson/qa-method-missing','permainan/tersimpan/1','matching/run/1','permainan/tersimpan/1/berhenti','matching/stop/1') $true $false
	Check-Methods $webs.adult @('permainan/riwayat','matching/index') $false $false
	Check-Methods $webs.parent @('keluarga/anak/1/latihan','family/practice/1','keluarga/anak/1/latihan/1','family/practice_result/1/1','keluarga/anak/1/permainan','family/matching/1','keluarga/anak/1/permainan/1','family/matching_result/1/1','keluarga/latihan/1/hasil','parent_practice/result/1') $false $false
	Check-Methods $webs.parent @('keluarga/latihan/1/salin','parent_practice/copy/1') $true $false
	Check-Methods $webs.admin @('kelola/pengguna','user_directory/index') $true $false
	Check-Methods $webs.admin @('kelola/audit','audit_log/index') $false $false
	if ((Request 'kursus' 'POST' $anonymous 'study_csrf_token=invalid').Status -ne 403) { throw 'Method guard replaced or bypassed CSRF protection.' }
	if ((Request 'kelola' 'DELETE' $webs.adult).Status -ne 403) { throw 'Role check no longer precedes method guard.' }
	if ((Request 'keluarga/anak' 'DELETE' $webs.adult).Status -ne 403) { throw 'Account-kind check no longer precedes method guard.' }
	if ((Request 'latihan' 'DELETE' $anonymous).Status -notin @(302,303,307)) { throw 'Anonymous route no longer requires login before method check.' }
	if ((Snapshot) -ne $before) { throw 'Denied methods or invalid form validation changed database counts or fixture credentials.' }
	Write-Output "PASS $requestCount HTTP method rejections on aliases/direct controllers; Allow headers, override resistance, GET/HEAD/form POST, auth/role/CSRF precedence, database counts and fixture credentials unchanged."
} finally {
	Db "START TRANSACTION; DELETE a FROM st_audit_events a JOIN st_users u ON u.id=a.actor_user_id WHERE u.username IN ($quotedNames); DELETE ur FROM st_user_roles ur JOIN st_users u ON u.id=ur.user_id WHERE u.username IN ($quotedNames); DELETE FROM st_users WHERE username IN ($quotedNames); COMMIT;" | Out-Null
	Write-Output 'PASS exact method-check fixture accounts, role grants and login audit rows cleaned.'
}
