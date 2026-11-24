# Checkpoint 7: files, offline sign-in, credentials, backup and monitoring

**Status:** built and tested on macOS (SQLite and MySQL 8). The Windows-only
parts (DPAPI through PHP FFI, the PowerShell scripts) have not run on Windows
yet. Nothing is committed. The disaster recovery procedure is in
[disaster-recovery.md](disaster-recovery.md).

| Requirement | Where |
|---|---|
| Offline sign-in with stored password hashes | bcrypt hashes from the `users` table; deactivation and transfers take effect on board ([UserAccess](../app/Support/UserAccess.php), [EndRevokedSessions](../app/Http/Middleware/EndRevokedSessions.php), [VesselDepartures](../app/Sync/VesselDepartures.php)) |
| Local roles and permissions | The `roles` table on board; every check reads the local copy |
| User and permission sync | `users` and `roles` rules (Checkpoint 6) plus departures |
| Secure attachment storage | [SafeUpload](../app/Support/Files/SafeUpload.php), view headers in [Attachment](../app/Attachment.php) |
| Separate upload/download process | [App\Sync\Files](../app/Sync/Files), `/api/sync/v1/files` |
| Checksums and integrity checks | `attachments.sha256`, `sync_files`, `sync:files verify` (nightly) |
| Database backups | [backup-database.ps1](../deploy/windows/backup-database.ps1) (unchanged apart from the root password) |
| Attachment backups | [backup-files.ps1](../deploy/windows/backup-files.ps1) |
| Restore procedure | `-Restore` on both scripts; [disaster-recovery.md](disaster-recovery.md) |
| Sync failure monitoring | [SyncHealth](../app/Sync/SyncHealth.php), `sync:health`, `/health`, check-health.ps1 |
| Secure cloud credentials | [SecretStore](../app/Support/Secrets/SecretStore.php), `navisuite:secrets` |

## 1. Credentials

No password, token or key is kept in plaintext on a vessel server any more:

| Credential | Before | Now |
|---|---|---|
| Sync node token | `.env` `SYNC_NODE_TOKEN` | `secrets\app\sync_node_token.secret` (DPAPI) |
| Vessel database password | `.env` `VESSEL_DB_PASSWORD` | `secrets\app\db_password.secret` (DPAPI) |
| Application key | `.env` `APP_KEY` | `secrets\app\app_key.secret` (DPAPI) |
| Health token | `.env` `APP_HEALTH_TOKEN` | `secrets\app\health_token.secret` (DPAPI) |
| MySQL root password | `secrets\mysql-root.cnf` (plaintext) | `secrets\mysql-root.secret` (DPAPI, administrators only) |
| User passwords | bcrypt hashes in `users` | unchanged |

How it works:

- The values are encrypted with Windows DPAPI, machine scope, with an
  application-specific entropy string. A file opens only on the Windows
  installation that wrote it, so a copied folder, a disk image or a backup is
  useless elsewhere. The folder is readable by administrators and the
  NaviSuite services only.
- The application reads them through `SecretStore`.
  `SecretsServiceProvider` fills them into the running configuration when
  the application starts.
- They never reach `bootstrap/cache/config.php`: while `config:cache`,
  `optimize` or `config:show` runs, nothing is filled in.
- PHP calls DPAPI through FFI (`extension=ffi`, `ffi.enable=true` in the
  vessel php.ini). PowerShell writes the same format with
  `[Security.Cryptography.ProtectedData]`, so the installer and the
  application share the files.
- Off Windows (the cloud on Linux, developers), the protector is a libsodium
  key file in the same folder. It keeps secrets out of `.env`, the config
  cache and copies of the application, but anyone who can read both files
  can open them. This is said plainly in the code and in question C7-9.
- `.env` still works. A stored secret wins over it, and `navisuite:status`,
  `navisuite:secrets status` and `/health` warn about a required credential
  that is still in plaintext on a vessel.
- MySQL's programs read a password only from an option file or the command
  line. The scripts therefore write a temporary option file to the
  administrators-only `secrets` folder for the duration of each call and
  delete it in a `finally` block ([Use-NsRootCnf](../deploy/windows/lib/NaviSuite.ps1)).
- A plaintext `mysql-root.cnf` from an earlier release is converted and
  deleted the first time any script needs it.

Commands (a value is never given on the command line):

    php artisan navisuite:secrets status         # where each credential comes from
    php artisan navisuite:secrets set sync_node_token   # hidden prompt, asked twice
    php artisan navisuite:secrets import-env     # move values out of .env, blank the lines
    php artisan navisuite:secrets check          # open every secret; exit 1 if one fails

install-server.ps1:
- stores the four application secrets and blanks their `.env` lines;
- sets `NAVISUITE_SECRETS_PATH`;
- requires the FFI and sodium extensions;
- stops if `navisuite:secrets check` fails, so a server is never left with
  credentials it cannot open.

## 2. Uploads

Every upload is now stored under a name and extension the server chooses:

| Upload | Before | Now |
|---|---|---|
| Attachments (requisition lines, library, consumptions) | `attachments/<uid>/<uuid>.<browser's extension>` | `attachments/<uid>/<uuid>.<extension of the detected type>` + SHA-256 |
| Certificate copy | `images/cert_copy/<time()>.<browser's extension>` | `images/cert_copy/<32 random>.pdf` |
| Photo, signature | `images/userphoto\|signature/<time()>.<browser's extension>` | `images/.../<32 random>.jpg\|png\|gif` |

The type comes from the content (PHP fileinfo), through a fixed allow-list.
So:
- a PDF called `invoice.html` is stored as `.pdf`;
- HTML, SVG or PHP calling itself `.pdf` or `.jpg` is refused;
- Laravel itself refuses any file named `*.php`.

The `images/` folders are served by Apache as static files with PHP switched
off there (Checkpoint 3). Now nothing in them can carry a script's extension
either.

Attachments are shown with `X-Content-Type-Options: nosniff`. Everything
except PDFs also gets `Content-Security-Policy: sandbox`; PDFs are left out
because the browser's PDF viewer refuses a sandboxed document.

Existing files keep their names.

## 3. Attachment sync

### Records

New rules in `config/sync.php`, in a new module `@attachments`. It needs
`@requisitions` and `@inventory`. Like the other modules it is off until the
bootstrap tool exists.

| Table | Direction | Notes |
|---|---|---|
| `attachments` | both, latest | `strict_scope`: a crew member's files belong to their vessel. An office file reaches a vessel only with a link to one of its records, never on its own. |
| `attachment_order_item` | up | Linked on board to draft lines |
| `attachment_stock_consumption` | up | Linked on board |
| `attachment_order_step` | both, latest | `carries` the attachment: the office's file on a procurement stage goes down with the link |
| `attachment_service_req_step` | both, latest | Same |

Two packager changes make this work ([ChangePackager](../app/Sync/Engine/ChangePackager.php)):
- **Carrying.** When the cloud sends a link to a vessel, it sends the linked
  attachment first. Its event id is derived from the row and its version, so
  sending it again is recognised as a duplicate.
- **Grants.** Every record with a file that the cloud sends to a vessel is
  recorded in `sync_file_grants`.

Files named in other tables (`config/sync.php` `files.columns`):
- `users.photo` and `users.sign`: come down with the office's user records,
  so superintendents' signatures show on board;
- `vessel_certificates.cert_copy`: both ways.

Later decisions (2026-11-24; PD-21 and PD-17 not yet built): PD-21 lets a vessel user change their own photo and signature on board, and the office must not overwrite them; PD-17 makes certificates and their copies vessel-managed. Built the same day (Checkpoint 15): PD-59 B, each attachment belongs to the side that uploaded it and only that side changes it; PD-22, sync's per-file limit is 20 MB (it was 64 MB); PD-22, office procurement documents are read-only copies on board, and each stage link belongs to the side owning the stage. See [pending-decisions.md](pending-decisions.md) and Checkpoint 15 in [implementation-roadmap.md](implementation-roadmap.md).

### Files

The files travel after the records of each cycle, over their own endpoints.
They never travel inside the records, so a slow PDF holds nothing else up and
a failed file never fails the cycle.

| Direction | How |
|---|---|
| Vessel to cloud | When the cloud applies a record naming a file it does not have, it notes the file as wanted from that vessel (`sync_files`). The vessel asks `GET files/wanted`, then sends the file with `PUT files/{table}/{uuid}/{column}` in pieces (512 KB), each with its own SHA-256. The cloud appends a piece only at the expected offset. The file is put in place only when the whole of it matches the declared SHA-256, and the record's own `attachments.sha256` where there is one. |
| Cloud to vessel | The vessel fetches each wanted file with `GET files/...?offset=&length=` into a partial file, checks every piece and then the whole file against the SHA-256 the cloud sends and the record's own, and only then moves it into place. |
| Resuming | A cut link loses one piece, not the file. The next cycle carries on from what the other side reports or from the partial file's size. |
| Entitlement | The cloud accepts a file only if it is waiting for it from that vessel, and writes it where its own copy of the record says. A vessel may download only files of its own records or of records the cloud sent it. Anything else answers 404, the same as a missing file, so a vessel cannot probe for other vessels' files. |
| Paths | A path must sit under the column's prefix, use plain characters, contain no `.`/`..` segments and end in an extension a stored file can have. From a vessel, an unsafe path refuses the whole change. On a vessel, a file at an unsafe path is never fetched. |
| Budget | At most `SYNC_FILES_MAX_BYTES` (50 MB) per cycle, so files never crowd out records on a slow link. An idle vessel makes no file requests: the handshake says how many files the cloud is waiting for. |
| Failures | A failed file is retried with backoff and marked `failed` after 20 attempts. `sync:files retry` resets those; `sync:files` lists them. |

### Integrity

- Every new attachment records its SHA-256 when it is uploaded.
- `sync:files checksum` fills it in for older ones; it reads the files only.
- `sync:files verify` runs nightly at 04:00 on servers with sync set up. It
  reads every file a record names and compares it with its checksum: the
  record's own, or the one noted when the file was stored or received.
- A damaged or missing file is reported by `sync:health` (error) and `/health`.
- On a vessel, `sync:files verify --refetch` fetches it again from the cloud.
  This works for the vessel's own files too.
- `sync:files scan` records every file the records name. Run it after a
  bootstrap or a restore.

## 4. Offline sign-in

- Sign-in on board checks the bcrypt hash in the local `users` table; no
  cloud is needed. Laravel 11 rehashes on sign-in when the cost changes.
  Wrong passwords are limited to 5 a minute (the existing throttle).
- **Deactivated users** (`users.status` false, the office's "Delete user")
  could still sign in before this checkpoint, on the cloud and on board,
  because sign-in never looked at the flag.
  - They are now refused everywhere, and a session they still have is ended
    at their next click ([EndRevokedSessions](../app/Http/Middleware/EndRevokedSessions.php)).
  - On board this takes effect with the sync that brings the flag.
  - `APP_BLOCK_DEACTIVATED_USERS=false` restores the old behaviour (C7-2).
- **Transfers.** A role moved to another vessel used to be sent only to the
  new vessel: rows are routed by their current vessel, so the old ship never
  heard of the move and kept letting the person sign in.
  - When a role's vessel changes on the cloud, the role and its user are now
    also sent once to the vessel they left (`departures` rule; [VesselDepartures](../app/Sync/VesselDepartures.php)).
  - That vessel accepts them, and the user arrives with the unusable
    placeholder hash that shore users already get.
- Role changes come down as before; every permission check on board reads the
  local `roles` table.
- Password hashes and remember tokens are no longer kept in sync bookkeeping:
  conflicts store `[hidden]`, and applied inbox entries are masked
  (`sync.sensitive_columns`).

## 5. Backups and restore

| What | Script | Kept | Notes |
|---|---|---|---|
| Database | backup-database.ps1 | 14 days, at least 3 | mysqldump, zipped; now uses the DPAPI root password |
| Files | backup-files.ps1 (new) | 30 days, at least 5 | Content-addressed: each file stored once by SHA-256, one manifest per backup with its own `.sha256` |

Both run nightly from the maintenance task, which existed already.

`backup-files.ps1`:
- `-Verify <manifest>` checks a backup without restoring.
- `-Restore <manifest>`:
  1. checks every file of the backup first and restores nothing if any is
     missing or damaged;
  2. backs up the current files;
  3. stops the services and puts the files back;
  4. runs `sync:files scan` and `sync:files verify`.
- `-Export <folder> [-WithDatabase]` copies the newest backup set to a USB
  drive or another PC. Backups on the same disk do not survive the disk.

check-health.ps1 now shows the age of the newest backups and the output of
`sync:health`.

## 6. Sync failure monitoring

`php artisan sync:health` (exit 0 ok, 1 warnings, 2 errors; `--json`) lists
what needs a person:

| Check | Level |
|---|---|
| Credentials that cannot be opened / still in plaintext on a vessel | error / warning |
| Sync not set up although configured | error |
| Three cycles in a row failed for a reason other than the link (wrong token, cloud error) | error |
| No successful sync for `SYNC_OFFLINE_WARNING_HOURS` (72) | warning |
| Changes made on board that have not reached the cloud for that long | warning |
| Received changes that failed, or waited more than 24 h for their parent | warning |
| Conflicts waiting for review | info |
| Files failed or missing; damaged files found by the integrity check | warning / error |
| Cloud: a vessel that has not synced for 72 h | warning |

- Being offline at sea is not an error.
- The scheduler runs `sync:health --log` every 15 minutes. It writes each new
  problem once to the `sync` log channel and `sync_logs`, where a log watcher
  can pick it up.
- `/health` has a non-critical `sync_alerts` check.

## 7. Database

One new migration, additive only:
[2026_11_25_100000_create_sync_file_tables.php](../database/migrations/2026_11_25_100000_create_sync_file_tables.php).

| Change | Content |
|---|---|
| `attachments.sha256` | char(64), nullable, indexed |
| New table `sync_files` | Per file a record refers to: path, checksum, size, state, peer, attempts |
| New table `sync_file_grants` | Cloud only |

No existing migration was changed and no data is rewritten.
`sync:files checksum` fills `attachments.sha256` on request.

## 8. Tests

| File | Tests | Covers |
|---|---|---|
| [tests/Feature/Security/SecretStoreTest.php](../tests/Feature/Security/SecretStoreTest.php) | 9 | encryption at rest and file permissions, wrong key, name traversal, settings filled in and never cached, import-env, hidden prompt, nothing printed |
| [tests/Feature/Security/UploadSecurityTest.php](../tests/Feature/Security/UploadSecurityTest.php) | 5 | server-chosen names, spoofed types refused, photos must be images, view headers, safe paths |
| [tests/Feature/Security/OfflineAuthTest.php](../tests/Feature/Security/OfflineAuthTest.php) | 6 | sign-in with no cloud, throttle, deactivation, transfer, role change, hashes masked |
| [tests/Feature/Sync/Files/AttachmentSyncTest.php](../tests/Feature/Sync/Files/AttachmentSyncTest.php) | 8 | upload on board, resume, office file via a stage (and not to another vessel), checksum mismatch, path attack, damage found and refetched, signature, files never block records |
| [tests/Feature/Sync/Engine/SyncHealthTest.php](../tests/Feature/Sync/Engine/SyncHealthTest.php) | 6 | healthy, offline, real failures, inbox, log once, /health |

## 9. Known limits

- **Not run on Windows.** This covers DPAPI through FFI, every PowerShell
  change (installer, backups, check-health) and the php.ini change. Run on a
  test PC before a ship:
  1. `install-server.ps1`;
  2. `navisuite:secrets check`;
  3. a backup and restore of both kinds.
- The MySQL root password exists in plaintext in a temporary file for the
  length of each `mysql`/`mysqldump` call. The file is in the
  administrators-only folder and is deleted afterwards.
- `sync_row_bases` (Checkpoint 6 merging) still holds the users' bcrypt
  hashes, because the password column is merged.
- Backups and exports are not encrypted. They are protected by folder
  permissions and wherever the export is kept (C7-4).
- A file whose record is deleted stays on disk. The backups and other
  records may still need it.
- Transfers are noticed when a role is saved through the application (the
  user screens). A change made directly in the database is not.
- Legacy attachments whose stored name kept an odd extension the browser sent
  are refused when a vessel sends them; the error says why.
- A vessel restored from a backup cannot get back what it sent after the
  backup for one-way tables (inventory, PMS) until the bootstrap tool exists.
  See [disaster-recovery.md](disaster-recovery.md).

## 10. Questions

- **C7-1.** Photos and signatures changed on board stay on board and are
  overwritten by the office's next change to the user, because user records
  are the office's (Checkpoint 6). Should they go up, newest wins, like the
  password (Q4)?
- **C7-2.** Deactivated users are now refused on the cloud too. Is that
  right, or should it apply only on board?
- **C7-3.** Office files on a vessel's procurement stages are now sent to the
  vessel, as the crew can see them on the cloud today. Should some stages'
  files (quotations, prices) stay in the office?
- **C7-4.** Who takes the off-PC export, how often, and should it be
  encrypted with a passphrase the office keeps?
- **C7-5.** Rebuilding a lost vessel server fully from the cloud needs the
  bootstrap tool. Should it come next?
- **C7-6.** Support accounts on board (C6-5) are still open. The mechanism
  exists and stays off.
- **C7-7.** Any signed-in user can open any attachment by its number. This is
  existing behaviour, and on board only the vessel's own files are there.
  Should viewing be limited to the people who can see the requisition?
- **C7-8.** bcrypt cost is 10. Should it be 12 (Laravel's default)? Users
  would be rehashed at their next sign-in.
- **C7-9.** Where does the cloud keep its secrets? On Linux the key-file
  protector is the fallback. A platform key service, or environment variables
  from the service manager, would be better.
