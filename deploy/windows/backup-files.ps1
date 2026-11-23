<#
.SYNOPSIS
    Backs up (or restores) the NaviSuite vessel server's files: attachments,
    photos, signatures and certificate copies.

.DESCRIPTION
    The database backup (backup-database.ps1) holds the records; the files
    they name live on disk and are backed up here:

      data\storage\app\attachments   requisition, consumption and procurement attachments
      data\images\cert_copy          certificate copies
      data\images\signature          signatures
      data\images\userphoto          photos

    Each backup is a manifest, data\backups\files\manifests\files-<time>-<reason>.json,
    listing every file with its size and SHA-256. The contents are kept once
    each, named by their SHA-256, in data\backups\files\objects, so a daily
    backup only copies what is new, and a file that is later damaged or
    overwritten never replaces its earlier, good copy. The manifest's own
    checksum is in a .sha256 file beside it.

    -Restore puts the files of one manifest back, after checking every one
    of them against its SHA-256. Files that are not in the manifest are left
    alone. The application services are stopped meanwhile, and a backup of
    the files as they are is taken first.

    -Verify checks a manifest and its objects without restoring anything.

    -Export copies a manifest and its objects (and, with -WithDatabase, the
    newest database backup) to another folder, for example a USB drive kept
    off the PC: the backups in data\backups do not survive the loss of the
    disk.

.EXAMPLE
    .\backup-files.ps1
    .\backup-files.ps1 -Verify  C:\NaviSuite\data\backups\files\manifests\files-20261121-023500-daily.json
    .\backup-files.ps1 -Restore C:\NaviSuite\data\backups\files\manifests\files-20261121-023500-daily.json
    .\backup-files.ps1 -Export E:\NaviSuite-backup -WithDatabase
#>
[CmdletBinding(SupportsShouldProcess = $true, ConfirmImpact = 'High')]
param(
    [string]$Root = 'C:\NaviSuite',
    [string]$Reason = 'manual',
    [ValidateRange(1, 3650)][int]$KeepDays = 30,
    [ValidateRange(1, 1000)][int]$KeepAtLeast = 5,
    [string]$Restore,
    [string]$Verify,
    [string]$Export,
    [switch]$WithDatabase
)

. "$PSScriptRoot\lib\NaviSuite.ps1"
Assert-NsAdministrator
$paths = Get-NsPaths $Root
if ($Reason -notmatch '^[A-Za-z0-9-]+$') { throw 'The reason may contain letters, digits and hyphens only.' }

$store = Join-Path $paths.Backups 'files'
$objects = Join-Path $store 'objects'
$manifests = Join-Path $store 'manifests'
foreach ($d in @($store, $objects, $manifests)) { if (-not (Test-Path -LiteralPath $d)) { New-Item -ItemType Directory -Path $d | Out-Null } }

# What is backed up: the name inside the manifest => the folder on this PC.
$sources = [ordered]@{
    'storage/app/attachments' = (Join-Path $paths.Storage 'app\attachments')
    'images/cert_copy'        = (Join-Path $paths.Images 'cert_copy')
    'images/signature'        = (Join-Path $paths.Images 'signature')
    'images/userphoto'        = (Join-Path $paths.Images 'userphoto')
}

function Get-ObjectPath([string]$Sha) { return (Join-Path (Join-Path $objects $Sha.Substring(0, 2)) $Sha) }

function Get-Sha256([string]$File) { return (Get-FileHash -LiteralPath $File -Algorithm SHA256).Hash.ToLowerInvariant() }

function Read-Manifest([string]$File) {
    if (-not (Test-Path -LiteralPath $File)) { throw "Manifest not found: $File" }
    $sumFile = "$File.sha256"
    if (Test-Path -LiteralPath $sumFile) {
        $expected = ([IO.File]::ReadAllText($sumFile)).Trim().Split(' ')[0].ToLowerInvariant()
        if ((Get-Sha256 $File) -ne $expected) { throw "The manifest $File does not match its checksum: it was changed or damaged." }
    } else {
        Write-NsWarn "No checksum file for $File."
    }
    return ([IO.File]::ReadAllText($File) | ConvertFrom-Json)
}

function New-FileBackup([string]$Why) {
    Write-NsStep 'Backing up files'
    # The previous manifest: a file with the same size and time is not read again.
    $previous = @{}
    $last = Get-ChildItem -LiteralPath $manifests -Filter 'files-*.json' -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if ($last) {
        try { foreach ($f in (Read-Manifest $last.FullName).files) { $previous[$f.path] = $f } } catch { Write-NsWarn "Previous manifest not used: $($_.Exception.Message)" }
    }

    $entries = New-Object Collections.Generic.List[object]
    $copied = 0; $bytes = [long]0; $total = [long]0
    foreach ($name in $sources.Keys) {
        $dir = $sources[$name]
        if (-not (Test-Path -LiteralPath $dir)) { continue }
        foreach ($file in Get-ChildItem -LiteralPath $dir -File -Recurse -Force) {
            $rel = $name + '/' + ($file.FullName.Substring($dir.Length).TrimStart('\') -replace '\\', '/')
            $ticks = $file.LastWriteTimeUtc.Ticks
            $p = $previous[$rel]
            $sha = if ($p -and [long]$p.size -eq $file.Length -and [long]$p.mtime -eq $ticks -and (Test-Path -LiteralPath (Get-ObjectPath $p.sha256))) { $p.sha256 } else { Get-Sha256 $file.FullName }
            $object = Get-ObjectPath $sha
            if (-not (Test-Path -LiteralPath $object)) {
                $objDir = Split-Path -Parent $object
                if (-not (Test-Path -LiteralPath $objDir)) { New-Item -ItemType Directory -Path $objDir | Out-Null }
                $tmp = "$object.tmp"
                Copy-Item -LiteralPath $file.FullName -Destination $tmp -Force
                if ((Get-Sha256 $tmp) -ne $sha) { Remove-Item -LiteralPath $tmp -Force; throw "$($file.FullName) changed while it was copied. Run the backup again." }
                Move-Item -LiteralPath $tmp -Destination $object -Force
                $copied++; $bytes += $file.Length
            }
            $total += $file.Length
            $entries.Add([ordered]@{ path = $rel; size = $file.Length; sha256 = $sha; mtime = $ticks })
        }
    }

    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $manifest = Join-Path $manifests "files-$stamp-$Why.json"
    $doc = [ordered]@{ format = 'navisuite-files-1'; created = (Get-Date).ToString('o'); computer = $env:COMPUTERNAME; files = $entries }
    Write-NsFile $manifest ($doc | ConvertTo-Json -Depth 5 -Compress)
    Write-NsFile "$manifest.sha256" ((Get-Sha256 $manifest) + "  " + (Split-Path -Leaf $manifest) + "`n")
    Write-NsOk "${manifest}: $($entries.Count) file(s), $([math]::Round($total / 1MB, 1)) MB; $copied new ($([math]::Round($bytes / 1MB, 1)) MB copied)."
    return $manifest
}

# Every object of the manifest is there and matches its SHA-256.
function Test-FileBackup([string]$Manifest) {
    $doc = Read-Manifest $Manifest
    $bad = @()
    foreach ($f in $doc.files) {
        $object = Get-ObjectPath $f.sha256
        if (-not (Test-Path -LiteralPath $object)) { $bad += "$($f.path): missing from the backup" }
        elseif ((Get-Sha256 $object) -ne $f.sha256) { $bad += "$($f.path): damaged in the backup" }
    }
    return [pscustomobject]@{ Files = @($doc.files).Count; Bad = $bad; Doc = $doc }
}

function Remove-UnusedObjects {
    $used = New-Object 'Collections.Generic.HashSet[string]'
    foreach ($m in Get-ChildItem -LiteralPath $manifests -Filter 'files-*.json') {
        foreach ($f in (Read-Manifest $m.FullName).files) { [void]$used.Add($f.sha256) }
    }
    $removed = 0
    foreach ($o in Get-ChildItem -LiteralPath $objects -File -Recurse) {
        if ($o.Name -like '*.tmp' -or -not $used.Contains($o.Name)) { Remove-Item -LiteralPath $o.FullName -Force; $removed++ }
    }
    if ($removed) { Write-NsInfo "Removed $removed file copies no backup refers to any more." }
}

# ------------------------------------------------------------------ verify
if ($Verify) {
    $result = Test-FileBackup $Verify
    if ($result.Bad.Count) { $result.Bad | ForEach-Object { Write-NsFail $_ }; throw "$($result.Bad.Count) of $($result.Files) file(s) cannot be restored from this backup." }
    Write-NsOk "All $($result.Files) file(s) of $Verify are in the backup and match their SHA-256."
    return
}

# ------------------------------------------------------------------ export
if ($Export) {
    $manifest = Get-ChildItem -LiteralPath $manifests -Filter 'files-*.json' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $manifest) { throw 'No file backup to export. Run this script without -Export first.' }
    $result = Test-FileBackup $manifest.FullName
    if ($result.Bad.Count) { throw "The newest file backup is incomplete: $($result.Bad[0])" }
    $targetStore = Join-Path $Export 'files'
    foreach ($d in @((Join-Path $targetStore 'objects'), (Join-Path $targetStore 'manifests'))) { if (-not (Test-Path -LiteralPath $d)) { New-Item -ItemType Directory -Path $d -Force | Out-Null } }
    foreach ($f in $result.Doc.files) {
        $to = Join-Path (Join-Path (Join-Path $targetStore 'objects') $f.sha256.Substring(0, 2)) $f.sha256
        if (-not (Test-Path -LiteralPath $to)) {
            if (-not (Test-Path -LiteralPath (Split-Path -Parent $to))) { New-Item -ItemType Directory -Path (Split-Path -Parent $to) | Out-Null }
            Copy-Item -LiteralPath (Get-ObjectPath $f.sha256) -Destination $to
        }
    }
    Copy-Item -LiteralPath $manifest.FullName, "$($manifest.FullName).sha256" -Destination (Join-Path $targetStore 'manifests') -Force
    Write-NsOk "Files exported to $targetStore ($($result.Files) file(s), manifest $($manifest.Name))."
    if ($WithDatabase) {
        $db = Get-ChildItem -LiteralPath $paths.Backups -Filter 'navisuite-*.zip' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
        if (-not $db) { Write-NsWarn 'No database backup to export. Run backup-database.ps1.' }
        else {
            Copy-Item -LiteralPath $db.FullName -Destination $Export -Force
            Write-NsFile (Join-Path $Export "$($db.Name).sha256") ((Get-Sha256 $db.FullName) + "  $($db.Name)`n")
            Write-NsOk "Database backup $($db.Name) exported."
        }
    }
    Write-NsInfo 'Keep the export away from this PC. It holds the crew''s documents: store it as you would the ship''s papers.'
    return
}

# ------------------------------------------------------------------ backup
if (-not $Restore) {
    $manifest = New-FileBackup $Reason
    $all = @(Get-ChildItem -LiteralPath $manifests -Filter 'files-*.json' | Sort-Object LastWriteTime -Descending)
    $old = @($all | Select-Object -Skip $KeepAtLeast | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$KeepDays) })
    foreach ($m in $old) {
        Remove-Item -LiteralPath $m.FullName, "$($m.FullName).sha256" -Force -ErrorAction SilentlyContinue
        Write-NsInfo "Deleted old file backup $($m.Name)"
    }
    Remove-UnusedObjects
    return $manifest
}

# ------------------------------------------------------------------ restore
$check = Test-FileBackup $Restore
if ($check.Bad.Count) {
    $check.Bad | Select-Object -First 20 | ForEach-Object { Write-NsFail $_ }
    throw "$($check.Bad.Count) of $($check.Files) file(s) in this backup are missing or damaged. Nothing was restored. Use an older backup, or an export."
}
$target = "files of $env:COMPUTERNAME"
if (-not $PSCmdlet.ShouldProcess($target, "Put back the $($check.Files) file(s) of $Restore")) { return }

$safety = New-FileBackup 'before-restore'
Stop-NsServices $script:NsAppServices
$restored = 0
try {
    foreach ($f in $check.Doc.files) {
        $name = $sources.Keys | Where-Object { $f.path.StartsWith($_ + '/') } | Select-Object -First 1
        if (-not $name) { Write-NsWarn "Skipped $($f.path): not a folder this script restores."; continue }
        $relative = $f.path.Substring($name.Length + 1)
        if ($relative -match '(^|/)\.\.(/|$)' -or $relative -match '[:*?"<>|]') { Write-NsWarn "Skipped $($f.path): unsafe name."; continue }
        $to = Join-Path $sources[$name] ($relative -replace '/', '\')
        if ((Test-Path -LiteralPath $to) -and (Get-Sha256 $to) -eq $f.sha256) { continue }
        $dir = Split-Path -Parent $to
        if (-not (Test-Path -LiteralPath $dir)) { New-Item -ItemType Directory -Path $dir | Out-Null }
        Copy-Item -LiteralPath (Get-ObjectPath $f.sha256) -Destination $to -Force
        $restored++
    }
    Write-NsOk "$restored file(s) put back; the others were already as in the backup."
} catch {
    Write-NsFail $_.Exception.Message
    Write-NsWarn "The files as they were before the restore are in $safety."
    throw
} finally {
    Start-NsServices $script:NsAppServices
}

# The application re-reads every file against the checksums it knows.
Invoke-NsArtisan $paths @('sync:files', 'scan') -AllowFailure | Out-Null
$code = Invoke-NsArtisan $paths @('sync:files', 'verify') -AllowFailure
if ($code -ne 0) { Write-NsWarn 'Some records name files that are still missing or damaged (see above). On a vessel, "artisan.cmd sync:files verify --refetch" fetches them from the cloud.' }
