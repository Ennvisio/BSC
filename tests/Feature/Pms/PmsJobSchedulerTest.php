<?php

namespace Tests\Feature\Pms;

use App\PmsCondition;
use App\PmsJob;
use App\PmsJobDefinition;
use App\Services\PmsJobScheduler;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsPmsSchema;
use Tests\TestCase;

class PmsJobSchedulerTest extends TestCase
{
    use BuildsPmsSchema;

    private PmsJobScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildPmsSchema();
        $this->scheduler = new PmsJobScheduler;
        $this->today('2026-01-10');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function today(string $date): void
    {
        Carbon::setTestNow($date);
        CarbonImmutable::setTestNow($date);
    }

    private function openJob(PmsJobDefinition $definition): PmsJob
    {
        return PmsJob::where('job_definition_id', $definition->id)->open()->sole();
    }

    private function activeJob(PmsJobDefinition $definition): PmsJob
    {
        return PmsJob::where('job_definition_id', $definition->id)->whereIn('status', PmsJob::ACTIVE_STATUSES)->sole();
    }

    // ---------------------------------------------------------------- calendar

    public function test_calendar_job_is_due_one_interval_after_its_start_date(): void
    {
        $definition = $this->makeDefinition($this->makeComponent(), ['start_date' => '2026-01-15'], [[3, 'Month']]);

        $job = $this->scheduler->definitionSaved($definition);

        $this->assertSame('2026-04-15', $job->due_date->toDateString());
        $this->assertSame('2026-04-15', $job->original_due_date->toDateString());
        $this->assertSame('2026-04-08', $job->warning_date->toDateString(), 'due date minus the default 7-day warning');
        $this->assertSame(PmsJob::PLANNED, $job->status);
        $this->assertNull($job->due_running_hours);
    }

    public function test_calendar_base_falls_back_to_installation_date_then_today(): void
    {
        $installed = $this->scheduler->definitionSaved(
            $this->makeDefinition($this->makeComponent(['installation_date' => '2025-11-20']), [], [[1, 'Month']])
        );
        $this->assertSame('2025-12-20', $installed->due_date->toDateString());
        $this->assertSame(PmsJob::OVERDUE, $installed->status, 'already past its due date on creation');

        $neither = $this->scheduler->definitionSaved($this->makeDefinition($this->makeComponent(), [], [[2, 'Week']]));
        $this->assertSame('2026-01-24', $neither->due_date->toDateString());
    }

    public function test_month_interval_does_not_overflow_into_the_next_month(): void
    {
        $job = $this->scheduler->definitionSaved(
            $this->makeDefinition($this->makeComponent(), ['start_date' => '2026-01-31'], [[1, 'Month']])
        );

        $this->assertSame('2026-02-28', $job->due_date->toDateString());
    }

    // ---------------------------------------------------------------- running hours

    public function test_rh_job_is_due_at_latest_reading_plus_interval_with_an_estimated_date(): void
    {
        $component = $this->makeComponent(['has_counter' => true]);
        $this->reading($component, '2025-12-01', 1000);
        $this->reading($component, '2026-01-10', 1400); // 400 h over 40 days = 10 h/day

        $job = $this->scheduler->definitionSaved($this->makeDefinition($component, [], [[500, 'RunningHour']]));

        $this->assertSame(1900.0, (float) $job->due_running_hours);
        $this->assertSame('2026-03-01', $job->due_date->toDateString(), '500 h at 10 h/day = 50 days after the latest reading');
        $this->assertSame('2026-02-22', $job->warning_date->toDateString());
        $this->assertSame(PmsJob::PLANNED, $job->status);
    }

    public function test_rh_job_without_an_average_has_no_due_date_and_goes_overdue_on_the_meter(): void
    {
        $component = $this->makeComponent(['has_counter' => true]);
        $this->reading($component, '2026-01-10', 1000);

        $job = $this->scheduler->definitionSaved($this->makeDefinition($component, [], [[500, 'RunningHour']]));
        $this->assertNull($job->due_date, 'one reading gives no hours-per-day average to estimate from');
        $this->assertSame(1500.0, (float) $job->due_running_hours);

        $this->today('2026-02-01');
        $this->reading($component, '2026-02-01', 1500.5);
        $this->scheduler->refreshComponent($component);

        $job->refresh();
        $this->assertSame(PmsJob::OVERDUE, $job->status);
        $this->assertSame(1500.0, (float) $job->due_running_hours, 'a new reading never moves the due RH');
    }

    public function test_rh_interval_needs_a_component_with_a_counter_and_a_reading(): void
    {
        try {
            $this->scheduler->definitionSaved($this->makeDefinition($this->makeComponent(), [], [[500, 'RunningHour']]));
            $this->fail('Expected a validation error for a component without a counter.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('intervals', $e->errors());
        }

        $this->expectException(ValidationException::class);
        $this->scheduler->definitionSaved(
            $this->makeDefinition($this->makeComponent(['has_counter' => true]), [], [[500, 'RunningHour']])
        );
    }

    public function test_rh_warning_unit_makes_the_job_due_soon_within_that_many_hours(): void
    {
        $component = $this->makeComponent(['has_counter' => true]);
        $this->reading($component, '2026-01-10', 1000);
        $definition = $this->makeDefinition($component, ['warning_period' => 50, 'warning_unit' => 'RunningHours'], [[500, 'RunningHour']]);
        $job = $this->scheduler->definitionSaved($definition);

        $this->today('2026-01-20');
        $this->reading($component, '2026-01-20', 1449);
        $this->scheduler->refreshComponent($component);
        $this->assertSame(PmsJob::PLANNED, $job->fresh()->status);

        $this->today('2026-01-21');
        $this->reading($component, '2026-01-21', 1450);
        $this->scheduler->refreshComponent($component);
        $this->assertSame(PmsJob::DUE_SOON, $job->fresh()->status);
    }

    // ---------------------------------------------------------------- whichever first

    public function test_whichever_first_takes_the_rh_estimate_when_it_comes_before_the_year(): void
    {
        $component = $this->makeComponent(['has_counter' => true]);
        $this->reading($component, '2025-12-31', 800);
        $this->reading($component, '2026-01-10', 1000); // 20 h/day

        $job = $this->scheduler->definitionSaved(
            $this->makeDefinition($component, ['start_date' => '2026-01-10'], [[1000, 'RunningHour'], [1, 'Year']])
        );

        $this->assertSame(2000.0, (float) $job->due_running_hours);
        $this->assertSame('2026-03-01', $job->due_date->toDateString(), '1000 h at 20 h/day = 50 days, well before 1 year');
    }

    public function test_whichever_first_takes_the_calendar_interval_when_it_comes_first_or_there_is_no_average(): void
    {
        $slow = $this->makeComponent(['has_counter' => true]);
        $this->reading($slow, '2025-12-31', 990);
        $this->reading($slow, '2026-01-10', 1000); // 1 h/day -> 1000 h takes ~3 years
        $job = $this->scheduler->definitionSaved(
            $this->makeDefinition($slow, ['start_date' => '2026-01-10'], [[1000, 'RunningHour'], [1, 'Year']])
        );
        $this->assertSame('2027-01-10', $job->due_date->toDateString());
        $this->assertSame(2000.0, (float) $job->due_running_hours, 'the RH limit still applies');

        $noAverage = $this->makeComponent(['has_counter' => true]);
        $this->reading($noAverage, '2026-01-10', 1000);
        $job = $this->scheduler->definitionSaved(
            $this->makeDefinition($noAverage, ['start_date' => '2026-01-10'], [[1000, 'RunningHour'], [1, 'Year']])
        );
        $this->assertSame('2027-01-10', $job->due_date->toDateString());
    }

    // ---------------------------------------------------------------- completion

    public function test_completing_a_job_creates_the_next_one_from_the_completed_date(): void
    {
        $component = $this->makeComponent();
        $definition = $this->makeDefinition($component, ['start_date' => '2026-01-15'], [[3, 'Month']]);
        $first = $this->scheduler->definitionSaved($definition);

        $this->today('2026-04-10');
        $this->scheduler->complete($first, $this->completionData(['completed_at' => '2026-04-10', 'remark' => 'Done']), $this->userId);

        $first->refresh();
        $this->assertSame(PmsJob::COMPLETED, $first->status);
        $this->assertSame($this->userId, (int) $first->completed_by);
        $this->assertSame(PmsCondition::where('name', 'Good')->value('id'), $component->fresh()->condition_id, 'component condition updated');

        $next = $this->openJob($definition);
        $this->assertNotSame($first->id, $next->id);
        $this->assertSame('2026-07-10', $next->due_date->toDateString(), 'completed date + 3 months');
        $this->assertSame('2026-07-10', $next->original_due_date->toDateString());
        $this->assertSame(2, $definition->jobs()->count());
    }

    public function test_completing_an_rh_job_counts_the_next_due_from_rh_at_completion(): void
    {
        $component = $this->makeComponent(['has_counter' => true]);
        $this->reading($component, '2026-01-10', 1000);
        $definition = $this->makeDefinition($component, [], [[500, 'RunningHour']]);
        $first = $this->scheduler->definitionSaved($definition);

        try {
            $this->scheduler->complete($first, $this->completionData(['completed_at' => '2026-01-10']), $this->userId);
            $this->fail('RH at completion should be required for a running-hours job.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('rh_at_completion', $e->errors());
        }

        $this->scheduler->complete($first, $this->completionData(['completed_at' => '2026-01-10', 'rh_at_completion' => 1450]), $this->userId);

        $this->assertSame(1950.0, (float) $this->openJob($definition)->due_running_hours);
    }

    public function test_completing_late_requires_an_overdue_reason(): void
    {
        $definition = $this->makeDefinition($this->makeComponent(), ['start_date' => '2025-12-01'], [[1, 'Month']]);
        $job = $this->scheduler->definitionSaved($definition); // due 2026-01-01, today 2026-01-10

        try {
            $this->scheduler->complete($job, $this->completionData(['completed_at' => '2026-01-10']), $this->userId);
            $this->fail('A late completion should need a reason.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('overdue_reason', $e->errors());
        }

        $this->scheduler->complete($job, $this->completionData([
            'completed_at' => '2026-01-10', 'overdue_reason' => 'Spares not available', 'overdue_remark' => 'Waited for gasket',
        ]), $this->userId);
        $this->assertSame('Spares not available', $job->fresh()->overdue_reason);
    }

    public function test_job_needing_approval_schedules_the_next_job_straight_away(): void
    {
        $definition = $this->makeDefinition($this->makeComponent(), ['start_date' => '2026-01-10', 'requires_approval' => true], [[1, 'Month']]);
        $job = $this->scheduler->definitionSaved($definition);

        $this->scheduler->complete($job, $this->completionData(['completed_at' => '2026-01-10']), $this->userId);
        $this->assertSame(PmsJob::WAITING_APPROVAL, $job->fresh()->status);

        // The next job doesn't wait on approval - only its own completion does.
        $next = $this->activeJob($definition);
        $this->assertNotSame($job->id, $next->id);
        $this->assertSame('2026-02-10', $next->due_date->toDateString());
        $this->assertSame(2, $definition->jobs()->count());

        $this->scheduler->approve($job->fresh(), $this->userId);
        $job->refresh();
        $this->assertSame(PmsJob::COMPLETED, $job->status);
        $this->assertNotNull($job->approved_at);

        // Approving doesn't create yet another one.
        $this->assertSame(2, $definition->jobs()->count());
        $this->assertSame($next->id, $this->activeJob($definition)->id);
    }

    // ---------------------------------------------------------------- single jobs

    public function test_single_job_uses_its_own_due_date_and_does_not_repeat(): void
    {
        $definition = $this->makeDefinition($this->makeComponent(), ['is_single' => true, 'due_type' => 'Date', 'due_date' => '2026-02-01']);
        $job = $this->scheduler->definitionSaved($definition);
        $this->assertSame('2026-02-01', $job->due_date->toDateString());

        $this->scheduler->complete($job, $this->completionData(['completed_at' => '2026-01-10']), $this->userId);

        $this->assertSame(PmsJob::COMPLETED, $job->fresh()->status);
        $this->assertSame(0, PmsJob::where('job_definition_id', $definition->id)->open()->count());
        $this->assertSame(1, $definition->jobs()->count());
    }

    // ---------------------------------------------------------------- reschedule

    public function test_reschedule_moves_the_due_date_but_keeps_the_original(): void
    {
        $definition = $this->makeDefinition($this->makeComponent(), ['start_date' => '2025-12-01'], [[1, 'Month']]);
        $job = $this->scheduler->definitionSaved($definition);
        $this->assertSame(PmsJob::OVERDUE, $job->status);

        $this->scheduler->reschedule($job, ['due_date' => '2026-03-01', 'reason' => 'Dock', 'remark' => 'Next dry dock']);
        $job->refresh();

        $this->assertSame('2026-03-01', $job->due_date->toDateString());
        $this->assertSame('2026-01-01', $job->original_due_date->toDateString());
        $this->assertSame('2026-02-22', $job->warning_date->toDateString());
        $this->assertSame(PmsJob::PLANNED, $job->status);
        $this->assertSame('Dock', $job->reschedule_reason);

        // ...and the nightly refresh doesn't put it back.
        $this->scheduler->refreshAll();
        $this->assertSame('2026-03-01', $job->fresh()->due_date->toDateString());
    }

    /** A running-hours job: 10 h/day, due at 1900 h around 2026-03-01 (today is 2026-01-10). */
    private function rhJob(): array
    {
        $component = $this->makeComponent(['has_counter' => true]);
        $this->reading($component, '2025-12-01', 1000);
        $this->reading($component, '2026-01-10', 1400);
        $job = $this->scheduler->definitionSaved($this->makeDefinition($component, [], [[500, 'RunningHour']]));
        $this->assertSame('2026-03-01', $job->due_date->toDateString());

        return [$component, $job];
    }

    public function test_reschedule_postpones_the_rh_limit_by_the_average_hours_per_day_added(): void
    {
        [, $job] = $this->rhJob();

        $this->scheduler->reschedule($job, ['due_date' => '2026-03-31', 'reason' => 'Dock']); // 30 days later
        $job->refresh();

        $this->assertSame(2200.0, (float) $job->due_running_hours, '1900 + 10 h/day x 30 days');
        $this->assertSame('2026-03-31', $job->due_date->toDateString());
        $this->assertSame('2026-03-01', $job->original_due_date->toDateString());

        $this->scheduler->refreshAll();
        $this->assertSame(2200.0, (float) $job->fresh()->due_running_hours, 'the nightly refresh keeps the postponed limit');
    }

    public function test_reschedule_takes_an_explicit_new_due_rh(): void
    {
        [, $job] = $this->rhJob();

        $this->scheduler->reschedule($job, ['due_date' => '2026-03-31', 'reason' => 'Dock', 'due_running_hours' => 2050]);

        $this->assertSame(2050.0, (float) $job->fresh()->due_running_hours);
    }

    public function test_rescheduled_job_is_not_overdue_on_rh_before_its_new_date(): void
    {
        [$component, $job] = $this->rhJob();
        $this->scheduler->reschedule($job, ['due_date' => '2026-03-31', 'reason' => 'Dock', 'due_running_hours' => 1950]);

        // The meter passes the postponed limit well before the new date.
        $this->today('2026-02-20');
        $this->reading($component, '2026-02-20', 2000);
        $this->scheduler->refreshComponent($component);
        $this->assertNotSame(PmsJob::OVERDUE, $job->fresh()->status, 'RH is over its limit, but the new date has not come');

        $this->today('2026-03-31');
        $this->scheduler->refreshAll();
        $this->assertNotSame(PmsJob::OVERDUE, $job->fresh()->status, 'still not overdue on the new due date itself');

        $this->today('2026-04-01');
        $this->scheduler->refreshAll();
        $this->assertSame(PmsJob::OVERDUE, $job->fresh()->status);
    }

    public function test_reschedule_of_an_rh_job_needs_a_usable_new_due_rh(): void
    {
        [, $job] = $this->rhJob();

        try {
            $this->scheduler->reschedule($job, ['due_date' => '2026-03-31', 'reason' => 'Dock', 'due_running_hours' => 1400]);
            $this->fail('A new due RH at or below the current reading should be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('due_running_hours', $e->errors());
        }

        // One reading only: no hours-per-day average, so nothing to add by - the officer has to say.
        $noAverage = $this->makeComponent(['has_counter' => true]);
        $this->reading($noAverage, '2026-01-10', 1000);
        $other = $this->scheduler->definitionSaved($this->makeDefinition($noAverage, [], [[500, 'RunningHour']]));
        try {
            $this->scheduler->reschedule($other, ['due_date' => '2026-03-31', 'reason' => 'Dock']);
            $this->fail('Without an average the new due RH must be given.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('due_running_hours', $e->errors());
        }
        $this->assertSame(1500.0, (float) $other->fresh()->due_running_hours, 'nothing changed');
    }

    public function test_reschedule_needs_a_future_date_and_a_known_reason(): void
    {
        $job = $this->scheduler->definitionSaved($this->makeDefinition($this->makeComponent(), [], [[1, 'Month']]));

        try {
            $this->scheduler->reschedule($job, ['due_date' => '2026-01-10', 'reason' => 'Holiday']);
            $this->fail('Expected validation errors.');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing(['due_date', 'reason'], array_keys($e->errors()));
        }
    }

    // ---------------------------------------------------------------- status

    public function test_status_moves_from_planned_to_due_soon_to_overdue(): void
    {
        $job = $this->scheduler->definitionSaved(
            $this->makeDefinition($this->makeComponent(), ['start_date' => '2026-01-10', 'warning_period' => 2, 'warning_unit' => 'Weeks'], [[1, 'Month']])
        ); // due 2026-02-10, warning 2026-01-27

        foreach (['2026-01-26' => PmsJob::PLANNED, '2026-01-27' => PmsJob::DUE_SOON, '2026-02-10' => PmsJob::DUE_SOON, '2026-02-11' => PmsJob::OVERDUE] as $date => $status) {
            $this->today($date);
            $this->scheduler->refreshAll();
            $this->assertSame($status, $job->fresh()->status, "on {$date}");
        }
    }

    // ---------------------------------------------------------------- definitions

    public function test_a_definition_never_has_more_than_one_open_job(): void
    {
        $definition = $this->makeDefinition($this->makeComponent(), [], [[1, 'Month']]);

        $this->scheduler->definitionSaved($definition);
        $this->scheduler->definitionSaved($definition->fresh());
        $this->scheduler->definitionSaved($definition->fresh());

        $this->assertSame(1, $definition->jobs()->count());
    }

    public function test_editing_the_interval_recalculates_the_open_job_but_not_its_original_due_date(): void
    {
        $definition = $this->makeDefinition($this->makeComponent(), ['start_date' => '2026-01-15'], [[3, 'Month']]);
        $job = $this->scheduler->definitionSaved($definition);

        $definition->intervals()->update(['every' => 6]);
        $this->scheduler->definitionSaved($definition->fresh());

        $job->refresh();
        $this->assertSame('2026-07-15', $job->due_date->toDateString());
        $this->assertSame('2026-04-15', $job->original_due_date->toDateString());
    }

    public function test_deactivating_a_definition_cancels_its_open_job(): void
    {
        $definition = $this->makeDefinition($this->makeComponent(), [], [[1, 'Month']]);
        $job = $this->scheduler->definitionSaved($definition);

        $this->scheduler->deactivate($definition);

        $this->assertFalse($definition->fresh()->is_active);
        $this->assertSame(PmsJob::CANCELLED, $job->fresh()->status);
        $this->assertSame(0, PmsJob::where('job_definition_id', $definition->id)->open()->count());
    }
}
