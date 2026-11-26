<#
.SYNOPSIS
    Builds the runtime folder that NaviSuite-Server-Setup.exe carries:
    Apache, PHP, MySQL, WinSW, the CA bundle and the Visual C++ runtime.
    Run on a Windows build PC, not on a vessel.

.DESCRIPTION
    Download these into one folder from the official sites (nothing is
    downloaded by this script, so what goes into the installer is exactly
    what a person chose):

      Apache 2.4, Win64, from https://www.apachelounge.com/download/
          httpd-2.4.*-win64-VS17.zip
      PHP 8.2 or later, VS16/VS17 x64 Thread Safe, from https://windows.php.net/download/
          php-8.*-Win32-vs1*-x64.zip           (not the "nts" one)
      MySQL Community Server 8.4 LTS, Windows ZIP Archive, from https://dev.mysql.com/downloads/mysql/
          mysql-8.4.*-winx64.zip               (not the debug and test suite)
      WinSW 2.12, from https://github.com/winsw/winsw/releases/tag/v2.12.0
          WinSW-x64.exe
      The Mozilla CA bundle, from https://curl.se/ca/cacert.pem
          cacert.pem
      Microsoft Visual C++ 2015-2022 Redistributable x64, from https://aka.ms/vs/17/release/vc_redist.x64.exe
          vc_redist.x64.exe

    Every file is checked against deploy\windows\runtime.lock.json. A file
    not listed there, or with a different SHA-256, stops the script. With
    -Pin the files found are recorded in the lock file instead: do that once
    per new version, after checking the downloads against the checksums the
    sites publish, and commit the lock file, so every later build uses
    exactly these files.

    The result (-Output) has the layout install-server.ps1 expects, plus
    runtime.json with each program's version as the program itself reports
    it. Copy that folder to the machine that runs deploy/build-installers.sh
    (--runtime).

.EXAMPLE
    .\prepare-runtime.ps1 -Downloads D:\downloads -Output D:\navisuite-runtime
    .\prepare-runtime.ps1 -Downloads D:\downloads -Output D:\navisuite-runtime -Pin
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string]$Downloads,
    [Parameter(Mandatory = $true)][string]$Output,
    [switch]$Pin
)

$ErrorActionPreference = 'Stop'
. "$PSScriptRoot\lib\NaviSuite.ps1"
$lockFile = Join-Path $PSScriptRoot 'runtime.lock.json'
$lock = [IO.File]::ReadAllText($lockFile) | ConvertFrom-Json

$parts = [ordered]@{
    apache    = 'httpd-2.4.*-win64-*.zip'
    php       = 'php-8.*-Win32-vs1*-x64.zip'
    mysql     = 'mysql-8.*-winx64.zip'
    winsw     = 'WinSW-x64.exe'
    cacert    = 'cacert.pem'
    vc_redist = 'vc_redist.x64.exe'
}

Write-NsStep "Checking the downloads in $Downloads"
$found = [ordered]@{}
foreach ($name in $parts.Keys) {
    $files = @(Get-ChildItem -LiteralPath $Downloads -Filter $parts[$name] -File | Where-Object { $_.Name -notmatch 'nts|debug|test' })
    if ($files.Count -ne 1) { throw "${name}: expected exactly one file matching $($parts[$name]) in $Downloads, found $($files.Count)." }
    $file = $files[0]
    $sha = (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
    $pinned = $lock.components.$name
    if ($Pin) {
        $lock.components.$name = [pscustomobject]@{ file = $file.Name; sha256 = $sha; source = $pinned.source }
        Write-NsOk "${name}: $($file.Name) pinned ($sha)"
    } elseif (-not $pinned.sha256) {
        throw "${name}: nothing pinned in runtime.lock.json. Check $($file.Name) against the publisher's checksum, then run again with -Pin."
    } elseif ($pinned.file -ne $file.Name -or $pinned.sha256 -ne $sha) {
        throw "${name}: $($file.Name) ($sha) is not the pinned $($pinned.file) ($($pinned.sha256)). A new version must be pinned on purpose (-Pin)."
    } else {
        Write-NsOk "${name}: $($file.Name) matches the pinned SHA-256."
    }
    $found[$name] = @{ File = $file; Sha = $sha }
}
if ($Pin) {
    [IO.File]::WriteAllText($lockFile, (($lock | ConvertTo-Json -Depth 5) + "`n"), (New-Object Text.UTF8Encoding($false)))
    Write-NsOk "Pinned in $lockFile. Commit it."
}

Write-NsStep "Building $Output"
if (Test-Path -LiteralPath $Output) {
    if (Get-ChildItem -LiteralPath $Output -Force | Select-Object -First 1) { throw "$Output is not empty." }
} else {
    New-Item -ItemType Directory -Path $Output | Out-Null
}
$work = Join-Path $env:TEMP ('navisuite-runtime-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $work | Out-Null
try {
    foreach ($name in @('apache', 'php', 'mysql')) {
        $dir = Join-Path $work $name
        Expand-Archive -LiteralPath $found[$name].File.FullName -DestinationPath $dir
        # Apache Lounge zips hold Apache24\, MySQL zips mysql-8.x-winx64\: use the folder that has bin\ (or php.exe).
        $top = $dir
        $marker = if ($name -eq 'php') { 'php.exe' } else { 'bin' }
        if (-not (Test-Path -LiteralPath (Join-Path $top $marker))) {
            $inner = @(Get-ChildItem -LiteralPath $dir -Directory | Where-Object { Test-Path -LiteralPath (Join-Path $_.FullName $marker) })
            if ($inner.Count -ne 1) { throw "${name}: could not find the program folder in $($found[$name].File.Name)." }
            $top = $inner[0].FullName
        }
        Move-Item -LiteralPath $top -Destination (Join-Path $Output $name)
        Write-NsOk "$name extracted."
    }
    New-Item -ItemType Directory -Path (Join-Path $Output 'winsw') | Out-Null
    Copy-Item -LiteralPath $found.winsw.File.FullName -Destination (Join-Path $Output 'winsw\WinSW-x64.exe')
    Copy-Item -LiteralPath $found.cacert.File.FullName -Destination (Join-Path $Output 'cacert.pem')
    Copy-Item -LiteralPath $found.vc_redist.File.FullName -Destination (Join-Path $Output 'vc_redist.x64.exe')
} finally {
    & cmd.exe /c rmdir /s /q "$work"
}

Write-NsStep 'Versions, as the programs report them'
$php = Invoke-NsCapture (Join-Path $Output 'php\php.exe') @('-n', '-r', 'echo(PHP_VERSION.chr(32).PHP_ZTS.chr(32).PHP_INT_SIZE);')
if ($php.Code -eq -1073741515) { throw 'PHP cannot start on this build PC: install the Visual C++ runtime here first.' }
$p = ($php.Output -join '').Split(' ')
if ($p.Count -lt 3 -or [version]$p[0] -lt [version]'8.2' -or $p[1] -ne '1' -or $p[2] -ne '8') { throw "PHP must be 8.2 or later, thread-safe, x64. Found: $($php.Output -join ' ')" }
$httpd = Invoke-NsCapture (Join-Path $Output 'apache\bin\httpd.exe') @('-v')
if ("$($httpd.Output)" -notmatch 'Apache/(2\.4\.\d+)') { throw "Apache 2.4 not recognised: $($httpd.Output)" }
$apacheVersion = $Matches[1]
$mysqld = Invoke-NsCapture (Join-Path $Output 'mysql\bin\mysqld.exe') @('--version')
if ("$($mysqld.Output)" -notmatch 'Ver (\d+\.\d+\.\d+)') { throw "MySQL not recognised: $($mysqld.Output)" }
$mysqlVersion = $Matches[1]
if ((ConvertTo-NsVersion $mysqlVersion).Major -lt 8) { throw "MySQL 8.0 or later is required. Found $mysqlVersion." }
# WinSW 2.x answers no command (not even --version) without a service XML next to it, so the version comes from the file's version resource.
$winswProduct = "$((Get-Item -LiteralPath (Join-Path $Output 'winsw\WinSW-x64.exe')).VersionInfo.ProductVersion)"
if ($winswProduct -notmatch '^(\d+\.\d+\.\d+)') { throw "WinSW not recognised: product version '$winswProduct'" }
$winswVersion = $Matches[1]
$vcVersion = (Get-Item -LiteralPath (Join-Path $Output 'vc_redist.x64.exe')).VersionInfo.ProductVersion
foreach ($dll in @('php8apache2_4.dll')) { if (-not (Test-Path -LiteralPath (Join-Path $Output "php\$dll"))) { throw "php\$dll is missing: the PHP zip is not the thread-safe build." } }

$manifest = [ordered]@{
    prepared_at = (Get-Date).ToString('s')
    components = [ordered]@{
        apache    = [ordered]@{ version = $apacheVersion; file = $found.apache.File.Name; sha256 = $found.apache.Sha }
        php       = [ordered]@{ version = $p[0]; file = $found.php.File.Name; sha256 = $found.php.Sha }
        mysql     = [ordered]@{ version = $mysqlVersion; file = $found.mysql.File.Name; sha256 = $found.mysql.Sha }
        winsw     = [ordered]@{ version = $winswVersion; file = $found.winsw.File.Name; sha256 = $found.winsw.Sha }
        cacert    = [ordered]@{ version = $found.cacert.File.LastWriteTime.ToString('yyyy-MM-dd'); file = 'cacert.pem'; sha256 = $found.cacert.Sha }
        vc_redist = [ordered]@{ version = $vcVersion; file = $found.vc_redist.File.Name; sha256 = $found.vc_redist.Sha }
    }
}
[IO.File]::WriteAllText((Join-Path $Output 'runtime.json'), (($manifest | ConvertTo-Json -Depth 5) + "`n"), (New-Object Text.UTF8Encoding($false)))
foreach ($k in $manifest.components.Keys) { Write-NsOk "$k $($manifest.components[$k].version)" }
Write-NsOk "Runtime ready in $Output. Use it with: deploy/build-installers.sh --runtime <that folder>"
