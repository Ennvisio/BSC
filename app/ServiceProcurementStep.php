<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One completed stage of a service requisition's procurement workflow. See
 * ServiceProcurementStage for the sequence these belong to.
 */
class ServiceProcurementStep extends Model
{
    protected $table = 'service_requisition_procurement_steps';

    protected $fillable = [
        'service_requisition_id', 'step', 'outcome', 'completed_by', 'completed_at', 'remarks', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'completed_at' => 'datetime',
    ];

    const OUTCOME_DONE = 'done';
    const OUTCOME_SKIPPED = 'skipped';

    public function serviceRequisition()
    {
        return $this->belongsTo(ServiceRequisition::class);
    }

    /** Master/Chief Engineer on Receipt & Verification, the assigned SRD officer everywhere else. */
    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function attachments()
    {
        return $this->belongsToMany(Attachment::class, 'attachment_service_req_step', 'step_id', 'attachment_id')
            ->withTimestamps()
            ->orderBy('attachment_service_req_step.created_at');
    }

    /**
     * Links files uploaded while the stage was being worked on.
     *
     * The step row only exists once the stage completes, so documents go to
     * the user's own library first and their ids ride along as hidden inputs
     * until then. Restricted to the caller's own uploads, so editing the form
     * fields can't link somebody else's file.
     */
    public function syncOwnedAttachments(array $attachmentIds, int $userId): void
    {
        if ($attachmentIds === []) {
            return;
        }

        $owned = Attachment::where('uploaded_by', $userId)
            ->whereIn('id', array_map('intval', $attachmentIds))
            ->pluck('id')
            ->all();

        $this->attachments()->sync($owned);
    }

    public function label(): string
    {
        return ServiceProcurementStage::label($this->step);
    }

    public function wasSkipped(): bool
    {
        return $this->outcome === self::OUTCOME_SKIPPED;
    }
}
