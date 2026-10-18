<?php

namespace Database\Seeders;

use App\Manufacturer;
use App\PmsComponent;
use App\PmsComponentCategory;
use App\PmsCondition;
use App\PmsCounterReading;
use App\PmsLocation;
use App\User;
use App\Vessel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * MT Banglar Agradoot's engine-room tree (compressed air, supply, MARPOL,
 * fresh water), as it's laid out in the ship's previous PMS. Not called from
 * DatabaseSeeder. Safe to re-run: components are matched by name under the
 * same parent, and meter readings by date.
 *
 *   php artisan db:seed --class=PmsAgradootComponentSeeder
 */
class PmsAgradootComponentSeeder extends Seeder
{
    private const TREE = [
        ['COMPRESSED AIR SYSTEM', 'Compressor', [
            ['MAIN AIR COMPRESSORS', 'Compressor', [
                ['MAIN AIR COMPRESSOR NO.1', 'Compressor', self::COMPRESSOR_PARTS, ['counter' => [6120, 6480], 'maker' => 'Sperre', 'type' => 'HV2/200']],
                ['MAIN AIR COMPRESSOR NO.2', 'Compressor', self::COMPRESSOR_PARTS, ['counter' => [5870, 6210], 'maker' => 'Sperre', 'type' => 'HV2/200']],
            ]],
            ['EMERGENCY AIR COMPRESSOR', 'Compressor', [], ['counter' => [412, 418], 'maker' => 'Sperre', 'type' => 'HL2/77']],
            ['SERVICE AIR COMPRESSORS', 'Compressor', [
                ['SERVICE AIR COMPRESSOR NO.1', 'Compressor', []],
                ['SERVICE AIR COMPRESSOR NO.2', 'Compressor', []],
            ]],
            ['MAIN AIR RESERVOIRS', 'Compressor', [
                ['MAIN AIR RESERVOIR NO.1', 'Compressor', []],
                ['MAIN AIR RESERVOIR NO.2', 'Compressor', []],
            ]],
            ['AUXILLARY AIR RESERVOIR', 'Compressor', []],
            ['WORKING AIR RESERVOIR', 'Compressor', []],
            ['CONTROL AIR DRYER', 'Compressor', []],
        ]],
        ['SUPPLY SYSTEM', 'Pump', []],
        ['MARPOL EQUIPMENTS', 'Unknown', [
            ['OILY WATER SEPARATOR', 'Purifier', [], ['maker' => 'Alfa Laval']],
            ['INCINERATOR', 'Unknown', []],
            ['SEWAGE TREATMENT PLANT', 'Unknown', []],
            ['OIL CONTENT METER (15 PPM)', 'Unknown', []],
        ]],
        ['FRESH WATER GENERATION SYSTEM', 'Unknown', [
            ['FRESH WATER GENERATOR', 'Unknown', [], ['maker' => 'Alfa Laval', 'counter' => [9300, 9620]]],
            ['FRESH WATER HYDROPHORE UNIT', 'Pump', []],
        ]],
    ];

    private const COMPRESSOR_PARTS = [
        ['ELECTRIC MOTOR', 'Compressor', []],
        ['CYLINDER HEAD & VALVES', 'Compressor', []],
        ['INTERCOOLER / AFTERCOOLER', 'Compressor', []],
    ];

    private Vessel $vessel;

    private int $locationId;

    private ?int $conditionId;

    private ?int $enteredBy;

    private int $created = 0;

    public function run(): void
    {
        $this->vessel = Vessel::where('name', 'like', '%AGRADOOT%')->firstOrFail();
        $this->locationId = PmsLocation::where('name', 'ENGINE')->valueOrFail('id');
        $this->conditionId = PmsCondition::defaultId();
        // Readings need someone to have entered them: the ship's chief
        // engineer if it has one, else super-admin.
        $this->enteredBy = DB::table('roles')->where('vessel_id', $this->vessel->id)->where('role', 'chief-engineer')->value('user_id')
            ?? DB::table('roles')->where('role', 'super-admin')->value('user_id')
            ?? User::min('id');

        DB::transaction(function () {
            foreach (self::TREE as $i => $node) {
                $this->plant($node, null, ($i + 1) * 10);
            }
        });

        $total = PmsComponent::where('vessel_id', $this->vessel->id)->count();
        $this->command->info("{$this->vessel->name}: {$this->created} components added ({$total} in its tree).");
    }

    private function plant(array $node, ?PmsComponent $parent, int $sort): void
    {
        [$name, $categoryName, $children] = $node;
        $extra = $node[3] ?? [];
        $category = PmsComponentCategory::where('name', $categoryName)->firstOrFail();

        $component = PmsComponent::firstOrCreate(
            ['vessel_id' => $this->vessel->id, 'parent_id' => $parent?->id, 'name' => $name],
            [
                'location_id' => $this->locationId,
                'category_id' => $category->id,
                'condition_id' => $this->conditionId,
                'type' => $extra['type'] ?? null,
                'manufacturer_id' => isset($extra['maker']) ? Manufacturer::where('name', $extra['maker'])->value('id') : null,
                'has_counter' => isset($extra['counter']),
                'is_critical' => $category->is_critical,
                'is_class_relevant' => $category->is_class_relevant,
                'sort_order' => $sort,
            ]
        );
        $this->created += $component->wasRecentlyCreated ? 1 : 0;

        // Two readings 30 days apart give the scheduler an hours-per-day
        // average to estimate running-hour due dates from.
        if (isset($extra['counter'])) {
            foreach ([30 => $extra['counter'][0], 0 => $extra['counter'][1]] as $daysAgo => $value) {
                PmsCounterReading::firstOrCreate(
                    ['component_id' => $component->id, 'reading_date' => now()->subDays($daysAgo)->toDateString()],
                    ['value' => $value, 'entered_by' => $this->enteredBy]
                );
            }
        }

        foreach ($children as $i => $child) {
            $this->plant($child, $component, ($i + 1) * 10);
        }
    }
}
