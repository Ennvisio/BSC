# Checkpoint 3: Windows Vessel Server Deployment

**Status:** Implemented on branch `feature/offline-sync`, awaiting review. **The Windows scripts have not yet been run on Windows** (section 11).
**Date:** 2026-11-20
**Plan:** builds on [checkpoint-1-app-mode.md](checkpoint-1-app-mode.md) and [checkpoint-2-sync-foundation.md](checkpoint-2-sync-foundation.md).
**Numbering:** this checkpoint follows your definition. In [offline-implementation-roadmap.md](offline-implementation-roadmap.md), checkpoint 3 was the vessel data bootstrap and checkpoint 9 the installer. This checkpoint covers the server half of checkpoint 9. The bootstrap is still to come (section 12, item 2).

---

## 1. What this checkpoint delivers

The ship's master PC can run NaviSuite as a production server for the crew PCs on the LAN, with no internet.

| Requirement | Delivered as |
|---|---|
| Production-ready Windows hosting | Apache 2.4 (Apache Lounge) with PHP 8.3 as an Apache module. Hardened configuration (section 4). `php artisan serve` is not used anywhere. |
| Local MySQL connection | MySQL 8.0 or 8.4 on the same PC, listening on `127.0.0.1` only. The app uses its own MySQL user with rights on its own database only. |
| Environment-specific configuration | `.env` generated from [.env.vessel.example](../.env.vessel.example) with `APP_MODE=vessel`, `QUEUE_CONNECTION=database` and fresh secrets. The install choices are saved in `config\install.json`, so updates re-create the same configuration. |
| Local storage for attachments | `storage` and the three upload folders (`images\cert_copy`, `images\signature`, `images\userphoto`) are junctions into `C:\NaviSuite\data`. They survive every update. |
| Queue worker and scheduler | Two Windows services, `NaviSuite-Queue` (`queue:work`) and `NaviSuite-Scheduler` (`schedule:work`), run by WinSW 2.12. Both report a heartbeat to `/health`. |
| Automatic start and recovery | All four services start with Windows. After a failure, Windows restarts them after 10, 30 and 60 seconds. |
| LAN HTTP/HTTPS endpoint | HTTPS on port 443 with a certificate from the vessel's own certificate authority. Plain HTTP on port 80 serves `/health` and the CA certificate, and redirects everything else to HTTPS. |
| Health check | `GET /health` (section 6) and [check-health.ps1](../deploy/windows/check-health.ps1). |
| Firewall documentation | Section 5, implemented by [configure-firewall.ps1](../deploy/windows/configure-firewall.ps1). |
| Secure local configuration | Locked folder permissions, one virtual account per service, no secrets on command lines, and only asset files served (section 7). |
| Deployment scripts and documentation | [deploy/windows/](../deploy/windows/), [deploy/build-release.sh](../deploy/build-release.sh) and this document |
| MySQL 3306 not exposed | Bound to `127.0.0.1`, X Protocol (33060) off, and both ports blocked inbound by the firewall |
| Cloud deployment preserved | Nothing changes on the cloud unless it is switched on (section 9) |

## 2. What runs on the master PC

```
C:\NaviSuite
  app\                  the release (replaced on every update)
    storage\         -> data\storage            (junction)
    images\cert_copy -> data\images\cert_copy   (junction)
    images\signature -> data\images\signature   (junction)
    images\userphoto -> data\images\userphoto   (junction)
    .env                readable by the three application services only
  app.previous\         the release before the last update, for -Rollback
  data\
    storage\            Laravel storage: attachments, sessions, cache, logs
    images\             uploaded certificates, signatures, photos
    mysql\              database files (MySQL service only)
    backups\            daily database backups (administrators only)
    logs\               apache, php, mysql, queue, scheduler, maintenance
    tmp\                upload and MySQL temporary files
  runtime\              apache, php, mysql, winsw (unchanged by updates)
  config\               httpd.conf, php.ini, my.ini, certs\, install.json
  secrets\              MySQL root password, CA key (administrators only)
  artisan.cmd           run artisan with the server's php.ini
```

| Service | Program | Account | Start | Depends on |
|---|---|---|---|---|
| `NaviSuite-MySQL` | `mysqld` | `NT SERVICE\NaviSuite-MySQL` | Automatic | |
| `NaviSuite-Web` | Apache `httpd` with PHP | `NT SERVICE\NaviSuite-Web` | Automatic (delayed) | |
| `NaviSuite-Queue` | `php artisan queue:work database --sleep=3 --tries=3 --timeout=120 --memory=256` | `NT SERVICE\NaviSuite-Queue` | Automatic (delayed) | MySQL |
| `NaviSuite-Scheduler` | `php artisan schedule:work` | `NT SERVICE\NaviSuite-Scheduler` | Automatic (delayed) | MySQL |
| `NaviSuite-Discovery` | `php artisan navisuite:discovery` (UDP 47810). Added in Checkpoint 4, see [checkpoint-4-desktop-client.md](checkpoint-4-desktop-client.md). | `NT SERVICE\NaviSuite-Discovery` | Automatic (delayed) | |

- **Apache does not depend on MySQL on purpose.** If MySQL is down, Apache still answers and `/health` reports the database failure, instead of the whole server looking dead.
- **Recovery.** Every service uses the same rules: restart after 10 s, then 30 s, then 60 s. The failure count resets after an hour without failures. `failureflag` is set, so a service that stops itself with an error code is restarted too, not only one that crashes.
- **Never run `php artisan queue:restart` on a vessel server.** It makes the worker exit with code 0, which Windows treats as a deliberate stop, so it would not restart. Restart the service instead. The same applies to the `--max-time` and `--max-jobs` options, which are therefore not used. When the worker reaches its 256 MB memory limit, it exits with code 12 and is restarted.
- **Scheduled tasks.** The schedule in [routes/console.php](../routes/console.php) is now built by [app/Console/VesselSchedule.php](../app/Console/VesselSchedule.php):
  - `pms:refresh-jobs` daily at 00:15, as before. On a vessel server it runs for this vessel only.
  - On vessel servers only, two one-minute heartbeats. One is recorded by the scheduler itself. The other is a queued job, so it proves that the queue worker is processing jobs.
- **Daily maintenance.** The Windows scheduled task "NaviSuite Maintenance" runs as SYSTEM at 02:30, or at the next start if the PC was off. It does three things:
  - backs up the database, keeping 14 days and always at least 3 backups
  - renews the certificate when needed
  - clears old temporary files

## 3. Installing

### 3.1 What you need

| Item | Where from | Notes |
|---|---|---|
| Windows 10 or 11 Pro (64-bit), or Windows Server 2016 or later | | A fixed IP address or a DHCP reservation for this PC |
| Microsoft Visual C++ 2015-2022 Redistributable (x64) | Microsoft | Needed by Apache, PHP and MySQL |
| Apache 2.4, Win64 | apachelounge.com | The zip contains an `Apache24` folder |
| PHP 8.3, VS16 x64 **Thread Safe** | windows.php.net | It must be the Thread Safe build, which contains `php8apache2_4.dll` |
| MySQL Community Server 8.4 LTS (or 8.0), Windows x64 **ZIP archive** | dev.mysql.com | Not the MSI installer. Rename the extracted folder to `mysql`. |
| WinSW 2.12.0, `WinSW-x64.exe` | github.com/winsw/winsw releases | |
| A NaviSuite release zip | `deploy/build-release.sh` (section 3.3) | |

Put the runtimes in one folder, for example on a USB stick:

```
D:\navisuite-runtime\
  Apache24\     bin\httpd.exe, bin\openssl.exe, modules\...
  php\          php.exe, php8apache2_4.dll, ext\...
  mysql\        bin\mysqld.exe, bin\mysql.exe, bin\mysqldump.exe
  winsw\        WinSW-x64.exe
```

The installer checks the version of each one and stops with a clear message if one is missing, too old, the wrong build, or cannot start because the Visual C++ runtime is missing.

### 3.2 Steps

1. **On the cloud,** register the vessel's server and note the two values it prints. The token is shown only once.
   ```
   php artisan sync:register-node vessel --vessel=12 --name="MV Example master PC"
   ```
2. **On the master PC:**
   - Set the power plan so it never sleeps.
   - Set Windows Update active hours to cover working hours.
   - Mark the ship's LAN as a Private network.
3. Unzip the release, for example to `D:\navisuite-2026.11.20-b63bc21`.
4. Open Windows PowerShell **as administrator** and run:
   ```
   cd D:\navisuite-2026.11.20-b63bc21
   powershell -ExecutionPolicy Bypass -File .\deploy\windows\install-server.ps1 `
       -VesselId 12 -RuntimeSource D:\navisuite-runtime `
       -SyncNodeId <id from step 1> -SyncNodeToken <token from step 1>
   ```
   Add `-ImportSqlDump D:\vessel12.sql` to load data into the new database (section 12, item 2).
5. The script ends with a health check and prints the address for the crew.
6. **On each crew PC:**
   - Open `http://<server>/navisuite-ca.crt`.
   - Install the certificate into "Trusted Root Certification Authorities". From an elevated prompt, `certutil -addstore -f Root navisuite-ca.crt` does the same.
   - Then open `https://<server>/`.

| Parameter | Default | Meaning |
|---|---|---|
| `-VesselId` | required | `vessels.id` in the cloud database. It can never change for this PC. |
| `-Root` | `C:\NaviSuite` | Install folder. Must not contain spaces. |
| `-RuntimeSource` | | Folder from section 3.1. Not needed when `runtime\` is already filled. |
| `-ServerName` | computer name | Host name in `APP_URL` and the certificate |
| `-ExtraNames` | | More host names for the certificate |
| `-SyncNodeId`, `-SyncNodeToken` | | From step 1. Can be added later by running the script again. |
| `-SupportAccounts` | | Shore e-mails allowed to sign in read-only (Checkpoint 1) |
| `-ImportSqlDump` | | Load a dump into the empty database before migrating |
| `-FirewallRemoteAddress` | `LocalSubnet` | Who may connect (section 5) |
| `-IncludePublicProfile` | off | Also allow connections on a "Public" network |
| `-SkipFirewall` | off | Leave the firewall alone |
| `-UseLocalSystem` | off | Run all services as LocalSystem instead of virtual accounts. Only for troubleshooting. |
| `-InnoDbBufferPoolMB` | 512 | MySQL memory. Around a quarter of the PC's RAM is reasonable. |
| `-SkipPortsImport` | off | Do not load the UN/LOCODE port list |

### 3.3 What the installer does

Every step checks what is already there first, so the script can be run again at any time, for example to add the sync token or repair a service. It never overwrites an existing `.env`, database, certificate or uploaded file. It refuses to continue if the PC is already set up for a different vessel.

1. **Checks.**
   - Administrator rights.
   - Windows and PowerShell versions.
   - That the files are a release.
   - That ports 80, 443 and 3306 are free. It names IIS (HTTP.sys) or any other program using them.
   - The version of each runtime.
2. **Folders.** Creates the layout from section 2 and locks it to administrators and SYSTEM. Copies the runtimes.
3. **Application files.** Copies the release to `app\`, creates the data junctions, and moves any files already in those folders into `data\`.
4. **Secrets.** Generates:
   - the MySQL root password, in `secrets\mysql-root.cnf`
   - `APP_KEY`
   - the application's database password
   - `APP_HEALTH_TOKEN`

   It then writes `.env`. All passwords are 32 or more random letters and digits.
5. **Configuration files.**
   - Writes `httpd.conf`, `php.ini`, `my.ini`, the WinSW service files and `artisan.cmd` from [deploy/windows/templates/](../deploy/windows/templates/).
   - Checks that every PHP extension the application needs loads.
6. **Certificate.** Creates the vessel CA once, then the server certificate (section 4.2).
7. **MySQL.**
   - Initialises the data folder with the root password already set, so root never exists without one.
   - Installs and starts the service.
   - Confirms that MySQL listens on `127.0.0.1` only.
   - Creates the database and the application user from [create-database.sql](../deploy/windows/templates/create-database.sql).
   - Loads the dump, if one was given.
8. **Laravel.**
   - `migrate --force`
   - `sync:triggers install`
   - `ports:import`, if the ports table is empty
   - `config:cache` and `view:cache`
   - `navisuite:status`
9. **Services.** Installs Apache, the queue worker and the scheduler as services, then sets for each:
   - its account
   - the recovery rules
   - delayed start
   - folder rights

   Apache gets its own PATH so PHP finds its DLLs. The PC's PATH is not changed.
10. **Firewall and maintenance task.** Sets the firewall rules and registers the daily maintenance task.
11. **Start and check.** Starts everything and waits up to 150 seconds for `/health` to report `ok`. The scheduler and queue report in once a minute.

### 3.4 Building a release

```
deploy/build-release.sh [version]
```

This writes `build/navisuite-<version>.zip`. The default version is `<date>-<commit>`, with `-dirty` added when there are uncommitted changes. The release contains:
- the application
- `composer install --no-dev` packages
- a `RELEASE` file
- `deploy\windows`
- the UN/LOCODE files used by `ports:import`

It never contains:
- `.env`, SQL dumps, zip files or logs
- uploaded files and attachments
- tests, docs, the `.git` folder, or development packages

The script checks this before packing and fails if anything slipped in. The current build is 18 MB.

## 4. Web server

### 4.1 What is served

The application's web root is the project root, set in `bootstrap/app.php`. The cloud's `.htaccess` serves any file that exists (audit R10). The vessel server ignores `.htaccess` files (`AllowOverride None`) and uses an allow-list instead:

| Request | Result |
|---|---|
| A real file under `/css`, `/js`, `/images`, `/assets`, `/svg`, or `/favicon.ico`, `/robots.txt` | Served, cached for an hour |
| A `.php`, `.phtml`, `.phar`, `.htaccess`, `.ini`, `.log`, `.sql` or `.env` file in those folders | Not served. The request goes to Laravel, which answers 404. |
| Everything else, including `/.env`, `/composer.json`, `/artisan`, `/vendor/...`, `/storage/...`, `/config/...`, `/database/...`, `/public/...`, dumps and zips | Goes to Laravel (`index.php`), which answers 404 or the page |
| `/navisuite-ca.crt` | The vessel's public CA certificate, on HTTP and HTTPS |

PHP runs only through `/index.php`. It is also switched off in `data\` and in the upload folders. So a file uploaded with a `.php` name is never executed, even if it is requested directly.

### 4.2 HTTPS

| Setting | Value |
|---|---|
| Protocols | TLS 1.2 and 1.3. TLS 1.1 and older are refused. |
| Ciphers | ECDHE and DHE with AES-GCM or ChaCha20 only |
| Certificate authority | One per vessel, RSA 3072, valid 10 years. The key is in `secrets\`. |
| Server certificate | RSA 2048, valid 825 days, the longest Apple devices accept. It covers the computer name, `<name>.local`, `localhost`, the PC's FQDN, every LAN IPv4 address and `127.0.0.1`. |
| Renewal | Daily by the maintenance task: when fewer than 60 days remain, or when the PC's names or addresses changed. Clients need no change, because they trust the CA. By hand: `new-certificate.ps1 -Force -RestartWeb`. |
| Headers | `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: same-origin`, `Server: Apache` (no version). `X-Powered-By` is removed and TRACE is refused. |
| HSTS | Not sent. With a ship-private certificate, HSTS would lock every browser out if the certificate ever had to be replaced in a hurry. |
| Other limits | 25 MB per request (`LimitRequestBody`), 20 MB per file (PHP). There are timeouts against slow clients. |

### 4.3 PHP and MySQL settings

| PHP (`php.ini`) | Value |
|---|---|
| Extensions | curl, fileinfo, gd, mbstring, openssl, pdo_mysql, zip, plus OPcache. ctype, dom, tokenizer, xml and iconv are built in. |
| Errors | `display_errors=Off`, logged to `data\logs\php\` |
| Limits | `memory_limit=512M`, `upload_max_filesize=20M`, `post_max_size=25M`, `max_input_vars=5000` |
| Other | `expose_php=Off`, `date.timezone=Asia/Dhaka`, OPcache with timestamp checks so updates take effect |

| MySQL (`my.ini`) | Value | Why |
|---|---|---|
| `bind-address` | `127.0.0.1` | Reachable from this PC only |
| `mysqlx`, `named_pipe`, `shared_memory` | off | No other ways in |
| `local_infile`, `secure_file_priv` | `0`, `NULL` | No file import or export from SQL |
| Character set | `utf8mb4`, `utf8mb4_unicode_ci` | Same as the application |
| `default-time-zone` | `+06:00` | Matches the application's Asia/Dhaka time |
| `log_bin_trust_function_creators` | `1` | Lets the application user install the sync triggers while binary logging is on. Tested: without it MySQL refuses them. |
| Binary log | kept 7 days | Lets a backup be rolled forward after a fault |
| `innodb_flush_log_at_trx_commit`, `sync_binlog` | `1`, `1` | A committed requisition survives a power cut |

`skip-name-resolve` is deliberately not set. With it, `root@localhost` cannot sign in over TCP on Windows. MySQL only listens on `127.0.0.1`, so name resolution costs nothing.

The application user (`navisuite`) has these rights on its own database only:
- SELECT, INSERT, UPDATE, DELETE
- CREATE, ALTER, DROP, INDEX, REFERENCES
- CREATE TEMPORARY TABLES, LOCK TABLES
- TRIGGER, CREATE VIEW, SHOW VIEW

It has no global rights, no FILE and no SUPER.

## 5. Windows Firewall

All rules are in the firewall group **NaviSuite**. [configure-firewall.ps1](../deploy/windows/configure-firewall.ps1) replaces them as a set every time it runs.

| Rule | Direction | Action | Protocol and port | Program | From | Profiles |
|---|---|---|---|---|---|---|
| NaviSuite HTTPS (LAN) | Inbound | Allow | TCP 443 | `runtime\apache\bin\httpd.exe` | `LocalSubnet` | Domain, Private |
| NaviSuite HTTP (LAN) | Inbound | Allow | TCP 80 | `runtime\apache\bin\httpd.exe` | `LocalSubnet` | Domain, Private |
| NaviSuite-Discovery (added in Checkpoint 4) | Inbound | Allow | UDP 47810 | `runtime\php\php.exe` | `LocalSubnet` | Domain, Private |
| NaviSuite block MySQL | Inbound | Block | TCP 3306, 33060 | any | any | all |

- **MySQL is protected twice.** It listens on `127.0.0.1` only, and the block rule stops any inbound connection to 3306 or 33060. Windows Firewall does not filter traffic from the PC to itself, so the application is unaffected. A block rule always wins over an allow rule, so a later mistake cannot open MySQL.
- **Other subnets.** `LocalSubnet` means the subnets of this PC's own adapters. If crew PCs are on other subnets, for example bridge and engine-room networks, list them:
  ```
  .\configure-firewall.ps1 -RemoteAddress 192.168.10.0/24,192.168.20.0/24
  ```
- **Public networks.** Windows sometimes classes a new network as Public. The allow rules do not cover Public, so crew PCs cannot connect. The script warns about this and prints the fix:
  ```
  Set-NetConnectionProfile -InterfaceIndex <n> -NetworkCategory Private
  ```
  Use `-IncludePublicProfile` only if the profile cannot be changed.
- **Leftover rules.** Allow rules for Apache or MySQL made outside the script, for example by a "Windows Security Alert" prompt during a manual install, are disabled and reported.
- **Other warnings.** The script warns when the firewall is switched off for a profile, and when the PC gets its IP address from DHCP without a reservation.

To check from a crew PC:
```
Test-NetConnection <server> -Port 443     # TcpTestSucceeded : True
Test-NetConnection <server> -Port 3306    # TcpTestSucceeded : False
```

## 6. Health endpoint

`GET /health` is answered by Laravel without a session or cookies, on HTTP and HTTPS.

```
{"status":"ok","app":"NaviSuite","mode":"vessel","vessel":{"id":12,"name":"MV Example"},
 "release":"2026.11.20-b63bc21","time":"2026-11-20T10:15:00+06:00"}
```

| Status | HTTP code | Meaning |
|---|---|---|
| `ok` | 200 | Everything works |
| `degraded` | 200 | The crew can work, but something needs attention: low disk space, scheduler or queue not reporting, or sync capture incomplete |
| `down` | 503 | A critical check failed: configuration, database or vessel record, writable storage, or PHP extensions |

The individual checks (`checks`) are shown only in these cases:
- the request comes from the server itself, as `check-health.ps1` does
- the caller's address is in `APP_HEALTH_DETAIL_IPS`
- the caller sends the `X-Health-Token` header with `APP_HEALTH_TOKEN`

Other LAN callers see only the summary above. The client app will use it to find the server.

| Check | Critical | Fails when |
|---|---|---|
| `configuration` | yes | Mode or vessel id invalid |
| `database` | yes | No connection, or this vessel's row is missing |
| `storage` | yes | `storage/app`, `storage/logs` or the cache folder is not writable |
| `php` | yes | A required extension is missing |
| `disk` | no | Less than 2 GB free |
| `scheduler` | no | No scheduler heartbeat for 3 minutes |
| `queue` | no | No queue heartbeat for 5 minutes. It also reports pending and failed jobs. |
| `sync_capture` | no | Fewer sync triggers installed than expected |

On the cloud, `/health` returns 404 unless `APP_HEALTH_ENABLED=true`.

[check-health.ps1](../deploy/windows/check-health.ps1) adds checks from Windows. It verifies:
- the four services are running, and how each one starts
- MySQL is not listening on any LAN address
- the firewall rules exist
- the network profile
- the certificate expiry, and that Apache presents that certificate
- the detailed `/health` result

It exits with 1 on a failure.

## 7. Security

| Area | Measure |
|---|---|
| Folder rights | `C:\NaviSuite` is closed to ordinary Windows users. Only administrators and SYSTEM can change it. A crew member signed in at the master PC cannot read `.env`, the database files, backups or keys. |
| Service accounts | Each service runs as its own virtual account with only the rights it needs. Apache, the queue and the scheduler can read the application and write only `data\storage`, `data\images`, `data\tmp`, their logs and `bootstrap\cache`. MySQL alone can read `data\mysql`. Apache alone can read the TLS key. |
| `.env` | Readable by the three application services and administrators. It is never served over HTTP (section 4.1). Debug is forced off. |
| Secrets | Generated on the PC. Passwords never appear on a command line. MySQL is given them through option files in `secrets\`. The SQL that sets the passwords is written there, run and deleted at once. |
| MySQL | Loopback only, X Protocol off, a firewall block, and an application user limited to one database. The root password lives only in `secrets\mysql-root.cnf`. |
| Uploads | Never executed (section 4.1). PHP temporary files go to `data\tmp`. |
| Configuration cache | After `config:cache`, `.env` is no longer read at runtime. After editing `.env`, run `C:\NaviSuite\artisan.cmd config:cache` and restart the three application services. |

## 8. Updating, backing up and removing

| Task | Command (elevated, from the release's `deploy\windows`) |
|---|---|
| Install a new release | `.\update-server.ps1 -ReleasePath D:\navisuite-<version>.zip` |
| Go back to the previous release | `.\update-server.ps1 -Rollback` |
| Back up the database now | `.\backup-database.ps1` |
| Restore a backup | `.\backup-database.ps1 -Restore C:\NaviSuite\data\backups\<file>.zip` (asks for confirmation and takes a safety backup first) |
| Renew the certificate | `.\new-certificate.ps1 -Force -RestartWeb` |
| Check the server | `.\check-health.ps1` |
| Remove the services, keep all data | `.\uninstall-server.ps1` |
| Remove everything | `.\uninstall-server.ps1 -RemoveData` (asks for confirmation) |

**Update sequence:**
1. Copy the new release to `app.new` while the crew keeps working.
2. Back up the database.
3. Stop Apache, the queue and the scheduler.
4. Swap the folders (`app` to `app.previous`, `app.new` to `app`).
5. Carry over `.env`, the junctions and the rights.
6. Re-create the configuration from the templates.
7. Migrate and reinstall change capture.
8. Rebuild the caches.
9. Start the services and check health.

The stop is short: it lasts only for steps 3 to 9.

If a step before the migrations fails, the old release is put back and started automatically. If a migration fails, the three services stay stopped, so nobody writes to a half-updated database, and the script prints the two commands that restore the backup and roll back. Rollback restores the code only. Database changes are undone by restoring the backup.

**Backups** are consistent `mysqldump` copies taken while the crew works. They are stored as zip files in `data\backups`. They are on the same disk as the database, so they protect against mistakes and failed updates, not against losing the PC. Uploaded files are not included. Until sync gives the cloud a full copy, copy `data\backups`, `data\storage` and `data\images` to other media regularly.

## 9. Effect on the cloud

| Change | Effect on the cloud |
|---|---|
| `/health` route | Returns 404, because it is off by default on the cloud |
| Schedule moved to `App\Console\VesselSchedule` | Same single task at the same time. A test compares it with the previous schedule. The heartbeats are added in vessel mode only. |
| New migration `2026_11_22_100000_create_queue_tables` | Creates `jobs`, `job_batches` and `failed_jobs` only if they are missing. The cloud keeps `QUEUE_CONNECTION=sync`, so they stay empty. They are excluded from sync. |
| `.env.example` | A commented `APP_HEALTH_ENABLED` example only |
| `.gitignore` | Adds `/build/` |
| Deployment files | `deploy/` is new and not used by the cloud |

The cloud's hosting, `.htaccess` and deployment are unchanged.

## 10. Database impact

| Change | Detail | Reversible |
|---|---|---|
| New tables `jobs`, `job_batches`, `failed_jobs` | Laravel's standard queue tables. Each one is created only if it does not exist yet. Existing tables and data are untouched. | Yes. Rollback drops the three tables. |
| Existing migrations, tables and data | Unchanged | |
| Vessel server only | Creates the database `navisuite` and the user `navisuite`@`127.0.0.1` and `@localhost`. It installs the 277 sync triggers. | `uninstall-server.ps1 -RemoveData` |

## 11. Tests and validation

### 11.1 Automated tests

There are 19 new tests in `tests/Feature/VesselServer`.

| Test file | Tests | What is proven |
|---|---|---|
| `HealthCheckTest` | 13 | 404 on the cloud by default, and works when switched on. `ok` with the vessel name. Sets no cookies. Degraded without heartbeats or with stale ones. Down when the vessel is missing, the configuration is wrong or an extension is missing. Degraded on low disk space. Sync capture before and after installing the triggers. The LAN sees the summary only, and listed addresses or the token unlock the details. |
| `BackgroundServicesTest` | 6 | Cloud schedule unchanged. Vessel schedule has three events. The scheduler heartbeat works. A real `queue:work` on the database queue processes the queue heartbeat. A failing job lands in `failed_jobs`. The queue-table migration repeats and reverses cleanly. |

| Full suite | Tests | Pass | Fail | Skipped |
|---|---|---|---|---|
| After Checkpoint 2 | 313 | 296 | 9 | 8 |
| After Checkpoint 3 | 332 | 315 | 9 | 8 |

The 9 failures are the same ones that failed before Checkpoint 1: sidebar counts query tables that the partial test schemas do not build. The 8 skipped tests are the MySQL tests. With a MySQL server they all pass, and they were run again for this checkpoint.

### 11.2 Validation outside the test suite

| What | How | Result |
|---|---|---|
| Apache allow-list, redirect, CA download, headers, TLS | The real `httpd.conf` template on Apache 2.4 (macOS build), with a decoy web root holding `.env`, dumps, logs, vendor files and `.php` files in asset and upload folders. 28 HTTPS and 5 HTTP requests were made. | Every sensitive path went to `index.php`. Assets were served. `.php` files in asset and upload folders were not served. HTTP redirected to HTTPS, except `/health` and the CA certificate. TLS 1.1 was refused. TRACE returned 405. |
| Certificates | The `openssl.cnf` template with OpenSSL 3.6. A CA and a server certificate were made with the script's exact commands. | The chain verifies for TLS server use, and the names and IP addresses are in the certificate |
| `my.ini` | `mysqld --validate-config` and a real start on MySQL 9.3 | Valid. Every setting in section 4.3 is in effect. Only `127.0.0.1` was listening, and nothing on 33060. |
| Root password at initialisation | `--initialize-insecure --init-file`, as the installer does | Root was refused without a password and accepted with it |
| Application user | `create-database.sql`, run twice | Runs repeatedly without errors. The user is refused outside its own database. |
| Migrations and triggers as the application user | All migrations, then `sync:triggers install`, as that user over TCP with `caching_sha2_password` | Migrations succeeded. Triggers were refused without `log_bin_trust_function_creators` and all 277 installed with it. |
| `/health` as the application user | `HealthCheck` against that database | Sync capture reported correctly. An empty database is correctly reported as `down` (no vessel row). |
| Release build | `deploy/build-release.sh` | Exclusions verified, and `create-database.sql` included |
| WinSW service files | `xmllint` | Well-formed |

### 11.3 Not yet validated: run this on a Windows PC before the first vessel

**PowerShell was not available in this environment, so none of the `.ps1` scripts has been executed.** They were reviewed for Windows PowerShell 5.1 pitfalls:
- program error output under `ErrorActionPreference=Stop`
- empty and quoted arguments
- files with a byte-order mark
- strict mode
- deleting through junctions

They were also checked for balanced brackets and strings, and they contain only ASCII characters. The Windows-specific parts are untested: services, virtual accounts, folder rights, junctions, firewall cmdlets, Apache Lounge with mod_php, WinSW and the scheduled task.

Acceptance checklist for a Windows test PC:

1. A fresh install completes and `check-health.ps1` reports `ok` within about two minutes.
2. A crew PC trusts the CA, opens `https://<server>/` and signs in. `Test-NetConnection` succeeds on port 443 and fails on 3306.
3. **Reboot.** All four services come back without anyone signing in, and health is `ok` again.
4. **Kill each process** (`httpd`, `mysqld` and the two `php` processes) in Task Manager. Each service restarts within about 10 seconds.
5. **Pull the power** during work. After start-up the database is consistent and the last saved requisition is there.
6. Upload a certificate copy, a photo and an attachment. Then run an update. All files are still there, and `update-server.ps1 -Rollback` works.
7. Signed in as a normal Windows user on the master PC, `C:\NaviSuite\app\.env` and `C:\NaviSuite\data\mysql` cannot be opened.
8. Run the install script a second time. It changes nothing and reports everything as kept.

## 12. Open issues

1. **The scripts must be run on Windows before the first vessel** (section 11.3). Expect small fixes on the first run.
2. **No vessel data yet.** A new server has an empty database. `/health` reports `down` and `navisuite:status` fails until this vessel's data is loaded. `-ImportSqlDump` can load a cloud dump, but a full dump holds every vessel's data, including other crews' password hashes. The vessel-only export and bootstrap (the roadmap's checkpoint 3) is the next piece of work. Dump with `--skip-triggers` to avoid cloud trigger definers.
3. **Pages still load libraries from public CDNs (Checkpoint 0).** Offline, jQuery and the other libraries do not load, so pages will not work properly on board until they are served locally. This is the main blocker for real offline use.
4. **Security finding in the existing upload code, which affects the cloud.** Certificate copies, photos and signatures keep the extension the uploader sends (`getClientOriginalExtension()`), while validation checks only the content. A PDF or GIF that also contains PHP, uploaded as `x.php`, is saved as `images/cert_copy/<time>.php`. The cloud's `.htaccess` serves existing files, with the PHP handler. So a signed-in user could probably run code on the cloud. The vessel configuration blocks this. The fix is small: store files with the extension guessed from their content (`->extension()`). I have not changed it, because it changes existing cloud behaviour. Please confirm.
5. **Uploaded files are served without sign-in,** on the cloud and on board. Their names are Unix timestamps, so they are easy to guess. Serving them through an authenticated route would close this. It is an application change for a later checkpoint.
6. **Certificate trust on every client.** Each crew PC must trust the vessel CA once. The client installer can do this later. Phones and tablets need it installed by hand.
7. **The PC needs a fixed address.** The certificate follows address changes automatically, but crew PCs and bookmarks do not. Use a DHCP reservation or a static IP.
8. **MySQL versions.** The vessel runs 8.4 or 8.0. The cloud version is still unknown (Q8). A 5.7 dump loads into 8.x, but not the other way round.
9. **Stopping the queue service ends a running job immediately.** PHP on Windows cannot catch the stop signal. The job is retried after 90 seconds, so the sync jobs in Checkpoint 4 must be safe to run twice.
10. **`.env` and SQL dumps are still tracked in git.** Releases exclude them, but the repository history holds cloud secrets. They should be rotated and removed from the repository (related to R10).
11. **No Content-Security-Policy.** The views use inline scripts and CDN libraries. A policy can follow Checkpoint 0.
12. **Backups are local only,** and do not include uploaded files (section 8).
13. **Still open from earlier checkpoints:**
    - Checkpoint 0: the R10 exposure check on the cloud, the 9 failing tests, the CDN libraries, and public registration.
    - Q8: the cloud MySQL version and TRIGGER privilege.

## 13. Files

**New:**
- **Laravel:**
  - `app/Support/HealthCheck.php`
  - `app/Support/Heartbeat.php`
  - `app/Http/Controllers/HealthController.php`
  - `app/Jobs/QueueHeartbeat.php`
  - `app/Console/VesselSchedule.php`
  - `routes/health.php`
- **Migration:** `database/migrations/2026_11_22_100000_create_queue_tables.php`
- **Tests:** `tests/Feature/VesselServer/HealthCheckTest.php` and `BackgroundServicesTest.php`
- **Deployment:**
  - `deploy/build-release.sh`
  - `deploy/windows/`:
    - `install-server.ps1`
    - `update-server.ps1`
    - `uninstall-server.ps1`
    - `configure-firewall.ps1`
    - `new-certificate.ps1`
    - `check-health.ps1`
    - `backup-database.ps1`
    - `maintenance.ps1`
    - `lib/NaviSuite.ps1`
    - `README.md`
  - `deploy/windows/templates/`:
    - `httpd.conf`
    - `php.ini`
    - `my.ini`
    - `openssl.cnf`
    - `create-database.sql`
    - `NaviSuite-Queue.xml`
    - `NaviSuite-Scheduler.xml`
- This document

**Modified:**
- `bootstrap/app.php`: loads `routes/health.php` outside the `web` group, so `/health` has no session or cookies.
- `routes/console.php`: the schedule now comes from `VesselSchedule`.
- `config/navisuite.php`: health settings and `release`.
- `config/sync.php`: the queue tables are excluded from sync.
- `.env.vessel.example`: `QUEUE_CONNECTION=database`, `APP_RELEASE`, the health settings, and the installer note.
- `.env.example`: commented health example.
- `.gitignore`: `/build/`.
- `docs/offline-implementation-roadmap.md`: status line.
