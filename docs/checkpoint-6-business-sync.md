# Checkpoint 6: Business modules on the sync engine

Status: built and tested on 2026-11-21. Not yet enabled on any server. Not yet run on Windows.

This checkpoint connects four modules to the Checkpoint 5 engine:

* requisitions (item and service);
* inventory;
* PMS;
* the master data those modules refer to.

Every rule below comes from the code as it is today (who writes which column, in which controller or service). Where the code does not settle a question, the rule is provisional and listed in section 9.

## 1. Switching it on

Business modules are **off by default**. `SYNC_TABLES` must be the same on the cloud and on every vessel:

```
SYNC_TABLES=currencies,ranks,categories,@master,@inventory,@pms,@requisitions
```

`@name` stands for the tables of module `name` in `config/sync.php` `modules`. `php artisan sync:status` reports two kinds of problem:

* errors in the rules;
* warnings when an enabled table refers to one that is not enabled. A new row in that table would never arrive, so changes that refer to it would wait until they fail.

**Do not enable these modules on a live vessel before the bootstrap checkpoint.** Sync assumes the vessel starts as a copy of the cloud (same rows, same UUIDs). The tests simulate that copy. Production has no copy tool yet.

## 2. What travels, and in which direction

| Module | Down (office to vessel) | Up (vessel to office) | Both ways, column by column |
|---|---|---|---|
| Master data | vessels, roles, certificates, surveys, pms_locations, pms_component_categories, pms_job_types, pms_conditions, currencies, ranks, categories | | users (password only from the vessel); items, item_groups, manufacturers, budget_groups, certificate_categories (whole row, newest wins) |
| Inventory | | stock_movements, stock_consumptions, stock_imports | vessel_items |
| PMS | | pms_components, pms_counter_readings, pms_job_definitions, pms_job_intervals, pms_job_checklist_items, pms_required_parts, pms_used_parts | pms_jobs (office: approval only) |
| Requisitions | order_invoices, order_item_quantity_changes, service_requisition_invoices | order_form_part_edits, vessel_equipment | orders, order_items, order_approvals, order_form_parts, order_procurement_steps, service_requisitions, service_requisition_items, service_requisition_approvals, service_requisition_procurement_steps, vessel_certificates |

Attachments and their link tables are **not** synced. Files are a later checkpoint (Q10).

## 3. New engine features (reusable, not module-specific)

All of these are keys of a rule in `config/sync.php` `replication`. They are applied in `App\Sync\Engine\ChangeApplier`.

| Key | Meaning |
|---|---|
| `columns` | Column owners: `cloud`, `vessel`, `shared`, plus `other` for every column not listed. A receiver never takes a column it owns from the sender. |
| `together` | Shared columns decided as one unit, so a status never sits next to the other side's rejection details. |
| `review` | Conflicts on these columns (or, with `true`, on the whole table) are settled provisionally and flagged `needs_review`. |
| `guarded` | `[from, to]` pairs in which the non-owner may still change a column: the office approving a PMS job. |
| `creates`, `deletes` | The only side allowed to create or delete rows. |
| `row_owner` | The owner of each row, decided by a column's value: requisition Part A is the ship's, Parts B and C are the office's. |
| `append_only` | A permanent record (ledger line, audit entry). Once written, sync never changes or removes it. |
| `on_duplicate: merge` | The same record created on both sides (same natural key) becomes one record. The second UUID is kept in `sync_aliases`, and references to it still resolve. |
| `hooks` | Module logic run inside the same savepoint (`App\Sync\Engine\ApplyHook`). |
| `redact` | Values sent instead of the real ones for rows that do not belong to the receiving vessel. |

### 3.1 Three-way merge

When both sides changed a column-merged row before seeing each other's change, the applier compares three versions of each shared column:

* this server's value;
* the sender's value;
* the value both last agreed on.

The agreed value is kept in `sync_row_bases`. It is updated at three moments:

* when a change is received and applied;
* on the vessel, when the cloud acknowledges a push it applied as sent;
* on the cloud, when the vessel confirms a pull page. Values are offered when the page is sent and promoted when it is confirmed.

| This side | Other side | Result |
|---|---|---|
| unchanged | changed | take the other side's value, no conflict |
| changed | unchanged | keep this side's value, no conflict |
| changed | changed, same value | no conflict |
| changed | changed, different | the table's `conflict` rule decides; recorded in `sync_conflicts`, flagged if `review` |

`updated_at` always takes the later of the two values.

### 3.2 Other engine changes

* Database-generated columns, such as `pms_components.unique_scope`, are never sent or written.
* Scope checks accept UUID primary keys (the PMS tables).
* A reference to a row that does not exist is now left out of the values, so the receiver keeps its own value. Before this checkpoint it was sent as null, which would have blanked legacy data (for example `orders.created_by` holding a name).
* A conflict the cloud found while applying a push is mirrored on the vessel with its real kind, note and review flag.
* Push answers report `merged` as a resolution, together with a `review` flag.

### 3.3 New tables (one new migration, no existing migration changed)

`2026_11_24_100000_create_sync_merge_tables.php`:

* `sync_row_bases` (`peer_node`, `table_name`, `row_uuid`, `base_values`, `offered_values`, `offered_seq`);
* `sync_aliases` (`table_name`, `alias_uuid`, `target_uuid`, `source_node`).

Both tables are listed under `excluded` in `config/sync.php`.

## 4. Requisitions

Ownership of each column follows the controllers:

* `RequisitionController` and `HomeController@storeOrder` (raise on board);
* `RoleController` (approve, reject, deliver, receive);
* `OrderOfficialRemarksController`, `OrderFormPartController`, `ProcurementController`.

| Table | Vessel | Office | Shared (merged) |
|---|---|---|---|
| orders | everything raised on board: req_no, dates, port, title, budget group, machinery, rcv_date, ord_status | deliver_date, official_remarks (+by/at), status_from_am | status, procurement_stage, rejected_by / role / stage / at, rejection_reason, updated_by |
| order_items | order, item, rcv_item_qty, stock snapshot | del_item_qty, invoice_qty, unit_price, line_total | item_qty (master/chief engineer at their stage, SRD at theirs), status |
| order_approvals | cheif_ofcr_app, second_eng_app, master_app, chief_eng_app | every office signature, forward and assignment | |
| order_form_parts | Part A rows | Part B and C rows | |
| order_procurement_steps | the receipt_verification row | every other stage | append-only |
| order_item_quantity_changes | | all (SRD corrections) | append-only |
| order_form_part_edits | all (Part A corrections) | | append-only |
| order_invoices | | all | |

Rows are created and deleted only on board (orders, lines, approvals). Service requisitions follow the same pattern; their procurement is run by SRD.

**Baton conflicts.** Normally only the current holder changes the status. A real conflict needs two people acting on the same stage at once. In today's code, every such case has the ship as the holder:

* a superintendent rejects while the master forwards;
* a superintendent rejects while the raiser confirms receipt.

So the vessel's version of the whole status group stands for now (roadmap 5.5, "holder wins"), and the conflict is flagged for a person on both servers. The office's rejection is kept in the conflict record. See question C6-1.

**Duplicates.** `req_no` is a natural key. A number already used in the office blocks the requisition and raises a `duplicate_key` conflict for review. It is never renumbered automatically (roadmap 5.7). A batch sent twice is answered from `sync_batches` and creates nothing twice.

## 5. Inventory

* Stock on board moves only through `App\Services\StockService`, which writes the balance and a `stock_movements` line together.
* **Only the movement lines travel.** `vessel_items.stock_qty` is a local column, never sent and never overwritten.
* The office's balance is moved by `App\Sync\Engine\Hooks\StockLedgerHook`. For each new movement it checks that the office balance plus the movement's quantity equals the movement's `balance_after`. An `opening` movement declares a figure and does not move stock.
* If the sum does not add up, a `stock_mismatch` conflict is flagged for a person. `SYNC_STOCK_ON_MISMATCH` then decides the balance: `adopt` (default) takes the vessel's figure; `keep` leaves the office figure. See question C6-2.
* A movement applied late never moves the balance backwards; only the newest movement sets it.
* Movements and consumptions are append-only.
* `vessel_items` (the catalog link) can be created by the office (`CatalogService::linkToVessel`) and on board (`StockService`). The same item linked on both sides is merged into one link (natural key: vessel and item). The stock figures (opening, minimum, last supply, updated by/at) belong to the vessel.

**Catalog items.** An item's identity is `items.item_key`, which `CatalogService::itemKey()` computes from the article number, item group and name.

* The key contains the item group's **local id**, so it is never sent. `App\Sync\Engine\Hooks\ItemKeyHook` recomputes it on arrival.
* The same item created on both sides is merged, as a catalog import would do. Requisition lines then point at the merged item.
* Items without an article number (legacy) have no key and are never merged.

## 6. PMS

* Everything is written on board, with the original timestamps and authors: `completed_at`, `completed_by`, `done_by_rank_id`, `rh_at_completion`, readings' `entered_by` and `created_at`.
* The office's only change is approving a job: `approved_by` and `approved_at`, and the status from WaitingApproval to Completed (`guarded`).
* Any other office change to a job is ignored on board, and a job created in the office is refused there.

Two small application changes, both active only on the cloud and only for a vessel with `offline_enabled_at` (behind `navisuite.cloud.lock_offline_vessels`):

* `PmsAccess::canEditPmsVessel`: super-admin can no longer edit that vessel's PMS tree in the office (roadmap Q2). Other vessels are unchanged.
* `pms:refresh-jobs` skips those vessels on the cloud. They refresh on board (VesselSchedule), so there is one writer of their job statuses. `PmsJobScheduler::refreshAll` gained an optional `$exceptVessels` argument.

## 7. Master data

* Users, roles, the vessel record and the PMS reference lists are the office's.
* A crew member may change their own password on board (Q4). Only the `password` column is taken from the vessel; the newest change wins.
* The vessel cannot create or delete users. `remember_token` never travels.
* Shore users arrive on board with an unusable password hash (roadmap 5.7 and Q5), so their names show on signatures but they cannot sign in. Crew of the receiving vessel keep their real hash.

## 8. Tests

The module tests are in `tests/Feature/Sync/Modules`. Each one builds two servers with the application's real schema: every migration that runs on SQLite, plus stand-ins for the three MySQL-only ones. The vessel starts as a copy of the cloud. After that, ids on board are pushed 1000 ahead, so a test only passes if references really travel as UUIDs. The tests drive the real endpoints (submit, approve, reject, Part B, official remarks, delivery, receipt, password change, PMS complete and approve) and the real services (StockService, CatalogService, PmsJobScheduler).

| File | Tests | Covers |
|---|---|---|
| RequisitionSyncTest | 11 | raise to office; office approval, Part B, remarks and SRD correction back; delivery and receipt with stock moved once; concurrent reject vs forward; concurrent signatures with no conflict; duplicate req_no; batch sent twice; office-owned columns and rows; permanent procurement steps; service requisition; legacy reference not blanked |
| InventorySyncTest | 7 | consumption and correction as ledger lines; ledger line sent twice; mismatch adopt; mismatch keep; same link on both sides; same item on both sides; ledger never rewritten |
| PmsSyncTest | 5 | original dates and authors; approval back; office cannot change other job columns or create jobs; super-admin guard; nightly refresh guard |
| MasterDataSyncTest | 5 | new crew and shore users; password up; concurrent passwords; vessel cannot create users or change office data; vessel and reference lists down |

Full suite: 437 passed, 9 failed, 10 skipped (the MySQL tests, which need a MySQL server). With a MySQL server, before the last test was added: 446 passed, 9 failed, 0 skipped. The 9 failures were already failing before this checkpoint (dashboard, stock view, order detail, service requisition filters). The sync tests alone: 160 passed.

## 9. Questions for the business (provisional rules in brackets)

* **C6-1** A superintendent (or any office holder) rejecting while the ship acts on the same stage: who wins? [The ship's version, flagged for review on both servers.]
* **C6-2** A stock movement from the vessel that does not add up with the office balance: adopt the vessel's figure or keep the office's? [Adopt, flagged for review.]
* **C6-3** Which catalog duplicates may be merged automatically? [Same item_key, as the importer does. Never for items without an article number.]
* **C6-4** Super-admin editing an offline vessel's PMS in the office. [Blocked; only job approval is allowed.]
* **C6-5** Support accounts that must sign in on board. [None: every shore user's password is withheld.]
* **C6-6** item_qty corrected by the ship and by SRD at the same time. [The ship's value, flagged.] In today's code each side can only edit at its own stage, so this should not happen.
* **C6-7** Certificates and surveys (Q3). [Newest wins, flagged for review.] Certificate files do not travel yet.

## 10. Known limits

* No bootstrap tool yet (section 1). It must also withhold shore password hashes.
* Files: attachments, signatures and photos. Their paths come down, the files do not.
* An office write to a vessel-owned column (outside the screens) is ignored on board. The office copy differs until the vessel next saves that row, which then corrects it.
* A user created before their role row is routed to every vessel once, with the password withheld.
* `item_key` depends on local item-group ids. An item group created on both sides under the same name and parent is a `duplicate_key` conflict for review (item_groups are not merged automatically), and items in it wait until a person resolves it.
* Service requisition column ownership is taken from the same pattern as item requisitions, checked against `ServiceRequisitionApprovalController`. It is less exercised by tests (one round trip).
