<#
.SYNOPSIS
    Run by the "NaviSuite Startup Check" task a few minutes after Windows
    starts. Starts any NaviSuite service that is still stopped, then checks
    /health, and writes what it found to data\logs\startup.

.DESCRIPTION
    Every NaviSuite service is set to start with Windows and to restart
    itself after a failure, so this normally finds nothing to do. It exists
    for the cases where that is not enough: a service that gave up while
    MySQL was still recovering after a power cut, or one an administrator
    stopped and forgot. The log shows each boot, so "did it come back after
    the reboot?" can be answered later.

    Exit code: 0 when everything runs and /health answers; 1 otherwise.

.EXAMPLE
    .\startup-check.ps1
#>
[CmdletBinding()]
param(
    [string]$Root = 'C:\NaviSuite',
    [int]$WaitSeconds = 120
)

. "$PSScriptRoot\lib\NaviSuite.ps1"
$paths = Get-NsPaths $Root
$logDir = Join-Path $paths.Logs 'startup'
if (-not (Test-Path -LiteralPath $logDir)) { New-Item -ItemType Directory -Path $logDir -Force | Out-Null }
$log = Join-Path $logDir ('startup-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.log')
Remove-NsOldFiles $logDir 'startup-*.log' 60

$lines = New-Object Collections.Generic.List[string]
function Note([string]$Text) { $lines.Add("$((Get-Date).ToString('s')) $Text"); Write-Host $Text }

$boot = $null
try { $boot = (Get-CimInstance Win32_OperatingSystem).LastBootUpTime } catch { }
Note "Windows started at $boot. Release $(Get-NsRelease $paths.App)."

$problems = 0
foreach ($name in $script:NsServiceNames) {
    $svc = Get-Service -Name $name -ErrorAction SilentlyContinue
    if (-not $svc) { Note "MISSING  $name is not installed."; $problems++; continue }
    if ($svc.StartType -eq 'Disabled') { Note "SKIPPED  $name is disabled."; continue }
    if ($svc.Status -eq 'Running') { Note "OK       $name is running."; continue }
    Note "STARTING $name was $($svc.Status)."
    try {
        Start-Service -Name $name -ErrorAction Stop
        $svc.WaitForStatus('Running', [TimeSpan]::FromSeconds(90))
        Note "OK       $name started."
    } catch {
        Note "FAILED   $name did not start: $($_.Exception.Message)"
        $problems++
    }
}

$deadline = (Get-Date).AddSeconds($WaitSeconds)
$health = $null
do {
    $health = Get-NsHealth 10
    if ($health.Code -eq 200) { break }
    Start-Sleep -Seconds 5
} while ((Get-Date) -lt $deadline)
if ($health.Code -eq 200) {
    Note "OK       /health answers '$($health.Body.status)'."
} else {
    Note "FAILED   /health did not answer 200 (got $($health.Code)). Run check-health.ps1."
    $problems++
}

[IO.File]::WriteAllLines($log, $lines)
if ($problems) { exit 1 }
exit 0
