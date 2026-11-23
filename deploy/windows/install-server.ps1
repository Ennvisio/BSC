<#
.SYNOPSIS
    Installs, or repairs, the NaviSuite vessel server on this Windows PC.

.DESCRIPTION
    Sets up the ship's master PC to serve NaviSuite to the crew PCs on the
    LAN, with no internet needed:

      NaviSuite-MySQL      MySQL, listening on 127.0.0.1 only
      NaviSuite-Web        Apache with PHP, HTTPS on 443 (HTTP 80 redirects)
      NaviSuite-Queue      Laravel queue worker
      NaviSuite-Scheduler  Laravel scheduler
      NaviSuite-Discovery  answers desktop clients looking for the server
      NaviSuite-Sync       sends and fetches changes when the internet is up

    All of them start with Windows and restart themselves after a failure.
    A daily task (02:30) backs up the database and renews the certificate.

    Run it from an extracted NaviSuite release, in an elevated Windows
    PowerShell. It can be run again at any time: every step checks what is
    already in place. An existing .env, database, certificate and uploaded
    files are always kept. To install a newer release, use
    update-server.ps1 instead.

    See docs\checkpoint-3-windows-vessel-server.md for the full procedure.

.PARAMETER VesselId
    This vessel's id in the cloud database (vessels.id).

.PARAMETER RuntimeSource
    A folder holding the extracted runtimes: apache (or Apache24), php,
    mysql and winsw\WinSW-x64.exe, and cacert.pem (the Mozilla CA bundle
    from https://curl.se/ca/cacert.pem, needed to sync with the cloud). Not
    needed if they are already in <Root>\runtime.

.PARAMETER SyncCloudUrl
    Optional. The cloud's https:// address, for example
    https://navisuite.example.com. Written to SYNC_CLOUD_URL; NaviSuite-Sync
    stays idle until it is set (with SYNC_NODE_ID and SYNC_NODE_TOKEN).

.PARAMETER ImportSqlDump
    Optional. A mysqldump file to load into the new, empty database before
    the migrations run.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\deploy\windows\install-server.ps1 -VesselId 12 -RuntimeSource D:\navisuite-runtime
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][ValidateRange(1, 2147483647)][int]$VesselId,
    [string]$Root = 'C:\NaviSuite',
    [string]$RuntimeSource,
    [string]$ServerName = $env:COMPUTERNAME.ToLower(),
    [string[]]$ExtraNames = @(),
    [string]$SyncNodeId,
    [string]$SyncNodeToken,
    [string]$SyncCloudUrl,
    [string]$SupportAccounts,
    [string]$ImportSqlDump,
    [string[]]$FirewallRemoteAddress = @('LocalSubnet'),
    [switch]$IncludePublicProfile,
    [switch]$SkipFirewall,
    [switch]$UseLocalSystem,
    [ValidateRange(128, 65536)][int]$InnoDbBufferPoolMB = 512,
    [switch]$SkipPortsImport
)

. "$PSScriptRoot\lib\NaviSuite.ps1"

# ==================================================================== 1. checks
Write-NsStep 'Checking this PC'
Assert-NsAdministrator
Assert-NsRoot $Root
if ($PSVersionTable.PSVersion -lt [version]'5.1') { throw 'Windows PowerShell 5.1 or later is required.' }
if ([Environment]::OSVersion.Version -lt [version]'10.0') { throw 'Windows 10, Windows 11 or Windows Server 2016 or later is required.' }
if (-not [Environment]::Is64BitOperatingSystem) { throw 'A 64-bit Windows is required.' }
if ($ServerName -notmatch '^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$') { throw "Invalid server name '$ServerName'. Use letters, digits and hyphens." }
if ($ImportSqlDump -and -not (Test-Path -LiteralPath $ImportSqlDump)) { throw "SQL dump not found: $ImportSqlDump" }

$paths = Get-NsPaths $Root
$source = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path.TrimEnd('\')
foreach ($f in @('artisan', '.env.vessel.example', 'vendor\autoload.php')) {
    if (-not (Test-Path -LiteralPath (Join-Path $source $f))) { throw "This does not look like a NaviSuite release: $source\$f is missing. Build one with deploy/build-release.sh." }
}
$release = Get-NsRelease $source
Write-NsOk "Release $release from $source"

# Ports 80, 443 and 3306 must be free, or already ours.
$ours = @($paths.HttpdExe, $paths.MySqldExe)
foreach ($port in @(80, 443, 3306)) {
    foreach ($c in @(Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue)) {
        if (-not $c) { continue }
        $proc = Get-Process -Id $c.OwningProcess -ErrorAction SilentlyContinue
        $path = if ($proc) { try { $proc.Path } catch { $null } } else { $null }
        if ($path -and ($ours -contains $path)) { continue }
        $who = if ($c.OwningProcess -eq 4) { 'Windows HTTP.sys (IIS or another web service)' } elseif ($proc) { "$($proc.ProcessName) (pid $($c.OwningProcess))" } else { "pid $($c.OwningProcess)" }
        throw "Port $port is already used by $who. Stop or uninstall it, then run this script again."
    }
}
foreach ($u in @(Get-NetUDPEndpoint -LocalPort $script:NsDiscoveryPort -ErrorAction SilentlyContinue)) {
    if (-not $u) { continue }
    $proc = Get-Process -Id $u.OwningProcess -ErrorAction SilentlyContinue
    $path = if ($proc) { try { $proc.Path } catch { $null } } else { $null }
    if ($path -ne $paths.PhpExe) { throw "UDP port $($script:NsDiscoveryPort) (discovery) is already used by $(if ($proc) { $proc.ProcessName } else { "pid $($u.OwningProcess)" })." }
}
Write-NsOk "Ports 80, 443, 3306 and UDP $($script:NsDiscoveryPort) are free."

# ==================================================================== 2. folders and runtimes
Write-NsStep "Folders under $Root"
New-NsFolders $paths
Set-NsAcl $paths -Part Base
Write-NsOk 'Created. Only administrators and SYSTEM have access; services are added below.'

Write-NsStep 'Runtimes (Apache, PHP, MySQL, WinSW)'
if ($RuntimeSource) {
    $map = @(
        @{ Name = 'apache'; Candidates = @('apache', 'Apache24') },
        @{ Name = 'php'; Candidates = @('php') },
        @{ Name = 'mysql'; Candidates = @('mysql') },
        @{ Name = 'winsw'; Candidates = @('winsw') }
    )
    foreach ($m in $map) {
        $dest = Join-Path $paths.Runtime $m.Name
        if ((Test-Path -LiteralPath $dest) -and (Get-ChildItem -LiteralPath $dest -Force | Select-Object -First 1)) { Write-NsInfo "$($m.Name): already in place, kept"; continue }
        $src = $m.Candidates | ForEach-Object { Join-Path $RuntimeSource $_ } | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
        if (-not $src) { throw "$($m.Name) not found in $RuntimeSource (looked for $($m.Candidates -join ', '))." }
        Copy-NsTree $src $dest
        Write-NsOk "$($m.Name): copied from $src"
    }
    # The CA bundle is small and is replaced by newer copies, so it is copied every time.
    $caSource = Join-Path $RuntimeSource 'cacert.pem'
    if (Test-Path -LiteralPath $caSource) {
        if (-not (Test-Path -LiteralPath $paths.PhpIniDir)) { New-Item -ItemType Directory -Path $paths.PhpIniDir | Out-Null }
        Copy-Item -LiteralPath $caSource -Destination $paths.CaBundle -Force
        Write-NsOk "cacert.pem: copied from $caSource"
    } elseif (-not (Test-Path -LiteralPath $paths.CaBundle)) {
        Write-NsWarn "cacert.pem not found in $RuntimeSource. Needed only to sync with the cloud."
    }
}
$winSwExe = Get-NsWinSwExe $paths
$required = @($paths.HttpdExe, $paths.OpenSslExe, (Join-Path $paths.Php 'php8apache2_4.dll'), $paths.PhpExe, $paths.MySqldExe, $paths.MySqlExe, $paths.MySqlDumpExe)
$absent = @($required | Where-Object { -not (Test-Path -LiteralPath $_) })
if (-not $winSwExe) { $absent += (Join-Path $paths.WinSw 'WinSW-x64.exe') }
if ($absent.Count) { throw "Runtime files missing:`n  $($absent -join "`n  ")`nPass -RuntimeSource, see the documentation for the downloads." }

# 0xC0000135 (-1073741515): a DLL is missing, nearly always the Visual C++ runtime.
$vcHint = 'Install the Microsoft Visual C++ 2015-2022 Redistributable (x64), then run this script again.'
$php = Invoke-NsCapture $paths.PhpExe @('-n', '-r', 'echo(PHP_VERSION.chr(32).PHP_ZTS.chr(32).PHP_INT_SIZE);')
if ($php.Code -eq -1073741515) { throw "PHP cannot start. $vcHint" }
$parts = ($php.Output -join '').Split(' ')
if ($parts.Count -lt 3 -or [version]$parts[0] -lt [version]'8.2' -or $parts[1] -ne '1' -or $parts[2] -ne '8') {
    throw "PHP must be 8.2 or later, thread-safe (TS), x64. Found: $($php.Output -join ' ')"
}
Write-NsOk "PHP $($parts[0]) (thread-safe, x64)"
$httpd = Invoke-NsCapture $paths.HttpdExe @('-v')
if ($httpd.Code -eq -1073741515) { throw "Apache cannot start. $vcHint" }
$httpdVersion = $httpd.Output | Select-Object -First 1
if ("$httpdVersion" -notmatch 'Apache/2\.4\.') { throw "Apache 2.4 is required. Found: $httpdVersion" }
Write-NsOk "$httpdVersion"
$mysqld = Invoke-NsCapture $paths.MySqldExe @('--version')
if ($mysqld.Code -eq -1073741515) { throw "MySQL cannot start. $vcHint" }
$mysqlVersion = $mysqld.Output | Select-Object -First 1
if ("$mysqlVersion" -notmatch 'Ver (\d+)\.(\d+)') { throw "Could not read the MySQL version: $mysqlVersion" }
if ([int]$Matches[1] -lt 8) { throw "MySQL 8.0 or later is required. Found: $mysqlVersion" }
Write-NsOk "$mysqlVersion"

# ==================================================================== 3. application files
Write-NsStep "Application files in $($paths.App)"
if ($source -ieq $paths.App) {
    Write-NsInfo 'Running from the installed copy.'
} elseif (Test-Path -LiteralPath (Join-Path $paths.App 'artisan')) {
    Write-NsWarn "NaviSuite is already installed here ($(Get-NsRelease $paths.App)). Its files are kept; use update-server.ps1 to install release $release."
} else {
    Copy-NsTree $source $paths.App -ExcludeFiles @('.env')
    Write-NsOk "Copied release $release."
}
Set-NsDataJunctions $paths
Write-NsOk 'storage and the upload folders point to data\.'

# ==================================================================== 4. settings and secrets
Write-NsStep 'Settings and secrets'
$settings = [ordered]@{
    VesselId = $VesselId
    ServerName = $ServerName
    ExtraNames = @($ExtraNames)
    InnoDbBufferPoolMB = $InnoDbBufferPoolMB
    UseLocalSystem = [bool]$UseLocalSystem
    FirewallRemoteAddress = @($FirewallRemoteAddress)
    IncludePublicProfile = [bool]$IncludePublicProfile
    SkipFirewall = [bool]$SkipFirewall
    InstalledRelease = $release
    InstalledAt = (Get-Date).ToString('s')
}
$settingsFile = Get-NsSettingsPath $paths
if (Test-Path -LiteralPath $settingsFile) {
    $old = Read-NsSettings $paths
    if ([int]$old.VesselId -ne $VesselId) { throw "This PC is already set up for vessel $($old.VesselId), not $VesselId. A server's vessel never changes." }
    $settings.InstalledAt = $old.InstalledAt
}
Save-NsSettings $paths ([pscustomobject]$settings)

if (-not (Test-NsRootPassword $paths)) {
    if (Test-Path -LiteralPath (Join-Path $paths.MySqlData 'mysql')) {
        throw "MySQL is already initialised in $($paths.MySqlData), but its root password file $($paths.RootSecret) is missing."
    }
    $rootPassword = New-NsSecret 32
    Set-NsRootPassword $paths $rootPassword
    Write-NsOk "MySQL root password created (kept encrypted with DPAPI in $($paths.RootSecret), administrators only)."
} else {
    $rootPassword = $null
    Get-NsRootPassword $paths | Out-Null   # converts a plaintext mysql-root.cnf from an older release
    Write-NsInfo 'MySQL root password: kept.'
}

$envIsNew = -not (Test-Path -LiteralPath $paths.Env)
if ($envIsNew) { Copy-Item -LiteralPath (Join-Path $paths.App '.env.vessel.example') -Destination $paths.Env }
$envNow = Read-NsDotEnv $paths.Env
if (-not $envIsNew -and $envNow['APP_VESSEL_ID'] -and $envNow['APP_VESSEL_ID'] -ne "$VesselId") {
    throw ".env is set up for vessel $($envNow['APP_VESSEL_ID']), not $VesselId."
}
$envValues = @{
    APP_ENV = 'production'
    APP_DEBUG = 'false'
    APP_MODE = 'vessel'
    APP_VESSEL_ID = "$VesselId"
    APP_RELEASE = $release
    DB_CONNECTION = 'vessel'
    QUEUE_CONNECTION = 'database'
    APP_TLS_CA_FILE = $paths.CaCert
}
function Test-Blank($v) { return (-not $v) -or ($v -eq '') }
# Checkpoint 7: the credentials are kept encrypted with DPAPI in
# secrets\app (read by the application through SecretStore), not in .env.
# A value still in .env from an older release is moved there. .env keeps
# an empty line for each, so the old place is visibly unused.
$envValues.NAVISUITE_SECRETS_PATH = $paths.AppSecrets
$envValues.NAVISUITE_SECRETS_PROTECTOR = 'dpapi'
$secretNames = [ordered]@{ APP_KEY = 'app_key'; VESSEL_DB_PASSWORD = 'db_password'; APP_HEALTH_TOKEN = 'health_token'; SYNC_NODE_TOKEN = 'sync_node_token' }
$secretValues = @{}
foreach ($key in $secretNames.Keys) {
    $name = $secretNames[$key]
    if (-not (Test-Blank $envNow[$key])) { $secretValues[$name] = $envNow[$key] }
    elseif ($null -ne (Get-NsSecret $paths $name)) { $secretValues[$name] = $null }   # already stored
}
if (-not $secretValues.ContainsKey('app_key')) { $secretValues.app_key = New-NsAppKey }
if (-not $secretValues.ContainsKey('db_password')) { $secretValues.db_password = New-NsSecret 32 }
if (-not $secretValues.ContainsKey('health_token')) { $secretValues.health_token = New-NsSecret 40 }
if ($PSBoundParameters.ContainsKey('SyncNodeToken')) { $secretValues.sync_node_token = $SyncNodeToken }
foreach ($name in @($secretValues.Keys)) {
    if ($null -ne $secretValues[$name] -and $secretValues[$name] -ne '') { Set-NsSecret $paths $name $secretValues[$name] }
}
foreach ($key in $secretNames.Keys) { $envValues[$key] = '' }
if ($envIsNew -or (Test-Blank $envNow['APP_URL'])) { $envValues.APP_URL = "https://$ServerName" }
if ($PSBoundParameters.ContainsKey('SyncNodeId')) { $envValues.SYNC_NODE_ID = $SyncNodeId }
if ($PSBoundParameters.ContainsKey('SyncCloudUrl')) {
    if ($SyncCloudUrl -and $SyncCloudUrl -notmatch '^https://[^\s/]+') { throw "-SyncCloudUrl must start with https://, for example https://navisuite.example.com" }
    $envValues.SYNC_CLOUD_URL = $SyncCloudUrl.TrimEnd('/')
}
if ($PSBoundParameters.ContainsKey('SupportAccounts')) { $envValues.APP_SUPPORT_ACCOUNTS = $SupportAccounts }
Set-NsDotEnv $paths.Env $envValues
Set-NsAcl $paths -Part Base
$envNow = Read-NsDotEnv $paths.Env
Write-NsOk ".env $(if ($envIsNew) { 'created' } else { 'updated, existing values kept' })."
if ((Test-Blank $envNow['SYNC_NODE_ID']) -or ($null -eq (Get-NsSecret $paths 'sync_node_token'))) {
    Write-NsWarn 'SYNC_NODE_ID and SYNC_NODE_TOKEN are empty. Register this vessel on the cloud (php artisan sync:register-node vessel --vessel=<id>) and run this script again with -SyncNodeId and -SyncNodeToken.'
}
if (Test-Blank $envNow['SYNC_CLOUD_URL']) {
    Write-NsWarn 'SYNC_CLOUD_URL is empty, so this vessel does not sync yet. Run this script again with -SyncCloudUrl https://<cloud address> when the vessel is registered.'
} elseif (-not (Test-Path -LiteralPath $paths.CaBundle) -and (Test-Blank $envNow['SYNC_CA_BUNDLE'])) {
    Write-NsWarn "No CA bundle at $($paths.CaBundle): sync cannot check the cloud's certificate and will fail. Put cacert.pem in the runtime folder and run this script again."
}

# ==================================================================== 5. configuration files
Write-NsStep 'Configuration files'
Write-NsConfigs $paths ([pscustomobject]$settings)
Use-NsPhpPath $paths
$modules = (Invoke-NsCapture $paths.PhpExe @('-m')).Output
$missingExt = @($script:NsPhpExtensions | Where-Object { $modules -notcontains $_ })
if ($missingExt.Count) { throw "PHP extensions not loaded with $($paths.PhpIniDir)\php.ini: $($missingExt -join ', ')" }
Write-NsOk 'httpd.conf, php.ini, my.ini, service files and artisan.cmd written. PHP extensions load.'
# The application must be able to open the credentials stored above.
if ((Invoke-NsArtisan $paths @('navisuite:secrets', 'check') -AllowFailure -Quiet) -ne 0) {
    throw "PHP cannot open the credentials in $($paths.AppSecrets). Run '$Root\artisan.cmd navisuite:secrets check' to see why (FFI must be enabled in php.ini)."
}
Write-NsOk "Credentials are stored encrypted (DPAPI) in $($paths.AppSecrets); .env holds none."

# ==================================================================== 6. certificate
& (Join-Path $PSScriptRoot 'new-certificate.ps1') -Root $Root -ServerName $ServerName -ExtraNames $ExtraNames

# ==================================================================== 7. MySQL
Write-NsStep 'MySQL'
if (-not (Test-Path -LiteralPath (Join-Path $paths.MySqlData 'mysql'))) {
    if (Get-ChildItem -LiteralPath $paths.MySqlData -Force | Select-Object -First 1) {
        throw "$($paths.MySqlData) is not empty but holds no MySQL data directory. Move its contents away and run again."
    }
    if (-not $rootPassword) { throw 'MySQL needs initialising but the root password file already exists. Remove it only if no database was ever created.' }
    # The root password is set while the data directory is created, so root
    # never exists without one. The statement file is deleted straight away.
    $initFile = Join-Path $paths.Secrets 'init.sql'
    Write-NsFile $initFile "ALTER USER 'root'@'localhost' IDENTIFIED BY '$rootPassword';`r`n"
    try {
        Invoke-NsNative $paths.MySqldExe @("--defaults-file=$($paths.MyIni)", '--initialize-insecure', "--init-file=$initFile", '--console') -Quiet | Out-Null
    } finally { Remove-Item -LiteralPath $initFile -Force -ErrorAction SilentlyContinue }
    Write-NsOk "Data directory created in $($paths.MySqlData)."
} else {
    Write-NsInfo 'Data directory: kept.'
}

if (-not (Test-NsService 'NaviSuite-MySQL')) {
    Invoke-NsNative $paths.MySqldExe @('--install', 'NaviSuite-MySQL', "--defaults-file=$($paths.MyIni)") -Quiet | Out-Null
    Write-NsOk 'Service NaviSuite-MySQL installed.'
}
Invoke-NsNative 'sc.exe' @('description', 'NaviSuite-MySQL', 'NaviSuite database (MySQL, this PC only).') -Quiet | Out-Null
Set-NsServiceAccount 'NaviSuite-MySQL' -UseLocalSystem:$UseLocalSystem
Set-NsServiceRecovery 'NaviSuite-MySQL'
Set-NsAcl $paths -Part MySql -UseLocalSystem:$UseLocalSystem
Start-NsServices @('NaviSuite-MySQL')

$ready = $false
for ($i = 0; $i -lt 60 -and -not $ready; $i++) {
    try { Invoke-NsMySqlRoot $paths 'SELECT 1' | Out-Null; $ready = $true } catch { Start-Sleep -Seconds 1 }
}
if (-not $ready) { throw "MySQL does not answer. See $($paths.Logs)\mysql\mysqld.err." }
$bind = (Invoke-NsMySqlRoot $paths 'SELECT @@bind_address') | Select-Object -First 1
if ("$bind" -ne '127.0.0.1') { throw "MySQL is listening on '$bind' instead of 127.0.0.1. Check $($paths.MyIni)." }
Write-NsOk 'MySQL is running and listens on 127.0.0.1 only.'

$dbName = if ($envNow['VESSEL_DB_DATABASE']) { $envNow['VESSEL_DB_DATABASE'] } else { 'navisuite' }
$dbUser = if ($envNow['VESSEL_DB_USERNAME']) { $envNow['VESSEL_DB_USERNAME'] } else { 'navisuite' }
foreach ($v in @($dbName, $dbUser)) { if ($v -notmatch '^[A-Za-z0-9_]+$') { throw "Invalid database or user name '$v' in .env." } }
$sqlFile = Join-Path $paths.Secrets 'create-database.sql'
Write-NsFile $sqlFile (Expand-NsTemplate 'create-database.sql' @{ DB_NAME = $dbName; DB_USER = $dbUser; DB_PASSWORD = (Get-NsSecret $paths 'db_password') })
try {
    Use-NsRootCnf $paths { param($cnf) Invoke-NsWithInput $paths.MySqlExe @("--defaults-extra-file=$cnf") $sqlFile }
} finally { Remove-Item -LiteralPath $sqlFile -Force -ErrorAction SilentlyContinue }
Write-NsOk "Database '$dbName' and user '$dbUser' ready (rights on '$dbName' only)."

if ($ImportSqlDump) {
    Write-NsStep "Loading $ImportSqlDump"
    $tables = [int]((Invoke-NsMySqlRoot $paths "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$dbName'") | Select-Object -First 1)
    if ($tables -gt 0) { throw "Database '$dbName' already has $tables tables. A dump is only loaded into an empty database." }
    Write-NsWarn 'A full cloud dump contains every vessel''s data. Use a dump of this vessel only where possible.'
    $dumpPath = (Resolve-Path $ImportSqlDump).Path
    Use-NsRootCnf $paths { param($cnf) Invoke-NsWithInput $paths.MySqlExe @("--defaults-extra-file=$cnf", '--default-character-set=utf8mb4', $dbName) $dumpPath }
    Write-NsOk 'Dump loaded.'
}

# ==================================================================== 8. Laravel
Write-NsStep 'Application database and caches'
Invoke-NsArtisan $paths @('config:clear') -Quiet | Out-Null
Invoke-NsArtisan $paths @('migrate', '--force') | Out-Null
Invoke-NsArtisan $paths @('sync:triggers', 'install') | Out-Null
$ports = [int]((Invoke-NsMySqlRoot $paths "SELECT COUNT(*) FROM ``$dbName``.ports") | Select-Object -First 1)
$unlocode = Join-Path $paths.Storage 'app\unlocode'
if ($ports -eq 0 -and -not $SkipPortsImport -and (Test-Path -LiteralPath $unlocode)) {
    Write-NsInfo 'Loading the UN/LOCODE port list (one time, about a minute).'
    Invoke-NsArtisan $paths @('ports:import') -Quiet | Out-Null   # reads storage\app\unlocode
}
Invoke-NsArtisan $paths @('config:cache') -Quiet | Out-Null
Invoke-NsArtisan $paths @('view:cache') -Quiet | Out-Null
Write-NsOk 'Migrated, change capture installed, configuration and views cached.'
$statusCode = Invoke-NsArtisan $paths @('navisuite:status') -AllowFailure
if ($statusCode -ne 0) { Write-NsWarn 'navisuite:status reports problems (above). The server is installed, but fix these before the crew uses it.' }

# ==================================================================== 9. Apache
Write-NsStep 'Apache (NaviSuite-Web)'
Invoke-NsNative $paths.HttpdExe @('-t', '-f', $paths.HttpdConf) -Quiet | Out-Null
if (-not (Test-NsService 'NaviSuite-Web')) {
    Invoke-NsNative $paths.HttpdExe @('-k', 'install', '-n', 'NaviSuite-Web', '-f', $paths.HttpdConf) -Quiet | Out-Null
    Write-NsOk 'Service NaviSuite-Web installed.'
}
Invoke-NsNative 'sc.exe' @('description', 'NaviSuite-Web', 'NaviSuite web server (Apache with PHP) for the crew PCs.') -Quiet | Out-Null
Set-NsWebServiceEnvironment $paths
Set-NsServiceAccount 'NaviSuite-Web' -UseLocalSystem:$UseLocalSystem
Set-NsServiceRecovery 'NaviSuite-Web' -Delayed

# ==================================================================== 10. background services
Write-NsStep 'Queue worker, scheduler, discovery and sync'
Install-NsBackgroundServices $paths ([pscustomobject]$settings)
Write-NsOk "$($script:NsWinSwServices -join ', ') ready."
Set-NsAcl $paths -Part App -UseLocalSystem:$UseLocalSystem
Write-NsOk "Folder rights set$(if ($UseLocalSystem) { ' (services run as LocalSystem)' } else { ': each service runs as its own virtual account' })."

# ==================================================================== 11. firewall and maintenance
if ($SkipFirewall) {
    Write-NsWarn 'Firewall not configured (-SkipFirewall). Run configure-firewall.ps1 before crew PCs connect.'
} else {
    & (Join-Path $PSScriptRoot 'configure-firewall.ps1') -Root $Root -RemoteAddress $FirewallRemoteAddress -IncludePublicProfile:$IncludePublicProfile
}

Write-NsStep 'Daily maintenance task'
$taskName = 'NaviSuite Maintenance'
Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
$script = Join-Path $paths.App 'deploy\windows\maintenance.ps1'
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -NonInteractive -ExecutionPolicy Bypass -File `"$script`" -Root `"$Root`""
$trigger = New-ScheduledTaskTrigger -Daily -At '02:30'
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
$taskSettings = New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Hours 2) -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $taskSettings `
    -Description 'NaviSuite: database backup to data\backups and HTTPS certificate renewal.' | Out-Null
Write-NsOk "'$taskName' runs every day at 02:30 (or at the next start if the PC was off)."
Register-NsStartupTask $paths
Write-NsOk "'NaviSuite Startup Check' runs 4 minutes after every Windows start (log: $($paths.Logs)\startup)."

# ==================================================================== 12. start and check
Write-NsStep 'Starting the services'
Start-NsServices $script:NsAppServices
& (Join-Path $PSScriptRoot 'check-health.ps1') -Root $Root -WaitSeconds 150
$healthCode = $LASTEXITCODE

$lan = @(Get-NsLanAddresses)
Write-Host ''
Write-Host 'NaviSuite vessel server' -ForegroundColor Cyan
Write-Host "  Crew PCs open:        https://$ServerName/   (or https://$($lan | Select-Object -First 1)/)"
Write-Host "  Trust certificate:    http://$ServerName/navisuite-ca.crt  (install into 'Trusted Root Certification Authorities')"
Write-Host "  Health:               http://$ServerName/health"
Write-Host "  Server fingerprint:   $(Get-NsCaFingerprint $paths)"
Write-Host "                        (crew PCs show this when they first connect; it must match)"
Write-Host "  Artisan commands:     $Root\artisan.cmd <command>   (elevated prompt)"
Write-Host "  Logs:                 $($paths.Logs) and $($paths.Storage)\logs"
if ($healthCode -ne 0) { Write-Host ''; Write-NsWarn 'The health check did not pass. See the checks above.'; exit 1 }
