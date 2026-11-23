# Disaster recovery: vessel server

For the person who looks after a vessel's NaviSuite master PC, and the office
staff who support them. Every command runs on the master PC in an
**elevated** PowerShell prompt in `C:\NaviSuite\app\deploy\windows`, unless
it says "on the cloud".

> Not yet rehearsed on Windows. Run sections 3 to 6 once on a test PC before
> relying on them at sea.

## 1. What is protected, and where

| What | Where on the PC | Backed up by | Kept |
|---|---|---|---|
| Database (requisitions, stock, PMS, users, sync bookkeeping) | MySQL, `C:\NaviSuite\data\mysql` | `backup-database.ps1`, nightly | 14 days, at least 3 |
| Attachments | `C:\NaviSuite\app\storage\app\attachments` | `backup-files.ps1`, nightly | 30 days, at least 5 |
| Certificates, signatures, photos | `C:\NaviSuite\app\images\cert_copy`, `signature`, `userphoto` | `backup-files.ps1`, nightly | same |
| Credentials (sync token, DB password, app key, health token, MySQL root) | `C:\NaviSuite\secrets` | **not backed up, on purpose** | see section 7 |
| Configuration | `C:\NaviSuite\app\.env` | reinstalled by `install-server.ps1` | |

Backups go to:
- database: `C:\NaviSuite\data\backups\navisuite-<date>-<time>-<reason>.zip`;
- files: `C:\NaviSuite\data\backups\files\`:
  - `manifests\files-<date>-<time>-<reason>.json`, each with a `.sha256` beside it;
  - `objects\`: each distinct file stored once, under its SHA-256.

The credentials are encrypted with this Windows installation's machine key
(DPAPI). A copy of them would not open on any other PC, so copying them
somewhere is pointless and they are left out. Everything they protect can be
issued again (section 7).

**The cloud is the second copy.** What reached the cloud before a disaster
can come back from it, within the limits in section 5.4.

## 2. Every day: checks

The nightly maintenance task makes both backups. Check them at least weekly:

    .\check-health.ps1

| Step | Good | Act when |
|---|---|---|
| Backups | Database and file backup newer than 36 h | older, or missing: run the scripts by hand and read the error |
| Sync problems (sync:health) | `OK` | any error; warnings when they persist |

From the application folder:

    C:\NaviSuite\artisan.cmd sync:health         # what needs a person
    C:\NaviSuite\artisan.cmd sync:files          # files waiting, failed, missing
    C:\NaviSuite\artisan.cmd navisuite:secrets check

Check a backup without restoring it:

    .\backup-files.ps1 -Verify C:\NaviSuite\data\backups\files\manifests\<manifest>.json

## 3. Weekly: a copy off the PC

Backups on the master PC's own disk do not survive the disk, a fire, theft or
ransomware. At least once a week, and before any repair or port call with
IT work, copy the newest set to a USB drive:

    .\backup-files.ps1 -Export E:\NaviSuite-backup -WithDatabase

This copies:
- the newest file manifest;
- only the files that drive does not already hold;
- the newest database backup, with a `.sha256` beside it.

Run it again on the same drive and only new files are copied.

The export is **not encrypted**. It holds requisitions, prices and users'
password hashes. Keep the drive locked away, and do not leave it in the PC
(question C7-4).

## 4. Which section do I need?

| What happened | Go to |
|---|---|
| A few attachments are damaged or missing | 5.1 |
| Data was deleted or damaged by mistake, the PC works | 5.2 and 5.3 |
| Windows reinstalled, disk replaced, new PC | 6 |
| A credential cannot be opened (`navisuite:secrets check` fails) | 7 |
| The cloud was restored or moved | 8 |

**Before any restore:**
1. tell the office;
2. note the time;
3. if the PC still works, run `C:\NaviSuite\artisan.cmd sync:run` once, so
   that what can still reach the cloud does.

## 5. Restoring on a working PC

### 5.1 Some attachments are damaged or missing

The nightly integrity check (`sync:files verify`) finds them, and
`sync:health` reports them. If the cloud has them, fetch them again:

    C:\NaviSuite\artisan.cmd sync:files verify --refetch
    C:\NaviSuite\artisan.cmd sync:run

This works for the vessel's own uploads too, once they have reached the
cloud. A file that never reached the cloud has to come from a file backup
(5.3).

### 5.2 Database

    .\backup-database.ps1 -Restore C:\NaviSuite\data\backups\navisuite-<date>-<time>-daily.zip

The script:
1. asks for confirmation;
2. takes a safety backup (`...-before-restore.zip`);
3. stops the NaviSuite services, replaces the database and starts them again.

If the restore fails, the message names the safety backup.

### 5.3 Files

    .\backup-files.ps1 -Restore C:\NaviSuite\data\backups\files\manifests\files-<date>-<time>-daily.json

The script:
1. checks every file of the backup against its SHA-256 and changes nothing if
   one is missing or damaged;
2. backs up the current files (`files-...-before-restore.json`);
3. stops the services and puts back only the files that differ;
4. restarts the services;
5. runs `sync:files scan` and `sync:files verify`.

Files added after the backup are left in place.

Restore the database and the files from the same night when both were
affected. A record pointing to a file from a different day shows as
"missing" in `sync:files` until it is fetched or restored.

### 5.4 After a restore: bring the vessel back up to date

The restored database is as it was at the time of the backup. The cloud
already holds what the vessel sent after that.

1. Sync once and read the result:

       C:\NaviSuite\artisan.cmd sync:run
       C:\NaviSuite\artisan.cmd sync:health

   The office's own changes since the backup come down by themselves.

2. **On the cloud**, resend this vessel's rows of the tables that sync both
   ways, so the vessel's own changes since the backup come back:

       php artisan sync:resend <table> --vessel=<vessel id> --force

   for each both-ways table of the enabled modules (`config/sync.php`,
   `direction => both`), for example `requisitions`. Rows the vessel already
   has are recognised as unchanged.

3. Fetch any files the restored records now name:

       C:\NaviSuite\artisan.cmd sync:files scan
       C:\NaviSuite\artisan.cmd sync:run

**What is lost:**

| What | Comes back? |
|---|---|
| Changes made on board after the backup that reached the cloud, both-ways tables | Yes, step 2 |
| Same, one-way tables (vessel to cloud: stock, consumptions, PMS records) | **No, not yet.** The cloud has them, but nothing sends them down. Needs the bootstrap tool (question C7-5). Until then, re-enter them on board, or ask the office to read them from the cloud. |
| Changes made on board after the backup that never reached the cloud | **No.** Re-enter them. |
| Files uploaded on board after the backup | Yes if they reached the cloud (5.1), else no |

That is why the third step under "Before any restore" (section 4) matters.

## 6. New PC, new disk or reinstalled Windows

The old credentials cannot be opened on the new installation (section 1), so
the vessel gets new ones.

1. **On the cloud**, retire the old PC's identity and issue a new one. The
   token is shown once:

       php artisan sync:register-node vessel --vessel=<id> --name="<new PC name>" --replace

   The old token stops working at once, which also covers a stolen PC.

2. On the new PC, run `NaviSuite-Server-Setup.exe` of **the version the
   backup was taken with** ([checkpoint-8](checkpoint-8-installers.md); a
   restore does not migrate, so upgrade only after step 3). Enter
   the vessel id, the cloud address and the new id and token. Or, with the
   scripts as in [checkpoint-3](checkpoint-3-windows-vessel-server.md):

       .\install-server.ps1 -VesselId <id> -SyncNodeId <new id> -SyncNodeToken <new token> -SyncCloudUrl https://<cloud>

   The new PC has a new certificate authority, so the crew PCs must be set
   up again with the new client kit from `C:\NaviSuite\data\client-setup`
   (or confirm the new fingerprint once).

   The installer:
   - creates a new MySQL root password, database password, app key and
     health token, all stored with DPAPI;
   - stops if `navisuite:secrets check` fails.

3. Put the data back from the newest export (section 3) or the old disk.
   Check the database copy first:

       Get-FileHash E:\NaviSuite-backup\navisuite-<...>.zip -Algorithm SHA256
       # compare with E:\NaviSuite-backup\navisuite-<...>.zip.sha256

       .\backup-database.ps1 -Restore E:\NaviSuite-backup\navisuite-<...>.zip

   For the files, copy `E:\NaviSuite-backup\files` to
   `C:\NaviSuite\data\backups\files`, then:

       .\backup-files.ps1 -Restore C:\NaviSuite\data\backups\files\manifests\<newest>.json

4. Check that the new identity is in use. The node id and token live in
   `.env` and `secrets\app`, not in the database, so the restore does not
   bring the old ones back:

       C:\NaviSuite\artisan.cmd sync:status      # shows the node id in use

5. Continue with 5.4.

6. Users sign in with their usual passwords: the hashes are in the restored
   database. Everyone has to sign in again, because the new app key ends all
   old sessions.

**No backup at all:** install as above and ask the office. Until the
bootstrap tool exists (C7-5), the cloud can resend the both-ways tables and
the office's records (users, roles, vessel, reference lists) with
`sync:resend`. One-way tables start empty.

## 7. Lost or unreadable credentials

`navisuite:secrets check` names the one that fails. Each one can be issued
again; none needs the old value.

| Credential | Effect when lost | Fix |
|---|---|---|
| `sync_node_token` | Sync stops (401) | On the cloud: `sync:register-node vessel --vessel=<id> --replace`. On the PC: `C:\NaviSuite\artisan.cmd navisuite:secrets set sync_node_token` (hidden prompt), and set `SYNC_NODE_ID` in `.env` to the new id. |
| `db_password` | The application cannot reach MySQL | Choose a new password. Run `ALTER USER 'navisuite'@'localhost' IDENTIFIED BY '<new>'; ALTER USER 'navisuite'@'127.0.0.1' IDENTIFIED BY '<new>';` (the user is `VESSEL_DB_USERNAME` if set) through `C:\NaviSuite\runtime\mysql\bin\mysql.exe` as root, then `navisuite:secrets set db_password`. |
| `app_key` | Pages fail to load | `navisuite:secrets set app_key` with the output of `php artisan key:generate --show`. Only sessions and remember-me cookies are lost; nothing else on board is encrypted with it. |
| `health_token` | `/health` details refused | `navisuite:secrets set health_token` with any new long random value. |
| MySQL root (`secrets\mysql-root.secret`) | Backups, restores and updates stop | Standard MySQL root reset: stop the NaviSuite-MySQL service, start mysqld once with `--init-file` holding `ALTER USER 'root'@'localhost' IDENTIFIED BY '<new>';`, then delete that file. Store the new password: `. .\lib\NaviSuite.ps1; Set-NsRootPassword (Get-NsPaths 'C:\NaviSuite') '<new>'`. |

After any change, refresh the settings and restart the application services:

    C:\NaviSuite\artisan.cmd config:cache
    Restart-Service NaviSuite-Web, NaviSuite-Queue, NaviSuite-Scheduler, NaviSuite-Discovery, NaviSuite-Sync

`.env` is read only by `config:cache`. The credentials in `secrets\app` are
read when each service starts and never enter the cache.

Never paste a credential into `.env` as a shortcut. It works, but leaves it
in plaintext, and `sync:health` warns until it is moved
(`navisuite:secrets import-env`).

## 8. The cloud was restored or moved

The vessel stops syncing with a clear message (409, "Was the cloud database
restored from a backup?"). On each vessel:

    C:\NaviSuite\artisan.cmd sync:reset-cloud

Details, including when `sync:resend` is also needed, are in
[checkpoint-5, section 11.4](checkpoint-5-sync-engine.md).

After a cloud restore, the files it lost are wanted again from the vessels.
They are sent automatically over the next cycles: up to 50 MB per cycle,
in the order the cloud noted them.

## 9. Checklist after any recovery

| Check | Command | Expect |
|---|---|---|
| Credentials | `artisan.cmd navisuite:secrets check` | all open |
| Health | `.\check-health.ps1` | no failures |
| Sync | `artisan.cmd sync:run`, then `sync:health` | OK, or only "offline" while at sea |
| Files | `artisan.cmd sync:files verify` | no damaged or missing files, or only ones still being fetched |
| Sign-in | sign in on a LAN client as a crew member | works |
| New backup | `.\backup-database.ps1 -Reason after-recovery`; `.\backup-files.ps1 -Reason after-recovery` | both succeed |
| Off-PC copy | `.\backup-files.ps1 -Export E:\NaviSuite-backup -WithDatabase` | done |
| Office told | | which backup was used, what has to be re-entered |
