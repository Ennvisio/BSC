<?php

namespace Tests\Concerns;

use App\PmsComponent;
use App\PmsComponentCategory;
use App\PmsCondition;
use App\PmsCounterReading;
use App\PmsJobDefinition;
use App\PmsJobType;
use App\PmsLocation;
use App\Rank;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Builds just the tables the PMS needs, from the real migration files, in the
 * test suite's in-memory SQLite database. The app's full migration chain
 * can't run on SQLite (an older orders migration uses MySQL-only ALTER ...
 * MODIFY), and the PMS doesn't depend on any of that anyway.
 */
trait BuildsPmsSchema
{
    protected int $vesselId;

    protected int $userId;

    protected function buildPmsSchema(): void
    {
        $migrations = [
            '2014_10_12_000000_create_users_table.php',
            '2019_02_13_084750_create_vessels_table.php',
            '2026_10_13_100000_create_ranks_table.php',
            '2026_10_13_100100_create_pms_master_data_tables.php',
            '2026_10_13_100200_create_pms_components_table.php',
            '2026_10_13_100300_create_pms_counter_readings_table.php',
            '2026_10_13_100400_add_unique_name_to_pms_components_table.php',
            '2026_10_14_100000_create_pms_job_definitions_table.php',
            '2026_10_14_100100_create_pms_jobs_table.php',
            '2026_10_15_100000_create_pms_parts_tables.php',
            '2026_10_16_100000_make_pms_component_category_nullable.php',
            '2026_10_17_100000_add_cfm_guid_to_pms_tables.php',
        ];

        $this->buildStockStubs();

        Artisan::call('migrate', [
            '--path' => array_map(fn ($m) => database_path('migrations/'.$m), $migrations),
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->userId = DB::table('users')->insertGetId([
            'name' => 'Test Officer', 'email' => 'officer@test.local', 'password' => 'x',
        ]);
        $this->vesselId = DB::table('vessels')->insertGetId([
            'name' => 'MT TEST VESSEL', 'owner_name' => '-', 'owner_address' => '-', 'manager_name' => '-',
            'manager_address' => '-', 'master_name' => '-', 'master_cert_no' => '-', 'master_cert_validity' => '2030-01-01',
            'ch_eng_name' => '-', 'ch_eng_cert_no' => '-', 'ch_eng_cert_validity' => '2030-01-01', 'status' => true,
        ]);

        DB::table('roles')->insert(['user_id' => $this->userId, 'vessel_id' => $this->vesselId, 'role' => 'chief-officer', 'user_type' => 'ship']);

        PmsLocation::create(['name' => 'ENGINE', 'sort_order' => 10]);
        PmsComponentCategory::create(['name' => 'Engine', 'is_critical' => true, 'is_class_relevant' => true]);
        PmsJobType::create(['name' => 'OVERHAUL', 'abbreviation' => 'O']);
        Rank::create(['code' => 'CE', 'name' => 'CHIEF ENGINEER', 'department' => 'Engine']);
        foreach (['Good' => false, 'Fair' => false, 'Not Classified' => true] as $name => $default) {
            PmsCondition::create(['name' => $name, 'is_default' => $default]);
        }
    }

    /**
     * Minimal stand-ins (only the columns the PMS reads or writes) for the
     * requisition and stock tables the parts feature links to - their real
     * migrations can't run on SQLite - created before the parts migration
     * so its foreign keys have something to point at.
     */
    protected function buildStockStubs(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('vessel_id')->nullable();
            $t->string('role');
            $t->string('user_type')->nullable();
            $t->timestamps();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('symbol')->nullable();
        });
        Schema::create('items', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('category_id');
            $t->string('name');
            $t->string('unit');
            $t->string('article_number')->nullable();
            $t->string('part_number')->nullable();
            $t->string('drawing_number')->nullable();
            $t->string('manufacturer')->nullable();
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('vessel_id');
            $t->integer('category_id')->nullable();
            $t->string('req_no')->nullable();
            $t->string('title')->nullable();
            $t->string('machinery_name')->nullable();
            $t->date('req_date');
            $t->string('port_name');
            $t->string('status')->default('on process');
            $t->boolean('ord_status')->default(false);
            $t->boolean('high_priority')->default(false);
            $t->string('created_by_role');
            $t->integer('created_by');
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('order_id');
            $t->integer('item_id');
            $t->integer('item_qty');
            $t->integer('opening_stock')->nullable();
            $t->integer('last_supply_qty')->nullable();
            $t->date('last_supply_date')->nullable();
            $t->timestamps();
        });
        Schema::create('vessel_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('vessel_id');
            $t->unsignedInteger('item_id');
            $t->integer('stock_qty')->default(0);
            $t->integer('opening_stock')->nullable();
            $t->integer('min_qty')->nullable();
            $t->integer('last_supply_qty')->nullable();
            $t->date('last_supply_date')->nullable();
            $t->timestamp('stock_updated_at')->nullable();
            $t->unsignedInteger('stock_updated_by')->nullable();
            $t->timestamps();
        });
        Schema::create('stock_consumptions', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('vessel_id');
            $t->unsignedInteger('item_id');
            $t->unsignedInteger('order_id')->nullable();
            $t->string('consumption_type', 20);
            $t->integer('qty');
            $t->date('consumed_on');
            $t->string('department')->nullable();
            $t->text('purpose');
            $t->text('remarks')->nullable();
            $t->unsignedInteger('recorded_by');
            $t->timestamps();
        });
    }

    /** A catalogue item, optionally with stock on this test's vessel. */
    protected function makeItem(string $name, string $categorySymbol = 'SPR', ?int $stock = null, array $attributes = []): \App\Item
    {
        $category = DB::table('categories')->where('symbol', $categorySymbol)->first()
            ?? (object) ['id' => DB::table('categories')->insertGetId(['name' => $categorySymbol.' category', 'symbol' => $categorySymbol])];

        $id = DB::table('items')->insertGetId($attributes + [
            'category_id' => $category->id, 'name' => $name, 'unit' => 'pcs', 'article_number' => 'A-'.uniqid(),
        ]);
        if ($stock !== null) {
            DB::table('vessel_items')->insert(['vessel_id' => $this->vesselId, 'item_id' => $id, 'stock_qty' => $stock]);
        }

        return \App\Item::find($id);
    }

    protected function makeComponent(array $attributes = []): PmsComponent
    {
        return PmsComponent::create($attributes + [
            'vessel_id' => $this->vesselId,
            'location_id' => PmsLocation::value('id'),
            'name' => 'MAIN ENGINE '.uniqid(),
            'category_id' => PmsComponentCategory::value('id'),
            'condition_id' => PmsCondition::defaultId(),
        ]);
    }

    protected function reading(PmsComponent $component, string $date, float $value): void
    {
        PmsCounterReading::create([
            'component_id' => $component->id, 'reading_date' => $date, 'value' => $value, 'entered_by' => $this->userId,
        ]);
    }

    /** @param array $intervals [[every, period], ...] */
    protected function makeDefinition(PmsComponent $component, array $attributes = [], array $intervals = [], array $checklist = []): PmsJobDefinition
    {
        $definition = PmsJobDefinition::create($attributes + [
            'vessel_id' => $this->vesselId,
            'component_id' => $component->id,
            'name' => 'Overhaul',
            'job_type_id' => PmsJobType::value('id'),
            'rank_id' => Rank::value('id'),
        ]);

        foreach ($intervals as [$every, $period]) {
            $definition->intervals()->create(['every' => $every, 'period' => $period]);
        }
        foreach ($checklist as $i => $text) {
            $definition->checklistItems()->create(['position' => $i + 1, 'text' => $text]);
        }

        return $definition->fresh();
    }

    protected function completionData(array $overrides = []): array
    {
        return $overrides + [
            'done_by_rank_id' => Rank::value('id'),
            'condition_after_id' => PmsCondition::where('name', 'Good')->value('id'),
        ];
    }
}
