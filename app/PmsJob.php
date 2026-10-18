<?php

namespace App;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A PMS work order generated from a definition. Due date, warning date and
 * status are maintained by App\Services\PmsJobScheduler - never set them
 * directly, or the next refresh will disagree with you.
 */
class PmsJob extends Model
{
    use HasUuids;

    const PLANNED = 'Planned';
    const DUE_SOON = 'DueSoon';
    const OVERDUE = 'Overdue';
    const WAITING_APPROVAL = 'WaitingApproval';
    const COMPLETED = 'Completed';
    const CANCELLED = 'Cancelled';

    /** Still to be done - a definition has at most one job in one of these. */
    const OPEN_STATUSES = [self::PLANNED, self::DUE_SOON, self::OVERDUE, self::WAITING_APPROVAL];

    /** Open and not yet done - the statuses the scheduler recalculates. */
    const ACTIVE_STATUSES = [self::PLANNED, self::DUE_SOON, self::OVERDUE];

    const RESCHEDULE_REASONS = ['Dock', 'Spares not available', 'Vessel trading', 'Other'];

    protected $fillable = [
        'cfm_guid', 'vessel_id', 'component_id', 'job_definition_id', 'due_date', 'original_due_date', 'due_running_hours',
        'warning_date', 'status', 'completed_at', 'done_by_rank_id', 'rh_at_completion', 'condition_after_id',
        'remark', 'overdue_reason', 'overdue_remark', 'reschedule_reason', 'reschedule_remark',
        'completed_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'original_due_date' => 'date',
        'due_running_hours' => 'decimal:1',
        'warning_date' => 'date',
        'completed_at' => 'date',
        'rh_at_completion' => 'decimal:1',
        'approved_at' => 'datetime',
    ];

    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    public function component()
    {
        return $this->belongsTo(PmsComponent::class, 'component_id');
    }

    public function definition()
    {
        return $this->belongsTo(PmsJobDefinition::class, 'job_definition_id')->withTrashed();
    }

    public function doneByRank()
    {
        return $this->belongsTo(Rank::class, 'done_by_rank_id');
    }

    public function conditionAfter()
    {
        return $this->belongsTo(PmsCondition::class, 'condition_after_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function requiredParts()
    {
        return $this->hasMany(PmsRequiredPart::class, 'job_id')->orderBy('id');
    }

    public function usedParts()
    {
        return $this->hasMany(PmsUsedPart::class, 'job_id')->orderBy('id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }
}
