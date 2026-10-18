<?php

namespace Tests\Feature\Pms;

use App\Exports\PmsComponentTemplateExport;
use App\Manufacturer;
use App\PmsComponent;
use App\PmsComponentCategory;
use App\PmsCondition;
use App\PmsCounterReading;
use App\PmsLocation;
use App\Services\PmsComponentImporter;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\Concerns\BuildsPmsSchema;
use Tests\TestCase;

class PmsComponentImportTest extends TestCase
{
    use BuildsPmsSchema;

    private PmsComponentImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildPmsSchema();
        $this->importer = new PmsComponentImporter;
        Carbon::setTestNow('2026-01-10');
        CarbonImmutable::setTestNow('2026-01-10');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function row(string $name, ?string $parent = null, array $extra = []): array
    {
        return $extra + ['location' => 'ENGINE', 'parent_path' => $parent, 'name' => $name];
    }

    private function findComponent(string $name): PmsComponent
    {
        return PmsComponent::where('vessel_id', $this->vesselId)->where('name', $name)->sole();
    }

    // ---------------------------------------------------------------- parents

    public function test_a_parent_can_be_anywhere_in_the_same_file(): void
    {
        $result = $this->importer->import($this->vesselId, [
            2 => $this->row('MAIN AIR COMPRESSOR NO.1', 'COMPRESSED AIR SYSTEM'),
            3 => $this->row('COOLER', 'COMPRESSED AIR SYSTEM > MAIN AIR COMPRESSOR NO.1'),
            4 => $this->row('COMPRESSED AIR SYSTEM'),
        ], $this->userId);

        $this->assertSame(3, $result['created']);
        $system = $this->findComponent('COMPRESSED AIR SYSTEM');
        $compressor = $this->findComponent('MAIN AIR COMPRESSOR NO.1');
        $this->assertNull($system->parent_id);
        $this->assertSame($system->id, $compressor->parent_id, 'the parent comes later in the file');
        $this->assertSame($compressor->id, $this->findComponent('COOLER')->parent_id);
        $this->assertSame([4, 2, 3], [$system->sort_order, $compressor->sort_order, $this->findComponent('COOLER')->sort_order], 'file row order');
        $this->assertSame(PmsLocation::where('name', 'ENGINE')->value('id'), $compressor->location_id);
    }

    public function test_a_parent_already_in_the_vessel_tree_is_used(): void
    {
        $system = $this->makeComponent(['name' => 'Compressed Air System']);

        $this->importer->import($this->vesselId, [2 => $this->row('MAIN AIR COMPRESSOR NO.1', '  compressed   AIR system ')], $this->userId);

        $this->assertSame($system->id, $this->findComponent('MAIN AIR COMPRESSOR NO.1')->parent_id, 'matched ignoring case and spacing');
    }

    public function test_unknown_location_and_missing_parent_are_errors_and_nothing_is_imported(): void
    {
        $rows = [
            2 => $this->row('GOOD SYSTEM'),
            3 => $this->row('BOILER', null, ['location' => 'BOILER ROOM']),
            4 => $this->row('PUMP NO.1', 'NO SUCH SYSTEM'),
        ];

        $analysis = $this->importer->analyse($this->vesselId, $rows);
        $this->assertSame(['create' => 1, 'skip' => 0, 'error' => 2, 'updated' => 0], $analysis['counts']);
        $this->assertStringContainsString('Unknown Location "BOILER ROOM"', $analysis['rows'][3]['errors'][0]);
        $this->assertStringContainsString('Parent "NO SUCH SYSTEM" not found', $analysis['rows'][4]['errors'][0]);

        try {
            $this->importer->import($this->vesselId, $rows, $this->userId);
            $this->fail('An import with errors must be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('2 row(s) have errors', $e->errors()['file'][0]);
        }
        $this->assertSame(0, PmsComponent::count(), 'not even the valid row is saved');
    }

    public function test_a_parent_in_another_location_does_not_count(): void
    {
        $deck = PmsLocation::create(['name' => 'DECK', 'sort_order' => 20]);
        $this->makeComponent(['name' => 'WINCHES', 'location_id' => $deck->id]);

        $analysis = $this->importer->analyse($this->vesselId, [2 => $this->row('MOORING WINCH NO.1', 'WINCHES')]);

        $this->assertSame(1, $analysis['counts']['error']);
    }

    // ---------------------------------------------------------------- duplicates

    public function test_duplicates_in_the_file_or_the_tree_are_skipped_and_reimport_creates_nothing(): void
    {
        $system = $this->makeComponent(['name' => 'COMPRESSED AIR SYSTEM']);
        $rows = [
            2 => $this->row('compressed air system'),
            3 => $this->row('MAIN AIR COMPRESSOR NO.1', 'COMPRESSED AIR SYSTEM'),
            4 => $this->row('Main Air Compressor No.1', 'COMPRESSED AIR SYSTEM'),
        ];

        $analysis = $this->importer->analyse($this->vesselId, $rows);
        $this->assertSame(['create' => 1, 'skip' => 2, 'error' => 0, 'updated' => 0], $analysis['counts']);
        $this->assertSame('Already in this vessel\'s tree - skipped.', $analysis['rows'][2]['warnings'][0]);
        $this->assertSame('Same component as row 3 - skipped.', $analysis['rows'][4]['warnings'][0]);

        $first = $this->importer->import($this->vesselId, $rows, $this->userId);
        $again = $this->importer->import($this->vesselId, $rows, $this->userId);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $again['created']);
        $this->assertSame(3, $again['skipped']);
        $this->assertSame(2, PmsComponent::count());
        $this->assertSame($system->id, $this->findComponent('MAIN AIR COMPRESSOR NO.1')->parent_id);
    }

    public function test_a_duplicate_row_fills_empty_fields_on_the_existing_component_but_never_overwrites(): void
    {
        Manufacturer::create(['name' => 'Sperre']);
        PmsComponentCategory::create(['name' => 'Compressor']);

        $existing = $this->makeComponent([
            'name' => 'MAIN AIR COMPRESSOR NO.1',
            'category_id' => PmsComponentCategory::where('name', 'Compressor')->value('id'),
            'type' => null,
            'serial_no' => null,
            'class_code' => null,
            'manufacturer_id' => null,
        ]);

        $rows = [2 => $this->row('Main Air Compressor No.1', null, [
            'category' => 'Engine', 'manufacturer' => 'Sperre', 'type' => 'HV2/200', 'serial_no' => 'SN-1', 'class_code' => 'CC-1',
        ])];

        $analysis = $this->importer->analyse($this->vesselId, $rows);
        $this->assertSame(['create' => 0, 'skip' => 1, 'error' => 0, 'updated' => 1], $analysis['counts']);
        $this->assertStringContainsString('Will also fill in its empty', $analysis['rows'][2]['warnings'][1]);
        $this->assertStringContainsString('Manufacturer', $analysis['rows'][2]['warnings'][1]);
        $this->assertStringContainsString('Model / Type', $analysis['rows'][2]['warnings'][1]);
        $this->assertStringContainsString('Serial No', $analysis['rows'][2]['warnings'][1]);
        $this->assertStringContainsString('Class Code', $analysis['rows'][2]['warnings'][1]);
        $this->assertStringNotContainsString('Category', $analysis['rows'][2]['warnings'][1], 'category was already set - not in the fill list');

        $result = $this->importer->import($this->vesselId, $rows, $this->userId);

        $this->assertSame(['created' => 0, 'skipped' => 1, 'updated' => 1, 'readings' => 0], $result);
        $this->assertSame(1, PmsComponent::count(), 'no second row was created');
        $existing->refresh();
        $this->assertSame(PmsComponentCategory::where('name', 'Compressor')->value('id'), $existing->category_id, 'the existing category was kept, not replaced by "Engine"');
        $this->assertSame(Manufacturer::where('name', 'Sperre')->value('id'), $existing->manufacturer_id);
        $this->assertSame('HV2/200', $existing->type);
        $this->assertSame('SN-1', $existing->serial_no);
        $this->assertSame('CC-1', $existing->class_code);
    }

    public function test_purifier_slash_separator_matches_the_purifier_category(): void
    {
        $purifier = PmsComponentCategory::create(['name' => 'Purifier']);

        $this->importer->import($this->vesselId, [2 => $this->row('L.O. PURIFIER', null, ['category' => 'Purifier / Separator'])], $this->userId);

        $this->assertSame($purifier->id, $this->findComponent('L.O. PURIFIER')->category_id);
    }

    // ---------------------------------------------------------------- running hours

    public function test_a_counter_with_current_rh_gets_a_reading(): void
    {
        $result = $this->importer->import($this->vesselId, [
            2 => $this->row('GENERATOR NO.1', null, ['has_counter' => 'Y', 'current_rh' => 12500.4, 'current_rh_date' => '05/01/2026']),
            3 => $this->row('GENERATOR NO.2', null, ['has_counter' => 'yes', 'current_rh' => '800']),
            4 => $this->row('GENERATOR NO.3', null, ['has_counter' => 'Y']),
        ], $this->userId);

        $this->assertSame(2, $result['readings']);
        $one = PmsCounterReading::where('component_id', $this->findComponent('GENERATOR NO.1')->id)->sole();
        $this->assertSame('2026-01-05', $one->reading_date->toDateString());
        $this->assertSame(12500.4, (float) $one->value);
        $this->assertSame($this->userId, (int) $one->entered_by);
        $two = PmsCounterReading::where('component_id', $this->findComponent('GENERATOR NO.2')->id)->sole();
        $this->assertSame('2026-01-10', $two->reading_date->toDateString(), 'no date given: today');
        $this->assertTrue($this->findComponent('GENERATOR NO.3')->has_counter);
        $this->assertSame(0, PmsCounterReading::where('component_id', $this->findComponent('GENERATOR NO.3')->id)->count());
    }

    public function test_current_rh_without_a_counter_or_bad_values_are_errors(): void
    {
        $analysis = $this->importer->analyse($this->vesselId, [
            2 => $this->row('PUMP', null, ['has_counter' => 'N', 'current_rh' => 100]),
            3 => $this->row('FAN', null, ['has_counter' => 'maybe']),
            4 => $this->row('MOTOR', null, ['installation_date' => '31/02/2025']),
            5 => $this->row('VALVE', null, ['has_counter' => 'Y', 'current_rh' => 10, 'current_rh_date' => '01/02/2026']),
        ]);

        $this->assertSame('Current RH is given but Has Counter isn\'t Y.', $analysis['rows'][2]['errors'][0]);
        $this->assertStringContainsString('must be Y or N', $analysis['rows'][3]['errors'][0]);
        $this->assertStringContainsString('isn\'t a date', $analysis['rows'][4]['errors'][0]);
        $this->assertStringContainsString('is in the future', $analysis['rows'][5]['errors'][0]);
    }

    // ---------------------------------------------------------------- master data

    public function test_master_data_is_matched_by_name_and_unknowns_are_warnings(): void
    {
        $maker = Manufacturer::create(['name' => 'Sperre']);

        $this->importer->import($this->vesselId, [
            2 => $this->row('A', null, ['category' => ' engine ', 'manufacturer' => 'SPERRE', 'condition' => 'good', 'type' => 'HV2/200', 'installation_date' => 45658]),
            3 => $this->row('B', null, ['category' => 'Heat Exchanger', 'manufacturer' => 'Nobody', 'is_critical' => 'Y']),
        ], $this->userId);

        $a = $this->findComponent('A');
        $this->assertNotNull($a->category_id);
        $this->assertSame($maker->id, $a->manufacturer_id);
        $this->assertSame(PmsCondition::where('name', 'Good')->value('id'), $a->condition_id);
        $this->assertSame('HV2/200', $a->type);
        $this->assertSame('2025-01-01', $a->installation_date->toDateString(), 'an Excel date number');
        $this->assertTrue($a->is_critical, 'blank Critical follows the category default');

        $b = $this->findComponent('B');
        $this->assertNull($b->category_id, 'unknown category left empty');
        $this->assertNull($b->manufacturer_id);
        $this->assertSame(PmsCondition::where('name', 'Not Classified')->value('id'), $b->condition_id, 'empty condition');
        $this->assertTrue($b->is_critical);

        $warnings = $this->importer->analyse($this->vesselId, [9 => $this->row('C', null, ['category' => 'Heat Exchanger', 'manufacturer' => 'Nobody'])])['rows'][9]['warnings'];
        $this->assertCount(2, $warnings);
    }

    // ---------------------------------------------------------------- all or nothing

    public function test_a_failure_part_way_through_rolls_everything_back(): void
    {
        $created = 0;
        PmsComponent::created(function () use (&$created) {
            if (++$created === 3) {
                throw new RuntimeException('database went away');
            }
        });

        try {
            $this->importer->import($this->vesselId, [
                2 => $this->row('SYSTEM'),
                3 => $this->row('UNIT NO.1', 'SYSTEM', ['has_counter' => 'Y', 'current_rh' => 50]),
                4 => $this->row('UNIT NO.2', 'SYSTEM'),
            ], $this->userId);
            $this->fail('Expected the simulated failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('database went away', $e->getMessage());
        }

        $this->assertSame(0, PmsComponent::count());
        $this->assertSame(0, PmsCounterReading::count());
    }

    // ---------------------------------------------------------------- the workbook

    public function test_only_the_components_sheet_is_read_with_loose_headers(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('Instructions')->fromArray([['Read me'], ['ENGINE', 'not data']]);
        $sheet = $book->createSheet()->setTitle('Components');
        $sheet->fromArray([
            ['Vessel Code *', 'Location *', 'Parent Path', 'Component Name *', 'Has Counter', 'Critical', 'Current RH', 'Current RH Date'],
            ['PRG', 'ENGINE', null, 'COMPRESSED AIR SYSTEM', 'N', 'N', null, null],
            [null, null, null, null, null, null, null, null],
            ['PRG', 'ENGINE', 'COMPRESSED AIR SYSTEM', 'MAIN AIR COMPRESSOR NO.1', 'Y', 'N', 1200, 46027],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'pms').'.xlsx';
        (new Xlsx($book))->save($path);

        $rows = $this->importer->read($path);

        $this->assertSame([2, 4], array_keys($rows), 'Excel row numbers, blank rows dropped');
        $this->assertSame('MAIN AIR COMPRESSOR NO.1', $rows[4]['name']);
        $this->assertSame('Y', $rows[4]['has_counter']);
        $result = $this->importer->import($this->vesselId, $rows, $this->userId);
        $this->assertSame(['created' => 2, 'skipped' => 0, 'updated' => 0, 'readings' => 1], $result);
        $this->assertSame('2026-01-05', PmsCounterReading::sole()->reading_date->toDateString());

        $book->removeSheetByIndex(1);
        (new Xlsx($book))->save($path);
        $this->expectException(ValidationException::class);
        $this->importer->read($path);
    }

    public function test_the_template_has_the_headers_and_dropdowns(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, Excel::raw(new PmsComponentTemplateExport, ExcelType::XLSX));
        $book = IOFactory::load($path);

        $this->assertSame(['Components', 'Lists'], $book->getSheetNames());
        $components = $book->getSheetByName('Components');
        $this->assertSame('Location *', $components->getCell('B1')->getValue());
        $this->assertSame('Remark', $components->getCell('Q1')->getValue());
        $this->assertSame('=Lists!$A$2:$A$2', '='.ltrim($components->getCell('B2')->getDataValidation()->getFormula1(), '='));
        $this->assertSame('ENGINE', $book->getSheetByName('Lists')->getCell('A2')->getValue());
        $this->assertSame(['Y', 'N'], [$book->getSheetByName('Lists')->getCell('E2')->getValue(), $book->getSheetByName('Lists')->getCell('E3')->getValue()]);

        // ...and an untouched template imports as nothing.
        $this->assertSame([], $this->importer->read($path));
    }
}
