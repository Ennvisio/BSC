<?php

namespace Database\Seeders;

use App\Manufacturer;
use App\PmsComponentCategory;
use App\PmsCondition;
use App\PmsJobType;
use App\PmsLocation;
use App\Rank;
use Illuminate\Database\Seeder;

/**
 * The PMS dropdown lists. Safe to re-run: every row is matched on its natural
 * key (code / name / abbreviation) and updated in place, never duplicated,
 * and nothing an admin added by hand is removed.
 */
class PmsMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $ranks = [
            ['MS', 'MASTER', 'Deck'],
            ['CO', 'CHIEF OFFICER', 'Deck'],
            ['2O', 'SECOND OFFICER', 'Deck'],
            ['3O', 'THIRD OFFICER', 'Deck'],
            ['BSN', 'BOSUN', 'Deck'],
            ['PMN', 'PUMPMAN', 'Deck'],
            ['CE', 'CHIEF ENGINEER', 'Engine'],
            ['2E', 'SECOND ENGINEER', 'Engine'],
            ['3E', 'THIRD ENGINEER', 'Engine'],
            ['4E', 'FOURTH ENGINEER', 'Engine'],
            ['FTR', 'FITTER', 'Engine'],
            ['EE', 'ELECTRICAL ENGINEER', 'Electrical'],
            ['ELC', 'ELECTRICIAN', 'Electrical'],
            ['CCK', 'CHIEF COOK', 'Catering'],
            ['2CK', '2ND COOK', 'Catering'],
        ];
        foreach ($ranks as $i => [$code, $name, $department]) {
            Rank::updateOrCreate(['code' => $code], ['name' => $name, 'department' => $department, 'sort_order' => ($i + 1) * 10]);
        }

        // Renamed in place rather than added as a new row, so any component
        // already filed under the old name keeps its location.
        if (! PmsLocation::where('name', 'RADIO AND NAVIGATION EQUIPMENTS')->exists()) {
            PmsLocation::where('name', 'RADIO & NAV')->update(['name' => 'RADIO AND NAVIGATION EQUIPMENTS']);
        }

        foreach (['ENGINE', 'DECK', 'ELECTRICAL', 'SAFETY', 'RADIO AND NAVIGATION EQUIPMENTS', 'CATERING'] as $i => $name) {
            PmsLocation::updateOrCreate(['name' => $name], ['sort_order' => ($i + 1) * 10]);
        }

        // [name, is_critical, is_class_relevant]
        $categories = [
            ['Engine', true, true],
            ['Generator', true, true],
            ['Boiler', true, true],
            ['Steering Gear', true, true],
            ['Compressor', false, true],
            ['Pump', false, false],
            ['Purifier', false, false],
            ['Fire Fighting Equipment', true, true],
            ['Life Saving Appliance', true, true],
            ['Navigation Equipment', true, true],
            ['Diesel Engine', true, true],
            ['Pressure Vessel', true, true],
            ['Incinerator', false, true],
            ['Valve', false, false],
            ['Heat Exchanger', false, false],
            ['Tank', false, false],
            ['Filter / Strainer', false, false],
            ['Fan / Blower', false, false],
            ['Electric Motor', false, false],
            ['Winch', false, false],
            ['Instrument', false, false],
            ['Unknown', false, false],
        ];
        foreach ($categories as [$name, $critical, $class]) {
            PmsComponentCategory::updateOrCreate(['name' => $name], ['is_critical' => $critical, 'is_class_relevant' => $class]);
        }

        $jobTypes = [
            ['CH', 'CHECK'], ['MA', 'MAINTENANCE'], ['O', 'OVERHAUL'], ['IN', 'INSPECTION'], ['TE', 'TEST'],
            ['CA', 'CALIBRATION'], ['CL', 'CLEANING'], ['RE', 'REPLACEMENT'], ['SU', 'SURVEY'],
            ['LU', 'LUBRICATION'], ['RN', 'RENEWAL'], ['SE', 'SERVICE'],
        ];
        foreach ($jobTypes as [$abbreviation, $name]) {
            PmsJobType::updateOrCreate(['abbreviation' => $abbreviation], ['name' => $name]);
        }

        // A new component's condition is genuinely unknown until someone
        // inspects it, so "Not Classified" is the default rather than "Good".
        foreach (['Good' => false, 'Fair' => false, 'Bad' => false, 'Not Classified' => true] as $name => $default) {
            PmsCondition::updateOrCreate(['name' => $name], ['is_default' => $default]);
        }
        PmsCondition::where('name', '!=', 'Not Classified')->where('is_default', true)->update(['is_default' => false]);

        foreach ([
            'Alfa Laval', 'Daihatsu', 'Furuno', 'Hatlapa', 'Hyundai', 'JRC', 'Kongsberg',
            'MAN Energy Solutions', 'Mitsubishi', 'Sperre', 'Sperry Marine', 'Wärtsilä', 'Yanmar',
        ] as $name) {
            Manufacturer::firstOrCreate(['name' => $name]);
        }
    }
}
