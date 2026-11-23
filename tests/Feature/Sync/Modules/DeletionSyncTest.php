<?php

namespace Tests\Feature\Sync\Modules;

use App\Sync\Engine\SyncMaintenance;
use App\Sync\Models\SyncConflict;
use Illuminate\Support\Facades\DB;

/**
 * Checkpoint 14: deletion safety at sync.
 *
 * - ADR-003 rule 5: a deleted record does not come back. Every delete
 *   leaves a lasting record (sync_deletions) with who deleted it and when.
 * - ADR-006: records are not deleted, retired or deactivated on a vessel.
 *   A delete or retirement that still comes from one is refused and
 *   flagged. The draft edits PD-72 allows still travel.
 * - PD-68 option 1 (default): the office's deletion or retirement stands
 *   against a change the vessel made offline; that change is kept for
 *   review.
 * - Who made a change travels with it (the actor).
 * - A vessel never applies a delete from the cloud of a record it owns, nor
 *   one that would cascade into rows it holds (PD-60); it is logged and
 *   flagged.
 */
class DeletionSyncTest extends BusinessSyncTestCase
{
    // ----------------------------------------------------- what still travels

    public function test_a_line_removed_from_a_draft_on_board_is_removed_on_the_cloud(): void
    {
        $orderId = $this->draftOnBoard(['ME-100' => 2, 'ME-200' => 4]);
        $this->assertCycleOk();
        $orderUuid = $this->onShip(fn () => $this->uuidFor('orders', $orderId));
        $this->assertSame(2, $this->linesOnCloud($orderUuid));

        // PD-72: removing a line from an unsubmitted draft is an edit.
        $this->onShip(function () use ($orderId) {
            $this->as('co@mvtest.test')->postJson("/requisition/{$orderId}/items/{$this->itemId('ME-200')}/remove")->assertOk();
        });
        $this->assertCycleOk();

        $this->assertSame(1, $this->linesOnCloud($orderUuid));
        $this->assertSame(0, $this->conflicts('cloud') + $this->conflicts('ship'));
    }

    public function test_every_delete_leaves_a_lasting_record_of_who_deleted_it(): void
    {
        $orderId = $this->draftOnBoard(['ME-100' => 2, 'ME-200' => 4]);
        $this->assertCycleOk();
        $lineUuid = $this->onShip(fn () => (string) DB::table('order_items')->where('order_id', $orderId)->where('item_id', $this->itemId('ME-200'))->value('sync_uuid'));

        $this->onShip(function () use ($orderId) {
            $this->as('co@mvtest.test')->postJson("/requisition/{$orderId}/items/{$this->itemId('ME-200')}/remove")->assertOk();
        });
        $co = $this->onShip(fn () => (string) DB::table('users')->where('email', 'co@mvtest.test')->value('sync_uuid'));

        $onBoard = $this->onShip(fn () => DB::table('sync_deletions')->where('row_uuid', $lineUuid)->sole());
        $this->assertSame(['order_items', $co, null], [$onBoard->table_name, $onBoard->actor_uuid, $onBoard->origin_node]);
        $this->assertNotNull($onBoard->deleted_at);

        $this->assertCycleOk();

        $onCloud = $this->onCloud(fn () => DB::table('sync_deletions')->where('row_uuid', $lineUuid)->sole());
        $this->assertSame(['order_items', $co, $this->vesselNode], [$onCloud->table_name, $onCloud->actor_uuid, $onCloud->origin_node],
            'the cloud records the same person, and that the delete came from the vessel');

        // The deletion record outlives the outbox, which is pruned.
        $this->onShip(fn () => app(SyncMaintenance::class)->prune());
        $this->onCloud(fn () => app(SyncMaintenance::class)->prune());
        $this->assertSame(1, $this->onShip(fn () => DB::table('sync_deletions')->where('row_uuid', $lineUuid)->count()));
    }

    public function test_who_made_a_change_travels_with_it(): void
    {
        $this->onShip(function () {
            $id = (int) DB::table('budget_groups')->where('name', 'Maintenance')->value('id');
            $this->as('master@mvtest.test')->postJson('/budget-group/update', ['BudgetGroup_Id' => $id, 'name' => 'Maintenance and repair', 'kind' => 'item'])->assertOk();
        });
        $master = $this->onShip(fn () => (string) DB::table('users')->where('email', 'master@mvtest.test')->value('sync_uuid'));
        $this->assertSame($master, $this->onShip(fn () => DB::table('sync_outbox')->where('table_name', 'budget_groups')->orderByDesc('seq')->value('actor_uuid')));

        $this->assertCycleOk();

        $this->assertSame($master, $this->onCloud(fn () => DB::table('sync_inbox')->where('table_name', 'budget_groups')->value('actor_uuid')));
        $this->assertSame($master, $this->onCloud(fn () => DB::table('sync_outbox')->where('table_name', 'budget_groups')->orderByDesc('seq')->value('actor_uuid')),
            'applied on the cloud as the same person');
        $this->assertNull($this->onCloud(fn () => \App\Sync\SyncContext::actor()), 'the actor is cleared after each change');
    }

    // ----------------------------------------------------------- ADR-006

    public function test_a_delete_from_the_vessel_is_refused_and_flagged(): void
    {
        $uuid = $this->onShip(fn () => (string) DB::table('vessel_items')->where('vessel_id', 7)->value('sync_uuid'));

        // Not possible through the application on board; a delete that slips
        // through some other way must still not reach the office.
        $this->onShip(fn () => DB::table('vessel_items')->where('sync_uuid', $uuid)->delete());
        $result = $this->syncCycle();
        $this->assertSame(1, $result['summary']['pushed']['rejected']);

        $this->assertNotNull($this->row('cloud', 'vessel_items', $uuid), 'kept on the cloud');
        $flag = $this->onCloud(fn () => SyncConflict::query()->sole());
        $this->assertSame(['refused', 'needs_review', 'vessel_items'], [$flag->kind, $flag->status, $flag->table_name]);
        $this->assertStringContainsString('ADR-006', $flag->note);
    }

    public function test_a_refused_change_is_flagged_once_however_often_it_is_retried(): void
    {
        $uuid = $this->onShip(fn () => (string) DB::table('vessel_items')->where('vessel_id', 7)->value('sync_uuid'));
        $this->onShip(fn () => DB::table('vessel_items')->where('sync_uuid', $uuid)->delete());
        $this->syncCycle();

        $this->onCloud(fn () => app(SyncMaintenance::class)->retry(true));
        $this->onCloud(fn () => app(SyncMaintenance::class)->retry(true));

        $this->assertSame(1, $this->onCloud(fn () => SyncConflict::query()->where('kind', 'refused')->count()));
        $this->assertNotNull($this->row('cloud', 'vessel_items', $uuid));
    }

    public function test_a_retirement_from_the_vessel_is_not_taken_but_the_rest_of_the_change_is(): void
    {
        $uuid = $this->onCloud(fn () => $this->uuidFor('items', $this->itemId('ME-100')));

        // The item "delete" sets status to false: refused on board, here it
        // arrives anyway, together with a legitimate rename.
        $this->onShip(fn () => DB::table('items')->where('sync_uuid', $uuid)->update(['status' => false, 'name' => 'FUEL INJECTOR NOZZLE', 'updated_at' => now()]));
        $this->assertCycleOk();

        $item = $this->row('cloud', 'items', $uuid);
        $this->assertSame('FUEL INJECTOR NOZZLE', $item->name, 'the rename is taken');
        $this->assertSame(1, (int) $item->status, 'still in use on the cloud');

        $flag = $this->onCloud(fn () => SyncConflict::query()->sole());
        $this->assertSame(['refused', 'needs_review'], [$flag->kind, $flag->status]);
        $this->assertStringContainsString('status', $flag->note);
    }

    public function test_a_pms_job_deactivated_on_board_stays_active_on_the_cloud(): void
    {
        $jobId = $this->definitionOnBoard();
        $this->assertCycleOk();

        $this->onShip(fn () => DB::table('pms_job_definitions')->where('id', $jobId)->update(['is_active' => false, 'updated_at' => now()]));
        $this->assertCycleOk();

        $this->assertSame(1, (int) $this->onCloud(fn () => DB::table('pms_job_definitions')->where('id', $jobId)->value('is_active')));
        $this->assertSame('refused', $this->onCloud(fn () => SyncConflict::query()->sole()->kind));
    }

    /** The component of a job definition made on board, already on the cloud. */
    private function componentOnBoth(): string
    {
        $jobId = $this->definitionOnBoard();
        $this->assertCycleOk();

        return $this->onShip(fn () => (string) DB::table('pms_job_definitions')->where('id', $jobId)->value('component_id'));
    }

    /** PD-73 (decided 2026-11-24): the "Inactive" switch is the office's; a vessel's change to it is refused and flagged. */
    public function test_a_pms_component_marked_inactive_on_board_is_refused_and_flagged(): void
    {
        $component = $this->componentOnBoth();

        $this->onShip(fn () => DB::table('pms_components')->where('id', $component)->update(['is_inactive' => true, 'remark' => 'Laid up', 'updated_at' => now()]));
        $this->assertCycleOk();

        $cloud = $this->onCloud(fn () => DB::table('pms_components')->where('id', $component)->first());
        $this->assertSame(0, (int) $cloud->is_inactive, 'not marked inactive on the cloud');
        $this->assertSame('Laid up', $cloud->remark, 'the rest of the change is taken');
        $flag = $this->onCloud(fn () => SyncConflict::query()->sole());
        $this->assertSame(['refused', 'needs_review', 'pms_components'], [$flag->kind, $flag->status, $flag->table_name]);
        $this->assertStringContainsString('PD-73', $flag->note);
        $this->assertStringContainsString('is_inactive', $flag->note);
        $this->assertSame(1, (int) $flag->remote_values['is_inactive'], "the vessel's attempt is kept in the flag");
    }

    public function test_a_pms_component_reactivated_on_board_is_refused_and_flagged(): void
    {
        $component = $this->componentOnBoth();
        // Inactive on both sides (as the office or the CFM import left it).
        foreach (['onCloud', 'onShip'] as $server) {
            $this->{$server}(fn () => \App\Sync\SyncContext::withoutCapture(fn () => DB::table('pms_components')->where('id', $component)->update(['is_inactive' => true])));
        }

        $this->onShip(fn () => DB::table('pms_components')->where('id', $component)->update(['is_inactive' => false, 'updated_at' => now()]));
        $this->assertCycleOk();

        $this->assertSame(1, (int) $this->onCloud(fn () => DB::table('pms_components')->where('id', $component)->value('is_inactive')), 'still inactive on the cloud');
        $flag = $this->onCloud(fn () => SyncConflict::query()->sole());
        $this->assertSame(['refused', 'needs_review'], [$flag->kind, $flag->status]);
        $this->assertStringContainsString('PD-73', $flag->note);
    }

    public function test_an_ordinary_component_edit_on_board_still_travels_without_a_flag(): void
    {
        $component = $this->componentOnBoth();

        $this->onShip(fn () => DB::table('pms_components')->where('id', $component)->update(['name' => 'MAIN ENGINE RENAMED', 'serial_no' => 'SN-9', 'updated_at' => now()]));
        $this->assertCycleOk();

        $cloud = $this->onCloud(fn () => DB::table('pms_components')->where('id', $component)->first());
        $this->assertSame(['MAIN ENGINE RENAMED', 'SN-9', 0], [$cloud->name, $cloud->serial_no, (int) $cloud->is_inactive]);
        $this->assertSame(0, $this->onCloud(fn () => SyncConflict::query()->count()));
    }

    public function test_a_record_created_retired_or_brought_back_into_use_is_not_refused(): void
    {
        $uuid = $this->onCloud(fn () => $this->uuidFor('items', $this->itemId('ME-200')));
        $this->onCloud(fn () => DB::table('items')->where('sync_uuid', $uuid)->update(['status' => false, 'updated_at' => now()]));
        $this->assertCycleOk();
        $this->assertSame(0, (int) $this->row('ship', 'items', $uuid)->status);

        $this->onShip(fn () => DB::table('items')->where('sync_uuid', $uuid)->update(['status' => true, 'updated_at' => now()->addMinute()]));
        $this->assertCycleOk();

        $this->assertSame(1, (int) $this->row('cloud', 'items', $uuid)->status);
        $this->assertSame(0, $this->conflicts('cloud'));
    }

    // -------------------------------------------- no resurrection, PD-68

    public function test_a_record_deleted_by_the_office_is_not_brought_back_by_an_old_copy(): void
    {
        $uuid = $this->onCloud(fn () => $this->uuidFor('budget_groups', (int) DB::table('budget_groups')->where('name', 'Maintenance')->value('id')));
        $copy = (array) $this->row('ship', 'budget_groups', $uuid);

        $this->onCloud(fn () => DB::table('budget_groups')->where('sync_uuid', $uuid)->delete());
        $this->assertCycleOk();
        $this->assertNull($this->row('ship', 'budget_groups', $uuid));

        // Months later: the outbox has long been pruned, and the vessel's
        // server is restored from an old backup that still holds the row.
        $this->onCloud(fn () => DB::table('sync_outbox')->delete());
        $this->onShip(fn () => DB::table('budget_groups')->insert(['name' => 'Maintenance (old copy)', 'updated_at' => now()] + $copy));
        $this->assertCycleOk();

        $this->assertNull($this->row('cloud', 'budget_groups', $uuid), 'not brought back');
        $flag = $this->onCloud(fn () => SyncConflict::query()->sole());
        $this->assertSame(['update_delete', 'kept_local', 'needs_review'], [$flag->kind, $flag->resolution, $flag->status]);
        $this->assertSame('Maintenance (old copy)', $flag->remote_values['name'], 'the vessel\'s copy is kept for review');
    }

    public function test_the_office_retirement_stands_against_an_offline_change_on_board(): void
    {
        $id = $this->onCloud(fn () => (int) DB::table('budget_groups')->where('name', 'Maintenance')->value('id'));
        $uuid = $this->onCloud(fn () => $this->uuidFor('budget_groups', $id));

        $this->onCloud(fn () => DB::table('budget_groups')->where('sync_uuid', $uuid)->update(['status' => false, 'updated_at' => '2026-11-20 09:00:00']));
        $this->onShip(fn () => DB::table('budget_groups')->where('sync_uuid', $uuid)->update(['name' => 'Maintenance and repair', 'updated_at' => '2026-11-20 10:00:00']));
        $this->assertCycleOk();

        foreach (['cloud', 'ship'] as $server) {
            $row = $this->row($server, 'budget_groups', $uuid);
            $this->assertSame(0, (int) $row->status, "retired on the {$server}");
            $this->assertSame('Maintenance and repair', $row->name, "the crew's newer rename stands on the {$server}");
        }
        $flags = $this->onCloud(fn () => SyncConflict::query()->get());
        $this->assertSame([['update_update', 'merged', 'needs_review']], $flags->map(fn ($c) => [$c->kind, $c->resolution, $c->status])->all());
        $this->assertSame(['Maintenance and repair', 1], [$flags[0]->remote_values['name'], (int) $flags[0]->remote_values['status']],
            "the vessel's edit, as made (still in use), is kept in the conflict");
    }

    public function test_the_office_retirement_stands_when_the_office_changed_it_last(): void
    {
        $uuid = $this->onCloud(fn () => $this->uuidFor('budget_groups', (int) DB::table('budget_groups')->where('name', 'Maintenance')->value('id')));

        $this->onShip(fn () => DB::table('budget_groups')->where('sync_uuid', $uuid)->update(['name' => 'Maintenance and repair', 'updated_at' => '2026-11-20 09:00:00']));
        $this->onCloud(fn () => DB::table('budget_groups')->where('sync_uuid', $uuid)->update(['status' => false, 'updated_at' => '2026-11-20 10:00:00']));
        $this->assertCycleOk();

        foreach (['cloud', 'ship'] as $server) {
            $row = $this->row($server, 'budget_groups', $uuid);
            $this->assertSame(0, (int) $row->status, "retired on the {$server}");
            $this->assertSame('Maintenance', $row->name, "the office's newer values stand on the {$server}");
        }
        // PD-68 A (decided): the vessel's edit is not lost without trace.
        $flag = $this->onCloud(fn () => SyncConflict::query()->sole());
        $this->assertSame(['update_update', 'needs_review'], [$flag->kind, $flag->status]);
        $this->assertSame('Maintenance and repair', $flag->remote_values['name'], "the vessel's edit is kept in the conflict");
        $this->assertSame(0, (int) $flag->local_values['status'], 'next to the retired record it met');
    }

    public function test_the_office_retirement_stands_when_the_vessel_receives_it_before_sending_its_edit(): void
    {
        $uuid = $this->onCloud(fn () => $this->uuidFor('budget_groups', (int) DB::table('budget_groups')->where('name', 'Maintenance')->value('id')));
        $this->onCloud(fn () => DB::table('budget_groups')->where('sync_uuid', $uuid)->update(['status' => false, 'updated_at' => '2026-11-20 09:00:00']));

        // The crew's edit is made on board while the office's retirement is
        // on its way down, so it is not sent yet when the retirement arrives.
        $this->network = function ($path, $n) use ($uuid) {
            if (str_ends_with($path, '/pull') && $n === 1) {
                $this->onShip(fn () => DB::table('budget_groups')->where('sync_uuid', $uuid)->update(['name' => 'Maintenance and repair', 'updated_at' => '2026-11-20 10:00:00']));
            }

            return 'up';
        };
        $this->assertCycleOk();
        $this->network = 'up';

        $onBoard = $this->row('ship', 'budget_groups', $uuid);
        $this->assertSame(0, (int) $onBoard->status, 'the office retirement takes effect on board; the edit does not undo it');
        $flag = $this->onShip(fn () => SyncConflict::query()->sole());
        $this->assertSame(['update_update', 'merged', 'needs_review'], [$flag->kind, $flag->resolution, $flag->status]);
        $this->assertSame('Maintenance and repair', $flag->local_values['name'], "the crew's edit is kept in the conflict on board");

        $this->assertCycleOk();
        foreach (['cloud', 'ship'] as $server) {
            $this->assertSame(0, (int) $this->row($server, 'budget_groups', $uuid)->status, "still retired on the {$server}");
        }
    }

    // ------------------------------------------------------------- PD-60

    /** The trash guard refuses this on the cloud; sync must refuse it too if it ever comes. */
    public function test_an_office_delete_that_would_cascade_into_the_vessels_rows_is_not_applied(): void
    {
        $uuid = $this->onCloud(fn () => $this->uuidFor('items', $this->itemId('ME-100')));
        $this->assertSame(1, $this->onShip(fn () => DB::table('vessel_items')->where('item_id', $this->itemId('ME-100'))->count()));

        // On MySQL, rows removed by ON DELETE CASCADE fire no trigger, so
        // only the item's delete travels. (SQLite does fire them, so the
        // cloud's own stock link is removed here without capture, as MySQL
        // would.)
        $this->onCloud(function () use ($uuid) {
            $item = (int) DB::table('items')->where('sync_uuid', $uuid)->value('id');
            \App\Sync\SyncContext::withoutCapture(fn () => DB::table('vessel_items')->where('item_id', $item)->delete());
            DB::table('items')->where('id', $item)->delete();
        });
        $result = $this->syncCycle();
        $this->assertSame(1, $result['summary']['pulled']['rejected']);

        $this->assertNotNull($this->row('ship', 'items', $uuid), 'the item stays on board');
        $this->assertSame(1, $this->onShip(fn () => DB::table('vessel_items')->where('item_id', $this->itemId('ME-100'))->count()), 'and so does its stock');
        $flag = $this->onShip(fn () => SyncConflict::query()->where('kind', 'refused')->sole());
        $this->assertStringContainsString('PD-60', $flag->note);
        $this->assertStringContainsString('vessel_items', $flag->note);
    }

    public function test_an_office_delete_with_nothing_on_board_below_it_is_applied(): void
    {
        $uuid = $this->onCloud(fn () => $this->uuidFor('items', $this->itemId('ME-200')));

        $this->onCloud(fn () => DB::table('items')->where('sync_uuid', $uuid)->delete());
        $this->assertCycleOk();

        $this->assertNull($this->row('ship', 'items', $uuid));
    }

    public function test_a_vessel_never_deletes_its_own_vessel_record(): void
    {
        $uuid = $this->onCloud(fn () => $this->uuidFor('vessels', 7));

        $this->onCloud(function () {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::table('vessels')->where('id', 7)->delete();
            DB::statement('PRAGMA foreign_keys = ON');
        });
        $this->syncCycle();

        $this->assertNotNull($this->row('ship', 'vessels', $uuid));
        $this->assertStringContainsString('own vessel record', $this->onShip(fn () => SyncConflict::query()->where('kind', 'refused')->sole()->note));
    }

    // ------------------------------------- the vessel's own records (PD-34)

    public function test_a_cloud_delete_of_a_vessel_owned_record_is_refused_logged_and_flagged(): void
    {
        $linkId = $this->onShip(fn () => (int) DB::table('vessel_items')->where('item_id', $this->itemId('ME-100'))->value('id'));
        $uuid = $this->onShip(fn () => $this->uuidFor('vessel_items', $linkId));

        $this->onCloud(fn () => DB::table('vessel_items')->where('sync_uuid', $uuid)->delete());
        $result = $this->syncCycle();
        $this->assertSame(1, $result['summary']['pulled']['rejected']);

        $this->assertNotNull($this->row('ship', 'vessel_items', $uuid), 'the stock link stays on board');
        $this->assertSame(0, $this->onShip(fn () => DB::table('sync_deletions')->where('table_name', 'vessel_items')->count()), 'nothing was deleted on board');

        $flag = $this->onShip(fn () => SyncConflict::query()->where('kind', 'refused')->sole());
        $this->assertSame([$uuid, 'vessel_items', 'needs_review'], [$flag->row_uuid, $flag->table_name, $flag->status]);
        $this->assertStringContainsString("vessel's own record", $flag->note);

        $log = $this->onShip(fn () => DB::table('sync_logs')->where('action', 'pull')->where('status', 'warning')->latest('id')->first());
        $this->assertNotNull($log, 'the refusal is in the sync log');
        $this->assertStringContainsString("vessel's own record", (string) $log->context);
    }

    public function test_a_cloud_delete_of_a_submitted_requisition_leaves_it_and_its_lines_on_board(): void
    {
        $orderId = $this->draftOnBoard(['ME-100' => 2, 'ME-200' => 4]);
        $this->submitOnBoard($orderId);
        $this->assertCycleOk();
        $uuid = $this->onShip(fn () => $this->uuidFor('orders', $orderId));
        $this->assertSame(2, $this->linesOnCloud($uuid));

        // The office's database removes the lines with the requisition (on
        // SQLite each line's delete is captured and travels too).
        $this->onCloud(fn () => DB::table('orders')->where('sync_uuid', $uuid)->delete());
        $result = $this->syncCycle();
        $this->assertGreaterThanOrEqual(1, $result['summary']['pulled']['rejected']);

        $this->assertNotNull($this->row('ship', 'orders', $uuid));
        $this->assertSame(2, $this->onShip(fn () => DB::table('order_items')->where('order_id', $orderId)->count()), 'every line stays');
        $refused = $this->onShip(fn () => SyncConflict::query()->where('kind', 'refused')->get());
        foreach ($refused as $flag) {
            $this->assertStringStartsWith('Delete refused:', $flag->note, 'every delete that reached the vessel is refused and flagged');
        }
        $this->assertTrue($refused->contains(fn ($f) => $f->table_name === 'orders' && str_contains($f->note, 'a delete from the cloud is not applied here')));
        $this->assertSame(0, $this->onShip(fn () => DB::table('sync_deletions')->count()), 'nothing was deleted on board');
    }

    // ------------------------------------------------------------ helpers

    private function linesOnCloud(string $orderUuid): int
    {
        return $this->onCloud(fn () => DB::table('order_items')->where('order_id', $this->idOf('orders', $orderUuid))->count());
    }

    private function conflicts(string $server): int
    {
        return $this->on($server, fn () => SyncConflict::query()->count());
    }

    /** A component and a job on it, set up on board; returns the job definition's id. */
    private function definitionOnBoard(): string
    {
        return $this->onShip(function () {
            $component = \App\PmsComponent::create([
                'vessel_id' => 7, 'location_id' => DB::table('pms_locations')->value('id'), 'name' => 'MAIN ENGINE',
                'category_id' => DB::table('pms_component_categories')->value('id'),
                'condition_id' => DB::table('pms_conditions')->where('is_default', true)->value('id'),
            ]);

            return (string) \App\PmsJobDefinition::create([
                'vessel_id' => 7, 'component_id' => $component->id, 'name' => 'Overhaul', 'is_active' => true,
                'job_type_id' => DB::table('pms_job_types')->value('id'), 'rank_id' => DB::table('ranks')->value('id'),
                'start_date' => '2026-01-01',
            ])->id;
        });
    }
}
