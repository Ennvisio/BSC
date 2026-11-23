# NaviSuite offline-first: implementation roadmap

**Status:** approved for development on 2026-11-23, checkpoint by
checkpoint. Progress is recorded in section 17.

**Revision:** 3, 2026-11-23, after the deletion policy (ADR-006: vessel
users do not delete). Revision 2 followed the final clarifications
(ADR-003, ADR-004, ADR-005, PD-67, PD-64, PD-32). Section 2 lists what
changed in each revision.

**Replaces:**

- [implementation-plan.md](implementation-plan.md);
- the "still to do" parts of
  [offline-implementation-roadmap.md](offline-implementation-roadmap.md),
  whose architecture sections remain the reference for what is built.

**Sources:**

- the approved decisions in [architecture-decisions.md](architecture-decisions.md)
  (ADR-001, ADR-003 as amended by ADR-006, ADR-004, ADR-005; ADR-002 is
  superseded);
- the design for them in [adr-003-to-005-design.md](adr-003-to-005-design.md);
- the open items in [pending-decisions.md](pending-decisions.md) (PD-01 to PD-73);
- the code review in [adr-001-conformance-review.md](adr-001-conformance-review.md) (F1 to F21).

---

## 0. How to read this

- **Checkpoints continue from the built ones.** Checkpoints 1 to 8 are
  built. This roadmap runs from **Checkpoint 9 to Checkpoint 24**.
- **Every checkpoint has the same parts:** scope; depends on; decisions
  needed; expected outcome; required tests; cloud impact.
- **Two labels are kept apart throughout:**
  - **Approved:** your decision. The checkpoint builds it as given.
  - **Recommended:** my proposal. Where it touches a business rule, it
    waits for your answer in [pending-decisions.md](pending-decisions.md),
    and the checkpoint follows your answer, not my recommendation.
- **"Cloud" values:**
  - **No:** vessel mode only, or sync rules that stay switched off.
  - **Offline vessels:** only for vessels marked offline, of which there
    are none yet.
  - **Yes:** visible on the cloud once deployed.

**Rules for every checkpoint:**

1. Existing migrations are never edited. New storage comes only from new,
   additive migrations, and each one is named in the checkpoint so that you
   can refuse it.
2. No existing data is changed. Any data repair is a separate task with its
   own approval.
3. Each checkpoint ends with the full PHP suite at 0 failed and 0 skipped
   (MySQL tests on), plus `npm test` and the smoke test for client changes.
4. Each checkpoint ends with a report and your review, before the next one
   starts.
5. Nothing is deployed without your approval, and never without a passing
   test suite.
6. The requisition approval workflow is not changed by any checkpoint.
7. No crew management or manufacturer management module is added.
8. **Vessel users have no delete access** (ADR-006). No checkpoint adds a
   delete, retire, archive or deactivate action for them.
9. Work is done only on `feature/offline-sync`. Nothing is merged into
   `main` or deployed without your separate approval.
10. An open decision blocks only the step that needs it. The rest of the
    checkpoint goes ahead, and the step is listed as waiting.

---

## 1. Approved decisions

| Decision | Date | In short |
|---|---|---|
| **ADR-001** Operating model | 2026-11-22, confirmed 2026-11-23 | The vessel is the authority for its operational data. The office handles user administration and requisition approvals. The cloud is the hub. Vessels work offline. Authorized data syncs when the link returns. No silent overwrites. |
| **ADR-001 Clarification A** Network | 2026-11-23 | The vessel starts every connection (outbound HTTPS). The cloud never connects to a vessel. The vessel pushes and pulls on a schedule. Offline work continues locally. Sync resumes by itself. |
| **ADR-003** Master data (supersedes ADR-002), as amended by ADR-006 | 2026-11-23 | Vessel users create and update the existing master-data lists and their own vessel's records directly, offline, with no office approval, within existing permissions. Deletions (now office only) sync safely: tombstones, audit, no loss on interruption, no unsafe cascades, no resurrection, conflicts handled. Ownership of shared and office records is kept. |
| **ADR-006** No vessel deletion | 2026-11-23 | Vessel users have no delete access, on any record. Deletion is not replaced by retirement, archiving or deactivation. Create and update stay. Ledgers are never deleted or overwritten. Other permissions unchanged. |
| **ADR-004** User administration (PD-11) | 2026-11-23 | Only a Superadmin blocks, unblocks or changes or resets passwords. The master PC and vessel users cannot. A Superadmin can do it on board offline or on shore; it syncs both ways; it is audited; credentials never appear in logs or ordinary sync records. |
| **ADR-005** Backups (PD-25) | 2026-11-23 | Backups on the master PC only, vessel by vessel, with an integrity check and a documented procedure; a restore is tested before the pilot. No external copy is required. |
| **PD-67** Branch workflow | 2026-11-23 | Develop on `feature/offline-sync`. Urgent production fixes on `main`, brought into the branch and retested. Merge into `main` when complete and tested. Deploy separately, after approval. |
| **PD-64** Modules | 2026-11-23 | There is no crew or manufacturer management module, and none is added. |
| **PD-32** Laravel | 2026-11-23 | Ignored for now. Not a blocker; no upgrade task. |
| **PD-35** Monitoring | 2026-11-23 | Office sync monitoring is required for the first pilot. |
| **Direction, PD-13** | 2026-11-23 | An office rejection must not be silently overridden by a vessel action. |
| **Direction, PD-59** | 2026-11-23 | Attachments and important procurement documents must not silently replace one another. Keep both versions, with review where appropriate. |
| PD-05 | 2026-11-22 (via ADR-001) | Crew do not write their offline vessel's operational data on the cloud. |
| PD-71 A, B, C | 2026-11-23 | A: only a Superadmin creates users and changes roles. B: only a Superadmin maintains requisition categories. C: every office role may edit catalog items. C is not a grant of retirement: who may retire a catalog item is unchanged. |
| PD-72 | 2026-11-23 | Vessel users keep three draft edits; they may not deactivate PMS jobs. (Marking a PMS component inactive is not part of PD-72: see PD-73.) |
| PD-73 | 2026-11-24 (option 1) | The PMS component "Inactive" switch is restricted for vessel users: they can neither mark a component inactive nor reactivate it (screen, server and sync). Office permissions, ordinary edits and the CFM import are unchanged. |
| PD-72 B | 2026-11-23 (option 2) | No action needed for removing a wrong entry: the master and SRD update requisition quantities, so they fix it. No office permission is widened; wrong entries are corrected by editing. |
| PD-61 scope | 2026-11-23 | No attachment deletion and no new permission is added. The current protection stays. Deleting attachments is out of scope for now. |
| PD-34, deletes | 2026-11-23, confirmed 2026-11-24 | The cloud must not delete vessel-owned records. The vessel rejects such a delete (logged and flagged; never silently deleted or cascade-deleted), and the cloud prevents office users from deleting them in the first place. |
| Certificate copy history | 2026-11-23 | Synchronized safely: each copy an immutable record with a unique id, idempotent sync, the original author and time, and file integrity checks. |
| Requisition trash restore | 2026-11-23 | Fix the bug if necessary. Unsubmitted drafts stay listed in the requisition trash (confirmed). |
| Offline vessel's stock links | 2026-11-23 | The office may not delete the stock link of a vessel that runs its own server. |
| PD-68 A | 2026-11-23 (option 1) | The office retirement remains effective. A vessel's offline edit never silently restores or reactivates the retired record; the edit is kept in the conflict and audit history and flagged for review. Confirmed: a full deletion follows the same rule. |
| PD-68 B | 2026-11-23 | Not applicable: ADR-006 already stops vessel users retiring or deleting records. |
| PD-06 | 2026-11-23 (option 1) | Office PMS job approval stays a required approval. The office copy of an offline vessel's equipment list is read-only. |
| PD-60 | 2026-11-23 (option 1) | The trash refuses a permanent delete while other records refer to the record, and for any record of an offline vessel; the record stays retired. |
| PD-61 | 2026-11-23 (options 2 and 3) | A file that is the record of a procurement stage or a consumption is kept; a removed file is hidden, not deleted. |
| PD-62 | 2026-11-23 (option 1) | Every certificate copy is kept as history. |
| PD-04 | 2026-11-23 (via ADR-003) | Both contradicting tests were wrong; they are updated to the approved behaviour. |
| Development | 2026-11-23 | Authorized checkpoint by checkpoint, following this roadmap; only on `feature/offline-sync`. |

---

## 2. What changed in this revision

**Revision 4 (PD-06, PD-60, PD-61, PD-62, PD-68, PD-71 and PD-72 decided):**

| Revision 3 | Revision 4 | Why |
|---|---|---|
| Checkpoint 10 items 4 to 7 waiting | Built and tested; Checkpoint 10 complete on the branch | PD-06, PD-60, PD-61, PD-62 approved |
| PD-71 C: every role edits catalog items | Every office role edits catalog items; retirement unchanged | PD-71 C as approved |
| Component inactive switch listed under PD-72 | Recorded separately as PD-73. The switch exists in the code, so its existing permissions are kept until you decide; the restriction built on my reading was removed | It was not part of the PD-72 approval; your instruction |
| PD-73 open | Decided (option 1, 2026-11-24): the switch is office only; built on the screen, the server and at sync | Your approval |
| Checkpoint 14 item 4 waiting | PD-60 part built (a vessel refuses cascading deletes and a delete of its own vessel row) | PD-60 |
| PD-72 B open (default: no wider removal) | Decided, option 2: no action; wrong entries are corrected by editing | Your answer of 2026-11-23 |
| PD-61: removing a wrong file open | Out of scope for now; protection kept; no new permission | Your instruction |
| Cloud deletes of vessel-owned rows applied on board | Refused on board, logged and flagged (Checkpoint 14 item 7, vessel side) | Your instruction |
| Certificate copy history kept per server; sync in Checkpoint 15 | Synchronized now (Checkpoint 15, certificate part) | Your instruction |
| Trash restore of requisitions crashed | Fixed: a draft is refused, a retired requisition is restored | Your instruction |
| PD-68 A built to its default (option 1), waiting for you | Decided (option 1): the office retirement stands; the vessel's edit is kept and flagged. Checked in every order of events, with one more test; a rules check now refuses retire columns on a column-merged table | Your answer of 2026-11-23 |
| PD-68 B not applicable | Confirmed not applicable | Your answer of 2026-11-23 (ADR-006) |

**Revision 3 (ADR-006):**

| Revision 2 | Revision 3 | Why |
|---|---|---|
| Vessel users delete and retire master data and their own records (ADR-003) | **Vessel users delete nothing.** Every delete action is refused to them, on the cloud and on board, and the screens no longer offer it (Checkpoint 10) | ADR-006 |
| Checkpoint 14: rules for vessel deletes, both directions | Deletions start in the office only; a delete or retirement arriving from a vessel is refused and flagged | ADR-006 |
| Checkpoint 17: create, update and retire master data on board | Create and update only | ADR-006 |
| Checkpoint 18: per-module vessel deletion rules | No vessel deletion; office deletions of vessel records follow PD-68 A | ADR-006 |
| PD-68 A and B | B not applicable; A narrowed to an office deletion against a vessel's change | ADR-006 |
| PD-04 waiting | Settled: the tests are updated (Checkpoint 10) | ADR-003 |
| Checkpoints 10 and 11 on `main` | Built on `feature/offline-sync`; moved to `main` early only with your approval | Your instruction to work only on the branch |
| none | New PD-72: actions not labelled "delete"; removing a wrong entry | ADR-006 |

**Revision 2 (final clarifications):**

| Revision 1 | Revision 2 | Why |
|---|---|---|
| Checkpoint 13, framework upgrade | **Removed** | PD-32 is ignored for now |
| Checkpoint 19, master-data proposals (ADR-002): proposals table, pending state, review screen, approvers, alias routing, merge tool | **Removed.** Replaced by Checkpoint 17, direct create, update and delete of master data | ADR-003 supersedes ADR-002 |
| Checkpoint 18, requisition conflict hold (a held state; approval actions blocked) | **Replaced** by a sync rule inside Checkpoint 18 (operational records): the office rejection wins at sync, flagged; no new state, no blocked actions | The requisition approval workflow stays unchanged |
| Checkpoint 17, users: office reset wins; master's reset; one-time codes; master's suspension | **Replaced** by Checkpoint 16, Superadmin-only administration on board and on shore | ADR-004 |
| Delete safety as one task in the sync core | **Extended** to a full create, update and delete core (Checkpoint 14): deletion records, actor, cascade refusal, resurrection prevention, conflict rules | ADR-003 |
| No operational-records checkpoint | **New** Checkpoint 18: deletion and conflict rules for requisitions, inventory, PMS, equipment, certificates and attachments | ADR-003 |
| Off-PC backup export by a named person (pilot entry) | **Replaced** by Checkpoint 21, vessel-wise backup and restore on the master PC, with a restore drill before the pilot | ADR-005 |
| Release path option 1 (recommended) | **Approved workflow** (section 4) | PD-67 |
| Review screen with a proposals tab | Review screen without one; adds deletion and user administration conflicts | ADR-003, ADR-004 |
| PD-52 merge tool (Critical) | Optional, after the pilot | No review screen needs it |

Kept, because ADR-003 does not remove them: the trash guard, the attachment
guard, the certificate history, the review flags, the wrong-side guards for
office-maintained data, the monitoring, and every security fix.

## 3. Contradictions resolved

| Topic | What the documents said | Resolution |
|---|---|---|
| Master data | ADR-002 required office approval of vessel entries | ADR-003 applies: no approval. ADR-002 and its design are kept only as history. |
| ADR-001 principle 3 ("the office manages central master data") | Read literally, it conflicts with ADR-003 | ADR-003 refines it: four lists are maintained by vessels and the office together; the other lists stay office-maintained under existing permissions (PD-64). Recorded in ADR-001's status notes. |
| Office rejection against ship action | Revision 1 recommended a hold that blocks approval actions | Your instruction to keep the workflow unchanged rules the hold out. Recommended: the rejection wins at sync, flagged (PD-13). |
| Password reset at sea | Revision 1 recommended a master's reset and one-time codes | ADR-004: a Superadmin only. Both are withdrawn. |
| Self-service password change | ADR-004 says only a Superadmin changes passwords; `/change/password` exists for every user | Kept until you answer PD-69, because removing it would remove existing functionality. |
| Duplicates | ADR-002 made every duplicate a suggestion for the office | No review exists now. Items keep the built `item_key` merge; exact duplicates in other lists, recommended: merged and flagged (PD-18). |
| Backups | PD-25 recommended a weekly off-PC export by a named officer | ADR-005: on the master PC only. The export stays available but is not required. |
| Release path | Revision 1 proposed options | PD-67 approved the workflow. |
| Cascade deletes | PD-36 (tests) and PD-60 (fix) overlapped | One task, split by place: the cloud trash guard (Checkpoint 10) and sync cascade refusal (Checkpoint 14). |
| Installer technology | The original roadmap said Inno Setup | What is built is NSIS (Checkpoint 8). NSIS stays. |
| Sync tokens | The original roadmap said Laravel Sanctum | Built as hashed node tokens in `sync_nodes`. They stay. |
| Retention (PD-39) | Earlier text said 30 days for everything | Outbox 14 days, inbox and batches 30, logs 90. Deletion records and user administration events are never pruned. |

## 4. Branch and release workflow (approved, PD-67)

1. Development continues on `feature/offline-sync`.
2. Urgent production fixes can be made on `main`. In this roadmap, the
   candidates are **Checkpoints 10 and 11** (live data-safety and security
   fixes). Following your instruction to work only on
   `feature/offline-sync`, they are built on the branch. Moving any of them
   to `main` early, as an urgent production fix, needs your approval.
3. Each fix released on `main` is merged into `feature/offline-sync`.
4. The feature branch is tested after every such merge (the full gate).
5. When offline-sync development is complete and tested, at **Checkpoint
   22**, `feature/offline-sync` is merged into `main`. The production
   migrations are rehearsed first on a copy of the production database.
6. Production deployment is a separate step, after your approval.

Everything on the branch (Checkpoints 1 to 8 and later) reaches the cloud
only at step 6. That includes the sync columns on 53 existing tables, the
new sync tables, the PD-12 sign-in change and the dormant offline features.

---

## 5. Overview

| # | Checkpoint | Branch | Depends on | Cloud |
|---|---|---|---|---|
| 9 | Urgent checks | none | none | No code (operations by you or IT) |
| 10 | Green test baseline, live data-safety and permission fixes | feature (to `main` early only with approval) | none for the approved parts | **Yes** |
| 11 | Cloud security hardening | feature (to `main` early only with approval) | 9, 10 | **Yes** |
| 12 | Offline web assets (no CDNs) | feature | 10 | **Yes** (asset source only) |
| 13 | Windows shakedown of what is built | feature | 9 | No |
| 14 | Sync safety core: create, update and delete | feature | 10 | No / Offline vessels |
| 15 | Files and documents | feature | 14 | Small (none visible) |
| 16 | User administration by Superadmin (ADR-004) | feature | 10, 14 | Small |
| 17 | Master data: direct create and update (ADR-003, ADR-006) | feature | 14 | No (rules off until `@master`) |
| 18 | Operational records: requisitions, inventory, PMS, attachments | feature | 14, 15 | Dormant |
| 19 | Monitoring and review screens (PD-35) | feature | 14; follows 15 to 18 | **Yes** (new screens) |
| 20 | Vessel bootstrap and rebuild | feature | 16, 17 | No (new commands) |
| 21 | Vessel-wise backup and restore (ADR-005) | feature | 13, 14, 16, 20 | No (one dashboard field) |
| 22 | Production readiness and merge into `main` | feature, then `main` | 9, 13 to 21 | **Yes**, at the separate deployment |
| 23 | Windows acceptance | feature or `main` | 12, 13, 15 to 22 | No |
| 24 | First pilot, module by module | `main` (deployed) | 23 | **Yes** (sync goes live for one vessel) |

**What can run side by side:**

- Checkpoint 13 alongside 10 to 12.
- Checkpoints 15 to 18 in parallel once 14 is done (16 also needs 10).
- Checkpoints 20 and 21 alongside 19.

---

## 6. Checkpoints

### Checkpoint 9. Urgent checks

- **Scope.**
  - Check whether the live cloud site exposes `.env`, the SQL dumps,
    `Archive.zip`, logs or `vendor/`; whether `APP_DEBUG` is on; and whether
    `images/` holds old files with script extensions (PD-01).
  - Rotate every credential found in git or exposed (PD-02).
  - Collect facts: production MySQL version, hosting type, TRIGGER
    privilege, cron and limits (PD-07); Windows versions and hardware on
    the ships (PD-28); the build machine (PD-16).
  - Decide PD-12 (blocked users on the cloud). (PD-04 is settled.)
- **Depends on.** Nothing.
- **Decisions needed.** PD-01 remediation, PD-02, PD-12; the facts for
  PD-07, PD-16 and PD-28. (PD-67 is approved; PD-04 is settled.)
- **Who does it.** You or IT. It touches the production site and its
  credentials, which development may not do. It does not block Checkpoint
  10.
- **Expected outcome.** Any live exposure is closed, the credentials are
  rotated, and the cloud's capabilities are known.
- **Required tests.** None in code. A checklist record of each URL checked,
  and the result.
- **Cloud.** No code change. Operations by you or IT.

### Checkpoint 10. Green test baseline, live data-safety and permission fixes

Built on `feature/offline-sync` (your instruction of 2026-11-23). These
are live cloud fixes; moving them to `main` early needs your approval.

- **Scope.**
  1. Update the two tests to the approved behaviour (PD-04, settled by
     ADR-003), so that the gate is green.
  2. **User administration check (F20). Approved by ADR-004:** blocking a
     user and setting another user's password require super-admin on the
     server; ship users are refused every user administration action
     (ADR-004: vessel users get no administration rights). The unblock
     action itself is built in Checkpoint 16. Creating users and changing
     roles: Superadmin only (PD-71 A, approved).
  2a. **No vessel deletion (ADR-006). Approved:** every delete, retire,
     disable or trash action is refused to ship users on the cloud, and to
     everyone on the vessel server; the screens stop offering them to ship
     users. PD-72 (approved): the three draft edits stay; PMS job
     deactivation is refused to vessel users. The PMS component
     "Inactive" switch is not part of PD-72; under PD-73 (decided
     2026-11-24) it is refused to vessel users both ways, and office
     permissions are unchanged.
  3. **Requisition categories (F21):** Superadmin only, in the screens and
     on the server (PD-71 B, approved). **Catalog item edit (F14):** every
     office role (PD-71 C, approved; today's behaviour, no change). PD-71 C
     does not grant retirement: the office roles that retire catalog items
     today keep that, and no other role gains it. Ship users' retirement of
     either is refused by 2a.
  4. **Trash guard** (F12, PD-60, approved): refuse a permanent delete when
     anything references the record, and for any record of an offline
     vessel; the record stays retired in the trash.
  5. **Attachment delete guard** (F13, PD-61, approved): refuse deleting a
     file linked to a requisition line, a completed procurement stage or a
     consumption record; any other delete hides the file.
  6. **Certificate copy history** (F15, PD-62, approved).
  7. **Equipment lock** (F2, PD-06, approved): refuse the office's
     equipment edits for offline vessels, as PMS already does.
- **Depends on.** Nothing for items 1, 2 and 2a. Checkpoint 9 is not
  needed first.
- **Decisions needed.** None: items 1 to 7 are decided, and so is PD-73
  (the PMS component switch, built 2026-11-24).
- **Expected outcome.** No signed-in user other than a super-admin can
  block a user or change another user's password; no ship user can
  delete anything; the live data-loss risks are closed; the suite is green.
- **Required tests.**
  - Each user administration action by each role: block and password
    refused unless super-admin; ship users refused everything.
  - Every delete action refused to every ship role, on the cloud and on
    the vessel server, and the record left unchanged; still allowed to the
    office roles that have it today.
  - Delete buttons absent for ship users, present for the office.
  - Category and item actions per role, as decided.
  - Each trash type: refused when referenced, allowed when not; a MySQL
    test that a refused delete leaves every child row.
  - Attachment delete per link type, by the uploader and by others.
  - Certificate replaced twice, every version retrievable.
  - Equipment edit refused for an offline vessel, unchanged for others.
- **Cloud.** **Yes.** Some actions are refused that are allowed today.
  Additive migrations, each for your approval before deployment: a
  certificate copy history table (`vessel_certificate_copies`); two
  columns on `attachments` (`hidden_at`, `hidden_by`).

### Checkpoint 11. Cloud security hardening

Made on `main`, like Checkpoint 10.

- **Scope.**
  1. Attachment viewing limited to people who can see the requisition
     (PD-23).
  2. Certificate copies, photos and signatures served only to signed-in
     users (PD-24).
  3. `/register` disabled, if you decide so (PD-33).
  4. `ServiceRequisitionApproval` lists its writable columns (PD-56).
  5. Tracked secrets and dumps removed from git tracking; history rewritten
     only if you choose to (PD-02).
  6. Web root: move the cloud to a `public/` web root, or confirm the
     host's deny rules (PD-01 option 2).
  7. Optional: bcrypt cost 12 (PD-46), more useful now that super-admin
     hashes will be on master PCs.
- **Depends on.** Checkpoints 9 and 10.
- **Decisions needed.** PD-23, PD-24, PD-33, PD-56, PD-02 (history), PD-01
  option 2, PD-46.
- **Expected outcome.** The known cloud exposures are closed.
- **Required tests.** Per route, access by each role and by a guest; static
  image URLs refused without sign-in; a mass-assignment test; a test that
  no tracked file contains secrets.
- **Cloud.** **Yes.** Access changes, and a layout change if the web root
  moves.

### Checkpoint 12. Offline web assets (no CDNs)

- **Scope.** Serve jQuery, Bootstrap, DataTables, pdfmake, Popper and the
  icon fonts locally, at the versions in use (24 references in 7 views)
  (PD-03).
- **Depends on.** Checkpoint 10.
- **Decisions needed.** PD-03: both modes (recommended) or vessel mode only.
- **Expected outcome.** Every screen works with no internet.
- **Required tests.** A test that fails if any view references an external
  host; the client smoke test with the network blocked; a manual check of
  the PDF export and the DataTables screens.
- **Cloud.** **Yes**, only where the files come from.

### Checkpoint 13. Windows shakedown of what is built

- **Scope.**
  - Pin the Windows runtimes (`runtime.lock.json`, checksums archived)
    (PD-16), and build a test release with real runtimes.
  - On a lab of one server PC and two clients, including the oldest
    Windows found in PD-28: server install, services, firewall,
    certificate, DPAPI secrets, **both backups and both restores**, client
    install, discovery and pinning, upgrade, uninstall, startup check.
  - Windows time sync from a reliable source (PD-26). Fix what breaks.
- **Depends on.** Checkpoint 9 (facts). It does not wait for 10 to 12.
- **Decisions needed.** PD-16, PD-26.
- **Expected outcome.** Every PowerShell script, both installers and the
  services proven on real Windows.
- **Required tests.** The installation, client, reboot and recovery sections
  of [e2e-test-plan.md](e2e-test-plan.md), with results in its record sheet.
- **Cloud.** No.

### Checkpoint 14. Sync safety core: create, update and delete

The base for ADR-003. Design: [adr-003-to-005-design.md](adr-003-to-005-design.md)
section 3.

- **Status (2026-11-23).** In progress. Items 1, 1a, 2, 3 and 5 are built
  and tested; the PD-60 part of item 4 and the delete part of item 7 are
  built; the rest of items 4 and 7, and items 6 and 8, wait for
  decisions. See section 17.

- **Scope.**
  1. **Deletion records (approved requirement: tombstone and audit).** A new
     additive table, `sync_deletions`: table, record, who, when, origin.
     Written by the capture trigger in the same transaction as each delete
     (built: section 17 lists the differences from the design). Never
     pruned. Since ADR-006, deletions start in the office; on board they
     arise only from the PD-72 draft edits and from replaced child rows.
     Retirements are updates and travel as such.
  1a. **Vessel deletes refused at sync (ADR-006).** A delete or retirement
     that arrives from a vessel is refused and flagged, never applied,
     except the PD-72 draft edits (decided) and the child rows a save
     replaces.
  2. **No resurrection (approved requirement).** An update or insert that
     arrives for a removed record is refused and flagged, never applied;
     a retired record stays retired unless the change is a restore. A
     delete that arrives twice is ignored.
  3. **Office deletion against a vessel's change (PD-68 A, decided).** The
     office's retirement or deletion stands; the vessel's change is kept in
     the conflict history and flagged (design section 3.7).
  4. **Cascades (PD-36, PD-60 sync part).** List every `ON DELETE CASCADE`
     key reachable from a deletable record. For each: delete the children
     explicitly, so each is recorded, or refuse the parent's removal. The
     receiving side refuses a delete that would cascade into rows it owns,
     and flags it. (PD-60 part built: a vessel refuses such a delete, and a
     delete of its own vessel record. The per-key list and its MySQL tests
     wait for PD-36.)
  5. **A refused change is flagged**, never dropped silently (F3, F4).
  6. **Review flags** as decided in PD-59 A, including newest-wins outcomes
     on shared master data and `vessel_surveys` (F6).
  7. **Wrong-side guards** (PD-34) for office-maintained data on board, and
     vessel-owned data on the cloud. (Built for deletes: the cloud
     prevents office deletes of an offline vessel's own records, and a
     delete that still arrives is refused on the vessel, logged and
     flagged. Wrong-side edits wait for PD-34.)
  8. Certificates and surveys authority, as decided in PD-17.
- **Depends on.** Checkpoint 10 (trash guard).
- **Decisions needed.** PD-36 (confirm), PD-59 A, PD-34 (edits), PD-17.
  PD-68 A and PD-72 are decided.
- **Expected outcome.** Create, update and delete all travel safely: a
  delete is never lost, never undone by a late change, never cascades
  silently, and always says who and when.
- **Required tests.**
  - Each delete kind in both directions, with the sync interrupted at each
    step.
  - Late update, late insert, outbox pruned: the record does not return.
  - Every row of the PD-68 A table, in both orders, with skewed clocks.
  - A delete or retirement pushed by a vessel is refused and flagged.
  - MySQL cascade tests for every listed key.
  - The actor and time in every deletion record.
  - A route test for each guard, in both modes.
- **Cloud.** No while the rules are off. One additive table
  (`sync_deletions`, for your approval), present on both sides.

### Checkpoint 15. Files and documents

- **Status (2026-11-23).** The certificate copy history travels between
  servers (your instruction): both copies are kept when both sides upload
  while apart. Photos, signatures, office procurement files and the size
  limit are not started. See section 17.

- **Scope.**
  - Keep both versions when two versions of one file field collide:
    certificate copy, photo, signature. The losing file is kept as a linked
    version and flagged (PD-59 B, F11, F15), building on the certificate
    history from Checkpoint 10.
  - Sync never removes a file from disk, neither by a conflict nor by a
    delete. When an attachment record is deleted, its file stays on disk,
    as today (PD-54).
  - Office procurement files to vessels, and the per-file size limit
    (PD-22).
- **Depends on.** Checkpoints 14 and 10.
- **Decisions needed.** PD-59 B, PD-22, PD-21 (photos and signatures).
- **Expected outcome.** No document or file silently replaces or removes
  another, on either side.
- **Required tests.** File conflict tests (both files present on both
  sides, flagged); a deleted attachment record leaves its file on disk on
  both sides; the size limit; procurement-stage files per stage.
- **Cloud.** Sync only. A version-link table if PD-59 B needs it
  (additive).

### Checkpoint 16. User administration by Superadmin (ADR-004)

Design: [adr-003-to-005-design.md](adr-003-to-005-design.md) section 5.

- **Scope. Approved by ADR-004:**
  - **Unblock**, a new action beside the existing block.
  - **Superadmin sign-in on board:** super-admin accounts' real hashes are
    sent to vessels (all other shore hashes stay withheld);
    `userMayUseThisServer` admits super-admin; on board, the block, unblock
    and password actions are opened for super-admin only.
  - **Both ways:** `users.password`, `users.status` and `roles.status`.
  - **Audit:** a new additive table, `user_admin_events` (user, action,
    Superadmin, time, server; never a password), synced both ways and never
    pruned.
  - **Guard:** another user's password or status change is accepted from
    the other side only with a matching event by a super-admin; otherwise
    refused and flagged.
  - **Credential secrecy:** hashes kept out of `sync_conflicts`, `sync_logs`
    and `sync_row_bases` (PD-37 item 2); a conflict shows "password
    changed"; a test that no log line holds a hash; the shore placeholder
    replaced by a value that never verifies (F17).
  - The master and vessel roles get no administration rights.
- **Scope. Recommended, waiting for your answer:**
  - conflict rules: block beats unblock; a Superadmin reset beats a user's
    own change; two resets, newest wins; all flagged (PD-11);
  - users keep changing their own password (PD-69);
  - on board, a Superadmin does only the approved actions, and only named
    super-admin accounts are sent (PD-70);
  - photos and signatures (PD-21); other shore accounts (PD-20).
- **Depends on.** Checkpoint 10 (the server check), Checkpoint 14.
- **Decisions needed.** PD-11 (conflict details), PD-69, PD-70, PD-20,
  PD-21, PD-37 item 1.
- **Expected outcome.** A Superadmin on board or on shore can block,
  unblock and reset passwords, offline or online; it reaches the other side;
  nobody else can; every action is on record; no credential leaks.
- **Required tests.**
  - Each action by each role on each side.
  - Offline on board, then synced; on shore, then synced down.
  - Every conflict case, both orders, skewed clocks.
  - The guard refuses a status or password change without an event.
  - Sessions end when a block arrives.
  - No hash in any log, conflict or row base.
  - A non-super-admin shore user still cannot sign in on board.
- **Cloud.** Small: the unblock action; the audit table (additive, for
  your approval).

### Checkpoint 17. Master data: direct create and update (ADR-003, ADR-006)

Design: [adr-003-to-005-design.md](adr-003-to-005-design.md) sections 0,
1, 2 and 4.

- **Scope. Approved by ADR-003 and ADR-006:**
  - Budget groups, certificate categories, catalog items and item groups:
    vessel users create and update them on board and use them at once,
    offline, within the existing permissions. No pending state, no
    review, no approval. **No delete or retirement by vessel users.**
  - Sync both ways with the Checkpoint 14 core: deletion records for the
    office's retirements and removals, no resurrection, conflict rules,
    review flags.
  - `items.manufacturer` travels with the item; nothing else is needed.
- **Scope. Recommended, waiting for your answer:**
  - Exact duplicates in budget groups, certificate categories and item
    groups merged automatically and flagged; items keep the built
    `item_key` merge (PD-18). A merge never deletes either row: the
    second becomes an alias of the first.
  - Office-maintained lists stay as they are: down only, blocked on board
    (requisition categories, PMS lists including manufacturers, vessels,
    currencies, ranks, survey types) (PD-64). The PMS manufacturers rule is
    set to "down" to match.
- **Not built** (withdrawn with ADR-002 and ADR-006): the proposals table,
  pending labels, "My proposals", the review screen, approval roles,
  proposal routing, the alias and repoint mechanism for approvals, the
  merge tool, and any retirement by vessels.
- **Depends on.** Checkpoint 14. Checkpoint 10 for item permissions
  (PD-71 C).
- **Decisions needed.** PD-64, PD-18, PD-19.
- **Expected outcome.** Vessels create and update these lists offline
  exactly as they do online, and every change, and every office
  retirement, reaches the cloud and the other vessels without loss or
  resurrection.
- **Required tests.**
  - Create and edit each list on board offline, then sync; on the cloud,
    then sync down.
  - The same entry created on two sides: merged or waiting, as decided.
  - An office retirement against a vessel's edit, both orders.
  - A retired entry keeps its existing references on every vessel.
  - A retirement or delete pushed by a vessel is refused and flagged.
  - Office-maintained lists: a change from a vessel is refused and flagged.
- **Cloud.** No while `@master` is off. No new screens.

### Checkpoint 18. Operational records: requisitions, inventory, PMS, attachments

The ADR-003 rules for each vessel-owned module (design section 1.3).

- **Scope.**
  - **Requisitions.** The requisition approval workflow is unchanged.
    - Office rejection against a ship action: recommended, the rejection
      wins at sync, the ship's action is kept and flagged; no new state;
      the crew resubmit as today; a receipt already recorded keeps its
      stock (PD-13).
    - Draft lines removed on board carry a deletion record (PD-72 keeps
      draft-line removal; built in Checkpoint 14).
    - A wrong quantity is corrected by the master at approval or SRD at
      its review, as today (PD-72 B: no other mechanism).
    - Vessel users do not delete or retire requisitions (ADR-006).
    - Quantity changed on both sides (PD-38).
  - **Inventory.** Stock movements and consumption stay append-only and are
    never deleted; a correction is a new entry, as today. The stock figure
    rule (PD-14). Retiring an item never touches a ledger.
  - **PMS.** Vessel users do not remove components, readings or parts
    (ADR-006). Intervals and checklist lines that a job's save replaces
    carry deletion records, children deleted explicitly; the CFM import's
    removals (an office tool) recorded the same way. Job deactivation as
    PD-72 decides. Office PMS job approval stays (PD-06, decided).
  - **Equipment.** Vessel users do not disable equipment (ADR-006); the
    office, as today, where permitted.
  - **Vessel certificates and surveys.** Vessel users do not retire them
    (ADR-006); the fleet records roles, as today; authority per PD-17.
  - **Attachments.** Vessel users do not delete files (ADR-006). A removed
    file is hidden, not deleted (PD-61, built in Checkpoint 10); the hide
    travels as an update, and the file stays on disk (PD-54, Checkpoint 15).
- **Depends on.** Checkpoints 14 and 15.
- **Decisions needed.** PD-13, PD-14, PD-38. (PD-06, PD-68 A and PD-72
  are decided.)
- **Expected outcome.** Every vessel-owned record can be created and
  changed on board, offline, and the cloud ends up the same, with no loss,
  no resurrection, no vessel deletion and no change to approvals.
- **Required tests.** Per module: create and update offline, then sync;
  a delete pushed by a vessel is refused and flagged; an office deletion
  reaches the vessel and does not return; each conflict case; both
  requisition cases (forward, receipt) in both orders; a ledger row cannot
  be deleted on either side.
- **Cloud.** Sync rules only, dormant until each module is on.

### Checkpoint 19. Monitoring and review screens (PD-35)

- **Scope.**
  1. **The handshake reports** the vessel's waiting changes and failures,
     and the age of its last good backup and test restore (F19, ADR-005).
     Additive; older vessels still accepted.
  2. **Office dashboard**, one row per vessel: last contact; last
     successful push and pull; waiting changes both ways; failed batches
     and files; `sync:health` alerts; conflicts waiting; last good backup.
  3. **One review screen** for everything flagged: conflicts (PD-14, 17,
     38, 59); requisition rejections against ship actions (PD-13); office
     deletion against a vessel's change (PD-68 A); refused changes,
     including vessel deletes (ADR-006); user administration
     conflicts (PD-11); file versions (Checkpoint 15). Who resolved it,
     and when, is recorded.
  4. Optional: alerts and an on-board banner, as you decide.
- **Depends on.** Checkpoint 14; each review kind follows its checkpoint.
- **Decisions needed.** PD-35 details (who sees, who resolves, alert
  threshold, banner).
- **Expected outcome.** The office sees every vessel's sync and backup
  state, and can act on everything flagged.
- **Required tests.** Dashboard figures match the sync tables; access by
  role; each resolution action, its audit and its effect on both servers;
  an old-version vessel still accepted.
- **Cloud.** **Yes.** New screens and an additive API field.

### Checkpoint 20. Vessel bootstrap and rebuild

- **Scope.**
  - **A vessel-only export from the cloud, and an import on the vessel
    server** (PD-08): same UUIDs; the node registered first; only that
    vessel's data and the shared lists in scope (PD-19); crew hashes, and
    super-admin hashes as PD-70 decides, no other shore hashes;
    requisitions in progress as PD-10 decides; deletion records and user
    administration events included.
  - **Rebuild of a lost vessel server** from the cloud, including the
    one-way tables (stock, PMS) that cannot come back today.
  - **The lost-server procedure** for switching a vessel back to cloud
    working (PD-05).
- **Depends on.** Checkpoints 16 and 17.
- **Decisions needed.** PD-08 (confirm), PD-10, PD-19, PD-05.
- **Expected outcome.** A vessel can be commissioned and rebuilt without
  manual SQL and without other vessels' data.
- **Required tests.** A round trip on the two-server harness and on MySQL;
  no other vessel's rows and no unwanted hashes; sync continues without
  gaps or duplicates; a rebuild after a simulated loss restores the one-way
  tables; removed records stay removed.
- **Cloud.** New commands only.

### Checkpoint 21. Vessel-wise backup and restore (ADR-005)

Design: [adr-003-to-005-design.md](adr-003-to-005-design.md) section 6.

- **Scope. Approved by ADR-005:** backups on the master PC only, per
  vessel; a documented procedure; an integrity check; a restore tested
  before the pilot.
  - **The backup set** (built parts plus additions):
    - database dump with every table, including the sync state, deletion
      records and user administration events (built);
    - files with their SHA-256 manifest (built);
    - **new:** a set manifest naming both, their checksums, the vessel and
      node identity, cloud address, enabled modules, application version,
      backup time, and the last event sent and received;
    - secrets are not copied; they are protected by Windows and a new PC
      gets new ones (built procedure).
  - **Verification:** checksums for the database zip as for files; **new:**
    a weekly test restore of the database into a scratch database with
    row counts; results in `sync:health` and on the dashboard.
  - **Restore:** the steps in [disaster-recovery.md](disaster-recovery.md)
    plus: check the set manifest first; after restoring, pull deletion
    records and user administration events from the cloud and apply them,
    so nothing deleted or blocked since the backup returns.
  - **Documentation:** update disaster-recovery.md: the set, the checks,
    the restore, and the risks of keeping backups only on the master PC
    (design 6.5), which you accept with ADR-005.
- **Scope. Optional, your choice (PD-25):** encrypted backups; a second
  internal disk; BitLocker on the master PC.
- **Depends on.** Checkpoints 13 (scripts on Windows), 14, 16 and 20 (the
  rebuild is the answer to a lost disk).
- **Decisions needed.** None required; PD-25 options are optional.
- **Expected outcome.** Each vessel's data can be backed up, checked and
  restored on its own master PC, and a restore has been proven.
- **Required tests.**
  - The set manifest is complete and verifies; a damaged file or dump is
    found.
  - A full restore drill on the Windows lab: same PC, and new PC.
  - After a restore: sync recovers, records deleted after the backup stay
    deleted, blocks after the backup stay in force.
  - The test restore job reports correctly, and failure is visible on the
    dashboard.
- **Cloud.** No, apart from the dashboard field in Checkpoint 19.

### Checkpoint 22. Production readiness and merge into `main`

- **Scope.**
  - **Rehearse** the branch's production migrations on a copy of the
    production database: migrations, UUID backfill
    (`sync:backfill-uuids`), timing, rollback.
  - Install the cloud triggers (`sync:triggers install`), or move the cloud
    to a host that allows them (PD-07).
  - Cloud secrets (PD-31); retention (PD-39); one cloud database (PD-40).
  - Production installers: code signing (PD-29); the production build with
    the green gate; update delivery documented (PD-30); CA bundle source
    (PD-41).
  - **Merge `feature/offline-sync` into `main`** (PD-67 step 5), once the
    full gate is green and you approve the merge.
  - **Deployment is a separate step**, after your approval (PD-67 step 6).
- **Depends on.** Checkpoints 9 and 13 to 21.
- **Decisions needed.** PD-07, PD-29, PD-30, PD-31, PD-39, PD-40, PD-41;
  your approval of the merge, and later of the deployment.
- **Expected outcome.** The branch is merged into `main`, ready to deploy,
  with signed installers and a rehearsed migration.
- **Required tests.** The rehearsal record (duration, row counts before and
  after, rollback tested); the MySQL sync suite against the production
  MySQL version; the full gate on `main` after the merge; signature
  verification of both installers.
- **Cloud.** **Yes**, at deployment: sync columns on 53 existing tables, new
  tables (including `sync_deletions` and `user_admin_events`), triggers,
  and the dormant offline features. Done once, rehearsed, with a backup.

### Checkpoint 23. Windows acceptance

- **Scope.** The full [e2e-test-plan.md](e2e-test-plan.md) on the lab,
  against the production release or a staging copy. Extended with cases
  for Checkpoints 10 to 21: no delete action for ship users; office
  deletes and their sync; no resurrection;
  Superadmin on board; master data offline; requisition rejections;
  file versions; bootstrap and rebuild; backup and restore; the dashboard.
- **Depends on.** Checkpoints 12, 13 and 15 to 22.
- **Decisions needed.** PD-15: who runs it, when, and what counts as a
  pass. Recommended: every P1 case passes, and no case loses data.
- **Expected outcome.** A signed-off acceptance record.
- **Required tests.** The test plan itself: multiple clients; no internet
  and internet restored; server reboot and database recovery; sync
  failures; duplicate prevention; deletion and restore cases.
- **Cloud.** No if against staging; test data only if against production.

### Checkpoint 24. First pilot, module by module

- **Scope.** One vessel.
  1. Commission it with the bootstrap (Checkpoint 20).
  2. Mark it offline (`navisuite:vessel-offline`).
  3. Switch on the modules in the order PD-09 decides. Recommended:
     24a `@master`; 24b `@inventory` and `@pms`; 24c `@requisitions`;
     24d `@attachments`.

  Each stage runs for an agreed period and is reviewed before the next.
- **Depends on.** Checkpoint 23. Ship-side preparation: a dedicated master
  PC with a fixed address and time sync (PD-26, PD-28); the link and sync
  timing agreed (PD-27); **a restore drill passed on that master PC**
  (ADR-005).
- **Decisions needed.** PD-09, PD-14, PD-27;
  optional PD-44, PD-45, PD-50.
- **Expected outcome.** One vessel working offline with every module, and
  the office seeing and resolving everything flagged.
- **Required tests.** Before each stage: the dashboard is clean; that
  module's cascade tests pass on MySQL; a backup is verified on board.
  During each stage: a daily dashboard check; every flagged item resolved.
  **Exit criteria:** no data loss; no deleted record reappears; no
  unresolved conflict older than the agreed limit; sync recovers by itself
  after every outage.
- **Cloud.** **Yes.** Sync goes live for one vessel. Its crew become
  read-only on the cloud.
- **Rollback.** A module is switched off by removing it from
  `SYNC_TABLES`. The vessel returns to cloud working with
  `navisuite:vessel-offline {id} --disable`, by the PD-05 procedure.

---

## 7. Business decisions requiring your approval

In the order they are needed. "Recommended" is my suggestion; the full
context is in [pending-decisions.md](pending-decisions.md). Decided on
2026-11-23 and no longer listed: PD-06, PD-60, PD-61, PD-62, PD-71,
PD-72 and PD-72 B (section 1).

| Needed by | Item | Question | Recommended |
|---|---|---|---|
| 9 | PD-12 | Refuse blocked users at cloud sign-in too | Yes (matches ADR-004), after checking no account in use is blocked |
| 9 | PD-02 | Rotate, untrack, possibly rewrite history | Rotate and untrack; rewrite only if the repository was shared widely |
| 11 | PD-23, PD-24, PD-33 | Attachment viewing; images behind sign-in; `/register` | Yes; yes; disable unless the office uses it |
| 11, 16 | PD-37 | Remaining security limits | Accept item 1; item 2 is now required (ADR-004) |
| 14 | PD-59 A | Which automatic outcomes are flagged | Every case where the owner lost, every newest-wins on shared data, every file, password and status conflict |
| 14 | PD-34 | Block or warn for wrong-side **edits** (deletes of vessel-owned records are decided and built) | Block |
| 14 | PD-17 | Certificates and surveys: ship, office or both | Ask whoever manages certificates; if both, newest wins with review |
| 15 | PD-59 B | How both document versions are kept | History for certificate copies and procurement documents; keep both on conflict for photos and signatures |
| 15 | PD-22 | Office procurement files to vessels; size limit | Decide with procurement; set a limit after measuring link cost |
| 15, 16 | PD-21 | Photos and signatures changed on board | Up, newest wins, if crew update their own signature |
| 16 | PD-11 | Conflict rules for blocks and passwords | Block beats unblock; Superadmin reset beats own change; newest of two resets; all flagged |
| 16 | PD-69 | Keep users' own password change | Keep it |
| 16 | PD-70 | Superadmin powers on board; which accounts are sent | Only the approved actions; named accounts only |
| 16 | PD-20 | Other shore accounts on board | None |
| 17 | PD-64 | Should any office-maintained list (PMS lists, including manufacturers) become vessel-maintained? | No; keep existing permissions |
| 17 | PD-18 | Automatic merge of exact duplicates | Exact name matches, flagged; items by `item_key` |
| 17, 20 | PD-19 | Catalog scope on board | Ask the requisition raisers |
| 18 | PD-13 | Office rejection against ship action | The rejection wins at sync, flagged; workflow unchanged; receipt settled by the office |
| 18 | PD-38 | Quantity changed on both sides | Ship's value, flagged |
| 19 | PD-35 | Who sees and resolves; alerts; banner | Named office roles; alert after an agreed silence; banner optional |
| 20 | PD-10, PD-08, PD-05 | Requisition numbers at commissioning; bootstrap scope; lost-server procedure | Vessel number final, with a cut-off; as in Checkpoint 20; a written exception |
| 21 | PD-25 options | Encrypt backups; second internal disk; BitLocker | Optional; BitLocker recommended because of the super-admin hashes |
| 22 | PD-07, PD-29, PD-30, PD-31, PD-39, PD-40, PD-41 | Hosting, signing, updates, secrets, retention | As in pending-decisions.md |
| 24 | PD-09, PD-14, PD-27 | Module order; stock figure; sync timing | As in Checkpoint 24; vessel's figure, flagged; 5 minutes on cheap links, hourly on metered |
| Any time | PD-44, 45, 47, 48, 49, 50 | Single-user sessions; Edge fallback; time zone; client approach; fleet size; phones | As in pending-decisions.md |

## 8. Technical fixes required

| Fix | Source | Checkpoint |
|---|---|---|
| Update the two contradicting tests | PD-04 (settled) | 10 |
| Refuse every delete action to ship users, and every delete on the vessel server; hide the buttons | ADR-006 | 10 |
| Super-admin check on user administration; checks on categories and items | F20, F21, F14, ADR-004, PD-71 | 10 |
| Trash guard; attachment guard; certificate history; equipment lock (built) | F12, F13, F15, F2, PD-60, PD-61, PD-62, PD-06 | 10 |
| Attachment view authorization; signed-in image serving; `$fillable` | PD-23, PD-24, PD-56 | 11 |
| Local web assets | PD-03, F9 | 12 |
| Fixes found by the Windows shakedown | PD-15, CP3 to CP8 | 13 |
| Deletion records; actor; vessel deletes refused at sync; no resurrection; office deletion against change; cascade refusal; refused-change flagging; review flags; wrong-side guards | ADR-003, ADR-006, F1, F3, F4, F6, F12, PD-34, PD-36, PD-59, PD-68 | 14 |
| File and document versions; sync never removes a file | F11, F15, PD-59 B | 15 |
| Unblock; Superadmin on board; status both ways; audit events; guard; hashes out of conflicts, logs and row bases; never-verifying shore hash | ADR-004, F16 to F18, PD-37 | 16 |
| Master-data rules both ways (create, update; office retirements with deletion records); exact duplicate merge | ADR-003, ADR-006, PD-18 | 17 |
| Requisition rejection rule; per-module rules (no vessel deletion; office deletions travel down) | ADR-003, ADR-006, F7, PD-13 | 18 |
| Handshake backlog and backup age; dashboard; review screen | F10, F19, PD-35, ADR-005 | 19 |
| Vessel bootstrap and rebuild | F9, PD-08 | 20 |
| Backup set manifest; database checksum; test restore; tombstone re-apply after restore | ADR-005 | 21 |

## 9. Security issues and remediation

| Issue | Where | Severity | Remediation | Checkpoint |
|---|---|---|---|---|
| `.env`, dumps, logs possibly served (web root is the project root) | Cloud | Critical (unverified) | Check now; deny rules; rotate; later a `public/` web root | 9, 11 |
| Secrets and database dumps tracked in git | Repository | Critical | Rotate; untrack; optional history rewrite | 9, 11 |
| **Any signed-in user can change another user's password, role or block by posting to `/user/update` or `/user/delete`** | Cloud | **Critical** | Super-admin check on the server (ADR-004) | 10 |
| Old uploads with script extensions may remain | Cloud `images/` | High (unverified) | Check and remove (PD-01) | 9 |
| Any signed-in user can retire vessels, requisition categories and catalog items, and use the trash (restore, permanent delete) | Cloud | High | Ship users refused (ADR-006, built); requisition categories Superadmin only (PD-71 B, built); a permanent delete refused while referenced or for an offline vessel (PD-60, built). Office roles keep the retirements they have today. | 10 |
| Any signed-in user can edit catalog items and requisition categories | Cloud | High | Requisition categories: Superadmin only (PD-71 B, built). Catalog items: every office role may edit (PD-71 C, accepted) | 10 |
| Any signed-in user can open any attachment by id | Cloud | Medium | Limit to people who can see the requisition | 11 |
| Certificate copies, photos and signatures served without sign-in | Cloud and board | Medium | Serve through a signed-in route | 11 |
| Public self-registration | Cloud and board | Medium | Disable, if you decide | 11 |
| Mass assignment in `ServiceRequisitionApproval` | Cloud | Low | `$fillable` | 11 |
| A password or block changed by someone other than a Superadmin arrives through sync | Sync | High | Guard with audit events (ADR-004) | 16 |
| Super-admin password hashes on every master PC (new, needed by ADR-004) | Board | Medium | Named accounts only, long passwords, bcrypt 12, BitLocker | 11, 16, 21 |
| Password hashes in `sync_row_bases`, conflicts or logs | Board and cloud | Medium | Keep them out (ADR-004) | 16 |
| Shore placeholder hash with an unrecorded plaintext | Sync | Low | A value that never verifies | 16 |
| Backups unencrypted on the master PC | Board | Medium | Optional encryption or BitLocker (PD-25) | 21 |
| MySQL root password in a temporary file during dumps | Board | Low | Accept, if you agree (PD-37) | none |
| Laravel 11 out of security support | Everywhere | Medium | **Deferred by you (PD-32).** Accepted for now; no task planned | none |
| Unsigned installers | Ships | Medium | Code signing | 22 |
| Cloud secrets in a key file on Linux | Cloud | Medium | A key service or service-manager environment | 22 |
| No Content-Security-Policy | Everywhere | Low | Optional (PD-51) | Later |

## 10. Offline infrastructure and Windows deployment requirements

| Requirement | Detail | Where settled |
|---|---|---|
| Master PC | Dedicated machine, fixed address, power plan and service recovery set by the installer | PD-28; Checkpoints 13, 24 |
| Windows versions | Server: Windows 10 1607 or later, or Server 2016 or later. Clients: Windows 10 or 11, 64-bit. The oldest version found is tested. | PD-28; Checkpoints 13, 23 |
| Time | Windows time sync from GPS or NTP; the skew warning at 120 seconds stays | PD-26; Checkpoint 13 |
| Network | Outbound HTTPS to the cloud only; no inbound port from the internet; LAN discovery on UDP 47810; a proxy must exclude the server | ADR-001 Clarification A |
| Certificates | A vessel CA per ship; clients pin it through the installer; Edge as fallback | CP3, CP4; PD-45 |
| Services | Web, MySQL, queue, scheduler, discovery and sync, as Windows services; a startup check after every boot | CP3, CP5, CP8 |
| Secrets | DPAPI-protected on board; never in plaintext files; not portable to another PC | CP7 |
| Backups | On the master PC only, per vessel: database, files, set manifest, checksums, weekly test restore; no external copy required | ADR-005; Checkpoint 21 |
| Runtimes | Pinned versions and checksums; MySQL updates within one series only | PD-16, PD-42 |
| Installers | Signed, from a known build machine, only with the green gate | PD-16, PD-29; Checkpoint 22 |
| Updates | Technician or USB for installers; the release zip over the link | PD-30 |
| Assets | No external host is needed by any page | Checkpoint 12 |

## 11. Synchronization and conflict-resolution requirements

1. **Ownership: three kinds of record** (design section 2).
   - Shared master data (budget groups, certificate categories, catalog
     items, item groups): vessels and the office both create and update;
     only the office retires or removes, within existing permissions
     (ADR-003, ADR-006, approved).
   - Vessel-owned operational records: the vessel creates and updates, for
     its own vessel only; it does not delete (ADR-001, ADR-003, ADR-006,
     approved).
   - Office-owned or shared records (users, roles, vessels, requisition
     categories, PMS lists, currencies, ranks, survey types, the office's
     requisition stages, other vessels' records): the office; users by a
     Superadmin (ADR-004). A vessel cannot change or delete them.
2. **Network** as in Clarification A: the vessel initiates, pushes then
   pulls, retries by itself; batches are idempotent.
3. **Create, update and delete all travel.** Deletes start in the office
   (ADR-006). Every delete writes a lasting deletion record with who, when
   and where, in the same transaction (approved requirement; Checkpoint
   14). A delete or retirement arriving from a vessel is refused and
   flagged.
4. **Nothing deleted comes back.** Late updates and inserts for a removed
   record are refused and flagged; after a backup restore, deletion
   records are re-applied (approved requirement; Checkpoints 14, 21).
5. **No unsafe cascade.** A referenced record is retired, not removed.
   Cascading keys are either replaced by explicit, recorded child deletes,
   or the delete is refused (approved requirement; Checkpoint 14).
6. **No silent overwrite.** A change from the side that may not make it is
   refused and flagged. Conflicts are flagged as decided in PD-59 A. An
   office deletion against a vessel's change follows PD-68 A (decided). Everything flagged reaches the
   review screen (Checkpoint 19).
7. **No silent loss of documents.** No file replaces or removes another
   through sync (Checkpoint 15). Ledger rows are permanent.
8. **Requisition approvals unchanged.** At sync, an office rejection is not
   overridden by a ship action (direction PD-13; recommended rule in
   Checkpoint 18).
9. **Users.** Block, unblock and passwords by a Superadmin only, both ways,
   audited, guarded; credentials never in logs or ordinary sync records
   (ADR-004; Checkpoint 16).
10. **Modules switched on one at a time** (PD-09), each only after its
    rules, tests and review screen exist.

## 12. Deletion safety by module

Vessel users delete nothing (ADR-006). The table says what the office may
still delete, and how that is kept safe.

| Module | Vessel users | Office (existing permissions) | Safety and conflicts |
|---|---|---|---|
| Master data (four shared lists) | Create, update. No delete or retire | Retire, as today; remove in the trash only when nothing refers to it (PD-60, built) | Deletion record; references kept; PD-68 A |
| Office-maintained lists | Nothing (blocked on board) | As today | A vessel change is refused at sync, flagged |
| Requisitions | Create and edit drafts (PD-72 for removing draft lines); no delete | Trash (super-admin) | Approval workflow unchanged; PD-60 guard |
| Inventory ledger | Append only; corrections are new entries | Never deleted | Ledger rows are permanent on both sides |
| Equipment | Create, update. No disable | Disable, as today, for vessels without their own server; read-only for offline vessels (PD-06, built). Wrong entries are corrected by editing (PD-72 B) | Deletion record when it travels |
| PMS | Create, update. No job deactivation (PD-72). No component "Inactive" switch, either way (PD-73). No removal | Super-admin, not for offline vessels; wrong entries are corrected by editing (PD-72 B) | Children deleted explicitly; deletion records |
| Certificates and surveys | Create, update. No retire | Fleet records roles retire; trash | Per PD-17 |
| Attachments | Upload, link. No delete (unlink from a draft: PD-72) | Nobody reaches the file delete today (the route admits only two ship roles, which ADR-006 refuses). The server guard (PD-61) hides the file and refuses it when it is a record. Deleting attachments is out of scope for now; no permission is added | The file and its record stay (PD-54, PD-61). A cloud delete of a file uploaded on board is refused on the vessel |
| Users | Nothing | Superadmin blocks and unblocks (ADR-004); blocking is not a delete | ADR-004 rules |

## 13. Vessel bootstrap requirements

1. Export from the cloud only: that vessel's rows; the shared lists in
   scope (PD-19); its crew with their real hashes; super-admin hashes as
   PD-70 decides; no other shore hashes; deletion records and user
   administration events.
2. Keep every UUID.
3. Register the node **before** taking the export, so its cursors start at
   the right place.
4. Carry over requisitions in progress as PD-10 decides, with a cut-off.
5. Import into an empty vessel server; verify the counts against the cloud.
6. Rebuild a lost or restored vessel from the cloud, including one-way
   tables.
7. Never include another vessel's data.
8. Document it in [disaster-recovery.md](disaster-recovery.md), with the
   PD-05 switch-back procedure.

## 14. Backup and restore requirements (ADR-005)

1. Backups stay on the master PC. No external copy is required.
2. One master PC is one vessel, so each backup is that vessel's alone, and
   restoring it touches no other vessel.
3. Each nightly set holds: the database (with sync state, deletion records
   and user administration events), the files, and a set manifest with the
   identity, configuration, version, sync position and checksums.
4. Verification: checksums, and a weekly test restore into a scratch
   database; results on `sync:health` and the dashboard.
5. Restore: [disaster-recovery.md](disaster-recovery.md), plus the set
   check and the re-apply of deletion records and user events.
6. A restore drill passes on the Windows lab (Checkpoint 21) and on the
   pilot master PC (Checkpoint 24) before the pilot.
7. **Risk accepted with ADR-005:** a lost or destroyed master PC loses the
   live data and every backup together. What reached the cloud is rebuilt
   from it (Checkpoint 20); changes since the last successful sync are
   lost. Frequent sync and the office alert are the main protection.

## 15. Testing and first-pilot requirements

**The test gate (every checkpoint):** the full PHP suite at 0 failed and 0
skipped (MySQL tests on); the desktop `npm test` and smoke test; a
production installer build only from a clean tree with the gate green. On
`main` too, for Checkpoints 10 and 11, and again on the feature branch
after each merge from `main` (PD-67).

**Suites kept and extended:** the two-server sync harness; the MySQL sync
and cascade tests; the module tests on the real schema; the client tests.
Each checkpoint adds the tests listed under it.

**Windows:** the shakedown (Checkpoint 13) and the full acceptance run
(Checkpoint 23).

**The production cloud:** a rehearsal on a copy of the production database
before the merge (Checkpoint 22).

**Pilot entry criteria:** acceptance signed off; dashboard and review
screen live; the vessel bootstrapped; the master PC prepared; **a restore
drill passed on it**; the office reviewers named; the rollback rehearsed.

**Pilot exit criteria, per module stage:** no data loss; no deleted record
reappears; every flagged item resolved within the agreed time; sync
recovered by itself after every outage; the office's figures match the
vessel's.

## 16. Later, and accepted for now

Not needed for the pilot:

- PD-32: Laravel upgrade (deferred by you; accepted risk).
- PD-52: a merge tool for near-duplicates.
- PD-42: MySQL series upgrade in the installer.
- PD-43: LAN download of the client installer.
- PD-46: bcrypt cost 12, unless done in Checkpoint 11.
- PD-51: Content-Security-Policy.
- PD-53: cleanup after a move between vessels.
- PD-54: removing files whose record was deleted.
- PD-55: renaming legacy attachments.
- PD-57: changing sync settings in the guided installer.

---


## 17. Progress

Filled in as checkpoints are worked on. A checkpoint is marked complete
only when its acceptance criteria and tests are satisfied. Nothing is
committed, merged into `main` or deployed.

| Checkpoint | Status (2026-11-23) |
|---|---|
| 9 | **Waiting for you or IT.** It is operations on the production site and its credentials, which development may not touch. It does not block other work. |
| 10 | **Complete on the branch** (not committed, not deployed). Items 1 to 7 built, and every required test listed for it written and passing (below). The PMS component switch is restricted for vessel users (PD-73, decided 2026-11-24). |
| 14 | **In progress.** Items 1, 1a, 2, 3 and 5 built and tested; the PD-60 part of item 4 and the delete part of item 7 built and tested. The rest of items 4 and 7, and items 6 and 8, wait for decisions (below). |
| 15 | **In progress.** The certificate copy history syncs (below). The rest is not started. |
| 11 to 13, 16 to 24 | Not started. |

### Checkpoint 10 (2026-11-23)

| Item | Status | Detail |
|---|---|---|
| 1. The two PD-04 tests | **Done** | Updated to the approved behaviour: crew may open certificate categories (ADR-003); `/` sends a guest to sign-in. |
| 2. User administration (F20, ADR-004, PD-71 A) | **Done** | Ship users are refused every user screen and action. Only a super-admin creates a user, blocks one, sets another user's password, or changes a user's role or vessel. Other office roles get 403, including by a direct request to `/user/store` or `/user/update`; they can still correct a user's name and email. The Users screen shows Add New User, the block button, the password fields and the role and vessel choices to super-admin only. |
| 2a. No vessel deletion (ADR-006) | **Done** | The 15 delete, retire, disable and trash actions are refused to every ship role on the cloud, and to everyone on the vessel server; the record is left unchanged. The screens hide those buttons from ship users. Office users keep the delete actions they had. |
| 2b. Draft edits and PMS deactivation (PD-72) | **Done** | The three draft edits still work for vessel users (removing a draft line, unlinking an attachment from a draft line while the file stays in the library, taking a PMS part off a draft). Deactivating a PMS job is refused to vessel users on the server and hidden on the screen; a job the office deactivated can still be reactivated on board. The PMS component "Inactive" switch is not part of PD-72; see 2c. |
| 2c. PMS component "Inactive" switch (PD-73, decided 2026-11-24) | **Done** | Vessel users, and everyone on a vessel server, get 403 for a change to the switch in either direction, and for a new component sent as inactive; a request without the switch keeps the current value. The screen shows the box disabled for them, with "Set by the office only." Office permissions are unchanged (`canEditPmsVessel`). Ordinary edits to the other fields work. Tests: `VesselUserDeletionTest` (5). |
| 3. Office checks for categories and items (F21, F14) | **Done** | PD-71 B: only a super-admin creates, edits or retires requisition categories, on the screen and on the server. PD-71 C: every office role may edit catalog items, which is today's behaviour, so no code change; F14 is accepted. Catalog retirement is unchanged: the office roles that retire items today still do, ship roles are refused by 2a, and nobody gains it. |
| 4. Trash guard (F12, PD-60) | **Done** | `App\Support\TrashGuard`. A permanent delete is refused (JSON 422 with the reason, shown on the screen) unless the record is retired, belongs to no vessel that runs its own server, and nothing refers to it. The check covers every column that refers to a trash table, declared foreign key or not; a test fails if a declared key is missing from the list. **Bug fixed on the way:** the trash's delete for surveys and certificates looked the id up in the fleet-wide `surveys` and `certificates` tables, so it deleted a survey or certificate *type* that shared the id, not the vessel's record. |
| 5. Attachment guard (F13, PD-61) | **Done** | A file on a requisition line, a completed procurement stage or a consumption record is refused (422 with the reason). Any other removal sets `hidden_at` and `hidden_by`: the record and the file stay, and the file is no longer offered in the library or for linking. Today nobody reaches this route from the screens (see the contradictions below), so this is a server-side guard. |
| 6. Certificate copy history (F15, PD-62) | **Done** | New table `vessel_certificate_copies`. Each upload is recorded with who and when; a replaced copy is marked replaced, and its file is no longer deleted. A copy uploaded before the history began is recorded when it is first replaced. The certificate form lists earlier copies. A certificate with copy history stays in the trash (PD-60). |
| 7. Equipment lock for offline vessels (F2, PD-06) | **Done** | On the cloud, adding, editing, disabling and uploading equipment for a vessel that runs its own server returns 403, and the screen shows the list read-only with a notice. Vessels without their own server are unchanged. Office PMS job approval is unchanged (PD-06: it stays a required approval). |

**How it is built.**

- `App\Support\DeletionPolicy` lists the delete paths and says who may
  use them. The middleware `RefuseVesselUserDeletes`, on the `web` group,
  refuses them before any record is loaded (JSON 403 with a message for
  the screens' AJAX calls).
- `partials/deletion-policy-styles` (in the page head) hides the buttons.
- `HomeController`: `refuseVesselUserAdministration()` on the five user
  actions; super-admin checks on creating, blocking, password changes,
  role and vessel changes, and category maintenance.
- `PmsJobDefinitionController::update` and `PmsComponentController::update`
  refuse a deactivation by a user who may not delete; the edit forms hide
  the switch from them (`can_deactivate`).
- Items 4 to 7: `App\Support\TrashGuard` and `HomeController::permanentDelete`;
  `Attachment::deletionRefusal()`, `Attachment::scopeVisible()` and
  `AttachmentController::destroy`, with the library and linking queries
  (`ProcurementStep`, `ServiceProcurementStep`, `RequisitionController`)
  limited to visible files; `HomeController::recordCertificateCopy()` and
  the certificate form; `EquipmentController::refuseIfLocked()` and the
  equipment screen. `js/dataForm.js` shows the refusal messages and the
  earlier certificate copies.
- New additive migrations: `2026_11_26_110000_add_hidden_columns_to_attachments_table.php`,
  `2026_11_26_120000_create_vessel_certificate_copies_table.php`.

**Required tests (Checkpoint 10), and where they are.**

| Required test | Where | Result |
|---|---|---|
| Each user administration action by each role | `VesselUserDeletionTest` | Passing |
| Every delete refused to every ship role, on the cloud and on the vessel server; still allowed to the office | `VesselUserDeletionTest` | Passing |
| Delete buttons absent for ship users, present for the office | `VesselUserDeletionTest` | Passing |
| Category and item actions per role | `VesselUserDeletionTest` (requisition categories Superadmin only; catalog item edit by each of the 12 office roles, and item retirement unchanged: an office role still retires, a ship role is refused) | Passing |
| Each trash type refused when referenced, allowed when not | `ApprovedGuardsTest` (pd60, 9 tests: category, item, survey, vessel, requisition and certificate) | Passing |
| A MySQL test that a refused delete leaves every child row | `TrashGuardMySqlTest` (new, 4 tests: item, requisition and vessel refused with every child row left; a control shows MySQL would cascade without the guard; an unreferenced item is deleted and leaves its deletion record) | Passing on local MySQL 9.3 |
| Attachment delete per link type, by the uploader and by others | `ApprovedGuardsTest` (pd61, 4 tests). Per link type: yes. "By others": nobody can reach the route today, which the first test checks | Passing |
| Certificate replaced twice, every version retrievable | `ApprovedGuardsTest` (pd62, 3 tests) | Passing |
| Equipment edit refused for an offline vessel, unchanged for others | `ApprovedGuardsTest` (pd06, 2 tests) | Passing |

**Behaviour that changes on the cloud once deployed.** Each is approved:

- ship users can no longer retire budget groups, certificate categories,
  catalog items, requisition categories, certificates, surveys,
  equipment or vessels, delete PMS components, readings, parts or their
  own uploaded files, or use the trash (ADR-006);
- ship users can no longer deactivate PMS jobs (PD-72), nor mark a PMS
  component inactive or reactivate it (PD-73);
- only a super-admin creates users, changes roles or vessels, blocks users
  or sets their passwords (ADR-004, PD-71 A);
- only a super-admin maintains requisition categories (PD-71 B);
- ship users can no longer open the Users screen by its address;
- the trash refuses a permanent delete while anything refers to the
  record, for an offline vessel's record, and for a record not retired
  (PD-60); and it now deletes the vessel's survey or certificate, not
  the fleet-wide type with the same id (bug fix);
- a removed attachment is hidden, not deleted, and a file that is a
  record is kept (PD-61);
- replacing a certificate copy keeps the earlier one (PD-62);
- the office cannot change the equipment list of a vessel that runs its
  own server (PD-06).

### Checkpoint 14, first part (2026-11-23)

| Item | Status | Detail |
|---|---|---|
| 1. Deletion records | **Done** | New table `sync_deletions`: table, row id and UUID, vessel, who (`actor_uuid`), where from (`origin_node`, empty when deleted on this server) and when. Written by the capture trigger in the same transaction as the delete, so a delete made any way (screen, command, service) is recorded and a rolled-back delete leaves no record. Never pruned. Each server keeps its own; a delete received from another server is recorded there too, with the same person and its origin. |
| 1a. Vessel deletes refused at sync (ADR-006) | **Done** | A delete from a vessel is accepted only for `order_items`, `attachment_order_item`, `pms_required_parts`, `pms_job_intervals` and `pms_job_checklist_items` (config `sync.vessel_deletes`: the PD-72 draft edits and the child rows a job save replaces). Any other is refused and flagged. A change from a vessel that would retire a record (config `sync.retire_columns`: the `status` of budget groups, certificate categories, items, certificates, surveys and equipment; PMS job `is_active`; both PMS tables' `deleted_at`) is not taken; the rest of that change is, and the refusal is flagged. A vessel's change to an office-only column (config `sync.office_only_columns`: the component "Inactive" switch, PD-73), in either direction, is refused the same way. |
| 2. No resurrection | **Done** | A change arriving for a record deleted on the receiving server, at any time, is not applied: the record is not recreated, and the change is kept in a conflict flagged for review. This holds after the outbox is pruned, and for a copy restored from an old backup. A delete arriving twice is ignored. |
| 3. Office deletion against a vessel's change (PD-68 A, decided: option 1) | **Done** | A delete always stands against a change made meanwhile on the other side, which is kept in the conflict record for review. An office retirement stands against a change the vessel made offline: the vessel's edit never reactivates the record, its other values follow the table's usual rule, and the edit is kept in the conflict and flagged. Checked in every order: at the office (edit in `remote_values`), when the office changed the row last, and on the vessel when the retirement arrives before the edit is sent (edit in `local_values`; retired on both sides after the next cycle). A record created already retired, or brought back into use deliberately, is not affected. A table with column owners cannot also have retire columns (the rules check refuses it), because this rule compares whole rows. |
| 5. A refused change is flagged (F3, F4) | **Done** | Every change the receiver refuses (ownership, a permanent record, ADR-006) now records a `refused` conflict needing review, once per change however often it is retried. The vessel also receives a copy, so the crew side can see it. |
| Who made each change | **Done** | The signed-in user is recorded with every change (middleware `SetSyncActor`), travels with the sync event, and is kept by the receiving server. Changes made by commands and the scheduler are recorded as the system. |
| 4a. Cascades, PD-60 part | **Done** | A vessel refuses a delete from the cloud that would cascade, through an `ON DELETE CASCADE` key, into rows it holds, and a delete of its own vessel record; each is flagged as a refused conflict. The keys are read from the schema, so a new key is covered without a list. A delete with nothing below it on board is applied. |
| 4. Cascades, the rest | Waiting | PD-36 (confirm): the per-key list and MySQL cascade tests at sync. |
| 6. Review flags | Waiting | PD-59 A. The new deletion and retirement conflicts above are already flagged. |
| 7a. Cloud deletes of vessel-owned records (PD-34, vessel side) | **Done** | On the vessel, a delete from the cloud is refused when the row is the vessel's own record: a vessel-owned operational table, a table only the vessel creates rows in (requisitions, lines, approvals, PMS jobs), a row the vessel keeps in a shared table (form Part A, Receipt & Verification), or a file its crew uploaded. The refusal is in the sync log with the pull and flagged as a `refused` conflict; nothing is deleted, so no deletion record is written. Certificates, surveys and shared master data are not counted as vessel-owned (PD-17 open). |
| 7b. Cloud deletes of vessel-owned records (PD-34, cloud side, confirmed 2026-11-24) | **Done** | `App\Sync\VesselOwnership` holds the one rule of what is a vessel's own record; the vessel's refusal (7a) and the cloud now both use it. On the cloud, every model delete of an offline vessel's own record is refused before anything changes (403 with the reason; listener in `SyncServiceProvider`, exception `VesselRecordProtected`). Every office delete route was checked and already refuses such records (trash, equipment, PMS, files, requisition drafts); a test walks them. The CFM import's `--remove-unmatched` is refused on the cloud for an offline vessel (a bulk delete the model check does not see). Not affected: vessels without their own server, the vessel server, rows the office keeps, office files, master data, certificates and surveys (PD-17). Tests: `ApprovedGuardsTest::test_pd34_*` (5). |
| 7. Wrong-side edits | Waiting | PD-34. |
| 8. Certificates and surveys authority | Waiting | PD-17. |

**Differences from the design** ([adr-003-to-005-design.md](adr-003-to-005-design.md)
section 3), all simplifications:

- The deletion record is written by the database trigger, not by the
  application, so no delete path can miss it.
- It has no "short label" column: the conflict record keeps the values.
- It is not synced: each server writes its own when it applies a delete,
  with the same person and the origin.
- The person is passed to the trigger in a session variable
  (`@sync_actor` on MySQL), like the origin.
- No draft check at sync for `order_items`: the screens and the server
  already allow line removal only on drafts, and the sync batches can
  carry a submit before the line removal that preceded it, which would
  refuse a legitimate removal.

**Known limits.**

- The `pms:import-cfm` command marks components it no longer finds as
  deleted. Run on a vessel server, that retirement is refused and flagged
  at the cloud. It is an IT tool, normally run before a vessel goes
  offline.
- Restoring a vessel from a backup and re-applying deletions recorded
  since is Checkpoint 21.

**Files (Checkpoint 14).**

| File | Change |
|---|---|
| `database/migrations/2026_11_26_100000_create_sync_deletions_table.php` | New, additive: `sync_deletions`; `actor_uuid` on `sync_outbox` and `sync_inbox` |
| `app/Sync/TriggerBuilder.php` | Outbox events carry the actor; a delete also writes the deletion record (MySQL and SQLite) |
| `app/Sync/TriggerManager.php` | Refuses to install triggers before the migration has run |
| `app/Sync/SyncContext.php` | The actor (`setActor`, `asActor`, `@sync_actor`, `sync_actor()`) |
| `app/Http/Middleware/SetSyncActor.php`, `bootstrap/app.php` | New; on the `web` group |
| `app/Sync/Engine/ChangePackager.php`, `InboxProcessor.php`, `app/Sync/SyncInbox.php` | Carry and store the actor; refused changes flagged |
| `app/Sync/Engine/ChangeApplier.php` | Applies as the actor; ADR-006 delete and retirement refusal; no resurrection; delete stands; office retirement stands; PD-60 cascade and own-vessel refusal on a vessel; cloud deletes of vessel-owned records refused on a vessel (`vesselOwns()`); a permanent record created on both sides merges into one |
| `app/Sync/Engine/ReplicationRules.php`, `config/sync.php` | `vessel_deletes`, `retire_columns`, `office_only_columns` (PD-73); `sync_deletions` listed as bookkeeping; the rules check refuses retire columns on a column-merged table (PD-68 A) and office-only columns on a table a vessel never sends |
| `tests/Feature/Sync/Modules/DeletionSyncTest.php` | New, 20 tests through two servers (3 for PD-60, 2 for vessel-owned records, 3 for PD-68 A, 3 for the component switch, PD-73) |
| `tests/Feature/Sync/MySqlCaptureTest.php` | New MySQL test of the deletion record, actor, capture off and rollback |
| `tests/Feature/Sync/Engine/SyncConflictTest.php`, `SyncFlowTest.php` | Two delete tests rewritten to the approved behaviour (they expected a change to recreate a deleted record); one test allows its stand-in table explicitly |
| `tests/Concerns/BuildsSyncSchema.php`, `SyncEngineHarness.php` | Run the new migration |

**Tests (both checkpoints).**

| Run | Result |
|---|---|
| `VesselUserDeletionTest` (Checkpoint 10, with PD-73) | 56 passed |
| `ApprovedGuardsTest` (Checkpoint 10, items 4 to 7, the trash restore fix, the stock link guard, and PD-34 on the cloud) | 28 passed |
| `TrashGuardMySqlTest` (Checkpoint 10, MySQL) | 4 passed |
| `DeletionSyncTest` (Checkpoint 14, with PD-68 A and PD-73) | 20 passed |
| `CertificateCopySyncTest` (Checkpoint 15) | 6 passed |
| Full PHP suite, MySQL tests on (local throwaway MySQL 9.3), 2026-11-23 | **615 passed, 0 failed, 0 skipped** (after PD-34 on the cloud, 2026-11-24; 610 after PD-73; 604 after the stock link guard; 601 after PD-68 A; 600 after the PD-73 check; 488 passed, 2 failed, 10 skipped before Checkpoint 10) |
| Desktop `npm test` (no client change since) | 36 passed (last run before this round) |

**Not yet run:** any of this on Windows (Checkpoint 13), or against a
copy of production data.

**Still required before Checkpoint 14 is complete:** the rest of items 4
and 7, and items 6 and 8 (PD-36, PD-34 for edits, PD-59 A, PD-17), and the
tests that go with them: a sync interrupted at each step, the PD-68 cases
with skewed clocks, and MySQL cascade tests for every listed key.

### Trash restore fix (2026-11-23)

The trash's restore for a requisition failed on an undefined variable
(`$certificate`), after it had already reactivated the vessel and the
category. It is fixed in `HomeController::restore`:

- The trash lists requisitions with `ord_status` false. In today's code
  that is only an **unsubmitted draft**: no screen retires a submitted
  requisition, and none did in the first commit. Restoring a draft would
  make it a live requisition with no number or approval record, skipping
  submission, so it is refused with a message (422), and nothing else is
  changed first.
- A submitted requisition with `ord_status` false (only possible through
  data edits) is restored as intended: `ord_status` true, with its vessel
  and category.
- The screen shows the refusal message (`js/dataForm.js`).
- Tests: `ApprovedGuardsTest::test_trash_restore_*` (2).

The trash's requisition tab still lists unsubmitted drafts, as you
confirmed on 2026-11-23. Restoring one is refused with the message above.

### Offline vessel's stock links, office side (2026-11-23)

Your decision: the office may not delete the stock link (`vessel_items`
row) of a vessel that runs its own server. Found: no screen deletes a
link. The only office routes to one are the trash's permanent delete of
the item or of the vessel, and both are already refused while a link
refers to them (PD-60). Built:

- a test that both trash deletes are refused for an offline vessel's
  link, and that the link and its stock are untouched;
- a backstop: on the cloud, deleting an offline vessel's link through
  the model raises an error with the reason, never a silent skip. It
  follows the existing `navisuite.cloud.lock_offline_vessels` switch,
  and does not apply on the vessel server or to vessels without their
  own server. (Since 2026-11-24 this is the general PD-34 cloud check,
  which covers every vessel-owned record; `VesselItem` keeps only
  `lockedOnCloud()`.)
- Tests: `ApprovedGuardsTest` (3). A mutation check (guard removed)
  makes the backstop test fail.

A direct database delete outside the application is not covered here;
if one reaches the vessel, it is still refused on board and flagged
(PD-34, vessel side).

### Checkpoint 15, certificate copy history (2026-11-23)

Your instruction: synchronize the history safely, each copy an immutable
historical record, with unique ids, idempotent sync, the original author
and time, and integrity checks.

| Requirement | How it is met | Test |
|---|---|---|
| Unique ids | `sync_uuid` on every copy; a unique key on certificate and file; the same file listed on both sides (a copy from before the history began) becomes one record | `copies made on both sides while apart are all kept everywhere` |
| Immutable | Rule `append_only`: never changed or removed by sync; a change or delete that arrives is refused and flagged. The current copy is the certificate's own, so `replaced_at` is no longer written | `a copy record is never changed or removed by sync` |
| Original author and time | `uploaded_by` (as the user's UUID) and `uploaded_at` travel unchanged | `a copy made on board reaches the office with its author time and checksum` |
| Idempotent | A repeated event is a duplicate; the same row under a new event id changes nothing | `receiving a copy again changes nothing` |
| Integrity | SHA-256 and size recorded at upload; the file channel checks each copy against them in both directions; the certificate's `cert_copy`, which names the same file without a checksum, is now checked against the copy record's checksum too (`FileIndex::recordedChecksum`) | `a copy whose file does not match its checksum is never put in place`; `a damaged file on board is not taken by the office through either record` |
| Both sides uploading while apart | Both copies kept, with their files, on both servers; the certificate's current copy follows the certificate rule (newest, flagged) | `copies made on both sides while apart are all kept everywhere` |

Files: `config/sync.php` (registered, rule, `@requisitions`, file column);
`HomeController` (`recordCertificateCopy`, `certificateFileFacts`,
`getOneCertificate`); `js/dataForm.js`; `app/Sync/Engine/ChangeApplier.php`
(a permanent record created on both sides merges into one);
`app/Sync/Files/FileIndex.php`, `FileTransfer.php`, `FileServer.php` (the
recorded checksum); new migrations
`2026_11_26_130000_add_checksum_to_vessel_certificate_copies_table.php`
and `2026_11_26_130100_add_sync_columns_to_vessel_certificate_copies_table.php`
(additive, for your approval before deployment);
`tests/Feature/Sync/Files/CertificateCopySyncTest.php` (new, 6 tests);
`SyncMigrationTest` reads the new migration's table list.
