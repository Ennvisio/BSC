<#
.SYNOPSIS
    Daily maintenance of the NaviSuite vessel server.

.DESCRIPTION
    Run by the scheduled task "NaviSuite Maintenance" every day at 02:30 as
    SYSTEM (and at the next start if the PC was off then):

      1. Backs up the database (backup-database.ps1, 14 days kept) and the
         files (backup-files.ps1, 30 days kept, each file stored once).
      2. Renews the HTTPS certificate if it expires within 60 days or the
         PC's IP addresses changed, and restarts Apache if it did.
      3. Deletes temporary upload files older than two days and its own
         logs older than 30 days.

    Its output goes to data\logs\maintenance\maintenance-<date>.log.
#>
[CmdletBinding()]
param([string]$Root = 'C:\NaviSuite')

. "$PSScriptRoot\lib\NaviSuite.ps1"
$paths = Get-NsPaths $Root
$logDir = Join-Path $paths.Logs 'maintenance'
if (-not (Test-Path -LiteralPath $logDir)) { New-Item -ItemType Directory -Path $logDir | Out-Null }
Start-Transcript -Path (Join-Path $logDir ("maintenance-" + (Get-Date -Format 'yyyyMMdd') + '.log')) -Append | Out-Null

$failed = $false
try {
    try { & (Join-Path $PSScriptRoot 'backup-database.ps1') -Root $Root -Reason 'daily' }
    catch { Write-NsFail "Backup failed: $($_.Exception.Message)"; $failed = $true }

    # Checkpoint 7: the files the records name (attachments, signatures...).
    try { & (Join-Path $PSScriptRoot 'backup-files.ps1') -Root $Root -Reason 'daily' | Out-Null }
    catch { Write-NsFail "File backup failed: $($_.Exception.Message)"; $failed = $true }

    try { & (Join-Path $PSScriptRoot 'new-certificate.ps1') -Root $Root -RenewWithinDays 60 -RestartWeb }
    catch { Write-NsFail "Certificate renewal failed: $($_.Exception.Message)"; $failed = $true }

    Get-ChildItem -LiteralPath $paths.Tmp -File -Force -ErrorAction SilentlyContinue |
        Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-2) } | Remove-Item -Force -ErrorAction SilentlyContinue
    Get-ChildItem -LiteralPath $logDir -Filter 'maintenance-*.log' |
        Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-30) } | Remove-Item -Force -ErrorAction SilentlyContinue
} finally {
    Stop-Transcript | Out-Null
}
if ($failed) { exit 1 }
