<?php

namespace Tests\Feature\Sync;

use App\Sync\SyncRegistry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\Concerns\BuildsSyncSchema;
use Tests\TestCase;

/**
 * The two Checkpoint 2 migrations: global UUIDs for existing rows, the sync
 * columns, the sync tables, and rolling all of it back.
 */
class SyncMigrationTest extends TestCase
{
    use BuildsRequisitionSchema;
    use BuildsSyncSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();
    }

    private function seedBudgetGroups(int $count): void
    {
        foreach (array_chunk(range(1, $count), 500) as $chunk) {
            DB::table('budget_groups')->insert(array_map(fn ($i) => ['name' => "Group {$i}", 'status' => true, 'created_by' => 1], $chunk));
        }
    }

    public function test_every_existing_row_gets_its_own_uuid_across_several_batches(): void
    {
        // More than one backfill batch (5,000 rows), to prove the loop finishes.
        $this->seedBudgetGroups(6200);
        foreach (range(1, 40) as $i) {
            $this->makeUser('master', $this->vesselId);
        }

        $this->runSyncMigrations();

        foreach (['budget_groups', 'users', 'vessels', 'roles'] as $table) {
            $total = DB::table($table)->count();
            $this->assertGreaterThan(0, $total);
            $this->assertSame(0, DB::table($table)->whereNull('sync_uuid')->count(), "{$table} has rows without a uuid");
            $this->assertSame($total, DB::table($table)->distinct()->count('sync_uuid'), "{$table} has a repeated uuid");
            $this->assertSame($total, DB::table($table)->where('sync_version', 1)->count(), "{$table} rows must start at version 1");
        }

        DB::table('budget_groups')->limit(50)->pluck('sync_uuid')->each(fn ($uuid) => $this->assertValidUuid($uuid));
    }

    public function test_existing_primary_keys_and_data_are_untouched(): void
    {
        $this->seedBudgetGroups(30);
        $before = DB::table('budget_groups')->orderBy('id')->get(['id', 'name', 'status']);

        $this->runSyncMigrations();

        $this->assertEquals($before, DB::table('budget_groups')->orderBy('id')->get(['id', 'name', 'status']));
    }

    public function test_the_database_refuses_a_repeated_uuid(): void
    {
        $this->runSyncMigrations();
        $existing = DB::table('vessels')->value('sync_uuid');

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('budget_groups')->insert(['name' => 'One', 'status' => true, 'created_by' => 1, 'sync_uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa']);
        DB::table('budget_groups')->insert(['name' => 'Two', 'status' => true, 'created_by' => 1, 'sync_uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa']);
        $this->assertNotNull($existing);
    }

    public function test_the_sync_tables_are_created_with_unique_event_ids(): void
    {
        $this->runSyncMigrations();

        foreach (['sync_nodes', 'sync_outbox', 'sync_inbox', 'sync_cursors', 'sync_logs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} is missing");
        }

        $this->assertTrue(Schema::hasIndex('sync_outbox', 'sync_outbox_event_id_unique'));
        $this->assertTrue(Schema::hasIndex('sync_inbox', 'sync_inbox_event_id_unique'));
        $this->assertTrue(Schema::hasIndex('sync_cursors', 'sync_cursors_node_id_stream_unique'));
    }

    public function test_the_migrations_can_be_repeated_and_rolled_back_without_losing_data(): void
    {
        $this->seedBudgetGroups(20);
        $columnsBefore = Schema::getColumnListing('orders');
        $rowsBefore = DB::table('budget_groups')->orderBy('id')->get();

        $this->runSyncMigrations();
        $this->syncMigration(self::SYNC_MIGRATIONS[1])->up(); // a repeat is harmless
        $uuids = DB::table('budget_groups')->pluck('sync_uuid', 'id');
        $this->syncMigration(self::SYNC_MIGRATIONS[1])->up();
        $this->assertEquals($uuids, DB::table('budget_groups')->pluck('sync_uuid', 'id'), 'a repeat must not replace existing uuids');

        $this->syncMigration(self::SYNC_MIGRATIONS[1])->down();
        $this->syncMigration(self::SYNC_MIGRATIONS[0])->down();

        $this->assertSame($columnsBefore, Schema::getColumnListing('orders'));
        $this->assertFalse(Schema::hasColumn('budget_groups', 'sync_uuid'));
        $this->assertFalse(Schema::hasTable('sync_outbox'));
        $this->assertEquals($rowsBefore, DB::table('budget_groups')->orderBy('id')->get(), 'rolling back must keep every row and value');
    }

    public function test_the_migration_covers_exactly_the_registered_tables(): void
    {
        $constants = (new ReflectionClass($this->syncMigration(self::SYNC_MIGRATIONS[1])))->getConstants();
        $registry = app(SyncRegistry::class);

        $withSyncUuid = array_filter($registry->tableNames(), fn ($t) => $registry->hasSyncUuidColumn($t));
        $withUuidKey = array_diff($registry->tableNames(), $withSyncUuid);

        // Tables registered later get their columns from their own migration.
        $later = (new ReflectionClass($this->syncMigration('2026_11_26_130100_add_sync_columns_to_vessel_certificate_copies_table.php')))->getConstants();

        $this->assertEqualsCanonicalizing($withSyncUuid, array_merge($constants['WITH_SYNC_UUID'], $later['WITH_SYNC_UUID']));
        $this->assertEqualsCanonicalizing($withUuidKey, $constants['UUID_PRIMARY_KEY']);
        $this->assertSame([], $registry->definitionErrors());
    }
}
