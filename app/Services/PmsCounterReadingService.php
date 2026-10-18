<?php

namespace App\Services;

use App\PmsComponent;
use App\PmsCounterReading;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recording running-hour meter readings - one at a time from a component's
 * Running Hours page, or many at once from the Counters page - under one set
 * of rules. Each reading is the total on the meter that day, so the series
 * can only go up, and only by what a day can hold.
 */
class PmsCounterReadingService
{
    const MAX_VALUE = 99999999999.9;

    public function __construct(private PmsJobScheduler $scheduler)
    {
    }

    /**
     * Record one reading and refresh the component's jobs.
     *
     * @throws ValidationException  'reading_date' or 'value'
     */
    public function record(PmsComponent $component, $date, $value, int $userId): PmsCounterReading
    {
        $reading = DB::transaction(fn () => $this->create($component, $date, $value, $userId));
        $this->scheduler->refreshComponent($component);

        return $reading;
    }

    /**
     * Record several readings on one vessel. Each row stands alone: a bad one
     * is reported and skipped, the good ones are saved. Rows with no value
     * are ignored. Jobs are refreshed once per component that got a reading.
     *
     * @param  array<string,array{reading_date?:mixed,value?:mixed}>  $rows  component id => row
     * @return array{saved: array<string,PmsCounterReading>, errors: array<string,string>}
     */
    public function recordMany(int $vesselId, array $rows, int $userId): array
    {
        $rows = array_filter($rows, fn ($row) => trim((string) ($row['value'] ?? '')) !== '');
        $components = PmsComponent::where('vessel_id', $vesselId)->whereIn('id', array_keys($rows))->get()->keyBy('id');

        $saved = [];
        $errors = [];
        foreach ($rows as $componentId => $row) {
            $component = $components->get($componentId);
            if (! $component) {
                $errors[$componentId] = 'This component isn\'t on the vessel.';

                continue;
            }

            try {
                $saved[$componentId] = DB::transaction(fn () => $this->create($component, $row['reading_date'] ?? null, $row['value'], $userId));
            } catch (ValidationException $e) {
                $errors[$componentId] = collect($e->errors())->flatten()->first();
            }
        }

        foreach (array_keys($saved) as $componentId) {
            $this->scheduler->refreshComponent($components->get($componentId));
        }

        return ['saved' => $saved, 'errors' => $errors];
    }

    private function create(PmsComponent $component, $date, $value, int $userId): PmsCounterReading
    {
        if (! $component->has_counter) {
            $this->fail('value', 'This component doesn\'t have a running-hour counter.');
        }

        if ($date === null || trim((string) $date) === '') {
            $this->fail('reading_date', 'Enter the reading date.');
        }
        try {
            $day = Carbon::parse($date)->startOfDay();
        } catch (\Throwable) {
            $this->fail('reading_date', 'The reading date isn\'t a valid date.');
        }
        if ($day->gt(Carbon::today())) {
            $this->fail('reading_date', 'A reading can\'t be dated in the future.');
        }
        if (! is_numeric($value) || (float) $value < 0 || (float) $value > self::MAX_VALUE) {
            $this->fail('value', 'Enter the meter reading in hours.');
        }

        $date = $day->toDateString();
        $value = round((float) $value, 1);

        // "On the same day" as a range, since a date column can come back
        // with a time part on some drivers.
        $nextDay = $day->copy()->addDay()->toDateString();
        $sameDay = PmsCounterReading::where('component_id', $component->id)
            ->where('reading_date', '>=', $date)->where('reading_date', '<', $nextDay)->lockForUpdate()->exists();
        if ($sameDay) {
            $this->fail('reading_date', 'There\'s already a reading for that date - delete it first to correct it.');
        }

        $before = PmsCounterReading::where('component_id', $component->id)
            ->where('reading_date', '<', $date)->orderByDesc('reading_date')->first();
        $after = PmsCounterReading::where('component_id', $component->id)
            ->where('reading_date', '>=', $nextDay)->orderBy('reading_date')->first();

        if ($before && $value < (float) $before->value) {
            $this->fail('value', 'The meter can\'t go backwards: '.$before->reading_date->format('d M Y').' already reads '
                .number_format((float) $before->value, 1).' h.');
        }
        // A day only has 24 hours - more means a typo (an extra digit, or
        // another component's meter).
        if ($before) {
            $days = (int) round($before->reading_date->copy()->startOfDay()->diffInDays($day, true));
            if ($days > 0 && ($value - (float) $before->value) / $days > 24) {
                $this->fail('value', 'More than 24 running hours per day since the previous reading.');
            }
        }
        if ($after && $value > (float) $after->value) {
            $this->fail('value', 'That\'s higher than the later reading of '.number_format((float) $after->value, 1)
                .' h on '.$after->reading_date->format('d M Y').'.');
        }

        return PmsCounterReading::create([
            'component_id' => $component->id,
            'reading_date' => $date,
            'value' => $value,
            'entered_by' => $userId,
        ]);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
