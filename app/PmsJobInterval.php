<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** "Every 3 Months" / "Every 1000 RunningHour" - a recurring definition has one or more. */
class PmsJobInterval extends Model
{
    const PERIODS = ['Day', 'Week', 'Month', 'Year', 'RunningHour'];
    const RUNNING_HOUR = 'RunningHour';

    protected $fillable = ['job_definition_id', 'every', 'period'];

    protected $casts = ['every' => 'integer'];

    public function definition()
    {
        return $this->belongsTo(PmsJobDefinition::class, 'job_definition_id');
    }

    public function isRunningHours(): bool
    {
        return $this->period === self::RUNNING_HOUR;
    }

    public function label(): string
    {
        $unit = $this->isRunningHours() ? 'Running Hour' : $this->period;

        return 'Every '.$this->every.' '.($this->every === 1 ? $unit : $unit.'s');
    }
}
