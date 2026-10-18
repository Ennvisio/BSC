<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One completed stage of the SSM procurement workflow. See ProcurementStage
 * for the sequence these belong to.
 */
class ProcurementStep extends Model
{
    protected $table = 'order_procurement_steps';

    protected $fillable = [
        'order_id', 'step', 'outcome', 'completed_by', 'completed_at', 'remarks', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'completed_at' => 'datetime',
    ];

    const OUTCOME_DONE = 'done';
    const OUTCOME_SKIPPED = 'skipped';

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /** Whoever performed it - the Master on Receipt & Verification, the assigned SSM officer everywhere else. */
    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** Tender docs, PO copy, acknowledgement receipt, invoice - by stage. */
    public function attachments()
    {
        return $this->belongsToMany(Attachment::class, 'attachment_order_step', 'order_procurement_step_id', 'attachment_id')
            ->withTimestamps()
            ->orderBy('attachment_order_step.created_at');
    }

    /**
     * Links files that were uploaded while the stage was being worked on.
     *
     * The step row only exists once the stage completes, so documents are
     * uploaded to the user's library first and their ids ride along as hidden
     * inputs until then - the same approach the requisition wizard uses for
     * rows that aren't saved yet. Restricted to the caller's own uploads, so
     * editing the form fields can't link somebody else's file.
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
        return ProcurementStage::label($this->step);
    }

    public function wasSkipped(): bool
    {
        return $this->outcome === self::OUTCOME_SKIPPED;
    }
}
