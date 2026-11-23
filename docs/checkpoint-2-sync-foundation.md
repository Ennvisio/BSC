# Checkpoint 2: Sync Foundation in the Database

**Status:** Implemented on branch `feature/offline-sync`, awaiting review. No sync API exists yet, and no data moves between servers.
**Date:** 2026-11-20
**Plan:** [offline-implementation-roadmap.md](offline-implementation-roadmap.md), checkpoint 2. Builds on [checkpoint-1-app-mode.md](checkpoint-1-app-mode.md).

---

## 1. What this checkpoint delivers

The database can now identify every synchronised row globally and record every change reliably. It also has the tables the sync API will need.

| Requirement | Delivered as |
|---|---|
| Globally unique UUIDs | `sync_uuid` on 53 tables, unique. Every existing row was given one. The 3 PMS tables already had UUID keys and reuse them. |
| Vessel identification | Each change records the vessel it belongs to, found directly or through its parent records. Each server has a node id, and each vessel server is registered against its vessel. |
| Sync metadata | `sync_version` on all 56 synchronised tables. The registry records each table's ownership class. |
| Transactional outbox | `sync_outbox`, written by database triggers in the same transaction as the change |
| Inbox with unique event ids | `sync_inbox`, with a unique `event_id` and a service that stores each event exactly once |
| Sync cursors | `sync_cursors`, which only move forward |
| Device registration | `sync_nodes`, with hashed tokens, replacement and revocation |
| Sync logs | `sync_logs` and a small logger |

Existing primary keys, existing data and existing application code are unchanged. The work is two new migrations, one config file, the new `app/Sync` module, five commands and tests.

## 2. Relationship analysis

The full schema was built from all 94 migrations on a scratch MySQL server and inspected before anything was changed.

- **59 tables.** 56 are synchronised. `migrations`, `password_resets` and `ports` are excluded, and the reasons are recorded in [config/sync.php](../config/sync.php). `ports` holds the 100,000-row UN/LOCODE list. It is used only for search autocomplete, and each server imports it itself.
- **Every table has a single-column primary key.** 53 are auto-increment integers. `pms_components`, `pms_job_definitions` and `pms_jobs` use UUIDs.
- **Vessel ownership.** 19 tables have their own `vessel_id`, and `vessels` is its own vessel. 20 reach a vessel through one or two parent tables, such as order items through orders, or attachment links through procurement steps and orders. `users` and `attachments` reach it through `roles`. 14 tables are fleet-wide reference data.
- **Natural keys** exist for most reference tables, such as `items.item_key`, `categories.symbol` and `currencies.code`. They will be used when two sides create the same reference record independently (section 8).
- **Foreign keys** are mostly declared on the newer tables and mostly missing on the older ones. Several cascade on delete.

The analysis is captured in the registry in `config/sync.php`. It lists each table's ownership class from the audit, the column holding its global id, and the SQL that finds its vessel. When run against MySQL, the test suite fails if any table is neither synchronised nor excluded.

One correction to the audit: budget groups, certificate categories and catalog items are written by crew today. They are classed as co-authored (B) rather than cloud-owned (A).

## 3. How change capture works

Each synchronised table gets database triggers. Triggers were chosen because at least 16 write paths bypass Eloquent (audit R2), and they all go through the database.

| Event | What the triggers do |
|---|---|
| Insert | Assign `sync_uuid` if none was given. Record an `insert` event. |
| Update | Keep `sync_uuid` unchanged even if the code tries to change it. Raise `sync_version` by one. Record an `update` event. |
| Delete | Record a `delete` event with the deleted row's UUID and vessel. |

Each event holds:
- A unique event id.
- The table, the local row id and the row UUID.
- The operation and the row version.
- The vessel id, or null for fleet-wide data.
- The origin node.
- A microsecond timestamp.

The event is written by the same statement as the change, so the two commit or roll back together. A rolled-back transaction or savepoint leaves no event behind.

Two session switches exist for later checkpoints, both in `App\Sync\SyncContext`:
- **Capture off** records nothing and does not raise versions. It is used for maintenance, such as filling missing UUIDs or loading a snapshot.
- **Origin** marks changes applied from another node. They keep the sender's version and carry its node id, so they are never sent back to it.

MySQL is the production engine and its triggers read session variables. SQLite versions of the same triggers exist only so the normal test suite exercises real capture.

## 4. Database impact

| Change | Detail | Reversible |
|---|---|---|
| New tables | `sync_nodes`, `sync_outbox`, `sync_inbox`, `sync_cursors`, `sync_logs`. All start empty. | Yes. Rollback drops them and the triggers. |
| New column `sync_uuid CHAR(36)` + unique index | On 53 tables. Each existing row gets a UUID, 5,000 rows per statement. | Yes. Rollback drops the triggers, the index and the column. |
| New column `sync_version INT UNSIGNED DEFAULT 1` | On all 56 synchronised tables | Yes |
| Triggers | 277 in total: 5 per table, 4 on the UUID-keyed tables. Installed by command, not by migration (section 5). | `sync:triggers drop` |
| Existing keys, columns and values | Unchanged. Tests compare every row before and after, and after rollback. | Not applicable |

On a local MySQL with 12,000 rows in one table, migrating all 56 tables took about one second. The cloud will take longer in proportion to its data, and each table is rebuilt once.

With triggers installed, every insert, update and delete writes one extra outbox row. Child tables also run one small lookup to find their vessel. Bulk catalog imports will roughly double their write count.

## 5. Deployment steps (cloud)

1. Take a full `mysqldump` backup.
2. Run `php artisan migrate`. This adds the tables and columns and needs only the usual ALTER privilege.
3. Run `php artisan sync:status`. It must report no tables without sync columns.
4. **Install the triggers later, just before the first vessel is commissioned (Checkpoint 3).** Run `php artisan sync:triggers install`. Nothing reads the outbox before then, so installing earlier only grows it. Installing is all or nothing: if MySQL refuses one trigger, every trigger is removed again and the command explains why.
5. Register the cloud itself with `php artisan sync:register-node cloud`, and put the printed `SYNC_NODE_ID` in the cloud `.env`.

A vessel is registered on the cloud with `php artisan sync:register-node vessel --vessel=<id>`. The printed id and token go into that vessel's `.env`.

## 6. Commands

| Command | Purpose |
|---|---|
| `sync:status` | Read-only. Shows node identity, tables, capture coverage, outbox and inbox counts, cursors and nodes. Exits with failure when something is wrong. |
| `sync:triggers install\|drop\|status` | Manages the capture triggers. Rerun `install` after any migration that renames or drops a column the triggers use. |
| `sync:backfill-uuids` | Gives a UUID to rows written while the triggers were absent. Records nothing and can be run any number of times. |
| `sync:register-node cloud\|vessel [--vessel=] [--name=] [--replace]` | Cloud only. Prints the node id and token once. Only a SHA-256 hash of the token is stored. `--replace` retires a vessel's current node, for example after a PC swap. |
| `sync:revoke-node {node}` | Cloud only. The token stops working immediately. |

## 7. Tests

There are 64 new tests: 56 on SQLite, which run in every test run, and 8 on MySQL.

| Area | What is proven |
|---|---|
| UUID uniqueness | 6,200 existing rows in one table and every row in other tables get distinct, valid UUIDs across several batches. 300 rows in one statement on SQLite and 1,000 on MySQL get distinct UUIDs. The database refuses a repeated UUID. A row's UUID cannot be changed. Repeating the migration does not replace UUIDs. |
| Event creation | Eloquent and query-builder inserts, updates and deletes each produce one event with the right table, row, UUID, version and vessel. This covers vessels found through parents, uploader roles and UUID-keyed PMS tables. A real master approval request records its approval event. |
| Transaction rollback | Rolled-back transactions, savepoints and updates leave no rows, no events and no version changes. Committed work keeps its events. |
| Duplicate prevention | The inbox stores an event once, including when the id differs only in letter case or a batch contains it twice. The database itself refuses a repeated id in both the inbox and the outbox. A duplicate inside a larger transaction does not undo the rest. A malformed batch stores nothing. |
| Other | Capture off, origin and state restoration after errors. Installing twice adds no duplicate triggers. Dropping triggers stops capture without blocking writes. Cursors, node registration, tokens, revocation, logs and all commands are covered. |
| MySQL only | Every table in the full schema is registered or excluded. All 56 tables are captured. Session variables are reset after use. Rollback removes the triggers and writes keep working, then migrating forward again restores everything. |

To run the MySQL tests, point them at a local database whose name ends in `_test`. The test refuses any other database, because it rebuilds it.

```
SYNC_MYSQL_TEST_DATABASE=navisuite_sync_test SYNC_MYSQL_TEST_PORT=3306 vendor/bin/phpunit tests/Feature/Sync
```

Full suite: 313 tests. 296 pass, the 8 MySQL tests skip without a server, and the same 9 tests fail as before Checkpoint 1. With the MySQL settings, all 8 MySQL tests pass. They were run against MySQL 9.3 on a throwaway local server.

## 8. Changes from the roadmap

- **The outbox is called `sync_outbox`, not `sync_change_log`.** Batches will be built from it in Checkpoint 4, so no separate batch table was created yet. `sync_logs` replaces `sync_runs`.
- **Node tokens are stored as SHA-256 hashes in `sync_nodes`, instead of using Laravel Sanctum.** Nodes are not users, and this avoids a new dependency. The API checkpoint can still adopt Sanctum if you prefer.
- **UUIDs are random.** MySQL's `UUID()` is time-and-node based. The roadmap suggested name-based UUIDs for natural keys. That is unnecessary for existing rows, because each vessel starts from a copy of the cloud with the same UUIDs. Independently created duplicates will be matched by natural key when changes are applied.
- **Triggers are installed by a command, not by a migration.** A refused privilege then cannot leave a migration half-applied on the cloud.

## 9. Open issues

1. **The production MySQL version and TRIGGER privilege are still unknown (Q8).** Testing used MySQL 9.3. The SQL used is valid on 5.7.2 and later. With binary logging on, MySQL may also require SUPER or `log_bin_trust_function_creators=1`. If the cloud host refuses triggers, the roadmap's application-level fallback would be needed for the cloud only.
2. **Cascading foreign keys do not fire triggers on MySQL.** Deleting a parent removes or nulls its children without events. Applying the parent's delete on the other side cascades the same way, because both sides share the schema. Checkpoints 5 and 6 must rely on this, and must not expect child delete events. SQLite does fire them, so the two engines differ here.
3. **Outbox event ids can commit out of order** when transactions overlap. The pull API in Checkpoint 4 must read with a short safety delay or detect gaps, or a cursor could skip an event.
4. **User rows are only roughly routed.** A new user's event is written before their role exists, so it looks fleet-wide. Checkpoint 4 must decide routing when changes are packaged, so other vessels never receive a crew member's password hash.
5. **There is no outbox pruning yet.** It arrives with cursors in Checkpoint 4. This is the reason to delay installing the cloud triggers until Checkpoint 3.
6. **Future migrations need a sync check.** A new table must be added to `config/sync.php`, and the MySQL test catches one that is missing. Renaming or dropping a column used in a vessel lookup requires rerunning `sync:triggers install`.
7. **`ServiceRequisitionApproval` accepts mass assignment of any column,** so a crafted request could supply its own `sync_uuid`. The database still refuses a duplicate, and the value never changes after insert.
8. **Still open from Checkpoint 0:** the cloud exposure check (R10), the 9 failing tests, local copies of the CDN libraries, and public registration.
