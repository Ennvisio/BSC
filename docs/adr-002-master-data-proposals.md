# ADR-002 design: master-data proposals

> **Superseded on 2026-11-23. Do not implement.** ADR-002 was replaced by
> [ADR-003](architecture-decisions.md#adr-003-vessel-users-maintain-master-data-and-delete-their-own-records-directly):
> vessel users create, update and delete master data directly, with no
> pending state, proposal review or office approval. The current design is
> [adr-003-to-005-design.md](adr-003-to-005-design.md). This document is
> kept only as a record of what was considered. Its findings about the
> existing lists (section 2) are carried into the new design, corrected
> where needed.

Status (historical): **proposed design, not approved, not implemented.** It implements
[ADR-002](architecture-decisions.md#adr-002-vessels-propose-new-master-data-the-office-approves).
Where it needs a choice that ADR-002 does not make, it says so and points
to the open item in [pending-decisions.md](pending-decisions.md) (PD-63 to
PD-66). Recommendations are suggestions only.

Date: 2026-11-23.

## 1. Summary

1. **On board.** A crew member creates a new entry. The vessel server writes
   the entry and a **proposal record** in one transaction, and shows the
   entry as "Pending office approval".
2. **Sync.** Both go up with the next sync. The cloud keeps the pending entry
   only for the proposing vessel; other vessels do not receive it.
3. **Review.** An authorized office user reviews the proposal on the cloud,
   then makes one of these decisions:
   - **approve** it, optionally after editing it;
   - **link** it to the identical existing record;
   - **merge** it into a chosen existing record;
   - **reject** it.
4. **The decision travels down.**
   - *Approved:* the entry becomes ordinary master data and is sent to the
     relevant vessels.
   - *Linked or merged:* the vessel points its own records at the existing
     record instead.
   - *Rejected:* the entry is retired on board, and the crew see the reason.
5. **Existing office records** stay read-only on board for these five lists.
6. **The audit trail** (the original vessel, creator, time and values) is
   kept in the proposal record, whatever the outcome.

## 2. The five lists today

| List | Created on board today | Changed or retired on board today | Sync rule today (switched off) | Unique key | Retire flag | Creator column |
|---|---|---|---|---|---|---|
| Budget groups | Yes, any ship crew | Yes | both, newest wins, no review | `name` | `status` | `created_by` |
| Certificate categories | Yes, any ship crew | Yes (retire refused when in use) | both, newest wins, no review | `name` | `status` | `created_by` |
| Manufacturers | **No** (`pms/master/*` blocked) | No | both, newest wins, no review | `name` | none | none |
| Item groups | Yes, implicitly, when an item with a new group path is added | No rename screen found | both, newest wins, no review | `category_id, parent_id, name` | none | none |
| Catalog items | Yes, import or manual add | Yes, **any signed-in user** (finding F14) | both, newest wins, merged by `item_key` | `item_key` | `status` | `created_by` |

So:

- Manufacturers and item groups have no retire flag and no creator column.
  The audit fields required by rule 7 cannot live on those rows, which
  points to a separate proposal record (section 3).
- Exact duplicates cannot exist side by side, because of the unique keys.
  Matches are case-insensitive under MySQL's default collation, so "Engine
  Stores" and "engine stores" count as the same name.
- A catalog import on board can create hundreds of items, and the item
  groups for them, in one go.

## 3. The proposal record

**Recommended:** one new table, `master_data_proposals`, synced like any
other table. It is an additive migration and needs your approval. Existing
tables stay unchanged.

| Column | Meaning | Owner |
|---|---|---|
| `sync_uuid` | The proposal's identity, made on board | vessel |
| `table_name`, `row_uuid` | Which entry is proposed: the entry's own UUID, made on board | vessel |
| `vessel_id` | The proposing vessel (rule 7) | vessel |
| `proposed_by` | The crew member (rule 7) | vessel |
| `proposed_at` | Creation time on board (rule 7). Never changed. | vessel |
| `proposed_values` | The values as first proposed, frozen for audit | vessel |
| `parent_proposal_uuid` | For an item group or item created as part of another proposal (an item and its new groups, one import) | vessel |
| `status` | `pending`, `approved`, `linked`, `merged`, `rejected`, and `withdrawn` (only if PD-66 allows it) | cloud, except `withdrawn` |
| `target_uuid` | For `linked` and `merged`: the existing record that replaces the entry | cloud |
| `duplicate_hint_uuid` | An existing record the cloud found that may be the same | cloud |
| `decided_by`, `decided_at`, `decision_note` | Who decided, when, and why. A note is required for a rejection. | cloud |

**Alternative:** add `approval_status`, `proposed_by_vessel_id`,
`proposed_by` and `proposed_at` columns to each of the five tables.

- It is simpler to filter on.
- But it changes five existing tables.
- It still has no place for the frozen original values.
- Every existing cloud query on these tables would then need the new
  filter.

Not recommended.

Existing rows have no proposal record. They count as office-managed. Entries
that crew created on the cloud in the past are **not** made pending
retroactively.

## 4. Lifecycle

| From | To | Who | Where | Effect on the entry | Effect on the proposing vessel |
|---|---|---|---|---|---|
| (none) | pending | Crew | On board | Entry and proposal created in one transaction | Usable or not, as PD-63 decides; labelled pending |
| pending | approved | Office | Cloud | The entry is saved as office data, possibly edited. That save is what sends it to every relevant vessel. | Label removed; office edits arrive |
| pending | linked | Office | Cloud | The identical existing record replaces the entry (exact duplicate) | References repointed to the existing record; entry retired or removed |
| pending | merged | Office | Cloud | A chosen existing record replaces the entry | As for linked |
| pending | rejected | Office | Cloud | Unused: retired (or removed where there is no retire flag). In use: see section 8. | Entry retired; the reason is shown |
| pending | withdrawn | Crew | On board | Only if PD-66 allows it, and only while pending | Entry retired |

A decision is final. The cloud accepts a decision only while the proposal
is still `pending`. It checks this in the same database update, so two office
users cannot both decide the same proposal; the second is told it is
already decided.

## 5. Changes to the sync rules

For each of the five lists in `config/sync.php`:

| Rule key | Today | After ADR-002 | Why |
|---|---|---|---|
| `direction` | both | both | Entries come up as proposals; decisions and approved entries come down |
| `creates` | (anyone) | both | Rule 1 |
| `deletes` | (anyone) | cloud | Rule 5; plus the PD-60 delete guard |
| `conflict` | latest | cloud | Rule 5: the office version of an existing record wins |
| `review` | (none) | true | Rule 5: a vessel change to an office record is flagged, never dropped silently |
| `columns` | (whole row shared) | `other => cloud`, plus the vessel's own pending rows if PD-66 allows edits | Rule 5 |
| `on_duplicate` | review (items: merge) | **propose**, a new mode: link to the existing record and record the proposal as a duplicate for the office | Rule 4 |
| Vessel routing (`config/sync.php` `tables`) | fleet-wide | fleet-wide when decided; **only the proposing vessel while pending or rejected** | Rule 6: pending entries do not reach other vessels |

**New table rule:** `master_data_proposals`.

- Two-way. Created only on board.
- The vessel owns the proposal columns; the cloud owns the decision
  columns.
- Never deleted by sync.
- Routed to the proposing vessel only.

**Engine changes:**

1. **A refused change is flagged.** A vessel change to a column it does not
   own, on a table under these rules, is refused and flagged for review.
   Today such a change is ignored without a trace (CP6 section 10).
2. **New `on_duplicate: propose` mode** (section 7).
3. **Routing that follows the proposal state.** The vessel expression for
   these tables reads `master_data_proposals`.
4. **On approval, the entry is re-sent.** Approval saves the entry, so the
   existing capture sends it to every vessel now in scope.
5. **A new apply hook, `ProposalDecisionHook`, on the vessel.** It applies
   linked, merged and rejected decisions (section 6).
6. **`ItemKeyHook` runs again** when an item group is linked or merged,
   because `item_key` contains the group's local id.

**On-board screen changes:**

- The create forms (budget group, certificate category, item import and
  manual add, and a manufacturer path if PD-64 adds one) write the
  proposal too.
- The edit and retire actions for office records of these lists are
  blocked in vessel mode (`blocked_write_paths`, or a check per record if
  the vessel may edit its own pending entries).
- Pending entries are labelled wherever they are listed or picked.

## 6. Mapping vessel UUIDs to canonical records

Every record already has a UUID made where it was created. There are no
separate "temporary" ids, which keeps the mapping small.

- **Approved.** The vessel's UUID **becomes** the canonical UUID. Nothing is
  remapped, and every reference made on board stays valid. This is the
  common case.
- **Linked or merged.** The entry's UUID is replaced by the existing
  record's UUID (`target_uuid`):
  1. **On the cloud.** The decision adds the pair to `sync_aliases`. This
     table already exists (Checkpoint 6), and every incoming reference to
     the alias already resolves to the target (`RowCodec`). Records the
     cloud already holds that point at the entry are repointed to the
     target, inside the decision's transaction.
  2. **On the vessel.** The decision arrives on the proposal row.
     `ProposalDecisionHook` then:
     - adds the same alias;
     - repoints every local foreign key that names the entry, using the
       references listed in `config/sync.php`;
     - retires the entry, or removes it where there is no retire flag.

     This runs as a sync-origin write, so the repointing is not sent back as
     new vessel changes. That matters, because stock movements and
     consumptions are permanent records and the cloud would refuse changes
     to them.
  3. **Order does not matter.** A record that refers to the entry and
     arrives after the decision resolves through the alias. One that
     arrived before it was repointed by the decision.
- **Item groups first.** Linking or merging an item group changes the
  `item_key` of every item under it. The hook recomputes them, and an item
  that now matches an existing item becomes a duplicate for the office
  (section 7). Groups are therefore decided before the items under them;
  the review screen keeps an item and its new groups together.
- **Stock under both items.** If the proposing vessel already holds stock
  under both the entry and the target, two stock links would collide. The
  decision is then held for a person, and **stock is never combined
  automatically.** If only the entry has stock on that vessel, its link and
  movements are simply repointed.

## 7. Duplicate detection

| When | Check | Result |
|---|---|---|
| On board, while the crew member types | Same name or key in the local copy | Warn, and offer the existing record. The current item form already does this for article numbers. |
| On arrival at the cloud: exact match | Same unique key as an existing record (`on_duplicate: propose`) | The entry cannot be stored beside the original. It is **linked** at once, so the vessel's references resolve and nothing waits. The proposal is recorded with `duplicate_hint_uuid` and shown to the office as "exact duplicate, linked: confirm". Whether to link at once, or to hold for a decision, is PD-65. |
| On arrival: similar | Same name ignoring spaces, punctuation and case; for items, also the same article number | Stored as pending, with `duplicate_hint_uuid` as a suggestion. The office approves, merges or rejects. |
| Two vessels propose the same thing | The second arrival matches the first pending proposal | Both are shown together. Approving one turns the other into a link to it. |
| Matches a retired office record | Exact match with a record whose `status` is false | Shown as "matches a retired record". The office can reactivate it (then link) or approve as new. |

The cloud never merges two **different** existing office records on its
own.

## 8. Rejection

| Case | What happens | Data kept |
|---|---|---|
| Not used anywhere | Retired (`status` false). Manufacturers and item groups have no retire flag, so they are removed, which is allowed because nothing uses them. | The proposal record, with the original values and the reason |
| Already used, for example on a requisition line, a stock movement or a certificate (only possible if PD-63 allows use before approval) | The office must choose (PD-65): <ul><li>**reject and replace** with an existing record, which works as a merge (section 6), so history points at the replacement;</li><li>or **reject and keep for history**: retired, so existing records keep it, but it cannot be picked again.</li></ul> | Everything. No referencing record is changed except by repointing. |
| On the vessel | The entry is retired locally. The crew see "Rejected by <office user>: <reason>", with the replacement if there is one. | The proposal record |

A rejection never deletes stock movements, consumptions or requisition
lines.

## 9. Retry and idempotency

| Situation | Why it is safe |
|---|---|
| The link drops while proposals are sent | The entry and proposal sit in the outbox and are resent. A batch the cloud already applied is answered from `sync_batches`, not applied again (existing behaviour). |
| The same proposal arrives twice | It carries the same UUIDs, so it is an update with identical values, and nothing changes. |
| A decision arrives twice | It sets the same status and target, so nothing changes. The repoint finds nothing left to repoint. The alias insert ignores an existing pair. |
| Two office users decide at once | Only one update from `pending` succeeds (section 4). |
| A record using the entry arrives after a merge | It resolves through the alias. |
| A vessel is restored from an older backup | Its proposals are resent with the same UUIDs (no duplicates). It then receives the decisions again. This depends on the bootstrap and recovery work (PD-08). |
| A vessel edits its own pending entry after the office decided (if PD-66 allows edits) | Refused and flagged, because once decided the row belongs to the cloud |
| A proposal waits for a long time | Nothing expires. The dashboard (PD-35) shows the age of each pending proposal. |

## 10. Use before approval (open: PD-63)

ADR-002 does not say whether a pending entry can be used at once. The
choice decides how much of section 6 and section 8 is needed.

| Option | On board | Consequence |
|---|---|---|
| 1. **Usable at once, labelled pending** | Crew can raise requisitions, record stock and file certificates against it | Work never waits for the link (ADR-001 principle 5). The office sees "item pending approval" inside the requisition. Rejection of a used entry needs "replace" or "keep for history". |
| 2. Usable in drafts only | Saved, but a requisition cannot be submitted, and no stock moved, until approved | Simpler rejections, but crew wait for the link and for the office |
| 3. Not usable until approved | Entry is visible but not selectable | Simplest, but on a long passage new parts cannot be requested at all |

An office question goes with option 1: may an office approver approve a
requisition whose lines use pending items, or must the items be decided
first? (PD-63)

## 11. How the office reviews proposals

**A "Master data proposals" screen on the cloud, part of the PD-35 review
area.**

- **List.** One row per pending proposal, newest first. Filters by list,
  vessel, age and "has a duplicate hint". An item and the item groups it
  created appear as one group; a catalog import appears as one batch.
- **Detail.** For each proposal:
  - the values as proposed and as they are now;
  - the proposing vessel, creator and creation time on board;
  - where it is already used (requisitions, stock links, certificates), with
    counts and links;
  - the duplicate hint, side by side with the existing record.
- **Actions.**
  - **Approve**, optionally after editing.
  - **Link** to an exact duplicate.
  - **Merge** into a record the office chooses. A preview shows how many
    records will be repointed.
  - **Reject**, with a required reason, and a replacement when the entry is
    in use.
  - **Bulk approve** for an import batch, after review (PD-65).
- **Permissions.** Only the roles you name per list (PD-65). Everyone else
  sees the list read-only, or not at all.
- **Audit.** The decision, the user and the time are stored in the proposal
  record. They appear on the record's history, and on board.
- **On board.** A "My proposals" view shows the vessel's pending, approved,
  linked, merged and rejected entries, with the office's notes.

## 12. Effect on the cloud application

| Change | Cloud affected? |
|---|---|
| New table `master_data_proposals` (additive migration) | Yes: new table. Existing tables unchanged. |
| Sync rule and engine changes | No, while `@master` is off |
| Review screen and "My proposals" | Yes: new screens |
| Pending entries hidden from other vessels' pickers on the cloud | Yes: the pickers for these five lists gain a filter |
| Who may create entries on the cloud | Only if PD-64 extends the pending rule to crew on the cloud |
| Missing role check on catalog item edit and retire (F14) | Yes (roadmap Checkpoint 10) |

## 13. Tests

- **Lifecycle.** Each transition, on the two-server harness and on MySQL.
  A decision is accepted only from `pending`; two office users decide at
  once.
- **Routing.** A pending entry never reaches another vessel. An approved
  entry reaches every vessel in scope.
- **Mapping.**
  - Approve keeps the UUID.
  - Link and merge repoint every reference on both sides, including stock
    movements and consumptions, without sending them as new changes.
  - Records arriving before and after the decision both end up on the
    target.
  - A group merge recomputes item keys.
- **Duplicates.**
  - Exact, case-variant, similar, and the same proposal from two vessels.
  - A match with a retired record.
  - Stock under both items is held, and never combined.
- **Rejection.** Unused, used with replacement, used and kept for history.
  Nothing is deleted from the ledger.
- **Idempotency.** Resent batches, repeated decisions, a restored vessel.
- **Rule 5.** On board, editing or retiring an office record is refused. A
  vessel change to an office record that reaches the cloud is refused and
  flagged.
- **Audit (rule 7).** Vessel, creator, time and original values survive
  every outcome.
