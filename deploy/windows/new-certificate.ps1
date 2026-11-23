<#
.SYNOPSIS
    Creates or renews the HTTPS certificate of the NaviSuite vessel server.

.DESCRIPTION
    Each vessel server has its own small certificate authority (CA). The CA
    is created once and is valid for 10 years. Client PCs trust it once,
    from http://<server>/navisuite-ca.crt or through the client installer.

    The server certificate is signed by that CA and names this PC: its
    computer name, <name>.local, localhost and every LAN IP address. It is
    valid for 825 days, the longest that Apple devices accept. Renewing it,
    for example after an IP address change, needs no change on the clients.

    Without -Force the certificate is only re-issued when it expires within
    -RenewWithinDays days or no longer covers this PC's names and addresses.
    The daily maintenance task runs this script that way.

.EXAMPLE
    .\new-certificate.ps1 -RestartWeb
    .\new-certificate.ps1 -ExtraAddresses 10.1.2.3 -Force -RestartWeb
#>
[CmdletBinding()]
param(
    [string]$Root = 'C:\NaviSuite',
    [string]$ServerName,
    [string[]]$ExtraNames = @(),
    [string[]]$ExtraAddresses = @(),
    [ValidateRange(1, 825)][int]$ServerDays = 825,
    [int]$RenewWithinDays = 60,
    [switch]$Force,
    [switch]$RestartWeb
)

. "$PSScriptRoot\lib\NaviSuite.ps1"
Assert-NsAdministrator
$paths = Get-NsPaths $Root

if (-not $ServerName) {
    $settingsFile = Get-NsSettingsPath $paths
    if (Test-Path -LiteralPath $settingsFile) {
        $settings = Read-NsSettings $paths
        $ServerName = $settings.ServerName
        if ($settings.PSObject.Properties['ExtraNames']) { $ExtraNames = @($ExtraNames) + @($settings.ExtraNames) }
    } else {
        $ServerName = $env:COMPUTERNAME.ToLower()
    }
}
if (-not (Test-Path -LiteralPath $paths.OpenSslExe)) { throw "openssl.exe not found at $($paths.OpenSslExe). Apache must be in runtime\apache." }

# ---------------------------------------------------------------- names

$dnsNames = New-Object Collections.Generic.List[string]
foreach ($n in (@($ServerName, "$ServerName.local", 'localhost') + $ExtraNames)) {
    if ($n -and -not ($dnsNames -contains $n.ToLower())) { $dnsNames.Add($n.ToLower()) }
}
try {
    $fqdn = [Net.Dns]::GetHostEntry('').HostName.ToLower()
    if ($fqdn -and -not ($dnsNames -contains $fqdn)) { $dnsNames.Add($fqdn) }
} catch { }

$ipAddresses = New-Object Collections.Generic.List[string]
foreach ($ip in (@('127.0.0.1') + @(Get-NsLanAddresses) + $ExtraAddresses)) {
    if ($ip -and -not ($ipAddresses -contains $ip)) { $ipAddresses.Add($ip) }
}

# ---------------------------------------------------------------- is a new one needed?

function Get-CertNames([Security.Cryptography.X509Certificates.X509Certificate2]$Cert) {
    $ext = $Cert.Extensions | Where-Object { $_.Oid.Value -eq '2.5.29.17' }
    if (-not $ext) { return @() }
    return [regex]::Matches($ext.Format($false), '(?i)(?:DNS Name|IP Address)=([^,\s]+)') | ForEach-Object { $_.Groups[1].Value.ToLower() }
}

$reason = $null
if ($Force) { $reason = 'requested with -Force' }
elseif (-not (Test-Path -LiteralPath $paths.ServerCert) -or -not (Test-Path -LiteralPath $paths.ServerKey)) { $reason = 'no certificate yet' }
elseif (-not (Test-Path -LiteralPath $paths.CaCert)) { $reason = 'no certificate authority yet' }
else {
    $current = New-Object Security.Cryptography.X509Certificates.X509Certificate2($paths.ServerCert)
    $covered = Get-CertNames $current
    $missing = @(@($dnsNames) + @($ipAddresses) | Where-Object { $covered -notcontains $_ })
    if ($current.NotAfter -lt (Get-Date).AddDays($RenewWithinDays)) { $reason = "it expires on $($current.NotAfter.ToString('yyyy-MM-dd'))" }
    elseif ($missing.Count) { $reason = "it does not cover $($missing -join ', ')" }
}

if (-not $reason) {
    Write-NsOk "The HTTPS certificate is valid until $($current.NotAfter.ToString('yyyy-MM-dd')) and covers this PC. Nothing to do."
    return
}

Write-NsStep "Issuing a new HTTPS certificate ($reason)"
New-NsFolders $paths
$env:OPENSSL_CONF = $null
$work = Join-Path $paths.Secrets ('cert-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $work | Out-Null

try {
    # ------------------------------------------------------------ CA (once)
    if (-not (Test-Path -LiteralPath $paths.CaCert) -or -not (Test-Path -LiteralPath $paths.CaKey)) {
        if ((Test-Path -LiteralPath $paths.CaCert) -and -not (Test-Path -LiteralPath $paths.CaKey)) {
            Write-NsWarn 'The CA key is missing, so a new CA is created. Client PCs must trust the new CA again.'
        }
        $caCnf = Join-Path $work 'ca.cnf'
        Write-NsFile $caCnf (Expand-NsTemplate 'openssl.cnf' @{ COMMON_NAME = "NaviSuite Vessel CA ($ServerName)"; ALT_NAMES = "DNS.1 = $ServerName" })
        Invoke-NsNative $paths.OpenSslExe @('req', '-x509', '-new', '-newkey', 'rsa:3072', '-nodes', '-sha256', '-days', '3650',
            '-config', $caCnf, '-extensions', 'ca_ext', '-keyout', $paths.CaKey, '-out', (Join-Path $work 'ca.crt')) -Quiet | Out-Null
        Copy-Item -LiteralPath (Join-Path $work 'ca.crt') -Destination $paths.CaCert -Force
        Write-NsOk "Created the vessel CA: $($paths.CaCert)"
    }

    # ------------------------------------------------------------ server certificate
    $alt = New-Object Collections.Generic.List[string]
    for ($i = 0; $i -lt $dnsNames.Count; $i++) { $alt.Add("DNS.$($i + 1) = $($dnsNames[$i])") }
    for ($i = 0; $i -lt $ipAddresses.Count; $i++) { $alt.Add("IP.$($i + 1) = $($ipAddresses[$i])") }
    $cnf = Join-Path $work 'server.cnf'
    Write-NsFile $cnf (Expand-NsTemplate 'openssl.cnf' @{ COMMON_NAME = $ServerName; ALT_NAMES = ($alt -join "`n") })

    $key = Join-Path $work 'server.key'; $csr = Join-Path $work 'server.csr'; $crt = Join-Path $work 'server.crt'
    Invoke-NsNative $paths.OpenSslExe @('req', '-new', '-newkey', 'rsa:2048', '-nodes', '-sha256', '-config', $cnf, '-keyout', $key, '-out', $csr) -Quiet | Out-Null
    Invoke-NsNative $paths.OpenSslExe @('x509', '-req', '-in', $csr, '-CA', $paths.CaCert, '-CAkey', $paths.CaKey,
        '-CAserial', (Join-Path $paths.Secrets 'ca.srl'), '-CAcreateserial', '-days', "$ServerDays", '-sha256',
        '-extfile', $cnf, '-extensions', 'server_ext', '-out', $crt) -Quiet | Out-Null
    Invoke-NsNative $paths.OpenSslExe @('verify', '-CAfile', $paths.CaCert, '-purpose', 'sslserver', $crt) -Quiet | Out-Null

    # Keep the previous pair until the new one is in place.
    foreach ($f in @($paths.ServerCert, $paths.ServerKey)) {
        if (Test-Path -LiteralPath $f) { Copy-Item -LiteralPath $f -Destination (Join-Path $paths.Secrets ((Split-Path -Leaf $f) + '.previous')) -Force }
    }
    Copy-Item -LiteralPath $crt -Destination $paths.ServerCert -Force
    Copy-Item -LiteralPath $key -Destination $paths.ServerKey -Force
} finally {
    & cmd.exe /c rmdir /s /q "$work"
}

# The key is readable by Apache only.
$keyAcl = @('*S-1-5-32-544:F', '*S-1-5-18:F')
$useLocalSystem = $false
if (Test-Path -LiteralPath (Get-NsSettingsPath $paths)) { $useLocalSystem = [bool](Read-NsSettings $paths).UseLocalSystem }
if ((Test-NsService 'NaviSuite-Web') -and -not $useLocalSystem) { $keyAcl += "$(Get-NsServiceSid 'NaviSuite-Web'):R" }
Grant-NsAcl $paths.ServerKey $keyAcl -Protect

$new = New-Object Security.Cryptography.X509Certificates.X509Certificate2($paths.ServerCert)
Write-NsOk "Certificate valid until $($new.NotAfter.ToString('yyyy-MM-dd')) for: $((@($dnsNames) + @($ipAddresses)) -join ', ')"

if ($RestartWeb -and (Test-NsService 'NaviSuite-Web')) {
    Stop-NsServices @('NaviSuite-Web')
    Start-NsServices @('NaviSuite-Web')
    Write-NsOk 'Apache restarted with the new certificate.'
}
