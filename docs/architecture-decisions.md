# NaviSuite offline-first: architecture decisions

This file records approved architecture decisions. Each one is binding on
later work. A later decision can replace an earlier one, but only by a new
entry; entries are not edited after approval. Only an entry's **Status** row
and its status notes are updated, to show what replaced it.

Open questions are kept in [pending-decisions.md](pending-decisions.md).

---

## ADR-001. Operating model: vessel authority, office administration, cloud hub

| | |
|---|---|
| **Status** | Approved |
| **Approved** | 2026-11-22, by the project owner |
| **Affects** | [pending-decisions.md](pending-decisions.md): PD-03, 05, 06, 08, 10 to 14, 17, 18, 21, 27, 34, 35, 38, and the new PD-58 and PD-59 |

### Decision

The approved operating model, as given:

1. The vessel is the primary authority for its own operational data.
2. Vessel users can create requisitions, manage onboard inventory and stock
   transactions, and record PMS activities.
3. The office manages central master data, user administration and the
   required requisition approvals.
4. The cloud is the central synchronization hub between the office and the
   vessels.
5. The vessel must keep operating independently when there is no internet
   connection.
6. When the connection returns, authorized data synchronizes between the
   vessel and the cloud.
7. Data ownership and synchronization rules must stop unauthorized office
   or vessel changes from silently overwriting the authoritative data.

### What this decision does not settle

The decision sets the principles. It does not approve any detailed
permission or conflict rule. These still need your answer, item by item,
in [pending-decisions.md](pending-decisions.md):

- **Which lists count as "central master data"** (PD-58). Crew create and
  edit budget groups, certificate categories, manufacturers, item groups
  and catalog items today.
- **The exact line between "operational data" and office data** for
  certificates and surveys (PD-17), the equipment list (PD-06), and users'
  own photos, signatures and passwords (PD-11, PD-21).
- **Which approvals are "required"** (PD-06, PD-13). Office PMS job
  approval exists today, but the decision lists only requisition approvals.
- **Who wins when principle 1 and principle 3 meet** on the same record: an
  office rejection against a ship action (PD-13), or a quantity changed by
  both (PD-38).
- **Which automatic conflict outcomes must be shown to a person** (PD-59),
  and where they are shown (PD-35).

### Consequences for the design

- A vessel's operational records (requisitions it raises, stock, stock
  movements and consumption, PMS records) are written on board. The cloud
  copy is a mirror, not a second place to edit them.
- Central master data and user records are written in the office. A
  vessel receives them; it does not change them, except where a later
  decision allows an exception.
- Requisitions move between the two sides by stage. Each stage's columns
  belong to the side that acts at that stage. This is already how the
  Checkpoint 6 rules work.
- Every place where the non-owning side can still write must either refuse
  the write, or send it through a conflict that a person reviews. "Owner
  wins without telling anyone" is not acceptable under principle 7.
- Anything that stops a vessel working offline is a blocker (principle 5).
  That covers the libraries loaded from CDNs (PD-03), the missing vessel
  bootstrap (PD-08), and password reset by email (PD-11).

### Status notes

These notes record what happened after approval. They do not change the
decision above.

- **2026-11-23.** The project owner confirmed ADR-001 again, and added
  Clarification A below (the synchronization network).
- **2026-11-23.** PD-35 (sync monitoring for the office) was made a
  requirement for the first pilot. The owner also set directions for PD-13
  (an office rejection must not be silently overridden) and PD-59 (documents
  must not silently replace one another). See
  [pending-decisions.md](pending-decisions.md).
- **2026-11-23.** The conformance review and the list of changes it needs
  moved to their own documents, because they will change as fixes land,
  while this record must not:
  - [adr-001-conformance-review.md](adr-001-conformance-review.md)
    (findings F1 to F19);
  - [implementation-plan.md](implementation-plan.md) (the ordered plan).
- Items affected since approval, besides those in the table above: PD-36,
  PD-60, PD-61 and PD-62.
- **2026-11-23.** The ordered plan was consolidated, with both reviews and
  ADR-002, into [implementation-roadmap.md](implementation-roadmap.md)
  (Checkpoints 9 to 24). It replaces implementation-plan.md.
- **2026-11-23.** ADR-002 settled the first open point above (which lists
  are central master data, PD-58) for five lists, by letting vessels
  propose new entries for office approval.
- **2026-11-23, final clarifications.** The project owner made three further
  decisions, recorded below as ADR-003, ADR-004 and ADR-005. ADR-001 stays
  in force; they refine it:
  - **Principle 3, master data.** ADR-003 replaces ADR-002. Vessel users
    create, update and delete the master-data lists they already maintain
    (budget groups, certificate categories, catalog items, item groups)
    directly, with no office approval. The office still maintains the
    other lists (users, vessels, requisition categories, PMS lists).
  - **Principle 3, user administration.** ADR-004: blocking, unblocking and
    password resets are done only by a Superadmin, who may do them on board
    as well as on shore.
  - **Principle 3, requisition approvals.** Unchanged.
  - **Principles 1 and 7.** ADR-003 adds that deletions synchronize safely,
    are audited, and never bring a record back. ADR-005 sets backups.
  - The first open point above (PD-58) is now settled by ADR-003.
- **2026-11-23, deletion policy.** ADR-006 amends ADR-003: vessel users
  create and update, but do not delete, retire, archive or deactivate
  records. Principle 1 (the vessel is the authority for its operational
  data) stands for creating and changing those records; removing them is
  an office action under existing office permissions. Principle 7 (no
  silent overwrites) and the ledger rule are unchanged.
- **2026-11-23, PD-06, PD-60, PD-61 and PD-62 decided.** The owner
  approved the recommended option of each. They settle part of the open
  points above, and are built in Checkpoint 10:
  - **PD-06 (option 1).** Office PMS job approval stays a required
    approval. The office copy of an offline vessel's equipment list is
    read-only.
  - **PD-60 (option 1).** The trash refuses a permanent delete while other
    records refer to the record, and for any record of a vessel that runs
    its own server; the record stays retired in the trash. At sync, a
    vessel refuses a delete of its own vessel record, or one that would
    cascade into rows it holds.
  - **PD-61 (options 2 and 3).** An attachment that is the record of a
    procurement stage or a consumption is kept; a removed file is hidden,
    not deleted.
  - **PD-62 (option 1).** A replaced certificate copy is kept as history.
  The PD-17 line (certificates and surveys) and PD-11 and PD-21 stay open.

### Clarification A. Synchronization network (2026-11-23)

Requested by the project owner. It states how principles 4 to 6 work on
the network. It matches what is built; no code change is needed.

1. **The vessel starts every connection.** It connects outbound, over
   HTTPS on port 443, to the cloud's sync API (`/api/sync/v1`, and
   `/api/sync/v1/files` for files). It signs each request with its own node
   token.
2. **The cloud never connects to a vessel server.** A vessel server needs
   no inbound port from the internet, no public address, no port forwarding
   and no VPN. Satellite NAT and ship firewalls are therefore no obstacle.
   The cloud only answers requests.
3. **The vessel pushes, then pulls, on a schedule.** In each cycle it
   first sends its own waiting changes, then pulls what the cloud holds for
   it. The interval is `SYNC_INTERVAL`, 300 seconds by default. "Authorized"
   means only:
   - rows that belong to that vessel, or that the whole fleet shares, in
     the modules switched on;
   - with other users' password hashes withheld;
   - office files only when they are linked to one of that vessel's
     records.

   Files travel after the rows, within a per-cycle budget.
4. **Offline work continues locally.** Crew keep working on the vessel
   server over the LAN. Every change is written to the local outbox in the
   same database transaction, so nothing is lost while the link is down.
5. **Sync resumes by itself.** After a failure the worker retries after 30
   seconds, doubling each time up to 30 minutes, with a random spread of 20
   percent. So, after a long outage, the first successful cycle can take up
   to about 30 minutes once the link returns. An operator can start one at
   once with `php artisan sync:run` (there is no button yet). Changes are
   sent in order. A batch sent twice is answered from the cloud's record
   and never applied twice.

**What follows from this:**

- The office learns about a vessel only when the vessel calls in. So
  "connectivity" on the cloud means the time of the vessel's last contact.
  This is what the PD-35 monitoring must show.
- An office change, such as an approval, reaches the vessel at the
  vessel's next successful cycle, not at once.
- The only internet requirement on board is outbound HTTPS to the cloud.
  A proxy, if there is one, must allow it (CP4 12.4).

---

## ADR-002. Vessels propose new master data; the office approves

| | |
|---|---|
| **Status** | **Superseded by ADR-003** on 2026-11-23. Kept for history; not to be implemented. |
| **Approved** | 2026-11-23, by the project owner |
| **Refines** | ADR-001 principle 3 (the office manages central master data) and principle 7 (no silent overwrites) |
| **Resolves** | [pending-decisions.md](pending-decisions.md) PD-58 |
| **Design** | [adr-002-master-data-proposals.md](adr-002-master-data-proposals.md) (not part of the decision; it will change during implementation) |

### Decision

Vessels can propose new master-data entries, pending office review.

**This applies to:**

- budget groups;
- certificate categories;
- manufacturers;
- item groups;
- catalog items.

**Approved rules, as given:**

1. Vessel users can create new master-data entries while working offline.
2. These new entries must be marked as `Pending Office Approval`.
3. When internet connectivity returns, pending entries synchronize to the
   cloud.
4. Authorized office users can review and approve or reject each proposal.
5. Existing office-managed master-data records must not be silently
   modified or deleted by vessel users.
6. Approved entries become part of the centrally managed master data and
   synchronize to the relevant vessels.
7. The original vessel ID, creator and creation timestamp are preserved for
   audit purposes.

### What this decision does not settle

These are open in [pending-decisions.md](pending-decisions.md):

- **Use before approval.** Can a pending entry be used at once in requisitions,
  stock and certificates on board? (PD-63)
- **Crew on the cloud.** Does the pending rule also cover crew creating entries
  on the cloud, as crew of vessels without their own server do today?
  (PD-64)
- **Manufacturers on board.** No on-board screen creates them today, so
  which screen would propose one? (PD-64)
- **Review rules** (PD-65):
  - which office roles approve each list;
  - how exact duplicates are handled;
  - how a rejected entry that is already in use is handled;
  - how a bulk catalog import is reviewed.
- **The vessel's own pending entries.** May a vessel edit or withdraw an entry
  of its own while it is still pending? (PD-66)
- **Which vessels are "relevant"** for approved catalog items: the catalog
  scope question, PD-19.

### Consequences for the design

- A vessel can create entries in these five lists, but cannot change or
  retire an office-managed record. On board, the screens that edit or
  retire existing records are blocked for these lists. A change that still
  reaches the cloud is refused and flagged, never applied or dropped
  without a trace.
- A pending entry stays with the proposing vessel and the cloud. Other
  vessels receive it only once it is approved.
- Every office decision is recorded, with who decided and when, and is
  sent back to the proposing vessel.
- The automatic merging of catalog items with the same `item_key`
  (Checkpoint 6) becomes a suggestion that the office confirms, because
  rule 4 gives every proposal to an office user.

### Status notes

- **2026-11-23.** Superseded by ADR-003. The project owner decided that
  vessel users create, update and delete master data without office
  approval. The proposal design,
  [adr-002-master-data-proposals.md](adr-002-master-data-proposals.md), is
  withdrawn. Rule 7 (keep the vessel, creator and time) carries over into
  ADR-003 as part of its audit rule.

---

## ADR-003. Vessel users maintain master data and delete their own records directly

| | |
|---|---|
| **Status** | Approved; **amended by ADR-006** on 2026-11-23: vessel users do not delete (decision points 4 and 5 as they apply to vessel users are withdrawn) |
| **Approved** | 2026-11-23, by the project owner |
| **Supersedes** | ADR-002 |
| **Refines** | ADR-001 principle 3 (master data) and principle 7 (no silent overwrites) |
| **Resolves** | [pending-decisions.md](pending-decisions.md) PD-58, PD-63, PD-65; withdraws PD-66 |
| **Design** | [adr-003-to-005-design.md](adr-003-to-005-design.md) sections 1 to 4 (not part of the decision) |

### Decision

As given:

1. **Vessel users can create and update master-data entries without office
   approval.** This covers the master-data lists that already exist in the
   application, such as budget groups, certificate categories, item groups
   and catalog items, and other applicable existing lists.
2. There is **no** "Pending Office Approval" state, no proposal review
   screen, no office approval queue and no new master-data approval role.
3. Vessel users can use their changes immediately, including offline.
   Changes synchronize to the cloud when connectivity returns.
4. **Vessel users can delete** applicable master-data entries and their own
   vessel's operational records, subject to the existing application
   permissions. Deletions work offline and synchronize when connectivity
   returns. There is no office approval for vessel deletions.
5. Deletion must be synchronization-safe:
   - represented by a deletion event or tombstone;
   - received and applied by the cloud when the vessel reconnects;
   - not lost if synchronization is interrupted;
   - safe for records that other records reference, with no accidental
     cascading deletion of unrelated or dependent records;
   - audited: who deleted the record, and when;
   - soft deletion or archiving where physical deletion would break data
     integrity or audit history;
   - a deleted record must not reappear unexpectedly through sync;
   - conflicts handled when the same record is updated or deleted on the
     other side before sync.
6. Shared and office-owned records keep their ownership. Vessel users do
   not delete records that belong to other vessels or to the office.
7. The existing requisition approval workflow is unchanged.
8. No crew management or manufacturer management module is added.

### What this decision does not settle

Open in [pending-decisions.md](pending-decisions.md):

- **Which existing lists are "applicable"** beyond the four named: in
  particular the PMS lists, which include the manufacturers list (PD-64).
- ~~**The conflict rule** when a record is deleted on one side and changed
  on the other, and the fleet-wide effect of a vessel retiring a shared
  entry (PD-68).~~ Decided 2026-11-23; see the status notes below.
- **Server-side permission checks** that the code lacks today, for catalog
  items, requisition categories and user creation (PD-71).
- **Automatic merging** of the same entry created on two sides (PD-18).

### Consequences for the design

- Records fall into three kinds: master data that vessels and the office
  maintain together; vessel-owned operational records; office-owned or
  shared records. Only the first two can be changed or deleted from a
  vessel.
- On board, shared master data is **retired**, not physically removed,
  because a vessel cannot see other vessels' references. Physical removal
  stays an office action, refused while anything references the record.
- Every deletion writes a lasting deletion record (the tombstone), with the
  user, time and origin, in the same transaction as the delete.
- An update or insert that arrives for a removed record is refused and
  flagged; it never recreates the row.
- Deletes that would cascade through foreign keys are refused, or the
  children are deleted explicitly so that each deletion is recorded.
- The ADR-002 proposal table, review screen, routing and alias mapping
  are not built. The duplicate merge tool (PD-52) is no longer needed for
  the pilot.
- Security, audit and integrity protections stay: the trash guard, the
  attachment guard, the review flags, the wrong-side guards and the
  monitoring.

### Status notes

- **2026-11-23, deletion policy.** Amended by ADR-006. Vessel users do
  **not** have delete access, and deletion is not replaced by retirement,
  archiving or deactivation. Decision points 1 to 3 (create and update
  master data without approval, offline) stand. Point 4 (vessel users can
  delete) is withdrawn. Point 5 (sync-safe deletion) now applies to the
  deletions that remain, which are made by the office under its existing
  permissions and travel down to vessels. Points 6 to 8 stand. The
  consequence "on board, shared master data is retired" no longer applies:
  on board, nothing is retired or deleted by vessel users.
- **2026-11-23, PD-71 decided.** The open point on server-side permission
  checks is settled: only a Superadmin creates users and changes roles
  (A), only a Superadmin maintains requisition categories (B), and every
  office role may edit catalog items (C). C is not a grant of retirement:
  who may retire a catalog item is unchanged. Built in Checkpoint 10.
- **2026-11-23, PD-68 decided.** A: the office retirement remains
  effective; a vessel's offline edit never silently restores or
  reactivates the retired record; the edit is kept in the conflict and
  audit history and flagged for review, so neither side's history is
  discarded. An office deletion is treated the same way (confirmed
  2026-11-23). B: not applicable, because ADR-006 already stops vessel users
  retiring or deleting records. Built in Checkpoint 14.

---

## ADR-004. User blocking and passwords: Superadmin only, on board and on shore

| | |
|---|---|
| **Status** | Approved |
| **Approved** | 2026-11-23, by the project owner |
| **Refines** | ADR-001 principle 3 (user administration) and principle 5 (offline independence) |
| **Resolves** | [pending-decisions.md](pending-decisions.md) PD-11, apart from the details listed below |
| **Design** | [adr-003-to-005-design.md](adr-003-to-005-design.md) section 5 (not part of the decision) |

### Decision

As given:

1. Only a Superadmin can block or unblock a user, or change or reset a
   user's password.
2. The onboard master PC itself cannot block users or change or reset
   passwords.
3. A Superadmin working on board can do these things while the vessel is
   offline. The changes are saved locally and synchronize to the cloud when
   internet connectivity returns.
4. A shore-side Superadmin can do them too. The changes synchronize to the
   vessel when connectivity is available.
5. Who performed each action, and when, is preserved.
6. Conflicting changes are handled safely.
7. Passwords and password credentials are never exposed in logs or
   ordinary sync records.
8. Neither the master PC nor ordinary vessel users get Superadmin
   privileges.

### What this decision does not settle

Open in [pending-decisions.md](pending-decisions.md):

- **The conflict rules** for block against unblock, and for two password
  changes (PD-11).
- **Users changing their own password** with `/change/password`, which
  exists today (PD-69).
- **What else a Superadmin may do on board**, and which Superadmin accounts
  are sent to vessels (PD-70).
- **Creating users and changing roles:** the server does not check the role
  today (PD-71).

### Consequences for the design

- The earlier on-board options (a master's reset, office one-time codes, a
  suspension by the master) are withdrawn.
- Super-admin accounts' password hashes must be on every vessel server, so
  that a Superadmin can sign in offline. That is a new exposure if a master
  PC is stolen; it is listed as a risk.
- Every block, unblock and reset is recorded as an audit event that never
  holds a password. A change to another user's password or status is
  accepted from the other side only with such an event by a Superadmin.
- An unblock action must be added; only blocking exists today.

### Status notes

- **2026-11-23.** PD-71 A extends the rule: only a Superadmin also
  creates users and changes user roles. Built in Checkpoint 10. The open
  point "Creating users and changing roles" above is settled by it.

---

## ADR-005. Backups on the vessel's master PC, vessel by vessel

| | |
|---|---|
| **Status** | Approved |
| **Approved** | 2026-11-23, by the project owner |
| **Resolves** | [pending-decisions.md](pending-decisions.md) PD-25 |
| **Design** | [adr-003-to-005-design.md](adr-003-to-005-design.md) section 6 (not part of the decision) |

### Decision

As given:

1. Backups are stored on the vessel's master PC only.
2. An external PC, external drive or off-PC backup is not required for this
   project at present.
3. If possible, backups are vessel by vessel, so that each vessel's data
   can be backed up and restored independently.
4. The backup and restore procedure is documented.
5. There is a way to verify a backup's integrity.
6. Restoration is tested before the pilot.

### What this decision does not settle

Nothing that blocks the work. Optional choices are recorded in PD-25:
encryption of the backups on the PC, and a second internal disk.

### Consequences for the design

- Each master PC holds one vessel, so its backups are vessel by vessel
  already, and a restore affects no other vessel.
- A backup set holds the database (including the sync state), the files,
  and the vessel's identity and configuration, with checksums. Secrets are
  protected by Windows and cannot be moved to another PC; a new PC gets new
  ones.
- If the master PC is lost, its backups are lost with it. What reached the
  cloud can be rebuilt from the cloud; changes since the last sync cannot.
  This risk is accepted by the decision, and frequent sync is the main
  protection.
- `backup-files.ps1 -Export` stays available, but no procedure depends on
  it.

---

## ADR-006. Vessel users do not delete records

| | |
|---|---|
| **Status** | Approved |
| **Approved** | 2026-11-23, by the project owner |
| **Amends** | ADR-003 decision points 4 and 5, as they apply to vessel users |
| **Resolves** | [pending-decisions.md](pending-decisions.md) PD-68 B (no longer applicable) |
| **Affects** | PD-61, PD-68 A (narrowed to office deletions), new PD-72 |
| **Design** | [adr-003-to-005-design.md](adr-003-to-005-design.md) section 3, as amended (not part of the decision) |

### Decision

As given:

1. **Vessel users must not have delete access.** Vessel-side delete
   permissions and delete actions are removed across the application.
   This applies to master data, operational records, attachments and other
   applicable records.
2. Deletion is **not** replaced by retirement, archiving, deactivation or
   any other equivalent action that effectively removes a record from
   active use.
3. Vessel users continue to **create and update** the master data approved
   under ADR-003, without office approval.
4. Existing requisition approval workflows and other existing business
   permissions remain unchanged.
5. Office-side permissions are not changed unless an existing approved
   decision requires it.
6. Data integrity is preserved. Stock movements, consumption and other
   append-only financial or operational ledgers are never deleted or
   overwritten.
7. PD-68's question about the fleet-wide effect of a vessel deletion or
   retirement is no longer applicable, because vessel users cannot delete
   or retire records.

### What this decision does not settle

Open in [pending-decisions.md](pending-decisions.md):

- **PD-72:** whether a few actions that are not labelled "delete" count as
  deletion: removing a line from an unsubmitted requisition draft,
  unlinking an attachment from a draft line, taking a PMS part off a
  draft, and deactivating a PMS job. Until answered, they stay as they are
  today. PD-72 also asks how a vessel gets a wrong entry removed once it
  cannot do so itself, in particular an offline vessel's PMS records,
  which the office cannot edit today.
- ~~**PD-68 A,** narrowed: an office deletion or retirement that meets a
  vessel's offline change to the same record.~~ Decided 2026-11-23: the
  office's side stands, and the vessel's change is kept and flagged.

### Consequences for the design

- "Vessel users" means users whose role is a ship role (`user_type`
  `ship`): master, chief engineer, chief officer, second engineer and
  operator. The rule applies wherever they sign in: on the cloud and on
  the vessel server.
- On the vessel server, every delete action is refused for everyone. Only
  vessel users and read-only support accounts can sign in there today. A
  Superadmin on board does only what ADR-004 allows; blocking a user is not
  a deletion of a record.
- The server refuses the delete actions; the screens no longer offer them
  to vessel users. Hiding a button is never the only protection.
- At sync, a delete or retirement arriving from a vessel is refused and
  flagged, never applied.
- Deletions still happen in the office, under existing office
  permissions, and travel down to vessels. ADR-003's sync-safety rules
  (deletion record, audit, no loss, no unsafe cascade, no resurrection,
  conflicts handled) apply to them unchanged.
- Office permissions are not widened to compensate. Where only vessel
  users could remove a record before, nobody can until PD-72 is answered.

### Status notes

- **2026-11-23, PD-72 decided.** Removing a line from an unsubmitted
  requisition draft, unlinking an attachment from a draft line, and taking
  a PMS part off a draft stay allowed: they edit an unsubmitted draft, and
  do not delete submitted records. Deactivating a PMS job is refused to
  vessel users. Built in Checkpoint 10.
- **2026-11-23, correction.** The note above said earlier that marking a
  PMS component inactive is refused "for the same reason". That was not
  part of the PD-72 approval. It is built on a reading of point 2 of this
  decision ("deactivation or any other equivalent action that effectively
  removes a record from active use"), and is recorded for the owner's
  confirmation as PD-73. It is not an approved restriction.
- **2026-11-23, PD-73 checked.** The component switch exists in the code,
  so PD-73 stays open, and the restriction built on that reading was
  removed: the switch keeps its existing permissions until the owner
  decides.
- **2026-11-24, PD-73 decided.** The owner approved restricting the PMS
  component "Inactive" switch for vessel users, under point 2. Office
  users keep their existing permission. Vessel users can neither mark a
  component inactive nor reactivate it: the screen disables the box, the
  server refuses the change, and a vessel's change to the column is
  refused at sync and flagged. Ordinary edits to other fields, the CFM
  import and office permissions are unchanged. Built in Checkpoint 10
  (screen and server) and Checkpoint 14 (sync).
- **2026-11-24, PD-34 confirmed (deletes).** The cloud must not delete a
  vessel's own records. The vessel rejects such a delete (logged and
  flagged), and the cloud prevents office users from making one for a
  vessel that runs its own server. Office deletes of office and shared
  records are unchanged (point 5). Built in Checkpoint 14.
- **2026-11-23, PD-72 B decided.** No removal mechanism is added for wrong
  entries: the master and SRD update requisition quantities, so a wrong
  entry is fixed by them. Office permissions are not widened, so the
  consequence above ("nobody can until PD-72 is answered") stands: such a
  record is corrected by editing, not removed.
- **2026-11-23, owner's instructions.** (1) PD-61: no attachment deletion
  and no new permission is added; the current protection stays, and
  deleting attachments is out of scope for now. (2) Under ADR-001
  principle 1, a vessel refuses a delete from the cloud of a record it
  owns; the refusal is logged and flagged, and nothing is deleted or
  cascade-deleted. Built in Checkpoint 14. (3) The certificate copy
  history travels between servers as immutable records (Checkpoint 15).
