<?php

namespace App;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The template a PMS job is generated from: what to do, who does it, and how
 * often (its intervals) - or, for a single job, one fixed due date/RH.
 * See App\Services\PmsJobScheduler for how jobs come out of it.
 */
class PmsJobDefinition extends Model
{
    use HasUuids, SoftDeletes;

    const PERFORMED_BY = ['Crew', 'External', 'ClassSurveyor'];
    const PERFORM_WHEN = ['Anytime', 'AtSea', 'InPort', 'Docking'];
    const DUE_TYPES = ['Date', 'RunningHours'];
    const WARNING_UNITS = ['Days', 'Weeks', 'Months', 'RunningHours'];

    protected $fillable = [
        'cfm_guid', 'vessel_id', 'component_id', 'name', 'job_type_id', 'rank_id', 'performed_by', 'perform_when',
        'is_single', 'due_type', 'due_date', 'due_running_hours', 'start_date', 'warning_period', 'warning_unit',
        'internal_code', 'class_code', 'staff_needed', 'hours_needed', 'minutes_needed', 'description',
        'requires_approval', 'is_critical', 'is_class_relevant', 'is_safety_relevant', 'is_active',
    ];

    protected $casts = [
        'is_single' => 'boolean',
        'due_date' => 'date',
        'due_running_hours' => 'decimal:1',
        'start_date' => 'date',
        'requires_approval' => 'boolean',
        'is_critical' => 'boolean',
        'is_class_relevant' => 'boolean',
        'is_safety_relevant' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function component()
    {
        return $this->belongsTo(PmsComponent::class, 'component_id');
    }

    public function jobType()
    {
        return $this->belongsTo(PmsJobType::class, 'job_type_id');
    }

    public function rank()
    {
        return $this->belongsTo(Rank::class);
    }

    public function intervals()
    {
        return $this->hasMany(PmsJobInterval::class, 'job_definition_id')->orderBy('id');
    }

    public function checklistItems()
    {
        return $this->hasMany(PmsJobChecklistItem::class, 'job_definition_id')->orderBy('position');
    }

    /** The template parts copied onto each job that's generated. */
    public function requiredParts()
    {
        return $this->hasMany(PmsRequiredPart::class, 'job_definition_id')->orderBy('id');
    }

    public function jobs()
    {
        return $this->hasMany(PmsJob::class, 'job_definition_id');
    }

    /** The one job still to be done, if any (see PmsJob::OPEN_STATUSES). */
    public function openJob()
    {
        return $this->hasOne(PmsJob::class, 'job_definition_id')->whereIn('status', PmsJob::OPEN_STATUSES);
    }

    /** Is any part of when this job falls due measured in running hours? */
    public function usesRunningHours(): bool
    {
        if ($this->is_single) {
            return $this->due_type === 'RunningHours';
        }

        return $this->intervals->contains(fn ($i) => $i->period === PmsJobInterval::RUNNING_HOUR);
    }
}
