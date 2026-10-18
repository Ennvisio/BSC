<?php

namespace Tests\Feature\Pms;

use App\PmsComponent;
use App\PmsCounterReading;
use App\PmsJob;
use App\Services\PmsCounterReadingService;
use App\Services\PmsJobScheduler;
use App\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsPmsSchema;
use Tests\TestCase;

class PmsCounterReadingsTest extends TestCase
{
    use BuildsPmsSchema;

    private PmsJobScheduler $scheduler;

    private PmsCounterReadingService $readings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildPmsSchema();
        $this->scheduler = new PmsJobScheduler;
        $this->readings = new PmsCounterReadingService($this->scheduler);
        $this->today('2026-01-10');
    }

    protected function tearDown(): void
    {
        $this->today(null);
        parent::tearDown();
    }

    private function today(?string $date): void
    {
        Carbon::setTestNow($date);
        CarbonImmutable::setTestNow($date);
    }

    private function counter(string $name, ?float $lastValue = null, string $lastDate = '2026-01-01'): PmsComponent
    {
        $component = $this->makeComponent(['name' => $name, 'has_counter' => true]);
        if ($lastValue !== null) {
            $this->reading($component, $lastDate, $lastValue);
        }

        return $component;
    }

    private function valueOn(PmsComponent $component, string $date): ?float
    {
        $value = PmsCounterReading::where('component_id', $component->id)->whereDate('reading_date', $date)->value('value');

        return $value === null ? null : (float) $value;
    }

    // ---------------------------------------------------------------- bulk

    public function test_only_filled_rows_are_saved(): void
    {
        $a = $this->counter('ME', 1000);
        $b = $this->counter('DG1', 500);
        $c = $this->counter('DG2', 700);

        $result = $this->readings->recordMany($this->vesselId, [
            $a->id => ['reading_date' => '2026-01-10', 'value' => '1100'],
            $b->id => ['reading_date' => '2026-01-10', 'value' => ''],
            $c->id => ['reading_date' => '2026-01-09', 'value' => 750.04],
        ], $this->userId);

        $this->assertEqualsCanonicalizing([$a->id, $c->id], array_keys($result['saved']));
        $this->assertSame([], $result['errors']);
        $this->assertSame(1100.0, $this->valueOn($a, '2026-01-10'));
        $this->assertSame(750.0, $this->valueOn($c, '2026-01-09'), 'rounded to one decimal');
        $this->assertSame(1, PmsCounterReading::where('component_id', $b->id)->count(), 'the blank row added nothing');
        $this->assertSame($this->userId, (int) PmsCounterReading::where('component_id', $a->id)->latest('reading_date')->value('entered_by'));
    }

    public function test_bad_rows_get_their_own_error_and_good_rows_are_still_saved(): void
    {
        $good = $this->counter('GOOD', 1000);
        $lower = $this->counter('LOWER', 1000);
        $tooFast = $this->counter('TOO FAST', 1000);                  // 9 days since 01-01: at most 216 h
        $sameDay = $this->counter('SAME DAY', 1000, '2026-01-10');
        $future = $this->counter('FUTURE', 1000);
        $notCounter = $this->makeComponent(['name' => 'NO COUNTER']);
        $otherVessel = $this->makeComponent(['name' => 'ELSEWHERE', 'has_counter' => true]);
        $otherVessel->forceFill(['vessel_id' => $this->secondVessel()])->save();

        $result = $this->readings->recordMany($this->vesselId, [
            $good->id => ['reading_date' => '2026-01-10', 'value' => 1200],
            $lower->id => ['reading_date' => '2026-01-10', 'value' => 999],
            $tooFast->id => ['reading_date' => '2026-01-10', 'value' => 1217],
            $sameDay->id => ['reading_date' => '2026-01-10', 'value' => 1010],
            $future->id => ['reading_date' => '2026-01-11', 'value' => 1010],
            $notCounter->id => ['reading_date' => '2026-01-10', 'value' => 10],
            $otherVessel->id => ['reading_date' => '2026-01-10', 'value' => 10],
        ], $this->userId);

        $this->assertSame([$good->id], array_keys($result['saved']));
        $this->assertStringContainsString('can\'t go backwards', $result['errors'][$lower->id]);
        $this->assertSame('More than 24 running hours per day since the previous reading.', $result['errors'][$tooFast->id]);
        $this->assertStringContainsString('already a reading for that date', $result['errors'][$sameDay->id]);
        $this->assertStringContainsString('future', $result['errors'][$future->id]);
        $this->assertStringContainsString('doesn\'t have a running-hour counter', $result['errors'][$notCounter->id]);
        $this->assertSame('This component isn\'t on the vessel.', $result['errors'][$otherVessel->id]);

        $this->assertSame(1200.0, $this->valueOn($good, '2026-01-10'));
        $this->assertSame(1, PmsCounterReading::where('component_id', $lower->id)->count());
        $this->assertSame(1, PmsCounterReading::where('component_id', $sameDay->id)->count());
        $this->assertSame(0, PmsCounterReading::where('component_id', $otherVessel->id)->count());
    }

    public function test_exactly_24_hours_a_day_is_allowed(): void
    {
        $c = $this->counter('ME', 1000);   // 9 days

        $result = $this->readings->recordMany($this->vesselId, [$c->id => ['reading_date' => '2026-01-10', 'value' => 1216]], $this->userId);

        $this->assertSame([], $result['errors']);
    }

    public function test_saving_refreshes_the_rh_jobs_of_those_components(): void
    {
        $engine = $this->counter('ME', 1000, '2026-01-10');
        $job = $this->scheduler->definitionSaved($this->makeDefinition($engine, [], [[500, 'RunningHour']]));
        $this->assertSame(1500.0, (float) $job->due_running_hours);
        $this->assertSame(PmsJob::PLANNED, $job->status);

        $this->today('2026-02-01');                               // 22 days later
        $this->readings->recordMany($this->vesselId, [$engine->id => ['reading_date' => '2026-02-01', 'value' => 1510]], $this->userId);

        $job->refresh();
        $this->assertSame(PmsJob::OVERDUE, $job->status, 'the meter passed the due RH');
        $this->assertSame(1500.0, (float) $job->due_running_hours, 'a reading never moves the due RH');
    }

    public function test_a_new_average_moves_the_estimated_due_date(): void
    {
        $engine = $this->counter('ME', 1000, '2026-01-10');
        $job = $this->scheduler->definitionSaved($this->makeDefinition($engine, [], [[500, 'RunningHour']]));
        $this->assertNull($job->due_date, 'one reading: no average yet');

        $this->today('2026-01-20');
        $this->readings->recordMany($this->vesselId, [$engine->id => ['reading_date' => '2026-01-20', 'value' => 1100]], $this->userId);

        // 100 h in 10 days = 10 h/day; 400 h still to run = 40 days.
        $this->assertSame('2026-03-01', $job->fresh()->due_date->toDateString());
    }

    // ---------------------------------------------------------------- shared rules

    public function test_a_single_reading_follows_the_same_rules(): void
    {
        $c = $this->counter('ME', 1000, '2025-12-01');
        $this->reading($c, '2026-01-09', 1100);

        try {
            $this->readings->record($c, '2026-01-05', 1150, $this->userId);   // back-filled above a later reading
            $this->fail('Expected a refusal.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('higher than the later reading of 1,100.0 h', $e->errors()['value'][0]);
        }

        $this->readings->record($c, '2026-01-05', 1050, $this->userId);
        $this->assertSame(1050.0, $this->valueOn($c, '2026-01-05'));
    }

    public function test_the_batch_average_matches_the_single_one(): void
    {
        $a = $this->counter('A', 1000, '2025-12-01');
        $this->reading($a, '2026-01-10', 1400);
        $b = $this->counter('B', 50, '2026-01-10');

        $batch = $this->scheduler->averagesPerDay([$a->id, $b->id]);

        $this->assertSame(10.0, $batch[$a->id]);
        $this->assertSame($this->scheduler->averagePerDay($a), $batch[$a->id]);
        $this->assertNull($batch[$b->id]);
    }

    // ---------------------------------------------------------------- the page

    public function test_saving_is_for_the_vessel_editors_only(): void
    {
        $engine = $this->counter('ME', 1000);
        $viewer = User::create(['name' => 'GM', 'email' => 'gm@test.local', 'password' => 'x']);
        DB::table('roles')->insert(['user_id' => $viewer->id, 'role' => 'gm-srd', 'user_type' => 'srd']);

        $this->actingAs($viewer)->postJson('/pms/counters', ['vessel_id' => $this->vesselId, 'readings' => [$engine->id => ['reading_date' => '2026-01-10', 'value' => 1100]]])
            ->assertForbidden();

        $officer = User::find($this->userId); // chief officer of this vessel
        $this->actingAs($officer)->postJson('/pms/counters', ['readings' => [
            $engine->id => ['reading_date' => '2026-01-10', 'value' => 1100],
        ]])->assertOk()->assertJsonPath('saved', [$engine->id])->assertJsonPath('rows.'.$engine->id.'.last_value', 1100);

        $this->actingAs($officer)->postJson('/pms/counters', ['readings' => [
            $engine->id => ['reading_date' => '2026-01-10', 'value' => 1200],
        ]])->assertStatus(422)->assertJsonPath('saved', []);
    }

    private function secondVessel(): int
    {
        $row = collect((array) DB::table('vessels')->where('id', $this->vesselId)->first())->except('id')->all();

        return DB::table('vessels')->insertGetId(['name' => 'OTHER VESSEL'] + $row);
    }
}
