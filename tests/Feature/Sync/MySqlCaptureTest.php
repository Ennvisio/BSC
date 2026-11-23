<?php

namespace Tests\Feature\Sync;

use App\Sync\SyncContext;
use App\Sync\SyncInbox;
use App\Sync\SyncRegistry;
use App\Sync\TriggerManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * The capture triggers on real MySQL, the production engine, against the
 * complete schema built from every migration.
 *
 * Opt-in, because it needs a MySQL server. Set SYNC_MYSQL_TEST_DATABASE (and
 * optionally _HOST, _PORT, _USERNAME, _PASSWORD). The database is wiped and
 * rebuilt, so its name must end in "_test" and the host must be local.
 */
class MySqlCaptureTest extends TestCase
{
    private const CONNECTION = 'sync_mysql_test';

    private static bool $built = false;

    private int $vesselId;

    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) env('SYNC_MYSQL_TEST_DATABASE', '');
        $host = (string) env('SYNC_MYSQL_TEST_HOST', '127.0.0.1');

        if ($database === '') {
            $this->markTestSkipped('Set SYNC_MYSQL_TEST_DATABASE to run the MySQL capture tests.');
        }
        if (! str_ends_with($database, '_test') || ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $this->fail('Refusing to rebuild a database whose name does not end in "_test" or that is not on this machine.');
        }

        config([
            'database.connections.'.self::CONNECTION => [
                'driver' => 'mysql', 'host' => $host, 'port' => env('SYNC_MYSQL_TEST_PORT', '3306'),
                'database' => $database, 'username' => env('SYNC_MYSQL_TEST_USERNAME', 'root'),
                'password' => env('SYNC_MYSQL_TEST_PASSWORD', ''), 'unix_socket' => '',
                'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
                'prefix_indexes' => true, 'strict' => true, 'engine' => null,
            ],
            'database.default' => self::CONNECTION,
        ]);
        DB::purge(self::CONNECTION);

        if (! self::$built) {
            Artisan::call('migrate:fresh', ['--database' => self::CONNECTION, '--force' => true]);
            app(TriggerManager::class)->install();
            self::$built = true;
        }

        SyncContext::withoutCapture(function () {
            $this->vesselId = (int) (DB::table('vessels')->value('id') ?? DB::table('vessels')->insertGetId([
                'name' => 'MT MYSQL', 'owner_name' => '-', 'owner_address' => '-', 'manager_name' => '-',
                'manager_address' => '-', 'master_name' => '-', 'master_cert_no' => '-', 'master_cert_validity' => '2030-01-01',
                'ch_eng_name' => '-', 'ch_eng_cert_no' => '-', 'ch_eng_cert_validity' => '2030-01-01', 'status' => true,
            ]));
        });

        DB::table('sync_outbox')->delete();
        DB::table('sync_inbox')->delete();
        DB::table('sync_deletions')->delete();
    }

    private function outbox()
    {
        return DB::table('sync_outbox')->orderBy('id')->get();
    }

    private function insertOrder(?string $reqNo = null): int
    {
        return DB::table('orders')->insertGetId([
            'vessel_id' => $this->vesselId, 'category_id' => DB::table('categories')->value('id'), 'req_no' => $reqNo ?? 'MY/'.uniqid(),
            'req_date' => '2026-11-20', 'port_name' => 'Port', 'status' => 'draft', 'created_by' => 1,
        ]);
    }

    public function test_every_table_is_either_synchronised_or_explicitly_excluded(): void
    {
        $registry = app(SyncRegistry::class);
        $tables = collect(DB::select('SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE()'))->pluck('name')->all();
        $known = array_merge($registry->tableNames(), array_keys($registry->excluded()));

        $this->assertSame([], array_values(array_diff($tables, $known)), 'Add these tables to config/sync.php');
        $this->assertSame([], array_values(array_diff($known, $tables)), 'config/sync.php lists tables that do not exist');
    }

    public function test_every_synchronised_table_is_captured(): void
    {
        $manager = app(TriggerManager::class);

        $this->assertTrue($manager->isFullyInstalled());
        $this->assertSame([], $manager->tablesWithMissingReferences());
        $this->assertCount(count(app(SyncRegistry::class)->tableNames()), $manager->status());
    }

    public function test_inserts_updates_and_deletes_are_recorded_with_versions_and_vessels(): void
    {
        $orderId = $this->insertOrder();
        $uuid = DB::table('orders')->where('id', $orderId)->value('sync_uuid');
        $itemId = DB::table('order_items')->insertGetId(['order_id' => $orderId, 'item_id' => 1, 'item_qty' => 1]);
        DB::table('orders')->where('id', $orderId)->update(['status' => 'submitted', 'sync_uuid' => (string) Str::uuid()]);
        DB::table('order_items')->where('id', $itemId)->delete();

        $this->assertSame($uuid, DB::table('orders')->where('id', $orderId)->value('sync_uuid'), 'the uuid must not change');
        $this->assertSame(2, (int) DB::table('orders')->where('id', $orderId)->value('sync_version'));

        $this->assertSame([
            ['orders', 'insert', 1, $this->vesselId],
            ['order_items', 'insert', 1, $this->vesselId],
            ['orders', 'update', 2, $this->vesselId],
            ['order_items', 'delete', 1, $this->vesselId],
        ], $this->outbox()->map(fn ($e) => [$e->table_name, $e->operation, (int) $e->row_version, (int) $e->vessel_id])->all());
    }

    public function test_a_rolled_back_transaction_leaves_no_row_and_no_event(): void
    {
        $reqNo = 'ROLLBACK/'.uniqid();

        try {
            DB::transaction(function () use ($reqNo) {
                $this->insertOrder($reqNo);
                throw new RuntimeException('simulated failure');
            });
        } catch (RuntimeException) {
        }

        $this->assertFalse(DB::table('orders')->where('req_no', $reqNo)->exists());
        $this->assertCount(0, $this->outbox());
    }

    public function test_a_thousand_new_rows_get_a_thousand_distinct_uuids(): void
    {
        DB::table('manufacturers')->insert(array_map(fn ($i) => ['name' => 'Bulk '.uniqid()."-{$i}"], range(1, 1000)));

        $uuids = DB::table('manufacturers')->where('name', 'like', 'Bulk %')->pluck('sync_uuid');
        $this->assertCount(1000, $uuids->unique());
        $this->assertCount(1000, $this->outbox()->pluck('event_id')->unique());
    }

    public function test_capture_off_and_origin_use_session_variables_that_are_reset(): void
    {
        $orderId = $this->insertOrder();
        DB::table('sync_outbox')->delete();
        $origin = '44444444-4444-4444-8444-444444444444';

        SyncContext::withoutCapture(fn () => DB::table('orders')->where('id', $orderId)->update(['status' => 'quiet']));
        SyncContext::asOrigin($origin, fn () => DB::table('orders')->where('id', $orderId)->update(['status' => 'remote', 'sync_version' => 5]));

        $event = $this->outbox()->sole();
        $this->assertSame([$origin, 5], [$event->origin_node, (int) $event->row_version]);
        $this->assertEquals((object) ['a' => null, 'b' => null], DB::selectOne('SELECT @sync_capture_off AS a, @sync_origin AS b'));
    }

    /** Checkpoint 14: the deletion record and the actor, from the MySQL triggers. */
    public function test_a_delete_leaves_a_lasting_record_with_its_actor_and_origin(): void
    {
        $actor = '55555555-5555-4555-8555-555555555555';
        $origin = '66666666-6666-4666-8666-666666666666';
        $orderId = $this->insertOrder();
        $first = DB::table('order_items')->insertGetId(['order_id' => $orderId, 'item_id' => 1, 'item_qty' => 1]);
        $second = DB::table('order_items')->insertGetId(['order_id' => $orderId, 'item_id' => 2, 'item_qty' => 1]);
        $third = DB::table('order_items')->insertGetId(['order_id' => $orderId, 'item_id' => 3, 'item_qty' => 1]);
        $uuids = DB::table('order_items')->whereIn('id', [$first, $second, $third])->pluck('sync_uuid', 'id');

        SyncContext::asActor($actor, fn () => DB::table('order_items')->where('id', $first)->delete());
        SyncContext::asOrigin($origin, fn () => SyncContext::asActor($actor, fn () => DB::table('order_items')->where('id', $second)->delete()));
        SyncContext::withoutCapture(fn () => DB::table('order_items')->where('id', $third)->delete());

        $this->assertSame([
            [(string) $first, $uuids[$first], $actor, null, $this->vesselId],
            [(string) $second, $uuids[$second], $actor, $origin, $this->vesselId],
        ], DB::table('sync_deletions')->orderBy('id')->get()->map(fn ($d) => [(string) $d->row_id, $d->row_uuid, $d->actor_uuid, $d->origin_node, (int) $d->vessel_id])->all(),
            'one record per captured delete; none with capture off');
        $this->assertSame($actor, $this->outbox()->where('operation', 'delete')->first()->actor_uuid, 'the outbox event carries the actor');
        $this->assertEquals((object) ['a' => null], DB::selectOne('SELECT @sync_actor AS a'), 'the actor is reset afterwards');

        try {
            DB::transaction(function () use ($orderId) {
                DB::table('order_items')->insert(['order_id' => $orderId, 'item_id' => 4, 'item_qty' => 1]);
                DB::table('order_items')->where('order_id', $orderId)->delete();
                throw new \RuntimeException('rolled back');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame(2, DB::table('sync_deletions')->count(), 'a rolled-back delete leaves no record');
    }

    public function test_the_inbox_refuses_duplicates_on_mysql(): void
    {
        $event = ['event_id' => (string) Str::uuid(), 'table_name' => 'orders', 'row_uuid' => (string) Str::uuid(), 'operation' => 'update', 'row_version' => 2];
        $inbox = app(SyncInbox::class);

        $this->assertTrue($inbox->receive('55555555-5555-4555-8555-555555555555', $event));
        $this->assertFalse($inbox->receive('55555555-5555-4555-8555-555555555555', $event));
        $this->assertSame(1, DB::table('sync_inbox')->count());
    }

    /** Runs last: it rolls the sync migrations back and forward again. */
    public function test_the_sequencer_numbers_a_late_commit_after_events_already_numbered(): void
    {
        // A second connection plays a web request whose transaction is still open.
        config(['database.connections.sync_mysql_other' => config('database.connections.'.self::CONNECTION)]);
        $other = DB::connection('sync_mysql_other');
        $sequencer = app(\App\Sync\Engine\OutboxSequencer::class);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 5');
        $start = $sequencer->highWater();

        $other->beginTransaction();
        try {
            $early = $this->rawOutboxEvent($other);   // lower id, not committed yet
            $late = $this->rawOutboxEvent(DB::connection());

            $this->assertSame(1, $sequencer->sequence(), 'only the committed event is numbered');
            $this->assertSame($start + 1, (int) DB::table('sync_outbox')->where('id', $late)->value('seq'));
            $cursor = $sequencer->highWater();   // a vessel reads up to here

            $other->commit();
        } finally {
            if ($other->transactionLevel() > 0) {
                $other->rollBack();
            }
        }

        $this->assertLessThan($late, $early);
        $this->assertSame(1, $sequencer->sequence());
        $this->assertSame($start + 2, (int) DB::table('sync_outbox')->where('id', $early)->value('seq'), 'numbered after, so the cursor cannot have passed it');
        $this->assertGreaterThan($cursor, $start + 2);
        $this->assertSame(1, (int) DB::scalar("SELECT IS_FREE_LOCK('navisuite_sync_sequencer')"), 'the lock is released');
    }

    public function test_the_sequencer_refuses_to_run_inside_a_transaction(): void
    {
        $this->expectException(\LogicException::class);

        DB::transaction(fn () => app(\App\Sync\Engine\OutboxSequencer::class)->sequence());
    }

    private function rawOutboxEvent($connection): int
    {
        return (int) $connection->table('sync_outbox')->insertGetId([
            'event_id' => (string) Str::uuid(), 'table_name' => 'manufacturers', 'row_id' => 1, 'row_uuid' => (string) Str::uuid(),
            'operation' => 'update', 'row_version' => 1, 'vessel_id' => null, 'origin_node' => null, 'created_at' => now(),
        ]);
    }

    public function test_zz_rolling_back_removes_the_triggers_and_writes_keep_working(): void
    {
        // Everything from the sync tables on (Checkpoints 2 to 5), however
        // many migrations that is by now.
        $steps = DB::connection(self::CONNECTION)->table('migrations')->where('migration', '>=', '2026_11_21_100000')->count();
        Artisan::call('migrate:rollback', ['--database' => self::CONNECTION, '--step' => $steps, '--force' => true]);
        $this->assertFalse(Schema::connection(self::CONNECTION)->hasTable('sync_batches'), 'the engine tables went too');

        $this->assertSame([], app(TriggerManager::class)->installedTriggerNames());
        $orderId = $this->insertOrder();
        $this->assertGreaterThan(0, $orderId, 'writes must work without the sync columns and tables');

        Artisan::call('migrate', ['--database' => self::CONNECTION, '--force' => true]);
        app(TriggerManager::class)->install();
        $this->assertValidSyncUuid(DB::table('orders')->where('id', $orderId)->value('sync_uuid'));
    }

    private function assertValidSyncUuid(?string $uuid): void
    {
        $this->assertTrue(\App\Sync\SyncSql::isUuid($uuid));
    }
}
