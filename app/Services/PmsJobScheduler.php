<?php

namespace App\Services;

use App\PmsComponent;
use App\PmsCondition;
use App\PmsCounterReading;
use App\PmsJob;
use App\PmsJobDefinition;
use App\PmsJobInterval;
use App\PmsRequiredPart;
use App\Rank;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Everything about WHEN a PMS job is due, and what happens to it after.
 *
 * A job's due date, warning date and status are always recalculated from
 * stored facts - the definition, the base point the job counts from, and the
 * component's meter readings - never nudged incrementally. That makes
 * refresh() safe to run any number of times (the nightly pms:refresh-jobs
 * does exactly that) and lets an edited definition recalculate its open job.
 *
 * The base point a job counts from:
 *  - the previous completed job of the same definition (its completed date,
 *    and its RH at completion), or for the very first job
 *  - start_date, else the component's installation date, else the day the
 *    job was created; with the meter reading as it stood on that day.
 * Because the base is fixed, new readings never move a job's due RH - they
 * only refine the estimated date it will reach it.
 */
class PmsJobScheduler
{
    /** How far back the average running hours per day is measured. */
    const AVERAGE_WINDOW_DAYS = 90;

    // ------------------------------------------------------------------
    // Pure calculation - no database. Public so they can be tested alone.
    // ------------------------------------------------------------------

    /**
     * When a recurring job falls due: whichever of its intervals comes first.
     *
     * @param  iterable  $intervals  PmsJobInterval models or ['every' => int, 'period' => string]
     * @param  array|null  $meter  ['value' => float, 'date' => CarbonInterface, 'per_day' => ?float]
     * @return array{due_date: ?CarbonImmutable, due_running_hours: ?float, calendar_due: ?CarbonImmutable}
     */
    public function dueFromIntervals(iterable $intervals, ?CarbonInterface $baseDate, ?float $baseRh, ?array $meter): array
    {
        $calendarDue = null;
        $dueRh = null;

        foreach ($intervals as $interval) {
            $every = (int) data_get($interval, 'every');
            $period = data_get($interval, 'period');

            if ($period === PmsJobInterval::RUNNING_HOUR) {
                if ($baseRh !== null) {
                    $candidate = round($baseRh + $every, 1);
                    $dueRh = $dueRh === null ? $candidate : min($dueRh, $candidate);
                }
            } elseif ($baseDate !== null) {
                $candidate = $this->addPeriod(CarbonImmutable::parse($baseDate)->startOfDay(), $every, $period);
                $calendarDue = $calendarDue === null || $candidate->lt($calendarDue) ? $candidate : $calendarDue;
            }
        }

        return [
            'due_date' => $this->earliest($calendarDue, $dueRh !== null ? $this->estimateDateForRh($dueRh, $meter) : null),
            'due_running_hours' => $dueRh,
            'calendar_due' => $calendarDue,
        ];
    }

    /**
     * The date the meter is expected to reach $rh, from its latest reading and
     * average hours per day. Null when there's no average to go on.
     */
    public function estimateDateForRh(float $rh, ?array $meter): ?CarbonImmutable
    {
        if (! $meter || empty($meter['per_day']) || $meter['per_day'] <= 0) {
            return null;
        }

        $remaining = max(0.0, $rh - (float) $meter['value']);

        return CarbonImmutable::parse($meter['date'])->startOfDay()->addDays((int) ceil($remaining / $meter['per_day']));
    }

    /** due date minus the warning period; for RunningHours, the date the meter is expected to get within range. */
    public function warningDate(?CarbonInterface $dueDate, ?float $dueRh, int $period, string $unit, ?array $meter): ?CarbonImmutable
    {
        if ($unit === 'RunningHours') {
            return $dueRh !== null ? $this->estimateDateForRh($dueRh - $period, $meter) : null;
        }

        if ($dueDate === null) {
            return null;
        }

        $due = CarbonImmutable::parse($dueDate);

        return match ($unit) {
            'Weeks' => $due->subWeeks($period),
            'Months' => $due->subMonthsNoOverflow($period),
            default => $due->subDays($period),
        };
    }

    /**
     * Overdue if today is past the due date or the meter is past the due RH;
     * DueSoon once today reaches the warning date (or, for a RunningHours
     * warning, once the meter is within the warning period of the due RH).
     */
    public function statusFor(?CarbonInterface $dueDate, ?CarbonInterface $warningDate, ?float $dueRh, ?float $currentRh,
        int $warningPeriod, string $warningUnit, CarbonInterface $today, bool $rhCanMakeOverdue = true): string
    {
        if (($dueDate && $today->gt($dueDate)) || ($rhCanMakeOverdue && $dueRh !== null && $currentRh !== null && $currentRh > $dueRh)) {
            return PmsJob::OVERDUE;
        }

        if (($warningDate && $today->gte($warningDate))
            || ($warningUnit === 'RunningHours' && $dueRh !== null && $currentRh !== null && $currentRh >= $dueRh - $warningPeriod)) {
            return PmsJob::DUE_SOON;
        }

        return PmsJob::PLANNED;
    }

    // ------------------------------------------------------------------
    // Definitions
    // ------------------------------------------------------------------

    /**
     * Call after a definition (and its intervals/checklist) has been saved.
     * Makes sure it has exactly one open job: creates it if there's none,
     * recalculates it if there is - or, for a deactivated definition,
     * cancels it.
     */
    public function definitionSaved(PmsJobDefinition $definition): ?PmsJob
    {
        $definition->load(['intervals', 'component']);

        if (! $definition->is_active) {
            $this->deactivate($definition);

            return null;
        }

        $this->assertSchedulable($definition);

        // Only an active job is recalculated or stands in for "nothing to
        // schedule" here - a job awaiting approval is historical, and its
        // successor (already created when it was completed) is what reacts
        // to a definition edit.
        $open = $definition->jobs()->whereIn('status', PmsJob::ACTIVE_STATUSES)->first();
        if ($open) {
            return $this->refresh($open);
        }

        // A single job never repeats, whatever its current status.
        if ($definition->is_single && $definition->jobs()->exists()) {
            return null;
        }

        // Its successor is only missing here if the predecessor is itself
        // still waiting on approval - nothing new to schedule yet.
        if ($definition->jobs()->where('status', PmsJob::WAITING_APPROVAL)->exists()) {
            return null;
        }

        return $this->createJob($definition);
    }

    /** Deactivate a definition and cancel the job it still has to do. */
    public function deactivate(PmsJobDefinition $definition): void
    {
        DB::transaction(function () use ($definition) {
            if ($definition->is_active) {
                $definition->update(['is_active' => false]);
            }

            // A job already done and only awaiting approval isn't cancelled -
            // the work happened; approving it just won't schedule another.
            $definition->jobs()->whereIn('status', PmsJob::ACTIVE_STATUSES)->update(['status' => PmsJob::CANCELLED]);
        });
    }

    /** Rules a definition must meet before a job can be scheduled from it. */
    public function assertSchedulable(PmsJobDefinition $definition): void
    {
        $definition->loadMissing(['intervals', 'component']);
        $errors = [];

        if ($definition->is_single) {
            if ($definition->due_type === 'Date' && ! $definition->due_date) {
                $errors['due_date'] = 'Enter the due date.';
            } elseif ($definition->due_type === 'RunningHours' && $definition->due_running_hours === null) {
                $errors['due_running_hours'] = 'Enter the due running hours.';
            } elseif (! in_array($definition->due_type, PmsJobDefinition::DUE_TYPES, true)) {
                $errors['due_type'] = 'Choose a due type.';
            }
        } elseif ($definition->intervals->isEmpty()) {
            $errors['intervals'] = 'Add at least one interval.';
        }

        if ($definition->usesRunningHours()) {
            if (! $definition->component->has_counter) {
                $errors['intervals'] = 'This component has no running-hour counter, so the job can\'t be due by running hours.';
            } elseif (! $definition->is_single && ! $this->latestReading($definition->component)) {
                $errors['intervals'] = 'Enter a running-hours reading for this component first - the first due RH counts from it.';
            }
        }

        if ($definition->warning_unit === 'RunningHours' && ! $definition->usesRunningHours()) {
            $errors['warning_unit'] = 'A running-hours warning needs the job to be due by running hours.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    // ------------------------------------------------------------------
    // Jobs
    // ------------------------------------------------------------------

    /**
     * Recalculate an open job's due date, warning date and status. Safe to
     * call any time; does nothing to a job that's done, cancelled or
     * awaiting approval.
     */
    public function refresh(PmsJob $job): PmsJob
    {
        if (! $job->isActive()) {
            return $job;
        }

        $job->fill($this->scheduleFor($job));
        if ($job->isDirty()) {
            $job->save();
        }

        return $job;
    }

    /** Refresh every open job - what the nightly pms:refresh-jobs runs. Returns how many changed. */
    public function refreshAll(?int $vesselId = null): int
    {
        $changed = 0;

        PmsJob::whereIn('status', PmsJob::ACTIVE_STATUSES)
            ->when($vesselId, fn ($q) => $q->where('vessel_id', $vesselId))
            ->with(['definition.intervals', 'component'])
            ->chunkById(200, function ($jobs) use (&$changed) {
                foreach ($jobs as $job) {
                    $before = [$job->due_date?->toDateString(), $job->warning_date?->toDateString(), $job->status];
                    $this->refresh($job);
                    $after = [$job->due_date?->toDateString(), $job->warning_date?->toDateString(), $job->status];
                    $changed += $before !== $after ? 1 : 0;
                }
            });

        return $changed;
    }

    /** A reading was added or removed: estimates and RH statuses for this component may have moved. */
    public function refreshComponent(PmsComponent $component): void
    {
        PmsJob::where('component_id', $component->id)
            ->whereIn('status', PmsJob::ACTIVE_STATUSES)
            ->with(['definition.intervals', 'component'])
            ->get()
            ->each(fn ($job) => $this->refresh($job));
    }

    /**
     * Mark a job as done.
     *
     * @param  array  $data  completed_at, done_by_rank_id, condition_after_id, remark?,
     *                       overdue_reason?, overdue_remark?, rh_at_completion?
     */
    public function complete(PmsJob $job, array $data, int $userId): PmsJob
    {
        $job->loadMissing(['definition.intervals', 'component']);
        $definition = $job->definition;

        if (! $job->isActive()) {
            throw ValidationException::withMessages(['job' => 'This job is no longer open.']);
        }

        $errors = [];
        $completedAt = ! empty($data['completed_at']) ? CarbonImmutable::parse($data['completed_at'])->startOfDay() : null;
        $today = CarbonImmutable::today();

        if (! $completedAt) {
            $errors['completed_at'] = 'Enter the date the job was completed.';
        } elseif ($completedAt->gt($today)) {
            $errors['completed_at'] = 'The completed date can\'t be in the future.';
        }
        if (empty($data['done_by_rank_id']) || ! Rank::whereKey($data['done_by_rank_id'])->exists()) {
            $errors['done_by_rank_id'] = 'Select the rank that did the job.';
        }
        if (empty($data['condition_after_id']) || ! PmsCondition::whereKey($data['condition_after_id'])->exists()) {
            $errors['condition_after_id'] = 'Select the component\'s condition.';
        }

        $rh = isset($data['rh_at_completion']) && $data['rh_at_completion'] !== '' ? round((float) $data['rh_at_completion'], 1) : null;
        if ($definition->usesRunningHours()) {
            if ($rh === null || ! is_numeric($data['rh_at_completion']) || $rh < 0) {
                $errors['rh_at_completion'] = 'Enter the running hours at completion.';
            }
        } else {
            $rh = null;
        }

        // Late = done after the due date, or past the due running hours.
        $late = $completedAt && (
            ($job->due_date && $completedAt->gt($job->due_date))
            || ($job->due_running_hours !== null && $rh !== null && $rh > (float) $job->due_running_hours)
        );
        if ($late && trim((string) ($data['overdue_reason'] ?? '')) === '') {
            $errors['overdue_reason'] = 'This job was completed late - give the reason.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($job, $definition, $data, $completedAt, $rh, $late, $userId) {
            $job->fill([
                'completed_at' => $completedAt->toDateString(),
                'done_by_rank_id' => $data['done_by_rank_id'],
                'rh_at_completion' => $rh,
                'condition_after_id' => $data['condition_after_id'],
                'remark' => $this->clean($data['remark'] ?? null),
                'overdue_reason' => $late ? $this->clean($data['overdue_reason']) : null,
                'overdue_remark' => $late ? $this->clean($data['overdue_remark'] ?? null) : null,
                'completed_by' => $userId,
                'status' => $definition->requires_approval ? PmsJob::WAITING_APPROVAL : PmsJob::COMPLETED,
            ])->save();

            $job->component->update(['condition_id' => $data['condition_after_id']]);

            // The next job is scheduled from this completion straight away -
            // approval signs off the paperwork, it doesn't hold up the work.
            $this->createNextJob($definition);

            return $job;
        });
    }

    /** Office sign-off: WaitingApproval -> Completed. The next job was already scheduled when this one was completed. */
    public function approve(PmsJob $job, int $userId): PmsJob
    {
        if ($job->status !== PmsJob::WAITING_APPROVAL) {
            throw ValidationException::withMessages(['job' => 'Only a job waiting for approval can be approved.']);
        }

        $job->update(['status' => PmsJob::COMPLETED, 'approved_by' => $userId, 'approved_at' => now()]);

        return $job;
    }

    /**
     * Move an open job's due date. original_due_date is untouched; the
     * warning date and status follow the new date. A job with a running-hours
     * limit has that postponed too: to due_running_hours if given, otherwise
     * by the component's average hours per day for each day added.
     *
     * @param  array  $data  due_date, reason, remark?, due_running_hours?
     */
    public function reschedule(PmsJob $job, array $data): PmsJob
    {
        if (! $job->isActive()) {
            throw ValidationException::withMessages(['job' => 'This job is no longer open.']);
        }

        $errors = [];
        $newDate = ! empty($data['due_date']) ? CarbonImmutable::parse($data['due_date'])->startOfDay() : null;
        if (! $newDate) {
            $errors['due_date'] = 'Enter the new due date.';
        } elseif (! $newDate->gt(CarbonImmutable::today())) {
            $errors['due_date'] = 'The new due date must be in the future.';
        }
        if (! in_array($data['reason'] ?? null, PmsJob::RESCHEDULE_REASONS, true)) {
            $errors['reason'] = 'Select a reason.';
        }

        $newRh = $job->due_running_hours;
        if ($newDate && $job->due_running_hours !== null) {
            $component = $job->component ?? $job->definition->component;
            $given = $data['due_running_hours'] ?? null;

            if ($given !== null && $given !== '') {
                $current = $this->latestReading($component)?->value;
                if (! is_numeric($given) || (float) $given < 0) {
                    $errors['due_running_hours'] = 'Enter the new due running hours.';
                } elseif ($current !== null && (float) $given <= (float) $current) {
                    $errors['due_running_hours'] = 'The new due RH must be above the current meter reading ('.number_format((float) $current, 1).' h).';
                } else {
                    $newRh = round((float) $given, 1);
                }
            } else {
                $perDay = $this->averagePerDay($component);
                if (! $perDay) {
                    $errors['due_running_hours'] = 'Enter the new due running hours - there is no running-hours average to work it out from.';
                } else {
                    // Days added from the old due date (or from today if it had none).
                    $from = $job->due_date ? CarbonImmutable::parse($job->due_date)->startOfDay() : CarbonImmutable::today();
                    $daysAdded = max(0, (int) $from->diffInDays($newDate, false));
                    $newRh = round((float) $job->due_running_hours + $perDay * $daysAdded, 1);
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $job->fill([
            'due_date' => $newDate->toDateString(),
            'due_running_hours' => $newRh,
            'reschedule_reason' => $data['reason'],
            'reschedule_remark' => $this->clean($data['remark'] ?? null),
        ]);

        return $this->refresh($job);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function createJob(PmsJobDefinition $definition): PmsJob
    {
        $job = new PmsJob([
            'vessel_id' => $definition->vessel_id,
            'component_id' => $definition->component_id,
            'job_definition_id' => $definition->id,
            'status' => PmsJob::PLANNED,
        ]);
        $job->setRelation('definition', $definition);
        $job->setRelation('component', $definition->component);
        // The base for a first job with no start/installation date is the
        // day it was created - set before scheduling so it's stable.
        $job->created_at = now();

        $job->fill($this->scheduleFor($job));
        $job->original_due_date = $job->due_date;
        $job->save();

        // The job gets its own copy of the parts, so they can be changed
        // for this job without touching the definition.
        PmsRequiredPart::copyTemplateTo($definition, $job);

        return $job;
    }

    private function createNextJob(PmsJobDefinition $definition): ?PmsJob
    {
        if ($definition->is_single || ! $definition->is_active || $definition->trashed()) {
            return null;
        }
        // Checked against active statuses only, not the full "open" set - the
        // job just completed may still be WaitingApproval, and that's not a
        // reason to withhold its successor.
        if ($definition->jobs()->whereIn('status', PmsJob::ACTIVE_STATUSES)->exists()) {
            return null;
        }

        return $this->createJob($definition);
    }

    /** due_date, due_running_hours, warning_date and status for an open job, from stored facts. */
    private function scheduleFor(PmsJob $job): array
    {
        $definition = $job->definition;
        $definition->loadMissing('intervals');
        $component = $job->component ?? $definition->component;
        $meter = $this->meter($component);
        $today = CarbonImmutable::today();

        if ($definition->is_single) {
            $dueRh = $definition->due_type === 'RunningHours' ? (float) $definition->due_running_hours : null;
            $calendarDue = $definition->due_type === 'Date' && $definition->due_date
                ? CarbonImmutable::parse($definition->due_date) : null;
            $dueDate = $this->earliest($calendarDue, $dueRh !== null ? $this->estimateDateForRh($dueRh, $meter) : null);
        } else {
            [$baseDate, $baseRh] = $this->baseFor($job, $definition, $component);
            $due = $this->dueFromIntervals($definition->intervals, $baseDate, $baseRh, $meter);
            $dueDate = $due['due_date'];
            $dueRh = $due['due_running_hours'];
        }

        // A rescheduled date - and the running-hours limit that was moved
        // with it - is the officer's decision: it stays put.
        $rescheduled = (bool) $job->reschedule_reason;
        if ($rescheduled && $job->due_date) {
            $dueDate = CarbonImmutable::parse($job->due_date);
        }
        if ($rescheduled && $job->due_running_hours !== null) {
            $dueRh = (float) $job->due_running_hours;
        }

        $warningDate = $this->warningDate($dueDate, $dueRh, (int) $definition->warning_period, $definition->warning_unit, $meter);

        return [
            'due_date' => $dueDate?->toDateString(),
            'due_running_hours' => $dueRh,
            'warning_date' => $warningDate?->toDateString(),
            // Until a rescheduled job's new date arrives, the meter passing
            // its (postponed) RH limit doesn't make it overdue.
            'status' => $this->statusFor($dueDate, $warningDate, $dueRh, $meter['value'] ?? null,
                (int) $definition->warning_period, $definition->warning_unit, $today,
                ! ($rescheduled && $dueDate && $today->lte($dueDate))),
        ];
    }

    /** [base date, base RH] a recurring job counts its intervals from - see the class doc. */
    private function baseFor(PmsJob $job, PmsJobDefinition $definition, PmsComponent $component): array
    {
        // Completed or still WaitingApproval - either way it has a real
        // completed_at/rh_at_completion to count the next job from.
        $previous = PmsJob::where('job_definition_id', $definition->id)
            ->whereIn('status', [PmsJob::COMPLETED, PmsJob::WAITING_APPROVAL])
            ->when($job->exists, fn ($q) => $q->where('id', '!=', $job->id))
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->first();

        if ($previous) {
            $baseDate = CarbonImmutable::parse($previous->completed_at);
            $baseRh = $previous->rh_at_completion !== null
                ? (float) $previous->rh_at_completion
                : $this->readingAsOf($component, $baseDate);

            return [$baseDate, $baseRh];
        }

        $baseDate = CarbonImmutable::parse(
            $definition->start_date ?? $component->installation_date ?? $job->created_at ?? now()
        )->startOfDay();
        $createdOn = CarbonImmutable::parse($job->created_at ?? now())->startOfDay();

        return [$baseDate, $this->readingAsOf($component, $createdOn)];
    }

    /**
     * Latest reading on or before today, plus the average hours per day over
     * the last AVERAGE_WINDOW_DAYS. Null for a component with no counter or
     * no readings.
     */
    public function meter(PmsComponent $component): ?array
    {
        if (! $component->has_counter) {
            return null;
        }

        $latest = $this->latestReading($component);
        if (! $latest) {
            return null;
        }

        return [
            'value' => (float) $latest->value,
            'date' => CarbonImmutable::parse($latest->reading_date),
            'per_day' => $this->averagePerDay($component),
        ];
    }

    /** Average running hours per day over the readings of the last 90 days; null with fewer than two. */
    public function averagePerDay(PmsComponent $component): ?float
    {
        return $this->averagesPerDay([$component->id])[$component->id] ?? null;
    }

    /**
     * averagePerDay() for many components in one query: component id =>
     * hours per day (null with fewer than two readings in the window).
     *
     * @param  array<string>  $componentIds
     */
    public function averagesPerDay(array $componentIds): array
    {
        if (! $componentIds) {
            return [];
        }

        $today = CarbonImmutable::today();
        $byComponent = PmsCounterReading::whereIn('component_id', $componentIds)
            ->where('reading_date', '>=', $today->subDays(self::AVERAGE_WINDOW_DAYS)->toDateString())
            ->where('reading_date', '<', $this->dayAfter($today))
            ->orderBy('reading_date')
            ->get(['component_id', 'reading_date', 'value'])
            ->groupBy('component_id');

        $averages = array_fill_keys($componentIds, null);
        foreach ($byComponent as $componentId => $readings) {
            if ($readings->count() < 2) {
                continue;
            }
            $first = $readings->first();
            $last = $readings->last();
            $days = CarbonImmutable::parse($first->reading_date)->diffInDays(CarbonImmutable::parse($last->reading_date), true);
            $averages[$componentId] = $days > 0 ? ((float) $last->value - (float) $first->value) / $days : null;
        }

        return $averages;
    }

    private function latestReading(PmsComponent $component): ?PmsCounterReading
    {
        return PmsCounterReading::where('component_id', $component->id)
            ->where('reading_date', '<', $this->dayAfter(CarbonImmutable::today()))
            ->orderByDesc('reading_date')
            ->first();
    }

    private function readingAsOf(PmsComponent $component, CarbonInterface $date): ?float
    {
        if (! $component->has_counter) {
            return null;
        }

        $value = PmsCounterReading::where('component_id', $component->id)
            ->where('reading_date', '<', $this->dayAfter($date))
            ->orderByDesc('reading_date')
            ->value('value');

        return $value !== null ? (float) $value : null;
    }

    /**
     * "On or before $date" is written as "< the day after": a date column can
     * come back as '2026-01-10 00:00:00' (SQLite stores the full string), and
     * that doesn't compare <= '2026-01-10'. Same result on MySQL's DATE
     * columns, and unlike whereDate() it can still use an index.
     */
    private function dayAfter(CarbonInterface $date): string
    {
        return CarbonImmutable::parse($date)->startOfDay()->addDay()->toDateString();
    }

    private function addPeriod(CarbonImmutable $date, int $every, string $period): CarbonImmutable
    {
        // NoOverflow: 31 Jan + 1 month is 28/29 Feb, not 2/3 March.
        return match ($period) {
            'Day' => $date->addDays($every),
            'Week' => $date->addWeeks($every),
            'Month' => $date->addMonthsNoOverflow($every),
            'Year' => $date->addYearsNoOverflow($every),
        };
    }

    private function earliest(?CarbonImmutable $a, ?CarbonImmutable $b): ?CarbonImmutable
    {
        if (! $a || ! $b) {
            return $a ?? $b;
        }

        return $a->lte($b) ? $a : $b;
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
