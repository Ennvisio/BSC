<?php

namespace Tests\Feature\Sync;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsPmsSchema;
use Tests\Concerns\BuildsSyncSchema;
use Tests\TestCase;

/** PMS tables already have UUID primary keys, which serve as their global id. */
class SyncCaptureUuidKeyTest extends TestCase
{
    use BuildsPmsSchema;
    use BuildsSyncSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildPmsSchema();
        $this->runSyncMigrations();
        $this->installSyncTriggers();
        $this->clearOutbox();
    }

    public function test_a_uuid_keyed_row_uses_its_primary_key_as_its_global_id(): void
    {
        $component = $this->makeComponent();

        $event = $this->outbox()->firstWhere('table_name', 'pms_components');
        $this->assertSame($component->id, $event->row_id);
        $this->assertSame($component->id, $event->row_uuid);
        $this->assertSame($this->vesselId, (int) $event->vessel_id);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('pms_components', 'sync_uuid'));
    }

    public function test_child_rows_of_uuid_keyed_tables_find_their_vessel(): void
    {
        $component = $this->makeComponent();
        $this->reading($component, '2026-11-01', 1200);
        $this->makeDefinition($component, [], [[6, 'Month']], ['Check oil']);

        $events = $this->outbox()->keyBy('table_name');
        foreach (['pms_counter_readings', 'pms_job_definitions', 'pms_job_intervals', 'pms_job_checklist_items'] as $table) {
            $this->assertSame($this->vesselId, (int) $events[$table]->vessel_id, "{$table} must carry the vessel");
        }
    }

    public function test_updating_a_uuid_keyed_row_raises_its_version(): void
    {
        $component = $this->makeComponent();
        $this->clearOutbox();

        $component->update(['name' => 'RENAMED']);

        $this->assertSame(2, (int) DB::table('pms_components')->where('id', $component->id)->value('sync_version'));
        $this->assertSame(['update'], $this->outbox()->pluck('operation')->all());
    }
}
