# Checkpoint 5: Cloud-Vessel Synchronisation Engine

**Status:** Implemented and tested on SQLite and MySQL 8. Not yet run between a real cloud and a real vessel over a satellite link. Business modules are **not** enabled: by default only three reference lists travel (currencies, ranks, categories), and only cloud to vessel.

This document is the specification of sync protocol version 1, and the operating guide for the engine. It builds on the capture layer of [Checkpoint 2](checkpoint-2-sync-foundation.md) (triggers, outbox, inbox, nodes, cursors) and runs on the vessel server of [Checkpoint 3](checkpoint-3-windows-vessel-server.md).

---

## 1. What this checkpoint delivers

| Requirement | Where |
|---|---|
| Transactional outbox and inbox | Capture triggers write `sync_outbox` in the same transaction as the business change (Checkpoint 2). Received changes are recorded in `sync_inbox` and applied in the same transaction (section 7). |
| Secure vessel authentication | One node id and secret token per vessel master PC, hashed on the cloud, sent as a bearer token over HTTPS; body checksums; rate limits (section 12). |
| Push API | `POST /api/sync/v1/push` (section 4.4). |
| Pull API | `GET /api/sync/v1/pull` (section 4.5). |
| Incremental sync with cursors | Every event gets a gap-free stream position (`seq`) when it is committed; each side keeps a cursor per direction (section 5). |
| Acknowledgements | A push is acknowledged by its answer; a pull page by the next request and by `POST /ack` (sections 4.4 to 4.6). |
| Idempotency | Batch level (stored answer per batch id) and event level (unique event id in the inbox) (section 7.2). |
| Retry and exponential backoff | 30 s doubling to 30 minutes, with jitter; schedule kept in the database (section 9). |
| Transaction-safe processing | One database transaction per batch or page, one savepoint per event (section 7.1). |
| Conflict detection | Both sides changed a row before seeing the other's change; rule per table; every conflict recorded (section 8). |
| Monitoring and logs | `sync_logs`, `sync_conflicts`, `storage/logs/sync.log`, `sync:status`, `/health` (section 11). |
| Background worker | `php artisan sync:work`, run by the Windows service NaviSuite-Sync (section 9). |
| Recovery after an internet outage or Windows reboot | Nothing is lost or applied twice; tested scenario by scenario (section 10). |
| The vessel works without internet | Changes are captured locally and wait in the outbox; nothing on board depends on the link. |
| Reusable infrastructure, not every module | Tables travel only with an explicit rule **and** when listed in `SYNC_TABLES` (section 3). |

---

## 2. Architecture

```
 Vessel master PC (APP_MODE=vessel)                         Cloud (APP_MODE=cloud)
 ----------------------------------                         ----------------------
 business write ──trigger──> sync_outbox                    sync_outbox <──trigger── business write
                                 │ OutboxSequencer: seq             │ OutboxSequencer: seq
 NaviSuite-Sync (sync:work)      v                                  │
   SyncWorker ─> SyncAgent  ── handshake ───────────────>  SyncServer (/api/sync/v1)
                   │         ── push  batch (stored) ────>   InboxProcessor ─> ChangeApplier ─> tables
                   │         <─ answer (stored on cloud) ─   sync_batches 'in', sync_inbox
                   │         ── pull  ?after=cursor ─────>   ChangePackager (rows' current state)
                   │         <─ page of events ──────────
                   v
             InboxProcessor ─> ChangeApplier ─> tables
             sync_inbox, cursors, sync_conflicts, sync_logs
```

* **The vessel always initiates.** The cloud never connects to a ship; it answers. A ship behind satellite NAT or a firewall only needs outbound HTTPS.
* **State, not history.** An event says "this row changed". The sender reads the row's current values when it packages the event, so ten edits to one row since the last sync travel as one change (section 6).
* **UUIDs, not ids.** Rows are identified by `sync_uuid`; foreign keys travel as the referenced row's UUID and are turned back into local ids on arrival. The cloud's and the vessel's auto-increment ids never need to match.

---

## 3. What travels

A table is synchronised only when **both** are true:

1. it has a replication rule in `config/sync.php` (`replication`), and
2. it is listed in `SYNC_TABLES` in `.env` (default `currencies,ranks,categories`).

`SYNC_TABLES` must list the same tables on the cloud and on every vessel. Each handshake also tells the vessel which tables the cloud sends and accepts, and the vessel only sends what the cloud accepts.

### 3.1 Rules

| Key | Meaning |
|---|---|
| `direction` | `down` (cloud to vessels), `up` (vessel to cloud) or `both`. |
| `conflict` | For `both`: `cloud`, `vessel` or `latest` (newer `updated_at` wins, a tie goes to the cloud). For one-way tables the owner always wins. |
| `references` | Foreign-key columns and the table they point to. Sent as UUIDs. |
| `natural_key` | Columns that identify a row besides its UUID (for example `code`). A different UUID with the same natural key is a conflict for review, never merged silently. |
| `local_columns` | Columns never sent and never overwritten. |

Rows of a vessel-scoped table (one with a vessel expression in the Checkpoint 2 registry, such as `vessel_surveys`) go only to their own vessel. Rows with no vessel (fleet-wide lists) go to every vessel.

### 3.2 Rules shipped

| Table | Direction | Conflict | Enabled by default |
|---|---|---|---|
| currencies, ranks, categories | down | cloud | yes |
| manufacturers, budget_groups, certificate_categories, item_groups | both | latest | no |
| vessel_surveys | both | latest | no (question Q3 of the roadmap is still open) |

Requisitions, orders, stock, PMS, users and roles have **no rule**. They are captured (so nothing is lost when they are enabled later) but never sent. Each needs its own agreed rules first (section 15).

### 3.3 Enabling a table

1. Agree the direction and conflict rule (and natural key) with the business owner.
2. Add or check the rule in `config/sync.php`; `php artisan sync:status` reports rule errors.
3. Deploy the release with the rule to the cloud **and** the vessels.
4. Add the table to `SYNC_TABLES` on the cloud and on every vessel, then `php artisan config:cache`.
5. Changes made before the table was enabled were passed over by the cursors. Send the existing rows once:
   * on the vessel, for tables it sends up: `php artisan sync:resend <table> --force`
   * on the cloud, for tables it sends down: `php artisan sync:resend <table> --force`, or `--vessel=<id>` for one vessel's rows.

   Sending a row the other side already has is harmless: it is recognised as unchanged.

---

## 4. Protocol version 1

### 4.1 Transport

* HTTPS to `SYNC_CLOUD_URL` + `/api/sync/v1/...`. Plain HTTP is refused by the vessel outside tests.
* Request bodies are JSON, gzip-compressed (`Content-Encoding: gzip`). Responses over 1 KB are gzip-compressed when the request says `Accept-Encoding: gzip`.
* Timeouts: 20 s to connect, 120 s per request.
* No cookies, sessions or CSRF.

### 4.2 Headers (every request)

| Header | Value |
|---|---|
| `X-Sync-Protocol` | `1` |
| `X-Sync-Node` | The vessel's node id (`SYNC_NODE_ID`). |
| `Authorization` | `Bearer <SYNC_NODE_TOKEN>` |
| `X-Sync-Body-SHA256` | POST only: hex SHA-256 of the **uncompressed** body. A damaged or cut-short upload is refused whole. |

### 4.3 `POST /handshake`

Opens every cycle. Request:

```json
{ "protocol": 1, "node_id": "…", "vessel_id": 12, "app_release": "2026.11.20", "schema": "<last migration>", "time": "2026-11-20T08:00:00.000000+00:00" }
```

Answer:

```json
{
  "protocol": 1,
  "cloud_node_id": "…",
  "vessel_id": 12,
  "server_time": "2026-11-20T08:00:01.234000+00:00",
  "tables": { "down": ["categories", "currencies", "ranks"], "up": [] },
  "cursors": { "pull": 4810, "push": 977 },
  "schema": "<cloud's last migration>",
  "schema_matches": true,
  "limits": { "max_events": 500, "max_bytes": 2097152 }
}
```

The vessel stops the cycle, without sending anything, when:

* `vessel_id` is not its own `APP_VESSEL_ID` (a token copied to the wrong ship), or
* `cloud_node_id` differs from the one it synced with before (a wrong `SYNC_CLOUD_URL`, or a replaced cloud: section 11.4).

It also measures the clock difference to the cloud and logs a warning above 120 s, because the `latest` conflict rule compares times.

### 4.4 `POST /push`

Sends the vessel's changes, oldest first, in batches of at most 500 events and 2 MB.

```json
{
  "protocol": 1,
  "batch_id": "6c0d…",
  "seq_from": 978,
  "seq_to": 1012,
  "events": [ { "event_id": "…", "seq": 980, "table": "manufacturers", "row_uuid": "…", "op": "update", "version": 4, "vessel_id": null, "values": { "name": "MAN Energy Solutions", "updated_at": "2026-11-20 09:00:00" } } ]
}
```

`seq_from` and `seq_to` are the vessel's stream positions the batch covers; events inside may be fewer, because changes that do not travel are skipped (section 6).

Answer (stored by the cloud with the batch):

```json
{
  "protocol": 1, "batch_id": "6c0d…", "status": "applied", "cursor": 1012,
  "counts": { "applied": 30, "unchanged": 2, "took_remote": 1, "kept_local": 0, "skipped": 0, "duplicate": 0, "deferred": 1, "rejected": 1 },
  "rejected": [ { "event_id": "…", "reason": "This vessel_surveys row belongs to vessel 8, not to vessel 12." } ],
  "conflicts": [ { "event_id": "…", "resolution": "vessel_won", "conflict_id": 41 } ],
  "retried": { "applied": 1, "deferred": 0, "failed": 0 }
}
```

* **Acknowledgement.** The answer is the acknowledgement. The vessel then marks the batch acknowledged and moves its push cursor to `cursor`.
* **Resending.** The vessel stores each batch (`sync_batches`, direction `out`) **before** sending it, and sends exactly the same body again until it gets an answer, also after a reboot. The cloud answers a batch id it has seen with the stored answer and `"repeated": true`, without applying anything again.
* **Rejected events** are refused one by one and do not stop the batch (section 7.1). They are recorded on the cloud with the reason, and the vessel logs them as a warning.

### 4.5 `GET /pull?after=<cursor>&limit=<n>`

Fetches the next page of cloud changes for this vessel: fleet-wide rows and this vessel's own rows, of the tables the cloud sends down, not counting changes that came from this vessel.

```json
{ "protocol": 1, "after": 4810, "cursor": 4890, "has_more": true, "high_water": 5203, "events": [ … same event shape … ] }
```

* Asking for a page with `after=N` **acknowledges everything up to N**: the cloud moves this vessel's pull cursor to N.
* The vessel applies the whole page and moves its own cursor to `cursor` in **one** transaction, then asks again while `has_more` is true (up to 50 pages per cycle).
* `after` beyond the end of the cloud's stream is answered with 409: the cloud has lost changes the vessel already received, usually because it was restored from a backup (section 11.4).

### 4.6 `POST /ack`

```json
{ "stream": "pull", "cursor": 5203 }
```

Sent once at the end of a pull so the cloud knows at once how far the vessel got (section 11.2 "Behind"), instead of at the next cycle. Best effort: a lost ack is harmless, because the next pull acknowledges the same position.

### 4.7 Status codes

| Code | Meaning | Vessel's reaction |
|---|---|---|
| 200 | Done. | Continue. |
| 400 | Wrong `X-Sync-Protocol`, body not gzip, cut short, checksum mismatch, or not JSON. | Cycle fails; retried with backoff. |
| 401 | Unknown node, wrong or revoked token. | Cycle fails; an operator must fix the token (since Checkpoint 7: `navisuite:secrets set sync_node_token`; see [disaster-recovery.md](disaster-recovery.md) section 7). |
| 403 | The node is not a vessel node. | As 401. |
| 404 | Not a cloud server (the API does not exist in vessel mode). | As 401: check `SYNC_CLOUD_URL`. |
| 409 | Position beyond the end of the stream (cloud restored), or a batch id already used by another node. | Cycle fails; see section 11.4. |
| 413 | Body over 32 MB after decompression. | Cycle fails. |
| 422 | Malformed batch, query or acknowledgement. | Cycle fails. |
| 429 | More than 10 failed sign-ins a minute from this address, or more than 300 requests a minute from this node. | Retried with backoff. |
| 500 | Unexpected error on the cloud; nothing was applied (the batch transaction rolled back). Logged in the cloud's `sync.log`. | Retried with backoff. |
| 503 | The cloud has no `SYNC_NODE_ID` yet. | Retried with backoff. |

---

## 5. Stream positions and cursors

Outbox ids are taken when a row is inserted, but transactions commit in a different order. If cursors used ids, a long transaction holding id 7 could commit after id 8 was sent, and id 7 would be skipped for ever.

Instead, `OutboxSequencer` gives each event its position `seq` once it is **committed**:

* one process at a time (MySQL `GET_LOCK`), reading with READ COMMITTED so uncommitted events are invisible and the capture triggers are never blocked;
* it refuses to run inside a database transaction, where it could see that transaction's own uncommitted events;
* an event that commits late gets a **higher** number than everything already numbered, so no cursor can have passed it. This is tested on real MySQL with two connections.

The last number given out is also kept in `sync_state` (`outbox.high_water`). `sync:prune` deletes old events; without this, the numbering would restart below every cursor once the outbox was emptied.

Cursors (`sync_cursors`, Checkpoint 2) only move forward:

| Where | Node | Stream | Meaning |
|---|---|---|---|
| Vessel | cloud | `push` | Vessel events up to here were acknowledged by the cloud. |
| Vessel | cloud | `pull` | Cloud events up to here were applied on board. |
| Cloud | vessel | `push` | Last batch position received from this vessel. |
| Cloud | vessel | `pull` | This vessel has acknowledged cloud events up to here. |

A new vessel node starts its pull cursor at the cloud's current end of stream, because its database is a copy of the cloud's at that moment (bootstrap, roadmap checkpoint 3).

---

## 6. Events and packaging

`ChangePackager` turns a range of outbox events into a page:

* Events are read in `seq` order. All events for one row within the page become **one** event at the position of the first, carrying the row's **current** values. A row that no longer exists is sent as a delete.
* Excluded: tables not sent in this direction, and events that came from the receiver (no echo).
* `values` holds every column except `id`, `sync_uuid`, `sync_version` and `local_columns`. Foreign keys in `references` are replaced by the referenced row's UUID. A reference to a row that does not exist is reported in the log (`dangling_references`) and left out of `values`, so the receiver keeps its own value (until Checkpoint 6 it was sent as null).
* A page is at most 500 events and 2 MB. If it is larger, it is cut and the rest follows.
* `version` is the row's `sync_version`. On arrival, a row keeps the higher of its own and the incoming version.

Pages that contain only events which do not travel just move the cursor on.

---

## 7. Applying changes

### 7.1 Transactions

* **Cloud, push:** one transaction per batch: the `sync_batches` row (inserted first, so a second copy of the same batch waits for the first and then gets its stored answer), every event, the retry of waiting events, the stored answer, and the cursor. A crash anywhere rolls all of it back, and the vessel resends the batch.
* **Vessel, pull:** one transaction per page: every event and the pull cursor.
* **Each event** is applied in a savepoint. An event that is refused (wrong vessel, table not accepted, malformed, or a natural-key duplicate) is rolled back alone, recorded as rejected, and the rest of the batch continues. Lost connections, deadlocks and lock timeouts are not treated as a bad event: they fail the whole batch, which is then retried.
* Applied changes are written with the sender as their origin (Checkpoint 2 `SyncContext::asOrigin`), so the capture triggers do not send them back.

### 7.2 Idempotency

| Level | Mechanism |
|---|---|
| Batch | `sync_batches` is unique on (`batch_id`, direction). A repeated batch gets the stored answer. A batch id used by another node is refused (409). |
| Event | `sync_inbox.event_id` is unique. An event already received (for example in another batch) is counted as `duplicate` and not applied again. |
| Row | Identical values are not written (`unchanged`), so a resend or a repeated page does not bump versions or create conflicts. |
| Cursor | Cursors only move forward. |

### 7.3 Changes that must wait

A change that refers to a row the receiver does not have yet (for example a child item group whose parent arrives later) is kept as `deferred` and retried after every batch or page and every cycle. After 50 attempts it is marked `failed` for an operator (`sync:retry --failed` once the cause is fixed). If a newer change for the same row arrives while an older one waits, the older one is marked `skipped`, so it cannot overwrite the newer one later.

### 7.4 Results per event

| Result | Meaning |
|---|---|
| applied | Written. |
| unchanged | Already identical. |
| took_remote | Conflict; the incoming change won. |
| kept_local | Conflict; the receiver's own change won. |
| skipped | Superseded or not applicable. |
| duplicate | Event already received. |
| deferred | Waiting for a referenced row. |
| rejected | Refused, with the reason. |

---

## 8. Conflicts

### 8.1 Detection

There is a conflict when the receiver has a change of its own to the same row that the sender **has not seen yet**:

* on the cloud: an event for the row not from this vessel, not yet acknowledged by this vessel's pull cursor (only for tables the cloud sends down: changes to tables that only go up cannot conflict);
* on the vessel: a local event for the row not yet acknowledged by the cloud (only for tables the vessel sends up).

No version vectors are needed: the cursors already say what the other side has seen.

### 8.2 Resolution

| Situation | `cloud` rule | `vessel` rule | `latest` rule |
|---|---|---|---|
| Both changed the row | Cloud's values | Vessel's values | Newer `updated_at`; a tie goes to the cloud |
| One side deleted, the other changed | Cloud's outcome (its delete, or its change) | Vessel's outcome | The change wins: the row is restored with the same UUID. A delete never beats a change. |
| Both deleted | Not a conflict | Not a conflict | Not a conflict |
| Both made the same change | Not a conflict | Not a conflict | Not a conflict |

Same natural key with a different UUID (two people created "Wartsila"), under any rule: both rows stay as they are on their own side, the incoming one is rejected, and a `duplicate_key` conflict needing review is recorded on both sides.

Every conflict is recorded in `sync_conflicts` with both versions of the values, the rule and the outcome. The side that did not decide gets a copy of the outcome (the push answer lists them). After the losing side's next sync, both servers hold the same row.

`php artisan sync:conflicts` lists the conflicts needing review; `--all` shows resolved ones; `--reviewed=<id>` marks one as reviewed.

---

## 9. The worker

`php artisan sync:work` is the NaviSuite-Sync Windows service on the vessel. Every 5 seconds it records a heartbeat and checks whether a cycle is due.

| Situation | Next cycle |
|---|---|
| Success | After `SYNC_INTERVAL` (300 s). After 5 s if a direction hit 50 pages (a backlog). |
| Failure n in a row | 30 s × 2^(n-1): 30 s, 1, 2, 4, 8, 16 minutes, then every 30 minutes, ±20 % random, so a fleet does not retry in step after an outage. |
| "Sync now" (`sync:run --background`) | At the next check. |
| Another cycle running | Skipped; a lock (`navisuite:sync-cycle`) prevents overlap with `sync:run`. |
| `SYNC_CLOUD_URL` empty | Idle: heartbeat only, nothing logged. |

The schedule, failure count, last error and last summary are kept in `sync_state` (`worker`), so a restart or reboot continues the same back-off instead of hammering the link. Being offline is logged as a warning, not an error. `php artisan sync:run` runs one cycle in the foreground and prints what moved.

---

## 10. Failure and recovery

Every scenario below is an automated test against two complete servers (cloud and vessel, each with its own database, the real migrations, triggers, HTTP routes, authentication, compression and checksums).

| Scenario | What happens | Test |
|---|---|---|
| No internet for days | Work is saved on board; failed cycles back off; everything arrives when the link returns. | `SyncFlowTest::test_the_vessel_works_offline_…` |
| Push answer lost | The vessel resends the stored batch; the cloud answers from its record; applied once. | `SyncReliabilityTest::test_a_batch_whose_answer_was_lost_…` |
| Same event in another batch | Recognised by event id, not applied twice. | `…test_an_event_delivered_again_in_another_batch_…` |
| Same batch id, other content | The stored answer is returned; the new content is ignored. | `…test_the_same_batch_id_gets_the_same_answer_…` |
| Pull answer lost | The page is fetched again from the same cursor and applied once. | `…test_a_page_whose_answer_was_lost_…` |
| Link fails between push and pull | The push stays done; the pull resumes next cycle. | `…test_the_link_failing_between_push_and_pull_…` |
| Windows reboot with a batch in flight | The stored batch is sent unchanged after the restart. | `…test_a_batch_created_before_a_reboot_…` |
| Cloud crashes in the middle of a batch | Nothing of the batch remains; the resend applies all of it. | `…test_a_cloud_crash_part_way_through_a_batch_…` |
| Vessel crashes while applying a page | Nothing of the page remains; the cursor did not move; fetched again. | `…test_a_vessel_crash_while_applying_a_page_…` |
| One bad change in a batch | Refused alone with a reason; the others are applied. | `…test_one_bad_change_does_not_stop_the_rest_…` |
| Child arrives before its parent | Waits, then is applied when the parent arrives. | `…test_a_change_that_arrives_before_the_record_…` |
| Parent never arrives | Marked failed after the maximum attempts; `sync:retry --failed` after fixing. | `…test_a_change_that_keeps_waiting_…`, `SyncMaintenanceTest::test_a_change_that_gave_up_…` |
| A long transaction commits late | Numbered after the events already sent; not skipped. | `SyncReliabilityTest::test_an_event_that_commits_late_is_still_delivered`, `MySqlCaptureTest::test_the_sequencer_numbers_a_late_commit_…` |
| Outbox pruned to empty | Numbering continues; nothing is skipped or refused. | `SyncMaintenanceTest::test_numbering_carries_on_after_everything_was_pruned` |
| Cloud replaced / restored from a backup | Sync stops with a clear error; `sync:reset-cloud` recovers. | `SyncMaintenanceTest::test_a_replaced_cloud_…`, `…test_a_cloud_restored_from_a_backup_…` |

---

## 11. Operations

### 11.1 Commands

| Command | Where | Purpose |
|---|---|---|
| `sync:status` | both | Identity, capture, rules, backlog, worker state (vessel), lag per vessel (cloud). Fails on configuration errors. |
| `sync:work` | vessel | The background worker (NaviSuite-Sync). `--once` for one check. |
| `sync:run [--background]` | vessel | Sync now. |
| `sync:conflicts [--all] [--reviewed=ID]` | both | Conflicts needing review. |
| `sync:retry [--failed]` | both | Apply waiting (and failed) received changes again. |
| `sync:resend <table> [--vessel=ID] --force` | both | Send every row of a table again (section 3.3). |
| `sync:prune [--dry-run]` | both | Delete old bookkeeping. Scheduled daily at 03:30 once `SYNC_NODE_ID` is set. |
| `sync:register-node vessel --vessel=ID --name=…` | cloud | Create a vessel's id and token (printed once). `--replace` for a replaced master PC. |
| `sync:revoke-node <id>` | cloud | Withdraw a node's access. |
| `sync:reset-cloud [--force]` | vessel | Start over with a replaced or restored cloud (section 11.4). |

### 11.2 Monitoring

* **`/health`** on a vessel includes a non-critical `sync` check once `SYNC_CLOUD_URL` is set: configuration problems, the service heartbeat (stale after 180 s), seconds since the last successful sync, failures in a row and the last error. Being offline at sea does not fail the check; a monitor decides from `last_success_age_seconds`.
* **`check-health.ps1`** shows the same, plus whether the CA bundle is in place.
* **`sync:status`** on the cloud lists each vessel with its last contact, pull position and how many stream positions it is **behind**.

### 11.3 Logs

| Log | Contents | Kept |
|---|---|---|
| `sync_logs` table | One row per handshake, push, pull, cycle, reset: counts, bytes, duration, message. Warnings for rejected events, conflicts, clock skew, dangling references. | 90 days |
| `storage/logs/sync.log` | Errors with detail (cloud 500s, failed cycles other than offline). | 30 days |
| `sync_conflicts` | Every conflict. | Kept |
| `sync_inbox` | Every received event and its outcome. Applied and skipped ones are pruned; failed and waiting ones are kept. | 30 days |
| `sync_batches` | Every batch sent or received, with the stored answer. | 30 days |
| `data\logs\sync` (Windows) | Console output of the NaviSuite-Sync service. | 5 × 10 MB |

The token is never logged or stored in clear (tested).

### 11.4 Cloud replaced or restored from a backup

Symptoms on the vessel (`sync:status`, `/health`, `sync_logs`):

* *"The cloud's identity changed from … to …"*: the cloud now has another `SYNC_NODE_ID`. First check `SYNC_CLOUD_URL` points at the right server. If the cloud really was rebuilt, continue below.
* *409 "The vessel is at position N, but the cloud's stream ends at M. Was the cloud database restored from a backup?"*: the cloud lost changes the vessel had already received.

Recovery, on the vessel master PC (elevated prompt):

```
C:\NaviSuite\artisan.cmd sync:reset-cloud
```

It shows what it is about to forget, asks, then deletes only this vessel's sync bookkeeping about the cloud (cursors, remembered identity) and asks the service to sync. The next cycle sends every change still in the vessel's outbox and fetches every change still in the cloud's. Rows the other side already has are recognised as unchanged; nothing is duplicated.

Changes older than the outbox retention (14 days) are no longer in the outbox. If the cloud lost more than that, also run `sync:resend <table> --force` on the vessel for the tables it sends up, and on the cloud `sync:resend <table> --vessel=<id> --force` for the tables it sends down.

If the cloud got a new identity, every vessel needs this once. A vessel whose node was not in the restored cloud database must also be registered again (`sync:register-node … --replace`) and given the new id and token.

### 11.5 Retention

`config/sync.php` `retention`: outbox 14 days (and only once every reader has passed an event), inbox 30, batches 30, logs 90 days. Waiting and failed received changes and conflicts are never pruned.

---

## 12. Security

| Threat | Protection |
|---|---|
| Someone calls the API without a token | 401. Node id and token are both required; the cloud stores only a SHA-256 hash of the 64-character token and compares in constant time. |
| Guessing tokens | 10 failed attempts a minute per address, then 429 for everyone from that address. |
| A lost or replaced master PC | `sync:revoke-node`, or `sync:register-node … --replace`, makes the old token useless at once (tested). |
| A vessel token used by another vessel's server | The handshake says which vessel the token belongs to; the vessel stops if it is not its own. |
| A compromised vessel trying to change other vessels' data | Every incoming row of a vessel-scoped table must belong to the sending vessel, before and after the change: no taking over, no moving a row to another vessel (tested). |
| A vessel changing office reference data | Tables the cloud does not accept from vessels are refused (tested). |
| Tampered or damaged upload | SHA-256 of the body must match; gzip must be complete. |
| Oversized or "zip bomb" upload | Inflated in steps and refused past 32 MB (413). |
| Interception | HTTPS only. The vessel checks the cloud's certificate against the Mozilla CA bundle (`config\php\cacert.pem`) or `SYNC_CA_BUNDLE`. |
| Calling the API on a vessel server | It does not exist there (404). |
| Flooding | 300 requests a minute per node. |
| Replay of a captured request | A replayed push gets the stored answer and changes nothing. A replayed pull only reads what the vessel may read anyway. |

---

## 13. Deployment

### 13.1 Cloud

1. Deploy this release and run `php artisan migrate` (adds the tables in section 14; no existing table changes).
2. If not done in Checkpoint 2: `php artisan sync:register-node cloud`, put the printed id in `SYNC_NODE_ID`, run `php artisan sync:triggers install`.
3. Leave `SYNC_TABLES` at its default unless a module has been agreed (section 3.3). `php artisan config:cache`.
4. HTTPS must reach Laravel with the `Authorization` header. The repository's `.htaccess` already passes it on Apache; on nginx or a load balancer check it is not dropped.
5. For each vessel: `php artisan sync:register-node vessel --vessel=<id> --name="<PC name>"`. Copy the printed id and token to that vessel. The token is shown only once.

### 13.2 Vessel

The master PC must already be a copy of the cloud database (vessel data bootstrap, roadmap checkpoint 3, **not yet built**; see section 15).

```
powershell -ExecutionPolicy Bypass -File .\deploy\windows\install-server.ps1 -VesselId 12 -RuntimeSource D:\navisuite-runtime `
    -SyncNodeId <id> -SyncNodeToken <token> -SyncCloudUrl https://<cloud address>
```

* Put `cacert.pem` (from https://curl.se/ca/cacert.pem) in the runtime folder. The installer copies it to `config\php\cacert.pem` and enables it in `php.ini`. Without it, PHP on Windows cannot check the cloud's certificate.
* NaviSuite-Sync is installed with the other services, as its own virtual account, restarting after failures, starting with Windows (delayed). `update-server.ps1` adds it to servers installed by an earlier release.
* `check-health.ps1` then shows the sync check and the CA bundle.

---

## 14. Database impact

One new migration, `2026_11_23_100000_create_sync_engine_tables.php`. No existing migration is modified and no business table changes.

| Change | Detail |
|---|---|
| `sync_outbox.seq` | New nullable, unique column: the stream position. Existing events are numbered on the first sync. |
| `sync_inbox.batch_id` | New nullable column: the batch an event arrived in. |
| `sync_batches` | New: every batch sent or received, with its stored answer. |
| `sync_conflicts` | New: every conflict, with both versions. |
| `sync_state` | New: small key-value store (worker schedule, cloud identity, last handshake, outbox high-water mark). |

`down()` removes all of it (tested on MySQL, as part of rolling back all sync migrations). The sync tables are excluded from capture.

---

## 15. Open issues and questions

### 15.1 Blocking real use

1. **Vessel bootstrap is not built** (roadmap checkpoint 3). Sync assumes the vessel starts as a copy of the cloud with the same UUIDs. Until then a vessel can only be commissioned by hand, and its node must be registered **before** the database copy is taken, so the pull cursor starts at the right place.
2. **No business module is enabled.** Each needs rules agreed (questions below), then enabling and testing.
3. **Not yet run end to end on Windows** against a real cloud over a slow link.

### 15.2 Decisions needed

| # | Question | Assumed until answered |
|---|---|---|
| Q1 | Which modules to enable first, in which direction? | Reference lists down only. |
| Q2 | Conflict rule per module (office wins, ship wins, latest wins, or review)? | `latest` for co-authored lists. |
| Q3 | Users and passwords: may a password changed on board travel to the cloud, and the other way? Roles? | Users not synced. |
| Q4 | Are the master PCs' clocks trustworthy (synchronised with GPS or the internet)? `latest` depends on them. | Yes, warned above 120 s difference. |
| Q5 | Retention: how long must the cloud keep received batches and inbox events for audit? | 30 days (90 may be better on the cloud). |
| Q6 | One cloud server or several behind a load balancer? Rate limits and the sequencer lock assume one database; several web servers are fine. | One database. |
| Q7 | Where does the Windows CA bundle come from, and who updates it? | Mozilla bundle in the runtime folder, refreshed with each runtime update. |

### 15.3 Known limitations

* **Owner wins on one-way tables without a record.** A vessel's local edit to a `down` table is overwritten by the next cloud change to that row, and a cloud edit to an `up` table by the next vessel change. One-way tables should not be edited on the other side; the application does not prevent it yet.
* **Natural-key duplicates need a person.** Two servers creating "Wartsila" keep separate rows; the second is refused and listed for review. Merging them (pointing references at one UUID) is a manual job; a merge tool is not built.
* **`latest` trusts the PCs' clocks.**
* **Moving a row between vessels** is refused from a vessel; the cloud can do it, but the old vessel keeps its copy until a cleanup rule exists.
* **Cascading deletes** done by the database (foreign keys with `ON DELETE CASCADE`) are captured by triggers only where MySQL fires them, which it does not for cascades. Tables with cascades need care when enabled.
* **A stored batch the cloud refuses with 422** (for example after the cloud lowered its batch limit) is resent every cycle. Keep `sync.batch` the same on both sides.
* **Attachments and files** are not synchronised (roadmap section 5.6).

---

## 16. Tests

All sync tests run in the normal suite on SQLite; the MySQL tests run with `SYNC_MYSQL_TEST_DATABASE=<name>_test` (and `_PORT`, `_USERNAME`, `_PASSWORD`) against a local server.

| File | Tests | Covers |
|---|---|---|
| `tests/Feature/Sync/Engine/SyncFlowTest.php` | 9 | Both directions, no echo, foreign keys as UUIDs, coalescing, offline work, opt-in tables, one-way tables, vessel scoping, large backlogs. |
| `tests/Feature/Sync/Engine/SyncReliabilityTest.php` | 13 | Duplicates, lost answers, interrupted links, reboots, crashes, partial failure, waiting changes, late commits. |
| `tests/Feature/Sync/Engine/SyncConflictTest.php` | 11 | Each conflict rule, deletes against changes, conflicts found on board, natural-key duplicates, convergence after many concurrent edits. |
| `tests/Feature/Sync/Engine/SyncSecurityTest.php` | 19 | Authentication, revocation, rate limit, protocol and body checks, vessel scope, identity checks. |
| `tests/Feature/Sync/Engine/SyncWorkerTest.php` | 12 | Back-off, jitter, schedule across restarts, sync now, overlap lock, idle mode, commands, health. |
| `tests/Feature/Sync/Engine/SyncMaintenanceTest.php` | 12 | Prune, retry, resend, registration cursor, status, recovery from a replaced or restored cloud, rule check. |
| `tests/Feature/Sync/MySqlCaptureTest.php` | +2 | Sequencer on MySQL: late commit, refusal inside a transaction. |
| `tests/Feature/VesselServer/BackgroundServicesTest.php` | +1 | Prune scheduled only once sync is set up; the cloud's schedule otherwise unchanged. |

The two-server harness is `tests/Concerns/SyncEngineHarness.php`.

---

## 17. Files

**New:** `app/Sync/Engine/*` (sequencer, packager, codec, applier, inbox processor, server, client, agent, worker, maintenance, rules, sides, exceptions), `app/Sync/Models/SyncBatch.php`, `app/Sync/Models/SyncConflict.php`, `app/Sync/SyncState.php`, `app/Http/Controllers/Sync/SyncApiController.php`, `app/Http/Middleware/AuthenticateSyncNode.php`, `routes/sync.php`, the commands `sync:work`, `sync:run`, `sync:prune`, `sync:retry`, `sync:resend`, `sync:conflicts`, `sync:reset-cloud`, the migration in section 14, `deploy/windows/templates/NaviSuite-Sync.xml`, the tests in section 16.

**Changed:** `config/sync.php`, `config/logging.php` (`sync` channel), `config/navisuite.php` (`health.sync_max_age`), `bootstrap/app.php` (routes), `app/Providers/SyncServiceProvider.php` (rate limit), `app/Support/HealthCheck.php`, `app/Support/Heartbeat.php`, `app/Console/VesselSchedule.php`, `app/Console/Commands/SyncStatus.php`, `app/Console/Commands/SyncRegisterNode.php`, `deploy/windows/*` (service, CA bundle, `-SyncCloudUrl`), `.env.example`, `.env.vessel.example`.
