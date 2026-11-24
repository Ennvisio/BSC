<?php

namespace Tests\Feature\Security;

use App\Sync\SyncContext;
use App\Sync\TriggerManager;
use App\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * PD-60 on real MySQL, where the foreign keys really cascade: a permanent
 * delete the trash refuses leaves every child row in place, and the same
 * delete made without the guard would have taken them (the control).
 *
 * Opt-in, like MySqlCaptureTest: set SYNC_MYSQL_TEST_DATABASE (a local
 * database whose name ends in "_test"; it is wiped and rebuilt). Each test
 * runs inside a transaction that is rolled back.
 */
class TrashGuardMySqlTest extends TestCase
{
    private const CONNECTION = 'sync_mysql_test';

    private static bool $built = false;

    private int $vesselId;

    private int $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) env('SYNC_MYSQL_TEST_DATABASE', '');
        $host = (string) env('SYNC_MYSQL_TEST_HOST', '127.0.0.1');
        if ($database === '') {
            $this->markTestSkipped('Set SYNC_MYSQL_TEST_DATABASE to run the MySQL trash guard tests.');
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
            'navisuite.mode' => 'cloud', 'navisuite.vessel_id' => null, 'navisuite.support_accounts' => '',
        ]);
        DB::purge(self::CONNECTION);

        if (! self::$built) {
            Artisan::call('migrate:fresh', ['--database' => self::CONNECTION, '--force' => true]);
            app(TriggerManager::class)->install();
            self::$built = true;
        }

        DB::beginTransaction();

        $this->vesselId = DB::table('vessels')->insertGetId([
            'name' => 'MT TRASH', 'owner_name' => '-', 'owner_address' => '-', 'manager_name' => '-',
            'manager_address' => '-', 'master_name' => '-', 'master_cert_no' => '-', 'master_cert_validity' => '2030-01-01',
            'ch_eng_name' => '-', 'ch_eng_cert_no' => '-', 'ch_eng_cert_validity' => '2030-01-01', 'status' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->superAdmin = DB::table('users')->insertGetId([
            'name' => 'Admin', 'email' => 'trash-admin-'.uniqid().'@test.test', 'password' => Hash::make('secret'),
            'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('roles')->insert([
            'user_id' => $this->superAdmin, 'vessel_id' => null, 'role' => 'super-admin', 'user_type' => \App\Role::TYPE_BY_ROLE['super-admin'],
            'created_by' => 'seed', 'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        if (self::$built) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function category(): int
    {
        return DB::table('categories')->insertGetId([
            'name' => 'Old spares '.uniqid(), 'symbol' => strtoupper(substr(uniqid(), -3)), 'status' => false,
            'created_by' => 'seed', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function retiredItem(): int
    {
        return DB::table('items')->insertGetId([
            'category_id' => $this->category(), 'impa_code' => 0, 'name' => 'Gasket', 'unit' => 'PC',
            'article_number' => 'G-'.uniqid(), 'item_key' => uniqid(), 'created_by' => 'seed', 'status' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function retiredOrder(): int
    {
        return DB::table('orders')->insertGetId([
            'vessel_id' => $this->vesselId, 'category_id' => $this->category(), 'req_no' => 'TR/'.uniqid(),
            'req_date' => '2026-11-20', 'port_name' => 'Port', 'status' => 'draft', 'ord_status' => false, 'created_by' => 1,
        ]);
    }

    /** The children of item $item on tables whose key cascades on MySQL. */
    private function itemChildren(int $item): array
    {
        DB::table('vessel_items')->insert(['vessel_id' => $this->vesselId, 'item_id' => $item, 'stock_qty' => 4, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('stock_movements')->insert([
            'vessel_id' => $this->vesselId, 'item_id' => $item, 'type' => 'receipt', 'qty' => 4, 'balance_after' => 4,
            'moved_on' => '2026-11-20', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('stock_consumptions')->insert([
            'vessel_id' => $this->vesselId, 'item_id' => $item, 'consumption_type' => 'routine', 'qty' => 1,
            'consumed_on' => '2026-11-21', 'purpose' => 'Test', 'recorded_by' => $this->superAdmin,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->rowCounts('item_id', $item, ['vessel_items', 'stock_movements', 'stock_consumptions']);
    }

    private function orderChildren(int $order): array
    {
        DB::table('order_form_parts')->insert(['order_id' => $order, 'part' => 'A', 'answers' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('order_invoices')->insert(['order_id' => $order, 'verified_by' => $this->superAdmin, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('order_procurement_steps')->insert(['order_id' => $order, 'step' => 'quotation', 'completed_by' => $this->superAdmin, 'created_at' => now(), 'updated_at' => now()]);

        return $this->rowCounts('order_id', $order, ['order_form_parts', 'order_invoices', 'order_procurement_steps']);
    }

    /** @return array<string, int> */
    private function rowCounts(string $column, int $id, array $tables): array
    {
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->where($column, $id)->count();
        }

        return $counts;
    }

    private function trashDelete(string $type, int $id)
    {
        return $this->actingAs(User::findOrFail($this->superAdmin))->postJson('/permanent-delete', ['type' => $type, 'id' => $id]);
    }

    /** Run $delete in a savepoint that is always rolled back, and return what $after saw. */
    private function withoutKeeping(callable $delete, callable $after): mixed
    {
        $seen = null;
        try {
            DB::transaction(function () use ($delete, $after, &$seen) {
                $delete();
                $seen = $after();
                throw new RuntimeException('roll back');
            });
        } catch (RuntimeException) {
        }

        return $seen;
    }

    public function test_a_refused_item_delete_leaves_every_child_row(): void
    {
        $item = $this->retiredItem();
        $before = $this->itemChildren($item);
        $this->assertSame(['vessel_items' => 1, 'stock_movements' => 1, 'stock_consumptions' => 1], $before);

        $this->trashDelete('item', $item)->assertStatus(422)->assertJson(['deleted' => false]);

        $this->assertNotNull(DB::table('items')->find($item));
        $this->assertSame($before, $this->rowCounts('item_id', $item, array_keys($before)));

        $withoutGuard = $this->withoutKeeping(
            fn () => SyncContext::withoutCapture(fn () => DB::table('items')->where('id', $item)->delete()),
            fn () => $this->rowCounts('item_id', $item, array_keys($before)),
        );
        $this->assertSame(['vessel_items' => 0, 'stock_movements' => 0, 'stock_consumptions' => 0], $withoutGuard,
            'control: on MySQL the same delete without the guard takes the children with it');
        $this->assertSame($before, $this->rowCounts('item_id', $item, array_keys($before)));
    }

    public function test_a_refused_requisition_delete_leaves_every_child_row(): void
    {
        $order = $this->retiredOrder();
        $before = $this->orderChildren($order);
        $this->assertSame(['order_form_parts' => 1, 'order_invoices' => 1, 'order_procurement_steps' => 1], $before);

        $this->trashDelete('order', $order)->assertStatus(422)->assertJson(['deleted' => false]);

        $this->assertNotNull(DB::table('orders')->find($order));
        $this->assertSame($before, $this->rowCounts('order_id', $order, array_keys($before)));

        $withoutGuard = $this->withoutKeeping(
            fn () => SyncContext::withoutCapture(fn () => DB::table('orders')->where('id', $order)->delete()),
            fn () => $this->rowCounts('order_id', $order, array_keys($before)),
        );
        $this->assertSame(['order_form_parts' => 0, 'order_invoices' => 0, 'order_procurement_steps' => 0], $withoutGuard,
            'control: on MySQL the same delete without the guard takes the children with it');
    }

    public function test_a_refused_vessel_delete_leaves_every_child_row(): void
    {
        $item = $this->retiredItem();
        $before = $this->itemChildren($item);
        DB::table('vessel_equipment')->insert(['vessel_id' => $this->vesselId, 'name' => 'Boiler', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
        $tables = ['vessel_items', 'stock_movements', 'stock_consumptions', 'vessel_equipment'];
        $held = $this->rowCounts('vessel_id', $this->vesselId, $tables);

        $this->trashDelete('vessel', $this->vesselId)->assertStatus(422)->assertJson(['deleted' => false]);

        $this->assertNotNull(DB::table('vessels')->find($this->vesselId));
        $this->assertSame($held, $this->rowCounts('vessel_id', $this->vesselId, $tables));
        $this->assertSame($before, $this->rowCounts('item_id', $item, array_keys($before)));
    }

    public function test_a_retired_item_nothing_refers_to_is_deleted_on_mysql(): void
    {
        $item = $this->retiredItem();

        $this->trashDelete('item', $item)->assertOk()->assertJson(['deleted' => true]);

        $this->assertNull(DB::table('items')->find($item));
        $this->assertSame(1, DB::table('sync_deletions')->where('table_name', 'items')->where('row_id', $item)->count(),
            'the delete leaves its deletion record');
    }

    /** 'refused' when the PD-34 ownership guard stops $write; nothing is kept either way. */
    private function verdict(callable $write): string
    {
        DB::beginTransaction();
        try {
            $write();

            return 'allowed';
        } catch (\App\Sync\Exceptions\WrongSideEdit|\App\Sync\Exceptions\VesselRecordProtected) {
            return 'refused';
        } finally {
            DB::rollBack();
        }
    }

    /** PD-34: the ownership guard's own lookups (through a parent row, on a new row, for a mass write) on MySQL. */
    public function test_the_ownership_guard_works_on_mysql(): void
    {
        config(['sync.enabled_tables' => 'currencies,ranks,categories,@master,@inventory,@pms,@requisitions']);
        $category = DB::table('categories')->insertGetId(['name' => 'Spares '.uniqid(), 'symbol' => 'S'.substr(uniqid(), -2), 'status' => true, 'created_by' => 'seed', 'created_at' => now(), 'updated_at' => now()]);
        $item = DB::table('items')->insertGetId(['category_id' => $category, 'impa_code' => 0, 'name' => 'Gasket', 'unit' => 'PC', 'article_number' => 'G-'.uniqid(), 'item_key' => uniqid(), 'created_by' => 'seed', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
        $order = DB::table('orders')->insertGetId(['vessel_id' => $this->vesselId, 'category_id' => $category, 'req_no' => 'REQ/'.uniqid(), 'req_date' => '2026-11-20', 'port_name' => 'Port', 'status' => 'submitted', 'created_by' => $this->superAdmin, 'created_at' => now(), 'updated_at' => now()]);
        $line = DB::table('order_items')->insertGetId(['order_id' => $order, 'item_id' => $item, 'item_qty' => 2, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('vessels')->where('id', $this->vesselId)->update(['offline_enabled_at' => now()]);

        // On the cloud, for a vessel that runs its own server.
        $this->assertSame('refused', $this->verdict(fn () => DB::table('order_items')->where('id', $line)->update(['rcv_item_qty' => 1])), 'the vessel\'s column, found through the requisition');
        $this->assertSame('allowed', $this->verdict(fn () => DB::table('order_items')->where('id', $line)->update(['del_item_qty' => 1])), 'the office\'s column');
        $this->assertSame('refused', $this->verdict(fn () => DB::table('order_items')->insert(['order_id' => $order, 'item_id' => $item, 'item_qty' => 1, 'created_at' => now(), 'updated_at' => now()])), 'a new line');
        $this->assertSame('refused', $this->verdict(fn () => DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.vessel_id', $this->vesselId)->update(['order_items.rcv_item_qty' => 1])), 'a mass update with a join');
        $this->assertSame('refused', $this->verdict(fn () => DB::table('orders')->where('id', $order)->delete()), 'a delete');
        $this->assertSame('allowed', $this->verdict(fn () => DB::table('orders')->where('id', $order)->update(['status' => 'approved by am (ssm)'])), 'a shared column');

        // On board.
        config(['navisuite.mode' => 'vessel', 'navisuite.vessel_id' => $this->vesselId]);
        $this->assertSame('refused', $this->verdict(fn () => DB::table('categories')->where('id', $category)->update(['name' => 'X'])));
        $this->assertSame('refused', $this->verdict(fn () => DB::table('order_items')->where('id', $line)->update(['del_item_qty' => 1])));
        $this->assertSame('allowed', $this->verdict(fn () => DB::table('order_items')->where('id', $line)->update(['rcv_item_qty' => 1])));
    }

    /**
     * PD-59 B backfill on MySQL, with rows to fill: each existing file gets
     * its owner from the uploader's role. The column already exists
     * (migrate:fresh), so only the data step runs, inside the transaction.
     */
    public function test_the_attachment_owner_backfill_works_on_mysql(): void
    {
        $crew = DB::table('users')->insertGetId([
            'name' => 'Master', 'email' => 'bf-master-'.uniqid().'@test.test', 'password' => Hash::make('secret'),
            'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('roles')->insert([
            'user_id' => $crew, 'vessel_id' => $this->vesselId, 'role' => 'master', 'user_type' => 'ship',
            'created_by' => 'seed', 'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $file = fn (int $user, string $title) => DB::table('attachments')->insertGetId([
            'uploaded_by' => $user, 'title' => $title, 'original_filename' => 'a.pdf', 'path' => 'attachments/'.$user.'/'.uniqid().'.pdf',
            'mime_type' => 'application/pdf', 'kind' => 'pdf', 'file_size' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $crewFile = $file($crew, 'Crew file');
        $officeFile = $file($this->superAdmin, 'Office file');
        DB::table('attachments')->whereIn('id', [$crewFile, $officeFile])->update(['owner_side' => null]);

        (require database_path('migrations/2026_11_27_100000_add_owner_side_to_attachments_table.php'))->up();
        (require database_path('migrations/2026_11_27_100000_add_owner_side_to_attachments_table.php'))->up(); // repeated: changes nothing

        $this->assertSame('vessel', DB::table('attachments')->where('id', $crewFile)->value('owner_side'));
        $this->assertSame('cloud', DB::table('attachments')->where('id', $officeFile)->value('owner_side'));
    }
}
