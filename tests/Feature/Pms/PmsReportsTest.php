<?php

namespace Tests\Feature\Pms;

use App\PmsJob;
use App\PmsJobDefinition;
use App\Services\PmsReports;
use App\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\BuildsPmsSchema;
use Tests\TestCase;

class PmsReportsTest extends TestCase
{
    use BuildsPmsSchema;

    private int $otherVesselId;

    private User $shore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildPmsSchema();
        Carbon::setTestNow('2026-01-10');
        CarbonImmutable::setTestNow('2026-01-10');

        $row = collect((array) DB::table('vessels')->where('id', $this->vesselId)->first())->except('id')->all();
        $this->otherVesselId = DB::table('vessels')->insertGetId(['name' => 'MT OTHER VESSEL'] + $row);

        $this->shore = User::create(['name' => 'GM', 'email' => 'gm@test.local', 'password' => 'x']);
        DB::table('roles')->insert(['user_id' => $this->shore->id, 'role' => 'gm-srd', 'user_type' => 'srd']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- helpers

    private function definition(?int $vesselId = null, string $name = 'Overhaul', array $attributes = []): PmsJobDefinition
    {
        $component = $this->makeComponent(['vessel_id' => $vesselId ?? $this->vesselId, 'name' => $name.' COMPONENT']);

        return $this->makeDefinition($component, ['name' => $name, 'vessel_id' => $vesselId ?? $this->vesselId] + $attributes);
    }

    private function job(PmsJobDefinition $definition, array $attributes): PmsJob
    {
        return PmsJob::create($attributes + [
            'vessel_id' => $definition->vessel_id,
            'component_id' => $definition->component_id,
            'job_definition_id' => $definition->id,
        ]);
    }

    private function overdueFilters(array $overrides = []): array
    {
        return $overrides + ['vessel_ids' => null, 'location_id' => null, 'rank_id' => null, 'job_type_id' => null, 'critical' => false, 'days' => null];
    }

    /** The data rows of a downloaded report, as arrays of cell values, found by their heading row. */
    private function exportRows($response, array $columns): array
    {
        $response->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet()->toArray(null, true, false);

        $header = array_search(array_values($columns), array_map(fn ($r) => array_slice($r, 0, count($columns)), $sheet), true);
        $this->assertNotFalse($header, 'heading row found');

        return array_map(fn ($r) => array_combine(array_keys($columns), array_slice($r, 0, count($columns))), array_slice($sheet, $header + 1));
    }

    // ---------------------------------------------------------------- access

    public function test_a_ship_user_only_sees_their_own_vessel(): void
    {
        $this->job($this->definition(null, 'OWN JOB'), ['status' => PmsJob::OVERDUE, 'due_date' => '2026-01-01']);
        $this->job($this->definition($this->otherVesselId, 'OTHER JOB'), ['status' => PmsJob::OVERDUE, 'due_date' => '2026-01-01']);
        $officer = User::find($this->userId); // chief officer of $this->vesselId

        // Asking for the other vessel - or the fleet - still gives only their own.
        foreach (['', '&vessel_id='.$this->otherVesselId] as $extra) {
            $rows = $this->exportRows($this->actingAs($officer)->get('/pms/reports/overdue?export=1'.$extra), PmsReports::OVERDUE_COLUMNS);
            $this->assertSame(['OWN JOB'], array_column($rows, 'job'));
        }

        $overview = $this->exportRows($this->actingAs($officer)->get('/pms/reports/overview?export=1&vessel_id='.$this->otherVesselId), PmsReports::OVERVIEW_COLUMNS);
        $this->assertSame(['MT TEST VESSEL'], array_column($overview, 'vessel'), 'one vessel, and no fleet totals row');
    }

    public function test_a_shore_user_sees_the_whole_fleet_or_one_vessel(): void
    {
        $this->job($this->definition(null, 'OWN JOB'), ['status' => PmsJob::OVERDUE, 'due_date' => '2026-01-01']);
        $this->job($this->definition($this->otherVesselId, 'OTHER JOB'), ['status' => PmsJob::OVERDUE, 'due_date' => '2025-12-01']);

        $fleet = $this->exportRows($this->actingAs($this->shore)->get('/pms/reports/overdue?export=1'), PmsReports::OVERDUE_COLUMNS);
        $this->assertSame(['OTHER JOB', 'OWN JOB'], array_column($fleet, 'job'), 'most overdue first');

        $one = $this->exportRows($this->actingAs($this->shore)->get('/pms/reports/overdue?export=1&vessel_id='.$this->otherVesselId), PmsReports::OVERDUE_COLUMNS);
        $this->assertSame(['OTHER JOB'], array_column($one, 'job'));

        $overview = $this->exportRows($this->actingAs($this->shore)->get('/pms/reports/overview?export=1'), PmsReports::OVERVIEW_COLUMNS);
        $this->assertSame(['MT OTHER VESSEL', 'MT TEST VESSEL', 'Fleet total'], array_column($overview, 'vessel'));
        $this->assertSame(2, (int) $overview[2]['overdue']);
        $this->assertSame('100%', $overview[2]['pct_overdue']);
    }

    // ---------------------------------------------------------------- overdue

    public function test_days_overdue_is_calculated_from_the_due_date_and_drives_sort_filters_and_bands(): void
    {
        $this->job($this->definition(null, 'ONE DAY'), ['status' => PmsJob::OVERDUE, 'due_date' => '2026-01-09']);
        $this->job($this->definition(null, 'THIRTY DAYS'), ['status' => PmsJob::OVERDUE, 'due_date' => '2025-12-11']);
        $this->job($this->definition(null, 'FORTY DAYS'), ['status' => PmsJob::OVERDUE, 'due_date' => '2025-12-01']);
        $this->job($this->definition(null, 'LONG OVERDUE'), ['status' => PmsJob::OVERDUE, 'due_date' => '2025-09-01']);
        $this->job($this->definition(null, 'RH ONLY'), ['status' => PmsJob::OVERDUE, 'due_date' => null, 'due_running_hours' => 1500]);
        $this->job($this->definition(null, 'NOT OVERDUE'), ['status' => PmsJob::DUE_SOON, 'due_date' => '2026-01-15']);
        $reports = app(PmsReports::class);

        $rows = $reports->overdueRows($reports->overdueQuery($this->overdueFilters())->get());
        $this->assertSame(['LONG OVERDUE', 'FORTY DAYS', 'THIRTY DAYS', 'ONE DAY', 'RH ONLY'], array_column($rows, 'job'), 'highest first, no due date last');
        $this->assertSame([131, 40, 30, 1, null], array_column($rows, 'days'));

        $over30 = $reports->overdueRows($reports->overdueQuery($this->overdueFilters(['days' => '30']))->get());
        $this->assertSame(['LONG OVERDUE', 'FORTY DAYS'], array_column($over30, 'job'), '30 days exactly is not "> 30"');
        $over90 = $reports->overdueRows($reports->overdueQuery($this->overdueFilters(['days' => '90']))->get());
        $this->assertSame(['LONG OVERDUE'], array_column($over90, 'job'));

        $summary = $reports->overdueSummary($this->overdueFilters());
        $this->assertSame(5, $summary['total']);
        $this->assertSame(['0–30 days' => 2, '31–90 days' => 1, '90+ days' => 1, 'Running hours only' => 1], $summary['bands']);
        $this->assertSame([['rank' => 'CE', 'count' => 5]], $summary['by_rank']->all());
    }

    public function test_critical_only_counts_a_critical_job_or_component(): void
    {
        $this->job($this->definition(null, 'CRITICAL JOB', ['is_critical' => true]), ['status' => PmsJob::OVERDUE, 'due_date' => '2026-01-01']);
        $plain = $this->definition(null, 'PLAIN JOB');
        $this->job($plain, ['status' => PmsJob::OVERDUE, 'due_date' => '2026-01-01']);
        $onCritical = $this->definition(null, 'ON CRITICAL COMPONENT');
        $onCritical->component->update(['is_critical' => true]);
        $this->job($onCritical, ['status' => PmsJob::OVERDUE, 'due_date' => '2026-01-02']);
        $reports = app(PmsReports::class);

        $rows = $reports->overdueRows($reports->overdueQuery($this->overdueFilters(['critical' => true]))->get());
        $this->assertSame(['CRITICAL JOB', 'ON CRITICAL COMPONENT'], array_column($rows, 'job'));
    }

    // ---------------------------------------------------------------- history

    public function test_history_date_filter_defaults_to_the_last_12_months_and_includes_both_ends(): void
    {
        $definition = $this->definition(null, 'HISTORY JOB');
        $this->job($definition, ['status' => PmsJob::COMPLETED, 'completed_at' => '2025-01-05', 'due_date' => '2025-01-01', 'overdue_reason' => 'Dock']);
        $this->job($definition, ['status' => PmsJob::COMPLETED, 'completed_at' => '2025-06-01', 'due_date' => '2025-06-10']);
        $this->job($definition, ['status' => PmsJob::COMPLETED, 'completed_at' => '2026-01-10', 'remark' => 'Imported from CFM']);
        $this->job($definition, ['status' => PmsJob::PLANNED, 'due_date' => '2026-02-01']);
        $columns = PmsReports::HISTORY_COLUMNS;

        // Default: 2025-01-10 .. 2026-01-10 - the January 2025 one is out; today's (imported) one is in.
        $rows = $this->exportRows($this->actingAs($this->shore)->get('/pms/reports/history?export=1'), $columns);
        $this->assertSame(['10/01/2026', '01/06/2025'], array_column($rows, 'completed_at'));
        $this->assertSame('Imported from CFM', $rows[0]['remark']);

        // Both ends inclusive.
        $rows = $this->exportRows($this->actingAs($this->shore)->get('/pms/reports/history?export=1&from=2025-01-05&to=2025-06-01'), $columns);
        $this->assertSame(['01/06/2025', '05/01/2025'], array_column($rows, 'completed_at'));

        // Only late: completed after its due date (or with an overdue reason).
        $rows = $this->exportRows($this->actingAs($this->shore)->get('/pms/reports/history?export=1&from=2024-01-01&to=2026-01-10&late=1'), $columns);
        $this->assertSame(['05/01/2025'], array_column($rows, 'completed_at'));
        $this->assertSame(4, (int) $rows[0]['days_late']);
    }

    // ---------------------------------------------------------------- export = table

    public function test_the_export_has_exactly_the_rows_the_table_shows(): void
    {
        foreach (range(1, 60) as $i) {
            $this->job($this->definition($i % 2 ? $this->vesselId : $this->otherVesselId, sprintf('JOB %02d', $i)),
                ['status' => PmsJob::OVERDUE, 'due_date' => CarbonImmutable::today()->subDays($i)->toDateString()]);
        }
        $reports = app(PmsReports::class);

        // What the page shows: the same query, page by page (50 per page).
        $query = fn () => $reports->overdueQuery($this->overdueFilters(), 'days', 'desc');
        $page1 = $reports->overdueRows($query()->paginate(50, ['*'], 'page', 1)->items());
        $page2 = $reports->overdueRows($query()->paginate(50, ['*'], 'page', 2)->items());
        $table = array_merge($page1, $page2);
        $this->assertCount(60, $table);

        $export = $this->exportRows($this->actingAs($this->shore)->get('/pms/reports/overdue?export=1&sort=days&dir=desc'), PmsReports::OVERDUE_COLUMNS);

        $this->assertSame(array_column($table, 'job'), array_column($export, 'job'));
        $this->assertSame(array_column($table, 'days'), array_map('intval', array_column($export, 'days')));
        $this->assertSame(array_column($table, 'vessel'), array_column($export, 'vessel'));
        $this->assertSame(
            array_map(fn ($d) => CarbonImmutable::parse($d)->format('d/m/Y'), array_column($table, 'due_date')),
            array_column($export, 'due_date')
        );
    }
}
