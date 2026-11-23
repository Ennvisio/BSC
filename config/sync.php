<?php

/*
|--------------------------------------------------------------------------
| Cloud-vessel synchronisation: identity and table registry
|--------------------------------------------------------------------------
|
| Checkpoint 2 prepares the database for sync. It does not move any data
| yet. See docs/checkpoint-2-sync-foundation.md.
|
| Every table in the application is either listed in 'tables' (captured into
| sync_outbox by database triggers) or in 'excluded' with the reason. A test
| fails if a table appears in neither list, so a new migration cannot add an
| unsynchronised table by accident.
|
*/

return [

    /*
    | This server's own node id (a UUID), as registered in sync_nodes on the
    | cloud. Every server has one: the cloud and each vessel. Generate it once
    | with `php artisan sync:register-node` on the cloud and copy it into the
    | vessel's .env. It must never change for the life of the server.
    */
    'node_id' => env('SYNC_NODE_ID'),

    /*
    | The secret this server presents to the cloud. Only a vessel needs it.
    | The cloud stores just its SHA-256 hash (sync_nodes.token_hash).
    */
    'node_token' => env('SYNC_NODE_TOKEN'),

    /*
    |----------------------------------------------------------------------
    | Synchronised tables
    |----------------------------------------------------------------------
    |
    | class  Ownership, from the audit (section 13) with the Checkpoint 1
    |        correction for crew-maintained reference data:
    |          A   cloud-owned reference data          cloud -> vessels
    |          A'  cloud-owned, vessel-scoped          cloud -> one vessel
    |          B   co-authored catalog/reference       both ways, merged
    |          C   vessel-owned operational records    vessel -> cloud
    |          D   workflow aggregates (requisitions)  both ways, by stage
    |          E   co-authored vessel records          both ways
    |          F   file metadata                       follows its parent
    |
    | uuid   The column holding the row's global id. Defaults to sync_uuid,
    |        added by migration. The three PMS tables already use UUID
    |        primary keys, so their `id` is reused.
    |
    | vessel SQL for the vessel a row belongs to, written into each outbox
    |        event so changes can be routed to the right ship. {row} becomes
    |        NEW or OLD inside the trigger. null means fleet-wide data that
    |        every vessel receives.
    |
    */
    'tables' => [

        // A: cloud-owned reference data, fleet-wide.
        'categories' => ['class' => 'A', 'vessel' => null],
        'currencies' => ['class' => 'A', 'vessel' => null],
        'ranks' => ['class' => 'A', 'vessel' => null],
        'surveys' => ['class' => 'A', 'vessel' => null],
        'certificates' => ['class' => 'A', 'vessel' => null],
        'pms_locations' => ['class' => 'A', 'vessel' => null],
        'pms_component_categories' => ['class' => 'A', 'vessel' => null],
        'pms_job_types' => ['class' => 'A', 'vessel' => null],
        'pms_conditions' => ['class' => 'A', 'vessel' => null],

        // A': cloud-owned, belongs to one vessel.
        'vessels' => ['class' => "A'", 'vessel' => '{row}.id'],
        'vessel_particulars' => ['class' => "A'", 'vessel' => '{row}.vessel_id'],
        'boilers' => ['class' => "A'", 'vessel' => '{row}.vessel_id'],
        'dimensions' => ['class' => "A'", 'vessel' => '{row}.vessel_id'],
        'engines' => ['class' => "A'", 'vessel' => '{row}.vessel_id'],
        'framework_descriptions' => ['class' => "A'", 'vessel' => '{row}.vessel_id'],
        'roles' => ['class' => "A'", 'vessel' => '{row}.vessel_id'],
        // A user's vessel is their role's vessel. Shore users have none, so
        // they are fleet-wide. The user row is written before the role row,
        // so the final routing of users is decided when changes are packaged.
        'users' => ['class' => "A'", 'vessel' => '(SELECT vessel_id FROM roles WHERE user_id = {row}.id LIMIT 1)'],

        // B: co-authored. Crew add catalog items, budget groups and
        // certificate categories on board today (Checkpoint 1 finding).
        'items' => ['class' => 'B', 'vessel' => null],
        'item_groups' => ['class' => 'B', 'vessel' => null],
        'manufacturers' => ['class' => 'B', 'vessel' => null],
        'budget_groups' => ['class' => 'B', 'vessel' => null],
        'certificate_categories' => ['class' => 'B', 'vessel' => null],

        // C: vessel-owned operational records.
        'vessel_items' => ['class' => 'C', 'vessel' => '{row}.vessel_id'],
        'stock_movements' => ['class' => 'C', 'vessel' => '{row}.vessel_id'],
        'stock_consumptions' => ['class' => 'C', 'vessel' => '{row}.vessel_id'],
        'attachment_stock_consumption' => ['class' => 'C', 'vessel' => '(SELECT vessel_id FROM stock_consumptions WHERE id = {row}.stock_consumption_id)'],
        'stock_imports' => ['class' => 'C', 'vessel' => '{row}.vessel_id'],
        'item_imports' => ['class' => 'C', 'vessel' => '{row}.vessel_id'],
        'vessel_equipment' => ['class' => 'C', 'vessel' => '{row}.vessel_id'],
        'pms_components' => ['class' => 'C', 'uuid' => 'id', 'vessel' => '{row}.vessel_id'],
        'pms_counter_readings' => ['class' => 'C', 'vessel' => '(SELECT vessel_id FROM pms_components WHERE id = {row}.component_id)'],
        'pms_job_definitions' => ['class' => 'C', 'uuid' => 'id', 'vessel' => '{row}.vessel_id'],
        'pms_job_intervals' => ['class' => 'C', 'vessel' => '(SELECT vessel_id FROM pms_job_definitions WHERE id = {row}.job_definition_id)'],
        'pms_job_checklist_items' => ['class' => 'C', 'vessel' => '(SELECT vessel_id FROM pms_job_definitions WHERE id = {row}.job_definition_id)'],
        'pms_jobs' => ['class' => 'C', 'uuid' => 'id', 'vessel' => '{row}.vessel_id'],
        'pms_required_parts' => ['class' => 'C', 'vessel' => 'COALESCE((SELECT vessel_id FROM pms_job_definitions WHERE id = {row}.job_definition_id), (SELECT vessel_id FROM pms_jobs WHERE id = {row}.job_id))'],
        'pms_used_parts' => ['class' => 'C', 'vessel' => '(SELECT vessel_id FROM pms_jobs WHERE id = {row}.job_id)'],

        // D: requisition workflow aggregates.
        'orders' => ['class' => 'D', 'vessel' => '{row}.vessel_id'],
        'order_items' => ['class' => 'D', 'vessel' => '(SELECT vessel_id FROM orders WHERE id = {row}.order_id)'],
        'order_approvals' => ['class' => 'D', 'vessel' => '(SELECT vessel_id FROM orders WHERE id = {row}.order_id)'],
        'order_form_parts' => ['class' => 'D', 'vessel' => '(SELECT vessel_id FROM orders WHERE id = {row}.order_id)'],
        'order_form_part_edits' => ['class' => 'D', 'vessel' => '(SELECT o.vessel_id FROM orders o JOIN order_form_parts p ON p.order_id = o.id WHERE p.id = {row}.order_form_part_id)'],
        'order_item_quantity_changes' => ['class' => 'D', 'vessel' => '(SELECT o.vessel_id FROM orders o JOIN order_items i ON i.order_id = o.id WHERE i.id = {row}.order_item_id)'],
        'order_procurement_steps' => ['class' => 'D', 'vessel' => '(SELECT vessel_id FROM orders WHERE id = {row}.order_id)'],
        'order_invoices' => ['class' => 'D', 'vessel' => '(SELECT vessel_id FROM orders WHERE id = {row}.order_id)'],
        'attachment_order_item' => ['class' => 'D', 'vessel' => '(SELECT o.vessel_id FROM orders o JOIN order_items i ON i.order_id = o.id WHERE i.id = {row}.order_item_id)'],
        'attachment_order_step' => ['class' => 'D', 'vessel' => '(SELECT o.vessel_id FROM orders o JOIN order_procurement_steps s ON s.order_id = o.id WHERE s.id = {row}.order_procurement_step_id)'],
        'service_requisitions' => ['class' => 'D', 'vessel' => '{row}.vessel_id'],
        'service_requisition_items' => ['class' => 'D', 'vessel' => '(SELECT vessel_id FROM service_requisitions WHERE id = {row}.service_requisition_id)'],
        'service_requisition_approvals' => ['class' => 'D', 'vessel' => '(SELECT vessel_id FROM service_requisitions WHERE id = {row}.service_requisition_id)'],
        'service_requisition_procurement_steps' => ['class' => 'D', 'vessel' => '(SELECT vessel_id FROM service_requisitions WHERE id = {row}.service_requisition_id)'],
        'service_requisition_invoices' => ['class' => 'D', 'vessel' => '(SELECT vessel_id FROM service_requisitions WHERE id = {row}.service_requisition_id)'],
        'attachment_service_req_step' => ['class' => 'D', 'vessel' => '(SELECT r.vessel_id FROM service_requisitions r JOIN service_requisition_procurement_steps s ON s.service_requisition_id = r.id WHERE s.id = {row}.step_id)'],

        // E: vessel records both sides edit (pending Q3).
        'vessel_certificates' => ['class' => 'E', 'vessel' => '{row}.vessel_id'],
        'vessel_surveys' => ['class' => 'E', 'vessel' => '{row}.vessel_id'],
        // PD-62: every copy of a certificate, kept as history.
        'vessel_certificate_copies' => ['class' => 'E', 'vessel' => '(SELECT vessel_id FROM vessel_certificates WHERE id = {row}.vessel_certificate_id)'],

        // F: file metadata. Attachments belong to the uploader's vessel.
        'attachments' => ['class' => 'F', 'vessel' => '(SELECT vessel_id FROM roles WHERE user_id = {row}.uploaded_by LIMIT 1)'],
    ],

    /*
    | Tables that are never synchronised, with the reason.
    */
    'excluded' => [
        'migrations' => 'Each server runs its own migrations.',
        'password_resets' => 'Short-lived reset tokens. Reset is disabled on vessels.',
        'ports' => 'UN/LOCODE reference list (100,000+ rows). Each server imports it with `php artisan ports:import`.',
        'sync_nodes' => 'Sync bookkeeping.',
        'sync_outbox' => 'Sync bookkeeping.',
        'sync_inbox' => 'Sync bookkeeping.',
        'sync_cursors' => 'Sync bookkeeping.',
        'sync_logs' => 'Sync bookkeeping.',
        'sync_batches' => 'Sync bookkeeping.',
        'sync_conflicts' => 'Sync bookkeeping. Each server keeps the conflicts it found.',
        'sync_state' => 'Sync bookkeeping: local worker state.',
        'sync_row_bases' => 'Sync bookkeeping: values last agreed with each peer, for merging.',
        'sync_aliases' => 'Sync bookkeeping: second UUIDs of records created on both sides and merged.',
        'sync_deletions' => 'Sync bookkeeping: the lasting record of every delete on this server (Checkpoint 14). Each server keeps its own; it is what stops a deleted record coming back.',
        'sync_files' => 'Sync bookkeeping: which files this server has, wants or could not get.',
        'sync_file_grants' => 'Sync bookkeeping (cloud): the files each vessel may download.',
        'jobs' => 'Local queue of the server it runs on.',
        'job_batches' => 'Local queue of the server it runs on.',
        'failed_jobs' => 'Local queue of the server it runs on.',
    ],

    /*
    | Rows updated per statement when filling sync_uuid on existing data, so
    | a large table is never locked for long.
    */
    'backfill_chunk' => 5000,

    /*
    |----------------------------------------------------------------------
    | Sync engine (Checkpoint 5)
    |----------------------------------------------------------------------
    |
    | The protocol is described in docs/checkpoint-5-sync-engine.md.
    |
    */

    // Vessel only: the cloud's address, e.g. https://navisuite.example.com
    'cloud_url' => env('SYNC_CLOUD_URL'),

    /*
    | Replication rules. Capture (above) records every change; a table only
    | travels between servers when it has a rule here AND is listed in
    | 'enabled_tables'. Business modules are added one at a time, each with
    | rules agreed for it, never all at once.
    |
    | direction   down = cloud to vessels, up = vessel to cloud, both.
    | conflict    Who wins when both sides changed the same row before
    |             seeing each other's change: cloud, vessel, or latest
    |             (the newer updated_at; a tie goes to the cloud). One-way
    |             tables always let their owner win.
    | references  Foreign-key columns, sent as the referenced row's UUID and
    |             turned back into the local id on arrival.
    | natural_key Columns that identify a row besides its UUID. A row with
    |             the same natural key but another UUID is a conflict for
    |             review, never silently merged.
    | local_columns  Columns never sent and never overwritten.
    */
    'replication' => [
        // Reference data the office maintains (class A).
        'currencies' => ['direction' => 'down', 'natural_key' => ['code']],
        'ranks' => ['direction' => 'down', 'natural_key' => ['code']],
        'categories' => ['direction' => 'down', 'natural_key' => ['symbol']],

        // Defined but not enabled by default: co-authored lists (class B).
        'manufacturers' => ['direction' => 'both', 'conflict' => 'latest', 'natural_key' => ['name']],
        'budget_groups' => ['direction' => 'both', 'conflict' => 'latest', 'natural_key' => ['name']],
        'certificate_categories' => ['direction' => 'both', 'conflict' => 'latest', 'natural_key' => ['name'],
            'references' => ['created_by' => 'users', 'updated_by' => 'users']],
        'item_groups' => ['direction' => 'both', 'conflict' => 'latest', 'natural_key' => ['category_id', 'parent_id', 'name'],
            'references' => ['parent_id' => 'item_groups', 'category_id' => 'categories']],

        // Defined but not enabled by default: a vessel-scoped record both
        // sides edit (class E, question Q3: last writer wins, with review).
        'vessel_surveys' => ['direction' => 'both', 'conflict' => 'latest',
            'references' => ['vessel_id' => 'vessels', 'survey_id' => 'surveys', 'created_by' => 'users', 'updated_by' => 'users']],

        /*
        |------------------------------------------------------------------
        | Business modules (Checkpoint 6), see docs/checkpoint-6-business-sync.md
        |------------------------------------------------------------------
        |
        | Further keys, for records whose parts belong to different sides:
        |
        | columns     Column owners: 'cloud' => [...], 'vessel' => [...],
        |             'shared' => [...], 'other' => owner of the rest. A
        |             column is only ever taken from its owner; shared
        |             columns are merged one by one, and only a column both
        |             sides changed is a conflict (decided by 'conflict').
        | together    Shared columns decided as one (a status and the
        |             rejection details that go with it).
        | review      Conflicts on these shared columns (or, true, on any
        |             column) are decided provisionally and flagged for a
        |             person (`php artisan sync:conflicts`).
        | guarded     [from, to] values in which the side that does not own
        |             a column may still change it.
        | creates / deletes  The only side that may create / delete rows.
        | row_owner   The side owning each row, by a column's value.
        | append_only Permanent records: once written never changed or
        |             removed by sync (ledger lines, audit entries).
        | on_duplicate  review (default) or merge: the same record created
        |             on both sides (same natural key) becomes one.
        | hooks       Module logic run while a change is applied.
        | redact      Values the cloud sends instead, for rows that do not
        |             belong to the receiving vessel.
        */

        // ---- Master data -------------------------------------------------

        'vessels' => ['direction' => 'down'],
        // 'departures': a role moved to another vessel is also sent to the
        // vessel it left, so that vessel stops letting the person sign in.
        'roles' => ['direction' => 'down', 'departures' => true, 'references' => ['user_id' => 'users', 'vessel_id' => 'vessels']],
        // Users are created, changed and removed in the office. A crew
        // member may change their own password on board (Q4): the newest
        // password wins. Shore users come down without their password hash
        // (roadmap 5.7), so they cannot sign in on board.
        'users' => ['direction' => 'both', 'conflict' => 'latest', 'creates' => 'cloud', 'deletes' => 'cloud', 'departures' => true,
            'columns' => ['shared' => ['password'], 'other' => 'cloud'],
            'local_columns' => ['remember_token'],
            'redact' => ['password' => '$2y$10$uh8pLmsuk2DIHER9UyVeZOAW/Yp1tktW3S8bLchflgJMT9jPiE85a']],
        'pms_locations' => ['direction' => 'down'],
        'pms_component_categories' => ['direction' => 'down'],
        'pms_job_types' => ['direction' => 'down'],
        'pms_conditions' => ['direction' => 'down'],
        // The catalog. An item's identity is its item_key (article number,
        // group and name), worked out on each server; the same item added on
        // both sides is merged, as a catalog import would.
        'items' => ['direction' => 'both', 'conflict' => 'latest', 'natural_key' => ['item_key'], 'on_duplicate' => 'merge',
            'local_columns' => ['item_key'],
            'hooks' => [App\Sync\Engine\Hooks\ItemKeyHook::class],
            'references' => ['category_id' => 'categories', 'item_group_id' => 'item_groups']],

        // ---- Inventory (vessel-owned, class C) ----------------------------

        // The catalog link: created by the office (linking items to a
        // vessel) or on board. The stock figures are the vessel's; the
        // balance itself never travels: the cloud derives it from the
        // movements (StockLedgerHook).
        'vessel_items' => ['direction' => 'both', 'conflict' => 'vessel', 'natural_key' => ['vessel_id', 'item_id'], 'on_duplicate' => 'merge',
            'columns' => ['vessel' => ['opening_stock', 'min_qty', 'last_supply_qty', 'last_supply_date', 'stock_updated_at', 'stock_updated_by'], 'other' => 'shared'],
            'local_columns' => ['stock_qty'],
            'references' => ['vessel_id' => 'vessels', 'item_id' => 'items', 'stock_updated_by' => 'users']],
        'stock_movements' => ['direction' => 'up', 'append_only' => true,
            'hooks' => [App\Sync\Engine\Hooks\StockLedgerHook::class],
            'references' => ['vessel_id' => 'vessels', 'item_id' => 'items', 'order_id' => 'orders', 'order_item_id' => 'order_items',
                'stock_consumption_id' => 'stock_consumptions', 'stock_import_id' => 'stock_imports', 'user_id' => 'users']],
        'stock_consumptions' => ['direction' => 'up', 'append_only' => true,
            'references' => ['vessel_id' => 'vessels', 'item_id' => 'items', 'order_id' => 'orders', 'recorded_by' => 'users']],
        'stock_imports' => ['direction' => 'up',
            'references' => ['vessel_id' => 'vessels', 'category_id' => 'categories', 'uploaded_by' => 'users']],

        // ---- PMS (vessel-owned; the office only approves jobs, Q2) --------

        'pms_components' => ['direction' => 'up',
            'references' => ['vessel_id' => 'vessels', 'location_id' => 'pms_locations', 'parent_id' => 'pms_components',
                'category_id' => 'pms_component_categories', 'manufacturer_id' => 'manufacturers', 'condition_id' => 'pms_conditions',
                'counter_parent_id' => 'pms_components']],
        'pms_counter_readings' => ['direction' => 'up', 'references' => ['component_id' => 'pms_components', 'entered_by' => 'users']],
        'pms_job_definitions' => ['direction' => 'up',
            'references' => ['vessel_id' => 'vessels', 'component_id' => 'pms_components', 'job_type_id' => 'pms_job_types', 'rank_id' => 'ranks']],
        'pms_job_intervals' => ['direction' => 'up', 'references' => ['job_definition_id' => 'pms_job_definitions']],
        'pms_job_checklist_items' => ['direction' => 'up', 'references' => ['job_definition_id' => 'pms_job_definitions']],
        // Jobs are planned and completed on board. The office's only change
        // is approving a completed job: approved_by/at, and the status from
        // WaitingApproval to Completed.
        'pms_jobs' => ['direction' => 'both', 'conflict' => 'vessel', 'creates' => 'vessel', 'deletes' => 'vessel',
            'columns' => ['cloud' => ['approved_by', 'approved_at'], 'other' => 'vessel'],
            'guarded' => ['status' => [['WaitingApproval', 'Completed']]],
            'references' => ['vessel_id' => 'vessels', 'component_id' => 'pms_components', 'job_definition_id' => 'pms_job_definitions',
                'done_by_rank_id' => 'ranks', 'condition_after_id' => 'pms_conditions', 'completed_by' => 'users', 'approved_by' => 'users']],
        'pms_required_parts' => ['direction' => 'up',
            'references' => ['job_definition_id' => 'pms_job_definitions', 'job_id' => 'pms_jobs', 'item_id' => 'items',
                'manufacturer_id' => 'manufacturers', 'order_id' => 'orders']],
        'pms_used_parts' => ['direction' => 'up', 'append_only' => true,
            'references' => ['job_id' => 'pms_jobs', 'item_id' => 'items', 'stock_consumption_id' => 'stock_consumptions']],

        // ---- Requisitions (class D: the record passes between ship and office)

        // Raised on board; each stage writes its own columns. The status
        // and rejection details are the "baton": normally only the holder
        // of the requisition changes them. When both sides did (a
        // superintendent rejecting while the master forwards), the vessel's
        // version stands for now, as the ship held the requisition at every
        // stage where that can happen (roadmap 5.5 "holder wins"), and the
        // conflict waits for a person. See docs, question C6-1.
        'orders' => ['direction' => 'both', 'conflict' => 'vessel', 'creates' => 'vessel', 'deletes' => 'vessel',
            'columns' => [
                'cloud' => ['deliver_date', 'status_from_am', 'official_remarks', 'official_remarks_by', 'official_remarks_at'],
                'shared' => ['status', 'procurement_stage', 'rejected_by', 'rejected_by_role', 'rejected_at_stage', 'rejected_at', 'rejection_reason', 'updated_by'],
                'other' => 'vessel',
            ],
            'together' => [['status', 'procurement_stage', 'rejected_by', 'rejected_by_role', 'rejected_at_stage', 'rejected_at', 'rejection_reason']],
            'review' => ['status', 'procurement_stage', 'rejected_by', 'rejected_by_role', 'rejected_at_stage', 'rejected_at', 'rejection_reason'],
            'natural_key' => ['req_no'],
            'references' => ['vessel_id' => 'vessels', 'category_id' => 'categories', 'created_by' => 'users', 'budget_group_id' => 'budget_groups',
                'rejected_by' => 'users', 'official_remarks_by' => 'users']],
        // Lines: requested on board (and corrected by the master or chief
        // engineer, or by SRD in the office: item_qty is shared), delivered
        // and invoiced by the office, received on board.
        'order_items' => ['direction' => 'both', 'conflict' => 'vessel', 'creates' => 'vessel', 'deletes' => 'vessel',
            'columns' => [
                'cloud' => ['del_item_qty', 'invoice_qty', 'unit_price', 'line_total'],
                'shared' => ['item_qty', 'status'],
                'other' => 'vessel',
            ],
            'review' => ['item_qty'],
            'references' => ['order_id' => 'orders', 'item_id' => 'items']],
        // One signature column per role: the ship's four, the office's rest.
        'order_approvals' => ['direction' => 'both', 'conflict' => 'vessel', 'creates' => 'vessel', 'deletes' => 'vessel',
            'columns' => ['vessel' => ['order_id', 'cheif_ofcr_app', 'second_eng_app', 'master_app', 'chief_eng_app', 'created_by_role'], 'other' => 'cloud'],
            'references' => ['order_id' => 'orders', 'cheif_ofcr_app' => 'users', 'master_app' => 'users', 'chief_eng_app' => 'users',
                'second_eng_app' => 'users', 'ast_m_app' => 'users', 'agm_app' => 'users', 'gm_app' => 'users', 'dgm_app_ssm' => 'users',
                'agm_app_ssm' => 'users', 'am_app_ssm' => 'users', 'forwarded_to_agm_by_gm_srd' => 'users', 'forwarded_to_am_by_agm_srd' => 'users',
                'forwarded_to_dgm_srd' => 'users', 'dgm_srd_app' => 'users', 'forwarded_to_superintendent_srd' => 'users',
                'superintendent_srd_app' => 'users', 'superintendent_ssm_app' => 'users', 'tech_superintendent_app' => 'users',
                'marine_superintendent_app' => 'users', 'assigned_to_ssm' => 'users', 'assigned_to_srd' => 'users']],
        // Part A is the ship's declaration; Parts B and C the office's.
        'order_form_parts' => ['direction' => 'both', 'conflict' => 'vessel',
            'row_owner' => ['column' => 'part', 'values' => ['A' => 'vessel'], 'other' => 'cloud'],
            'references' => ['order_id' => 'orders', 'filled_by' => 'users', 'edited_by' => 'users']],
        'order_form_part_edits' => ['direction' => 'up', 'append_only' => true,
            'references' => ['order_form_part_id' => 'order_form_parts', 'edited_by' => 'users']],
        'order_item_quantity_changes' => ['direction' => 'down', 'append_only' => true,
            'references' => ['order_item_id' => 'order_items', 'changed_by' => 'users']],
        // One row per completed stage. Receipt & Verification is done on
        // board, every other stage in the office.
        'order_procurement_steps' => ['direction' => 'both', 'conflict' => 'cloud', 'append_only' => true,
            'row_owner' => ['column' => 'step', 'values' => ['receipt_verification' => 'vessel'], 'other' => 'cloud'],
            'references' => ['order_id' => 'orders', 'completed_by' => 'users']],
        'order_invoices' => ['direction' => 'down', 'references' => ['order_id' => 'orders', 'verified_by' => 'users']],

        // Service requisitions: the same shape (SRD runs procurement).
        'service_requisitions' => ['direction' => 'both', 'conflict' => 'vessel', 'creates' => 'vessel', 'deletes' => 'vessel',
            'columns' => [
                'shared' => ['status', 'procurement_stage', 'rejected_by', 'rejected_by_role', 'rejected_at_stage', 'rejected_at', 'rejection_reason'],
                'other' => 'vessel',
            ],
            'together' => [['status', 'procurement_stage', 'rejected_by', 'rejected_by_role', 'rejected_at_stage', 'rejected_at', 'rejection_reason']],
            'review' => ['status', 'procurement_stage', 'rejected_by', 'rejected_by_role', 'rejected_at_stage', 'rejected_at', 'rejection_reason'],
            'natural_key' => ['req_no'],
            'references' => ['vessel_id' => 'vessels', 'created_by' => 'users', 'rejected_by' => 'users', 'budget_group_id' => 'budget_groups']],
        'service_requisition_items' => ['direction' => 'both', 'conflict' => 'vessel', 'creates' => 'vessel', 'deletes' => 'vessel',
            'columns' => ['cloud' => ['invoice_qty', 'unit_price', 'line_total'], 'other' => 'vessel'],
            'references' => ['service_requisition_id' => 'service_requisitions', 'vessel_equipment_id' => 'vessel_equipment',
                'vessel_certificate_id' => 'vessel_certificates']],
        'service_requisition_approvals' => ['direction' => 'both', 'conflict' => 'vessel', 'creates' => 'vessel', 'deletes' => 'vessel',
            'columns' => ['vessel' => ['service_requisition_id', 'cheif_ofcr_app', 'second_eng_app', 'master_app', 'chief_eng_app'], 'other' => 'cloud'],
            'references' => ['service_requisition_id' => 'service_requisitions', 'cheif_ofcr_app' => 'users', 'second_eng_app' => 'users',
                'master_app' => 'users', 'chief_eng_app' => 'users', 'gm_app' => 'users', 'assigned_to_srd' => 'users',
                'forwarded_to_dgm_srd' => 'users', 'dgm_srd_app' => 'users', 'forwarded_to_agm_by_gm_srd' => 'users', 'agm_app' => 'users',
                'forwarded_to_am_by_agm_srd' => 'users', 'ast_m_app' => 'users', 'forwarded_to_superintendent_srd' => 'users',
                'superintendent_srd_app' => 'users']],
        'service_requisition_procurement_steps' => ['direction' => 'both', 'conflict' => 'cloud', 'append_only' => true,
            'row_owner' => ['column' => 'step', 'values' => ['receipt_verification' => 'vessel'], 'other' => 'cloud'],
            'references' => ['service_requisition_id' => 'service_requisitions', 'completed_by' => 'users']],
        'service_requisition_invoices' => ['direction' => 'down', 'references' => ['service_requisition_id' => 'service_requisitions', 'verified_by' => 'users']],
        // What a service requisition is for: equipment the crew keep, and
        // the vessel's certificates (Q3: last writer wins, with review).
        'vessel_equipment' => ['direction' => 'up', 'references' => ['vessel_id' => 'vessels', 'created_by' => 'users', 'updated_by' => 'users']],
        'vessel_certificates' => ['direction' => 'both', 'conflict' => 'latest', 'review' => true,
            'references' => ['vessel_id' => 'vessels', 'certificate_id' => 'certificates', 'category_id' => 'certificate_categories']],
        // Every copy of a certificate (PD-62), from either side. Each is an
        // immutable historical record: written once, never changed or
        // removed by sync, with who uploaded it, when, and the file's
        // checksum, which the file channel checks wherever it arrives. Both
        // sides uploading while apart gives two records, both kept (the
        // certificate's own cert_copy is the one with review). The same
        // file listed on both sides (a copy from before the history began)
        // becomes one record.
        'vessel_certificate_copies' => ['direction' => 'both', 'conflict' => 'cloud', 'append_only' => true,
            'natural_key' => ['vessel_certificate_id', 'path'], 'on_duplicate' => 'merge',
            'references' => ['vessel_certificate_id' => 'vessel_certificates', 'uploaded_by' => 'users']],
        'certificates' => ['direction' => 'down'],
        'surveys' => ['direction' => 'down'],

        // ---- Attachments (Checkpoint 7) ----------------------------------

        // The file records. A crew member's files belong to their vessel
        // and go up; the office's files reach a vessel only together with a
        // link to one of that vessel's records (the "carries" below), never
        // on their own (strict_scope). The files themselves travel
        // separately (App\Sync\Files, config 'files' below).
        'attachments' => ['direction' => 'both', 'conflict' => 'latest', 'strict_scope' => true,
            'references' => ['uploaded_by' => 'users', 'hidden_by' => 'users']],
        // Links made on board: to a draft line, to a consumption record.
        'attachment_order_item' => ['direction' => 'up', 'references' => ['order_item_id' => 'order_items', 'attachment_id' => 'attachments']],
        'attachment_stock_consumption' => ['direction' => 'up', 'references' => ['stock_consumption_id' => 'stock_consumptions', 'attachment_id' => 'attachments']],
        // Links made by whoever completes a procurement stage: the office
        // for most stages, the vessel for Receipt & Verification.
        'attachment_order_step' => ['direction' => 'both', 'conflict' => 'latest', 'carries' => ['attachment_id' => 'attachments'],
            'references' => ['order_procurement_step_id' => 'order_procurement_steps', 'attachment_id' => 'attachments']],
        'attachment_service_req_step' => ['direction' => 'both', 'conflict' => 'latest', 'carries' => ['attachment_id' => 'attachments'],
            'references' => ['step_id' => 'service_requisition_procurement_steps', 'attachment_id' => 'attachments']],
    ],

    // Values sync bookkeeping never keeps (conflicts, applied inbox
    // entries): see App\Sync\SensitiveValues.
    'sensitive_columns' => [
        'users' => ['password', 'remember_token'],
    ],

    /*
    | ADR-006 (2026-11-23): vessel users never delete, retire or deactivate
    | records. At sync (Checkpoint 14) a delete from a vessel is accepted only
    | for these tables, the draft edits PD-72 allows and the child rows a
    | save replaces:
    |   order_items              a line removed from an unsubmitted draft
    |                            (Save & Next rewrites the draft's lines)
    |   attachment_order_item    an attachment unlinked from a draft line;
    |                            the file stays in the library
    |   pms_required_parts       a PMS part taken off a draft (its template
    |                            is replaced when a job is saved)
    |   pms_job_intervals,       replaced when a job definition is saved
    |   pms_job_checklist_items
    | Any other delete from a vessel is refused and flagged for review.
    */
    'vessel_deletes' => ['order_items', 'attachment_order_item', 'pms_required_parts', 'pms_job_intervals', 'pms_job_checklist_items'],

    /*
    | The columns that retire a record without deleting it (the application's
    | "delete" sets them), with their retired value; 'not_null' means any
    | value. A vessel's change that retires a record is refused and flagged
    | (ADR-006); a record the office retires stays retired when it meets a
    | vessel's change made offline (PD-68 option 1). A record created
    | already retired, or brought back into use, is not affected.
    */
    'retire_columns' => [
        'budget_groups' => ['status' => false],
        'certificate_categories' => ['status' => false],
        'items' => ['status' => false],
        'vessel_certificates' => ['status' => false],
        'vessel_surveys' => ['status' => false],
        'vessel_equipment' => ['status' => false],
        // The component's delete action sets deleted_at. Its "Inactive"
        // switch is in office_only_columns below (PD-73).
        'pms_components' => ['deleted_at' => 'not_null'],
        'pms_job_definitions' => ['is_active' => false, 'deleted_at' => 'not_null'],
        'attachments' => ['hidden_at' => 'not_null'],   // PD-61: a delete hides the file
    ],

    /*
    | Columns only the office changes (PD-73, decided 2026-11-24). A
    | vessel's change that sets one of them to a value other than the one
    | the receiver holds is not taken, in either direction (marking
    | inactive or reactivating); the rest of that change is, and the
    | refusal is flagged for review. A new row from a vessel is not
    | checked: the screens refuse an inactive new component, and the CFM
    | import command (run by a technician, not a vessel user) keeps its
    | behaviour.
    */
    'office_only_columns' => [
        'pms_components' => ['is_inactive'],
    ],

    // Tables a reference may point at without being synchronised (see
    // ReplicationRules::warnings()). Empty: every referenced table travels.
    'reference_without_sync' => [],

    /*
    | Named groups for SYNC_TABLES: "@requisitions" stands for its tables.
    | Enable a module with everything it refers to, on the cloud and every
    | vessel at once, e.g.
    |   SYNC_TABLES=currencies,ranks,categories,@master,@inventory,@pms,@requisitions
    */
    'modules' => [
        'master' => ['vessels', 'users', 'roles', 'manufacturers', 'budget_groups', 'item_groups', 'items',
            'certificate_categories', 'certificates', 'surveys', 'pms_locations', 'pms_component_categories', 'pms_job_types', 'pms_conditions'],
        'inventory' => ['vessel_items', 'stock_imports', 'stock_consumptions', 'stock_movements'],
        'pms' => ['pms_components', 'pms_counter_readings', 'pms_job_definitions', 'pms_job_intervals', 'pms_job_checklist_items',
            'pms_jobs', 'pms_required_parts', 'pms_used_parts'],
        'requisitions' => ['orders', 'order_items', 'order_approvals', 'order_form_parts', 'order_form_part_edits',
            'order_item_quantity_changes', 'order_procurement_steps', 'order_invoices',
            'vessel_equipment', 'vessel_certificates', 'vessel_certificate_copies', 'service_requisitions', 'service_requisition_items',
            'service_requisition_approvals', 'service_requisition_procurement_steps', 'service_requisition_invoices'],
        // Needs @requisitions and @inventory (the links point at their records).
        'attachments' => ['attachments', 'attachment_order_item', 'attachment_stock_consumption', 'attachment_order_step',
            'attachment_service_req_step'],
    ],

    /*
    |----------------------------------------------------------------------
    | Files (Checkpoint 7)
    |----------------------------------------------------------------------
    |
    | Columns that hold the path of a file. When such a record arrives, the
    | receiving server fetches the file through its own channel, after the
    | records of the cycle are in: never inside the records, so a slow
    | upload holds nothing else up. Each file is checked against its
    | SHA-256 before it is put in place; a transfer that is cut off carries
    | on where it stopped. See docs/checkpoint-7-files-security-backup.md.
    |
    | disk    a disk in config/filesystems.php
    | prefix  every path must start with it; anything else, or a path with
    |         "..", odd characters or an extension a stored file never has,
    |         is refused (from a vessel) or never fetched (on a vessel)
    | sha256  the column with the file's checksum, where the table has one
    */
    'files' => [
        'enabled' => env('SYNC_FILES', true),
        'columns' => [
            'attachments' => ['path' => ['disk' => 'local', 'prefix' => 'attachments/', 'sha256' => 'sha256', 'size' => 'file_size']],
            'users' => [
                'photo' => ['disk' => 'public_images', 'prefix' => 'images/userphoto/'],
                'sign' => ['disk' => 'public_images', 'prefix' => 'images/signature/'],
            ],
            'vessel_certificates' => ['cert_copy' => ['disk' => 'public_images', 'prefix' => 'images/cert_copy/']],
            'vessel_certificate_copies' => ['path' => ['disk' => 'public_images', 'prefix' => 'images/cert_copy/', 'sha256' => 'sha256', 'size' => 'file_size']],
        ],
        'chunk_bytes' => 512 * 1024,                     // per request, both ways
        'max_file_bytes' => 64 * 1024 * 1024,
        'max_bytes_per_cycle' => (int) env('SYNC_FILES_MAX_BYTES', 50 * 1024 * 1024),
        'max_attempts' => 20,                            // then the file is marked failed for an operator
        'tmp_dir' => 'sync-files-tmp',                   // under the local disk; partial transfers
    ],

    // Inventory: what the cloud's stock balance becomes when a movement from
    // the vessel does not add up (see App\Sync\Engine\Hooks\StockLedgerHook):
    // adopt = the vessel's figure, keep = the office's. Flagged either way.
    'inventory' => [
        'on_mismatch' => env('SYNC_STOCK_ON_MISMATCH', 'adopt'),
    ],

    // Comma-separated. Must be the same on the cloud and the vessels.
    'enabled_tables' => env('SYNC_TABLES', 'currencies,ranks,categories'),

    'worker' => [
        'interval' => (int) env('SYNC_INTERVAL', 300),   // seconds between cycles when all is well
        'backoff_base' => 30,                             // first retry after a failure
        'backoff_max' => 1800,                            // never wait longer than this
        'jitter' => 0.2,                                  // +/- 20 %, so vessels do not retry in step
        'max_pages' => 50,                                // per direction per cycle
    ],

    'batch' => [
        'max_events' => 500,
        'max_bytes' => 2 * 1024 * 1024,                   // uncompressed JSON
    ],

    'http' => [
        'connect_timeout' => 20,
        'timeout' => 120,
        // CA bundle for the cloud's HTTPS certificate. Empty: PHP's default.
        'ca_bundle' => env('SYNC_CA_BUNDLE'),
        'max_request_bytes' => 32 * 1024 * 1024,          // after decompression
    ],

    'inbox' => [
        // A change waiting for a record it refers to is retried this often
        // before it is marked failed for an operator.
        'max_attempts' => 50,
    ],

    'retention' => [
        'outbox_days' => 14,
        'inbox_days' => 30,
        'batches_days' => 30,
        'logs_days' => 90,
    ],

    'clock_skew_warning' => 120,                          // seconds

    // Sync failure monitoring (Checkpoint 7, App\Sync\SyncHealth).
    'alerts' => [
        'failures_in_a_row' => 3,          // failed cycles, other than "offline", before an error
        'offline_warning_hours' => (int) env('SYNC_OFFLINE_WARNING_HOURS', 72),
        'deferred_warning_hours' => 24,    // a received change waiting this long for its parent
    ],

];
