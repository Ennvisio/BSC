<?php

namespace Tests\Feature\Sync\Engine;

use App\Sync\Engine\ReplicationRules;
use App\Sync\Models\SyncConflict;
use Illuminate\Support\Facades\DB;

/**
 * Both sides changing the same row before seeing each other's change. Every
 * case must end with both servers holding the same row, and a record of
 * what happened.
 */
class SyncConflictTest extends SyncEngineTestCase
{
    private string $uuid;

    protected function setUp(): void
    {
        parent::setUp();
        // A co-authored list entry ("latest" rule), already on both sides.
        $this->uuid = $this->addManufacturer('cloud', 'Original', '2026-11-20 08:00:00');
        $this->assertCycleOk();
        $this->assertNotNull($this->row('ship', 'manufacturers', $this->uuid));
        $this->requests = [];
    }

    public function test_the_newer_change_wins_when_the_office_changed_it_last(): void
    {
        $this->renameManufacturer('ship', $this->uuid, 'Ship version', '2026-11-20 10:00:00');
        $this->renameManufacturer('cloud', $this->uuid, 'Office version', '2026-11-20 11:00:00');

        $this->assertCycleOk();

        $this->assertSame('Office version', $this->row('cloud', 'manufacturers', $this->uuid)->name);
        $this->assertSame('Office version', $this->row('ship', 'manufacturers', $this->uuid)->name);
        $this->assertConverged('manufacturers', ['name', 'updated_at']);

        $conflict = $this->onCloud(fn () => SyncConflict::query()->sole());
        $this->assertSame(['update_update', 'latest', 'kept_local', 'cloud'], [$conflict->kind, $conflict->policy, $conflict->resolution, $conflict->detected_by]);
        $this->assertSame('Ship version', $conflict->remote_values['name']);
        $this->assertSame('Office version', $conflict->local_values['name']);
        $this->assertSame('took_remote', $this->onShip(fn () => SyncConflict::query()->sole()->resolution), 'the vessel keeps a copy of the outcome');
    }

    public function test_the_newer_change_wins_when_the_crew_changed_it_last(): void
    {
        $this->renameManufacturer('cloud', $this->uuid, 'Office version', '2026-11-20 10:00:00');
        $this->renameManufacturer('ship', $this->uuid, 'Ship version', '2026-11-20 11:00:00');

        $this->assertCycleOk();

        $this->assertSame('Ship version', $this->row('cloud', 'manufacturers', $this->uuid)->name);
        $this->assertSame('Ship version', $this->row('ship', 'manufacturers', $this->uuid)->name);
        $this->assertSame('took_remote', $this->onCloud(fn () => SyncConflict::query()->sole()->resolution));
    }

    public function test_a_tie_goes_to_the_cloud(): void
    {
        $this->renameManufacturer('ship', $this->uuid, 'Ship version', '2026-11-20 10:00:00');
        $this->renameManufacturer('cloud', $this->uuid, 'Office version', '2026-11-20 10:00:00');

        $this->assertCycleOk();

        $this->assertConverged('manufacturers', ['name']);
        $this->assertSame('Office version', $this->row('ship', 'manufacturers', $this->uuid)->name);
    }

    public function test_a_fixed_rule_ignores_the_times(): void
    {
        config(['sync.replication.manufacturers.conflict' => ReplicationRules::VESSEL]);
        $this->renameManufacturer('ship', $this->uuid, 'Ship version', '2026-11-20 09:00:00');
        $this->renameManufacturer('cloud', $this->uuid, 'Office version', '2026-11-20 23:00:00');

        $this->assertCycleOk();

        $this->assertSame('Ship version', $this->row('cloud', 'manufacturers', $this->uuid)->name);
        $this->assertConverged('manufacturers', ['name']);
        $this->assertSame('vessel', $this->onCloud(fn () => SyncConflict::query()->sole()->policy));
    }

    /**
     * Checkpoint 14 (ADR-003 rule 5, PD-68 option 1): the office's deletion
     * stands and the record is not brought back. The crew's change is kept
     * in the conflict record for a person. Before Checkpoint 14 the change
     * recreated the record.
     */
    public function test_deleted_on_the_cloud_but_changed_on_board_stays_deleted(): void
    {
        $this->onCloud(fn () => DB::table('manufacturers')->where('sync_uuid', $this->uuid)->delete());
        $this->renameManufacturer('ship', $this->uuid, 'Still in use on board', '2026-11-20 09:00:00');

        $this->assertCycleOk();

        $this->assertNull($this->row('cloud', 'manufacturers', $this->uuid), 'not brought back on the cloud');
        $this->assertNull($this->row('ship', 'manufacturers', $this->uuid), 'the deletion reaches the vessel');

        $conflict = $this->onCloud(fn () => SyncConflict::query()->sole());
        $this->assertSame(['update_delete', 'kept_local', 'needs_review'], [$conflict->kind, $conflict->resolution, $conflict->status]);
        $this->assertSame('Still in use on board', $conflict->remote_values['name'], 'the crew\'s change is kept for review');
    }

    /**
     * ADR-006: records are not deleted on board. A delete that still comes
     * from a vessel is refused and flagged; the office's record stays, and
     * the vessel, where the row is gone, does not get it back on its own.
     */
    public function test_deleted_on_board_is_refused_and_flagged(): void
    {
        $this->onShip(fn () => DB::table('manufacturers')->where('sync_uuid', $this->uuid)->delete());
        $this->renameManufacturer('cloud', $this->uuid, 'Office correction', '2026-11-20 09:00:00');

        $this->assertCycleOk();

        $this->assertSame('Office correction', $this->row('cloud', 'manufacturers', $this->uuid)?->name, 'kept on the cloud');
        $refused = $this->onCloud(fn () => SyncConflict::query()->sole());
        $this->assertSame(['refused', 'needs_review'], [$refused->kind, $refused->status]);
        $this->assertStringContainsString('ADR-006', $refused->note);

        $this->assertNull($this->row('ship', 'manufacturers', $this->uuid), 'a deleted row is not brought back');
        $this->assertSame(['refused', 'update_delete'], $this->onShip(fn () => SyncConflict::query()->orderBy('kind')->pluck('kind')->all()),
            'the vessel is told its delete was refused, and keeps the office\'s change for review');
    }

    public function test_deleted_on_both_sides_is_not_a_conflict(): void
    {
        $this->onShip(fn () => DB::table('manufacturers')->where('sync_uuid', $this->uuid)->delete());
        $this->onCloud(fn () => DB::table('manufacturers')->where('sync_uuid', $this->uuid)->delete());

        $this->assertCycleOk();

        $this->assertSame(0, $this->cloudCount('manufacturers') + $this->shipCount('manufacturers'));
        $this->assertSame(0, $this->onCloud(fn () => SyncConflict::query()->count()) + $this->onShip(fn () => SyncConflict::query()->count()));
    }

    public function test_the_same_change_on_both_sides_is_not_a_conflict(): void
    {
        $this->renameManufacturer('ship', $this->uuid, 'Same', '2026-11-20 10:00:00');
        $this->renameManufacturer('cloud', $this->uuid, 'Same', '2026-11-20 10:00:00');

        $this->assertCycleOk();

        $this->assertSame(0, $this->onCloud(fn () => SyncConflict::query()->count()));
        $this->assertConverged('manufacturers', ['name', 'updated_at']);
    }

    public function test_a_conflict_found_on_board_while_pulling(): void
    {
        $this->renameManufacturer('cloud', $this->uuid, 'Office version', '2026-11-20 10:00:00');
        // The crew edit the row after the vessel pushed, while the pull is on its way.
        $this->network = function ($path, $n) {
            if ($path === '/api/sync/v1/pull' && $n === 1) {
                $this->renameManufacturer('ship', $this->uuid, 'Crew version', '2026-11-20 11:00:00');
            }

            return 'up';
        };

        $this->assertCycleOk();
        $this->onShip(function () {
            $conflict = SyncConflict::query()->sole();
            $this->assertSame(['vessel', 'kept_local'], [$conflict->detected_by, $conflict->resolution]);
            $this->assertSame('Crew version', DB::table('manufacturers')->where('sync_uuid', $this->uuid)->value('name'));
        });

        $this->assertCycleOk(); // the crew's newer edit now goes up
        $this->assertConverged('manufacturers', ['name']);
        $this->assertSame('Crew version', $this->row('cloud', 'manufacturers', $this->uuid)->name);
    }

    public function test_the_same_record_created_on_both_sides_is_kept_apart_for_review(): void
    {
        $onBoard = $this->addManufacturer('ship', 'Wartsila');
        $inOffice = $this->addManufacturer('cloud', 'Wartsila');

        $result = $this->assertCycleOk();

        $this->assertSame(1, $result['summary']['pushed']['rejected']);
        $this->assertSame($inOffice, $this->onCloud(fn () => DB::table('manufacturers')->where('name', 'Wartsila')->value('sync_uuid')), 'nothing overwritten');
        $this->assertSame($onBoard, $this->onShip(fn () => DB::table('manufacturers')->where('name', 'Wartsila')->value('sync_uuid')));
        foreach (['cloud', 'ship'] as $server) {
            $detectedBy = $server === 'ship' ? 'vessel' : 'cloud';
            $conflict = $this->on($server, fn () => SyncConflict::query()->where('kind', 'duplicate_key')->where('detected_by', $detectedBy)->sole());
            $this->assertSame(['needs_review', 'needs_review'], [$conflict->status, $conflict->resolution]);
        }

        $this->assertSame('duplicate_key', $this->onShip(fn () => SyncConflict::query()->where('detected_by', 'cloud')->sole()->kind), 'the cloud\'s finding is mirrored on board with its kind');

        $this->onCloud(fn () => $this->artisan('sync:conflicts')->expectsOutputToContain('duplicate_key')->assertSuccessful()->run());
    }

    public function test_after_many_concurrent_edits_both_sides_agree(): void
    {
        $more = [$this->uuid, $this->addManufacturer('cloud', 'Second'), $this->addManufacturer('ship', 'Third')];
        $this->assertCycleOk();

        foreach (range(1, 6) as $round) {
            foreach ($more as $i => $uuid) {
                $hour = str_pad((string) ($round + $i), 2, '0', STR_PAD_LEFT);
                $this->renameManufacturer($round % 2 ? 'ship' : 'cloud', $uuid, "r{$round} ship {$i}", "2026-11-21 {$hour}:00:00");
                $this->renameManufacturer($round % 2 ? 'cloud' : 'ship', $uuid, "r{$round} office {$i}", "2026-11-21 {$hour}:30:00");
            }
            $this->assertCycleOk();
        }
        $this->assertCycleOk();

        $this->assertConverged('manufacturers', ['name', 'updated_at']);
        foreach ($more as $i => $uuid) {
            $this->assertSame("r6 office {$i}", $this->row('ship', 'manufacturers', $uuid)->name, 'the later edit of the last round wins');
        }
    }
}
