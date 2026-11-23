<#
.SYNOPSIS
    Installs a new NaviSuite release on the vessel server, or rolls back.

.DESCRIPTION
    1. Copies the new release next to the current one (app.new) while the
       crew keeps working.
    2. Backs up the database.
    3. Stops Apache, the queue and the scheduler. MySQL keeps running.
    4. Swaps the folders: app becomes app.previous, app.new becomes app.
       .env, the data junctions and the folder rights are carried over.
    5. Runs the migrations, reinstalls change capture and rebuilds the
       configuration and view caches.
    6. Starts the services and checks /health.

    If a step before the migrations fails, the previous release is put back
    and started automatically. If a migration fails, the services stay
    stopped and the script prints how to roll back.

    -Rollback puts app.previous back. It does not undo database changes;
    restore the backup taken in step 2 for that (backup-database.ps1
    -Restore).

    Checks before anything changes (Checkpoint 8):
      - the new release is not older than the installed one (release.json
        product_version), unless -AllowDowngrade;
      - the installed release is at least the new release's
        min_upgrade_from;
      - the database holds no migration the new release lacks (a database
        already updated by a newer release is never handed to older code,
        even with -AllowDowngrade).

.PARAMETER ReleasePath
    The new release: an extracted folder or the .zip built by
    deploy/build-release.sh.

.EXAMPLE
    .\update-server.ps1 -ReleasePath D:\navisuite-2026.12.01.zip
    .\update-server.ps1 -Rollback
#>
[CmdletBinding()]
param(
    [string]$ReleasePath,
    [string]$Root = 'C:\NaviSuite',
    [switch]$SkipBackup,
    [switch]$Rollback,
    [switch]$AllowDowngrade
)

. "$PSScriptRoot\lib\NaviSuite.ps1"
Assert-NsAdministrator
$paths = Get-NsPaths $Root
# The app folder is renamed or deleted below, which Windows refuses while
# this shell's current folder is inside it.
Set-Location -LiteralPath $env:SystemRoot
$settings = Read-NsSettings $paths
$previous = "$($paths.App).previous"
$staged = "$($paths.App).new"
$failedDir = "$($paths.App).failed"
if (-not (Test-Path -LiteralPath $paths.Env)) { throw "$($paths.Env) is missing. Is NaviSuite installed in $Root?" }

# Things that keep a server installed by an older release up to date: the
# configuration files, services added since (NaviSuite-Discovery came with
# Checkpoint 4, NaviSuite-Sync with Checkpoint 5), the folder rights and the firewall rules.
function Update-NsInstallation {
    Use-NsPhpPath $paths
    Write-NsConfigs $paths $settings
    $envNow = Read-NsDotEnv $paths.Env
    if (-not $envNow['APP_TLS_CA_FILE']) { Set-NsDotEnv $paths.Env @{ APP_TLS_CA_FILE = $paths.CaCert } }
    Install-NsBackgroundServices $paths $settings
    Register-NsStartupTask $paths   # Checkpoint 8
    Set-NsAcl $paths -Part Base
    Set-NsAcl $paths -Part App -UseLocalSystem:([bool]$settings.UseLocalSystem)
    if (-not (Get-NsSetting $settings 'SkipFirewall' $false)) {
        & (Join-Path $PSScriptRoot 'configure-firewall.ps1') -Root $Root -RemoteAddress @(Get-NsSetting $settings 'FirewallRemoteAddress' @('LocalSubnet')) `
            -IncludePublicProfile:([bool](Get-NsSetting $settings 'IncludePublicProfile' $false))
    }
}

function Invoke-Finish {
    Use-NsPhpPath $paths
    Write-NsConfigs $paths $settings
    Set-NsAcl $paths -Part Base
    Set-NsAcl $paths -Part App -UseLocalSystem:([bool]$settings.UseLocalSystem)
    Invoke-NsArtisan $paths @('config:cache') -Quiet | Out-Null
    Invoke-NsArtisan $paths @('view:cache') -Quiet | Out-Null
    Invoke-NsNative $paths.HttpdExe @('-t', '-f', $paths.HttpdConf) -Quiet | Out-Null
    Start-NsServices $script:NsAppServices
    & (Join-Path $paths.App 'deploy\windows\check-health.ps1') -Root $Root -WaitSeconds 150
}

# ================================================================== rollback
if ($Rollback) {
    if (-not (Test-Path -LiteralPath (Join-Path $previous 'artisan'))) { throw "There is no previous release in $previous." }
    $from = Get-NsRelease $paths.App; $to = Get-NsRelease $previous
    Write-NsStep "Rolling back from $from to $to"
    Stop-NsServices $script:NsAppServices
    Remove-NsAppFolder $paths $failedDir
    Rename-Item -LiteralPath $paths.App -NewName (Split-Path -Leaf $failedDir)
    Rename-Item -LiteralPath $previous -NewName (Split-Path -Leaf $paths.App)
    Copy-Item -LiteralPath (Join-Path $failedDir '.env') -Destination $paths.Env -Force
    Set-NsDotEnv $paths.Env @{ APP_RELEASE = $to }
    Set-NsDataJunctions $paths
    Invoke-Finish
    Write-NsWarn "Release $to is running again. The database was not changed back. If the update had migrated it, restore the backup from before the update with backup-database.ps1 -Restore."
    exit $LASTEXITCODE
}

# ================================================================== update
if (-not $ReleasePath) { throw 'Give -ReleasePath (the new release folder or .zip), or -Rollback.' }
if (-not (Test-Path -LiteralPath $ReleasePath)) { throw "Release not found: $ReleasePath" }

$unpacked = $null
$source = (Resolve-Path $ReleasePath).Path
if ($source -like '*.zip') {
    $unpacked = Join-Path $paths.Tmp ('release-' + [guid]::NewGuid().ToString('N'))
    Write-NsStep "Unpacking $source"
    Expand-Archive -LiteralPath $source -DestinationPath $unpacked
    $source = $unpacked
    $inner = @(Get-ChildItem -LiteralPath $unpacked -Directory)
    if (-not (Test-Path -LiteralPath (Join-Path $source 'artisan')) -and $inner.Count -eq 1) { $source = $inner[0].FullName }
}

try {
    foreach ($f in @('artisan', 'RELEASE', 'vendor\autoload.php', 'deploy\windows\templates\httpd.conf')) {
        if (-not (Test-Path -LiteralPath (Join-Path $source $f))) { throw "Not a NaviSuite release: $source\$f is missing." }
    }
    $current = Get-NsRelease $paths.App
    $release = Get-NsRelease $source
    Write-NsStep "Updating from $current to $release"
    if ($release -eq $current) { Write-NsWarn "Release $release is already installed. Continuing reinstalls it." }

    # Checkpoint 8: version compatibility, before anything is changed.
    $oldInfo = Get-NsReleaseInfo $paths.App
    $newInfo = Get-NsReleaseInfo $source
    $oldVersion = ConvertTo-NsVersion $oldInfo.product_version
    $newVersion = ConvertTo-NsVersion $newInfo.product_version
    if ($newVersion -lt $oldVersion -and -not $AllowDowngrade) {
        throw "Version $($newInfo.product_version) is older than the installed $($oldInfo.product_version). To go back to the previous release use -Rollback; to install an older one anyway add -AllowDowngrade."
    }
    if ($newInfo.min_upgrade_from -and $oldVersion -lt (ConvertTo-NsVersion $newInfo.min_upgrade_from) -and $oldVersion -ne [version]'0.0.0') {
        throw "Version $($newInfo.product_version) can only be installed over $($newInfo.min_upgrade_from) or later; this server has $($oldInfo.product_version). Install an intermediate release first."
    }
    Assert-NsSchemaNotNewer $paths $source
    Write-NsOk "Version $($oldInfo.product_version) -> $($newInfo.product_version); the database schema is compatible."

    Write-NsInfo "Copying the release to $staged"
    Remove-NsAppFolder $paths $staged
    Copy-NsTree $source $staged -ExcludeFiles @('.env')
} finally {
    if ($unpacked) { & cmd.exe /c rmdir /s /q "$unpacked" }
}

$backup = $null
if (-not $SkipBackup) {
    $backup = & (Join-Path $PSScriptRoot 'backup-database.ps1') -Root $Root -Reason 'before-update' | Select-Object -Last 1
}

Write-NsStep 'Switching releases'
Stop-NsServices $script:NsAppServices
$swapped = $false
try {
    Remove-NsAppFolder $paths $previous
    Rename-Item -LiteralPath $paths.App -NewName (Split-Path -Leaf $previous)
    Rename-Item -LiteralPath $staged -NewName (Split-Path -Leaf $paths.App)
    $swapped = $true
    Copy-Item -LiteralPath (Join-Path $previous '.env') -Destination $paths.Env -Force
    Set-NsDotEnv $paths.Env @{ APP_RELEASE = $release }
    Set-NsDataJunctions $paths
    Update-NsInstallation
    Invoke-NsNative $paths.HttpdExe @('-t', '-f', $paths.HttpdConf) -Quiet | Out-Null
    Invoke-NsArtisan $paths @('config:clear') -Quiet | Out-Null
} catch {
    Write-NsFail $_.Exception.Message
    Write-NsWarn "Putting release $current back."
    if ($swapped) {
        Remove-NsAppFolder $paths $failedDir
        Rename-Item -LiteralPath $paths.App -NewName (Split-Path -Leaf $failedDir)
        Rename-Item -LiteralPath $previous -NewName (Split-Path -Leaf $paths.App)
        Copy-Item -LiteralPath (Join-Path $failedDir '.env') -Destination $paths.Env -Force
        Set-NsDotEnv $paths.Env @{ APP_RELEASE = $current }
        Set-NsDataJunctions $paths
    }
    Write-NsConfigs $paths $settings
    Start-NsServices $script:NsAppServices
    throw "The update was not applied; release $current is running. Nothing in the database was changed."
}

Write-NsStep 'Database'
try {
    Invoke-NsArtisan $paths @('migrate', '--force') | Out-Null
    Invoke-NsArtisan $paths @('sync:triggers', 'install') | Out-Null
} catch {
    Write-NsFail $_.Exception.Message
    Write-Host ''
    Write-NsWarn 'The migrations failed. Apache and the background services are stopped, so nobody writes to a half-updated database.'
    Write-NsInfo 'To go back to the previous release:'
    if ($backup) { Write-NsInfo "  .\backup-database.ps1 -Restore `"$backup`"" }
    Write-NsInfo '  .\update-server.ps1 -Rollback'
    Write-NsInfo "Details are in $($paths.Storage)\logs."
    exit 1
}

Write-NsStep 'Starting'
Invoke-Finish
$code = $LASTEXITCODE
if ($code -eq 0) { Write-NsOk "Release $release is running. The previous release stays in $previous for -Rollback." }
exit $code
