<?php

namespace Tests\Feature\Sync\Modules;

use App\Services\CatalogService;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SyncEngineHarness;
use Tests\TestCase;

/**
 * Two servers with the application's real schema (every migration that runs
 * on SQLite), the four business modules enabled, and a small fleet: the
 * office (super-admin, SRD, SSM, superintendents) and the crew of MV TEST,
 * which runs its own server. Tests act through the real controllers and
 * services on either side, then sync.
 *
 * The vessel starts as a copy of the cloud (the bootstrap a later checkpoint
 * builds). After that, ids on board are pushed 1000 ahead, so a test only
 * passes if references really travel as UUIDs.
 */
abstract class BusinessSyncTestCase extends TestCase
{
    use SyncEngineHarness;

    /** Migrations that only run on MySQL; stand-ins follow in buildServerSchema(). */
    private const MYSQL_ONLY = [
        '2026_09_23_110100_add_title_and_category_to_vessel_certificates_table.php',
        '2026_09_23_120000_make_issue_auth_nullable_on_vessel_certificates.php',
        '2026_11_15_100000_add_performance_indexes.php',
    ];

    protected const USERS = [
        'admin@office.test' => ['super-admin', null],
        'gm.srd@office.test' => ['gm-srd', null],
        'am.ssm@office.test' => ['am-ssm', null],
        'dgm.ssm@office.test' => ['dgm-ssm', null],
        'tech.supt@office.test' => ['technical-superintendent', null],
        'co@mvtest.test' => ['chief-officer', 7],
        'master@mvtest.test' => ['master', 7],
        'ce@mvtest.test' => ['chief-engineer', 7],
        '2e@mvtest.test' => ['second-engineer', 7],
        'master@mvother.test' => ['master', 8],
    ];

    protected int $categoryId;

    protected int $groupId;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'sync.enabled_tables' => 'currencies,ranks,categories,@master,@inventory,@pms,@requisitions',
            'sync.worker.jitter' => 0,
        ]);
        $this->setUpSyncServers();
    }

    protected function buildServerSchema(): void
    {
        foreach (glob(database_path('migrations/*.php')) as $file) {
            $name = basename($file);
            if (str_contains($name, '_sync_') || in_array($name, self::MYSQL_ONLY, true)) {
                continue;
            }
            Artisan::call('migrate', ['--path' => [$file], '--realpath' => true, '--force' => true]);
        }

        // What add_wizard_fields_to_orders_table does on MySQL only: a draft
        // has no category or requisition number yet.
        Schema::table('orders', function (Blueprint $t) {
            $t->integer('category_id')->nullable()->change();
            $t->string('req_no')->nullable()->change();
        });

        // Stand-in for the MySQL-only certificate migration: its columns.
        Schema::table('vessel_certificates', function (Blueprint $t) {
            $t->string('title')->nullable();
            $t->unsignedBigInteger('category_id')->nullable();
        });
    }

    protected function seedCloud(): void
    {
        $vessel = fn (int $id, string $name, ?string $offline) => DB::table('vessels')->insert([
            'id' => $id, 'name' => $name, 'owner_name' => '-', 'owner_address' => '-', 'manager_name' => '-',
            'manager_address' => '-', 'master_name' => '-', 'master_cert_no' => '-', 'master_cert_validity' => '2030-01-01',
            'ch_eng_name' => '-', 'ch_eng_cert_no' => '-', 'ch_eng_cert_validity' => '2030-01-01', 'status' => true,
            'offline_enabled_at' => $offline, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $vessel(7, 'MV TEST', '2026-11-20 00:00:00');
        $vessel(8, 'MV OTHER', null);

        $password = Hash::make('secret');
        foreach (self::USERS as $email => [$role, $vesselId]) {
            $id = DB::table('users')->insertGetId(['name' => ucfirst($role), 'email' => $email, 'password' => $password, 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('roles')->insert([
                'user_id' => $id, 'vessel_id' => $vesselId, 'role' => $role, 'user_type' => \App\Role::TYPE_BY_ROLE[$role],
                'created_by' => 'seed', 'status' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->categoryId = (int) DB::table('categories')->where('symbol', 'SPR')->value('id');
        $this->groupId = DB::table('item_groups')->insertGetId(['name' => 'ENGINE SPARES', 'path' => 'ENGINE SPARES', 'category_id' => $this->categoryId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('budget_groups')->insert(['name' => 'Maintenance', 'status' => true, 'created_by' => 'seed', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pms_locations')->insert(['name' => 'ENGINE ROOM', 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pms_component_categories')->insert(['name' => 'Engine', 'is_critical' => true, 'is_class_relevant' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pms_job_types')->insert(['name' => 'OVERHAUL', 'abbreviation' => 'O', 'created_at' => now(), 'updated_at' => now()]);
        if (DB::table('ranks')->count() === 0) {
            DB::table('ranks')->insert(['code' => 'CE', 'name' => 'CHIEF ENGINEER', 'department' => 'Engine', 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (['Good' => false, 'Fair' => false, 'Not Classified' => true] as $name => $default) {
            if (! DB::table('pms_conditions')->where('name', $name)->exists()) {
                DB::table('pms_conditions')->insert(['name' => $name, 'is_default' => $default, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        // The catalog, and MV TEST's stock of one item (10 on board).
        $this->addItem('ME-100', 'FUEL INJECTOR');
        $this->addItem('ME-200', 'PISTON RING');
        DB::table('vessel_items')->insert(['vessel_id' => 7, 'item_id' => $this->itemId('ME-100'), 'stock_qty' => 10, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function bootstrapTables(): array
    {
        return []; // copied in afterBootstrap(), everything at once
    }

    /** The vessel's server starts as a copy of everything the cloud holds. */
    protected function afterBootstrap(): void
    {
        $tables = $this->onCloud(fn () => array_values(array_filter(app(\App\Sync\SyncRegistry::class)->tableNames(), fn ($t) => Schema::hasTable($t))));
        $rows = [];
        foreach ($tables as $table) {
            $rows[$table] = $this->onCloud(fn () => DB::table($table)->get()->map(fn ($r) => (array) $r)->all());
        }

        $this->onShip(function () use ($tables, $rows) {
            DB::statement('PRAGMA foreign_keys = OFF');
            \App\Sync\SyncContext::withoutCapture(function () use ($tables, $rows) {
                foreach ($tables as $table) {
                    DB::table($table)->delete();
                    foreach (array_chunk($rows[$table], 200) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }
            });
            DB::statement('PRAGMA foreign_keys = ON');

            // New rows on board get ids the cloud does not have.
            foreach ($tables as $table) {
                if (Schema::hasColumn($table, 'id') && ! in_array($table, ['pms_components', 'pms_job_definitions', 'pms_jobs'], true)) {
                    $next = (int) DB::table($table)->max('id') + 1000;
                    DB::statement('DELETE FROM sqlite_sequence WHERE name = ?', [$table]);
                    DB::statement('INSERT INTO sqlite_sequence (name, seq) VALUES (?, ?)', [$table, $next]);
                }
            }
        });

        $this->categoryId = $this->onCloud(fn () => (int) DB::table('categories')->where('symbol', 'SPR')->value('id'));
        $this->groupId = $this->onCloud(fn () => (int) DB::table('item_groups')->where('name', 'ENGINE SPARES')->value('id'));
    }

    // ------------------------------------------------------------- helpers

    protected function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    /** A request as $email on the server currently active (use inside onShip / onCloud). */
    protected function as(string $email): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($this->user($email));
    }

    protected function addItem(string $article, string $name, ?int $groupId = null): int
    {
        $groupId ??= (int) DB::table('item_groups')->where('name', 'ENGINE SPARES')->value('id');
        $category = (int) DB::table('categories')->where('symbol', 'SPR')->value('id');

        return DB::table('items')->insertGetId([
            'category_id' => $category, 'item_group_id' => $groupId, 'impa_code' => 0, 'name' => $name, 'unit' => 'PC',
            'article_number' => $article, 'item_key' => CatalogService::itemKey($article, $groupId, $name),
            'created_by' => 'seed', 'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function itemId(string $article): int
    {
        return (int) DB::table('items')->where('article_number', $article)->value('id');
    }

    protected function idOf(string $table, string $uuid): ?int
    {
        $id = DB::table($table)->where('sync_uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }

    protected function uuidFor(string $table, int $id): string
    {
        return (string) DB::table($table)->where('id', $id)->value('sync_uuid');
    }

    protected function userId(string $email): int
    {
        return (int) DB::table('users')->where('email', $email)->value('id');
    }

    /**
     * A requisition drafted on board by the chief officer, as the wizard
     * leaves it before Submit: the draft, its lines and Part A.
     *
     * @param  array<string, int>  $lines  article number => quantity
     */
    protected function draftOnBoard(array $lines): int
    {
        return $this->onShip(function () use ($lines) {
            $co = $this->userId('co@mvtest.test');
            $orderId = DB::table('orders')->insertGetId([
                'vessel_id' => $this->vesselId, 'category_id' => $this->categoryId, 'req_date' => now()->toDateString(),
                'port_name' => 'Chattogram', 'status' => 'draft', 'ord_status' => false, 'created_by' => $co,
                'created_by_role' => 'chief-officer', 'title' => 'Main engine spares', 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($lines as $article => $qty) {
                DB::table('order_items')->insert(['order_id' => $orderId, 'item_id' => $this->itemId($article), 'item_qty' => $qty, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('order_form_parts')->insert([
                'order_id' => $orderId, 'part' => 'A', 'form_version' => 1, 'answers' => '{}', 'completed_at' => now(),
                'filled_by' => $co, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $orderId;
        });
    }

    /** Submitted through the real controller; returns the order's UUID. */
    protected function submitOnBoard(int $orderId): string
    {
        return $this->onShip(function () use ($orderId) {
            $this->as('co@mvtest.test')->post("/requisition/{$orderId}/submit")->assertRedirect();

            return $this->uuidFor('orders', $orderId);
        });
    }

    protected function approve(string $email, int $orderId): TestResponse
    {
        return $this->as($email)->postJson('/order/approve', ['id' => $orderId]);
    }

    protected function assertCycleOk(): array
    {
        $result = $this->syncCycle();
        $this->assertTrue($result['ok'], 'Sync cycle failed: '.json_encode($result));
        $this->assertSame(0, $result['summary']['pushed']['rejected'] + $result['summary']['pulled']['rejected'], 'Rejected changes: '.json_encode([$this->inboxProblems('cloud'), $this->inboxProblems('ship')]));

        return $result;
    }

    /** Failed or waiting received changes on $server, with the reasons. */
    protected function inboxProblems(string $server): array
    {
        return $this->on($server, fn () => DB::table('sync_inbox')->whereIn('status', ['failed', 'deferred'])->get(['table_name', 'status', 'last_error'])->map(fn ($r) => (array) $r)->all());
    }
}
