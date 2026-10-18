<?php

namespace Database\Seeders;

use App\Manufacturer;
use App\PmsComponent;
use App\PmsComponentCategory;
use App\PmsCondition;
use App\PmsLocation;
use App\Vessel;
use Illuminate\Database\Seeder;

/**
 * A small demo component tree, for trying the PMS screens out. NOT called
 * from DatabaseSeeder - run it on purpose, against a vessel you choose:
 *
 *   PMS_SAMPLE_VESSEL_ID=8 php artisan db:seed --class=PmsSampleComponentSeeder
 *
 * Refuses to touch a vessel that already has components, so it can never
 * mix demo equipment into a real tree. Needs PmsMasterDataSeeder first.
 */
class PmsSampleComponentSeeder extends Seeder
{
    public function run(): void
    {
        $vessel = Vessel::find((int) env('PMS_SAMPLE_VESSEL_ID'));
        if (! $vessel) {
            $this->command->error('Set PMS_SAMPLE_VESSEL_ID to the vessel to seed.');

            return;
        }
        if (PmsComponent::where('vessel_id', $vessel->id)->withTrashed()->exists()) {
            $this->command->warn("{$vessel->name} already has PMS components - nothing seeded.");

            return;
        }

        $loc = fn ($name) => PmsLocation::where('name', $name)->value('id');
        $cat = fn ($name) => PmsComponentCategory::where('name', $name)->firstOrFail();
        $maker = fn ($name) => Manufacturer::where('name', $name)->value('id');
        $condition = PmsCondition::defaultId();

        $make = function (array $attrs, ?PmsComponent $parent = null) use ($vessel, $cat, $condition) {
            $category = $cat($attrs['category']);
            unset($attrs['category']);

            return PmsComponent::create($attrs + [
                'vessel_id' => $vessel->id,
                'parent_id' => $parent?->id,
                'location_id' => $parent?->location_id ?? $attrs['location_id'],
                'category_id' => $category->id,
                'condition_id' => $condition,
                'is_critical' => $category->is_critical,
                'is_class_relevant' => $category->is_class_relevant,
            ]);
        };

        $me = $make(['name' => 'MAIN ENGINE', 'type' => '6S50MC-C', 'category' => 'Engine', 'location_id' => $loc('ENGINE'), 'manufacturer_id' => $maker('MAN Energy Solutions'), 'has_counter' => true, 'sort_order' => 10]);
        $make(['name' => 'Fuel Injection Pump No.1', 'category' => 'Pump', 'sort_order' => 10], $me);
        $make(['name' => 'Turbocharger', 'category' => 'Engine', 'has_counter' => true, 'sort_order' => 20], $me);

        $dg = $make(['name' => 'DIESEL GENERATOR No.1', 'type' => '6DK-20', 'category' => 'Generator', 'location_id' => $loc('ENGINE'), 'manufacturer_id' => $maker('Daihatsu'), 'has_counter' => true, 'sort_order' => 20]);
        $make(['name' => 'Alternator', 'category' => 'Generator', 'sort_order' => 10], $dg);

        $make(['name' => 'MAIN AIR COMPRESSOR No.1', 'category' => 'Compressor', 'location_id' => $loc('ENGINE'), 'manufacturer_id' => $maker('Sperre'), 'has_counter' => true, 'sort_order' => 30]);
        $make(['name' => 'STEERING GEAR', 'category' => 'Steering Gear', 'location_id' => $loc('DECK'), 'manufacturer_id' => $maker('Hatlapa'), 'sort_order' => 10]);
        $make(['name' => 'X-BAND RADAR', 'category' => 'Navigation Equipment', 'location_id' => $loc('RADIO AND NAVIGATION EQUIPMENTS'), 'manufacturer_id' => $maker('Furuno'), 'sort_order' => 10]);

        $this->command->info("Sample PMS components seeded for {$vessel->name}.");
    }
}
