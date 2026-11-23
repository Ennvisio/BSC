<#
.SYNOPSIS
    Installs, upgrades or repairs the NaviSuite vessel server from an
    installer payload. Run by NaviSuite-Server-Setup.exe; can be run by hand.

.DESCRIPTION
    The payload is what NaviSuite-Server-Setup.exe unpacks
    (deploy/build-installers.sh builds it):

      release\    the NaviSuite release (this script is release\deploy\windows\setup-server.ps1)
      runtime\    Apache, PHP, MySQL, WinSW, cacert.pem, vc_redist.x64.exe and runtime.json
      client\     NaviSuite-Client-Setup.exe
      build.json  how the installer was built, including the test results

    What it does:
      1. Writes everything it does to <Root>\data\logs\setup\setup-<time>.log.
      2. Checks compatibility before changing anything: Windows, disk space,
         versions (no downgrade, minimum version to upgrade from), and that
         the database is not newer than this release. A refusal changes
         nothing (exit code 3).
      3. Installs the Visual C++ runtime that PHP, Apache and MySQL need,
         when missing.
      4. New server: install-server.ps1 with the answers. Existing server:
         update-server.ps1, which keeps .env, the credentials, the database,
         the certificates and the uploaded files. A server uninstalled with
         its data kept: install-server.ps1 with its saved settings brings
         the services back, then update-server.ps1. Then runtimes whose
         version changed are replaced (MySQL only within the same release
         series), with the old ones kept to go back to.
      5. Puts the desktop client installer, with a client-config.json for
         this server, in <Root>\data\client-setup for IT.

    Credentials: the sync token is taken from the answer file's TokenFile
    or Token, or from the environment variable NAVISUITE_SETUP_SYNC_TOKEN
    (how the guided installer passes it, so it never touches the disk).
    It is never written to the log.

    Exit codes: 0 done; 1 failed (see the log; an upgrade puts the previous
    release back where it can); 2 installed, but the health check found
    problems; 3 refused by the compatibility checks, nothing changed.

.PARAMETER AnswerFile
    INI file with the settings for a new server (see
    deploy\installer\server\answers.example.ini). On an existing server only
    the [sync] values are used, if present.

.EXAMPLE
    .\setup-server.ps1 -AnswerFile D:\vessel-12.ini
#>
[CmdletBinding()]
param(
    [string]$Payload,
    [string]$Root = 'C:\NaviSuite',
    [string]$AnswerFile,
    [switch]$AllowDowngrade,
    [switch]$AcceptTestBuild,
    [string]$ResultFile
)

$ErrorActionPreference = 'Stop'
. "$PSScriptRoot\lib\NaviSuite.ps1"
if (-not $Payload) { $Payload = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path }
$Payload = $Payload.TrimEnd('\')
$releaseDir = Join-Path $Payload 'release'
$runtimeDir = Join-Path $Payload 'runtime'
$clientDir = Join-Path $Payload 'client'
$paths = Get-NsPaths $Root
if (-not $ResultFile) { $ResultFile = Join-Path $Root 'setup\last-result.ini' }

$logDir = Join-Path $paths.Logs 'setup'
New-Item -ItemType Directory -Path $logDir -Force | Out-Null
Remove-NsOldFiles $logDir 'setup-*.log' 30
$log = Join-Path $logDir ('setup-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.log')
Start-Transcript -Path $log | Out-Null

$script:refused = $false
$result = [ordered]@{ Status = 'failed'; Mode = ''; Version = ''; Release = ''; Url = ''; Fingerprint = ''; ClientKit = ''; Log = $log; Message = '' }

function Stop-NsRefused([string]$Message) { $script:refused = $true; throw $Message }

function Write-NsResult {
    $dir = Split-Path -Parent $ResultFile
    New-Item -ItemType Directory -Path $dir -Force | Out-Null
    $text = "[result]`r`n" + (($result.Keys | ForEach-Object { "$_=$(("$($result[$_])" -replace '[\r\n]+', ' '))" }) -join "`r`n") + "`r`n"
    [IO.File]::WriteAllText($ResultFile, $text, (New-Object Text.UTF8Encoding($false)))
}

function Read-NsIni([string]$Path) {
    $values = @{}
    $section = ''
    foreach ($line in [IO.File]::ReadAllLines($Path)) {
        $t = $line.Trim()
        if (-not $t -or $t.StartsWith(';') -or $t.StartsWith('#')) { continue }
        if ($t -match '^\[(.+)\]$') { $section = $Matches[1].Trim().ToLowerInvariant(); continue }
        $i = $t.IndexOf('=')
        if ($i -lt 1) { continue }
        $values["$section.$($t.Substring(0, $i).Trim().ToLowerInvariant())"] = $t.Substring($i + 1).Trim()
    }
    return $values
}

function Get-NsBool($Values, [string]$Key, [bool]$Default) {
    if (-not $Values.ContainsKey($Key) -or $Values[$Key] -eq '') { return $Default }
    return @('1', 'true', 'yes', 'on') -contains $Values[$Key].ToLowerInvariant()
}

# Versions of the runtimes installed in <Root>\runtime, read from the
# programs themselves (servers set up before Checkpoint 8 have no runtime.json).
function Get-NsInstalledRuntimes {
    $v = @{}
    if (Test-Path -LiteralPath $paths.PhpExe) {
        $r = Invoke-NsCapture $paths.PhpExe @('-n', '-r', 'echo PHP_VERSION;')
        if ($r.Code -eq 0) { $v.php = ($r.Output -join '').Trim() }
    }
    if (Test-Path -LiteralPath $paths.HttpdExe) {
        $r = Invoke-NsCapture $paths.HttpdExe @('-v')
        if ("$($r.Output)" -match 'Apache/(\d+\.\d+\.\d+)') { $v.apache = $Matches[1] }
    }
    if (Test-Path -LiteralPath $paths.MySqldExe) {
        $r = Invoke-NsCapture $paths.MySqldExe @('--version')
        if ("$($r.Output)" -match 'Ver (\d+\.\d+\.\d+)') { $v.mysql = $Matches[1] }
    }
    $winsw = Get-NsWinSwExe $paths
    if ($winsw) {
        $r = Invoke-NsCapture $winsw @('--version')
        if ("$($r.Output)" -match '(\d+\.\d+\.\d+)') { $v.winsw = $Matches[1] }
    }
    return $v
}

function Test-NsPhpRuntime {
    $php = Invoke-NsCapture $paths.PhpExe @('-n', '-r', 'echo(PHP_VERSION.chr(32).PHP_ZTS.chr(32).PHP_INT_SIZE);')
    $parts = ($php.Output -join '').Split(' ')
    if ($php.Code -ne 0 -or $parts.Count -lt 3 -or [version]$parts[0] -lt [version]'8.2' -or $parts[1] -ne '1' -or $parts[2] -ne '8') {
        throw "The new PHP does not start or is not 8.2+ thread-safe x64: $($php.Output -join ' ')"
    }
    Use-NsPhpPath $paths
    $modules = (Invoke-NsCapture $paths.PhpExe @('-m')).Output
    $missing = @($script:NsPhpExtensions | Where-Object { $modules -notcontains $_ })
    if ($missing.Count) { throw "The new PHP lacks extensions: $($missing -join ', ')" }
}

# Replaces runtimes whose version differs from the installer's, keeping the
# old ones in runtime\<name>.previous until the next replacement. Every check
# runs before the services are started; a failure puts the old ones back.
function Update-NsRuntimes($NewRuntimes) {
    $installed = Get-NsInstalledRuntimes
    $plan = @()
    foreach ($name in @('apache', 'php', 'mysql', 'winsw')) {
        $new = $NewRuntimes.components.$name.version
        $old = $installed[$name]
        if (-not $new) { continue }
        if ($old -eq $new) { Write-NsInfo "${name}: $old, unchanged"; continue }
        if ($name -eq 'mysql' -and $old) {
            $o = ConvertTo-NsVersion $old; $n = ConvertTo-NsVersion $new
            if ($o.Major -ne $n.Major -or $o.Minor -ne $n.Minor) {
                Write-NsWarn "MySQL $old is kept: the installer brings $new, a different release series. Moving between series needs a planned upgrade (docs\checkpoint-8-installers.md, 'MySQL')."
                $result.Message += "MySQL $old kept (installer has $new). "
                continue
            }
            if ($n -lt $o) { Write-NsWarn "MySQL $old is newer than the installer's $new; kept."; continue }
        }
        $plan += $name
        Write-NsInfo "${name}: $old -> $new"
    }

    $ca = Join-Path $runtimeDir 'cacert.pem'
    if (Test-Path -LiteralPath $ca) { Copy-Item -LiteralPath $ca -Destination $paths.CaBundle -Force }
    if (-not $plan.Count) { Write-NsOk 'Runtimes are up to date.'; return }

    if ($plan -contains 'mysql') {
        & (Join-Path $PSScriptRoot 'backup-database.ps1') -Root $Root -Reason 'before-runtime-update' | Out-Null
    }
    $stop = @($script:NsAppServices)
    if ($plan -contains 'mysql') { $stop += 'NaviSuite-MySQL' }
    Stop-NsServices $stop

    $swapped = @()
    try {
        foreach ($name in $plan) {
            if ($name -eq 'winsw') {
                # The folder also holds the service definitions: only the
                # program is replaced, as WinSW-x64.exe and each service's copy.
                $exe = Join-Path $runtimeDir 'winsw\WinSW-x64.exe'
                $prev = Join-Path $paths.WinSw 'previous'
                New-Item -ItemType Directory -Path $prev -Force | Out-Null
                foreach ($f in @('WinSW-x64.exe') + ($script:NsWinSwServices | ForEach-Object { "$_.exe" })) {
                    $target = Join-Path $paths.WinSw $f
                    if (Test-Path -LiteralPath $target) { Copy-Item -LiteralPath $target -Destination (Join-Path $prev $f) -Force }
                    Copy-Item -LiteralPath $exe -Destination $target -Force
                }
            } else {
                $dest = Join-Path $paths.Runtime $name
                $prev = "$dest.previous"
                if (Test-Path -LiteralPath $prev) { & cmd.exe /c rmdir /s /q "$prev" }
                Rename-Item -LiteralPath $dest -NewName (Split-Path -Leaf $prev)
                Copy-NsTree (Join-Path $runtimeDir $name) $dest
            }
            $swapped += $name
            Write-NsOk "$name replaced."
        }
        Write-NsConfigs $paths (Read-NsSettings $paths)
        Set-NsAcl $paths -Part Base
        Set-NsAcl $paths -Part MySql -UseLocalSystem:([bool](Get-NsSetting (Read-NsSettings $paths) 'UseLocalSystem' $false))
        Set-NsAcl $paths -Part App -UseLocalSystem:([bool](Get-NsSetting (Read-NsSettings $paths) 'UseLocalSystem' $false))
        if ($swapped -contains 'php') { Test-NsPhpRuntime }
        Invoke-NsNative $paths.HttpdExe @('-t', '-f', $paths.HttpdConf) -Quiet | Out-Null
        Start-NsServices @('NaviSuite-MySQL')
        $ready = $false
        for ($i = 0; $i -lt 120 -and -not $ready; $i++) { try { Invoke-NsMySqlRoot $paths 'SELECT 1' | Out-Null; $ready = $true } catch { Start-Sleep -Seconds 1 } }
        if (-not $ready) { throw "MySQL does not answer after the update. See $($paths.Logs)\mysql\mysqld.err." }
        Start-NsServices $script:NsAppServices
    } catch {
        Write-NsFail $_.Exception.Message
        Write-NsWarn "Putting the previous runtimes back: $($swapped -join ', ')"
        Stop-NsServices $stop
        foreach ($name in $swapped) {
            if ($name -eq 'winsw') {
                Get-ChildItem -LiteralPath (Join-Path $paths.WinSw 'previous') -File | ForEach-Object { Copy-Item -LiteralPath $_.FullName -Destination (Join-Path $paths.WinSw $_.Name) -Force }
            } else {
                $dest = Join-Path $paths.Runtime $name
                & cmd.exe /c rmdir /s /q "$dest"
                Rename-Item -LiteralPath "$dest.previous" -NewName (Split-Path -Leaf $dest)
            }
        }
        Write-NsConfigs $paths (Read-NsSettings $paths)
        Start-NsServices $stop
        if ($plan -contains 'mysql') { Write-NsWarn 'If MySQL does not start with the old version either, restore the before-runtime-update backup (backup-database.ps1 -Restore).' }
        throw "The runtime update failed and was undone. The new NaviSuite release is installed and running with the previous runtimes."
    }
    Copy-Item -LiteralPath (Join-Path $runtimeDir 'runtime.json') -Destination (Join-Path $paths.Runtime 'runtime.json') -Force
}

# install-server.ps1 parameters that reproduce this server's own saved
# settings (config\install.json), so running it again changes nothing else.
function Get-NsSavedInstallParams {
    $s = Read-NsSettings $paths
    return @{
        VesselId = [int]$s.VesselId
        Root = $Root
        ServerName = $s.ServerName
        ExtraNames = @(Get-NsSetting $s 'ExtraNames' @())
        FirewallRemoteAddress = @(Get-NsSetting $s 'FirewallRemoteAddress' @('LocalSubnet'))
        InnoDbBufferPoolMB = [int](Get-NsSetting $s 'InnoDbBufferPoolMB' 512)
        SkipFirewall = [bool](Get-NsSetting $s 'SkipFirewall' $false)
        IncludePublicProfile = [bool](Get-NsSetting $s 'IncludePublicProfile' $false)
        UseLocalSystem = [bool](Get-NsSetting $s 'UseLocalSystem' $false)
    }
}

function Add-NsSyncParams([hashtable]$Params) {
    if ($answers['sync.cloudurl']) { $Params.SyncCloudUrl = $answers['sync.cloudurl'] }
    if ($answers['sync.nodeid']) { $Params.SyncNodeId = $answers['sync.nodeid'] }
    if ($token) { $Params.SyncNodeToken = $token }
    return $Params
}

function Install-NsVcRuntime($NewRuntimes) {
    $exe = Join-Path $runtimeDir 'vc_redist.x64.exe'
    if (-not (Test-Path -LiteralPath $exe)) { Write-NsInfo 'No Visual C++ runtime in the installer; assuming it is present.'; return }
    $key = 'HKLM:\SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64'
    $have = Get-ItemProperty -Path $key -ErrorAction SilentlyContinue
    $want = $NewRuntimes.components.vc_redist.version
    if ($have -and $have.Installed -eq 1 -and $want -and ([version]$have.Version.TrimStart('v') -ge [version]$want)) {
        Write-NsOk "Visual C++ runtime $($have.Version) present."
        return
    }
    Write-NsInfo 'Installing the Microsoft Visual C++ 2015-2022 runtime (x64).'
    $p = Start-Process -FilePath $exe -ArgumentList '/install', '/quiet', '/norestart' -Wait -PassThru
    switch ($p.ExitCode) {
        0 { Write-NsOk 'Visual C++ runtime installed.' }
        1638 { Write-NsOk 'A newer Visual C++ runtime is already installed.' }
        3010 { Write-NsWarn 'Visual C++ runtime installed; Windows asks for a restart. NaviSuite runs now; restart when convenient.' }
        default { throw "The Visual C++ runtime installer failed (exit code $($p.ExitCode))." }
    }
}

function Publish-NsClientKit {
    $exe = Join-Path $clientDir 'NaviSuite-Client-Setup.exe'
    if (-not (Test-Path -LiteralPath $exe)) { Write-NsWarn 'No client installer in this payload.'; return }
    $kit = Join-Path $paths.Data 'client-setup'
    New-Item -ItemType Directory -Path $kit -Force | Out-Null
    Copy-Item -LiteralPath $exe -Destination (Join-Path $kit 'NaviSuite-Client-Setup.exe') -Force
    $settings = Read-NsSettings $paths
    $fingerprint = Get-NsCaFingerprint $paths
    $hosts = @(@($settings.ServerName) + @(Get-NsLanAddresses | Select-Object -First 3)) | Where-Object { $_ }
    $config = [ordered]@{
        server = [ordered]@{ hosts = @($hosts); vesselId = [int]$settings.VesselId; caSha256 = $fingerprint }
        lockServer = $false
    }
    Write-NsFile (Join-Path $kit 'client-config.json') (($config | ConvertTo-Json -Depth 4) + "`r`n")
    $sha = (Get-FileHash -LiteralPath (Join-Path $kit 'NaviSuite-Client-Setup.exe') -Algorithm SHA256).Hash
    $readme = @(
        'NaviSuite desktop client for the crew PCs of this vessel',
        '',
        'Copy BOTH files in this folder to a USB stick or a shared folder, then on each crew PC',
        '(as administrator) run NaviSuite-Client-Setup.exe from there. The installer reads',
        'client-config.json next to it, so the PC is pinned to this server and its certificate.',
        '',
        'Silent install, for example from a script:',
        "    NaviSuite-Client-Setup.exe /S /SERVER=$($hosts -join ',') /FINGERPRINT=$fingerprint /VESSEL=$($settings.VesselId)",
        '',
        "Server certificate fingerprint (the client shows it): $fingerprint",
        "SHA-256 of NaviSuite-Client-Setup.exe: $sha"
    ) -join "`r`n"
    Write-NsFile (Join-Path $kit 'README.txt') ($readme + "`r`n")
    $result.ClientKit = $kit
    Write-NsOk "Client installer and settings for the crew PCs: $kit"
}

try {
    Write-NsStep 'NaviSuite server setup'
    Assert-NsAdministrator
    foreach ($d in @($releaseDir, $runtimeDir)) { if (-not (Test-Path -LiteralPath $d)) { throw "Incomplete installer payload: $d is missing." } }
    $new = Get-NsReleaseInfo $releaseDir
    $runtimes = [IO.File]::ReadAllText((Join-Path $runtimeDir 'runtime.json')) | ConvertFrom-Json
    $result.Version = $new.product_version
    $result.Release = $new.release
    Write-NsInfo "Installer: NaviSuite $($new.product_version) (release $($new.release))."
    Write-NsInfo "Log: $log"

    # ------------------------------------------------------------ compatibility
    Write-NsStep 'Compatibility checks'
    if ($new.test_build -and -not $AcceptTestBuild) {
        Stop-NsRefused 'This is a test build: it did not pass the test suite gate and must not be installed on a vessel. (For a test PC: -AcceptTestBuild, or /TESTBUILD for the installer.)'
    }
    if ($new.test_build) { Write-NsWarn 'TEST BUILD: not for production.' }
    if ([Environment]::OSVersion.Version -lt [version]'10.0.14393') { Stop-NsRefused 'Windows 10 (1607 or later), Windows 11 or Windows Server 2016 or later is required.' }
    if (-not [Environment]::Is64BitOperatingSystem) { Stop-NsRefused 'A 64-bit Windows is required.' }
    if ($PSVersionTable.PSVersion -lt [version]'5.1') { Stop-NsRefused 'Windows PowerShell 5.1 or later is required.' }

    $installedApp = Test-Path -LiteralPath (Join-Path $paths.App 'artisan')
    $mode = if ($installedApp) { 'upgrade' } else { 'install' }
    # Uninstalled earlier with the data kept: the files are there, the
    # services are not. install-server.ps1 brings them back first.
    if ($installedApp -and -not (Test-NsService 'NaviSuite-MySQL')) { $mode = 'reinstall' }
    if ($mode -ne 'install' -and -not (Test-Path -LiteralPath (Get-NsSettingsPath $paths))) {
        Stop-NsRefused "$($paths.App) exists but $(Get-NsSettingsPath $paths) does not. This folder was not set up by the NaviSuite scripts; choose another folder or move it away."
    }
    $drive = Get-PSDrive -Name $Root.Substring(0, 1) -ErrorAction SilentlyContinue
    $needGb = if ($mode -eq 'install') { 4 } else { 2 }
    if ($drive -and ($drive.Free / 1GB) -lt $needGb) { Stop-NsRefused "At least $needGb GB must be free on $($Root.Substring(0, 2)); $([math]::Round($drive.Free / 1GB, 1)) GB is." }

    $answers = @{}
    if ($AnswerFile) {
        if (-not (Test-Path -LiteralPath $AnswerFile)) { Stop-NsRefused "Answer file not found: $AnswerFile" }
        $answers = Read-NsIni $AnswerFile
    }
    $token = $null
    if ($answers['sync.tokenfile']) {
        if (-not (Test-Path -LiteralPath $answers['sync.tokenfile'])) { Stop-NsRefused "TokenFile not found: $($answers['sync.tokenfile'])" }
        $token = ([IO.File]::ReadAllText($answers['sync.tokenfile'])).Trim()
    } elseif ($answers['sync.token']) {
        $token = $answers['sync.token']
        Write-NsWarn 'The answer file holds the sync token. Delete the file now that it has been read (or use TokenFile next time).'
    } elseif ($env:NAVISUITE_SETUP_SYNC_TOKEN) {
        $token = $env:NAVISUITE_SETUP_SYNC_TOKEN
    }
    Remove-Item Env:\NAVISUITE_SETUP_SYNC_TOKEN -ErrorAction SilentlyContinue
    if ($token -and $token -notmatch '^[A-Za-z0-9_-]{32,256}$') { Stop-NsRefused 'The sync token does not look like one issued by sync:register-node (letters and digits, at least 32).' }
    $syncGiven = $answers['sync.cloudurl'] -or $answers['sync.nodeid'] -or $token

    if ($mode -ne 'install') {
        $old = Get-NsReleaseInfo $paths.App
        $settings = Read-NsSettings $paths
        $result.Mode = $mode
        Write-NsInfo "Installed: NaviSuite $($old.product_version) (release $($old.release)) for vessel $($settings.VesselId)."
        if ($answers['server.vesselid'] -and [int]$answers['server.vesselid'] -ne [int]$settings.VesselId) {
            Stop-NsRefused "This PC is set up for vessel $($settings.VesselId), but the answer file says $($answers['server.vesselid']). A server's vessel never changes."
        }
        $oldV = ConvertTo-NsVersion $old.product_version
        $newV = ConvertTo-NsVersion $new.product_version
        if ($newV -lt $oldV -and -not $AllowDowngrade) {
            Stop-NsRefused "NaviSuite $($old.product_version) is installed, which is newer than this installer ($($new.product_version)). Use update-server.ps1 -Rollback to go back, or /ALLOWDOWNGRADE."
        }
        if ($newV -eq $oldV -and $mode -eq 'upgrade') { $result.Mode = 'repair' }
        if ($oldV -ne [version]'0.0.0' -and $new.min_upgrade_from -and $oldV -lt (ConvertTo-NsVersion $new.min_upgrade_from)) {
            Stop-NsRefused "This installer upgrades $($new.min_upgrade_from) or later; this server has $($old.product_version). Install an intermediate version first."
        }
        # MySQL is not running after an uninstall; update-server.ps1 checks
        # the schema in that case, once install-server.ps1 has started it.
        if ($mode -eq 'upgrade') {
            Start-NsServices @('NaviSuite-MySQL')
            try { Assert-NsSchemaNotNewer $paths $releaseDir } catch { Stop-NsRefused $_.Exception.Message }
        }
        Write-NsOk "Upgrade $($old.product_version) -> $($new.product_version) is possible; settings, credentials and data are kept."
    } else {
        $result.Mode = 'install'
        if (-not $answers['server.vesselid'] -or $answers['server.vesselid'] -notmatch '^[1-9][0-9]{0,9}$') {
            Stop-NsRefused 'A new server needs the vessel id (VesselId in the [server] section of the answer file).'
        }
        if (Test-Path -LiteralPath (Join-Path $paths.MySqlData 'mysql')) {
            Write-NsWarn "A MySQL data directory already exists in $($paths.MySqlData); it is kept and used."
        }
        Write-NsOk "New server for vessel $($answers['server.vesselid']) in $Root."
    }

    # ------------------------------------------------------------ runtime prerequisites
    Write-NsStep 'Visual C++ runtime'
    Install-NsVcRuntime $runtimes

    # ------------------------------------------------------------ install or upgrade
    $installScript = Join-Path $PSScriptRoot 'install-server.ps1'
    $code = 0
    if ($mode -eq 'install') {
        $params = @{
            VesselId = [int]$answers['server.vesselid']
            Root = $Root
            RuntimeSource = $runtimeDir
        }
        if ($answers['server.servername']) { $params.ServerName = $answers['server.servername'].ToLowerInvariant() }
        if ($answers['server.extranames']) { $params.ExtraNames = @($answers['server.extranames'] -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ }) }
        if ($answers['server.firewallremoteaddress']) { $params.FirewallRemoteAddress = @($answers['server.firewallremoteaddress'] -split ',' | ForEach-Object { $_.Trim() } | Where-Object { $_ }) }
        if ($answers['server.innodbbufferpoolmb']) { $params.InnoDbBufferPoolMB = [int]$answers['server.innodbbufferpoolmb'] }
        if ($answers['server.importsqldump']) { $params.ImportSqlDump = $answers['server.importsqldump'] }
        if (-not (Get-NsBool $answers 'server.firewall' $true)) { $params.SkipFirewall = $true }
        if (Get-NsBool $answers 'server.includepublicprofile' $false) { $params.IncludePublicProfile = $true }
        if (Get-NsBool $answers 'server.uselocalsystem' $false) { $params.UseLocalSystem = $true }
        $params = Add-NsSyncParams $params
        $global:LASTEXITCODE = 0
        & $installScript @params
        $code = $LASTEXITCODE
        Copy-Item -LiteralPath (Join-Path $runtimeDir 'runtime.json') -Destination (Join-Path $paths.Runtime 'runtime.json') -Force
    } elseif ($mode -eq 'reinstall') {
        Write-NsStep 'Bringing back the services of the server installed before'
        $params = Add-NsSyncParams (Get-NsSavedInstallParams)
        $params.RuntimeSource = $runtimeDir
        $global:LASTEXITCODE = 0
        & $installScript @params
        $code = $LASTEXITCODE
        if ((ConvertTo-NsVersion $new.product_version) -ne (ConvertTo-NsVersion $old.product_version) -or $new.release -ne $old.release) {
            $global:LASTEXITCODE = 0
            & (Join-Path $PSScriptRoot 'update-server.ps1') -ReleasePath $releaseDir -Root $Root -AllowDowngrade:$AllowDowngrade
            $code = $LASTEXITCODE
            if ($code -ne 0) { throw "update-server.ps1 ended with exit code $code. See the messages above." }
        }
        Write-NsStep 'Runtimes'
        Update-NsRuntimes $runtimes
        $global:LASTEXITCODE = 0
        & (Join-Path $paths.App 'deploy\windows\check-health.ps1') -Root $Root -WaitSeconds 150
        $code = $LASTEXITCODE
    } else {
        $global:LASTEXITCODE = 0
        & (Join-Path $PSScriptRoot 'update-server.ps1') -ReleasePath $releaseDir -Root $Root -AllowDowngrade:$AllowDowngrade
        $code = $LASTEXITCODE
        if ($code -ne 0) { throw "update-server.ps1 ended with exit code $code. See the messages above." }

        Write-NsStep 'Runtimes'
        Update-NsRuntimes $runtimes

        if ($syncGiven) {
            # The answer file brings sync settings for an existing server
            # (registered after it was installed): apply them with the
            # server's own saved settings, so nothing else changes.
            Write-NsStep 'Applying the sync settings from the answer file'
            $params = Add-NsSyncParams (Get-NsSavedInstallParams)
            $global:LASTEXITCODE = 0
            & (Join-Path $paths.App 'deploy\windows\install-server.ps1') @params
            $code = $LASTEXITCODE
        } else {
            $global:LASTEXITCODE = 0
            & (Join-Path $paths.App 'deploy\windows\check-health.ps1') -Root $Root -WaitSeconds 150
            $code = $LASTEXITCODE
        }
    }

    # ------------------------------------------------------------ client kit and summary
    Write-NsStep 'Desktop client for the crew PCs'
    Publish-NsClientKit

    $settings = Read-NsSettings $paths
    $result.Url = "https://$($settings.ServerName)/"
    $result.Fingerprint = Get-NsCaFingerprint $paths
    $result.Status = if ($code -eq 0) { 'ok' } else { 'warnings' }
    if ($code -ne 0) { $result.Message += 'The health check found problems; see the log. ' }
    Write-NsResult
    Write-NsStep "Done: NaviSuite $($new.product_version) ($($result.Mode)). Status: $($result.Status)."
    Stop-Transcript | Out-Null
    if ($code -ne 0) { exit 2 }
    exit 0
} catch {
    Write-NsFail $_.Exception.Message
    $result.Status = if ($script:refused) { 'refused' } else { 'failed' }
    $result.Message += $_.Exception.Message
    if (-not $script:refused -and $result.Mode -eq 'install') {
        $result.Message += ' Fix the cause and run the installer again: every step checks what is already in place and continues.'
    }
    try { Write-NsResult } catch { }
    Write-Host "    The log is $log"
    Stop-Transcript | Out-Null
    if ($script:refused) { exit 3 }
    exit 1
}
