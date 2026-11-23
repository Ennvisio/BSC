<#
.SYNOPSIS
    Removes the NaviSuite vessel server from this PC.

.DESCRIPTION
    Stops and removes the six services, the firewall rules, the daily
    maintenance task and the startup check task. All files are kept, including the database, the
    uploaded files, the backups, .env and the certificates, so running
    install-server.ps1 again brings the server back as it was.

    -RemoveData also deletes the whole install folder, including the
    database and every backup in it. It asks for confirmation first. Copy
    data\backups elsewhere before using it.

.EXAMPLE
    .\uninstall-server.ps1
    .\uninstall-server.ps1 -RemoveData
    .\uninstall-server.ps1 -RemoveData -Force    (no prompt; used by the uninstaller)
#>
[CmdletBinding(SupportsShouldProcess = $true, ConfirmImpact = 'High')]
param(
    [string]$Root = 'C:\NaviSuite',
    [switch]$RemoveData,
    # Checkpoint 8: the uninstaller asks the person itself (twice) before
    # passing -RemoveData, and runs without a console to answer a prompt.
    [switch]$Force
)

. "$PSScriptRoot\lib\NaviSuite.ps1"
Assert-NsAdministrator
$paths = Get-NsPaths $Root
# The app folder is renamed or deleted below, which Windows refuses while
# this shell's current folder is inside it.
Set-Location -LiteralPath $env:SystemRoot

if ($RemoveData -and -not $Force -and -not $PSCmdlet.ShouldProcess($Root, 'Delete NaviSuite completely, including the database and all backups')) { return }

Write-NsStep 'Stopping services'
Stop-NsServices ($script:NsAppServices + 'NaviSuite-MySQL')

Write-NsStep 'Removing services'
foreach ($svc in $script:NsWinSwServices) {
    $exe = Join-Path $paths.WinSw "$svc.exe"
    if (Test-NsService $svc) {
        if (Test-Path -LiteralPath $exe) { Invoke-NsNative $exe @('uninstall') -AllowFailure -Quiet | Out-Null }
        if (Test-NsService $svc) { Invoke-NsNative 'sc.exe' @('delete', $svc) -AllowFailure -Quiet | Out-Null }
        Write-NsOk "$svc removed."
    }
}
if (Test-NsService 'NaviSuite-Web') {
    if (Test-Path -LiteralPath $paths.HttpdExe) { Invoke-NsNative $paths.HttpdExe @('-k', 'uninstall', '-n', 'NaviSuite-Web') -AllowFailure -Quiet | Out-Null }
    if (Test-NsService 'NaviSuite-Web') { Invoke-NsNative 'sc.exe' @('delete', 'NaviSuite-Web') -AllowFailure -Quiet | Out-Null }
    Write-NsOk 'NaviSuite-Web removed.'
}
if (Test-NsService 'NaviSuite-MySQL') {
    if (Test-Path -LiteralPath $paths.MySqldExe) { Invoke-NsNative $paths.MySqldExe @('--remove', 'NaviSuite-MySQL') -AllowFailure -Quiet | Out-Null }
    if (Test-NsService 'NaviSuite-MySQL') { Invoke-NsNative 'sc.exe' @('delete', 'NaviSuite-MySQL') -AllowFailure -Quiet | Out-Null }
    Write-NsOk 'NaviSuite-MySQL removed.'
}

Write-NsStep 'Firewall rules and maintenance task'
Get-NetFirewallRule -Group 'NaviSuite' -ErrorAction SilentlyContinue | Remove-NetFirewallRule
Unregister-ScheduledTask -TaskName 'NaviSuite Maintenance' -Confirm:$false -ErrorAction SilentlyContinue
Unregister-ScheduledTask -TaskName 'NaviSuite Startup Check' -Confirm:$false -ErrorAction SilentlyContinue
Write-NsOk 'Removed.'

if (-not $RemoveData) {
    Write-Host ''
    Write-NsInfo "All files are kept in $Root (database in $($paths.MySqlData), backups in $($paths.Backups))."
    Write-NsInfo 'Run install-server.ps1 again to bring the server back, or use -RemoveData to delete everything.'
    return
}

Write-NsStep "Deleting $Root"
foreach ($folder in @($paths.App, "$($paths.App).previous", "$($paths.App).new", "$($paths.App).failed")) { Remove-NsAppFolder $paths $folder }
& cmd.exe /c rmdir /s /q "$Root"
if (Test-Path -LiteralPath $Root) { throw "Some files in $Root could not be deleted. Close any program using them and delete the folder by hand." }
Write-NsOk "$Root deleted."
