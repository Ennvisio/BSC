# Checkpoint 8: production Windows installers

**Status:** built and checked on macOS, nothing committed.
- Both installers compile, and the server installer's contents were checked
  file by file.
- The client was tested end to end with the real Electron program.
- **Neither installer has run on Windows yet.** The PowerShell they drive has
  never run either (no Windows or PowerShell here). Run the
  [end-to-end test plan](e2e-test-plan.md) on test PCs before any vessel.

| Requirement | Server: `NaviSuite-Server-Setup.exe` | Client: `NaviSuite-Client-Setup.exe` |
|---|---|---|
| What it sets up | Visual C++ runtime, Apache with PHP, MySQL, the Laravel application, the database, 6 Windows services, the HTTPS certificate, the firewall, sync settings, nightly backups, a startup check | The desktop program, the firewall rule for discovery, the administrator settings that pin it to the ship's server |
| Guided or silent | Wizard, or `/S /ANSWERS=file.ini` | Wizard, or `/S [/SERVER=... /FINGERPRINT=... /VESSEL=... /LOCK]` |
| Upgrade | Detects an installed server and upgrades it: database backup first, automatic rollback before the migrations, runtimes replaced when their version changed | Replaces the program; per-user and administrator settings stay |
| Configuration kept | `.env`, credentials, database, certificates, uploads, backups, `install.json` | `%ProgramData%\NaviSuite Client\client-config.json`, each user's chosen server |
| Starts after reboot | All services start with Windows and restart after failures; a startup task checks 4 minutes after boot and logs it | Not a service; started by the user |
| Uninstall | Programs and Features. Removes services and tasks; **keeps all data** unless the person says yes twice (or `/REMOVEDATA=YES`) | Programs and Features. Keeps the administrator settings unless `/REMOVESETTINGS` |
| Logs | `<Root>\setup\installer.log`, `<Root>\data\logs\setup\setup-*.log` (everything), `data\logs\startup\` | `%ProgramData%\NaviSuite Client\logs\install.log` |
| Version checks | Windows build, 64-bit, administrator, disk space, no downgrade, minimum version to upgrade from, database not newer than the release, test builds refused | Windows 10/11 64-bit, no downgrade; at run time, client and server versions (section 5) |

## 1. Building the installers

The installers are built on a Mac or Linux machine with PHP, Composer and
Node.js. The runtimes are prepared once on a Windows PC.

1. **Runtimes (Windows build PC, once per runtime version).**
   - Download Apache, PHP, MySQL, WinSW, `cacert.pem` and
     `vc_redist.x64.exe` from the official sites listed in
     [prepare-runtime.ps1](../deploy/windows/prepare-runtime.ps1).
   - Check them against the publishers' checksums.
   - Pin them with `.\prepare-runtime.ps1 -Downloads D:\dl -Output D:\rt -Pin`
     and commit [runtime.lock.json](../deploy/windows/runtime.lock.json).
   - Later builds run without `-Pin` and stop if any file differs from the
     pinned one.
   - The output folder holds the runtimes and `runtime.json`, with each
     version as the program itself reports it.
2. **Installers.**

       deploy/build-installers.sh --runtime /path/to/rt

   Output: `build/installers/<release>/`, containing:
   - `NaviSuite-Server-Setup.exe` and `NaviSuite-Client-Setup.exe`;
   - `SHA256SUMS`;
   - `build.json`: versions, commit, test results and runtime versions;
   - `answers.example.ini`;
   - the test logs.

### 1.1 The test gate

A production build is made only when all of these are true. Otherwise the
script stops before building anything:

| Check | Why |
|---|---|
| The working tree is clean | The installer is exactly one commit, so it can be rebuilt and reviewed |
| Full PHP suite: 0 failed, 0 errors, **0 skipped** | The MySQL tests (triggers, sequencer) must run, so `SYNC_MYSQL_TEST_DATABASE` and the other `SYNC_MYSQL_TEST_*` variables must be set |
| Desktop unit tests pass | |
| Electron smoke test passes | It runs the real client against a test server, so it needs a desktop session |
| `desktop/package.json` version equals [deploy/product.json](../deploy/product.json) | Both installers carry one version |

`--test-build` skips the gate and marks everything as a test build:
- the release and client version become `1.0.0-test.<commit>`;
- the server installer's first page says TEST BUILD and asks before going on;
- a silent install needs `/TESTBUILD`;
- `setup-server.ps1` refuses it otherwise.

**Today the gate fails.** Two existing tests fail (question C8-1), and the
working tree is uncommitted. So only test builds can be made until those are
resolved. That is intended.

### 1.2 Versions

- One product version, `X.Y.Z`, lives in `deploy/product.json`, and
  `desktop/package.json` must match it.
- Each release is named `<version>-<commit>`.
- `min_upgrade_from` is the oldest installed version a release can upgrade.
  `0.0.0` means servers installed with the scripts before this checkpoint.
- Every release now carries `release.json`, which the version checks read:
  product version, release, commit, test build, sync protocol and client
  compatibility.

### 1.3 Code signing

The installers are **not signed**. Windows SmartScreen will warn ("Windows
protected your PC"), and some antivirus products distrust unsigned
installers. The [build script](../deploy/build-installers.sh) has no signing
step yet (C8-2).

## 2. Server installer

### 2.1 Guided

Run `NaviSuite-Server-Setup.exe` as administrator. The pages:

1. **Welcome.**
2. **Folder** (new servers only). Default `C:\NaviSuite`, no spaces.
3. **Vessel and office connection** (new servers only):
   - vessel id;
   - server name;
   - cloud address, sync node id and token (all optional);
   - firewall checkbox.
4. **Progress.** Shows the setup script's output.
5. **Finish.** Shows:
   - the address the crew PCs open;
   - the certificate fingerprint;
   - the folder holding the client installer for the crew PCs;
   - the log file.

On an existing server, pages 2 and 3 are skipped. Everything is kept and the
server is upgraded or repaired.

### 2.2 Silent

    NaviSuite-Server-Setup.exe /S /ANSWERS=D:\vessel-12.ini     new server
    NaviSuite-Server-Setup.exe /S                               upgrade or repair
    NaviSuite-Server-Setup.exe /S /ANSWERS=D:\sync-only.ini     upgrade and add sync settings

| Option | Meaning |
|---|---|
| `/ANSWERS=<file>` | Settings ([answers.example.ini](../deploy/installer/server/answers.example.ini)). Needed for a new server. On an existing one only `[sync]` is used. |
| `/ROOT=<folder>` | Install folder (default `C:\NaviSuite`, or the one already installed) |
| `/ALLOWDOWNGRADE` | Install an older version over a newer one. Still refused if the database is newer (2.4). |
| `/TESTBUILD` | Required to install a test build silently |

| Exit code | Meaning |
|---|---|
| 0 | Done |
| 1 | Failed (see the log) or cancelled |
| 2 | Installed, but the health check found problems |
| 3 | Refused: this PC or the installed version is not compatible. **Nothing changed.** |
| 4 | A newer version is installed |
| 5 | Missing or wrong options |

### 2.3 What it does

The exe is a thin wrapper:
1. It unpacks into `<Root>\setup\payload-<version>`. That folder is owned by
   Administrators and only Administrators and SYSTEM can open it. This is so
   a non-administrator cannot swap the scripts between unpacking and running,
   which the user's TEMP folder would allow.
2. It runs [setup-server.ps1](../deploy/windows/setup-server.ps1) in 64-bit
   PowerShell.
3. It deletes the payload afterwards.

`setup-server.ps1`:
1. Starts a full transcript in `data\logs\setup\` (the last 30 are kept).
2. Runs the compatibility checks. A refusal changes nothing.
3. Installs the Visual C++ runtime if it is missing or older than the
   bundled one.
4. Picks the path for the server's state:
   - **New server:** `install-server.ps1` (Checkpoints 3 and 7) with the
     answers.
   - **Existing server:** `update-server.ps1`, then the runtimes (2.5).
     With `[sync]` answers, `install-server.ps1` is then run again with the
     server's own saved settings plus the new sync values.
   - **Uninstalled earlier with the data kept:** `install-server.ps1` with
     the saved settings brings the services back, then `update-server.ps1`.
5. Puts the client installer, a `client-config.json` pinned to this server's
   fingerprint, and a README in `data\client-setup`.
6. Writes `setup\last-result.ini`, which the finish page reads.

**The sync token:**
- The wizard passes it through the environment of the setup process only.
  It is never written to a file or a command line, and it is cleared
  afterwards.
- A silent install takes it from the answer file's `TokenFile` (recommended)
  or `Token`.
- It is stored encrypted with DPAPI (Checkpoint 7) and never logged. A test
  checks every log line in the scripts for it.

### 2.4 Upgrades and version checks

Checked before anything changes, in `setup-server.ps1` and again in
`update-server.ps1`, so a manual update gets the same protection:

| Check | Refused when |
|---|---|
| Downgrade | The installer's version is older than the installed one (unless `/ALLOWDOWNGRADE`). `update-server.ps1 -Rollback` is the way back. |
| Upgrade path | The installed version is older than the release's `min_upgrade_from` |
| Database schema | The database has a migration the release does not have, meaning a newer release migrated it. This is refused **even with `/ALLOWDOWNGRADE`**, because older code on a newer schema breaks silently. |
| Vessel | The answer file names a different vessel than the server's |
| Test build | Not accepted explicitly |
| Windows | Older than Windows 10 1607 or Server 2016, 32-bit, PowerShell below 5.1 |
| Disk | Less than 4 GB free (new server) or 2 GB (upgrade) |

The upgrade itself is the Checkpoint 3 procedure:
1. The new release is copied next to the current one.
2. The database is backed up.
3. The services are stopped and the folders swapped.
4. Migrations run and change capture is reinstalled.
5. The services are started and `/health` is checked.

A failure before the migrations puts the previous release back
automatically. A failed migration leaves the services stopped and prints the
restore and rollback commands.

### 2.5 Runtime updates

On an upgrade, each runtime whose version differs from the installer's is
replaced. The old one is kept as `runtime\<name>.previous`.
- Apache and PHP are replaced as whole folders. Their configuration lives in
  `config\` and is rewritten.
- WinSW: only the program files are replaced (the service definitions stay).
- PHP is checked before anything starts: version, thread-safe x64, and every
  extension the application needs.
- Apache's configuration is tested.
- On any failure the old runtimes go back and the services start again.
- **MySQL** is replaced only within the same release series (8.4.x to
  8.4.y), after a database backup. MySQL upgrades its system tables itself on
  the first start.
- A different series (8.0 to 8.4, or 8.4 to 9.x) is never applied
  automatically. The setup log says so, and the installed MySQL keeps
  running (C8-4).

### 2.6 After a reboot

Nothing new is needed for the services to come back:
- MySQL starts automatically;
- Apache, the queue, the scheduler, discovery and sync start delayed;
- each restarts itself after a failure: 10 s, 30 s, then 60 s;
- the failure count resets after an hour.

New: the **NaviSuite Startup Check** task runs 4 minutes after every boot
([startup-check.ps1](../deploy/windows/startup-check.ps1)). It:
- starts any NaviSuite service that is still stopped;
- waits for `/health`;
- writes one log per boot to `data\logs\startup` (the last 60 are kept).

### 2.7 Uninstall

From Programs and Features, or
`<Root>\setup\NaviSuite-Server-Uninstall.exe`:
- It asks first whether to remove the services. Data is kept.
- It then asks separately, defaulting to No, whether to delete everything.
- Silent: `/S` keeps the data. `/S /REMOVEDATA=YES` deletes everything,
  including the backups.
- It runs the installed release's own `uninstall-server.ps1`. That script
  gained `-Force`, so the uninstaller can pass its own confirmation, and it
  now also removes the startup task.

Installing again over kept data brings the server back as it was (2.3,
"uninstalled earlier").

## 3. Client installer

Built by electron-builder as in Checkpoint 4. Additions in
[installer.nsh](../desktop/build/installer.nsh):

| Option | Meaning |
|---|---|
| `/S` | Silent |
| `/SERVER=host[,host2]` | Writes the administrator settings for this server |
| `/FINGERPRINT=AB:CD:...` | Pins the server's certificate authority (recommended) |
| `/VESSEL=<id>` | Refuses another vessel's server |
| `/LOCK` | The crew cannot choose another server |
| `/ALLOWDOWNGRADE` | Install over a newer client |
| `/REMOVESETTINGS` | Uninstall: also delete the administrator settings |

Where the settings come from, in order:
1. `client-config.json` next to the installer. This is the kit the server
   installer leaves in `data\client-setup`: copy both files to a USB stick.
2. The command-line options. They are written by `NaviSuite.exe` itself
   (`--navisuite-write-managed-config`), which checks them with the same code
   that reads them at every start. A bad value leaves the old file untouched
   and exits with code 5.
3. Nothing: the existing settings are kept, which is the upgrade case.

Exit codes:

| Code | Meaning |
|---|---|
| 0 | Done |
| 3 | Not a 64-bit Windows 10 or 11 |
| 4 | A newer client is installed |
| 5 | Installed, but the settings given were refused |

## 4. Version compatibility at run time

A vessel server's `/health` now also says:

    "clients": { "api": 1, "min_version": "1.0.0" }

The client ([compat.js](../desktop/src/main/compat.js)) compares this with
its own version and the interface versions it understands. On a mismatch it:
- does not open the server;
- shows "This client and the server do not match", with which side needs
  updating;
- keeps checking, so it recovers by itself once the right version is in
  place.

`APP_MIN_CLIENT_VERSION` raises the minimum client version.
`navisuite.compat.client_api` is raised only by a change that breaks older
clients. Servers from before this checkpoint publish nothing and are treated
as api 1.

| Pair | Checked by | Result |
|---|---|---|
| Server installer and installed server | `setup-server.ps1`, `update-server.ps1` | Refuses downgrades, too-old installs and newer schemas (2.4) |
| Client installer and installed client | `installer.nsh` | Refuses downgrades |
| Client and server | `compat.js` against `/health` | Mismatch page; both installers ship matching versions |
| Vessel and cloud | Sync protocol (Checkpoint 5) | The handshake refuses another protocol version; `sync:health` reports it |

## 5. Files

| Area | Files |
|---|---|
| New | [deploy/build-installers.sh](../deploy/build-installers.sh), [deploy/product.json](../deploy/product.json), [deploy/installer/server/NaviSuite-Server.nsi](../deploy/installer/server/NaviSuite-Server.nsi), [answers.example.ini](../deploy/installer/server/answers.example.ini), [setup-server.ps1](../deploy/windows/setup-server.ps1), [startup-check.ps1](../deploy/windows/startup-check.ps1), [prepare-runtime.ps1](../deploy/windows/prepare-runtime.ps1), [runtime.lock.json](../deploy/windows/runtime.lock.json), [desktop/src/main/compat.js](../desktop/src/main/compat.js), tests |
| Changed | [build-release.sh](../deploy/build-release.sh) (release.json), [update-server.ps1](../deploy/windows/update-server.ps1) (version and schema checks, startup task), [install-server.ps1](../deploy/windows/install-server.ps1) (startup task), [uninstall-server.ps1](../deploy/windows/uninstall-server.ps1) (`-Force`, startup task), [lib/NaviSuite.ps1](../deploy/windows/lib/NaviSuite.ps1) (release helpers), [installer.nsh](../desktop/build/installer.nsh), client `main.js`, `config.js`, `probe.js`, `connection.js`, `bar.js`, `offline.js`, [HealthController](../app/Http/Controllers/HealthController.php) and [config/navisuite.php](../config/navisuite.php) (`clients` on /health) |
| Tests only | `BuildsRequisitionSchema` gained the real `vessel_surveys` migration (7 old failures fixed), and `FleetOverviewDashboardTest` drops it before making its own |

No migration and no database change.

## 6. Known limits

- **Not run on Windows.** This covers both installers, every PowerShell
  script, the runtime update, the startup task and the uninstaller. The
  [test plan](e2e-test-plan.md) is the acceptance test.
- Unsigned (C8-2).
- **The runtimes are not pinned yet.** `runtime.lock.json` is empty until
  someone downloads and pins them on a Windows PC, so no production server
  installer can be built before that.
- A MySQL series upgrade is manual (C8-4).
- The test build is 125 MB with placeholder runtimes: the client installer
  inside it is 115 MB. With real runtimes it will be several hundred MB,
  MySQL being the largest part. That is too large to send over most
  satellite links. For application-only updates, the release zip (18 MB)
  still works with `update-server.ps1`.
- The guided installer cannot change the sync settings of an existing server.
  Use an answer file with only `[sync]`, or `install-server.ps1`.
- Upgrading a vessel whose business modules are switched on still depends on
  the bootstrap tool for a vessel rebuilt from scratch (C7-5).

## 7. Questions

- **C8-1.** Two existing tests block the gate:
  - `FleetOverviewDashboardTest::test_ship_crew_cannot_open_certificate_categories`
    expects crew to get 403. The code deliberately lets them in
    (`isShipCrew()`), and they do write categories.
  - `ExampleTest::testBasicTest` expects `/` to answer 200 to a guest. It
    redirects to sign-in.

  Which is right in each case? Then I update the test, not the behaviour.
- **C8-2.** Is there a code-signing certificate (OV or EV) for the
  installers? Who holds it, and where should signing run?
- **C8-3.** Who builds the releases (a CI machine, or one developer's Mac),
  and where are the pinned runtimes and `SHA256SUMS` kept for audits?
- **C8-4.** MySQL series upgrades: should a future installer do them (with a
  dump and reload), or stay a planned manual job?
- **C8-5.** Should the server also offer the client installer for download on
  the LAN? Today IT copies it from `data\client-setup`. A download page adds
  convenience, but crew PCs would fetch an unsigned exe before they trust the
  server's certificate.
- **C8-6.** Which Windows versions are on the ships' PCs? The installers
  accept Windows 10 1607 or later and Server 2016 or later for the server, and
  Windows 10 or 11 64-bit for the clients.
