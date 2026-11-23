# Design for ADR-003, ADR-004 and ADR-005

**Status:** design, not approved. It explains how the approved decisions in
[architecture-decisions.md](architecture-decisions.md) would be built. It
will change during implementation; the decisions will not.

**Date:** 2026-11-23.

**Covers:**

- ADR-003: vessel users create and update master data directly, and
  deletions synchronize safely; **amended by ADR-006: vessel users do not
  delete, retire, archive or deactivate records** (section 0);
- ADR-004: only a Superadmin blocks, unblocks or resets passwords, on board
  or on shore;
- ADR-005: backups on the vessel's master PC, vessel by vessel.

**Replaces:** [adr-002-master-data-proposals.md](adr-002-master-data-proposals.md),
which is superseded and must not be implemented.

**Labels used below:**

- **Approved:** your decision, as given on 2026-11-23.
- **Found:** what the application does today, from reading the code. Nothing
  was changed.
- **Recommended:** my proposal. It needs your confirmation where it touches
  a business rule; the open ones are listed in
  [pending-decisions.md](pending-decisions.md) (PD-64, PD-69, PD-70). PD-68, PD-71 and PD-72 were
  decided on 2026-11-23, and PD-73 on 2026-11-24.

---

## 0. Amendment: ADR-006 (2026-11-23)

ADR-006 withdrew vessel users' delete access. Read the rest of this design
with these changes; the sections below are corrected where they differed.

| Topic | Before ADR-006 | Now |
|---|---|---|
| Who deletes | Vessel users (own records, shared master data) and the office | **The office only**, under its existing permissions. Vessel users are refused every delete action, on the cloud and on board (built in Checkpoint 10) |
| Retirement by vessels | Shared master data retired on board | **None.** Retirement, archiving or deactivation is not a substitute for deletion |
| Where a deletion record starts | Both sides | The cloud. A vessel creates deletion records only for the three draft edits kept by PD-72 (decided), and for system work (for example PMS job intervals replaced on save) |
| A delete arriving from a vessel at sync | Applied, by rules | **Refused and flagged** (except the PD-72 draft edits and the child rows a save replaces) |
| Conflict rules (3.7) | Vessel delete against office edit, and the reverse | Only an office deletion against a vessel's change (PD-68 A, decided: the office's side stands, the change is kept and flagged) |
| Fleet-wide effect of a vessel retirement (PD-68 B) | Open | Not applicable (decided 2026-11-23) |

What stays: the deletion record, the audit, no loss on interruption, no
unsafe cascade, no resurrection (sections 3.2 to 3.6). Office deletions
travel down to vessels and need all of them.

---

## 1. What exists today (found)

### 1.1 Master-data lists, and who may change them

No crew module and no manufacturer module exist. These are the lists that
do exist, with the permission the server enforces today.

| List | Table | Create | Edit | Delete today | Sync rule today (off) |
|---|---|---|---|---|---|
| Budget groups | `budget_groups` | super-admin or any ship role (`HomeController::canManageBudgetGroups`) | same | Retire: `status = false` | Both ways, newest wins |
| Certificate categories | `certificate_categories` | fleet records admins or ship crew | same | Retire, refused while an active certificate uses it | Both ways, newest wins |
| Catalog items (including the free-text `manufacturer`) | `items` | any signed-in user (`storeItem`); ship users' import and manual add are scoped to their vessel (`ItemImportController`) | any signed-in user (`/item/update`, no role check, F14) | Retire: `status = false`; hard delete only in super-admin's trash | Both ways, newest wins, same `item_key` merged |
| Item groups | `item_groups` | created by a catalog import or manual add (`CatalogService::resolveGroupPath`) | no screen | no screen | Both ways, newest wins |
| Requisition categories | `categories` | the menu shows it to super-admin only; the server checks only sign-in (F21) | same | Retire; hard delete in the trash | Down only; blocked on board |
| PMS lists: ranks, locations, component categories, job types, conditions, manufacturers | `ranks`, `pms_locations`, `pms_component_categories`, `pms_job_types`, `pms_conditions`, `manufacturers` | super-admin only (`PmsAccess::authorizePmsMasterData`) | same | **Hard delete**, refused by the foreign keys while in use | Down only (manufacturers defined both ways); `pms/master/*` blocked on board |
| Currencies, survey types, ports | `currencies`, `surveys`, `ports` | no screen found (seeded or imported) | none | none | Down only; ports imported on each server |
| Vessels | `vessels` | super-admin | super-admin | Retire; hard delete in the trash | Down only; blocked on board |
| Users and roles | `users`, `roles` | the menu shows it to super-admin; **the server checks only sign-in** (F20) | same | Block: `status = false` on user and role; **no unblock action exists** | Users both ways (password only); roles down |

### 1.2 Manufacturer fields (PD-64)

There are exactly two:

1. **`items.manufacturer`**, a free-text column on catalog items, added in
   `2026_08_26_184702_add_catalog_fields_to_items_table.php`. It is part
   of the item row, so it travels with the item. It needs no separate sync
   rule and no screen.
2. **`manufacturers`**, one of the six lists on the PMS master-data screen
   (`PmsMasterDataController`, `/pms/master/manufacturers`). It is
   referenced by `pms_components.manufacturer_id` and by PMS parts
   (`manufacturer_id`, set to null if the manufacturer is deleted). Only
   super-admin maintains it, and the screen is blocked on board. It needs
   to travel **down** to vessels, so that components can name a
   manufacturer on board; that is already defined.

The PMS screen is a master-data screen, not a manufacturer module.

### 1.3 Operational records and how they are deleted

| Records | Delete today | Sync rule today (off) |
|---|---|---|
| Requisition lines in a draft | Hard delete (`RequisitionController`, lines 230 and 269) | Both ways; the vessel creates and deletes |
| Requisitions | Retire (status); hard delete only in super-admin's trash | Both ways, by stage |
| Stock movements, consumption | Never deleted; the ledger is append-only | Up, append-only |
| Equipment | Disabled, not deleted (`EquipmentController`) | Up |
| PMS components | Hard delete, refused while it has children or active job definitions; the CFM import removes components | Up |
| PMS readings, required parts | Hard delete | Up |
| PMS job definitions | Intervals and checklist lines replaced by hard delete | Up |
| Vessel certificates and surveys | Retire, own vessel only for ship roles; hard delete in the trash | Both ways, newest wins (certificates reviewed) |
| Attachments | Hard delete by the uploader, refused only while linked to a requisition item (F13) | Both ways, `@attachments` |

### 1.4 How sync treats deletes today

- A MySQL trigger writes each delete to the outbox in the same transaction
  as the delete, so an interrupted sync cannot lose it.
- Events are identified by UUID, and each side refuses an event it has
  already stored. A delete sent twice is applied once.
- A **retire** is an ordinary update of `status`, so it travels like any
  other change.
- **Gaps:**
  1. Once the outbox is pruned (14 days), nothing remembers that a row
     was deleted. A late update, or a restored backup, can bring it back.
  2. An update that meets a delete is detected only while the local
     delete is still waiting to be sent. With `latest`, a change can win
     over a delete and recreate the row.
  3. MySQL does not fire triggers for rows removed by `ON DELETE CASCADE`,
     so those deletions are never recorded or sent.
  4. The outbox does not record who deleted the row.

---

## 2. Three kinds of record (recommended classification)

This follows your instruction to distinguish them. Only the boundaries
between the kinds are mine; the permissions inside each are the existing
ones.

| Kind | Lists or records | Who may create, update, delete |
|---|---|---|
| **1. Master data maintained by vessels and the office together** | Budget groups, certificate categories, catalog items (with `manufacturer`), item groups | **Approved (ADR-003, ADR-006):** vessel users **create and update**, offline, at once, no approval, within the existing permissions in section 1.1. **Only the office deletes or retires**, as today. |
| **2. Vessel-owned operational records** | Requisitions while the vessel owns the stage, stock and consumption, equipment, PMS records, the vessel's certificates and surveys, attachments the vessel uploaded | **Approved (ADR-003, ADR-006):** the vessel's own users **create and update**, for **their own vessel only**, within existing permissions. They do not delete. The office deletes only where its existing permissions allow. |
| **3. Office-owned or shared records** | Users and roles; vessels; requisition categories; PMS lists (including manufacturers); currencies, ranks, survey types, ports; the office's stages of a requisition (approvals, procurement, invoices); office procurement files; every other vessel's records | The office, as today. A vessel **cannot** delete or change them. A change that still arrives from a vessel is refused and flagged. Users: ADR-004. |

Whether any kind 3 list should move to kind 1 (for example the PMS
manufacturers list) is PD-64.

---

## 3. Deletion model

**As built (Checkpoint 14, first part, 2026-11-23).** Sections 3.2, 3.3,
3.6 and 3.7 are built with these simplifications:

- The deletion record is written by the capture trigger in the same
  transaction as the delete, not by the application, so no delete path
  can miss it. A rolled-back delete leaves no record.
- It has no short-label column; the conflict record keeps the values.
- It is not synced. Each server writes its own when it applies a delete,
  with the same person and the origin node, and never prunes it.
- The person (users.sync_uuid) reaches the trigger in a session variable,
  `@sync_actor` on MySQL, set per web request by `SetSyncActor`, and
  travels with every sync event.
- Vessel deletes allowed at sync: `config/sync.php` `vessel_deletes`.
  Retirement columns: `retire_columns`.

### 3.1 Who removes what (ADR-006)

| Kind | On board | On the cloud |
|---|---|---|
| 1. Shared master data | **Nothing is deleted or retired** by anyone. | The office retires, or removes in the trash, under existing permissions. A permanent removal only when nothing references the record (PD-60, decided and built). |
| 2. Vessel operational records | **Nothing is deleted, retired or disabled.** The three draft edits stay (PD-72, decided). PMS job deactivation is refused (PD-72); the component "Inactive" switch is office only (PD-73, decided 2026-11-24): refused to vessel users on the screen, the server and at sync. Append-only ledgers are never deleted. | Vessel users: nothing. The office: only where existing permissions allow, and not for offline vessels' PMS or equipment (ADR-001; PD-06 decided and built). A wrong entry is corrected by editing, not removed (PD-72 B, decided: no action needed). |
| 3. Office or shared records | Not deletable. | As today, with the trash guard (PD-60, decided and built). |

Item groups and PMS lists have no `status` column. Item groups have no
delete screen. The PMS lists are kind 3, so their hard delete stays
office-only.

### 3.2 The deletion record (tombstone)

One new, additive table, recommended name `sync_deletions`:

| Column | Meaning |
|---|---|
| `table`, `sync_uuid` | Which record |
| `kind` | `retire` or `remove` |
| `label` | A short readable copy, for example the name or number, so the history still reads after a removal |
| `deleted_by` | The user's `sync_uuid` |
| `deleted_at` | The time, on the server where it happened |
| `origin` | `cloud` or the vessel id |
| `restored_at`, `restored_by` | Set if a retired record is brought back |

- It is written **in the same transaction** as the delete or retire. So it
  is either there with the delete, or neither happened.
- It syncs like any append-only table: to the cloud, and from the cloud to
  the vessel that owns the record (for kind 1, to every vessel).
- It is **never pruned** with the outbox. It is the lasting memory that a
  record was deleted, and the audit of who and when.

### 3.3 Who deleted it (technical question)

The triggers cannot see the signed-in user. Two ways to capture the actor:

1. The application sets a MySQL session variable (for example
   `@navisuite_actor`) at the start of each request, and the trigger copies
   it into the outbox event and the deletion record.
2. The application writes the deletion record itself, in model events,
   inside the same transaction.

**Recommended:** option 2 for the deletion record, because it can also hold
the label. Option 1 is worth adding later for every change. Both need a
test on MySQL 5.7 and 8 (PD-07).

### 3.4 Interrupted sync (approved requirement, design)

- The delete and its outbox event commit together (built), and so does the
  deletion record (3.2).
- Batches are idempotent (built). A batch cut off half way is sent again,
  and nothing is applied twice.
- So a deletion is lost only if the master PC is lost before it syncs.
  That is the backup question (section 6).

### 3.5 Referenced records and cascades

1. **A record that others still use is never removed.** When the office
   deletes kind 1 or kind 2 records, it retires them instead, or the
   removal is refused while anything references them, as the PMS
   components and certificate categories already do. The trash guard
   applies this to permanent deletes (PD-60, decided and built in
   Checkpoint 10).
2. **No silent cascades.** Before any module is switched on, every
   `ON DELETE CASCADE` key reachable from a deletable record is listed
   (PD-36). For each one:
   - the children are deleted by the application first, so that each gets
     its own outbox event and deletion record; or
   - the parent's removal is refused.
3. **The receiving side refuses a delete that would cascade into rows it
   owns**, for example a cloud delete of an item that would take a vessel's
   ledger with it. The refusal is flagged.
4. **Set-to-null keys** (`nullOnDelete`, for example PMS parts to
   manufacturers) are allowed for kind 3 office deletes, but the cleared
   references are listed in the deletion record, so they can be repaired.

### 3.6 A deleted record must not come back

Rules applied on both sides when an event arrives:

| Arrives | The record is | Result |
|---|---|---|
| Update | removed (deletion record exists) | **Refused.** Kept in the conflict record, flagged. The row is not recreated. |
| Insert with the same `sync_uuid` | removed | Refused, flagged. |
| Insert with a new `sync_uuid` and the same name or key | removed | Allowed. It is a new record, not a resurrection. |
| Update | retired | Applied to the other columns. `status` stays retired unless the update is a restore. |
| Delete | already removed | Ignored. Not a conflict. |

After a vessel restores an older backup (section 6), its deletion records
are pulled again from the cloud, and the removals in them are applied, so
the restore cannot bring back what was deleted after the backup.

### 3.7 Conflicts: an office deletion meeting a vessel's change (PD-68 A, decided)

Since ADR-006 a deletion starts only in the office. PD-68 A was decided
on 2026-11-23 (option 1): the office retirement remains effective, the
vessel's offline edit never restores or reactivates the record, and the
edit is kept in the conflict history and flagged for review. These rules
are built (Checkpoint 14). The removal row applies the same rule to a
full deletion, as you confirmed on 2026-11-23.

| Case | Result |
|---|---|
| Kind 1: the office retires, a vessel edits other fields | Both apply: the entry stays retired, with the vessel's values. Flagged for information. |
| Kind 1: the office retires, a vessel uses it in a new record | The entry stays retired; the new record keeps its reference. Not a conflict. |
| Kind 1 or 2: the office removes, a vessel edits | The removal stands. The vessel's change is kept in the conflict record, flagged. The row is not recreated. |
| Any delete or retirement arriving **from** a vessel | Refused, flagged (ADR-006). |
| Deleted twice (the same office delete sent again) | Applied once. Not a conflict. |

"Flagged" means it appears on the office review screen (PD-35) with both
versions and both users.

---

## 4. Master data without approval (ADR-003)

- **Approved:** vessel users create and update kind 1 entries offline, and
  use them at once. They do not delete or retire them (ADR-006). There is
  no pending state, no proposal table, no review queue, no approval role
  and no change to requisition approvals.
- **Sync rules (recommended):** budget groups, certificate categories,
  items and item groups travel both ways, as already defined, plus:
  - the deletion record (3.2);
  - the rules in 3.6 and 3.7;
  - the flags in PD-59 A for newest-wins outcomes on shared data.
- **Same entry created on two sides while apart (PD-18):**
  - items with the same `item_key` are merged, as built;
  - exact name duplicates in the other lists are refused today and wait
    for a person. **Recommended:** merge exact matches (same name, ignoring
    case and spaces) automatically, and flag them; never merge similar
    names.
- **Fleet-wide effect (PD-68 B):** not applicable since ADR-006 (decided 2026-11-23). Only the
  office retires entries, and a retirement applies to the whole fleet, as
  on the cloud today.

---

## 5. User administration (ADR-004)

### 5.1 Approved rules

- Only a Superadmin blocks, unblocks, or changes or resets another
  user's password.
- The master PC, the master and other vessel roles get no such power.
- A Superadmin on board can do it offline; it is saved locally and synced
  up. A shore Superadmin's change syncs down.
- Who and when is kept. Conflicts are handled safely. Passwords never
  appear in logs or ordinary sync records.

### 5.2 Found

- `/user/store`, `/user/update` and `/user/delete` check only that someone
  is signed in. On the cloud **today**, any signed-in user can post to
  them, and so set another user's password, role or block (F20). The menu
  hides them from everyone but super-admin.
- "Delete user" is a block: `status = false` on the user and the role.
  There is no unblock action.
- `/change/password` lets every user change their own password, if they
  know the current one. It works on board too.
- On board, only this vessel's crew may sign in
  (`AppMode::userMayUseThisServer`). Shore users' password hashes are
  replaced before they are sent to vessels. So no Superadmin can sign in on
  board today, and `user/*` is blocked there.

### 5.3 Recommended design

1. **Server-side check.** `user/store`, `user/update` and `user/delete`
   require super-admin, on the cloud and on board. For block, unblock and
   password this is approved. For creating users and changing roles it
   is approved too (PD-71 A, 2026-11-23), and built in Checkpoint 10.
2. **Unblock.** A new action, beside the existing block, that sets
   `status = true` on the user and the role. It is part of the approved
   rule, not a new module.
3. **Superadmin on board.**
   - Super-admin accounts' real password hashes are sent to vessels. Every
     other shore user's hash stays withheld.
   - `userMayUseThisServer` admits super-admin.
   - On board, a super-admin may block, unblock and reset passwords. Which
     other office powers they have on board is PD-70; until you decide,
     everything else that is blocked on board stays blocked.
4. **What travels.**
   - `users.password` and `users.status`, and `roles.status`, both ways.
   - Every block, unblock and reset writes one row to a new additive table,
     recommended name `user_admin_events`: the user, the action, the
     Superadmin, the time, the server. It never contains a password or
     hash. It syncs both ways and is never pruned.
   - **Guard:** a server accepts a password or status change for another
     user from the other side only together with a matching
     `user_admin_events` row by a super-admin. Otherwise it is refused and
     flagged. A user's own password change is accepted only if PD-69 keeps
     it.
5. **Conflicts (recommended, PD-11):**
   - Block on one side, unblock on the other: the block wins, flagged. It is
     the safe outcome; a Superadmin can unblock again.
   - A Superadmin reset against a user's own change: the reset wins,
     flagged.
   - Two Superadmin resets: the newest wins, flagged.
   - A blocked user cannot sign in with any password (built).
6. **Credential secrecy:**
   - the hash is sent only inside the `users` row event, over HTTPS with
     the node token (built);
   - it is kept out of `sync_conflicts`, `sync_logs` and `sync_row_bases`
     (PD-37 item 2); a conflict shows "password changed", not the value;
   - outbox and inbox rows that carry it are pruned on the normal schedule;
   - no log line ever contains it (a test checks this).
7. **Sessions.** When a block arrives, the user's open sessions end
   (`EndRevokedSessions`, built).

### 5.4 Risks to accept or reduce

- **Super-admin hashes on every master PC.** Anyone who takes a master PC
  can try to guess a super-admin password offline. To reduce this:
  - long super-admin passwords;
  - bcrypt cost 12 (PD-46);
  - only named super-admin accounts sent (PD-70);
  - disk encryption (BitLocker) on the master PC, recommended.
- **Locked out at sea.** The master can no longer reset a password. A crew
  member who forgets it waits for a Superadmin, on board or by the next
  sync.

---

## 6. Backups on the master PC, vessel by vessel (ADR-005)

### 6.1 Why it is vessel by vessel already

Each master PC holds one vessel's server: that vessel's records and the
shared lists. So every backup taken on it is one vessel's backup, and
restoring it touches no other vessel. The cloud is not restored; sync
brings the vessel and the cloud back into step afterwards.

### 6.2 What a backup set contains

| Part | Content | Today |
|---|---|---|
| Database | A consistent dump (`--single-transaction`) of every table, including the sync tables: outbox, inbox, cursors, `sync_state`, row bases, aliases, conflicts, and the new `sync_deletions` and `user_admin_events` | Built (`backup-database.ps1`) |
| Files | Attachments, certificate copies, photos, signatures, in a content store with a SHA-256 manifest | Built (`backup-files.ps1`) |
| Identity and configuration | Vessel id, node id, cloud address, app mode, enabled modules, application version, backup time | **Recommended addition:** a small set manifest. The `.env` itself is not copied, because it can hold secrets. |
| Secrets | The sync token, database passwords and app key, protected by Windows DPAPI | Not portable by design. A restore on the same PC keeps them; on a new PC new ones are issued ([disaster-recovery.md](disaster-recovery.md) section 6) |
| Sync position | The last event sent and received, so the restore report can say what may be missing | **Recommended addition**, in the set manifest |

**Recommended:** one **set manifest** per night that names the database
file, the file manifest, their checksums and the identity block, so the set
is checked and restored as one.

### 6.3 Checking a backup

- **Checksums.** Every file in the set, and the set manifest, have SHA-256
  checksums. `backup-files.ps1 -Verify` checks the files today. The
  database zip needs the same check.
- **Test restore** (recommended). A weekly job loads the newest database
  dump into a scratch database on the same PC, compares row counts, and
  drops it.
- **Reporting.** `sync:health` and the office dashboard show the age of the
  last good backup and of the last good test restore.

### 6.4 Restore

The procedure in [disaster-recovery.md](disaster-recovery.md) sections 5 and
6 stays. It gains three steps:

1. Check the set manifest before restoring anything.
2. After the restore, pull the deletion records and user administration
   events again from the cloud, and apply them (section 3.6).
3. On a new PC, register a new node identity and revoke the old one (built).

**Approved:** a restore is tested before the pilot (Checkpoint 21).

### 6.5 Risks of keeping backups only on the master PC

Your decision is that no external copy is required. Please accept these
risks knowingly:

| Event | What is lost |
|---|---|
| Disk failure, fire, flood, theft, ransomware, or Windows reinstalled over the disk | The live data **and every backup**, together |
| The same, after a recent sync | Only the changes since the last successful sync, and files not yet uploaded. Everything else is rebuilt from the cloud (Checkpoint 20). Today one-way tables, such as stock and PMS, cannot be rebuilt; the rebuild fixes that |
| The same, after weeks without a link | Everything since the last successful sync |
| The PC is stolen | Unencrypted backups and the super-admin hashes on it can be read (5.4) |

**What reduces the risk without external storage (recommended):**

- **The cloud is the off-PC copy.** Frequent sync whenever there is a
  link, and an office alert when a vessel has not synced for a set time
  (PD-35).
- **The rebuild from the cloud** (Checkpoint 20).
- Optional: a second internal disk in the master PC for the backup
  folder, or a mirrored pair of disks. It is still on the master PC, and
  helps against a single disk failure only.
- Optional: BitLocker on the master PC, against theft.
- `backup-files.ps1 -Export` stays available but is not required.

---

## 7. Tests (summary)

The roadmap lists the tests per checkpoint. Together they cover:

- every delete type, for each kind, in both directions, interrupted at each
  step;
- a retired or removed record that never comes back: late update, late
  insert, outbox pruned, backup restored;
- every case in the conflict table (3.7), in both orders, with skewed
  clocks;
- cascade refusal on MySQL for every key listed under PD-36;
- the actor and time in every deletion record;
- user administration: each action by each role on each side, the guard,
  the conflicts, the session end, no hash in any log, conflict or row base;
- backup: the set manifest, a damaged file found, a full restore drill, and
  the deletion records re-applied after it.
