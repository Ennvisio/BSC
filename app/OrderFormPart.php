<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One saved part of a requisition's approval form. What the questions ARE is
 * in RequisitionForm; this is what was answered.
 */
class OrderFormPart extends Model
{
    protected $fillable = [
        'order_id', 'part', 'form_version', 'answers', 'completed_at', 'declaration', 'filled_by',
    ];

    protected $casts = [
        'answers' => 'array',
        'declaration' => 'array',
        'completed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function filledBy()
    {
        return $this->belongsTo(User::class, 'filled_by');
    }

    /** Every required answer and the declaration are in. */
    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    /** The answer set for one question key, or an empty one. */
    public function answerFor(string $key): array
    {
        $answer = $this->answers[$key] ?? ['choice' => null, 'followup' => null, 'fields' => []];

        // Questions 1, 2, 3 and 8 used to accept several choices and were
        // stored as arrays. They're single-choice now, so anything saved
        // under the old shape is read back as its first choice - otherwise a
        // draft answered before the change comes back looking unanswered.
        if (is_array($answer['choice'] ?? null)) {
            $answer['choice'] = $answer['choice'][0] ?? null;
        }

        return $answer;
    }
}
