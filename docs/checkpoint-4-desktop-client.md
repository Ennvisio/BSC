# Checkpoint 4: NaviSuite Desktop Client for Windows

**Status:** Implemented on branch `feature/offline-sync`, awaiting review. The installer builds and the client was tested end to end on macOS. **It has not yet been installed on Windows** (section 12.3).
**Date:** 2026-11-20
**Builds on:** [checkpoint-3-windows-vessel-server.md](checkpoint-3-windows-vessel-server.md). The plan is in [offline-implementation-roadmap.md](offline-implementation-roadmap.md), where this work is part of checkpoint 9.

---

## 1. What this checkpoint delivers

Crew PCs on the ship's LAN get a NaviSuite program. It finds the vessel server, checks that the server is genuine, and shows the existing NaviSuite screens served by that server. The client has no database and no copy of Laravel. Each crew member signs in with their own account, exactly as on the cloud today.

| Requirement | Delivered as |
|---|---|
| Load the existing interface from the onboard server | The client opens `https://<server>/` in a locked-down browser view. The Blade pages, CSS and JavaScript are unchanged. |
| Server IP or host name configuration | The setup screen accepts a host name or an IPv4 address. IT can also set it in a file (section 7). |
| Automatic LAN server discovery | UDP broadcast question, answered by a new service on the server (section 5). If nobody answers, the client checks the PCs on its own subnet for a NaviSuite `/health` page. |
| Manual IP fallback | "Enter the address yourself" on the setup screen. The address is checked in the same way as a discovered one. |
| Save the server configuration securely | Encrypted with Windows DPAPI for the signed-in Windows user. IT settings go in `%ProgramData%`, writable by administrators only (section 7). |
| Connection status indicator | A bar above the page: Connected, Offline, Server problem, Server not verified, Wrong server, Reconnecting in N s (section 8). |
| Automatic reconnection | Checks every 10 s while connected. When offline it retries after 2, 4, 8, 15 and then every 30 s, and returns to the page by itself. After a minute offline it asks the LAN again, in case the server's address changed. |
| Individual user authentication | The client has no account of its own. Each person signs in on the NaviSuite login page. By default the session is erased when the program closes, because crew PCs are shared (section 9). |
| Multiple simultaneous clients | Each client has its own session cookie and server-side session. Tested with three clients signed in as three people at the same time (section 10). |
| No MySQL, no Laravel on the client | The installer contains only the Electron program and 17 small source files (section 11). |
| Windows client installer | `NaviSuite-Client-Setup-1.0.0.exe` (NSIS, 64-bit, 115 MB). It supports a silent install for IT. |
| Frontend not rewritten | No Blade view, CSS or JavaScript file of the application was changed. |
| Discovery protocol and security documented | Sections 5 and 6. |

## 2. Why Electron

The brief asked for Electron to be evaluated. Four options were compared:

| Option | For | Against | Verdict |
|---|---|---|---|
| **Electron** (chosen) | Ships its own Chromium, so every PC renders the pages the same way, including old PCs that are never updated. It can pin the vessel's certificate, store settings with DPAPI and send UDP broadcasts, all in JavaScript, which the team already uses. Mature Windows installer tooling. | Large: about 115 MB to download and 370 MB installed. Chromium security fixes arrive only when the client is rebuilt. | Chosen |
| Microsoft Edge WebView2 (a C# wrapper, or Tauri) | Small installer (5 to 15 MB). Uses the Edge engine already on Windows 10 and 11. | Relies on the WebView2 runtime installed on each PC. On PCs that are offline for months, or Windows 10 LTSC builds, it may be old or missing. Certificate pinning is awkward, because the hook only runs after Windows has already rejected a certificate. It adds C# or Rust to the project. | Second choice if installer size becomes a problem |
| A browser shortcut (Edge or Chrome to `https://master-pc/`) | Nothing to install. It already works today once the vessel CA is trusted by Windows. | No discovery, no connection indicator, no pinning, and sessions persist on shared PCs. Crew could browse elsewhere in the same window. | Kept as the emergency fallback (section 12.2) |
| NativePHP, or a PHP app on each PC | | Puts a backend on every client, which the brief rules out. Rejected in the audit. | Rejected |

**Version.** Electron 44.10.2 (released 2026-11-03) and electron-builder 26.18.0. Each was the newest release at least two weeks old. Electron supports the three newest major versions, and 45 is already out. **The client should therefore be rebuilt on a newer Electron about every six months**, and promptly after a serious Chromium security fix.

## 3. How the client works

```
 +--------------------------------------------------------------+
 | bar:  [Connected]  MV EXAMPLE   < > R  Home   Server  About  |  local page, part of the client
 +--------------------------------------------------------------+
 |                                                              |
 |   the NaviSuite pages from https://<server>/                 |  remote page, unchanged Blade UI
 |   (or the client's own Setup or Offline page)                |
 |                                                              |
 +--------------------------------------------------------------+
```

The window holds three views:

| View | Shows | Can do |
|---|---|---|
| Bar | Connection status and navigation buttons | Talks to the client through a small, fixed set of messages |
| Local | The client's own Setup and Offline pages | Same. Can load only the client's own files. |
| Application | The NaviSuite web pages from the vessel server | Nothing beyond a normal web page: sandboxed, no Node.js, no access to the client's functions |

Rules for the application view:

- **Only the vessel server.** The view may open only the chosen server's address. A link to any other `http`, `https` or `mailto` address opens in the PC's normal browser. Every other kind of link is ignored.
- **Pop-ups the app already uses still work.** Print windows (`window.open('', 'PRINT')`), same-site `target="_blank"` links and `window.open(url, '_blank')` open as NaviSuite windows with the same restrictions.
- **Pinned certificate.** Pages from the server load only if its certificate was issued by the vessel's CA (section 6.2). Other sites, such as the CDNs the pages still use (section 12.1), are checked by Chromium in the normal way.
- **Few permissions.** The pages may copy to the clipboard and go full screen, and nothing else. Camera, microphone, location and notifications are refused.
- **No developer tools** in the installed program, unless IT allows them (section 7).

Keyboard and menu: F5 reloads, Ctrl+P prints, Alt+Left and Alt+Right go back and forward, Ctrl+plus and Ctrl+minus zoom. The menu also has Change server and About.

## 4. First start

1. The client looks for servers in this order:
   - the server set by IT, if any
   - a discovery broadcast (2.5 s, sent three times)
   - only if nobody answered, a check of every address on this PC's /24 subnet for a NaviSuite `/health` page, about 250 addresses at 48 at a time
2. Each server found is shown with:
   - the vessel name and its addresses
   - the release
   - the certificate fingerprint
   - a **Verified** badge, if the server matches the IT setting or Windows already trusts its certificate
3. The crew member picks the server, or types an address. The client then checks that the server is a genuine NaviSuite vessel server (section 6.2).
4. If the server is not verified, the client shows its fingerprint and asks for it to be compared with the one printed by the server installer, or shown by `check-health.ps1` on the master PC. The connection continues only after the person confirms.
5. The server is saved, and the NaviSuite login page opens.

If IT set the server and its fingerprint, the client connects straight away, without asking.

## 5. LAN discovery protocol (version 1)

### 5.1 Exchange

One UDP datagram from the client and one from the server, both JSON in UTF-8.

| | Value |
|---|---|
| Port | UDP **47810** on the server. The client uses a random port. |
| Client sends to | The broadcast address of each private network the PC is on (for example `192.168.10.255`) and `255.255.255.255` |
| Server replies to | The address and port the question came from (unicast) |
| Attempts | Three questions, 2.5 s apart. Answers are collected for the whole period. |

**Question** (at most 512 bytes; the server ignores anything larger):

```json
{"type": "navisuite.discover", "v": 1, "nonce": "9f2c...e1", "vessel": 12}
```

| Field | Rule |
|---|---|
| `type` | Must be `navisuite.discover` |
| `v` | Protocol version, an integer of 1 or more. A server answers any version with its own version 1 answer, so newer clients still find older servers. |
| `nonce` | 16 to 64 hexadecimal characters, new for every search |
| `vessel` | Optional. If given, only the server of that vessel answers. |

**Answer:**

```json
{
  "type": "navisuite.server", "v": 1, "nonce": "9f2c...e1",
  "app": "NaviSuite",
  "vessel": {"id": 12, "name": "MV EXAMPLE"},
  "host": "master-pc",
  "https_port": 443, "http_port": 80,
  "ca_sha256": "42:9A:7B:...:4E",
  "release": "2026.11.20-b63bc21"
}
```

| Field | Meaning |
|---|---|
| `nonce` | Copied from the question. The client ignores answers with any other nonce. |
| `vessel` | `id` from `APP_VESSEL_ID`. `name` is read from the database, cut to 100 characters, and is `null` if the database is unavailable. |
| `host` | The host name from `APP_URL`, or else the PC's name, in lower case |
| `https_port`, `http_port` | Where the web server listens |
| `ca_sha256` | SHA-256 fingerprint of the vessel CA certificate (`config\certs\ca.crt`), or `null` if the file cannot be read |
| `release` | `APP_RELEASE`, cut to 64 characters |

The client merges answers by vessel and fingerprint, and remembers the address each answer came from. The `host` name is only used if it resolves. The sender's IP address works even without DNS.

### 5.2 Server behaviour

The responder is `php artisan navisuite:discovery`, run by the new Windows service `NaviSuite-Discovery`. It:

| Rule | Detail |
|---|---|
| Answers only private senders | 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 169.254.0.0/16 and 127.0.0.0/8. Others are ignored. |
| Ignores bad questions | Wrong type, a missing or malformed nonce, nesting deeper than 4 levels, more than 512 bytes, or a different `vessel` |
| Limits the rate | 5 answers per sender per 10 s, and 20 answers per second in total. Further questions are dropped silently. |
| Reads little | Vessel name and fingerprint are cached for 60 s, so a burst of questions costs no database work |
| Reports health | A heartbeat every 30 s, shown as the non-critical `discovery` check in `/health`. Counts of answered, ignored and limited questions go to the log every hour. |
| Runs only on a vessel | It refuses to start in cloud mode, with a vessel configuration error, or when `APP_DISCOVERY_ENABLED=false` |

Settings (all optional) in `.env`: `APP_DISCOVERY_ENABLED`, `APP_DISCOVERY_PORT`, `APP_DISCOVERY_BIND` and `APP_TLS_CA_FILE`. The installer writes the last one.

## 6. Security of discovery and of the connection

### 6.1 The principle: discovery is a hint, the certificate is the proof

Discovery has no authentication. Anyone on the LAN can send questions and fake answers. So the client never trusts an answer by itself. It uses the answer only to learn where to look. It then proves the server's identity with the certificate chain before any page or password is sent.

### 6.2 How the client checks a server

| Step | What happens | Stops |
|---|---|---|
| 1 | `GET http://<address>/health` must return NaviSuite's health format in vessel mode | Ordinary web servers and printers |
| 2 | `GET http://<address>/navisuite-ca.crt` must be a CA certificate that is valid now, at most 16 KB. If discovery announced a fingerprint, it must match. | A device that announces one CA and serves another |
| 3 | `GET https://<address>/health`, trusting **only** that CA. The server's certificate must be issued and signed by it, valid now, not itself a CA, and made out to the address used. | A device that copied the vessel's CA certificate but does not hold its key |
| 4 | Decide whether this CA is the vessel's own (below) | An impostor with its own complete CA |

Steps 1 and 2 use plain HTTP. They only collect information that steps 3 and 4 verify.

The CA counts as the vessel's own in one of three ways, strongest first:

1. **IT pinned it.** `client-config.json` contains the fingerprint (section 7). Anything else is refused.
2. **Windows already trusts it.** For example, IT installed `ca.crt` on the PC or through group policy.
3. **The person confirms it (trust on first use).** The client shows the fingerprint in four short lines and explains what to compare it with. The server installer prints it, and `check-health.ps1` shows it on the master PC.

After that, the CA certificate is stored with the server settings. **Every later connection is pinned to it.** The status check (Node.js) and the page view (Chromium, through `setCertificateVerifyProc`) both refuse a certificate from any other CA, even one Windows trusts. The page view reports this as `ERR_CERT_AUTHORITY_INVALID`, the bar shows "Server not verified", and the page is replaced by a warning not to sign in.

### 6.3 Threats

| Threat | Effect | Mitigation | Remaining risk |
|---|---|---|---|
| **Fake answers** (spoofing) | A device answers discovery, pretending to be the server | The certificate check in 6.2. A fake server cannot produce a certificate from the vessel's CA without the CA key, which stays in `secrets\` on the master PC, readable by administrators only. | **On a PC's first connection, a person who does not compare the fingerprint can accept an impostor.** IT pinning removes this risk; see the recommendation below. |
| **Man in the middle** after setup | Someone intercepts traffic, for example by ARP spoofing | The pin. Tested: a server with another CA's certificate gets no HTTP request at all from the client, so no password or session cookie is sent. | None while the CA key stays secret |
| **Stolen CA key** | Copying `secrets\ca.key` lets someone make trusted certificates | Administrators-only ACL on the master PC | Changing the CA means re-pinning every client. This is the same risk as in Checkpoint 3. |
| **Server reinstalled with a new CA** | The pin no longer matches, so clients refuse it | This is intended. The client says why. | IT must re-pin or re-confirm each client. Keep `config\certs` and `secrets\` in the master PC's backups to avoid this. |
| **Reflection and amplification** | Faked sender addresses make the server flood a victim | Answers go only to private addresses. An answer is at most 476 bytes, about 6 times the smallest question. 5 answers per sender per 10 s and 20 per second overall, so the server sends at most about 10 KB/s however many questions arrive. | Negligible on a ship LAN |
| **Flooding the responder** | Many questions use CPU | Oversized datagrams are not parsed. Rate limits are applied before any work. The answer is cached. The service is separate from the web server, so the web pages are unaffected. | A flood can delay discovery, not the application |
| **Information disclosure** | Anyone on the LAN learns the vessel id and name, the host name, the release and the CA fingerprint | All of this is already public to the LAN through `/health` and the certificate. No user, credential, database or path information is sent. | Accepted. A visitor's laptop on the crew LAN learns that a NaviSuite server exists. |
| **Answers leaving the ship** | Discovery reaching the internet or the satellite link | The firewall rule allows UDP 47810 only from `LocalSubnet` on Private and Domain networks. The server ignores non-private senders. Broadcasts do not cross routers. | None |
| **Subnet scan looks like an attack** | The fallback sweep makes up to 253 HTTP requests to the /24 | It runs only when no discovery answer came. It touches only the PC's own private subnets and only `GET /health` on port 80. IT can turn it off (`discovery.sweep: false`). | An intrusion detection system may log it |
| **Planted settings file** | An ordinary user writes a `client-config.json` pointing at their own server | `%ProgramData%\NaviSuite Client` is writable by administrators and SYSTEM only. The installer sets this. | Relies on the installer's permissions; see section 12.3 |
| **Copied user settings** | The saved server settings are copied to another PC | DPAPI encryption is tied to the Windows user, so they do not open elsewhere. A changed or damaged file is discarded and setup runs again. | The settings are not secret, only their integrity matters |

**Recommendation.** Install every crew PC with a `client-config.json` that contains the vessel's fingerprint (section 7). Trust on first use then never happens, and no one can be tricked into accepting an impostor.

## 7. Settings

### 7.1 Per Windows user (set by the setup screen)

`%APPDATA%\NaviSuite\server.dat` holds:
- the vessel id and name
- up to eight addresses
- the HTTPS and HTTP ports
- the CA certificate and its fingerprint
- how the server was found and how it was trusted

The file is encrypted with `safeStorage` (Windows DPAPI). If DPAPI is unavailable, which should not happen on Windows, the client writes `server.json` instead, readable by that user only, and logs a warning. Writes are atomic, so a power cut cannot leave a half-written file.

The log is `%APPDATA%\NaviSuite\logs\client.log`, kept at about 1 MB.

### 7.2 Per PC (set by IT): `%ProgramData%\NaviSuite Client\client-config.json`

```json
{
  "server": {
    "hosts": ["master-pc", "192.168.10.5"],
    "vesselId": 12,
    "caSha256": "42:9A:7B:90:5E:CC:06:27:A6:4B:AC:99:31:53:39:6F:A7:60:62:0F:6A:7D:35:B7:75:27:8D:76:D3:BF:62:4E"
  },
  "lockServer": true,
  "clearSessionOnExit": true,
  "allowDevTools": false,
  "discovery": { "enabled": true, "port": 47810, "sweep": true }
}
```

| Key | Default | Meaning |
|---|---|---|
| `server.hosts` | | Addresses to try, in order |
| `server.httpsPort`, `server.httpPort` | 443, 80 | |
| `server.vesselId` | | Refuse any other vessel's server |
| `server.caSha256` | | Pin this CA. The client connects without asking and refuses any other CA. |
| `lockServer` | `false` | Crew cannot choose another server. Needs `server`. |
| `clearSessionOnExit` | `true` | Sign the user out when the program closes (section 9) |
| `allowDevTools` | `false` | Chromium developer tools, for support |
| `discovery.enabled`, `.port`, `.sweep` | `true`, 47810, `true` | |

A file with a mistake is ignored as a whole. The setup screen shows the reason, and the client behaves as if there were no file. If the saved per-user server disagrees with the IT settings, it is discarded and setup runs again.

## 8. Connection states and reconnection

| State | Bar | Meaning | Page |
|---|---|---|---|
| online | Connected (green) | `/health` says ok | NaviSuite |
| degraded | Connected - server needs attention (amber) | Answers, but a non-critical check failed, for example the queue | NaviSuite |
| down | Server problem (red) | Answers 503, for example MySQL stopped | Offline page with the reason |
| offline | Offline, with "retry in N s" | No answer. One missed check shows only in the bar; two in a row also cover the page. | Offline page with Retry now |
| untrusted | Server not verified | Certificate not from the pinned CA | Warning page: do not sign in |
| wrong-server | Wrong server | The address now answers for another vessel | Warning page |

- **Checks.** Every 10 s while connected. After a failure, retries come after 2, 4, 8, 15 and then every 30 s. Waking from sleep checks at once.
- **Several addresses.** If the usual address fails, the client tries the others it knows.
- **Address changed.** After 60 s offline, discovery runs again (at most once a minute). A new address is added only if it is the same vessel with the same pinned CA.
- **Coming back.** If the open page did not fail, the client simply shows it again, so a half-filled form is kept. If the page itself failed to load, it is loaded again.
- **What is lost.** A form submitted while the server is unreachable is not sent. The crew member sees the offline page and must submit again after reconnecting. The client does not queue work. Offline working is the vessel server's job, not the client's.

## 9. Individual sign-in

- The client never sees or stores a password. Each person signs in on the server's login page, and the server decides who may sign in (Checkpoint 1: only this vessel's crew).
- **Shared PCs.** With `clearSessionOnExit` (the default), closing NaviSuite deletes the session cookies and stored page data. The next person must sign in as themselves. IT can turn this off for single-user PCs. The server's own idle timeout (120 minutes, `SESSION_LIFETIME`) still applies.
- Session cookies on disk are encrypted with DPAPI (Electron's cookie encryption fuse), and travel over HTTPS only (`SESSION_SECURE_COOKIE=true`).
- **The installed program cannot be misused as a script runner.** Electron's Node.js modes (`ELECTRON_RUN_AS_NODE`, `NODE_OPTIONS`, `--inspect`) are switched off in the executable. The program loads only the code packed into it at build time, and checks that package for changes (asar integrity).

## 10. Several clients at once

Nothing in the design limits clients to one. Each client is an ordinary HTTPS browser session with its own cookie, stored in its own session file on the server.

The new test `MultipleClientsTest` proves this, with each request carrying only its own client's cookie and nothing shared in memory:
- Three crew members are signed in on three clients at once, and interleaved requests always see the right person.
- Signing out on one client leaves the others signed in.
- One person can be signed in on two PCs.
- Another vessel's crew cannot sign in.

**Capacity of the master PC** (Checkpoint 3 settings):

| Limit | Value | What it means |
|---|---|---|
| Apache worker threads | 64 | 64 requests handled at the same moment. Chromium opens at most 6 connections per server, and a NaviSuite page usually needs 1 or 2 at a time once its files are cached. |
| MySQL connections | 100 | One per running PHP request, plus the 3 background services |
| Sessions | File driver | One small file per signed-in client |

A vessel's crew of 20 to 30 on 5 to 15 PCs is well within these limits. Measuring real response times needs a Windows master PC (section 12.3).

## 11. Installer

Built with `npm run dist` in `desktop/`. The output is `desktop/dist/NaviSuite-Client-Setup-1.0.0.exe`.

| | |
|---|---|
| Type | NSIS, 64-bit, for all users of the PC (asks for administrator rights). The folder can be changed. |
| Size | 115 MB download, about 370 MB installed |
| Contains | Electron and the client's 17 source files. No MySQL, PHP, Laravel or Node.js modules. |
| Shortcuts | Desktop and Start menu: "NaviSuite" |
| Firewall | Rule "NaviSuite client discovery": allows inbound UDP to `NaviSuite.exe` from the local subnet, on Private and Domain networks, so discovery answers arrive without a Windows Security Alert prompt |
| IT settings folder | Creates `%ProgramData%\NaviSuite Client`. Administrators and SYSTEM have full control; Users can read only. |
| Silent install | `NaviSuite-Client-Setup-1.0.0.exe /S`. If `client-config.json` is in the same folder as the installer, it is copied into the IT settings folder. Since Checkpoint 8 also `/SERVER=`, `/FINGERPRINT=`, `/VESSEL=` and `/LOCK`, an install log, and refusal of downgrades; see [checkpoint-8-installers.md](checkpoint-8-installers.md) section 3. |
| Uninstall | Removes the program and the firewall rule. Keeps the IT settings, so a reinstall connects to the same server. |
| Code signing | **Not signed** (section 12.2) |

Suggested rollout per vessel:

1. Install the server (Checkpoint 3) and note the fingerprint it prints.
2. Write `client-config.json` with the server's name and address, the vessel id and that fingerprint.
3. On each crew PC, as administrator, run `NaviSuite-Client-Setup-1.0.0.exe /S` from a USB stick or share containing both files.

### Building

```
cd desktop
npm ci
npm test           # unit and integration tests (Node.js 22 or later)
npm run smoke      # starts the real program against a test server (needs a desktop session)
npm run dist       # Windows installer; works on Windows, macOS or Linux
```

## 12. Open issues

### 12.1 Blocking real offline use

1. **The pages still load libraries from public CDNs** (Checkpoint 0): jQuery, Bootstrap, DataTables, pdfmake, Popper and icon fonts. Without internet, many screens will not work properly, in the client and in any browser alike. These files must be copied into the app before a vessel goes live. This is the most important remaining item.

### 12.2 Decisions needed

2. **Code signing.** Windows SmartScreen warns about an unsigned installer ("Windows protected your PC"). An EV or OV code-signing certificate removes the warning. Until then, IT must choose "More info", then "Run anyway", or install silently. Should one be bought, and in whose name?
3. **Updates.** The client does not update itself. A new version means running the new installer on each PC. An update channel served by the vessel server is possible later. Should it be planned now?
4. **Single-user PCs.** Should any PCs, for example the master's cabin PC, keep the session after closing (`clearSessionOnExit: false`)?
5. **Emergency fallback.** If the client fails, crew can use Edge with `https://<server>/`, after IT installs `ca.crt` (from `http://<server>/navisuite-ca.crt`) into the PC's trusted roots. Is that acceptable as the documented fallback?

### 12.3 Not yet validated: do this on Windows before the first vessel

6. Install the client on Windows 10 and 11. Check:
   - the firewall rule
   - the `%ProgramData%` permissions (an ordinary user must not be able to write there)
   - that DPAPI is used (`server.dat`, not `server.json`)
   - that the silent install copies `client-config.json`
7. **Discovery on a real ship LAN.** Check broadcasts across switches and VLANs. Check Windows Firewall on the server with the Public profile, which needs the fix described in Checkpoint 3.
8. **Measure** sign-in and page times with 10 or more clients against a real master PC.
9. **Server side.** The new `NaviSuite-Discovery` service and firewall rule are part of the Checkpoint 3 scripts, which have not yet been run on Windows either.

### 12.4 Known limitations

10. **Switching address signs the user out.** Cookies belong to a host name. If the client moves from `master-pc` to `192.168.10.5`, for example after a DNS failure, the crew member must sign in again.
11. **System proxy.** The page view follows the Windows proxy settings. A proxy that does not exclude local addresses would break the connection. It is unusual on ship LANs. The fix is to exclude the server in the proxy settings.
12. **Rolling the server back to a release from before this checkpoint** leaves the `NaviSuite-Discovery` service failing, because the old release has no `navisuite:discovery` command. The web application is unaffected. Stop and disable the service, or rerun the new release's installer.
13. **The PHP firewall rule** allows UDP 47810 to `php.exe`, which the queue and scheduler services also run. Only the discovery service listens on that port.
14. **Still open from Checkpoint 3:** uploads of certificate copies, photos and signatures keep the file extension the browser sends. A crafted upload could be saved as `.php`, which is a risk on the cloud server. The fix is one line per upload (use the detected extension) and waits for your approval.

## 13. Effect on the cloud

None. The discovery command refuses to run in cloud mode. The `/health` change adds a check only in vessel mode. The new configuration keys default to off or empty. The `desktop/` folder is left out of server releases.

## 14. Database impact

None. No migration, no table, no change to data. The discovery service reads only the vessel's name.

## 15. Tests

| Suite | Tests | Result |
|---|---|---|
| Client unit and integration tests (`npm test`) | 28 | All pass |
| Client end-to-end smoke test (`npm run smoke`): the real Electron program against a test server | 7 steps | All pass |
| Laravel: new discovery tests | 13 | All pass |
| Laravel: new multi-client session tests | 4 | All pass |
| Laravel: whole `VesselServer` folder | 36 | All pass |
| Laravel: full suite | 349 | 332 pass, 8 skipped (MySQL-only tests), 9 fail. The 9 are the same pre-existing failures (Checkpoint 0). |

What the client tests prove:

| Area | Proven |
|---|---|
| Discovery | The question has the documented shape. Answers are checked field by field, and a wrong nonce, bad host or bad fingerprint is ignored. Broadcast addresses are computed per private network; public and link-local networks are skipped. Real UDP answers are merged. A search with no answer ends quietly. |
| Interoperability | The client finds the real PHP responder (`php artisan navisuite:discovery`) and reads its answer. The responder stays silent for a different vessel. |
| Certificates | The CA is read and fingerprinted. A server certificate from the pinned CA for the right name is accepted. A wrong name, a different CA, an expired certificate, a CA used as a server certificate and a self-signed certificate are refused. |
| Server check | A test server is identified, including its vessel and CA. A server whose HTTPS certificate comes from a different CA than it hands out is refused. HTTPS trusts only the pinned CA. A non-NaviSuite web server and an unreachable address are told apart. |
| Reconnection | Checks every 10 s, and backs off 2, 4, 8, 15, 30, 30 s. A reconnect is reported once. "Retry now" replaces the timer. An untrusted or wrong server counts as unreachable. |
| Settings | Saved encrypted and read back. A changed file is rejected. The certificate and fingerprint must agree. Plain-file fallback works. IT settings: defaults, pinning, lock, and a clear message for each kind of mistake. |

The smoke test starts the real program with IT settings pointing at a test server, then:

1. The server is verified, and the page loads over pinned HTTPS.
2. The server is stopped. Offline shows in the bar after one check, and the offline page after the second.
3. The server is restarted. The client reconnects by itself and keeps the open page.
4. The client is restarted while the server is down. The saved settings are used and the offline page shows.
5. The server returns. The failed page is loaded again.
6. The server is replaced by an impostor with a certificate from another CA. Both the status check and the page view refuse it (`ERR_CERT_AUTHORITY_INVALID`), the warning page shows, and the impostor receives **no HTTP request at all**.
7. The settings were saved encrypted (`server.dat`).

The installer was built with electron-builder on macOS. The contents and the Electron fuses of the Windows executable were checked. Windows itself was not available.

## 16. Files

**New, desktop client (`desktop/`):**
- `package.json`, `package-lock.json`, `electron-builder.yml`, `.gitignore`
- `build/installer.nsh` (firewall rule, IT settings folder), `build/icon.png`
- `src/main/`:
  - `main.js`: window, views, rules, IPC, menu
  - `discovery.js`, `probe.js`, `trust.js`, `http.js`, `hosts.js`, `config.js`, `connection.js`
- `src/preload/bar.js`, `src/preload/local.js`
- `src/renderer/`: `bar.html` and `.js`, `setup.html` and `.js`, `offline.html` and `.js`, `style.css`
- `test/`:
  - `hosts`, `trust`, `discovery`, `interop`, `probe`, `connection` and `config` tests (`.test.js`)
  - `helpers/pki.js`, `helpers/fake-vessel.js`
  - `smoke/run-smoke.js`

**New, server:**
- `app/Support/Discovery/DiscoveryProtocol.php`, `DiscoveryResponder.php`, `DiscoveryInfo.php`
- `app/Console/Commands/NaviSuiteDiscovery.php`
- `deploy/windows/templates/NaviSuite-Discovery.xml`
- `tests/Feature/VesselServer/DiscoveryTest.php`, `MultipleClientsTest.php`
- This document

**Modified (all new in this branch, from Checkpoint 3):**
- `config/navisuite.php`: `discovery` and `tls` settings, `health.discovery_max_age`
- `app/Support/Heartbeat.php`, `app/Support/HealthCheck.php`: the discovery heartbeat
- `deploy/windows/lib/NaviSuite.ps1`:
  - the fifth service
  - the shared service lists
  - `Install-NsBackgroundServices`
  - `Get-NsCaFingerprint`
- `deploy/windows/`:
  - `install-server.ps1`, `update-server.ps1`, `uninstall-server.ps1`, `backup-database.ps1`
  - `configure-firewall.ps1` (UDP 47810 rule)
  - `check-health.ps1` (fingerprint and a live discovery test)
  - `README.md`
- `deploy/build-release.sh`: leaves out `desktop/`
- `.env.vessel.example`: discovery settings, `APP_TLS_CA_FILE`
- `tests/Feature/VesselServer/HealthCheckTest.php`
- `docs/checkpoint-3-windows-vessel-server.md`, `docs/offline-implementation-roadmap.md`

No existing migration, controller, model, Blade view or frontend asset was changed.
