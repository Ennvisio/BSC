<#
.SYNOPSIS
    Checks that the NaviSuite vessel server is healthy.

.DESCRIPTION
    Checks the four Windows services, that MySQL cannot be reached from
    the LAN, the firewall rules, the HTTPS certificate, the age of the
    newest database and file backups, sync problems (sync:health) and the
    application's own health report (http://127.0.0.1/health, which shows
    every check to this PC). Exits with code 0 when everything critical is
    fine, otherwise 1, so it can be used by other tools.

.PARAMETER WaitSeconds
    Keep asking /health for up to this many seconds until it reports "ok".
    The scheduler and queue report in once a minute, so right after a
    start they show as not yet running.

.EXAMPLE
    .\check-health.ps1
#>
[CmdletBinding()]
param(
    [string]$Root = 'C:\NaviSuite',
    [int]$WaitSeconds = 0
)

. "$PSScriptRoot\lib\NaviSuite.ps1"
$paths = Get-NsPaths $Root
$failed = $false

Write-NsStep 'Windows services'
foreach ($n in $script:NsServiceNames) {
    $svc = Get-Service -Name $n -ErrorAction SilentlyContinue
    if (-not $svc) { Write-NsFail "$n is not installed"; $failed = $true; continue }
    $cfg = Get-CimInstance Win32_Service -Filter "Name='$n'" -ErrorAction SilentlyContinue
    $delayed = $cfg -and $cfg.PSObject.Properties['DelayedAutoStart'] -and $cfg.DelayedAutoStart
    $mode = if ($cfg) { "$($cfg.StartMode)$(if ($delayed) { ' (delayed)' }), runs as $($cfg.StartName)" } else { '' }
    if ($svc.Status -eq 'Running') { Write-NsOk "$n running. Start: $mode" }
    else { Write-NsFail "$n is $($svc.Status). Start: $mode"; $failed = $true }
}

Write-NsStep 'Network exposure'
$listeners = @(Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue | Where-Object { $_.LocalPort -in 3306, 33060 })
$exposed = @($listeners | Where-Object { $_.LocalAddress -notin '127.0.0.1', '::1' })
if ($exposed.Count) { Write-NsFail "MySQL listens on $(($exposed | ForEach-Object { "$($_.LocalAddress):$($_.LocalPort)" }) -join ', '). It must listen on 127.0.0.1 only."; $failed = $true }
elseif ($listeners.Count) { Write-NsOk 'MySQL listens on 127.0.0.1 only.' }
else { Write-NsWarn 'MySQL is not listening.' }

$rules = @(Get-NetFirewallRule -Group 'NaviSuite' -ErrorAction SilentlyContinue | Where-Object { $_.Enabled -eq 'True' })
foreach ($name in @('NaviSuite-HTTPS', 'NaviSuite-HTTP', 'NaviSuite-Discovery', 'NaviSuite-Block-MySQL')) {
    if ($rules | Where-Object { $_.Name -eq $name }) { Write-NsOk "Firewall rule $name" } else { Write-NsWarn "Firewall rule $name is missing or disabled. Run configure-firewall.ps1." }
}
foreach ($c in @(Get-NetConnectionProfile -ErrorAction SilentlyContinue)) {
    if ($c.NetworkCategory -eq 'Public') { Write-NsWarn "Network '$($c.Name)' ($($c.InterfaceAlias)) is Public: crew PCs on it cannot connect unless the profile is Private." }
}

Write-NsStep 'HTTPS'
if (Test-Path -LiteralPath $paths.ServerCert) {
    $cert = New-Object Security.Cryptography.X509Certificates.X509Certificate2($paths.ServerCert)
    $days = [int]($cert.NotAfter - (Get-Date)).TotalDays
    if ($days -lt 0) { Write-NsFail "The certificate expired on $($cert.NotAfter.ToString('yyyy-MM-dd')). Run new-certificate.ps1 -RestartWeb."; $failed = $true }
    elseif ($days -lt 30) { Write-NsWarn "The certificate expires in $days days. The maintenance task renews it; or run new-certificate.ps1 -RestartWeb." }
    else { Write-NsOk "Certificate valid for $days more days." }
    Write-NsInfo "Server fingerprint (shown on crew PCs): $(Get-NsCaFingerprint $paths)"

    # Connect to 443 and compare the certificate Apache presents.
    try {
        $tcp = New-Object Net.Sockets.TcpClient('127.0.0.1', 443)
        $ssl = New-Object Net.Security.SslStream($tcp.GetStream(), $false, ({ $true } -as [Net.Security.RemoteCertificateValidationCallback]))
        $ssl.AuthenticateAsClient('localhost', $null, [Security.Authentication.SslProtocols]::Tls12, $false)
        $presented = New-Object Security.Cryptography.X509Certificates.X509Certificate2($ssl.RemoteCertificate)
        if ($presented.Thumbprint -eq $cert.Thumbprint) { Write-NsOk "Apache serves this certificate over $($ssl.SslProtocol)." }
        else { Write-NsWarn 'Apache serves a different certificate. Restart NaviSuite-Web.' }
        $ssl.Dispose(); $tcp.Close()
    } catch {
        Write-NsFail "No HTTPS answer on port 443: $($_.Exception.Message)"; $failed = $true
    }
} else {
    Write-NsFail 'No certificate. Run new-certificate.ps1.'; $failed = $true
}

Write-NsStep 'LAN discovery'
try {
    $udp = New-Object Net.Sockets.UdpClient
    $udp.Client.ReceiveTimeout = 3000
    $nonce = -join ((1..16) | ForEach-Object { '{0:x2}' -f (Get-Random -Maximum 256) })
    $question = [Text.Encoding]::UTF8.GetBytes((@{ type = 'navisuite.discover'; v = 1; nonce = $nonce } | ConvertTo-Json -Compress))
    [void]$udp.Send($question, $question.Length, '127.0.0.1', $script:NsDiscoveryPort)
    $from = New-Object Net.IPEndPoint([Net.IPAddress]::Any, 0)
    $answer = [Text.Encoding]::UTF8.GetString($udp.Receive([ref]$from)) | ConvertFrom-Json
    $udp.Close()
    if ($answer.nonce -eq $nonce) { Write-NsOk "Answers on UDP $($script:NsDiscoveryPort) as '$($answer.host)', vessel $($answer.vessel.id)." }
    else { Write-NsWarn 'Discovery answered with the wrong nonce.' }
} catch {
    Write-NsWarn "No discovery answer on UDP $($script:NsDiscoveryPort). Desktop clients must then be given the server address by hand. Is NaviSuite-Discovery running?"
}

Write-NsStep 'Sync with the cloud'
$syncEnv = if (Test-Path -LiteralPath $paths.Env) { Read-NsDotEnv $paths.Env } else { @{} }
if (-not $syncEnv['SYNC_CLOUD_URL']) {
    Write-NsInfo 'Off: SYNC_CLOUD_URL is empty in .env.'
} elseif ($syncEnv['SYNC_CA_BUNDLE']) {
    if (Test-Path -LiteralPath $syncEnv['SYNC_CA_BUNDLE']) { Write-NsOk "Cloud $($syncEnv['SYNC_CLOUD_URL']), CA bundle $($syncEnv['SYNC_CA_BUNDLE'])." }
    else { Write-NsWarn "SYNC_CA_BUNDLE names $($syncEnv['SYNC_CA_BUNDLE']), which does not exist. Sync will fail." }
} elseif (Test-Path -LiteralPath $paths.CaBundle) {
    Write-NsOk "Cloud $($syncEnv['SYNC_CLOUD_URL']), CA bundle in php.ini."
} else {
    Write-NsWarn "No CA bundle at $($paths.CaBundle): sync cannot check the cloud's certificate. Copy cacert.pem there and run update-server.ps1 or install-server.ps1 again."
}

Write-NsStep 'Backups'
foreach ($b in @(
        @{ Name = 'Database'; Dir = $paths.Backups; Filter = 'navisuite-*.zip' },
        @{ Name = 'Files'; Dir = (Join-Path $paths.Backups 'files\manifests'); Filter = 'files-*.json' })) {
    $newest = Get-ChildItem -LiteralPath $b.Dir -Filter $b.Filter -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $newest) { Write-NsWarn "$($b.Name): no backup yet. The maintenance task makes one every night at 02:30." }
    elseif ($newest.LastWriteTime -lt (Get-Date).AddHours(-36)) { Write-NsWarn "$($b.Name): the newest backup is from $($newest.LastWriteTime.ToString('yyyy-MM-dd HH:mm')). Is the maintenance task running?" }
    else { Write-NsOk "$($b.Name): $($newest.Name)" }
}

Write-NsStep 'Sync problems (sync:health)'
if ($syncEnv['SYNC_CLOUD_URL']) {
    $code = Invoke-NsArtisan $paths @('sync:health') -AllowFailure
    switch ($code) {
        0 { Write-NsOk 'Nothing needs attention.' }
        1 { Write-NsWarn 'Warnings above: sync works but something needs a look.' }
        default { Write-NsFail 'Sync needs attention (see above).' }
    }
} else {
    Write-NsInfo 'Off: SYNC_CLOUD_URL is empty in .env.'
}

Write-NsStep 'Application (/health)'
$deadline = (Get-Date).AddSeconds($WaitSeconds)
do {
    $h = Get-NsHealth
    $status = if ($h.Body) { $h.Body.status } else { $null }
    if ($status -eq 'ok' -or (Get-Date) -ge $deadline) { break }
    Write-NsInfo "Status '$status', waiting for the scheduler and queue to report in..."
    Start-Sleep -Seconds 10
} while ($true)

if (-not $h.Body) {
    Write-NsFail "No answer from http://127.0.0.1/health (HTTP $($h.Code)). See $($paths.Logs)\apache\error.log."
    $failed = $true
} else {
    $vessel = if ($h.Body.vessel) { "$($h.Body.vessel.name) (id $($h.Body.vessel.id))" } else { 'unknown vessel' }
    Write-NsInfo "Release $($h.Body.release), $vessel"
    if ($h.Body.PSObject.Properties['checks']) {
        foreach ($p in $h.Body.checks.PSObject.Properties) {
            $c = $p.Value
            $msg = if ($c.PSObject.Properties['message']) { "$($p.Name): $($c.message)" } else { $p.Name }
            if ($c.ok) { Write-NsOk $msg } elseif ($c.critical) { Write-NsFail $msg } else { Write-NsWarn $msg }
        }
    }
    switch ($status) {
        'ok' { Write-NsOk 'Overall: ok' }
        'degraded' { Write-NsWarn 'Overall: degraded. The crew can work; fix the warnings above.' }
        default { Write-NsFail "Overall: $status"; $failed = $true }
    }
}

if ($failed) { exit 1 }
exit 0
