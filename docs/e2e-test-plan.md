# End-to-end test plan: NaviSuite vessel server, crew PCs and sync

For the people who accept a NaviSuite build on real Windows PCs before it
goes to any vessel. Each test lists its steps and the result that counts as
a pass. Record every run in the sheet in section 12.

**Rule:** no build goes to a vessel until this plan passes with a
**production** build: `build.json` shows `"test_build": false` and every test
suite passed. A test build may be used for a dry run of this plan, never on a
ship.

## 1. Entry and exit criteria

| | Criteria |
|---|---|
| Entry | The build came from `deploy/build-installers.sh` without `--test-build`, and its `build.json` shows the PHP suite with 0 failures, 0 errors and 0 skipped, the desktop tests passed and the smoke test passed. `SHA256SUMS` matches both installers on the test PCs (`Get-FileHash -Algorithm SHA256`). |
| Exit | Every P1 test passes. Every P2 failure has a written, accepted workaround. No test lost data or produced a duplicate record. Every failure found is fixed in a new build, and that build passes again from section 3. |
| Stop | Any data loss, duplicate record, or credential seen in plain text in a log or file: stop, report, fix, rebuild. |

## 2. Test lab

| Item | Setup |
|---|---|
| **SRV**, the master PC | Windows 11 Pro or Windows 10 22H2, 64-bit, 8 GB RAM, SSD. Physical if possible, so the power-cut tests are real. Take an image or snapshot of the clean Windows first. |
| **CL1** | Windows 11, local administrator for installing, a standard user account for testing |
| **CL2** | Windows 10 22H2, standard user |
| **CL3** | The oldest Windows build found on the ships (ask IT: question C8-6), standard user |
| **LAN** | One switch with SRV and the clients; a router to the internet. |
| **Cutting the internet** | Unplug the router's internet side, or on SRV: `New-NetFirewallRule -DisplayName 'LAB cut cloud' -Direction Outbound -RemoteAddress <cloud IP> -Action Block`. Remove the rule to restore. |
| **Slow link (optional, P2)** | A link emulator between the router and the internet: 512 kbit/s, 700 ms latency, 2 % loss, like a VSAT link |
| **Staging cloud** | The same release as the build under test, with a test vessel (for example id 9001) registered: `php artisan sync:register-node vessel --vessel=9001 --name=LAB-SRV`. Keep the printed node id and token. |
| **Business modules** | For sections 6 to 9 the modules must be on, on both sides: `SYNC_TABLES=currencies,ranks,categories,@master,@inventory,@pms,@requisitions,@attachments`. On a vessel they stay off until the bootstrap tool exists (C7-5), so this applies to the lab only. Start the lab vessel empty and send the office data down with `sync:resend <table> --vessel=9001 --force` on the cloud for the master tables. |
| **Accounts** | On the cloud: master and chief engineer of vessel 9001, one more crew member, one office superintendent (approvals). |
| **Clocks** | All PCs on the correct time, except where a test says otherwise. |

Commands used below run on SRV in an elevated prompt:
`C:\NaviSuite\artisan.cmd <command>`. "Cloud" means `php artisan <command>`
on the staging cloud.

## 3. Installation

| ID | Pri | Steps | Pass when |
|---|---|---|---|
| IN-1 | P1 | On clean SRV run `NaviSuite-Server-Setup.exe` (guided): vessel 9001, server name `lab-srv`, the cloud address, node id and token from section 2, firewall on. | The finish page shows `https://lab-srv/`, a fingerprint and the client kit folder. `check-health.ps1` passes. The 6 services `NaviSuite-*` are Running and set to start automatically. `C:\NaviSuite\setup\installer.log` and `C:\NaviSuite\data\logs\setup\setup-*.log` exist. |
| IN-2 | P1 | Search every file under `C:\NaviSuite` (data folder included) and `%TEMP%` for the sync token: `Get-ChildItem -Recurse -File \| Select-String -SimpleMatch '<token>'`. Also check `.env`. | No match. `.env` has `SYNC_NODE_TOKEN=` empty. `navisuite:secrets status` shows it comes from the store. |
| IN-3 | P1 | Restore the clean image. Silent install: `NaviSuite-Server-Setup.exe /S /ANSWERS=D:\lab.ini` with the token in a `TokenFile`. Then `echo %ERRORLEVEL%` (or `$LASTEXITCODE`). | Exit code 0, the same result as IN-1, and no window appeared. |
| IN-4 | P1 | Silent install with no `/ANSWERS` on a clean PC. | Exit code 5. Nothing installed. |
| IN-5 | P2 | Run the installer as a standard user, and on a 32-bit or older Windows if one is available. | Refused with a clear message: exit code 3. |
| IN-6 | P1 | Run a **test build** server installer silently without `/TESTBUILD`. | Exit code 3. Nothing installed. |
| IN-7 | P1 | Copy `C:\NaviSuite\data\client-setup` (both files) to a USB stick. On CL1, as administrator, run `NaviSuite-Client-Setup.exe` from the stick. Start NaviSuite as the standard user. | The client opens `lab-srv` directly with no fingerprint question: it is pinned. `%ProgramData%\NaviSuite Client\client-config.json` holds the fingerprint. `logs\install.log` records the install. |
| IN-8 | P1 | On CL2, silently: `NaviSuite-Client-Setup.exe /S /SERVER=lab-srv,<SRV IP> /FINGERPRINT=<fp> /VESSEL=9001 /LOCK`. | Exit code 0. The client connects without questions. "Change server" is not available (locked). |
| IN-9 | P1 | On CL3, silently with a wrong fingerprint (`/FINGERPRINT=00:11`). | Exit code 5. The client is installed. `install.log` says the settings were refused. The client then finds the server by discovery and asks for the fingerprint to be confirmed. |
| IN-10 | P1 | As the standard user on CL2, try to edit `%ProgramData%\NaviSuite Client\client-config.json`. | Access denied. |

## 4. Several crew PCs at once

| ID | Pri | Steps | Pass when |
|---|---|---|---|
| MC-1 | P1 | Sign in on CL1 as master, CL2 as chief engineer, CL3 as crew, all at the same time. Each opens their dashboard. | All three work, each sees only what their role allows, and nobody is signed out. |
| MC-2 | P1 | The master and the chief engineer edit the same draft requisition on CL1 and CL2, and both save. | The behaviour matches the cloud application today (one wins, or the second gets a clear message). No error page, and nothing is lost silently. Note which. |
| MC-3 | P1 | Crew on CL3 attaches a 5 MB PDF to a requisition line. The master opens it on CL1. | It opens. It is stored as `.pdf` under a random name in `data\storage\app\attachments`. |
| MC-4 | P1 | Close the client on CL1 and open it again. | Asks to sign in again: the session is cleared on exit. |
| MC-5 | P2 | Change SRV's IP address (DHCP reservation or static) while the clients are open. | Within about 2 minutes every client finds the server again at the new address, by itself. |
| MC-6 | P2 | Start a second PC answering as NaviSuite (any test server with another CA) on the LAN. | Pinned clients refuse it. The page says "The server's identity could not be verified". |
| MC-7 | P1 | Set `APP_MIN_CLIENT_VERSION=99.0.0` in SRV's `.env`, then run `artisan.cmd config:cache` and restart NaviSuite-Web. Remove the setting again afterwards. | Each client shows "This client and the server do not match" and says the client is too old. After the setting is removed, each comes back by itself. |

## 5. No internet

Cut the internet (section 2) and keep it cut for the whole section. For the
lab, set `SYNC_OFFLINE_WARNING_HOURS=1` on SRV (then `config:cache`) so the
offline warning comes within the test day.

| ID | Pri | Steps | Pass when |
|---|---|---|---|
| NI-1 | P1 | Crew raise 5 requisitions with attachments, record stock consumption, and record 3 PMS jobs, from all three clients. | All of it works normally. No page mentions the internet. |
| NI-2 | P1 | `artisan.cmd sync:status` and `sync:health`. | Status shows the backlog growing. Health shows a warning, not an error: being offline at sea is normal. |
| NI-3 | P1 | Sign in with a password changed on the cloud **after** the cut. | The old password still works on board; the new one does not yet. That is expected: the change arrives with the next sync. |
| NI-4 | P2 | Wait more than `SYNC_OFFLINE_WARNING_HOURS`. | `sync:health` warns "no successful sync for ...". `/health` stays usable for the clients. |
| NI-5 | P1 | Watch `data\logs\sync`. | Retries back off: 30 s, doubling to 30 min. No flood of errors. |

## 6. Internet back

| ID | Pri | Steps | Pass when |
|---|---|---|---|
| IR-1 | P1 | Restore the internet. Wait, or run `artisan.cmd sync:run`. | Within a few cycles the cloud has all 5 requisitions, the consumptions and the PMS jobs from NI-1, in the right order (a line never arrives before its requisition). `sync:status` shows the backlog at 0. |
| IR-2 | P1 | On the cloud, compare each requisition's lines and attachments with SRV. | Same lines and values. Each attachment's SHA-256 is the same on both sides (`attachments.sha256`). `sync:files` on SRV shows nothing waiting or failed. |
| IR-3 | P1 | While offline, the office approved an older requisition and changed a user's role. | Both come down. The approval shows on board; the role change takes effect at that user's next click. |
| IR-4 | P1 | During NI, the master edited requisition R on board while the office edited the same field of R. | One conflict is recorded (`sync:conflicts`). The outcome follows the Checkpoint 6 rule for that table. Both sides end with the same value. |
| IR-5 | P2 | With the slow-link emulator: attach a 30 MB file on board, and cut the internet halfway through the upload (watch `sync:files`). Restore it. | The upload continues from where it stopped, not from the start. The cloud keeps the file only once its whole SHA-256 matches. Records keep flowing while the file waits. |
| IR-6 | P2 | 200 MB of new attachments in one go. | No more than about 50 MB per cycle. Records are never held up. Everything arrives over the following cycles. |

## 7. Server restart and power cuts

| ID | Pri | Steps | Pass when |
|---|---|---|---|
| RB-1 | P1 | With clients open, restart SRV normally (`Restart-Computer`). Do not sign in to SRV afterwards. | Within 5 minutes of Windows starting, all 6 services run. `data\logs\startup\startup-*.log` for this boot shows OK. Each client reconnects by itself, the open page is kept or reloaded, and nobody has to restart the client. |
| RB-2 | P1 | Pull SRV's power while two users save forms and a sync cycle runs. Power on. | MySQL recovers by itself. The services start. Saved forms are complete or absent, never half-saved. The next sync completes. Nothing is duplicated on the cloud (check as in DP-1). |
| RB-3 | P1 | Stop `NaviSuite-Queue` by hand and disable nothing else. Restart SRV. | The startup check log shows `STARTING NaviSuite-Queue`, then `OK`. |
| RB-4 | P2 | Pull the power during an **upgrade** (UP-1), at the "Switching releases" step. Power on and run the installer again. | Either the old or the new release runs, never a mix. Running the installer again finishes the upgrade, or `update-server.ps1 -Rollback` brings back the previous release. The database is intact (the backup from before the update exists). |
| RB-5 | P2 | Fill SRV's disk to under 2 GB free. | `/health` reports disk as a problem. The installer refuses an upgrade with a clear message (exit code 3). |

## 8. Database recovery

Follow [disaster-recovery.md](disaster-recovery.md) as written. Every unclear
step is a finding.

| ID | Pri | Steps | Pass when |
|---|---|---|---|
| DR-1 | P1 | `backup-database.ps1` and `backup-files.ps1` by hand. Then `backup-files.ps1 -Verify` on the new manifest. | Both succeed. The verify finds no damage. `check-health.ps1` shows both backups as fresh. |
| DR-2 | P1 | Note the record counts. Delete a requisition by accident (as a user would), then restore last night's backup (disaster-recovery.md 5.2 and 5.3), then 5.4. | The requisition is back. On the cloud, `sync:resend` for the both-ways tables brings back the vessel's later changes. Nothing is duplicated (DP-1). The guide's "What is lost" table matches what happened. |
| DR-3 | P1 | Corrupt one attachment file on SRV (overwrite a few bytes). Run `artisan.cmd sync:files verify`, then `--refetch`. | `verify` reports it as damaged, and `sync:health` shows an error. `--refetch` fetches it from the cloud, and verify is clean again. |
| DR-4 | P1 | Replace the PC (disaster-recovery.md 6). Restore the image of a clean Windows on SRV, re-register the node on the cloud with `--replace`, install with the new token, restore the newest export from USB. | Users sign in with their usual passwords. Sync works with the new token, and the old token is refused (401) by the cloud. The clients need the new fingerprint: re-run IN-7 with the new kit. |
| DR-5 | P2 | Delete `C:\NaviSuite\secrets\app\sync_node_token.secret`. Follow disaster-recovery.md 7. | `navisuite:secrets check` names the missing secret. After the steps, sync works. |
| DR-6 | P2 | Restore the staging cloud from an older backup, then let the vessel sync (disaster-recovery.md 8). | The vessel stops with the "Was the cloud database restored?" message. `sync:reset-cloud` recovers. Nothing is duplicated on the cloud. |

## 9. Sync failures

| ID | Pri | Steps | Pass when |
|---|---|---|---|
| SF-1 | P1 | On SRV set a wrong token: `navisuite:secrets set sync_node_token` with a random value. Restart NaviSuite-Sync. | Cycles fail with 401. After 3 cycles, `sync:health` reports an error ("not a network problem"). The crew can still work. After the right token is set, the backlog drains. |
| SF-2 | P1 | On the cloud, `sync:revoke-node <id>`. | Same as SF-1. Re-register with `--replace` and set the new token: it works again. |
| SF-3 | P1 | Make the cloud answer 500 (stop its PHP-FPM, or put it in maintenance mode) for 10 minutes. | The vessel backs off and does not hammer the cloud. Nothing is lost. It catches up afterwards. |
| SF-4 | P2 | On the cloud, change an attachment file so its SHA-256 no longer matches. On SRV, delete its copy and run `sync:files verify --refetch`. | The vessel refuses the file ("checksum"), never places it, and lists it as failed. |
| SF-5 | P2 | Set SRV's clock 2 days fast, make changes, sync, then set the clock right. | Nothing is lost or duplicated. Note any "latest wins" result that went the wrong way because of the clock; it is a finding for Checkpoint 6. |
| SF-6 | P2 | On the cloud, deactivate the crew member signed in on CL3 (office "Delete user"). Sync. | At their next click on CL3 they are signed out with "This account has been deactivated." |
| SF-7 | P2 | Transfer the chief engineer to another vessel on the cloud. Sync. | They can no longer sign in on SRV (Checkpoint 7). |

## 10. Duplicate prevention

Use the record's `sync_uuid` to check: on the cloud,
`SELECT COUNT(*) FROM <table> WHERE sync_uuid = '<uuid>'` must be 1.

| ID | Pri | Steps | Pass when |
|---|---|---|---|
| DP-1 | P1 | After every P1 test above that synced, count the vessel's requisitions, lines, consumptions, PMS records and attachments on both sides. | The counts are equal, and no `sync_uuid` appears twice on the cloud. |
| DP-2 | P1 | Cut the internet **while a batch is on its way**: start `sync:run`, cut within a second. Restore and sync. | The vessel sends the same batch again unchanged. The cloud recognises the batch id and repeats its first answer (`sync_batches`), or marks the repeated events `duplicate` in `sync_inbox`. Each record exists once. |
| DP-3 | P1 | Power off SRV right after the cloud logged the batch as applied, before the vessel stored the answer (cut SRV's power as soon as the cloud's log shows the batch). Power on. | As DP-2: the batch is repeated and recognised, with no duplicates. |
| DP-4 | P1 | Double-click "Submit" on a requisition on CL1, and press F5 on the confirmation page. | Exactly one requisition, on board and on the cloud. |
| DP-5 | P1 | On the cloud, `sync:resend requisitions --vessel=9001 --force`, then sync twice. | No new rows on either side. The resent rows are recognised as unchanged. |
| DP-6 | P2 | On board and on the cloud, create the same catalog item (same group, maker and part number) while offline. Sync. | One item on both sides, merged by `item_key` (Checkpoint 6). The stock refers to the merged item. |
| DP-7 | P1 | Run the server installer again over the installed server (repair), twice. | One of each service, one of each scheduled task (`Get-ScheduledTask -TaskName 'NaviSuite*'`), and no duplicated firewall rules (`Get-NetFirewallRule -Group NaviSuite`). The data is untouched. |

## 11. Upgrade, downgrade and uninstall

Needs two production builds, for example 1.0.0 and 1.0.1. A lab-only
version bump of `deploy/product.json` and `desktop/package.json` is enough.

| ID | Pri | Steps | Pass when |
|---|---|---|---|
| UP-1 | P1 | Before: note the SHA-256 of `.env` without its `APP_RELEASE` line, the file list of `secrets\app`, the certificate fingerprint and the record counts. Run the 1.0.1 server installer (guided, then silent `/S` on a second run). | It reports an upgrade, and a `before-update` database backup exists. `.env` is unchanged apart from `APP_RELEASE`. The secrets, fingerprint and counts are unchanged. The clients keep working without re-trusting. `/health` shows the new release. |
| UP-2 | P1 | Run the 1.0.0 installer over 1.0.1. | Refused with exit code 4 (guided: a message). Nothing changed. |
| UP-3 | P1 | A lab build of 1.0.1 with one extra migration, installed over 1.0.0. Then install 1.0.0 with `/ALLOWDOWNGRADE`. | Refused with exit code 3: "The database has 1 migration(s) this release does not have". Nothing changed. |
| UP-4 | P2 | A lab build with a migration that fails. Install it as an upgrade. | The migration fails, the services stay stopped and the restore and rollback commands are printed. Running them brings back the previous release with its data. |
| UP-5 | P2 | An upgrade whose runtime has a newer PHP patch release. | `runtime\php.previous` exists, the new PHP runs, and `/health` is ok. |
| UP-6 | P2 | An upgrade whose runtime has MySQL from another series. | The log says MySQL is kept. The old MySQL still runs. Everything else is upgraded. |
| UP-7 | P1 | Upgrade the client on CL1 and CL2 (silent, no options). | The settings and each user's chosen server stay. The client opens the server straight away. |
| UN-1 | P1 | Uninstall the server from Programs and Features, keeping the data. | The services, tasks and firewall rules are gone. `C:\NaviSuite\data`, `secrets` and `config` are still there. |
| UN-2 | P1 | Run the server installer again after UN-1. | The server comes back with its data, credentials, fingerprint and users (setup log: "Bringing back the services"). The clients reconnect. |
| UN-3 | P2 | Uninstall with `/S /REMOVEDATA=YES` (lab only). | `C:\NaviSuite` is gone, along with the services, tasks and the Programs and Features entry. |
| UN-4 | P2 | Uninstall the client on CL3, then again with `/REMOVESETTINGS`. | The first keeps `%ProgramData%\NaviSuite Client`; the second removes it. Both remove the firewall rule. |

## 12. Record sheet

Copy this table for each run.

| ID | Build (release) | Date | Tester | Pass / Fail | Evidence (log file, screenshot, command output) | Finding |
|---|---|---|---|---|---|---|
| IN-1 | | | | | | |
| ... | | | | | | |

Sign-off: the build named here passed every P1 test and the P2 tests listed
with accepted workarounds.

| Role | Name | Date | Signature |
|---|---|---|---|
| Tester | | | |
| IT / fleet systems | | | |
| Owner of the release | | | |
