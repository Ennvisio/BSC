# NaviSuite offline-first: pending decisions

Status as of 2026-11-23 (deletion policy, ADR-006), after Checkpoints 1 to 8 on `feature/offline-sync`.
Nothing on the branch is committed.

**ADR-001 (the operating model) was approved on 2026-11-22, and confirmed
on 2026-11-23.** See [architecture-decisions.md](architecture-decisions.md).

On 2026-11-23, you also:

- added a clarification of the sync network;
- made PD-35 a pilot requirement;
- set directions for PD-13 and PD-59;
- asked for PD-11, PD-13, PD-58 and PD-59 to be reviewed again;
- approved **ADR-002**: vessels propose new master-data entries, and the
  office approves them. This resolves PD-58. Its design is in
  [adr-002-master-data-proposals.md](adr-002-master-data-proposals.md).
  (Superseded later the same day by ADR-003; see below.)
- asked for one consolidated roadmap:
  [implementation-roadmap.md](implementation-roadmap.md) (Checkpoints 9 to
  24). It found one new decision, PD-67 (how this branch reaches the
  cloud).

**Final clarifications, 2026-11-23.** You then decided:

- **PD-67:** the branch workflow (develop on `feature/offline-sync`, urgent
  fixes on `main`, merge when complete and tested, deploy separately after
  approval). Not a blocker.
- **ADR-003** (later amended by ADR-006), superseding ADR-002: vessel users create, update and delete
  master data and their own vessel's records directly, offline, with no
  office approval; deletions must sync safely. This settles PD-58, PD-63
  and PD-65, and withdraws PD-66.
- **PD-64:** there is no crew or manufacturer module, and none is added.
- **ADR-004 (PD-11):** only a Superadmin blocks, unblocks or resets
  passwords, on board or on shore.
- **ADR-005 (PD-25):** backups on the master PC only, vessel by vessel,
  verified, and a restore tested before the pilot.
- **PD-32:** ignored for now; not a blocker.

**Deletion policy and development, 2026-11-23 (later).** You then decided:

- **ADR-006**, amending ADR-003: **vessel users do not delete records**,
  and deletion is not replaced by retirement, archiving or deactivation.
  They still create and update master data without approval. As a result:
  - PD-68 B (fleet-wide effect of a vessel retirement) no longer applies;
    PD-68 A is narrowed to an office deletion meeting a vessel's change;
  - PD-61 is narrowed to office uploaders;
  - the new **PD-72** asks whether four actions that are not labelled
    "delete" count, and how a wrong entry gets removed.
- **Development is authorized**, checkpoint by checkpoint, following
  [implementation-roadmap.md](implementation-roadmap.md). An open item
  blocks only the step that needs it, not unrelated work.
- PD-04 is settled by ADR-003 and the existing behaviour (see below).

**Further decisions, 2026-11-23 (later).** You decided:

- **PD-71 A:** only a Superadmin creates users and changes user roles.
- **PD-71 B:** only a Superadmin creates, edits and maintains requisition
  categories, in the screens and on the server.
- **PD-71 C:** every office role may edit catalog items, for this phase.
  This is not a new permission to retire items: retiring stays as it is
  today for office roles, and refused to vessel users (ADR-006).
- **PD-72:** the three draft edits stay allowed for vessel users; vessel
  users may not deactivate PMS jobs.
- **PD-72 B:** no correction mechanism is needed, because the master and
  SRD can update requisition quantities, so a wrong entry is fixed by them.
  No action. (Option 2: no removal action is added and no office
  permission is widened.)
- **PD-06, PD-60, PD-61 and PD-62:** approved as recommended (option 1;
  option 1; option 2 with option 3; option 1). Built in Checkpoint 10.
- **PD-68 A:** the office retirement stays effective. A vessel's offline
  edit never restores or reactivates the retired record; the edit is kept
  in the conflict history and flagged for review, so neither side's
  history is lost. **PD-68 B** is not applicable, because ADR-006 already
  stops vessel users retiring or deleting records.

**PD-73, 2026-11-24.** You approved restricting the existing PMS
component "Inactive" switch for vessel users: the office keeps its
permission; vessel users can neither mark a component inactive nor
reactivate it, on the screen, on the server or through sync. Built; see
PD-73.

The decisions are in [architecture-decisions.md](architecture-decisions.md)
and their design in [adr-003-to-005-design.md](adr-003-to-005-design.md).
Their open details are PD-64, PD-69 and PD-70 (PD-68 was decided later
the same day).

The review of the code against ADR-001 is in
[adr-001-conformance-review.md](adr-001-conformance-review.md), and the
order of work in [implementation-roadmap.md](implementation-roadmap.md).
Implementation started on 2026-11-23; progress is recorded in the
roadmap's section 17.

This document gathers every open item recorded in the checkpoint documents,
the architecture audit, the roadmap and the ADR-001 conformance review
into one list:

- decisions needed;
- questions for the business;
- open issues;
- known limits that still need a yes or no;
- working assumptions waiting for confirmation.

Each item says what is assumed or built today, so you can see what changes
if you choose differently. Nothing here changes code. The recommendations
are suggestions only. Business rules stay yours to decide.

## How to read this

| Category | Meaning | Items |
|---|---|---|
| **Critical** | Decide before implementation continues or before any production use | 32 |
| **Important** | Decide before final testing (the Windows acceptance run in [e2e-test-plan.md](e2e-test-plan.md)) | 19 |
| **Optional** | Can be decided later; the current behaviour is safe meanwhile | 22 |
| | **Total** (nineteen closed, kept for their open details or for the record: decided PD-04, PD-05, PD-06, PD-11, PD-25, PD-35, PD-58, PD-60, PD-61, PD-62, PD-63, PD-65, PD-67, PD-68, PD-71, PD-72, PD-73; PD-32 deferred; PD-66 withdrawn) | **73** |

Changes in priority:

- **2026-11-22.** PD-34 and PD-35 moved from Important to Critical
  (ADR-001 principle 7). The new PD-58 and PD-59 are Critical.
- **2026-11-23.** PD-36 moved from Important to Critical, because the
  review found concrete cascade losses (PD-60). The new PD-60 and PD-61 are
  Critical; the new PD-62 is Important.
- **2026-11-23, after ADR-002.** PD-52 moved from Optional to Critical,
  because the review of proposals needs it. The new PD-63, PD-64 and PD-65
  are Critical; the new PD-66 is Important.
- **2026-11-23, consolidated roadmap.** The new PD-67 is Critical.
- **2026-11-23, final clarifications.** PD-52 moved from Critical to
  Optional (no review screen needs it). PD-32 (deferred) and PD-66
  (withdrawn) moved from Important to Optional. The new PD-68 to PD-71 are
  Critical. ADR-002 is superseded by ADR-003.
- **2026-11-23, deletion policy (ADR-006).** The new PD-72 is Critical.
  PD-04 is settled.
- **2026-11-23, later.** PD-71 and PD-72 decided. PD-06, PD-60, PD-61 and
  PD-62 approved as recommended. The new PD-73 is Important.
- **2026-11-23, PD-73 checked.** The component switch exists in the code,
  so PD-73 stays open. The restriction built on my reading of ADR-006 was
  removed: the switch keeps its existing permissions until you decide.
- **2026-11-24, PD-73 decided.** The switch is restricted for vessel users
  (option 1). Built with tests.
- **2026-11-23, later still.** PD-72 B decided: no action needed (option 2).
- **2026-11-23, owner's instructions.** PD-61: attachment deletion is out of scope for now, protection kept. PD-34: the vessel side for deletes is decided and built (a cloud delete of a vessel-owned record is refused, logged and flagged); edits stay open. PD-62: the copy history is synchronized.
- **2026-11-23, PD-68.** A decided: the office retirement stands, and the
  vessel's offline edit is kept in the conflict history and flagged. B is
  not applicable (ADR-006). You then confirmed that a full deletion follows
  the same rule, that unsubmitted drafts stay listed in the requisition
  trash, and that the office may not delete an offline vessel's stock link
  (PD-34).
- **2026-11-24, PD-34 confirmed.** The cloud must not delete vessel-owned
  records: the vessel rejects such deletes (built 2026-11-23), and the
  cloud now prevents office users from making them. Edits stay open.

Item numbers never change, so references stay valid. Within a section,
items moved there later follow the original ones.

Each item also belongs to one **group**, which says who acts next. Groups
are listed in "Work groups" below.

Every item has these parts:

- **Decision or question.** What exactly needs an answer.
- **Why it is needed.** What depends on it.
- **Options.** The realistic choices.
- **Recommended.** My suggestion and the reason. It is not a decision.
- **If not decided.** What happens if it stays open.
- **Today.** What the code or documents do now.
- **Source.** The original IDs, so you can trace each item back.
- **ADR-001.** For affected items: what the approved operating model settles,
  and what it leaves open.

Source abbreviations:

| Abbreviation | Document |
|---|---|
| CP1 to CP8 | [checkpoint-1-app-mode.md](checkpoint-1-app-mode.md) to [checkpoint-8-installers.md](checkpoint-8-installers.md) |
| Audit | [offline-architecture-audit.md](offline-architecture-audit.md) (C = cloud assumption, R = risk, Q = open question) |
| Roadmap | [offline-implementation-roadmap.md](offline-implementation-roadmap.md) |
| DR | [disaster-recovery.md](disaster-recovery.md) |
| CP5 Q1 to Q7 | The table in CP5 section 15.2 (not the same as Audit Q1 to Q15) |

---

## Decisions made

| Decision | Date | Record |
|---|---|---|
| ADR-001. Operating model: the vessel is the authority for its operational data; the office for master data, user administration and requisition approvals; the cloud is the hub | Approved 2026-11-22, confirmed 2026-11-23 | [architecture-decisions.md](architecture-decisions.md) |
| ADR-001 Clarification A. The vessel starts every connection; the cloud never connects to a vessel; the vessel pulls on a schedule; offline work continues; sync resumes by itself | 2026-11-23 | [architecture-decisions.md](architecture-decisions.md#clarification-a-synchronization-network-2026-11-23) |
| PD-35. Sync monitoring for the office is required for the first pilot | 2026-11-23 | PD-35 below |
| Direction on PD-13. An office rejection must not be silently overridden by a vessel action | 2026-11-23 | PD-13 below |
| Direction on PD-59. Attachments and important procurement documents must not silently replace one another; keep both versions, with review where appropriate | 2026-11-23 | PD-59 below |
| ~~ADR-002~~. Vessels propose new master data for office approval. **Superseded by ADR-003** | 2026-11-23 | [architecture-decisions.md](architecture-decisions.md#adr-002-vessels-propose-new-master-data-the-office-approves) |
| ADR-003. Vessel users create, update and delete master data and their own vessel's records directly, offline, with no office approval; deletions sync safely (tombstones, audit, no resurrection, no unsafe cascades); ownership boundaries kept; requisition approvals unchanged. **Amended by ADR-006: vessel users do not delete** | 2026-11-23 | [architecture-decisions.md](architecture-decisions.md#adr-003-vessel-users-maintain-master-data-and-delete-their-own-records-directly) |
| ADR-004 (PD-11). Only a Superadmin blocks, unblocks or resets passwords, on board offline or on shore; synced both ways; audited; credentials never in logs or ordinary sync records | 2026-11-23 | [architecture-decisions.md](architecture-decisions.md#adr-004-user-blocking-and-passwords-superadmin-only-on-board-and-on-shore) |
| ADR-005 (PD-25). Backups on the master PC only, vessel by vessel, with integrity checks, a documented procedure and a restore test before the pilot | 2026-11-23 | [architecture-decisions.md](architecture-decisions.md#adr-005-backups-on-the-vessels-master-pc-vessel-by-vessel) |
| PD-67. Branch workflow: develop on `feature/offline-sync`; urgent fixes on `main`, merged into the branch and retested; merge into `main` when complete and tested; deploy separately after approval | 2026-11-23 | PD-67 below |
| PD-64. No crew management or manufacturer management module exists or is added | 2026-11-23 | PD-64 below |
| PD-32. Laravel upgrade ignored for now; not a blocker | 2026-11-23 | PD-32 below |
| **ADR-006.** Vessel users do not delete records. Deletion is not replaced by retirement, archiving or deactivation. Vessel users still create and update master data. Ledgers are never deleted or overwritten. Amends ADR-003 | 2026-11-23 | [architecture-decisions.md](architecture-decisions.md#adr-006-vessel-users-do-not-delete-records) |
| Development authorized checkpoint by checkpoint, following the roadmap; work only on `feature/offline-sync`; no merge into `main` or deployment without separate approval | 2026-11-23 | [implementation-roadmap.md](implementation-roadmap.md) |
| PD-71 A and B. Only a Superadmin creates users, changes user roles, and maintains requisition categories, in the screens and on the server | 2026-11-23 | PD-71 below |
| PD-71 C. Every office role may edit catalog items, for this phase. Not a grant of retirement: existing office retirement permissions are kept | 2026-11-23 | PD-71 below |
| PD-72. Vessel users keep three draft edits (remove a draft line, unlink an attachment from a draft line, take a PMS part off a draft); they may not deactivate PMS jobs | 2026-11-23 | PD-72 below |
| PD-72 B. No mechanism for removing a wrong entry is added: the master and SRD update requisition quantities, so a wrong entry is fixed by them. No office permission is widened | 2026-11-23 | PD-72 below |
| PD-61 scope. No attachment deletion and no new permission; the current protection stays; deletion is out of scope for now | 2026-11-23 | PD-61 below |
| PD-34, deletes. The cloud does not delete vessel-owned records: office deletes are prevented on the cloud (confirmed 2026-11-24), and a delete that still arrives is refused on the vessel, logged and flagged; never silently deleted or cascade-deleted | 2026-11-23, confirmed 2026-11-24 | PD-34 below |
| PD-62, sync. The certificate copy history is synchronized: immutable records, unique ids, idempotent, original author and time, checked files | 2026-11-23 | PD-62 below |
| PD-04. Settled: both tests were wrong. Crew may open certificate categories (ADR-003); `/` sends a guest to sign-in | 2026-11-23 | PD-04 below |
| PD-06. The office does only PMS job approval for an offline vessel's own data; its equipment list is read-only on the cloud, as its PMS records are | 2026-11-23 | PD-06 below |
| PD-60. The trash's permanent delete is refused while anything refers to the record, and for any record of an offline vessel; at sync, a vessel never applies a delete that would cascade into its rows, nor of its own vessel row | 2026-11-23 | PD-60 below |
| PD-61. A file on a requisition line, a completed procurement stage or a consumption record is not deleted; any other delete hides the file and keeps it | 2026-11-23 | PD-61 below |
| PD-62. Every certificate copy is kept, with who uploaded it and when | 2026-11-23 | PD-62 below |

ADR-001 sets principles. It does not approve any detailed permission or
conflict rule, and the two directions set limits, not the full rules. The
effect on the items:

| Status | Meaning | Items |
|---|---|---|
| **Decided** | Answered; only named details are left | PD-04, PD-05, PD-06, PD-11, PD-25, PD-35, PD-58, PD-60, PD-61, PD-62, PD-63, PD-65, PD-67, PD-68, PD-71, PD-72, PD-73 |
| **Partly resolved** | Some options are ruled out or settled; you still choose among the rest | PD-03, PD-08, PD-13, PD-18, PD-20, PD-21, PD-34, PD-37, PD-59, PD-64 |
| **Guided** | A decision points to one option; your explicit confirmation is still needed | PD-10, PD-12, PD-14, PD-27 |
| **Open, affected** | New context or evidence; still fully open | PD-17, PD-19, PD-36, PD-38 |
| **New** | Found by the final clarifications | PD-69, PD-70 |
| **Deferred, withdrawn or not needed** | Kept so that the numbers stay valid | PD-32 (deferred), PD-52 (not needed), PD-66 (withdrawn) |
| **Not affected** | Unchanged | All other items |

---

## Work groups

Each item is in one group, by who acts next. Every technical item still
waits for your go-ahead. Being in the Technical group means no business
rule is needed, only your approval to start.

| Group | Who acts | Critical | Important | Optional |
|---|---|---|---|---|
| **Business decisions** | You approve a rule, a permission or a policy | PD-04, PD-05, PD-06, PD-09, PD-10, PD-11, PD-12, PD-13, PD-14, PD-34, PD-58, PD-59, PD-61, PD-63, PD-64, PD-65, PD-67, PD-68, PD-69, PD-70, PD-71, PD-72 | PD-17, PD-18, PD-19, PD-20, PD-21, PD-22, PD-23, PD-25, PD-27, PD-30, PD-33, PD-37, PD-62, PD-73 | PD-38, PD-39, PD-44, PD-45, PD-47, PD-48, PD-49, PD-50, PD-66 |
| **Technical fixes** | Developers implement, once approved | PD-03, PD-08, PD-35, PD-60 | PD-24 | PD-56 |
| **Infrastructure and testing** | IT, hosting, purchasing, and the test lab | PD-01, PD-02, PD-07, PD-15, PD-16, PD-36 | PD-26, PD-28, PD-29, PD-31 | PD-40, PD-41 |
| **Optional future improvements** | Nobody yet; revisit after the pilot | none | none | PD-32, PD-42, PD-43, PD-46, PD-51, PD-52, PD-53, PD-54, PD-55, PD-57 |

| Group | Items |
|---|---|
| Business decisions | 45 |
| Technical fixes | 6 |
| Infrastructure and testing | 12 |
| Optional future improvements | 10 |
| **Total** | **73** |

Some business items also contain technical work, which starts once you
decide. For example:

- PD-13 needs a change to one sync rule;
- PD-68 (ADR-003, ADR-006) needed deletion records and conflict rules for office deletions (built in Checkpoint 14, decided);
- PD-11 (ADR-004) needs an unblock action, an audit table and a sync guard;
- PD-61 needed a delete guard (built in Checkpoint 10).

The [implementation roadmap](implementation-roadmap.md) lists that work in
order, with tests.

---

## Summary

| ID | Item | Category | Group | Today | Status |
|---|---|---|---|---|---|
| PD-01 | Is the live cloud site exposing `.env`, dumps and logs? | Critical | Infra and testing | Unknown, never checked | Not affected |
| PD-02 | Secrets and database dumps tracked in git | Critical | Infra and testing | Still tracked | Not affected |
| PD-03 | Pages load libraries from public CDNs | Critical | Technical | 24 CDN references in 7 views | Partly resolved |
| PD-04 | Two existing tests contradict the code | Critical | Business | Settled; tests updated in Checkpoint 10 | Decided |
| PD-05 | May crew still write to the cloud once their vessel has a server? (decided) | Critical | Business | No: ship roles are read-only on the cloud | Decided |
| PD-06 | May the office edit an offline vessel's own data? (decided) | Critical | Business | PMS job approval only; PMS and equipment read-only on the cloud for offline vessels (built) | Decided |
| PD-07 | Production MySQL version, hosting and TRIGGER privilege | Critical | Infra and testing | Unknown | Not affected |
| PD-08 | Build the vessel data bootstrap tool next? | Critical | Technical | Not built | Partly resolved |
| PD-09 | Which business modules go live first? | Critical | Business | All off | Not affected |
| PD-10 | Requisition numbers and requisitions in progress at commissioning | Critical | Business | Vessel number kept; a clash goes to review | Guided |
| PD-11 | User blocking and passwords (decided: ADR-004) | Critical | Business | Any signed-in user can post to the user screens (F20); no unblock; no Superadmin on board | Decided |
| PD-12 | Deactivated users refused on the cloud too | Critical | Business | On by default, cloud included | Guided |
| PD-13 | Office rejection and ship action on the same stage | Critical | Business | Ship wins, flagged (ruled out); recommended: rejection wins at sync, workflow unchanged | Partly resolved |
| PD-14 | Vessel stock figure that does not match the office's | Critical | Business | Vessel's figure adopted, flagged | Guided |
| PD-15 | Who runs the Windows acceptance test, where and when | Critical | Infra and testing | Nothing has run on Windows | Not affected |
| PD-16 | Pinning the Windows runtimes and choosing the build machine | Critical | Infra and testing | `runtime.lock.json` is empty | Not affected |
| PD-17 | Certificates and surveys: ship or office authority? | Important | Business | Newest wins, flagged | Open, affected |
| PD-18 | The same master-data entry created on two sides: merge automatically? | Important | Business | Items merged by `item_key`; other exact duplicates wait | Partly resolved |
| PD-19 | Catalog scope on board | Important | Business | Vessel-linked items | Open, affected |
| PD-20 | Shore and support accounts on board (other than Superadmin) | Important | Business | Nobody from shore can sign in on board | Partly resolved |
| PD-21 | Photos and signatures changed on board | Important | Business | Overwritten by the office | Partly resolved |
| PD-22 | Office procurement files sent to vessels; file size limit | Important | Business | Sent; 64 MB per file, 50 MB per cycle | Not affected |
| PD-23 | Who may open an attachment | Important | Business | Any signed-in user, by id | Not affected |
| PD-24 | Uploaded images served without sign-in | Important | Technical | Served as static files | Not affected |
| PD-25 | Backups and disaster recovery (decided: ADR-005) | Important | Business | Scripts built; no set manifest, test restore or reporting | Decided |
| PD-26 | Are the master PCs' clocks trustworthy? | Important | Infra and testing | Assumed yes; warning at 120 s | Not affected |
| PD-27 | Connectivity and sync timing | Important | Business | Automatic every 5 minutes | Guided |
| PD-28 | Windows versions and hardware on board | Important | Infra and testing | Installers accept 10 1607+; plan assumes 22H2+ | Not affected |
| PD-29 | Code-signing certificate | Important | Infra and testing | Installers unsigned | Not affected |
| PD-30 | How new versions reach vessels | Important | Business | Technician or USB | Not affected |
| PD-31 | Where the cloud keeps its secrets | Important | Infra and testing | Linux key-file fallback | Not affected |
| PD-32 | Laravel 11 out of security support (deferred) | Optional | Future | Laravel 11; ignored for now | Deferred |
| PD-33 | Public self-registration (`/register`) | Important | Business | Open | Not affected |
| PD-34 | Should the app block edits to one-way data on the wrong side? | Critical | Business | Ship roles and the office's edits for offline vessels (PMS, equipment); cloud deletes of vessel-owned records prevented on the cloud and refused on board (built); ADR-003 settles four lists. Edits elsewhere open | Partly resolved |
| PD-35 | Sync monitoring for the office | Critical | Technical | Decided for the pilot; not built | Decided |
| PD-36 | Cascading deletes when modules are switched on | Critical | Infra and testing | Relied on, untested on live data | Open, affected |
| PD-37 | Accept the remaining security limits of Checkpoint 7 | Important | Business | Documented; item 2 now required by ADR-004 | Partly resolved |
| PD-38 | Ship and office changing an item's quantity at the same time | Optional | Business | Ship's value, flagged | Open, affected |
| PD-39 | Retention of sync history on the cloud | Optional | Business | Outbox 14 days; inbox, batches 30; logs 90 | Not affected |
| PD-40 | One cloud database or several | Optional | Infra and testing | One | Not affected |
| PD-41 | Source of the Windows CA bundle | Optional | Infra and testing | Mozilla bundle in the runtime | Not affected |
| PD-42 | MySQL series upgrades | Optional | Future | Manual | Not affected |
| PD-43 | LAN download of the client installer | Optional | Future | IT copies it | Not affected |
| PD-44 | Keep the session on single-user PCs | Optional | Business | Cleared on exit | Not affected |
| PD-45 | Edge as the documented emergency fallback | Optional | Business | Documented, not agreed | Not affected |
| PD-46 | bcrypt cost 10 or 12 | Optional | Future | 10 | Not affected |
| PD-47 | Fixed Asia/Dhaka time zone on every vessel | Optional | Business | Asia/Dhaka | Not affected |
| PD-48 | Client built as a shell over the existing web pages | Optional | Business | Built that way | Not affected |
| PD-49 | Fleet size and onboarding pace | Optional | Business | Unknown | Not affected |
| PD-50 | Phones and tablets on the ship LAN | Optional | Business | Manual certificate install | Not affected |
| PD-51 | Content-Security-Policy for the pages | Optional | Future | None | Not affected |
| PD-52 | A tool to merge duplicate records (not needed for the pilot) | Optional | Future | Manual | Not needed (ADR-003) |
| PD-53 | Moving a record from one vessel to another | Optional | Future | Old vessel keeps its copy | Not affected |
| PD-54 | Files left on disk after their record is deleted | Optional | Future | Kept | Not affected |
| PD-55 | Legacy attachments with unusual stored extensions | Optional | Future | Refused from vessels | Not affected |
| PD-56 | Mass assignment in `ServiceRequisitionApproval` | Optional | Technical | `$guarded = []` | Not affected |
| PD-57 | Changing sync settings of an installed server | Optional | Future | Answer file or script only | Not affected |
| PD-58 | Master data: who maintains it (decided: ADR-003, ADR-006) | Critical | Business | Crew create and update four lists directly; no vessel deletes (ADR-006) | Decided |
| PD-59 | Flagged conflict outcomes; keeping both document versions | Critical | Business | Only some flagged; certificate copy replaced | Partly resolved |
| PD-60 | Permanent deletes that cascade through vessel records (decided) | Critical | Technical | Refused while referenced or for an offline vessel; sync refuses cascades on board (built) | Decided |
| PD-61 | Deleting an attachment removes it from procurement stages (decided) | Critical | Business | Refused for evidence; otherwise hidden and kept (built). Deletion out of scope for now; no permission added | Decided |
| PD-62 | Replacing a certificate copy deletes the previous file (decided) | Important | Business | Every copy kept and synchronized as an immutable record (built) | Decided |
| PD-63 | Can a vessel's new entry be used at once? (decided: ADR-003) | Critical | Business | Yes, as decided | Decided |
| PD-64 | Which existing lists vessels maintain; the manufacturer fields | Critical | Business | No crew or manufacturer module; PMS lists super-admin only | Partly resolved |
| PD-65 | Office review of vessel master data (decided: none) | Critical | Business | No review, as decided | Decided |
| PD-66 | May a vessel edit or withdraw its own pending entry? (withdrawn) | Optional | Business | No pending entries exist | Withdrawn |
| PD-67 | How the offline branch reaches the cloud (decided) | Critical | Business | Decided workflow; nothing deployed | Decided |
| PD-68 | An office deletion meeting a vessel's change (decided; B not applicable) | Critical | Business | The office retirement or deletion stands; the vessel's edit is kept in the conflict and flagged (built in Checkpoint 14; not switched on) | Decided |
| PD-69 | Users changing their own password (`/change/password`) | Critical | Business | Every user can, cloud and board; newest wins | New |
| PD-70 | What a Superadmin may do on board; which accounts are sent | Critical | Business | No Superadmin on board | New |
| PD-71 | Server-side permission checks that rely on the menu today (decided) | Critical | Business | Users and categories: Superadmin only; item editing: every office role, retirement unchanged (built in Checkpoint 10) | Decided |
| PD-72 | Actions not labelled "delete" (decided) | Critical | Business | Draft edits allowed; PMS job deactivation refused to vessel users (built in Checkpoint 10). B: no action needed (option 2) | Decided |
| PD-73 | Vessel users marking a PMS component inactive (decided) | Important | Business | Restricted: the office only; vessel users refused on the screen, the server and at sync (built 2026-11-24) | Decided |

Appendix A lists known limits that need no decision unless you disagree.
Appendix B lists items raised earlier that are now resolved, so you can
see that nothing was dropped.

---

## Critical

### PD-01. Is the live cloud site exposing `.env`, dumps and logs?

**Source:** Audit R10 and C1, C14; CP1 to CP3 "still outstanding from Checkpoint 0".

- **Decision or question.** Please check whether the live cPanel site serves
  any of these files to the public:
  - `/.env`
  - `/ennvisio_srd.sql`, `/vesselitemreq.sql`, `/Archive.zip`
  - `/storage/logs/laravel.log`
  - `/vendor/`

  Is `APP_DEBUG` true on production? Do the cloud's `images/` folders already
  hold a file with a script extension (`.php`, `.phtml`) uploaded before
  Checkpoint 7? Then decide how to fix whatever is found.
- **Why it is needed.** The application uses the project root as its web
  root (`usePublicPath(dirname(__DIR__))` in `bootstrap/app.php`). So every
  file in the project can be reached over the web, unless the server refuses
  it. Before Checkpoint 7, uploads kept the extension the browser sent. A
  crafted image saved as `.php` may still be sitting on the cloud.
- **Options.**
  1. Check now. If anything is reachable, add deny rules on the host, remove
     the exposed files and rotate every credential they held.
  2. Option 1, plus move the cloud to a `public/` web root later. This is a
     planned change to the cloud's layout.
  3. Leave it until the offline work ships.
- **Recommended.** Option 1 now, and plan option 2. This check does not
  depend on the offline project. It takes minutes, and the exposure, if it
  exists, is already live.
- **If not decided.** Database passwords, the app key and full database
  dumps could be public. A dump includes every user's password hash. With
  `APP_DEBUG=true`, error pages show configuration. An old uploaded script
  could still be run.
- **Today.** Not checked. The vessel server is locked down: Apache serves
  only allowed paths, and PHP is off in `images/`. The cloud is not.

### PD-02. Secrets and database dumps tracked in git

**Source:** CP3 #10; rough-edges notes.

- **Decision or question.** How should the tracked secrets and dumps be
  removed from the repository? These are tracked today:
  - `.env`
  - `ennvisio_srd.sql`
  - `vesselitemreq.sql`
  - `database/vesselitemreq (2).sql`
- **Why it is needed.** `.gitignore` already ignores them, but they were
  committed before that rule existed, so git still tracks them. Anyone with
  the repository, or any copy of it, has the cloud's credentials and data.
  Releases already leave them out.
- **Options.**
  1. Rotate the cloud credentials, then stop tracking the files
     (`git rm --cached`). History keeps the old values, but they are no
     longer valid.
  2. Option 1, plus rewrite history to remove the files. This needs a force
     push, and every clone must be re-cloned.
  3. Leave as is.
- **Recommended.** Option 1 before the branch is merged or pushed anywhere
  new. Add option 2 if the repository has been, or will be, shared beyond a
  small trusted group. Rotation is what actually protects you; history
  rewriting only tidies up.
- **If not decided.** The credentials stay valid in every copy. The
  database dumps keep personal data and password hashes in the history
  forever.
- **Today.** Untouched. I have not removed anything, because it changes
  the repository and needs your approval.

### PD-03. Pages load libraries from public CDNs

**Source:** Audit C2; CP1 #1 and #7; CP3 #3; CP4 12.1 (called "the most
important remaining item").

- **ADR-001: partly resolved.** Principle 5 requires the vessel to work without internet, so local copies are required and option 3 is ruled out. Still open: option 1 or option 2, and your approval to change the cloud's layout files (roadmap Checkpoint 12).
- **Decision or question.** May jQuery, Bootstrap, DataTables, pdfmake,
  Popper and the icon fonts be copied into the application and served
  locally? There are 24 references in 7 views. May the cloud serve them
  locally too?
- **Why it is needed.** Without internet these libraries do not load, so
  many screens do not work on board, in the client or in a browser. This is
  the main blocker for real offline use.
- **Options.**
  1. Serve local copies in both modes, with the same versions the CDN serves
     today. One code path.
  2. Local copies in vessel mode only; the cloud keeps the CDN. Two code
     paths in the layouts.
  3. Keep the CDN. *Ruled out by ADR-001 (principle 5).*
- **Recommended.** Option 1. The pages behave the same, because the
  versions are the same. The cloud also stops depending on third-party
  hosts, and a Content-Security-Policy (PD-51) becomes possible. It is a
  visible change to cloud files, which is why it needs your approval.
- **If not decided.** The vessel server cannot be used without internet,
  which defeats the project.
- **Today.** All CDN references remain.

### PD-04. Two existing tests contradict the code

**Source:** CP8 C8-1; CP1 to CP3 "9 failing tests" (7 are now fixed).

- **Decision or question.** For each of the two tests, which is right, the
  test or the code?
  1. `FleetOverviewDashboardTest::test_ship_crew_cannot_open_certificate_categories`
     expects ship crew to get 403. The code deliberately allows them
     (`isShipCrew()`), and crew do maintain certificate categories today
     (CP1 #2).
  2. `ExampleTest::testBasicTest` expects `/` to answer 200 to a guest. The
     application redirects a guest to the sign-in page.
- **Why it is needed.** The production build gate (`deploy/build-installers.sh`)
  refuses to build while any test fails, as you asked ("do not deploy to
  production without passing the test suite").
- **Options.** For each test:
  - (a) the code is right: update the test;
  - (b) the test is right: change the behaviour.
- **Recommended.**
  - Test 1: (a), unless crew should lose that function. Changing the code
    would remove something crew use now.
  - Test 2: (a). A redirect to sign-in is normal for an internal system;
    the test is Laravel's unchanged sample.
  - Either way, this is your call on access rules.
- **If not decided.** No production installer can be built. Only test
  builds, which must not be installed on a ship.
- **Settled (2026-11-23).** Test 1: ADR-003 lets vessel users maintain
  certificate categories, so crew opening the list is approved behaviour,
  and the test was wrong. Test 2: `/` sends a guest to the sign-in page by
  design (`routes/web.php`), so the test was wrong. Both tests are updated
  in Checkpoint 10 to assert the approved behaviour. If you meant
  otherwise for either, say so and the behaviour changes instead.
- **Today.** Before Checkpoint 10: 488 passed, 2 failed, 10 skipped (the
  skipped tests need MySQL).

### PD-05. May crew still write to the cloud once their vessel has a server? (decided)

**Source:** Audit Q1 and R1; Roadmap 13 Q1.

- **ADR-001: resolved, option 1.** Principles 1 and 7: the vessel's own server is the one place its operational data is written, and the cloud copy is not a second author. One detail stays open: the written procedure for a lost vessel server (option 3). It is an exception, so it needs your approval.
- **Decision or question.** Once a vessel has its own server, may its crew
  still sign in to the cloud and raise requisitions, record consumption or
  update PMS for that vessel?
- **Why it is needed.** Two places writing the same vessel's stock and
  requisitions causes stock divergence and requisition number clashes
  (R1). The whole sync design assumes one author per vessel.
- **Options.**
  1. No. The cloud is read-only for that vessel's ship roles (built). **Chosen by ADR-001.**
  2. Yes, both places, with conflict review. Much more conflict handling,
     and stock cannot be reconciled reliably. *Ruled out by ADR-001.*
  3. Normally no, but the office may switch a vessel back to cloud working
     if its server is lost (`navisuite:vessel-offline {id} --disable`
     exists for this).
- **Recommended.** Option 1, with option 3 as the written procedure for a
  lost server. It keeps one source of truth per vessel and needs no new
  code.
- **If not decided.** The lock stays on, which may surprise crew who use
  the cloud from shore. If someone turns it off, the risks in R1 apply.
- **Today.** Option 1. The lock takes effect only for vessels marked
  offline-enabled (`offline_enabled_at`), and it can be switched off with
  `navisuite.cloud.lock_offline_vessels`.

### PD-06. May the office edit an offline vessel's own data? (decided)

**Source:** Audit Q2; CP6 C6-4; CP1 #3; Roadmap 13 Q2.

- **ADR-001: partly resolved.** Principles 1 and 7 rule out option 2 as a general rule: the office does not edit a vessel's operational data directly. Still open:
  - Does office PMS job approval stay? Principle 3 lists only requisition approvals (finding F8).
  - Is the equipment list vessel operational data? Super-admin can still edit an offline vessel's list on the cloud today (finding F2).
  - For changes the office needs: option 1 or option 3.
- **Decision or question.** May super-admin or other office users keep
  editing a commissioned vessel's PMS tree, equipment list or stock on the
  cloud?
- **Why it is needed.** These records are vessel-owned ("one-way, vessel to
  cloud"). An office edit to them is overwritten by the vessel's next change
  to that row (CP5 15.3, "owner wins").
- **Options.**
  1. PMS job approval only. Other edits are made on board (provisional).
  2. The office may edit; changes go down with conflict review. This needs
     those tables to become two-way. *Ruled out as a general rule by ADR-001.*
  3. The office raises a change request, and the crew applies it on board.
- **Recommended.** Option 1 for go-live. It keeps the rules simple for the
  first vessels. After ADR-001, option 2 for any table would need an
  explicit exception that you approve.
- **If not decided.** Office edits to those tables can be lost without
  warning.
- **Decided (2026-11-23).** Option 1, as recommended. For a vessel that
  runs its own server, the office's only change to its operational data is
  PMS job approval, which stays (finding F8 confirmed). Other edits are
  made on board.
- **Today.** Built in Checkpoint 10. For vessels marked offline, the cloud
  refuses super-admin's PMS edits (`PmsAccess`, as before) and now also
  equipment adds, edits, removals and sheet uploads (`EquipmentController`;
  the screen shows the list read-only). Tests:
  `ApprovedGuardsTest::test_pd06_*`. Office edits to other vessel-owned
  tables are covered by PD-34, still open.

### PD-07. Production MySQL version, hosting and TRIGGER privilege

**Source:** Audit Q8, R4 and C9; CP2 #1; CP3 #8; Roadmap 13 Q8.

- **Decision or question.** For the production cloud:
  - Which MySQL version runs?
  - Is it shared cPanel hosting?
  - Does the database user have `TRIGGER`?
  - With binary logging on, is `SUPER` or `log_bin_trust_function_creators=1`
    available?
  - Can `schedule:run` run from cron?
  - What limits apply to request time and upload size?
- **Why it is needed.** Cloud change capture uses MySQL triggers, installed
  with `sync:triggers install`. The vessels run MySQL 8.4 or 8.0. A dump
  from 5.7 loads into 8.x, but not the other way round.
- **Options.**
  1. Keep the current host, if it allows triggers and cron.
  2. Move the cloud to a VPS or managed MySQL that you control.
  3. Build the application-level capture fallback for the cloud (not built).
- **Recommended.** Find out first. If triggers are refused, choose option 2
  over option 3. The fallback cannot see the 16 or more query-builder
  writes that bypass Eloquent (R2), so changes would be missed silently.
- **If not decided.** The cloud cannot record its changes, so nothing
  travels from office to vessel. Sync cannot go live.
- **Today.** Tested on MySQL 9.3 locally. The SQL is valid on 5.7.2 and
  later.

### PD-08. Build the vessel data bootstrap tool next?

**Source:** CP7 C7-5; CP3 #2; CP5 15.1 #1; CP6 section 10; CP8 section 6;
DR section 5.4; Roadmap checkpoint 3.

- **ADR-001: partly resolved.** Principle 5 needs a vessel that starts with its own data and then works alone. So the tool is required, and options 2 and 3 are not acceptable for production. Still open: whether it is the next piece of work, and its scope.
- **Decision or question.** Should the vessel-only export and bootstrap
  tool be the next piece of work?
- **Why it is needed.** Sync assumes each vessel starts as a copy of the
  cloud with the same UUIDs. Today the only way to load data is a full
  cloud dump. A full dump holds every vessel's data, including other crews'
  password hashes. The node must also be registered before the copy is
  taken, so the pull cursor starts at the right place. After a restore from
  backup, a vessel cannot get back its one-way records (stock, PMS) without
  this tool.
- **Options.**
  1. Build it next, before any business module is switched on.
  2. Commission by hand with a full dump (not acceptable for production). *Ruled out by ADR-001 for production.*
  3. Commission by hand with a manually filtered dump. Error-prone, and it
     must be repeated for every vessel. *Ruled out by ADR-001 for production.*
- **Recommended.** Option 1. Every other item about going live depends on
  it, including PD-10's carry-over of requisitions in progress.
- **If not decided.** No vessel can be commissioned safely, and a lost
  vessel server cannot be fully rebuilt from the cloud.
- **Today.** Not built. `install-server.ps1 -ImportSqlDump` loads a dump
  you supply.

### PD-09. Which business modules go live first?

**Source:** CP5 Q1 and 15.1 #2; CP6 (modules off by default).

- **Decision or question.** Which modules should be switched on first, and
  in what order? The modules are `@master`, `@inventory`, `@pms`,
  `@requisitions` and `@attachments`. Attachments need requisitions and
  inventory.
- **Why it is needed.** Each module has rules in `config/sync.php` that
  depend on the business answers in this document. Each needs to be
  switched on and tested on Windows.
- **Options.**
  1. In stages, on one pilot vessel first:
     master data, then inventory and PMS, then requisitions, then
     attachments.
  2. All at once on a pilot vessel.
  3. Reference lists only, for a first pilot.
- **Recommended.** Option 1. Each stage is small enough to check, and an
  answer you change later affects fewer records. Before the first module
  is switched on:
  - the dashboard (PD-35) must be live;
  - the bootstrap (PD-08) must exist;
  - the delete safety (PD-60, PD-68) and the cascade tests (PD-36) must be done;
  - a backup restore must have been tested on board (PD-25).

  See [implementation-roadmap.md](implementation-roadmap.md) Checkpoint 24.
- **If not decided.** Only reference lists travel (cloud to vessel). The
  vessel server works as an island.
- **Today.** Every business module is off (`SYNC_TABLES` without the `@`
  groups).

### PD-10. Requisition numbers and requisitions in progress at commissioning

**Source:** Audit Q12 and C5; CP6 section 5 (duplicates).

- **ADR-001: guided.** Principles 1 and 2 put requisition creation on board, which points to option 1 for the numbers. Please confirm. What happens to requisitions in progress at commissioning is still open.
- **Decision or question.**
  - Is the requisition number (`req_no`) created on board final?
  - What happens to requisitions already in progress on the cloud on the
    day a vessel gets its server?
- **Why it is needed.** The number is a per-vessel counter. It clashes if
  both sides create requisitions for the same vessel. Requisitions part-way
  through approval at the switch-over must continue somewhere, without
  being entered twice.
- **Options for numbers.**
  1. The vessel's number is final. The cloud never creates requisitions for
     that vessel (follows PD-05 option 1).
  2. Prefix the numbers by origin, for example `V-`. This changes the
     numbering people see.
  3. A provisional number on board, and the final one given by the cloud on
     arrival.
- **Options for requisitions in progress.**
  - (a) The bootstrap (PD-08) copies them, and they continue on board.
  - (b) Finish them on the cloud before a cut-off time.
- **Recommended.** Numbers: option 1, if you choose PD-05 option 1. In
  progress: (a), with a stated cut-off time after which crew stop using the
  cloud for that vessel. No re-entry, and no clash.
- **If not decided.** Duplicate numbers block requisitions for review, and
  work in progress can be stranded or entered twice.
- **Today.** A number already used in the office blocks the incoming
  requisition and raises a `duplicate_key` conflict. It is never renumbered
  automatically.

### PD-11. User blocking and passwords (decided: ADR-004)

**Source:** CP5 Q3; Audit Q4 and C8; CP1 #6; Roadmap 13 Q4; CP7 (deactivation, sessions); conformance findings F16, F17, F18 and F20; **decided on 2026-11-23 as [ADR-004](architecture-decisions.md#adr-004-user-blocking-and-passwords-superadmin-only-on-board-and-on-shore).**

- **Decided (2026-11-23).**
  - Only a Superadmin blocks or unblocks a user, or changes or resets a user's password.
  - The master PC cannot, and neither can the master or any ordinary vessel user.
  - A Superadmin on board can do it offline; the change is saved locally and syncs up when the link returns.
  - A shore Superadmin can do it too; the change syncs down to the vessel.
  - Who did it, and when, is kept. Conflicts are handled safely. Passwords never appear in logs or ordinary sync records.
- **Withdrawn.** The earlier options for a master's reset on board, office one-time codes, and a suspension by the master. They conflict with the decision.
- **Found in the code** (design section 5.2):
  - `/user/store`, `/user/update` and `/user/delete` check only that someone is signed in. On the cloud **today**, any signed-in user can set another user's password, role or block by posting to them (F20). The menu hides them from everyone but super-admin. Fixing this enforces the decision, so it needs no further business answer; it is a live fix (roadmap Checkpoint 10, released on `main` as PD-67 allows).
  - "Delete user" is a block (`status = false`). There is **no unblock** action; one is needed.
  - No Superadmin can sign in on board today: shore users' hashes are withheld, and only this vessel's crew may sign in.
- **Still open: the conflict rules (recommended, for your confirmation).**
  - Block on one side and unblock on the other while apart: **the block wins**, flagged. It is the safe outcome, and a Superadmin can unblock again.
  - A Superadmin reset against the user's own change: **the reset wins**, flagged. (Only relevant if PD-69 keeps self-service changes.)
  - Two Superadmin resets: **the newest wins**, flagged.
- **Related open items.** PD-69 (self-service `/change/password`), PD-70 (other Superadmin powers on board, and which accounts are sent). PD-71 (creating users and changing roles) is decided: Superadmin only.
- **Technical safeguards** (no business rule needed, only approval to implement):
  - an audit event for every block, unblock and reset, without any password (`user_admin_events`, an additive table for your approval);
  - a change to another user's password or status is accepted from the other side only with such an event by a Superadmin;
  - the hash kept out of `sync_conflicts`, `sync_logs` and `sync_row_bases` (PD-37 item 2);
  - the shore placeholder hash replaced by a value that can never verify (F17);
  - a test that no log line contains a hash.
- **Risk to accept.** Super-admin password hashes will be on every master PC. Someone who takes a PC can try to guess them offline. Recommended: long super-admin passwords, bcrypt cost 12 (PD-46), disk encryption on the master PC.
- **If the open details are not decided.** The recommended conflict rules apply in the build, and are shown to you before the pilot.
- **Today.** Newest password wins, both ways; status travels down only; no Superadmin on board; no unblock. See [implementation-roadmap.md](implementation-roadmap.md) Checkpoint 16.

### PD-12. Deactivated users refused on the cloud too

**Source:** CP7 C7-2.

- **ADR-004 (2026-11-23).** Blocking is now a Superadmin action, on board and on shore. This item only decides whether the cloud also refuses a blocked user at sign-in. Option 1 matches ADR-004.
- **ADR-001: guided.** Deactivating a user is user administration, which principle 3 gives to the office. Option 1 applies the office's decision everywhere. It still changes existing cloud behaviour at the next deployment, so please confirm it explicitly.
- **Decision or question.** Should a deactivated user be refused at sign-in
  on the cloud as well as on board, or only on board?
- **Why it is needed.** This changes existing cloud behaviour. Before
  Checkpoint 7, the cloud did not check the flag at sign-in. The change
  takes effect on the cloud at the next deployment.
- **Options.**
  1. Both (today's default, `APP_BLOCK_DEACTIVATED_USERS=true`).
  2. On board only (set it to `false` on the cloud).
- **Recommended.** Option 1, if deactivating a user is meant to remove
  their access. Before deploying, check that no account still in use on the
  cloud is marked deactivated.
- **If not decided.** On deployment, anyone currently signing in with a
  deactivated account loses cloud access without warning.
- **Today.** Option 1. The rest of deactivation, offline, is in PD-11 (question C).

### PD-13. Office rejection and ship action on the same stage

**Source:** CP6 C6-1 and section 4; CP5 Q2; conformance finding F7; your direction of 2026-11-23; your instruction of 2026-11-23 to keep the requisition approval workflow unchanged.

- **Your direction (2026-11-23).** An office rejection must not be silently overridden by a vessel action. Today's provisional rule does exactly that: the ship's version stands, flagged. So option 1 is ruled out, and the rule must change before `@requisitions` is switched on.
- **Your instruction (2026-11-23).** Keep the existing requisition approval workflow unchanged. That rules out the hold I recommended earlier (option 4), because it added a held state and blocked approval actions.
- **Decision or question.** Suppose that, while apart, an office holder rejects a requisition at a stage where the ship also acted. Which result should sync produce?
- **Why it is needed.** Today's code has two such cases (CP6 section 4):
  - a superintendent rejects while the master forwards;
  - a superintendent rejects while the raiser confirms receipt.
- **Options.**
  1. The ship's version wins, flagged (today). *Ruled out by your direction.*
  2. **The office rejection wins at sync, flagged.** The ship's action is kept in full in the conflict record.
  3. Hold: nothing proceeds until a person decides. *Ruled out: it changes the workflow.*
  4. Rejection stands, and the requisition is held. *Ruled out: it changes the workflow.*
- **Recommended: option 2, as a sync rule only.**
  - Both servers show the requisition as rejected, with the reason, exactly as the existing workflow shows any rejection.
  - The ship's action (who, role, when, what) is kept in the conflict record and appears on the office review screen (PD-35).
  - The crew continue as they do today after a rejection: they resubmit through the existing workflow.
  - **Receipt case.** If the ship's action was confirming receipt, the stock movement already written on board stays, because the goods are on board and the ledger is a record of fact. The review shows "received after rejection" so that the office can settle it with the crew, outside the system as today.
  - No new state, no blocked actions, no new role.
- **Question for you.** Please confirm option 2, and that the receipt case is settled by the office outside the system.
- **If not decided.** `@requisitions` must stay off, because the provisional rule breaks your direction.
- **Today.** Option 1 (built and tested, switched off). Option 2 is a change to the sync rule only. See [implementation-roadmap.md](implementation-roadmap.md) Checkpoint 18.

### PD-14. Vessel stock figure that does not match the office's

**Source:** CP6 C6-2.

- **ADR-001: guided.** Stock is vessel operational data (principles 1 and 2), which supports option 1. Please confirm: the rule is not approved automatically.
- **Decision or question.** When a stock movement from the vessel does not
  add up with the office's balance, should the system adopt the vessel's
  figure or keep the office's?
- **Why it is needed.** Stock is counted on board. The office balance can
  differ after a correction or an earlier divergence.
- **Options.**
  1. Adopt the vessel's figure, flagged for review (provisional).
  2. Keep the office's figure, flagged; the vessel is corrected.
  3. Hold the movement until a person decides.
- **Recommended.** Option 1. The ship holds the physical stock, so its
  count is usually the one to trust. The review flag keeps the office
  informed.
- **If not decided.** The provisional rule applies once inventory is
  switched on.
- **Today.** Option 1 (built, module off). Option 2 is already available as a setting (`SYNC_STOCK_ON_MISMATCH=keep`).

### PD-15. Who runs the Windows acceptance test, where and when

**Source:** CP3 #1; CP4 12.3; CP5 15.1 #3; CP7 section 9; CP8 section 6;
[e2e-test-plan.md](e2e-test-plan.md).

- **Decision or question.** Who runs [e2e-test-plan.md](e2e-test-plan.md),
  on which machines, and by when? What result counts as a pass? For example,
  all P1 cases pass and no P2 case loses data.
- **Why it is needed.** None of the Windows parts has ever run on Windows.
  That covers every PowerShell script, both installers, the services,
  DPAPI through FFI, discovery on a real LAN, backup and restore, and the
  startup check. Everything so far was tested on macOS.
- **Options.**
  1. A lab: one server PC, three client PCs (one with the oldest Windows
     found on the ships, PD-28), a switch, and a way to cut the internet.
  2. Pilot directly on one vessel.
- **Recommended.** Option 1, before any vessel. Expect small fixes on the
  first run. A failure at sea is expensive to fix.
- **If not decided.** The first real run happens on a ship.
- **Today.** The test plan is written; nothing has run.

### PD-16. Pinning the Windows runtimes and choosing the build machine

**Source:** CP8 C8-3 and section 6.

- **Decision or question.**
  - Who builds releases: a CI machine, or one developer's computer?
  - Who downloads and pins the Windows runtimes (Apache, PHP, MySQL, WinSW)?
  - Where are the pinned files and `SHA256SUMS` kept for audits?
- **Why it is needed.** `deploy/windows/runtime.lock.json` is empty. The
  production server installer cannot be built until each runtime has a
  pinned version and checksum.
- **Options.**
  1. A dedicated build machine or CI job, with the runtimes and checksums
     archived in access-controlled storage.
  2. One developer's computer, with a Windows PC used once to pin the
     runtimes.
- **Recommended.** Option 1 if more than one person will release. Either
  way, keep the lock file in git and the runtime archives somewhere
  backed up. That way any release can be rebuilt and checked.
- **If not decided.** No production server installer.
- **Today.** Test builds only, with placeholder runtimes.

### PD-34. Should the app block edits to one-way data on the wrong side?

**Source:** CP5 15.3 (owner wins on one-way tables); CP6 section 10.

- **ADR-001: partly resolved, and moved to Critical (2026-11-22).** Principle 7 rules out option 3. You still choose, table by table, between blocking (option 1) and warning with a reviewed conflict (option 2). The review found one concrete case, the equipment list on the cloud (finding F2), and two general gaps (F3 and F4).
- **ADR-003 (2026-11-23).** Vessel users now maintain budget groups, certificate categories, catalog items and item groups directly, so those four lists are not wrong-side writes on either side. The office-maintained lists (users, vessels, requisition categories, PMS lists) stay blocked on board, and a change that still arrives from a vessel is refused and flagged. The ADR-002 rule for these lists is withdrawn. Other tables remain open here.
- **Partly decided (2026-11-23, later): deletes of the vessel's own records.** Your instruction: block cloud-originated deletion of vessel-owned records on the vessel; log and flag the rejected event; never silently delete or cascade-delete the record. Built (Checkpoint 14):
  - On the vessel, a delete arriving from the cloud is not applied when the row is the vessel's own record: a vessel-owned operational table (stock and its item links, consumption, PMS, equipment), a table only the vessel creates rows in (requisitions, their lines and approvals, PMS jobs), a row the vessel keeps in a shared table (requisition form Part A, Receipt & Verification), or a file uploaded by its crew.
  - The delete is refused, recorded in the sync log with the pull, and flagged as a `refused` conflict for review. Nothing is deleted on board, so no deletion record is written; a delete that would cascade into rows held on board is refused the same way (PD-60).
  - Records both sides edit (certificates and surveys, PD-17) and shared master data are not counted as vessel-owned.
  - Tests: `DeletionSyncTest` (a stock link, and a submitted requisition with its lines).
  - **Cloud side (decided, confirmed 2026-11-24).** Your decision: the cloud must not delete vessel-owned records; the vessel rejects such a delete, and the cloud prevents office users from deleting them in the first place. Built:
    - **One rule for both sides.** `App\Sync\VesselOwnership` decides whether a row is a vessel's own record, from the sync definitions (the four kinds above). The vessel's sync refusal now uses it too, so the two sides cannot disagree.
    - **Every model delete on the cloud is checked.** For a vessel that runs its own server, deleting one of its own records through the application's models (soft or permanent, from any screen, command or later code) is refused before anything changes, with a 403 and the reason on screen. Rows the office keeps (form Parts B and C, other procurement steps), files uploaded in the office, shared master data, and certificates and surveys (PD-17) are not affected.
    - **Every office screen checked.** Each office route that removes something already refuses an offline vessel's own records through an existing guard: the trash (PD-60), the equipment lock (PD-06), the PMS edit rule (super-admin cannot edit an offline vessel's PMS), the file delete (uploader only), and the requisition draft routes (ship roles only). A test walks them as super-admin and as an office approver role.
    - **The CFM import** (`pms:import-cfm`): its `--remove-unmatched` option is refused on the cloud for a vessel that runs its own server, before anything is imported, because it removes components with a bulk soft delete that the model check does not see. The import itself, and the option for other vessels and on the vessel's own server, are unchanged.
    - **Not affected.** Vessels without their own server (the cloud is their only copy), the vessel's own server, and the existing `navisuite.cloud.lock_offline_vessels` switch, which turns this off together with the other offline locks.
    - **Not covered.** A delete written directly in the database, outside the application. If one reaches the vessel, it is refused on board and flagged, as above.
    - Tests: `ApprovedGuardsTest::test_pd34_*` (5), with the stock link tests. A mutation check (the cloud check removed) makes 3 of them fail.
  - **Office side for stock links (decided 2026-11-23).** The office may not delete the stock link (`vessel_items` row) of a vessel that runs its own server, so the two sides never differ over one. No screen deletes a link; the trash refuses to delete an item or a vessel while a link refers to it (PD-60), which now has its own test; and the `VesselItem` model refuses such a delete on the cloud with an error, as a backstop for later code. The vessel server, and vessels without their own server, are not affected. Tests: `ApprovedGuardsTest` (3).
  - The rest of this item (blocking other wrong-side **edits** in the screens, table by table) is still open.
- **Decision or question.** Should the application refuse an edit to a
  one-way table on the side that does not own it? Examples are a cloud edit
  to a vessel's stock, or a vessel edit to a reference list.
- **Why it is needed.** Such an edit is overwritten by the owner's next
  change to that row, without warning.
- **Options.**
  1. Block it in the screens, with a clear message.
  2. Allow it, and warn.
  3. Leave as is. *Ruled out by ADR-001 (principle 7).*
- **Recommended.** Option 1. Together with PD-06 it makes ownership visible
  to users.
- **If not decided.** Silent loss of such edits.
- **Today.** Option 3 for edits. Crew writes are locked on the cloud
  (PD-05), and so are the office's PMS and equipment edits for offline
  vessels (PD-06) and its trash deletes of their records (PD-60). A cloud
  delete of a vessel's own record is refused on board (above).

### PD-35. Sync monitoring for the office (decided: required for the first pilot)

**Source:** CP1 #5; Roadmap checkpoint 8; conformance findings F10 and F19; your decision of 2026-11-23.

- **Decided (2026-11-23).** Sync monitoring is required for the first pilot. For every vessel, the office must see:
  - its connectivity (last contact);
  - its last successful sync;
  - pending events;
  - failures;
  - conflicts that need review.
- **Decision or question (what is still open).**
  - Which office roles may see the dashboard, and which may resolve conflicts?
  - Should the office be alerted, for example by email when a vessel has not synced for a set number of hours? What is the threshold?
  - Is an on-board status page or banner also wanted for the pilot? Your decision covered the office only.
- **Why it is needed.** Conflicts that are flagged for review (PD-13, PD-14, PD-17, PD-59, PD-68, and the user administration conflicts of PD-11) need someone who can see them and act. Today they are visible only through `php artisan sync:conflicts`.
- **What the cloud already records.**
  - Last contact: `sync_nodes.last_seen_at`.
  - Batches and their results: `sync_batches`.
  - Conflicts: `sync_conflicts`.
  - Logs: `sync_logs`.
  - Files: `sync_files`.
  - Its own backlog for each vessel: the outbox, beyond that vessel's cursor.

  **Missing:** how many changes wait **on board** (F19). The vessel must report this in its handshake. That is an additive protocol change on both sides, and may need one column or some `sync_state` rows on the cloud.
- **Options (for the on-board part only).**
  1. Office dashboard and review screen, plus an on-board banner.
  2. Office dashboard and review screen only.
- **Recommended.** Option 2 for the pilot, as you decided. Add the banner if crew need to know whether the office has their changes.
- **If not decided.** The open details default to: super-admin only, no alerts, no on-board banner.
- **Today.** Not built. Commands only (`sync:status`, `sync:conflicts`, `sync:health`) and `/health`. See [implementation-roadmap.md](implementation-roadmap.md) Checkpoint 19.

### PD-58. Master data: who maintains it (decided: ADR-003, superseding ADR-002)

**Source:** ADR-001 principle 3; conformance findings F1 and F14; CP1 #2; Audit section 13 class B; ADR-002 (2026-11-23, superseded); **decided on 2026-11-23 as [ADR-003](architecture-decisions.md#adr-003-vessel-users-maintain-master-data-and-delete-their-own-records-directly).**

- **Amended by ADR-006 (2026-11-23).** Vessel users create and update these lists, but do **not** delete, retire, archive or deactivate entries. The office still can, under its existing permissions.
- **Decided (2026-11-23).** Vessel users create, update and delete the existing master-data lists directly, without office approval, and use their changes at once, offline included. Changes and deletions sync when the link returns. There is no pending state, no proposal review, no approval queue and no new approval role. The requisition approval workflow is unchanged.
- **Superseded.** ADR-002 (vessels propose, the office approves) and its design, [adr-002-master-data-proposals.md](adr-002-master-data-proposals.md). Nothing of it was built.
- **The lists** (design section 1.1): budget groups, certificate categories, catalog items (with their free-text manufacturer) and item groups are maintained by vessels and the office together. The other lists stay office-maintained under the existing permissions; whether any of them should change is PD-64.
- **Still open.**
  - **PD-64:** which other existing lists are "applicable".
  - ~~PD-68 A~~ (an office deletion against a vessel's change): decided 2026-11-23; the office's retirement stands and the vessel's edit is kept and flagged. (PD-68 B no longer applies.)
  - ~~PD-71~~ (server-side permission checks, F14): decided 2026-11-23; every office role edits items for this phase.
  - **PD-18:** automatic merging of exact duplicates.
- **Today.** Crew create and update these four lists directly on the cloud, and on their own vessel server; since Checkpoint 10 they cannot delete or retire entries. The sync rules for them are defined (both ways, newest wins) and switched off. See [implementation-roadmap.md](implementation-roadmap.md) Checkpoint 17.

### PD-59. Conflict outcomes that must be flagged, and keeping both versions of documents

**Source:** ADR-001 principle 7; conformance findings F1, F6, F11, F15 and F16; CP5 Q2; your direction of 2026-11-23.

- **Your direction (2026-11-23).** Different attachments and important procurement documents must not silently replace one another. Preserve both versions, and require review where appropriate. So option 3 (today's set) is ruled out for documents.
- **ADR-003 (2026-11-23).** The ADR-002 rule that always flagged vessel changes to master data is withdrawn. Vessels and the office now both maintain four lists, so newest-wins outcomes on them become common; option A 2 flags them. A change against a delete is PD-68.
- **Decision or question.**
  - **A.** When sync settles a conflict on its own, which outcomes must also be flagged for a person?
  - **B.** How exactly are both versions of a document kept?
- **Why it is needed.** How files can replace one another today:

  | Where | Can two versions replace each other? |
  |---|---|
  | Requisition and library attachments (`attachments`) | **No.** Every upload is a new record with its own file. Two sides adding files never collide. A conflict needs the same record's details edited on both sides; that is newest wins, not flagged. |
  | Procurement-stage documents (`attachment_order_step`, `attachment_service_req_step`) | **No.** Each link is its own row, so two documents added to one stage are both kept. Deleting a file used to remove its links; since Checkpoint 10 (PD-61) a file on a stage is kept, and any other removed file is hidden, not deleted. |
  | Certificate copy (`vessel_certificates.cert_copy`) | **Yes.** One path per certificate. A new upload replaces it. The old file used to be deleted at once; since Checkpoint 10 (PD-62) it is kept and listed in `vessel_certificate_copies`. That history travels both ways (2026-11-23): if both sides upload while apart, the certificate's current copy is decided by the newest (flagged), and both copies are kept, with their files, on both servers. |
  | User photo and signature (`users.photo`, `users.sign`) | **Yes.** One path each; the office's version wins (PD-21). |

- **Options, A: which outcomes to flag.**
  1. Flag every conflict.
  2. **Flag every conflict where the owning side's value lost, every newest-wins outcome on office data, and every conflict on a file, attachment, user password or status.**
  3. Keep today's set: requisition stage columns, `item_qty` and vessel certificates only. *Ruled out for documents by your direction.*
- **Options, B: keeping both versions.**
  1. **Keep both on conflict.** When two versions of one file field collide, the losing file is kept as a separate, linked version, and the conflict is flagged. Sync never deletes a file.
  2. **Version history always.** Every replacement keeps the previous file as history, conflict or not (PD-62).
  3. Newest wins, flagged (today, for certificates).
- **Recommended.**
  - **A: option 2.** It covers what principle 7 protects, without flagging cases where the rightful owner simply won. Identical changes on both sides are not conflicts at all.
  - **B: option 2 for certificate copies and procurement documents, option 1 for photos and signatures.** Certificate copies and procurement documents are evidence, so their history matters. For photos and signatures, only the collision matters.
  - **"Where appropriate".** Please name any document types that must always be reviewed by a person when they arrive, even without a conflict. I have assumed none.
- **If not decided.** Some overwrites stay visible only in a log that nobody reads, and a certificate copy can be lost when both sides replace it.
- **Today.** A option 3, B option 3. Keeping linked versions needs somewhere to record them: a link or history table, an additive migration for your approval. See [implementation-roadmap.md](implementation-roadmap.md) Checkpoints 14 and 15.

### PD-36. Cascading deletes when modules are switched on

**Source:** CP2 #2; CP5 15.3.

- **ADR-003 (2026-11-23).** Vessel deletions make this more important. The list of cascading keys decides which deletes are refused, and which are done child by child so that each deletion is recorded ([adr-003-to-005-design.md](adr-003-to-005-design.md) section 3.5).
- **ADR-001: open, affected; moved to Critical (2026-11-23).** The review found concrete cascade losses (PD-60, finding F12). These tests are now a condition for switching on any module (roadmap Checkpoint 24).
- **Decision or question.** Before each module is switched on, should
  every table with `ON DELETE CASCADE` be reviewed and tested on MySQL,
  with real data?
- **Why it is needed.** MySQL does not fire triggers for rows a cascade
  deletes. Sync relies on the other side cascading the same way, because
  both sides share the schema. That holds only while the schemas match
  exactly.
- **Options.**
  1. Review and test per module before enabling it.
  2. Rely on the design.
- **Recommended.** Option 1. It is a test task, not new code.
- **If not decided.** A schema difference could leave child rows on one
  side.
- **Today.** Covered by design and the SQLite and MySQL tests, not by live
  data.

### PD-60. Permanent deletes that cascade through vessel records (decided)

**Source:** conformance finding F12; CP2 #2; CP5 15.3.

- **ADR-006 (2026-11-23).** Vessel users do not delete, and the trash is refused to them (Checkpoint 10). This item is now only about the office's own permanent deletes in the trash, and how they travel to vessels. It changes an office permission, so it still needs your decision.
- **ADR-001: new.** Found in the 2026-11-23 review. It breaks principle 1 (vessel data is the vessel's) and principle 7 (no silent loss).
- **Decision or question.** Should a permanent delete be refused when other records depend on the record, and for every record of an offline vessel? Deleting through sync would also be limited to the record's owner.
- **Why it is needed.**
  - Super-admin's trash hard-deletes items, requisitions, vessels, certificates, surveys and categories (`HomeController::permanentDelete`).
  - Foreign keys cascade:
    - **an item:** its stock movements, consumptions and vessel links;
    - **a requisition:** its form parts, procurement steps and invoices;
    - **a vessel:** its stock, consumptions, equipment and service requisitions.
  - That is permanent loss on the cloud **today**, by one click.
  - Once synced:
    - **Items.** They have no delete limit, so the delete would go to every vessel. MySQL does not fire triggers for cascaded rows, so each vessel's own ledger lines for that item would vanish, unrecorded and unreported.
    - **Requisitions.** A requisition delete is refused by the vessel, so the cloud loses its copy and the two disagree.
    - **The vessel row.** Whether a vessel would apply a delete of its own `vessels` row is not tested.
- **Options.**
  1. **Refuse a permanent delete when anything depends on the record, and for any record of an offline vessel. Offer "retire" instead.** In sync, only the cloud deletes office master data, a delete that would cascade into vessel-owned rows is refused, and a vessel never applies a delete of its own vessel row.
  2. Allow it on the cloud, but never sync deletes of master data: retire only.
  3. Leave it as is.
- **Recommended.** Option 1. It closes the loss on both sides, and retiring already exists for every one of these record types (the `status` flag). It changes what the trash can do on the cloud, so it needs your approval.
- **If not decided.** The cloud can lose ledgers today, and `@master` and `@inventory` cannot be switched on safely.
- **Decided (2026-11-23).** Option 1, as recommended. Who may use the trash is unchanged.
- **Today.** Built:
  - **Trash (Checkpoint 10).** `App\Support\TrashGuard` refuses a permanent delete while any row refers to the record (declared foreign key or not; a test checks that no declared key is missing), for any record of a vessel that runs its own server, and for a record not in the trash. The record stays retired, and the screen says why.
  - **A defect fixed on the way.** The trash's permanent delete of a vessel survey or vessel certificate used to delete the fleet-wide survey type or certificate name that happened to have the same id. It now deletes the row shown.
  - **Sync (Checkpoint 14).** A vessel never applies a delete of its own vessel row, nor one that would cascade into rows it holds; both are refused and flagged. Deletes of office master data from a vessel were already refused (ADR-006).
  - Tests: `ApprovedGuardsTest::test_pd60_*`, `DeletionSyncTest` (PD-60 section).
  - **Still open:** the MySQL cascade tests per module (PD-36). (Explicit deletes of vessel-owned rows from the cloud are settled by PD-34: prevented on the cloud, refused on board.)

### PD-61. Deleting an attachment removes it from procurement stages and consumption records (decided)

**Source:** conformance finding F13; your direction of 2026-11-23 on procurement documents.

- **ADR-006 (2026-11-23).** Vessel users no longer delete attachments (Checkpoint 10). The uploader rule still lets **office** uploaders (for example the SSM officer's procurement documents) delete their own files. This item decides where that office permission stops for files that are evidence.
- **ADR-001: new.** Found in the 2026-11-23 review. Your direction for PD-59 says important procurement documents must not silently disappear.
- **Decision or question.** May the uploader delete a file that is attached to a procurement stage or to a consumption record? If not, should a delete hide the file, or be refused?
- **Why it is needed.** `AttachmentController::destroy` lets the uploader delete a file unless it is linked to a requisition item. It does not check links to procurement stages or consumption records; foreign keys remove those links with the file. Procurement stages are permanent records in sync, but their documents are not. This is existing cloud behaviour, and once `@attachments` is on the delete travels too.
- **Options.**
  1. Refuse a delete while the file is linked to anything. To remove it, unlink it first, where unlinking is allowed.
  2. **Refuse a delete while it is linked to a completed procurement stage or a consumption record; allow it otherwise.**
  3. Never remove: a delete hides the file and keeps it.
  4. Leave it as is.
- **Recommended.** Option 2, combined with option 3 for the deletes that remain allowed. Documents on completed stages, and evidence for consumption, are then never lost. Option 3 needs one column on `attachments` (an additive migration, for your approval). Option 2 alone needs none.
- **If not decided.** A procurement document can disappear from a completed stage without anyone being told.
- **Decided (2026-11-23).** Option 2 with option 3, as recommended. One additive migration (`attachments.hidden_at`, `hidden_by`).
- **Today.** Built in Checkpoint 10. A file attached to a requisition line, to a procurement stage (a stage row exists only once the stage is completed) or to a consumption record is not deleted. Any other delete hides the file from its uploader's library and from linking; the record and the file on disk are kept. A hidden file arriving from a vessel is refused at sync like any retirement. Tests: `ApprovedGuardsTest::test_pd61_*`.
- **Correction to the question above.** Office uploaders cannot reach the delete today: the route is in the `member` group, which admits only chief officers and second engineers, and ADR-006 now refuses those. So **nobody** can delete a file through the screens. The guard protects the server if that ever changes; it does not restore an office delete action (that would widen a permission, ADR-006 point 5).
- **Scope confirmed (2026-11-23, later).** Your instruction: do not add attachment deletion or new permissions; keep the current protection, and record that deletion is out of scope for now. So:
  - no attachment delete action is added for anyone, on board or in the office, and no permission changes;
  - the server-side protection built above stays as it is (refused when the file is a record; otherwise hidden and kept);
  - a way to remove a wrong attachment is **out of scope for now**. If one is wanted later, it is a new decision.

### PD-63. Can a vessel's new entry be used at once? (decided: ADR-003)

**Source:** ADR-002 (superseded); ADR-001 principle 5; **decided on 2026-11-23 by ADR-003.**

- **Decided (2026-11-23).** Yes. Vessel users can use their changes immediately, including while offline. There is no pending state, so the follow-up question about approving requisitions whose lines use pending items no longer arises.
- **Today.** Entries are usable at once, as decided.

### PD-64. Which existing lists vessels maintain, and the manufacturer fields

**Source:** ADR-002 design section 2 (superseded); ADR-003; your instruction of 2026-11-23; inspection of the code, design sections 1.1 and 1.2.

- **Settled (2026-11-23).** The application has **no crew management module and no manufacturer management module**, and neither is added. The earlier question A (crew creating entries on the cloud) is moot: crew keep doing on the cloud what they do today, because there is no approval to apply.
- **Found: the manufacturer fields.** There are exactly two:
  1. `items.manufacturer`, a free-text column on catalog items. It is part of the item, so it syncs with the item. Nothing else is needed.
  2. `manufacturers`, one of the six lists on the PMS master-data screen (`/pms/master/manufacturers`, `PmsMasterDataController`). PMS components and PMS parts reference it. Only super-admin maintains it (`PmsAccess::authorizePmsMasterData`), and the screen is blocked on board. It must travel **down** to vessels, so that components on board can name a manufacturer; that is already defined.
- **Decision or question.** ADR-003 covers "other applicable master-data lists that already exist". Under the existing permissions, these lists are maintained by super-admin only, and so stay office-maintained:
  - the PMS lists: ranks, locations, component categories, job types, conditions and manufacturers;
  - requisition categories;
  - vessels.

  (Currencies, survey types and ports have no maintenance screen.) Should any of them become maintainable by vessel users?
- **Options.**
  1. **Keep the existing permissions.** None of them changes. Vessel users maintain budget groups, certificate categories, catalog items and item groups.
  2. Open the PMS lists (or only manufacturers) to vessel users, on board and on the cloud. This changes an existing permission.
- **Recommended.** Option 1. It follows your instruction to use the existing permissions, and keeps PMS lists consistent across the fleet. If crew need a manufacturer that is missing, the free-text field on items already serves the catalog; for PMS, ask super-admin.
- **If not decided.** Option 1.
- **Today.** Option 1.

### PD-65. Office review of vessel master data (decided: none)

**Source:** ADR-002 rule 4 (superseded); **decided on 2026-11-23 by ADR-003.**

- **Decided (2026-11-23).** There is no office approval of vessel-created master data. Vessel users create and update applicable entries without office review (deleting is withdrawn by ADR-006). No pending status, no proposal review screen, no approval queue, no new approval role, and no change to requisition approvals.
- **Moved elsewhere.**
  - Part B (exact duplicates) is now PD-18.
  - Part E (who may edit catalog items on the cloud; F14) is now PD-71, decided 2026-11-23.
  - Parts A, C and D (approvers, rejecting entries in use, approving imports) no longer arise.
- **Today.** No review exists, as decided.

### PD-67. How the offline branch reaches the cloud (decided)

**Source:** consolidated roadmap, section 4 (2026-11-23); **decided on 2026-11-23.**

- **Decided (2026-11-23).** Not a blocker; no further approval is needed for the branch strategy. The workflow:
  1. Development continues on `feature/offline-sync`.
  2. Urgent production fixes can be made on `main`.
  3. Fixes needed by the branch are brought from `main` into `feature/offline-sync`.
  4. The feature branch is tested after integrating them.
  5. Once offline-sync development is complete and tested, `feature/offline-sync` is merged into `main`.
  6. Production deployment is done separately, after your approval.
- **How the roadmap applies it.**
  - On 2026-11-23 you also asked that work be done only on `feature/offline-sync`, and that nothing be merged into `main` or deployed without your separate approval. So the live fixes of Checkpoints 10 and 11 are built on the branch. Moving any of them to `main` early, as an urgent production fix, waits for your approval.
  - The merge into `main` is part of Checkpoint 22, after a rehearsal of the production migrations on a copy of the production database. The deployment itself is a separate step that you approve.
- **Today.** Nothing from this branch is deployed or committed.

### PD-68. An office deletion meeting a vessel's change (decided)

**Source:** ADR-003 rule 5 (2026-11-23); ADR-006; design sections 3.6 and 3.7.

- **ADR-006 (2026-11-23).** Vessel users cannot delete or retire records, so:
  - **B is no longer applicable** (the fleet-wide effect of a vessel retiring a shared entry). Resolved.
  - **A is narrowed.** A deletion can now only start in the office. A delete or retirement that still arrives from a vessel is refused and flagged (Checkpoint 14).
- **Decision or question (A).** When the office deletes or retires a record while a vessel, offline, changes the same record, which result applies? It arises for:
  - shared master data (budget groups, certificate categories, items) that the office retires while a vessel edits it;
  - vessel records the office may delete today (super-admin's trash: certificates, surveys, requisitions, items; for vessels that are not offline).
- **Why it is needed.** Today, `latest` can let a change win over a delete and recreate the row. That breaks the approved requirement that a deleted record must not reappear.
- **Options.**
  1. **The office's deletion stands; the vessel's change is kept in the conflict record and flagged.** For a retirement, the vessel's other field changes still apply, and the entry stays retired.
  2. The vessel's change wins, and undoes the office's retirement (a removed record is never recreated, so this only applies to retirements). Flagged.
  3. Newest wins. *Conflicts with the requirement that deleted records do not reappear, for removals.*
- **Recommended.** Option 1. It needs no new behaviour, and nothing is lost: the vessel's change is kept for review.
- **Decided (2026-11-23), A.**
  - The office retirement remains effective.
  - The vessel's offline edit must not silently restore or reactivate the retired record.
  - The vessel's edit is preserved in the conflict and audit history.
  - The conflict is flagged for review; neither side's history is silently discarded.

  This is option 1. **Deletions (confirmed 2026-11-23):** the same rule applies to a full deletion as to a retirement. An office deletion (a row removed in the trash) stands, the record is never recreated, and the vessel's change is kept in the conflict record and flagged in the same way.
- **Decided (2026-11-23), B.** Not applicable: ADR-006 already stops vessel users retiring or deleting records, so a vessel retirement never reaches the fleet. A retirement or delete that still arrives from a vessel is refused and flagged.
- **How it is met (checked against the code, 2026-11-23).** In every order of events:

  | Order of events | Where the conflict is recorded | What is kept |
  |---|---|---|
  | The vessel's edit reaches the office after the retirement | Office, `remote_values` | The vessel's edit; the row stays retired |
  | The office changes the row again after the vessel's edit | Office | The vessel's edit, flagged; the row stays retired |
  | The vessel receives the retirement before sending its edit | Vessel, `local_values` | The crew's edit; the row is retired on board too, and stays retired on both sides after the next cycle |
  | Any edit meeting a deletion | The receiving side | The edit; the row is not recreated |

  Each conflict is marked `needs_review`. A table whose columns are merged one by one cannot have retire columns, because the retirement rule compares whole rows; the rules check refuses such a definition.
- **Today.** Built on `feature/offline-sync` (Checkpoint 14, 2026-11-23), not deployed, and no module is switched on. A delete always stands; an office retirement stands against a vessel's offline change, whose other values follow the table's rule; the losing change is kept in a conflict flagged for review. Tests: `DeletionSyncTest` (18). See [implementation-roadmap.md](implementation-roadmap.md) section 17.

### PD-69. Users changing their own password (`/change/password`)

**Source:** ADR-004 rule 1 (2026-11-23); design section 5.2.

- **New (2026-11-23).** ADR-004 says only a Superadmin can change or reset a user's password. Today every user can change their own password on the profile page (`/change/password`), by giving the current one, on the cloud and on board.
- **Decision or question.** Does a user's own password change remain?
- **Why it is needed.** If it stays, it must sync both ways, with the Superadmin reset winning a conflict (PD-11). If it goes, existing functionality is removed, on the cloud too.
- **Options.**
  1. **Keep it.** ADR-004 is read as covering other users' passwords. A user's own change syncs both ways, and a Superadmin reset wins.
  2. Remove it, on board only.
  3. Remove it everywhere.
- **Recommended.** Option 1. It keeps existing functionality, and a user who knows their own password changing it is not administration of another user. Please confirm, because the decision's wording could cover it.
- **If not decided.** Option 1 is kept in the build (nothing is removed), and shown to you before the pilot.
- **Today.** Option 1, newest wins.

### PD-70. What a Superadmin may do on board, and which accounts are sent

**Source:** ADR-004 rules 3 and 8 (2026-11-23); `config/navisuite.php` blocked paths; design section 5.3.

- **New (2026-11-23).** ADR-004 lets a Superadmin block, unblock and reset passwords on board. A vessel server blocks other office functions today, for everyone.
- **Decision or question.**
  - **A.** On board, may a Superadmin do anything else that is blocked there today: create users or change roles, edit vessels, requisition categories or PMS lists, use the trash?
  - **B.** Which super-admin accounts are sent to vessels (with their hashes): every super-admin, or named ones?
- **Why it is needed.** Each power opened on board needs its own sync rule, and each hash sent to a vessel is exposure if a PC is stolen.
- **Options, A.**
  1. **Only the approved actions** (block, unblock, password). Everything else stays blocked on board.
  2. Also create users and change roles on board.
  3. Everything a Superadmin can do on the cloud.
- **Options, B.**
  1. Every active super-admin.
  2. **Named super-admin accounts** only, listed in configuration.
- **Recommended.** A: option 1, the smallest change that meets the decision. B: option 2.
- **If not decided.** A option 1 and B option 2 are used in the build, and shown to you before the pilot.
- **Today.** No Superadmin can sign in on board.

### PD-71. Server-side permission checks that rely on the menu today (decided)

**Source:** conformance findings F14, F20 and F21 (2026-11-23); ADR-003 ("subject to existing permissions"); ADR-004.

- **New (2026-11-23).** These screens are hidden in the menu, but the server checks only that someone is signed in. So any signed-in user can use them by posting to the address. This is live on the cloud.

  | Screen | Menu shows it to | Server checks | Finding |
  |---|---|---|---|
  | Users: create, edit (role, vessel, password), block | super-admin | sign-in only | F20 |
  | Requisition categories: create, edit, retire | super-admin | sign-in only | F21 |
  | Catalog items: edit, retire (`/item/update`, `/item/delete`) | (not checked) | sign-in only | F14 |

- **Decision or question.** Should the server enforce what the menu shows?
  - **A. Users.** Block, unblock and password are settled by ADR-004 (Superadmin only), so that check is added without further decision. Creating users and changing roles: super-admin only, as the menu shows?
  - **B. Requisition categories.** Super-admin only, as the menu shows?
  - **C. Catalog items.** Who may edit and retire items? ADR-003 lets vessel users. On the cloud, any signed-in user can today.
- **Options, C.**
  1. Keep it open to every signed-in user (today's behaviour).
  2. Ship roles for items linked to their vessel, plus super-admin and the office roles that manage the catalog (please name them).
- **Recommended.** A and B: yes, enforce the menu. C: option 2, but only once you name the office roles; until then option 1 stays.
- **If not decided.** A: the ADR-004 part is enforced; creating users stays open to any signed-in user who knows the address. B and C stay open.
- **ADR-004 and ADR-006 (2026-11-23).** Built in Checkpoint 10 without waiting for this item: only a super-admin blocks a user or sets another user's password; ship users are refused every user administration action (ADR-004: vessel users get no administration rights); ship users are refused category and item retirement (ADR-006). What remains open is only the office side: which office roles may create users and change roles (A), maintain requisition categories (B), and edit items (C).
- **Decided (2026-11-23).**
  - **A.** Only a Superadmin creates users and changes user roles. Every other office role is refused, including by a direct request to `/user/update`. The role record also names the vessel, so moving a user to another vessel counts as a role change (my reading; tell me if you meant otherwise). Other office roles who see the Users screen (gm-srd) can still correct a name or e-mail address.
  - **B.** Only a Superadmin creates, edits and maintains requisition categories, in the screens and on the server. Other roles still see the list.
  - **C.** Every office role may edit catalog items, for this phase (today's behaviour, unchanged). Vessel users edit them under ADR-003. **This is not a grant of retirement:** retiring an item stays exactly as it is today for office roles (any office role that can reach `/item/delete` keeps it; nothing is widened or narrowed), and vessel users cannot retire items (ADR-006).
- **No part of PD-71 is open.**
- **Today.** A and B built in Checkpoint 10, with tests (`VesselUserDeletionTest`); C needs no change.

### PD-72. Actions not labelled "delete", and removing a wrong entry once vessels cannot (decided)

**Source:** ADR-006 (2026-11-23); inspection of `routes/web.php` and the controllers.

- **New (2026-11-23).** ADR-006 removes vessel users' delete actions and rules out retirement, archiving or deactivation as a substitute. Every action labelled delete, remove or disable on a whole record is now refused to them (roadmap Checkpoint 10). Four other actions take something out of use while a record is being composed or planned:

  | Action | Where | Who today | What it takes away |
  |---|---|---|---|
  | Remove a line from an unsubmitted requisition draft, and lines dropped on Save & Next | `RequisitionController::destroyStep2Item`, `storeStep2` | Chief officer, second engineer, own draft | The draft line. Nothing submitted is touched. |
  | Unlink an attachment from a draft line | `AttachmentController::detach` | Same | The link only. The file stays in the uploader's library. |
  | Take a PMS part off a draft requisition | `PmsRequiredPartController::removeFromRequisition` | Same | The draft line, and the part's link to it |
  | Deactivate a PMS job | `PmsJobDefinitionController::update`, "active" off | Master, chief engineer, chief officer, second engineer, own vessel | The job from planning; its open job is cancelled; history is kept |

- **Decision or question.**
  - **A.** Do these four count as deletion under ADR-006?
  - **B.** Once vessel users cannot delete, how is a wrong entry removed? Super-admin still can on the cloud, under existing permissions, for vessels that are not offline. For an **offline** vessel's PMS records and equipment, nobody can: the office copy is locked (ADR-001, PD-06). Examples: a wrong running-hour reading (it affects the job schedule), a component entered twice, a wrong equipment row, a file uploaded by mistake.
- **Why it is needed.** If all four count, a draft cannot be corrected before submission. A line added by mistake stays (the quantity minimum is 1), so the crew would have to abandon the draft and start again. Abandoned drafts would then accumulate, since nobody on board can remove them.
- **Options, A.**
  1. **Draft actions are editing an unsubmitted draft, not deletion: keep them. PMS job deactivation counts: refuse it to vessel users.**
  2. All four count: refuse them all to vessel users.
  3. None counts: keep all four.
- **Options, B.**
  1. **The vessel asks the office; super-admin removes it on the cloud under existing permissions. For an offline vessel, allow super-admin to remove that vessel's PMS and equipment records on the cloud, and the removal travels down with a deletion record (Checkpoint 14).** This widens an office permission, so it needs your approval.
  2. The vessel asks the office, and the office removes it only where existing permissions already allow. Wrong entries of an offline vessel stay, and the crew correct around them (for example a new reading).
- **Recommended.** A: option 1. B: option 1.
- **If not decided.** A: all four stay as they are today; nothing beyond the labelled delete actions is removed. B: option 2. No vessel is offline yet, so B matters only from the pilot (Checkpoint 24).
- **Decided (2026-11-23).**
  - Allowed for vessel users, because they are normal edits to unsubmitted drafts, not deletion of submitted records: (1) removing a line from an unsubmitted requisition draft; (2) unlinking an attachment from a draft line, with the file staying in the library; (3) taking a PMS part off a draft requisition. This is not permission to delete submitted records or other persistent operational records.
  - **Not allowed:** (4) deactivating a PMS job. It takes the job out of active planning, which the no-delete policy forbids. Enforced on the server and in the screens.
  - Reactivating a job stays allowed.
  - **Not part of this decision:** marking a PMS component inactive. It was a separate question, PD-73, decided on 2026-11-24 (restricted for vessel users).
- **Part B decided (2026-11-23, later).** Your answer: no correction is needed, because the ship's master and the office's SRD can update the requisition quantity, so if an entry is wrong they fix it. No action needed.
  - **Option 2.** No removal action is added, and no office permission is widened. Super-admin keeps only the removals existing permissions allow, and the office copy of an offline vessel's PMS and equipment stays read-only (ADR-001, PD-06).
  - **Checked in the code.** The master and chief engineer can correct requested quantities when they approve (`RoleController`, the approval step), and SRD at its review (`RoleController::applyQuantityCorrection`, `HomeController::addDelQty` for am-srd).
  - **What it means elsewhere.** The reason you gave is about requisitions. Under option 2, a wrong PMS or equipment entry of an offline vessel is also corrected by editing it on board (for example a new reading, or a corrected name), not removed.
  - Nothing to build.
- **Today.** Built in Checkpoint 10.

---

## Important

### PD-17. Certificates and surveys: ship or office authority?

**Source:** CP6 C6-7; Audit Q3; Roadmap 13 Q3.

- **ADR-001: still open.** ADR-001 does not say whether certificates and surveys are vessel operational data or office data. The review found a mismatch (finding F6). `vessel_surveys` is set to newest wins **without** a review flag. Certificates have the flag, and CP6 says both are flagged. Under principle 7, surveys need the flag whichever option you choose (roadmap Checkpoint 14).
- **Decision or question.** Who is the authority for `vessel_certificates`
  and `vessel_surveys`: the ship, the office, or both?
- **Why it is needed.** Both sides edit them today. With both writing
  offline, someone has to win.
- **Options.**
  1. Both, newest wins, flagged for review (provisional).
  2. Office only (read-only on board).
  3. Ship only (read-only in the office).
- **Recommended.** Ask whoever manages certificates. If both really edit,
  option 1. If one side is the real owner, choose option 2 or 3; that
  avoids conflicts entirely.
- **If not decided.** The provisional rule applies once master data is
  switched on.
- **Today.** Option 1. Certificate files do not travel yet (see PD-22).

### PD-18. The same master-data entry created on two sides: merge automatically?

**Source:** CP6 C6-3; CP5 15.3 (natural-key duplicates); ADR-003.

- **ADR-003 (2026-11-23).** There is no office review of vessel entries, so duplicates are not sent to an approver. The question is only which duplicates sync may combine on its own.
- **Decision or question.** When a vessel and the office (or two vessels) create the same entry while apart, which duplicates may sync merge automatically?
- **Why it is needed.** Merging the wrong entries mixes stock and history. Not merging leaves duplicates, and today a duplicate name in budget groups, certificate categories or item groups is refused and waits for a person, with the records that use it.
- **Options.**
  1. Catalog items with the same `item_key` only (built, as the importer does). Other lists wait for a person.
  2. Option 1, plus **exact name matches** in budget groups, certificate categories and item groups (ignoring case and spaces), merged automatically and flagged.
  3. Never automatically.
  4. Similar names too. Risky.
- **Recommended.** Option 2. Exact matches are plainly the same entry; merging them keeps crew working without anyone resolving them by hand. Every merge is flagged, so it can be checked.
- **If not decided.** Option 1 applies.
- **Today.** Option 1.

### PD-19. Catalog scope on board

**Source:** Audit Q9; Roadmap 13 Q9.

- **ADR-003 (2026-11-23).** Items a vessel creates go to the cloud at once. Which items each vessel receives is decided here.
- **Decision or question.** Does a vessel need only the catalog items
  linked to it, or the whole fleet catalog?
- **Why it is needed.** It sets the database size, the first download, and
  whether crew can request items not yet linked to their vessel.
- **Options.**
  1. Vessel-linked items only (assumed).
  2. The whole fleet catalog.
  3. The whole catalog for reference, but only linked items can be stocked.
- **Recommended.** Ask the people who raise requisitions whether they ever
  pick items not linked to their vessel. If they do, option 3; if not,
  option 1.
- **If not decided.** Crew may not find an item on board that they can
  find on the cloud.
- **Today.** Option 1.

### PD-20. Shore and support accounts on board

**Source:** CP6 C6-5; CP7 C7-6; Audit Q5; Roadmap 13 Q5; ADR-004.

- **ADR-004: partly resolved (2026-11-23).** A Superadmin must be able to sign in on board. So super-admin accounts' hashes will be sent to vessels (see PD-70 for which accounts). This item now covers **other** shore users only.
- **Decision or question.** Besides Superadmins, should any shore user, such as a visiting superintendent or a support account, be able to sign in to a vessel server?
- **Why it is needed.** Today shore users' password hashes are never sent to vessels.
- **Options.**
  1. Nobody else (today).
  2. One named, read-only support account per vessel.
  3. Named shore users, chosen per vessel.
- **Recommended.** Option 1. A Superadmin now covers administration on board, and every extra hash on a master PC is extra exposure if the PC is lost.
- **If not decided.** Option 1.
- **Today.** Option 1. `APP_SUPPORT_ACCOUNTS` lets listed accounts sign in read-only, but their hashes are withheld, so none can.

### PD-21. Photos and signatures changed on board

**Source:** CP7 C7-1.

- **ADR-004 (2026-11-23).** It covers blocking and passwords only. Photos and signatures stay open here.
- **ADR-001: partly resolved.** Principle 7 rules out option 3: today a crew member's change is overwritten without warning (finding F5). Options 1 and 2 remain. Option 2 follows principle 3 most closely. Option 1 would be an exception for a user's own photo and signature.
- **Decision or question.** Should a user's photo and signature changed on
  board go up to the cloud, with the newest winning, like the password
  (PD-11)?
- **Why it is needed.** User records belong to the office. So a change
  made on board stays there, and is overwritten by the office's next change
  to that user.
- **Options.**
  1. Up, newest wins.
  2. Office only. Block the change on board, so nothing is lost silently.
  3. Leave as today. *Ruled out by ADR-001 (principle 7).*
- **Recommended.** Option 1 if crew are expected to update their own
  signature. Signatures appear on requisitions, so the right one matters.
  Otherwise option 2.
- **If not decided.** Crew changes can disappear without warning.
- **Today.** Option 3.

### PD-22. Office procurement files sent to vessels; file size limit

**Source:** CP7 C7-3; Audit Q10; Roadmap 13 Q10.

- **Decision or question.**
  - Should office files on a requisition's procurement stages (quotations,
    prices, POs, invoices) go down to the vessel?
  - What is the largest file that may be sent over satellite?
- **Why it is needed.** Crew can see these files on the cloud today. On
  board they would see only what is sent. Large files cost money on
  satellite links.
- **Options for content.**
  1. Send all, as visible today.
  2. Keep some stages' files in the office (for example, quotations and
     prices).
  3. Send on request only.
- **Options for size.** No limit; a fixed limit (for example 10 MB); or
  larger files only on a cheap link.
- **Recommended.** Content: decide with procurement whether crew should see
  prices. If not, option 2. Size: set a limit after measuring your link
  costs (PD-27).
- **If not decided.** Crew see on board what they saw on the cloud. Large
  files go over whatever link is up.
- **Today.** Option 1. Sync refuses files over 64 MB (`sync.files.max_file_bytes`)
  and sends at most 50 MB of files per cycle (`SYNC_FILES_MAX_BYTES`).
  There is no per-file limit for satellite links.

### PD-23. Who may open an attachment

**Source:** CP7 C7-7.

- **Decision or question.** Should opening an attachment be limited to
  people who can see its requisition?
- **Why it is needed.** Any signed-in user can open any attachment by its
  number. That is existing cloud behaviour. On board, only the vessel's own
  files are present.
- **Options.**
  1. Limit it to people who can see the requisition.
  2. Leave as is.
- **Recommended.** Option 1. On the cloud, crew of one vessel can open
  another vessel's quotations by trying numbers.
- **If not decided.** The exposure stays on the cloud.
- **Today.** Option 2.

### PD-24. Uploaded images served without sign-in

**Source:** CP3 #5.

- **Decision or question.** Should certificate copies, photos and
  signatures be served only to signed-in users?
- **Why it is needed.** They are served as static files from `images/`. New
  files have random names (Checkpoint 7). Older files are named by Unix
  timestamp, so their names are easy to guess.
- **Options.**
  1. Serve them through a signed-in route.
  2. Keep static, and rename old files to random names.
  3. Leave as is.
- **Recommended.** Option 1. Signatures and certificate copies are
  sensitive. It is an application change, on the cloud as well.
- **If not decided.** Anyone who guesses an old file name can download it,
  on the cloud and on the LAN.
- **Today.** Option 3 for old files; new files are hard to guess.

### PD-25. Backups and disaster recovery (decided: ADR-005)

**Source:** CP7 C7-4 and section 9; Audit Q14; DR section 3; **decided on 2026-11-23 as [ADR-005](architecture-decisions.md#adr-005-backups-on-the-vessels-master-pc-vessel-by-vessel).**

- **Decided (2026-11-23).**
  - Backups are stored on the vessel's master PC only. No external PC, drive or off-PC copy is required.
  - Vessel by vessel where possible, so each vessel's data is backed up and restored independently.
  - The backup and restore procedure is documented; backup integrity can be verified; restoration is tested before the pilot.
- **How it is met** ([adr-003-to-005-design.md](adr-003-to-005-design.md) section 6):
  - Each master PC holds one vessel, so its backups are vessel by vessel already.
  - A nightly set: the database (with the sync state), the files, and the identity and configuration, tied by one manifest with checksums. Secrets are protected by Windows and are not portable; a new PC gets new ones.
  - Checks: checksums, a weekly test restore into a scratch database, and the age of the last good backup on `sync:health` and the office dashboard.
  - After a restore, deletion records are pulled again, so nothing deleted since the backup returns.
- **Risk accepted by the decision.** A failed disk, fire, theft or ransomware on the master PC loses the live data and every backup together. What reached the cloud can be rebuilt from it (roadmap Checkpoint 20); changes since the last successful sync cannot. Frequent sync, and an office alert when a vessel goes quiet, are the main protection.
- **Optional choices (not required):**
  - encrypt the backups on the PC (not built);
  - a second internal disk for the backup folder;
  - disk encryption (BitLocker) on the master PC.
- **Today.** Database and file backups, `-Verify`, `-Restore` and `-Export` are built (Checkpoint 7), not yet run on Windows. No set manifest, no test restore, no reporting. See [implementation-roadmap.md](implementation-roadmap.md) Checkpoint 21.

### PD-26. Are the master PCs' clocks trustworthy?

**Source:** CP5 Q4; Audit R7 and C7.

- **Decision or question.** Are the master PCs' clocks kept accurate, by
  GPS time or internet time?
- **Why it is needed.** "Newest wins" rules compare times from two PCs.
  Sync order itself does not depend on the clock.
- **Options.**
  1. Yes, synchronised: keep "newest wins" (assumed).
  2. No: set up Windows time sync from the ship's GPS or NTP source during
     installation.
  3. No: change "newest wins" rules to review.
- **Recommended.** Option 2 if the ships have a GPS time source. It is an
  installation setting, not a code change.
- **If not decided.** A PC with a wrong clock wins conflicts it should lose.
  Today it is only warned above 120 seconds.
- **Today.** Option 1, with the warning.

### PD-27. Connectivity and sync timing

**Source:** Audit Q6 and R9; Roadmap 13 Q6.

- **ADR-001: guided.** Principle 6 says data syncs when the connection returns. Automatic sync (options 1 and 2) does that; manual-only sync (option 3) does not. The interval, and the policy for metered links, are still open.
- **Decision or question.**
  - Which links do the ships use (VSAT, Fleet Broadband, Starlink, port
    Wi-Fi)?
  - What are their typical speeds and data costs?
  - Should sync run automatically, on a schedule, or only when the crew
    starts it?
- **Why it is needed.** It sets the sync interval, the batch size, the file
  size limit (PD-22) and the cost of running the system.
- **Options.**
  1. Automatic every 5 minutes when online (`SYNC_INTERVAL=300`), plus `php artisan sync:run` for an immediate cycle. There is no button yet (assumed).
  2. A fixed schedule, for example hourly.
  3. Manual only. *Does not meet ADR-001 principle 6.*
- **Recommended.** Option 1 on cheap links, option 2 on metered ones. The
  interval is a setting. Measure data use on the pilot vessel before you
  decide fleet-wide.
- **If not decided.** Every vessel syncs every 5 minutes when it can reach
  the cloud.
- **Today.** Option 1.

### PD-28. Windows versions and hardware on board

**Source:** Audit Q7 and R13; CP8 C8-6; CP3 #7; Roadmap 13 Q7.

- **Decision or question.**
  - Which Windows editions and versions do the ships' PCs run?
  - How many client PCs are there per vessel?
  - Is the master PC a dedicated machine?
  - Can it have a fixed address (DHCP reservation or static IP)?
- **Why it is needed.**
  - The installers accept Windows 10 1607 or later, or Server 2016 or
    later, for the server, and Windows 10 or 11 64-bit for clients.
  - The test plan needs the oldest version you have (PC "CL3").
  - A master PC that sleeps, updates or is used for other work takes the
    system down for everyone (R13).
- **Options.** Gather the facts from IT. For the master PC:
  - a dedicated PC with a fixed address (recommended);
  - a shared PC.
- **Recommended.** A dedicated master PC with a fixed address. The
  installer sets the power plan and service recovery, but it cannot stop
  someone shutting the PC down.
- **If not decided.** An unsupported or untested Windows version may only
  be found on board.
- **Today.** The installers accept Windows 10 1607 or later, or Server 2016
  or later, for the server, and Windows 10 or 11 64-bit for clients. The
  roadmap's working assumption is Windows 10 22H2 or later, 64-bit. Only the
  facts from the ships can settle which to test (PD-15).

### PD-29. Code-signing certificate

**Source:** CP4 12.2 #2; CP8 C8-2.

- **Decision or question.**
  - Should an OV or EV code-signing certificate be bought, and in whose
    name?
  - Who holds it, and where does signing run?
- **Why it is needed.** Without a signature, Windows SmartScreen warns
  ("Windows protected your PC"). Crew PCs cannot tell a genuine installer
  from a tampered one.
- **Options.**
  1. An EV certificate: no warning from the first install. Hardware token
     or cloud signing.
  2. An OV certificate: the warning fades as reputation builds.
  3. None: IT chooses "Run anyway" or installs silently.
- **Recommended.** Option 2, or option 1 if the budget allows, in the
  company's name. Keep it with the release machine (PD-16).
- **If not decided.** Every install shows a warning. Crew learn to click
  past it, which is a habit worth avoiding.
- **Today.** Unsigned.

### PD-30. How new versions reach vessels

**Source:** Audit Q11; CP4 12.2 #3; CP8 section 6; Roadmap 13 Q11.

- **Decision or question.**
  - How should new versions reach the vessels: over the sync link, by USB,
    or with a technician?
  - Should the client update itself from the vessel server?
- **Why it is needed.** The full server installer will be several hundred
  MB, which is too large for most satellite links. The application-only
  release zip is about 18 MB and works with `update-server.ps1`. The client
  does not update itself.
- **Options.**
  1. Technician or USB for installers. The release zip over the link for
     application updates (assumed).
  2. Plan an update channel served by the vessel server for clients (new
     work).
  3. Everything over the link.
- **Recommended.** Option 1 for go-live. Plan option 2 if there are many
  client PCs per vessel (PD-28).
- **If not decided.** Each update means touching every PC by hand.
- **Today.** Option 1.

### PD-31. Where the cloud keeps its secrets

**Source:** CP7 C7-9.

- **Decision or question.** Where should the cloud server keep its
  secrets, such as the node tokens' keys and database passwords?
- **Why it is needed.** On Windows, vessel secrets are protected by DPAPI.
  On Linux, the fallback is a key file on the same disk, which protects
  less.
- **Options.**
  1. The host's key service, or environment variables set by the service
     manager.
  2. The key file (today).
- **Recommended.** Option 1, if the host supports it. This ties in with
  PD-07: shared hosting usually offers only option 2.
- **If not decided.** Anyone who can read the cloud's files can read its
  secrets.
- **Today.** Option 2.

### PD-33. Public self-registration (`/register`)

**Source:** Audit R14; CP1 to CP3 "public registration".

- **Decision or question.** Should anyone be able to create an account
  through `/register`?
- **Why it is needed.** `Auth::routes()` enables registration. It creates
  accounts with no role. On a vessel, anyone on the LAN could create one.
- **Options.**
  1. Disable it on both cloud and vessels.
  2. Disable it on vessels only.
  3. Keep it.
- **Recommended.** Option 1, unless your office relies on self-registration
  for new staff. I could not tell from the code whether it does.
- **If not decided.** Role-less accounts can be created on the cloud and on
  board.
- **Today.** Option 3.

### PD-37. Accept the remaining security limits of Checkpoint 7

**Source:** CP7 section 9.

- **ADR-004 and ADR-005 (2026-11-23): partly resolved.** Item 2 must now be fixed, because ADR-004 keeps password credentials out of ordinary sync records (roadmap Checkpoint 16). Item 3: backups stay on the master PC (ADR-005); encryption is optional (PD-25). Item 1 is still yours to accept.
- **Decision or question.** Do you accept these limits, or should any be
  fixed?
  1. The MySQL root password sits in a temporary file for the length of
     each `mysql` or `mysqldump` call. The file is in an
     administrators-only folder and deleted afterwards.
  2. `sync_row_bases` (used for merging) holds users' password hashes,
     because the password column is merged.
  3. Backups and exports are not encrypted (see PD-25).
- **Why it is needed.** Each one is a small exposure, if someone gets
  administrator access or a copy of the files.
- **Options.**
  - Accept them as documented.
  - Fix item 2 by keeping password hashes out of the merge store (a code
    change).
  - Fix item 3 with PD-25.
- **Recommended.** Accept item 1. Fix item 2 when convenient. Decide item 3
  with PD-25.
- **If not decided.** They stay as documented.
- **Today.** Documented, not fixed.

### PD-62. Replacing a certificate copy deletes the previous file (decided)

**Source:** conformance finding F15; PD-59.

- **ADR-001: new.** Found in the 2026-11-23 review.
- **Decision or question.** When a certificate copy is replaced, should the previous file be kept?
- **Why it is needed.** The certificate update deletes the old file before storing the new one. So there is no history of earlier copies. With sync, if both sides replace a copy while apart, one of the two new files is left behind with nothing pointing at it (PD-59).
- **Options.**
  1. Keep every version, with who uploaded it and when.
  2. Keep only the previous version.
  3. Delete it (today).
- **Recommended.** Option 1. Certificate copies are evidence for surveys and audits, and they are small. It needs a history table (an additive migration, for your approval). This changes cloud behaviour: old copies stop being deleted.
- **If not decided.** Earlier certificate copies keep being deleted on replacement.
- **Decided (2026-11-23).** Option 1, as recommended. One additive migration (table `vessel_certificate_copies`).
- **Today.** Built in Checkpoint 10. Every upload is recorded with who and when; the replaced copy's file is kept. A copy uploaded before the history began is recorded, without an uploader, the first time it is replaced. The edit form lists earlier copies as links. Tests: `ApprovedGuardsTest::test_pd62_*`.
- **Synchronized (2026-11-23, later; your instruction).** The history travels both ways, each copy an immutable historical record:
  - **Unique ids:** each copy has its own `sync_uuid`; the same certificate and file are never listed twice (unique key), and a copy listed on both sides (one from before the history began) becomes one record.
  - **Immutable:** a copy record is written once and never changed or removed by sync; a change or delete that arrives for one is refused and flagged. Which copy is current is the certificate's own copy, so `replaced_at` is no longer written.
  - **Original author and time:** the uploader and upload time travel unchanged.
  - **Idempotent:** a copy received again, or under a new event id, changes nothing.
  - **Integrity:** the file's SHA-256 and size are recorded at upload; the file channel checks every copy against them wherever it arrives, and a damaged file is never put in place. The certificate's own `cert_copy` names the same file without a checksum; it is now checked against the copy record's checksum too.
  - **Both sides uploading while apart:** both copies are kept, with their files, on both servers; the certificate's current copy is decided by the existing certificate rule (newest, flagged).
  - Migrations (additive, for your approval before deployment): `2026_11_26_130000_add_checksum_to_vessel_certificate_copies_table.php`, `2026_11_26_130100_add_sync_columns_to_vessel_certificate_copies_table.php`. Tests: `CertificateCopySyncTest` (6).

### PD-73. Vessel users marking a PMS component inactive (decided)

**Source:** building PD-72 (2026-11-23); ADR-006.

- **New (2026-11-23).** PD-72 decided that vessel users may not deactivate a PMS **job**. A PMS **component** has a similar switch, "inactive" (`pms_components.is_inactive`). It was not part of PD-72.
- **What the switch does.** An inactive component is left out of the PMS dashboard (`PmsDashboardController`) and the PMS reports (`PmsReports`). It is still in the component tree, marked "Inactive". Its jobs are not cancelled.
- **Checked against ADR-006.**
  - **For refusing it:** point 2 says deletion is not replaced by "deactivation or any other equivalent action that effectively removes a record from active use". Leaving a component out of the dashboard and reports is that.
  - **Against:** point 3 keeps vessel users' create and update rights, and point 4 keeps existing business permissions. The switch is part of the component's ordinary edit form.
- **Checked in the code (2026-11-23, your instruction).** The switch is real and predates this work: the column comes from the original `create_pms_components_table` migration (2026-10-13); the component form has an "Inactive" checkbox that `PmsComponentController` saves for anyone who may edit the component; the dashboard, the reports and the counter screen read it; the CFM import sets it. So PD-73 is applicable and stays open.
- **Before your decision (2026-11-23): permissions left unchanged.** The restriction I had built on my reading of point 2 (a server check, a hidden checkbox, and a sync rule refusing a vessel's inactive mark) is removed. The switch behaves exactly as in the committed code: whoever may edit a component may set or clear it, and a vessel's change travels as an ordinary edit. Tests: `VesselUserDeletionTest::test_the_component_inactive_switch_keeps_its_existing_permissions`, `DeletionSyncTest::test_a_pms_component_marked_inactive_on_board_travels_as_an_ordinary_edit`.
- **Decision or question.** Does ADR-006 point 2 cover marking a component inactive?
- **Options.**
  1. Yes: refuse it to vessel users (one server check, one form line, one sync rule).
  2. No: it is an ordinary edit, as today.
- **Decided (2026-11-24): option 1.** Your approval: restrict the existing switch for vessel users.
  1. Office users keep their existing permission to mark a component active or inactive.
  2. Vessel users may not mark a component inactive, or reactivate one.
  3. The Inactive checkbox is disabled on the vessel users' screen.
  4. The restriction is enforced on the server, not only on the screen.
  5. A vessel's change to `pms_components.is_inactive` is refused at sync and flagged.
  6. Ordinary edits to the other component fields still work.
  7. The CFM import and existing office permissions are not changed unless a change is identified and documented.
- **How it is built.**
  - **Server.** `PmsComponentController`: a user who may not use delete actions (`DeletionPolicy::userMayDelete`: vessel users anywhere, and everyone on a vessel server, the same rule as PMS job deactivation in PD-72) gets 403, "Only the office can mark a PMS component inactive or make it active again. Please ask the office.", for a request that would change the switch, in either direction, and for a new component sent as inactive. A request that leaves the switch out keeps the current value (a new component starts active). Sending the value the component already has is not a change, so it is accepted.
  - **Screen.** For those users the checkbox is shown but disabled, with "Set by the office only." A disabled box is not sent, so the rest of the form saves as before.
  - **Sync.** New config `sync.office_only_columns` (`pms_components.is_inactive`). A vessel's change that sets the column to a value other than the receiver's is not taken, either way; the rest of the change is, and a `refused` conflict needing review records the vessel's values. A new row from a vessel is not checked (see the CFM import below). The rules check refuses an office-only column on a table without a rule, or on a table that only travels down.
- **What did not change (point 7).**
  - **Office permissions.** Unchanged. Who may edit a component is still `canEditPmsVessel`: super-admin for vessels without their own server; shore viewers (GM and SRD roles, superintendents) read only, as before; and the office copy of a vessel with its own server stays read-only (PD-06). For the office, leaving the box out still means "active", as before.
  - **CFM import.** Unchanged. It is a console command (`pms:import-cfm`) run by a technician, not a screen for vessel users. It sets the switch only when it creates a component, from the CFM file; existing components are reused untouched. Because new rows from a vessel are not checked at sync, a CFM import run on a vessel server still syncs as before.
  - **The spreadsheet import** (`PmsComponentImporter`) never sets the switch, so it needs no change.
- **A consequence to note.** For a vessel that runs its own server, nobody can now change the switch: the office copy is read-only (PD-06), `pms_components` only travels up to the cloud, and the vessel's users are refused. To change it on such a vessel, the office would need a new permission (for example, a one-column exception to PD-06 that travels down). Nothing is built for that; it is a question for you.
- **Tests.**
  - `VesselUserDeletionTest` (5): the office keeps its permission (both ways, and the unchanged shore-viewer and offline-vessel refusals); vessel users refused both ways and for a new inactive component; ordinary edits still work, including on an inactive component; the screen disables the box for vessel users only; nobody changes the switch on a vessel server.
  - `DeletionSyncTest` (3): marking inactive on board is refused and flagged while the rest of the change is taken; reactivating on board is refused and flagged; an ordinary edit travels without a flag.
  - A mutation check: with the sync rule or the server check removed, those tests fail.
- **Today.** Built on `feature/offline-sync` (2026-11-24), not committed, not deployed.

---

## Optional

### PD-38. Ship and office changing an item's quantity at the same time

**Source:** CP6 C6-6.

- **ADR-001: still open.** If you approve the PD-13 workflow, the same pattern could apply here: SRD's correction stands, and the line is held for review. It has the same tension as PD-13. The quantity is the vessel's request (principle 2), and SRD's correction is an office approval step (principle 3).
- **Decision or question.** If the ship and SRD both change `item_qty` on
  the same line while apart, whose value wins?
- **Why it is needed.** It is the rule for an unlikely case. In today's
  code, each side can edit only at its own stage.
- **Options.**
  1. The ship's value, flagged (provisional).
  2. SRD's value, flagged.
  3. Hold for review.
- **Recommended.** Option 1. It should not happen, and the flag shows it if
  it does.
- **If not decided.** The provisional rule applies.
- **Today.** Option 1.

### PD-39. Retention of sync history on the cloud

**Source:** CP5 Q5.

- **Decision or question.** How long must the cloud keep received batches
  and inbox events, for audit?
- **Why it is needed.** It sets disk use, and how far back you can trace a
  change.
- **Options.** Today's periods, 90 days for batches and inbox events, or longer.
- **Recommended.** 90 days for batches and inbox events on the cloud, and today's periods on vessels. The cloud has the disk space.
- **If not decided.** Today's periods everywhere.
- **Today.** Outbox 14 days, inbox events and batches 30 days, sync logs 90 days (`sync.retention`). (Corrected on 2026-11-23: the earlier text said 30 days for everything.)

### PD-40. One cloud database or several

**Source:** CP5 Q6.

- **Decision or question.** Will the cloud stay on one database server?
- **Why it is needed.** The sequencer lock and rate limits assume one
  database. Several web servers are fine.
- **Options.** One database (assumed), or several (needs design work).
- **Recommended.** One. Nothing suggests you need more.
- **If not decided.** The assumption stands.
- **Today.** One.

### PD-41. Source of the Windows CA bundle

**Source:** CP5 Q7.

- **Decision or question.** Where does the CA bundle the vessel uses to
  trust the cloud's certificate come from, and who updates it?
- **Why it is needed.** If the bundle is old, sync fails once the cloud
  renews to a certificate the bundle does not know.
- **Options.**
  1. The Mozilla bundle in the runtime folder, refreshed with each runtime
     update (assumed).
  2. Windows' own certificate store.
- **Recommended.** Option 1, and pin the cloud's certificate authority in
  the release notes.
- **If not decided.** The assumption stands.
- **Today.** Option 1.

### PD-42. MySQL series upgrades

**Source:** CP8 C8-4.

- **Decision or question.** Should a future installer upgrade MySQL to a
  new series (for example 8.0 to 8.4) itself, with a dump and reload?
- **Why it is needed.** The runtime update accepts MySQL only within the
  same series. Changing series needs a dump and reload.
- **Options.** Automate it, or keep it a planned manual job.
- **Recommended.** Keep it manual. It is rare and risky, so it is better
  done with a person watching.
- **If not decided.** It stays manual.
- **Today.** Manual.

### PD-43. LAN download of the client installer

**Source:** CP8 C8-5.

- **Decision or question.** Should the vessel server offer the client
  installer as a download on the LAN?
- **Why it is needed.** It is convenient. But crew PCs would fetch an
  unsigned installer before they trust the server's certificate.
- **Options.** Offer it, or keep IT copying it from `data\client-setup`.
- **Recommended.** Wait until the installers are signed (PD-29).
- **If not decided.** IT copies it by hand.
- **Today.** Copied by IT.

### PD-44. Keep the session on single-user PCs

**Source:** CP4 12.2 #4.

- **Decision or question.** Should any PC, such as the master's cabin PC,
  keep the session after the client is closed?
- **Why it is needed.** It is convenient, but less safe if others use the
  PC.
- **Options.** Clear the session on exit everywhere (today), or keep it on
  named PCs (`clearSessionOnExit: false`).
- **Recommended.** Clear everywhere, unless the master asks.
- **If not decided.** Users sign in again each time.
- **Today.** Cleared.

### PD-45. Edge as the documented emergency fallback

**Source:** CP4 12.2 #5.

- **Decision or question.** If the client fails, is Edge with
  `https://<server>/` acceptable as the documented fallback? IT installs
  `ca.crt` into the PC's trusted roots first.
- **Why it is needed.** Crew need a way to work while the client is
  repaired.
- **Options.** Accept it, or require a reinstall of the client.
- **Recommended.** Accept it. It needs no new software.
- **If not decided.** The documented procedure has no approval.
- **Today.** Documented.

### PD-46. bcrypt cost 10 or 12

**Source:** CP7 C7-8.

- **ADR-004 (2026-11-23).** Super-admin password hashes will be on every master PC, which makes the higher cost more useful.
- **Decision or question.** Should the password hashing cost go from 10 to
  12, Laravel's default?
- **Why it is needed.** A higher cost makes stolen hashes slower to crack.
  Sign-in is slightly slower on older PCs.
- **Options.** 10 (today), or 12. With 12, users are rehashed at their next
  sign-in.
- **Recommended.** 12, after checking sign-in time on the oldest master PC
  during the test (PD-15).
- **If not decided.** 10.
- **Today.** 10.

### PD-47. Fixed Asia/Dhaka time zone on every vessel

**Source:** Audit C7.

- **Decision or question.** Is it acceptable that every vessel records
  times in Asia/Dhaka time, wherever it is?
- **Why it is needed.** Times are stored without a zone. Keeping one zone
  keeps cloud and vessel data consistent. Crew in other zones see office
  time.
- **Options.**
  1. Keep Asia/Dhaka everywhere (today).
  2. Store UTC and show local time. A large change touching existing data.
- **Recommended.** Option 1.
- **If not decided.** Option 1 stays.
- **Today.** Option 1.

### PD-48. Client built as a shell over the existing web pages

**Source:** Audit Q13; Roadmap 13 Q13.

- **Decision or question.** Please confirm that a desktop client which
  loads the vessel server's existing web pages is acceptable. The
  alternative is a new client built against JSON APIs.
- **Why it is needed.** The requirement said "HTTP APIs". A shell reuses
  every existing screen.
- **Options.** Keep the shell (built), or plan a new client.
- **Recommended.** Keep the shell. A new client would duplicate every
  screen.
- **If not decided.** The built approach stands.
- **Today.** Shell.

### PD-49. Fleet size and onboarding pace

**Source:** Audit Q15.

- **Decision or question.** How many vessels are there, and how fast will
  they be onboarded?
- **Why it is needed.** It sets how much to automate commissioning
  (PD-08), and the cloud's capacity.
- **Options.** Not a choice; facts needed.
- **Recommended.** Share an estimate before PD-08 is designed.
- **If not decided.** The tools are sized for a small fleet.
- **Today.** Unknown.

### PD-50. Phones and tablets on the ship LAN

**Source:** CP3 #6.

- **Decision or question.** Will crew use phones or tablets with the vessel
  server?
- **Why it is needed.** Each device must trust the vessel's certificate
  authority. The client installer does this for PCs; phones and tablets
  need it by hand.
- **Options.** PCs only, or document a manual install for phones and
  tablets.
- **Recommended.** PCs only, for go-live.
- **If not decided.** Phones show certificate warnings.
- **Today.** PCs only.

### PD-51. Content-Security-Policy for the pages

**Source:** CP3 #11.

- **Decision or question.** Should the pages get a Content-Security-Policy?
- **Why it is needed.** It limits the damage from injected scripts. It is
  possible only after PD-03, and inline scripts need work.
- **Options.** Add one after PD-03, or not at all.
- **Recommended.** Add a report-only policy after PD-03, then enforce it.
- **If not decided.** No policy.
- **Today.** None.

### PD-53. Moving a record from one vessel to another

**Source:** CP5 15.3; CP7 (transfers).

- **Decision or question.** When a record (or a crew member) moves to
  another vessel, should the old vessel's copy be cleaned up?
- **Why it is needed.**
  - A vessel cannot move a row to another vessel; the cloud can.
  - The old vessel keeps its copy.
  - Crew transfers are noticed when made through the user screens, but not
    when made directly in the database.
- **Options.** Build a cleanup rule, or accept the leftovers.
- **Recommended.** Accept for now. Make all transfers through the screens.
- **If not decided.** Leftover copies stay on the old vessel.
- **Today.** Kept.

### PD-54. Files left on disk after their record is deleted

**Source:** CP7 section 9.

- **Decision or question.** Should files be removed when their record is
  deleted?
- **Why it is needed.** Backups and other records may still need them.
  Keeping them uses disk space.
- **Options.** Keep them (today), or a periodic cleanup after the backup
  retention period.
- **Recommended.** Keep them for now.
- **If not decided.** Disk use grows slowly.
- **Today.** Kept.

### PD-55. Legacy attachments with unusual stored extensions

**Source:** CP7 section 9.

- **Decision or question.** Older attachments kept the extension the
  browser sent. A vessel refuses to send those with unusual extensions.
  Should they be renamed once, to their detected type?
- **Why it is needed.** It affects only legacy files. The error message
  says why a file was refused.
- **Options.** A one-off rename job, or leave them.
- **Recommended.** Count them on the cloud first. Rename them only if there
  are many.
- **If not decided.** Those few files do not travel.
- **Today.** Refused.

### PD-56. Mass assignment in `ServiceRequisitionApproval`

**Source:** CP2 #7.

- **Decision or question.** Should `ServiceRequisitionApproval` list its
  writable columns, instead of `$guarded = []`?
- **Why it is needed.** With `$guarded = []`, a crafted request could set
  any column, including its own `sync_uuid`. The database refuses a
  duplicate, and the value never changes after insert.
- **Options.** List `$fillable`, or leave it.
- **Recommended.** List `$fillable`. It is a small change to existing cloud
  code, so it needs your approval.
- **If not decided.** A small exposure stays.
- **Today.** `$guarded = []`.

### PD-57. Changing sync settings of an installed server

**Source:** CP8 section 6.

- **Decision or question.** Should the guided installer be able to change
  the sync settings of an existing server?
- **Why it is needed.** Today, you use an answer file with only `[sync]`,
  or `install-server.ps1`.
- **Options.** Add a page to the guided installer, or keep the current way.
- **Recommended.** Keep the current way. It is rare and done by IT.
- **If not decided.** As today.
- **Today.** Answer file or script.

### PD-32. Laravel 11 out of security support (deferred)

**Source:** Audit R11.

- **Deferred (2026-11-23).** You asked to ignore this item for now. It is not a blocker, no upgrade task is planned, and the Laravel version does not change. It moved from Important to Optional. The text below is kept for when you revisit it.
- **Decision or question.** Should the application be upgraded to a supported Laravel version, and when?
- **Why it is needed.** Vessels are hard to patch. A framework without security fixes stays exposed on every ship until a technician visits.
- **Options.** Upgrade before the first vessel; after the pilot; or stay.
- **If not decided.** The fleet runs an unsupported framework. This is listed as an accepted risk in the roadmap.
- **Today.** Laravel 11.

### PD-52. A tool to merge duplicate records (not needed for the pilot)

**Source:** CP5 15.3.

- **ADR-003 (2026-11-23).** The ADR-002 review screen that needed it is withdrawn, so it moved back from Critical to Optional. PD-18 decides which exact duplicates sync merges on its own.
- **Decision or question.** Should a tool be built to merge duplicates that were not merged automatically, for example two "Wartsila" entries with different spelling?
- **Why it is needed.** Such duplicates are refused and listed for review. A person then merges them by hand in the database.
- **Options.** Build it, or keep merging by hand.
- **Recommended.** Wait for the pilot. Build it if such duplicates turn out to be common.
- **If not decided.** Merging stays manual.
- **Today.** Manual.

### PD-66. May a vessel edit or withdraw its own pending entry? (withdrawn)

**Source:** ADR-002 (superseded).

- **Withdrawn (2026-11-23).** ADR-003 has no pending entries. A vessel edits and deletes its entries like any other, within the existing permissions. Moved from Important to Optional; kept so that the number stays valid.

---

## Appendix A. Known limits that need no decision

These are recorded in the checkpoint documents. They are accepted unless
you say otherwise.

| Limit | Source | Why it is acceptable |
|---|---|---|
| Switching the client between host name and IP address signs the user out | CP4 12.4 #10 | Cookies belong to a host name. It happens only if DNS fails. |
| A system proxy that does not exclude local addresses breaks the client | CP4 12.4 #11 | Unusual on ship LANs. The fix is a proxy exclusion. |
| Rolling the server back to a release before Checkpoint 4 leaves the discovery service failing | CP4 12.4 #12 | The web application is unaffected. Stop the service or reinstall. |
| The UDP 47810 firewall rule allows `php.exe`, which other services also run | CP4 12.4 #13 | Only the discovery service listens on that port. |
| Stopping the queue service ends a running job at once | CP3 #9 | The job is retried after 90 seconds, and jobs are safe to run twice. |
| Outbox event ids can commit out of order | CP2 #3 | Handled in CP5: `OutboxSequencer` numbers each event once it is committed, so a late commit is not skipped. |
| A user created before their role is routed to every vessel once | CP2 #4; CP6 section 10 | The password is withheld, so no hash leaks. |
| A batch refused with 422 is resent every cycle | CP5 15.3 | Keep `sync.batch` the same on both sides. |
| An office write to a vessel-owned column outside the screens is ignored on board | CP6 section 10 | Corrected by the vessel's next save of that row. See PD-34. |
| Service requisition sync is less exercised by tests (one round trip) | CP6 section 10 | Covered in the Windows test (PD-15). |
| A MySQL 8.x dump does not load into 5.7 | CP3 #8 | Relevant only if the cloud stays on 5.7 (PD-07). |
| Future migrations need a sync check (`config/sync.php`, `sync:triggers install` after a renamed lookup column) | CP2 #6 | A MySQL test catches a missing table. This is a development rule. |
| Server installer is several hundred MB | CP8 section 6 | Application updates use the 18 MB release zip. See PD-30. |

## Appendix B. Items raised earlier and since resolved

| Item | Raised in | Resolved in |
|---|---|---|
| Uploads kept the browser's extension, so a crafted `.php` could be saved | CP3 #4, CP4 12.4 #14 | Checkpoint 7, SafeUpload: the server chooses the name and extension. Files already on the cloud are covered by PD-01. |
| The cloud scheduler refreshed PMS jobs for offline-enabled vessels | CP1 #4 | Before Checkpoint 5: `pms:refresh-jobs` skips vessels with their own server. |
| No outbox pruning | CP2 #5 | Checkpoint 5: `sync:prune`, scheduled. |
| Nine failing tests | Checkpoint 0, CP1 to CP4 | Seven fixed in Checkpoint 8; the last two settled as PD-04 and updated in Checkpoint 10. |
| Backups did not include uploaded files | CP3 #12 | Checkpoint 7. |
| Attachments and files did not sync | CP5 15.3; CP6 section 10 | Checkpoint 7 file channel (module `@attachments`, off by default). |
| The ADR-002 proposal workflow (pending entries, review screen, approvers) | ADR-002, PD-63 to PD-66 | Withdrawn: superseded by ADR-003 on 2026-11-23. |
| Master's password reset, office one-time codes, suspension by the master | PD-11 options B and C | Withdrawn: ADR-004 gives these to a Superadmin only. |
| Vessel users deleting or retiring records (ADR-003 point 4); fleet-wide effect of a vessel retirement (PD-68 B) | ADR-003, PD-68 | Withdrawn by ADR-006 on 2026-11-23. |

## Working assumptions in force

Until you answer, the system runs on these assumptions. They come from
roadmap section 13 and the provisional rules of Checkpoints 5 to 7.
ADR-001 decides the first row. The rest are still assumptions, even where
ADR-001 points the same way.

| Assumption | Item |
|---|---|
| The cloud is read-only for a commissioned vessel's ship roles. **Decided by ADR-001.** | PD-05 |
| **Decided (PD-06):** the office does only PMS job approval for offline vessels; their PMS records and equipment list are read-only on the cloud | PD-06 |
| Cloud MySQL allows triggers and can match the vessels' major version | PD-07 |
| Only reference lists sync; business modules are off | PD-09 |
| **Decided (ADR-004):** only a Superadmin blocks, unblocks or resets passwords, on board or on shore. Recommended until confirmed: a block wins over an unblock; a Superadmin reset wins; users keep changing their own password | PD-11, PD-69, PD-70 |
| Deactivated users are refused everywhere | PD-12 |
| Ship wins a stage conflict (ruled out; recommended: the office rejection wins at sync, workflow unchanged); vessel stock figure adopted; both flagged | PD-13, PD-14 |
| Certificates and surveys: newest wins, flagged | PD-17 |
| **Decided (ADR-003, ADR-006):** vessel users create and update four master-data lists directly, no approval, and delete nothing. Items with the same `item_key` merge; other duplicates wait; vessel-linked items only on board | PD-18, PD-19, PD-58 |
| No shore user signs in on board, except Superadmins once ADR-004 is built | PD-20, PD-70 |
| Office procurement files are sent to vessels | PD-22 |
| Clocks are trustworthy | PD-26 |
| Automatic sync every 5 minutes when online; resumes by itself (ADR-001 Clarification A) | PD-27 |
| Windows 10 22H2 or later, 64-bit (the installers accept older builds) | PD-28 |
| Updates by technician or USB | PD-30 |
| **Decided (ADR-005):** backups stay on the master PC only | PD-25 |
| **Decided (PD-68 A):** an office retirement or deletion and a vessel's change while apart: the office's side stands, the change is kept in the conflict history and flagged | PD-68 |
| **Decided (PD-72):** draft edits stay; PMS job deactivation refused to vessel users. Part B: no removal mechanism; wrong entries are corrected by editing | PD-72 |
| **Decided (PD-73):** only the office marks a PMS component inactive or active again; vessel users are refused, on the screen, the server and at sync | PD-73 |
| Shell client over the existing pages | PD-48 |
