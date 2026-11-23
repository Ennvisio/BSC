<#
.SYNOPSIS
    Backs up, or restores, the NaviSuite database on the vessel server.

.DESCRIPTION
    Backup (the default) writes a consistent mysqldump of the application
    database, without stopping anything, to
    data\backups\navisuite-<date>-<time>-<reason>.zip. Backups older than
    -KeepDays are deleted, but the newest -KeepAtLeast are always kept.

    The backups are on the same disk as the database. They protect against
    mistakes and failed updates, not against the loss of the PC. Copy
    data\backups to other media regularly until sync to the cloud is live.

    Uploaded files (data\storage and data\images) are not in these backups.

    -Restore replaces the whole database with a backup. It stops the
    application services, takes a safety backup first and asks for
    confirmation unless -Confirm:$false is given.

.EXAMPLE
    .\backup-database.ps1
    .\backup-database.ps1 -Restore C:\NaviSuite\data\backups\navisuite-20261120-023000-daily.zip
#>
[CmdletBinding(SupportsShouldProcess = $true, ConfirmImpact = 'High')]
param(
    [string]$Root = 'C:\NaviSuite',
    [string]$Reason = 'manual',
    [ValidateRange(1, 3650)][int]$KeepDays = 14,
    [ValidateRange(1, 1000)][int]$KeepAtLeast = 3,
    [string]$Restore
)

. "$PSScriptRoot\lib\NaviSuite.ps1"
Assert-NsAdministrator
$paths = Get-NsPaths $Root
$envNow = Read-NsDotEnv $paths.Env
$dbName = if ($envNow['VESSEL_DB_DATABASE']) { $envNow['VESSEL_DB_DATABASE'] } else { 'navisuite' }
if ($dbName -notmatch '^[A-Za-z0-9_]+$') { throw "Invalid database name '$dbName'." }
if ($Reason -notmatch '^[A-Za-z0-9-]+$') { throw 'The reason may contain letters, digits and hyphens only.' }

function New-Backup([string]$Why) {
    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $sql = Join-Path $paths.Backups "navisuite-$stamp-$Why.sql"
    $zip = [IO.Path]::ChangeExtension($sql, '.zip')
    Write-NsStep "Backing up database '$dbName'"
    # --single-transaction: a consistent copy while the crew keeps working.
    Use-NsRootCnf $paths {
        param($cnf)
        Invoke-NsNative $paths.MySqlDumpExe @("--defaults-extra-file=$cnf", '--single-transaction', '--quick',
            '--routines', '--triggers', '--events', '--hex-blob', '--default-character-set=utf8mb4',
            '--set-gtid-purged=OFF', "--result-file=$sql", '--databases', $dbName) -Quiet | Out-Null
    } | Out-Null
    Compress-Archive -LiteralPath $sql -DestinationPath $zip -CompressionLevel Optimal -Force
    Remove-Item -LiteralPath $sql -Force
    $mb = [math]::Round((Get-Item -LiteralPath $zip).Length / 1MB, 1)
    Write-NsOk "$zip ($mb MB)"
    return $zip
}

if (-not $Restore) {
    $zip = New-Backup $Reason
    # Retention.
    $all = @(Get-ChildItem -LiteralPath $paths.Backups -Filter 'navisuite-*.zip' | Sort-Object LastWriteTime -Descending)
    $old = @($all | Select-Object -Skip $KeepAtLeast | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$KeepDays) })
    foreach ($f in $old) { Remove-Item -LiteralPath $f.FullName -Force; Write-NsInfo "Deleted old backup $($f.Name)" }
    $free = [math]::Round((Get-PSDrive -Name ($paths.Root.Substring(0, 1))).Free / 1GB, 1)
    Write-NsInfo "$($all.Count - $old.Count) backup(s) kept. $free GB free on the drive."
    return $zip
}

# ------------------------------------------------------------------ restore
if (-not (Test-Path -LiteralPath $Restore)) { throw "Backup not found: $Restore" }
$target = "database '$dbName' on $env:COMPUTERNAME"
if (-not $PSCmdlet.ShouldProcess($target, "Replace with the contents of $Restore")) { return }

$safety = New-Backup 'before-restore'
$work = Join-Path $paths.Tmp ('restore-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $work | Out-Null
try {
    $sql = $Restore
    if ($Restore -like '*.zip') {
        Expand-Archive -LiteralPath $Restore -DestinationPath $work
        $sql = (Get-ChildItem -LiteralPath $work -Filter '*.sql' | Select-Object -First 1).FullName
        if (-not $sql) { throw 'The backup archive holds no .sql file.' }
    }
    Stop-NsServices $script:NsAppServices
    Write-NsStep "Restoring $Restore"
    Invoke-NsMySqlRoot $paths "DROP DATABASE IF EXISTS ``$dbName``; CREATE DATABASE ``$dbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null
    Use-NsRootCnf $paths { param($cnf) Invoke-NsWithInput $paths.MySqlExe @("--defaults-extra-file=$cnf", '--default-character-set=utf8mb4', $dbName) $sql }
    Write-NsOk 'Database restored. (The user and its rights are kept by MySQL.)'
    Invoke-NsArtisan $paths @('cache:clear') -Quiet -AllowFailure | Out-Null
} catch {
    Write-NsFail $_.Exception.Message
    Write-NsWarn "The safety backup taken before the restore is $safety."
    throw
} finally {
    & cmd.exe /c rmdir /s /q "$work"
    Start-NsServices $script:NsAppServices
}
