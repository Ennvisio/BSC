# ADR-001 conformance review

Second review, 2026-11-23. The first review (2026-11-22, findings F1 to
F11) was in [architecture-decisions.md](architecture-decisions.md). It is
repeated here, corrected, and extended with F12 to F19. F20 and F21 were
added on 2026-11-23, while checking the code for ADR-003 and ADR-004; the
last section re-assesses earlier findings against those decisions.

**Question asked.** Where could the existing code conflict with
[ADR-001](architecture-decisions.md#adr-001-operating-model-vessel-authority-office-administration-cloud-hub),
causing data loss, unauthorized edits or silent overwrites?

**Scope.** I read:

- the sync rules (`config/sync.php`) and the conflict code
  (`ChangeApplier`, `ReplicationRules::keepsLocal`);
- the mode guards (`EnforceAppMode`, `config/navisuite.php`, `PmsAccess`);
- the controllers that write the affected records: `HomeController`,
  `AttachmentController`, `EquipmentController`, `ItemImportController`,
  `StockController`, `StockConsumptionController`;
- the foreign keys in the migrations.

Nothing was run against real data, and no code was changed.

## How to read the findings

| Column | Meaning |
|---|---|
| Risk | **Loss**: data disappears. **Unauthorized**: someone changes what they should not. **Silent**: a change is overwritten and nobody is told. |
| Live | **Today**: it can happen on the cloud now, without any sync. **Offline vessels**: only for vessels marked offline, of which there are none yet. **Module on**: only once that sync module is switched on. All business modules are off. |
| Severity | **High**: authoritative data lost, or an approval decided by the wrong side. **Medium**: a copy diverges, or a change is lost silently, but the authoritative record survives. **Low**: unlikely, or already mitigated. |

## Findings

| # | Finding | Risk | Live | Severity | Item |
|---|---|---|---|---|---|
| F20 | **Any signed-in user can administer users.** `/user/store`, `/user/update` and `/user/delete` sit outside every role-checked route group; `HomeController` checks only sign-in, and `UserFormVal::authorize()` returns true. Posting to them sets another user's password, role, vessel or block. The menu shows them to super-admin only. ADR-004 gives block, unblock and password to a Superadmin only. | Unauthorized | **Today** | **High** | PD-11, PD-71 |
| F12 | **Permanent delete cascades through vessel records.** Super-admin's trash (`HomeController::permanentDelete`) hard-deletes items, requisitions, vessels, certificates, surveys and categories. Foreign keys cascade. Deleting an item removes its stock movements, consumptions and vessel links. Deleting a requisition removes its form parts, procurement steps and invoices. Deleting a vessel removes its stock, consumptions, equipment and service requisitions. Once synced: `items` has no `deletes` limit, so the delete would go to every vessel. MySQL does not fire triggers for cascaded rows, so the vessel's own ledger lines would vanish without being recorded or reported. A requisition delete is refused by the vessel, so the cloud loses its copy and the two disagree. Whether a vessel would apply a delete of its own `vessels` row is not tested. | Loss | Today (cloud); worse with Module on | **High** | PD-60, PD-36 |
| F13 | **Deleting an attachment removes it from procurement stages and consumption records.** `AttachmentController::destroy` lets the uploader delete a file unless it is linked to a requisition item. It does not check links to procurement steps or consumption records, and those links cascade away. Procurement steps are permanent records in sync; their documents are not. | Loss | Today; spreads once `@attachments` is on | **High** | PD-61 |
| F14 | **Any signed-in user can edit or retire any catalog item.** `/item/update` and `/item/delete` have no role check (`ItemFormValidate::authorize()` returns true). Once items sync, a crew member's edit changes the item for the whole fleet; with `latest` and no review, it can overwrite the office's version. | Unauthorized, Silent | Today; fleet-wide once `@master` is on | **High** | PD-58 |
| F21 | **Any signed-in user can create, edit or retire requisition categories.** `/category/store`, `/category/update` and `/category/delete` check only sign-in; the menu shows them to super-admin only. (Blocked on board already.) | Unauthorized | **Today** | Medium | PD-71 |
| F15 | **Replacing a certificate copy deletes the previous file at once** (`HomeController`, certificate update). If the ship and the office both replace it while apart, the newer path wins (flagged, because certificates have review). The other file survives only as a path in the conflict record. | Loss | Today; conflict once `@master` is on | Medium | PD-62, PD-59 |
| F7 | **The ship's action overrides an office rejection.** In the two known cases (a superintendent rejects while the master forwards, or while the raiser confirms receipt), the vessel's whole status group stands. The rejection is kept only inside the conflict record. It is flagged, but the requisition continues as if not rejected. This contradicts the owner's direction for PD-13. | Unauthorized | Module on (`@requisitions`) | **High** | PD-13 |
| F16 | **An office password reset can be undone from the vessel.** `users.password` is shared, and the newest change wins by timestamp, without a review flag. A crew member's later change on board, or a vessel clock that runs ahead, beats an office reset made, for example, after a suspected compromise. | Unauthorized, Silent | Module on (`@master`) | **High** | PD-11, PD-59 |
| F1 | **Vessels can overwrite office master data.** Budget groups, certificate categories, item groups and catalog items are two-way, newest wins, with no review flag. Crew can change budget groups and certificate categories today, and create item groups and items. (The first review also listed manufacturers. Crew cannot write them on board, because `pms/master/*` is blocked, so the two-way rule is unnecessary rather than harmful.) | Unauthorized, Silent | Module on (`@master`) | Medium | PD-58, PD-59 |
| F2 | **Super-admin can edit an offline vessel's equipment list on the cloud** (`EquipmentController`, no offline check, unlike `PmsAccess`). `vessel_equipment` travels vessel to cloud only, so the edit never reaches the vessel and is overwritten at the vessel's next change. | Silent | Offline vessels | Medium | PD-06, PD-34 |
| F3 | **Office writes to vessel-owned data on the cloud are not refused in general.** Only ship roles are locked, plus super-admin's PMS edits (and F2 is the gap). An office edit to a vessel-to-cloud table, or to a vessel-owned column, is overwritten without a conflict record. The trash (F12) is one such path. Not every screen has been checked. | Silent | Offline vessels; Module on | Medium | PD-34 |
| F4 | **On board, a write to cloud-owned data outside `blocked_write_paths` is overwritten** by the next cloud change, with no conflict recorded. The main screens are blocked; the full list has not been checked route by route. | Silent | Module on | Medium | PD-34 |
| F5 | **A photo or signature changed on board is overwritten** at the office's next edit of that user, because user columns other than the password belong to the cloud. | Silent | Module on (`@master`) | Medium | PD-21 |
| F11 | **Attachment records and procurement-step links are two-way, newest wins, not flagged.** New uploads never replace one another: each is its own record and file. A conflict needs the same record edited on both sides. | Silent | Module on (`@attachments`) | Low | PD-59 |
| F6 | **`vessel_surveys` is newest wins without review.** `vessel_certificates` has review, and CP6 says both do. | Silent | Module on (`@master`) | Medium | PD-17, PD-59 |
| F17 | **Shore users' placeholder password hash.** Shore users arrive on board with one fixed bcrypt hash from `config/sync.php`, and its plaintext is not recorded. Vessel mode also refuses anyone but this vessel's crew at sign-in, so a second guard exists. A value that can never match any password would be safer. | Unauthorized | Module on (`@master`) | Low | PD-11 |
| F18 | **Deactivation reaches a vessel only at its next sync.** Until then, a deactivated crew member can still sign in on board. This is inherent to offline working; what to do about it is a business choice. | Unauthorized | Module on | Medium | PD-11 |
| F8 | **The office approves PMS jobs**, but ADR-001 lists only requisition approvals. This is not a defect; it needs confirming. | (none) | Module on (`@pms`) | Low | PD-06 |
| F9 | **Offline blockers:** libraries loaded from CDNs, no vessel bootstrap, password reset by email. | Loss of service | Today on board | **High** | PD-03, PD-08, PD-11 |
| F10 | **Conflicts are visible only through `sync:conflicts`.** | Silent in practice | Module on | **High** (pilot requirement) | PD-35 |
| F19 | **The cloud cannot see a vessel's waiting changes.** It knows the last contact (`sync_nodes.last_seen_at`), batches, conflicts and its own backlog for the vessel. It does not know how many changes wait on board, because the handshake does not report them. PD-35 needs this. | (visibility) | Module on | Medium | PD-35 |

## Checked and consistent

| Area | Why it is safe |
|---|---|
| Delete against change | With `latest`, a change beats a delete on both sides, so a delete never wins over an edit made apart (`ReplicationRules::keepsLocal`). With `cloud` or `vessel`, the owner decides, and `deletes` limits who may delete at all. |
| Stock ledger | Stock movements and consumptions are append-only, and travel vessel to cloud only. The balance (`stock_qty`) never travels. The cloud derives it, and a mismatch is flagged. |
| Requisition ownership | Requisitions are created and deleted only on board. Each office approval and procurement column belongs to the cloud, so the vessel cannot set an office signature. A duplicate number goes to review. |
| Ship crew on the cloud | Once a vessel is marked offline, its crew are read-only on the cloud. |
| Office data on board | User, role, vessel, category, PMS master, trash and procurement-completion screens are blocked in vessel mode. |
| Users | Users are created and removed only in the office. A delete through the user screen is a soft delete (`status` false). Deactivation is office-owned and wins over any password change, because sign-in checks it first. |
| Network | Only the vessel connects (see ADR-001 Clarification A). |
| Duplicates | Natural-key duplicates go to review, except catalog items with the same `item_key`, which are merged (PD-18). |

## What follows

Every finding has a matching item in
[pending-decisions.md](pending-decisions.md), and a step in
[implementation-roadmap.md](implementation-roadmap.md).

Six findings are live on the cloud today, without any sync: F12, F13,
F14, F15, F20 and F21. They are existing behaviour. Each fix changes the
cloud application, so each needs your approval first. Under the approved
branch workflow (PD-67) they are fixed on `main` as urgent production
fixes (roadmap Checkpoint 10).

## Re-assessment after ADR-003, ADR-004 and ADR-005 (2026-11-23)

| Finding or row | Before | Now |
|---|---|---|
| F1 | Vessels overwrite office master data | Budget groups, certificate categories, catalog items and item groups are maintained by vessels and the office together (ADR-003). Vessel changes are authorized. What remains: newest wins without a flag (PD-59 A), and a change against a delete (PD-68). Roadmap Checkpoints 14 and 17. |
| F14 | A defect: no role check on items | A permission question, **decided by PD-71 C** (2026-11-23): every office role may edit items for this phase. See the status below. |
| F16 | An office reset can be undone from a vessel | ADR-004: only a Superadmin resets. Recommended rule: a Superadmin reset wins over a user's own change, flagged (PD-11, PD-69). Checkpoint 16. |
| F17 | Placeholder hash for shore users | Still applies to every shore user except Superadmins, whose real hashes will be sent (ADR-004, PD-70). |
| F18 | A deactivation reaches a vessel only at the next sync | Unchanged for a shore Superadmin's block. A Superadmin on board can now block at once (ADR-004). The master's suspension is withdrawn. |
| F9 | Password reset by email is an offline blocker | Covered by a Superadmin on board, or the next sync (ADR-004). |
| "Checked and consistent: delete against change" | Safe: a change beats a delete | **No longer acceptable.** ADR-003 requires that a deleted record does not reappear. A change winning over a delete recreates it. Roadmap Checkpoint 14, PD-68. |
| "Checked and consistent: users" | Users created and removed only in the office | True in the menu, not on the server (F20). Blocking will also come from a Superadmin on board (ADR-004). |
| "Checked and consistent: office data on board" | Blocked in vessel mode | Still true. A Superadmin on board will be allowed block, unblock and password actions only (PD-70). |

## Status after Checkpoint 10 (2026-11-23)

Built on `feature/offline-sync`, not committed, not deployed. Details in
[implementation-roadmap.md](implementation-roadmap.md) section 17.

| Finding | Now | Still open |
|---|---|---|
| F20 | **Closed.** Ship users are refused every user screen and action. Only a super-admin blocks a user, sets another user's password (ADR-004), creates a user or changes a role or vessel (PD-71 A). The Users screen offers none of these to anyone else. | Unblock is Checkpoint 16. |
| F21 | **Closed.** Only a super-admin creates, edits or retires requisition categories, in the screens and on the server (PD-71 B). | None. |
| F14 | **Closed for this phase by decision.** PD-71 C (decided): every office role may edit items; vessel users edit them under ADR-003. PD-71 C grants no retirement: retiring an item stays as it is today for office roles, and ship users cannot (ADR-006). | Revisit after this phase, if you wish. Nothing about PD-71 is unresolved. |
| F12 | **Closed.** PD-60 (decided): the trash's permanent delete is refused while anything refers to the record and for any record of an offline vessel, so it no longer cascades. Ship users cannot use the trash (ADR-006). A defect found on the way is fixed: deleting a trashed vessel survey or certificate used to delete the survey type or certificate name with the same id. Tested on MySQL too (`TrashGuardMySqlTest`): a refused item, requisition or vessel delete leaves every child row. | MySQL cascade tests at sync, per module (PD-36). |
| F13 | **Closed.** PD-61 (decided): a file on a requisition line, a procurement stage or a consumption record is not deleted; any other delete hides it and keeps it. | None. Note: nobody can reach the delete through the screens today (the route admits only two ship roles, which ADR-006 refuses), so the guard is a server-side protection. By your instruction (2026-11-23), deleting attachments is out of scope for now: no delete action or permission is added. |
| F15 | **Closed.** PD-62 (decided): every certificate copy is kept, with who and when; earlier copies are listed in the edit form. The history now travels between servers as immutable records with checked files, so both sides replacing a copy while apart keeps both (Checkpoint 15, certificate part). | Photos and signatures (PD-59 B, PD-21). |
| F2 | **Closed.** PD-06 (decided): the cloud refuses equipment edits for a vessel that runs its own server, as it already did for PMS. | None. |
| F8 | **Settled.** PD-06 (decided): office PMS job approval stays. | None. |
| PD-73 (decided 2026-11-24, not a finding) | **Closed.** The PMS component "Inactive" switch is restricted for vessel users: disabled on their screen, refused on the server in both directions, and a vessel's change refused at sync and flagged (`sync.office_only_columns`). Office permissions, ordinary edits and the CFM import are unchanged. | For a vessel with its own server, nobody can change the switch (the office copy is read-only under PD-06). Your question. |

## Status after Checkpoint 14, first part (2026-11-23)

Built on `feature/offline-sync`, not committed, not deployed. Details in
[implementation-roadmap.md](implementation-roadmap.md) section 17.

| Finding | Now | Still open |
|---|---|---|
| PD-60 at sync | **Built.** A vessel never applies a delete of its own vessel row, nor one that would cascade into rows it holds; both are refused and flagged. | None. |
| Cloud deletes of vessel-owned records | **Built** (your instruction, 2026-11-23). On the vessel, a delete from the cloud of a record the vessel owns is refused, logged with the pull and flagged; nothing is deleted or cascade-deleted. On the cloud, office users cannot delete an offline vessel's own records (confirmed 2026-11-24): every model delete is checked against the same rule the vessel uses, every office delete route was checked and refuses, and the CFM import's removal option is refused for such a vessel. | Certificates and surveys are not counted as vessel-owned until PD-17 is decided. |
| "Delete against change" (above) | **Closed.** A deleted record is never recreated by a late change, including after the outbox is pruned; the change is kept in a conflict flagged for review. A delete stands against a change made meanwhile, and an office retirement stands against a vessel's offline edit, which is kept in the conflict and flagged (PD-68 A, decided 2026-11-23: option 1). Every delete is recorded with who, when and where from. | Re-applying deletions after a backup restore (Checkpoint 21). |
| F3, F4 | **Partly.** A change the receiver refuses (ownership, a permanent record, ADR-006, a vessel-owned record deleted from the cloud) is now recorded as a conflict needing review, never only in the inbox. For deletes, F3 is closed on the cloud too: office users cannot delete an offline vessel's own records (PD-34, confirmed 2026-11-24). | Wrong-side edits that are overwritten without any record (PD-34, Checkpoint 14 item 7). |
| ADR-006 at sync | **Built.** A delete or retirement arriving from a vessel is refused and flagged, except the PD-72 draft edits and replaced child rows. | None. |

