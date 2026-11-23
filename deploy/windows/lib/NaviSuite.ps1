# Shared helpers for the NaviSuite vessel server scripts.
# Dot-sourced by the scripts in deploy\windows. Windows PowerShell 5.1 or later.

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

$script:NsServiceNames = @('NaviSuite-MySQL', 'NaviSuite-Web', 'NaviSuite-Queue', 'NaviSuite-Scheduler', 'NaviSuite-Discovery', 'NaviSuite-Sync')
# Everything that runs application code. MySQL keeps running during updates.
$script:NsAppServices = @('NaviSuite-Web', 'NaviSuite-Queue', 'NaviSuite-Scheduler', 'NaviSuite-Discovery', 'NaviSuite-Sync')
# The PHP services run by WinSW.
$script:NsWinSwServices = @('NaviSuite-Queue', 'NaviSuite-Scheduler', 'NaviSuite-Discovery', 'NaviSuite-Sync')
$script:NsDiscoveryPort = 47810
# PHP extensions the application needs, as php -m lists them.
$script:NsPhpExtensions = @('ctype', 'curl', 'dom', 'fileinfo', 'gd', 'mbstring', 'openssl', 'pdo_mysql', 'tokenizer', 'xml', 'zip', 'Zend OPcache', 'FFI', 'sodium')
$script:NsTemplates = Join-Path $PSScriptRoot '..\templates'

# ---------------------------------------------------------------- output

function Write-NsStep([string]$Message) { Write-Host ''; Write-Host "==> $Message" -ForegroundColor Cyan }
function Write-NsInfo([string]$Message) { Write-Host "    $Message" }
function Write-NsOk([string]$Message) { Write-Host "    OK   $Message" -ForegroundColor Green }
function Write-NsWarn([string]$Message) { Write-Host "    WARN $Message" -ForegroundColor Yellow }
function Write-NsFail([string]$Message) { Write-Host "    FAIL $Message" -ForegroundColor Red }

# ---------------------------------------------------------------- checks

function Assert-NsAdministrator {
    $principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw 'Run this script from an elevated PowerShell (Run as administrator).'
    }
}

function Assert-NsRoot([string]$Root) {
    if ($Root -match '\s') { throw "The install folder must not contain spaces: '$Root'." }
    if (-not [IO.Path]::IsPathRooted($Root)) { throw "The install folder must be a full path: '$Root'." }
}

# ---------------------------------------------------------------- layout

# Every path the scripts use, in one place.
function Get-NsPaths([string]$Root) {
    $Root = $Root.TrimEnd('\')
    $p = [ordered]@{
        Root        = $Root
        RootFwd     = $Root -replace '\\', '/'
        App         = Join-Path $Root 'app'
        Data        = Join-Path $Root 'data'
        Runtime     = Join-Path $Root 'runtime'
        Config      = Join-Path $Root 'config'
        Secrets     = Join-Path $Root 'secrets'
    }
    $p.Storage      = Join-Path $p.Data 'storage'
    $p.Images       = Join-Path $p.Data 'images'
    $p.MySqlData    = Join-Path $p.Data 'mysql'
    $p.Backups      = Join-Path $p.Data 'backups'
    $p.Logs         = Join-Path $p.Data 'logs'
    $p.Tmp          = Join-Path $p.Data 'tmp'
    $p.Php          = Join-Path $p.Runtime 'php'
    $p.PhpExe       = Join-Path $p.Runtime 'php\php.exe'
    $p.Apache       = Join-Path $p.Runtime 'apache'
    $p.HttpdExe     = Join-Path $p.Runtime 'apache\bin\httpd.exe'
    $p.OpenSslExe   = Join-Path $p.Runtime 'apache\bin\openssl.exe'
    $p.MySql        = Join-Path $p.Runtime 'mysql'
    $p.MySqldExe    = Join-Path $p.Runtime 'mysql\bin\mysqld.exe'
    $p.MySqlExe     = Join-Path $p.Runtime 'mysql\bin\mysql.exe'
    $p.MySqlDumpExe = Join-Path $p.Runtime 'mysql\bin\mysqldump.exe'
    $p.WinSw        = Join-Path $p.Runtime 'winsw'
    $p.HttpdConf    = Join-Path $p.Config 'apache\httpd.conf'
    $p.PhpIniDir    = Join-Path $p.Config 'php'
    $p.CaBundle     = Join-Path $p.PhpIniDir 'cacert.pem'
    $p.MyIni        = Join-Path $p.Config 'mysql\my.ini'
    $p.Certs        = Join-Path $p.Config 'certs'
    $p.CaCert       = Join-Path $p.Config 'certs\ca.crt'
    $p.ServerCert   = Join-Path $p.Config 'certs\server.crt'
    $p.ServerKey    = Join-Path $p.Config 'certs\server.key'
    $p.CaKey        = Join-Path $p.Secrets 'ca.key'
    $p.RootCnf      = Join-Path $p.Secrets 'mysql-root.cnf'      # before Checkpoint 7: plaintext, converted on first use
    $p.RootSecret   = Join-Path $p.Secrets 'mysql-root.secret'   # DPAPI, administrators only
    $p.AppSecrets   = Join-Path $p.Secrets 'app'                 # DPAPI, read by the NaviSuite services (NAVISUITE_SECRETS_PATH)
    $p.Env          = Join-Path $p.App '.env'
    $p.BootstrapCache = Join-Path $p.App 'bootstrap\cache'
    return [pscustomobject]$p
}

# Folders in app\ that hold data, and where that data really lives. The app
# folder is replaced on every update; these survive because they are
# junctions into data\.
function Get-NsJunctions($Paths) {
    return @(
        @{ Link = (Join-Path $Paths.App 'storage');            Target = $Paths.Storage },
        @{ Link = (Join-Path $Paths.App 'images\cert_copy');   Target = (Join-Path $Paths.Images 'cert_copy') },
        @{ Link = (Join-Path $Paths.App 'images\signature');   Target = (Join-Path $Paths.Images 'signature') },
        @{ Link = (Join-Path $Paths.App 'images\userphoto');   Target = (Join-Path $Paths.Images 'userphoto') }
    )
}

function New-NsFolders($Paths) {
    $folders = @(
        $Paths.App, $Paths.Data, $Paths.Runtime, $Paths.Config, $Paths.Secrets, $Paths.AppSecrets,
        $Paths.Storage, $Paths.Images, $Paths.Backups, $Paths.Logs, $Paths.Tmp,
        (Join-Path $Paths.Tmp 'mysql'),
        (Join-Path $Paths.Images 'cert_copy'), (Join-Path $Paths.Images 'signature'), (Join-Path $Paths.Images 'userphoto'),
        (Join-Path $Paths.Logs 'apache'), (Join-Path $Paths.Logs 'php'), (Join-Path $Paths.Logs 'mysql'),
        (Join-Path $Paths.Logs 'queue'), (Join-Path $Paths.Logs 'scheduler'), (Join-Path $Paths.Logs 'discovery'), (Join-Path $Paths.Logs 'sync'), (Join-Path $Paths.Logs 'maintenance'),
        (Join-Path $Paths.Config 'apache'), $Paths.PhpIniDir, (Join-Path $Paths.Config 'mysql'), $Paths.Certs
    )
    foreach ($f in $folders) {
        if (-not (Test-Path -LiteralPath $f)) { New-Item -ItemType Directory -Path $f | Out-Null }
    }
}

# Laravel's storage skeleton inside data\storage.
function New-NsStorageSkeleton($Paths) {
    foreach ($rel in @('app\public', 'app\attachments', 'app\pms-imports', 'framework\cache\data', 'framework\sessions', 'framework\views', 'logs')) {
        $f = Join-Path $Paths.Storage $rel
        if (-not (Test-Path -LiteralPath $f)) { New-Item -ItemType Directory -Path $f | Out-Null }
    }
}

# ---------------------------------------------------------------- files

function Write-NsFile([string]$Path, [string]$Text) {
    # UTF-8 without a byte-order mark: Apache, PHP and MySQL all reject or
    # misread a BOM. Windows PowerShell's Set-Content would add one.
    $dir = Split-Path -Parent $Path
    if ($dir -and -not (Test-Path -LiteralPath $dir)) { New-Item -ItemType Directory -Path $dir | Out-Null }
    [IO.File]::WriteAllText($Path, $Text, (New-Object Text.UTF8Encoding($false)))
}

function Expand-NsTemplate([string]$Name, [hashtable]$Values) {
    $text = [IO.File]::ReadAllText((Join-Path $script:NsTemplates $Name))
    foreach ($key in $Values.Keys) { $text = $text.Replace('{{' + $key + '}}', [string]$Values[$key]) }
    $left = [regex]::Matches($text, '\{\{[A-Z0-9_]+\}\}') | ForEach-Object { $_.Value } | Select-Object -Unique
    if ($left) { throw "Template $Name has unfilled values: $($left -join ', ')" }
    return $text
}

function New-NsSecret([int]$Length = 32) {
    # Letters and digits only, so the value needs no quoting in .env, SQL or
    # MySQL option files.
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'.ToCharArray()
    $bytes = New-Object byte[] ($Length * 2)
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    $rng.GetBytes($bytes)
    $sb = New-Object Text.StringBuilder
    foreach ($b in $bytes) {
        if ($sb.Length -ge $Length) { break }
        if ($b -lt (256 - (256 % $alphabet.Length))) { [void]$sb.Append($alphabet[$b % $alphabet.Length]) }
    }
    if ($sb.Length -lt $Length) { return $sb.ToString() + (New-NsSecret ($Length - $sb.Length)) }
    return $sb.ToString()
}

function New-NsAppKey {
    $bytes = New-Object byte[] 32
    [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    return 'base64:' + [Convert]::ToBase64String($bytes)
}

# ---------------------------------------------------------------- .env

function Read-NsDotEnv([string]$Path) {
    $values = [ordered]@{}
    if (-not (Test-Path -LiteralPath $Path)) { return $values }
    foreach ($line in [IO.File]::ReadAllLines($Path)) {
        if ($line -match '^\s*([A-Z0-9_]+)\s*=(.*)$') {
            $v = $Matches[2].Trim()
            if ($v.Length -ge 2 -and $v.StartsWith('"') -and $v.EndsWith('"')) { $v = $v.Substring(1, $v.Length - 2) }
            $values[$Matches[1]] = $v
        }
    }
    return $values
}

# Sets KEY=value lines, keeping every other line and comment as it is.
function Set-NsDotEnv([string]$Path, [hashtable]$Values) {
    $lines = New-Object Collections.Generic.List[string]
    if (Test-Path -LiteralPath $Path) { $lines.AddRange([string[]][IO.File]::ReadAllLines($Path)) }
    foreach ($key in $Values.Keys) {
        $v = [string]$Values[$key]
        if ($v -match '[\s#"$]') { $v = '"' + ($v -replace '"', '\"') + '"' }
        $newLine = "$key=$v"
        $found = $false
        for ($i = 0; $i -lt $lines.Count; $i++) {
            if ($lines[$i] -match ('^\s*' + [regex]::Escape($key) + '\s*=')) { $lines[$i] = $newLine; $found = $true; break }
        }
        if (-not $found) { $lines.Add($newLine) }
    }
    Write-NsFile $Path (($lines -join "`r`n") + "`r`n")
}

# ---------------------------------------------------------------- processes

# Runs a program and throws if it fails. Arguments are passed as an array;
# none of them may contain double quotes.
function Invoke-NsNative([string]$FilePath, [string[]]$Arguments = @(), [switch]$AllowFailure, [switch]$Quiet) {
    # Windows PowerShell 5.1 turns a program's error output into a
    # terminating error under ErrorActionPreference=Stop. Judge by exit code.
    $saved = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { $output = & $FilePath @Arguments 2>&1; $code = $LASTEXITCODE } finally { $ErrorActionPreference = $saved }
    if (-not $Quiet) { $output | ForEach-Object { Write-NsInfo "$_" } }
    if ($code -ne 0 -and -not $AllowFailure) {
        if ($Quiet) { $output | ForEach-Object { Write-NsInfo "$_" } }
        throw "$(Split-Path -Leaf $FilePath) failed with exit code $code."
    }
    return $code
}

# Runs a program only to read its output, for version checks and the like.
# Never throws; the caller looks at .Code and .Output.
function Invoke-NsCapture([string]$FilePath, [string[]]$Arguments = @()) {
    $saved = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { $out = & $FilePath @Arguments 2>$null; $code = $LASTEXITCODE }
    catch { $out = @(); $code = -1 }
    finally { $ErrorActionPreference = $saved }
    return [pscustomobject]@{ Code = $code; Output = @($out | ForEach-Object { "$_" }) }
}

# Feeds a file to a program's standard input (mysql.exe < file).
function Invoke-NsWithInput([string]$FilePath, [string[]]$Arguments, [string]$InputFile) {
    $quoted = $Arguments | ForEach-Object { if ($_ -match '\s') { '"' + $_ + '"' } else { $_ } }
    $out = [IO.Path]::GetTempFileName(); $err = [IO.Path]::GetTempFileName()
    try {
        $proc = Start-Process -FilePath $FilePath -ArgumentList $quoted -RedirectStandardInput $InputFile `
            -RedirectStandardOutput $out -RedirectStandardError $err -NoNewWindow -Wait -PassThru
        Get-Content -LiteralPath $out, $err | ForEach-Object { Write-NsInfo $_ }
        if ($proc.ExitCode -ne 0) { throw "$(Split-Path -Leaf $FilePath) failed with exit code $($proc.ExitCode)." }
    } finally {
        Remove-Item -LiteralPath $out, $err -Force -ErrorAction SilentlyContinue
    }
}

function Invoke-NsArtisan($Paths, [string[]]$Arguments, [switch]$AllowFailure, [switch]$Quiet) {
    $env:PHPRC = $Paths.PhpIniDir
    Push-Location $Paths.App
    try {
        return Invoke-NsNative $Paths.PhpExe (@('artisan') + $Arguments + @('--no-interaction')) -AllowFailure:$AllowFailure -Quiet:$Quiet
    } finally { Pop-Location }
}

# mysql.exe as root. The password never appears on a command line, where
# other users could see it (see Use-NsRootCnf).
function Invoke-NsMySqlRoot($Paths, [string]$Sql, [string]$Database) {
    return Use-NsRootCnf $Paths {
        param($cnf)
        $argv = @("--defaults-extra-file=$cnf", '--batch', '--skip-column-names')
        if ($Database) { $argv += $Database }
        $argv += @('-e', $Sql)
        $saved = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        try { $output = & $Paths.MySqlExe @argv 2>&1; $code = $LASTEXITCODE } finally { $ErrorActionPreference = $saved }
        if ($code -ne 0) { $output | ForEach-Object { Write-NsInfo "$_" }; throw 'mysql.exe failed.' }
        return $output
    }
}

# ---------------------------------------------------------------- secrets (Checkpoint 7)
#
# Credentials are stored with Windows DPAPI, machine scope: a file can be
# opened on this Windows installation only, so a copied secrets folder or a
# backup taken elsewhere is useless. Format, shared with the application
# (App\Support\Secrets\SecretStore, DpapiProtector):
#     NSSEC1:dpapi:<base64 of ProtectedData.Protect(UTF-8 value, entropy, LocalMachine)>

$script:NsSecretEntropy = 'NaviSuite secrets v1'

function Protect-NsValue([string]$Value) {
    Add-Type -AssemblyName System.Security
    $blob = [Security.Cryptography.ProtectedData]::Protect([Text.Encoding]::UTF8.GetBytes($Value),
        [Text.Encoding]::UTF8.GetBytes($script:NsSecretEntropy), [Security.Cryptography.DataProtectionScope]::LocalMachine)
    return 'NSSEC1:dpapi:' + [Convert]::ToBase64String($blob)
}

function Unprotect-NsValue([string]$Text) {
    Add-Type -AssemblyName System.Security
    $parts = $Text.Trim().Split([char[]]@(':'), 3)
    if ($parts.Count -ne 3 -or $parts[0] -ne 'NSSEC1' -or $parts[1] -ne 'dpapi') { throw 'Not a DPAPI-protected NaviSuite secret.' }
    $plain = [Security.Cryptography.ProtectedData]::Unprotect([Convert]::FromBase64String($parts[2]),
        [Text.Encoding]::UTF8.GetBytes($script:NsSecretEntropy), [Security.Cryptography.DataProtectionScope]::LocalMachine)
    return [Text.Encoding]::UTF8.GetString($plain)
}

# A secret the application reads (secrets\app\<name>.secret).
function Set-NsSecret($Paths, [string]$Name, [string]$Value) {
    if ($Name -notmatch '^[a-z][a-z0-9_]{1,63}$') { throw "Invalid secret name '$Name'." }
    Write-NsFile (Join-Path $Paths.AppSecrets "$Name.secret") ((Protect-NsValue $Value) + "`n")
}

function Get-NsSecret($Paths, [string]$Name) {
    $file = Join-Path $Paths.AppSecrets "$Name.secret"
    if (-not (Test-Path -LiteralPath $file)) { return $null }
    return Unprotect-NsValue ([IO.File]::ReadAllText($file))
}

function Test-NsRootPassword($Paths) {
    return (Test-Path -LiteralPath $Paths.RootSecret) -or (Test-Path -LiteralPath $Paths.RootCnf)
}

function Set-NsRootPassword($Paths, [string]$Password) {
    Write-NsFile $Paths.RootSecret ((Protect-NsValue $Password) + "`n")
}

# The MySQL root password. A plaintext mysql-root.cnf from before
# Checkpoint 7 is converted to mysql-root.secret and deleted.
function Get-NsRootPassword($Paths) {
    if (Test-Path -LiteralPath $Paths.RootSecret) { return Unprotect-NsValue ([IO.File]::ReadAllText($Paths.RootSecret)) }
    if (Test-Path -LiteralPath $Paths.RootCnf) {
        $line = [IO.File]::ReadAllLines($Paths.RootCnf) | Where-Object { $_ -match '^\s*password\s*=' } | Select-Object -First 1
        if (-not $line) { throw "$($Paths.RootCnf) holds no password." }
        $password = ($line -replace '^\s*password\s*=\s*', '').Trim()
        Set-NsRootPassword $Paths $password
        if ((Unprotect-NsValue ([IO.File]::ReadAllText($Paths.RootSecret))) -ne $password) { throw 'Could not read the protected root password back.' }
        Remove-Item -LiteralPath $Paths.RootCnf -Force
        Write-NsInfo "MySQL root password moved from $($Paths.RootCnf) (plaintext) to $($Paths.RootSecret) (DPAPI)."
        return $password
    }
    throw "The MySQL root password is missing ($($Paths.RootSecret)). See docs\disaster-recovery.md, 'Lost credentials'."
}

# Runs $Script with the path of a MySQL option file holding the root
# password. MySQL's programs read passwords only from such a file (or the
# command line, which other users can see), so the file exists for the
# duration of the call only, in the administrators-only secrets folder,
# and is deleted afterwards even if the call fails.
function Use-NsRootCnf($Paths, [scriptblock]$Script) {
    $password = Get-NsRootPassword $Paths
    $cnf = Join-Path $Paths.Secrets ('mysql-root-' + [guid]::NewGuid().ToString('N') + '.cnf')
    Write-NsFile $cnf "[client]`r`nuser=root`r`npassword=$password`r`nhost=127.0.0.1`r`nport=3306`r`n"
    try { return (& $Script $cnf) }
    finally { Remove-Item -LiteralPath $cnf -Force -ErrorAction SilentlyContinue }
}

# ---------------------------------------------------------------- copying

function Copy-NsTree([string]$Source, [string]$Destination, [string[]]$ExcludeDirs = @(), [string[]]$ExcludeFiles = @()) {
    $argv = @($Source, $Destination, '/E', '/R:2', '/W:2', '/NFL', '/NDL', '/NJH', '/NJS', '/NP')
    if ($ExcludeDirs.Count) { $argv += '/XD'; $argv += $ExcludeDirs }
    if ($ExcludeFiles.Count) { $argv += '/XF'; $argv += $ExcludeFiles }
    & robocopy.exe @argv | Out-Null
    # Robocopy: 0-7 mean success (files copied, extra files, and so on).
    if ($LASTEXITCODE -ge 8) { throw "Copying $Source to $Destination failed (robocopy exit code $LASTEXITCODE)." }
}

function Test-NsJunction([string]$Path) {
    if (-not (Test-Path -LiteralPath $Path)) { return $false }
    $item = Get-Item -LiteralPath $Path -Force
    return [bool]($item.Attributes -band [IO.FileAttributes]::ReparsePoint)
}

function Get-NsJunctionTarget([string]$Path) {
    $item = Get-Item -LiteralPath $Path -Force
    $t = $item.Target
    if ($t -is [array]) { $t = $t[0] }
    return ([string]$t).TrimEnd('\')
}

# Removes a junction without touching the folder it points to.
function Remove-NsJunction([string]$Path) {
    if (Test-NsJunction $Path) {
        & cmd.exe /c rmdir "$Path"
        if ($LASTEXITCODE -ne 0) { throw "Could not remove the junction $Path." }
    }
}

# Deletes an old app folder. Junctions are removed first, so data\ is never
# reached, whichever version of PowerShell follows links when deleting.
function Remove-NsAppFolder($Paths, [string]$Folder) {
    if (-not (Test-Path -LiteralPath $Folder)) { return }
    $relative = @('storage', 'images\cert_copy', 'images\signature', 'images\userphoto')
    foreach ($rel in $relative) { Remove-NsJunction (Join-Path $Folder $rel) }
    foreach ($rel in $relative) {
        if (Test-NsJunction (Join-Path $Folder $rel)) { throw "Refusing to delete $Folder while it still contains a junction." }
    }
    & cmd.exe /c rmdir /s /q "$Folder"
    if (Test-Path -LiteralPath $Folder) { throw "Could not delete $Folder. Close any program using it and try again." }
}

# Points app\storage and the upload folders at data\. A real folder that a
# release (or a manual install) brought along is merged into data\ first.
# Nothing is overwritten: a file that differs from the one already in data\
# is kept in data\backups\replaced-<time>\ for an administrator to check.
function Set-NsDataJunctions($Paths) {
    foreach ($j in (Get-NsJunctions $Paths)) {
        if (-not (Test-Path -LiteralPath $j.Target)) { New-Item -ItemType Directory -Path $j.Target | Out-Null }
        if (Test-NsJunction $j.Link) {
            if ((Get-NsJunctionTarget $j.Link) -ieq $j.Target.TrimEnd('\')) { continue }
            Remove-NsJunction $j.Link
        } elseif (Test-Path -LiteralPath $j.Link) {
            # /XC /XN /XO: copy only files that data\ does not have yet.
            $argv = @($j.Link, $j.Target, '/E', '/XC', '/XN', '/XO', '/R:2', '/W:2', '/NFL', '/NDL', '/NJH', '/NJS', '/NP')
            & robocopy.exe @argv | Out-Null
            if ($LASTEXITCODE -ge 8) { throw "Could not move $($j.Link) into $($j.Target)." }
            $conflicts = @(Get-ChildItem -LiteralPath $j.Link -Recurse -File -Force | Where-Object {
                $rel = $_.FullName.Substring($j.Link.Length).TrimStart('\')
                $other = Join-Path $j.Target $rel
                -not (Test-Path -LiteralPath $other) -or (Get-Item -LiteralPath $other -Force).Length -ne $_.Length
            })
            if ($conflicts.Count -gt 0) {
                $keep = Join-Path $Paths.Backups ('replaced-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
                $dest = Join-Path $keep ($j.Link.Substring($Paths.App.Length).TrimStart('\'))
                New-Item -ItemType Directory -Path (Split-Path -Parent $dest) -Force | Out-Null
                Move-Item -LiteralPath $j.Link -Destination $dest
                Write-NsWarn "$($conflicts.Count) file(s) in $($j.Link) differ from data\. The folder was kept in $dest."
            } else {
                & cmd.exe /c rmdir /s /q "$($j.Link)"
            }
            if (Test-Path -LiteralPath $j.Link) { throw "Could not replace $($j.Link) with a junction." }
        }
        $parent = Split-Path -Parent $j.Link
        if (-not (Test-Path -LiteralPath $parent)) { New-Item -ItemType Directory -Path $parent | Out-Null }
        New-Item -ItemType Junction -Path $j.Link -Value $j.Target | Out-Null
    }
    New-NsStorageSkeleton $Paths
}

# ---------------------------------------------------------------- services

function Test-NsService([string]$Name) { return [bool](Get-Service -Name $Name -ErrorAction SilentlyContinue) }

function Stop-NsServices([string[]]$Names) {
    foreach ($n in $Names) {
        $svc = Get-Service -Name $n -ErrorAction SilentlyContinue
        if ($svc -and $svc.Status -ne 'Stopped') {
            Write-NsInfo "Stopping $n"
            Stop-Service -Name $n -Force -ErrorAction SilentlyContinue
            try { $svc.WaitForStatus('Stopped', [TimeSpan]::FromSeconds(60)) } catch { throw "$n did not stop within 60 seconds." }
        }
    }
}

function Start-NsServices([string[]]$Names) {
    foreach ($n in $Names) {
        $svc = Get-Service -Name $n -ErrorAction SilentlyContinue
        if ($svc -and $svc.Status -ne 'Running') {
            Write-NsInfo "Starting $n"
            Start-Service -Name $n
            try { $svc.WaitForStatus('Running', [TimeSpan]::FromSeconds(60)) } catch { throw "$n did not start within 60 seconds. See $($n) in data\logs." }
        }
    }
}

# Restart after a crash: 10 s, 30 s, then 60 s, counting from zero again
# after an hour without failures. failureflag 1 also restarts a service
# that stops itself with an error code, not only one that crashes.
function Set-NsServiceRecovery([string]$Name, [switch]$Delayed) {
    Invoke-NsNative 'sc.exe' @('failure', $Name, 'reset=', '3600', 'actions=', 'restart/10000/restart/30000/restart/60000') -Quiet | Out-Null
    Invoke-NsNative 'sc.exe' @('failureflag', $Name, '1') -Quiet | Out-Null
    $start = if ($Delayed) { 'delayed-auto' } else { 'auto' }
    Invoke-NsNative 'sc.exe' @('config', $Name, 'start=', $start) -Quiet | Out-Null
}

# Each service runs as its own virtual account (NT SERVICE\<name>), which
# has no password and only the folder rights granted in Set-NsAcl.
function Set-NsServiceAccount([string]$Name, [switch]$UseLocalSystem) {
    $account = if ($UseLocalSystem) { 'LocalSystem' } else { "NT SERVICE\$Name" }
    $code = Invoke-NsNative 'sc.exe' @('config', $Name, 'obj=', $account) -Quiet -AllowFailure
    if ($code -eq 0) { return }
    # Some Windows builds want an explicit empty password for a virtual
    # account, which Windows PowerShell cannot pass to sc.exe. WMI can.
    $svc = Get-CimInstance Win32_Service -Filter "Name='$Name'"
    $result = Invoke-CimMethod -InputObject $svc -MethodName Change -Arguments @{ StartName = $account; StartPassword = '' }
    if ($result.ReturnValue -ne 0) { throw "Could not set $Name to run as $account (error $($result.ReturnValue)). Run the script again with -UseLocalSystem." }
}

function Get-NsServiceSid([string]$Name) { return "NT SERVICE\$Name" }

# ---------------------------------------------------------------- permissions

function Grant-NsAcl([string]$Path, [string[]]$Grants, [switch]$Protect) {
    if (-not (Test-Path -LiteralPath $Path)) { return }
    $argv = @($Path)
    if ($Protect) { $argv += '/inheritance:r' }
    foreach ($g in $Grants) { $argv += '/grant:r'; $argv += $g }
    $argv += '/Q'
    Invoke-NsNative 'icacls.exe' $argv -Quiet | Out-Null
}

# Only administrators and SYSTEM may change anything under the install
# folder. Ordinary Windows users on the master PC get no access at all, so
# they cannot read .env, the database files or the keys. Each service gets
# exactly what it needs. A service's account exists only once the service
# is installed, so the rights are granted in three parts:
#   Base   the install folder itself (run first)
#   MySql  after NaviSuite-MySQL is installed
#   App    after NaviSuite-Web, -Queue, -Scheduler, -Discovery and -Sync are installed
function Set-NsAcl($Paths, [ValidateSet('Base', 'MySql', 'App')][string]$Part, [switch]$UseLocalSystem) {
    $admins = '*S-1-5-32-544'
    $system = '*S-1-5-18'
    $full = @("${admins}:(OI)(CI)F", "${system}:(OI)(CI)F")
    $fileFull = @("${admins}:F", "${system}:F")

    if ($Part -eq 'Base') {
        Grant-NsAcl $Paths.Root $full -Protect
        Grant-NsAcl $Paths.Backups $full -Protect
        Grant-NsAcl $Paths.Secrets $full -Protect
        Grant-NsAcl $Paths.AppSecrets $full -Protect
        Grant-NsAcl $Paths.MySqlData $full -Protect
        Grant-NsAcl $Paths.Env $fileFull -Protect
        Grant-NsAcl $Paths.ServerKey $fileFull -Protect
        return
    }
    if ($UseLocalSystem) { return }   # SYSTEM already has full control.

    if ($Part -eq 'MySql') {
        $mysql = Get-NsServiceSid 'NaviSuite-MySQL'
        Grant-NsAcl $Paths.Root @("${mysql}:(OI)(CI)RX")
        Grant-NsAcl $Paths.MySqlData ($full + "${mysql}:(OI)(CI)F") -Protect
        Grant-NsAcl (Join-Path $Paths.Logs 'mysql') @("${mysql}:(OI)(CI)M")
        Grant-NsAcl (Join-Path $Paths.Tmp 'mysql') @("${mysql}:(OI)(CI)M")
        return
    }

    $web = Get-NsServiceSid 'NaviSuite-Web'
    $queue = Get-NsServiceSid 'NaviSuite-Queue'
    $sched = Get-NsServiceSid 'NaviSuite-Scheduler'
    $disco = Get-NsServiceSid 'NaviSuite-Discovery'
    $sync = Get-NsServiceSid 'NaviSuite-Sync'
    $appAccounts = @($web, $queue, $sched, $disco, $sync)

    # Read and run: the application, the runtimes and the configuration.
    Grant-NsAcl $Paths.Root ($appAccounts | ForEach-Object { "${_}:(OI)(CI)RX" })
    # Written by the application.
    foreach ($f in @($Paths.Storage, $Paths.Images, $Paths.Tmp, (Join-Path $Paths.Logs 'php'), $Paths.BootstrapCache)) {
        Grant-NsAcl $f ($appAccounts | ForEach-Object { "${_}:(OI)(CI)M" })
    }
    Grant-NsAcl (Join-Path $Paths.Logs 'apache') @("${web}:(OI)(CI)M")
    Grant-NsAcl (Join-Path $Paths.Logs 'queue') @("${queue}:(OI)(CI)M")
    Grant-NsAcl (Join-Path $Paths.Logs 'scheduler') @("${sched}:(OI)(CI)M")
    Grant-NsAcl (Join-Path $Paths.Logs 'discovery') @("${disco}:(OI)(CI)M")
    Grant-NsAcl (Join-Path $Paths.Logs 'sync') @("${sync}:(OI)(CI)M")
    # .env: read by the application services only.
    Grant-NsAcl $Paths.Env ($fileFull + ($appAccounts | ForEach-Object { "${_}:R" })) -Protect
    # The application's encrypted credentials (Checkpoint 7): read only.
    Grant-NsAcl $Paths.AppSecrets ($full + ($appAccounts | ForEach-Object { "${_}:(OI)(CI)R" })) -Protect
    # TLS key: Apache only.
    Grant-NsAcl $Paths.ServerKey ($fileFull + "${web}:R") -Protect
}

# ---------------------------------------------------------------- settings

# The choices made at install time, so update and maintenance can render the
# same configuration again.
function Get-NsSettingsPath($Paths) { return (Join-Path $Paths.Config 'install.json') }

function Read-NsSettings($Paths) {
    $file = Get-NsSettingsPath $Paths
    if (-not (Test-Path -LiteralPath $file)) { throw "No NaviSuite installation found ($file is missing). Run install-server.ps1 first." }
    return ([IO.File]::ReadAllText($file) | ConvertFrom-Json)
}

function Save-NsSettings($Paths, $Settings) {
    Write-NsFile (Get-NsSettingsPath $Paths) ($Settings | ConvertTo-Json -Depth 4)
}

# A saved setting, or the default when an older install did not save it.
function Get-NsSetting($Settings, [string]$Name, $Default) {
    if ($Settings -and $Settings.PSObject.Properties[$Name]) { return $Settings.$Name }
    return $Default
}

function Get-NsRelease([string]$AppFolder) {
    $file = Join-Path $AppFolder 'RELEASE'
    if (Test-Path -LiteralPath $file) { return ([IO.File]::ReadAllText($file)).Trim() }
    return 'unknown'
}

# Writes httpd.conf, php.ini, my.ini, the WinSW service files and
# artisan.cmd from the templates.
function Write-NsConfigs($Paths, $Settings) {
    $values = @{
        ROOT = $Paths.Root
        ROOT_FWD = $Paths.RootFwd
        SERVER_NAME = $Settings.ServerName
        INNODB_BUFFER_POOL_MB = $Settings.InnoDbBufferPoolMB
        # The CA bundle lines in php.ini stay commented out until the file is
        # there: PHP refuses every HTTPS call if they name a missing file.
        CA_BUNDLE_OFF = $(if (Test-Path -LiteralPath $Paths.CaBundle) { '' } else { ';' })
    }
    Write-NsFile $Paths.HttpdConf (Expand-NsTemplate 'httpd.conf' $values)
    Write-NsFile (Join-Path $Paths.PhpIniDir 'php.ini') (Expand-NsTemplate 'php.ini' $values)
    Write-NsFile $Paths.MyIni (Expand-NsTemplate 'my.ini' $values)
    foreach ($svc in $script:NsWinSwServices) {
        Write-NsFile (Join-Path $Paths.WinSw "$svc.xml") (Expand-NsTemplate "$svc.xml" $values)
    }
    # For administrators: C:\NaviSuite\artisan.cmd <command> runs artisan
    # with the server's php.ini, from an elevated prompt.
    $cmd = "@echo off`r`nset ""PHPRC=$($Paths.PhpIniDir)""`r`ncd /d ""$($Paths.App)""`r`n""$($Paths.PhpExe)"" artisan %*`r`n"
    Write-NsFile (Join-Path $Paths.Root 'artisan.cmd') $cmd
}

# Apache's PHP module needs PHP's own DLLs (libssh2, libcrypto and so on),
# which sit in the PHP folder. Give the Apache service a PATH that starts
# there, without changing the PATH of the whole PC.
function Set-NsWebServiceEnvironment($Paths) {
    $machinePath = [Environment]::GetEnvironmentVariable('Path', 'Machine')
    $key = 'HKLM:\SYSTEM\CurrentControlSet\Services\NaviSuite-Web'
    New-ItemProperty -Path $key -Name 'Environment' -PropertyType MultiString -Force -Value @(
        "PATH=$($Paths.Php);$machinePath",
        "PHPRC=$($Paths.PhpIniDir)"
    ) | Out-Null
}

function Use-NsPhpPath($Paths) {
    if (-not ($env:PATH -split ';' | Where-Object { $_ -ieq $Paths.Php })) { $env:PATH = "$($Paths.Php);$env:PATH" }
    $env:PHPRC = $Paths.PhpIniDir
}

function Get-NsWinSwExe($Paths) {
    return @('WinSW-x64.exe', 'WinSW.exe', 'WinSW.NET461.exe') | ForEach-Object { Join-Path $Paths.WinSw $_ } |
        Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
}

# Installs the PHP background services that are missing (queue, scheduler,
# discovery, sync) and sets their account and recovery. Also used by updates, so a
# server installed by an older release gets services added since.
function Install-NsBackgroundServices($Paths, $Settings) {
    $winSwExe = Get-NsWinSwExe $Paths
    if (-not $winSwExe) { throw "WinSW not found in $($Paths.WinSw)." }
    foreach ($svc in $script:NsWinSwServices) {
        $isNew = -not (Test-NsService $svc)
        Install-NsWinSwService $Paths $svc $winSwExe
        Set-NsServiceAccount $svc -UseLocalSystem:([bool]$Settings.UseLocalSystem)
        Set-NsServiceRecovery $svc -Delayed
        if ($isNew) { Write-NsOk "$svc installed." }
    }
}

# SHA-256 fingerprint of the vessel CA, as the clients show it.
function Get-NsCaFingerprint($Paths) {
    if (-not (Test-Path -LiteralPath $Paths.CaCert)) { return $null }
    $cert = New-Object Security.Cryptography.X509Certificates.X509Certificate2($Paths.CaCert)
    $hash = [Security.Cryptography.SHA256]::Create().ComputeHash($cert.RawData)
    return (($hash | ForEach-Object { $_.ToString('X2') }) -join ':')
}

function Install-NsWinSwService($Paths, [string]$Name, [string]$WinSwExe) {
    $exe = Join-Path $Paths.WinSw "$Name.exe"
    if (-not (Test-Path -LiteralPath $exe)) { Copy-Item -LiteralPath $WinSwExe -Destination $exe }
    if (-not (Test-NsService $Name)) {
        Invoke-NsNative $exe @('install') | Out-Null
    }
}

# ---------------------------------------------------------------- network

function Get-NsLanAddresses {
    Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' -and $_.PrefixOrigin -ne 'WellKnown' } |
        Select-Object -ExpandProperty IPAddress -Unique
}

function Get-NsHealth([int]$TimeoutSeconds = 10) {
    try {
        $r = Invoke-WebRequest -Uri 'http://127.0.0.1/health' -UseBasicParsing -TimeoutSec $TimeoutSeconds
        return [pscustomobject]@{ Code = [int]$r.StatusCode; Body = ($r.Content | ConvertFrom-Json) }
    } catch [Net.WebException] {
        $resp = $_.Exception.Response
        if ($resp) {
            $reader = New-Object IO.StreamReader($resp.GetResponseStream())
            $text = $reader.ReadToEnd()
            $body = $null
            try { $body = $text | ConvertFrom-Json } catch { }
            return [pscustomobject]@{ Code = [int]$resp.StatusCode; Body = $body }
        }
        return [pscustomobject]@{ Code = 0; Body = $null; Error = $_.Exception.Message }
    }
}

# ---------------------------------------------------------------- releases and installers (Checkpoint 8)
#
# Every release built by deploy/build-release.sh carries release.json:
#   product_version   1.2.3, the installer's version, compared on upgrades
#   release           the build's name (also in RELEASE, shown by /health)
#   min_upgrade_from  the oldest installed version this release upgrades
#   test_build        true when the build did not pass the test suite gate
# Releases from before Checkpoint 8 have only RELEASE: version 0.0.0.

function Get-NsReleaseInfo([string]$Folder) {
    $file = Join-Path $Folder 'release.json'
    if (Test-Path -LiteralPath $file) {
        $info = [IO.File]::ReadAllText($file) | ConvertFrom-Json
        if (-not $info.product_version) { throw "$file has no product_version." }
        return $info
    }
    return [pscustomobject]@{ product_version = '0.0.0'; release = (Get-NsRelease $Folder); min_upgrade_from = '0.0.0'; test_build = $false }
}

# "1.2.3-test.abc" -> [version]'1.2.3'
function ConvertTo-NsVersion([string]$Text) {
    if ("$Text" -notmatch '^(\d+)\.(\d+)\.(\d+)') { throw "Not a version: '$Text'." }
    return [version]"$($Matches[1]).$($Matches[2]).$($Matches[3])"
}

# Refuses a release whose migrations folder lacks migrations the database
# already ran: that database belongs to a newer release, and the older code
# would misread it. Migrations only ever move forward (no down migrations
# are run on a vessel), so this is the real downgrade guard.
function Assert-NsSchemaNotNewer($Paths, [string]$ReleaseFolder) {
    $envNow = Read-NsDotEnv $Paths.Env
    $dbName = if ($envNow['VESSEL_DB_DATABASE']) { $envNow['VESSEL_DB_DATABASE'] } else { 'navisuite' }
    if ($dbName -notmatch '^[A-Za-z0-9_]+$') { throw "Invalid database name '$dbName'." }
    $exists = (Invoke-NsMySqlRoot $Paths "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$dbName' AND table_name = 'migrations'") | Select-Object -First 1
    if ([int]$exists -eq 0) { return }
    $ran = @(Invoke-NsMySqlRoot $Paths "SELECT migration FROM ``$dbName``.migrations")
    $known = @(Get-ChildItem -LiteralPath (Join-Path $ReleaseFolder 'database\migrations') -Filter '*.php' | ForEach-Object { $_.BaseName })
    $unknown = @($ran | Where-Object { $_ -and ($known -notcontains $_) })
    if ($unknown.Count) {
        throw "The database has $($unknown.Count) migration(s) this release does not have (newest: $(@($unknown | Sort-Object)[-1])). It was updated by a newer NaviSuite release; installing this older one would break it. Install the newer release again, or restore a database backup taken before that update."
    }
}

# After a reboot every service starts by itself (MySQL automatically, the
# others delayed). This task runs startup-check.ps1 a few minutes later: it
# starts any NaviSuite service that is still stopped and writes the result to
# data\logs\startup, so a failed start is visible without anyone watching.
function Register-NsStartupTask($Paths) {
    $taskName = 'NaviSuite Startup Check'
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
    $script = Join-Path $Paths.App 'deploy\windows\startup-check.ps1'
    $action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -NonInteractive -ExecutionPolicy Bypass -File `"$script`" -Root `"$($Paths.Root)`""
    $trigger = New-ScheduledTaskTrigger -AtStartup
    $trigger.Delay = 'PT4M'
    $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
    $taskSettings = New-ScheduledTaskSettingsSet -ExecutionTimeLimit (New-TimeSpan -Minutes 30) -MultipleInstances IgnoreNew
    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $taskSettings `
        -Description 'NaviSuite: after Windows starts, starts any NaviSuite service that did not start and logs the result.' | Out-Null
}

# Keeps the newest $Keep files matching $Filter in $Folder.
function Remove-NsOldFiles([string]$Folder, [string]$Filter, [int]$Keep) {
    if (-not (Test-Path -LiteralPath $Folder)) { return }
    Get-ChildItem -LiteralPath $Folder -Filter $Filter -File | Sort-Object LastWriteTime -Descending |
        Select-Object -Skip $Keep | ForEach-Object { Remove-Item -LiteralPath $_.FullName -Force -ErrorAction SilentlyContinue }
}
