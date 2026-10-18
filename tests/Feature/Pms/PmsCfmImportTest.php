<?php

namespace Tests\Feature\Pms;

use App\Manufacturer;
use App\PmsComponent;
use App\PmsComponentCategory;
use App\PmsJob;
use App\PmsJobDefinition;
use App\PmsJobType;
use App\Services\PmsCfmImporter;
use App\Services\PmsJobScheduler;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Tests\Concerns\BuildsPmsSchema;
use Tests\TestCase;

class PmsCfmImportTest extends TestCase
{
    use BuildsPmsSchema;

    private PmsCfmImporter $importer;

    private array $master;

    private array $vesselData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildPmsSchema();

        // Master rows the minimal trait seed doesn't include but this
        // fixture's definitions need to resolve against.
        PmsComponentCategory::create(['name' => 'Unknown', 'is_critical' => false, 'is_class_relevant' => false]);
        PmsJobType::create(['name' => 'CHECK', 'abbreviation' => 'CH']);
        PmsJobType::create(['name' => 'LUBRICATION', 'abbreviation' => 'LU']);
        PmsJobType::create(['name' => 'MAINTENANCE', 'abbreviation' => 'MA']);
        Manufacturer::create(['name' => 'Sperre']);

        $this->importer = new PmsCfmImporter(new PmsJobScheduler);
        $this->master = json_decode(file_get_contents(base_path('tests/fixtures/cfm/master.json')), true)['master'];
        $this->vesselData = json_decode(file_get_contents(base_path('tests/fixtures/cfm/vessel.json')), true);

        Carbon::setTestNow('2026-01-10');
        CarbonImmutable::setTestNow('2026-01-10');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function runImport(bool $dryRun = false)
    {
        return $this->importer->import($this->vesselId, $this->vesselData, $this->master, $this->userId, $dryRun);
    }

    private function componentNamed(string $name): PmsComponent
    {
        return PmsComponent::where('vessel_id', $this->vesselId)->where('name', $name)->sole();
    }

    // ---------------------------------------------------------------- tree

    public function test_the_tree_is_built_and_a_folder_is_skipped_but_its_children_keep_their_path(): void
    {
        $result = $this->runImport();

        $this->assertSame(3, $result->counts['components_created'], 'the system, the compressor and the main engine - not the folder');
        $engineSystem = $this->componentNamed('ENGINE SYSTEM');
        $compressor = $this->componentNamed('MAIN AIR COMPRESSOR NO.1');

        $this->assertNull($engineSystem->parent_id);
        $this->assertSame($engineSystem->id, $compressor->parent_id, 'parented on the system directly, the folder between them skipped');
        $this->assertSame(0, PmsComponent::where('cfm_guid', 'c-folder')->count(), 'the folder itself was never created');
        $this->assertSame('SN-1', $compressor->serial_no);
        $this->assertTrue($compressor->has_counter);
        $this->assertTrue($compressor->is_critical);
        $this->assertSame(Manufacturer::where('name', 'Sperre')->value('id'), $compressor->manufacturer_id);
        $this->assertSame('2020-01-01', $compressor->installation_date->toDateString());
    }

    public function test_an_existing_component_with_the_same_name_and_parent_is_reused(): void
    {
        $existing = $this->makeComponent(['name' => 'MAIN ENGINE', 'parent_id' => null]);

        $result = $this->runImport();

        $this->assertSame(1, $result->counts['components_reused']);
        $this->assertSame(1, PmsComponent::where('name', 'MAIN ENGINE')->count(), 'no duplicate was created');
        $this->assertSame('c-main-engine', $existing->fresh()->cfm_guid);
    }

    // ---------------------------------------------------------------- fleet definitions

    public function test_a_fleet_definition_is_split_into_one_definition_per_component(): void
    {
        $this->runImport();

        $defs = PmsJobDefinition::where('name', 'FLEET SAFETY CHECK')->get()->keyBy('component_id');
        $this->assertCount(2, $defs);

        $compressorId = $this->componentNamed('MAIN AIR COMPRESSOR NO.1')->id;
        $mainEngineId = $this->componentNamed('MAIN ENGINE')->id;
        $this->assertEqualsCanonicalizing([$compressorId, $mainEngineId], $defs->keys()->all());

        foreach ($defs as $def) {
            $this->assertTrue($def->is_single);
            $this->assertSame('Date', $def->due_type);
            $this->assertSame('2027-01-01', $def->due_date->toDateString());
            $this->assertSame(14, $def->warning_period);
            $this->assertSame('Days', $def->warning_unit);
            $this->assertTrue($def->requires_approval);
            // "COMPANY SPECIFIC-BULK" matches neither by name nor abbreviation - defaulted.
            $this->assertSame(PmsJobType::where('name', 'MAINTENANCE')->value('id'), $def->job_type_id);
        }

        $overdueJob = PmsJob::where('job_definition_id', $defs[$mainEngineId]->id)->sole();
        $this->assertSame(PmsJob::OVERDUE, $overdueJob->status, 'due 2025-12-01, today is 2026-01-10');
        $plannedJob = PmsJob::where('job_definition_id', $defs[$compressorId]->id)->sole();
        $this->assertSame(PmsJob::PLANNED, $plannedJob->status);
    }

    // ---------------------------------------------------------------- excluded definitions

    public function test_the_known_junk_fleet_definition_is_never_imported(): void
    {
        $result = $this->runImport();

        $this->assertSame(0, PmsJobDefinition::where('name', 'Irene Flowers')->count());
        $this->assertSame(0, PmsJob::where('cfm_guid', 'job-junk')->count());
        $this->assertSame(1, $result->counts['jobs_excluded']);
        $this->assertGreaterThanOrEqual(1, $result->counts['definitions_excluded']);

        $entry = collect($result->fleetDefinitions)->firstWhere('guid', '6bab916b-eec5-4181-a916-bae996010691');
        $this->assertNotNull($entry, 'still reported, for review, even though excluded');
        $this->assertSame('Irene Flowers', $entry['name']);
        $this->assertSame(1, $entry['job_count']);
        $this->assertTrue($entry['excluded']);

        // The other fleet definition is unaffected.
        $other = collect($result->fleetDefinitions)->firstWhere('name', 'FLEET SAFETY CHECK');
        $this->assertFalse($other['excluded']);
    }

    public function test_an_extra_definition_can_be_excluded_via_the_option(): void
    {
        $result = $this->importer->import($this->vesselId, $this->vesselData, $this->master, $this->userId, false, ['def-rh']);

        $this->assertSame(0, PmsJobDefinition::where('name', 'COMPRESSOR OVERHAUL')->count());
        $this->assertSame(0, PmsJob::where('cfm_guid', 'job-rh')->count());
        // job-rh plus the always-excluded job-junk.
        $this->assertSame(2, $result->counts['jobs_excluded']);

        // Everything else still imports normally.
        $this->assertSame(1, PmsJobDefinition::where('name', 'MAIN ENGINE CHECK')->count());
    }

    // ---------------------------------------------------------------- intervals

    public function test_an_rh_interval_is_imported(): void
    {
        $this->runImport();

        $definition = PmsJobDefinition::where('name', 'COMPRESSOR OVERHAUL')->sole();
        $interval = $definition->intervals->sole();
        $this->assertSame('RunningHour', $interval->period);
        $this->assertSame(500, $interval->every);
    }

    public function test_two_intervals_are_both_stored(): void
    {
        $this->runImport();

        $definition = PmsJobDefinition::where('name', 'MAIN ENGINE CHECK')->sole();
        $periods = $definition->intervals->pluck('every', 'period')->all();
        $this->assertSame(['Month' => 6, 'RunningHour' => 2000], $periods);
        $this->assertSame('Check oil level', $definition->checklistItems->sole()->text);
        $this->assertSame(2, $definition->hours_needed);
        $this->assertSame(30, $definition->minutes_needed);
        $this->assertSame(1, $definition->staff_needed);
        // "LUBRICATE" (abbreviation LU) matches our "LUBRICATION" (also LU) only by abbreviation.
        $this->assertSame(PmsJobType::where('name', 'LUBRICATION')->value('id'), $definition->job_type_id);
        // RankID 99 isn't in the master ranks list at all - defaulted by the component's location (ENGINE).
        $this->assertSame(\App\Rank::where('code', 'CE')->value('id'), $definition->rank_id);
    }

    // ---------------------------------------------------------------- jobs kept exactly

    public function test_cfm_due_date_is_kept_exactly_not_recalculated(): void
    {
        $this->runImport();

        $job = PmsJob::where('cfm_guid', 'job-rh')->sole();
        $this->assertSame('2027-06-15', $job->due_date->toDateString());
        $this->assertSame('2027-05-01', $job->original_due_date->toDateString());
        $this->assertSame('2027-06-08', $job->warning_date->toDateString());
        $this->assertSame(1500.0, (float) $job->due_running_hours);
        $this->assertSame(PmsJob::PLANNED, $job->status);
    }

    // ---------------------------------------------------------------- last done

    public function test_last_done_creates_a_completed_history_job_alongside_the_open_one(): void
    {
        $this->runImport();

        $definition = PmsJobDefinition::where('name', 'MAIN ENGINE CHECK')->sole();
        $jobs = PmsJob::where('job_definition_id', $definition->id)->get()->keyBy('status');

        $this->assertCount(2, $jobs);
        $history = $jobs[PmsJob::COMPLETED];
        $this->assertSame('2026-01-05', $history->completed_at->toDateString());
        $this->assertSame(400.0, (float) $history->rh_at_completion);
        $this->assertSame('Imported from CFM', $history->remark);
        $this->assertNotSame('job-two', $history->cfm_guid, 'the live job keeps "job-two" - history has its own synthetic guid');

        $live = $jobs[PmsJob::PLANNED];
        $this->assertSame('job-two', $live->cfm_guid);
        $this->assertSame('2026-02-01', $live->due_date->toDateString());
    }

    // ---------------------------------------------------------------- re-run and dry-run

    public function test_rerunning_the_import_adds_nothing_new(): void
    {
        $first = $this->runImport();
        $componentCount = PmsComponent::count();
        $definitionCount = PmsJobDefinition::count();
        $jobCount = PmsJob::count();

        $second = $this->runImport();

        $this->assertSame($componentCount, PmsComponent::count());
        $this->assertSame($definitionCount, PmsJobDefinition::count());
        $this->assertSame($jobCount, PmsJob::count());

        $this->assertSame(0, $second->counts['components_created']);
        $this->assertSame(0, $second->counts['components_reused']);
        $this->assertSame($first->counts['components_created'] + $first->counts['components_reused'], $second->counts['components_already_imported']);

        $this->assertSame(0, $second->counts['definitions_created']);
        $this->assertSame($first->counts['definitions_created'], $second->counts['definitions_already_imported']);

        $this->assertSame(0, $second->counts['jobs_created']);
        $this->assertSame($first->counts['jobs_created'], $second->counts['jobs_already_imported']);

        $this->assertSame(0, $second->counts['last_done_created']);
        $this->assertSame($first->counts['last_done_created'], $second->counts['last_done_already_imported']);

        $this->assertSame(1, $second->counts['readings_skipped'], 'the counter reading already covers that date');
        $this->assertSame(0, $second->counts['readings_created']);
    }

    public function test_dry_run_rolls_back_every_write(): void
    {
        $result = $this->runImport(true);

        $this->assertGreaterThan(0, $result->counts['components_created']);
        $this->assertGreaterThan(0, $result->counts['jobs_created']);
        $this->assertSame(0, PmsComponent::count(), 'nothing was actually saved');
        $this->assertSame(0, PmsJobDefinition::count());
        $this->assertSame(0, PmsJob::count());
    }
}
