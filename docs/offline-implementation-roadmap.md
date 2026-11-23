# NaviSuite Offline: Implementation Roadmap

**Status:** Approved. Checkpoints 1 to 8 are implemented; see [checkpoint-8-installers.md](checkpoint-8-installers.md) and [e2e-test-plan.md](e2e-test-plan.md) for 8 (the two Windows installers, the test gate and the acceptance test plan), [checkpoint-7-files-security-backup.md](checkpoint-7-files-security-backup.md) and [disaster-recovery.md](disaster-recovery.md) for 7, and [checkpoint-1-app-mode.md](checkpoint-1-app-mode.md), [checkpoint-2-sync-foundation.md](checkpoint-2-sync-foundation.md), [checkpoint-3-windows-vessel-server.md](checkpoint-3-windows-vessel-server.md), [checkpoint-4-desktop-client.md](checkpoint-4-desktop-client.md), [checkpoint-5-sync-engine.md](checkpoint-5-sync-engine.md) and [checkpoint-6-business-sync.md](checkpoint-6-business-sync.md). Checkpoints 3 and 4 were redefined as the Windows vessel server and the Windows desktop client (together, checkpoint 9 below); checkpoint 5 is the reusable sync engine (the pull and push APIs, inbox, deferral and conflict log of checkpoints 4 to 6 below, and the sync log and status of checkpoint 8); checkpoint 6 adds the rules for requisitions, inventory, PMS and master data, which are built and tested but switched off by default. Checkpoint 7 adds attachment and signature files, offline sign-in hardening, encrypted credentials, file backups, a restore procedure and sync failure monitoring (command line and /health). Still to do: the vessel data bootstrap (checkpoint 3 below, needed before the modules are switched on) and the monitoring screens (8).
**Date:** 2026-11-19
**Based on:** [offline-architecture-audit.md](offline-architecture-audit.md). References such as R1 or Q1 point to that document's risks and questions.

---

## 0. Guiding decisions

The operating model was approved on 2026-11-22 as ADR-001, recorded in [architecture-decisions.md](architecture-decisions.md). Where this roadmap and ADR-001 differ, ADR-001 applies, as refined by ADR-003 to ADR-005 (2026-11-23: vessels maintain master data and delete their own records directly; user blocking and passwords by a Superadmin only; backups on the master PC). Open questions are in [pending-decisions.md](pending-decisions.md), and the remaining work, in order, in [implementation-roadmap.md](implementation-roadmap.md) (Checkpoints 9 to 24). Two details below are historical: the server installer is built with NSIS, not Inno Setup, and sync nodes use hashed tokens in `sync_nodes`, not Sanctum.

1. **One codebase, two modes.** The vessel server runs the same Laravel application as the cloud, configured with `APP_MODE=vessel` and `APP_VESSEL_ID=<id>`. The cloud keeps `APP_MODE=cloud`, which is the default. Without the flag, behaviour is unchanged.
2. **Additive changes only.** New migrations, new tables, new columns, new middleware, new commands and new API routes. Existing migrations are not edited. Existing behaviour stays the default. A handful of small compatibility fixes are listed explicitly in section 4.3.
3. **Hub and spoke.** Vessels talk only to the cloud, and only the vessel initiates connections. The cloud never connects to a vessel, so satellite NAT and firewalls are not a problem.
4. **Every row has one owner per column.** Most tables have a single writer side. The requisition aggregates hand the "baton" back and forth. Sync rules follow that ownership rather than generic merging (audit section 13).
5. **Capture changes in the database, in the same transaction.** MySQL triggers write a change log. This is the transactional outbox. It also catches upserts and query-builder writes, of which the audit found at least 16 sites that bypass Eloquent (R2).
6. **No new runtime technologies where an existing one will do.** PHP, MySQL, Apache and Laravel's own queue and scheduler run on the server. JavaScript and Electron run on the client. The additions are Laravel Sanctum (first-party), Inno Setup for the server installer, WinSW to run PHP commands as Windows services, and electron-builder for the client installer.

---

## 1. Proposed architecture

```
                       ┌──────────────────────────────────────────┐
                       │  CLOUD  (APP_MODE=cloud)                 │
                       │  Laravel app (unchanged UI)              │
                       │  MySQL: all vessels                      │
                       │  + /api/sync/v1  (Sanctum node tokens)   │
                       │  + sync_change_log (triggers)            │
                       │  + fleet sync dashboard                  │
                       └───────────────▲──────────────────────────┘
                                       │ HTTPS, vessel-initiated only
                                       │ push batches / pull changes / files
          ┌────────────────────────────┴───────────────────────────┐
          │  VESSEL MASTER PC  (APP_MODE=vessel)                    │
          │  Apache + PHP  ── same Laravel app, vessel-scoped       │
          │  MySQL (127.0.0.1 only): this vessel + reference data   │
          │  Windows services:                                       │
          │    NaviSuite-Web  NaviSuite-MySQL  NaviSuite-Sync        │
          │    NaviSuite-Queue  NaviSuite-Scheduler                  │
          │    NaviSuite-Discovery (UDP)                             │
          │  C:\NaviSuite\data  (uploads, storage, backups)          │
          └───────▲──────────────────▲──────────────────▲───────────┘
                  │ HTTPS (LAN, pinned self-signed cert)            │
          ┌───────┴──────┐   ┌───────┴──────┐   ┌───────┴──────┐
          │ Client PC 1  │   │ Client PC 2  │   │ Client PC n  │
          │ Electron     │   │ Electron     │   │ or any       │
          │ thin shell   │   │ thin shell   │   │ browser      │
          └──────────────┘   └──────────────┘   └──────────────┘
```

### 1.1 What changes in vessel mode

| Concern | Vessel mode behaviour |
|---|---|
| Who can log in | Users whose role is on this vessel, plus one designated support account (Q5). Shore users exist only as display and signature records, with login disabled. |
| Cloud-owned screens | Master data, user management, vessel records, SRD and SSM approval and procurement actions are read-only or hidden. This is enforced by one new `app.mode` middleware (`EnforceAppMode`) that uses a request-path deny list, so existing controllers are not edited. |
| Office actions | Not available onboard. They arrive by sync. Pages show "awaiting office, last synced at …". |
| Scheduler | `pms:refresh-jobs` runs on the vessel for its own jobs. The cloud skips offline-enabled vessels (section 5.7). |
| Assets | Served locally. CDN libraries are vendored, and the same files are used in the cloud (section 4.3). |

### 1.2 What changes in the cloud

| Concern | Cloud behaviour |
|---|---|
| Offline-enabled vessels | New flag `vessels.offline_enabled_at`. Once set, ship roles for that vessel are read-only on the cloud (Q1, R1). Office edits to vessel-owned data are blocked, except PMS approval (Q2). |
| Sync API | New `routes/api.php` group `/api/sync/v1`, protected by Sanctum tokens bound to `sync_nodes`, not to users |
| Monitoring | Fleet sync dashboard showing each vessel's last contact, backlog, failures, conflicts and versions |

---

## 2. Database changes required: principles

1. **Global identity.** Every synchronized row gets a `sync_uuid`. Local integer primary keys stay as they are, so no existing code changes.
   - Payloads reference rows and foreign keys by UUID only.
   - The receiver translates UUIDs to local IDs.
   - PMS tables with UUID primary keys use `id` directly.
2. **Deterministic UUIDs for natural keys.** Rows that two sides may create independently get a UUID computed from their natural key, so both sides arrive at the same value:

   | Table | Natural key |
   |---|---|
   | items | `item_key` |
   | item_groups | category symbol plus lower-cased `path` |
   | vessel_items | vessel UUID plus item UUID |
   | manufacturers | lower-cased name |
   | categories | symbol |
   | currencies | code |
   | ports | `unlocode` |
   | ranks | code |
   | budget_groups, certificate_categories, PMS master tables | name |

   The formula is a UUIDv5-shaped SHA-1. It is implemented identically in PHP and in trigger SQL, with a test that proves the two match.
3. **Cloud-inserted tables keep identical integer IDs on vessels.** Only the cloud inserts users, roles, vessels and reference data. The vessel copies them with the cloud's IDs. URLs, file paths such as `attachments/{user_id}/…` and FK values for these tables are therefore identical on both sides.
4. **Row versions.** Each synchronized table gets `sync_version INT UNSIGNED DEFAULT 0`. A trigger increments it on update. Payloads carry `version_before` and `version_after` for conflict detection (section 5.5).
5. **Identical schema everywhere.** The handshake exchanges the migration version and refuses to sync on mismatch (R3).

### 2.1 Why not per-vessel ID ranges

The alternative was giving each server its own auto-increment offset, so integer IDs never collide. It was rejected for three reasons:

- It depends on a MySQL session setting on every connection, and a missed setting on one reinstalled server fails silently.
- It does nothing for rows created independently on both sides, which still need natural-key merging.
- It gives no idempotency key for payloads.

---

## 3. New tables required

All are created by new migrations. All are local-only (class G) and never synchronized.

| Table | Purpose | Key columns |
|---|---|---|
| `sync_nodes` | Identity of this server and its peers. On the cloud: one row per vessel server. On a vessel: self plus cloud. | `id`, `uuid`, `type` (cloud or vessel), `vessel_id`, `name`, `generation` (incremented after a restore), `schema_version`, `app_version`, `status` (active, suspended or retired), `last_seen_at`, `last_push_at`, `last_pull_at`, `pull_cursor`, `push_acked_seq`, `clock_skew_seconds` |
| `personal_access_tokens` | Sanctum tokens. The tokenable is a `SyncNode`, not a user. | Standard Sanctum |
| `sync_change_log` | **The outbox capture.** Written by triggers in the same transaction as the business write. | `id` (BIGINT, the sequence), `table_name`, `row_uuid`, `row_pk`, `op` (I, U or D), `changed_columns`, `version_after`, `scope_vessel_id` (NULL means global), `origin_node_id` (NULL means local user), `created_at(6)` |
| `sync_outbox_batches` | Packaged, compressed batches ready to send. Their UUIDs make resends idempotent. | `uuid`, `target_node_id`, `seq_from`, `seq_to`, `change_count`, `bytes`, `sha256`, `payload` (LONGBLOB, gzip JSON), `status` (pending, sent, acked, rejected), `attempts`, `next_attempt_at`, `last_error`, `acked_at` |
| `sync_inbox_batches` | **The inbox.** Every received batch, with a unique UUID, so duplicates are acknowledged but not re-applied. | `uuid` (unique), `origin_node_id`, `seq_from`, `seq_to`, `status` (applied, partially_deferred, failed), `received_at`, `applied_at`, `error` |
| `sync_inbox_deferred` | Changes whose foreign-key parents have not arrived yet. They are retried automatically. | `inbox_batch_id`, `table_name`, `row_uuid`, `payload`, `missing_refs`, `attempts` |
| `sync_conflicts` | Every detected conflict with both versions, how it was auto-resolved, and manual review state | `table_name`, `row_uuid`, `local_values`, `remote_values`, `version_base`, `version_local`, `version_remote`, `rule_applied`, `status` (auto_resolved, needs_review, reviewed), `reviewed_by`, `reviewed_at` |
| `sync_files` | Binary transfer queue for attachments and legacy images | `file_uuid`, `owner_table`, `owner_uuid`, `disk`, `path`, `sha256`, `size`, `direction`, `status` (pending, transferring, done, failed, missing), `bytes_done`, `attempts`, `last_error` |
| `sync_runs` | Log and monitoring of each push, pull or file cycle | `node_id`, `kind`, `started_at`, `finished_at`, `status`, `changes`, `bytes`, `error` |
| `jobs`, `job_batches`, `failed_jobs` | Laravel database queue, which does not exist today | Standard Laravel |
| `cache`, `cache_locks` | Optional, if a database cache is preferred to file cache on Windows | Standard Laravel |

---

## 4. Existing tables requiring modifications

All changes are additive and made by new migrations.

### 4.1 Sync columns

**`sync_uuid CHAR(36) NULL UNIQUE` and `sync_version INT UNSIGNED NOT NULL DEFAULT 0`** are added to these 52 tables:

- **People and fleet:** users, roles, vessels, vessel_particulars, framework_descriptions, dimensions, engines, boilers
- **Reference data:** categories, budget_groups, currencies, ports, ranks, surveys, certificates, certificate_categories, manufacturers, pms_locations, pms_component_categories, pms_job_types, pms_conditions
- **Catalog:** items, item_groups, vessel_items
- **Vessel records:** vessel_surveys, vessel_certificates, vessel_equipment
- **Item requisitions:** orders, order_items, order_approvals, order_form_parts, order_form_part_edits, order_item_quantity_changes, order_procurement_steps, order_invoices
- **Service requisitions:** service_requisitions, service_requisition_items, service_requisition_approvals, service_requisition_procurement_steps, service_requisition_invoices
- **Attachments:** attachments, attachment_order_item, attachment_order_step, attachment_stock_consumption, attachment_service_req_step
- **Inventory:** stock_imports, item_imports, stock_consumptions, stock_movements
- **PMS (integer keys):** pms_counter_readings, pms_job_intervals, pms_job_checklist_items, pms_required_parts, pms_used_parts

The UUID-keyed tables `pms_components`, `pms_job_definitions` and `pms_jobs` get `sync_version` only.

**Rollout:**

1. Add the columns as nullable.
2. Backfill in chunks, using deterministic values where section 2 applies.
3. Install triggers that fill `sync_uuid` on insert.
4. Make the column `NOT NULL` in a later migration once every row is filled.

On MySQL 8, adding a nullable column is instant. Building the unique indexes on large tables such as `stock_movements` and `ports` needs a quiet window.

### 4.2 Other columns

| Table | Change | Reason |
|---|---|---|
| `vessels` | `offline_enabled_at TIMESTAMP NULL` | Cut-over flag. Activates the cloud-side write lock for ship roles (R1). |
| `users` | `password_changed_at TIMESTAMP NULL` | Newest-wins rule for password changes made onboard (Q4) |
| `attachments` | `sha256 CHAR(64) NULL` | Integrity check and duplicate detection for file transfer |

### 4.3 Small, compatible code fixes required before or alongside sync

These change existing code minimally and behave the same in the cloud. Each needs explicit approval.

1. **Vendor all CDN assets** into `js/vendor` and `css/vendor` and update about 7 layout and partial views. The pages look the same in the cloud and also work offline.
2. **Disable `/register`** by passing `Auth::routes(['register' => false])` (R14).
3. **Use UUID filenames for legacy uploads** of certificate copies, photos and signatures, instead of `time()`. Existing stored paths keep working.
4. **Guard the requisition counter.** Use `lockForUpdate`, or retry once on a duplicate `req_no` (audit section 5.2).
5. **Let `pms:refresh-jobs` skip offline-enabled vessels** on the cloud, through an option or a mode check.
6. **Fix the 9 failing tests.** The partial test schemas need the tables the cached sidebar counts now read (R12).

---

## 5. Synchronization strategy

### 5.1 Change capture (transactional outbox)

A generated trigger set is installed by `php artisan sync:install-triggers` from a single registry, `config/sync.php`. The registry describes each table: class, scope path, owned columns, FKs, natural key and conflict rule.

For each synchronized table:

| Trigger | Action |
|---|---|
| `BEFORE INSERT` | Set `sync_uuid` if it is empty: random, or deterministic from the natural key |
| `BEFORE UPDATE` | `NEW.sync_version = OLD.sync_version + 1` |
| `AFTER INSERT`, `UPDATE`, `DELETE` | Insert into `sync_change_log` with the changed column list (`OLD.c <=> NEW.c` per column), `scope_vessel_id` (read directly or through the parent, as the registry defines), and `origin_node_id = @sync_origin`. A delete becomes a tombstone. |

- The sync applier sets `@sync_origin` to the sending node. Changes received from vessel A are therefore never echoed back to A, but still flow to anyone else who needs them, such as a shared catalog item.
- Triggers do not fire for FK cascades. Parent deletes are replicated and the receiver's identical FKs cascade the same way.
- **Fallback if the cloud host forbids triggers** (R4, Q8): an Eloquent observer trait plus explicit `SyncCapture::record()` calls at the bypassing write sites the audit found. This is acceptable on the cloud only, where far fewer of those sites run. The vessel always uses triggers.

### 5.2 Packaging

`sync:work` is the vessel's NaviSuite-Sync service. It reads `sync_change_log` from the last packaged sequence and builds batches:

- **Coalesce per row.** Repeated updates to one row become one change, holding the row's current values for the changed columns. It keeps the position of the row's first change, so parents still precede children.
- **Filter by ownership.** Columns this side does not own are dropped. This removes noise, such as the cloud's derived PMS status, and stops illegitimate writes leaking.
- **Translate foreign keys** to UUIDs.
- **Limit batch size** to about 2 MB or 2,000 changes, gzip-compressed, with a SHA-256 checksum.
- **Send data before files.**

### 5.3 Transport and API (`/api/sync/v1`)

| Endpoint | Purpose |
|---|---|
| `POST /handshake` | Node UUID, generation, schema and app version. Returns the cloud's time (for skew detection), cursors and protocol version. |
| `POST /push` | Upload one batch. The cloud records the batch UUID in its inbox, applies it, and returns `ack` with any conflicts or deferrals. Resending the same batch returns the same ack. |
| `GET /pull?cursor=N&limit=M` | Cloud changes with `scope_vessel_id IN (this vessel, NULL)` and origin other than this vessel, in sequence order. Returns the next cursor and `has_more`. FK parents the vessel lacks, such as a newly linked catalog item, are included automatically. |
| `POST /pull/ack` | Records the vessel's applied cursor, for monitoring |
| `PUT /files/{uuid}` and `GET /files/{uuid}` | Chunked, resumable file transfer with `Content-Range`, verified by SHA-256 |
| `GET /bootstrap/{vessel}` | Initial vessel snapshot. Also available as an offline file, for USB commissioning. |

**Cycle and retry.** The sync service runs a cycle when the cloud is reachable: handshake, push until empty, pull until empty, then files.

| Condition | Behaviour |
|---|---|
| Online | Every 5 minutes. Configurable. |
| Offline | Exponential back-off from 30 seconds up to 30 minutes |
| User presses "Sync now" | Runs immediately |
| Bandwidth cap or schedule window set | Respected (Q6) |

Pending changes live in MySQL, so they survive restarts and power loss.

**Optional later transport.** Batches are self-contained and signed, so the same batch files could later travel by email or USB, as CloudShipManager does. This is not in scope for the first release.

### 5.4 Applying changes (inbox)

1. Check the inbox. A known batch UUID means "re-ack and stop" (idempotency).
2. Open one transaction per batch and set `@sync_origin`.
3. For each change, in order:
   - Check the sender may write this table, this scope and these columns.
   - Resolve FK UUIDs to local IDs. Any missing parent sends the change to `sync_inbox_deferred`.
   - Detect conflicts (section 5.5) and apply the change.
4. Apply each requisition aggregate's rows within the same batch, so the implicit state is never half-applied (R5).
5. Commit, write the inbox row and ack.
6. After commit, flush `NavCounts` and `PmsDashboardCache` for affected vessels, because direct writes skip their Eloquent hooks.
7. Apply ledger rows such as `stock_movements` and `stock_consumptions` as facts. They are never replayed through `StockService::consume()`.

### 5.5 Conflict detection and resolution

- **Fast-forward.** If the receiver's `sync_version == version_before`, apply the change and set `sync_version = version_after`.
- **Concurrent change, disjoint columns.** Merge both and bump the version. The merged state flows back on the next cycle.
- **Concurrent change, same column.** Apply the table's rule and record the conflict in `sync_conflicts`.

| Class (audit section 13) | Rule |
|---|---|
| A, A' (cloud-owned) | The cloud wins. Vessel changes are rejected at the API. |
| B (catalog) | Inserts merge by deterministic UUID. Updates use last-writer-wins by `updated_at`, logged. |
| C (vessel-owned) | The vessel wins. The cloud can only make the listed exceptions, such as PMS approval `WaitingApproval` to `Completed`, which is a guarded transition that cannot overlap a vessel edit. |
| D (requisitions) | **The holder wins.** The registry defines `holder(order)` from the same rules as `Order::hasPendingActionFor()`. The vessel holds draft, ship approvals, and delivered until receipt. The cloud holds everything else. Baton columns are `status`, `procurement_stage`, `rejected_*` and `*_app`. A change from the side that did not hold the baton at `version_before` is logged for review. Column ownership on `order_items` (`item_qty` and `rcv_item_qty` for the vessel, `del_item_qty` and invoice fields for the cloud) prevents most overlaps. |
| E (certificates, surveys) | Last-writer-wins, logged, until Q3 is answered |
| Users' password | The newest `password_changed_at` wins |

In practice, conflicts should be rare because the business flow already serializes who acts. The conflict log exists to make the rare case visible rather than silent.

### 5.6 Attachments

- Attachment metadata rows sync with their parent's class.
- A `sync_files` entry is created for each file that needs to move.
- Files are transferred after data, in chunks, resumable, with SHA-256 checks. Priority and caps follow Q6 and Q10.
- Until a file arrives, the attachment view shows "File not yet transferred" instead of a 404.
- **Up:** requisition item files, consumption evidence and receipt evidence.
- **Down:** shore users' signature images (needed for print forms) and, if Q10 says so, procurement documents.

### 5.7 Module-specific rules

- **Requisitions.**
  - The vessel generates `req_no`, and the cloud accepts it as-is.
  - A duplicate `req_no` on ingest blocks that batch and raises an alert. It is never auto-renumbered.
  - Office decisions travel down as column changes and appear onboard on the next pull.
- **Inventory.**
  - The vessel is the single writer.
  - `vessel_items` stock columns, `stock_movements`, `stock_consumptions` and `stock_imports` go up verbatim.
  - Cloud catalog links (`vessel_items` rows) come down as inserts.
- **PMS.**
  - Only the vessel generates jobs and refreshes job status for offline-enabled vessels.
  - Office approval comes down.
  - Super-admin edits to a commissioned vessel's PMS tree are blocked on the cloud (Q2).
- **Users.**
  - This vessel's crew come down with password hashes.
  - Shore users come down without hashes and cannot log in.
  - A role moved to another vessel produces a change for both the old and the new vessel, so the old vessel disables the login.

### 5.8 Commissioning a vessel (bootstrap)

1. On the cloud, set `vessels.offline_enabled_at`. Ship-role writes for that vessel stop on the cloud.
2. Run `sync:export-vessel {id}`. It reads the change-log cursor first, then snapshots in one consistent transaction:
   - reference data
   - this vessel's crew and shore display users
   - linked catalog
   - all vessel-owned rows and workflow aggregates
   - a manifest of files, with the files themselves optional
3. The server installer imports the package and stores the cursor. The next pull catches anything changed since the snapshot. Duplicates are harmless.
4. Verify row counts and checksums per table. Then the vessel goes live.

### 5.9 Monitoring and logs

| Where | What it shows |
|---|---|
| Vessel `/sync` page (Master, Chief Engineer, support) | Online or offline, last successful push and pull, pending changes and files, failed batches, conflicts needing review, disk space, last backup, "Sync now" |
| Cloud fleet dashboard (super-admin) | Per vessel: last contact, backlog age, app and schema version, clock skew, conflicts and errors. Highlights vessels silent for more than N days. |
| Logs | A dedicated `sync` log channel (daily files), plus `sync_runs` rows retained for 90 days |

---

## 6. LAN connectivity strategy

| Topic | Approach |
|---|---|
| Server address | Recommend a DHCP reservation or static IP for the master PC, set at installation. The Windows hostname is fixed, for example `NAVISUITE-SRV`. |
| Protocol | HTTPS on TCP 443, with port 80 redirecting. A per-vessel self-signed certificate is generated by the installer. MySQL listens on `127.0.0.1` only. |
| Discovery | `navisuite:discovery` runs as a Windows service using PHP's sockets extension, which is already used locally. It listens on one UDP port, for example 47800, to be confirmed as free on ship networks. The client broadcasts a probe to `255.255.255.255` and each interface's subnet broadcast. The server answers with server ID, vessel name and IMO, hostname, IPs, port, certificate SHA-256 fingerprint and app version. |
| Pairing | On first run the client lists the servers it found, normally exactly one. It stores the server ID, fingerprint, hostname and last-known IP. |
| Manual fallback | Enter an IP or hostname and port. The client calls `GET /api/navisuite/info`, shows the vessel name and fingerprint, and asks the user to confirm. |
| DHCP changes | Reconnect order: last-known IP, then hostname, then re-discovery by server ID. A changed IP means the user logs in again, because the session cookie is tied to the host. |
| Auto-reconnect | When a request fails, the client polls `GET /api/navisuite/health` every 5 seconds and shows a non-blocking "Reconnecting to onboard server…" overlay. It reloads the current page when the server is back. Server restarts keep sessions, because sessions are stored in files. |
| Separate subnets | Broadcast does not cross routers. Bridge and engine-room VLANs need manual configuration (Q7). |
| Firewall | Inbound TCP 443 and 80 and the UDP discovery port, limited to Private and Domain profiles with remote address `LocalSubnet`. MySQL is not opened. |

---

## 7. Windows server deployment strategy (`NaviSuite-Server-Setup.exe`)

**Built with Inno Setup.** It is a fully offline installer with no downloads at install time.

| Bundled component | Choice | Reason |
|---|---|---|
| Web server | Apache 2.4 (Apache Lounge build) with PHP as an Apache module (thread-safe build) | The app already relies on Apache `.htaccess`. It runs as a native Windows service. It avoids `php-cgi` process management. |
| PHP | The same minor version as the cloud. Extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`, `xml`, `intl`, `sockets`, `curl` | Matches the current dependencies |
| Database | MySQL Community, the same major version as the cloud (R3) | Must be confirmed (Q8). If the cloud is still 5.7, which reached end of life in 2023, upgrade the cloud first. |
| Service wrapper | WinSW | Runs `php artisan sync:work`, `queue:work --max-time=3600`, `schedule:work` and `navisuite:discovery` as services with restart-on-failure |
| Runtime | VC++ redistributable | Required by PHP and Apache |

**Directory layout:**

| Path | Contents |
|---|---|
| `C:\NaviSuite\app` | Code, read-only to normal users. `vendor/` is included. |
| `C:\NaviSuite\data` | MySQL data, `storage/`, and uploaded images. The app's `images/cert_copy`, `images/signature` and `images/userphoto` are NTFS junctions into this folder, so upgrades never touch uploads. |
| `C:\NaviSuite\backups` | Local backups |
| `C:\NaviSuite\logs` | Logs |

**Apache configuration.** `DocumentRoot` is the app folder, because the public path is the project root. It uses a **strict allowlist**: only `index.php`, `css/`, `js/`, `images/`, `assets/`, `svg/` and vendored libraries are served. Everything else returns 403, including `.env`, `*.sql`, `storage/`, `vendor/`, `app/`, `config/`, `database/`, `routes/`, `tests/` and `.git` (C1).

**Install steps:**

1. Check prerequisites: Windows 10 or 11 64-bit, disk space, and ports 443, 80 and the UDP port being free.
2. Install the components and initialize MySQL with a random root password. Create the app database and a least-privilege app user.
3. Write `.env`: `APP_MODE=vessel`, vessel ID, a new `APP_KEY`, `APP_DEBUG=false`, cloud URL, database credentials, `QUEUE_CONNECTION=database`.
4. Run `php artisan migrate --force`, then `sync:install-triggers`.
5. Import the bootstrap package, from a file or downloaded online, and register the node with a one-time enrolment code from the cloud. That code is exchanged for the Sanctum token.
6. Generate the LAN TLS certificate and configure the vhost.
7. Create the services, all set to automatic start with recovery on failure. The workers start after MySQL.
8. Add firewall rules.
9. Set Windows options: a power plan that never sleeps, hibernation off, active hours and no automatic restart for updates, time sync on, and antivirus exclusions for the MySQL data folder.
10. Run a health check and show the server address and fingerprint for client pairing.

**Upgrades.** The same installer runs in upgrade mode:

1. Stop the services.
2. Take a full backup.
3. Replace the code.
4. Run `migrate --force` and re-install the triggers.
5. Run the health check.
6. Start the services.
7. If any step fails, roll back automatically to the pre-upgrade backup.

Upgrade packages are signed. They are delivered by technician or USB first. Delivery over the sync link comes later (Q11). Within a support window, the cloud API accepts the current and previous protocol versions.

---

## 8. Desktop client strategy (`NaviSuite-Client-Setup.exe`)

### 8.1 Options evaluated against the existing frontend

| Option | Fit with the Blade, jQuery and Bootstrap app | Verdict |
|---|---|---|
| **Electron thin shell** that loads the onboard server's existing web UI | Full. Nothing is rewritten. | **Recommended** |
| Plain browser (Edge or Chrome) pointed at the server | Full. Zero install, but no discovery, pinning or reconnect handling. | Keep as a supported fallback |
| WebView2 shell (.NET) or Tauri | Full and smaller, but adds C# or Rust tooling. The WebView2 runtime may be missing on offline Windows 10 PCs. | Not chosen |
| NativePHP | Bundles PHP and Laravel per PC | **Rejected.** It contradicts the requirement for no backend on clients. |
| SPA rewrite on new JSON APIs | Would duplicate 106 views and about 120 AJAX endpoints. Months of work and two UIs to maintain. | **Rejected for now.** It can be revisited module by module later. |

### 8.2 Why Electron fits here

- The team already works in JavaScript.
- Chromium is bundled, so there is no runtime to install on offline PCs and rendering is identical everywhere.
- Node's `dgram` module handles UDP discovery.
- `setCertificateVerifyProc` pins the vessel's self-signed certificate.
- electron-builder produces a per-machine NSIS installer.
- `electron-updater` with a "generic" provider can fetch client updates **from the vessel server itself**, so client PCs never need internet.

The costs are about 100 MB installed and a requirement for Windows 10 or later (Q7).

### 8.3 Client features

- First-run discovery and pairing screen, with manual entry.
- A main window that loads `https://<server>/`.
- An offline and reconnect overlay.
- A download handler for PDF and Excel exports that saves to Downloads.
- Printing through Chromium.
- A version check against the server.
- No local data storage, apart from settings such as server ID, fingerprint, last IP and window state.

The requirement says clients use "HTTP/HTTPS APIs" (Q13). This design uses HTTPS to load the existing UI, plus a few small JSON endpoints for info, health and client updates. If the requirement means a separate API-driven client, that is a much larger project and needs a separate decision.

---

## 9. Security considerations

| Area | Measure |
|---|---|
| **Fix now, cloud** | Check whether `/.env`, `/*.sql`, `/Archive.zip` and `/storage/logs/*` can be downloaded on the live site (R10, C1). Block them. Rotate `APP_KEY` and database credentials if they were exposed. Move dumps and the archive out of the web root. |
| Framework | Upgrade from Laravel 11, now out of security support, to the current supported major before the first vessel release (R11). Verify the support dates at that time. |
| Node authentication | One Sanctum token per vessel node, stored encrypted in the vessel's `.env`. It is issued through a one-time enrolment code and can be revoked or rotated from the cloud dashboard. |
| Ingest authorization | The cloud checks every pushed change against the registry: allowed table, `scope_vessel_id` equal to the node's vessel, owned columns only, and valid workflow transitions. A compromised vessel can damage only its own vessel's data. |
| Transport | TLS to the cloud with normal certificate validation. Payload SHA-256 checksums. Optional HMAC signing of batches for file transport later. |
| LAN | HTTPS with a pinned per-vessel certificate. `SESSION_SECURE_COOKIE=true`. Firewall limited to the local subnet. MySQL bound to localhost. |
| Vessel logins | Only this vessel's crew and one support account. Shore users have no password hash onboard. Login throttling (Laravel default) applies. |
| Data minimisation | Vessels receive only their own data, plus reference data and the catalog subset. They never receive other vessels' records or other crews' password hashes. |
| At rest | BitLocker on the master PC if the Windows edition allows it (Q7). Backups encrypted with a per-vessel key. |
| App hardening | `APP_DEBUG=false`. `/register` disabled. A unique `APP_KEY` per vessel. Signature and certificate images later moved behind authenticated routes, since today they are public files (audit section 8). |
| Updates | Signed installers and update packages, with the signature checked before install |

---

## 10. Backup and recovery strategy

| Layer | Plan |
|---|---|
| Vessel, nightly | A scheduled `navisuite:backup` task: `mysqldump --single-transaction --routines --triggers`, plus `storage/app` and the upload folders, compressed and encrypted to `C:\NaviSuite\backups`. Keep 14 daily and 8 weekly copies. Optionally copy to a second disk, NAS or USB (Q14). |
| Vessel, verification | Weekly automatic test restore into a scratch schema with row-count checks. The result appears on the `/sync` page. |
| Off-site | Once synced, the cloud holds a full copy of the vessel's data. That makes the cloud the vessel's off-site backup. |
| Cloud | Confirm the existing hosting backups. Daily database plus files, with point-in-time recovery if available. This is outside this project but a prerequisite. |
| Master PC failure | Install on a spare PC, then commission from a cloud bootstrap (section 5.8). Only changes made since the last successful push are lost, unless a local backup is newer. If one is, restore it, and the sync then sends what the cloud lacks. |
| Restore from an old backup | Restoring increments `sync_nodes.generation`. At handshake the cloud sees the regression and does two things: it ignores re-sent batches it already applied (inbox idempotency), and it re-sends cloud-side changes from the vessel's restored cursor (R8). |
| Runbook | A one-page crew procedure for "server will not start" and a technician procedure for "replace master PC" |

---

## 11. Testing strategy

1. **Baseline.** Make the existing 186 tests pass. Nine fail today. Run them in CI on every push.
2. **Unit tests** for the registry, deterministic UUIDs (the PHP and SQL formulas must give identical results), payload building and coalescing, FK translation, ownership filtering, conflict rules and `holder(order)`.
3. **MySQL-backed sync suite.** Triggers do not exist in SQLite. Use two MySQL schemas, `cloud` and `vessel`, on one MySQL service, with a fake in-process transport. Scenarios:
   - A full item requisition cycle across offline gaps: create, approve onboard, office approval, delivery, receipt with stock credit, invoice, closed.
   - Service requisition including renewal receipt.
   - Consumption and stock upload while offline for days, then sync. The cloud ledger and balance must equal the vessel's exactly.
   - PMS completion, next-job generation and office approval. Refresh jobs on both sides must not create conflicts.
   - Duplicate, out-of-order and partially failed batches. A missing FK parent leads to deferral and later application.
   - Concurrent edits to every class, checking the expected rule and conflict log.
   - Hard deletes and cascades.
   - Clock skew of plus or minus several days.
   - Restore from an old backup.
4. **Attachment tests.** Interrupted and resumed transfer, checksum mismatch, a missing file placeholder.
5. **Performance tests.** First bootstrap of the largest vessel. A 45k-row stock upload producing changes. Pull of a full catalog over a throttled link (`curl --limit-rate` on the test transport).
6. **Windows tests on clean VMs** (Windows 10 22H2 and Windows 11):
   - install, reboot and auto-start
   - upgrade and rollback
   - DHCP address change
   - firewall profiles
   - a sleep attempt
   - power loss during sync
   - three clients at once
   - client discovery across a switch
7. **Pilot** on one vessel for at least 4 weeks with daily review of the fleet dashboard, before the wider rollout.

---

## 12. Implementation checkpoints

Each checkpoint ends with a demo and your approval before the next starts. Sizes are relative: S is days, M is 1 to 2 weeks, L is 3 or more weeks for one developer.

| # | Checkpoint | Deliverables | Exit criteria | Size |
|---|---|---|---|---|
| 0 | **Decisions and hygiene** | Answers to Q1–Q15. Cloud exposure check and fix (R10). Green test suite. Vendored CDN assets. `/register` off. Laravel upgrade plan. | Questions answered. Cloud passes an exposure check. CI is green. | M |
| 1 | **Vessel mode** | `APP_MODE` config, `app.mode` middleware (`EnforceAppMode`), login scoping, read-only cloud screens onboard, `offline_enabled_at` write lock on the cloud | The app runs from a manually loaded vessel snapshot on a Windows PC, fully offline, with all ship workflows usable. The cloud is unchanged when the flag is off. | M |
| 2 | **Identity and capture** | Sync column migrations and backfill, `config/sync.php` registry, trigger generator, `sync_change_log`, node tables, Sanctum | Triggers run on the cloud with no functional or noticeable performance change. Every write site produces correct change-log rows. | L |
| 3 | **Bootstrap** | `sync:export-vessel` and import, enrolment, verification report | A vessel is commissioned from the cloud with counts and checksums matching | M |
| 4 | **Cloud to vessel** | Pull API and applier for classes A, A' and B. Users and signatures. | Master data and user changes appear onboard within one cycle. Duplicates are harmless. | M |
| 5 | **Vessel to cloud** | Packaging, push API, inbox, deferral, class C including stock and PMS | Days of offline stock and PMS work arrive intact. The cloud ledger equals the vessel ledger. | L |
| 6 | **Requisitions both ways** | Class D aggregates, holder rule, conflict log and review screen | The full requisition life cycle completes across offline gaps. The conflict tests pass. | L |
| 7 | **Files** | `sync_files`, chunked resumable transfer, placeholders | Attachments and signatures transfer reliably over a throttled, interrupted link | M |
| 8 | **Monitoring and operations** | Vessel `/sync` page, cloud fleet dashboard, sync log channel, backup command and verification | Operators can see and fix every failure state without database access | M |
| 9 | **Server installer** | Inno Setup package, services, firewall, Windows settings, upgrade and rollback | Clean-VM install, reboot and upgrade tests pass | L |
| 10 | **Desktop client** | Electron shell, discovery, pinning, reconnect, NSIS installer, updates served from the vessel server | The client tests from section 11 pass | M |
| 11 | **Pilot and rollout** | One pilot vessel, runbooks, training, then a phased fleet rollout | 4 weeks of pilot without data loss or unresolved conflicts | L |

Checkpoints 9 and 10 can run in parallel with 4 to 7 if a second developer is available.

---

## 13. Assumptions in force until the questions are answered

| Question | Working assumption |
|---|---|
| Q1 Single author per vessel | Yes. The cloud is read-only for a commissioned vessel's ship roles. |
| Q2 Office edits to vessel data | Only PMS job approval |
| Q3 Certificates and surveys | Co-authored, last-writer-wins with review |
| Q4 Password changes onboard | Allowed, newest wins |
| Q5 Shore logins onboard | No, apart from one support account |
| Q6 Sync timing | Automatic every 5 minutes when online, with a manual "Sync now" |
| Q7 Operating system | Windows 10 22H2 or later, 64-bit |
| Q8 Production database | Cloud MySQL can be aligned to the vessels' major version, and triggers are permitted. Otherwise the cloud uses the application-level capture fallback. |
| Q9 Catalog scope | Vessel-linked items only |
| Q10 Files | Ship files go up. Signatures come down. Procurement documents only if requested. |
| Q11 Updates | Technician or USB first |
| Q13 Client | Thin shell over the existing UI |
