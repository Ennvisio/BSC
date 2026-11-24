<?php

namespace Tests\Feature\Sync\Engine;

use App\Sync\Models\SyncBatch;
use Illuminate\Support\Facades\DB;

/** Changes travel between the cloud and a vessel, and only the ones that should. */
class SyncFlowTest extends SyncEngineTestCase
{
    public function test_inserts_updates_and_deletes_travel_both_ways_with_the_same_uuids(): void
    {
        $eur = $this->addCurrency('EUR', 'Euro');
        $man = $this->addManufacturer('ship', 'MAN Energy');
        $this->assertCycleOk();

        $this->assertSame('Euro', $this->row('ship', 'currencies', $eur)->name);
        $this->assertSame('MAN Energy', $this->row('cloud', 'manufacturers', $man)->name);

        $this->onCloud(fn () => DB::table('currencies')->where('sync_uuid', $eur)->update(['name' => 'Euro (EU)']));
        $this->renameManufacturer('ship', $man, 'MAN Energy Solutions', '2026-11-20 09:00:00');
        $this->assertCycleOk();

        $this->assertSame('Euro (EU)', $this->row('ship', 'currencies', $eur)->name);
        $this->assertSame('MAN Energy Solutions', $this->row('cloud', 'manufacturers', $man)->name);
        $this->assertSame($this->row('ship', 'manufacturers', $man)->sync_version, $this->row('cloud', 'manufacturers', $man)->sync_version);

        // The engine carries a delete up for the tables ADR-006 allows a
        // vessel to delete (sync.vessel_deletes: draft lines and replaced
        // child rows). Manufacturers stand in for one here; their real rule
        // is checked in SyncConflictTest.
        config(['sync.vessel_deletes' => ['manufacturers']]);
        $this->onShip(fn () => DB::table('manufacturers')->where('sync_uuid', $man)->delete());
        $this->onCloud(fn () => DB::table('currencies')->where('sync_uuid', $eur)->delete());
        $this->assertCycleOk();

        $this->assertNull($this->row('cloud', 'manufacturers', $man));
        $this->assertNull($this->row('ship', 'currencies', $eur));
    }

    public function test_nothing_is_echoed_back_and_an_idle_cycle_sends_nothing(): void
    {
        $this->addCurrency('EUR', 'Euro');
        $this->addManufacturer('ship', 'MAN Energy');
        $this->assertCycleOk();
        $this->requests = [];

        $result = $this->assertCycleOk();

        $this->assertSame(0, $result['summary']['pushed']['events']);
        $this->assertSame(0, $result['summary']['pulled']['events']);
        $this->assertSame(['/api/sync/v1/handshake', '/api/sync/v1/pull'], array_column($this->requests, 'path'), 'No push and no acknowledgement when there is nothing new.');
    }

    public function test_foreign_keys_travel_as_uuids_and_land_on_the_local_ids(): void
    {
        // Make the cloud's ids differ from the vessel's.
        $this->onCloud(fn () => DB::table('item_groups')->insert([
            ['name' => 'Cloud A', 'path' => 'a', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Cloud B', 'path' => 'b', 'created_at' => now(), 'updated_at' => now()],
        ]));

        [$parent, $child] = $this->onShip(function () {
            $p = DB::table('item_groups')->insertGetId(['name' => 'Engine spares', 'path' => '1', 'created_at' => now(), 'updated_at' => now()]);
            $c = DB::table('item_groups')->insertGetId(['name' => 'Pistons', 'path' => '1/2', 'parent_id' => $p, 'created_at' => now(), 'updated_at' => now()]);

            return [DB::table('item_groups')->where('id', $p)->value('sync_uuid'), DB::table('item_groups')->where('id', $c)->value('sync_uuid')];
        });

        $this->assertCycleOk();

        $cloudParent = $this->row('cloud', 'item_groups', $parent);
        $cloudChild = $this->row('cloud', 'item_groups', $child);
        $this->assertNotSame($this->row('ship', 'item_groups', $parent)->id, $cloudParent->id, 'ids differ between the servers');
        $this->assertSame($cloudParent->id, $cloudChild->parent_id, 'the child points at the cloud\'s own id for its parent');
        $this->assertSame($this->row('ship', 'item_groups', $parent)->id, $this->row('ship', 'item_groups', $child)->parent_id);
    }

    public function test_many_changes_to_one_row_travel_as_one_change_with_the_latest_values(): void
    {
        $uuid = $this->addManufacturer('ship', 'Draft 0');
        foreach (range(1, 5) as $i) {
            $this->renameManufacturer('ship', $uuid, "Draft {$i}", "2026-11-20 08:0{$i}:00");
        }

        $result = $this->assertCycleOk();

        $this->assertSame(1, $result['summary']['pushed']['events']);
        $this->assertSame('Draft 5', $this->row('cloud', 'manufacturers', $uuid)->name);
        $this->assertSame(6, (int) $this->row('cloud', 'manufacturers', $uuid)->sync_version, 'the vessel\'s version travels with the row');
    }

    public function test_the_vessel_works_offline_and_everything_arrives_when_the_link_returns(): void
    {
        $this->network = 'down';
        $made = [];
        foreach (range(1, 3) as $day) {
            // A day of work at sea, then a failed sync attempt.
            $made[] = $this->addManufacturer('ship', "Maker day {$day}");
            $this->renameManufacturer('ship', $made[0], "Maker day 1 (rev {$day})", "2026-11-2{$day} 10:00:00");
            $result = $this->syncCycle();
            $this->assertFalse($result['ok']);
        }

        $this->assertSame(3, $this->shipCount('manufacturers'), 'work done offline is kept on board');
        $this->assertSame(0, $this->cloudCount('manufacturers'));

        $this->network = 'up';
        $this->assertCycleOk();

        $this->assertSame(3, $this->cloudCount('manufacturers'));
        $this->assertSame('Maker day 1 (rev 3)', $this->row('cloud', 'manufacturers', $made[0])->name);
        $this->assertConverged('manufacturers', ['name', 'updated_at', 'sync_version']);
    }

    public function test_tables_without_an_enabled_rule_stay_where_they_were_changed(): void
    {
        // surveys: captured, but has no replication rule. vessel_surveys:
        // has a rule, but is switched off here.
        config(['sync.enabled_tables' => 'currencies,manufacturers']);
        $this->onShip(fn () => DB::table('surveys')->where('id', 1)->update(['name' => 'Renamed on board']));
        $this->onShip(fn () => DB::table('vessel_surveys')->insert(['vessel_id' => $this->vesselId, 'survey_id' => 1, 'society_name' => 'DNV', 'survey_date' => '2026-01-01', 'survey_exp_date' => '2027-01-01', 'created_by' => 2]));
        $this->addManufacturer('ship', 'Sent');

        $result = $this->assertCycleOk();

        $this->assertSame(1, $result['summary']['pushed']['events']);
        $this->assertSame('Annual', $this->onCloud(fn () => DB::table('surveys')->where('id', 1)->value('name')));
        $this->assertSame(0, $this->cloudCount('vessel_surveys'));
        // ...and the vessel's push position moved past them, so they do not hold up the queue.
        $this->onShip(function () {
            $outbox = DB::table('sync_outbox')->max('seq');
            $this->assertSame((int) $outbox, app(\App\Sync\SyncCursors::class)->position($this->cloudNode, 'push'));
        });
    }

    public function test_cloud_owned_reference_data_is_never_sent_up_and_the_cloud_copy_wins(): void
    {
        $eur = $this->addCurrency('EUR', 'Euro');
        $this->assertCycleOk();

        $this->onShip(fn () => \App\Sync\WrongSideEdits::outsideTheApplication(fn () => DB::table('currencies')->where('sync_uuid', $eur)->update(['name' => 'Changed on board'])));
        $this->assertCycleOk();
        $this->assertSame('Euro', $this->row('cloud', 'currencies', $eur)->name, 'not sent up');

        $this->onCloud(fn () => DB::table('currencies')->where('sync_uuid', $eur)->update(['symbol' => 'EUR']));
        $this->assertCycleOk();
        $this->assertSame('Euro', $this->row('ship', 'currencies', $eur)->name, 'the office copy replaces the local edit');
        $this->assertConverged('currencies', ['code', 'name', 'symbol']);
    }

    public function test_a_vessel_receives_its_own_records_and_fleet_wide_ones_only(): void
    {
        config(['sync.enabled_tables' => 'vessel_surveys,manufacturers']);
        $this->onCloud(function () {
            foreach ([$this->vesselId, 8] as $vessel) {
                DB::table('vessel_surveys')->insert(['vessel_id' => $vessel, 'survey_id' => 1, 'society_name' => "Class of {$vessel}", 'survey_date' => '2026-01-01', 'survey_exp_date' => '2027-01-01', 'created_by' => 1]);
            }
            DB::table('manufacturers')->insert(['name' => 'Fleet-wide', 'created_at' => now(), 'updated_at' => now()]);
        });

        $this->assertCycleOk();

        $this->assertSame(['Class of 7'], $this->onShip(fn () => DB::table('vessel_surveys')->pluck('society_name')->all()));
        $this->assertSame(1, $this->shipCount('manufacturers'));
        $this->assertSame($this->vesselId, (int) $this->onShip(fn () => DB::table('vessel_surveys')->value('vessel_id')), 'vessel id translated to the local vessel row');
    }

    public function test_large_backlogs_travel_in_several_batches_and_pages(): void
    {
        config(['sync.batch.max_events' => 10]);
        $this->onShip(fn () => DB::table('manufacturers')->insert(array_map(fn ($i) => ['name' => "Ship maker {$i}", 'created_at' => now(), 'updated_at' => now()], range(1, 35))));
        $this->onCloud(fn () => DB::table('budget_groups')->insert(array_map(fn ($i) => ['name' => "Budget {$i}", 'created_by' => 'office', 'created_at' => now(), 'updated_at' => now()], range(1, 23))));

        $result = $this->assertCycleOk();

        $this->assertSame(4, $result['summary']['pushed']['batches']);
        $this->assertSame(3, $result['summary']['pulled']['pages']);
        $this->assertSame(35, $this->cloudCount('manufacturers'));
        $this->assertSame(23, $this->shipCount('budget_groups'));
        $this->assertSame(0, $this->onShip(fn () => SyncBatch::query()->where('status', 'pending')->count()));
    }
}
