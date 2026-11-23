<#
.SYNOPSIS
    Sets the Windows Firewall rules of the NaviSuite vessel server.

.DESCRIPTION
    All rules are in the firewall group "NaviSuite", so they are easy to
    find in "Windows Defender Firewall with Advanced Security" and are
    replaced as a set each time this script runs.

      NaviSuite HTTPS (LAN)   allow TCP 443 to Apache, from the LAN only
      NaviSuite HTTP (LAN)    allow TCP 80 to Apache, from the LAN only.
                              It answers /health and /navisuite-ca.crt and
                              redirects everything else to HTTPS.
      NaviSuite discovery     allow UDP 47810 to the discovery service, from
                              the LAN only, so desktop clients can find it
      NaviSuite block MySQL   block TCP 3306 and 33060 from everywhere

    MySQL also listens on 127.0.0.1 only, so the block rule is a second line
    of defence. Traffic from this PC to itself is not filtered by Windows
    Firewall, so the application still reaches MySQL.

    The allow rules apply to the Domain and Private network profiles. A ship
    LAN that Windows has classed as "Public" is not covered; the script
    warns about it and prints the command that changes it. Use
    -IncludePublicProfile only if the profile cannot be changed.

.PARAMETER RemoteAddress
    Who may connect. Default: LocalSubnet, the subnets of this PC's own
    network adapters. Give explicit ranges when crew PCs are on other
    subnets, for example -RemoteAddress 192.168.10.0/24,192.168.20.0/24.

.EXAMPLE
    .\configure-firewall.ps1
    .\configure-firewall.ps1 -RemoteAddress 192.168.10.0/24,10.0.5.0/24
    .\configure-firewall.ps1 -Remove
#>
[CmdletBinding()]
param(
    [string]$Root = 'C:\NaviSuite',
    [string[]]$RemoteAddress = @('LocalSubnet'),
    [switch]$IncludePublicProfile,
    [switch]$Remove
)

. "$PSScriptRoot\lib\NaviSuite.ps1"
Assert-NsAdministrator
$paths = Get-NsPaths $Root
$group = 'NaviSuite'

Write-NsStep 'Windows Firewall'
Get-NetFirewallRule -Group $group -ErrorAction SilentlyContinue | Remove-NetFirewallRule
if ($Remove) { Write-NsOk 'NaviSuite firewall rules removed.'; return }

$profiles = @('Domain', 'Private')
if ($IncludePublicProfile) { $profiles += 'Public' }

$common = @{
    Group = $group; Direction = 'Inbound'; Action = 'Allow'; Protocol = 'TCP'
    Program = $paths.HttpdExe; RemoteAddress = $RemoteAddress; Profile = ($profiles -join ','); Enabled = 'True'
}
New-NetFirewallRule @common -Name 'NaviSuite-HTTPS' -DisplayName 'NaviSuite HTTPS (LAN)' -LocalPort 443 `
    -Description 'NaviSuite vessel server: crew PCs reach the application over HTTPS.' | Out-Null
New-NetFirewallRule @common -Name 'NaviSuite-HTTP' -DisplayName 'NaviSuite HTTP (LAN)' -LocalPort 80 `
    -Description 'NaviSuite vessel server: /health, /navisuite-ca.crt and the redirect to HTTPS.' | Out-Null
New-NetFirewallRule -Name 'NaviSuite-Discovery' -DisplayName 'NaviSuite discovery (LAN)' -Group $group `
    -Direction Inbound -Action Allow -Protocol UDP -LocalPort $script:NsDiscoveryPort -Program $paths.PhpExe `
    -RemoteAddress $RemoteAddress -Profile ($profiles -join ',') -Enabled True `
    -Description 'NaviSuite vessel server: desktop clients find the server by a UDP broadcast. Answers are read-only.' | Out-Null
New-NetFirewallRule -Name 'NaviSuite-Block-MySQL' -DisplayName 'NaviSuite block MySQL' -Group $group `
    -Direction Inbound -Action Block -Protocol TCP -LocalPort 3306, 33060 -Profile Any -Enabled True `
    -Description 'NaviSuite vessel server: MySQL is for this PC only. Never allow 3306 or 33060 from the LAN.' | Out-Null
Write-NsOk "Allowed TCP 80 and 443 to Apache, and UDP $($script:NsDiscoveryPort) to discovery, from $($RemoteAddress -join ', ') on the $($profiles -join ', ') profile(s)."
Write-NsOk 'Blocked TCP 3306 and 33060 inbound on every profile.'

# Rules made outside this script for the same programs, for example by an
# earlier manual install or a "Windows Security Alert" prompt, could open
# more than intended. Disable them and say so.
foreach ($program in @($paths.HttpdExe, $paths.MySqldExe)) {
    $others = Get-NetFirewallApplicationFilter -ErrorAction SilentlyContinue | Where-Object { $_.Program -ieq $program } |
        Get-NetFirewallRule | Where-Object { $_.Group -ne $group -and $_.Direction -eq 'Inbound' -and $_.Action -eq 'Allow' -and $_.Enabled -eq 'True' }
    foreach ($r in @($others)) {
        if ($r) { Disable-NetFirewallRule -Name $r.Name; Write-NsWarn "Disabled the other inbound rule '$($r.DisplayName)' for $(Split-Path -Leaf $program)." }
    }
}

foreach ($p in (Get-NetFirewallProfile)) {
    if (-not $p.Enabled) { Write-NsWarn "Windows Firewall is switched off for the $($p.Name) profile. Switch it on; the block rule has no effect while it is off." }
}

foreach ($c in @(Get-NetConnectionProfile -ErrorAction SilentlyContinue)) {
    if ($c.NetworkCategory -eq 'Public' -and -not $IncludePublicProfile) {
        Write-NsWarn "The network '$($c.Name)' on '$($c.InterfaceAlias)' is classed as Public, so crew PCs on it cannot connect."
        Write-NsInfo "If this is the ship's LAN, mark it Private:"
        Write-NsInfo "  Set-NetConnectionProfile -InterfaceIndex $($c.InterfaceIndex) -NetworkCategory Private"
    }
}

$dhcp = Get-NetIPInterface -AddressFamily IPv4 -Dhcp Enabled -ConnectionState Connected -ErrorAction SilentlyContinue |
    Where-Object { $_.InterfaceAlias -notlike 'Loopback*' }
if ($dhcp) {
    Write-NsWarn "This PC gets its IP address from DHCP ($(($dhcp.InterfaceAlias) -join ', ')). Give it a fixed address or a DHCP reservation, so crew PCs always find it."
}
